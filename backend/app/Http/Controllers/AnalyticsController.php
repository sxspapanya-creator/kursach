<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    public function overview(Request $request)
    {
        try {
            $period = $request->get('period', 'month');
            $month = $request->get('month', date('m'));
            $year = $request->get('year', date('Y'));

            // Получаем даты периода
            $dateRange = $this->getDateRange($period, $month, $year);

            // Основные суммы доходов и расходов
            $totalIncome = Transaction::where('type', 'income')
                ->whereBetween('date', [$dateRange['start'], $dateRange['end']])
                ->sum('amount');

            $totalExpenses = Transaction::where('type', 'expense')
                ->whereBetween('date', [$dateRange['start'], $dateRange['end']])
                ->sum('amount');

            // Баланс
            $balance = $totalIncome - $totalExpenses;

            // Расходы по категориям
            $categorySpending = Transaction::where('transactions.type', 'expense')
                ->whereBetween('date', [$dateRange['start'], $dateRange['end']])
                ->join('categories', 'transactions.category_id', '=', 'categories.id')
                ->selectRaw('categories.id, categories.name, categories.color, categories.budget_limit, SUM(transactions.amount) as total')
                ->groupBy('categories.id', 'categories.name', 'categories.color', 'categories.budget_limit')
                ->orderBy('total', 'desc')
                ->get();

            // Топ транзакций
            $largestExpenses = Transaction::with('category')
                ->where('transactions.type', 'expense')
                ->whereBetween('date', [$dateRange['start'], $dateRange['end']])
                ->orderBy('amount', 'desc')
                ->take(5)
                ->get();

            $largestIncomes = Transaction::with('category')
                ->where('transactions.type', 'income')
                ->whereBetween('date', [$dateRange['start'], $dateRange['end']])
                ->orderBy('amount', 'desc')
                ->take(5)
                ->get();

            // Статистика по методам оплаты
            $paymentMethods = Transaction::whereBetween('date', [$dateRange['start'], $dateRange['end']])
                ->selectRaw('payment_method, COUNT(*) as count, SUM(amount) as total')
                ->groupBy('payment_method')
                ->get();

            // Рекомендации
            $recommendations = $this->generateBudgetRecommendations($totalIncome, $totalExpenses, $categorySpending);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'period' => $period,
                    'date_range' => $dateRange,
                    'totals' => [
                        'income' => (float) $totalIncome,
                        'expenses' => (float) $totalExpenses,
                        'balance' => (float) $balance,
                        'savings_rate' => $totalIncome > 0 ? round(($balance / $totalIncome) * 100, 1) : 0
                    ],
                    'category_spending' => $categorySpending,
                    'largest_transactions' => [
                        'expenses' => $largestExpenses,
                        'incomes' => $largestIncomes
                    ],
                    'payment_methods' => $paymentMethods,
                    'recommendations' => $recommendations
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to load analytics overview: ' . $e->getMessage()
            ], 500);
        }
    }

    public function categorySpending(Request $request)
    {
        try {
            $month = $request->get('month', date('m'));
            $year = $request->get('year', date('Y'));
            $limit = $request->get('limit', 10);

            $categorySpending = Transaction::where('type', 'expense')
                ->whereMonth('date', $month)
                ->whereYear('date', $year)
                ->join('categories', 'transactions.category_id', '=', 'categories.id')
                ->selectRaw('categories.id, categories.name, categories.color, categories.budget_limit, 
                            SUM(transactions.amount) as total,
                            COUNT(transactions.id) as transaction_count')
                ->groupBy('categories.id', 'categories.name', 'categories.color', 'categories.budget_limit')
                ->orderBy('total', 'desc')
                ->take($limit)
                ->get();

            // Добавляем процент от общих расходов
            $totalExpenses = Transaction::where('type', 'expense')
                ->whereMonth('date', $month)
                ->whereYear('date', $year)
                ->sum('amount');

            $categorySpending->each(function ($category) use ($totalExpenses) {
                $category->percentage = $totalExpenses > 0 ? round(($category->total / $totalExpenses) * 100, 1) : 0;
                $category->budget_status = $this->getBudgetStatus($category->budget_limit, $category->total);
            });

            return response()->json([
                'status' => 'success',
                'data' => [
                    'category_spending' => $categorySpending,
                    'total_expenses' => (float) $totalExpenses,
                    'period' => [
                        'month' => $month,
                        'year' => $year
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to load category spending: ' . $e->getMessage()
            ], 500);
        }
    }

    public function monthlyTrends(Request $request)
    {
        try {
            $year = $request->get('year', date('Y'));
            $months = $request->get('months', 12);

            $trends = Transaction::selectRaw('
                    YEAR(date) as year,
                    MONTH(date) as month,
                    SUM(CASE WHEN type = "income" THEN amount ELSE 0 END) as income,
                    SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END) as expenses
                ')
                ->whereYear('date', $year)
                ->groupBy('year', 'month')
                ->orderBy('year', 'desc')
                ->orderBy('month', 'desc')
                ->take($months)
                ->get()
                ->map(function ($item) {
                    return [
                        'period' => $item->year . '-' . str_pad($item->month, 2, '0', STR_PAD_LEFT),
                        'income' => (float) $item->income,
                        'expenses' => (float) $item->expenses,
                        'balance' => (float) $item->income - (float) $item->expenses
                    ];
                })
                ->reverse()
                ->values();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'trends' => $trends,
                    'year' => $year,
                    'months_count' => $trends->count()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to load monthly trends: ' . $e->getMessage()
            ], 500);
        }
    }

    public function categoryComparison(Request $request)
    {
        try {
            $currentMonth = $request->get('current_month', date('m'));
            $currentYear = $request->get('current_year', date('Y'));
            $previousMonth = $currentMonth == 1 ? 12 : $currentMonth - 1;
            $previousYear = $currentMonth == 1 ? $currentYear - 1 : $currentYear;

            // Текущий месяц
            $currentData = Transaction::where('type', 'expense')
                ->whereMonth('date', $currentMonth)
                ->whereYear('date', $currentYear)
                ->join('categories', 'transactions.category_id', '=', 'categories.id')
                ->selectRaw('categories.id, categories.name, categories.color, SUM(transactions.amount) as total')
                ->groupBy('categories.id', 'categories.name', 'categories.color')
                ->get();

            // Предыдущий месяц
            $previousData = Transaction::where('type', 'expense')
                ->whereMonth('date', $previousMonth)
                ->whereYear('date', $previousYear)
                ->join('categories', 'transactions.category_id', '=', 'categories.id')
                ->selectRaw('categories.id, categories.name, SUM(transactions.amount) as total')
                ->groupBy('categories.id', 'categories.name')
                ->get()
                ->keyBy('id');

            // Сравнение
            $comparison = $currentData->map(function ($current) use ($previousData) {
                $previous = $previousData->get($current->id);
                $previousTotal = $previous ? $previous->total : 0;
                $change = $previousTotal > 0 ? (($current->total - $previousTotal) / $previousTotal) * 100 : 0;

                return [
                    'id' => $current->id,
                    'name' => $current->name,
                    'color' => $current->color,
                    'current_amount' => (float) $current->total,
                    'previous_amount' => (float) $previousTotal,
                    'change_percentage' => round($change, 1),
                    'change_amount' => (float) $current->total - (float) $previousTotal,
                    'trend' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'stable')
                ];
            })->sortByDesc('current_amount')->values();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'comparison' => $comparison,
                    'periods' => [
                        'current' => [
                            'month' => $currentMonth,
                            'year' => $currentYear
                        ],
                        'previous' => [
                            'month' => $previousMonth,
                            'year' => $previousYear
                        ]
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to load category comparison: ' . $e->getMessage()
            ], 500);
        }
    }

    public function budgetProgress(Request $request)
    {
        try {
            $month = $request->get('month', date('m'));
            $year = $request->get('year', date('Y'));

            // Категории с лимитами
            $categoriesWithLimits = Category::whereNotNull('budget_limit')
                ->where('type', 'expense')
                ->get();

            $progress = $categoriesWithLimits->map(function ($category) use ($month, $year) {
                $spent = Transaction::where('category_id', $category->id)
                    ->where('type', 'expense')
                    ->whereMonth('date', $month)
                    ->whereYear('date', $year)
                    ->sum('amount');

                $percentage = $category->budget_limit > 0 ? min(100, ($spent / $category->budget_limit) * 100) : 0;
                $remaining = max(0, $category->budget_limit - $spent);
                $overspent = max(0, $spent - $category->budget_limit);

                return [
                    'category_id' => $category->id,
                    'category_name' => $category->name,
                    'category_color' => $category->color,
                    'budget_limit' => (float) $category->budget_limit,
                    'spent' => (float) $spent,
                    'remaining' => (float) $remaining,
                    'overspent' => (float) $overspent,
                    'percentage' => round($percentage, 1),
                    'status' => $this->getBudgetStatus($category->budget_limit, $spent)
                ];
            })->sortByDesc('percentage')->values();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'progress' => $progress,
                    'period' => [
                        'month' => $month,
                        'year' => $year
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to load budget progress: ' . $e->getMessage()
            ], 500);
        }
    }

    public function financialHealth(Request $request)
    {
        try {
            $month = $request->get('month', date('m'));
            $year = $request->get('year', date('Y'));

            // Основные метрики
            $income = Transaction::where('type', 'income')
                ->whereMonth('date', $month)
                ->whereYear('date', $year)
                ->sum('amount');

            $expenses = Transaction::where('type', 'expense')
                ->whereMonth('date', $month)
                ->whereYear('date', $year)
                ->sum('amount');

            $balance = $income - $expenses;
            $savingsRate = $income > 0 ? ($balance / $income) * 100 : 0;

            // Анализ расходов
            $essentialCategories = ['Продукты', 'Коммунальные расходы', 'Транспорт', 'Здоровье'];
            $essentialSpending = Transaction::where('type', 'expense')
                ->whereMonth('date', $month)
                ->whereYear('date', $year)
                ->whereHas('category', function ($query) use ($essentialCategories) {
                    $query->whereIn('name', $essentialCategories);
                })
                ->sum('amount');

            $discretionarySpending = $expenses - $essentialSpending;

            // Оценка финансового здоровья
            $healthScore = $this->calculateHealthScore($income, $expenses, $savingsRate, $essentialSpending);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'metrics' => [
                        'income' => (float) $income,
                        'expenses' => (float) $expenses,
                        'balance' => (float) $balance,
                        'savings_rate' => round($savingsRate, 1),
                        'essential_spending' => (float) $essentialSpending,
                        'discretionary_spending' => (float) $discretionarySpending,
                        'essential_ratio' => $expenses > 0 ? round(($essentialSpending / $expenses) * 100, 1) : 0,
                        'discretionary_ratio' => $expenses > 0 ? round(($discretionarySpending / $expenses) * 100, 1) : 0
                    ],
                    'health_score' => $healthScore,
                    'health_status' => $this->getHealthStatus($healthScore),
                    'period' => [
                        'month' => $month,
                        'year' => $year
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to load financial health: ' . $e->getMessage()
            ], 500);
        }
    }

    // Вспомогательные методы

    private function getDateRange($period, $month, $year)
    {
        $carbon = Carbon::create($year, $month, 1);

        switch ($period) {
            case 'week':
                $start = now()->startOfWeek();
                $end = now()->endOfWeek();
                $label = 'Неделя ' . $start->format('d.m') . ' - ' . $end->format('d.m.Y');
                break;
            case 'year':
                $start = $carbon->copy()->startOfYear();
                $end = $carbon->copy()->endOfYear();
                $label = $carbon->translatedFormat('Y');
                break;
            case 'month':
            default:
                $start = $carbon->copy()->startOfMonth();
                $end = $carbon->copy()->endOfMonth();
                $label = $carbon->translatedFormat('F Y');
                break;
        }

        return [
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
            'label' => $label
        ];
    }

    private function generateBudgetRecommendations($income, $expenses, $categorySpending)
    {
        $recommendations = [];
        $balance = $income - $expenses;
        $totalSpending = $categorySpending->sum('total');

        // Анализ общего баланса
        if ($balance < 0) {
            $recommendations[] = [
                'type' => 'critical',
                'title' => 'Перерасход бюджета',
                'message' => "Вы потратили на " . number_format(abs($balance), 0, ',', ' ') . " ₽ больше, чем заработали. Срочно сократите расходы.",
                'priority' => 1
            ];
        } elseif ($balance < ($income * 0.1)) {
            $recommendations[] = [
                'type' => 'warning',
                'title' => 'Низкий уровень сбережений',
                'message' => "Старайтесь откладывать минимум 10% от дохода. Сейчас сбережения составляют " . number_format($balance, 0, ',', ' ') . " ₽ (" . round(($balance/$income)*100, 1) . "%).",
                'priority' => 2
            ];
        } else {
            $recommendations[] = [
                'type' => 'success',
                'title' => 'Отличные сбережения',
                'message' => "Вы сохранили " . number_format($balance, 0, ',', ' ') . " ₽ (" . round(($balance/$income)*100, 1) . "%) от дохода. Продолжайте в том же духе!",
                'priority' => 5
            ];
        }

        // Анализ по категориям
        foreach ($categorySpending as $category) {
            $percentage = $totalSpending > 0 ? ($category->total / $totalSpending) * 100 : 0;

            // Высокие расходы в категории
            if ($percentage > 30) {
                $recommendations[] = [
                    'type' => 'warning',
                    'title' => 'Высокие расходы на ' . $category->name,
                    'message' => "На " . $category->name . " уходит " . round($percentage) . "% всех расходов. Рассмотрите возможность оптимизации.",
                    'priority' => 3
                ];
            }

            // Превышение лимита бюджета
            if ($category->budget_limit && $category->total > $category->budget_limit) {
                $overspend = $category->total - $category->budget_limit;
                $recommendations[] = [
                    'type' => 'critical',
                    'title' => 'Превышен лимит по ' . $category->name,
                    'message' => "Лимит превышен на " . number_format($overspend, 0, ',', ' ') . " ₽ (" . round(($overspend/$category->budget_limit)*100, 1) . "%).",
                    'priority' => 1
                ];
            }

            // Категории приближающиеся к лимиту
            if ($category->budget_limit && $category->total > ($category->budget_limit * 0.8)) {
                $remaining = $category->budget_limit - $category->total;
                $recommendations[] = [
                    'type' => 'info',
                    'title' => 'Приближение к лимиту: ' . $category->name,
                    'message' => "До достижения лимита осталось " . number_format($remaining, 0, ',', ' ') . " ₽.",
                    'priority' => 4
                ];
            }
        }

        // Сортируем по приоритету
        usort($recommendations, function ($a, $b) {
            return $a['priority'] - $b['priority'];
        });

        return $recommendations;
    }

    private function getBudgetStatus($limit, $spent)
    {
        if (!$limit) return 'no_limit';

        $percentage = ($spent / $limit) * 100;

        if ($percentage <= 70) return 'good';
        if ($percentage <= 90) return 'warning';
        return 'critical';
    }

    private function calculateHealthScore($income, $expenses, $savingsRate, $essentialSpending)
    {
        $score = 0;

        // Оценка сбережений (макс 40 баллов)
        if ($savingsRate >= 20) $score += 40;
        elseif ($savingsRate >= 15) $score += 30;
        elseif ($savingsRate >= 10) $score += 20;
        elseif ($savingsRate >= 5) $score += 10;
        elseif ($savingsRate >= 0) $score += 5;

        // Оценка соотношения доход/расход (макс 30 баллов)
        $ratio = $expenses > 0 ? $income / $expenses : 0;
        if ($ratio >= 1.5) $score += 30;
        elseif ($ratio >= 1.3) $score += 25;
        elseif ($ratio >= 1.1) $score += 20;
        elseif ($ratio >= 1.0) $score += 10;

        // Оценка обязательных расходов (макс 30 баллов)
        $essentialRatio = $expenses > 0 ? ($essentialSpending / $expenses) * 100 : 0;
        if ($essentialRatio <= 50) $score += 30;
        elseif ($essentialRatio <= 60) $score += 25;
        elseif ($essentialRatio <= 70) $score += 20;
        elseif ($essentialRatio <= 80) $score += 10;

        return min(100, $score);
    }

    private function getHealthStatus($score)
    {
        if ($score >= 80) return 'excellent';
        if ($score >= 60) return 'good';
        if ($score >= 40) return 'fair';
        return 'poor';
    }
}