<?php

namespace Tests\Feature\Cmdb;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateUserCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_verified_admin(): void
    {
        $this->artisan('merimap:user', ['email' => 'DSI@Exemple.cg', '--name' => 'DSI', '--password' => 'motdepasse-solide'])
            ->expectsOutputToContain('Compte créé')
            ->assertSuccessful();

        $user = User::firstWhere('email', 'dsi@exemple.cg');
        $this->assertSame(UserRole::Admin, $user->role);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('motdepasse-solide', $user->password));
    }

    public function test_it_updates_the_role_of_an_existing_user_without_touching_the_password(): void
    {
        $user = User::factory()->create(['email' => 'agent@exemple.cg']);
        $hash = $user->password;

        $this->artisan('merimap:user', ['email' => 'agent@exemple.cg', '--role' => 'editor', '--password' => ''])
            ->expectsOutputToContain('Compte mis à jour')
            ->assertSuccessful();

        $this->assertSame(UserRole::Editor, $user->fresh()->role);
        $this->assertSame($hash, $user->fresh()->password);
    }

    public function test_it_rejects_weak_passwords_and_unknown_roles(): void
    {
        $this->artisan('merimap:user', ['email' => 'x@exemple.cg', '--password' => 'court'])->assertFailed();
        $this->artisan('merimap:user', ['email' => 'x@exemple.cg', '--password' => 'motdepasse-solide', '--role' => 'chef'])->assertFailed();
        $this->assertSame(0, User::count());
    }
}
