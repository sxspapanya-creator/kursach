<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

            $query = Transaction::where('user_id', $userId)->with('category');

            // Фильтрация по типу
            if ($request->has('type') && in_array($request->type, ['income', 'expense'])) {
                $query->where('type', $request->type);
            }

            // Фильтрация по категории
            if ($request->has('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            // Фильтрация по месяцу
            if ($request->has('month')) {
                $query->whereMonth('date', $request->month);
            }

            // Фильтрация по году
            if ($request->has('year')) {
                $query->whereYear('date', $request->year);
            }

            // Фильтрация по дате (диапазон)
            if ($request->has('date_from')) {
                $query->where('date', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->where('date', '<=', $request->date_to);
            }

            // Лимит для пагинации
            $limit = $request->get('limit', 50);
            if ($limit > 100) $limit = 100; // Максимальный лимит

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
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch transactions'
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
                'category_id' => 'required|exists:categories,id',
                'description' => 'nullable|string|max:500',
                'date' => 'required|date',
                'payment_method' => 'required|in:cash,card,transfer,other'
            ]);

            // Проверяем, что категория принадлежит текущему пользователю
            $category = Category::where('user_id', $userId)
                ->where('id', $validated['category_id'])
                ->first();
            
            if (!$category) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => ['category_id' => ['Категория не найдена или не принадлежит вам.']]
                ], 422);
            }

            $validated['user_id'] = $userId;
            $transaction = Transaction::create($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Transaction created successfully',
                'data' => $transaction->load('category')
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
                'message' => 'Failed to create transaction'
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
                ->with('category')
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
                'category_id' => 'sometimes|required|exists:categories,id',
                'description' => 'nullable|string|max:500',
                'date' => 'sometimes|required|date',
                'payment_method' => 'sometimes|required|in:cash,card,transfer,other'
            ]);

            // Если изменяется категория, проверяем, что она принадлежит текущему пользователю
            if (isset($validated['category_id'])) {
                $category = Category::where('user_id', $userId)
                    ->where('id', $validated['category_id'])
                    ->first();
                
                if (!$category) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Validation failed',
                        'errors' => ['category_id' => ['Категория не найдена или не принадлежит вам.']]
                    ], 422);
                }
            }

            $transaction->update($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Transaction updated successfully',
                'data' => $transaction->load('category')
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

    // Дополнительные методы для статистики

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

            $month = $request->get('month', date('m'));
            $year = $request->get('year', date('Y'));

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

            $limit = $request->get('limit', 10);

            $transactions = Transaction::where('user_id', $userId)
                ->with('category')
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