<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;

class SocialLoginController extends Controller
{
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();

            // Ищем пользователя по google_id
            $user = User::where('google_id', $googleUser->getId())->first();

            if (!$user) {
                // Ищем по email
                $user = User::where('email', $googleUser->getEmail())->first();

                if (!$user) {
                    // Создаем нового пользователя
                    $user = User::create([
                        'name' => $googleUser->getName(),
                        'email' => $googleUser->getEmail(),
                        'google_id' => $googleUser->getId(),
                        'password' => Hash::make(Str::random(24)), // случайный пароль
                        'email_verified_at' => now(), // Email уже верифицирован Google
                    ]);
                } else {
                    // Обновляем google_id для существующего пользователя
                    $user->update(['google_id' => $googleUser->getId()]);
                }
            }

            // Авторизуем пользователя
            Auth::login($user, true);
            $token = $user->createToken($user->google_id . now());
            $session = request()->session();
            $session->put('token', $token->plainTextToken);

            return redirect()->intended('/');

        } catch (\Exception $e) {
            \Log::error('Google Auth Error: ' . $e->getMessage());
            return redirect('/login')->with('error', 'Ошибка аутентификации через Google');
        }
    }
}