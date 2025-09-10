<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class OrganizadorController extends Controller
{
    /**
     * GET /admin/organizadores
     * Página de listado (vacía por ahora)
     */
    public function index()
    {
        return Inertia::render('Admin/Organizadores/Index', [
            'title' => 'Organizadores (Administrador)',
        ]);
    }
}
