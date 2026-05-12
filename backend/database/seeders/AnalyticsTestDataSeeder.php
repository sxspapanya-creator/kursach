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

        // Очищаем старые данные
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

        // ========== 2.5 ГОДА = 30 МЕСЯЦЕВ ==========
        $totalMonths = 30; // 2.5 года
        $now = Carbon::now();

        // Базовый уровень дохода с плавным ростом
        $baseIncome = 2500; // Начальный доход 2.5 года назад

        for ($monthOffset = $totalMonths - 1; $monthOffset >= 0; $monthOffset--) {
            $monthDate = $now->copy()->subMonths($monthOffset);
            $daysInMonth = $monthDate->daysInMonth;
            $monthName = $monthDate->translatedFormat('F Y');
            $monthNumber = $monthDate->month;
            $year = $monthDate->year;

            // Сезонные коэффициенты
            $seasonalIncomeFactor = 1;
            $seasonalExpenseFactor = 1;

            // Декабрь — высокие доходы (премии) и расходы
            if ($monthNumber == 12) {
                $seasonalIncomeFactor = 1.3;      // Премия в декабре
                $seasonalExpenseFactor = 1.35;   // Подарки, праздники
            }
            // Январь — повышенные расходы (новогодние траты)
            elseif ($monthNumber == 1) {
                $seasonalExpenseFactor = 1.20;
            }
            // Летние месяцы (июнь, июль, август) — отпуска
            elseif (in_array($monthNumber, [6, 7, 8])) {
                $seasonalExpenseFactor = 1.25;
            }
            // Сентябрь — подготовка к учебному году
            elseif ($monthNumber == 9) {
                $seasonalExpenseFactor = 1.15;
            }
            // Март, апрель — возможные премии
            elseif (in_array($monthNumber, [3, 4])) {
                $seasonalIncomeFactor = 1.05;
            }

            // Тренд роста (от прошлого к настоящему)
            // За 2.5 года доход вырос на ~30%
            $trendFactor = 1 + ($totalMonths - 1 - $monthOffset) * 0.01;

            // ========== ДОХОДЫ ==========

            // 1. Зарплата (всегда)
            $salary = round($baseIncome * $trendFactor * $seasonalIncomeFactor, 0);
            $finalSalary = max(1800, round($salary * (1 + (rand(-30, 30) / 1000)), 2));

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

            // 2. Фриланс (80% вероятность, чаще чем раньше)
            if (rand(1, 100) <= 80) {
                $freelanceAmount = round(rand(150, 700) * $trendFactor * $seasonalIncomeFactor, 2);
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
            }

            // 3. Инвестиции (60% вероятность)
            if (rand(1, 100) <= 60) {
                $investmentAmount = round(rand(50, 400) * $trendFactor * $seasonalIncomeFactor, 2);
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
            }

            // 4. Подарки (декабрь, апрель, день рождения)
            $giftMonths = [12, 4];
            // Дополнительный месяц для подарков (летом)
            if (in_array($monthNumber, $giftMonths) || ($monthNumber == 7 && rand(1, 100) <= 30)) {
                $giftAmount = rand(100, 500);
                $transaction = Transaction::create([
                    'user_id' => $userId,
                    'amount' => $giftAmount,
                    'currency_id' => $defaultCurrencyId,
                    'type' => 'income',
                    'description' => $monthNumber == 12 ? 'Новогодние подарки' : ($monthNumber == 4 ? 'День рождения' : 'Подарки от родственников'),
                    'date' => $monthDate->copy()->day($monthNumber == 12 ? rand(25, 30) : rand(10, 20)),
                    'payment_method' => 'cash',
                ]);
                DB::table('category_transaction')->insert([
                    'category_id' => $categories['Подарки']->id,
                    'transaction_id' => $transaction->id,
                ]);
            }

            // ========== РАСХОДЫ ==========
            $baseExpensePercent = rand(65, 85) / 100;
            $targetMonthlyExpense = round($finalSalary * $baseExpensePercent * $seasonalExpenseFactor * (1 + (rand(-100, 100) / 1000)), 2);

            $expenseData = [
                'Продукты' => ['min_percent' => 22, 'max_percent' => 32],
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
                'Развлечения' => ['Кино', 'Ресторан', 'Кафе', 'Концерт', 'Бильярд'],
                'Коммунальные услуги' => ['Комуналка', 'Электричество', 'Квартплата', 'Вода', 'Отопление'],
                'Одежда' => ['Одежда', 'Обувь', 'Аксессуары', 'Мебель'],
                'Здоровье' => ['Аптека', 'Врач', 'Спортзал', 'Витамины', 'Лекарства'],
                'Кафе и рестораны' => ['Кафе', 'Ресторан', 'Обед', 'Ужин', 'Кофе'],
                'Образование' => ['Курсы', 'Учебники', 'Тренинг', 'Книги'],
            ];

            $paymentMethods = ['cash', 'card', 'transfer'];

            foreach ($expenseData as $categoryName => $data) {
                $percent = rand($data['min_percent'] * 10, $data['max_percent'] * 10) / 10;
                $categoryTotal = round($targetMonthlyExpense * $percent / 100, 2);

                if ($categoryTotal <= 0) continue;

                // Количество транзакций: от 2 до 10
                $transactionCount = rand(2, 10);
                $remaining = $categoryTotal;

                for ($i = 0; $i < $transactionCount; $i++) {
                    if ($remaining <= 0) break;

                    $amount = ($i == $transactionCount - 1)
                        ? round($remaining, 2)
                        : round($remaining * rand(8, 25) / 100, 2);

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
                }
            }
        }

    }
}