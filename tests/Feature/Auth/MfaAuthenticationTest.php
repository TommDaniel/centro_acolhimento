<?php

namespace Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Models\AuditEvent;
use App\Models\MfaEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia as Assert;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class MfaAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_first_login_is_restricted_to_enrollment_until_totp_is_confirmed(): void
    {
        $user = User::factory()->create([
            'email' => 'mfa-pendente@exemplo-ficticio.local',
            'status' => UserStatus::PendenteMfa,
        ]);

        $login = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $login->assertRedirect(route('mfa.enrollment'));
        $this->get(route('dashboard'))->assertForbidden();
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $user->getKey(),
            'action' => 'auth.mfa_required',
            'result' => 'denied',
        ]);

        $enrollment = MfaEnrollment::query()->whereBelongsTo($user)->sole();
        $enrollmentPage = $this->get(route('mfa.enrollment'));
        $enrollmentPage
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/MfaEnroll')
                ->has('enrollmentId')
                ->where('setupKey', $enrollment->secret)
                ->where('qrCodeSvg', fn (string $svg): bool => str_contains($svg, '<svg')));
        $this->get(route('mfa.enrollment'), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => Inertia::getVersion(),
        ])
            ->assertOk()
            ->assertJsonPath('encryptHistory', true);

        $passwordOnlySessionId = session()->getId();
        $code = app(Google2FA::class)->getCurrentOtp($enrollment->secret);

        $this->post(route('mfa.enrollment.confirm'), [
            'enrollment_id' => $enrollment->getKey(),
            'code' => $code,
        ])->assertRedirect(route('dashboard'));

        $this->assertNotSame($passwordOnlySessionId, session()->getId());
        $this->assertSame(UserStatus::Ativa, $user->fresh()->status);
        $this->assertSame('active', $enrollment->fresh()->state);
        $this->assertSame('mfa_verified', session('auth.level'));
        $this->get(route('dashboard'), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => Inertia::getVersion(),
        ])
            ->assertOk()
            ->assertJsonPath('clearHistory', true)
            ->assertJsonMissing(['setupKey' => $enrollment->secret]);
    }

    public function test_active_account_requires_challenge_and_totp_cannot_be_replayed(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $user = User::factory()->create(['email' => 'mfa-ativa@exemplo-ficticio.local']);
        $factor = MfaEnrollment::query()->create([
            'user_id' => $user->getKey(),
            'version' => 1,
            'state' => 'active',
            'secret' => $secret,
            'confirmed_at' => now('UTC'),
        ]);
        $code = app(Google2FA::class)->getCurrentOtp($secret);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('mfa.challenge'));
        $this->get(route('dashboard'))->assertForbidden();

        $this->post(route('mfa.challenge.verify'), ['code' => '000000'])
            ->assertSessionHasErrors('code');
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $user->getKey(),
            'action' => 'auth.mfa_challenge',
            'result' => 'denied',
        ]);

        $this->post(route('mfa.challenge.verify'), ['code' => $code])
            ->assertRedirect(route('dashboard'));
        $this->assertNotNull($factor->fresh()->last_accepted_time_step);

        $this->post(route('logout'));
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->post(route('mfa.challenge.verify'), ['code' => $code])
            ->assertSessionHasErrors('code');
    }

    public function test_legacy_active_account_without_factor_is_forced_into_enrollment(): void
    {
        $user = User::factory()->create([
            'email' => 'legada@exemplo-ficticio.local',
            'status' => UserStatus::Ativa,
        ]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('mfa.enrollment'));

        $this->assertSame(UserStatus::PendenteMfa, $user->fresh()->status);
        $this->assertAuthenticatedAs($user);
    }

    public function test_new_password_login_revokes_the_previous_pending_generation_and_old_qr(): void
    {
        $user = User::factory()->create([
            'email' => 'geracao-mfa@exemplo-ficticio.local',
            'status' => UserStatus::PendenteMfa,
        ]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $first = MfaEnrollment::query()->whereBelongsTo($user)->where('state', 'pending')->sole();
        $firstCode = app(Google2FA::class)->getCurrentOtp($first->secret);
        $this->post(route('logout'));

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $second = MfaEnrollment::query()->whereBelongsTo($user)->where('state', 'pending')->sole();

        $this->assertNotSame($first->getKey(), $second->getKey());
        $this->assertNotSame($first->secret, $second->secret);
        $this->assertSame('revoked', $first->fresh()->state);
        $this->post(route('mfa.enrollment.confirm'), [
            'enrollment_id' => $first->getKey(),
            'code' => $firstCode,
        ])->assertSessionHasErrors('code');
        $this->assertSame('pending', $second->fresh()->state);
    }

    public function test_enrollment_start_and_rotation_are_audited_without_sensitive_fields(): void
    {
        $user = User::factory()->create([
            'email' => 'auditoria-enrolamento@exemplo-ficticio.local',
            'status' => UserStatus::PendenteMfa,
        ]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $started = AuditEvent::query()
            ->where('actor_id', $user->getKey())
            ->where('subject_id', (string) $user->getKey())
            ->where('action', 'auth.mfa_enrollment_started')
            ->where('result', 'success')
            ->sole();
        $this->assertSame([], $started->changed_fields);

        $this->post(route('logout'));
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $rotated = AuditEvent::query()
            ->where('actor_id', $user->getKey())
            ->where('subject_id', (string) $user->getKey())
            ->where('action', 'auth.mfa_enrollment_rotated')
            ->where('result', 'success')
            ->sole();
        $this->assertSame([], $rotated->changed_fields);
    }

    public function test_mfa_session_enforces_idle_absolute_and_access_generation_limits(): void
    {
        $user = User::factory()->create();
        MfaEnrollment::query()->create([
            'user_id' => $user->getKey(),
            'version' => 1,
            'state' => 'active',
            'secret' => 'JBSWY3DPEHPK3PXP',
            'confirmed_at' => now('UTC'),
        ]);
        $now = now('UTC')->getTimestamp();

        foreach ([
            ['issued' => $now - 100, 'last' => $now - 901, 'generation' => 1],
            ['issued' => $now - 28801, 'last' => $now - 100, 'generation' => 1],
            ['issued' => $now - 100, 'last' => $now - 100, 'generation' => 999],
        ] as $invalidSession) {
            $this->actingAsWithVerifiedMfa($user)->withSession([
                'auth.level' => 'mfa_verified',
                'auth.access_generation' => $invalidSession['generation'],
                'auth.mfa_issued_at' => $invalidSession['issued'],
                'auth.mfa_last_activity_at' => $invalidSession['last'],
            ])->get(route('dashboard'))->assertRedirect(route('login'));
            $this->assertGuest();
        }
    }

    public function test_first_enrollment_cannot_replace_an_active_factor(): void
    {
        $user = User::factory()->create();
        $active = MfaEnrollment::query()->create([
            'user_id' => $user->getKey(),
            'version' => 1,
            'state' => 'active',
            'secret' => 'JBSWY3DPEHPK3PXP',
            'confirmed_at' => now('UTC'),
        ]);
        $pending = MfaEnrollment::query()->create([
            'user_id' => $user->getKey(),
            'version' => 2,
            'state' => 'pending',
            'secret' => 'KRSXG5DSNFXGOIDB',
        ]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $code = app(Google2FA::class)->getCurrentOtp($pending->secret);
        $this->post(route('mfa.enrollment.confirm'), [
            'enrollment_id' => $pending->getKey(),
            'code' => $code,
        ])->assertSessionHasErrors('code');

        $this->assertSame('active', $active->fresh()->state);
        $this->assertSame('pending', $pending->fresh()->state);
    }

    public function test_mfa_post_rate_limit_invalidates_only_the_limited_password_session(): void
    {
        RateLimiter::clear('mfa');
        $first = $this->activeMfaUser('limitada@exemplo-ficticio.local', 'JBSWY3DPEHPK3PXP');
        $second = $this->activeMfaUser('nao-limitada@exemplo-ficticio.local', 'KRSXG5DSNFXGOIDB');

        $this->post('/login', ['email' => $first->email, 'password' => 'password']);
        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->post(route('mfa.challenge.verify'), ['code' => '000000'])
                ->assertSessionHasErrors('code')
                ->assertSessionHas('_old_input', fn (array $oldInput): bool => ! array_key_exists('code', $oldInput));
        }

        $this->post(route('mfa.challenge.verify'), ['code' => '000000'])
            ->assertTooManyRequests()
            ->assertHeader('X-Mfa-Session-Expired', '1');
        $this->assertGuest();
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $first->getKey(),
            'action' => 'auth.mfa_rate_limited',
            'result' => 'denied',
        ]);
        $this->get(route('login'), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => Inertia::getVersion(),
        ])->assertJsonPath('clearHistory', true);

        $this->post('/login', ['email' => $second->email, 'password' => 'password'])
            ->assertRedirect(route('mfa.challenge'));
        $this->post(route('mfa.challenge.verify'), [
            'code' => app(Google2FA::class)->getCurrentOtp('KRSXG5DSNFXGOIDB'),
        ])->assertRedirect(route('dashboard'));
    }

    public function test_mfa_account_attempt_budget_survives_logout_relogin_and_ip_change(): void
    {
        $user = $this->activeMfaUser('limite-duravel@exemplo-ficticio.local', 'JBSWY3DPEHPK3PXP');

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->post('/login', ['email' => $user->email, 'password' => 'password']);

        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
                ->post(route('mfa.challenge.verify'), ['code' => '000000'])
                ->assertSessionHasErrors('code');
        }

        $this->post(route('logout'));
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
            ->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('mfa.challenge'));
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
            ->post(route('mfa.challenge.verify'), ['code' => '000000'])
            ->assertTooManyRequests();

        $this->assertGuest();
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $user->getKey(),
            'action' => 'auth.mfa_rate_limited',
            'result' => 'denied',
        ]);
    }

    public function test_expired_restricted_session_is_audited_distinctly_and_requires_new_password_login(): void
    {
        $user = $this->activeMfaUser('restrita-expirada@exemplo-ficticio.local', 'JBSWY3DPEHPK3PXP');
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->travel(301)->seconds();
        $this->get(route('mfa.challenge'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $user->getKey(),
            'action' => 'auth.mfa_restricted_expired',
            'result' => 'denied',
        ]);
        $this->get(route('login'), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => Inertia::getVersion(),
        ])->assertJsonPath('clearHistory', true);
    }

    public function test_restricted_mfa_routes_reject_and_audit_any_other_session_level(): void
    {
        $user = $this->activeMfaUser('nivel-invalido@exemplo-ficticio.local', 'JBSWY3DPEHPK3PXP');
        $now = now('UTC')->getTimestamp();

        $this->actingAs($user)->withSession([
            'auth.level' => 'mfa_verified',
            'auth.password_verified_at' => $now,
            'auth.access_generation' => $user->access_generation,
        ])->get(route('mfa.challenge'))->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $user->getKey(),
            'action' => 'auth.mfa_restricted_invalid_level',
            'result' => 'denied',
        ]);
    }

    public function test_inactive_account_in_restricted_session_is_audited_distinctly(): void
    {
        $user = $this->activeMfaUser('restrita-inativa@exemplo-ficticio.local', 'JBSWY3DPEHPK3PXP');
        $user->update(['status' => UserStatus::Inativa]);
        $now = now('UTC')->getTimestamp();

        $this->actingAs($user->fresh())->withSession([
            'auth.level' => 'password_only',
            'auth.password_verified_at' => $now,
            'auth.access_generation' => $user->access_generation,
            'auth.restricted_session_id' => 'sessao-inativa-ficticia',
        ])->get(route('mfa.challenge'))->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $user->getKey(),
            'action' => 'auth.account_inactive',
            'result' => 'denied',
        ]);
    }

    public function test_logout_clears_encrypted_enrollment_history_in_the_anonymous_session(): void
    {
        $user = User::factory()->create([
            'email' => 'historico-logout@exemplo-ficticio.local',
            'status' => UserStatus::PendenteMfa,
        ]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->get(route('mfa.enrollment'))->assertOk();
        $this->post(route('logout'))->assertRedirect('/');
        $this->assertGuest();
        $this->get(route('login'), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => Inertia::getVersion(),
        ])->assertJsonPath('clearHistory', true);
    }

    public function test_administrator_cannot_return_an_active_factor_to_pending_state(): void
    {
        $administrator = User::factory()->administrator()->create();
        $target = $this->activeMfaUser('alvo-mfa@exemplo-ficticio.local', 'JBSWY3DPEHPK3PXP');

        $this->actingAsWithVerifiedMfa($administrator)->put(route('equipe.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'password' => '',
            'password_confirmation' => '',
            'setor_id' => $target->setor_id,
            'role' => $target->role->value,
            'status' => UserStatus::PendenteMfa->value,
            'cargo' => $target->cargo,
            'telefone' => $target->telefone,
        ])->assertSessionHasErrors('status');

        $this->assertSame(UserStatus::Ativa, $target->fresh()->status);
        $this->assertTrue($target->fresh()->hasActiveMfa());
    }

    public function test_fortify_default_and_passkey_routes_are_not_exposed(): void
    {
        $user = User::factory()->create();

        $this->actingAsWithVerifiedMfa($user);

        foreach ([
            ['post', '/user/two-factor-authentication'],
            ['get', '/user/two-factor-qr-code'],
            ['get', '/user/two-factor-secret-key'],
            ['post', '/user/confirmed-two-factor-authentication'],
            ['get', '/user/two-factor-recovery-codes'],
            ['post', '/user/two-factor-recovery-codes'],
            ['delete', '/user/two-factor-authentication'],
            ['post', '/two-factor-challenge'],
            ['post', '/passkeys/login'],
        ] as [$method, $uri]) {
            $this->{$method}($uri)->assertNotFound();
        }

        $this->assertSame(0, AuditEvent::query()->where('action', 'auth.mfa_challenge')->count());
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
}
