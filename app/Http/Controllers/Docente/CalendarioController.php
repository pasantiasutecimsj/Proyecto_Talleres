<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Services\RegistroPersonasService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Response;

class CalendarioController extends Controller
{
    protected RegistroPersonasService $personasService;

    public function __construct(RegistroPersonasService $personasService)
    {
        $this->personasService = $personasService;
    }

    /**
     * GET /docente/api/docentes
     * Devuelve lista de docentes locales (ci) y, cuando sea posible, nombre completo desde RegistroPersonas.
     */
    public function docentes()
    {
        $docentes = DB::table('docentes')->select('ci')->orderBy('ci')->get();

        $result = $docentes->map(function ($d) {
            $row = ['ci' => $d->ci, 'nombre' => null];

            try {
                $resp = $this->personasService->getPersona($d->ci);
                if ($resp && method_exists($resp, 'ok') ? $resp->ok() : true) {
                    $json = method_exists($resp, 'json') ? $resp->json() : (is_array($resp) ? $resp : null);
                    if (is_array($json)) {
                        if (!empty($json['nombre'])) {
                            $row['nombre'] = $json['nombre'];
                        } elseif (!empty($json['nombres']) || !empty($json['apellidos'])) {
                            $n = $json['nombres'] ?? '';
                            $a = $json['apellidos'] ?? '';
                            $row['nombre'] = trim($n . ' ' . $a);
                        } elseif (!empty($json['nombre_completo'])) {
                            $row['nombre'] = $json['nombre_completo'];
                        }
                    }
                }
            } catch (\Throwable $e) {
                // si falla el servicio, devolvemos solo CI
            }

            if (empty($row['nombre'])) {
                $row['nombre'] = null;
            }

            return $row;
        })->values();

        return response()->json(['data' => $result]);
    }

