<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class ClaseController extends Controller
{
    /**
     * GET /admin/clases
     * Página de listado (vacía por ahora)
     */
    public function index()
    {
        return Inertia::render('Admin/Clases/Index', [
            'title' => 'Clases (Administrador)',
        ]);
    }
}
