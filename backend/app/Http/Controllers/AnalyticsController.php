<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    protected function getUserId()
    {
        $userId = Auth::id();
        if (!$userId) abort(401, 'Unauthorized');
        return $userId;
    }

    public function overview(Request $request)
    {
        try {
            $userId = $this->getUserId();
            $validated = $request->validate([
                'period' => 'nullable|in:week,month,year',
                'year' => 'nullable|integer|min:2000|max:2100',
                'month' => 'nullable|integer|min:1|max:12'
            ]);

            $period = $validated['period'] ?? 'month';
            $year = $validated['year'] ?? date('Y');
            $month = $validated['month'] ?? date('m');

            $baseData = $this->getBaseAnalytics($year, $month, $period, $userId);
            $categorySpending = $this->getCategorySpendingWithAnalysis($year, $month, $userId);
            $largestTransactions = $this->getLargestTransactions($year, $month, $userId);
            $financialHealth = $this->calculateFinancialHealth(
                $baseData['totals']['balance'] ?? 0,
                $baseData['totals']['savings_rate'] ?? 0
            );

            return response()->json([
                'status' => 'success',
                'data' => [
                    'totals' => $baseData['totals'] ?? [],
                    'date_range' => $baseData['date_range'] ?? [],
                    'category_spending' => $categorySpending,
                    'largest_transactions' => $largestTransactions,
                    'financial_health' => $financialHealth
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Ошибка: ' . $e->getMessage()], 200);
        }
    }

    /**
     * Расчет финансового здоровья
     */
    private function calculateFinancialHealth($balance, $savingsRate)
    {
        try {
            $score = 0;

            // Оценка баланса (максимум 50 баллов)
            if ($balance > 0) {
                $score += min(50, ($balance / 10000) * 50);
            } elseif ($balance < 0) {
                $score += max(-25, ($balance / 10000) * 25);
            }

            // Оценка нормы сбережений (максимум 50 баллов)
            if ($savingsRate > 0) {
                $score += min(50, ($savingsRate / 25) * 50);
            } elseif ($savingsRate < 0) {
                $score += max(-25, ($savingsRate / 25) * 25);
            }

            $score = min(100, max(0, $score));

            // Определение статуса
            if ($score >= 80) {
                $status = 'excellent';
                $statusLabel = 'Отлично';
                $color = '#27ae60';
            } elseif ($score >= 60) {
                $status = 'good';
                $statusLabel = 'Хорошо';
                $color = '#2ecc71';
            } elseif ($score >= 40) {
                $status = 'fair';
                $statusLabel = 'Удовлетворительно';
                $color = '#f39c12';
            } elseif ($score >= 20) {
                $status = 'poor';
                $statusLabel = 'Плохо';
                $color = '#e74c3c';
            } else {
                $status = 'critical';
                $statusLabel = 'Критично';
                $color = '#c0392b';
            }

            return [
                'score' => round($score),
                'status' => $status,
                'status_label' => $statusLabel,
                'color' => $color
            ];
        } catch (\Exception $e) {
            return [
                'score' => 0,
                'status' => 'poor',
                'status_label' => 'Не определено',
                'color' => '#95a5a6'
            ];
        }
    }

    private function getBaseAnalytics($year, $month, $period = 'month', $userId = null)
    {
        if (!$userId) $userId = $this->getUserId();

        $startDate = null;
        $endDate = null;

        switch ($period) {
            case 'week':
                $startDate = Carbon::now()->subDays(6);
                $endDate = Carbon::now();
                break;
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
            ->whereBetween('date', [$startDate, $endDate])
            ->selectRaw('SUM(CASE WHEN type = "income" THEN amount ELSE 0 END) as total_income')
            ->selectRaw('SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END) as total_expense')
            ->first();

        $totalIncome = $transactions->total_income ?? 0;
        $totalExpense = $transactions->total_expense ?? 0;
        $balance = $totalIncome - $totalExpense;
        $savingsRate = $totalIncome > 0 ? ($balance / $totalIncome) * 100 : 0;

        return [
            'totals' => [
                'income' => (float) $totalIncome,
                'expenses' => (float) $totalExpense,
                'balance' => (float) $balance,
                'savings_rate' => (float) $savingsRate,
            ],
            'date_range' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
                'label' => $startDate->translatedFormat('F Y')
            ]
        ];
    }

    private function getCategorySpendingWithAnalysis($year, $month, $userId = null)
    {
        try {
            if (!$userId) $userId = $this->getUserId();
            $startDate = Carbon::create($year, $month, 1)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();

            return Category::where('user_id', $userId)
                ->where('type', 'expense')
                ->with(['transactions' => function($query) use ($startDate, $endDate, $userId) {
                    $query->where('user_id', $userId)
                        ->where('type', 'expense')
                        ->whereBetween('date', [$startDate, $endDate]);
                }])
                ->get()
                ->map(function($category) {
                    $total = $category->transactions->sum('amount');
                    return [
                        'id' => $category->id,
                        'name' => $category->name,
                        'color' => $category->color ?? '#3498db',
                        'total' => $total,
                        'budget_limit' => $category->budget_limit ?? 0,
                        'limit_percentage' => $category->budget_limit > 0 ? ($total / $category->budget_limit) * 100 : 0,
                        'budget_status' => 'no_limit',
                        'average_monthly' => 0
                    ];
                })
                ->filter(fn($cat) => $cat['total'] > 0)
                ->sortByDesc('total')
                ->values()
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Получение помесячных трендов для дашборда
     */
    public function monthlyTrends(Request $request)
    {
        try {
            $userId = $this->getUserId();
            $months = $request->input('months', 12);

            $endDate = Carbon::now()->endOfMonth();
            $startDate = $endDate->copy()->subMonths($months)->startOfMonth();

            $trends = Transaction::where('user_id', $userId)
                ->whereBetween('date', [$startDate, $endDate])
                ->selectRaw('YEAR(date) as year, MONTH(date) as month')
                ->selectRaw('SUM(CASE WHEN type = "income" THEN amount ELSE 0 END) as income')
                ->selectRaw('SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END) as expense')
                ->groupBy('year', 'month')
                ->orderBy('year', 'asc')
                ->orderBy('month', 'asc')
                ->get()
                ->map(function($item) {
                    return [
                        'month' => $item->year . '-' . str_pad($item->month, 2, '0', STR_PAD_LEFT),
                        'income' => (float) ($item->income ?? 0),
                        'expense' => (float) ($item->expense ?? 0),
                        'balance' => (float) (($item->income ?? 0) - ($item->expense ?? 0))
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => $trends
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Ошибка при получении трендов',
                'data' => []
            ], 500);
        }
    }

    private function getLargestTransactions($year, $month, $userId = null)
    {
        try {
            if (!$userId) $userId = $this->getUserId();
            $startDate = Carbon::create($year, $month, 1)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();

            return [
                'expenses' => Transaction::where('user_id', $userId)
                    ->with('category')
                    ->whereBetween('date', [$startDate, $endDate])
                    ->where('type', 'expense')
                    ->orderBy('amount', 'desc')
                    ->limit(5)
                    ->get()
                    ->map(fn($t) => [
                        'id' => $t->id,
                        'description' => $t->description ?? 'Без описания',
                        'amount' => $t->amount,
                        'date' => $t->date->format('d.m.Y'),
                        'category' => $t->category->name ?? 'Без категории',
                        'category_color' => $t->category->color ?? '#95a5a6'
                    ]),
                'incomes' => Transaction::where('user_id', $userId)
                    ->with('category')
                    ->whereBetween('date', [$startDate, $endDate])
                    ->where('type', 'income')
                    ->orderBy('amount', 'desc')
                    ->limit(5)
                    ->get()
                    ->map(fn($t) => [
                        'id' => $t->id,
                        'description' => $t->description ?? 'Без описания',
                        'amount' => $t->amount,
                        'date' => $t->date->format('d.m.Y'),
                        'category' => $t->category->name ?? 'Без категории',
                        'category_color' => $t->category->color ?? '#95a5a6'
                    ])
            ];
        } catch (\Exception $e) {
            return ['expenses' => [], 'incomes' => []];
        }
    }
}