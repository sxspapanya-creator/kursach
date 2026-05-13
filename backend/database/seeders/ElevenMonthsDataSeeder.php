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

/**
 * Сидер для генерации тестовых данных за 11 ПОЛНЫХ месяцев + текущий месяц
 *
 * Генерирует:
 * - 11 полных месяцев (июнь, июль, август, сентябрь, октябрь, ноябрь, декабрь, январь, февраль, март, апрель)
 * - Транзакции в текущем месяце ТОЛЬКО за прошедшие дни (до сегодня)
 *
 * Всего в истории будет 11 полных месяцев + частичный текущий месяц
 *
 * Запуск: php artisan db:seed --class=ElevenMonthsDataSeeder
 *
 * ВНИМАНИЕ: Перед запуском очистите данные пользователя самостоятельно!
 */
class ElevenMonthsDataSeeder extends Seeder
{
    public function run(): void
    {
        // Генерируем 11 полных месяцев (без текущего)
        $completeMonths = 11;
        $label = '11 полных месяцев + текущий';

        // Получаем или создаем пользователя
        $user = User::first();
        if (!$user) {
            $user = User::create([
                'name' => 'Test ' . $label . ' User',
                'email' => 'test_11months@test.com',
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

        // ========== 1. ГЕНЕРАЦИЯ 11 ПОЛНЫХ МЕСЯЦЕВ (без текущего) ==========
        // Начинаем с месяца, который был 11 месяцев назад
        for ($i = $completeMonths; $i >= 1; $i--) {
            $monthDate = $now->copy()->subMonths($i);
            $daysInMonth = $monthDate->daysInMonth;
            $monthName = $monthDate->translatedFormat('F Y');
            $monthNumber = $monthDate->month;
            $year = $monthDate->year;

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

            // Тренд роста за 11 месяцев (~5% всего)
            $trendFactor = 1 + ($completeMonths - $i) * 0.0045;

            // ========== ДОХОДЫ ==========

            // Зарплата
            $salary = round($baseIncome * $trendFactor * $seasonalIncomeFactor, 0);
            $finalSalary = max(2500, round($salary * (1 + (rand(-20, 20) / 1000)), 2));

            $transaction = Transaction::create([
                'user_id' => $userId,
                'amount' => $finalSalary,
                'currency_id' => $defaultCurrencyId,
                'type' => 'income',
                'description' => 'Зарплата за ' . $monthName,
                'date' => $monthDate->copy()->day(rand(25, 28)),
                'payment_method' => 'transfer',
                'is_anomaly' => false,
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
                    'is_anomaly' => false,
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
                    'is_anomaly' => false,
                ]);
                DB::table('category_transaction')->insert([
                    'category_id' => $categories['Инвестиции']->id,
                    'transaction_id' => $transaction->id,
                ]);
                $transactionsCount++;
            }

            // Подарки (разовый доход)
            if ($monthNumber == 12 || $monthNumber == 4) {
                $giftAmount = rand(100, 450);
                $isAnomaly = ($monthNumber == 4); // День рождения - разовый доход
                $transaction = Transaction::create([
                    'user_id' => $userId,
                    'amount' => $giftAmount,
                    'currency_id' => $defaultCurrencyId,
                    'type' => 'income',
                    'description' => $monthNumber == 12 ? 'Новогодние подарки' : 'День рождения',
                    'date' => $monthDate->copy()->day($monthNumber == 12 ? rand(25, 30) : rand(10, 20)),
                    'payment_method' => 'cash',
                    'is_anomaly' => $isAnomaly,
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

                for ($j = 0; $j < $transactionCount; $j++) {
                    if ($remaining <= 0) break;

                    $amount = ($j == $transactionCount - 1)
                        ? round($remaining, 2)
                        : round($remaining * rand(10, 30) / 100, 2);

                    $remaining -= $amount;

                    // Иногда добавляем аномальные расходы (крупные покупки)
                    $isAnomaly = false;

                    // Крупные покупки в категории Одежда (12% вероятность)
                    if ($categoryName == 'Одежда' && rand(1, 100) <= 12) {
                        $amount = $amount * rand(3, 7);
                        $isAnomaly = true;
                    }

                    // Крупные покупки в категории Развлечения (10% вероятность)
                    if ($categoryName == 'Развлечения' && rand(1, 100) <= 10) {
                        $amount = $amount * rand(4, 9);
                        $isAnomaly = true;
                    }

                    // Крупные покупки (техника, мебель) через категорию Развлечения
                    if ($categoryName == 'Развлечения' && rand(1, 100) <= 7) {
                        $amount = rand(600, 1800);
                        $isAnomaly = true;
                    }

                    // Медицинские расходы (аномалия)
                    if ($categoryName == 'Здоровье' && rand(1, 100) <= 8) {
                        $amount = $amount * rand(3, 6);
                        $isAnomaly = true;
                    }

                    $transaction = Transaction::create([
                        'user_id' => $userId,
                        'amount' => min($amount, $categoryTotal),
                        'currency_id' => $defaultCurrencyId,
                        'type' => 'expense',
                        'description' => $descriptions[$categoryName][array_rand($descriptions[$categoryName])],
                        'date' => $monthDate->copy()->day(rand(1, $daysInMonth)),
                        'payment_method' => $paymentMethods[array_rand($paymentMethods)],
                        'is_anomaly' => $isAnomaly,
                    ]);
                    DB::table('category_transaction')->insert([
                        'category_id' => $categories[$categoryName]->id,
                        'transaction_id' => $transaction->id,
                    ]);
                    $transactionsCount++;
                }
            }

            // Добавляем аномальные расходы в некоторые месяцы (покупка техники, мебели, ремонт)
            if (in_array($monthNumber, [9, 12, 3, 6, 8]) && rand(1, 100) <= 30) {
                $bigPurchaseAmount = rand(600, 2200);
                $descriptions_list = ['Покупка техники', 'Мебель', 'Ремонт', 'Крупная покупка', 'Электроника'];
                $transaction = Transaction::create([
                    'user_id' => $userId,
                    'amount' => $bigPurchaseAmount,
                    'currency_id' => $defaultCurrencyId,
                    'type' => 'expense',
                    'description' => $descriptions_list[array_rand($descriptions_list)],
                    'date' => $monthDate->copy()->day(rand(1, $daysInMonth)),
                    'payment_method' => 'card',
                    'is_anomaly' => true,
                ]);
                DB::table('category_transaction')->insert([
                    'category_id' => $categories['Развлечения']->id,
                    'transaction_id' => $transaction->id,
                ]);
                $transactionsCount++;
            }
        }

        // ========== 2. ГЕНЕРАЦИЯ ТРАНЗАКЦИЙ В ТЕКУЩЕМ МЕСЯЦЕ (ТОЛЬКО ПРОШЕДШИЕ ДНИ) ==========
        $currentDate = Carbon::now();
        $currentMonth = $currentDate->month;
        $currentYear = $currentDate->year;
        $currentDay = $currentDate->day;
        $monthName = $currentDate->translatedFormat('F Y');

        // Сезонные коэффициенты для текущего месяца
        $seasonalIncomeFactor = 1;
        $seasonalExpenseFactor = 1;

        if ($currentMonth == 12) {
            $seasonalIncomeFactor = 1.25;
            $seasonalExpenseFactor = 1.35;
        } elseif ($currentMonth == 1) {
            $seasonalExpenseFactor = 1.20;
        } elseif (in_array($currentMonth, [6, 7, 8])) {
            $seasonalExpenseFactor = 1.25;
        } elseif ($currentMonth == 9) {
            $seasonalExpenseFactor = 1.15;
        } elseif (in_array($currentMonth, [3, 4])) {
            $seasonalIncomeFactor = 1.05;
        }

        // Тренд для текущего месяца
        $trendFactor = 1 + $completeMonths * 0.0045;

        // ========== ДОХОДЫ В ТЕКУЩЕМ МЕСЯЦЕ (только если зарплата уже прошла) ==========
        $salaryDay = rand(25, 28);
        if ($salaryDay <= $currentDay) {
            $salary = round($baseIncome * $trendFactor * $seasonalIncomeFactor, 0);
            $finalSalary = max(2600, round($salary * (1 + (rand(-20, 20) / 1000)), 2));

            $transaction = Transaction::create([
                'user_id' => $userId,
                'amount' => $finalSalary,
                'currency_id' => $defaultCurrencyId,
                'type' => 'income',
                'description' => 'Зарплата за ' . $monthName,
                'date' => $currentDate->copy()->day($salaryDay),
                'payment_method' => 'transfer',
                'is_anomaly' => false,
            ]);
            DB::table('category_transaction')->insert([
                'category_id' => $categories['Зарплата']->id,
                'transaction_id' => $transaction->id,
            ]);
            $transactionsCount++;
        }

        // Фриланс в текущем месяце (только если дата прошла)
        if (rand(1, 100) <= 65) {
            $freelanceDay = rand(10, 20);
            if ($freelanceDay <= $currentDay) {
                $freelanceAmount = round(rand(200, 600) * $trendFactor * $seasonalIncomeFactor, 2);
                $transaction = Transaction::create([
                    'user_id' => $userId,
                    'amount' => $freelanceAmount,
                    'currency_id' => $defaultCurrencyId,
                    'type' => 'income',
                    'description' => 'Проект фриланс',
                    'date' => $currentDate->copy()->day($freelanceDay),
                    'payment_method' => 'transfer',
                    'is_anomaly' => false,
                ]);
                DB::table('category_transaction')->insert([
                    'category_id' => $categories['Фриланс']->id,
                    'transaction_id' => $transaction->id,
                ]);
                $transactionsCount++;
            }
        }

        // ========== РАСХОДЫ В ТЕКУЩЕМ МЕСЯЦЕ (ТОЛЬКО ПРОШЕДШИЕ ДНИ) ==========
        $baseExpensePercent = rand(70, 82) / 100;
        $avgMonthlyIncome = $baseIncome * $trendFactor;
        $targetMonthlyExpense = round($avgMonthlyIncome * $baseExpensePercent * $seasonalExpenseFactor, 2);

        // Пропорционально распределяем расходы на прошедшие дни
        $daysInCurrentMonth = $currentDate->daysInMonth;
        $targetExpenseForPassedDays = $targetMonthlyExpense * ($currentDay / $daysInCurrentMonth);

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
            $categoryTotal = round($targetExpenseForPassedDays * $percent / 100, 2);

            if ($categoryTotal <= 0) continue;

            $transactionCount = rand(1, 4);
            $remaining = $categoryTotal;

            for ($j = 0; $j < $transactionCount; $j++) {
                if ($remaining <= 0) break;

                $amount = ($j == $transactionCount - 1)
                    ? round($remaining, 2)
                    : round($remaining * rand(20, 50) / 100, 2);

                $remaining -= $amount;

                $day = rand(1, $currentDay);
                $transaction = Transaction::create([
                    'user_id' => $userId,
                    'amount' => $amount,
                    'currency_id' => $defaultCurrencyId,
                    'type' => 'expense',
                    'description' => $descriptions[$categoryName][array_rand($descriptions[$categoryName])],
                    'date' => $currentDate->copy()->day($day),
                    'payment_method' => $paymentMethods[array_rand($paymentMethods)],
                    'is_anomaly' => false,
                ]);
                DB::table('category_transaction')->insert([
                    'category_id' => $categories[$categoryName]->id,
                    'transaction_id' => $transaction->id,
                ]);
                $transactionsCount++;
            }
        }

        $this->command->info("✅ Создано {$transactionsCount} транзакций для пользователя {$user->email}");
        $this->command->info("📊 Полных месяцев в истории: {$completeMonths}");
        $this->command->info("📅 Текущий месяц: {$currentDate->translatedFormat('F Y')}, пройдено дней: {$currentDay}");
        $this->command->info("🏷️ Аномальные транзакции отмечены флагом is_anomaly = true");
        $this->command->info("📈 Метод прогноза: LinearRegression (7-14 месяцев)");
    }
}