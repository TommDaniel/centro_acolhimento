<?php

namespace Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthenticatedHistorySecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_authenticated_inertia_history_is_encrypted(): void
    {
        $user = User::factory()->create();

        $this->actingAsWithVerifiedMfa($user)->get(route('dashboard'))->assertOk();
        $this
            ->get(route('dashboard'), $this->inertiaHeaders())
            ->assertOk()
            ->assertJsonPath('encryptHistory', true);
    }

    public function test_remote_password_change_creates_anonymous_session_and_clears_history(): void
    {
        $user = User::factory()->create();
        $this->actingAsWithVerifiedMfa($user)->get(route('dashboard'))->assertOk();
        $authenticatedSessionId = session()->getId();

        $user->forceFill([
            'password' => Hash::make('senha-remota-ficticia'),
            'access_generation' => $user->access_generation + 1,
        ])->save();

        $this->get(route('dashboard'))->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertNotSame($authenticatedSessionId, session()->getId());
        $this->get(route('login'), $this->inertiaHeaders())
            ->assertOk()
            ->assertJsonPath('clearHistory', true);
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $user->getKey(),
            'action' => 'auth.password_hash_revoked',
            'result' => 'denied',
        ]);
    }

    public function test_expired_verified_session_clears_authenticated_history(): void
    {
        $user = User::factory()->create();
        $this->actingAsWithVerifiedMfa($user)->get(route('dashboard'))->assertOk();

        $this->travel(901)->seconds();
        $this->get(route('dashboard'))->assertRedirect(route('login'));

        $this->assertGuest();
        $this->get(route('login'), $this->inertiaHeaders())
            ->assertOk()
            ->assertJsonPath('clearHistory', true);
    }

    public function test_pending_mfa_state_is_explicit_in_account_edit_and_listing(): void
    {
        $administrator = User::factory()->administrator()->create();
        $pending = User::factory()->create([
            'name' => 'Técnica Pendente Fictícia',
            'email' => 'tecnica-pendente@exemplo-ficticio.local',
            'status' => UserStatus::PendenteMfa,
        ]);
        $legacyActiveWithoutMfa = User::factory()->create([
            'name' => 'Técnica Legada Fictícia',
            'email' => 'tecnica-legada@exemplo-ficticio.local',
            'status' => UserStatus::Ativa,
        ]);

        $this->actingAsWithVerifiedMfa($administrator)
            ->get(route('equipe.edit', $pending))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Equipe/Form')
                ->where('usuario.status', UserStatus::PendenteMfa->value)
                ->where('usuario.effective_status', UserStatus::PendenteMfa->value)
                ->where('usuario.has_active_mfa', false));

        $this->actingAsWithVerifiedMfa($administrator)
            ->get(route('equipe.edit', $legacyActiveWithoutMfa))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Equipe/Form')
                ->where('usuario.status', UserStatus::Ativa->value)
                ->where('usuario.effective_status', UserStatus::PendenteMfa->value)
                ->where('usuario.has_active_mfa', false));

        $this->actingAsWithVerifiedMfa($administrator)
            ->get(route('equipe.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Equipe/Index')
                ->where('grupos.Sem setor', fn ($users): bool => collect($users)->contains(
                    fn (array $user): bool => $user['id'] === $pending->getKey()
                        && $user['status'] === UserStatus::PendenteMfa->value
                        && $user['effective_status'] === UserStatus::PendenteMfa->value
                        && $user['has_active_mfa'] === false,
                ))
                ->where('grupos.Sem setor', fn ($users): bool => collect($users)->contains(
                    fn (array $user): bool => $user['id'] === $legacyActiveWithoutMfa->getKey()
                        && $user['status'] === UserStatus::Ativa->value
                        && $user['effective_status'] === UserStatus::PendenteMfa->value
                        && $user['has_active_mfa'] === false,
                )));

        $this->assertSame(UserStatus::Ativa, $legacyActiveWithoutMfa->fresh()->status);
    }

    /**
     * @return array<string, string|null>
     */
    private function inertiaHeaders(): array
    {
        return [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => Inertia::getVersion(),
        ];
    }
}
