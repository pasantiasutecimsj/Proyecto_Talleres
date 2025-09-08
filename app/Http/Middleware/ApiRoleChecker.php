<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ApiRoleChecker
{
    public function handle(Request $request, Closure $next, ...$rolesRequeridos)
    {
        $apiUser = session('usuarios_api.user');

        if (!$apiUser) {
            // Si alguien puso 'rol' sin 'api.auth' antes, forzamos auth primero
            return redirect()->route('auth_api.login');
        }

        // 1) Obtener la clave de proyecto desde config o env
        $proyectoClave = config('services.usuarios_api.proyecto_clave')
            ?? env('USUARIOS_API_PROYECTO_CLAVE');

        if (!$proyectoClave) {
            abort(500, 'Falta configurar USUARIOS_API_PROYECTO_CLAVE.');
        }

        // 2) Ubicar el proyecto del usuario que coincide con la clave
        $proyecto = collect($apiUser['proyectos'] ?? [])
            ->first(fn ($p) => ($p['clave'] ?? null) === $proyectoClave);

        if (!$proyecto) {
            // Está autenticado, pero no tiene el proyecto requerido
            abort(403, 'No tienes acceso a esta aplicación.');
        }

        // 3) Extraer roles SOLO de ese proyecto (normalizando a minúsculas)
        $rolesUsuario = collect($proyecto['roles'] ?? [])
            ->map(function ($r) {
                if (is_string($r)) return mb_strtolower($r);
                $k = $r['clave'] ?? $r['nombre'] ?? $r['name'] ?? null;
                return $k ? mb_strtolower($k) : null;
            })
            ->filter()
            ->unique()
            ->values();

        // 4) Si la ruta no pide roles específicos, alcanza con pertenecer al proyecto
        if (empty($rolesRequeridos)) {
            return $next($request);
        }

        // 5) Normalizar requeridos y chequear intersección
        $requeridos = collect($rolesRequeridos)->map(fn ($r) => mb_strtolower($r));
        $ok = $rolesUsuario->intersect($requeridos)->isNotEmpty();

        if (!$ok) {
            abort(403, 'No tiene permisos para esta acción.');
        }

        return $next($request);
    }
}
