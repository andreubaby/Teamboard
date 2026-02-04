<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});

// Auth API (stateful) — antes del catch-all
Route::post('/api/login', [AuthController::class, 'login'])->name('api.login');
Route::post('/api/logout', [AuthController::class, 'logout'])->name('api.logout');
Route::post('/api/register', [AuthController::class, 'register'])->name('api.register');

// SPA al final
Route::view('/{any}', 'app')->where('any', '.*');
