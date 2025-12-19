<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class AuthSessionMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Получаем токен из сессии
        $tokenValue = $request->session()->get('token');

        // Проверяем наличие токена
        if (!$tokenValue) {
            Log::warning('No token found in session', [
                'session_id' => $request->session()->getId(),
                'ip' => $request->ip(),
                'path' => $request->path()
            ]);

            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'No authentication token found'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Ищем токен в базе данных
        $token = PersonalAccessToken::findToken($tokenValue);

        if (!$token) {
            Log::warning('Invalid token found in session', [
                'session_id' => $request->session()->getId(),
                'token' => substr($tokenValue, 0, 20) . '...', // Логируем только часть токена
                'ip' => $request->ip()
            ]);

            // Очищаем невалидный токен из сессии
            $request->session()->forget('token');

            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Invalid authentication token'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Проверяем срок действия токена
        if ($token->expires_at && $token->expires_at->isPast()) {
            Log::warning('Token has expired', [
                'user_id' => $token->tokenable_id,
                'token_id' => $token->id,
                'expired_at' => $token->expires_at
            ]);

            $token->delete();
            $request->session()->forget('token');

            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Token has expired'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Получаем пользователя и аутентифицируем его
        $user = $token->tokenable;

        if (!$user) {
            Log::error('Token exists but user not found', [
                'token_id' => $token->id,
                'tokenable_type' => $token->tokenable_type,
                'tokenable_id' => $token->tokenable_id
            ]);

            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'User not found'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Аутентифицируем пользователя
        Auth::login($user);

        // Обновляем время последнего использования токена
        $token->forceFill([
            'last_used_at' => now()
        ])->save();

        // Добавляем информацию о токене к запросу для удобства
        $request->attributes->set('sanctum_token', $token);

        // Продолжаем выполнение запроса
        return $next($request);
    }
}