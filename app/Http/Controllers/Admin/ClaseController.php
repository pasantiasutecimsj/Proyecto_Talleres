<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Clase;
use App\Models\Taller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;
use App\Services\RegistroPersonasService;

class ClaseController extends Controller
{
    public function __construct(
        protected RegistroPersonasService $personas
    ) {}

    /**
     * GET /admin/clases
     * Listado con filtros: q (CI o nombre docente), taller, desde, hasta.
     * Enriquecimiento de nombre del docente vía api_personas (cache 30').
     */
    public function index(Request $request)
    {
        $term     = trim((string) $request->input('q', ''));           // CI o nombre del docente
        $tallerId = $request->filled('taller') ? (int) $request->input('taller') : null;
        $desdeStr = trim((string) $request->input('desde', ''));       // YYYY-MM-DD (esperado)
        $hastaStr = trim((string) $request->input('hasta', ''));       // YYYY-MM-DD (esperado)

        $q = Clase::query()
            ->with([
                'taller:id,nombre',
                'docente:ci', // cargamos CI; nombre lo enriquecemos desde api_personas
            ]);

        // Filtro por Taller
        if ($tallerId) {
            $q->where('taller_id', $tallerId);
        }

        // Filtro por rango de fechas
        if ($desdeStr !== '') {
            $desde = Carbon::parse($desdeStr)->startOfDay();
            $q->where('fecha_hora', '>=', $desde);
        }
        if ($hastaStr !== '') {
            $hasta = Carbon::parse($hastaStr)->endOfDay();
            $q->where('fecha_hora', '<=', $hasta);
        }

        // Si el término parece CI (solo dígitos), filtramos DB por ci_docente
        if ($term !== '' && preg_match('/^\d+$/', $term)) {
            $q->where('ci_docente', 'like', "%{$term}%");
        }

        // Orden por proximidad: futuras primero, luego pasadas
        $now = Carbon::now();
        $q->orderByRaw('CASE WHEN fecha_hora >= ? THEN 0 ELSE 1 END', [$now])
          ->orderBy('fecha_hora', 'asc');

        $clases = $q->get();

        // Enriquecimiento: nombre del docente desde Registro de Personas (cache 30')
        $cis = $clases->pluck('ci_docente')->filter()->unique()->values();
        $personasByCi = [];
        foreach ($cis as $ci) {
            $personasByCi[$ci] = $this->personaFromApiCached($ci);
        }

        $enriquecidas = $clases->map(function ($clase) use ($personasByCi) {
            $ci = (string) ($clase->ci_docente ?? '');
            $p  = $personasByCi[$ci] ?? null;

            // Agregamos campos al objeto relacionado "docente" para mostrarlos en la vista
            if ($clase->relationLoaded('docente') && $clase->docente) {
                $clase->docente->nombre           = $p['nombre']          ?? null;
                $clase->docente->segundo_nombre   = $p['segundoNombre']   ?? null;
                $clase->docente->apellido         = $p['apellido']        ?? null;
                $clase->docente->segundo_apellido = $p['segundoApellido'] ?? null;
            }

            return $clase;
        });

        // Si el término NO es CI, filtramos en memoria por nombre/apellido del docente
        if ($term !== '' && !preg_match('/^\d+$/', $term)) {
            $needle = $this->norm($term);

            $enriquecidas = $enriquecidas->filter(function ($clase) use ($needle) {
                $n  = $clase->docente->nombre           ?? null;
                $sn = $clase->docente->segundo_nombre   ?? null;
                $a  = $clase->docente->apellido         ?? null;
                $sa = $clase->docente->segundo_apellido ?? null;

                $full1 = trim(implode(' ', array_filter([$n, $sn, $a, $sa])));
                $full2 = trim(implode(' ', array_filter([$a, $sa, $n, $sn])));

                return str_contains($this->norm($full1), $needle)
                    || str_contains($this->norm($full2), $needle);
            })->values();
        }

        // Catálogo de talleres (para selects/filtros)
        $talleres = Taller::select('id', 'nombre')
            ->orderBy('nombre')
            ->get();

        return Inertia::render('Admin/Clases/Index', [
            'clases'  => $enriquecidas,
            'talleres'=> $talleres,
            'filtros' => [
                'q'      => $term,
                'taller' => $tallerId ? (string) $tallerId : '',
                'desde'  => $desdeStr,
                'hasta'  => $hastaStr,
            ],
        ]);
    }

    /**
     * POST /admin/clases
     * Crear clase.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'fecha_hora'         => ['required', 'date'],
            'asistentes_maximos' => ['required', 'integer', 'min:1'],
            'ci_docente'         => ['required', 'string', 'size:8', 'exists:docentes,ci'],
            'taller_id'          => ['required', 'integer', 'exists:talleres,id'],
        ]);

        Clase::create($data);

        return redirect()
            ->route('admin.clases.index')
            ->with('success', 'Clase creada correctamente.');
    }

    /**
     * PUT/PATCH /admin/clases/{clase}
     * Actualizar clase.
     */
    public function update(Request $request, Clase $clase)
    {
        $data = $request->validate([
            'fecha_hora'         => ['required', 'date'],
            'asistentes_maximos' => ['required', 'integer', 'min:1'],
            'ci_docente'         => ['required', 'string', 'size:8', 'exists:docentes,ci'],
            'taller_id'          => ['required', 'integer', 'exists:talleres,id'],
        ]);

        $clase->update($data);

        return redirect()
            ->route('admin.clases.index')
            ->with('success', 'Clase actualizada correctamente.');
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
            $json = $res->json();
            return $json['persona'] ?? null;
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

    /** Normaliza: lowercase + quita acentos y trim */
    private function norm(?string $s): string
    {
        if ($s === null) return '';
        return Str::of($s)->lower()->ascii()->trim()->value();
    }
}
