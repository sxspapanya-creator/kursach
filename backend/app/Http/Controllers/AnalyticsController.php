<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Category;
use App\Models\Currency;
use App\Models\CurrencyRate;
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

            // Получаем транзакции с категориями и валютой
            $transactions = Transaction::where('user_id', $userId)
                ->with(['categories', 'currency'])
                ->whereBetween('date', [$startDate, $endDate])
                ->get();

            $byn = Currency::where('code', 'BYN')->first();

            // ========== КЛЮЧЕВОЕ: добавляем amount_in_byn к каждой транзакции (как в TransactionController@index) ==========
            $transactionsWithRates = $transactions->map(function ($transaction) use ($byn) {
                if ($transaction->currency_id && $byn && $transaction->currency_id != $byn->id) {
                    // Ищем курс на дату транзакции или ближайший предыдущий
                    $rate = CurrencyRate::where('from_currency_id', $transaction->currency_id)
                        ->where('to_currency_id', $byn->id)
                        ->where('date', '<=', $transaction->date)
                        ->orderBy('date', 'desc')
                        ->first();

                    $transaction->exchange_rate = $rate ? $rate->rate : null;
                    $transaction->amount_in_byn = $rate ? $transaction->amount * $rate->rate : $transaction->amount;
                } else {
                    $transaction->exchange_rate = 1;
                    $transaction->amount_in_byn = $transaction->amount;
                }
                return $transaction;
            });

            // Считаем суммы используя amount_in_byn
            $totalIncome = 0;
            $totalExpense = 0;
            $categoryTotals = [];

            foreach ($transactionsWithRates as $transaction) {
                $amountInByn = $transaction->amount_in_byn;

                if ($transaction->type === 'income') {
                    $totalIncome += $amountInByn;
                } else {
                    $totalExpense += $amountInByn;

                    // Группируем по категориям
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
                        $categoryTotals[$catId]['total'] += $amountInByn;
                    }
                }
            }

            $balance = $totalIncome - $totalExpense;
            $savingsRate = $totalIncome > 0 ? ($balance / $totalIncome) * 100 : 0;

            // Формируем результат по категориям
            $categorySpending = [];
            foreach ($categoryTotals as $cat) {
                $limitPercentage = 0;
                $budgetStatus = 'no_limit';

                if ($cat['budget_limit'] > 0) {
                    $limitPercentage = ($cat['total'] / $cat['budget_limit']) * 100;
                    if ($limitPercentage <= 80) {
                        $budgetStatus = 'good';
                    } elseif ($limitPercentage <= 100) {
                        $budgetStatus = 'warning';
                    } else {
                        $budgetStatus = 'critical';
                    }
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

            // Сортируем по сумме (убывание)
            usort($categorySpending, function($a, $b) {
                return $b['total'] <=> $a['total'];
            });

            $financialHealth = $this->calculateFinancialHealth($balance, $savingsRate);

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
            return response()->json([
                'status' => 'error',
                'message' => 'Ошибка: ' . $e->getMessage()
            ], 500);
        }
    }

    public function monthlyTrends(Request $request)
    {
        try {
            $userId = $this->getUserId();
            $months = $request->input('months', 12);

            $endDate = Carbon::now()->endOfMonth();
            $startDate = $endDate->copy()->subMonths($months)->startOfMonth();

            $transactions = Transaction::where('user_id', $userId)
                ->whereBetween('date', [$startDate, $endDate])
                ->get();

            $byn = Currency::where('code', 'BYN')->first();

            $monthlyData = [];

            foreach ($transactions as $transaction) {
                // Вычисляем amount_in_byn (как в TransactionController)
                if ($transaction->currency_id && $byn && $transaction->currency_id != $byn->id) {
                    $rate = CurrencyRate::where('from_currency_id', $transaction->currency_id)
                        ->where('to_currency_id', $byn->id)
                        ->where('date', '<=', $transaction->date)
                        ->orderBy('date', 'desc')
                        ->first();
                    $amountInByn = $rate ? $transaction->amount * $rate->rate : $transaction->amount;
                } else {
                    $amountInByn = $transaction->amount;
                }

                $date = Carbon::parse($transaction->date);
                $monthKey = $date->format('Y-m');

                if (!isset($monthlyData[$monthKey])) {
                    $monthlyData[$monthKey] = [
                        'month' => $monthKey,
                        'income' => 0,
                        'expense' => 0,
                        'balance' => 0
                    ];
                }

                if ($transaction->type === 'income') {
                    $monthlyData[$monthKey]['income'] += $amountInByn;
                } else {
                    $monthlyData[$monthKey]['expense'] += $amountInByn;
                }
            }

            // Сортируем по дате
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

            return response()->json([
                'status' => 'success',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            \Log::error('Monthly trends error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Ошибка при получении трендов',
                'data' => []
            ], 500);
        }
    }

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
}