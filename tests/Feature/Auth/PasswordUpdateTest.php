<?php

namespace Tests\Feature\Auth;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_can_be_updated(): void
    {
        $user = User::factory()->create();
        $oldPasswordHash = $user->getAuthPassword();
        $oldRememberToken = $user->getRememberToken();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
        $this->assertNotSame($oldRememberToken, $user->getRememberToken());
        $response->assertSessionHas(
            'password_hash_web',
            fn (string $storedHash): bool => $storedHash !== $oldPasswordHash,
        );
        $this->assertNotSame($oldPasswordHash, $user->getAuthPassword());
        $event = AuditEvent::query()->where('action', 'user.password_changed')->sole();
        $this->assertSame([], $event->changed_fields);
        $this->assertStringNotContainsString('new-password', $event->toJson());
    }

    public function test_password_change_revokes_another_session_but_preserves_the_rotated_current_session(): void
    {
        $user = User::factory()->create();
        $oldPasswordHash = $user->getAuthPassword();

        $this->actingAs($user)
            ->withSession(['password_hash_web' => $oldPasswordHash])
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertSessionHasNoErrors();

        $this->get(route('dashboard'))->assertOk();

        $this->app['auth']->forgetGuards();
        $guard = Auth::guard('web');
        $this->withSession([
            $guard->getName() => $user->id,
            'password_hash_web' => $oldPasswordHash,
        ])->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasErrors('current_password')
            ->assertRedirect('/profile');

        $this->assertDatabaseMissing('audit_events', ['action' => 'user.password_changed']);
    }
}
