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
            ]);

            // Нельзя валидировать fetch_all правилом boolean: в query приходит строка "true", она не проходит validateBoolean (принимаются только 0/1 и т.д.) → 422.
            $fetchAll = $request->boolean('fetch_all');

            $query = Transaction::where('user_id', $userId)->with(['categories', 'currency']);

            // Фильтрация по типу
            if (isset($validated['type'])) {
                $query->where('type', $validated['type']);
            }

            // Фильтрация по категориям (многие ко многим): транзакция попадает в выборку,
            // если у неё есть хотя бы одна из выбранных категорий (OR).
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
                // Пытаемся найти курс на указанную дату
                $rate = CurrencyRate::where('from_currency_id', $validated['currency_id'])
                    ->where('to_currency_id', $byn->id)
                    ->where('date', $validated['date'])
                    ->first();

                // Если нет курса на точную дату, берем ближайший предыдущий
                if (!$rate) {
                    $rate = CurrencyRate::where('from_currency_id', $validated['currency_id'])
                        ->where('to_currency_id', $byn->id)
                        ->where('date', '<=', $validated['date'])
                        ->orderBy('date', 'desc')
                        ->first();
                }

                // Если всё равно нет курса - возвращаем ошибку
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

            // Создаем транзакцию
            $transaction = Transaction::create([
                'amount' => $validated['amount'],
                'type' => $validated['type'],
                'description' => $validated['description'] ?? null,
                'date' => $validated['date'],
                'payment_method' => $validated['payment_method'],
                'user_id' => $userId,
                'currency_id' => $validated['currency_id']
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
                'currency_id' => 'sometimes|required|exists:currencies,id'
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

                // Синхронизируем категории
                $transaction->categories()->sync($validCategoryIds);
            }

            // Обновляем остальные поля (исключая category_ids)
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

            $month = $validated['month'] ?? date('m');
            $year = $validated['year'] ?? date('Y');

            $income = Transaction::where('user_id', $userId)
                ->where('type', 'income')
                ->whereMonth('date', $month)
                ->whereYear('date', $year)
                ->sum('amount');

            $expenses = Transaction::where('user_id', $userId)
                ->where('type', 'expense')
                ->whereMonth('date', $month)
                ->whereYear('date', $year)
                ->sum('amount');

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
                    ]
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
        try {
            $userId = Auth::id();
            if (!$userId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized'
                ], 401);
            }

            $validated = $request->validate([
                'limit' => 'nullable|integer|min:1|max:1000'
            ]);

            $limit = $validated['limit'] ?? 10;

            $transactions = Transaction::where('user_id', $userId)
                ->with(['categories', 'currency'])
                ->orderBy('date', 'desc')
                ->orderBy('created_at', 'desc')
                ->take($limit)
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $transactions
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

            // Проверяем, что все транзакции принадлежат пользователю
            $count = Transaction::where('user_id', $userId)
                ->whereIn('id', $transactionIds)
                ->count();

            if ($count !== count($transactionIds)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Some transactions do not belong to you'
                ], 403);
            }

            // Удаляем связи с категориями
            foreach ($transactionIds as $id) {
                $transaction = Transaction::find($id);
                if ($transaction) {
                    $transaction->categories()->detach();
                }
            }

            // Удаляем транзакции
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

    /**
     * Предложить категорию для транзакции на основе описания
     */
    public function suggestCategory(Request $request)
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