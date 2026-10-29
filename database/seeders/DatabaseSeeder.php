<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            CategorySeeder::class,
            USAStateTaxSeeder::class,
            CarouselSlideSeeder::class,
        ]);

        $seedUsers = [
            ['name' => 'Super Admin', 'email' => 'super-admin@example.com', 'role' => 'super-admin'],
            ['name' => 'Admin', 'email' => 'admin@example.com', 'role' => 'admin'],
            ['name' => 'Test Customer', 'email' => 'customer@example.com', 'role' => 'customer'],
        ];

        foreach ($seedUsers as $seedUser) {
            $user = User::firstOrCreate(
                ['email' => $seedUser['email']],
                ['name' => $seedUser['name'], 'password' => Hash::make('password')]
            );
            $user->syncRoles([$seedUser['role']]);
        }
    }
}
