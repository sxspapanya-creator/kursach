<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Category;
use App\Models\Currency;
use App\Models\CurrencyRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class AnalyticsController extends Controller
{
    protected function getUserId()
    {
        $userId = Auth::id();
        if (!$userId) abort(401, 'Unauthorized');
        return $userId;
    }

    // ==================== ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ ДЛЯ КУРСОВ ВАЛЮТ ====================

    private function loadRatesForTransactions(Collection $transactions, Currency $baseCurrency): array
    {
        if ($transactions->isEmpty()) return [];

        $currencyIds = $transactions->pluck('currency_id')->unique()->filter(fn($id) => $id != $baseCurrency->id);
        if ($currencyIds->isEmpty()) return [];

        $minDate = $transactions->min('date');
        $maxDate = $transactions->max('date');

        $allRates = CurrencyRate::whereIn('from_currency_id', $currencyIds)
            ->where('to_currency_id', $baseCurrency->id)
            ->whereBetween('date', [$minDate, $maxDate])
            ->orderBy('date', 'asc')
            ->get();

        $rates = [];
        foreach ($allRates as $rate) {
            $currencyId = $rate->from_currency_id;
            $dateKey = $rate->date->toDateString();
            if (!isset($rates[$currencyId])) $rates[$currencyId] = [];
            $rates[$currencyId][$dateKey] = $rate->rate;
        }
        return $rates;
    }

    private function getRateFromCache(array $rates, int $currencyId, Carbon $date, Currency $baseCurrency): ?float
    {
        if ($currencyId == $baseCurrency->id) return 1;
        if (!isset($rates[$currencyId])) return null;

        $dateKey = $date->toDateString();
        if (isset($rates[$currencyId][$dateKey])) return $rates[$currencyId][$dateKey];

        $availableDates = array_keys($rates[$currencyId]);
        $lastDate = null;
        foreach ($availableDates as $d) {
            if ($d <= $dateKey) $lastDate = $d;
            else break;
        }
        return $lastDate ? $rates[$currencyId][$lastDate] : null;
    }

    private function getRateFromDatabase(int $currencyId, int $baseCurrencyId, Carbon $date): ?float
    {
        $rate = CurrencyRate::where('from_currency_id', $currencyId)
            ->where('to_currency_id', $baseCurrencyId)
            ->where('date', '<=', $date)
            ->orderBy('date', 'desc')
            ->first();
        return $rate ? $rate->rate : null;
    }

    private function convertToBaseCurrency(Transaction $transaction, Currency $baseCurrency, array $ratesCache = []): float
    {
        if ($transaction->currency_id == $baseCurrency->id) return (float) $transaction->amount;

        $rate = null;
        if (!empty($ratesCache)) {
            $rate = $this->getRateFromCache($ratesCache, $transaction->currency_id, $transaction->date, $baseCurrency);
        }
        if ($rate === null) {
            $rate = $this->getRateFromDatabase($transaction->currency_id, $baseCurrency->id, $transaction->date);
        }
        if ($rate === null) {
            Log::warning("Курс не найден для валюты {$transaction->currency_id} на дату {$transaction->date}");
            return (float) $transaction->amount;
        }
        return (float) $transaction->amount * $rate;
    }

    // ==================== ОСНОВНОЙ МЕТОД OVERVIEW ====================

    public function overview(Request $request)
    {
        try {
            $userId = $this->getUserId();
            $validated = $request->validate([
                'period' => 'nullable|in:month,year',
                'year' => 'nullable|integer|min:2000|max:2100',
                'month' => 'nullable|integer|min:1|max:12'
            ]);

            $period = $validated['period'] ?? 'month';
            $year = $validated['year'] ?? date('Y');
            $month = $validated['month'] ?? date('m');

            $startDate = null;
            $endDate = null;

            switch ($period) {
                case 'year':
                    $startDate = Carbon::create($year, 1, 1)->startOfYear();
                    $endDate = Carbon::create($year, 12, 31)->endOfYear();
                    break;
                default:
                    $startDate = Carbon::create($year, $month, 1)->startOfMonth();
                    $endDate = $startDate->copy()->endOfMonth();
                    break;
            }

            $transactions = Transaction::where('user_id', $userId)
                ->with(['categories', 'currency'])
                ->whereBetween('date', [$startDate, $endDate])
                ->get();

            $baseCurrency = Currency::where('code', 'BYN')->first();
            if (!$baseCurrency) {
                return response()->json(['status' => 'error', 'message' => 'Базовая валюта BYN не найдена'], 500);
            }

            $ratesCache = $this->loadRatesForTransactions($transactions, $baseCurrency);

            $totalIncome = 0;
            $totalExpense = 0;
            $categoryTotals = [];

            foreach ($transactions as $transaction) {
                $amountInBase = $this->convertToBaseCurrency($transaction, $baseCurrency, $ratesCache);

                if ($transaction->type === 'income') {
                    $totalIncome += $amountInBase;
                } else {
                    $totalExpense += $amountInBase;
                    foreach ($transaction->categories as $category) {
                        $catId = $category->id;
                        if (!isset($categoryTotals[$catId])) {
                            $categoryTotals[$catId] = [
                                'id' => $catId,
                                'name' => $category->name,
                                'color' => $category->color ?? '#3498db',
                                'total' => 0,
                                'budget_limit' => $category->budget_limit ?? 0
                            ];
                        }
                        $categoryTotals[$catId]['total'] += $amountInBase;
                    }
                }
            }

            $balance = $totalIncome - $totalExpense;
            $savingsRate = $totalIncome > 0 ? ($balance / $totalIncome) * 100 : 0;

            $categorySpending = [];
            foreach ($categoryTotals as $cat) {
                $limitPercentage = 0;
                $budgetStatus = 'no_limit';
                if ($cat['budget_limit'] > 0) {
                    $limitPercentage = ($cat['total'] / $cat['budget_limit']) * 100;
                    if ($limitPercentage <= 80) $budgetStatus = 'good';
                    elseif ($limitPercentage <= 100) $budgetStatus = 'warning';
                    else $budgetStatus = 'critical';
                }
                $categorySpending[] = [
                    'id' => $cat['id'],
                    'name' => $cat['name'],
                    'color' => $cat['color'],
                    'total' => round($cat['total'], 2),
                    'budget_limit' => (float) $cat['budget_limit'],
                    'limit_percentage' => round($limitPercentage, 1),
                    'budget_status' => $budgetStatus,
                    'average_monthly' => round($cat['total'], 2)
                ];
            }

            usort($categorySpending, fn($a, $b) => $b['total'] <=> $a['total']);

            $financialHealth = $this->calculateFinancialHealth($userId, $endDate);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'totals' => [
                        'income' => round($totalIncome, 2),
                        'expenses' => round($totalExpense, 2),
                        'balance' => round($balance, 2),
                        'savings_rate' => round($savingsRate, 1)
                    ],
                    'date_range' => [
                        'start' => $startDate->format('Y-m-d'),
                        'end' => $endDate->format('Y-m-d'),
                        'label' => $startDate->translatedFormat('F Y')
                    ],
                    'category_spending' => $categorySpending,
                    'financial_health' => $financialHealth
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Overview error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Ошибка: ' . $e->getMessage()], 500);
        }
    }

    // ==================== ФИНАНСОВОЕ ЗДОРОВЬЕ ====================

    private function calculateFinancialHealth(int $userId, ?Carbon $currentDate = null): array
    {
        try {
            $currentDate = $currentDate ?? Carbon::now();
            $salaryDay = $this->getUserSalaryDay($userId);
            $threeMonthsAgo = $currentDate->copy()->subMonths(3);

            $avgMonthlyIncome = Transaction::where('user_id', $userId)
                    ->where('type', 'income')
                    ->whereBetween('date', [$threeMonthsAgo, $currentDate])
                    ->sum('amount') / 3;

            $avgMonthlyExpense = Transaction::where('user_id', $userId)
                    ->where('type', 'expense')
                    ->whereBetween('date', [$threeMonthsAgo, $currentDate])
                    ->sum('amount') / 3;

            $savings = $this->getUserSavings($userId);
            $monthlyLoanPayments = $this->getMonthlyLoanPayments($userId);
            $cycleBalance = $this->getCurrentCycleBalance($userId, $currentDate, $salaryDay);
            $daysUntilSalary = $this->getDaysUntilNextSalary($userId, $salaryDay, $currentDate);

            $dailyExpenseRate = $avgMonthlyExpense / 30;
            $neededUntilSalary = $dailyExpenseRate * max(1, $daysUntilSalary);

            // Ликвидность (30%)
            if ($cycleBalance <= 0) $liquidityScore = 0;
            elseif ($cycleBalance >= $neededUntilSalary * 1.5) $liquidityScore = 100;
            elseif ($cycleBalance >= $neededUntilSalary) $liquidityScore = 70;
            elseif ($cycleBalance >= $neededUntilSalary * 0.7) $liquidityScore = 40;
            else $liquidityScore = 10;

            // Подушка безопасности (30%)
            if ($avgMonthlyExpense <= 0) $emergencyFundScore = 50;
            elseif ($savings >= $avgMonthlyExpense * 6) $emergencyFundScore = 100;
            elseif ($savings >= $avgMonthlyExpense * 3) $emergencyFundScore = 70;
            elseif ($savings >= $avgMonthlyExpense * 1) $emergencyFundScore = 40;
            elseif ($savings > 0) $emergencyFundScore = 20;
            else $emergencyFundScore = 0;

            // Долговая нагрузка (20%)
            if ($avgMonthlyIncome <= 0) $debtLoadScore = 0;
            elseif ($monthlyLoanPayments <= $avgMonthlyIncome * 0.2) $debtLoadScore = 100;
            elseif ($monthlyLoanPayments <= $avgMonthlyIncome * 0.35) $debtLoadScore = 60;
            elseif ($monthlyLoanPayments <= $avgMonthlyIncome * 0.5) $debtLoadScore = 30;
            else $debtLoadScore = 0;

            // Норма сбережений (20%)
            $availableAfterExpenses = $avgMonthlyIncome - $avgMonthlyExpense - $monthlyLoanPayments;
            $savingsRate = $avgMonthlyIncome > 0 ? ($availableAfterExpenses / $avgMonthlyIncome) * 100 : 0;
            if ($savingsRate >= 20) $savingsRateScore = 100;
            elseif ($savingsRate >= 10) $savingsRateScore = 70;
            elseif ($savingsRate >= 5) $savingsRateScore = 40;
            elseif ($savingsRate > 0) $savingsRateScore = 20;
            else $savingsRateScore = 0;

            $totalScore = ($liquidityScore * 0.30) + ($emergencyFundScore * 0.30) + ($debtLoadScore * 0.20) + ($savingsRateScore * 0.20);
            $totalScore = round(min(100, max(0, $totalScore)));

            if ($totalScore >= 80) { $status = 'excellent'; $statusLabel = 'Отлично'; $color = '#27ae60'; }
            elseif ($totalScore >= 60) { $status = 'good'; $statusLabel = 'Хорошо'; $color = '#2ecc71'; }
            elseif ($totalScore >= 40) { $status = 'fair'; $statusLabel = 'Удовлетворительно'; $color = '#f39c12'; }
            elseif ($totalScore >= 20) { $status = 'poor'; $statusLabel = 'Плохо'; $color = '#e74c3c'; }
            else { $status = 'critical'; $statusLabel = 'Критично'; $color = '#c0392b'; }

            return [
                'score' => $totalScore,
                'status' => $status,
                'status_label' => $statusLabel,
                'color' => $color,
                'components' => [
                    'liquidity' => ['score' => round($liquidityScore), 'balance' => round($cycleBalance, 2), 'needed_until_salary' => round($neededUntilSalary, 2), 'days_until_salary' => $daysUntilSalary],
                    'emergency_fund' => ['score' => round($emergencyFundScore), 'savings' => round($savings, 2), 'months_coverage' => $avgMonthlyExpense > 0 ? round($savings / $avgMonthlyExpense, 1) : 0],
                    'debt_load' => ['score' => round($debtLoadScore), 'monthly_payments' => round($monthlyLoanPayments, 2), 'percent_of_income' => $avgMonthlyIncome > 0 ? round(($monthlyLoanPayments / $avgMonthlyIncome) * 100) : 0],
                    'savings_rate' => ['score' => round($savingsRateScore), 'rate' => round($savingsRate, 1), 'saved_amount' => round($availableAfterExpenses, 2)]
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Financial health error: ' . $e->getMessage());
            return ['score' => 0, 'status' => 'poor', 'status_label' => 'Не определено', 'color' => '#95a5a6', 'components' => []];
        }
    }

    private function getCurrentCycleBalance(int $userId, Carbon $currentDate, int $salaryDay): float
    {
        $lastSalaryDate = Carbon::create($currentDate->year, $currentDate->month, $salaryDay);
        if ($lastSalaryDate > $currentDate) $lastSalaryDate->subMonth();

        $income = Transaction::where('user_id', $userId)->where('type', 'income')
            ->where('date', '>=', $lastSalaryDate)->where('date', '<=', $currentDate)->sum('amount');
        $expense = Transaction::where('user_id', $userId)->where('type', 'expense')
            ->where('date', '>=', $lastSalaryDate)->where('date', '<=', $currentDate)->sum('amount');
        return $income - $expense;
    }

    private function getUserSalaryDay(int $userId): int {
        $incomes = Transaction::where('user_id', $userId)
            ->where('type', 'income')
            ->where('date', '>=', Carbon::now()->subMonths(6))
            ->orderBy('amount', 'desc')
            ->get();

        if ($incomes->isEmpty()) return 25;

        $avgIncome = $incomes->avg('amount');
        $potentialSalaries = $incomes->filter(fn($t) => $t->amount >= $avgIncome * 0.7);
        if ($potentialSalaries->isEmpty()) return 25;

        $dayCount = [];
        foreach ($potentialSalaries as $salary) {
            $day = Carbon::parse($salary->date)->day;
            $dayCount[$day] = ($dayCount[$day] ?? 0) + 1;
        }

        $maxCount = 0;
        $salaryDay = 25;
        foreach ($dayCount as $day => $count) {
            if ($count > $maxCount) {
                $maxCount = $count;
                $salaryDay = $day;
            }
        }
        return $salaryDay;
    }

    private function getDaysUntilNextSalary(int $userId, int $salaryDay, Carbon $currentDate): int
    {
        $nextSalaryDate = Carbon::create($currentDate->year, $currentDate->month, $salaryDay);
        if ($nextSalaryDate <= $currentDate) $nextSalaryDate->addMonth();
        return $currentDate->diffInDays($nextSalaryDate);
    }

    private function getUserSavings(int $userId): float
    {
        $totalIncome = Transaction::where('user_id', $userId)->where('type', 'income')->sum('amount');
        $totalExpense = Transaction::where('user_id', $userId)->where('type', 'expense')->sum('amount');
        return max(0, $totalIncome - $totalExpense);
    }

    private function getMonthlyLoanPayments(int $userId): float
    {
        $loanCategory = Category::where('user_id', $userId)->where('name', 'Кредиты')->where('type', 'expense')->first();
        if ($loanCategory) {
            $threeMonthsAgo = Carbon::now()->subMonths(3);
            $total = Transaction::where('user_id', $userId)->whereHas('categories', fn($q) => $q->where('categories.id', $loanCategory->id))
                ->whereBetween('date', [$threeMonthsAgo, Carbon::now()])->sum('amount');
            return $total / 3;
        }
        return 0;
    }

    // ==================== МЕТОДЫ ДЛЯ ПОЛУЧЕНИЯ ДАННЫХ ====================

    private function getMonthlyExpenseAmount(int $userId, int $year, int $month): float
    {
        $transactions = Transaction::where('user_id', $userId)->where('type', 'expense')
            ->with(['currency'])->whereYear('date', $year)->whereMonth('date', $month)->get();
        $baseCurrency = Currency::where('code', 'BYN')->first();
        if (!$baseCurrency) return 0;
        $ratesCache = $this->loadRatesForTransactions($transactions, $baseCurrency);
        $total = 0;
        foreach ($transactions as $transaction) {
            $total += $this->convertToBaseCurrency($transaction, $baseCurrency, $ratesCache);
        }
        return $total;
    }

    private function getMonthlyExpensesArray(int $userId, int $months = 24): array
    {
        $result = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $amount = $this->getMonthlyExpenseAmount($userId, $date->year, $date->month);
            $result[] = $amount > 0 ? $amount : 0;
        }
        return $result;
    }

    // ==================== КЛАССИЧЕСКИЙ МЕТОД ХОЛЬТА-ВИНТЕРСА ====================

    /**
     * Классический метод Хольта-Винтерса для прогнозирования временных рядов
     *
     * Формулы:
     * Lt = α × Yt + (1-α) × (Lt-1 + Tt-1)      - уровень
     * Tt = β × (Lt - Lt-1) + (1-β) × Tt-1      - тренд
     * St = γ × (Yt / Lt) + (1-γ) × St-p        - сезонность
     * Ft+k = (Lt + k × Tt) × St-p+k            - прогноз
     *
     * @param array $data Исторические данные (помесячные расходы)
     * @param int $seasonalPeriod Период сезонности (12 месяцев)
     * @param int $forecastSteps Количество шагов прогноза (месяцев)
     * @return array Прогнозные значения
     */
    private function holtWinters(array $data, int $seasonalPeriod = 12, int $forecastSteps = 6): array
    {
        $n = count($data);
        if ($n < $seasonalPeriod * 2) {
            // Недостаточно данных — используем простой метод
            return $this->simpleForecast($data, $forecastSteps);
        }

        // Параметры сглаживания (оптимизированы для финансовых данных)
        $alpha = $this->optimizeAlpha($data);
        $beta = $this->optimizeBeta($data);
        $gamma = $this->optimizeGamma($data);

        // Инициализация компонентов
        list($level, $trend, $seasonality) = $this->initHoltWinters($data, $seasonalPeriod);

        // Применение формул Хольта-Винтерса
        for ($t = 0; $t < $n; $t++) {
            $oldLevel = $level;

            // Уровень (горизонталь)
            $level = $alpha * $data[$t] + (1 - $alpha) * ($level + $trend);

            // Тренд
            $trend = $beta * ($level - $oldLevel) + (1 - $beta) * $trend;

            // Сезонность
            $seasonIndex = $t % $seasonalPeriod;
            $seasonality[$seasonIndex] = $gamma * ($data[$t] / $level) + (1 - $gamma) * $seasonality[$seasonIndex];
        }

        // Прогноз
        $forecast = [];
        for ($k = 1; $k <= $forecastSteps; $k++) {
            $seasonIndex = ($n + $k - 1) % $seasonalPeriod;
            $forecast[] = ($level + $k * $trend) * $seasonality[$seasonIndex];
        }

        return $forecast;
    }

    /**
     * Инициализация компонентов Хольта-Винтерса
     */
    private function initHoltWinters(array $data, int $seasonalPeriod): array
    {
        $n = count($data);

        // Начальный уровень — среднее за первый сезон
        $firstSeason = array_slice($data, 0, $seasonalPeriod);
        $level = array_sum($firstSeason) / $seasonalPeriod;

        // Начальный тренд — среднее изменение между сезонами
        $trend = 0;
        $seasons = floor($n / $seasonalPeriod);
        for ($s = 0; $s < $seasons - 1; $s++) {
            $seasonStart = $s * $seasonalPeriod;
            $seasonEnd = $seasonStart + $seasonalPeriod;
            $seasonAvg = array_sum(array_slice($data, $seasonStart, $seasonalPeriod)) / $seasonalPeriod;
            $nextSeasonAvg = array_sum(array_slice($data, $seasonEnd, $seasonalPeriod)) / $seasonalPeriod;
            $trend += ($nextSeasonAvg - $seasonAvg) / $seasonalPeriod;
        }
        $trend = $trend / max(1, $seasons - 1);

        // Начальная сезонность
        $seasonality = [];
        for ($i = 0; $i < $seasonalPeriod; $i++) {
            $seasonalSum = 0;
            $seasonCount = 0;
            for ($j = $i; $j < $n; $j += $seasonalPeriod) {
                if ($j < $n) {
                    $seasonalSum += $data[$j] / $level;
                    $seasonCount++;
                }
            }
            $seasonality[] = $seasonCount > 0 ? $seasonalSum / $seasonCount : 1.0;
        }

        // Нормализация сезонности (среднее = 1)
        $seasonalityMean = array_sum($seasonality) / $seasonalPeriod;
        if ($seasonalityMean > 0) {
            $seasonality = array_map(fn($s) => $s / $seasonalityMean, $seasonality);
        }

        return [$level, $trend, $seasonality];
    }

    /**
     * Оптимизация параметра α (уровень)
     */
    private function optimizeAlpha(array $data): float
    {
        // Поиск оптимального α в диапазоне [0.1, 0.9]
        $bestAlpha = 0.3;
        $bestMSE = INF;

        for ($alpha = 0.1; $alpha <= 0.9; $alpha += 0.1) {
            $mse = $this->calculateMSE($data, $alpha, 0.2, 0.3);
            if ($mse < $bestMSE) {
                $bestMSE = $mse;
                $bestAlpha = $alpha;
            }
        }

        return $bestAlpha;
    }

    /**
     * Оптимизация параметра β (тренд)
     */
    private function optimizeBeta(array $data): float
    {
        $bestBeta = 0.2;
        $bestMSE = INF;

        for ($beta = 0.05; $beta <= 0.5; $beta += 0.05) {
            $mse = $this->calculateMSE($data, 0.3, $beta, 0.3);
            if ($mse < $bestMSE) {
                $bestMSE = $mse;
                $bestBeta = $beta;
            }
        }

        return $bestBeta;
    }

    /**
     * Оптимизация параметра γ (сезонность)
     */
    private function optimizeGamma(array $data): float
    {
        $bestGamma = 0.3;
        $bestMSE = INF;

        for ($gamma = 0.1; $gamma <= 0.7; $gamma += 0.1) {
            $mse = $this->calculateMSE($data, 0.3, 0.2, $gamma);
            if ($mse < $bestMSE) {
                $bestMSE = $mse;
                $bestGamma = $gamma;
            }
        }

        return $bestGamma;
    }

    /**
     * Расчет MSE (Mean Squared Error) для заданных параметров
     */
    private function calculateMSE(array $data, float $alpha, float $beta, float $gamma): float
    {
        $n = count($data);
        if ($n < 12) return INF;

        $seasonalPeriod = 12;
        list($level, $trend, $seasonality) = $this->initHoltWinters($data, $seasonalPeriod);

        $errors = [];
        for ($t = 0; $t < $n; $t++) {
            $oldLevel = $level;
            $level = $alpha * $data[$t] + (1 - $alpha) * ($level + $trend);
            $trend = $beta * ($level - $oldLevel) + (1 - $beta) * $trend;
            $seasonIndex = $t % $seasonalPeriod;

            // Ошибка для последней трети данных (валидация)
            if ($t > $n * 0.7) {
                $predicted = ($oldLevel + $trend) * $seasonality[$seasonIndex];
                $errors[] = pow($data[$t] - $predicted, 2);
            }

            $seasonality[$seasonIndex] = $gamma * ($data[$t] / $level) + (1 - $gamma) * $seasonality[$seasonIndex];
        }

        return empty($errors) ? INF : array_sum($errors) / count($errors);
    }

    /**
     * Простой прогноз при недостатке данных
     */
    private function simpleForecast(array $data, int $steps): array
    {
        $n = count($data);
        if ($n == 0) return array_fill(0, $steps, 0);

        $lastValue = $data[$n - 1];
        $avgChange = 0;

        if ($n >= 2) {
            $sumChange = 0;
            for ($i = 1; $i < $n; $i++) {
                $sumChange += $data[$i] - $data[$i - 1];
            }
            $avgChange = $sumChange / ($n - 1);
        }

        $forecast = [];
        for ($i = 1; $i <= $steps; $i++) {
            $forecast[] = max(0, $lastValue + $avgChange * $i);
        }

        return $forecast;
    }

    // ==================== ПРОГНОЗ НА 30 ДНЕЙ (НА ОСНОВЕ ХОЛЬТА-ВИНТЕРСА) ====================

    public function forecastNext30Days(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $userId = $this->getUserId();
            if (!Transaction::where('user_id', $userId)->exists()) {
                return response()->json(['status' => 'success', 'data' => ['has_data' => false, 'message' => 'Нет данных для прогноза. Добавьте транзакции.']]);
            }

            // Получаем исторические данные за последние 24 месяца
            $historicalData = $this->getMonthlyExpensesArray($userId, 24);

            // Фильтрация нулевых значений и аномалий
            $filteredData = array_values(array_filter($historicalData, fn($v) => $v > 0));

            // Прогноз на 6 месяцев с помощью Хольта-Винтерса
            $monthlyForecast = $this->holtWinters($filteredData, 12, 6);

            // Текущий средний дневной расход
            $lastCompleteMonth = Carbon::now()->subMonth()->startOfMonth();
            $baselineTotal = $this->getMonthlyExpenseAmount($userId, $lastCompleteMonth->year, $lastCompleteMonth->month);
            $dailyBaseline = $baselineTotal / $lastCompleteMonth->daysInMonth;

            // Ближайший месячный прогноз (на следующий месяц)
            $nextMonthForecast = $monthlyForecast[0] ?? $dailyBaseline * 30;
            $nextMonthDaily = $nextMonthForecast / 30;

            // Коэффициент дней недели
            $dayOfWeekFactors = $this->calculateDayOfWeekFactors($userId);

            // Коэффициент тренда (из Хольта-Винтерса)
            $trend = $this->calculateTrendFromHoltWinters($filteredData);

            // Сезонный коэффициент
            $seasonalFactor = $this->calculateSeasonalFactor($historicalData);

            // Прогноз на 30 дней
            $dailyForecasts = [];
            $totalForecast = 0;
            $currentDate = Carbon::now()->copy();

            for ($i = 0; $i < 30; $i++) {
                $dayOfWeek = $currentDate->dayOfWeek;
                $dayFactor = $dayOfWeekFactors[$dayOfWeek] ?? 1.0;

                // Комбинированный прогноз: дневной базовый + ежемесячный прогноз Хольта-Винтерса
                $dailyForecast = ($dailyBaseline * $dayFactor * $trend + $nextMonthDaily) / 2;

                $dailyForecasts[] = [
                    'date' => $currentDate->format('Y-m-d'),
                    'day_of_week' => $this->getRussianDayOfWeek($currentDate->dayOfWeek),
                    'forecast' => round($dailyForecast, 2)
                ];
                $totalForecast += $dailyForecast;
                $currentDate->addDay();
            }

            // Прогноз по категориям
            $categoryForecasts = $this->getCategoryForecasts($userId, $dailyBaseline, $seasonalFactor, $trend);

            // Доверительный интервал
            $interval = $this->getPredictionInterval($userId, $totalForecast, 30);

            // Confidence score
            $confidence = $this->calculateConfidence($userId);

            // Метрики качества модели
            $modelMetrics = $this->calculateModelMetrics($filteredData, $monthlyForecast);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'has_data' => true,
                    'model' => 'Holt-Winters',
                    'period' => ['start' => Carbon::now()->format('Y-m-d'), 'end' => Carbon::now()->addDays(29)->format('Y-m-d'), 'days' => 30],
                    'total_forecast' => round($totalForecast, 2),
                    'daily_average' => round($totalForecast / 30, 2),
                    'daily_forecasts' => $dailyForecasts,
                    'monthly_forecast' => array_map(fn($v) => round($v, 2), $monthlyForecast),
                    'category_forecasts' => $categoryForecasts,
                    'confidence_interval' => $interval,
                    'trend_factor' => round($trend, 3),
                    'seasonal_factor' => round($seasonalFactor, 2),
                    'model_metrics' => $modelMetrics,
                    'confidence' => $confidence['percent'],
                    'confidence_level' => $confidence['level'],
                    'confidence_text' => $confidence['text']
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Forecast30Days error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Расчет тренда на основе метода Хольта-Винтерса
     */
    private function calculateTrendFromHoltWinters(array $data): float
    {
        if (count($data) < 6) return 1.0;

        $n = count($data);
        $firstHalf = array_sum(array_slice($data, 0, floor($n / 2))) / floor($n / 2);
        $secondHalf = array_sum(array_slice($data, floor($n / 2))) / ceil($n / 2);

        if ($firstHalf == 0) return 1.0;

        $trend = $secondHalf / $firstHalf;
        return max(0.95, min(1.05, $trend));
    }

    /**
     * Расчет сезонного фактора
     */
    private function calculateSeasonalFactor(array $data): float
    {
        if (count($data) < 24) return 1.0;

        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;

        $thisYearAvg = 0;
        $lastYearAvg = 0;
        $count = 0;

        for ($i = 0; $i < 12; $i++) {
            $date = Carbon::create($currentYear - 1, $i + 1, 1);
            $amount = $this->getMonthlyExpenseAmount($userId ?? 0, $date->year, $date->month);
            if ($amount > 0) {
                $lastYearAvg += $amount;
                $count++;
            }
        }
        $lastYearAvg = $lastYearAvg / max(1, $count);

        if ($lastYearAvg == 0) return 1.0;

        $currentMonthAmount = $this->getMonthlyExpenseAmount($userId ?? 0, $currentYear - 1, $currentMonth);
        return $currentMonthAmount / $lastYearAvg;
    }

    /**
     * Метрики качества модели (MAPE, MSE, RMSE)
     */
    private function calculateModelMetrics(array $historical, array $forecast): array
    {
        if (empty($historical) || empty($forecast)) {
            return ['mape' => 0, 'mse' => 0, 'rmse' => 0];
        }

        $n = min(count($historical), count($forecast));
        $mapeSum = 0;
        $mseSum = 0;

        for ($i = 0; $i < $n; $i++) {
            $actual = $historical[count($historical) - $n + $i];
            $predicted = $forecast[$i];

            if ($actual > 0) {
                $mapeSum += abs(($actual - $predicted) / $actual);
            }
            $mseSum += pow($actual - $predicted, 2);
        }

        $mape = $n > 0 ? ($mapeSum / $n) * 100 : 0;
        $mse = $n > 0 ? $mseSum / $n : 0;
        $rmse = sqrt($mse);

        return [
            'mape' => round($mape, 1),
            'mse' => round($mse, 2),
            'rmse' => round($rmse, 2),
            'interpretation' => $mape < 10 ? 'Высокая точность' : ($mape < 20 ? 'Хорошая точность' : 'Приемлемая точность')
        ];
    }

    // ==================== КОЭФФИЦИЕНТЫ ДНЕЙ НЕДЕЛИ ====================

    private function calculateDayOfWeekFactors(int $userId): array
    {
        $defaultFactors = [0 => 1.0, 1 => 0.9, 2 => 0.9, 3 => 0.9, 4 => 1.2, 5 => 1.1, 6 => 1.3];

        $startDate = Carbon::now()->subMonths(3);
        $endDate = Carbon::now();

        $transactions = Transaction::where('user_id', $userId)
            ->where('type', 'expense')
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        if ($transactions->count() < 10) return $defaultFactors;

        $baseCurrency = Currency::where('code', 'BYN')->first();
        if (!$baseCurrency) return $defaultFactors;

        $ratesCache = $this->loadRatesForTransactions($transactions, $baseCurrency);

        $dailyTotals = array_fill(0, 7, 0.0);
        $dailyCounts = array_fill(0, 7, 0);

        foreach ($transactions as $transaction) {
            $amountInBase = $this->convertToBaseCurrency($transaction, $baseCurrency, $ratesCache);
            $dayOfWeek = Carbon::parse($transaction->date)->dayOfWeek;
            $dailyTotals[$dayOfWeek] += $amountInBase;
            $dailyCounts[$dayOfWeek]++;
        }

        $dailyAverages = [];
        for ($i = 0; $i < 7; $i++) {
            $dailyAverages[$i] = $dailyCounts[$i] > 0 ? $dailyTotals[$i] / $dailyCounts[$i] : 0;
        }

        $totalAverage = 0;
        $daysWithData = 0;
        for ($i = 0; $i < 7; $i++) {
            if ($dailyAverages[$i] > 0) {
                $totalAverage += $dailyAverages[$i];
                $daysWithData++;
            }
        }
        $totalAverage = $daysWithData > 0 ? $totalAverage / $daysWithData : 1.0;

        $factors = [];
        for ($i = 0; $i < 7; $i++) {
            if ($dailyAverages[$i] > 0 && $totalAverage > 0) {
                $factor = $dailyAverages[$i] / $totalAverage;
                $factors[$i] = max(0.5, min(2.0, $factor));
            } else {
                $factors[$i] = $defaultFactors[$i];
            }
        }

        return $factors;
    }

    private function getRussianDayOfWeek(int $dayOfWeek): string
    {
        return ['Воскресенье', 'Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота'][$dayOfWeek] ?? '';
    }

    // ==================== ПРОГНОЗ ПО КАТЕГОРИЯМ ====================

    private function getCategoryForecasts(int $userId, float $dailyBaseline, float $seasonalFactor, float $trend): array
    {
        $categories = Category::where('user_id', $userId)->where('type', 'expense')->get();
        $forecasts = [];

        foreach ($categories as $category) {
            $lastCompleteMonth = Carbon::now()->subMonth()->startOfMonth();
            $categoryBaseline = $this->getCategoryMonthlyExpense($userId, $category->id, $lastCompleteMonth->year, $lastCompleteMonth->month);
            $categoryDailyBaseline = $categoryBaseline / $lastCompleteMonth->daysInMonth;

            if ($categoryDailyBaseline <= 0) continue;

            $categoryTotal = $categoryDailyBaseline * 30 * $seasonalFactor * $trend;

            $forecasts[] = [
                'category_id' => $category->id,
                'category_name' => $category->name,
                'color' => $category->color ?? '#3498db',
                'forecast' => round($categoryTotal, 2),
                'daily_average' => round($categoryTotal / 30, 2),
                'share_percent' => $dailyBaseline > 0 ? round(($categoryDailyBaseline / $dailyBaseline) * 100, 1) : 0
            ];
        }

        usort($forecasts, fn($a, $b) => $b['forecast'] <=> $a['forecast']);
        return array_slice($forecasts, 0, 10);
    }

    private function getCategoryMonthlyExpense(int $userId, int $categoryId, int $year, int $month): float
    {
        $transactions = Transaction::where('user_id', $userId)->where('type', 'expense')
            ->whereHas('categories', fn($q) => $q->where('categories.id', $categoryId))
            ->with(['currency'])->whereYear('date', $year)->whereMonth('date', $month)->get();
        $baseCurrency = Currency::where('code', 'BYN')->first();
        if (!$baseCurrency) return 0;
        $ratesCache = $this->loadRatesForTransactions($transactions, $baseCurrency);
        $total = 0;
        foreach ($transactions as $transaction) {
            $total += $this->convertToBaseCurrency($transaction, $baseCurrency, $ratesCache);
        }
        return $total;
    }

    // ==================== ДОВЕРИТЕЛЬНЫЙ ИНТЕРВАЛ ====================

    private function getPredictionInterval(int $userId, float $forecast, int $horizonDays = 30): array
    {
        $monthlyExpenses = $this->getMonthlyExpensesArray($userId, 12);
        $cv = $this->calculateCoefficientOfVariation($monthlyExpenses);

        $horizonMultiplier = sqrt($horizonDays / 30);
        $baseErrorMargin = min(0.5, $cv);
        $errorMargin = min(0.7, $baseErrorMargin * $horizonMultiplier);

        if ($horizonDays < 7) {
            $errorMargin = $errorMargin * 0.8;
        }

        return [
            'lower' => round($forecast * (1 - $errorMargin), 2),
            'upper' => round($forecast * (1 + $errorMargin), 2),
            'margin_percent' => round($errorMargin * 100, 1),
            'horizon_days' => $horizonDays,
            'cv' => round($cv, 3)
        ];
    }

    private function calculateCoefficientOfVariation(array $values): float
    {
        $values = array_filter($values, fn($v) => $v > 0);
        $n = count($values);
        if ($n < 3) return 0.5;
        $mean = array_sum($values) / $n;
        if ($mean == 0) return 0.5;
        $variance = array_sum(array_map(fn($x) => pow($x - $mean, 2), $values)) / $n;
        $stdDev = sqrt($variance);
        return $stdDev / $mean;
    }

    // ==================== CONFIDENCE SCORE ====================

    private function calculateConfidence(int $userId): array
    {
        $oldest = Transaction::where('user_id', $userId)->orderBy('date', 'asc')->first();
        if (!$oldest) return ['percent' => 0, 'level' => 'low', 'text' => 'Нет данных для анализа'];

        $monthsOfData = Carbon::parse($oldest->date)->diffInMonths(Carbon::now());
        $monthsScore = $this->calculateMonthsScore($monthsOfData);

        $monthlyExpenses = $this->getMonthlyExpensesArray($userId, 12);
        $stabilityScore = $this->calculateStabilityScore($monthlyExpenses);

        $totalScore = ($monthsScore * 0.5) + ($stabilityScore * 0.5);
        $totalScore = round(min(100, max(0, $totalScore)));

        if ($totalScore >= 70) return ['percent' => $totalScore, 'level' => 'high', 'text' => 'Высокая надежность. Прогноз статистически значим.'];
        if ($totalScore >= 45) return ['percent' => $totalScore, 'level' => 'medium', 'text' => 'Средняя надежность. Прогноз требует осторожности.'];
        return ['percent' => $totalScore, 'level' => 'low', 'text' => 'Низкая надежность. Недостаточно данных для точного прогноза.'];
    }

    private function calculateMonthsScore(int $months): float
    {
        if ($months < 3) return 0;
        return min(100, 100 * (1 - 1 / sqrt($months)));
    }

    private function calculateStabilityScore(array $monthlyExpenses): float
    {
        $values = array_filter($monthlyExpenses, fn($v) => $v > 0);
        $n = count($values);
        if ($n < 3) return 0;
        $mean = array_sum($values) / $n;
        if ($mean == 0) return 0;
        $variance = array_sum(array_map(fn($x) => pow($x - $mean, 2), $values)) / $n;
        $stdDev = sqrt($variance);
        $cv = $stdDev / $mean;
        return 100 * exp(-2 * $cv);
    }
}