<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_guest_is_redirected_from_the_root_to_login_via_the_protected_dashboard(): void
    {
        $this->get('/')
            ->assertRedirect('/dashboard');

        $this->get('/dashboard')
            ->assertRedirectToRoute('login');
    }
}
