<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BoardColumn;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Events\BoardCreated;

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

    // POST /api/projects
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        // Wrap in transaction for safety
        $project = DB::transaction(function () use ($request, $data) {
            $project = Project::create([
                'owner_id' => $request->user()->id,
                'name' => $data['name'],
            ]);

            $defaults = ['To Do', 'In Progress', 'Done'];

            foreach ($defaults as $i => $name) {
                BoardColumn::create([
                    'project_id' => $project->id,
                    'name' => $name,
                    'position' => $i,
                ]);
            }

            return $project;
        });

        // Broadcast event
        broadcast(new BoardCreated(
            ownerId: (int) $request->user()->id,
            project: [
                'id' => $project->id,
                'name' => $project->name,
                'owner_id' => $project->owner_id,
                'created_at' => $project->created_at,
            ],
            senderId: (int) $request->user()->id,
        ))->toOthers();

        return response()->json([
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'created_at' => $project->created_at,
            ],
        ], 201);
    }

    // PATCH /api/projects/{project}
    public function update(Request $request, Project $project)
    {
        abort_unless($project->owner_id === $request->user()->id, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $project->update([
            'name' => $data['name'],
        ]);

        return response()->json([
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
            ],
        ]);
    }

    // DELETE /api/projects/{project}
    public function destroy(Request $request, Project $project)
    {
        abort_unless($project->owner_id === $request->user()->id, 403);

        // Load relationships to delete children manually if cascade is not set in DB
        // But assuming cascade is set in DB migration, simple delete works.
        // If not, use this logic to be safe:
        $project->columns->each(function($column) {
            $column->cards()->delete();
            $column->delete();
        });

        $project->delete();

        return response()->json(['ok' => true]);
    }

    // GET /api/projects/{project}/board
    public function board(Request $request, Project $project)
    {
        abort_unless($project->owner_id === $request->user()->id, 403);

        // ✅ UPDATE: Load 'tags' relationship for cards
        $project->load([
            'columns' => function ($q) {
                $q->orderBy('position')
                    ->with(['cards' => function($c) {
                        $c->orderBy('position')
                            ->with('tags'); // <--- NEW: Eager load tags
                    }]);
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
                            'priority' => $card->priority, // <--- NEW: Return priority
                            'tags' => $card->tags,         // <--- NEW: Return tags array
                        ];
                    })->values(),
                ];
            })->values(),
        ]);
    }
}
