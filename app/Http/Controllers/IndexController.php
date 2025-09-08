<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Services\UsuariosApiService;
use App\Services\RegistroPersonasService;
use App\Models\UserComedor;
use App\Models\stockModel;
use App\Models\stockUsadoModel;
use App\Models\stockComedorModel;

class IndexController extends Controller
{
    public function __construct(private RegistroPersonasService $registroPersonasService)
    {
    }

    public function index(Request $request, UsuariosApiService $api)
    {
        // 1) Tomar el usuario que injecta el middleware api.auth
        $apiUser = $request->attributes->get('api_user');
        if (!$apiUser) {
            // si alguien llegó sin pasar por api.auth, redirigí a login
            return redirect()->route('auth_api.login')->withErrors(['api' => 'No autenticado']);
        }

        $userId = (int)($apiUser['id'] ?? 0);

        // 2) Aplanar roles desde proyectos[*].roles[*].clave (igual que ApiRoleChecker)
        $roleKeys = collect($apiUser['proyectos'] ?? [])
            ->flatMap(fn ($p) => collect($p['roles'] ?? [])->pluck('clave'))
            ->filter()
            ->map(fn ($k) => strtolower($k))
            ->unique()
            ->values();

        $isAdmin     = $roleKeys->contains('admin');
        $isEncargado = $roleKeys->contains('encargado');
        $isCocinero  = $roleKeys->contains('cocinero');

        return Inertia::render('Dashboard', [
        ]);
    }
}
