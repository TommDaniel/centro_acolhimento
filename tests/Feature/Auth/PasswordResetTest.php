<?php

namespace Tests\Feature\Auth;

use App\Models\AuditEvent;
use App\Models\User;
use App\Support\InstitutionContext;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'nova-senha-reset-ficticia',
                'password_confirmation' => 'nova-senha-reset-ficticia',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            $event = AuditEvent::query()->where('action', 'user.password_reset')->sole();
            $this->assertNull($event->actor_id);
            $this->assertSame(app(InstitutionContext::class)->unit()->id, $event->unidade_id);
            $this->assertSame((string) $user->id, $event->subject_id);
            $this->assertSame([], $event->changed_fields);
            $this->assertStringNotContainsString($notification->token, $event->toJson());
            $this->assertStringNotContainsString($user->email, $event->toJson());
            $this->assertStringNotContainsString('nova-senha-reset-ficticia', $event->toJson());

            return true;
        });
    }

    public function test_external_password_reset_revokes_existing_sessions_and_remember_token(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $oldPasswordHash = $user->getAuthPassword();
        $oldRememberToken = $user->getRememberToken();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user, $oldPasswordHash, $oldRememberToken) {
            $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'nova-senha-reset-ficticia',
                'password_confirmation' => 'nova-senha-reset-ficticia',
            ])->assertSessionHasNoErrors();

            $user->refresh();
            $this->assertNotSame($oldRememberToken, $user->getRememberToken());
            $this->app['auth']->forgetGuards();
            $guard = Auth::guard('web');

            $this->withSession([
                $guard->getName() => $user->id,
                'password_hash_web' => $oldPasswordHash,
            ])->get(route('dashboard'))->assertRedirect(route('login'));

            return true;
        });
    }

    public function test_invalid_reset_token_does_not_create_password_reset_audit_event(): void
    {
        $user = User::factory()->create();

        $this->post('/reset-password', [
            'token' => 'token-de-reset-invalido-ficticio',
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('audit_events', ['action' => 'user.password_reset']);
    }
}
