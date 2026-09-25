<?php

namespace Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetRequestSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_known_and_unknown_accounts_receive_the_same_public_response_and_minimized_audit(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'email' => 'reset-conhecido@exemplo-ficticio.local',
        ]);
        $inactiveUser = User::factory()->create([
            'email' => 'reset-inativo@exemplo-ficticio.local',
            'status' => UserStatus::Inativa,
        ]);

        $known = $this->from(route('password.request'))->post(route('password.email'), [
            'email' => $user->email,
        ]);
        $known->assertRedirect(route('password.request'))->assertSessionHasNoErrors()->assertSessionHas('status');
        $knownStatus = $known->getSession()->get('status');
        $this->app['session']->flush();

        $unknownEmail = 'reset-desconhecido@exemplo-ficticio.local';
        $unknown = $this->from(route('password.request'))->post(route('password.email'), [
            'email' => $unknownEmail,
        ]);

        $unknown->assertRedirect(route('password.request'))->assertSessionHasNoErrors()->assertSessionHas('status');
        $this->assertSame($knownStatus, $unknown->getSession()->get('status'));
        $this->assertSame($known->getStatusCode(), $unknown->getStatusCode());
        $this->assertSame($known->headers->get('Location'), $unknown->headers->get('Location'));
        $this->assertSame($known->getContent(), $unknown->getContent());

        $this->app['session']->flush();
        $inactive = $this->from(route('password.request'))->post(route('password.email'), [
            'email' => $inactiveUser->email,
        ]);
        $inactive->assertRedirect(route('password.request'))->assertSessionHasNoErrors()->assertSessionHas('status');
        $this->assertSame($knownStatus, $inactive->getSession()->get('status'));
        $this->assertSame($known->getStatusCode(), $inactive->getStatusCode());
        $this->assertSame($known->headers->get('Location'), $inactive->headers->get('Location'));
        $this->assertSame($known->getContent(), $inactive->getContent());
        Notification::assertSentTo($user, ResetPassword::class);

        $events = AuditEvent::query()
            ->where('action', 'auth.password_reset_requested')
            ->orderBy('id')
            ->get();

        $this->assertCount(3, $events);
        $this->assertSame((string) $user->getKey(), $events[0]->subject_id);
        $this->assertNull($events[1]->subject_id);
        $this->assertSame((string) $inactiveUser->getKey(), $events[2]->subject_id);
        $serialized = $events->toJson();
        $this->assertStringNotContainsString($user->email, $serialized);
        $this->assertStringNotContainsString($inactiveUser->email, $serialized);
        $this->assertStringNotContainsString($unknownEmail, $serialized);
    }

    public function test_unknown_account_requests_are_rate_limited_by_a_non_reversible_identifier(): void
    {
        Notification::fake();
        $email = 'reset-repetido-inexistente@exemplo-ficticio.local';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from(route('password.request'))->post(route('password.email'), [
                'email' => $email,
            ])->assertRedirect(route('password.request'))->assertSessionHasNoErrors();
        }

        $response = $this->from(route('password.request'))->post(route('password.email'), [
            'email' => $email,
        ])->assertTooManyRequests();

        $this->assertStringNotContainsString($email, $response->getContent());
        $this->assertDatabaseHas('audit_events', [
            'action' => 'auth.password_reset_rate_limited',
            'result' => 'denied',
            'actor_id' => null,
            'subject_id' => null,
        ]);
        Notification::assertNothingSent();
    }
}
