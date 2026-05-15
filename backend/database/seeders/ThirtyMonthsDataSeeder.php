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
 * Сидер для генерации тестовых данных за 30 ПОЛНЫХ месяцев + текущий месяц
 *
 * ОСОБЕННОСТИ (МЯГКАЯ ВЕРСИЯ):
 * - Меньше аномалий (0-1 в месяц вместо 1-2)
 * - Более плавные изменения (меньше случайных скачков)
 * - Сезонность выражена мягче (коэффициенты снижены)
 * - Расходы более стабильные, меньше волатильности
 */
class ThirtyMonthsDataSeeder extends Seeder
{
    public function run(): void
    {
        $completeMonths = 30;
        $label = '30 полных месяцев + текущий (мягкая версия)';

        // Получаем или создаем пользователя
        $user = User::first();
        if (!$user) {
            $user = User::create([
                'name' => 'Test ' . $label . ' User',
                'email' => 'test_30months_soft@test.com',
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

        // Очищаем старые данные пользователя
        $this->command->info('🗑️ Очистка старых данных...');
        DB::table('category_transaction')->whereIn('transaction_id', Transaction::where('user_id', $userId)->pluck('id'))->delete();
        Transaction::where('user_id', $userId)->delete();
        Category::where('user_id', $userId)->delete();

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

        $this->command->info('📁 Создание категорий...');
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
        $baseIncome = 2800; // Увеличил базовый доход для лучших пропорций
        $transactionsCount = 0;
        $totalAnomalies = 0;
        $anomalySum = 0;

        // Сглаженный тренд (очень мягкий)
        $trendGrowthRate = 0.003; // 0.3% в месяц (~10% за 30 месяцев)

        // ========== 1. ГЕНЕРАЦИЯ 30 ПОЛНЫХ МЕСЯЦЕВ ==========
        for ($i = $completeMonths; $i >= 1; $i--) {
            $monthDate = $now->copy()->subMonths($i);
            $daysInMonth = $monthDate->daysInMonth;
            $monthName = $monthDate->translatedFormat('F Y');
            $monthNumber = $monthDate->month;
            $year = $monthDate->year;

            // МЯГКИЕ сезонные коэффициенты
            $seasonalIncomeFactor = 1;
            $seasonalExpenseFactor = 1;

            if ($monthNumber == 12) {
                $seasonalIncomeFactor = 1.15;  // было 1.30
                $seasonalExpenseFactor = 1.20;  // было 1.40
            } elseif ($monthNumber == 1) {
                $seasonalExpenseFactor = 1.10;  // было 1.22
            } elseif (in_array($monthNumber, [6, 7, 8])) {
                $seasonalExpenseFactor = 1.10;  // было 1.28
            } elseif ($monthNumber == 9) {
                $seasonalExpenseFactor = 1.05;  // было 1.18
            } elseif (in_array($monthNumber, [3, 4])) {
                $seasonalIncomeFactor = 1.03;  // было 1.05
            }

            // Мягкий тренд (плавный рост)
            $trendFactor = 1 + ($completeMonths - $i) * $trendGrowthRate;

            // Случайные колебания (уменьшены с ±2% до ±1%)
            $randomFactor = 1 + (rand(-10, 10) / 1000);

            // ========== ДОХОДЫ ==========
            // Зарплата (регулярно, без пропусков)
            $salary = round($baseIncome * $trendFactor * $seasonalIncomeFactor * $randomFactor, 0);
            $finalSalary = max(2500, $salary);

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

            // Фриланс (50% вероятность, вместо 70%)
            if (rand(1, 100) <= 50) {
                $freelanceAmount = round(rand(200, 500) * $trendFactor * $seasonalIncomeFactor * $randomFactor, 2);
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

            // Инвестиции (20% вероятность, вместо 40%)
            if (rand(1, 100) <= 20) {
                $investmentAmount = round(rand(50, 200) * $trendFactor * $seasonalIncomeFactor * $randomFactor, 2);
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

            // Подарки (только декабрь)
            if ($monthNumber == 12 && rand(1, 100) <= 60) {
                $giftAmount = rand(150, 350);
                $transaction = Transaction::create([
                    'user_id' => $userId,
                    'amount' => $giftAmount,
                    'currency_id' => $defaultCurrencyId,
                    'type' => 'income',
                    'description' => 'Новогодние подарки',
                    'date' => $monthDate->copy()->day(rand(25, 30)),
                    'payment_method' => 'cash',
                    'is_anomaly' => false,
                ]);
                DB::table('category_transaction')->insert([
                    'category_id' => $categories['Подарки']->id,
                    'transaction_id' => $transaction->id,
                ]);
                $transactionsCount++;
            }

            // ========== РАСХОДЫ ==========
            $baseExpensePercent = rand(70, 78) / 100; // более узкий диапазон
            $targetMonthlyExpense = round($finalSalary * $baseExpensePercent * $seasonalExpenseFactor * $randomFactor, 2);

            // Минимальный порог расходов
            if ($targetMonthlyExpense < 2000) {
                $targetMonthlyExpense = 2000;
            }

            // Более стабильные проценты распределения
            $expenseData = [
                'Продукты' => ['min_percent' => 28, 'max_percent' => 32],
                'Транспорт' => ['min_percent' => 10, 'max_percent' => 12],
                'Развлечения' => ['min_percent' => 6, 'max_percent' => 8],
                'Коммунальные услуги' => ['min_percent' => 14, 'max_percent' => 16],
                'Одежда' => ['min_percent' => 6, 'max_percent' => 8],
                'Здоровье' => ['min_percent' => 4, 'max_percent' => 6],
                'Кафе и рестораны' => ['min_percent' => 5, 'max_percent' => 7],
                'Образование' => ['min_percent' => 3, 'max_percent' => 5],
            ];

            $descriptions = [
                'Продукты' => ['Покупка продуктов', 'Супермаркет', 'Продукты на неделю', 'Еда'],
                'Транспорт' => ['Проездной', 'Такси', 'Бензин', 'Парковка'],
                'Развлечения' => ['Кино', 'Театр', 'Концерт'],
                'Коммунальные услуги' => ['Квартплата', 'Электричество', 'Вода'],
                'Одежда' => ['Одежда', 'Обувь'],
                'Здоровье' => ['Аптека', 'Врач', 'Спортзал'],
                'Кафе и рестораны' => ['Кафе', 'Ресторан', 'Обед'],
                'Образование' => ['Курсы', 'Книги'],
            ];

            $paymentMethods = ['cash', 'card', 'transfer'];
            $hasAnyExpense = false;

            // Счетчик аномалий в месяце (максимум 1, а не 2)
            $anomaliesThisMonth = 0;
            $maxAnomaliesPerMonth = 1;

            foreach ($expenseData as $categoryName => $data) {
                $percent = rand($data['min_percent'] * 10, $data['max_percent'] * 10) / 10;
                $categoryTotal = round($targetMonthlyExpense * $percent / 100, 2);

                if ($categoryTotal <= 0) continue;

                $transactionCount = rand(2, 5);
                $remaining = $categoryTotal;

                for ($j = 0; $j < $transactionCount; $j++) {
                    if ($remaining <= 0) break;

                    $amount = ($j == $transactionCount - 1)
                        ? round($remaining, 2)
                        : round($remaining * rand(20, 40) / 100, 2);

                    $remaining -= $amount;

                    $isAnomaly = false;

                    // МЯГКИЕ аномалии (только 1 в месяц, меньший множитель)
                    if ($anomaliesThisMonth < $maxAnomaliesPerMonth && rand(1, 100) <= 8) {
                        $amount = $amount * rand(2, 3); // множитель 2-3 вместо 3-6
                        $isAnomaly = true;
                        $anomaliesThisMonth++;
                        $totalAnomalies++;
                        $anomalySum += $amount;
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
                    $hasAnyExpense = true;
                }
            }

            // Гарантия расходов
            if (!$hasAnyExpense) {
                $fallbackAmount = rand(1800, 2200);
                $transaction = Transaction::create([
                    'user_id' => $userId,
                    'amount' => $fallbackAmount,
                    'currency_id' => $defaultCurrencyId,
                    'type' => 'expense',
                    'description' => 'Прочие расходы',
                    'date' => $monthDate->copy()->day(rand(1, $daysInMonth)),
                    'payment_method' => 'card',
                    'is_anomaly' => false,
                ]);
                DB::table('category_transaction')->insert([
                    'category_id' => $categories['Продукты']->id,
                    'transaction_id' => $transaction->id,
                ]);
                $transactionsCount++;
            }
        }

        // ========== 2. ГЕНЕРАЦИЯ ТЕКУЩЕГО МЕСЯЦА ==========
        $currentDate = Carbon::now();
        $currentDay = $currentDate->day;
        $monthName = $currentDate->translatedFormat('F Y');
        $passedDays = $currentDay - 1;

        $this->command->info("📅 Генерация текущего месяца - только прошедшие дни (1-{$passedDays})");

        // Сезонные коэффициенты для текущего месяца
        $currentMonth = $currentDate->month;
        $seasonalIncomeFactor = 1;
        $seasonalExpenseFactor = 1;

        if ($currentMonth == 12) {
            $seasonalIncomeFactor = 1.15;
            $seasonalExpenseFactor = 1.20;
        } elseif ($currentMonth == 1) {
            $seasonalExpenseFactor = 1.10;
        } elseif (in_array($currentMonth, [6, 7, 8])) {
            $seasonalExpenseFactor = 1.10;
        } elseif ($currentMonth == 9) {
            $seasonalExpenseFactor = 1.05;
        } elseif (in_array($currentMonth, [3, 4])) {
            $seasonalIncomeFactor = 1.03;
        }

        // Тренд для текущего месяца
        $trendFactor = 1 + $completeMonths * $trendGrowthRate;
        $randomFactor = 1 + (rand(-10, 10) / 1000);

        // Доходы в текущем месяце
        $salaryDay = rand(25, 28);
        if ($salaryDay <= $currentDay) {
            $salary = round($baseIncome * $trendFactor * $seasonalIncomeFactor * $randomFactor, 0);
            $finalSalary = max(2500, $salary);

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

        // Расходы в текущем месяце (только прошедшие дни, без аномалий)
        if ($passedDays > 0) {
            $baseExpensePercent = rand(70, 78) / 100;
            $avgMonthlyIncome = $baseIncome * $trendFactor;
            $targetMonthlyExpense = round($avgMonthlyIncome * $baseExpensePercent * $seasonalExpenseFactor, 2);

            $daysInCurrentMonth = $currentDate->daysInMonth;
            $targetExpenseForPassedDays = $targetMonthlyExpense * ($passedDays / $daysInCurrentMonth);

            $expenseData = [
                'Продукты' => ['min_percent' => 28, 'max_percent' => 32],
                'Транспорт' => ['min_percent' => 10, 'max_percent' => 12],
                'Развлечения' => ['min_percent' => 6, 'max_percent' => 8],
                'Коммунальные услуги' => ['min_percent' => 14, 'max_percent' => 16],
                'Одежда' => ['min_percent' => 6, 'max_percent' => 8],
                'Здоровье' => ['min_percent' => 4, 'max_percent' => 6],
                'Кафе и рестораны' => ['min_percent' => 5, 'max_percent' => 7],
                'Образование' => ['min_percent' => 3, 'max_percent' => 5],
            ];

            $descriptions = [
                'Продукты' => ['Покупка продуктов', 'Супермаркет', 'Продукты на неделю', 'Еда'],
                'Транспорт' => ['Проездной', 'Такси', 'Бензин', 'Парковка'],
                'Развлечения' => ['Кино', 'Театр', 'Концерт'],
                'Коммунальные услуги' => ['Квартплата', 'Электричество', 'Вода'],
                'Одежда' => ['Одежда', 'Обувь'],
                'Здоровье' => ['Аптека', 'Врач', 'Спортзал'],
                'Кафе и рестораны' => ['Кафе', 'Ресторан', 'Обед'],
                'Образование' => ['Курсы', 'Книги'],
            ];

            $paymentMethods = ['cash', 'card', 'transfer'];

            foreach ($expenseData as $categoryName => $data) {
                $percent = rand($data['min_percent'] * 10, $data['max_percent'] * 10) / 10;
                $categoryTotal = round($targetExpenseForPassedDays * $percent / 100, 2);

                if ($categoryTotal <= 0) continue;

                $transactionCount = rand(1, 3);
                $remaining = $categoryTotal;

                for ($j = 0; $j < $transactionCount; $j++) {
                    if ($remaining <= 0) break;

                    $amount = ($j == $transactionCount - 1)
                        ? round($remaining, 2)
                        : round($remaining * rand(25, 50) / 100, 2);

                    $remaining -= $amount;

                    $day = rand(1, $passedDays);
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
        } else {
            $this->command->info("📅 Сегодня первый день месяца, расходы за текущий месяц не созданы");
        }

        $this->command->info("✅ Создано {$transactionsCount} транзакций для пользователя {$user->email}");
        $this->command->info("📊 Полных месяцев в истории: {$completeMonths}");
        $this->command->info("🏷️ Аномальных транзакций: {$totalAnomalies} (≈ " . round($totalAnomalies / $completeMonths, 1) . " в месяц)");
        $this->command->info("💰 Сумма аномалий: " . round($anomalySum, 2) . " BYN");
        $this->command->info("📅 Текущий месяц: {$currentDate->translatedFormat('F Y')}, пройдено дней: " . max(0, $passedDays));
        $this->command->info("📈 Ожидаемый MAPE: ~15-25% (умеренная волатильность)");
    }
}