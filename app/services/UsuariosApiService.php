<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class UsuariosApiService
{
    public function __construct(
        private string $base = ''
    ) {
        $this->base = rtrim(config('services.usuarios_api.base_url', 'http://localhost:4010'), '/');
    }

// ENDPOINTS DE USER
    /** Traer los datos del usuario Auth usando Bearer */
        public function me(): array
        {
            $token = $this->getToken();
            if (!$token) {
                throw new \RuntimeException('No autenticado', 401);
            }

            $res = $this->client($token)->get('/api/user');
            if ($res->failed()) {
                // Si el token expiró/revocado:
                if ($res->status() === 401) {
                    $this->clearToken();
                }
                throw new \RuntimeException($res->json('message') ?? 'No autenticado', $res->status());
            }

            return $res->json();
        }

    /** Traer los datos de todos los usuarios usando Bearer */
    public function listUsers(array $params = []): array
    {
        $token = session('usuarios_api.token');
        if (!$token) throw new \RuntimeException('No autenticado');

        $res = $this->client($token)->get('/api/users', $params);

        if ($res->failed()) {
            throw new \RuntimeException($res->json('message') ?? 'Error al listar usuarios', $res->status());
        }
        return $res->json();
    }

    /** Crear un nuevo usuario */
    public function createUser(array $payload): array
    {
        $token = $this->getToken(); // o session('usuarios_api.token')
        if (!$token) {
            throw new \RuntimeException('No autenticado', 401);
        }

        $res = $this->client($token)->post('/api/user', $payload);

        if ($res->failed()) {
            // devolvé los errores de validación si vienen
            $msg = $res->json('message') ?: 'Error al crear usuario';
            $errors = $res->json('errors') ?: null;
            throw new \RuntimeException($errors ? json_encode($errors) : $msg, $res->status());
        }

        return $res->json(); // usuario creado por la API
    }

    /** Actualizar parcialmente un usuario */
    public function patchUser(int $id, array $payload): array
    {
        $token = $this->getToken();
        if (!$token) throw new \RuntimeException('No autenticado', 401);

        $res = $this->client($token)->patch("/api/user/{$id}", $payload);
        if ($res->failed()) {
            $msg = $res->json('message') ?: 'Error al actualizar usuario';
            $errors = $res->json('errors') ?: null;
            throw new \RuntimeException($errors ? json_encode($errors) : $msg, $res->status());
        }

        return $res->json();
    }

// ENDPOINTS DE PROYECTOS
    /** Obtener un proyecto por ID */
    public function getProject(int $id): array
    {
        $token = $this->getToken();
        if (!$token) throw new \RuntimeException('No autenticado', 401);

        $res = $this->client($token)->get("/api/project/{$id}");
        if ($res->failed()) {
            throw new \RuntimeException($res->json('message') ?? 'No se pudo obtener el proyecto', $res->status());
        }
        return $res->json();
    }

    /**
     * Obtener todos los roles de un proyecto
     *
     * @param int $idProyecto
     * @return array
     * @throws \RuntimeException
     */
    public function getProjectRoles(int $idProyecto): array
    {
        $token = session('usuarios_api.token');
        if (!$token) {
            throw new \RuntimeException('No autenticado');
        }

        $res = $this->client($token)->get("/api/project/{$idProyecto}/roles");

        if ($res->failed()) {
            throw new \RuntimeException(
                $res->json('message') ?? 'Error al obtener roles del proyecto',
                $res->status()
            );
        }

        return $res->json();
    }

    /** Obtener un proyecto por clave (string) */
    public function getProjectByClave(string $clave): array
    {
        $token = $this->getToken();
        if (!$token) throw new \RuntimeException('No autenticado', 401);

        $res = $this->client($token)->get("/api/project/clave/{$clave}");
        if ($res->failed()) {
            throw new \RuntimeException($res->json('message') ?? 'No se pudo obtener el proyecto por clave', $res->status());
        }
        return $res->json();
    }

    /**
     * Obtener todos los usuarios con un rol dado dentro de un proyecto.
     * Acepta $params para paginar/filtrar: ['page'=>1,'per_page'=>10,...]
     */
    public function getProjectUsersByRole(int $projectId, int $roleId, array $params = []): array
    {
        $token = $this->getToken();
        if (!$token) throw new \RuntimeException('No autenticado', 401);

        $res = $this->client($token)->get("/api/project/{$projectId}/users/role/{$roleId}", $params);
        if ($res->failed()) {
            throw new \RuntimeException($res->json('message') ?? 'No se pudo obtener usuarios por rol del proyecto', $res->status());
        }
        return $res->json();
    }

    /**
     * Usuarios del proyecto (ideal: que la API ya incluya roles del usuario EN ESE proyecto)
     * Acepta filtros/paginación: page, per_page, busqueda, rol (id), activo, etc.
     */
    public function getProjectUsers(int $projectId, array $params = []): array {
        $token = $this->getToken(); if(!$token) throw new \RuntimeException('No autenticado',401);
        $res = $this->client($token)->get("/api/project/{$projectId}/users", $params);
        if ($res->failed()) throw new \RuntimeException($res->json('message') ?? 'No se pudo obtener usuarios del proyecto', $res->status());
        return $res->json();
    }

    /** (Opcional) Listar todos los proyectos, con paginación/filters si tu API lo soporta */
    public function listProjects(array $params = []): array
    {
        $token = $this->getToken();
        if (!$token) throw new \RuntimeException('No autenticado', 401);

        $res = $this->client($token)->get('/api/projects', $params);
        if ($res->failed()) {
            throw new \RuntimeException($res->json('message') ?? 'Error al listar proyectos', $res->status());
        }
        return $res->json();
    }

// ENDPOINTS DE ROLES
    /** Obtener un rol por ID */
    public function getRole(int $id): array
    {
        $token = $this->getToken();
        if (!$token) throw new \RuntimeException('No autenticado', 401);

        $res = $this->client($token)->get("/api/role/{$id}");
        if ($res->failed()) {
            throw new \RuntimeException($res->json('message') ?? 'No se pudo obtener el rol', $res->status());
        }
        return $res->json();
    }

    /** Obtener un rol por clave + proyecto_id */
    public function getRoleByClaveAndProyecto(string $clave, int $proyectoId): array
    {
        $token = $this->getToken();
        if (!$token) throw new \RuntimeException('No autenticado', 401);

        $res = $this->client($token)->get("/api/role/clave/{$clave}/proyecto/{$proyectoId}");
        if ($res->failed()) {
            throw new \RuntimeException($res->json('message') ?? 'No se pudo obtener el rol por clave/proyecto', $res->status());
        }
        return $res->json();
    }

    /** (Opcional) Listar roles (global) o por proyecto si tu API acepta ?proyecto_id= */
    public function listRoles(array $params = []): array
    {
        $token = $this->getToken();
        if (!$token) throw new \RuntimeException('No autenticado', 401);

        $res = $this->client($token)->get('/api/roles', $params);
        if ($res->failed()) {
            throw new \RuntimeException($res->json('message') ?? 'Error al listar roles', $res->status());
        }
        return $res->json();
    }

// ENDPOINTS DE AUTENTIFICACIÓN
    private function client(?string $token = null)
    {
        $req = Http::baseUrl($this->base)->acceptJson();
        if ($token) {
            $req = $req->withToken($token); // Authorization: Bearer <token>
        }
        return $req;
    }

    private function getToken(): ?string
    {
        return session('usuarios_api.token');
    }

    private function putToken(string $token): void
    {
        session(['usuarios_api.token' => $token]);
    }

    private function clearToken(): void
    {
        session()->forget('usuarios_api.token');
    }

    /** 1) Login: pedir token a la API */
    public function login(string $email, string $password, string $deviceName = 'frontend-app'): void
    {
        $res = $this->client()->post('/api/auth/token', [
            'email'       => $email,
            'password'    => $password,
            'device_name' => $deviceName,
        ]);

        if ($res->failed()) {
            throw new \RuntimeException($res->json('message') ?? 'Login failed', $res->status());
        }

        $token = $res->json('token');
        if (!is_string($token) || $token === '') {
            throw new \RuntimeException('Token inválido');
        }

        $this->putToken($token);

        // ← Trae el usuario UNA sola vez y guárdalo en sesión
        $me = $this->client($token)->get('/api/user')->json();
        session([
            'usuarios_api.user'      => $me,
            'usuarios_api.user_fresh'=> now()->timestamp, // para expiración
        ]);
    }

    /** 2) Logout: revocar token en la API y limpiar sesión */
    public function logout(): void
    {
        $token = $this->getToken();
        if ($token) {
            // Revocá el token actual (ignorar errores de red)
            try { $this->client($token)->post('/api/auth/logout'); } catch (\Throwable) {}
        }
        $this->clearToken();
        session()->forget(['usuarios_api.user','usuarios_api.user_fresh']);
    }

    // helpers
    public function sessionUser(): ?array
    {
        return session('usuarios_api.user');
    }
    public function refreshSessionUser(): ?array
    {
        $token = $this->getToken();
        if (!$token) return null;

        $me = $this->client($token)->get('/api/user')->json();
        session([
            'usuarios_api.user'      => $me,
            'usuarios_api.user_fresh'=> now()->timestamp,
        ]);
        return $me;
    }
}
