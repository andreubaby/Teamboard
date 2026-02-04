<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\BoardColumnController;
use App\Http\Controllers\Api\CardController;

Route::middleware('auth:sanctum')->get('/user', function (Illuminate\Http\Request $request) {
    return $request->user();
});

Route::get('/version', fn () => ['Laravel' => app()->version()]);

Route::middleware('auth:sanctum')->group(function () {

    // -------------------------
    // BOARDS (projects)
    // -------------------------
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);              // crear board
    Route::patch('/projects/{project}', [ProjectController::class, 'update']);  // renombrar
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy']); // borrar

    // cargar board (proyecto + columnas + cards)
    Route::get('/projects/{project}/board', [ProjectController::class, 'board']);

    // -------------------------
    // COLUMNAS
    // -------------------------
    // crear columna en un board
    Route::post('/projects/{project}/columns', [BoardColumnController::class, 'store']);

    // editar / borrar columna
    Route::patch('/columns/{column}', [BoardColumnController::class, 'update']);
    Route::delete('/columns/{column}', [BoardColumnController::class, 'destroy']);

    // reordenar columnas (drag & drop columnas)
    Route::patch('/projects/{project}/columns/reorder', [BoardColumnController::class, 'reorder']);

    // -------------------------
    // CARDS
    // -------------------------
    Route::post('/cards', [CardController::class, 'store']);
    Route::patch('/cards/{card}/move', [CardController::class, 'move']);

    // (opcional pero muy útil)
    Route::patch('/cards/{card}', [CardController::class, 'update']);      // editar título/desc
    Route::delete('/cards/{card}', [CardController::class, 'destroy']);    // borrar
});
