<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Services\RegistroPersonasService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

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

            // Intentamos enriquecer con servicio externo
            try {
                $resp = $this->personasService->getPersona($d->ci);
                if ($resp && method_exists($resp, 'ok') ? $resp->ok() : true) {
                    $json = method_exists($resp, 'json') ? $resp->json() : (is_array($resp) ? $resp : null);
                    if (is_array($json)) {
                        // Intentar detectar nombre completo con claves comunes
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
                // No romper si servicio cae; devolvemos solo CI
            }

            // Fallback: si no hay nombre, dejar la CI como nombre provisional
            if (empty($row['nombre'])) {
                $row['nombre'] = null;
            }

            return $row;
        })->values();

        return response()->json(['data' => $result]);
    }

    /**
     * GET /docente/api/{ci}/clases?from=YYYY-MM-DD&to=YYYY-MM-DD
     * Devuelve todas las clases del docente en el rango [from,to] (inclusive).
     *
     * Si no se envían from/to se calcula:
     *   from = now()->subMonths(6)->startOfMonth()
     *   to   = now()->addMonths(3)->endOfMonth()
     */
    public function clasesInRange(Request $request, $ci)
    {
        if (!preg_match('/^\d{8}$/', $ci)) {
            return response()->json(['message' => 'CI inválida (se esperan 8 dígitos)'], 422);
        }

        $anchor = $request->query('anchor'); // YYYY-MM (ej: 2025-09)
        $from   = $request->query('from');   // YYYY-MM-DD
        $to     = $request->query('to');     // YYYY-MM-DD

        try {
            if ($anchor) {
                // Validar formato YYYY-MM simple
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
                    // si preferís, usá el mes actual ±1 como default
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
                'taller_calle' => $row->taller_calle,
                'taller_numero' => $row->taller_numero,
            ];
        });

        return response()->json([
            'ci'    => $ci,
            'from'  => $fromDt->toDateString(),
            'to'    => $toDt->toDateString(),
            'anchor' => $anchor,
            'clases' => $payload,
        ]);
    }
}
