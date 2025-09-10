<?php

namespace App\Http\Controllers\Organizador;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class TallerController extends Controller
{
    /**
     * GET /organizador/talleres
     * Listado/mantenimiento de talleres (placeholder)
     */
    public function index()
    {
        return Inertia::render('Organizador/Talleres/Index', [
            'title' => 'Talleres (Organizador)',
        ]);
    }
}
