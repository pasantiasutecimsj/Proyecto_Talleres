<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Docente;
use App\Models\Clase;
use App\Models\Taller;
use App\Services\RegistroPersonasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class DocenteController extends Controller
{
    public function __construct(
        protected RegistroPersonasService $personas
    ) {}

    /**
     * GET /admin/docentes
     * Filtros: CI (busqueda), nombre (API), taller (con clases), estado (activos|inactivos|todos)
     */
    public function index(Request $request)
    {
        $busquedaCi = trim((string) $request->input('busqueda', ''));
        $nombreTerm = trim((string) $request->input('nombre', ''));
        $tallerId   = $request->filled('taller') ? (int) $request->input('taller') : null;
        $estado     = $request->input('estado', 'activos'); // activos|inactivos|todos

        // 1) Scope por estado
        $q = match ($estado) {
            'inactivos' => Docente::soloInactivos(),
            'todos'     => Docente::conInactivos(),
            default     => Docente::query(), // activos por scope global
        };

        // 2) Filtros base
        $q->orderBy('ci');

        if ($busquedaCi !== '') {
            $q->where('ci', 'like', "%{$busquedaCi}%");
        }

        // Solo docentes que tengan al menos 1 clase en el taller dado
        if ($tallerId) {
            $q->whereExists(function ($sub) use ($tallerId) {
                $sub->select(DB::raw(1))
                    ->from('clases')
                    ->whereColumn('clases.ci_docente', 'docentes.ci')
                    ->where('clases.taller_id', $tallerId);
            });
        }

        $docentes = $q->get();

        // 3) Enriquecer con persona (cache 30')
        $enriquecidos = $docentes->map(function ($doc) {
            $p = $this->personaFromApiCached($doc->ci);
            $doc->nombre           = $p['nombre']           ?? null;
            $doc->apellido         = $p['apellido']         ?? null;
            $doc->segundo_nombre   = $p['segundoNombre']    ?? null;
            $doc->segundo_apellido = $p['segundoApellido']  ?? null;
            $doc->telefono         = $p['telefono']         ?? null;
            return $doc;
        });

        // 4) Filtro por nombre (en memoria con datos API)
        if ($nombreTerm !== '') {
            $needle = $this->norm($nombreTerm);

            $enriquecidos = $enriquecidos->filter(function ($doc) use ($needle) {
                $full1 = trim(implode(' ', array_filter([
                    $doc->nombre, $doc->segundo_nombre, $doc->apellido, $doc->segundo_apellido,
                ])));
                $full2 = trim(implode(' ', array_filter([
                    $doc->apellido, $doc->segundo_apellido, $doc->nombre, $doc->segundo_nombre,
                ])));
                return str_contains($this->norm($full1), $needle)
                    || str_contains($this->norm($full2), $needle);
            })->values();
        }

        // 5) Talleres en los que dictaron (distinct por docente)
        $cis = $enriquecidos->pluck('ci')->all();

        $talleresPorDocente = collect();
        if (!empty($cis)) {
            $talleresPorDocente = Clase::query()
                ->whereIn('ci_docente', $cis)
                ->join('talleres', 'clases.taller_id', '=', 'talleres.id')
                ->select('clases.ci_docente', 'talleres.id as id', 'talleres.nombre as nombre')
                ->distinct()
                ->get()
                ->groupBy('ci_docente')
                ->map(fn($rows) => $rows->map(fn($r) => ['id' => (int)$r->id, 'nombre' => $r->nombre])->values()->all());
        }

        $enriquecidos = $enriquecidos->map(function ($doc) use ($talleresPorDocente) {
            $doc->talleres_dicta = $talleresPorDocente->get($doc->ci, []);
            return $doc;
        });

        // 6) Catálogo de talleres (solo con clases cargadas, para filtros)
        $talleres = Taller::select('id', 'nombre')
            ->whereIn('id', Clase::query()->distinct()->pluck('taller_id'))
            ->orderBy('nombre')
            ->get();

        return Inertia::render('Admin/Docentes/Index', [
            'docentes' => $enriquecidos,
            'talleres' => $talleres,
            'filtros'  => [
                'busqueda' => $busquedaCi,
                'nombre'   => $nombreTerm,
                'taller'   => $tallerId ? (string)$tallerId : '',
                'estado'   => $estado,
            ],
        ]);
    }

    /**
     * POST /admin/docentes
     * - updateOrCreatePersona en API
     * - crear/activar local (Activo=1)
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'ci'               => ['required', 'string', 'size:8'],
            'nombre'           => ['required', 'string', 'max:255'],
            'apellido'         => ['required', 'string', 'max:255'],
            'segundo_nombre'   => ['nullable', 'string', 'max:255'],
            'segundo_apellido' => ['nullable', 'string', 'max:255'],
            'telefono'         => ['nullable', 'string', 'max:50'],
        ]);

        // 1) Sync con Registro de Personas
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
        } catch (\Throwable) {
            return back()->withErrors(['ci' => 'No se pudo contactar Registro de Personas'])->withInput();
        }

        // 2) Crear/activar local (si existe inactivo, restaurar)
        $doc = Docente::conInactivos()->where('ci', $data['ci'])->first();
        if ($doc) {
            $doc->restaurar();
        } else {
            Docente::create(['ci' => $data['ci'], 'Activo' => 1]);
        }

        // 3) Invalidar cache
        Cache::forget($this->personaCacheKey($data['ci']));

        return redirect()
            ->route('admin.docentes.index')
            ->with('success', 'Docente sincronizado correctamente.');
    }

    /** DELETE /admin/docentes/{docente} -> borrado lógico */
    public function destroy(Docente $docente)
    {
        $docente->desactivar();
        return redirect()
            ->route('admin.docentes.index')
            ->with('success', 'Docente desactivado.');
    }

    /** PATCH /admin/docentes/{ci}/restore -> restauración */
    public function restore(string $ci)
    {
        $doc = Docente::conInactivos()->findOrFail($ci);
        $doc->restaurar();

        return redirect()
            ->route('admin.docentes.index', ['estado' => 'todos'])
            ->with('success', 'Docente restaurado.');
    }

    /* ============================
       Endpoints auxiliares (modal)
       ============================ */

    public function persona(string $ci): JsonResponse
    {
        try {
            $res = $this->personas->getPersona($ci);
            if ($res->failed()) {
                return response()->json(['persona' => null], $res->status());
            }
            return response()->json($res->json(), 200);
        } catch (\Throwable) {
            return response()->json(['persona' => null, 'error' => 'No se pudo contactar Registro de Personas'], 500);
        }
    }

    public function existe(string $ci): JsonResponse
    {
        // Contar existencia cualquiera sea el estado (igual que en Organizador)
        $exists = Docente::conInactivos()->where('ci', $ci)->exists();
        return response()->json(['existe' => $exists], 200);
    }

    /** GET /admin/docentes/buscar?q=...  ->  [{ci,nombre,apellido}] */
    public function buscar(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json([], 200);
        }

        $needle = $this->norm($q);

        // Traemos CIs de docentes activos por defecto
        $cis = Docente::query()->orderBy('ci')->pluck('ci');

        $found = [];
        foreach ($cis as $ci) {
            $persona = $this->personaFromApiCached($ci);

            $nombre   = $persona['nombre']          ?? '';
            $apellido = $persona['apellido']        ?? '';
            $segNom   = $persona['segundoNombre']   ?? '';
            $segApe   = $persona['segundoApellido'] ?? '';

            $full1 = trim("{$nombre} {$segNom} {$apellido} {$segApe}");
            $full2 = trim("{$apellido} {$segApe} {$nombre} {$segNom}");

            $matches =
                str_contains($this->norm($ci), $needle) ||
                str_contains($this->norm($full1), $needle) ||
                str_contains($this->norm($full2), $needle);

            if ($matches) {
                $found[] = [
                    'ci'       => (string) $ci,
                    'nombre'   => $nombre ?: null,
                    'apellido' => $apellido ?: null,
                ];
            }

            if (count($found) >= 30) break;
        }

        return response()->json($found, 200);
    }

    /**
     * GET /admin/docentes/top?taller_id=ID[&limit=20]
     * Devuelve docentes ordenados por cantidad de clases dictadas en el taller dado.
     */
    public function top(Request $request)
    {
        $data = $request->validate([
            'taller_id' => ['required', 'integer', 'exists:talleres,id'],
            'limit'     => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $tallerId = (int) $data['taller_id'];
        $limit    = (int) ($data['limit'] ?? 20);

        $rows = Clase::query()
            ->select('ci_docente', DB::raw('COUNT(*) as clases_count'))
            ->where('taller_id', $tallerId)
            ->whereNotNull('ci_docente')
            ->groupBy('ci_docente')
            ->orderByDesc('clases_count')
            ->limit($limit)
            ->get();

        $result = $rows->map(function ($r) {
            $ci = (string) $r->ci_docente;
            $p  = $this->personaFromApiCached($ci);

            $nombreCompleto = trim(implode(' ', array_filter([
                $p['nombre']   ?? null,
                $p['apellido'] ?? null,
            ])));

            return [
                'ci'           => $ci,
                'nombre'       => $nombreCompleto !== '' ? $nombreCompleto : null,
                'clases_count' => (int) $r->clases_count,
            ];
        })->values();

        return response()->json($result, 200);
    }

    /* ============================ Helpers ============================ */

    private function personaCacheKey(string $ci): string
    {
        return "api_personas:persona:{$ci}";
    }

    private function personaFromApi(string $ci): ?array
    {
        try {
            $res = $this->personas->getPersona($ci);
            if ($res->failed()) return null;
            return $res->json('persona') ?? null;
        } catch (\Throwable) {
            return null;
        }
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
}
