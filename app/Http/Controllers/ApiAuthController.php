<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\UsuariosApiService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Throwable;

class ApiAuthController extends Controller
{
    public function login_redirect()
    {
        return Inertia::render('Auth/Login', [
            'error' => session('auth_error')
        ]);
    }

    public function api_login(Request $request, UsuariosApiService $api)
    {
        $data = $request->validate([
            'email'    => ['required','email'],
            'password' => ['required','string'],
        ]);

        try {
            $api->login($data['email'], $data['password'], $request->userAgent() ?: 'frontend-app');
            return redirect()->route('dashboard');
        } catch (Throwable $e) {
            return Inertia::render('Auth/Login', [
                'error' => $e->getMessage() ?: 'Error de login'
            ]);
        }
    }

    public function api_auth_info(UsuariosApiService $api)
    {
        try {
            $user = $api->me();
            return Inertia::render('Auth_Api/auth', ['user' => $api->me()]);
        } catch (Throwable $e) {
            return redirect()->route('auth_api.login')->with('auth_error', 'No autenticado');
        }
    }

    public function api_logout(UsuariosApiService $api)
    {
        $api->logout();
        return redirect()->route('dashboard');
    }
}
