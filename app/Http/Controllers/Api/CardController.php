<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BoardColumn;
use App\Models\Card;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Events\CardUpdated;
use App\Events\CardDeleted;
use Illuminate\Support\Facades\Log;

class CardController extends Controller
{
    // GET /api/cards/{card} (Faltaba este método para cargar datos en el modal)
    public function show(Request $request, Card $card)
    {
        $column = BoardColumn::with('project')->findOrFail($card->board_column_id);
        abort_unless($column->project->owner_id === $request->user()->id, 403);

        return response()->json([
            'card' => $card->load('tags'),
        ]);
    }

    // POST /api/cards
    public function store(Request $request)
    {
        $data = $request->validate([
            'board_column_id' => ['required', 'integer', 'exists:board_columns,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['nullable', 'string', 'in:low,normal,high,urgent'],
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
            'priority' => $data['priority'] ?? 'normal',
        ]);

        $senderId = (int) $request->user()->id;

        // ✅ BROADCAST PROTEGIDO
        try {
            broadcast(new \App\Events\CardCreated(
                (int) $column->project_id,
                [
                    'id' => (int) $card->id,
                    'title' => $card->title,
                    'description' => $card->description,
                    'position' => (int) $card->position,
                    'board_column_id' => (int) $card->board_column_id,
                    'priority' => $card->priority,
                    'tags' => [],
                    'senderId' => $senderId,
                ],
                (int) $column->id,
                $senderId
            ))->toOthers();
        } catch (\Exception $e) {
            Log::error("Error broadcast CardCreated: " . $e->getMessage());
        }

        return response()->json([
            'card' => [
                'id' => $card->id,
                'title' => $card->title,
                'description' => $card->description,
                'position' => $card->position,
                'board_column_id' => $card->board_column_id,
                'priority' => $card->priority,
                'tags' => [],
            ],
        ], 201);
    }

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

        $OFFSET = 1000000;
        $senderId = (int) $request->user()->id;

        return DB::transaction(function () use (
            $card, $projectId, $fromColumnId, $toColumnId, $toPos, $OFFSET, $senderId
        ) {
            $card = Card::whereKey($card->id)->lockForUpdate()->firstOrFail();

            // Lógica de movimiento
            $fromIds = Card::where('board_column_id', $fromColumnId)->orderBy('position')->lockForUpdate()->pluck('id')->all();
            $toIds = ($toColumnId === $fromColumnId) ? $fromIds : Card::where('board_column_id', $toColumnId)->orderBy('position')->lockForUpdate()->pluck('id')->all();

            $fromList = array_values(array_filter($fromIds, fn ($id) => (int) $id !== (int) $card->id));
            $toListBase = ($toColumnId === $fromColumnId) ? $fromList : $toIds;
            $toPos = max(0, min($toPos, count($toListBase)));

            // Optimización No-op
            if ($toColumnId === $fromColumnId) {
                $currentIndex = array_search($card->id, $fromIds, true);
                if ($currentIndex !== false && (int) $currentIndex === (int) $toPos) {
                    return response()->json(['card' => ['id' => $card->id, 'board_column_id' => $fromColumnId, 'position' => (int) $card->position]]);
                }
            }

            // 1. Mover temporalmente lejos
            $card->update(['board_column_id' => $toColumnId, 'position' => $OFFSET + 999999]);

            // 2. Recalcular posiciones destino
            $toList = $toListBase;
            array_splice($toList, $toPos, 0, [$card->id]);
            foreach ($toList as $index => $id) {
                Card::where('id', $id)->update(['position' => $index]);
            }

            // 3. Recalcular origen si es diferente
            if ($toColumnId !== $fromColumnId) {
                foreach ($fromList as $index => $id) {
                    Card::where('id', $id)->update(['position' => $index]);
                }
            }

            $card->refresh();

            // ✅ BROADCAST PROTEGIDO
            try {
                broadcast(new \App\Events\CardMoved(
                    $projectId,
                    $card->id,
                    $fromColumnId,
                    $toColumnId,
                    $card->position,
                    $senderId
                ))->toOthers();
            } catch (\Exception $e) {
                Log::error("Error broadcast CardMoved: " . $e->getMessage());
            }

            return response()->json(['card' => [
                'id' => $card->id,
                'board_column_id' => $toColumnId,
                'position' => $card->position,
            ]]);
        });
    }

