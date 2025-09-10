<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class TallerController extends Controller
{
    /**
     * GET /admin/talleres
     * Página de listado (vacía por ahora)
     */
    public function index()
    {
        return Inertia::render('Admin/Talleres/Index', [
            'title' => 'Talleres (Administrador)',
        ]);
    }
}
