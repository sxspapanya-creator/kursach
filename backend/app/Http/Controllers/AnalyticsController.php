<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Category;
use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Services\Analytics\AnomalyService;
use App\Services\Analytics\ForecastService;
use App\Services\Analytics\ForecastResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class AnalyticsController extends Controller
{
    protected AnomalyService $anomalyService;
    protected ForecastService $forecastService;

    public function __construct()
    {
        $this->anomalyService = new AnomalyService();
        $this->forecastService = new ForecastService(new ForecastResolver());
    }

    protected function getUserId()
    {
        $userId = Auth::id();
        if (!$userId) abort(401, 'Unauthorized');
        return $userId;
    }

    public function monthlyTrends(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $userId = $this->getUserId();
            $months = (int) $request->input('months', 12);

            $baseCurrency = Currency::where('code', 'BYN')->first();
            if (!$baseCurrency) {
                return response()->json(['status' => 'error', 'message' => 'Базовая валюта BYN не найдена'], 500);
            }

            $startDate = Carbon::now()->subMonths($months - 1)->startOfMonth();

            $transactions = Transaction::where('user_id', $userId)
                ->with(['currency'])
                ->where('date', '>=', $startDate)
                ->get();

            $grouped = $transactions->groupBy(fn($t) => $t->date->format('Y-m'));

            $ratesCache = $this->loadRatesForTransactions($transactions, $baseCurrency);

            $trends = [];
            $currentDate = Carbon::now();

            for ($i = $months - 1; $i >= 0; $i--) {
                $date = $currentDate->copy()->subMonths($i);
                $monthKey = $date->format('Y-m');

                $monthTransactions = $grouped[$monthKey] ?? collect();

                $income = 0;
                $expense = 0;

                foreach ($monthTransactions as $transaction) {
                    $amountInBase = $this->convertToBaseCurrency($transaction, $baseCurrency, $ratesCache);

                    if ($transaction->type === 'income') {
                        $income += $amountInBase;
                    } else {
                        $expense += $amountInBase;
                    }
                }

                $trends[] = [
                    'month' => $monthKey,
                    'month_label' => $date->translatedFormat('F Y'),
                    'income' => round($income, 2),
                    'expense' => round($expense, 2),
                    'balance' => round($income - $expense, 2),
                    'savings_rate' => $income > 0 ? round((($income - $expense) / $income) * 100, 1) : 0
                ];
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'trends' => $trends,
                    'base_currency' => $baseCurrency->code,
                    'period_months' => $months
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('MonthlyTrends error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Ошибка: ' . $e->getMessage()], 500);
        }
    }
    // ==================== КОНВЕРТАЦИЯ ВАЛЮТ ====================

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

        $rate = $this->getRateFromCache($ratesCache, $transaction->currency_id, $transaction->date, $baseCurrency);

        if ($rate === null) {
            $rate = $this->getRateFromDatabase($transaction->currency_id, $baseCurrency->id, $transaction->date);
        }

        if ($rate === null) {
            Log::warning("Курс не найден для валюты {$transaction->currency_id} на дату {$transaction->date}");
            return (float) $transaction->amount;
        }

        return (float) $transaction->amount * $rate;
    }

    // ==================== ЗАРПЛАТНЫЙ ДЕНЬ ====================

    private function getUserSalaryDay(int $userId): int
    {
        $user = \App\Models\User::find($userId);
        return $user->salary_day ?? 25;
    }

    private function getDaysUntilNextSalary(int $userId, int $salaryDay, Carbon $currentDate): int
    {
        $today = $currentDate->copy()->startOfDay();
        $nextSalaryDate = Carbon::create($today->year, $today->month, $salaryDay)->startOfDay();

        if ($nextSalaryDate <= $today) {
            $nextSalaryDate->addMonth();
        }

        return $today->diffInDays($nextSalaryDate);
    }

    // ==================== СТАТИСТИКА ПО АНОМАЛИЯМ ====================

    private function getAnomaliesStats(int $userId, ?Carbon $startDate = null, ?Carbon $endDate = null, bool $forForecast = false): array
    {
        $query = Transaction::where('user_id', $userId)
            ->where('type', 'expense')
            ->where('is_anomaly', true);

        if ($startDate && $endDate && !$forForecast) {
            $query->whereBetween('date', [$startDate, $endDate]);
        }

        $totalCount = $query->count();
        $totalAmount = $query->sum('amount');

        if ($forForecast) {
            $recentCount = Transaction::where('user_id', $userId)
                ->where('type', 'expense')
                ->where('is_anomaly', true)
                ->where('date', '>=', Carbon::now()->subMonths(3))
                ->count();

            return [
                'total_count' => $totalCount,
                'recent_3_months_count' => $recentCount,
                'total_amount' => round($totalAmount, 2),
                'excluded_from_forecast' => true,
                'message' => $totalCount > 0 ? "{$totalCount} разовых транзакций исключены из прогноза" : null
            ];
        }

        return [
            'excluded' => true,
            'count' => $totalCount,
            'total_amount' => round($totalAmount, 2),
            'message' => $totalCount > 0 ? "{$totalCount} разовых транзакций исключены из аналитики" : null
        ];
    }

    // ==================== ФИНАНСОВОЕ ЗДОРОВЬЕ ====================

    private function getMonthlyLoanPayments(int $userId): float
    {
        $loanCategory = Category::where('user_id', $userId)
            ->whereIn('name', ['Кредиты', 'Кредит', 'Займы', 'Рассрочка'])
            ->where('type', 'expense')
            ->first();

        if (!$loanCategory) return 0;

        $threeMonthsAgo = Carbon::now()->subMonths(3);

        return Transaction::where('user_id', $userId)
                ->whereHas('categories', fn($q) => $q->where('categories.id', $loanCategory->id))
                ->whereBetween('date', [$threeMonthsAgo, Carbon::now()])
                ->sum('amount') / 3;
    }

    private function getCurrentCycleBalance(int $userId, Carbon $currentDate, int $salaryDay): float
    {
        $lastSalaryDate = Carbon::create($currentDate->year, $currentDate->month, $salaryDay);
        if ($lastSalaryDate > $currentDate) $lastSalaryDate->subMonth();

        $income = Transaction::where('user_id', $userId)
            ->where('type', 'income')
            ->whereBetween('date', [$lastSalaryDate, $currentDate])
            ->sum('amount');

        $expense = Transaction::where('user_id', $userId)
            ->where('type', 'expense')
            ->whereBetween('date', [$lastSalaryDate, $currentDate])
            ->sum('amount');

        return $income - $expense;
    }

    private function calculateFinancialHealth(int $userId): array
    {
        try {
            $currentDate = Carbon::now();

            $salaryDay = $this->getUserSalaryDay($userId);
            $threeMonthsAgo = $currentDate->copy()->subMonths(3);

            $stats = Transaction::where('user_id', $userId)
                ->whereBetween('date', [$threeMonthsAgo, $currentDate])
                ->selectRaw('
                    SUM(CASE WHEN type = "income" THEN amount ELSE 0 END) as total_income,
                    SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END) as total_expense
                ')
                ->first();

            $totalIncome3Months = $stats->total_income ?? 0;
            $totalExpense3Months = $stats->total_expense ?? 0;

            $avgMonthlyIncome = $totalIncome3Months / 3;
            $avgMonthlyExpense = $totalExpense3Months / 3;

            $allStats = Transaction::where('user_id', $userId)
                ->selectRaw('
                    SUM(CASE WHEN type = "income" THEN amount ELSE 0 END) as total_income_all,
                    SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END) as total_expense_all
                ')
                ->first();

            $totalIncomeAll = $allStats->total_income_all ?? 0;
            $totalExpenseAll = $allStats->total_expense_all ?? 0;

            $savings = max(0, $totalIncomeAll - $totalExpenseAll);
            $monthlyLoanPayments = $this->getMonthlyLoanPayments($userId);
            $cycleBalance = $this->getCurrentCycleBalance($userId, $currentDate, $salaryDay);
            $daysUntilSalary = $this->getDaysUntilNextSalary($userId, $salaryDay, $currentDate);

            $neededUntilSalary = ($avgMonthlyExpense / 30) * max(1, $daysUntilSalary);

            // Ликвидность (30%)
            if ($cycleBalance <= 0) $liquidityScore = 0;
            elseif ($cycleBalance >= $neededUntilSalary * 1.5) $liquidityScore = 100;
            elseif ($cycleBalance >= $neededUntilSalary) $liquidityScore = 70;
            elseif ($cycleBalance >= $neededUntilSalary * 0.7) $liquidityScore = 40;
            else $liquidityScore = 10;

            // Подушка безопасности (30%) - цель 3 месяца
            if ($avgMonthlyExpense <= 0) {
                $emergencyFundScore = 50;
            } elseif ($savings >= $avgMonthlyExpense * 3) {
                $emergencyFundScore = 100;
            } elseif ($savings >= $avgMonthlyExpense * 2) {
                $emergencyFundScore = 70;
            } elseif ($savings >= $avgMonthlyExpense * 1) {
                $emergencyFundScore = 40;
            } elseif ($savings >= $avgMonthlyExpense * 0.5) {
                $emergencyFundScore = 20;
            } else {
                $emergencyFundScore = 0;
            }

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
                    'liquidity' => [
                        'score' => round($liquidityScore),
                        'balance' => round($cycleBalance, 2),
                        'needed_until_salary' => round($neededUntilSalary, 2),
                        'days_until_salary' => $daysUntilSalary
                    ],
                    'emergency_fund' => [
                        'score' => round($emergencyFundScore),
                        'savings' => round($savings, 2),
                        'months_coverage' => $avgMonthlyExpense > 0 ? round($savings / $avgMonthlyExpense, 1) : 0
                    ],
                    'debt_load' => [
                        'score' => round($debtLoadScore),
                        'monthly_payments' => round($monthlyLoanPayments, 2),
                        'percent_of_income' => $avgMonthlyIncome > 0 ? round(($monthlyLoanPayments / $avgMonthlyIncome) * 100) : 0
                    ],
                    'savings_rate' => [
                        'score' => round($savingsRateScore),
                        'rate' => round($savingsRate, 1),
                        'saved_amount' => round($availableAfterExpenses, 2)
                    ]
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Financial health error: ' . $e->getMessage());
            return ['score' => 0, 'status' => 'poor', 'status_label' => 'Не определено', 'color' => '#95a5a6', 'components' => []];
        }
    }

    // ==================== ПРОГНОЗИРОВАНИЕ ====================

    private function getExpensesForMonth(int $userId, int $year, int $month, ?int $categoryId = null): float
    {
        $query = Transaction::where('user_id', $userId)
            ->where('type', 'expense')
            ->where('is_anomaly', false)
            ->with(['currency'])
            ->whereYear('date', $year)
            ->whereMonth('date', $month);

        if ($categoryId !== null) {
            $query->whereHas('categories', fn($q) => $q->where('categories.id', $categoryId));
        }

        $transactions = $query->get();

        $baseCurrency = Currency::where('code', 'BYN')->first();
        if (!$baseCurrency) return 0;

        $ratesCache = $this->loadRatesForTransactions($transactions, $baseCurrency);
        $total = 0;
        foreach ($transactions as $transaction) {
            $total += $this->convertToBaseCurrency($transaction, $baseCurrency, $ratesCache);
        }
        return $total;
    }

    private function getMonthlyExpenseAmount(int $userId, int $year, int $month): float
    {
        return $this->getExpensesForMonth($userId, $year, $month);
    }

    private function getCategoryMonthlyExpense(int $userId, int $categoryId, int $year, int $month): float
    {
        return $this->getExpensesForMonth($userId, $year, $month, $categoryId);
    }

    private function getMonthlyExpenseAmountForCompleteMonth(int $userId, int $year, int $month): float
    {
        $date = Carbon::create($year, $month, 1);
        if ($date->copy()->endOfMonth() > Carbon::now()) return 0;
        return $this->getMonthlyExpenseAmount($userId, $year, $month);
    }

    private function getCompleteMonthsExpensesArray(int $userId, int $months = 24): array
    {
        $result = [];
        $now = Carbon::now();

        for ($i = $months - 1; $i >= 0; $i--) {
            $date = $now->copy()->subMonths($i);
            $amount = $this->getMonthlyExpenseAmountForCompleteMonth($userId, $date->year, $date->month);
            if ($amount > 0) $result[] = $amount;
        }

        return $result;
    }

    private function forecastRemainingCurrentMonth(int $userId): array
    {
        $now = Carbon::now();
        $today = (int)$now->day;
        $daysTotal = (int)$now->daysInMonth;

        // ========== ОСНОВНОЙ РАСЧЕТ ==========
        // Количество оставшихся дней (НАЧИНАЯ С ЗАВТРАШНЕГО ДНЯ)
        $daysLeft = $daysTotal - $today;
        if ($daysLeft < 0) $daysLeft = 0;

        // Количество пройденных дней (ВКЛЮЧАЯ СЕГОДНЯШНИЙ)
        $daysSpent = $today;
        if ($daysSpent <= 0) $daysSpent = 1;

        // ========== ФАКТИЧЕСКИЕ ДАННЫЕ ==========
        $actualSpent = $this->getMonthlyExpenseAmount($userId, $now->year, $now->month);
        $currentDailyRate = $actualSpent / $daysSpent;

        // Данные за прошлый месяц
        $lastMonth = $now->copy()->subMonth();
        $lastMonthTotal = $this->getMonthlyExpenseAmount($userId, $lastMonth->year, $lastMonth->month);
        $lastMonthDailyRate = $lastMonthTotal / $lastMonth->daysInMonth;

        // Взвешенная дневная ставка
        $currentWeight = min(0.8, $daysSpent / $daysTotal);
        $weightedDailyRate = ($currentDailyRate * $currentWeight) + ($lastMonthDailyRate * (1 - $currentWeight));

        // Прогноз на оставшиеся дни (только на будующие дни, начиная с завтра)
        $forecastRemaining = $weightedDailyRate * $daysLeft;

        // ========== ПОДНЕВНОЙ ПРОГНОЗ (НАЧИНАЯ С ЗАВТРА) ==========
        $dayFactors = $this->calculateDayOfWeekFactors($userId);
        $dailyBreakdown = [];

        // ВАЖНО: начинаем с ЗАВТРАШНЕГО ДНЯ!
        $currentDate = $now->copy()->addDay();

        for ($i = 0; $i < $daysLeft; $i++) {
            $dayOfWeek = $currentDate->dayOfWeek;
            $dayFactor = $dayFactors[$dayOfWeek] ?? 1.0;
            $dailyBreakdown[] = [
                'date' => $currentDate->format('Y-m-d'),
                'day_of_week' => $this->getRussianDayOfWeek($dayOfWeek),
                'forecast' => round($weightedDailyRate * $dayFactor, 2)
            ];
            $currentDate->addDay();
        }

        // ========== ВОЗВРАТ ==========
        return [
            'days_left' => $daysLeft,
            'already_spent' => round($actualSpent, 2),
            'daily_rate_current' => round($currentDailyRate, 2),
            'daily_rate_last_month' => round($lastMonthDailyRate, 2),
            'weighted_daily_rate' => round($weightedDailyRate, 2),
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

        return [
            'month' => $targetMonth->translatedFormat('F Y'),
            'total_forecast' => round($monthlyForecast, 2),
            'daily_average' => round($monthlyForecast / $targetMonth->daysInMonth, 2),
            'change_from_previous' => $changePercent,
            'trend' => $changePercent > 5 ? 'growth' : ($changePercent < -5 ? 'decline' : 'stable'),
            'days_in_month' => $targetMonth->daysInMonth
        ];
    }

    private function calculateDayOfWeekFactors(int $userId): array
    {
        $defaultFactors = [0 => 1.0, 1 => 0.85, 2 => 0.85, 3 => 0.85, 4 => 1.15, 5 => 1.2, 6 => 1.2];

        $startDate = Carbon::now()->subMonths(3);
        $transactions = Transaction::where('user_id', $userId)
            ->where('type', 'expense')
            ->where('is_anomaly', false)
            ->whereBetween('date', [$startDate, Carbon::now()])
            ->get();

        if ($transactions->count() < 10) return $defaultFactors;

        $baseCurrency = Currency::where('code', 'BYN')->first();
        if (!$baseCurrency) return $defaultFactors;

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
            $dailyAverages[$i] = $dailyWeights[$i] > 0 ? $dailyTotals[$i] / $dailyWeights[$i] : 0;
        }

        $totalWeightedSum = array_sum($dailyTotals);
        $totalWeight = array_sum($dailyWeights);
        if ($totalWeight <= 0) return $defaultFactors;

        $totalAverage = $totalWeightedSum / $totalWeight;
        if ($totalAverage <= 0) return $defaultFactors;

        $dataConfidence = min(0.8, $transactions->count() / 100);
        $factors = [];

        for ($i = 0; $i < 7; $i++) {
            if ($dailyAverages[$i] > 0) {
                $calculatedFactor = min(1.8, max(0.6, $dailyAverages[$i] / $totalAverage));
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

        $interpretation = match(true) {
            $mape < 10 => 'Высокая точность',
            $mape < 20 => 'Хорошая точность',
            $mape < 30 => 'Приемлемая точность',
            default => 'Низкая точность'
        };

        return [
            'mape' => round($mape, 1),
            'mse' => round($mse, 2),
            'rmse' => round($rmse, 2),
            'interpretation' => $interpretation
        ];
    }

    private function calculateConfidence(int $userId): array
    {
        // Получаем расходы за последние 12 месяцев (или сколько есть)
        $monthlyExpenses = $this->getCompleteMonthsExpensesArray($userId, 12);
        $values = array_filter($monthlyExpenses, fn($v) => $v > 0);
        $n = count($values);

        // Если нет данных или меньше 3 месяцев
        if ($n < 3) {
            return [
                'cv' => null,
                'cv_percent' => null,
                'level' => 'low',
                'text' => 'Недостаточно данных',
                'confidence' => 0
            ];
        }

        // Расчет коэффициента вариации (CV)
        $mean = array_sum($values) / $n;
        $variance = array_sum(array_map(fn($x) => pow($x - $mean, 2), $values)) / $n;
        $stdDeviation = sqrt($variance);
        $cv = $stdDeviation / $mean;
        $cvPercent = round($cv * 100, 1);

        // Определяем уровень стабильности
        if ($cv < 0.15) {
            $level = 'high';
            $text = 'Стабильные расходы';
        } elseif ($cv < 0.30) {
            $level = 'medium';
            $text = 'Умеренные колебания';
        } elseif ($cv < 0.50) {
            $level = 'low';
            $text = 'Нестабильные расходы';
        } else {
            $level = 'low';
            $text = 'Очень нестабильные расходы';
        }

        return [
            'cv' => round($cv, 3),
            'cv_percent' => $cvPercent,
            'level' => $level,
            'text' => $text,
            'confidence' => 0 // не используем
        ];
    }

    // ==================== API ENDPOINTS ====================

    public function overview(Request $request)
    {
        try {
            $userId = $this->getUserId();
            $validated = $request->validate([
                'period' => 'nullable|in:month,year',
                'year' => 'nullable|integer|min:2000|max:2100',
                'month' => 'nullable|integer|min:1|max:12',
                'include_anomalies' => 'nullable|in:true,false,0,1'
            ]);

            $period = $validated['period'] ?? 'month';
            $year = $validated['year'] ?? date('Y');
            $month = $validated['month'] ?? date('m');

            $startDate = $period === 'year'
                ? Carbon::create($year, 1, 1)->startOfYear()
                : Carbon::create($year, $month, 1)->startOfMonth();

            $endDate = $period === 'year'
                ? Carbon::create($year, 12, 31)->endOfYear()
                : $startDate->copy()->endOfMonth();

            // Доходы (все)
            $incomeTransactions = Transaction::where('user_id', $userId)
                ->where('type', 'income')
                ->whereBetween('date', [$startDate, $endDate])
                ->with(['currency'])
                ->get();

            // Расходы - ВСЕ (включая аномалии) для аналитики
            $expenseTransactions = Transaction::where('user_id', $userId)
                ->where('type', 'expense')
                ->whereBetween('date', [$startDate, $endDate])
                ->with(['categories', 'currency'])
                ->get();

            $baseCurrency = Currency::where('code', 'BYN')->first();
            if (!$baseCurrency) {
                return response()->json(['status' => 'error', 'message' => 'Базовая валюта BYN не найдена'], 500);
            }

            // Конвертация доходов
            $incomeRatesCache = $this->loadRatesForTransactions($incomeTransactions, $baseCurrency);
            $totalIncome = 0;
            foreach ($incomeTransactions as $transaction) {
                $totalIncome += $this->convertToBaseCurrency($transaction, $baseCurrency, $incomeRatesCache);
            }

            // Конвертация расходов (все, включая аномалии)
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
                    'financial_health' => $this->calculateFinancialHealth($userId),
                    'anomalies_info' => $this->getAnomaliesStats($userId, $startDate, $endDate, false)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Overview error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Ошибка: ' . $e->getMessage()], 500);
        }
    }

    public function forecast(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $userId = $this->getUserId();

            // Получаем ВСЕ транзакции расходов
            $allTransactions = Transaction::where('user_id', $userId)
                ->where('type', 'expense')
                ->with(['categories', 'currency'])
                ->get();

            if ($allTransactions->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'has_data' => false,
                        'message' => 'Нет данных для прогноза. Добавьте транзакции.'
                    ]
                ]);
            }

            // ========== ДЛЯ ОТОБРАЖЕНИЯ В ТАБЛИЦЕ ==========
            // Аномалии по алгоритму (показываем пользователю)
            $anomaliesList = $this->anomalyService->getAnomaliesList($allTransactions);

            // ========== ДЛЯ ПРОГНОЗА ==========
            // Берем транзакции, учитывая ТОЛЬКО ручные метки is_anomaly
            $transactionsForForecast = $this->anomalyService->getCleanTransactions($allTransactions);

            // Проверяем, остались ли транзакции для прогноза
            if ($transactionsForForecast->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'has_data' => false,
                        'message' => 'После исключения отмеченных пользователем аномалий не осталось данных для прогноза.'
                    ]
                ]);
            }

            // Формируем массив помесячных расходов
            $transactionsByMonth = $transactionsForForecast->groupBy(fn($t) => $t->date->format('Y-m'));

            $baseCurrency = Currency::where('code', 'BYN')->first();
            if (!$baseCurrency) {
                return response()->json(['status' => 'error', 'message' => 'Базовая валюта BYN не найдена'], 500);
            }

            $ratesCache = $this->loadRatesForTransactions($transactionsForForecast, $baseCurrency);

            $monthlyExpenses = [];
            $now = Carbon::now();

            for ($i = 29; $i >= 0; $i--) {
                $date = $now->copy()->subMonths($i);
                $monthKey = $date->format('Y-m');
                $monthTransactions = $transactionsByMonth[$monthKey] ?? collect();

                $total = 0;
                foreach ($monthTransactions as $transaction) {
                    $total += $this->convertToBaseCurrency($transaction, $baseCurrency, $ratesCache);
                }

                if ($total > 0) {
                    $monthlyExpenses[] = $total;
                }
            }

            $monthsCount = count($monthlyExpenses);

            if ($monthsCount < 3) {
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'has_data' => true,
                        'forecast_available' => false,
                        'message' => 'Недостаточно полных месяцев данных для прогноза.',
                        'complete_months_available' => $monthsCount,
                        'anomalies_list' => $anomaliesList
                    ]
                ]);
            }

            // Выполняем прогноз
            $monthlyForecast = $this->forecastService->forecast($monthlyExpenses, 3);
            $strategy = $this->forecastService->getStrategy($monthsCount);

            if ($monthlyForecast === null || $strategy === null) {
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'has_data' => true,
                        'forecast_available' => false,
                        'message' => 'Не удалось построить прогноз'
                    ]
                ]);
            }

            // Формируем остальные данные
            $remainingMonth = $this->forecastRemainingCurrentMonth($userId);
            $nextMonthDate = Carbon::now()->addMonth()->startOfMonth();
            $lastCompleteMonthTotal = end($monthlyExpenses);
            $nextMonthSummary = $this->forecastFullMonthSummary($monthlyForecast[0], $nextMonthDate, $lastCompleteMonthTotal);
            $secondMonthSummary = $this->forecastFullMonthSummary(
                $monthlyForecast[1] ?? $monthlyForecast[0],
                Carbon::now()->addMonths(2)->startOfMonth(),
                $monthlyForecast[0]
            );

            $lastCompleteMonth = Carbon::now()->subMonth()->startOfMonth();
            $baselineTotal = $this->getMonthlyExpenseAmount($userId, $lastCompleteMonth->year, $lastCompleteMonth->month);
            $dailyBaseline = $baselineTotal / $lastCompleteMonth->daysInMonth;

            $trend = $this->calculateTrendFromData($monthlyExpenses);
            $seasonalFactor = $this->calculateSeasonalFactor($userId, $monthlyExpenses);

            // Прогноз по категориям для первого месяца
            $categoryForecasts = $this->getCategoryForecasts($userId, $dailyBaseline, $seasonalFactor, $trend);

            // Прогноз по категориям для второго месяца (с учетом тренда)
            $secondMonthTrendFactor = $trend * $trend; // Усиленный тренд для второго месяца
            $secondMonthCategoryForecasts = $this->getCategoryForecasts($userId, $dailyBaseline, $seasonalFactor, $secondMonthTrendFactor);

            // Расчет коэффициента вариации (CV)
            $cvData = $this->calculateConfidence($userId);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'has_data' => true,
                    'forecast_available' => true,
                    'anomalies_list' => $anomaliesList,
                    'model' => $strategy->getName(),
                    // Коэффициент вариации (CV)
                    'cv' => $cvData['cv'],
                    'cv_percent' => $cvData['cv_percent'],
                    'cv_level' => $cvData['level'],
                    'cv_text' => $cvData['text'],
                    'complete_months_used' => $monthsCount,
                    'remaining_current_month' => $remainingMonth,
                    'next_month' => $nextMonthSummary,
                    'second_month' => $secondMonthSummary,
                    'category_forecasts' => $categoryForecasts,
                    'second_month_category_forecasts' => $secondMonthCategoryForecasts,
                    'trend_factor' => round($trend, 3),
                    'seasonal_factor' => round($seasonalFactor, 2),
                    'model_metrics' => $this->calculateModelMetrics($monthlyExpenses, $monthlyForecast),
                    'reliability_message' => $strategy->getReliabilityMessage()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Forecast error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
    public function batchMarkAnomalies(Request $request)
    {
        try {
            $userId = $this->getUserId();

            $validated = $request->validate([
                'anomalies' => 'required|array',
                'anomalies.*.id' => 'required|integer|exists:transactions,id',
                'anomalies.*.is_anomaly' => 'required|boolean'
            ]);

            $updatedCount = 0;
            $errors = [];

            foreach ($validated['anomalies'] as $item) {
                $success = $this->anomalyService->updateAnomalyStatus($item['id'], $userId, $item['is_anomaly']);

                if ($success) {
                    $updatedCount++;
                } else {
                    $errors[] = $item['id'];
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => "Обновлено {$updatedCount} транзакций",
                'data' => [
                    'updated_count' => $updatedCount,
                    'errors' => $errors
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Batch mark anomalies error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Ошибка: ' . $e->getMessage()
            ], 500);
        }
    }
}