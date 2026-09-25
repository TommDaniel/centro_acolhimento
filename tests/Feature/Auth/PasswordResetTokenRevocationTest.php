<?php

namespace Tests\Feature\Auth;

use App\Actions\ConfirmMfaEnrollment;
use App\Actions\EstablishPasswordOnlyLogin;
use App\Actions\UpdateUserAccount;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\MfaEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use PragmaRX\Google2FA\Google2FA;
use RuntimeException;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PasswordResetTokenRevocationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_legacy_account_transition_to_pending_mfa_revokes_an_older_reset_link(): void
    {
        $user = User::factory()->create([
            'email' => 'reset-login-legado-ficticio@exemplo.local',
            'status' => UserStatus::Ativa,
        ]);
        $token = Password::createToken($user);
        $generation = $user->access_generation;

        app(EstablishPasswordOnlyLogin::class)->handle(
            $user,
            $generation,
            $user->getAuthPassword(),
            'password',
        );

        $user->refresh();
        $this->assertSame(UserStatus::PendenteMfa, $user->status);
        $this->assertSame($generation + 1, $user->access_generation);
        $this->assertFalse(Password::broker()->tokenExists($user, $token));
    }

    public function test_mfa_confirmation_revokes_an_older_reset_link(): void
    {
        [$user, $factor, $secret] = $this->pendingMfaUser('reset-confirmacao-ficticio@exemplo.local');
        $token = Password::createToken($user);
        $generation = $user->access_generation;

        app(ConfirmMfaEnrollment::class)->handle(
            $user,
            $factor->getKey(),
            app(Google2FA::class)->getCurrentOtp($secret),
            $generation,
            'password_only',
        );

        $user->refresh();
        $this->assertSame(UserStatus::Ativa, $user->status);
        $this->assertSame($generation + 1, $user->access_generation);
        $this->assertSame('active', $factor->fresh()->state);
        $this->assertFalse(Password::broker()->tokenExists($user, $token));
    }

    public function test_legacy_transition_rollback_restores_account_and_reset_link(): void
    {
        $user = User::factory()->create([
            'email' => 'reset-login-rollback-ficticio@exemplo.local',
            'status' => UserStatus::Ativa,
        ]);
        $token = Password::createToken($user);
        $generation = $user->access_generation;

        try {
            DB::transaction(function () use ($user, $generation): void {
                app(EstablishPasswordOnlyLogin::class)->handle(
                    $user,
                    $generation,
                    $user->getAuthPassword(),
                    'password',
                );

                throw new RuntimeException('synthetic rollback');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('synthetic rollback', $exception->getMessage());
        }

        $user->refresh();
        $this->assertSame(UserStatus::Ativa, $user->status);
        $this->assertSame($generation, $user->access_generation);
        $this->assertTrue(Password::broker()->tokenExists($user, $token));
    }

    public function test_mfa_confirmation_rollback_restores_factor_account_and_reset_link(): void
    {
        [$user, $factor, $secret] = $this->pendingMfaUser('reset-confirmacao-rollback-ficticio@exemplo.local');
        $token = Password::createToken($user);
        $generation = $user->access_generation;

        try {
            DB::transaction(function () use ($user, $factor, $secret, $generation): void {
                app(ConfirmMfaEnrollment::class)->handle(
                    $user,
                    $factor->getKey(),
                    app(Google2FA::class)->getCurrentOtp($secret),
                    $generation,
                    'password_only',
                );

                throw new RuntimeException('synthetic rollback');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('synthetic rollback', $exception->getMessage());
        }

        $user->refresh();
        $this->assertSame(UserStatus::PendenteMfa, $user->status);
        $this->assertSame($generation, $user->access_generation);
        $this->assertSame('pending', $factor->fresh()->state);
        $this->assertTrue(Password::broker()->tokenExists($user, $token));
    }

    public function test_legacy_transition_and_concurrent_reset_have_only_one_valid_outcome(): void
    {
        $user = User::factory()->create([
            'email' => 'reset-login-corrida-ficticio@exemplo.local',
            'status' => UserStatus::Ativa,
        ]);

        $this->assertTransitionWinsConcurrentReset('login', $user, Password::createToken($user));

        $this->assertSame(UserStatus::PendenteMfa, $user->fresh()->status);
    }

    public function test_mfa_confirmation_and_concurrent_reset_have_only_one_valid_outcome(): void
    {
        [$user, $factor] = $this->pendingMfaUser('reset-confirmacao-corrida-ficticio@exemplo.local');

        $this->assertTransitionWinsConcurrentReset(
            'confirm',
            $user,
            Password::createToken($user),
            $factor,
        );

        $this->assertSame(UserStatus::Ativa, $user->fresh()->status);
        $this->assertSame('active', $factor->fresh()->state);
    }

    public function test_self_service_password_change_revokes_an_older_reset_link(): void
    {
        $user = $this->activeMfaUser('reset-self-ficticio@exemplo.local');
        $token = Password::createToken($user);

        $this->actingAsWithVerifiedMfa($user)
            ->put(route('password.update'), [
                'current_password' => 'password',
                'password' => 'senha-self-nova-ficticia',
                'password_confirmation' => 'senha-self-nova-ficticia',
            ])
            ->assertSessionHasNoErrors();

        $this->assertFalse(Password::broker()->tokenExists($user->fresh(), $token));
    }

    public function test_access_revocation_and_email_change_invalidate_pending_reset_links(): void
    {
        $administrator = User::factory()->administrator()->create([
            'email' => 'admin-revogacao-ficticio@exemplo.local',
        ]);
        $subject = $this->activeMfaUser('reset-revogacao-ficticio@exemplo.local');
        $oldEmailToken = Password::createToken($subject);

        app(UpdateUserAccount::class)->handle($subject, [
            'name' => $subject->name,
            'email' => 'reset-revogacao-novo-ficticio@exemplo.local',
            'role' => UserRole::EquipeTecnica->value,
            'status' => UserStatus::Inativa->value,
        ], $administrator);

        $this->assertFalse(Password::broker()->tokenExists($subject->fresh(), $oldEmailToken));
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'reset-revogacao-ficticio@exemplo.local',
        ]);
    }

    public function test_token_validated_before_a_concurrent_administrative_reset_cannot_overwrite_the_new_password(): void
    {
        $user = $this->activeMfaUser('reset-concorrente-ficticio@exemplo.local');
        $token = Password::createToken($user);
        [$reset, $resetInput] = $this->resetVerifierProcess($user, $token);
        [$writer, $writerInput] = $this->administrativeResetProcess($user);

        $reset->start();
        $deadline = microtime(true) + 15;
        while (! str_contains($reset->getOutput(), 'VALIDATED') && microtime(true) < $deadline) {
            usleep(10_000);
        }
        $this->assertStringContainsString('VALIDATED', $reset->getOutput(), $reset->getErrorOutput());

        $writer->start();
        $deadline = microtime(true) + 15;
        while (! str_contains($writer->getOutput(), 'LOCKED') && microtime(true) < $deadline) {
            usleep(10_000);
        }
        $this->assertStringContainsString('LOCKED', $writer->getOutput(), $writer->getErrorOutput());

        $resetInput->write("CONTINUE\n");
        $resetInput->close();
        $writerInput->write("COMMIT\n");
        $writerInput->close();
        $writer->wait();
        $reset->wait();

        $this->assertSame(0, $writer->getExitCode(), $writer->getErrorOutput());
        $this->assertSame(2, $reset->getExitCode(), $reset->getErrorOutput());

        $user->refresh();
        $this->assertTrue(Hash::check('senha-admin-concorrente-ficticia', $user->getAuthPassword()));
        $this->assertFalse(Hash::check('senha-link-concorrente-ficticia', $user->getAuthPassword()));
        $this->assertFalse(Password::broker()->tokenExists($user, $token));
    }

    /** @return array{Process, InputStream} */
    private function administrativeResetProcess(User $user): array
    {
        $script = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
App\Support\DestructiveTestDatabaseGuard::assertProcessEnvironmentIsSafe(
    App\Support\DestructiveTestDatabaseGuard::processEnvironment(),
);
Illuminate\Support\Facades\DB::transaction(function () use ($argv): void {
    $user = App\Models\User::query()->lockForUpdate()->findOrFail((int) $argv[1]);
    $user->password = Illuminate\Support\Facades\Hash::make('senha-admin-concorrente-ficticia');
    $user->access_generation++;
    $user->save();
    app(App\Services\PasswordResetTokenService::class)->revokeLocked($user);
    fwrite(STDOUT, "LOCKED\n");
    fflush(STDOUT);
    fgets(STDIN);
});
PHP;

        $input = new InputStream;
        $process = $this->childProcess($script, [(string) $user->getKey()]);
        $process->setInput($input);

        return [$process, $input];
    }

    /** @return array{Process, InputStream} */
    private function resetVerifierProcess(User $user, string $token): array
    {
        $script = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
App\Support\DestructiveTestDatabaseGuard::assertProcessEnvironmentIsSafe(
    App\Support\DestructiveTestDatabaseGuard::processEnvironment(),
);
$user = App\Models\User::query()->findOrFail((int) $argv[1]);
if (! Illuminate\Support\Facades\Password::broker()->tokenExists($user, $argv[2])) {
    exit(4);
}
fwrite(STDOUT, "VALIDATED\n");
fflush(STDOUT);
fgets(STDIN);
$changed = Illuminate\Support\Facades\DB::transaction(function () use ($user, $argv): bool {
    $lockedUser = App\Models\User::query()->lockForUpdate()->findOrFail($user->getKey());
    if (! app(App\Services\PasswordResetTokenService::class)->consumeLocked($lockedUser, $argv[2])) {
        return false;
    }
    $lockedUser->password = Illuminate\Support\Facades\Hash::make('senha-link-concorrente-ficticia');
    $lockedUser->access_generation++;
    $lockedUser->save();

    return true;
});
exit($changed ? 0 : 2);
PHP;

        $input = new InputStream;
        $process = $this->childProcess($script, [(string) $user->getKey(), $token]);
        $process->setInput($input);

        return [$process, $input];
    }

    private function assertTransitionWinsConcurrentReset(
        string $operation,
        User $user,
        string $token,
        ?MfaEnrollment $factor = null,
    ): void {
        [$transition, $transitionInput] = $this->mfaTransitionProcess($operation, $user, $factor);
        [$reset, $resetInput] = $this->resetVerifierProcess($user, $token);

        $transition->start();
        $this->waitForOutput($transition, 'LOCKED');

        $reset->start();
        $this->waitForOutput($reset, 'VALIDATED');

        $transitionInput->write("CONTINUE\n");
        $transitionInput->close();
        $transition->wait();

        $resetInput->write("CONTINUE\n");
        $resetInput->close();
        $reset->wait();

        $this->assertSame(0, $transition->getExitCode(), $transition->getErrorOutput());
        $this->assertSame(2, $reset->getExitCode(), $reset->getErrorOutput());
        $this->assertTrue(Hash::check('password', $user->fresh()->getAuthPassword()));
        $this->assertFalse(Password::broker()->tokenExists($user->fresh(), $token));
    }

    /** @return array{Process, InputStream} */
    private function mfaTransitionProcess(string $operation, User $user, ?MfaEnrollment $factor): array
    {
        $script = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
App\Support\DestructiveTestDatabaseGuard::assertProcessEnvironmentIsSafe(
    App\Support\DestructiveTestDatabaseGuard::processEnvironment(),
);
try {
    Illuminate\Support\Facades\DB::transaction(function () use ($argv): void {
        $user = App\Models\User::query()->lockForUpdate()->findOrFail((int) $argv[2]);
        fwrite(STDOUT, "LOCKED\n");
        fflush(STDOUT);
        fgets(STDIN);
        if ($argv[1] === 'login') {
            app(App\Actions\EstablishPasswordOnlyLogin::class)->handle(
                $user,
                $user->access_generation,
                $user->getAuthPassword(),
                'password',
            );

            return;
        }
        $factor = App\Models\MfaEnrollment::query()->findOrFail((int) $argv[3]);
        $code = app(PragmaRX\Google2FA\Google2FA::class)->getCurrentOtp($factor->secret);
        app(App\Actions\ConfirmMfaEnrollment::class)->handle(
            $user,
            $factor->getKey(),
            $code,
            $user->access_generation,
            'password_only',
        );
    });
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, get_class($exception).': '.$exception->getMessage());
    exit(3);
}
PHP;

        $input = new InputStream;
        $process = $this->childProcess($script, [
            $operation,
            (string) $user->getKey(),
            (string) ($factor?->getKey() ?? 0),
        ]);
        $process->setInput($input);

        return [$process, $input];
    }

    private function waitForOutput(Process $process, string $marker): void
    {
        $deadline = microtime(true) + 15;

        while (! str_contains($process->getOutput(), $marker) && microtime(true) < $deadline) {
            usleep(10_000);
        }

        $this->assertStringContainsString($marker, $process->getOutput(), $process->getErrorOutput());
    }

    /** @param list<string> $arguments */
    private function childProcess(string $script, array $arguments): Process
    {
        return (new Process(
            [PHP_BINARY, '-r', $script, '--', ...$arguments],
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

    private function activeMfaUser(string $email): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'status' => UserStatus::Ativa,
        ]);
        MfaEnrollment::query()->create([
            'user_id' => $user->getKey(),
            'version' => 1,
            'state' => 'active',
            'secret' => 'MFRGGZDFMZTWQ2LK',
            'confirmed_at' => now('UTC'),
        ]);

        return $user;
    }

    /** @return array{User, MfaEnrollment, string} */
    private function pendingMfaUser(string $email): array
    {
        $secret = 'KRSXG5DSNFXGOIDB';
        $user = User::factory()->create([
            'email' => $email,
            'status' => UserStatus::PendenteMfa,
        ]);
        $factor = MfaEnrollment::query()->create([
            'user_id' => $user->getKey(),
            'version' => 1,
            'state' => 'pending',
            'secret' => $secret,
        ]);

        return [$user, $factor, $secret];
    }
}
