<?php

namespace App\Services;

use App\Models\User;
use App\Models\Transaction;
use App\Models\Currency;
use App\Services\Analytics\CurrencyConverterService;
use App\Services\Analytics\FinancialHealthService;
use App\Services\Analytics\MetricsCalculatorService;
use App\Services\Analytics\CategoryAnalysisService;
use App\Services\Analytics\AnomalyService;
use App\Services\Analytics\ForecastService;
use App\Services\Analytics\ForecastResolver;
use App\Services\Contracts\AnalyticsServiceInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AnalyticsService implements AnalyticsServiceInterface
{
    private CurrencyConverterService $currencyConverter;
    private FinancialHealthService $healthService;
    private MetricsCalculatorService $metricsCalculator;
    private CategoryAnalysisService $categoryAnalysis;
    private AnomalyService $anomalyService;
    private ForecastService $forecastService;

    public function __construct()
    {
        $this->currencyConverter = new CurrencyConverterService();
        $this->healthService = new FinancialHealthService();
        $this->metricsCalculator = new MetricsCalculatorService();
        $this->categoryAnalysis = new CategoryAnalysisService($this->currencyConverter);
        $this->anomalyService = new AnomalyService();
        $this->forecastService = new ForecastService(new ForecastResolver());
    }

    /**
     * Основной метод анализа для пользователя
     */
    public function analyze(User $user): array
    {
        // Можно объединить overview и forecast
        return [
            'overview' => $this->getOverview($user->id, 'month', date('Y'), date('m')),
            'forecast' => $this->getForecast($user->id)
        ];
    }

    /**
     * Получение обзорной аналитики за период
     */
    public function getOverview(int $userId, string $period, int $year, ?int $month): array
    {
        $startDate = $period === 'year'
            ? Carbon::create($year, 1, 1)->startOfYear()
            : Carbon::create($year, $month, 1)->startOfMonth();

        $endDate = $period === 'year'
            ? Carbon::create($year, 12, 31)->endOfYear()
            : $startDate->copy()->endOfMonth();

        // Доходы
        $incomeTransactions = Transaction::where('user_id', $userId)
            ->where('type', 'income')
            ->whereBetween('date', [$startDate, $endDate])
            ->with(['currency'])
            ->get();

        // Расходы (все, включая аномалии)
        $expenseTransactions = Transaction::where('user_id', $userId)
            ->where('type', 'expense')
            ->whereBetween('date', [$startDate, $endDate])
            ->with(['categories', 'currency'])
            ->get();

        $incomeRatesCache = $this->currencyConverter->loadRatesForTransactions($incomeTransactions);
        $totalIncome = 0;
        foreach ($incomeTransactions as $transaction) {
            $totalIncome += $this->currencyConverter->convert($transaction, $incomeRatesCache);
        }

        $expenseRatesCache = $this->currencyConverter->loadRatesForTransactions($expenseTransactions);
        $totalExpense = 0;
        $categoryTotals = [];

        foreach ($expenseTransactions as $transaction) {
            $amountInBase = $this->currencyConverter->convert($transaction, $expenseRatesCache);
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
            $categorySpending[] = [
                'id' => $cat['id'],
                'name' => $cat['name'],
                'color' => $cat['color'],
                'total' => round($cat['total'], 2),
                'budget_limit' => (float) $cat['budget_limit'],
                'limit_percentage' => $cat['budget_limit'] > 0 ? round(($cat['total'] / $cat['budget_limit']) * 100, 1) : 0,
                'budget_status' => $this->getBudgetStatus($cat['total'], $cat['budget_limit']),
                'average_monthly' => round($cat['total'], 2)
            ];
        }

        usort($categorySpending, fn($a, $b) => $b['total'] <=> $a['total']);

        return [
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
            'financial_health' => $this->healthService->calculate($userId)
        ];
    }

    /**
     * Получение прогноза
     */
    public function getForecast(int $userId): array
    {
        // Получаем ВСЕ транзакции расходов
        $allTransactions = Transaction::where('user_id', $userId)
            ->where('type', 'expense')
            ->with(['categories', 'currency'])
            ->get();

        if ($allTransactions->isEmpty()) {
            return [
                'has_data' => false,
                'message' => 'Нет данных для прогноза. Добавьте транзакции.'
            ];
        }

        // Аномалии для отображения
        $anomaliesList = $this->anomalyService->getAnomaliesList($allTransactions);

        // Транзакции для прогноза (без ручных аномалий)
        $transactionsForForecast = $this->anomalyService->getCleanTransactions($allTransactions);

        if ($transactionsForForecast->isEmpty()) {
            return [
                'has_data' => false,
                'message' => 'После исключения отмеченных пользователем аномалий не осталось данных для прогноза.'
            ];
        }

        // Формируем массив помесячных расходов
        $transactionsByMonth = $transactionsForForecast->groupBy(fn($t) => $t->date->format('Y-m'));
        $ratesCache = $this->currencyConverter->loadRatesForTransactions($transactionsForForecast);

        $monthlyExpenses = [];
        $now = Carbon::now();

        for ($i = 29; $i >= 0; $i--) {
            $date = $now->copy()->subMonths($i);
            $monthKey = $date->format('Y-m');
            $monthTransactions = $transactionsByMonth[$monthKey] ?? collect();

            $total = 0;
            foreach ($monthTransactions as $transaction) {
                $total += $this->currencyConverter->convert($transaction, $ratesCache);
            }

            if ($total > 0) {
                $monthlyExpenses[] = $total;
            }
        }

        $monthsCount = count($monthlyExpenses);

        if ($monthsCount < 3) {
            return [
                'has_data' => true,
                'forecast_available' => false,
                'message' => 'Недостаточно полных месяцев данных для прогноза.',
                'complete_months_available' => $monthsCount,
                'anomalies_list' => $anomaliesList
            ];
        }

        // Выполняем прогноз
        $monthlyForecast = $this->forecastService->forecast($monthlyExpenses, 3);
        $strategy = $this->forecastService->getStrategy($monthsCount);

        if ($monthlyForecast === null || $strategy === null) {
            return [
                'has_data' => true,
                'forecast_available' => false,
                'message' => 'Не удалось построить прогноз'
            ];
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

        $trend = $this->metricsCalculator->calculateTrend($monthlyExpenses);
        $seasonalFactor = $this->calculateSeasonalFactor($userId, $monthlyExpenses);

        // Прогнозы по категориям
        $categoryForecasts = $this->categoryAnalysis->getCategoryForecasts($userId, $dailyBaseline, $seasonalFactor, $trend);
        $secondMonthCategoryForecasts = $this->categoryAnalysis->getCategoryForecasts($userId, $dailyBaseline, $seasonalFactor, $trend * $trend);

        // Коэффициент вариации
        $cvData = $this->metricsCalculator->calculateCoefficientOfVariation($monthlyExpenses);
        $modelMetrics = $this->metricsCalculator->calculateModelMetrics($monthlyExpenses, $monthlyForecast);

        return [
            'has_data' => true,
            'forecast_available' => true,
            'anomalies_list' => $anomaliesList,
            'model' => $strategy->getName(),
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
            'model_metrics' => $modelMetrics,
            'reliability_message' => $strategy->getReliabilityMessage()
        ];
    }

    private function forecastRemainingCurrentMonth(int $userId): array
    {
        $now = Carbon::now();
        $today = (int)$now->day;
        $daysTotal = (int)$now->daysInMonth;

        $daysLeft = $daysTotal - $today;
        if ($daysLeft < 0) $daysLeft = 0;

        $daysSpent = $today;
        if ($daysSpent <= 0) $daysSpent = 1;

        $actualSpent = $this->getMonthlyExpenseAmount($userId, $now->year, $now->month);
        $currentDailyRate = $actualSpent / $daysSpent;

        $lastMonth = $now->copy()->subMonth();
        $lastMonthTotal = $this->getMonthlyExpenseAmount($userId, $lastMonth->year, $lastMonth->month);
        $lastMonthDailyRate = $lastMonthTotal / $lastMonth->daysInMonth;

        $currentWeight = min(0.8, $daysSpent / $daysTotal);
        $weightedDailyRate = ($currentDailyRate * $currentWeight) + ($lastMonthDailyRate * (1 - $currentWeight));

        $forecastRemaining = $weightedDailyRate * $daysLeft;

        $dayFactors = $this->calculateDayOfWeekFactors($userId);
        $dailyBreakdown = [];
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

    private function getMonthlyExpenseAmount(int $userId, int $year, int $month): float
    {
        $transactions = Transaction::where('user_id', $userId)
            ->where('type', 'expense')
            ->where('is_anomaly', false)
            ->with(['currency'])
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->get();

        $baseCurrency = $this->currencyConverter->getBaseCurrency();
        $ratesCache = $this->currencyConverter->loadRatesForTransactions($transactions);

        $total = 0;
        foreach ($transactions as $transaction) {
            $total += $this->currencyConverter->convert($transaction, $ratesCache);
        }
        return $total;
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

        $baseCurrency = $this->currencyConverter->getBaseCurrency();
        $ratesCache = $this->currencyConverter->loadRatesForTransactions($transactions);
        $dailyTotals = array_fill(0, 7, 0.0);
        $dailyWeights = array_fill(0, 7, 0.0);
        $now = Carbon::now();

        foreach ($transactions as $transaction) {
            $amountInBase = $this->currencyConverter->convert($transaction, $ratesCache);
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

    private function getRussianDayOfWeek(int $dayOfWeek): string
    {
        return ['Воскресенье', 'Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота'][$dayOfWeek] ?? '';
    }

    private function getBudgetStatus(float $total, float $limit): string
    {
        if ($limit <= 0) return 'no_limit';
        $percentage = ($total / $limit) * 100;
        if ($percentage <= 80) return 'good';
        if ($percentage <= 100) return 'warning';
        return 'critical';
    }

    /**
     * Получение месячных трендов доходов/расходов
     */
    public function getMonthlyTrends(int $userId, int $months = 12): array
    {
        $baseCurrency = $this->currencyConverter->getBaseCurrency();
        $startDate = Carbon::now()->subMonths($months - 1)->startOfMonth();

        $transactions = Transaction::where('user_id', $userId)
            ->with(['currency'])
            ->where('date', '>=', $startDate)
            ->get();

        $grouped = $transactions->groupBy(fn($t) => $t->date->format('Y-m'));
        $ratesCache = $this->currencyConverter->loadRatesForTransactions($transactions);

        $trends = [];
        $currentDate = Carbon::now();

        for ($i = $months - 1; $i >= 0; $i--) {
            $date = $currentDate->copy()->subMonths($i);
            $monthKey = $date->format('Y-m');
            $monthTransactions = $grouped[$monthKey] ?? collect();

            $income = 0;
            $expense = 0;

            foreach ($monthTransactions as $transaction) {
                $amountInBase = $this->currencyConverter->convert($transaction, $ratesCache);

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

        return [
            'trends' => $trends,
            'base_currency' => $baseCurrency->code,
            'period_months' => $months
        ];
    }
}