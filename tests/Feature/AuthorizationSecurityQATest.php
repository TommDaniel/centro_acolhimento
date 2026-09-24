<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthorizationSecurityQATest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_legacy_recaller_cannot_authenticate_when_remember_me_is_disabled(): void
    {
        $user = User::factory()->create();
        $guard = Auth::guard('web');
        $cookieName = $guard->getRecallerName();
        $oldRememberToken = $user->getRememberToken();
        $recaller = implode('|', [
            $user->getAuthIdentifier(),
            $oldRememberToken,
            $guard->hashPasswordForCookie($user->getAuthPassword()),
        ]);

        $this->withCookie($cookieName, $recaller)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertCookieExpired($cookieName);

        $this->assertGuest();
        $this->assertNotSame($oldRememberToken, $user->refresh()->getRememberToken());

        $event = AuditEvent::query()->where('action', 'auth.remember_rejected')->sole();
        $this->assertSame($user->id, $event->actor_id);
        $this->assertSame([], $event->changed_fields);
        $this->assertDatabaseMissing('audit_events', ['action' => 'auth.login_succeeded']);
    }

    public function test_password_change_revokes_an_existing_authenticated_session(): void
    {
        $user = User::factory()->create();
        $guard = Auth::guard('web');
        $oldPasswordHash = $user->getAuthPassword();
        $user->forceFill(['password' => Hash::make('nova-senha-ficticia-segura')])->save();

        $this->withSession([
            $guard->getName() => $user->getKey(),
            'password_hash_web' => $oldPasswordHash,
        ])->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
