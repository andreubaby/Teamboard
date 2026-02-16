<?php

namespace App\Http\Controllers\Api;

use App\Events\ColumnCreated;
use App\Events\ColumnReordered;
use App\Http\Controllers\Controller;
use App\Models\BoardColumn;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BoardColumnController extends Controller
{
    // POST /api/projects/{project}/columns
    public function store(Request $request, Project $project)
    {
        $project->load('members');
        $isMember = $project->owner_id === $request->user()->id || $project->members->contains('id', $request->user()->id);
        abort_unless($isMember, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $nextPos = (int) BoardColumn::where('project_id', $project->id)->max('position');
        $nextPos = ($nextPos || $nextPos === 0) ? $nextPos + 1 : 0;

        $col = BoardColumn::create([
            'project_id' => $project->id,
            'name' => $data['name'],
            'position' => $nextPos,
        ]);

        broadcast(new ColumnCreated(
            projectId: $project->id,
            column: [
                'id' => $col->id,
                'name' => $col->name,
                'position' => $col->position,
                'cards' => [],
            ],
            senderId: (int) $request->user()->id,
        ))->toOthers();

        return response()->json([
            'column' => [
                'id' => $col->id,
                'name' => $col->name,
                'position' => $col->position,
            ],
        ], 201);
    }

    // PATCH /api/columns/{column}
    public function update(Request $request, BoardColumn $column)
    {
        $column->load('project.members');
        $isMember = $column->project->owner_id === $request->user()->id || $column->project->members->contains('id', $request->user()->id);
        abort_unless($isMember, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $column->update(['name' => $data['name']]);

        return response()->json([
            'column' => [
                'id' => $column->id,
                'name' => $column->name,
                'position' => $column->position,
            ],
        ]);
    }

    // DELETE /api/columns/{column}
    public function destroy(Request $request, BoardColumn $column)
    {
        $column->load('project.members');
        $isMember = $column->project->owner_id === $request->user()->id || $column->project->members->contains('id', $request->user()->id);
        abort_unless($isMember, 403);

        $projectId = (int) $column->project_id;
        $deletedPos = (int) $column->position;

        return DB::transaction(function () use ($column, $projectId, $deletedPos) {

            // borrar cards (si no hay cascade)
            $column->cards()->delete();
            $column->delete();

            // cerrar hueco de posiciones (0..N-1)
            BoardColumn::where('project_id', $projectId)
                ->where('position', '>', $deletedPos)
                ->update(['position' => DB::raw('position - 1')]);

            return response()->json(['ok' => true]);
        });
    }

    /**
     * PATCH /api/projects/{project}/columns/reorder
     * Body: { "ordered_ids": [5,2,9,...] }
     */
    public function reorder(Request $request, Project $project)
    {
        $project->load('members');
        $isMember = $project->owner_id === $request->user()->id || $project->members->contains('id', $request->user()->id);
        abort_unless($isMember, 403);

        $data = $request->validate([
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['integer', 'distinct'],
        ]);

        $ordered = array_values(array_map('intval', $data['ordered_ids']));

        return DB::transaction(function () use ($project, $ordered, $request) {

            // 1. Obtener IDs existentes para validar
            $existing = BoardColumn::where('project_id', $project->id)
                ->orderBy('position')
                ->pluck('id')
                ->map(fn ($x) => (int) $x)
                ->all();

            sort($existing);
            $sortedOrdered = $ordered;
            sort($sortedOrdered);

            if ($existing !== $sortedOrdered) {
                return response()->json([
                    'message' => 'ordered_ids no coincide con las columnas del proyecto',
                ], 422);
            }

            // 2. Actualizar posiciones (Lógica de SQL Case/Offset)
            // Primero un offset para evitar colisiones de UNIQUE constraints temporales
            $OFFSET = 1000000;
            BoardColumn::where('project_id', $project->id)
                ->update(['position' => DB::raw("position + {$OFFSET}")]);

            // Aplicar el nuevo orden
            foreach ($ordered as $pos => $id) {
                BoardColumn::where('project_id', $project->id)
                    ->where('id', $id)
                    ->update(['position' => $pos]);
            }

            // 3. BROADCAST PROTEGIDO (Aquí estaba el error 500)
            try {
                broadcast(new ColumnReordered(
                    projectId: (int) $project->id,
                    orderedIds: $ordered,
                    senderId: (int) $request->user()->id,
                ))->toOthers();
            } catch (\Exception $e) {
                // Si falla Reverb, solo lo logueamos, pero NO rompemos la transacción
                \Illuminate\Support\Facades\Log::error("⚠️ Error en Broadcast Reorder: " . $e->getMessage());
            }

            return response()->json(['ok' => true]);
        });
    }
}
