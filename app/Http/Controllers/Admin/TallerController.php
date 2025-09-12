<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Taller;
use App\Services\RegistroPersonasService; // ⬅️ ojo con el namespace (en tu proyecto está como App\Services)
use Illuminate\Http\Request;
use Inertia\Inertia;

class TallerController extends Controller
{
    public function __construct(
        protected RegistroPersonasService $registroPersonasService
    ) {}

    /**
     * GET /admin/talleres
     * Listado de talleres + ciudades para el select.
     */
    public function index(Request $request)
    {
        // --- 1) Leer filtros del request ---
        $term    = trim((string) $request->input('nombre', ''));   // busca en nombre + descripcion
        $ciudad  = $request->input('ciudad');                      // id_ciudad

        // --- 2) Query con filtros ---
        $q = Taller::query();

        if ($ciudad !== null && $ciudad !== '') {
            $q->where('id_ciudad', $ciudad);
        }

        if ($term !== '') {
            $q->where(function ($sub) use ($term) {
                $sub->where('nombre', 'like', "%{$term}%")
                    ->orWhere('descripcion', 'like', "%{$term}%");
            });
        }

        $talleres = $q->orderBy('id_ciudad')->orderBy('nombre')->get();

        // --- 3) Catálogo de ciudades desde api_personas ---
        $ciudadesResp = $this->registroPersonasService->getCiudades()->json();
        $ciudades = collect($ciudadesResp['ciudades'] ?? []);

        // --- 4) Enriquecer con nombre de ciudad ---
        $talleresConCiudad = $talleres->map(function ($t) use ($ciudades) {
            $ciudad = $ciudades->firstWhere('id', $t->id_ciudad);
            $t->ciudad = $ciudad['nombre'] ?? null;
            return $t;
        })->values();

        // --- 5) Retornar a la vista con filtros para hidratar el modal/banner ---
        return Inertia::render('Admin/Talleres/Index', [
            'talleres' => $talleresConCiudad,
            'ciudades' => $ciudades->sortBy('nombre')->values()->all(),
            'filtros'  => [
                'nombre' => $term,
                'ciudad' => $ciudad ?? '',
            ],
        ]);
    }


    /**
     * POST /admin/talleres
     * Crear taller (usado por el modal "Nuevo Taller").
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre'      => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'id_ciudad'   => ['required'], // validación contra API la hacemos después si querés
            'calle'       => ['nullable', 'string', 'max:255'],
            'numero'      => ['nullable', 'string', 'max:50'],
        ]);

        Taller::create($data);

        return redirect()
            ->route('admin.talleres.index')
            ->with('success', 'Taller creado exitosamente.');
    }

    /**
     * PUT/PATCH /admin/talleres/{taller}
     * Actualizar taller (usado por el modal "Editar Taller").
     */
    public function update(Request $request, Taller $taller)
    {
        $data = $request->validate([
            'nombre'      => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'id_ciudad'   => ['required'],
            'calle'       => ['nullable', 'string', 'max:255'],
            'numero'      => ['nullable', 'string', 'max:50'],
        ]);

        $taller->update($data);

        return redirect()
            ->route('admin.talleres.index')
            ->with('success', 'Taller actualizado exitosamente.');
    }
}