    /**
     * GET /docente/api/{ci}/clases?anchor=YYYY-MM
     * (también soporta from/to=YYYY-MM-DD)
     * Devuelve clases del docente en [mes ancla -1, mes ancla +1].
     */
    public function clasesInRange(Request $request, $ci)
    {
        if (!preg_match('/^\d{8}$/', $ci)) {
            return response()->json(['message' => 'CI inválida (se esperan 8 dígitos)'], 422);
        }

        $anchor = $request->query('anchor'); // YYYY-MM
        $from   = $request->query('from');   // YYYY-MM-DD
        $to     = $request->query('to');     // YYYY-MM-DD

        try {
            if ($anchor) {
                if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $anchor)) {
                    throw new \InvalidArgumentException('anchor debe ser YYYY-MM');
                }
                [$y, $m] = array_map('intval', explode('-', $anchor));
                $base = Carbon::createFromDate($y, $m, 1, 'America/Montevideo');

                $fromDt = $base->copy()->subMonth()->startOfMonth();
                $toDt   = $base->copy()->addMonth()->endOfMonth();
            } else {
                if ($from) {
                    $fromDt = Carbon::parse($from, 'America/Montevideo')->startOfDay();
                } else {
                    $now = Carbon::now('America/Montevideo');
                    $fromDt = $now->copy()->startOfMonth()->subMonth()->startOfMonth();
                }

                if ($to) {
                    $toDt = Carbon::parse($to, 'America/Montevideo')->endOfDay();
                } else {
                    $now = isset($now) ? $now : Carbon::now('America/Montevideo');
                    $toDt = $now->copy()->endOfMonth()->addMonth()->endOfMonth();
                }
            }
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'anchor|from|to' => 'Parámetros de fecha inválidos. anchor=YYYY-MM o from/to=YYYY-MM-DD',
            ]);
        }

        $clases = DB::table('clases as c')
            ->select(
                'c.id',
                'c.fecha_hora',
                'c.asistentes_maximos',
                'c.ci_docente',
                'c.taller_id',
                't.nombre as taller_nombre',
                't.descripcion as taller_descripcion',
                't.calle as taller_calle',
                't.numero as taller_numero'
            )
            ->join('talleres as t', 'c.taller_id', '=', 't.id')
            ->where('c.ci_docente', $ci)
            ->whereBetween('c.fecha_hora', [$fromDt->toDateTimeString(), $toDt->toDateTimeString()])
            ->orderBy('c.fecha_hora', 'asc')
            ->get();

        $payload = $clases->map(function ($row) {
            $dt = Carbon::parse($row->fecha_hora, 'America/Montevideo');
            return [
                'id' => $row->id,
                'fecha_hora' => $dt->toDateTimeString(),
                'fecha_hora_iso' => $dt->toIso8601String(),
                'asistentes_maximos' => $row->asistentes_maximos,
                'ci_docente' => $row->ci_docente,
                'taller_id' => $row->taller_id,
                'taller_nombre' => $row->taller_nombre,
                'taller_descripcion' => $row->taller_descripcion,
                'taller_calle' => $row->taller_calle,
                'taller_numero' => $row->taller_numero,
            ];
        });

        return response()->json([
            'ci'     => $ci,
            'from'   => $fromDt->toDateString(),
            'to'     => $toDt->toDateString(),
            'anchor' => $anchor,
            'clases' => $payload,
        ]);
    }

    /**
     * GET /docente/api/clases/{id}/asistentes
     * Devuelve la clase + asistentes (con nombre si está disponible) y totales.
     */
    public function asistentes(Request $request, int $claseId)
    {
        // Datos de la clase + taller
        $clase = DB::table('clases as c')
            ->select(
                'c.id',
                'c.fecha_hora',
                'c.asistentes_maximos',
                'c.ci_docente',
                'c.taller_id',
                't.nombre as taller_nombre',
                't.descripcion as taller_descripcion',
                't.calle as taller_calle',
                't.numero as taller_numero'
            )
            ->join('talleres as t', 'c.taller_id', '=', 't.id')
            ->where('c.id', $claseId)
            ->first();

        if (!$clase) {
            return response()->json(['message' => 'Clase no encontrada'], 404);
        }

        // Inscriptos con flag de asistencia
        $rows = DB::table('clase_asistentes')
            ->select('ci_asistente as ci', 'asistio')
            ->where('clase_id', $claseId)
            ->orderBy('ci_asistente', 'asc')
            ->get();

        // Enriquecer nombre por CI (cache 30')
        $asistentes = $rows->map(function ($r) {
            $p = $this->personaFromApiCached((string) $r->ci);
            $nombre = null;

            if (is_array($p)) {
                // Igual que en Admin\ClaseController
                $n  = $p['nombre']          ?? null;
                $sn = $p['segundoNombre']   ?? null;
                $a  = $p['apellido']        ?? null;
                $sa = $p['segundoApellido'] ?? null;

                $full = trim(implode(' ', array_filter([$n, $sn, $a, $sa])));
                $nombre = $full !== '' ? $full : null;
            }

            return [
                'ci'      => (string) $r->ci,
                'nombre'  => $nombre,
                'asistio' => (bool) $r->asistio,
            ];
        })->values();

        // Totales
        $inscriptos = $rows->count();
        $presentes  = $rows->where('asistio', true)->count();

        $dt = Carbon::parse($clase->fecha_hora, 'America/Montevideo');

        return response()->json([
            'clase' => [
                'id'                 => $clase->id,
                'fecha_hora'         => $dt->toDateTimeString(),
                'fecha_hora_iso'     => $dt->toIso8601String(),
                'asistentes_maximos' => $clase->asistentes_maximos,
                'taller_id'          => $clase->taller_id,
                'taller_nombre'      => $clase->taller_nombre,
                'taller_descripcion' => $clase->taller_descripcion,
                'taller_calle'       => $clase->taller_calle,
                'taller_numero'      => $clase->taller_numero,
                'totales'            => [
                    'inscriptos' => $inscriptos,
                    'presentes'  => $presentes,
                ],
            ],
            'asistentes' => $asistentes,
        ]);
    }

    /**
     * PATCH /docente/api/clases/{id}/asistentes/{ci}
     * Body: { asistio: bool }
     * Actualiza asistencia y devuelve totales recalculados.
     */
    public function updateAsistencia(Request $request, int $claseId, string $ci)
    {
        if (!preg_match('/^\d{8}$/', $ci)) {
            return response()->json(['message' => 'CI inválida (se esperan 8 dígitos)'], 422);
        }

        $data = $request->validate([
            'asistio' => ['required', 'boolean'],
        ]);

        // Verificar existencia de la fila
        $exists = DB::table('clase_asistentes')
            ->where('clase_id', $claseId)
            ->where('ci_asistente', $ci)
            ->exists();

        if (!$exists) {
            return response()->json(['message' => 'Asistente no inscripto en esta clase'], 404);
        }

        // Actualizar flag
        DB::table('clase_asistentes')
            ->where('clase_id', $claseId)
            ->where('ci_asistente', $ci)
            ->update(['asistio' => (bool) $data['asistio']]);

        // Recalcular totales
        $rows = DB::table('clase_asistentes')
            ->select('asistio')
            ->where('clase_id', $claseId)
            ->get();

        $inscriptos = $rows->count();
        $presentes  = $rows->where('asistio', true)->count();

        return response()->json([
            'ok' => true,
            'asistente' => [
                'ci'      => $ci,
                'asistio' => (bool) $data['asistio'],
            ],
            'totales' => [
                'inscriptos' => $inscriptos,
                'presentes'  => $presentes,
            ],
        ]);
    }

    /* ============================
       Helpers (duplicados de Admin)
       ============================ */

    private function personaCacheKey(string $ci): string
    {
        return "api_personas:persona:{$ci}";
    }

    private function personaFromApi(string $ci): ?array
    {
        try {
            $res = $this->personasService->getPersona($ci);
            if (method_exists($res, 'failed') && $res->failed()) {
                return null;
            }
            $json = method_exists($res, 'json') ? $res->json() : null;
            return is_array($json) ? ($json['persona'] ?? null) : null;
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

    /** Normaliza: lowercase + quita acentos y trim (por si luego filtramos por nombre en server) */
    private function norm(?string $s): string
    {
        if ($s === null) return '';
        return Str::of($s)->lower()->ascii()->trim()->value();
    }
}
