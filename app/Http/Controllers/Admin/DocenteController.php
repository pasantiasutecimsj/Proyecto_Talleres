<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Docente;
use App\Models\Clase;                     // 👈 NUEVO
use App\Services\RegistroPersonasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;        // 👈 NUEVO
use Illuminate\Support\Str;
use Inertia\Inertia;

class DocenteController extends Controller
{
    public function __construct(
        protected RegistroPersonasService $personas
    ) {}

    /**
     * GET /admin/docentes
     * Listado con filtros: CI (busqueda) y Nombre/Apellido (vía api_personas).
     */
    public function index(Request $request)
    {
        $busquedaCi = trim((string) $request->input('busqueda', '')); // por CI
        $nombreTerm = trim((string) $request->input('nombre', ''));   // por nombre/apellido (API)
        $tallerId   = $request->filled('taller') ? (int) $request->input('taller') : null; // 👈 NUEVO

        // 1) Filtro local por CI
        $q = \App\Models\Docente::query()->orderBy('ci');

        if ($busquedaCi !== '') {
            $q->where('ci', 'like', "%{$busquedaCi}%");
        }

        // 👉 Filtro por taller: solo docentes que tengan al menos 1 clase en ese taller
        if ($tallerId) {
            $q->whereExists(function ($sub) use ($tallerId) {
                $sub->select(DB::raw(1))
                    ->from('clases')
                    ->whereColumn('clases.ci_docente', 'docentes.ci')
                    ->where('clases.taller_id', $tallerId);
            });
        }

        $docentes = $q->get();

        // 2) Enriquecer con persona (cache 30 min)
        $enriquecidos = $docentes->map(function ($doc) {
            $p = $this->personaFromApiCached($doc->ci);
            $doc->nombre           = $p['nombre']           ?? null;
            $doc->apellido         = $p['apellido']         ?? null;
            $doc->segundo_nombre   = $p['segundoNombre']    ?? null;
            $doc->segundo_apellido = $p['segundoApellido']  ?? null;
            $doc->telefono         = $p['telefono']         ?? null;
            return $doc;
        });

        // 3) Filtro por nombre/apellido (en memoria usando datos de la API)
        if ($nombreTerm !== '') {
            $needle = $this->norm($nombreTerm);

            $enriquecidos = $enriquecidos->filter(function ($doc) use ($needle) {
                $full1 = trim(implode(' ', array_filter([
                    $doc->nombre,
                    $doc->segundo_nombre,
                    $doc->apellido,
                    $doc->segundo_apellido,
                ])));

                $full2 = trim(implode(' ', array_filter([
                    $doc->apellido,
                    $doc->segundo_apellido,
                    $doc->nombre,
                    $doc->segundo_nombre,
                ])));

                return str_contains($this->norm($full1), $needle)
                    || str_contains($this->norm($full2), $needle);
            })->values();
        }

        // 4) Talleres en los que dictaron (distinct por docente)
        $cis = $enriquecidos->pluck('ci')->all();

        $talleresPorDocente = collect();
        if (!empty($cis)) {
            $talleresPorDocente = \App\Models\Clase::query()
                ->whereIn('ci_docente', $cis)
                ->join('talleres', 'clases.taller_id', '=', 'talleres.id')
                ->select('clases.ci_docente', 'talleres.id as id', 'talleres.nombre as nombre')
                ->distinct()
                ->get()
                ->groupBy('ci_docente')
                ->map(function ($rows) {
                    return $rows->map(fn($r) => ['id' => (int)$r->id, 'nombre' => $r->nombre])->values()->all();
                });
        }

        // 5) Adjuntar la lista a cada docente
        $enriquecidos = $enriquecidos->map(function ($doc) use ($talleresPorDocente) {
            $doc->talleres_dicta = $talleresPorDocente->get($doc->ci, []);
            return $doc;
        });

        // 👇 NUEVO: catálogo de talleres para el modal de filtros
        // (solo talleres que tienen clases cargadas)
        $talleres = \App\Models\Taller::select('id', 'nombre')
            ->whereIn('id', \App\Models\Clase::query()->distinct()->pluck('taller_id'))
            ->orderBy('nombre')
            ->get();

        return Inertia::render('Admin/Docentes/Index', [
            'docentes' => $enriquecidos,
            'talleres' => $talleres, // 👈 NUEVO
            'filtros'  => [
                'busqueda' => $busquedaCi,
                'nombre'   => $nombreTerm,
                'taller'   => $tallerId ? (string)$tallerId : '', // 👈 NUEVO
            ],
        ]);
    }



    /**
     * POST /admin/docentes
     * Alta/sincronización:
     * - updateOrCreatePersona en API
     * - firstOrCreate local por CI
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
        } catch (\Throwable) {
            return back()->withErrors(['ci' => 'No se pudo contactar Registro de Personas'])->withInput();
        }

        // 2) Crear local si no existe
        Docente::firstOrCreate(['ci' => $data['ci']]);

        // 3) Invalidar cache para refrescar nombre/apellido en el listado
        Cache::forget($this->personaCacheKey($data['ci']));

        return redirect()
            ->route('admin.docentes.index')
            ->with('success', 'Docente sincronizado correctamente.');
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
        $exists = Docente::where('ci', $ci)->exists();
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

        // Traemos todas las CIs de docentes del sistema (podés paginar si tenés MUCHOS)
        $cis = Docente::query()
            ->orderBy('ci')
            ->pluck('ci');

        // Enriquecemos y filtramos en memoria por performance simple
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

            if (count($found) >= 30) break; // límite razonable
        }

        return response()->json($found, 200);
    }

    /**
     * GET /admin/docentes/top?taller_id=ID[&limit=20]
     * Devuelve docentes ordenados por cantidad de clases dictadas en el taller dado.
     * Respuesta: [{ ci, nombre|null, clases_count }]
     */
    public function top(Request $request)
    {
        $data = $request->validate([
            'taller_id' => ['required', 'integer', 'exists:talleres,id'],
            'limit'     => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $tallerId = (int) $data['taller_id'];
        $limit    = (int) ($data['limit'] ?? 20);

        // Agrupar clases por docente en el taller seleccionado
        $rows = Clase::query()
            ->select('ci_docente', DB::raw('COUNT(*) as clases_count'))
            ->where('taller_id', $tallerId)
            ->whereNotNull('ci_docente')
            ->groupBy('ci_docente')
            ->orderByDesc('clases_count')
            ->limit($limit)
            ->get();

        // Enriquecer con nombre desde Registro de Personas (cache 30')
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

    /* ============================
       Helpers privados
       ============================ */

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

    /** Normaliza: lowercase + sin acentos + trim */
    private function norm(?string $s): string
    {
        if ($s === null) return '';
        return Str::of($s)->lower()->ascii()->trim()->value();
    }

    private function personaFromApiCached(string $ci): ?array
    {
        return Cache::remember("api_personas:persona:{$ci}", 1800, function () use ($ci) {
            try {
                $res = $this->personas->getPersona($ci);
                if ($res->failed()) return null;
                $json = $res->json();
                return $json['persona'] ?? null;
            } catch (\Throwable) {
                return null;
            }
        });
    }
}
