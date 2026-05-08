<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Currency;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class AnalyticsTestDataSeeder extends Seeder
{
    public function run(): void
    {
        // Получаем или создаем пользователя
        $user = User::first();
        if (!$user) {
            $user = User::create([
                'name' => 'Test Analytics User',
                'email' => 'analytics@test.com',
                'password' => Hash::make('password'),
            ]);
            $this->command->info(" Created new test user with ID: {$user->id}");
        } else {
            $this->command->info(" Using existing user with ID: {$user->id}");
        }
        $userId = $user->id;

        // Получаем валюту BYN
        $defaultCurrency = Currency::where('code', 'BYN')->first();
        if (!$defaultCurrency) {
            $defaultCurrency = Currency::create([
                'code' => 'BYN',
                'name' => 'Белорусский рубль',
                'symbol' => 'Br',
                'is_base' => true,
            ]);
            $this->command->info(" Created BYN currency with ID: {$defaultCurrency->id}");
        }
        $defaultCurrencyId = $defaultCurrency->id;

        // Очищаем старые данные (сначала связи, потом транзакции, потом категории)
        DB::table('category_transaction')->whereIn('transaction_id', Transaction::where('user_id', $userId)->pluck('id'))->delete();
        Transaction::where('user_id', $userId)->delete();
        Category::where('user_id', $userId)->delete();

        $this->command->info(" Cleaned old data for user ID: {$userId}");

        // Создаем категории доходов
        $incomeCategories = [
            ['name' => 'Зарплата', 'type' => 'income', 'color' => '#27ae60'],
            ['name' => 'Фриланс', 'type' => 'income', 'color' => '#2ecc71'],
            ['name' => 'Инвестиции', 'type' => 'income', 'color' => '#16a085'],
        ];

        // Создаем категории расходов
        $expenseCategories = [
            ['name' => 'Продукты', 'type' => 'expense', 'color' => '#e74c3c'],
            ['name' => 'Транспорт', 'type' => 'expense', 'color' => '#3498db'],
            ['name' => 'Развлечения', 'type' => 'expense', 'color' => '#9b59b6'],
            ['name' => 'Коммунальные услуги', 'type' => 'expense', 'color' => '#f39c12'],
            ['name' => 'Одежда', 'type' => 'expense', 'color' => '#e67e22'],
            ['name' => 'Здоровье', 'type' => 'expense', 'color' => '#1abc9c'],
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
            ]);
        }

        $this->command->info(" Created " . (count($incomeCategories) + count($expenseCategories)) . " categories");

        // Генерируем транзакции за последние 12 месяцев
        $now = Carbon::now();
        $transactionsCount = 0;

        for ($monthOffset = 11; $monthOffset >= 0; $monthOffset--) {
            $monthDate = $now->copy()->subMonths($monthOffset);
            $daysInMonth = $monthDate->daysInMonth;
            $monthName = $monthDate->translatedFormat('F Y');

            // === ДОХОДЫ ===

            // Зарплата
            $baseSalary = 2500 + rand(-200, 500);
            $transaction = Transaction::create([
                'user_id' => $userId,
                'amount' => $baseSalary,
                'currency_id' => $defaultCurrencyId,
                'type' => 'income',
                'description' => 'Зарплата за ' . $monthName,
                'date' => $monthDate->copy()->day(5),
                'payment_method' => 'transfer',
            ]);
            DB::table('category_transaction')->insert([
                'category_id' => $categories['Зарплата']->id,
                'transaction_id' => $transaction->id,
            ]);
            $transactionsCount++;

            // Фриланс (70% вероятность)
            if (rand(1, 10) <= 7) {
                $transaction = Transaction::create([
                    'user_id' => $userId,
                    'amount' => rand(200, 800),
                    'currency_id' => $defaultCurrencyId,
                    'type' => 'income',
                    'description' => 'Проект фриланс',
                    'date' => $monthDate->copy()->day(rand(10, 20)),
                    'payment_method' => 'transfer',
                ]);
                DB::table('category_transaction')->insert([
                    'category_id' => $categories['Фриланс']->id,
                    'transaction_id' => $transaction->id,
                ]);
                $transactionsCount++;
            }

            // Инвестиции (50% вероятность)
            if (rand(1, 10) <= 5) {
                $transaction = Transaction::create([
                    'user_id' => $userId,
                    'amount' => rand(100, 500),
                    'currency_id' => $defaultCurrencyId,
                    'type' => 'income',
                    'description' => 'Дивиденды / доход от инвестиций',
                    'date' => $monthDate->copy()->day(rand(15, 25)),
                    'payment_method' => 'transfer',
                ]);
                DB::table('category_transaction')->insert([
                    'category_id' => $categories['Инвестиции']->id,
                    'transaction_id' => $transaction->id,
                ]);
                $transactionsCount++;
            }

            // === РАСХОДЫ ===

            $expenseData = [
                'Продукты' => ['count' => rand(8, 15), 'min' => 20, 'max' => 150],
                'Транспорт' => ['count' => rand(10, 20), 'min' => 5, 'max' => 30],
                'Развлечения' => ['count' => rand(3, 8), 'min' => 30, 'max' => 200],
                'Коммунальные услуги' => ['count' => 1, 'min' => 120, 'max' => 180],
                'Одежда' => ['count' => rand(0, 3), 'min' => 50, 'max' => 300],
                'Здоровье' => ['count' => rand(1, 4), 'min' => 30, 'max' => 200],
            ];

            $descriptions = [
                'Продукты' => ['Покупка продуктов', 'Супермаркет', 'Продукты на неделю', 'Еда', 'Магазин'],
                'Транспорт' => ['Проездной', 'Такси', 'Бензин', 'Парковка', 'Общественный транспорт'],
                'Развлечения' => ['Кино', 'Ресторан', 'Кафе', 'Концерт', 'Развлечения'],
                'Коммунальные услуги' => ['Коммунальные услуги', 'Электричество', 'Квартплата', 'Вода'],
                'Одежда' => ['Одежда', 'Обувь', 'Аксессуары'],
                'Здоровье' => ['Аптека', 'Врач', 'Спортзал', 'Витамины', 'Лекарства'],
            ];

            $paymentMethods = ['cash', 'card', 'transfer'];

            foreach ($expenseData as $categoryName => $data) {
                for ($i = 0; $i < $data['count']; $i++) {
                    $day = rand(1, $daysInMonth);
                    $amount = rand($data['min'] * 100, $data['max'] * 100) / 100;

                    $transaction = Transaction::create([
                        'user_id' => $userId,
                        'amount' => $amount,
                        'currency_id' => $defaultCurrencyId,
                        'type' => 'expense',
                        'description' => $descriptions[$categoryName][array_rand($descriptions[$categoryName])],
                        'date' => $monthDate->copy()->day($day),
                        'payment_method' => $paymentMethods[array_rand($paymentMethods)],
                    ]);
                    DB::table('category_transaction')->insert([
                        'category_id' => $categories[$categoryName]->id,
                        'transaction_id' => $transaction->id,
                    ]);
                    $transactionsCount++;
                }
            }
        }

        $this->command->info(" Test data generation completed!");
        $this->command->info(" User ID: {$userId}");
        $this->command->info(" Currency: BYN (ID: {$defaultCurrencyId})");
        $this->command->info(" Categories: " . Category::where('user_id', $userId)->count());
        $this->command->info(" Transactions: " . $transactionsCount);
        $this->command->info(" Category-Transaction links: " . DB::table('category_transaction')->count());
    }
}