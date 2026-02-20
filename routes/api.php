<?php

use App\Http\Controllers\Api\ProfileController;
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
    // MEMBERS
    // -------------------------
    Route::get('/projects/{project}/members', [App\Http\Controllers\Api\ProjectMemberController::class, 'index']);
    Route::post('/projects/{project}/members', [App\Http\Controllers\Api\ProjectMemberController::class, 'store']);
    Route::delete('/projects/{project}/members/{user}', [App\Http\Controllers\Api\ProjectMemberController::class, 'destroy']);

    // -------------------------
    // CARDS
    // -------------------------
    Route::post('/cards', [CardController::class, 'store']);
    Route::patch('/cards/{card}/move', [CardController::class, 'move']);
    Route::patch('/cards/{card}', [CardController::class, 'update']);
    Route::delete('/cards/{card}', [CardController::class, 'destroy']);

    // -------------------------
    // TAGS (via CardController)
    // -------------------------
    Route::get('/tags', [CardController::class, 'getTags']);
    Route::post('/cards/{card}/tags', [CardController::class, 'addTag']);
    Route::delete('/cards/{card}/tags/{tag}', [CardController::class, 'removeTag']);

    // -------------------------
    // AI (Harvis)
    // -------------------------
    // ✅ 2. Nueva ruta para generar tareas con IA
    Route::post('/ai/handle', [AiController::class, 'handleRequest']);
    Route::post('/ai/global', [AiController::class, 'handleGlobalRequest']);

    // -------------------------
    // COMENTARIOS
    // -------------------------
    Route::post('/cards/{card}/comments', [App\Http\Controllers\Api\CommentController::class, 'store']);

    // NOTIFICACIONES (TIENEN QUE ESTAR AQUÍ DENTRO)
    Route::get('/notifications', function (\Illuminate\Http\Request $request) {
        return response()->json($request->user()->unreadNotifications);
    });

    Route::post('/notifications/{id}/read', function (\Illuminate\Http\Request $request, $id) {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();
        return response()->json(['ok' => true]);
    });

    Route::post('/notifications/read-all', function (\Illuminate\Http\Request $request) {
        $request->user()->unreadNotifications->markAsRead();
        return response()->json(['ok' => true]);
    });

    //Perfil
    Route::post('/profile/update', [ProfileController::class, 'update']);
});
