<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\UsuariosApiService;

class ApiAuthChecker
{
    public function handle(Request $request, Closure $next)
    {
        if (!session()->has('usuarios_api.token')) {
            return redirect()->route('login'); // tu ruta de login por API
        }

        // Si ya lo pusimos en atributos (otro middleware lo dejó), seguir
        if ($request->attributes->has('api_user')) {
            return $next($request);
        }

        // Tomar de sesión
        $user   = session('usuarios_api.user');
        $fresh  = (int) session('usuarios_api.user_fresh', 0);
        $stale  = now()->timestamp - $fresh > 600; // 10 minutos

        if (!$user || $stale) {
            try {
                $user = app(\App\Services\UsuariosApiService::class)->refreshSessionUser();
            } catch (\Throwable $e) {
                session()->forget(['usuarios_api.token','usuarios_api.user','usuarios_api.user_fresh']);
                return redirect()->route('login');
            }
        }
        
        // Dejarlo disponible para el resto de la request (y para ApiRoleChecker)
        $request->attributes->set('api_user', $user);

        return $next($request);
    }
}