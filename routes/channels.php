<?php

use App\Models\Project;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('project.{projectId}', function ($user, $projectId) {
    return Project::where('id', $projectId)
        ->where('owner_id', $user->id)
        ->exists();
});
