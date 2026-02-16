<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\BoardColumnController;
use App\Http\Controllers\Api\CardController;
use App\Http\Controllers\Api\AiController; // ✅ 1. Importamos el controlador

Route::middleware('auth:sanctum')->get('/user', function (Illuminate\Http\Request $request) {
    return $request->user();
});

Route::get('/version', fn () => ['Laravel' => app()->version()]);

Route::middleware('auth:sanctum')->group(function () {

    // -------------------------
    // BOARDS (projects)
    // -------------------------
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::patch('/projects/{project}', [ProjectController::class, 'update']);
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy']);

    Route::get('/projects/{project}/board', [ProjectController::class, 'board']);

    // -------------------------
    // COLUMNAS
    // -------------------------
    Route::post('/projects/{project}/columns', [BoardColumnController::class, 'store']);
    Route::patch('/columns/{column}', [BoardColumnController::class, 'update']);
    Route::delete('/columns/{column}', [BoardColumnController::class, 'destroy']);
    Route::patch('/projects/{project}/columns/reorder', [BoardColumnController::class, 'reorder']);

    // -------------------------
    // CARDS
    // -------------------------
    Route::post('/cards', [CardController::class, 'store']);
    Route::patch('/cards/{card}/move', [CardController::class, 'move']);
    Route::patch('/cards/{card}', [CardController::class, 'update']);
    Route::delete('/cards/{card}', [CardController::class, 'destroy']);

    // -------------------------
    // AI (Harvis)
    // -------------------------
    // ✅ 2. Nueva ruta para generar tareas con IA
    Route::post('/ai/handle', [AiController::class, 'handleRequest']);
    Route::post('/ai/global', [AiController::class, 'handleGlobalRequest']);

});
