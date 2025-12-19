<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Transaction;
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

            // Проверяем уникальность имени для текущего пользователя
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

            // Проверяем уникальность имени для текущего пользователя (исключая текущую категорию)
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

            $month = $request->month ?? now()->month;
            $year = $request->year ?? now()->year;

            $categories = Category::where('user_id', $userId)
                // Общая статистика
                ->withCount(['transactions as transaction_count'])
                ->withSum(['transactions as total_amount'], 'amount')
                // Статистика за текущий месяц
                ->withCount(['transactions as current_month_count' => function($query) use ($month, $year) {
                    $query->whereMonth('date', $month)->whereYear('date', $year);
                }])
                ->withSum(['transactions as current_month_total' => function($query) use ($month, $year) {
                    $query->whereMonth('date', $month)->whereYear('date', $year);
                }], 'amount')
                // Дата последней транзакции
                ->with(['transactions' => function($query) {
                    $query->select('category_id', 'date')
                        ->orderBy('date', 'desc');
                }])
                ->orderBy('type')
                ->orderBy('name')
                ->get()
                ->map(function ($category) {
                    $lastTransaction = $category->transactions->first();

                    return [
                        'id' => $category->id,
                        'name' => $category->name,
                        'type' => $category->type,
                        'color' => $category->color,
                        'budget_limit' => $category->budget_limit,
                        'transaction_count' => $category->transaction_count,
                        'total_amount' => $category->total_amount,
                        'current_month_count' => $category->current_month_count,
                        'current_month_total' => $category->current_month_total,
                        'last_transaction_date' => $lastTransaction->date ?? null,
                        'updated_at' => $category->updated_at->format('Y-m-d H:i:s')
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => $categories
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch categories with transactions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

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

            $month = $request->input('month', now()->month);
            $year = $request->input('year', now()->year);

            $categories = Category::where('user_id', $userId)
                // Общее количество транзакций
                ->withCount(['transactions as transaction_count'])
                // Сумма всех транзакций
                ->withSum(['transactions as total_amount'], 'amount')
                // Количество транзакций за текущий месяц
                ->withCount(['transactions as current_month_count' => function($query) use ($month, $year) {
                    $query->whereMonth('date', $month)
                        ->whereYear('date', $year);
                }])
                // Сумма транзакций за текущий месяц
                ->withSum(['transactions as current_month_total' => function($query) use ($month, $year) {
                    $query->whereMonth('date', $month)
                        ->whereYear('date', $year);
                }], 'amount')
                // Дата последней транзакции
                ->with(['transactions' => function($query) {
                    $query->orderBy('date', 'desc')->limit(1);
                }])
                ->orderBy('type')
                ->orderBy('name')
                ->get()
                ->map(function ($category) {
                    return [
                        'id' => $category->id,
                        'name' => $category->name,
                        'type' => $category->type,
                        'color' => $category->color,
                        'budget_limit' => $category->budget_limit,
                        'transaction_count' => $category->transaction_count,
                        'total_amount' => abs($category->total_amount ?? 0),
                        'current_month_count' => $category->current_month_count,
                        'current_month_total' => abs($category->current_month_total ?? 0),
                        'last_transaction_date' => $category->transactions->first()->date ?? null,
                        'created_at' => $category->created_at,
                        'updated_at' => $category->updated_at,
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => $categories
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch categories with stats',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}
