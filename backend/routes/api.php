<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\BudgetMethodController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Middleware\UserPermissionMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CurrencyController;

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
Route::get('/categories/with-stats', [CategoryController::class, 'withStats']);
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
Route::post('/transactions/mass-delete', [TransactionController::class, 'massDelete']);
Route::post('/transactions/suggest-category', [TransactionController::class, 'suggestCategory']);

Route::group(['middleware' => [UserPermissionMiddleware::class]], function () {
    // Аналитика
    Route::get('/analytics/overview', [AnalyticsController::class, 'overview']);
    Route::get('/analytics/monthly-trends', [AnalyticsController::class, 'monthlyTrends']);
    Route::get('/analytics/financial-health', [AnalyticsController::class, 'financialHealth']);
    Route::get('/analytics/rule-50-30-20', [AnalyticsController::class, 'rule503020']);
    Route::get('/analytics/rule-60-40', [AnalyticsController::class, 'rule6040']);
    Route::get('/analytics/four-envelopes', [AnalyticsController::class, 'fourEnvelopes']);
    Route::get('/analytics/forecast-metrics', [AnalyticsController::class, 'forecastMetrics']);
});

Route::get('/currencies', [CurrencyController::class, 'index']);
Route::get('/currencies/available-dates', [CurrencyController::class, 'getAvailableDates']);

Route::get('/budget/four-envelopes', [BudgetMethodController::class, 'fourEnvelopes']);
Route::get('/budget/forecast-metrics', [BudgetMethodController::class, 'forecastMetrics']);
Route::get('/plans', [PlanController::class, 'index']);
Route::get('/plans/types', [PlanController::class, 'getPlanTypes']);
Route::post('/plans/set-plan', [PlanController::class, 'setPlanToUser']);

// Методы бюджетирования
Route::get('/budget/rule-50-30-20', [BudgetMethodController::class, 'rule503020']);
Route::get('/budget/rule-60-40', [BudgetMethodController::class, 'rule6040']);
Route::get('/budget/four-envelopes', [BudgetMethodController::class, 'fourEnvelopes']);
Route::get('/budget/forecast-metrics', [BudgetMethodController::class, 'forecastMetrics']);

// Fallback
Route::fallback(function () {
    return response()->json([
        'status' => 'error',
        'message' => 'API route not found'
    ], 404);
});

