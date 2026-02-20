<?php

use App\Models\Project;
use Illuminate\Support\Facades\Broadcast;

// ✅ El dueño Y los miembros pueden escuchar el tablero
Broadcast::channel('project.{projectId}', function ($user, $projectId) {
    $project = Project::find($projectId);

    if (!$project) {
        return false;
    }

    // Devolverá TRUE si el usuario es el dueño, O si existe en la relación de miembros
    return $project->owner_id === $user->id ||
        $project->members()->where('users.id', $user->id)->exists();
});

// ✅ Sidebar: cada usuario escucha sus propios eventos de tableros generales
Broadcast::channel('boards.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
