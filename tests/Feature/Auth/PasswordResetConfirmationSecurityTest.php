<?php

namespace Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PasswordResetConfirmationSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_link_keeps_the_bearer_in_a_fragment_and_captures_it_over_post(): void
    {
        $user = User::factory()->create(['email' => 'reset-url-ficticio@exemplo.local']);
        $token = Password::createToken($user);
        $mail = (new ResetPassword($token))->toMail($user);
        $parts = parse_url($mail->actionUrl);
        parse_str($parts['fragment'] ?? '', $fragment);

        $this->assertSame('/reset-password', $parts['path'] ?? null);
        $this->assertArrayNotHasKey('query', $parts);
        $this->assertTrue(hash_equals($token, $fragment['token'] ?? ''));
        $this->assertTrue(hash_equals($user->email, $fragment['email'] ?? ''));

        $landing = $this->get($parts['path']);
        $landing
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/CaptureResetPassword')
                ->missing('token')
                ->missing('email'));
        $this->assertStringNotContainsString($token, $landing->getContent());
        $this->assertStringNotContainsString($user->email, $landing->getContent());

        $capture = $this->post(route('password.reset.capture'), [
            'token' => $token,
            'email' => $user->email,
        ]);

        $capture
            ->assertRedirect(route('password.reset'))
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringNotContainsString($token, (string) $capture->headers->get('Location'));

        $form = $this->get(route('password.reset'));
        $form
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringNotContainsString($token, $form->getContent());
    }

    public function test_legacy_get_with_bearer_in_path_is_not_available(): void
    {
        $user = User::factory()->create(['email' => 'reset-legado-ficticio@exemplo.local']);
        $token = Password::createToken($user);

        $this->get("/reset-password/{$token}?email=".urlencode($user->email))
            ->assertNotFound();
    }

    public function test_capture_with_missing_context_fails_closed_without_flashing_input(): void
    {
        $response = $this->post(route('password.reset.capture'), [
            'email' => 'reset-captura-invalida-ficticio@exemplo.local',
        ]);

        $response
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors('email')
            ->assertSessionMissing('_old_input')
            ->assertSessionMissing('auth.password_reset_email')
            ->assertSessionMissing('auth.password_reset_token');
    }

    public function test_fragment_capture_is_rate_limited_without_echoing_or_retaining_context(): void
    {
        $payload = [
            'email' => 'reset-captura-limitada-ficticio@exemplo.local',
            'token' => 'token-captura-limitada-ficticio',
        ];

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('password.reset.capture'), $payload)
                ->assertRedirect(route('password.reset'));
        }

        $response = $this->post(route('password.reset.capture'), $payload);
        $response
            ->assertTooManyRequests()
            ->assertHeader('Retry-After')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertSessionMissing('auth.password_reset_email')
            ->assertSessionMissing('auth.password_reset_token');
        $this->assertStringNotContainsString($payload['email'], $response->getContent());
        $this->assertStringNotContainsString($payload['token'], $response->getContent());
    }

    public function test_captured_reset_context_expires_after_fifteen_minutes(): void
    {
        $user = User::factory()->create(['email' => 'reset-expirado-ficticio@exemplo.local']);
        $token = Password::createToken($user);

        $this->post(route('password.reset.capture'), ['token' => $token, 'email' => $user->email])
            ->assertRedirect(route('password.reset'));
        $this->travel(901)->seconds();

        $this->get(route('password.reset'))
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors('email')
            ->assertSessionMissing('auth.password_reset_token');
    }

    public function test_valid_captured_token_resets_password_under_lock_and_clears_sensitive_session_state(): void
    {
        $user = User::factory()->create(['email' => 'reset-valido-ficticio@exemplo.local']);
        $generation = $user->access_generation;
        $token = Password::createToken($user);

        $this->post(route('password.reset.capture'), ['token' => $token, 'email' => $user->email])
            ->assertRedirect(route('password.reset'));
        $this->get(route('password.reset'))->assertOk();

        $this->post(route('password.store'), [
            'password' => 'senha-nova-ficticia-segura',
            'password_confirmation' => 'senha-nova-ficticia-segura',
        ])
            ->assertRedirect(route('login'))
            ->assertSessionMissing('auth.password_reset_email')
            ->assertSessionMissing('auth.password_reset_token');

        $user->refresh();
        $this->assertTrue(Hash::check('senha-nova-ficticia-segura', $user->getAuthPassword()));
        $this->assertSame($generation + 1, $user->access_generation);
    }

    public function test_invalid_token_unknown_account_and_inactive_account_have_the_same_public_response(): void
    {
        $active = User::factory()->create(['email' => 'reset-ativo-ficticio@exemplo.local']);
        $inactive = User::factory()->create([
            'email' => 'reset-inativo-ficticio@exemplo.local',
            'status' => UserStatus::Inativa,
        ]);
        $attempts = [
            ['email' => $active->email, 'token' => 'token-invalido-ativo-ficticio'],
            ['email' => $inactive->email, 'token' => 'token-invalido-inativo-ficticio'],
            ['email' => 'reset-inexistente-ficticio@exemplo.local', 'token' => 'token-invalido-inexistente-ficticio'],
        ];
        $responses = [];

        foreach ($attempts as $attempt) {
            $response = $this->post(route('password.store'), [
                ...$attempt,
                'password' => 'senha-nova-ficticia-segura',
                'password_confirmation' => 'senha-nova-ficticia-segura',
            ]);
            $response
                ->assertRedirect(route('password.request'))
                ->assertSessionHasErrors('email');
            $responses[] = [
                $response->getStatusCode(),
                $response->headers->get('Location'),
                $response->getSession()->get('errors')->get('email'),
            ];
            $this->app['session']->flush();
        }

        $this->assertSame($responses[0], $responses[1]);
        $this->assertSame($responses[1], $responses[2]);
    }

    public function test_reset_confirmation_is_limited_by_hmac_email_and_ip_buckets(): void
    {
        $payload = [
            'email' => 'reset-limitado-ficticio@exemplo.local',
            'token' => 'token-invalido-limitado-ficticio',
            'password' => 'senha-nova-ficticia-segura',
            'password_confirmation' => 'senha-nova-ficticia-segura',
        ];

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('password.store'), $payload)
                ->assertRedirect(route('password.request'));
        }

        $response = $this->post(route('password.store'), $payload);
        $response
            ->assertTooManyRequests()
            ->assertHeader('Retry-After')
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringNotContainsString($payload['email'], $response->getContent());
        $this->assertStringNotContainsString($payload['token'], $response->getContent());
    }
}
