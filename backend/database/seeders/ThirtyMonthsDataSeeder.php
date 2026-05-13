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
 * Генерирует:
 * - 30 полных месяцев (2.5 года истории)
 * - Транзакции в текущем месяце ТОЛЬКО за прошедшие дни (до сегодня)
 * - Аномалии: 1-2 в месяц (реалистичное количество)
 *
 * 30 месяцев — это порог переключения на Holt-Winters (24+ месяца)
 *
 * Запуск: php artisan db:seed --class=ThirtyMonthsDataSeeder
 *
 * ВНИМАНИЕ: Перед запуском очистите данные пользователя самостоятельно!
 */
class ThirtyMonthsDataSeeder extends Seeder
{
    public function run(): void
    {
        // Генерируем 30 полных месяцев (без текущего)
        $completeMonths = 30;
        $label = '30 полных месяцев + текущий';

        // Получаем или создаем пользователя
        $user = User::first();
        if (!$user) {
            $user = User::create([
                'name' => 'Test ' . $label . ' User',
                'email' => 'test_30months@test.com',
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
        $baseIncome = 2500;
        $transactionsCount = 0;
        $totalAnomalies = 0;
        $anomalySum = 0;

        // ========== 1. ГЕНЕРАЦИЯ 30 ПОЛНЫХ МЕСЯЦЕВ (без текущего) ==========
        for ($i = $completeMonths; $i >= 1; $i--) {
            $monthDate = $now->copy()->subMonths($i);
            $daysInMonth = $monthDate->daysInMonth;
            $monthName = $monthDate->translatedFormat('F Y');
            $monthNumber = $monthDate->month;
            $year = $monthDate->year;

            // Счетчик аномалий в текущем месяце (максимум 2)
            $anomaliesThisMonth = 0;
            $maxAnomaliesPerMonth = rand(1, 2); // 1-2 аномалии в месяц

            // Сезонные коэффициенты
            $seasonalIncomeFactor = 1;
            $seasonalExpenseFactor = 1;

            if ($monthNumber == 12) {
                $seasonalIncomeFactor = 1.30;
                $seasonalExpenseFactor = 1.40;
            } elseif ($monthNumber == 1) {
                $seasonalExpenseFactor = 1.22;
            } elseif (in_array($monthNumber, [6, 7, 8])) {
                $seasonalExpenseFactor = 1.28;
            } elseif ($monthNumber == 9) {
                $seasonalExpenseFactor = 1.18;
            } elseif (in_array($monthNumber, [3, 4])) {
                $seasonalIncomeFactor = 1.05;
            }

            // Тренд роста за 30 месяцев (~15% всего)
            $trendFactor = 1 + ($completeMonths - $i) * 0.005;

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
                'is_anomaly' => false,
            ]);
            DB::table('category_transaction')->insert([
                'category_id' => $categories['Зарплата']->id,
                'transaction_id' => $transaction->id,
            ]);
            $transactionsCount++;

            // Фриланс (70% вероятность)
            if (rand(1, 100) <= 70) {
                $freelanceAmount = round(rand(180, 600) * $trendFactor * $seasonalIncomeFactor, 2);
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

            // Инвестиции (40% вероятность)
            if (rand(1, 100) <= 40) {
                $investmentAmount = round(rand(50, 300) * $trendFactor * $seasonalIncomeFactor, 2);
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

            // Подарки (только декабрь и апрель)
            if ($monthNumber == 12 || $monthNumber == 4) {
                $giftAmount = rand(100, 400);
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
                if ($isAnomaly) {
                    $anomaliesThisMonth++;
                    $totalAnomalies++;
                    $anomalySum += $giftAmount;
                }
            }

            // ========== РАСХОДЫ ==========
            $baseExpensePercent = rand(68, 84) / 100;
            $targetMonthlyExpense = round($finalSalary * $baseExpensePercent * $seasonalExpenseFactor * (1 + (rand(-50, 50) / 1000)), 2);

            // Минимальный порог расходов, чтобы месяц не был пустым
            if ($targetMonthlyExpense < 1800) {
                $targetMonthlyExpense = 1800;
            }

            $expenseData = [
                'Продукты' => ['min_percent' => 23, 'max_percent' => 33],
                'Транспорт' => ['min_percent' => 8, 'max_percent' => 14],
                'Развлечения' => ['min_percent' => 5, 'max_percent' => 12],
                'Коммунальные услуги' => ['min_percent' => 12, 'max_percent' => 18],
                'Одежда' => ['min_percent' => 5, 'max_percent' => 12],
                'Здоровье' => ['min_percent' => 3, 'max_percent' => 8],
                'Кафе и рестораны' => ['min_percent' => 4, 'max_percent' => 10],
                'Образование' => ['min_percent' => 2, 'max_percent' => 6],
            ];

            $descriptions = [
                'Продукты' => ['Покупка продуктов', 'Супермаркет', 'Продукты на неделю', 'Еда', 'Магазин'],
                'Транспорт' => ['Проездной', 'Такси', 'Бензин', 'Парковка', 'Метро'],
                'Развлечения' => ['Кино', 'Театр', 'Кафе', 'Концерт', 'Бильярд'],
                'Коммунальные услуги' => ['Комуналка', 'Электричество', 'Квартплата', 'Вода', 'Отопление'],
                'Одежда' => ['Одежда', 'Обувь', 'Аксессуары'],
                'Здоровье' => ['Аптека', 'Врач', 'Спортзал', 'Витамины', 'Лекарства'],
                'Кафе и рестораны' => ['Кафе', 'Ресторан', 'Обед', 'Ужин', 'Кофе'],
                'Образование' => ['Курсы', 'Учебники', 'Тренинг', 'Книги'],
            ];

            $paymentMethods = ['cash', 'card', 'transfer'];
            $hasAnyExpense = false;

            foreach ($expenseData as $categoryName => $data) {
                $percent = rand($data['min_percent'] * 10, $data['max_percent'] * 10) / 10;
                $categoryTotal = round($targetMonthlyExpense * $percent / 100, 2);

                if ($categoryTotal <= 0) continue;

                $transactionCount = rand(2, 6);
                $remaining = $categoryTotal;

                for ($j = 0; $j < $transactionCount; $j++) {
                    if ($remaining <= 0) break;

                    $amount = ($j == $transactionCount - 1)
                        ? round($remaining, 2)
                        : round($remaining * rand(10, 30) / 100, 2);

                    $remaining -= $amount;

                    $isAnomaly = false;

                    // Аномалии: только если не превысили лимит на месяц (1-2 аномалии)
                    if ($anomaliesThisMonth < $maxAnomaliesPerMonth) {
                        // Крупная покупка в категории Одежда (5% вероятность)
                        if ($categoryName == 'Одежда' && rand(1, 100) <= 5) {
                            $amount = $amount * rand(3, 5);
                            $isAnomaly = true;
                        }
                        // Крупная покупка в категории Развлечения (4% вероятность)
                        elseif ($categoryName == 'Развлечения' && rand(1, 100) <= 4) {
                            $amount = $amount * rand(3, 6);
                            $isAnomaly = true;
                        }
                        // Медицинские расходы (3% вероятность)
                        elseif ($categoryName == 'Здоровье' && rand(1, 100) <= 3) {
                            $amount = $amount * rand(2, 4);
                            $isAnomaly = true;
                        }
                    }

                    if ($isAnomaly) {
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

            // ГАРАНТИЯ: если месяц остался без расходов, добавляем принудительно
            if (!$hasAnyExpense) {
                $fallbackAmount = rand(1500, 2200);
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
                $hasAnyExpense = true;
            }

            // Крупная сезонная аномалия (например, покупка техники) - не чаще 1 раза в квартал
            $seasonalMonths = [3, 6, 9, 12];
            if (in_array($monthNumber, $seasonalMonths) && $anomaliesThisMonth < $maxAnomaliesPerMonth && rand(1, 100) <= 20) {
                $bigPurchaseAmount = rand(800, 2000);
                $transaction = Transaction::create([
                    'user_id' => $userId,
                    'amount' => $bigPurchaseAmount,
                    'currency_id' => $defaultCurrencyId,
                    'type' => 'expense',
                    'description' => 'Крупная покупка',
                    'date' => $monthDate->copy()->day(rand(1, $daysInMonth)),
                    'payment_method' => 'card',
                    'is_anomaly' => true,
                ]);
                DB::table('category_transaction')->insert([
                    'category_id' => $categories['Развлечения']->id,
                    'transaction_id' => $transaction->id,
                ]);
                $transactionsCount++;
                $anomaliesThisMonth++;
                $totalAnomalies++;
                $anomalySum += $bigPurchaseAmount;
            }

            // Отпуск (только летом, не чаще 1 раза в год)
            if (in_array($monthNumber, [7, 8]) && $anomaliesThisMonth < $maxAnomaliesPerMonth && rand(1, 100) <= 15) {
                $vacationAmount = rand(1000, 2500);
                $transaction = Transaction::create([
                    'user_id' => $userId,
                    'amount' => $vacationAmount,
                    'currency_id' => $defaultCurrencyId,
                    'type' => 'expense',
                    'description' => 'Отпуск',
                    'date' => $monthDate->copy()->day(rand(5, 25)),
                    'payment_method' => 'card',
                    'is_anomaly' => true,
                ]);
                DB::table('category_transaction')->insert([
                    'category_id' => $categories['Развлечения']->id,
                    'transaction_id' => $transaction->id,
                ]);
                $transactionsCount++;
                $anomaliesThisMonth++;
                $totalAnomalies++;
                $anomalySum += $vacationAmount;
            }
        }

        // ========== 2. ГЕНЕРАЦИЯ ТРАНЗАКЦИЙ В ТЕКУЩЕМ МЕСЯЦЕ (ТОЛЬКО ПРОШЕДШИЕ ДНИ) ==========
        $currentDate = Carbon::now();
        $currentMonth = $currentDate->month;
        $currentDay = $currentDate->day;
        $monthName = $currentDate->translatedFormat('F Y');
        $passedDays = $currentDay - 1;

        $this->command->info("📅 Генерация текущего месяца - только прошедшие дни (1-{$passedDays})");

        // Сезонные коэффициенты для текущего месяца
        $seasonalIncomeFactor = 1;
        $seasonalExpenseFactor = 1;

        if ($currentMonth == 12) {
            $seasonalIncomeFactor = 1.30;
            $seasonalExpenseFactor = 1.40;
        } elseif ($currentMonth == 1) {
            $seasonalExpenseFactor = 1.22;
        } elseif (in_array($currentMonth, [6, 7, 8])) {
            $seasonalExpenseFactor = 1.28;
        } elseif ($currentMonth == 9) {
            $seasonalExpenseFactor = 1.18;
        } elseif (in_array($currentMonth, [3, 4])) {
            $seasonalIncomeFactor = 1.05;
        }

        // Тренд для текущего месяца
        $trendFactor = 1 + $completeMonths * 0.005;

        // ========== ДОХОДЫ В ТЕКУЩЕМ МЕСЯЦЕ ==========
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

        // ========== РАСХОДЫ В ТЕКУЩЕМ МЕСЯЦЕ (ТОЛЬКО ПРОШЕДШИЕ ДНИ, БЕЗ АНОМАЛИЙ) ==========
        if ($passedDays > 0) {
            $baseExpensePercent = rand(68, 84) / 100;
            $avgMonthlyIncome = $baseIncome * $trendFactor;
            $targetMonthlyExpense = round($avgMonthlyIncome * $baseExpensePercent * $seasonalExpenseFactor, 2);

            $daysInCurrentMonth = $currentDate->daysInMonth;
            $targetExpenseForPassedDays = $targetMonthlyExpense * ($passedDays / $daysInCurrentMonth);

            $expenseData = [
                'Продукты' => ['min_percent' => 23, 'max_percent' => 33],
                'Транспорт' => ['min_percent' => 8, 'max_percent' => 14],
                'Развлечения' => ['min_percent' => 5, 'max_percent' => 12],
                'Коммунальные услуги' => ['min_percent' => 12, 'max_percent' => 18],
                'Одежда' => ['min_percent' => 5, 'max_percent' => 12],
                'Здоровье' => ['min_percent' => 3, 'max_percent' => 8],
                'Кафе и рестораны' => ['min_percent' => 4, 'max_percent' => 10],
                'Образование' => ['min_percent' => 2, 'max_percent' => 6],
            ];

            $descriptions = [
                'Продукты' => ['Покупка продуктов', 'Супермаркет', 'Продукты на неделю', 'Еда', 'Магазин'],
                'Транспорт' => ['Проездной', 'Такси', 'Бензин', 'Парковка', 'Метро'],
                'Развлечения' => ['Кино', 'Театр', 'Кафе', 'Концерт', 'Бильярд'],
                'Коммунальные услуги' => ['Комуналка', 'Электричество', 'Квартплата', 'Вода', 'Отопление'],
                'Одежда' => ['Одежда', 'Обувь', 'Аксессуары'],
                'Здоровье' => ['Аптека', 'Врач', 'Спортзал', 'Витамины', 'Лекарства'],
                'Кафе и рестораны' => ['Кафе', 'Ресторан', 'Обед', 'Ужин', 'Кофе'],
                'Образование' => ['Курсы', 'Учебники', 'Тренинг', 'Книги'],
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
        $this->command->info("📈 Метод прогноза: Holt-Winters (24+ месяца) - учитывает сезонность");
    }
}