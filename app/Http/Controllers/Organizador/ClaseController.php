<?php

namespace App\Http\Controllers\Organizador;

use App\Http\Controllers\Controller;
use App\Models\Clase;
use App\Models\Taller;
use App\Models\Organizador;
use App\Services\RegistroPersonasService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ClaseController extends Controller
{
    public function __construct(
        protected RegistroPersonasService $personas
    ) {}

    /**
     * GET /organizador/clases
     *
     * Filtros:
     * - organizador (CI)  -> limita a clases de talleres asociados a ese organizador
     * - q                 -> CI o nombre del docente (nombre/apellido vía API, cacheado)
     * - desde / hasta     -> rango de fechas
     *
     * Props:
     * - clases: [{ id, fecha_hora, asistentes_maximos, taller:{id,nombre}, docente:{ci, nombre?, apellido?} }]
     * - talleres: catálogo de talleres (según organizador si aplica)
     * - organizadores: [{ ci, nombre?, apellido? }]
     * - filtros: { organizador, q, desde, hasta, taller }
     */
    public function index(Request $request)
    {
        $orgCi   = trim((string) $request->input('organizador', ''));
        $term    = trim((string) $request->input('q', ''));
        $desde   = trim((string) $request->input('desde', ''));
        $hasta   = trim((string) $request->input('hasta', ''));
        $tallerId = $request->filled('taller') ? (int) $request->input('taller') : null;

        // Base query
        $q = Clase::query()->with([
            'taller:id,nombre',
            'docente:ci',
        ]);

        // Filtrar por organizador: limitar a clases de talleres vinculados a ese organizador
        if ($orgCi !== '') {
            $q->whereIn('taller_id', function ($sub) use ($orgCi) {
                $sub->select('taller_id')
                    ->from('talleres_organizadores') // 👈 nombre correcto del pivot
                    ->where('ci_organizador', $orgCi);
            });
        }

        // Filtro por Taller (extra, por si se usa un select específico)
        if ($tallerId) {
            $q->where('taller_id', $tallerId);
        }

        // Rango de fechas
        if ($desde !== '') {
            $q->where('fecha_hora', '>=', Carbon::parse($desde)->startOfDay());
        }
        if ($hasta !== '') {
            $q->where('fecha_hora', '<=', Carbon::parse($hasta)->endOfDay());
        }

        // Si q parece CI (solo dígitos), filtra por ci_docente en DB
        if ($term !== '' && preg_match('/^\d+$/', $term)) {
            $q->where('ci_docente', 'like', "%{$term}%");
        }

        // Orden: futuras primero, luego pasadas
        $now = Carbon::now();
        $q->orderByRaw('CASE WHEN fecha_hora >= ? THEN 0 ELSE 1 END', [$now])
          ->orderBy('fecha_hora', 'asc');

        $clases = $q->get();

        // Enriquecer docente con API de personas (cache 30')
        $cis = $clases->pluck('ci_docente')->filter()->unique()->values();
        $personasByCi = [];
        foreach ($cis as $ci) {
            $personasByCi[$ci] = $this->personaFromApiCached($ci);
        }

        $enriquecidas = $clases->map(function ($clase) use ($personasByCi) {
            $ci = (string) ($clase->ci_docente ?? '');
            $p  = $personasByCi[$ci] ?? null;

            if ($clase->relationLoaded('docente') && $clase->docente) {
                $clase->docente->nombre           = $p['nombre']          ?? null;
                $clase->docente->segundo_nombre   = $p['segundoNombre']   ?? null;
                $clase->docente->apellido         = $p['apellido']        ?? null;
                $clase->docente->segundo_apellido = $p['segundoApellido'] ?? null;
            }

            return $clase;
        });

        // Si q NO es CI, filtrar en memoria por nombre/apellido
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

        // Catálogo de talleres:
        // - si hay organizador, solo sus talleres; si no, todos (para selects del filtro)
        $talleresQuery = Taller::select('id', 'nombre')->orderBy('nombre');
        if ($orgCi !== '') {
            $talleresQuery->whereIn('id', function ($sub) use ($orgCi) {
                $sub->select('taller_id')
                    ->from('talleres_organizadores')
                    ->where('ci_organizador', $orgCi);
            });
        }
        $talleres = $talleresQuery->get();

        // Catálogo de organizadores para el selector (enriquecidos con nombre)
        $organizadores = Organizador::query()
            ->orderBy('ci')
            ->pluck('ci')
            ->map(function ($ci) {
                $p = Cache::remember("api_personas:persona:{$ci}", 1800, function () use ($ci) {
                    try {
                        $res = $this->personas->getPersona($ci);
                        if ($res->failed()) return null;
                        return $res->json('persona') ?? null;
                    } catch (\Throwable) {
                        return null;
                    }
                });

                return [
                    'ci'       => (string) $ci,
                    'nombre'   => $p['nombre']   ?? null,
                    'apellido' => $p['apellido'] ?? null,
                ];
            })
            ->values();

        return Inertia::render('Organizador/Clases/Index', [
            'clases'        => $enriquecidas,
            'talleres'      => $talleres,
            'organizadores' => $organizadores,
            'filtros'       => [
                'organizador' => $orgCi,
                'q'           => $term,
                'taller'      => $tallerId ? (string) $tallerId : '',
                'desde'       => $desde,
                'hasta'       => $hasta,
            ],
        ]);
    }

    /* ============================
       Helpers privados
       ============================ */

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

    private function norm(?string $s): string
    {
        if ($s === null) return '';
        return Str::of($s)->lower()->ascii()->trim()->value();
    }
}
