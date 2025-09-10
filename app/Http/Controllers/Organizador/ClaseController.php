<?php

namespace App\Http\Controllers\Organizador;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class ClaseController extends Controller
{
    /**
     * GET /organizador/clases
     * Listado/gestión de clases (placeholder)
     */
    public function index()
    {
        return Inertia::render('Organizador/Clases/Index', [
            'title' => 'Clases (Organizador)',
        ]);
    }
}
