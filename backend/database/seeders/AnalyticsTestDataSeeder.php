<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class AnalyticsTestDataSeeder extends Seeder
{
    /**
     * Seed test data for analytics for user with id 1
     */
    public function run(): void
    {
        $userId = 1;

        // Проверяем, существует ли пользователь
        if (!User::find($userId)) {
            $this->command->warn("User with id {$userId} does not exist. Creating test user...");
            User::create([
                'name' => 'Test Analytics User',
                'email' => 'analytics@test.com',
                'password' => Hash::make('password'),
            ]);
        }

        // Удаляем старые данные для пользователя
        Transaction::where('user_id', $userId)->delete();
        Category::where('user_id', $userId)->delete();

        // Создаем категории доходов
        $incomeCategories = [
            ['name' => 'Зарплата', 'type' => 'income', 'color' => '#27ae60'],
            ['name' => 'Фриланс', 'type' => 'income', 'color' => '#2ecc71'],
            ['name' => 'Инвестиции', 'type' => 'income', 'color' => '#16a085'],
        ];

        // Создаем категории расходов
        $expenseCategories = [
            ['name' => 'Продукты', 'type' => 'expense', 'color' => '#e74c3c', 'budget_limit' => 500.00],
            ['name' => 'Транспорт', 'type' => 'expense', 'color' => '#3498db', 'budget_limit' => 200.00],
            ['name' => 'Развлечения', 'type' => 'expense', 'color' => '#9b59b6', 'budget_limit' => 300.00],
            ['name' => 'Коммунальные услуги', 'type' => 'expense', 'color' => '#f39c12', 'budget_limit' => 150.00],
            ['name' => 'Одежда', 'type' => 'expense', 'color' => '#e67e22', 'budget_limit' => 400.00],
            ['name' => 'Здоровье', 'type' => 'expense', 'color' => '#1abc9c', 'budget_limit' => 250.00],
        ];

        $categories = [];

        foreach ($incomeCategories as $cat) {
            $categories[$cat['name']] = Category::create([
                'user_id' => $userId,
                'name' => $cat['name'],
                'type' => $cat['type'],
                'color' => $cat['color'],
            ]);
        }

        foreach ($expenseCategories as $cat) {
            $categories[$cat['name']] = Category::create([
                'user_id' => $userId,
                'name' => $cat['name'],
                'type' => $cat['type'],
                'color' => $cat['color'],
                'budget_limit' => $cat['budget_limit'],
            ]);
        }

        // Генерируем транзакции за последние 12 месяцев
        $now = Carbon::now();
        
        for ($monthOffset = 11; $monthOffset >= 0; $monthOffset--) {
            $monthDate = $now->copy()->subMonths($monthOffset);
            $daysInMonth = $monthDate->daysInMonth;
            
            // Доходы - зарплата в начале месяца, фриланс и инвестиции в течение месяца
            $baseSalary = 2500 + rand(-200, 500); // Зарплата с небольшими колебаниями
            $salaryDate = $monthDate->copy()->day(5);
            
            Transaction::create([
                'user_id' => $userId,
                'category_id' => $categories['Зарплата']->id,
                'amount' => $baseSalary,
                'type' => 'income',
                'description' => 'Зарплата за ' . $monthDate->translatedFormat('F Y'),
                'date' => $salaryDate,
                'payment_method' => 'transfer',
            ]);

            // Фриланс (не каждый месяц, 70% вероятность)
            if (rand(1, 10) <= 7) {
                $freelanceAmount = rand(200, 800);
                $freelanceDate = $monthDate->copy()->day(rand(10, 20));
                
                Transaction::create([
                    'user_id' => $userId,
                    'category_id' => $categories['Фриланс']->id,
                    'amount' => $freelanceAmount,
                    'type' => 'income',
                    'description' => 'Проект фриланс',
                    'date' => $freelanceDate,
                    'payment_method' => 'transfer',
                ]);
            }

            // Инвестиции (не каждый месяц, 50% вероятность)
            if (rand(1, 10) <= 5) {
                $investmentAmount = rand(100, 500);
                $investmentDate = $monthDate->copy()->day(rand(15, 25));
                
                Transaction::create([
                    'user_id' => $userId,
                    'category_id' => $categories['Инвестиции']->id,
                    'amount' => $investmentAmount,
                    'type' => 'income',
                    'description' => 'Дивиденды/прибыль',
                    'date' => $investmentDate,
                    'payment_method' => 'transfer',
                ]);
            }

            // Расходы - генерируем несколько транзакций в каждой категории
            $expenseData = [
                'Продукты' => ['count' => rand(8, 15), 'min' => 20, 'max' => 150],
                'Транспорт' => ['count' => rand(10, 20), 'min' => 5, 'max' => 30],
                'Развлечения' => ['count' => rand(3, 8), 'min' => 30, 'max' => 200],
                'Коммунальные услуги' => ['count' => 1, 'min' => 120, 'max' => 180],
                'Одежда' => ['count' => rand(0, 3), 'min' => 50, 'max' => 300],
                'Здоровье' => ['count' => rand(1, 4), 'min' => 30, 'max' => 200],
            ];

            foreach ($expenseData as $categoryName => $data) {
                for ($i = 0; $i < $data['count']; $i++) {
                    $day = rand(1, $daysInMonth);
                    $amount = rand($data['min'] * 100, $data['max'] * 100) / 100;
                    
                    $descriptions = [
                        'Продукты' => ['Покупка продуктов', 'Супермаркет', 'Продукты на неделю', 'Еда'],
                        'Транспорт' => ['Проездной', 'Такси', 'Бензин', 'Парковка', 'Общественный транспорт'],
                        'Развлечения' => ['Кино', 'Ресторан', 'Кафе', 'Концерт', 'Развлечения'],
                        'Коммунальные услуги' => ['Коммунальные услуги'],
                        'Одежда' => ['Одежда', 'Обувь', 'Аксессуары'],
                        'Здоровье' => ['Аптека', 'Врач', 'Спортзал', 'Витамины'],
                    ];
                    
                    Transaction::create([
                        'user_id' => $userId,
                        'category_id' => $categories[$categoryName]->id,
                        'amount' => $amount,
                        'type' => 'expense',
                        'description' => $descriptions[$categoryName][array_rand($descriptions[$categoryName])],
                        'date' => $monthDate->copy()->day($day),
                        'payment_method' => rand(1, 3) == 1 ? 'cash' : (rand(1, 2) == 1 ? 'card' : 'transfer'),
                    ]);
                }
            }
        }

        $this->command->info("Test analytics data created for user id {$userId}");
        $this->command->info("Categories: " . Category::where('user_id', $userId)->count());
        $this->command->info("Transactions: " . Transaction::where('user_id', $userId)->count());
    }
}

