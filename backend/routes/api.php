<?php

use App\Http\Controllers\TransactionController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\AnalyticsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Health check
Route::get('/health-check', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'API is working',
        'timestamp' => now(),
        'version' => '1.0.0'
    ]);
});

// Категории
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/with-transactions', [CategoryController::class, 'withTransactions']);
Route::post('/categories', [CategoryController::class, 'store']);
Route::get('/categories/{id}', [CategoryController::class, 'show']);
Route::put('/categories/{id}', [CategoryController::class, 'update']);
Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

// Транзакции
Route::get('/transactions', [TransactionController::class, 'index']);
Route::get('/transactions/summary', [TransactionController::class, 'summary']);
Route::get('/transactions/recent', [TransactionController::class, 'recent']);
Route::post('/transactions', [TransactionController::class, 'store']);
Route::get('/transactions/{id}', [TransactionController::class, 'show']);
Route::put('/transactions/{id}', [TransactionController::class, 'update']);
Route::delete('/transactions/{id}', [TransactionController::class, 'destroy']);

// Аналитика
Route::get('/analytics/overview', [AnalyticsController::class, 'overview']);
Route::get('/analytics/category-spending', [AnalyticsController::class, 'categorySpending']);
Route::get('/analytics/monthly-trends', [AnalyticsController::class, 'monthlyTrends']);
Route::get('/analytics/category-comparison', [AnalyticsController::class, 'categoryComparison']);
Route::get('/analytics/budget-progress', [AnalyticsController::class, 'budgetProgress']);
Route::get('/analytics/financial-health', [AnalyticsController::class, 'financialHealth']);

// Fallback
Route::fallback(function () {
    return response()->json([
        'status' => 'error',
        'message' => 'API route not found'
    ], 404);
});