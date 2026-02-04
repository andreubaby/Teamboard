<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function csrf(Request $request)
    {
        // Solo para que el front pueda llamar /sanctum/csrf-cookie si quieres
        return response()->noContent();
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales no son correctas.'],
            ]);
        }

        // Muy importante en Sanctum cookie auth
        $request->session()->regenerate();

        return response()->json([
            'user' => $request->user(),
        ]);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function register(Request $request)
    {
        // si ya tienes register hecho, úsalo; si no, lo dejamos para luego
        return response()->json(['message' => 'Not implemented'], 501);
    }

    public function user(Request $request)
    {
        return response()->json(['user' => $request->user()]);
    }
}
