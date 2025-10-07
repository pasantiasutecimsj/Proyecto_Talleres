<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Clase;
use App\Models\Taller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Inertia\Inertia;
use App\Services\RegistroPersonasService;

class ClaseController extends Controller
{
    public function __construct(
        protected RegistroPersonasService $personas
    ) {}

    public function index(Request $request)
    {
        $term     = trim((string) $request->input('q', ''));
        $tallerId = $request->filled('taller') ? (int) $request->input('taller') : null;
        $desdeStr = trim((string) $request->input('desde', ''));
        $hastaStr = trim((string) $request->input('hasta', ''));

        $q = Clase::query()
            ->with([
                'taller:id,nombre',
                'docente:ci',
            ]);

        if ($tallerId) $q->where('taller_id', $tallerId);

        if ($desdeStr !== '') $q->where('fecha_hora', '>=', Carbon::parse($desdeStr)->startOfDay());
        if ($hastaStr !== '') $q->where('fecha_hora', '<=', Carbon::parse($hastaStr)->endOfDay());

        if ($term !== '' && preg_match('/^\d+$/', $term)) {
            $q->where('ci_docente', 'like', "%{$term}%");
        }

        $now = Carbon::now();
        $q->orderByRaw('CASE WHEN fecha_hora >= ? THEN 0 ELSE 1 END', [$now])
          ->orderBy('fecha_hora', 'asc');

        $clases = $q->get();

        // Enriquecimiento con Registro de Personas (cache)
        $cis = $clases->pluck('ci_docente')->filter()->unique()->values();
        $personasByCi = [];
        foreach ($cis as $ci) $personasByCi[$ci] = $this->personaFromApiCached($ci);

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

        if ($term !== '' && !preg_match('/^\d+$/', $term)) {
            $needle = $this->norm($term);
            $enriquecidas = $enriquecidas->filter(function ($clase) use ($needle) {
                $d = $clase->docente ?? null;
                $full1 = trim(implode(' ', array_filter([$d->nombre ?? null, $d->segundo_nombre ?? null, $d->apellido ?? null, $d->segundo_apellido ?? null])));
                $full2 = trim(implode(' ', array_filter([$d->apellido ?? null, $d->segundo_apellido ?? null, $d->nombre ?? null, $d->segundo_nombre ?? null])));
                return str_contains($this->norm($full1), $needle) || str_contains($this->norm($full2), $needle);
            })->values();
        }

        // Catálogo de talleres (activos por scope de Taller)
        $talleres = Taller::select('id', 'nombre')->orderBy('nombre')->get();

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

    /** Crear clase (solo futuro; Taller y Docente deben estar activos) */
    public function store(Request $request)
    {
        $data = $request->validate([
            'fecha_hora'         => ['required', 'date', 'after:now'],
            'asistentes_maximos' => ['required', 'integer', 'min:1'],
            'ci_docente'         => [
                'required', 'string', 'size:8',
                Rule::exists('docentes', 'ci')->where(fn($q) => $q->where('Activo', 1)),
            ],
            'taller_id'          => [
                'required', 'integer',
                Rule::exists('talleres', 'id')->where(fn($q) => $q->where('Activo', 1)),
            ],
        ]);

        Clase::create($data);

        return redirect()->route('admin.clases.index')
            ->with('success', 'Clase creada correctamente.');
    }

    /** Actualizar clase (solo si sigue siendo futura) */
    public function update(Request $request, Clase $clase)
    {
        if (Carbon::parse($clase->fecha_hora)->isPast()) {
            return back()->withErrors(['fecha_hora' => 'No se puede editar una clase que ya sucedió.']);
        }

        $data = $request->validate([
            'fecha_hora'         => ['required', 'date', 'after:now'],
            'asistentes_maximos' => ['required', 'integer', 'min:1'],
            'ci_docente'         => [
                'required', 'string', 'size:8',
                Rule::exists('docentes', 'ci')->where(fn($q) => $q->where('Activo', 1)),
            ],
            'taller_id'          => [
                'required', 'integer',
                Rule::exists('talleres', 'id')->where(fn($q) => $q->where('Activo', 1)),
            ],
        ]);

        $clase->update($data);

        return redirect()->route('admin.clases.index')
            ->with('success', 'Clase actualizada correctamente.');
    }

    /** Eliminar físicamente una clase futura */
    public function destroy(Clase $clase)
    {
        if (Carbon::parse($clase->fecha_hora)->isPast()) {
            return back()->withErrors(['id' => 'No se puede eliminar una clase que ya sucedió.']);
        }

        DB::transaction(function () use ($clase) {
            // Por si tu FK pivot no tiene cascade:
            $clase->asistentes()->detach();
            $clase->delete();
        });

        return redirect()->route('admin.clases.index')
            ->with('success', 'Clase eliminada.');
    }

    /* ============================ Helpers API ============================ */

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
        return Cache::remember($this->personaCacheKey($ci), 1800, fn() => $this->personaFromApi($ci));
    }

    private function norm(?string $s): string
    {
        if ($s === null) return '';
        return Str::of($s)->lower()->ascii()->trim()->value();
    }
}
