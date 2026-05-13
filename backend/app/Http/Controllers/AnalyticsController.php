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
    // Возвращает ID авторизованного пользователя или 401 ошибку
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

        $income = Transaction::where('user_id', $userId)
            ->where('type', 'income')
            ->where('date', '>=', $lastSalaryDate)
            ->where('date', '<=', $currentDate)
            ->sum('amount');

        $expense = Transaction::where('user_id', $userId)
            ->where('type', 'expense')
            ->where('date', '>=', $lastSalaryDate)
            ->where('date', '<=', $currentDate)
            ->sum('amount');

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
        $totalIncome = Transaction::where('user_id', $userId)
            ->where('type', 'income')
            ->sum('amount');

        $totalExpense = Transaction::where('user_id', $userId)
            ->where('type', 'expense')
            ->sum('amount');

        return max(0, $totalIncome - $totalExpense);
    }

    private function getMonthlyLoanPayments(int $userId): float
    {
        $loanCategory = Category::where('user_id', $userId)->where('name', 'Кредиты')->where('type', 'expense')->first();
        if ($loanCategory) {
            $threeMonthsAgo = Carbon::now()->subMonths(3);
            $total = Transaction::where('user_id', $userId)
                ->whereHas('categories', fn($q) => $q->where('categories.id', $loanCategory->id))
                ->whereBetween('date', [$threeMonthsAgo, Carbon::now()])
                ->sum('amount');
            return $total / 3;
        }
        return 0;
    }

    // ==================== МЕТОДЫ ДЛЯ ПРОГНОЗИРОВАНИЯ ====================

    private function getMonthlyExpenseAmount(int $userId, int $year, int $month): float
    {
        $transactions = Transaction::where('user_id', $userId)
            ->where('type', 'expense')
            ->with(['currency'])
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->get();

        $baseCurrency = Currency::where('code', 'BYN')->first();
        if (!$baseCurrency) return 0;
        $ratesCache = $this->loadRatesForTransactions($transactions, $baseCurrency);
        $total = 0;
        foreach ($transactions as $transaction) {
            $total += $this->convertToBaseCurrency($transaction, $baseCurrency, $ratesCache);
        }
        return $total;
    }

    private function getCategoryMonthlyExpense(int $userId, int $categoryId, int $year, int $month): float
    {
        $transactions = Transaction::where('user_id', $userId)
            ->where('type', 'expense')
            ->whereHas('categories', fn($q) => $q->where('categories.id', $categoryId))
            ->with(['currency'])
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->get();

        $baseCurrency = Currency::where('code', 'BYN')->first();
        if (!$baseCurrency) return 0;
        $ratesCache = $this->loadRatesForTransactions($transactions, $baseCurrency);
        $total = 0;
        foreach ($transactions as $transaction) {
            $total += $this->convertToBaseCurrency($transaction, $baseCurrency, $ratesCache);
        }
        return $total;
    }

    private function getMonthlyExpenseAmountForCompleteMonth(int $userId, int $year, int $month): float
    {
        $date = Carbon::create($year, $month, 1);
        $lastDayOfMonth = $date->copy()->endOfMonth();

        if ($lastDayOfMonth > Carbon::now()) {
            return 0;
        }

        return $this->getMonthlyExpenseAmount($userId, $year, $month);
    }

    private function getCompleteMonthsExpensesArray(int $userId, int $months = 24): array
    {
        $result = [];
        $now = Carbon::now();

        for ($i = $months - 1; $i >= 0; $i--) {
            $date = $now->copy()->subMonths($i);
            $amount = $this->getMonthlyExpenseAmountForCompleteMonth($userId, $date->year, $date->month);
            if ($amount > 0) {
                $result[] = $amount;
            }
        }

        return $result;
    }

    // ==================== ПРОГНОЗ НА ОСТАТОК ТЕКУЩЕГО МЕСЯЦА ====================

    private function forecastRemainingCurrentMonth(int $userId): array
    {
        $now = Carbon::now();
        $currentMonth = $now->month;
        $currentYear = $now->year;
        $daysPassed = $now->day - 1;
        $daysTotal = $now->daysInMonth;

        if ($daysPassed <= 0) {
            $daysPassed = 1;
        }

        $actualSpent = $this->getMonthlyExpenseAmount($userId, $currentYear, $currentMonth);
        $currentDailyRate = $actualSpent / $daysPassed;

        $lastMonth = $now->copy()->subMonth();
        $lastMonthTotal = $this->getMonthlyExpenseAmount($userId, $lastMonth->year, $lastMonth->month);
        $lastMonthDailyRate = $lastMonthTotal / $lastMonth->daysInMonth;

        $currentWeight = min(0.8, $daysPassed / $daysTotal);
        $lastWeight = 1 - $currentWeight;
        $weightedDailyRate = ($currentDailyRate * $currentWeight) + ($lastMonthDailyRate * $lastWeight);

        $daysLeft = $daysTotal - $daysPassed;
        $forecastRemaining = $weightedDailyRate * $daysLeft;

        $dayFactors = $this->calculateDayOfWeekFactors($userId);

        $dailyBreakdown = [];
        $currentDate = $now->copy();

        for ($i = 0; $i < $daysLeft; $i++) {
            $dayOfWeek = $currentDate->dayOfWeek;
            $dayFactor = $dayFactors[$dayOfWeek] ?? 1.0;
            $dayForecast = $weightedDailyRate * $dayFactor;

            $dailyBreakdown[] = [
                'date' => $currentDate->format('Y-m-d'),
                'day_of_week' => $this->getRussianDayOfWeek($dayOfWeek),
                'forecast' => round($dayForecast, 2)
            ];

            $currentDate->addDay();
        }

        return [
            'days_left' => $daysLeft,
            'already_spent' => round($actualSpent, 2),
            'daily_rate_current' => round($currentDailyRate, 2),
            'daily_rate_last_month' => round($lastMonthDailyRate, 2),
            'weighted_daily_rate' => round($weightedDailyRate, 2),
            'current_month_weight' => round($currentWeight * 100),
            'last_month_weight' => round($lastWeight * 100),
            'forecast_total' => round($forecastRemaining, 2),
            'forecast_full_month' => round($actualSpent + $forecastRemaining, 2),
            'daily_breakdown' => $dailyBreakdown
        ];
    }

    private function forecastFullMonthSummary(float $monthlyForecast, Carbon $targetMonth, float $previousMonthActual): array
    {
        $changePercent = $previousMonthActual > 0
            ? round(($monthlyForecast - $previousMonthActual) / $previousMonthActual * 100, 1)
            : 0;

        $trend = $changePercent > 5 ? 'growth' : ($changePercent < -5 ? 'decline' : 'stable');

        return [
            'month' => $targetMonth->translatedFormat('F Y'),
            'total_forecast' => round($monthlyForecast, 2),
            'daily_average' => round($monthlyForecast / $targetMonth->daysInMonth, 2),
            'change_from_previous' => $changePercent,
            'trend' => $trend,
            'days_in_month' => $targetMonth->daysInMonth
        ];
    }

    // ==================== МЕТОДЫ ПРОГНОЗИРОВАНИЯ ====================

    private function simpleExtrapolation(array $data, int $steps): array
    {
        $n = count($data);
        if ($n < 2) {
            $lastValue = $data[$n - 1] ?? 0;
            return array_fill(0, $steps, $lastValue);
        }

        $lastValue = $data[$n - 1];
        $firstValue = $data[0];
        $avgChange = ($lastValue - $firstValue) / ($n - 1);

        $maxDecline = $lastValue * 0.15;
        $maxGrowth = $lastValue * 0.20;
        $avgChange = max(-$maxDecline, min($maxGrowth, $avgChange));

        $forecast = [];
        for ($i = 1; $i <= $steps; $i++) {
            $value = $lastValue + $avgChange * $i;
            $value = max($lastValue * 0.3, $value);
            $forecast[] = round($value, 2);
        }
        return $forecast;
    }

    private function linearRegression(array $data, int $steps): array
    {
        $n = count($data);
        if ($n < 2) {
            return $this->simpleExtrapolation($data, $steps);
        }

        $x = range(0, $n - 1);
        $y = $data;

        $sumX = array_sum($x);
        $sumY = array_sum($y);
        $sumXY = array_sum(array_map(fn($xi, $yi) => $xi * $yi, $x, $y));
        $sumX2 = array_sum(array_map(fn($xi) => $xi * $xi, $x));

        $denominator = ($n * $sumX2 - $sumX * $sumX);
        if ($denominator == 0) {
            return $this->simpleExtrapolation($data, $steps);
        }

        $slope = ($n * $sumXY - $sumX * $sumY) / $denominator;
        $intercept = ($sumY - $slope * $sumX) / $n;

        $forecast = [];
        $lastValue = $data[$n - 1];
        for ($k = 1; $k <= $steps; $k++) {
            $nextX = $n - 1 + $k;
            $value = $slope * $nextX + $intercept;
            $value = max($lastValue * 0.3, min($lastValue * 2.0, $value));
            $forecast[] = round(max(0, $value), 2);
        }
        return $forecast;
    }

    private function doubleExponentialSmoothingImproved(array $data, int $forecastSteps = 6): array
    {
        $n = count($data);
        if ($n < 4) {
            return $this->linearRegression($data, $forecastSteps);
        }

        list($alpha, $beta) = $this->optimizeHoltParametersAdvanced($data);
        list($level, $trend) = $this->initHoltWintersWithLinearRegression($data);

        for ($t = 1; $t < $n; $t++) {
            $oldLevel = $level;
            $level = $alpha * $data[$t] + (1 - $alpha) * ($level + $trend);
            $trend = $beta * ($level - $oldLevel) + (1 - $beta) * $trend;
        }

        $forecast = [];
        for ($k = 1; $k <= $forecastSteps; $k++) {
            $forecast[] = max(0, $level + $k * $trend);
        }

        $lastValue = $data[$n - 1];
        $forecast = $this->constrainForecast($forecast, $lastValue);
        $forecast = $this->smoothForecast($forecast);
        return $forecast;
    }

    private function holtWinters(array $data, int $seasonalPeriod = 12, int $forecastSteps = 6): array
    {
        $n = count($data);
        if ($n < $seasonalPeriod * 2) {
            return $this->doubleExponentialSmoothingImproved($data, $forecastSteps);
        }

        list($alpha, $beta, $gamma) = $this->optimizeHoltWintersParameters($data);
        list($level, $trend, $seasonality) = $this->initHoltWintersWithParams($data, $seasonalPeriod, $alpha, $beta, $gamma);

        for ($t = 0; $t < $n; $t++) {
            $oldLevel = $level;
            $level = $alpha * $data[$t] + (1 - $alpha) * ($level + $trend);
            $trend = $beta * ($level - $oldLevel) + (1 - $beta) * $trend;
            $seasonIndex = $t % $seasonalPeriod;
            $seasonality[$seasonIndex] = $gamma * ($data[$t] / $level) + (1 - $gamma) * $seasonality[$seasonIndex];
        }

        $forecast = [];
        for ($k = 1; $k <= $forecastSteps; $k++) {
            $seasonIndex = ($n + $k - 1) % $seasonalPeriod;
            $forecast[] = ($level + $k * $trend) * $seasonality[$seasonIndex];
        }
        return $forecast;
    }

    // ==================== ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ ДЛЯ ПРОГНОЗИРОВАНИЯ ====================

    private function optimizeHoltParametersAdvanced(array $data): array
    {
        $n = count($data);

        if ($n < 18) {
            $alphaRange = [0.2, 0.3, 0.4, 0.5, 0.6, 0.7, 0.8];
            $betaRange = [0.05, 0.1, 0.2, 0.3, 0.4, 0.5];
        } elseif ($n < 22) {
            $alphaRange = [0.2, 0.3, 0.4, 0.5, 0.6];
            $betaRange = [0.05, 0.1, 0.2, 0.3];
        } else {
            $alphaRange = [0.1, 0.2, 0.3, 0.4, 0.5];
            $betaRange = [0.05, 0.1, 0.15, 0.2, 0.25];
        }

        $bestAlpha = 0.3;
        $bestBeta = 0.2;
        $bestError = INF;

        foreach ($alphaRange as $alpha) {
            foreach ($betaRange as $beta) {
                $error = $this->calculateCombinedError($data, $alpha, $beta);
                if ($error < $bestError) {
                    $bestError = $error;
                    $bestAlpha = $alpha;
                    $bestBeta = $beta;
                }
            }
        }
        return [$bestAlpha, $bestBeta];
    }

    private function calculateCombinedError(array $data, float $alpha, float $beta): float
    {
        $n = count($data);
        if ($n < 4) return INF;

        $level = $data[0];
        $trend = $data[1] - $data[0];

        $mapeSum = 0;
        $rmseSum = 0;
        $validCount = 0;

        for ($t = 1; $t < $n; $t++) {
            $oldLevel = $level;
            $predicted = $level + $trend;

            if ($t > $n * 0.7 && $data[$t] > 0) {
                $mapeSum += abs(($data[$t] - $predicted) / $data[$t]);
                $rmseSum += pow($data[$t] - $predicted, 2);
                $validCount++;
            }

            $level = $alpha * $data[$t] + (1 - $alpha) * ($level + $trend);
            $trend = $beta * ($level - $oldLevel) + (1 - $beta) * $trend;
        }

        if ($validCount == 0) return INF;

        $mape = ($mapeSum / $validCount) * 100;
        $rmse = sqrt($rmseSum / $validCount);
        $mean = array_sum($data) / $n;
        $normalizedRmse = $rmse / $mean;

        return ($mape * 0.7) + ($normalizedRmse * 100 * 0.3);
    }

    private function initHoltWintersWithLinearRegression(array $data): array
    {
        $n = count($data);
        $x = range(0, $n - 1);
        $sumX = array_sum($x);
        $sumY = array_sum($data);
        $sumXY = array_sum(array_map(fn($xi, $yi) => $xi * $yi, $x, $data));
        $sumX2 = array_sum(array_map(fn($xi) => $xi * $xi, $x));

        $denominator = ($n * $sumX2 - $sumX * $sumX);
        if ($denominator != 0) {
            $trend = ($n * $sumXY - $sumX * $sumY) / $denominator;
        } else {
            $trend = 0;
        }

        $level = $data[$n - 1] - $trend * ($n - 1);
        $level = max($data[$n - 1] * 0.5, min($data[$n - 1] * 1.5, $level));
        return [$level, $trend];
    }

    private function optimizeHoltWintersParameters(array $data): array
    {
        $bestAlpha = 0.3;
        $bestBeta = 0.2;
        $bestGamma = 0.3;
        $bestMAPE = INF;

        for ($alpha = 0.1; $alpha <= 0.9; $alpha += 0.1) {
            for ($beta = 0.05; $beta <= 0.5; $beta += 0.05) {
                for ($gamma = 0.1; $gamma <= 0.9; $gamma += 0.1) {
                    $mape = $this->calculateMAPE($data, $alpha, $beta, $gamma);
                    if ($mape < $bestMAPE) {
                        $bestMAPE = $mape;
                        $bestAlpha = $alpha;
                        $bestBeta = $beta;
                        $bestGamma = $gamma;
                    }
                }
            }
        }
        return [$bestAlpha, $bestBeta, $bestGamma];
    }

    private function calculateMAPE(array $data, float $alpha, float $beta, float $gamma): float
    {
        $n = count($data);
        if ($n < 12) return INF;

        $seasonalPeriod = 12;
        list($level, $trend, $seasonality) = $this->initHoltWintersWithParams($data, $seasonalPeriod, $alpha, $beta, $gamma);

        $errors = [];
        $validCount = 0;

        for ($t = 0; $t < $n; $t++) {
            $oldLevel = $level;
            $level = $alpha * $data[$t] + (1 - $alpha) * ($level + $trend);
            $trend = $beta * ($level - $oldLevel) + (1 - $beta) * $trend;
            $seasonIndex = $t % $seasonalPeriod;

            if ($t > $n * 0.7) {
                $predicted = ($oldLevel + $trend) * $seasonality[$seasonIndex];
                if ($data[$t] > 0) {
                    $errors[] = abs(($data[$t] - $predicted) / $data[$t]);
                    $validCount++;
                }
            }

            $seasonality[$seasonIndex] = $gamma * ($data[$t] / $level) + (1 - $gamma) * $seasonality[$seasonIndex];
        }
        return $validCount > 0 ? (array_sum($errors) / $validCount) * 100 : INF;
    }

    private function initHoltWintersWithParams(array $data, int $seasonalPeriod, float $alpha, float $beta, float $gamma): array
    {
        $n = count($data);

        $firstSeason = array_slice($data, 0, $seasonalPeriod);
        $level = array_sum($firstSeason) / $seasonalPeriod;

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

        $seasonalityMean = array_sum($seasonality) / $seasonalPeriod;
        if ($seasonalityMean > 0) {
            $seasonality = array_map(fn($s) => $s / $seasonalityMean, $seasonality);
        }
        return [$level, $trend, $seasonality];
    }

    private function constrainForecast(array $forecast, float $lastValue): array
    {
        $minValue = $lastValue * 0.3;
        $maxValue = $lastValue * 2.0;
        return array_map(fn($v) => max($minValue, min($maxValue, $v)), $forecast);
    }

    private function smoothForecast(array $forecast): array
    {
        $smoothed = [];
        $window = 3;

        for ($i = 0; $i < count($forecast); $i++) {
            $sum = 0;
            $count = 0;
            for ($j = -$window; $j <= $window; $j++) {
                $idx = $i + $j;
                if ($idx >= 0 && $idx < count($forecast)) {
                    $sum += $forecast[$idx];
                    $count++;
                }
            }
            $smoothed[] = $sum / $count;
        }
        return $smoothed;
    }

    // ==================== ВЫБОР МЕТОДА ====================

    private function selectForecastMethod(array $data, int $forecastSteps = 3): ?array
    {
        $n = count($data);

        if ($n < 3) return null;
        if ($n < 7) return $this->simpleExtrapolation($data, $forecastSteps);
        if ($n < 12) return $this->linearRegression($data, $forecastSteps);
        if ($n < 24) return $this->doubleExponentialSmoothingImproved($data, $forecastSteps);
        return $this->holtWinters($data, 12, $forecastSteps);
    }

    // ==================== ОСНОВНОЙ ПУБЛИЧНЫЙ МЕТОД FORECAST ====================

    public function forecast(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $userId = $this->getUserId();

            $hasTransactions = Transaction::where('user_id', $userId)->exists();

            if (!$hasTransactions) {
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'has_data' => false,
                        'message' => 'Нет данных для прогноза. Добавьте транзакции.'
                    ]
                ]);
            }

            $completeMonthsData = $this->getCompleteMonthsExpensesArray($userId, 30);
            $monthsCount = count($completeMonthsData);

            if ($monthsCount < 3) {
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'has_data' => true,
                        'forecast_available' => false,
                        'message' => 'Недостаточно полных месяцев данных. Накопите минимум 3 месяца истории.',
                        'complete_months_available' => $monthsCount
                    ]
                ]);
            }

            $monthlyForecast = $this->selectForecastMethod($completeMonthsData, 3);

            if ($monthlyForecast === null) {
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'has_data' => true,
                        'forecast_available' => false,
                        'message' => 'Не удалось построить прогноз'
                    ]
                ]);
            }

            $remainingMonth = $this->forecastRemainingCurrentMonth($userId);

            $nextMonthDate = Carbon::now()->addMonth()->startOfMonth();
            $lastCompleteMonthTotal = end($completeMonthsData);
            $nextMonthSummary = $this->forecastFullMonthSummary($monthlyForecast[0], $nextMonthDate, $lastCompleteMonthTotal);

            $secondMonthDate = Carbon::now()->addMonths(2)->startOfMonth();
            $secondMonthSummary = $this->forecastFullMonthSummary($monthlyForecast[1] ?? $monthlyForecast[0], $secondMonthDate, $monthlyForecast[0]);

            $lastCompleteMonth = Carbon::now()->subMonth()->startOfMonth();
            $baselineTotal = $this->getMonthlyExpenseAmount($userId, $lastCompleteMonth->year, $lastCompleteMonth->month);
            $dailyBaseline = $baselineTotal / $lastCompleteMonth->daysInMonth;

            $trend = $this->calculateTrendFromData($completeMonthsData);
            $seasonalFactor = $this->calculateSeasonalFactor($userId, $completeMonthsData);

            $categoryForecasts = $this->getCategoryForecasts($userId, $dailyBaseline, $seasonalFactor, $trend);

            $confidence = $this->calculateConfidence($userId);
            $modelMetrics = $this->calculateModelMetrics($completeMonthsData, $monthlyForecast);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'has_data' => true,
                    'forecast_available' => true,
                    'model' => $this->getMethodName($monthsCount),
                    'confidence' => $confidence['percent'],
                    'confidence_level' => $confidence['level'],
                    'confidence_text' => $confidence['text'],
                    'complete_months_used' => $monthsCount,
                    'remaining_current_month' => $remainingMonth,
                    'next_month' => $nextMonthSummary,
                    'second_month' => $secondMonthSummary,
                    'category_forecasts' => $categoryForecasts,
                    'trend_factor' => round($trend, 3),
                    'seasonal_factor' => round($seasonalFactor, 2),
                    'model_metrics' => $modelMetrics,
                    'reliability_message' => $this->getReliabilityMessage($monthsCount)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Forecast error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    // ==================== ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ ДЛЯ ОТВЕТА ====================

    private function getReliabilityMessage(int $monthsCount): string
    {
        if ($monthsCount < 7) return '⚠️ Прогноз на основе минимальных данных (3-6 месяцев). Рекомендуется накопить больше истории.';
        if ($monthsCount < 12) return '📊 Прогноз имеет хорошую точность. Для улучшения рекомендуется накопить 12+ месяцев данных.';
        if ($monthsCount < 24) return '✅ Прогноз имеет высокую точность.';
        return '✅ Прогноз имеет высокую точность с учетом сезонности.';
    }

    private function getMethodName(int $monthsCount): string
    {
        if ($monthsCount < 7) return 'SimpleExtrapolation';
        if ($monthsCount < 12) return 'LinearRegression';
        if ($monthsCount < 24) return 'DoubleExponentialSmoothing';
        return 'HoltWinters';
    }

    private function calculateDayOfWeekFactors(int $userId): array
    {
        $defaultFactors = [0 => 1.0, 1 => 0.85, 2 => 0.85, 3 => 0.85, 4 => 1.15, 5 => 1.2, 6 => 1.2];

        $startDate = Carbon::now()->subMonths(3);
        $endDate = Carbon::now();

        $transactions = Transaction::where('user_id', $userId)
            ->where('type', 'expense')
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        if ($transactions->count() < 10) {
            return $defaultFactors;
        }

        $baseCurrency = Currency::where('code', 'BYN')->first();
        if (!$baseCurrency) {
            return $defaultFactors;
        }

        $ratesCache = $this->loadRatesForTransactions($transactions, $baseCurrency);

        $dailyTotals = array_fill(0, 7, 0.0);
        $dailyWeights = array_fill(0, 7, 0.0);

        $now = Carbon::now();

        foreach ($transactions as $transaction) {
            $amountInBase = $this->convertToBaseCurrency($transaction, $baseCurrency, $ratesCache);
            $dayOfWeek = Carbon::parse($transaction->date)->dayOfWeek;
            $daysAgo = Carbon::parse($transaction->date)->diffInDays($now);
            $weight = exp(-$daysAgo / 45);

            $dailyTotals[$dayOfWeek] += $amountInBase * $weight;
            $dailyWeights[$dayOfWeek] += $weight;
        }

        $dailyAverages = [];
        for ($i = 0; $i < 7; $i++) {
            $dailyAverages[$i] = $dailyWeights[$i] > 0
                ? $dailyTotals[$i] / $dailyWeights[$i]
                : 0;
        }

        $totalWeightedSum = array_sum($dailyTotals);
        $totalWeight = array_sum($dailyWeights);

        if ($totalWeight <= 0) {
            return $defaultFactors;
        }

        $totalAverage = $totalWeightedSum / $totalWeight;

        if ($totalAverage <= 0) {
            return $defaultFactors;
        }

        $dataConfidence = min(0.8, $transactions->count() / 100);

        $factors = [];
        for ($i = 0; $i < 7; $i++) {
            if ($dailyAverages[$i] > 0) {
                $calculatedFactor = $dailyAverages[$i] / $totalAverage;
                $calculatedFactor = max(0.6, min(1.8, $calculatedFactor));
                $factors[$i] = ($calculatedFactor * $dataConfidence) + ($defaultFactors[$i] * (1 - $dataConfidence));
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

    private function getCategoryForecasts(int $userId, float $dailyBaseline, float $seasonalFactor, float $trend): array
    {
        $categories = Category::where('user_id', $userId)->where('type', 'expense')->get();
        $forecasts = [];

        $lastCompleteMonth = Carbon::now()->subMonth()->startOfMonth();
        $daysInMonth = $lastCompleteMonth->daysInMonth;

        foreach ($categories as $category) {
            $categoryMonthlyTotal = $this->getCategoryMonthlyExpense($userId, $category->id, $lastCompleteMonth->year, $lastCompleteMonth->month);

            if ($categoryMonthlyTotal <= 0) {
                for ($i = 2; $i <= 6; $i++) {
                    $monthAgo = Carbon::now()->subMonths($i)->startOfMonth();
                    $amount = $this->getCategoryMonthlyExpense($userId, $category->id, $monthAgo->year, $monthAgo->month);
                    if ($amount > 0) {
                        $categoryMonthlyTotal = $amount;
                        break;
                    }
                }
            }

            if ($categoryMonthlyTotal <= 0) continue;

            $categoryDailyBaseline = $categoryMonthlyTotal / $daysInMonth;
            $categoryTotal = $categoryDailyBaseline * 30 * max(0.5, min(1.5, $seasonalFactor)) * max(0.95, min(1.05, $trend));
            $sharePercent = $dailyBaseline > 0 ? round(($categoryDailyBaseline / $dailyBaseline) * 100, 1) : 0;

            $forecasts[] = [
                'category_id' => $category->id,
                'category_name' => $category->name,
                'color' => $category->color ?? '#3498db',
                'forecast' => round(max(0, $categoryTotal), 2),
                'daily_average' => round(max(0, $categoryTotal / 30), 2),
                'share_percent' => $sharePercent
            ];
        }

        usort($forecasts, fn($a, $b) => $b['forecast'] <=> $a['forecast']);
        return array_slice($forecasts, 0, 10);
    }

    private function calculateTrendFromData(array $data): float
    {
        if (count($data) < 4) return 1.0;
        $n = count($data);
        $firstHalf = array_sum(array_slice($data, 0, floor($n / 2))) / floor($n / 2);
        $secondHalf = array_sum(array_slice($data, floor($n / 2))) / ceil($n / 2);
        if ($firstHalf == 0) return 1.0;
        $trend = $secondHalf / $firstHalf;
        return max(0.95, min(1.05, $trend));
    }

    private function calculateSeasonalFactor(int $userId, array $data): float
    {
        if (count($data) < 24) return 1.0;

        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;

        $lastYearAvg = 0;
        $count = 0;

        for ($i = 0; $i < 12; $i++) {
            $date = Carbon::create($currentYear - 1, $i + 1, 1);
            $amount = $this->getMonthlyExpenseAmount($userId, $date->year, $date->month);
            if ($amount > 0) {
                $lastYearAvg += $amount;
                $count++;
            }
        }
        $lastYearAvg = $lastYearAvg / max(1, $count);
        if ($lastYearAvg == 0) return 1.0;

        $currentMonthAmount = $this->getMonthlyExpenseAmount($userId, $currentYear - 1, $currentMonth);
        return $currentMonthAmount / $lastYearAvg;
    }

    private function calculateModelMetrics(array $historical, array $forecast): array
    {
        if (empty($historical) || empty($forecast)) {
            return ['mape' => 0, 'mse' => 0, 'rmse' => 0, 'interpretation' => 'Нет данных'];
        }

        $n = min(count($historical), count($forecast));
        $mapeSum = 0;
        $mseSum = 0;
        $validCount = 0;

        for ($i = 0; $i < $n; $i++) {
            $actual = $historical[count($historical) - $n + $i];
            $predicted = $forecast[$i];
            if ($actual > 0) {
                $mapeSum += abs(($actual - $predicted) / $actual);
                $validCount++;
            }
            $mseSum += pow($actual - $predicted, 2);
        }

        $mape = $validCount > 0 ? ($mapeSum / $validCount) * 100 : 0;
        $mse = $n > 0 ? $mseSum / $n : 0;
        $rmse = sqrt($mse);
        $interpretation = $mape < 10 ? 'Высокая точность' : ($mape < 20 ? 'Хорошая точность' : ($mape < 30 ? 'Приемлемая точность' : 'Низкая точность'));

        return ['mape' => round($mape, 1), 'mse' => round($mse, 2), 'rmse' => round($rmse, 2), 'interpretation' => $interpretation];
    }

    private function calculateConfidence(int $userId): array
    {
        $oldest = Transaction::where('user_id', $userId)
            ->orderBy('date', 'asc')
            ->first();

        if (!$oldest) return ['percent' => 0, 'level' => 'low', 'text' => 'Нет данных'];

        $monthsOfData = Carbon::parse($oldest->date)->diffInMonths(Carbon::now());
        $monthsScore = $this->calculateMonthsScore($monthsOfData);
        $monthlyExpenses = $this->getCompleteMonthsExpensesArray($userId, 12);
        $stabilityScore = $this->calculateStabilityScore($monthlyExpenses);
        $totalScore = ($monthsScore * 0.5) + ($stabilityScore * 0.5);
        $totalScore = round(min(100, max(0, $totalScore)));

        if ($totalScore >= 70) return ['percent' => $totalScore, 'level' => 'high', 'text' => 'Высокая надежность'];
        if ($totalScore >= 45) return ['percent' => $totalScore, 'level' => 'medium', 'text' => 'Средняя надежность'];
        return ['percent' => $totalScore, 'level' => 'low', 'text' => 'Низкая надежность'];
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