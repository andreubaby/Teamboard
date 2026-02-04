<?php

use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\CardController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', function (Illuminate\Http\Request $request) {
    return $request->user();
});

Route::get('/version', function () {
    return ['Laravel' => app()->version()];
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::get('/projects/{project}/board', [ProjectController::class, 'board']);

    Route::post('/cards', [CardController::class, 'store']);
    Route::patch('/cards/{card}/move', [CardController::class, 'move']);
});
