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
Route::prefix('admin')->name('admin.')->group(function () {

    // LISTADO DE TALLERES (index)
    Route::get('/talleres', [TallerController::class, 'index'])
        ->name('talleres.index');

    // LISTADO DE ORGANIZADORES (index)
    Route::get('/organizadores', [OrganizadorController::class, 'index'])
        ->name('organizadores.index');

    // Listado de clases
    Route::get('/clases', [ClaseController::class, 'index'])
        ->name('clases.index');
});

Route::prefix('organizador')->name('org.')->group(function () {
    // LISTADO DE TALLERES (organizador)
    Route::get('/talleres', [OrgTallerController::class, 'index'])
        ->name('talleres.index');

    // LISTADO/GESTIÓN DE CLASES (organizador)
    Route::get('/clases', [OrgClaseController::class, 'index'])
        ->name('clases.index');
});

Route::prefix('docente')->name('doc.')->group(function () {
    // Mis clases (listado del docente)
    Route::get('/clases', [DocClaseController::class, 'index'])->name('clases.index');

    // Gestión de asistencia: primero elegir la clase (lista filtrable)
    Route::get('/clases/gestion', [DocClaseController::class, 'gestion'])->name('clases.gestion');

});


require __DIR__ . '/auth.php';
