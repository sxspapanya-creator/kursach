<?php

namespace App\Http\Middleware;

use App\Enum\PlanCodeEnum;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class UserPermissionMiddleware
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

        if (!$user->plan || $user->plan->code !== PlanCodeEnum::PREMIUM) {
            return response()->json([
                'error' => 'user need premium plan',
                'status' => 'error'
            ], Response::HTTP_FORBIDDEN);
        }
        return $next($request);
    }
}