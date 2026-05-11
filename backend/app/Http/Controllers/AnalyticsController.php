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
        // 1. Находим все доходы за последние 6 месяцев
        $incomes = Transaction::where('user_id', $userId)
            ->where('type', 'income')
            ->where('date', '>=', Carbon::now()->subMonths(6))
            ->orderBy('amount', 'desc')
            ->get();

        if ($incomes->isEmpty()) {
            return 25; // значение по умолчанию
        }

        // 2. Отбираем крупные доходы (вероятно, зарплата)
        $avgIncome = $incomes->avg('amount');
        $potentialSalaries = $incomes->filter(function($t) use ($avgIncome) {
            return $t->amount >= $avgIncome * 0.7;
        });

        if ($potentialSalaries->isEmpty()) {
            return 25;
        }

        // 3. Группируем по дню месяца
        $dayCount = [];
        foreach ($potentialSalaries as $salary) {
            $day = Carbon::parse($salary->date)->day;
            $dayCount[$day] = ($dayCount[$day] ?? 0) + 1;
        }

        // 4. Находим самый частый день
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

    public function monthlyTrends(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $userId = $this->getUserId();
            $months = $request->input('months', 12);
            $endDate = Carbon::now()->endOfMonth();
            $startDate = $endDate->copy()->subMonths($months)->startOfMonth();

            $transactions = Transaction::where('user_id', $userId)->with(['currency'])->whereBetween('date', [$startDate, $endDate])->get();
            $baseCurrency = Currency::where('code', 'BYN')->first();
            if (!$baseCurrency) return response()->json(['status' => 'error', 'message' => 'Базовая валюта BYN не найдена'], 500);

            $ratesCache = $this->loadRatesForTransactions($transactions, $baseCurrency);
            $monthlyData = [];

            foreach ($transactions as $transaction) {
                $amountInBase = $this->convertToBaseCurrency($transaction, $baseCurrency, $ratesCache);
                $date = Carbon::parse($transaction->date);
                $monthKey = $date->format('Y-m');
                if (!isset($monthlyData[$monthKey])) $monthlyData[$monthKey] = ['month' => $monthKey, 'income' => 0, 'expense' => 0];
                if ($transaction->type === 'income') $monthlyData[$monthKey]['income'] += $amountInBase;
                else $monthlyData[$monthKey]['expense'] += $amountInBase;
            }

            ksort($monthlyData);
            $result = [];
            foreach ($monthlyData as $data) {
                $result[] = [
                    'month' => $data['month'],
                    'income' => round($data['income'], 2),
                    'expense' => round($data['expense'], 2),
                    'balance' => round($data['income'] - $data['expense'], 2)
                ];
            }
            return response()->json(['status' => 'success', 'data' => $result]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
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

    private function getExpensesForPeriod(int $userId, Carbon $startDate, Carbon $endDate): float
    {
        $transactions = Transaction::where('user_id', $userId)->where('type', 'expense')
            ->with(['currency'])->whereBetween('date', [$startDate, $endDate])->get();
        $baseCurrency = Currency::where('code', 'BYN')->first();
        if (!$baseCurrency) return 0;
        $ratesCache = $this->loadRatesForTransactions($transactions, $baseCurrency);
        $total = 0;
        foreach ($transactions as $transaction) {
            $total += $this->convertToBaseCurrency($transaction, $baseCurrency, $ratesCache);
        }
        return $total;
    }

    private function getMonthlyExpenses(int $userId, Carbon $startDate, Carbon $endDate): array
    {
        $transactions = Transaction::where('user_id', $userId)->where('type', 'expense')
            ->with(['currency'])->whereBetween('date', [$startDate, $endDate])->get();
        $baseCurrency = Currency::where('code', 'BYN')->first();
        if (!$baseCurrency) return [];
        $ratesCache = $this->loadRatesForTransactions($transactions, $baseCurrency);
        $monthlyData = [];
        foreach ($transactions as $transaction) {
            $amountInBase = $this->convertToBaseCurrency($transaction, $baseCurrency, $ratesCache);
            $date = Carbon::parse($transaction->date);
            $monthKey = $date->format('Y-m');
            if (!isset($monthlyData[$monthKey])) $monthlyData[$monthKey] = 0;
            $monthlyData[$monthKey] += $amountInBase;
        }
        ksort($monthlyData);
        return array_values($monthlyData);
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

    // ==================== СЕЗОННЫЕ КОЭФФИЦИЕНТЫ ====================

    private function getWeightedSeasonalCoefficients(int $userId): array
    {
        $coefficients = [];
        $currentYear = Carbon::now()->year;
        $monthlyRatios = array_fill(1, 12, []);
        $monthlyWeights = array_fill(1, 12, []);

        for ($year = $currentYear - 3; $year <= $currentYear - 1; $year++) {
            $yearsAgo = $currentYear - $year;
            $weight = pow(0.8, $yearsAgo);

            for ($month = 2; $month <= 12; $month++) {
                $prevExpense = $this->getMonthlyExpenseAmount($userId, $year, $month - 1);
                $currExpense = $this->getMonthlyExpenseAmount($userId, $year, $month);
                if ($prevExpense > 0 && $currExpense > 0) {
                    $ratio = max(0.5, min(2.0, $currExpense / $prevExpense));
                    $monthlyRatios[$month][] = $ratio;
                    $monthlyWeights[$month][] = $weight;
                }
            }

            if ($year > $currentYear - 3) {
                $prevDec = $this->getMonthlyExpenseAmount($userId, $year - 1, 12);
                $jan = $this->getMonthlyExpenseAmount($userId, $year, 1);
                if ($prevDec > 0 && $jan > 0) {
                    $ratio = max(0.5, min(2.0, $jan / $prevDec));
                    $monthlyRatios[1][] = $ratio;
                    $monthlyWeights[1][] = $weight;
                }
            }
        }

        for ($month = 1; $month <= 12; $month++) {
            if (count($monthlyRatios[$month]) >= 2) {
                $coefficients[$month] = round($this->calculateWeightedMedian($monthlyRatios[$month], $monthlyWeights[$month]), 2);
            } elseif (count($monthlyRatios[$month]) == 1) {
                $coefficients[$month] = round($monthlyRatios[$month][0], 2);
            } else {
                $coefficients[$month] = 1.0;
            }
        }

        $totalRatios = array_sum(array_map('count', $monthlyRatios));
        if ($totalRatios < 6) return array_fill(1, 12, 1.0);
        return $coefficients;
    }

    private function getCategorySeasonalFactor(int $userId, int $categoryId, int $targetMonth): float
    {
        $currentYear = Carbon::now()->year;
        $ratios = [];

        for ($year = $currentYear - 2; $year <= $currentYear - 1; $year++) {
            $prevMonthExpense = $this->getCategoryMonthlyExpense($userId, $categoryId, $year, $targetMonth - 1);
            $currentMonthExpense = $this->getCategoryMonthlyExpense($userId, $categoryId, $year, $targetMonth);
            if ($prevMonthExpense > 0 && $currentMonthExpense > 0) {
                $ratios[] = $currentMonthExpense / $prevMonthExpense;
            }
        }

        if (empty($ratios)) return 1.0;
        return max(0.5, min(2.0, array_sum($ratios) / count($ratios)));
    }

    private function calculateWeightedMedian(array $values, array $weights): float
    {
        if (count($values) != count($weights)) return $this->calculateMedian($values);
        array_multisort($values, $weights);
        $totalWeight = array_sum($weights);
        $cumulativeWeight = 0;
        $targetWeight = $totalWeight / 2;
        for ($i = 0; $i < count($values); $i++) {
            $cumulativeWeight += $weights[$i];
            if ($cumulativeWeight >= $targetWeight) return $values[$i];
        }
        return $values[count($values) - 1] ?? 1.0;
    }

    private function calculateMedian(array $values): float
    {
        $count = count($values);
        if ($count == 0) return 1.0;
        sort($values);
        $middle = floor($count / 2);
        if ($count % 2 == 0) return ($values[$middle - 1] + $values[$middle]) / 2;
        return $values[$middle];
    }

    // ==================== КОЭФФИЦИЕНТ ТРЕНДА ====================

    private function calculateTrend(int $userId): float
    {
        $monthlyTotals = [];
        for ($i = 23; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $amount = $this->getMonthlyExpenseAmount($userId, $date->year, $date->month);
            if ($amount > 0) $monthlyTotals[] = $amount;
        }

        if (count($monthlyTotals) < 6) return 1.0;

        // Фильтрация аномалий
        $filteredTotals = $this->filterAnomaliesFromArray($monthlyTotals);

        if (count($filteredTotals) < 4) return 1.0;

        $n = count($filteredTotals);
        $x = range(0, $n - 1);
        $sumX = array_sum($x);
        $sumY = array_sum($filteredTotals);
        $sumXY = array_sum(array_map(fn($xi, $yi) => $xi * $yi, $x, $filteredTotals));
        $sumX2 = array_sum(array_map(fn($xi) => $xi * $xi, $x));

        $denominator = ($n * $sumX2 - $sumX * $sumX);
        if ($denominator == 0) return 1.0;

        $slope = ($n * $sumXY - $sumX * $sumY) / $denominator;
        $lastMean = array_sum(array_slice($filteredTotals, -3)) / 3;

        if ($lastMean > 0) {
            $trend = 1 + ($slope / $lastMean);
            // Ограничиваем тренд более узкими пределами ±3%
            return max(0.97, min(1.03, $trend));
        }

        return 1.0;
    }

    // ==================== КОЭФФИЦИЕНТЫ ДНЕЙ НЕДЕЛИ ====================

    /**
     * Вычисление индивидуальных коэффициентов дней недели из истории пользователя
     *
     * Алгоритм:
     * 1. Собираем все транзакции за последние 3 месяца
     * 2. Группируем по дням недели
     * 3. Вычисляем среднюю сумму трат для каждого дня
     * 4. Нормализуем относительно среднего (чтобы базовый коэффициент был 1.0)
     */
    private function calculateDayOfWeekFactors(int $userId): array
    {
        // Коэффициенты по умолчанию (если недостаточно данных)
        $defaultFactors = [0 => 1.0, 1 => 0.9, 2 => 0.9, 3 => 0.9, 4 => 1.2, 5 => 1.1, 6 => 1.3];

        $startDate = Carbon::now()->subMonths(3);
        $endDate = Carbon::now();

        // Получаем все транзакции за последние 3 месяца
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

        // Суммы по дням недели
        $dailyTotals = array_fill(0, 7, 0.0);
        $dailyCounts = array_fill(0, 7, 0);

        foreach ($transactions as $transaction) {
            $amountInBase = $this->convertToBaseCurrency($transaction, $baseCurrency, $ratesCache);
            $dayOfWeek = Carbon::parse($transaction->date)->dayOfWeek;

            $dailyTotals[$dayOfWeek] += $amountInBase;
            $dailyCounts[$dayOfWeek]++;
        }

        // Вычисляем среднее для каждого дня
        $dailyAverages = [];
        for ($i = 0; $i < 7; $i++) {
            $dailyAverages[$i] = $dailyCounts[$i] > 0 ? $dailyTotals[$i] / $dailyCounts[$i] : 0;
        }

        // Вычисляем общее среднее (только по дням, где есть данные)
        $totalAverage = 0;
        $daysWithData = 0;
        for ($i = 0; $i < 7; $i++) {
            if ($dailyAverages[$i] > 0) {
                $totalAverage += $dailyAverages[$i];
                $daysWithData++;
            }
        }
        $totalAverage = $daysWithData > 0 ? $totalAverage / $daysWithData : 1.0;

        // Нормализуем и ограничиваем разумными пределами
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

    // ==================== ДОВЕРИТЕЛЬНЫЙ ИНТЕРВАЛ ====================

    /**
     * Доверительный интервал с учетом горизонта прогноза
     *
     * Формула: errorMargin = min(0.5, CV × √(горизонт/30))
     *
     * Где:
     * - CV — коэффициент вариации (характеризует стабильность расходов)
     * - горизонт — количество прогнозируемых дней (30)
     * - √(горизонт/30) — множитель, учитывающий рост ошибки с горизонтом
     *
     * При горизонте 30 дней множитель = 1
     * При горизонте 15 дней множитель = 0.71 (ошибка меньше)
     * При горизонте 60 дней множитель = 1.41 (ошибка больше)
     */
    private function getPredictionInterval(int $userId, float $forecast, int $horizonDays = 30): array
    {
        $monthlyExpenses = $this->getMonthlyExpenses($userId, Carbon::now()->subMonths(12), Carbon::now());
        $cv = $this->calculateCoefficientOfVariation($monthlyExpenses);

        // Множитель для горизонта прогноза (ошибка растет как корень из времени)
        $horizonMultiplier = sqrt($horizonDays / 30);

        // Базовый запас ошибки: CV, но не более 50%
        $baseErrorMargin = min(0.5, $cv);

        // Итоговая ошибка с учетом горизонта
        $errorMargin = min(0.7, $baseErrorMargin * $horizonMultiplier);

        // Для очень малого горизонта (менее 7 дней) можно немного уменьшить
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
        $n = count($values);
        if ($n < 3) return 0.5;
        $mean = array_sum($values) / $n;
        if ($mean == 0) return 0.5;
        $variance = array_sum(array_map(fn($x) => pow($x - $mean, 2), $values)) / $n;
        $stdDev = sqrt($variance);
        return $stdDev / $mean;
    }

    // ==================== ДЕТЕКЦИЯ АНОМАЛИЙ В РЕФЕРЕНТНЫХ ПЕРИОДАХ ====================

    private function filterAnomalyPeriods(array $periods, array $values): array
    {
        if (count($values) < 3) return [$periods, []];
        sort($values);
        $q1 = $values[floor(count($values) * 0.25)];
        $q3 = $values[floor(count($values) * 0.75)];
        $iqr = $q3 - $q1;
        $lowerBound = $q1 - 1.5 * $iqr;
        $upperBound = $q3 + 1.5 * $iqr;

        $filtered = [];
        $anomalies = [];
        foreach ($periods as $index => $period) {
            if ($period['value'] < $lowerBound || $period['value'] > $upperBound) {
                $anomalies[] = $period['source'];
            } else {
                $filtered[] = $period;
            }
        }
        return [empty($filtered) ? $periods : $filtered, $anomalies];
    }

    // ==================== ОСНОВНОЙ ПРОГНОЗ НА 30 ДНЕЙ ====================

    public function forecastNext30Days(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $userId = $this->getUserId();
            if (!Transaction::where('user_id', $userId)->exists()) {
                return response()->json(['status' => 'success', 'data' => ['has_data' => false, 'message' => 'Нет данных для прогноза. Добавьте транзакции.']]);
            }

            $now = Carbon::now();
            $startDate = $now->copy();
            $endDate = $now->copy()->addDays(29);

            // 1. Базовый среднедневной расход
            $lastCompleteMonth = $now->copy()->subMonth()->startOfMonth();
            $baselineTotal = $this->getMonthlyExpenseAmount($userId, $lastCompleteMonth->year, $lastCompleteMonth->month);
            $dailyBaseline = $this->getFilteredDailyBaseline($userId);

            // 2. Коэффициенты дней недели
            $dayOfWeekFactors = $this->calculateDayOfWeekFactors($userId);

            // 3. Сезонный коэффициент с детекцией аномалий
            $seasonalFactor = $this->getCalendarAdjustedFactorWithAnomalyDetection($userId, $startDate, $endDate);

            // 4. Коэффициент тренда
            $trend = $this->calculateTrend($userId);

            // 5. Прогноз на каждый день
            $dailyForecasts = [];
            $totalForecast = 0;
            $currentDate = $startDate->copy();

            for ($i = 0; $i < 30; $i++) {
                $dayOfWeek = $currentDate->dayOfWeek;
                $dayFactor = $dayOfWeekFactors[$dayOfWeek] ?? 1.0;
                $dailyForecast = $dailyBaseline * $dayFactor * $seasonalFactor * $trend;
                $dailyForecasts[] = [
                    'date' => $currentDate->format('Y-m-d'),
                    'day_of_week' => $this->getRussianDayOfWeek($currentDate->dayOfWeek),
                    'forecast' => round($dailyForecast, 2)
                ];
                $totalForecast += $dailyForecast;
                $currentDate->addDay();
            }

            // 6. Доверительный интервал
            $interval = $this->getPredictionInterval($userId, $totalForecast, 30);

            // 7. Прогноз по категориям
            $categoryForecasts = $this->getCategoryForecasts($userId, $dailyBaseline, $seasonalFactor, $trend);

            // 8. Confidence
            $confidence = $this->calculateConfidence($userId);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'has_data' => true,
                    'period' => ['start' => $startDate->format('Y-m-d'), 'end' => $endDate->format('Y-m-d'), 'days' => 30],
                    'total_forecast' => round($totalForecast, 2),
                    'daily_average' => round($totalForecast / 30, 2),
                    'daily_forecasts' => $dailyForecasts,
                    'category_forecasts' => $categoryForecasts,
                    'confidence_interval' => $interval,
                    'trend_factor' => round($trend, 3),
                    'seasonal_factor' => round($seasonalFactor, 2),
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

    private function getCalendarAdjustedFactorWithAnomalyDetection(int $userId, Carbon $startDate, Carbon $endDate): float
    {
        $targetStart = clone $startDate;
        $targetEnd = clone $endDate;

        $refPeriods = [
            ['value' => $this->getExpensesForPeriod($userId, $targetStart->copy()->subYear(), $targetEnd->copy()->subYear()), 'weight' => 0.6, 'source' => 'same_period'],
            ['value' => $this->getExpensesForPeriod($userId, $targetStart->copy()->subYear()->subMonth(), $targetEnd->copy()->subYear()->subMonth()), 'weight' => 0.3, 'source' => 'prev_month'],
            ['value' => $this->getExpensesForPeriod($userId, $targetStart->copy()->subYear()->addMonth(), $targetEnd->copy()->subYear()->addMonth()), 'weight' => 0.1, 'source' => 'next_month'],
        ];

        $values = array_column($refPeriods, 'value');
        list($filteredPeriods, $anomalies) = $this->filterAnomalyPeriods($refPeriods, $values);

        if (count($filteredPeriods) < 2) {
            return $this->getWeightedSeasonalCoefficients($userId)[$startDate->month] ?? 1.0;
        }

        $totalWeight = array_sum(array_column($filteredPeriods, 'weight'));
        $expectedPeriodExpense = 0;
        foreach ($filteredPeriods as $period) {
            $expectedPeriodExpense += ($period['value'] * ($period['weight'] / $totalWeight));
        }

        $lastCompleteMonth = Carbon::now()->subMonth()->startOfMonth();
        $baselineTotal = $this->getMonthlyExpenseAmount($userId, $lastCompleteMonth->year, $lastCompleteMonth->month);
        $baselineDaily = $baselineTotal / $lastCompleteMonth->daysInMonth;
        $expectedDailyFromRef = $expectedPeriodExpense / 30;

        if ($baselineDaily > 0) {
            return max(0.5, min(1.8, $expectedDailyFromRef / $baselineDaily));
        }
        return 1.0;
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

            $categorySeasonalFactor = $this->getCategorySeasonalFactor($userId, $category->id, Carbon::now()->month);
            $totalForecast = $categoryDailyBaseline * 30 * $categorySeasonalFactor * $trend;

            $forecasts[] = [
                'category_id' => $category->id,
                'category_name' => $category->name,
                'color' => $category->color ?? '#3498db',
                'forecast' => round($totalForecast, 2),
                'daily_average' => round($totalForecast / 30, 2),
                'share_percent' => $dailyBaseline > 0 ? round(($categoryDailyBaseline / $dailyBaseline) * 100, 1) : 0
            ];
        }

        usort($forecasts, fn($a, $b) => $b['forecast'] <=> $a['forecast']);
        return array_slice($forecasts, 0, 10);
    }

    // ==================== CONFIDENCE SCORE ====================

    private function calculateConfidence(int $userId): array
    {
        $oldest = Transaction::where('user_id', $userId)->orderBy('date', 'asc')->first();
        if (!$oldest) return ['percent' => 0, 'level' => 'low', 'text' => 'Нет данных для анализа'];

        $monthsOfData = Carbon::parse($oldest->date)->diffInMonths(Carbon::now());
        $monthsScore = $this->calculateMonthsScore($monthsOfData);

        $monthlyExpenses = $this->getMonthlyExpenses($userId, Carbon::now()->subMonths(12), Carbon::now());
        $stabilityScore = $this->calculateStabilityScore($monthlyExpenses);

        $coeffs = $this->getWeightedSeasonalCoefficients($userId);
        $uniqueCoeffs = count(array_unique($coeffs));
        $seasonalScore = min(100, ($uniqueCoeffs / 12) * 100);

        $totalScore = ($monthsScore * 0.4) + ($stabilityScore * 0.35) + ($seasonalScore * 0.25);
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

    /**
     * Расчет базового среднедневного расхода с фильтрацией аномалий
     *
     * Алгоритм:
     * 1. Берем расходы за последние 3 полных месяца
     * 2. Отфильтровываем аномальные месяцы (IQR метод)
     * 3. Берем медиану оставшихся месяцев
     * 4. Делим на количество дней в месяце
     */
    private function getFilteredDailyBaseline(int $userId): float
    {
        $now = Carbon::now();
        $monthlyTotals = [];
        $monthDays = [];

        // Собираем данные за последние 3 полных месяца
        for ($i = 1; $i <= 3; $i++) {
            $monthDate = $now->copy()->subMonths($i);
            $expenses = $this->getMonthlyExpenseAmount($userId, $monthDate->year, $monthDate->month);
            if ($expenses > 0) {
                $monthlyTotals[] = $expenses;
                $monthDays[] = $monthDate->daysInMonth;
            }
        }

        if (count($monthlyTotals) < 2) {
            // Недостаточно данных — используем метод с последним полным месяцем
            $lastCompleteMonth = $now->copy()->subMonth()->startOfMonth();
            $baselineTotal = $this->getMonthlyExpenseAmount($userId, $lastCompleteMonth->year, $lastCompleteMonth->month);
            return $baselineTotal / $lastCompleteMonth->daysInMonth;
        }

        // Фильтрация аномалий (IQR метод)
        $filteredTotals = $this->filterAnomaliesFromArray($monthlyTotals);

        if (count($filteredTotals) < 1) {
            $filteredTotals = $monthlyTotals;
        }

        // Медиана
        $medianTotal = $this->calculateMedian($filteredTotals);

        // Среднее количество дней в месяце (для расчета дневного расхода)
        $avgDays = array_sum($monthDays) / count($monthDays);

        return $medianTotal / $avgDays;
    }

    /**
     * Фильтрация аномалий из простого массива (IQR метод)
     */
    private function filterAnomaliesFromArray(array $values): array
    {
        if (count($values) < 3) {
            return $values;
        }

        $sorted = $values;
        sort($sorted);
        $q1 = $sorted[floor(count($sorted) * 0.25)];
        $q3 = $sorted[floor(count($sorted) * 0.75)];
        $iqr = $q3 - $q1;
        $lowerBound = $q1 - 1.5 * $iqr;
        $upperBound = $q3 + 1.5 * $iqr;

        return array_values(array_filter($values, function($value) use ($lowerBound, $upperBound) {
            return $value >= $lowerBound && $value <= $upperBound;
        }));
    }

    private function calculateStabilityScore(array $monthlyExpenses): float
    {
        $n = count($monthlyExpenses);
        if ($n < 3) return 0;
        $mean = array_sum($monthlyExpenses) / $n;
        if ($mean == 0) return 0;
        $variance = array_sum(array_map(fn($x) => pow($x - $mean, 2), $monthlyExpenses)) / $n;
        $stdDev = sqrt($variance);
        $cv = $stdDev / $mean;
        return 100 * exp(-2 * $cv);
    }
}