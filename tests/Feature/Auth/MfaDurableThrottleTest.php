<?php

namespace Tests\Feature\Auth;

use App\Models\AuditEvent;
use App\Models\MfaAttemptState;
use App\Models\MfaEnrollment;
use App\Models\User;
use App\Services\TotpService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use PragmaRX\Google2FA\Google2FA;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MfaDurableThrottleTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_progressive_cooldown_survives_cache_clear_relogin_ip_change_and_multiple_windows(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $user = $this->activeMfaUser('duravel-janelas@exemplo-ficticio.local', $secret);
        $factor = MfaEnrollment::query()->whereBelongsTo($user)->where('state', 'active')->sole();
        $invalidCode = $this->invalidCode($secret);

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->post(route('login'), ['email' => $user->email, 'password' => 'password']);

        for ($attempt = 1; $attempt < 5; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
                ->post(route('mfa.challenge.verify'), ['code' => $invalidCode])
                ->assertSessionHasErrors('code');
        }

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->post(route('mfa.challenge.verify'), ['code' => $invalidCode])
            ->assertTooManyRequests()
            ->assertHeader('Retry-After', '300')
            ->assertHeader('X-Mfa-Session-Expired', '1');

        $this->assertGuest();
        $state = MfaAttemptState::query()->whereBelongsTo($user)->sole();
        $this->assertSame(5, $state->consecutive_failures);
        $this->assertSame(1, $state->cooldown_level);

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
            ->post(route('login'), ['email' => $user->email, 'password' => 'password']);
        $activeCooldown = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
            ->post(route('mfa.challenge.verify'), [
                'code' => app(Google2FA::class)->getCurrentOtp($secret),
            ])
            ->assertTooManyRequests();
        $this->assertContains((int) $activeCooldown->headers->get('Retry-After'), [299, 300]);
        $this->assertSame(5, $state->fresh()->consecutive_failures);
        $this->assertNull($factor->fresh()->last_accepted_time_step);

        Cache::flush();

        for ($failure = 6; $failure <= 10; $failure++) {
            $this->travel(301)->seconds();
            $ip = '198.51.100.'.($failure + 10);
            $this->withServerVariables(['REMOTE_ADDR' => $ip])
                ->post(route('login'), ['email' => $user->email, 'password' => 'password'])
                ->assertRedirect(route('mfa.challenge'));

            $response = $this->withServerVariables(['REMOTE_ADDR' => $ip])
                ->post(route('mfa.challenge.verify'), ['code' => $invalidCode])
                ->assertTooManyRequests();

            if ($failure === 10) {
                $response->assertHeader('Retry-After', '900');
            }
        }

        $state->refresh();
        $this->assertSame(10, $state->consecutive_failures);
        $this->assertSame(2, $state->cooldown_level);
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $user->getKey(),
            'action' => 'auth.mfa_cooldown_started',
            'result' => 'denied',
        ]);
    }

    public function test_active_cooldown_returns_a_generic_denial_without_consuming_the_factor_or_mutating_strikes(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $user = $this->activeMfaUser('cooldown-ativo@exemplo-ficticio.local', $secret);
        $factor = MfaEnrollment::query()->whereBelongsTo($user)->where('state', 'active')->sole();
        $state = MfaAttemptState::query()->create([
            'user_id' => $user->getKey(),
            'consecutive_failures' => 7,
            'cooldown_level' => 1,
            'blocked_until' => now('UTC')->addMinutes(5),
            'last_failed_at' => now('UTC'),
        ]);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);
        $code = app(Google2FA::class)->getCurrentOtp($secret);
        $response = $this->post(route('mfa.challenge.verify'), ['code' => $code])
            ->assertTooManyRequests()
            ->assertHeader('X-Mfa-Session-Expired', '1');

        $this->assertGuest();
        $this->assertStringNotContainsString($secret, $response->getContent());
        $this->assertStringNotContainsString($code, $response->getContent());
        $this->assertStringNotContainsString($user->email, $response->getContent());
        $this->assertSame(7, $state->fresh()->consecutive_failures);
        $this->assertNull($factor->fresh()->last_accepted_time_step);
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $user->getKey(),
            'action' => 'auth.mfa_cooldown_active',
            'result' => 'denied',
        ]);
    }

    public function test_successful_second_factor_is_the_only_flow_that_resets_consecutive_strikes(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $user = $this->activeMfaUser('reset-strikes@exemplo-ficticio.local', $secret);
        $state = MfaAttemptState::query()->create([
            'user_id' => $user->getKey(),
            'consecutive_failures' => 11,
            'cooldown_level' => 2,
            'blocked_until' => now('UTC')->subSecond(),
            'last_failed_at' => now('UTC')->subMinutes(16),
        ]);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);
        $this->post(route('mfa.challenge.verify'), [
            'code' => app(Google2FA::class)->getCurrentOtp($secret),
        ])->assertRedirect(route('dashboard'));

        $state->refresh();
        $this->assertSame(0, $state->consecutive_failures);
        $this->assertSame(0, $state->cooldown_level);
        $this->assertNull($state->blocked_until);
        $this->assertNull($state->last_failed_at);
    }

    public function test_password_reset_does_not_clear_mfa_strikes(): void
    {
        Notification::fake();
        $user = $this->activeMfaUser('senha-nao-reseta-strikes@exemplo-ficticio.local', 'JBSWY3DPEHPK3PXP');
        $state = MfaAttemptState::query()->create([
            'user_id' => $user->getKey(),
            'consecutive_failures' => 8,
            'cooldown_level' => 1,
            'blocked_until' => now('UTC')->addMinutes(5),
            'last_failed_at' => now('UTC'),
        ]);

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            $this->post(route('password.store'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'senha-nova-ficticia-segura',
                'password_confirmation' => 'senha-nova-ficticia-segura',
            ])->assertSessionHasNoErrors();

            return true;
        });

        $state->refresh();
        $this->assertSame(8, $state->consecutive_failures);
        $this->assertSame(1, $state->cooldown_level);
        $this->assertTrue($state->blocked_until->isFuture());
    }

    public function test_progressive_cooldown_is_bounded_at_one_hour_and_emits_a_redacted_operational_signal(): void
    {
        Log::spy();
        $secret = 'JBSWY3DPEHPK3PXP';
        $user = $this->activeMfaUser('cooldown-limitado@exemplo-ficticio.local', $secret);
        $state = MfaAttemptState::query()->create([
            'user_id' => $user->getKey(),
            'consecutive_failures' => 14,
            'cooldown_level' => 2,
            'blocked_until' => now('UTC')->subSecond(),
            'last_failed_at' => now('UTC')->subHour(),
        ]);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);
        $this->post(route('mfa.challenge.verify'), ['code' => $this->invalidCode($secret)])
            ->assertTooManyRequests()
            ->assertHeader('Retry-After', '3600');

        $state->refresh();
        $this->assertSame(15, $state->consecutive_failures);
        $this->assertSame(3, $state->cooldown_level);
        Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context) use ($secret, $user): bool {
            $serialized = json_encode([$message, $context], JSON_THROW_ON_ERROR);

            return $context === [
                'security_event' => 'auth.mfa_cooldown_started',
                'verification_flow' => 'challenge',
                'cooldown_level' => 3,
                'retry_after_seconds' => 3600,
            ]
                && ! str_contains($serialized, $secret)
                && ! str_contains($serialized, $user->email);
        });
    }

    public function test_concurrent_fifth_attempts_are_serialized_and_consume_one_strike(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $user = $this->activeMfaUser('concorrencia-strikes@exemplo-ficticio.local', $secret);
        MfaAttemptState::query()->create([
            'user_id' => $user->getKey(),
            'consecutive_failures' => 4,
            'cooldown_level' => 0,
            'last_failed_at' => now('UTC'),
        ]);

        $startAt = microtime(true) + 0.75;
        $invalidCode = $this->invalidCode($secret);
        $processes = [
            $this->newChallengeProcess($user, $invalidCode, $startAt),
            $this->newChallengeProcess($user, $invalidCode, $startAt),
        ];

        foreach ($processes as $process) {
            $process->start();
        }

        foreach ($processes as $process) {
            $process->wait();
            $this->assertSame(2, $process->getExitCode(), $process->getErrorOutput());
        }

        $state = MfaAttemptState::query()->whereBelongsTo($user)->sole();
        $this->assertSame(5, $state->consecutive_failures);
        $this->assertSame(1, $state->cooldown_level);
        $this->assertSame(1, AuditEvent::query()->where('action', 'auth.mfa_cooldown_started')->count());
        $this->assertSame(1, AuditEvent::query()->where('action', 'auth.mfa_cooldown_active')->count());
    }

    private function activeMfaUser(string $email, string $secret): User
    {
        $user = User::factory()->create(['email' => $email]);
        MfaEnrollment::query()->create([
            'user_id' => $user->getKey(),
            'version' => 1,
            'state' => 'active',
            'secret' => $secret,
            'confirmed_at' => now('UTC'),
        ]);

        return $user;
    }

    private function invalidCode(string $secret): string
    {
        for ($candidate = 0; $candidate <= 999999; $candidate++) {
            $code = str_pad((string) $candidate, 6, '0', STR_PAD_LEFT);

            if (app(TotpService::class)->verifyNewer($secret, $code, null) === false) {
                return $code;
            }
        }

        throw new \RuntimeException('Não foi possível produzir um código TOTP sintético inválido.');
    }

    private function newChallengeProcess(User $user, string $code, float $startAt): Process
    {
        $script = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
App\Support\DestructiveTestDatabaseGuard::assertProcessEnvironmentIsSafe(
    App\Support\DestructiveTestDatabaseGuard::processEnvironment(),
);
while (microtime(true) < (float) $argv[3]) {
    usleep(1000);
}
$user = App\Models\User::query()->findOrFail((int) $argv[1]);
try {
    app(App\Actions\VerifyMfaChallenge::class)->handle($user, $argv[2]);
    exit(0);
} catch (App\Exceptions\MfaCooldownException) {
    exit(2);
} catch (Illuminate\Validation\ValidationException) {
    exit(3);
} catch (Throwable $exception) {
    fwrite(STDERR, get_class($exception).': '.$exception->getMessage());
    exit(1);
}
PHP;

        return (new Process(
            [PHP_BINARY, '-r', $script, '--', (string) $user->getKey(), $code, (string) $startAt],
            base_path(),
            $this->childEnvironment(),
        ))->setTimeout(20);
    }

    /** @return array<string, string> */
    private function childEnvironment(): array
    {
        return [
            'APP_ENV' => 'testing',
            'APP_DEBUG' => 'false',
            'APP_KEY' => (string) config('app.key'),
            'CACHE_STORE' => 'array',
            'DB_CONNECTION' => 'pgsql',
            'DB_URL' => '',
            'DB_HOST' => (string) config('database.connections.pgsql.host'),
            'DB_PORT' => (string) config('database.connections.pgsql.port'),
            'DB_DATABASE' => 'centro_acolhimento_test',
            'DB_USERNAME' => (string) config('database.connections.pgsql.username'),
            'DB_PASSWORD' => (string) config('database.connections.pgsql.password'),
            'DB_SSLMODE' => (string) config('database.connections.pgsql.sslmode'),
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'array',
        ];
    }
}
