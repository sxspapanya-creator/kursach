<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);
Route::get('/auth/user', [AuthController::class, 'user']);
Route::post('/auth/logout', [AuthController::class, 'logout']);

Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
Route::put('/auth/password', [AuthController::class, 'changePassword']);
Route::post('/auth/verify-email', [AuthController::class, 'verifyEmailChange']);
Route::post('/auth/verify-registration', [AuthController::class, 'verifyRegistration']);
Route::post('/auth/resend-verification', [AuthController::class, 'resendVerificationCode']);
Route::get('/auth/salary-day', [AuthController::class, 'getSalaryDay']);
Route::put('/auth/salary-day', [AuthController::class, 'updateSalaryDay']);
