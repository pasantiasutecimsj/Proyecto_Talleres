<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use App\Services\UsuariosApiService;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => function () use ($request) {
                /** Si no tenés token en sesión, no hay user */
                if (!session()->has('usuarios_api.token')) {
                    return ['user' => null, 'roles' => []];
                }

                try {
                    // Trae el usuario desde la API 4010 (Bearer)
                    if (!session()->has('usuarios_api.user')) {
                        // Si no hay user en sesión, lo refrescamos
                        $apiUser = app(UsuariosApiService::class)->me();
                        session(['usuarios_api.user' => $apiUser, 'usuarios_api.user_fresh' => now()->timestamp]);
                        //dd('Entró al IF');
                    } else {
                        // Si ya está en sesión, lo usamos directamente
                        $apiUser = session('usuarios_api.user');
                        //dd('Trajo del SESSION');
                    }

                    // Aplana los roles por nombre (únicos) para el navbar
                    $roles = collect($apiUser['proyectos'] ?? [])
                        ->flatMap(fn($p) => $p['roles'] ?? [])
                        ->unique('id')   // elimina duplicados por id
                        ->values()
                        ->all();

                    return [
                        'user'  => [
                            'id'    => $apiUser['id'] ?? null,
                            'name'  => $apiUser['name'] ?? null,
                            'email' => $apiUser['email'] ?? null,
                        ],
                        // Si querés usar objetos de rol completos, también podés pasarlos;
                        // para el navbar alcanza con nombres.
                        'roles' => $roles,
                    ];
                } catch (\Throwable $e) {
                    // Token inválido/expirado → limpiar sesión para evitar loops
                    session()->forget('usuarios_api.token');
                    return ['user' => null, 'roles' => []];
                }
            },
        ]);
    }
}
