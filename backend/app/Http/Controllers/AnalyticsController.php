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
                'month' => 'nullable|integer|min:1|max:12',
                'include_anomalies' => 'nullable|in:true,false,1,0'
            ]);

            $period = $validated['period'] ?? 'month';
            $year = $validated['year'] ?? date('Y');
            $month = $validated['month'] ?? date('m');
            $includeAnomalies = $request->boolean('include_anomalies', false);

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

            // Доходы - всегда учитываем ВСЕ
            $incomeTransactions = Transaction::where('user_id', $userId)
                ->where('type', 'income')
                ->whereBetween('date', [$startDate, $endDate])
                ->with(['currency'])
                ->get();

            // Расходы - по умолчанию исключаем аномалии, можно включить параметром
            $expenseQuery = Transaction::where('user_id', $userId)
                ->where('type', 'expense')
                ->whereBetween('date', [$startDate, $endDate])
                ->with(['categories', 'currency']);

            if (!$includeAnomalies) {
                $expenseQuery->where('is_anomaly', false);
            }

            $expenseTransactions = $expenseQuery->get();

            $baseCurrency = Currency::where('code', 'BYN')->first();
            if (!$baseCurrency) {
                return response()->json(['status' => 'error', 'message' => 'Базовая валюта BYN не найдена'], 500);
            }

            // Конвертируем доходы
            $incomeRatesCache = $this->loadRatesForTransactions($incomeTransactions, $baseCurrency);
            $totalIncome = 0;
            foreach ($incomeTransactions as $transaction) {
                $totalIncome += $this->convertToBaseCurrency($transaction, $baseCurrency, $incomeRatesCache);
            }

            // Конвертируем расходы
            $expenseRatesCache = $this->loadRatesForTransactions($expenseTransactions, $baseCurrency);
            $totalExpense = 0;
            $categoryTotals = [];

            foreach ($expenseTransactions as $transaction) {
                $amountInBase = $this->convertToBaseCurrency($transaction, $baseCurrency, $expenseRatesCache);
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

            // Финансовое здоровье - считаем с учетом всех расходов
            $financialHealth = $this->calculateFinancialHealth($userId, $endDate, true);

            // Статистика по исключенным аномалиям
            $excludedAnomalies = $this->getExcludedAnomaliesStats($userId, $startDate, $endDate, $includeAnomalies);

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
                    'financial_health' => $financialHealth,
                    'anomalies_info' => $excludedAnomalies
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Overview error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Ошибка: ' . $e->getMessage()], 500);
        }
    }

    private function getExcludedAnomaliesStats(int $userId, Carbon $startDate, Carbon $endDate, bool $includeAnomalies): array
    {
        if ($includeAnomalies) {
            return [
                'excluded' => false,
                'message' => 'В расчет включены все транзакции, включая разовые'
            ];
        }

        $anomaliesCount = Transaction::where('user_id', $userId)
            ->where('type', 'expense')
            ->where('is_anomaly', true)
            ->whereBetween('date', [$startDate, $endDate])
            ->count();

        $anomaliesAmount = Transaction::where('user_id', $userId)
            ->where('type', 'expense')
            ->where('is_anomaly', true)
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('amount');

        return [
            'excluded' => true,
            'count' => $anomaliesCount,
            'total_amount' => round($anomaliesAmount, 2),
            'message' => $anomaliesCount > 0
                ? "{$anomaliesCount} разовых транзакций исключены из аналитики"
                : null
        ];
    }

    // ==================== ФИНАНСОВОЕ ЗДОРОВЬЕ ====================

    private function calculateFinancialHealth(int $userId, ?Carbon $currentDate = null, bool $includeAnomalies = true): array
    {
        try {
            $currentDate = $currentDate ?? Carbon::now();
            $salaryDay = $this->getUserSalaryDay($userId);
            $threeMonthsAgo = $currentDate->copy()->subMonths(3);

            $avgMonthlyIncome = Transaction::where('user_id', $userId)
                    ->where('type', 'income')
                    ->whereBetween('date', [$threeMonthsAgo, $currentDate])
                    ->sum('amount') / 3;

            $expenseQuery = Transaction::where('user_id', $userId)
                ->where('type', 'expense')
                ->whereBetween('date', [$threeMonthsAgo, $currentDate]);

            if (!$includeAnomalies) {
                $expenseQuery->where('is_anomaly', false);
            }

            $avgMonthlyExpense = $expenseQuery->sum('amount') / 3;
            $savings = $this->getUserSavings($userId, $includeAnomalies);
            $monthlyLoanPayments = $this->getMonthlyLoanPayments($userId, $includeAnomalies);
            $cycleBalance = $this->getCurrentCycleBalance($userId, $currentDate, $salaryDay, $includeAnomalies);
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
                ],
                'calculation_note' => $includeAnomalies
                    ? 'Расчет с учетом всех транзакций (включая разовые)'
                    : 'Расчет без учета разовых транзакций'
            ];
        } catch (\Exception $e) {
            Log::error('Financial health error: ' . $e->getMessage());
            return ['score' => 0, 'status' => 'poor', 'status_label' => 'Не определено', 'color' => '#95a5a6', 'components' => []];
        }
    }

    private function getCurrentCycleBalance(int $userId, Carbon $currentDate, int $salaryDay, bool $includeAnomalies = true): float
    {
        $lastSalaryDate = Carbon::create($currentDate->year, $currentDate->month, $salaryDay);
        if ($lastSalaryDate > $currentDate) $lastSalaryDate->subMonth();

        $income = Transaction::where('user_id', $userId)
            ->where('type', 'income')
            ->where('date', '>=', $lastSalaryDate)
            ->where('date', '<=', $currentDate)
            ->sum('amount');

        $expenseQuery = Transaction::where('user_id', $userId)
            ->where('type', 'expense')
            ->where('date', '>=', $lastSalaryDate)
            ->where('date', '<=', $currentDate);

        if (!$includeAnomalies) {
            $expenseQuery->where('is_anomaly', false);
        }

        $expense = $expenseQuery->sum('amount');

        return $income - $expense;
    }

    private function getUserSalaryDay(int $userId): int {
        $incomes = Transaction::where('user_id', $userId)
            ->where('type', 'income')
            ->where('is_anomaly', false)
            ->where('date', '>=', Carbon::now()->subMonths(3))
            ->get();

        if ($incomes->isEmpty()) return 25;

        $amountCount = [];
        foreach ($incomes as $income) {
            $roundedAmount = round($income->amount / 100) * 100;
            $amountCount[$roundedAmount] = ($amountCount[$roundedAmount] ?? 0) + 1;
        }

        $maxCount = 0;
        $mostFrequentAmount = 0;
        foreach ($amountCount as $amount => $count) {
            if ($count > $maxCount) {
                $maxCount = $count;
                $mostFrequentAmount = $amount;
            }
        }

        $potentialSalaries = $incomes->filter(
            fn($t) => round($t->amount / 100) * 100 == $mostFrequentAmount
        );

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

    private function getUserSavings(int $userId, bool $includeAnomalies = true): float
    {
        $totalIncome = Transaction::where('user_id', $userId)
            ->where('type', 'income')
            ->sum('amount');

        $expenseQuery = Transaction::where('user_id', $userId)
            ->where('type', 'expense');

        if (!$includeAnomalies) {
            $expenseQuery->where('is_anomaly', false);
        }

        $totalExpense = $expenseQuery->sum('amount');

        return max(0, $totalIncome - $totalExpense);
    }

    private function getMonthlyLoanPayments(int $userId, bool $includeAnomalies = true): float
    {
        $loanCategory = Category::where('user_id', $userId)->where('name', 'Кредиты')->where('type', 'expense')->first();
        if ($loanCategory) {
            $threeMonthsAgo = Carbon::now()->subMonths(3);

            $query = Transaction::where('user_id', $userId)
                ->whereHas('categories', fn($q) => $q->where('categories.id', $loanCategory->id))
                ->whereBetween('date', [$threeMonthsAgo, Carbon::now()]);

            if (!$includeAnomalies) {
                $query->where('is_anomaly', false);
            }

            $total = $query->sum('amount');
            return $total / 3;
        }
        return 0;
    }

    // ==================== МЕТОДЫ ДЛЯ ПРОГНОЗИРОВАНИЯ ====================

    private function getMonthlyExpenseAmount(int $userId, int $year, int $month): float
    {
        $transactions = Transaction::where('user_id', $userId)
            ->where('type', 'expense')
            ->where('is_anomaly', false)
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
            ->where('is_anomaly', false)
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
        if ($n < 15) return $this->linearRegression($data, $forecastSteps);
        if ($n < 24) return $this->doubleExponentialSmoothingImproved($data, $forecastSteps);
        return $this->holtWinters($data, 12, $forecastSteps);
    }

    // ==================== ОСНОВНОЙ ПУБЛИЧНЫЙ МЕТОД FORECAST ====================

    public function forecast(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $userId = $this->getUserId();

            $hasRegularTransactions = Transaction::where('user_id', $userId)
                ->where('is_anomaly', false)
                ->exists();

            if (!$hasRegularTransactions) {
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
            $excludedAnomalies = $this->getExcludedAnomaliesInfo($userId);

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
                    'excluded_anomalies' => $excludedAnomalies,
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

    private function getExcludedAnomaliesInfo(int $userId): array
    {
        $totalAnomalies = Transaction::where('user_id', $userId)
            ->where('is_anomaly', true)
            ->where('type', 'expense')
            ->count();

        $recentAnomalies = Transaction::where('user_id', $userId)
            ->where('is_anomaly', true)
            ->where('type', 'expense')
            ->where('date', '>=', Carbon::now()->subMonths(3))
            ->count();

        $anomaliesAmount = Transaction::where('user_id', $userId)
            ->where('is_anomaly', true)
            ->where('type', 'expense')
            ->sum('amount');

        return [
            'total_count' => $totalAnomalies,
            'recent_3_months_count' => $recentAnomalies,
            'total_amount' => round($anomaliesAmount, 2),
            'excluded_from_forecast' => true,
            'message' => $totalAnomalies > 0
                ? "{$totalAnomalies} разовых транзакций исключены из прогноза"
                : null
        ];
    }

    // ==================== ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ ДЛЯ ОТВЕТА ====================

    private function getReliabilityMessage(int $monthsCount): string
    {
        if ($monthsCount < 7) return '⚠️ Прогноз на основе минимальных данных (3-6 месяцев). Рекомендуется накопить больше истории.';
        if ($monthsCount < 15) return '📊 Прогноз имеет хорошую точность. Для улучшения рекомендуется накопить 15+ месяцев данных.';
        if ($monthsCount < 24) return '✅ Прогноз имеет высокую точность.';
        return '✅ Прогноз имеет высокую точность с учетом сезонности.';
    }

    private function getMethodName(int $monthsCount): string
    {
        if ($monthsCount < 7) return 'SimpleExtrapolation';
        if ($monthsCount < 15) return 'LinearRegression';
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
            ->where('is_anomaly', false)
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

        // Получаем информацию о модели для рекомендаций
        $completeMonthsData = $this->getCompleteMonthsExpensesArray($userId, 24);
        $monthsCount = count($completeMonthsData);
        $modelInfo = $this->getForecastModelInfo($monthsCount);
        $bufferPercent = $modelInfo['buffer_percent'] / 100;

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
            $forecastAmount = round(max(0, $categoryTotal), 2);
            $sharePercent = $dailyBaseline > 0 ? round(($categoryDailyBaseline / $dailyBaseline) * 100, 1) : 0;

            $currentLimit = $category->budget_limit;
            $expenseType = $category->expense_type ?? 'variable';

            $forecastItem = [
                'category_id' => $category->id,
                'category_name' => $category->name,
                'color' => $category->color ?? '#3498db',
                'forecast' => $forecastAmount,
                'daily_average' => round(max(0, $categoryTotal / 30), 2),
                'share_percent' => $sharePercent,
                'current_limit' => $currentLimit,
                'expense_type' => $expenseType,
            ];

            // Добавляем рекомендацию по лимиту
            if ($currentLimit && $currentLimit > 0) {
                $deviationPercent = round(($forecastAmount - $currentLimit) / $currentLimit * 100, 1);
                $absDeviation = abs($deviationPercent);

                if ($forecastAmount <= $currentLimit * 0.8) {
                    $limitStatus = 'good';
                } elseif ($forecastAmount <= $currentLimit) {
                    $limitStatus = 'warning';
                } else {
                    $limitStatus = 'critical';
                }

                $forecastItem['limit_status'] = $limitStatus;
                $forecastItem['deviation_percent'] = $deviationPercent;

                if ($expenseType !== 'fixed' && $modelInfo['can_recommend'] && $absDeviation >= 10) {
                    if ($deviationPercent > 0) {
                        $increaseStep = min(25, ceil($deviationPercent / 2));
                        $recommendedLimit = round($currentLimit * (1 + $increaseStep / 100), 2);
                        $forecastItem['recommendation'] = [
                            'action' => 'increase',
                            'recommended_limit' => $recommendedLimit,
                            'deviation_percent' => $deviationPercent,
                            'priority' => $absDeviation >= 30 ? 'high' : ($absDeviation >= 15 ? 'medium' : 'low')
                        ];
                    } else {
                        $decreaseStep = min(20, ceil($absDeviation / 2));
                        $recommendedLimit = round($currentLimit * (1 - $decreaseStep / 100), 2);
                        $minReasonable = $forecastAmount * 0.85;
                        if ($recommendedLimit < $minReasonable) {
                            $recommendedLimit = round($minReasonable, 2);
                        }
                        $forecastItem['recommendation'] = [
                            'action' => 'decrease',
                            'recommended_limit' => $recommendedLimit,
                            'deviation_percent' => $deviationPercent,
                            'priority' => 'low'
                        ];
                    }
                }
            } elseif ($modelInfo['can_recommend']) {
                // Нет лимита - предложить установить
                $recommendedLimit = round($forecastAmount * (1 + $bufferPercent), 2);
                $forecastItem['recommendation'] = [
                    'action' => 'set',
                    'recommended_limit' => $recommendedLimit,
                    'priority' => 'medium'
                ];
            }

            $forecasts[] = $forecastItem;
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
            ->where('is_anomaly', false)
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

    // ==================== МЕТОДЫ ДЛЯ РЕКОМЕНДАЦИЙ ЛИМИТОВ КАТЕГОРИЙ ====================

    /**
     * Получить рекомендации по лимитам категорий на основе прогноза
     * Рекомендации привязаны к модели прогнозирования
     */
    public function getBudgetRecommendations(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $userId = $this->getUserId();

            $validated = $request->validate([
                'month' => 'nullable|integer|min:1|max:12',
                'year' => 'nullable|integer|min:2000|max:2100'
            ]);

            $targetMonth = $validated['month'] ?? Carbon::now()->addMonth()->month;
            $targetYear = $validated['year'] ?? Carbon::now()->addMonth()->year;

            // Получаем данные для прогноза и определяем модель
            $completeMonthsData = $this->getCompleteMonthsExpensesArray($userId, 30);
            $monthsCount = count($completeMonthsData);
            $monthlyForecast = $this->selectForecastMethod($completeMonthsData, 3);
            $forecastModelInfo = $this->getForecastModelInfo($monthsCount);

            // Если модель не позволяет давать рекомендации
            if (!$forecastModelInfo['can_recommend']) {
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'forecast_model' => $forecastModelInfo,
                        'can_recommend' => false,
                        'message' => $forecastModelInfo['message'],
                        'recommendations' => []
                    ]
                ]);
            }

            // Получаем прогноз общих расходов
            $totalMonthlyExpense = $this->getMonthlyExpenseAmount($userId, $targetYear, $targetMonth);
            if ($totalMonthlyExpense <= 0) {
                $forecast = $this->selectForecastMethod($completeMonthsData, 1);
                $totalMonthlyExpense = $forecast[0] ?? 2000;
            }

            // Получаем категории и формируем рекомендации
            $categories = Category::where('user_id', $userId)
                ->where('type', 'expense')
                ->get();

            $recommendations = [];
            $categoriesData = [];

            foreach ($categories as $category) {
                // Получаем историческую долю категории
                $averageShare = $this->getCategoryAverageShare($userId, $category->id, 6);
                $expenseType = $category->expense_type ?? 'variable';

                if ($averageShare > 0) {
                    $forecastAmount = round($totalMonthlyExpense * $averageShare / 100, 2);
                } else {
                    $forecastAmount = $this->getCategoryMonthlyExpense($userId, $category->id, $targetYear, $targetMonth);
                }

                // Корректируем прогноз с учетом модели
                $adjustedAmount = $this->adjustForecastByModel($forecastAmount, $expenseType, $forecastModelInfo);
                $confidenceInterval = $this->calculateConfidenceInterval($adjustedAmount, $forecastModelInfo);

                $categoriesData[$category->id] = [
                    'category_id' => $category->id,
                    'category_name' => $category->name,
                    'forecast' => $adjustedAmount,
                    'base_forecast' => $forecastAmount,
                    'confidence_interval' => $confidenceInterval,
                    'current_limit' => $category->budget_limit,
                    'expense_type' => $expenseType,
                    'color' => $category->color ?? '#3498db'
                ];
            }

            foreach ($categories as $category) {
                $forecastData = $categoriesData[$category->id] ?? null;
                if (!$forecastData) continue;

                $expenseType = $forecastData['expense_type'];

                // Для фиксированных расходов - только информирование
                if ($expenseType === 'fixed') {
                    $recommendations[] = $this->buildFixedExpenseInfo($category, $forecastData, $forecastModelInfo);
                    continue;
                }

                // Рассчитываем рекомендацию
                $recommendation = $this->calculateCategoryRecommendation($category, $forecastData, $expenseType, $forecastModelInfo);

                if ($recommendation) {
                    $recommendations[] = $recommendation;
                }
            }

            // Сортируем по приоритету
            usort($recommendations, fn($a, $b) =>
                $this->getPriorityWeight($b['priority']) <=> $this->getPriorityWeight($a['priority'])
            );

            return response()->json([
                'status' => 'success',
                'data' => [
                    'forecast_model' => $forecastModelInfo,
                    'target_month' => [
                        'month' => $targetMonth,
                        'year' => $targetYear,
                        'name' => Carbon::create($targetYear, $targetMonth, 1)->translatedFormat('F Y')
                    ],
                    'recommendations' => $recommendations,
                    'summary' => $this->getRecommendationsSummary($recommendations, $forecastModelInfo)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get budget recommendations error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Применить рекомендацию по лимиту
     */
    public function applyBudgetRecommendation(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $userId = $this->getUserId();

            $validated = $request->validate([
                'category_id' => 'required|exists:categories,id',
                'limit_amount' => 'required|numeric|min:0',
                'action_type' => 'required|in:increase,decrease,set,skip'
            ]);

            $category = Category::where('user_id', $userId)
                ->where('id', $validated['category_id'])
                ->firstOrFail();

            if ($validated['action_type'] === 'skip') {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Рекомендация пропущена'
                ]);
            }

            $oldLimit = $category->budget_limit;
            $category->budget_limit = $validated['limit_amount'];
            $category->save();

            Log::info("Budget limit updated", [
                'user_id' => $userId,
                'category_id' => $category->id,
                'category_name' => $category->name,
                'old_limit' => $oldLimit,
                'new_limit' => $validated['limit_amount']
            ]);

            return response()->json([
                'status' => 'success',
                'message' => "Лимит для категории '{$category->name}' обновлен",
                'data' => [
                    'category_id' => $category->id,
                    'category_name' => $category->name,
                    'old_limit' => $oldLimit,
                    'new_limit' => $validated['limit_amount']
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Apply budget recommendation error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Получить информацию о модели прогноза для рекомендаций
     */
    private function getForecastModelInfo(int $monthsCount): array
    {
        if ($monthsCount < 7) {
            return [
                'name' => 'SimpleExtrapolation',
                'months_used' => $monthsCount,
                'can_recommend' => false,
                'confidence_level' => 'low',
                'buffer_percent' => 0,
                'recommendation_type' => 'none',
                'message' => '⚠️ Недостаточно данных (3-6 месяцев). Накопите 7+ месяцев для точных рекомендаций.',
                'action_text' => 'Только наблюдение'
            ];
        }

        if ($monthsCount < 15) {
            return [
                'name' => 'LinearRegression',
                'months_used' => $monthsCount,
                'can_recommend' => true,
                'confidence_level' => 'medium',
                'buffer_percent' => 15,
                'recommendation_type' => 'cautious',
                'message' => '📊 Прогноз на основе линейной регрессии. Точность средняя. Рекомендуемый запас: 15%.',
                'action_text' => 'Предложить с запасом'
            ];
        }

        if ($monthsCount < 24) {
            return [
                'name' => 'DoubleExponentialSmoothing',
                'months_used' => $monthsCount,
                'can_recommend' => true,
                'confidence_level' => 'high',
                'buffer_percent' => 10,
                'recommendation_type' => 'balanced',
                'message' => '✅ Прогноз на основе двойного экспоненциального сглаживания. Высокая точность. Запас: 10%.',
                'action_text' => 'Предложить оптимально'
            ];
        }

        return [
            'name' => 'HoltWinters',
            'months_used' => $monthsCount,
            'can_recommend' => true,
            'confidence_level' => 'very_high',
            'buffer_percent' => 7,
            'recommendation_type' => 'precise',
            'message' => '🎯 Прогноз на основе модели Хольта-Винтерса с учетом сезонности. Максимальная точность. Запас: 7%.',
            'action_text' => 'Предложить точно'
        ];
    }

    /**
     * Скорректировать прогноз в зависимости от модели
     */
    private function adjustForecastByModel(float $amount, string $expenseType, array $modelInfo): float
    {
        $buffer = $modelInfo['buffer_percent'] / 100;

        if ($expenseType === 'discretionary') {
            $buffer = $buffer * 0.7;
        } elseif ($expenseType === 'fixed') {
            $buffer = 0;
        }

        return round($amount * (1 + $buffer), 2);
    }

    /**
     * Рассчитать доверительный интервал
     */
    private function calculateConfidenceInterval(float $amount, array $modelInfo): array
    {
        $percent = $modelInfo['buffer_percent'] / 100;

        return [
            'lower' => round($amount * (1 - $percent), 2),
            'upper' => round($amount * (1 + $percent), 2),
            'percent' => $percent * 100
        ];
    }

    /**
     * Получить среднюю долю категории в расходах
     */
    private function getCategoryAverageShare(int $userId, int $categoryId, int $months = 6): float
    {
        $totalExpenses = 0;
        $categoryExpenses = 0;
        $now = Carbon::now();

        for ($i = 1; $i <= $months; $i++) {
            $date = $now->copy()->subMonths($i);
            $monthTotal = $this->getMonthlyExpenseAmount($userId, $date->year, $date->month);
            $categoryTotal = $this->getCategoryMonthlyExpense($userId, $categoryId, $date->year, $date->month);

            $totalExpenses += $monthTotal;
            $categoryExpenses += $categoryTotal;
        }

        if ($totalExpenses <= 0) return 0;

        return round($categoryExpenses / $totalExpenses * 100, 1);
    }

    /**
     * Рассчитать рекомендацию по категории
     */
    private function calculateCategoryRecommendation(Category $category, array $forecastData, string $expenseType, array $modelInfo): ?array
    {
        $currentLimit = $category->budget_limit;
        $forecast = $forecastData['forecast'];
        $baseForecast = $forecastData['base_forecast'];
        $confidenceInterval = $forecastData['confidence_interval'];

        // Нет лимита - предложить установить
        if (!$currentLimit || $currentLimit <= 0) {
            return [
                'category_id' => $category->id,
                'category_name' => $category->name,
                'color' => $forecastData['color'],
                'expense_type' => $expenseType,
                'current_limit' => null,
                'forecast' => $forecast,
                'base_forecast' => $baseForecast,
                'recommended_limit' => $forecast,
                'confidence_interval' => $confidenceInterval,
                'deviation_percent' => null,
                'priority' => 'medium',
                'reason' => "У категории нет лимита. Рекомендуемый лимит: {$forecast} BYN",
                'action' => 'set',
                'action_text' => "Установить лимит {$forecast} BYN",
                'model_note' => $modelInfo['action_text']
            ];
        }

        // Расчет отклонения
        $deviationPercent = round(($forecast - $currentLimit) / $currentLimit * 100, 1);
        $absDeviation = abs($deviationPercent);

        // Минимальное отклонение для рекомендации
        $minDeviation = $modelInfo['name'] === 'LinearRegression' ? 15 : ($modelInfo['name'] === 'DoubleExponentialSmoothing' ? 10 : 7);

        if ($absDeviation < $minDeviation) {
            return null;
        }

        // Определяем приоритет
        $highThreshold = $modelInfo['name'] === 'LinearRegression' ? 30 : ($modelInfo['name'] === 'DoubleExponentialSmoothing' ? 25 : 20);
        $mediumThreshold = $modelInfo['name'] === 'LinearRegression' ? 15 : ($modelInfo['name'] === 'DoubleExponentialSmoothing' ? 12 : 10);

        if ($absDeviation >= $highThreshold) $priority = 'high';
        elseif ($absDeviation >= $mediumThreshold) $priority = 'medium';
        else $priority = 'low';

        if ($deviationPercent > 0) {
            // Увеличение лимита
            $increaseStep = $modelInfo['name'] === 'LinearRegression' ? min(25, ceil($deviationPercent / 2)) :
                ($modelInfo['name'] === 'DoubleExponentialSmoothing' ? min(20, ceil($deviationPercent / 1.5)) : min(15, $deviationPercent));
            $increaseStep = max(5, $increaseStep);
            $recommendedLimit = round($currentLimit * (1 + $increaseStep / 100), 2);

            return [
                'category_id' => $category->id,
                'category_name' => $category->name,
                'color' => $forecastData['color'],
                'expense_type' => $expenseType,
                'current_limit' => $currentLimit,
                'forecast' => $forecast,
                'base_forecast' => $baseForecast,
                'recommended_limit' => $recommendedLimit,
                'confidence_interval' => $confidenceInterval,
                'deviation_percent' => $deviationPercent,
                'priority' => $priority,
                'reason' => "Прогноз превышает текущий лимит на {$deviationPercent}%",
                'action' => 'increase',
                'action_text' => "Увеличить лимит до {$recommendedLimit} BYN",
                'model_note' => $modelInfo['action_text']
            ];
        } else {
            // Уменьшение лимита
            $decreaseStep = $modelInfo['name'] === 'LinearRegression' ? min(20, ceil($absDeviation / 2)) :
                ($modelInfo['name'] === 'DoubleExponentialSmoothing' ? min(15, ceil($absDeviation / 2)) : min(12, ceil($absDeviation / 2)));
            $decreaseStep = max(5, $decreaseStep);
            $recommendedLimit = round($currentLimit * (1 - $decreaseStep / 100), 2);

            $minReasonable = $forecast * 0.85;
            if ($recommendedLimit < $minReasonable) {
                $recommendedLimit = round($minReasonable, 2);
            }

            return [
                'category_id' => $category->id,
                'category_name' => $category->name,
                'color' => $forecastData['color'],
                'expense_type' => $expenseType,
                'current_limit' => $currentLimit,
                'forecast' => $forecast,
                'base_forecast' => $baseForecast,
                'recommended_limit' => $recommendedLimit,
                'confidence_interval' => $confidenceInterval,
                'deviation_percent' => $deviationPercent,
                'priority' => $priority,
                'reason' => "Текущий лимит превышает прогноз на " . abs($deviationPercent) . "%",
                'action' => 'decrease',
                'action_text' => "Уменьшить лимит до {$recommendedLimit} BYN",
                'model_note' => $modelInfo['action_text']
            ];
        }
    }

    /**
     * Информация для фиксированных расходов
     */
    private function buildFixedExpenseInfo(Category $category, array $forecastData, array $modelInfo): array
    {
        return [
            'category_id' => $category->id,
            'category_name' => $category->name,
            'color' => $forecastData['color'],
            'expense_type' => 'fixed',
            'current_limit' => $category->budget_limit,
            'forecast' => $forecastData['forecast'],
            'confidence_interval' => $forecastData['confidence_interval'],
            'priority' => 'info',
            'reason' => 'Фиксированный расход. Лимит не рекомендуется изменять.',
            'action' => 'info',
            'action_text' => 'Только наблюдение',
            'model_note' => $modelInfo['action_text']
        ];
    }

    /**
     * Вес приоритета для сортировки
     */
    private function getPriorityWeight(string $priority): int
    {
        return match($priority) {
            'high' => 3,
            'medium' => 2,
            'low' => 1,
            default => 0
        };
    }

    /**
     * Сводка по рекомендациям
     */
    private function getRecommendationsSummary(array $recommendations, array $modelInfo): array
    {
        $byAction = ['increase' => 0, 'decrease' => 0, 'set' => 0, 'info' => 0];
        $byPriority = ['high' => 0, 'medium' => 0, 'low' => 0, 'info' => 0];

        foreach ($recommendations as $rec) {
            $byAction[$rec['action']]++;
            $byPriority[$rec['priority']]++;
        }

        return [
            'total' => count($recommendations),
            'by_action' => $byAction,
            'by_priority' => $byPriority,
            'model_confidence' => $modelInfo['confidence_level'],
            'recommendation_type' => $modelInfo['recommendation_type']
        ];
    }
}