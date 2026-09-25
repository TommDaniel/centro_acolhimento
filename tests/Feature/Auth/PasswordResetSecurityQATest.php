<?php

namespace Tests\Feature\Auth;

use App\Actions\UpdateUserAccount;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\MfaEnrollment;
use App\Models\User;
use App\Services\AuditRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class PasswordResetSecurityQATest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_password_reset_consumes_the_token(): void
    {
        $user = User::factory()->create([
            'email' => 'qa-reset-consumo@exemplo-ficticio.local',
        ]);
        $generation = $user->access_generation;
        $token = Password::createToken($user);

        $this->post(route('password.store'), [
            'email' => $user->email,
            'token' => $token,
            'password' => 'senha-reset-ficticia-inicial',
            'password_confirmation' => 'senha-reset-ficticia-inicial',
        ])->assertRedirect(route('login'));

        $this->assertFalse(Password::broker()->tokenExists($user->fresh(), $token));

        $this->post(route('password.store'), [
            'email' => $user->email,
            'token' => $token,
            'password' => 'senha-reset-ficticia-replay',
            'password_confirmation' => 'senha-reset-ficticia-replay',
        ])
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors('email');

        $user->refresh();
        $this->assertTrue(Hash::check('senha-reset-ficticia-inicial', $user->getAuthPassword()));
        $this->assertFalse(Hash::check('senha-reset-ficticia-replay', $user->getAuthPassword()));
        $this->assertSame($generation + 1, $user->access_generation);
    }

    public function test_administrator_password_reset_invalidates_an_older_reset_link(): void
    {
        $administrator = User::factory()->administrator()->create([
            'email' => 'qa-admin-reset@exemplo-ficticio.local',
        ]);
        $technical = User::factory()->create([
            'email' => 'qa-alvo-reset@exemplo-ficticio.local',
        ]);
        MfaEnrollment::query()->create([
            'user_id' => $technical->getKey(),
            'version' => 1,
            'state' => 'active',
            'secret' => 'JBSWY3DPEHPK3PXP',
            'confirmed_at' => now('UTC'),
        ]);
        $tokenIssuedBeforeIntervention = Password::createToken($technical);

        app(UpdateUserAccount::class)->handle($technical, [
            'role' => UserRole::EquipeTecnica->value,
            'status' => UserStatus::Ativa->value,
            'password' => 'senha-administrativa-ficticia',
        ], $administrator);

        $response = $this->post(route('password.store'), [
            'email' => $technical->email,
            'token' => $tokenIssuedBeforeIntervention,
            'password' => 'senha-atacante-ficticia',
            'password_confirmation' => 'senha-atacante-ficticia',
        ]);

        $response
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors('email');

        $this->assertFalse(
            Password::broker()->tokenExists($technical->fresh(), $tokenIssuedBeforeIntervention),
            'Uma troca administrativa precisa revogar links de recuperação emitidos anteriormente.',
        );

        $technical->refresh();
        $this->assertTrue(Hash::check('senha-administrativa-ficticia', $technical->getAuthPassword()));
        $this->assertFalse(Hash::check('senha-atacante-ficticia', $technical->getAuthPassword()));
    }

    public function test_email_status_and_role_changes_each_revoke_pending_reset_links_without_rotating_mfa(): void
    {
        $administrator = User::factory()->administrator()->create([
            'email' => 'qa-admin-lifecycle@exemplo-ficticio.local',
        ]);
        $cases = [
            'email' => [
                'email' => 'qa-email-anterior@exemplo-ficticio.local',
                'attributes' => ['email' => 'qa-email-novo@exemplo-ficticio.local'],
            ],
            'status' => [
                'email' => 'qa-status@exemplo-ficticio.local',
                'attributes' => ['status' => UserStatus::Inativa->value],
            ],
            'role' => [
                'email' => 'qa-papel@exemplo-ficticio.local',
                'attributes' => ['role' => UserRole::Administradora->value],
            ],
        ];

        foreach ($cases as $case => $configuration) {
            $subject = User::factory()->create(['email' => $configuration['email']]);
            $factor = MfaEnrollment::query()->create([
                'user_id' => $subject->getKey(),
                'version' => 1,
                'state' => 'active',
                'secret' => 'JBSWY3DPEHPK3PXP',
                'confirmed_at' => now('UTC'),
            ]);
            $generation = $subject->access_generation;
            $token = Password::createToken($subject);
            $originalEmail = $subject->email;

            app(UpdateUserAccount::class)->handle($subject, [
                'role' => UserRole::EquipeTecnica->value,
                'status' => UserStatus::Ativa->value,
                ...$configuration['attributes'],
            ], $administrator);

            $subject->refresh();
            $this->assertFalse(
                Password::broker()->tokenExists($subject, $token),
                "A alteração de {$case} deve revogar o link pendente.",
            );
            $this->assertDatabaseMissing('password_reset_tokens', ['email' => $originalEmail]);
            $this->assertSame($generation + 1, $subject->access_generation);
            $this->assertSame('active', $factor->fresh()->state);
            $this->assertSame('JBSWY3DPEHPK3PXP', $factor->secret);
        }
    }

    public function test_password_and_token_revocation_roll_back_together_when_audit_fails(): void
    {
        $administrator = User::factory()->administrator()->create([
            'email' => 'qa-admin-rollback@exemplo-ficticio.local',
        ]);
        $technical = User::factory()->create([
            'email' => 'qa-alvo-rollback@exemplo-ficticio.local',
        ]);
        MfaEnrollment::query()->create([
            'user_id' => $technical->getKey(),
            'version' => 1,
            'state' => 'active',
            'secret' => 'JBSWY3DPEHPK3PXP',
            'confirmed_at' => now('UTC'),
        ]);
        $originalHash = $technical->getAuthPassword();
        $originalGeneration = $technical->access_generation;
        $token = Password::createToken($technical);
        $audit = Mockery::mock(AuditRecorder::class);
        $audit->shouldReceive('record')
            ->once()
            ->andThrow(new RuntimeException('Falha sintética após revogação do token.'));
        $this->app->instance(AuditRecorder::class, $audit);

        try {
            app(UpdateUserAccount::class)->handle($technical, [
                'role' => UserRole::EquipeTecnica->value,
                'status' => UserStatus::Ativa->value,
                'password' => 'senha-que-deve-reverter-ficticia',
            ], $administrator);
            $this->fail('A falha sintética da auditoria deveria reverter a transação.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Falha sintética após revogação do token.', $exception->getMessage());
        }

        $technical->refresh();
        $this->assertSame($originalHash, $technical->getAuthPassword());
        $this->assertSame($originalGeneration, $technical->access_generation);
        $this->assertTrue(Password::broker()->tokenExists($technical, $token));
    }
}
