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
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    public function login(Request $request)
    {
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
    }

    public function register(Request $request)
    {
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
                'email_verified_at' => null
            ]);

            // Генерируем код подтверждения
            $verificationCode = rand(100000, 999999);

            session()->put('email_verification', [
                'code' => $verificationCode,
                'user_id' => $user->id
            ]);

            // Отправляем код на почту
            try {
                Mail::raw("Ваш код подтверждения: {$verificationCode}\n\nКод действителен 5 минут.\n\nЕсли вы не регистрировались, проигнорируйте это сообщение.", function ($message) use ($validated) {
                    $message->to($validated['email'])
                        ->subject('Подтверждение регистрации в Finance App');
                });

                return response()->json([
                    'status' => 'needs_verification',
                    'message' => 'Код подтверждения отправлен на почту',
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'name' => $user->name
                ]);
            } catch (\Exception $mailError) {
                // Если письмо не отправилось - удаляем пользователя
                $user->delete();
                \Log::error('Mail sending failed: ' . $mailError->getMessage());
                return response()->json([
                    'status' => 'error',
                    'message' => 'Не удалось отправить код подтверждения. Попробуйте позже.'
                ], 500);
            }

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Registration failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function verifyRegistration(Request $request)
    {
        try {
            $validated = $request->validate([
                'code' => 'required|string|size:6',
                'user_id' => 'required|exists:users,id'
            ]);

            $pendingVerification = session()->get('email_verification');

            if (!$pendingVerification || $pendingVerification['user_id'] != $validated['user_id']) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Нет запроса на подтверждение'
                ], 400);
            }

            if ($validated['code'] != $pendingVerification['code']) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Неверный код подтверждения'
                ], 422);
            }

            $user = User::find($validated['user_id']);
            $user->update([
                'email_verified_at' => now()
            ]);

            session()->forget('email_verification');

            // Автоматически входим
            Auth::login($user, true);
            $tokenName = ($user->google_id ?? $user->email) . '_' . now()->timestamp;
            $token = $user->createToken($tokenName);
            $session = request()->session();
            $session->put('token', $token->plainTextToken);

            return response()->json([
                'status' => 'success',
                'message' => 'Email подтвержден, вход выполнен',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Verification failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function resendVerificationCode(Request $request)
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
                'email' => 'required|email'
            ]);

            $verificationCode = rand(100000, 999999);

            session()->put('email_verification', [
                'code' => $verificationCode,
                'user_id' => $validated['user_id']
            ]);

            Mail::raw("Ваш код подтверждения: {$verificationCode}\n\nКод действителен 5 минут.", function ($message) use ($validated) {
                $message->to($validated['email'])
                    ->subject('Подтверждение регистрации в Finance App');
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Код отправлен повторно'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to resend code: ' . $e->getMessage()
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

    // ========== МЕТОДЫ ПРОФИЛЯ ==========

    /**
     * Обновление профиля пользователя (имя и email)
     */
    public function updateProfile(Request $request)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not authenticated'
                ], 401);
            }

            $validated = $request->validate([
                'name' => 'required|string|min:2|max:255',
                'email' => 'required|email|max:255|unique:users,email,' . $user->id
            ]);

            // Если email меняется - отправляем код подтверждения
            $emailChanged = $validated['email'] !== $user->email;

            if ($emailChanged) {
                $verificationCode = rand(100000, 999999);

                session()->put('pending_email_verification', [
                    'code' => $verificationCode,
                    'email' => $validated['email'],
                    'name' => $validated['name']
                ]);

                // Отправляем письмо через Mailtrap
                try {
                    Mail::raw("Ваш код подтверждения: {$verificationCode}\n\nКод действителен 5 минут.\n\nЕсли вы не запрашивали смену email, проигнорируйте это сообщение.", function ($message) use ($validated) {
                        $message->to($validated['email'])
                            ->subject('Подтверждение смены email в Finance App');
                    });

                    return response()->json([
                        'status' => 'needs_verification',
                        'message' => 'Код подтверждения отправлен на почту',
                        'email' => $validated['email']
                    ]);
                } catch (\Exception $mailError) {
                    \Log::error('Mail sending failed: ' . $mailError->getMessage());
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Не удалось отправить код на почту. Проверьте настройки почты.'
                    ], 500);
                }
            }

            // Если email не меняется - просто обновляем имя
            $user->update([
                'name' => $validated['name']
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Профиль обновлен',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update profile: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Подтверждение смены email
     */
    public function verifyEmailChange(Request $request)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not authenticated'
                ], 401);
            }

            $pendingVerification = session()->get('pending_email_verification');

            if (!$pendingVerification) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Нет запроса на смену email'
                ], 400);
            }

            $validated = $request->validate([
                'code' => 'required|string|size:6'
            ]);

            if ($validated['code'] != $pendingVerification['code']) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Неверный код подтверждения'
                ], 422);
            }

            // Обновляем email и имя
            $user->update([
                'name' => $pendingVerification['name'],
                'email' => $pendingVerification['email']
            ]);

            // Очищаем сессию
            session()->forget('pending_email_verification');

            return response()->json([
                'status' => 'success',
                'message' => 'Email успешно изменен',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Verification failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Смена пароля
     */
    public function changePassword(Request $request)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not authenticated'
                ], 401);
            }

            $validated = $request->validate([
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:6|confirmed'
            ]);

            // Проверяем текущий пароль
            if (!Hash::check($validated['current_password'], $user->password)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Current password is incorrect',
                    'errors' => ['current_password' => ['Неверный текущий пароль']]
                ], 422);
            }

            // Обновляем пароль
            $user->update([
                'password' => Hash::make($validated['new_password'])
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Password changed successfully'
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to change password: ' . $e->getMessage()
            ], 500);
        }
    }
}