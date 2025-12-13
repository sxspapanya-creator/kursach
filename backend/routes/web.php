<?php

use App\Http\Controllers\Auth\SocialLoginController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::resource('tasks', TaskController::class);

Route::group(['prefix' => '/'], function ($route) {
    $route->get('/health-check', function (){
        return response()->json(['status' => 'ok']);
    });
});

Route::get('/auth/google', [SocialLoginController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [SocialLoginController::class, 'handleGoogleCallback']);

//// routes/api.php
//Route::get('/health-check', function () {
//    return response()->json(['status' => 'API is working']);
//});
//
//// Транзакции
//Route::apiResource('transactions', TransactionController::class);
//
//// Категории
//Route::apiResource('categories', CategoryController::class);
//
//// Аналитика
////Route::get('/analytics/overview', [AnalyticsController::class, 'overview']);
////Route::get('/analytics/category-spending', [AnalyticsController::class, 'categorySpending']);