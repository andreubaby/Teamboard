<?php

use App\Models\Project;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('project.{projectId}', function ($user, $projectId) {
    return Project::where('id', $projectId)
        ->where('owner_id', $user->id)
        ->exists();
});

// ✅ Sidebar: solo el dueño escucha sus boards
Broadcast::channel('boards.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
