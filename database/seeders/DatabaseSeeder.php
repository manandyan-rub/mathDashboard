<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'role' => User::ROLE_ADMIN,
                'password' => Hash::make('password'),
            ]
        );
        $admin->forceFill(['email_verified_at' => now()])->save();

        $this->call(CountrySeeder::class);
        $this->call(FormulaSeeder::class);
        $this->call(MathLevelsSeeder::class);
        $this->call(DashboardDemoSeeder::class);
    }
}
