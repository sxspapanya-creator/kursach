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
        // Создаем тестового пользователя
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call([
            FifteenMonthsDataSeeder::class,
            ElevenMonthsDataSeeder::class,
            SixMonthsDataSeeder::class,
            EightMonthsDataSeeder::class,
            SevenMonthsDataSeeder::class,
            TwentyMonthsDataSeeder::class,
            AnalyticsTestDataSeeder::class,
            PlansSeeder::class
        ]);
    }
}