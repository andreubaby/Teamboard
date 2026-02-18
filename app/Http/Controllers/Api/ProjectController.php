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
        $userId = $request->user()->id;

        // Find projects where user is owner OR a member
        $projects = Project::query()
            ->where('owner_id', $userId)
            ->orWhereHas('members', function($q) use ($userId) {
                $q->where('users.id', $userId);
            })
            ->distinct()
            ->orderBy('id', 'desc')
            ->get(['projects.id', 'projects.name', 'projects.owner_id', 'projects.created_at']);

        return response()->json([
            'data' => $projects,
        ]);
    }

    // POST /api/projects
    public function store(Request $request)
    {
        \Illuminate\Support\Facades\Log::info("Iniciando creación de proyecto para user: " . $request->user()->id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        try {
            // Wrap in transaction for safety
            $project = DB::transaction(function () use ($request, $data) {
                \Illuminate\Support\Facades\Log::info("Creando proyecto en DB...");
                $project = Project::create([
                    'owner_id' => $request->user()->id,
                    'name' => $data['name'],
                ]);
                \Illuminate\Support\Facades\Log::info("Proyecto creado ID: " . $project->id);

                $defaults = ['To Do', 'In Progress', 'Done'];

                foreach ($defaults as $i => $name) {
                    BoardColumn::create([
                        'project_id' => $project->id,
                        'name' => $name,
                        'position' => $i,
                    ]);
                }
                \Illuminate\Support\Facades\Log::info("Columnas creadas.");

                return $project;
            });

            // Broadcast event
            \Illuminate\Support\Facades\Log::info("Broadcasting evento Project ID: " . $project->id);
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

            \Illuminate\Support\Facades\Log::info("Evento broadcasted.");

            return response()->json([
                'project' => [
                    'id' => $project->id,
                    'name' => $project->name,
                    'created_at' => $project->created_at,
                ],
            ], 201);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Error creando proyecto: " . $e->getMessage());
            \Illuminate\Support\Facades\Log::error($e->getTraceAsString());
            return response()->json(['error' => 'Error interno al crear el proyecto'], 500);
        }
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
        $userId = $request->user()->id;
        $isMember = $project->owner_id === $userId || $project->members()->where('users.id', $userId)->exists();
        abort_unless($isMember, 403);

        // ✅ UPDATE: Eager load tags, assignee, AND comments with their users
        $project->load([
            'members',
            'columns' => function ($q) {
                $q->orderBy('position')
                    ->with(['cards' => function($c) {
                        $c->orderBy('position')
                            ->with(['tags', 'assignee', 'comments.user']); // <--- NUEVO
                    }]);
            },
        ]);

        return response()->json([
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'owner_id' => $project->owner_id,
                'owner' => [
                    'id' => $project->owner->id,
                    'name' => $project->owner->name,
                    'email' => $project->owner->email,
                    'avatar_url' => $project->owner->avatar_url,
                ],
                'members' => $project->members->map(fn($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'avatar_url' => $u->avatar_url,
                ]),
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
                            'priority' => $card->priority,
                            'tags' => $card->tags,
                            'assignee' => $card->assignee ? [
                                'id' => $card->assignee->id,
                                'name' => $card->assignee->name,
                                'email' => $card->assignee->email,
                                'avatar_url' => $card->assignee->avatar_url,
                            ] : null,
                            'assignee_id' => $card->assignee_id,
                            // 👇 NUEVO: Mapeo de comentarios 👇
                            'comments' => $card->comments->map(function ($comment) {
                                return [
                                    'id' => $comment->id,
                                    'content' => $comment->content,
                                    'created_at' => $comment->created_at,
                                    'user' => [
                                        'id' => $comment->user->id,
                                        'name' => $comment->user->name,
                                        'avatar_url' => $comment->user->avatar_url,
                                    ]
                                ];
                            })->values(),
                        ];
                    })->values(),
                ];
            })->values(),
        ]);
    }
}
