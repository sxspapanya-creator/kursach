<?php

namespace App\Http\Controllers;

use App\Services\Category\CategoryService;
use App\Services\Category\CategoryStatsService;
use App\Services\Category\CategoryValidationService;
use App\Services\Analytics\CurrencyConverterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CategoryController extends Controller
{
    private CategoryService $categoryService;
    private CategoryStatsService $categoryStatsService;
    private CategoryValidationService $validationService;

    public function __construct()
    {
        $currencyConverter = new CurrencyConverterService();
        $this->categoryService = new CategoryService($currencyConverter);
        $this->categoryStatsService = new CategoryStatsService($currencyConverter);
        $this->validationService = new CategoryValidationService();
    }

    protected function getUserId()
    {
        $userId = Auth::id();
        if (!$userId) abort(401, 'Unauthorized');
        return $userId;
    }

    public function index()
    {
        try {
            $categories = $this->categoryService->getAllCategories($this->getUserId());

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
            $userId = $this->getUserId();
            $validated = $this->validationService->validateStore($request->all(), $userId);

            $category = $this->categoryService->createCategory($userId, $validated);

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
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => ['name' => [$e->getMessage()]]
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
            $category = $this->categoryService->getCategory($this->getUserId(), $id);

            if (!$category) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Category not found'
                ], 404);
            }

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
            $userId = $this->getUserId();
            $category = $this->categoryService->getCategory($userId, $id);

            if (!$category) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Category not found'
                ], 404);
            }

            $validated = $this->validationService->validateUpdate($request->all(), $userId, $id);
            $category = $this->categoryService->updateCategory($category, $validated);

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
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => ['name' => [$e->getMessage()]]
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
            $userId = $this->getUserId();
            $category = $this->categoryService->getCategory($userId, $id);

            if (!$category) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Category not found'
                ], 404);
            }

            $this->categoryService->deleteCategory($category);

            return response()->json([
                'status' => 'success',
                'message' => 'Category deleted successfully'
            ]);

        } catch (\RuntimeException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 422);
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
            $validated = $request->validate([
                'month' => 'nullable|integer|min:1|max:12',
                'year' => 'nullable|integer|min:2000|max:2100'
            ]);

            $month = $validated['month'] ?? now()->month;
            $year = $validated['year'] ?? now()->year;

            $result = $this->categoryStatsService->getCategoriesWithTransactions(
                $this->getUserId(),
                $month,
                $year
            );

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

    public function withStats(Request $request)
    {
        try {
            $validated = $request->validate([
                'month' => 'nullable|integer|min:1|max:12',
                'year' => 'nullable|integer|min:2000|max:2100'
            ]);

            $month = $validated['month'] ?? now()->month;
            $year = $validated['year'] ?? now()->year;

            $result = $this->categoryStatsService->getCategoriesWithFullStats(
                $this->getUserId(),
                $month,
                $year
            );

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