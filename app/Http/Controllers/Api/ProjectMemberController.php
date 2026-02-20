<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use App\Events\BoardRefreshed;

class ProjectMemberController extends Controller
{
    /**
     * List all members of the project.
     */
    public function index(Request $request, Project $project)
    {
        // ✅ CORRECCIÓN: 'users.id' en lugar de 'id'
        $isMember = $project->members()->where('users.id', $request->user()->id)->exists();
        abort_unless($project->owner_id === $request->user()->id || $isMember, 403);

        return response()->json([
            'members' => $project->members,
            'owner'   => $project->owner,
        ]);
    }

    /**
     * Add a member to the project by email.
     */
    public function store(Request $request, Project $project)
    {
        // Only owner can add members
        abort_unless($project->owner_id === $request->user()->id, 403);

        $data = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
        ]);

        $user = User::where('email', $data['email'])->firstOrFail();

        // Prevent adding owner as member
        if ($user->id === $project->owner_id) {
            return response()->json(['message' => 'Este usuario es el dueño del tablero'], 422);
        }

        // ✅ CORRECCIÓN: 'users.id' en lugar de 'id'
        if ($project->members()->where('users.id', $user->id)->exists()) {
            return response()->json(['message' => 'El usuario ya es miembro de este tablero'], 422);
        }

        $project->members()->attach($user->id);

        // Notify others to refresh board data
        broadcast(new BoardRefreshed($project->id))->toOthers();

        return response()->json([
            'message' => 'Usuario invitado correctamente',
            'member' => $user,
        ]);
    }

    /**
     * Remove a member from the project.
     */
    public function destroy(Request $request, Project $project, User $user)
    {
        // Only owner can remove members
        abort_unless($project->owner_id === $request->user()->id, 403);

        $project->members()->detach($user->id);

        // Unassign cards assigned to this user in this project
        $project->columns->each(function($column) use ($user) {
            $column->cards()->where('assignee_id', $user->id)->update(['assignee_id' => null]);
        });

        // Notify others
        broadcast(new BoardRefreshed($project->id))->toOthers();

        return response()->json(['message' => 'Member removed successfully']);
    }
}