    // PATCH /api/cards/{card}
    public function update(Request $request, Card $card)
    {
        // 1. Validate including priority and tags array
        $data = $request->validate([
            'title'       => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority'    => ['nullable', 'string', 'in:low,normal,high,urgent'],
            'tags'        => ['nullable', 'array'],
            'tags.*'      => ['integer', 'exists:tags,id'],
        ]);

        $column = BoardColumn::with('project')->findOrFail($card->board_column_id);
        abort_unless($column->project->owner_id === $request->user()->id, 403);

        // 2. Update basic fields
        $updateData = [];
        if (isset($data['title'])) $updateData['title'] = $data['title'];
        if (array_key_exists('description', $data)) $updateData['description'] = $data['description'];
        if (isset($data['priority'])) $updateData['priority'] = $data['priority'];

        $card->update($updateData);

        // 3. Sync Tags (Many-to-Many)
        if (isset($data['tags'])) {
            $card->tags()->sync($data['tags']);
        }

        // Reload tags to return full object
        $card->load('tags');

        try {
            // Broadcast with updated data including priority and tags
            broadcast(new CardUpdated($column->project_id, $card))->toOthers();
        } catch (\Exception $e) {
            Log::error("Error broadcast CardUpdated: " . $e->getMessage());
        }

        return response()->json([
            'card' => $card,
        ]);
    }

    // GET /api/tags
    public function getTags() {
        return response()->json(Tag::all());
    }

    // DELETE /api/cards/{card}
    public function destroy(Request $request, Card $card)
    {
        $column = BoardColumn::with('project')->findOrFail($card->board_column_id);
        abort_unless($column->project->owner_id === $request->user()->id, 403);

        $columnId = (int) $card->board_column_id;
        $deletedPos = (int) $card->position;
        $cardId = (int) $card->id;
        $projectId = $column->project_id;

        return DB::transaction(function () use ($card, $columnId, $deletedPos, $cardId, $projectId) {
            $card->delete();

            // Cerrar hueco
            Card::where('board_column_id', $columnId)
                ->where('position', '>', $deletedPos)
                ->update(['position' => DB::raw('position - 1')]);

            // ✅ BROADCAST PROTEGIDO
            try {
                broadcast(new CardDeleted($projectId, $cardId))->toOthers();
            } catch (\Exception $e) {
                Log::error("Error broadcast CardDeleted: " . $e->getMessage());
            }

            return response()->json([
                'ok' => true,
                'deleted_card_id' => $cardId,
                'board_column_id' => $columnId,
            ]);
        });
    }

    // NEW: POST /api/cards/{card}/tags
    public function addTag(Request $request, Card $card)
    {
        $request->validate([
            'tag_name' => 'required|string|exists:tags,name',
        ]);

        $column = BoardColumn::with('project')->findOrFail($card->board_column_id);
        abort_unless($column->project->owner_id === $request->user()->id, 403);

        $tag = Tag::where('name', $request->tag_name)->firstOrFail();
        $card->tags()->syncWithoutDetaching([$tag->id]);

        // ✅ BROADCAST PROTEGIDO
        try {
            broadcast(new CardUpdated($column->project_id, $card->load('tags')))->toOthers();
        } catch (\Exception $e) {
            Log::error("Error broadcast AddTag: " . $e->getMessage());
        }

        return response()->json(['message' => 'Tag added', 'card' => $card->load('tags')]);
    }

    // NEW: DELETE /api/cards/{card}/tags/{tag}
    public function removeTag(Request $request, Card $card, Tag $tag)
    {
        $column = BoardColumn::with('project')->findOrFail($card->board_column_id);
        abort_unless($column->project->owner_id === $request->user()->id, 403);

        $card->tags()->detach($tag->id);

        // ✅ BROADCAST PROTEGIDO
        try {
            broadcast(new CardUpdated($column->project_id, $card->load('tags')))->toOthers();
        } catch (\Exception $e) {
            Log::error("Error broadcast RemoveTag: " . $e->getMessage());
        }

        return response()->json(['message' => 'Tag removed', 'card' => $card->load('tags')]);
    }
}
