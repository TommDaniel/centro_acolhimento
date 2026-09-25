<?php

namespace Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Models\MfaAttemptState;
use App\Models\MfaEnrollment;
use App\Models\User;
use App\Services\TotpService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class MfaSecurityQATest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_password_only_session_cannot_confirm_another_users_enrollment(): void
    {
        $user = User::factory()->create([
            'email' => 'qa-idor-origem@exemplo-ficticio.local',
            'status' => UserStatus::PendenteMfa,
        ]);
        $otherUser = User::factory()->create([
            'email' => 'qa-idor-alvo@exemplo-ficticio.local',
            'status' => UserStatus::PendenteMfa,
        ]);
        $otherEnrollment = MfaEnrollment::query()->create([
            'user_id' => $otherUser->getKey(),
            'version' => 1,
            'state' => 'pending',
            'secret' => 'KRSXG5DSNFXGOIDB',
        ]);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('mfa.enrollment'));

        $ownEnrollment = MfaEnrollment::query()
            ->whereBelongsTo($user)
            ->where('state', 'pending')
            ->sole();

        $this->post(route('mfa.enrollment.confirm'), [
            'enrollment_id' => $otherEnrollment->getKey(),
            'code' => app(Google2FA::class)->getCurrentOtp($otherEnrollment->secret),
        ])->assertSessionHasErrors('code');

        $this->assertSame('pending', $ownEnrollment->fresh()->state);
        $this->assertSame('pending', $otherEnrollment->fresh()->state);
        $this->assertSame(UserStatus::PendenteMfa, $user->fresh()->status);
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $user->getKey(),
            'action' => 'auth.mfa_enrollment_confirmed',
            'result' => 'denied',
        ]);
    }

    public function test_enrollment_and_challenge_get_requests_do_not_consume_the_post_attempt_budget(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $user = $this->activeMfaUser('qa-throttle-get@exemplo-ficticio.local', $secret);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('mfa.challenge'));

        for ($request = 0; $request < 10; $request++) {
            $this->get(route('mfa.challenge'))->assertOk();
        }

        $this->post(route('mfa.challenge.verify'), [
            'code' => app(Google2FA::class)->getCurrentOtp($secret),
        ])->assertRedirect(route('dashboard'));
    }

    public function test_active_password_only_session_cannot_reach_enrollment_secret(): void
    {
        $user = $this->activeMfaUser('qa-sem-qr@exemplo-ficticio.local', 'JBSWY3DPEHPK3PXP');

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('mfa.challenge'));

        $this->get(route('mfa.enrollment'))
            ->assertRedirect(route('mfa.challenge'))
            ->assertDontSee('JBSWY3DPEHPK3PXP');
    }

    public function test_malformed_challenge_is_not_flashed_and_is_audited_as_a_denial(): void
    {
        $user = $this->activeMfaUser('qa-formato-challenge@exemplo-ficticio.local', 'JBSWY3DPEHPK3PXP');

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->post(route('mfa.challenge.verify'), ['code' => 'formato-invalido'])
            ->assertSessionHasErrors('code')
            ->assertSessionHas('_old_input', fn (array $oldInput): bool => ! array_key_exists('code', $oldInput));

        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $user->getKey(),
            'action' => 'auth.mfa_challenge',
            'result' => 'denied',
        ]);
    }

    public function test_missing_enrollment_code_is_audited_without_flashing_a_secret(): void
    {
        $user = User::factory()->create([
            'email' => 'qa-codigo-ausente@exemplo-ficticio.local',
            'status' => UserStatus::PendenteMfa,
        ]);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);
        $enrollment = MfaEnrollment::query()->whereBelongsTo($user)->where('state', 'pending')->sole();

        $this->post(route('mfa.enrollment.confirm'), [
            'enrollment_id' => $enrollment->getKey(),
        ])
            ->assertSessionHasErrors('code')
            ->assertSessionHas('_old_input', fn (array $oldInput): bool => ! array_key_exists('code', $oldInput));

        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $user->getKey(),
            'action' => 'auth.mfa_enrollment_confirmed',
            'result' => 'denied',
        ]);
    }

    public function test_restricted_and_verified_session_limits_accept_the_exact_boundary_then_fail_closed(): void
    {
        $this->travelTo(now('UTC')->startOfSecond());
        $user = $this->activeMfaUser('qa-limites@exemplo-ficticio.local', 'JBSWY3DPEHPK3PXP');
        $now = now('UTC')->getTimestamp();

        $this->actingAs($user)->withSession([
            'auth.level' => 'password_only',
            'auth.password_verified_at' => $now - 300,
            'auth.access_generation' => $user->access_generation,
            'auth.restricted_session_id' => 'qa-restricted-session-ficticia',
        ])->get(route('mfa.challenge'))->assertOk();

        $this->travel(1)->seconds();
        $this->get(route('mfa.challenge'))->assertRedirect(route('login'));
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $user->getKey(),
            'action' => 'auth.mfa_restricted_expired',
            'result' => 'denied',
        ]);

        $this->travelBack();
        $this->travelTo(now('UTC')->startOfSecond());
        $now = now('UTC')->getTimestamp();
        $this->actingAs($user)->withSession([
            'auth.level' => 'mfa_verified',
            'auth.access_generation' => $user->access_generation,
            'auth.mfa_issued_at' => $now - 28800,
            'auth.mfa_last_activity_at' => $now - 900,
        ])->get(route('dashboard'))->assertOk();

        $this->travel(1)->seconds();
        $this->withSession([
            'auth.mfa_issued_at' => $now - 28800,
            'auth.mfa_last_activity_at' => $now - 900,
        ])->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $user->getKey(),
            'action' => 'auth.mfa_session_expired',
            'result' => 'denied',
        ]);
    }

    public function test_qr_svg_is_generated_as_static_markup_without_the_account_identifier(): void
    {
        $user = User::factory()->create([
            'email' => 'qa-svg@exemplo-ficticio.local',
            'status' => UserStatus::PendenteMfa,
        ]);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response = $this->get(route('mfa.enrollment'));
        $page = $response->viewData('page');
        $svg = $page['props']['qrCodeSvg'];

        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringNotContainsString('<script', mb_strtolower($svg));
        $this->assertStringNotContainsString('javascript:', mb_strtolower($svg));
        $this->assertStringNotContainsString($user->email, $svg);
    }

    public function test_password_reset_request_does_not_enumerate_whether_an_account_exists(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'email' => 'qa-recuperacao-existente@exemplo-ficticio.local',
        ]);

        $knownAccount = $this->from(route('password.request'))->post(route('password.email'), [
            'email' => $user->email,
        ]);
        $knownAccount
            ->assertRedirect(route('password.request'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');
        $knownStatus = $knownAccount->getSession()->get('status');
        Notification::assertSentTo($user, ResetPassword::class);

        $this->app['session']->flush();

        $unknownAccount = $this->from(route('password.request'))->post(route('password.email'), [
            'email' => 'qa-recuperacao-inexistente@exemplo-ficticio.local',
        ]);
        $unknownAccount
            ->assertRedirect(route('password.request'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $this->assertSame($knownStatus, $unknownAccount->getSession()->get('status'));
    }

    public function test_active_durable_cooldown_is_not_masked_by_a_shorter_retry_after(): void
    {
        $this->travelTo(now('UTC')->startOfSecond());
        $secret = 'JBSWY3DPEHPK3PXP';
        $user = $this->activeMfaUser('qa-retry-after-duravel@exemplo-ficticio.local', $secret);
        $factor = MfaEnrollment::query()->whereBelongsTo($user)->where('state', 'active')->sole();
        $invalidCode = $this->invalidCode($secret);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        for ($attempt = 1; $attempt < 5; $attempt++) {
            $this->post(route('mfa.challenge.verify'), ['code' => $invalidCode])
                ->assertSessionHasErrors('code');
        }

        $this->post(route('mfa.challenge.verify'), ['code' => $invalidCode])
            ->assertTooManyRequests()
            ->assertHeader('Retry-After', '300');

        $state = MfaAttemptState::query()->whereBelongsTo($user)->sole();
        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);
        $this->post(route('mfa.challenge.verify'), [
            'code' => app(Google2FA::class)->getCurrentOtp($secret),
        ])
            ->assertTooManyRequests()
            ->assertHeader('Retry-After', '300');

        $this->assertSame(5, $state->fresh()->consecutive_failures);
        $this->assertNull($factor->fresh()->last_accepted_time_step);
    }

    public function test_password_reset_rate_limit_normalizes_unknown_email_identifiers(): void
    {
        Notification::fake();
        $email = 'qa-reset-normalizado-inexistente@exemplo-ficticio.local';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $candidate = $attempt % 2 === 0 ? mb_strtoupper($email) : $email;

            $this->from(route('password.request'))->post(route('password.email'), [
                'email' => $candidate,
            ])
                ->assertRedirect(route('password.request'))
                ->assertSessionHasNoErrors();
        }

        $response = $this->from(route('password.request'))->post(route('password.email'), [
            'email' => mb_strtoupper($email),
        ])
            ->assertTooManyRequests()
            ->assertHeader('Retry-After');

        $this->assertStringNotContainsString($email, mb_strtolower($response->getContent()));
        Notification::assertNothingSent();
    }

    public function test_password_reset_rate_limit_aggregates_distinct_unknown_emails_by_ip(): void
    {
        Notification::fake();
        $ip = '192.0.2.201';

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => $ip])
                ->from(route('password.request'))
                ->post(route('password.email'), [
                    'email' => "qa-reset-ip-{$attempt}@exemplo-ficticio.local",
                ])
                ->assertRedirect(route('password.request'))
                ->assertSessionHasNoErrors();
        }

        $response = $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->from(route('password.request'))
            ->post(route('password.email'), [
                'email' => 'qa-reset-ip-limite@exemplo-ficticio.local',
            ])
            ->assertTooManyRequests()
            ->assertHeader('Retry-After');

        $this->assertStringNotContainsString('qa-reset-ip-limite', $response->getContent());
        Notification::assertNothingSent();
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
}
