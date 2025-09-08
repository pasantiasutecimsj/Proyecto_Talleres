<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\ApiAuthController;
use App\Http\Controllers\IndexController;

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

require __DIR__.'/auth.php';
