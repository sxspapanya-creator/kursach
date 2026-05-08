<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

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
                'category_ids.*' => 'integer|exists:categories,id',
                'month' => 'nullable|integer|min:1|max:12',
                'year' => 'nullable|integer|min:2000|max:2100',
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date|after_or_equal:date_from',
                'limit' => 'nullable|integer|min:1|max:10000'
            ]);

            $query = Transaction::where('user_id', $userId)->with('categories');

            // Фильтрация по типу
            if (isset($validated['type'])) {
                $query->where('type', $validated['type']);
            }

            // Фильтрация по категориям (многие ко многим)
            if (isset($validated['category_ids']) && !empty($validated['category_ids'])) {
                $categoryIds = $validated['category_ids'];

                $validCategoryIds = Category::where('user_id', $userId)
                    ->whereIn('id', $categoryIds)
                    ->pluck('id')
                    ->toArray();

                if (!empty($validCategoryIds)) {
                    $query->whereHas('categories', function($q) use ($validCategoryIds) {
                        $q->whereIn('categories.id', $validCategoryIds);
                    });
                }
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

            $transactions = $query->orderBy('date', 'desc')
                ->orderBy('created_at', 'desc')
                ->take($limit)
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $transactions,
                'meta' => [
                    'total' => $transactions->count(),
                    'limit' => $limit
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

            // Создаем транзакцию (без category_id)
            $transaction = Transaction::create([
                'amount' => $validated['amount'],
                'type' => $validated['type'],
                'description' => $validated['description'] ?? null,
                'date' => $validated['date'],
                'payment_method' => $validated['payment_method'],
                'user_id' => $userId,
                'currency_id' => $validated['currency_id'] // ID базовой валюты, можно сделать динамически
            ]);

            // Привязываем категории
            $transaction->categories()->attach($validCategoryIds);

            return response()->json([
                'status' => 'success',
                'message' => 'Transaction created successfully',
                'data' => $transaction->load('categories')
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
                ->with('categories')
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
                'payment_method' => 'sometimes|required|in:cash,card,transfer'
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
                'data' => $transaction->load('categories')
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

            // Категории отвяжутся автоматически (cascade)
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
                ->with('categories')
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
}