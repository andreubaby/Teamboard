<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    // GET /api/projects
    public function index(Request $request)
    {
        $projects = Project::query()
            ->where('owner_id', $request->user()->id)
            ->orderBy('id', 'desc')
            ->get(['id', 'name', 'owner_id', 'created_at']);

        return response()->json([
            'data' => $projects,
        ]);
    }

    // GET /api/projects/{project}/board
    public function board(Request $request, Project $project)
    {
        // Seguridad: que el proyecto sea del usuario
        abort_unless($project->owner_id === $request->user()->id, 403);

        $project->load([
            'columns' => function ($q) {
                $q->orderBy('position')
                    ->with(['cards' => fn($c) => $c->orderBy('position')]);
            },
        ]);

        return response()->json([
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
            ],
            'columns' => $project->columns->map(function ($col) {
                return [
                    'id' => $col->id,
                    'name' => $col->name,
                    'position' => $col->position,
                    'cards' => $col->cards->map(function ($card) {
                        return [
                            'id' => $card->id,
                            'title' => $card->title,
                            'description' => $card->description,
                            'position' => $card->position,
                        ];
                    })->values(),
                ];
            })->values(),
        ]);
    }
}
