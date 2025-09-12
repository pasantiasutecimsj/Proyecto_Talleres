<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Docente;
use App\Services\RegistroPersonasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

        // 1) Filtro local por CI
        $q = \App\Models\Docente::query()->orderBy('ci');
        if ($busquedaCi !== '') {
            $q->where('ci', 'like', "%{$busquedaCi}%");
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

        // 4) Traer, para los docentes visibles, los talleres que dictaron (distinct por docente)
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

        // 5) Adjuntar la lista a cada docente (prop distinta para no confundir con relaciones)
        $enriquecidos = $enriquecidos->map(function ($doc) use ($talleresPorDocente) {
            $doc->talleres_dicta = $talleresPorDocente->get($doc->ci, []);
            return $doc;
        });

        return Inertia::render('Admin/Docentes/Index', [
            'docentes' => $enriquecidos,
            'filtros'  => [
                'busqueda' => $busquedaCi,
                'nombre'   => $nombreTerm,
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
}
