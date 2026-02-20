<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Card;
use Illuminate\Http\Request;
use App\Events\CommentAdded;
use Illuminate\Support\Facades\Log;

class CommentController extends Controller
{
    public function store(Request $request, Card $card)
    {
        $request->validate([
            'content' => ['required', 'string', 'max:1000'],
        ]);

        // Cargar la columna y el proyecto para verificar permisos
        $card->load('column.project.members');
        $project = $card->column->project;
        $userId = $request->user()->id;

        $isMember = $project->owner_id === $userId || $project->members->contains('id', $userId);
        abort_unless($isMember, 403);

        // Crear el comentario
        $comment = $card->comments()->create([
            'user_id' => $userId,
            'content' => $request->input('content'),
        ]);

        // Cargar el usuario para que Vue tenga su nombre y avatar
        $comment->load('user');

        // Emitir el WebSocket a los demás
        try {
            broadcast(new CommentAdded($project->id, $comment))->toOthers();
        } catch (\Exception $e) {
            Log::error("Error broadcast CommentAdded: " . $e->getMessage());
        }

        return response()->json([
            'comment' => $comment
        ], 201);
    }
}
