<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\ApiAuthController;
use App\Http\Controllers\IndexController;
use App\Http\Controllers\Admin\TallerController;
use App\Http\Controllers\Admin\OrganizadorController;
use App\Http\Controllers\Admin\ClaseController;
use App\Http\Controllers\Organizador\TallerController as OrgTallerController;
use App\Http\Controllers\Organizador\ClaseController as OrgClaseController;
use App\Http\Controllers\Docente\ClaseController as DocClaseController;
use App\Http\Controllers\Admin\DocenteController;

// Rutas Públicas
Route::get('/', function (\App\Services\UsuariosApiService $api) {
    try {
        $user = $api->me(); // si no está logeado va a fallar
        if ($user) {
            return redirect()->route('dashboard');
        }
    } catch (\Throwable $e) {
        // no logeado → Welcome
    }

    return Inertia::render('Welcome', [
        'canLogin' => Route::has('auth_api.login'),
        'canRegister' => false,
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware('api.auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Rutas de autenticación usando la API
Route::prefix('auth_api')->name('auth_api.')->group(function () {
    Route::get('/login', [ApiAuthController::class, 'login_redirect'])->name('login');
    Route::post('/login', [ApiAuthController::class, 'api_login'])->name('login.post');
    Route::get('/auth', [ApiAuthController::class, 'api_auth_info'])->name('auth');
    Route::post('/logout', [ApiAuthController::class, 'api_logout'])->name('logout');
});

// Rutas Generales de Navegación
Route::middleware(['api.auth'])->group(function () {
    Route::get('/dashboard', [IndexController::class, 'index'])->name('dashboard');
});

// Rutas de admin
Route::middleware(['api.auth', 'api.rol:Admin'])->prefix('admin')->name('admin.')->group(function () {
    // Talleres
    Route::get('/talleres', [TallerController::class, 'index'])->name('talleres.index');
    Route::post('/talleres', [TallerController::class, 'store'])->name('talleres.store');
    Route::match(['put', 'patch'], '/talleres/{taller}', [TallerController::class, 'update'])->name('talleres.update');
    Route::delete('/talleres/{taller}', [TallerController::class, 'destroy'])->name('talleres.destroy');
    Route::patch('/talleres/{id}/restore', [TallerController::class, 'restore'])->name('talleres.restore');

    // Organizadores
    Route::get('/organizadores', [OrganizadorController::class, 'index'])->name('organizadores.index');
    Route::post('/organizadores', [OrganizadorController::class, 'store'])->name('organizadores.store');
    Route::match(['put', 'patch'], '/organizadores/{organizador}', [OrganizadorController::class, 'update'])->name('organizadores.update');
    Route::delete('/organizadores/{organizador}', [OrganizadorController::class, 'destroy'])->name('organizadores.destroy');
    Route::patch('/organizadores/{ci}/restore', [OrganizadorController::class, 'restore'])->name('organizadores.restore');

    // Auxiliares para el modal (prefill y check de existencia) de DOCENTES
    Route::get('docentes/persona/{ci}', [DocenteController::class, 'persona'])
        ->name('docentes.persona');
    Route::get('docentes/existe/{ci}', [DocenteController::class, 'existe'])
        ->name('docentes.existe');

    // Listado + alta/sync (Docentes)
    Route::resource('docentes', DocenteController::class)
        ->only(['index', 'store']);

    // Clases
    Route::get('/clases', [ClaseController::class, 'index'])->name('clases.index');
    Route::post('/clases', [ClaseController::class, 'store'])->name('clases.store');
    Route::match(['put', 'patch'], '/clases/{clase}', [ClaseController::class, 'update'])->name('clases.update');
    Route::delete('/clases/{clase}', [ClaseController::class, 'destroy'])->name('clases.destroy');

    // Docentes - búsqueda (autocomplete para el modal de Clase)
    Route::get('/docentes/buscar', [DocenteController::class, 'buscar'])->name('docentes.buscar');
    Route::delete('/admin/docentes/{docente}', [DocenteController::class, 'destroy'])->name('docentes.destroy');
    Route::patch('/admin/docentes/{docente}/restore', [DocenteController::class, 'restore'])->name('docentes.restore');
});

Route::middleware(['api.auth', 'api.rol:organizador'])->prefix('organizador')->name('org.')->group(function () {
    // LISTADO DE TALLERES (organizador)
    Route::get('/talleres', [OrgTallerController::class, 'index'])
        ->name('talleres.index');

    // LISTADO/GESTIÓN DE CLASES (organizador)
    Route::get('/clases', [OrgClaseController::class, 'index'])
        ->name('clases.index');
});

Route::middleware(['api.auth', 'api.rol:docente'])->prefix('docente')->name('doc.')->group(function () {
    // Mis clases (listado del docente)
    Route::get('/clases', [DocClaseController::class, 'index'])->name('clases.index');

    // Gestión de asistencia: primero elegir la clase (lista filtrable)
    Route::get('/clases/gestion', [DocClaseController::class, 'gestion'])->name('clases.gestion');

    // Auxiliares para el modal de Organizadores
    Route::get('/organizadores/persona/{ci}', [OrganizadorController::class, 'persona'])
        ->name('organizadores.persona');   // proxy a api_personas (por CI)

    Route::get('/organizadores/existe/{ci}', [OrganizadorController::class, 'existe'])
        ->name('organizadores.existe');    // existe en tabla local

    // Ranking de docentes por taller (para el modal de clases)
    Route::get('/docentes/top', [\App\Http\Controllers\Admin\DocenteController::class, 'top'])
        ->name('docentes.top');

    // Lista de docentes (para selector)
    Route::get('/api/docentes', [\App\Http\Controllers\Docente\CalendarioController::class, 'docentes'])
        ->name('api.docentes.list');

    // Clases por docente en rango
    Route::get('/api/docentes/{ci}/clases', [\App\Http\Controllers\Docente\CalendarioController::class, 'clasesInRange'])
        ->name('api.docentes.clases.range');

    Route::get('/api/clases/{clase}/asistentes', [\App\Http\Controllers\Docente\CalendarioController::class, 'asistentes'])
        ->name('api.clases.asistentes');

    Route::patch('/api/clases/{clase}/asistentes/{ci}', [\App\Http\Controllers\Docente\CalendarioController::class, 'updateAsistencia'])
        ->name('api.clases.asistentes.update');
});

Route::prefix('organizador')->name('organizador.')->group(function () {
    Route::get('/talleres', [OrgTallerController::class, 'index'])
        ->name('talleres.index');

    Route::get('/clases', [OrgClaseController::class, 'index'])
        ->name('clases.index');

    Route::match(['put', 'patch'], '/talleres/{taller}', [OrgTallerController::class, 'update'])
        ->name('talleres.update');
});

require __DIR__ . '/auth.php';
