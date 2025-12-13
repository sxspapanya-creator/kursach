<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class AuthSessionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->session()->get("token");
        $token = PersonalAccessToken::findToken($token);
        if (!$token) {
            return response()->json(
                'Unauthorized',
                Response::HTTP_UNAUTHORIZED
            );
        }

        return $next($request);
    }
}
