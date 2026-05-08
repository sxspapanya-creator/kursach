<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\Currency;
use App\Models\CurrencyRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CategoryController extends Controller
{
    public function index()
    {
        try {
            $userId = Auth::id();
            if (!$userId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized'
                ], 401);
            }

            $categories = Category::where('user_id', $userId)
                ->withCount(['transactions as transactions_count'])
                ->withSum(['transactions as transactions_amount'], 'amount')
                ->orderBy('type')
                ->orderBy('name')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $categories
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch categories'
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $userId = Auth::id();
            if (!$userId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized'
                ], 401);
            }

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'type' => 'required|in:income,expense',
                'color' => 'nullable|string|max:7',
                'budget_limit' => [
                    'nullable',
                    'numeric',
                    'min:0',
                    function ($attribute, $value, $fail) use ($request) {
                        if ($request->type === 'expense' && $value === null) {
                            $fail('Для категории расходов необходимо указать лимит бюджета.');
                        }
                    },
                ],
            ]);

            $existingCategory = Category::where('user_id', $userId)
                ->where('name', $validated['name'])
                ->first();

            if ($existingCategory) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => ['name' => ['Категория с таким именем уже существует.']]
                ], 422);
            }

            if ($validated['type'] === 'income') {
                $validated['budget_limit'] = null;
            }

            $validated['user_id'] = $userId;
            $category = Category::create($validated);

            $category->transactions_count = 0;
            $category->transactions_amount = 0;

            return response()->json([
                'status' => 'success',
                'message' => 'Category created successfully',
                'data' => $category
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create category'
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $userId = Auth::id();
            if (!$userId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized'
                ], 401);
            }

            $category = Category::where('user_id', $userId)
                ->withCount(['transactions as transactions_count'])
                ->withSum(['transactions as transactions_amount'], 'amount')
                ->findOrFail($id);

            return response()->json([
                'status' => 'success',
                'data' => $category
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Category not found'
            ], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $userId = Auth::id();
            if (!$userId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized'
                ], 401);
            }

            $category = Category::where('user_id', $userId)->findOrFail($id);

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'type' => 'sometimes|required|in:income,expense',
                'color' => 'nullable|string|max:7',
                'budget_limit' => [
                    'nullable',
                    'numeric',
                    'min:0',
                    function ($attribute, $value, $fail) use ($request, $category) {
                        $type = $request->type ?? $category->type;

                        if ($type === 'expense' && $value === null) {
                            $fail('Для категории расходов необходимо указать лимит бюджета.');
                        }

                        if ($type === 'income' && $value !== null) {
                            $fail('Лимит бюджета нельзя указывать для доходов.');
                        }
                    },
                ],
            ]);

            if (isset($validated['name'])) {
                $existingCategory = Category::where('user_id', $userId)
                    ->where('name', $validated['name'])
                    ->where('id', '!=', $id)
                    ->first();

                if ($existingCategory) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Validation failed',
                        'errors' => ['name' => ['Категория с таким именем уже существует.']]
                    ], 422);
                }
            }

            if (($validated['type'] ?? $category->type) === 'income') {
                $validated['budget_limit'] = null;
            }

            $category->update($validated);

            $category->loadCount(['transactions as transactions_count']);
            $category->loadSum(['transactions as transactions_amount'], 'amount');

            return response()->json([
                'status' => 'success',
                'message' => 'Category updated successfully',
                'data' => $category
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Category not found'
            ], 404);
        }
    }

    public function destroy($id)
    {
        try {
            $userId = Auth::id();
            if (!$userId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized'
                ], 401);
            }

            $category = Category::where('user_id', $userId)->findOrFail($id);

            $transactionCount = $category->transactions()->count();
            if ($transactionCount > 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot delete category with existing transactions. Please delete or reassign transactions first.'
                ], 422);
            }

            $category->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Category deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Category not found'
            ], 404);
        }
    }

    /**
     * Получить категории с транзакциями и разбивкой по валютам (только 5 валют)
     */
    public function withTransactions(Request $request)
    {
        try {
            $userId = Auth::id();
            if (!$userId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized'
                ], 401);
            }

            $validated = $request->validate([
                'month' => 'nullable|integer|min:1|max:12',
                'year' => 'nullable|integer|min:2000|max:2100'
            ]);

            $month = $validated['month'] ?? now()->month;
            $year = $validated['year'] ?? now()->year;

            // Получаем базовую валюту BYN
            $byn = Currency::where('code', 'BYN')->first();

            // Получаем все категории
            $categories = Category::where('user_id', $userId)
                ->orderBy('type')
                ->orderBy('name')
                ->get();

            $result = [];

            foreach ($categories as $category) {
                // Получаем транзакции категории за указанный месяц с валютой
                $transactions = Transaction::where('user_id', $userId)
                    ->whereHas('categories', function($q) use ($category) {
                        $q->where('category_id', $category->id);
                    })
                    ->whereMonth('date', $month)
                    ->whereYear('date', $year)
                    ->with('currency')
                    ->get();

                $totalAmountByn = 0;
                $transactionCount = $transactions->count();

                // Статистика по 5 валютам
                $currencyStats = [
                    'BYN' => ['currency_code' => 'BYN', 'currency_symbol' => 'Br', 'total_amount' => 0, 'transaction_count' => 0],
                    'RUB' => ['currency_code' => 'RUB', 'currency_symbol' => '₽', 'total_amount' => 0, 'transaction_count' => 0],
                    'USD' => ['currency_code' => 'USD', 'currency_symbol' => '$', 'total_amount' => 0, 'transaction_count' => 0],
                    'EUR' => ['currency_code' => 'EUR', 'currency_symbol' => '€', 'total_amount' => 0, 'transaction_count' => 0],
                    'CNY' => ['currency_code' => 'CNY', 'currency_symbol' => '¥', 'total_amount' => 0, 'transaction_count' => 0],
                ];

                foreach ($transactions as $transaction) {
                    $amount = $transaction->amount;
                    $currency = $transaction->currency;
                    $currencyCode = $currency ? $currency->code : 'BYN';
                    $currencyId = $transaction->currency_id;
                    $date = $transaction->date;

                    // Конвертируем в BYN
                    $rate = 1;
                    if ($currencyCode !== 'BYN' && $byn) {
                        $rateRecord = CurrencyRate::where('from_currency_id', $currencyId)
                            ->where('to_currency_id', $byn->id)
                            ->where('date', $date)
                            ->first();

                        if (!$rateRecord) {
                            $rateRecord = CurrencyRate::where('from_currency_id', $currencyId)
                                ->where('to_currency_id', $byn->id)
                                ->where('date', '<=', $date)
                                ->orderBy('date', 'desc')
                                ->first();
                        }

                        $rate = $rateRecord ? $rateRecord->rate : 1;
                    }

                    $totalAmountByn += $amount * $rate;

                    // Обновляем статистику по валюте
                    if (isset($currencyStats[$currencyCode])) {
                        $currencyStats[$currencyCode]['total_amount'] += $amount;
                        $currencyStats[$currencyCode]['transaction_count']++;
                    }
                }

                // Удаляем валюты с нулевыми показателями
                $filteredCurrencyStats = array_values(array_filter($currencyStats, function($stat) {
                    return $stat['transaction_count'] > 0;
                }));

                // Получаем последнюю транзакцию
                $lastTransaction = Transaction::where('user_id', $userId)
                    ->whereHas('categories', function($q) use ($category) {
                        $q->where('category_id', $category->id);
                    })
                    ->orderBy('date', 'desc')
                    ->first();

                // Получаем общее количество транзакций за все время
                $allTimeCount = Transaction::where('user_id', $userId)
                    ->whereHas('categories', function($q) use ($category) {
                        $q->where('category_id', $category->id);
                    })
                    ->count();

                $result[] = [
                    'id' => $category->id,
                    'name' => $category->name,
                    'type' => $category->type,
                    'color' => $category->color,
                    'budget_limit' => $category->budget_limit,
                    'transaction_count' => $transactionCount,
                    'total_amount_byn' => round($totalAmountByn, 2),
                    'currency_stats' => $filteredCurrencyStats,
                    'last_transaction_date' => $lastTransaction ? $lastTransaction->date : null,
                    'all_time_count' => $allTimeCount,
                    'updated_at' => $category->updated_at->format('Y-m-d H:i:s')
                ];
            }

            return response()->json([
                'status' => 'success',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch categories with transactions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получить категории со статистикой по валютам (только 5 валют)
     */
    public function withStats(Request $request)
    {
        try {
            $userId = Auth::id();
            if (!$userId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized'
                ], 401);
            }

            $validated = $request->validate([
                'month' => 'nullable|integer|min:1|max:12',
                'year' => 'nullable|integer|min:2000|max:2100'
            ]);

            $month = $validated['month'] ?? now()->month;
            $year = $validated['year'] ?? now()->year;

            // Получаем базовую валюту BYN
            $byn = Currency::where('code', 'BYN')->first();

            // Получаем все категории
            $categories = Category::where('user_id', $userId)
                ->orderBy('type')
                ->orderBy('name')
                ->get();

            $result = [];

            foreach ($categories as $category) {
                // Получаем транзакции за указанный месяц
                $monthTransactions = Transaction::where('user_id', $userId)
                    ->whereHas('categories', function($q) use ($category) {
                        $q->where('category_id', $category->id);
                    })
                    ->whereMonth('date', $month)
                    ->whereYear('date', $year)
                    ->with('currency')
                    ->get();

                // Получаем все транзакции за все время
                $allTransactions = Transaction::where('user_id', $userId)
                    ->whereHas('categories', function($q) use ($category) {
                        $q->where('category_id', $category->id);
                    })
                    ->with('currency')
                    ->get();

                $monthTotalByn = 0;
                $monthCount = $monthTransactions->count();
                $allTimeTotalByn = 0;
                $allTimeCount = $allTransactions->count();

                // Статистика по 5 валютам за месяц
                $monthCurrencyStats = [
                    'BYN' => ['currency_code' => 'BYN', 'currency_symbol' => 'Br', 'total_amount' => 0, 'transaction_count' => 0],
                    'RUB' => ['currency_code' => 'RUB', 'currency_symbol' => '₽', 'total_amount' => 0, 'transaction_count' => 0],
                    'USD' => ['currency_code' => 'USD', 'currency_symbol' => '$', 'total_amount' => 0, 'transaction_count' => 0],
                    'EUR' => ['currency_code' => 'EUR', 'currency_symbol' => '€', 'total_amount' => 0, 'transaction_count' => 0],
                    'CNY' => ['currency_code' => 'CNY', 'currency_symbol' => '¥', 'total_amount' => 0, 'transaction_count' => 0],
                ];

                // Обработка транзакций за месяц
                foreach ($monthTransactions as $transaction) {
                    $amount = $transaction->amount;
                    $currency = $transaction->currency;
                    $currencyCode = $currency ? $currency->code : 'BYN';
                    $currencyId = $transaction->currency_id;
                    $date = $transaction->date;

                    $rate = 1;
                    if ($currencyCode !== 'BYN' && $byn) {
                        $rateRecord = CurrencyRate::where('from_currency_id', $currencyId)
                            ->where('to_currency_id', $byn->id)
                            ->where('date', $date)
                            ->first();

                        if (!$rateRecord) {
                            $rateRecord = CurrencyRate::where('from_currency_id', $currencyId)
                                ->where('to_currency_id', $byn->id)
                                ->where('date', '<=', $date)
                                ->orderBy('date', 'desc')
                                ->first();
                        }

                        $rate = $rateRecord ? $rateRecord->rate : 1;
                    }

                    $monthTotalByn += $amount * $rate;

                    if (isset($monthCurrencyStats[$currencyCode])) {
                        $monthCurrencyStats[$currencyCode]['total_amount'] += $amount;
                        $monthCurrencyStats[$currencyCode]['transaction_count']++;
                    }
                }

                // Обработка всех транзакций (для all_time_total_byn)
                foreach ($allTransactions as $transaction) {
                    $amount = $transaction->amount;
                    $currency = $transaction->currency;
                    $currencyCode = $currency ? $currency->code : 'BYN';
                    $currencyId = $transaction->currency_id;
                    $date = $transaction->date;

                    $rate = 1;
                    if ($currencyCode !== 'BYN' && $byn) {
                        $rateRecord = CurrencyRate::where('from_currency_id', $currencyId)
                            ->where('to_currency_id', $byn->id)
                            ->where('date', $date)
                            ->first();

                        if (!$rateRecord) {
                            $rateRecord = CurrencyRate::where('from_currency_id', $currencyId)
                                ->where('to_currency_id', $byn->id)
                                ->where('date', '<=', $date)
                                ->orderBy('date', 'desc')
                                ->first();
                        }

                        $rate = $rateRecord ? $rateRecord->rate : 1;
                    }

                    $allTimeTotalByn += $amount * $rate;
                }

                // Удаляем валюты с нулевыми показателями
                $filteredMonthCurrencyStats = array_values(array_filter($monthCurrencyStats, function($stat) {
                    return $stat['transaction_count'] > 0;
                }));

                // Получаем последнюю транзакцию
                $lastTransaction = Transaction::where('user_id', $userId)
                    ->whereHas('categories', function($q) use ($category) {
                        $q->where('category_id', $category->id);
                    })
                    ->orderBy('date', 'desc')
                    ->first();

                $result[] = [
                    'id' => $category->id,
                    'name' => $category->name,
                    'type' => $category->type,
                    'color' => $category->color,
                    'budget_limit' => $category->budget_limit,
                    'transaction_count' => $monthCount,
                    'total_amount' => round($monthTotalByn, 2),
                    'currency_stats' => $filteredMonthCurrencyStats,
                    'all_time_count' => $allTimeCount,
                    'all_time_total_byn' => round($allTimeTotalByn, 2),
                    'last_transaction_date' => $lastTransaction ? $lastTransaction->date : null,
                    'created_at' => $category->created_at,
                    'updated_at' => $category->updated_at,
                ];
            }

            return response()->json([
                'status' => 'success',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch categories with stats: ' . $e->getMessage()
            ], 500);
        }
    }
}