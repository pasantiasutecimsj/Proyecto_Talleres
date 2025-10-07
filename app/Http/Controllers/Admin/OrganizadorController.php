<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Models\Organizador;
use App\Models\Taller;
use App\Services\RegistroPersonasService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class OrganizadorController extends Controller
{
    public function __construct(
        protected RegistroPersonasService $personas
    ) {}

    /**
     * GET /admin/organizadores
     */
    public function index(Request $request)
    {
        $busquedaCi = trim((string) $request->input('busqueda', ''));
        $tallerId   = $request->filled('taller') ? (int) $request->input('taller') : null;
        $nombreTerm = trim((string) $request->input('nombre', ''));
        $estado     = $request->input('estado', 'activos'); // activos | inactivos | todos

        // 1) Scope por estado
        $q = match ($estado) {
            'inactivos' => Organizador::soloInactivos(),
            'todos'     => Organizador::conInactivos(),
            default     => Organizador::query(), // activos por scope global
        };

        // 2) Filtros base
        $q->with(['talleres:id,nombre'])
          ->orderBy('ci');

        if ($busquedaCi !== '') {
            $q->where('ci', 'like', "%{$busquedaCi}%");
        }

        if ($tallerId) {
            $q->whereHas('talleres', fn($qq) => $qq->where('taller_id', $tallerId));
        }

        $organizadores = $q->get();

        // 3) Enriquecer con persona (cache)
        $enriquecidos = $organizadores->map(function ($org) {
            $p = $this->personaFromApiCached($org->ci);
            $org->nombre           = $p['nombre']          ?? null;
            $org->apellido         = $p['apellido']        ?? null;
            $org->segundo_nombre   = $p['segundoNombre']   ?? null;
            $org->segundo_apellido = $p['segundoApellido'] ?? null;
            $org->telefono         = $p['telefono']        ?? null;
            return $org;
        });

        // 4) Filtro por nombre (en memoria)
        if ($nombreTerm !== '') {
            $needle = $this->norm($nombreTerm);
            $enriquecidos = $enriquecidos->filter(function ($org) use ($needle) {
                $full1 = trim(implode(' ', array_filter([
                    $org->nombre, $org->segundo_nombre, $org->apellido, $org->segundo_apellido,
                ])));
                $full2 = trim(implode(' ', array_filter([
                    $org->apellido, $org->segundo_apellido, $org->nombre, $org->segundo_nombre,
                ])));
                return str_contains($this->norm($full1), $needle)
                    || str_contains($this->norm($full2), $needle);
            })->values();
        }

        // 5) Catálogo de talleres (SOLO activos para asignar)
        $talleres = Taller::select('id','nombre')->orderBy('nombre')->get();

        return Inertia::render('Admin/Organizadores/Index', [
            'organizadores' => $enriquecidos,
            'talleres'      => $talleres, // ya vienen activos por el scope de Taller
            'filtros'       => [
                'busqueda' => $busquedaCi,
                'taller'   => $tallerId ? (string)$tallerId : '',
                'nombre'   => $nombreTerm,
                'estado'   => $estado,
            ],
        ]);
    }

    public function update(Request $request, Organizador $organizador)
    {
        $data = $request->validate([
            'ci'         => ['required', 'string', 'size:8'],
            'talleres'   => ['nullable', 'array'],
            // existe en talleres y está activo
            'talleres.*' => [
                'integer',
                Rule::exists('talleres', 'id')->where(fn($q) => $q->where('Activo', 1)),
            ],
        ]);

        if ($data['ci'] !== $organizador->ci) {
            return back()->withErrors(['ci' => 'No se puede modificar la CI desde esta pantalla.'])->withInput();
        }

        if ($request->has('talleres')) {
            $organizador->talleres()->sync($request->input('talleres', []));
        }

        return redirect()->route('admin.organizadores.index')
            ->with('success', 'Organizador actualizado.');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'ci'               => ['required', 'string', 'size:8'],
            'nombre'           => ['required', 'string', 'max:255'],
            'apellido'         => ['required', 'string', 'max:255'],
            'segundo_nombre'   => ['nullable', 'string', 'max:255'],
            'segundo_apellido' => ['nullable', 'string', 'max:255'],
            'telefono'         => ['nullable', 'string', 'max:50'],
            'talleres'         => ['nullable', 'array'],
            'talleres.*'       => [
                'integer',
                Rule::exists('talleres', 'id')->where(fn($q) => $q->where('Activo', 1)),
            ],
        ]);

        // 1) Sync en api_personas
        try {
            $payload = [
                'ci'              => $data['ci'],
                'nombre'          => $data['nombre'],
                'apellido'        => $data['apellido'],
                'segundoNombre'   => $data['segundo_nombre']   ?? null,
                'segundoApellido' => $data['segundo_apellido'] ?? null,
                'telefono'        => $data['telefono']         ?? null,
            ];

            $res = $this->personas->updateOrCreatePersona($payload);
            if ($res->failed()) {
                $msg = $res->json('message') ?? 'Error al crear/actualizar persona en Registro de Personas';
                return back()->withErrors(['ci' => $msg])->withInput();
            }
        } catch (\Throwable $e) {
            return back()->withErrors(['ci' => 'No se pudo contactar Registro de Personas'])->withInput();
        }

        // 2) Crear/activar local
        $org = Organizador::conInactivos()->firstOrNew(['ci' => $data['ci']]);
        $org->Activo = true;
        $org->save();

        // 3) Sincronizar talleres
        $org->talleres()->sync($request->input('talleres', []));

        // 4) Invalidar cache
        Cache::forget($this->personaCacheKey($data['ci']));

        return redirect()->route('admin.organizadores.index')
            ->with('success', 'Organizador sincronizado correctamente.');
    }

    /** Borrado lógico */
    public function destroy(Organizador $organizador)
    {
        $organizador->desactivar();
        return redirect()->route('admin.organizadores.index')
            ->with('success', 'Organizador desactivado.');
    }

    /** Restauración */
    public function restore(string $ci)
    {
        $org = Organizador::conInactivos()->findOrFail($ci);
        $org->restaurar();
        return redirect()->route('admin.organizadores.index', ['estado' => 'todos'])
            ->with('success', 'Organizador restaurado.');
    }

    /* ==== Helpers API Personas (igual que tenías) ==== */

    private function personaCacheKey(string $ci): string { return "api_personas:persona:{$ci}"; }

    private function personaFromApi(string $ci): ?array
    {
        try {
            $res = $this->personas->getPersona($ci);
            if ($res->failed()) return null;
            $json = $res->json();
            return $json['persona'] ?? null;
        } catch (\Throwable) { return null; }
    }

    private function personaFromApiCached(string $ci): ?array
    {
        return Cache::remember($this->personaCacheKey($ci), 1800, function () use ($ci) {
            return $this->personaFromApi($ci);
        });
    }

    private function norm(?string $s): string
    {
        if ($s === null) return '';
        return Str::of($s)->lower()->ascii()->trim()->value();
    }

    public function persona(string $ci): JsonResponse
    {
        try {
            $res = $this->personas->getPersona($ci);
            if ($res->failed()) {
                return response()->json(['persona' => null], $res->status());
            }
            return response()->json($res->json(), 200);
        } catch (\Throwable $e) {
            return response()->json(['persona' => null, 'error' => 'No se pudo contactar Registro de Personas'], 500);
        }
    }

    public function existe(string $ci): JsonResponse
    {
        $exists = Organizador::conInactivos()->where('ci', $ci)->exists();
        return response()->json(['existe' => $exists], 200);
    }
}
