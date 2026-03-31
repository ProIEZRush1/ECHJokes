<?php

namespace Database\Seeders;

use App\Models\User;
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
        // Admin user
        User::factory()->create([
            'name' => 'Eduardo',
            'email' => 'edumaucherni@gmail.com',
            'password' => 'Eduardo2006!',
            'email_verified_at' => now(),
            'is_admin' => true,
        ]);

        // Seed fallback joke templates
        $this->call(JokeTemplateSeeder::class);
    }
}
