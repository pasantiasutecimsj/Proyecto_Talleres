<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class ClaseController extends Controller
{
    /**
     * GET /docente/clases
     * Listado de "mis clases" (placeholder)
     */
    public function index()
    {
        return Inertia::render('Docente/Clases/Index', [
            'title' => 'Mis clases (Docente)',
        ]);
    }

    /**
     * GET /docente/clases/gestion
     * Pantalla para elegir una clase y luego gestionar asistencia en el detalle
     */
    public function gestion()
    {
        return Inertia::render('Docente/Clases/Gestion', [
            'title' => 'Asistentes por clase (Docente)',
        ]);
    }
    
}
