<?php

namespace App\Services\Analytics;

use App\Models\Transaction;
use App\Models\Category;
use Carbon\Carbon;

class CategoryAnalysisService
{
    private CurrencyConverterService $currencyConverter;

    public function __construct(CurrencyConverterService $currencyConverter)
    {
        $this->currencyConverter = $currencyConverter;
    }

    /**
     * Получение расходов по категориям за период
     */
    public function getCategorySpending(int $userId, Carbon $startDate, Carbon $endDate): array
    {
        $expenseTransactions = Transaction::where('user_id', $userId)
            ->where('type', 'expense')
            ->whereBetween('date', [$startDate, $endDate])
            ->with(['categories', 'currency'])
            ->get();

        $ratesCache = $this->currencyConverter->loadRatesForTransactions($expenseTransactions);
        $categoryTotals = [];

        foreach ($expenseTransactions as $transaction) {
            $amountInBase = $this->currencyConverter->convert($transaction, $ratesCache);

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

        $result = [];
        foreach ($categoryTotals as $cat) {
            $result[] = [
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

        usort($result, fn($a, $b) => $b['total'] <=> $a['total']);
        return $result;
    }

    /**
     * Прогноз по категориям
     */
    public function getCategoryForecasts(int $userId, float $dailyBaseline, float $seasonalFactor, float $trend): array
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
            $categoryTotal = $categoryDailyBaseline * 30
                * max(0.5, min(1.5, $seasonalFactor))
                * max(0.95, min(1.05, $trend));
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

    private function getCategoryMonthlyExpense(int $userId, int $categoryId, int $year, int $month): float
    {
        $transactions = Transaction::where('user_id', $userId)
            ->where('type', 'expense')
            ->where('is_anomaly', false)
            ->with(['currency'])
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->whereHas('categories', fn($q) => $q->where('categories.id', $categoryId))
            ->get();

        $baseCurrency = $this->currencyConverter->getBaseCurrency();
        $ratesCache = $this->currencyConverter->loadRatesForTransactions($transactions);

        $total = 0;
        foreach ($transactions as $transaction) {
            $total += $this->currencyConverter->convert($transaction, $ratesCache);
        }
        return $total;
    }

    private function getBudgetStatus(float $total, float $limit): string
    {
        if ($limit <= 0) return 'no_limit';
        $percentage = ($total / $limit) * 100;
        if ($percentage <= 80) return 'good';
        if ($percentage <= 100) return 'warning';
        return 'critical';
    }
}