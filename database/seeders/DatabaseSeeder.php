<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ReferenceSeeder::class);

        if (app()->isProduction()) {
            return;
        }

        $this->call(DemoSeeder::class);

        // Comptes de démonstration (local uniquement) — mot de passe : "password"
        foreach ([
            ['Admin MeriMap', 'admin@merimap.test', UserRole::Admin],
            ['Contributeur', 'editeur@merimap.test', UserRole::Editor],
            ['Lecteur', 'lecteur@merimap.test', UserRole::Reader],
        ] as [$name, $email, $role]) {
            User::factory()->create(['name' => $name, 'email' => $email, 'role' => $role]);
        }
    }
}
