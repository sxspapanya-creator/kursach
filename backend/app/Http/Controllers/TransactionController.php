<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Category;
use App\Models\Currency;
use App\Models\CurrencyRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Services\TransactionCategorizer;

class TransactionController extends Controller
{
    public function index(Request $request)
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
                'type' => 'nullable|in:income,expense',
                'category_ids' => 'nullable|array',
                'category_ids.*' => [
                    'integer',
                    Rule::exists('categories', 'id')->where(fn ($q) => $q->where('user_id', $userId)),
                ],
                'month' => 'nullable|integer|min:1|max:12',
                'year' => 'nullable|integer|min:2000|max:2100',
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date|after_or_equal:date_from',
                'limit' => 'nullable|integer|min:1|max:10000',
                'include_anomalies' => 'nullable|boolean', // НОВЫЙ ПАРАМЕТР
            ]);

            $fetchAll = $request->boolean('fetch_all');
            $includeAnomalies = $request->boolean('include_anomalies', false); // По умолчанию false

            $query = Transaction::where('user_id', $userId)->with(['categories', 'currency']);

            // НОВОЕ: Фильтрация по is_anomaly (по умолчанию исключаем аномалии)
            if (!$includeAnomalies) {
                $query->where('is_anomaly', false);
            }

            // Фильтрация по типу
            if (isset($validated['type'])) {
                $query->where('type', $validated['type']);
            }

            // Фильтрация по категориям
            if (!empty($validated['category_ids'])) {
                $categoryIds = array_values(array_unique($validated['category_ids']));
                $query->whereHas('categories', function ($q) use ($categoryIds) {
                    $q->whereIn('categories.id', $categoryIds);
                });
            }

            // Фильтрация по месяцу
            if (isset($validated['month'])) {
                $query->whereMonth('date', $validated['month']);
            }

            // Фильтрация по году
            if (isset($validated['year'])) {
                $query->whereYear('date', $validated['year']);
            }

            // Фильтрация по дате (диапазон)
            if (isset($validated['date_from'])) {
                $query->where('date', '>=', $validated['date_from']);
            }

            if (isset($validated['date_to'])) {
                $query->where('date', '<=', $validated['date_to']);
            }

            $limit = $validated['limit'] ?? 50;

            $ordered = $query->orderBy('date', 'desc')
                ->orderBy('created_at', 'desc');

            $transactions = $fetchAll
                ? $ordered->get()
                : $ordered->take($limit)->get();

            // Получаем базовую валюту BYN для конвертации
            $byn = Currency::where('code', 'BYN')->first();

            // Добавляем курс конвертации к каждой транзакции
            $transactionsWithRates = $transactions->map(function ($transaction) use ($byn) {
                if ($transaction->currency_id && $transaction->currency_id != $byn->id) {
                    $rate = CurrencyRate::where('from_currency_id', $transaction->currency_id)
                        ->where('to_currency_id', $byn->id)
                        ->where('date', $transaction->date)
                        ->first();

                    if (!$rate) {
                        $rate = CurrencyRate::where('from_currency_id', $transaction->currency_id)
                            ->where('to_currency_id', $byn->id)
                            ->where('date', '<=', $transaction->date)
                            ->orderBy('date', 'desc')
                            ->first();
                    }

                    $transaction->exchange_rate = $rate ? $rate->rate : null;
                    $transaction->amount_in_byn = $rate ? $transaction->amount * $rate->rate : $transaction->amount;
                } else {
                    $transaction->exchange_rate = 1;
                    $transaction->amount_in_byn = $transaction->amount;
                }

                return $transaction;
            });

            return response()->json([
                'status' => 'success',
                'data' => $transactionsWithRates,
                'meta' => [
                    'total' => $transactions->count(),
                    'limit' => $fetchAll ? null : $limit,
                    'fetch_all' => $fetchAll,
                    'include_anomalies' => $includeAnomalies, // НОВОЕ
                ]
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('TransactionController::index error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch transactions: ' . $e->getMessage()
            ], 500);
        }
    }

    // НОВЫЙ МЕТОД: Отметить транзакцию как аномальную/разовую
    public function markAsAnomaly($id, Request $request)
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
                'is_anomaly' => 'required|boolean',
                'reason' => 'nullable|string|max:500'
            ]);

            $transaction = Transaction::where('user_id', $userId)->findOrFail($id);

            // Обновляем статус аномалии
            $transaction->is_anomaly = $validated['is_anomaly'];

            // Опционально: добавляем причину в описание
            if ($validated['is_anomaly'] && !empty($validated['reason'])) {
                $transaction->description = $transaction->description . " [Аномалия: {$validated['reason']}]";
            }

            $transaction->save();

            $statusText = $validated['is_anomaly'] ? 'отмечена как аномальная/разовая' : 'отмечена как обычная';

            return response()->json([
                'status' => 'success',
                'message' => "Транзакция {$statusText}",
                'data' => $transaction->load(['categories', 'currency'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to mark transaction: ' . $e->getMessage()
            ], 404);
        }
    }

    // НОВЫЙ МЕТОД: Получить список аномальных транзакций
    public function getAnomalies(Request $request)
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
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date',
            ]);

            $query = Transaction::where('user_id', $userId)
                ->where('is_anomaly', true)
                ->with(['categories', 'currency']);

            if (isset($validated['start_date'])) {
                $query->where('date', '>=', $validated['start_date']);
            }

            if (isset($validated['end_date'])) {
                $query->where('date', '<=', $validated['end_date']);
            }

            $anomalies = $query->orderBy('date', 'desc')->get();

            return response()->json([
                'status' => 'success',
                'data' => $anomalies,
                'meta' => [
                    'total' => $anomalies->count(),
                    'total_amount' => $anomalies->sum('amount')
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch anomalies: ' . $e->getMessage()
            ], 500);
        }
    }

    // Обновляем метод store - добавляем is_anomaly
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
                'amount' => 'required|numeric|min:0.01',
                'type' => 'required|in:income,expense',
                'category_ids' => 'required|array|min:1',
                'category_ids.*' => 'integer|exists:categories,id',
                'description' => 'nullable|string|max:500',
                'date' => 'required|date',
                'payment_method' => 'required|in:cash,card,transfer',
                'currency_id' => 'required|exists:currencies,id',
                'is_anomaly' => 'nullable|boolean', // НОВОЕ ПОЛЕ (опционально)
            ]);

            // Проверяем, что все категории принадлежат пользователю
            $categoryIds = $validated['category_ids'];
            $validCategoryIds = Category::where('user_id', $userId)
                ->whereIn('id', $categoryIds)
                ->pluck('id')
                ->toArray();

            if (count($validCategoryIds) !== count($categoryIds)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => ['category_ids' => ['Одна или несколько категорий не найдены или не принадлежат вам.']]
                ], 422);
            }

            // Получаем выбранную валюту
            $currency = Currency::find($validated['currency_id']);
            $byn = Currency::where('code', 'BYN')->first();

            // Для BYN пропускаем проверку курса
            if ($currency->code !== 'BYN') {
                $rate = CurrencyRate::where('from_currency_id', $validated['currency_id'])
                    ->where('to_currency_id', $byn->id)
                    ->where('date', $validated['date'])
                    ->first();

                if (!$rate) {
                    $rate = CurrencyRate::where('from_currency_id', $validated['currency_id'])
                        ->where('to_currency_id', $byn->id)
                        ->where('date', '<=', $validated['date'])
                        ->orderBy('date', 'desc')
                        ->first();
                }

                if (!$rate) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Validation failed',
                        'errors' => [
                            'date' => ["На дату {$validated['date']} нет курса для валюты {$currency->code}. Транзакция не может быть создана."]
                        ]
                    ], 422);
                }
            }

            // Создаем транзакцию (добавляем is_anomaly)
            $transaction = Transaction::create([
                'amount' => $validated['amount'],
                'type' => $validated['type'],
                'description' => $validated['description'] ?? null,
                'date' => $validated['date'],
                'payment_method' => $validated['payment_method'],
                'user_id' => $userId,
                'currency_id' => $validated['currency_id'],
                'is_anomaly' => $validated['is_anomaly'] ?? false, // НОВОЕ
            ]);

            // Привязываем категории
            $transaction->categories()->attach($validCategoryIds);

            return response()->json([
                'status' => 'success',
                'message' => 'Transaction created successfully',
                'data' => $transaction->load(['categories', 'currency'])
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
                'message' => 'Failed to create transaction: ' . $e->getMessage()
            ], 500);
        }
    }

    // Обновляем метод update - добавляем возможность обновлять is_anomaly
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

            $transaction = Transaction::where('user_id', $userId)->findOrFail($id);

            $validated = $request->validate([
                'amount' => 'sometimes|required|numeric|min:0.01',
                'type' => 'sometimes|required|in:income,expense',
                'category_ids' => 'sometimes|required|array|min:1',
                'category_ids.*' => 'integer|exists:categories,id',
                'description' => 'nullable|string|max:500',
                'date' => 'sometimes|required|date',
                'payment_method' => 'sometimes|required|in:cash,card,transfer',
                'currency_id' => 'sometimes|required|exists:currencies,id',
                'is_anomaly' => 'nullable|boolean', // НОВОЕ
            ]);

            // Если обновляются категории
            if (isset($validated['category_ids'])) {
                $categoryIds = $validated['category_ids'];
                $validCategoryIds = Category::where('user_id', $userId)
                    ->whereIn('id', $categoryIds)
                    ->pluck('id')
                    ->toArray();

                if (count($validCategoryIds) !== count($categoryIds)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Validation failed',
                        'errors' => ['category_ids' => ['Одна или несколько категорий не найдены или не принадлежат вам.']]
                    ], 422);
                }

                $transaction->categories()->sync($validCategoryIds);
            }

            // Обновляем остальные поля (включая is_anomaly)
            $updateData = collect($validated)->except(['category_ids'])->toArray();
            if (!empty($updateData)) {
                $transaction->update($updateData);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Transaction updated successfully',
                'data' => $transaction->load(['categories', 'currency'])
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
                'message' => 'Transaction not found'
            ], 404);
        }
    }

    // Остальные методы (show, destroy, summary, recent, massDelete, suggestCategory)
    // НЕ ТРЕБУЮТ изменений, так как они работают с конкретными транзакциями
    // или не связаны с фильтрацией по аномалиям

    public function show($id)
    {
        // Без изменений
        try {
            $userId = Auth::id();
            if (!$userId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized'
                ], 401);
            }

            $transaction = Transaction::where('user_id', $userId)
                ->with(['categories', 'currency'])
                ->findOrFail($id);

            return response()->json([
                'status' => 'success',
                'data' => $transaction
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Transaction not found'
            ], 404);
        }
    }

    public function destroy($id)
    {
        // Без изменений
        try {
            $userId = Auth::id();
            if (!$userId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized'
                ], 401);
            }

            $transaction = Transaction::where('user_id', $userId)->findOrFail($id);
            $transaction->categories()->detach();
            $transaction->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Transaction deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Transaction not found'
            ], 404);
        }
    }

    public function summary(Request $request)
    {
        // Можно добавить опциональную фильтрацию по is_anomaly
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
                'year' => 'nullable|integer|min:2000|max:2100',
                'exclude_anomalies' => 'nullable|boolean', // НОВОЕ
            ]);

            $month = $validated['month'] ?? date('m');
            $year = $validated['year'] ?? date('Y');
            $excludeAnomalies = $request->boolean('exclude_anomalies', true); // По умолчанию true

            $incomeQuery = Transaction::where('user_id', $userId)
                ->where('type', 'income')
                ->whereMonth('date', $month)
                ->whereYear('date', $year);

            $expenseQuery = Transaction::where('user_id', $userId)
                ->where('type', 'expense')
                ->whereMonth('date', $month)
                ->whereYear('date', $year);

            if ($excludeAnomalies) {
                $incomeQuery->where('is_anomaly', false);
                $expenseQuery->where('is_anomaly', false);
            }

            $income = $incomeQuery->sum('amount');
            $expenses = $expenseQuery->sum('amount');
            $balance = $income - $expenses;

            return response()->json([
                'status' => 'success',
                'data' => [
                    'income' => (float) $income,
                    'expenses' => (float) $expenses,
                    'balance' => (float) $balance,
                    'period' => [
                        'month' => $month,
                        'year' => $year
                    ],
                    'excluded_anomalies' => $excludeAnomalies
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch transaction summary'
            ], 500);
        }
    }

    public function recent(Request $request)
    {
        // Добавляем опциональную фильтрацию
        try {
            $userId = Auth::id();
            if (!$userId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized'
                ], 401);
            }

            $validated = $request->validate([
                'limit' => 'nullable|integer|min:1|max:1000',
                'include_anomalies' => 'nullable|boolean', // НОВОЕ
            ]);

            $limit = $validated['limit'] ?? 10;
            $includeAnomalies = $request->boolean('include_anomalies', false);

            $query = Transaction::where('user_id', $userId)
                ->with(['categories', 'currency']);

            if (!$includeAnomalies) {
                $query->where('is_anomaly', false);
            }

            $transactions = $query->orderBy('date', 'desc')
                ->orderBy('created_at', 'desc')
                ->take($limit)
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $transactions,
                'meta' => [
                    'include_anomalies' => $includeAnomalies
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch recent transactions'
            ], 500);
        }
    }

    public function massDelete(Request $request)
    {
        // Без изменений
        try {
            $userId = Auth::id();
            if (!$userId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized'
                ], 401);
            }

            $validated = $request->validate([
                'transaction_ids' => 'required|array',
                'transaction_ids.*' => 'integer|exists:transactions,id'
            ]);

            $transactionIds = $validated['transaction_ids'];

            $count = Transaction::where('user_id', $userId)
                ->whereIn('id', $transactionIds)
                ->count();

            if ($count !== count($transactionIds)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Some transactions do not belong to you'
                ], 403);
            }

            foreach ($transactionIds as $id) {
                $transaction = Transaction::find($id);
                if ($transaction) {
                    $transaction->categories()->detach();
                }
            }

            $deleted = Transaction::where('user_id', $userId)
                ->whereIn('id', $transactionIds)
                ->delete();

            return response()->json([
                'status' => 'success',
                'message' => "Deleted {$deleted} transactions",
                'data' => ['deleted_count' => $deleted]
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
                'message' => 'Failed to delete transactions: ' . $e->getMessage()
            ], 500);
        }
    }

    public function suggestCategory(Request $request)
    {
        // Без изменений
        try {
            $userId = Auth::id();
            if (!$userId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized'
                ], 401);
            }

            $validated = $request->validate([
                'description' => 'required|string|min:3',
                'type' => 'nullable|in:income,expense'
            ]);

            $categorizer = new TransactionCategorizer($userId);
            $suggestion = $categorizer->suggest(
                $validated['description'],
                $validated['type'] ?? null
            );

            if ($suggestion) {
                $category = Category::find($suggestion['category_id']);
                if ($category && $category->user_id == $userId) {
                    return response()->json([
                        'status' => 'success',
                        'data' => [
                            'category_id' => $category->id,
                            'category_name' => $category->name,
                            'category_type' => $category->type,
                            'confidence' => $suggestion['confidence']
                        ]
                    ]);
                }
            }

            return response()->json([
                'status' => 'success',
                'data' => null
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to suggest category: ' . $e->getMessage()
            ], 500);
        }
    }
}