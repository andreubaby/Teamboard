<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BoardColumn;
use App\Models\Card;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CardController extends Controller
{
    // POST /api/cards
    public function store(Request $request)
    {
        $data = $request->validate([
            'board_column_id' => ['required', 'integer', 'exists:board_columns,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $column = BoardColumn::with('project')->findOrFail($data['board_column_id']);
        abort_unless($column->project->owner_id === $request->user()->id, 403);

        $nextPos = (int) Card::where('board_column_id', $column->id)->max('position');
        $nextPos = ($nextPos || $nextPos === 0) ? $nextPos + 1 : 0;

        $card = Card::create([
            'board_column_id' => $column->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'position' => $nextPos,
        ]);

        broadcast(new \App\Events\CardCreated(
            $column->project_id,
            [
                'id' => $card->id,
                'title' => $card->title,
                'description' => $card->description,
                'position' => $card->position,
                'board_column_id' => $card->board_column_id,
            ],
            $column->id
        ))->toOthers();

        return response()->json([
            'card' => [
                'id' => $card->id,
                'title' => $card->title,
                'description' => $card->description,
                'position' => $card->position,
                'board_column_id' => $card->board_column_id,
            ],
        ], 201);
    }

    // PATCH /api/cards/{card}/move
    // PATCH /api/cards/{card}/move
    public function move(Request $request, Card $card)
    {
        $data = $request->validate([
            'to_board_column_id' => ['required', 'integer', 'exists:board_columns,id'],
            'to_position'        => ['required', 'integer', 'min:0'],
        ]);

        $toColumnId = (int) $data['to_board_column_id'];
        $toPos      = (int) $data['to_position'];

        $fromColumn = BoardColumn::with('project')->findOrFail($card->board_column_id);
        abort_unless($fromColumn->project->owner_id === $request->user()->id, 403);

        $toColumn = BoardColumn::with('project')->findOrFail($toColumnId);
        abort_unless($toColumn->project->owner_id === $request->user()->id, 403);

        $fromColumnId = (int) $card->board_column_id;
        $projectId    = (int) $toColumn->project_id;

        $OFFSET = 1000000; // siempre positivo (seguro para UNSIGNED)
        $senderId = (int) $request->user()->id;

        return DB::transaction(function () use (
            $card, $projectId, $fromColumnId, $toColumnId, $toPos, $OFFSET, $senderId
        ) {
            // Lock card
            $card = Card::whereKey($card->id)->lockForUpdate()->firstOrFail();

            // Lock columns (evita carreras)
            BoardColumn::whereIn('id', array_unique([$fromColumnId, $toColumnId]))
                ->lockForUpdate()
                ->get();

            // IDs ordenados origen
            $fromIds = Card::where('board_column_id', $fromColumnId)
                ->orderBy('position')
                ->lockForUpdate()
                ->pluck('id')
                ->all();

            // IDs ordenados destino
            $toIds = ($toColumnId === $fromColumnId)
                ? $fromIds
                : Card::where('board_column_id', $toColumnId)
                    ->orderBy('position')
                    ->lockForUpdate()
                    ->pluck('id')
                    ->all();

            // Lista lógica origen sin la movida
            $fromList = array_values(array_filter(
                $fromIds,
                fn ($id) => (int) $id !== (int) $card->id
            ));

            // Lista lógica destino (si misma col -> también sin la movida)
            $toListBase = ($toColumnId === $fromColumnId) ? $fromList : $toIds;

            // Clamp posición destino
            $toPos = max(0, min($toPos, count($toListBase)));

            // Insertar card en lista destino
            $toList = $toListBase;
            array_splice($toList, $toPos, 0, [$card->id]);

            // No-op (misma col y misma posición real)
            if ($toColumnId === $fromColumnId) {
                $currentIndex = array_search($card->id, $fromIds, true);
                if ($currentIndex !== false && (int) $currentIndex === (int) $toPos) {
                    return response()->json(['card' => [
                        'id' => $card->id,
                        'board_column_id' => $fromColumnId,
                        'position' => (int) $card->position,
                    ]]);
                }
            }

            // 1) Aparcar la card en destino con posición alta (sin colisión)
            $card->update([
                'board_column_id' => $toColumnId,
                'position'        => $OFFSET + 999999,
            ]);

            // 2) Subir temporalmente posiciones de columnas afectadas (siempre suma)
            $affected = array_unique([$fromColumnId, $toColumnId]);
            foreach ($affected as $colId) {
                Card::where('board_column_id', $colId)->update([
                    'position' => DB::raw("position + {$OFFSET}")
                ]);
            }

            // 3) Renumerar 0..N-1 en destino (CASE)
            $this->applyPositionsCase($toColumnId, $toList);

            // 4) Si cambia de columna, renumerar origen (CASE)
            if ($toColumnId !== $fromColumnId) {
                $this->applyPositionsCase($fromColumnId, $fromList);
            }

            broadcast(new \App\Events\CardMoved(
                $projectId,
                $card->id,
                $fromColumnId,
                $toColumnId,
                $toPos,
                $senderId
            ))->toOthers();

            return response()->json(['card' => [
                'id' => $card->id,
                'board_column_id' => $toColumnId,
                'position' => $toPos,
            ]]);
        });
    }

    /**
     * Renumera posiciones 0..N-1 en una columna con UPDATE CASE.
     * Evita colisiones con UNIQUE(board_column_id, position).
     */
    private function applyPositionsCase(int $columnId, array $orderedIds): void
    {
        if (count($orderedIds) === 0) return;

        $cases = [];
        $ids = [];

        foreach ($orderedIds as $pos => $id) {
            $id = (int) $id;
            $pos = (int) $pos;
            $cases[] = "WHEN {$id} THEN {$pos}";
            $ids[] = $id;
        }

        $idsSql  = implode(',', $ids);
        $caseSql = implode(' ', $cases);

        DB::statement("
        UPDATE cards
        SET position = CASE id {$caseSql} END
        WHERE board_column_id = ? AND id IN ({$idsSql})
    ", [$columnId]);
    }

    // PATCH /api/cards/{card}
    public function update(Request $request, Card $card)
    {
        $data = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        // Seguridad: comprobar que la card pertenece a un proyecto del usuario
        $column = BoardColumn::with('project')->findOrFail($card->board_column_id);
        abort_unless($column->project->owner_id === $request->user()->id, 403);

        $card->update([
            'title'       => $data['title'],
            'description' => $data['description'] ?? null,
        ]);

        return response()->json([
            'card' => [
                'id' => $card->id,
                'title' => $card->title,
                'description' => $card->description,
                'position' => $card->position,
                'board_column_id' => $card->board_column_id,
            ],
        ]);
    }

    // DELETE /api/cards/{card}
    public function destroy(Request $request, Card $card)
    {
        // Seguridad
        $column = BoardColumn::with('project')->findOrFail($card->board_column_id);
        abort_unless($column->project->owner_id === $request->user()->id, 403);

        $columnId = (int) $card->board_column_id;
        $deletedPos = (int) $card->position;
        $cardId = (int) $card->id;

        return DB::transaction(function () use ($card, $columnId, $deletedPos, $cardId) {

            // borrar la card
            $card->delete();

            // cerrar hueco de posiciones (0..N-1)
            Card::where('board_column_id', $columnId)
                ->where('position', '>', $deletedPos)
                ->update([
                    'position' => DB::raw('position - 1'),
                ]);

            return response()->json([
                'ok' => true,
                'deleted_card_id' => $cardId,
                'board_column_id' => $columnId,
            ]);
        });
    }

}
