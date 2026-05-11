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

class AnalyticsController extends Controller
{
    protected function getUserId()
    {
        $userId = Auth::id();
        if (!$userId) abort(401, 'Unauthorized');
        return $userId;
    }

    private function loadRatesForTransactions(Collection $transactions, Currency $baseCurrency): array
    {
        if ($transactions->isEmpty()) {
            return [];
        }

        $currencyIds = $transactions
            ->pluck('currency_id')
            ->unique()
            ->filter(function($id) use ($baseCurrency) {
                return $id != $baseCurrency->id;
            });

        if ($currencyIds->isEmpty()) {
            return [];
        }

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

            if (!isset($rates[$currencyId])) {
                $rates[$currencyId] = [];
            }

            $rates[$currencyId][$dateKey] = $rate->rate;
        }

        return $rates;
    }

    private function getRateFromCache(array $rates, int $currencyId, Carbon $date, Currency $baseCurrency): ?float
    {
        if ($currencyId == $baseCurrency->id) {
            return 1;
        }

        if (!isset($rates[$currencyId])) {
            return null;
        }

        $dateKey = $date->toDateString();

        if (isset($rates[$currencyId][$dateKey])) {
            return $rates[$currencyId][$dateKey];
        }

        $availableDates = array_keys($rates[$currencyId]);
        $lastDate = null;
        foreach ($availableDates as $d) {
            if ($d <= $dateKey) {
                $lastDate = $d;
            } else {
                break;
            }
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
        if ($transaction->currency_id == $baseCurrency->id) {
            return (float) $transaction->amount;
        }

        $rate = null;
        if (!empty($ratesCache)) {
            $rate = $this->getRateFromCache($ratesCache, $transaction->currency_id, $transaction->date, $baseCurrency);
        }

        if ($rate === null) {
            $rate = $this->getRateFromDatabase($transaction->currency_id, $baseCurrency->id, $transaction->date);
        }

        if ($rate === null) {
            \Log::warning("Курс не найден для валюты {$transaction->currency_id} на дату {$transaction->date}");
            return (float) $transaction->amount;
        }

        return (float) $transaction->amount * $rate;
    }

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
                return response()->json([
                    'status' => 'error',
                    'message' => 'Базовая валюта BYN не найдена'
                ], 500);
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

            usort($categorySpending, function($a, $b) {
                return $b['total'] <=> $a['total'];
            });

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
            \Log::error('Overview error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Ошибка: ' . $e->getMessage()
            ], 500);
        }
    }

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

            if ($cycleBalance <= 0) {
                $liquidityScore = 0;
            } elseif ($cycleBalance >= $neededUntilSalary * 1.5) {
                $liquidityScore = 100;
            } elseif ($cycleBalance >= $neededUntilSalary) {
                $liquidityScore = 70;
            } elseif ($cycleBalance >= $neededUntilSalary * 0.7) {
                $liquidityScore = 40;
            } else {
                $liquidityScore = 10;
            }

            if ($avgMonthlyExpense <= 0) {
                $emergencyFundScore = 50;
            } elseif ($savings >= $avgMonthlyExpense * 6) {
                $emergencyFundScore = 100;
            } elseif ($savings >= $avgMonthlyExpense * 3) {
                $emergencyFundScore = 70;
            } elseif ($savings >= $avgMonthlyExpense * 1) {
                $emergencyFundScore = 40;
            } elseif ($savings > 0) {
                $emergencyFundScore = 20;
            } else {
                $emergencyFundScore = 0;
            }

            if ($avgMonthlyIncome <= 0) {
                $debtLoadScore = 0;
            } elseif ($monthlyLoanPayments <= $avgMonthlyIncome * 0.2) {
                $debtLoadScore = 100;
            } elseif ($monthlyLoanPayments <= $avgMonthlyIncome * 0.35) {
                $debtLoadScore = 60;
            } elseif ($monthlyLoanPayments <= $avgMonthlyIncome * 0.5) {
                $debtLoadScore = 30;
            } else {
                $debtLoadScore = 0;
            }

            $availableAfterExpenses = $avgMonthlyIncome - $avgMonthlyExpense - $monthlyLoanPayments;
            $savingsRate = $avgMonthlyIncome > 0 ? ($availableAfterExpenses / $avgMonthlyIncome) * 100 : 0;

            if ($savingsRate >= 20) {
                $savingsRateScore = 100;
            } elseif ($savingsRate >= 10) {
                $savingsRateScore = 70;
            } elseif ($savingsRate >= 5) {
                $savingsRateScore = 40;
            } elseif ($savingsRate > 0) {
                $savingsRateScore = 20;
            } else {
                $savingsRateScore = 0;
            }

            $totalScore = ($liquidityScore * 0.30) + ($emergencyFundScore * 0.30) + ($debtLoadScore * 0.20) + ($savingsRateScore * 0.20);
            $totalScore = round(min(100, max(0, $totalScore)));

            if ($totalScore >= 80) {
                $status = 'excellent';
                $statusLabel = 'Отлично';
                $color = '#27ae60';
            } elseif ($totalScore >= 60) {
                $status = 'good';
                $statusLabel = 'Хорошо';
                $color = '#2ecc71';
            } elseif ($totalScore >= 40) {
                $status = 'fair';
                $statusLabel = 'Удовлетворительно';
                $color = '#f39c12';
            } elseif ($totalScore >= 20) {
                $status = 'poor';
                $statusLabel = 'Плохо';
                $color = '#e74c3c';
            } else {
                $status = 'critical';
                $statusLabel = 'Критично';
                $color = '#c0392b';
            }

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
            \Log::error('Financial health calculation error: ' . $e->getMessage());
            return [
                'score' => 0,
                'status' => 'poor',
                'status_label' => 'Не определено',
                'color' => '#95a5a6',
                'components' => []
            ];
        }
    }

    private function getCurrentCycleBalance(int $userId, Carbon $currentDate, int $salaryDay): float
    {
        $lastSalaryDate = Carbon::create($currentDate->year, $currentDate->month, $salaryDay);

        if ($lastSalaryDate > $currentDate) {
            $lastSalaryDate->subMonth();
        }

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

    private function getUserSalaryDay(int $userId): int
    {
        return 25;
    }

    private function getDaysUntilNextSalary(int $userId, int $salaryDay, Carbon $currentDate): int
    {
        $nextSalaryDate = Carbon::create($currentDate->year, $currentDate->month, $salaryDay);
        if ($nextSalaryDate <= $currentDate) {
            $nextSalaryDate->addMonth();
        }
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
        $loanCategory = Category::where('user_id', $userId)
            ->where('name', 'Кредиты')
            ->where('type', 'expense')
            ->first();

        if ($loanCategory) {
            $threeMonthsAgo = Carbon::now()->subMonths(3);
            $totalLoanPayments = Transaction::where('user_id', $userId)
                ->whereHas('categories', function($q) use ($loanCategory) {
                    $q->where('categories.id', $loanCategory->id);
                })
                ->whereBetween('date', [$threeMonthsAgo, Carbon::now()])
                ->sum('amount');
            return $totalLoanPayments / 3;
        }
        return 0;
    }

    public function monthlyTrends(Request $request)
    {
        try {
            $userId = $this->getUserId();
            $months = $request->input('months', 12);

            $endDate = Carbon::now()->endOfMonth();
            $startDate = $endDate->copy()->subMonths($months)->startOfMonth();

            $transactions = Transaction::where('user_id', $userId)
                ->with(['currency'])
                ->whereBetween('date', [$startDate, $endDate])
                ->get();

            $baseCurrency = Currency::where('code', 'BYN')->first();
            if (!$baseCurrency) {
                return response()->json(['status' => 'error', 'message' => 'Базовая валюта BYN не найдена'], 500);
            }

            $ratesCache = $this->loadRatesForTransactions($transactions, $baseCurrency);

            $monthlyData = [];

            foreach ($transactions as $transaction) {
                $amountInBase = $this->convertToBaseCurrency($transaction, $baseCurrency, $ratesCache);
                $date = Carbon::parse($transaction->date);
                $monthKey = $date->format('Y-m');

                if (!isset($monthlyData[$monthKey])) {
                    $monthlyData[$monthKey] = ['month' => $monthKey, 'income' => 0, 'expense' => 0];
                }

                if ($transaction->type === 'income') {
                    $monthlyData[$monthKey]['income'] += $amountInBase;
                } else {
                    $monthlyData[$monthKey]['expense'] += $amountInBase;
                }
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
}