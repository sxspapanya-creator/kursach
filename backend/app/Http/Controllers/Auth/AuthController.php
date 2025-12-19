<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email|max:255',
                'password' => 'required|string|min:6|max:255'
            ]);

            $user = User::where('email', $validated['email'])->first();
            if (!$user) {
                return response()->json([
                    'message' => 'User does not exist'
                ], 400);
            }
            
            if (!Hash::check($validated['password'], $user->password)) {
                return response()->json([
                    'message' => 'Wrong password'
                ], 400);
            }

            Auth::login($user, true);
            $tokenName = ($user->google_id ?? $user->email) . '_' . now()->timestamp;
            $token = $user->createToken($tokenName);
            $session = request()->session();
            $session->put('token', $token->plainTextToken);

            return redirect()->intended();
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Login failed'
            ], 500);
        }
    }

    public function register(Request $request) {
        try {
            $validated = $request->validate([
                'name' => 'required|string|min:2|max:255',
                'email' => 'required|email|max:255|unique:users,email',
                'password' => 'required|string|min:6|max:255'
            ]);

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            Auth::login($user, true);
            $tokenName = ($user->google_id ?? $user->email) . '_' . now()->timestamp;
            $token = $user->createToken($tokenName);
            $session = request()->session();
            $session->put('token', $token->plainTextToken);

            return redirect()->intended();
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Registration failed'
            ], 500);
        }
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