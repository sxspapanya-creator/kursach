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
class ElevenMonthsDataSeeder extends Seeder
{
    public function run(): void
    {
        $totalMonths = 11;
        $label = '11 месяцев';

        // Получаем или создаем пользователя
        $user = User::first();
        if (!$user) {
            $user = User::create([
                'name' => 'Test ' . $label . ' User',
                'email' => 'test' . $totalMonths . 'months@test.com',
                'password' => Hash::make('password'),
            ]);
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
        }
        $defaultCurrencyId = $defaultCurrency->id;

        // Создаем категории доходов
        $incomeCategories = [
            ['name' => 'Зарплата', 'type' => 'income', 'color' => '#27ae60'],
            ['name' => 'Фриланс', 'type' => 'income', 'color' => '#2ecc71'],
            ['name' => 'Инвестиции', 'type' => 'income', 'color' => '#16a085'],
            ['name' => 'Подарки', 'type' => 'income', 'color' => '#f1c40f'],
        ];

        // Создаем категории расходов
        $expenseCategories = [
            ['name' => 'Продукты', 'type' => 'expense', 'color' => '#e74c3c'],
            ['name' => 'Транспорт', 'type' => 'expense', 'color' => '#3498db'],
            ['name' => 'Развлечения', 'type' => 'expense', 'color' => '#9b59b6'],
            ['name' => 'Коммунальные услуги', 'type' => 'expense', 'color' => '#f39c12'],
            ['name' => 'Одежда', 'type' => 'expense', 'color' => '#e67e22'],
            ['name' => 'Здоровье', 'type' => 'expense', 'color' => '#1abc9c'],
            ['name' => 'Кафе и рестораны', 'type' => 'expense', 'color' => '#fd79a8'],
            ['name' => 'Образование', 'type' => 'expense', 'color' => '#00cec9'],
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

        $now = Carbon::now();
        $baseIncome = 2800;
        $transactionsCount = 0;

        for ($monthOffset = $totalMonths - 1; $monthOffset >= 0; $monthOffset--) {
            $monthDate = $now->copy()->subMonths($monthOffset);
            $daysInMonth = $monthDate->daysInMonth;
            $monthName = $monthDate->translatedFormat('F Y');
            $monthNumber = $monthDate->month;

            // Сезонные коэффициенты
            $seasonalIncomeFactor = 1;
            $seasonalExpenseFactor = 1;

            if ($monthNumber == 12) {
                $seasonalIncomeFactor = 1.25;
                $seasonalExpenseFactor = 1.35;
            } elseif ($monthNumber == 1) {
                $seasonalExpenseFactor = 1.20;
            } elseif (in_array($monthNumber, [6, 7, 8])) {
                $seasonalExpenseFactor = 1.25;
            } elseif ($monthNumber == 9) {
                $seasonalExpenseFactor = 1.15;
            } elseif (in_array($monthNumber, [3, 4])) {
                $seasonalIncomeFactor = 1.05;
            }

            // Тренд роста
            $trendFactor = 1 + ($totalMonths - 1 - $monthOffset) * 0.009;

            // ========== ДОХОДЫ ==========

            // Зарплата
            $salary = round($baseIncome * $trendFactor * $seasonalIncomeFactor, 0);
            $finalSalary = max(2300, round($salary * (1 + (rand(-20, 20) / 1000)), 2));

            $transaction = Transaction::create([
                'user_id' => $userId,
                'amount' => $finalSalary,
                'currency_id' => $defaultCurrencyId,
                'type' => 'income',
                'description' => 'Зарплата за ' . $monthName,
                'date' => $monthDate->copy()->day(rand(25, 28)),
                'payment_method' => 'transfer',
            ]);
            DB::table('category_transaction')->insert([
                'category_id' => $categories['Зарплата']->id,
                'transaction_id' => $transaction->id,
            ]);
            $transactionsCount++;

            // Фриланс
            if (rand(1, 100) <= 70) {
                $freelanceAmount = round(rand(200, 650) * $trendFactor * $seasonalIncomeFactor, 2);
                $transaction = Transaction::create([
                    'user_id' => $userId,
                    'amount' => $freelanceAmount,
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

            // Инвестиции
            if (rand(1, 100) <= 45) {
                $investmentAmount = round(rand(50, 350) * $trendFactor * $seasonalIncomeFactor, 2);
                $transaction = Transaction::create([
                    'user_id' => $userId,
                    'amount' => $investmentAmount,
                    'currency_id' => $defaultCurrencyId,
                    'type' => 'income',
                    'description' => 'Дивиденды от инвестиций',
                    'date' => $monthDate->copy()->day(rand(15, 22)),
                    'payment_method' => 'transfer',
                ]);
                DB::table('category_transaction')->insert([
                    'category_id' => $categories['Инвестиции']->id,
                    'transaction_id' => $transaction->id,
                ]);
                $transactionsCount++;
            }

            // Подарки
            if ($monthNumber == 12 || $monthNumber == 4) {
                $giftAmount = rand(100, 450);
                $transaction = Transaction::create([
                    'user_id' => $userId,
                    'amount' => $giftAmount,
                    'currency_id' => $defaultCurrencyId,
                    'type' => 'income',
                    'description' => $monthNumber == 12 ? 'Новогодние подарки' : 'День рождения',
                    'date' => $monthDate->copy()->day($monthNumber == 12 ? rand(25, 30) : rand(10, 20)),
                    'payment_method' => 'cash',
                ]);
                DB::table('category_transaction')->insert([
                    'category_id' => $categories['Подарки']->id,
                    'transaction_id' => $transaction->id,
                ]);
                $transactionsCount++;
            }

            // ========== РАСХОДЫ ==========
            $baseExpensePercent = rand(70, 82) / 100;
            $targetMonthlyExpense = round($finalSalary * $baseExpensePercent * $seasonalExpenseFactor, 2);

            $expenseData = [
                'Продукты' => ['min_percent' => 25, 'max_percent' => 35],
                'Транспорт' => ['min_percent' => 8, 'max_percent' => 15],
                'Развлечения' => ['min_percent' => 5, 'max_percent' => 12],
                'Коммунальные услуги' => ['min_percent' => 12, 'max_percent' => 18],
                'Одежда' => ['min_percent' => 5, 'max_percent' => 12],
                'Здоровье' => ['min_percent' => 3, 'max_percent' => 8],
                'Кафе и рестораны' => ['min_percent' => 4, 'max_percent' => 10],
                'Образование' => ['min_percent' => 2, 'max_percent' => 6],
            ];

            $descriptions = [
                'Продукты' => ['Покупка продуктов', 'Супермаркет', 'Продукты на неделю', 'Еда'],
                'Транспорт' => ['Проездной', 'Такси', 'Бензин', 'Парковка'],
                'Развлечения' => ['Кино', 'Ресторан', 'Кафе', 'Концерт'],
                'Коммунальные услуги' => ['Комуналка', 'Электричество', 'Квартплата', 'Вода'],
                'Одежда' => ['Одежда', 'Обувь', 'Аксессуары'],
                'Здоровье' => ['Аптека', 'Врач', 'Спортзал', 'Витамины'],
                'Кафе и рестораны' => ['Кафе', 'Ресторан', 'Обед', 'Ужин'],
                'Образование' => ['Курсы', 'Учебники', 'Тренинг'],
            ];

            $paymentMethods = ['cash', 'card', 'transfer'];

            foreach ($expenseData as $categoryName => $data) {
                $percent = rand($data['min_percent'] * 10, $data['max_percent'] * 10) / 10;
                $categoryTotal = round($targetMonthlyExpense * $percent / 100, 2);

                if ($categoryTotal <= 0) continue;

                $transactionCount = rand(2, 8);
                $remaining = $categoryTotal;

                for ($i = 0; $i < $transactionCount; $i++) {
                    if ($remaining <= 0) break;

                    $amount = ($i == $transactionCount - 1)
                        ? round($remaining, 2)
                        : round($remaining * rand(10, 30) / 100, 2);

                    $remaining -= $amount;

                    $transaction = Transaction::create([
                        'user_id' => $userId,
                        'amount' => $amount,
                        'currency_id' => $defaultCurrencyId,
                        'type' => 'expense',
                        'description' => $descriptions[$categoryName][array_rand($descriptions[$categoryName])],
                        'date' => $monthDate->copy()->day(rand(1, $daysInMonth)),
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
    }
}