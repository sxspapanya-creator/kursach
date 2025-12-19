<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');
        $user = User::where('email', $credentials['email'])->first();
        if (!$user) {
            throw new BadRequestHttpException('User does not exist');
        }
        if (!Hash::check($credentials['password'], $user->password)) {
            throw new BadRequestHttpException('Wrong password');
        }

        Auth::login($user, true);
        $tokenName = ($user->google_id ?? $user->email) . '_' . now()->timestamp;
        $token = $user->createToken($tokenName);
        $session = request()->session();
        $session->put('token', $token->plainTextToken);

        return redirect()->intended();
    }

    public function register(Request $request) {
        $data = $request->only('name', 'email', 'password');
        $user = User::where('email', $data['email'])->first();
        if ($user) {
            throw new BadRequestHttpException('User already exists');
        }
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        Auth::login($user, true);
        $tokenName = ($user->google_id ?? $user->email) . '_' . now()->timestamp;
        $token = $user->createToken($tokenName);
        $session = request()->session();
        $session->put('token', $token->plainTextToken);

        return redirect()->intended();
    }

    public function user(Request $request)
    {
        // Проверяем авторизацию через сессию
        if (Auth::check()) {
            $user = Auth::user();
            return response()->json([
                'authenticated' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ]
            ]);
        }

        // Если нет сессии, проверяем токен из сессии
        $sessionToken = $request->session()->get('token');
        if ($sessionToken) {
            $token = PersonalAccessToken::findToken($sessionToken);
            if ($token && (!$token->expires_at || $token->expires_at->isFuture())) {
                $user = $token->tokenable;
                if ($user) {
                    Auth::login($user);
                    return response()->json([
                        'authenticated' => true,
                        'user' => [
                            'id' => $user->id,
                            'name' => $user->name,
                            'email' => $user->email,
                        ]
                    ]);
                }
            }
        }

        return response()->json([
            'authenticated' => false
        ], 401);
    }

    public function logout(Request $request)
    {
        // Получаем токен из сессии
        $sessionToken = $request->session()->get('token');
        
        // Удаляем токен из базы данных, если он существует
        if ($sessionToken) {
            $token = PersonalAccessToken::findToken($sessionToken);
            if ($token) {
                $token->delete();
            }
        }

        // Получаем ID сессии из кук
        $sessionId = $request->session()->getId();

        // Удаляем сессию из базы данных
        if ($sessionId) {
            DB::table('sessions')
                ->where('id', $sessionId)
                ->delete();
        }

        // Очищаем данные из сессии
        $request->session()->forget('token');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Выходим из системы
        Auth::logout();

        return response()->json([
            'message' => 'Successfully logged out'
        ]);
    }
}