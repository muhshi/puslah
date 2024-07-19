<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@gmail.com',
            'password' => bcrypt('admin'),
        ]);
        User::factory()->create([
            'name' => 'Muhshi',
            'email' => 'amuhshi@gmail.com',
            'password' => bcrypt('muhshi'),
        ]);
        User::factory()->create([
            'name' => 'Masykuri Zaen',
            'email' => 'zaen@gmail.com',
            'password' => bcrypt('zaen'),
        ]);

        $this->call(OfficeSeeder::class);
        $this->call(ShiftSeeder::class);
    }
}
