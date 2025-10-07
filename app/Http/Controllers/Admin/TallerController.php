<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Taller;
use App\Services\RegistroPersonasService;
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
        $term    = trim((string) $request->input('nombre', '')); // busca en nombre + descripcion
        $ciudad  = $request->input('ciudad');                    // id_ciudad
        $estado  = $request->input('estado', 'activos');         // activos | inactivos | todos

        // Query base: por defecto con scope global (activos)
        $q = match ($estado) {
            'inactivos' => Taller::soloInactivos(),
            'todos'     => Taller::conInactivos(),
            default     => Taller::query(), // activos (scope global)
        };

        if ($ciudad !== null && $ciudad !== '') {
            $q->where('id_ciudad', $ciudad);
        }

        if ($term !== '') {
            $q->where(function ($sub) use ($term) {
                $termLike = "%{$term}%";
                $sub->where('nombre', 'like', $termLike)
                    ->orWhere('descripcion', 'like', $termLike);
            });
        }

        $talleres = $q->orderBy('id_ciudad')->orderBy('nombre')->get();

        // Catálogo de ciudades desde api_personas (por ahora)
        $ciudadesResp = $this->registroPersonasService->getCiudades()->json();
        $ciudades = collect($ciudadesResp['ciudades'] ?? []);

        // Enriquecer con nombre de ciudad
        $talleresConCiudad = $talleres->map(function ($t) use ($ciudades) {
            $ciudad = $ciudades->firstWhere('id', $t->id_ciudad);
            $t->ciudad = $ciudad['nombre'] ?? null;
            return $t;
        })->values();

        return Inertia::render('Admin/Talleres/Index', [
            'talleres' => $talleresConCiudad,
            'ciudades' => $ciudades->sortBy('nombre')->values()->all(),
            'filtros'  => [
                'nombre' => $term,
                'ciudad' => $ciudad ?? '',
                'estado' => $estado,
            ],
        ]);
    }

    /**
     * POST /admin/talleres
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre'      => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'id_ciudad'   => ['required'],
            'calle'       => ['nullable', 'string', 'max:255'],
            'numero'      => ['nullable', 'string', 'max:50'],
        ]);

        // Activo true por defecto (lo pone la migración); si quisieras, $data['Activo'] = true;
        Taller::create($data);

        return redirect()->route('admin.talleres.index')
            ->with('success', 'Taller creado exitosamente.');
    }

    /**
     * PUT/PATCH /admin/talleres/{taller}
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

        return redirect()->route('admin.talleres.index')
            ->with('success', 'Taller actualizado exitosamente.');
    }

    /**
     * DELETE /admin/talleres/{taller}
     * → Borrado lógico (Activo = 0)
     */
    public function destroy(Taller $taller)
    {
        $taller->desactivar();

        return redirect()->route('admin.talleres.index')
            ->with('success', 'Taller desactivado.');
    }

    /**
     * PATCH /admin/talleres/{taller}/restore
     * → Restaurar (Activo = 1)
     */
    public function restore($id)
    {
        // Necesitamos ver incluso si está inactivo
        $taller = Taller::conInactivos()->findOrFail($id);
        $taller->restaurar();

        return redirect()->route('admin.talleres.index', ['estado' => 'todos'])
            ->with('success', 'Taller restaurado.');
    }
}
