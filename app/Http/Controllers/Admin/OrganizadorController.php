<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organizador;
use App\Models\Taller;
use App\Services\UsuariosApiService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Inertia\Inertia;
use App\Models\Docente;


use Illuminate\Support\Facades\DB;


class OrganizadorController extends Controller
{
    public function __construct(protected UsuariosApiService $usuariosApi) {}

    /** GET /admin/organizadores */
    public function index(Request $request)
    {
        $tallerId   = $request->filled('taller') ? (int) $request->input('taller') : null;
        $nombreTerm = trim((string) $request->input('nombre', ''));
        $estado     = $request->input('estado', 'activos'); // activos|inactivos|todos

        // 1) Scope por estado (usa tu trait/global scope actual)
        $q = match ($estado) {
            'inactivos' => Organizador::soloInactivos(),
            'todos'     => Organizador::conInactivos(),
            default     => Organizador::query(),
        };

        // 2) Filtros locales
        $q->with(['talleres:id,nombre'])->orderBy('user_id', 'asc');

        if ($tallerId) {
            $q->whereHas('talleres', fn($qq) => $qq->where('taller_id', $tallerId));
        }

        $organizadores = $q->get();

        // 3) Enriquecer con datos mínimos desde api_usuarios (del proyecto actual)
        $proyectoClave = config('services.usuarios_api.proyecto_clave') ?? env('USUARIOS_API_PROYECTO_CLAVE');
        $remotos = [];
        try {
            $proj = $this->usuariosApi->getProjectByClave($proyectoClave);
            $remotos = $this->usuariosApi->getProjectUsers((int)($proj['id'] ?? 0), ['per_page' => 1000]);
        } catch (\Throwable) {
            // Si la API no responde, seguimos con lo local
        }

        $byId = collect($remotos['data'] ?? $remotos ?? [])->keyBy(fn($u) => $u['id'] ?? null);

        $lista = $organizadores->map(function ($org) use ($byId) {
            $u = $byId->get($org->user_id);
            $org->nombre   = $u['name']   ?? null;
            $org->email    = $u['email']  ?? null;
            $org->telefono = $u['telefono'] ?? ($u['phone'] ?? null);
            return $org;
        });

        // 4) Filtro por nombre/email en memoria
        if ($nombreTerm !== '') {
            $needle = $this->norm($nombreTerm);
            $lista = $lista->filter(function ($org) use ($needle) {
                $mix = trim(implode(' ', array_filter([$org->nombre, $org->email])));
                return str_contains($this->norm($mix), $needle);
            })->values();
        }

        // 5) Catálogo de talleres
        $talleres = Taller::select('id', 'nombre')->orderBy('nombre')->get();

        return Inertia::render('Admin/Organizadores/Index', [
            'organizadores' => $lista,
            'talleres'      => $talleres,
            'filtros'       => [
                'taller' => $tallerId ? (string)$tallerId : '',
                'nombre' => $nombreTerm,
                'estado' => $estado,
            ],
        ]);
    }

