<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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
        $token = $user->createToken($user->google_id . now());
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
        $token = $user->createToken($user->google_id . now());
        $session = request()->session();
        $session->put('token', $token->plainTextToken);

        return redirect()->intended();
    }
}