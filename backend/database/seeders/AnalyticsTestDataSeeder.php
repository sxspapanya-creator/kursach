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
            $this->command->info("✅ Created new test user with ID: {$user->id}");
        } else {
            $this->command->info("✅ Using existing user with ID: {$user->id}");
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
            $this->command->info("✅ Created BYN currency with ID: {$defaultCurrency->id}");
        }
        $defaultCurrencyId = $defaultCurrency->id;

        // Очищаем старые данные
        DB::table('category_transaction')->whereIn('transaction_id', Transaction::where('user_id', $userId)->pluck('id'))->delete();
        Transaction::where('user_id', $userId)->delete();
        Category::where('user_id', $userId)->delete();

        $this->command->info("✅ Cleaned old data for user ID: {$userId}");

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

        $this->command->info("✅ Created " . (count($incomeCategories) + count($expenseCategories)) . " categories");

        // Генерируем транзакции за последние 24 месяца (2 года)
        $now = Carbon::now();
        $transactionsCount = 0;
        $monthlyStats = [];

        // Базовый уровень дохода с плавным ростом (+2% в год)
        $baseIncome = 2800;

        for ($monthOffset = 23; $monthOffset >= 0; $monthOffset--) {
            $monthDate = $now->copy()->subMonths($monthOffset);
            $daysInMonth = $monthDate->daysInMonth;
            $monthName = $monthDate->translatedFormat('F Y');
            $monthNumber = $monthDate->month;
            $yearNumber = $monthDate->year;

            // ========== РАСЧЁТ МЕСЯЧНОГО ФАКТОРА ==========

            // 1. Тренд роста (доходы растут на 2% в год)
            $trendFactor = 1 + (23 - $monthOffset) * 0.0015; // +0.15% в месяц ≈ +1.8% в год

            // 2. Сезонные коэффициенты для доходов
            $seasonalIncomeFactor = 1;
            // Декабрь — премия (+25% к доходу)
            if ($monthNumber == 12) {
                $seasonalIncomeFactor = 1.25;
            }
            // Март-апрель — возможны бонусы (+5%)
            if (in_array($monthNumber, [3, 4])) {
                $seasonalIncomeFactor = 1.05;
            }

            // 3. Сезонные коэффициенты для расходов
            $seasonalExpenseFactor = 1;
            // Декабрь — больше трат на подарки, праздники (+35%)
            if ($monthNumber == 12) {
                $seasonalExpenseFactor = 1.35;
            }
            // Январь — распродажи, больше трат (+20%)
            if ($monthNumber == 1) {
                $seasonalExpenseFactor = 1.20;
            }
            // Июнь-август — отпуска, больше трат (+25%)
            if (in_array($monthNumber, [6, 7, 8])) {
                $seasonalExpenseFactor = 1.25;
            }
            // Сентябрь — подготовка к школе/учебе (+15%)
            if ($monthNumber == 9) {
                $seasonalExpenseFactor = 1.15;
            }
            // Февраль, ноябрь — обычные месяцы (без коэффициента)

            // ========== ДОХОДЫ ==========

            // Зарплата (основной доход)
            $salary = round($baseIncome * $trendFactor * $seasonalIncomeFactor, 0);
            // Небольшой случайный разброс ±3% (без аномалий)
            $variation = rand(-30, 30) / 1000;
            $finalSalary = max(2000, round($salary * (1 + $variation), 2));

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

            // Фриланс (75% вероятность)
            if (rand(1, 100) <= 75) {
                $freelanceBase = rand(200, 600);
                $freelanceAmount = round($freelanceBase * $trendFactor * $seasonalIncomeFactor, 2);
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

            // Инвестиции (50% вероятность)
            if (rand(1, 100) <= 50) {
                $investmentBase = rand(50, 300);
                $investmentAmount = round($investmentBase * $trendFactor * $seasonalIncomeFactor, 2);
                $transaction = Transaction::create([
                    'user_id' => $userId,
                    'amount' => $investmentAmount,
                    'currency_id' => $defaultCurrencyId,
                    'type' => 'income',
                    'description' => 'Дивиденды / доход от инвестиций',
                    'date' => $monthDate->copy()->day(rand(15, 22)),
                    'payment_method' => 'transfer',
                ]);
                DB::table('category_transaction')->insert([
                    'category_id' => $categories['Инвестиции']->id,
                    'transaction_id' => $transaction->id,
                ]);
                $transactionsCount++;
            }

            // Подарки (декабрь и апрель — день рождения)
            if ($monthNumber == 12 || $monthNumber == 4) {
                $giftAmount = rand(100, 400);
                $transaction = Transaction::create([
                    'user_id' => $userId,
                    'amount' => $giftAmount,
                    'currency_id' => $defaultCurrencyId,
                    'type' => 'income',
                    'description' => $monthNumber == 12 ? 'Подарки на Новый год' : 'День рождения',
                    'date' => $monthDate->copy()->day($monthNumber == 12 ? rand(25, 30) : rand(10, 20)),
                    'payment_method' => 'cash',
                ]);
                DB::table('category_transaction')->insert([
                    'category_id' => $categories['Подарки']->id,
                    'transaction_id' => $transaction->id,
                ]);
                $transactionsCount++;
            }

            // ========== РАСХОДЫ (более хаотично, но без аномалий) ==========

            // Базовая сумма расходов (70-85% от дохода)
            $baseExpensePercent = rand(70, 85) / 100;
            $baseExpenseTotal = round($finalSalary * $baseExpensePercent, 2);

            // Применяем сезонный коэффициент
            $targetMonthlyExpense = round($baseExpenseTotal * $seasonalExpenseFactor, 2);

            // Добавляем случайную вариацию ±10% (без выбросов)
            $expenseVariation = rand(-100, 100) / 1000;
            $targetMonthlyExpense = round($targetMonthlyExpense * (1 + $expenseVariation), 2);

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
                'Продукты' => ['Покупка продуктов', 'Супермаркет', 'Продукты на неделю', 'Еда', 'Магазин'],
                'Транспорт' => ['Проездной', 'Такси', 'Бензин', 'Парковка', 'Общественный транспорт'],
                'Развлечения' => ['Кино', 'Ресторан', 'Кафе', 'Концерт', 'Развлечения'],
                'Коммунальные услуги' => ['Коммунальные услуги', 'Электричество', 'Квартплата', 'Вода'],
                'Одежда' => ['Одежда', 'Обувь', 'Аксессуары'],
                'Здоровье' => ['Аптека', 'Врач', 'Спортзал', 'Витамины', 'Лекарства'],
                'Кафе и рестораны' => ['Кафе', 'Ресторан', 'Обед', 'Ужин', 'Кофе с собой'],
                'Образование' => ['Курсы', 'Учебники', 'Тренинг', 'Онлайн-курс'],
            ];

            $paymentMethods = ['cash', 'card', 'transfer'];

            $monthlyExpenseTotal = 0;

            foreach ($expenseData as $categoryName => $data) {
                // Рандомное распределение процента в заданном диапазоне
                $percent = rand($data['min_percent'] * 10, $data['max_percent'] * 10) / 10;
                $categoryTotal = round($targetMonthlyExpense * $percent / 100, 2);

                if ($categoryTotal <= 0) continue;

                // Разбиваем сумму на несколько транзакций (от 2 до 8)
                $transactionCount = rand(2, 8);
                $remaining = $categoryTotal;

                for ($i = 0; $i < $transactionCount; $i++) {
                    if ($remaining <= 0) break;

                    // Последняя транзакция забирает остаток
                    $amount = ($i == $transactionCount - 1)
                        ? round($remaining, 2)
                        : round($remaining * rand(10, 30) / 100, 2);

                    $remaining -= $amount;
                    $day = rand(1, $daysInMonth);

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
                    $monthlyExpenseTotal += $amount;
                }
            }

            // Собираем статистику
            $monthlyIncomeTotal = Transaction::where('user_id', $userId)
                ->whereYear('date', $yearNumber)
                ->whereMonth('date', $monthNumber)
                ->where('type', 'income')
                ->sum('amount');

            $monthlyStats[] = [
                'month' => $monthDate->format('Y-m'),
                'income' => $monthlyIncomeTotal,
                'expense' => $monthlyExpenseTotal,
                'balance' => $monthlyIncomeTotal - $monthlyExpenseTotal
            ];
        }

        $this->command->info("✅ Test data generation completed!");
        $this->command->info("📊 Period: Last 24 months (" . Carbon::now()->subMonths(23)->format('F Y') . " - " . Carbon::now()->format('F Y') . ")");
        $this->command->info("👤 User ID: {$userId}");
        $this->command->info("💰 Currency: BYN (ID: {$defaultCurrencyId})");
        $this->command->info("📁 Categories: " . Category::where('user_id', $userId)->count());
        $this->command->info("📝 Transactions: " . $transactionsCount);

        $this->command->info("\n📈 Monthly summary (last 12 months):");
        $last12 = array_slice($monthlyStats, -12);
        foreach ($last12 as $stat) {
            $balanceColor = $stat['balance'] >= 0 ? '🟢' : '🔴';
            $this->command->info("  {$stat['month']}: Доходы: {$stat['income']} Br, Расходы: {$stat['expense']} Br, Баланс: {$balanceColor} {$stat['balance']} Br");
        }
    }
}