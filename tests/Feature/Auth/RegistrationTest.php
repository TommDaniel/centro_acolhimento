<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_screen_is_not_available(): void
    {
        $response = $this->get('/register');

        $response->assertNotFound();
        $this->assertGuest();
    }

    public function test_direct_public_registration_request_does_not_create_a_user(): void
    {
        $existingUser = User::factory()->create([
            'name' => 'Usuaria Ficticia Existente',
            'email' => 'existente.ficticia@example.test',
        ]);

        $response = $this->post('/register', [
            'name' => 'Nova Pessoa Ficticia',
            'email' => 'nova.pessoa.ficticia@example.test',
            'password' => 'senha-ficticia-segura',
            'password_confirmation' => 'senha-ficticia-segura',
        ]);

        $response->assertNotFound();
        $this->assertGuest();
        self::assertSame(1, User::query()->count());
        $this->assertModelExists($existingUser);
    }
}