    /** POST /admin/organizadores (adjuntar existente por user_id + sync talleres) */
    public function store(Request $request)
    {
        $data = $request->validate([
            // A: adjuntar existente
            'user_id'   => ['nullable', 'integer', 'min:1'],
            // B: crear en API
            'name'      => ['required_without:user_id', 'string', 'max:255'],
            'email'     => ['required_without:user_id', 'email', 'max:255'],
            'password'  => ['required_without:user_id', 'string', 'min:8', 'confirmed'],
            // roles requeridos
            'roles'     => ['required', 'array', 'min:1'],
            'roles.*'   => ['string', Rule::in(['organizador', 'docente'])],
            // talleres (para vínculo con organizadores)
            'talleres'   => ['nullable', 'array'],
            'talleres.*' => [
                'integer',
                Rule::exists('talleres', 'id')->where(fn($q) => $q->where('Activo', 1)),
            ],
        ]);

        $proyectoClave = config('services.usuarios_api.proyecto_clave') ?? env('USUARIOS_API_PROYECTO_CLAVE');
        // 1) Resolver projectId y roleIds por clave
        $proyecto   = $this->usuariosApi->getProjectByClave($proyectoClave);
        $projectId  = (int) ($proyecto['id'] ?? 0);
        if ($projectId <= 0) {
            return back()->withErrors(['roles' => 'No se pudo resolver el proyecto destino.'])->withInput();
        }

        $roleIds = [];
        foreach (array_unique($data['roles']) as $rolClave) {
            $rol = $this->usuariosApi->getRoleByClaveAndProyecto($rolClave, $projectId);
            $rid = (int)($rol['id'] ?? 0);
            if ($rid <= 0) {
                return back()->withErrors(['roles' => "Rol '{$rolClave}' no encontrado en el proyecto."])->withInput();
            }
            $roleIds[] = $rid;
        }

        try {
            return DB::transaction(function () use ($request, $data, $projectId, $roleIds) {
                // 2) Crear o adjuntar usuario en API
                $userId = null;

                if (!empty($data['user_id'])) {
                    // (A) ADJUNTAR EXISTENTE
                    $userId = (int) $data['user_id'];

                    // Traer roles/proyectos actuales para no pisar nada
                    $actual = $this->usuariosApi->getUser($userId);

                    // Construir union de roles (IDs) y proyectos (IDs)
                    $actualRoles = collect($actual['roles'] ?? [])->pluck('id')->filter()->map(fn($v) => (int)$v)->values()->all();
                    $actualProys = collect($actual['proyectos'] ?? [])->pluck('id')->filter()->map(fn($v) => (int)$v)->values()->all();

                    $newRoles = collect($actualRoles)->merge($roleIds)->unique()->values()->all();
                    $newProys = collect($actualProys)->merge([$projectId])->unique()->values()->all();

                    // PATCH no destructivo (union)
                    $this->usuariosApi->patchUser($userId, [
                        'roles'     => $newRoles,
                        'proyectos' => $newProys,
                        'activo'    => true,
                    ]);
                } else {
                    // (B) CREAR NUEVO EN API
                    $payload = [
                        'name'                  => $data['name'],
                        'email'                 => $data['email'],
                        'password'              => $data['password'],
                        'password_confirmation' => $request->input('password_confirmation'),
                        'roles'                 => $roleIds,      // IDs
                        'proyectos'             => [$projectId],  // IDs
                        'activo'                => true,
                    ];

                    $nuevo  = $this->usuariosApi->createUser($payload);
                    $userId = (int) ($nuevo['id'] ?? 0);
                    if ($userId <= 0) {
                        throw new \RuntimeException('No se pudo crear el usuario remoto.');
                    }
                }

                // 3) Crear/activar locales según roles elegidos
                if (in_array('organizador', $data['roles'], true)) {
                    $org = Organizador::conInactivos()->firstOrNew(['user_id' => $userId]);
                    $org->Activo = true;
                    $org->save();

                    // Sincronizar talleres si vinieron
                    if ($request->has('talleres')) {
                        $org->talleres()->sync($request->input('talleres', []));
                    }
                }

                if (in_array('docente', $data['roles'], true)) {
                    $doc = Docente::conInactivos()->firstOrNew(['user_id' => $userId]);
                    $doc->Activo = true;
                    $doc->save();
                }

                return redirect()->route('admin.organizadores.index')
                    ->with('success', 'Usuario sincronizado y roles asignados correctamente.');
            });
        } catch (\Throwable $e) {
            // Podés loguearlo si querés: report($e);
            return back()->withErrors([
                'general' => $e->getMessage() ?: 'Error al crear/adjuntar el usuario y asignar roles.',
            ])->withInput();
        }
    }

public function update(Request $request, Organizador $organizador)
{
    $data = $request->validate([
        // datos del usuario remoto (opcionales)
        'usuario.name'                  => ['sometimes','string','max:255'],
        'usuario.email'                 => ['sometimes','email','max:255'],
        'usuario.password'              => ['sometimes','string','min:8','confirmed'],
        'usuario.password_confirmation' => ['sometimes','string','min:8'],

        // roles en este proyecto
        'roles'     => ['sometimes','array'],
        'roles.*'   => [Rule::in(['organizador','docente'])],

        // talleres (si es organizador)
        'talleres'   => ['sometimes','array'],
        'talleres.*' => [
            'integer',
            Rule::exists('talleres','id')->where(fn($q) => $q->where('Activo',1)),
        ],
    ]);

    $userId = (int) $organizador->user_id;
    
    // Resolver proyecto y roles-IDs por clave
    $proyectoClave = config('services.usuarios_api.proyecto_clave') ?? env('USUARIOS_API_PROYECTO_CLAVE');
    $proyecto   = $this->usuariosApi->getProjectByClave($proyectoClave);
    $projectId  = (int)($proyecto['id'] ?? 0);

    // Mapear claves -> IDs en este proyecto
    $roleIdsByClave = [];
    foreach (['organizador','docente'] as $clave) {
        try {
            $r = $this->usuariosApi->getRoleByClaveAndProyecto($clave, $projectId);
            $roleIdsByClave[$clave] = (int)($r['id'] ?? 0);
        } catch (\Throwable) {
            $roleIdsByClave[$clave] = 0;
        }
    }

    try {
        // 1) Cargar usuario actual para calcular roles nuevos
        $actual = $this->usuariosApi->getUser($userId);
        $actualRoleIds = collect($actual['roles'] ?? [])->pluck('id')->map(fn($v)=>(int)$v)->all();

        // IDs de roles del proyecto actual (ambos)
        $projectRoleIds = collect($roleIdsByClave)->values()->filter()->all();

        // Roles seleccionados ahora (si no viene, mantenemos los actuales del proyecto)
        $selectedClaves = collect($data['roles'] ?? [])
            ->filter(fn($x) => in_array($x, ['organizador','docente'], true))
            ->values()
            ->all();

        $selectedRoleIdsForProject = collect($selectedClaves)
            ->map(fn($k) => $roleIdsByClave[$k] ?? 0)
            ->filter()
            ->values()
            ->all();

        // Si enviaron 'roles', reemplazamos los de ESTE proyecto por la selección;
        // el resto de roles (de otros proyectos) se conservan.
        $newRoleIds = $actualRoleIds;
        if ($request->has('roles')) {
            $newRoleIds = collect($actualRoleIds)
                ->reject(fn($rid) => in_array($rid, $projectRoleIds, true)) // sacamos roles del proyecto actual
                ->merge($selectedRoleIdsForProject) // agregamos seleccionados
                ->unique()
                ->values()
                ->all();
        }

        // 2) Armar payload de patch del usuario remoto
        $patch = [];

        if ($request->has('usuario')) {
            $u = $data['usuario'];
            if (array_key_exists('name', $u))  $patch['name']  = $u['name'];
            if (array_key_exists('email', $u)) $patch['email'] = $u['email'];
            if (array_key_exists('password', $u)) {
                $patch['password']              = $u['password'];
                $patch['password_confirmation'] = $request->input('usuario.password_confirmation');
            }
        }

        if ($request->has('roles')) {
            $patch['roles'] = $newRoleIds;
        }

        // Mantener asociación al proyecto si tiene algún rol en este proyecto
        if (!empty($selectedRoleIdsForProject)) {
            $actualProys = collect($actual['proyectos'] ?? [])->pluck('id')->map(fn($v)=>(int)$v)->all();
            $patch['proyectos'] = collect($actualProys)->merge([$projectId])->unique()->values()->all();
        }

        if (!empty($patch)) {
            $this->usuariosApi->patchUser($userId, $patch);
        }

        // 3) Sincronizar locales según roles seleccionados (si los mandaron)
        if ($request->has('roles')) {
            // ORGANIZADOR
            if (in_array('organizador', $selectedClaves, true)) {
                $org = Organizador::conInactivos()->firstOrNew(['user_id' => $userId]);
                $org->Activo = true;
                $org->save();

                if ($request->has('talleres')) {
                    $org->talleres()->sync($request->input('talleres', []));
                }
            } else {
                // Si ya no es organizador, desactivar y limpiar talleres
                $org = Organizador::conInactivos()->find($userId);
                if ($org) {
                    $org->talleres()->sync([]); // opcional
                    $org->desactivar();
                }
            }

            // DOCENTE
            if (in_array('docente', $selectedClaves, true)) {
                $doc = \App\Models\Docente::conInactivos()->firstOrNew(['user_id' => $userId]);
                $doc->Activo = true;
                $doc->save();
            } else {
                $doc = \App\Models\Docente::conInactivos()->find($userId);
                if ($doc) $doc->desactivar();
            }
        } else {
            // Si no vino 'roles', solo talleres (si se envían)
            if ($request->has('talleres')) {
                $organizador->talleres()->sync($request->input('talleres', []));
            }
        }

        return redirect()->route('admin.organizadores.index')
            ->with('success', 'Usuario actualizado correctamente.');
    } catch (\Throwable $e) {
        return back()->withErrors(['general' => $e->getMessage() ?: 'Error al actualizar el usuario'])->withInput();
    }
}

