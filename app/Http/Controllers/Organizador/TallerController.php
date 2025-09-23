<?php

namespace App\Http\Controllers\Organizador;

use App\Http\Controllers\Controller;
use App\Models\Taller;
use App\Models\Organizador;
use App\Services\RegistroPersonasService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class TallerController extends Controller
{
    public function __construct(
        protected RegistroPersonasService $personas
    ) {}

    /**
     * GET /organizador/talleres
     * Filtros: organizador (CI), nombre, ciudad
     */
    public function index(Request $request)
    {
        $orgCi  = trim((string) $request->input('organizador', ''));
        $term   = trim((string) $request->input('nombre', ''));
        $ciudad = $request->input('ciudad');

        $q = Taller::query();

        // 🔎 filtrar por organizador → usar tabla pivot correcta
        if ($orgCi !== '') {
            $q->whereIn('id', function ($sub) use ($orgCi) {
                $sub->select('taller_id')
                    ->from('talleres_organizadores') // 👈 nombre correcto
                    ->where('ci_organizador', $orgCi);
            });
        }

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

        // Catálogo de ciudades (API externa)
        $ciudadesResp = $this->personas->getCiudades()->json();
        $ciudades     = collect($ciudadesResp['ciudades'] ?? [])->sortBy('nombre')->values();

        // Enriquecer con nombre de ciudad
        $talleresConCiudad = $talleres->map(function ($t) use ($ciudades) {
            $c = $ciudades->firstWhere('id', $t->id_ciudad);
            $t->ciudad = $c['nombre'] ?? null;
            return $t;
        })->values();

        // Catálogo de organizadores (enriquecidos con persona; cache 30’)
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

        return Inertia::render('Organizador/Talleres/Index', [
            'talleres'      => $talleresConCiudad,
            'ciudades'      => $ciudades->all(),
            'organizadores' => $organizadores,
            'filtros'       => [
                'organizador' => $orgCi,
                'nombre'      => $term,
                'ciudad'      => $ciudad ?? '',
            ],
        ]);
    }

    /**
     * PATCH/PUT /organizador/talleres/{taller}
     * Editar un taller desde el back del organizador.
     * Redirige de vuelta preservando filtros (organizador/nombre/ciudad).
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
            ->route('organizador.talleres.index', $this->redirectParams($request))
            ->with('success', 'Taller actualizado exitosamente.');
    }

    /**
     * POST /organizador/talleres
     * (Opcional) Crear taller desde esta vista si lo necesitás.
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

        Taller::create($data);

        return redirect()
            ->route('organizador.talleres.index', $this->redirectParams($request))
            ->with('success', 'Taller creado exitosamente.');
    }

    /** Preserva filtros actuales en la redirección */
    private function redirectParams(Request $request): array
    {
        return [
            'organizador' => $request->query('organizador', ''),
            'nombre'      => $request->query('nombre', ''),
            'ciudad'      => $request->query('ciudad', ''),
        ];
    }
}
