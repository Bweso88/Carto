<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * En production interne, l'inscription libre est fermée (MERIMAP_REGISTRATION=false).
 */
class RegistrationClosedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Les tests tournent avec l'inscription ouverte : on la ferme puis on recharge routes/web.php
        config(['merimap.registration' => false]);
        require base_path('routes/web.php');
        app('router')->getRoutes()->refreshNameLookups();
    }

    public function test_registration_routes_answer_404(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', ['name' => 'X', 'email' => 'x@exemple.cg', 'password' => 'motdepasse-solide', 'password_confirmation' => 'motdepasse-solide'])
            ->assertNotFound();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_login_page_hides_the_sign_up_link(): void
    {
        $this->get('/login')->assertInertia(fn (Assert $page) => $page->where('canRegister', false));
    }
}