    /** DELETE /admin/organizadores/{organizador} - borrado lógico */
    public function destroy(Organizador $organizador)
    {
        $organizador->desactivar();
        return redirect()->route('admin.organizadores.index')
            ->with('success', 'Organizador desactivado.');
    }

    /** PATCH /admin/organizadores/{user_id}/restore */
    public function restore(int $user_id)
    {
        $org = Organizador::conInactivos()->findOrFail($user_id);
        $org->restaurar();
        return redirect()->route('admin.organizadores.index', ['estado' => 'todos'])
            ->with('success', 'Organizador restaurado.');
    }

    private function norm(?string $s): string
    {
        return Str::of((string)$s)->lower()->ascii()->trim()->value();
    }

    public function buscarUsuarios(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if ($q === '' || mb_strlen($q) < 2) {
            return response()->json([]);
        }

        try {
            $proyectoClave = config('services.usuarios_api.proyecto_clave')
                ?? env('USUARIOS_API_PROYECTO_CLAVE');
            $proj = $this->usuariosApi->getProjectByClave($proyectoClave);
            $projectId = (int)($proj['id'] ?? 0);
            if ($projectId <= 0) return response()->json([]);

            // La API acepta 'busqueda' y per_page
            $remotos = $this->usuariosApi->getProjectUsers($projectId, [
                'busqueda' => $q,
                'per_page' => 8,
            ]);

            $items = collect($remotos['data'] ?? $remotos ?? [])
                ->map(fn($u) => [
                    'id'    => $u['id'] ?? null,
                    'name'  => $u['name'] ?? null,
                    'email' => $u['email'] ?? null,
                ])
                ->filter(fn($u) => !is_null($u['id']))
                ->values()
                ->all();

            return response()->json($items, 200);
        } catch (\Throwable $e) {
            return response()->json([], 200); // devolvemos vacío para no romper el modal
        }
    }
}
