<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use App\Services\UsuariosApiService;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => function () {
                // Si no hay token, no hay usuario
                if (!session()->has('usuarios_api.token')) {
                    return ['user' => null, 'roles' => [], 'projectKey' => null];
                }

                try {
                    // Cargar usuario desde sesión o refrescarlo desde la API
                    $apiUser = session('usuarios_api.user');
                    if (!$apiUser) {
                        $apiUser = app(UsuariosApiService::class)->me();
                        session([
                            'usuarios_api.user' => $apiUser,
                            'usuarios_api.user_fresh' => now()->timestamp,
                        ]);
                    }

                    // Clave del proyecto requerido (misma fuente que en tu middleware)
                    $proyectoClave = config('services.usuarios_api.proyecto_clave')
                        ?? env('USUARIOS_API_PROYECTO_CLAVE');

                    // Buscar el proyecto que coincide con la clave
                    $proyecto = null;
                    if ($apiUser && $proyectoClave) {
                        $proyecto = collect($apiUser['proyectos'] ?? [])
                            ->first(fn ($p) => ($p['clave'] ?? null) === $proyectoClave);
                    }

                    // Extraer SOLO los roles de ese proyecto, normalizados a minúsculas
                    $rolesProyecto = collect($proyecto['roles'] ?? [])
                        ->map(function ($r) {
                            if (is_string($r)) return mb_strtolower(trim($r));
                            $k = $r['clave'] ?? $r['nombre'] ?? $r['name'] ?? null;
                            return $k ? mb_strtolower(trim($k)) : null;
                        })
                        ->filter()
                        ->unique()   // unique por valor string
                        ->values()
                        ->all();

                    return [
                        'user' => [
                            'id'    => $apiUser['id'] ?? null,
                            'name'  => $apiUser['name'] ?? null,
                            'email' => $apiUser['email'] ?? null,
                            // Si te sirve en el front, podés enviar el proyecto actual también
                            // 'project' => $proyecto,
                        ],
                        // Ahora SOLO roles del proyecto válido, ya normalizados: ['admin','organizador','docente']
                        'roles'      => $rolesProyecto,
                        'projectKey' => $proyectoClave,
                    ];
                } catch (\Throwable $e) {
                    // Token inválido/expirado → limpiar sesión
                    session()->forget('usuarios_api.token');
                    session()->forget('usuarios_api.user');
                    return ['user' => null, 'roles' => [], 'projectKey' => null];
                }
            },
        ]);
    }
}
