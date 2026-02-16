<?php

namespace App\Services\Ai;

use App\Models\BoardColumn;
use App\Models\Card;
use App\Models\Project;
use App\Models\Tag;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HarvisService
{
    private const MAX_COLUMN_CARDS = 60;
    private const MAX_GLOBAL_COLUMNS = 12;
    private const MAX_GLOBAL_CARDS_PER_COLUMN = 40;
    private const MAX_TAGS_IN_PROMPT = 200;

    public function handleColumn(array $data): array
    {
        $columnId = (int) $data['column_id'];
        $prompt = $data['prompt'];

        $column = BoardColumn::with('cards')->find($columnId);
        if (!$column) {
            return [
                'status' => 404,
                'body' => ['message' => 'Columna no encontrada.'],
            ];
        }

        $currentCards = $this->buildColumnContext($column);
        $columnContext = json_decode($currentCards, true) ?? [];
        $columnTruncated = (bool) ($columnContext['truncated'] ?? false);

        $systemPrompt = $this->buildColumnPrompt($currentCards, $prompt);

        try {
            $rawContent = $this->callGemini($systemPrompt, 'gemini-2.5-flash');
            $data = $this->decodeJsonResponse($rawContent);

            $action = $data['action'] ?? null;

            if ($action === 'create') {
                $response = $this->performCreate($column, $data['tasks'] ?? []);
                return $this->withTruncationWarning($response, $columnTruncated);
            }

            if ($action === 'reorder') {
                $response = $this->performReorder($data['sorted_ids'] ?? []);
                return $this->withTruncationWarning($response, $columnTruncated);
            }

            return $this->withTruncationWarning([
                'status' => 400,
                'body' => ['message' => 'No entendi la instruccion'],
            ], $columnTruncated);
        } catch (\Exception $e) {
            Log::error('AI column error: ' . $e->getMessage());

            return $this->withTruncationWarning([
                'status' => 500,
                'body' => ['message' => 'Error AI: ' . $e->getMessage()],
            ], $columnTruncated);
        }
    }

    public function handleGlobal(array $data): array
    {
        $projectId = (int) $data['project_id'];
        $prompt = $data['prompt'];

        $project = Project::with([
            'columns' => function ($q) {
                $q->orderBy('position');
            },
            'columns.cards.tags',
        ])->find($projectId);
        if (!$project) {
            return [
                'status' => 404,
                'body' => ['message' => 'Proyecto no encontrado.'],
            ];
        }

        $existingColumns = $project->columns->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name) => [strtolower($name) => $id])
            ->toArray();
        $columnIds = array_values($existingColumns);
        $existingColumnIdSet = $project->columns->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->flip()
            ->toArray();
        $existingCardIdSet = $project->columns
            ->flatMap(fn ($col) => $col->cards->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->flip()
            ->toArray();
        $allowedPriorities = ['low', 'normal', 'high', 'urgent'];

        $allTags = Tag::all()->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name) => [strtolower($name) => $id])
            ->toArray();

        $tagsListString = $this->buildTagsListString($allTags);

        $boardContext = $this->buildGlobalContext($project);
        $globalContext = json_decode($boardContext, true) ?? [];
        $globalTruncated = (bool) ($globalContext['truncated'] ?? false);

        $systemPrompt = $this->buildGlobalPrompt($boardContext, $tagsListString, $prompt);

        try {
            $rawContent = $this->callGemini($systemPrompt, 'gemini-2.5-flash-lite', true);
            $data = $this->decodeJsonResponse($rawContent);
            $operations = $data['operations'] ?? [];

            Log::info('AI global operations', [
                'project_id' => $projectId,
                'prompt' => $prompt,
                'operations_count' => count($operations),
                'operations' => $operations,
            ]);

            if (empty($operations)) {
                return $this->withTruncationWarning([
                    'status' => 400,
                    'body' => ['message' => 'No se generaron cambios.'],
                ], $globalTruncated);
            }

            $failedOperations = [];
            $appliedCount = 0;
            $recordFailure = function (int $index, string $reason) use (&$failedOperations) {
                $failedOperations[] = [
                    'index' => $index,
                    'reason' => $reason,
                ];
            };

            DB::transaction(function () use (
                $operations,
                $projectId,
                $existingColumns,
                $allTags,
                $columnIds,
                $allowedPriorities,
                $recordFailure,
                &$appliedCount,
                &$existingColumnIdSet,
                &$existingCardIdSet
            ) {
                $columnMap = $existingColumns;
                $randomColumnIds = $columnIds;
                shuffle($randomColumnIds);
                $randomIndex = 0;

                foreach ($operations as $index => $action) {
                    $type = $action['op'] ?? null;
                    if (!$type) {
                        $recordFailure($index, 'Operacion sin tipo');
                        continue;
                    }

                    if ($type === 'set_priority') {
                        if (!isset($action['card_id'], $action['priority']) || !is_string($action['priority'])) {
                            $recordFailure($index, 'set_priority requiere card_id y priority');
                            continue;
                        }
                        $cardId = $this->parseId($action['card_id']);
                        if (!$cardId || !isset($existingCardIdSet[$cardId])) {
                            $recordFailure($index, 'card_id invalido');
                            continue;
                        }
                        if (!in_array($action['priority'], $allowedPriorities, true)) {
                            $recordFailure($index, 'priority invalido');
                            continue;
                        }
                        Card::where('id', $cardId)->update([
                            'priority' => $action['priority'],
                        ]);
                        $appliedCount += 1;
                    }

                    if ($type === 'add_tag') {
                        if (!isset($action['card_id'], $action['tag_name'])) {
                            $recordFailure($index, 'add_tag requiere card_id y tag_name');
                            continue;
                        }
                        $cardId = $this->parseId($action['card_id']);
                        if (!$cardId || !isset($existingCardIdSet[$cardId])) {
                            $recordFailure($index, 'card_id invalido');
                            continue;
                        }
                        $tagName = strtolower($action['tag_name']);
                        if (!isset($allTags[$tagName])) {
                            $recordFailure($index, 'tag_name invalido');
                            continue;
                        }
                        $tagId = $allTags[$tagName];
                        $card = Card::find($cardId);
                        if ($card && !$card->tags()->where('tag_id', $tagId)->exists()) {
                            $card->tags()->attach($tagId);
                        }
                        $appliedCount += 1;
                    }

                    if ($type === 'remove_tag') {
                        if (!isset($action['card_id'], $action['tag_name'])) {
                            $recordFailure($index, 'remove_tag requiere card_id y tag_name');
                            continue;
                        }
                        $cardId = $this->parseId($action['card_id']);
                        if (!$cardId || !isset($existingCardIdSet[$cardId])) {
                            $recordFailure($index, 'card_id invalido');
                            continue;
                        }
                        $tagName = strtolower($action['tag_name']);
                        if (!isset($allTags[$tagName])) {
                            $recordFailure($index, 'tag_name invalido');
                            continue;
                        }
                        $tagId = $allTags[$tagName];
                        $card = Card::find($cardId);
                        if ($card) {
                            $card->tags()->detach($tagId);
                        }
                        $appliedCount += 1;
                    }

                    if ($type === 'update_column') {
                        if (!isset($action['column_id'], $action['name']) || !is_string($action['name'])) {
                            $recordFailure($index, 'update_column requiere column_id y name');
                            continue;
                        }
                        $columnId = $this->parseId($action['column_id']);
                        if (!$columnId || !isset($existingColumnIdSet[$columnId])) {
                            $recordFailure($index, 'column_id invalido');
                            continue;
                        }
                        BoardColumn::where('id', $columnId)
                            ->update(['name' => $action['name']]);
                        $columnMap[strtolower($action['name'])] = $columnId;
                        $appliedCount += 1;
                    }

                    if ($type === 'delete_column') {
                        if (!isset($action['column_id'])) {
                            $recordFailure($index, 'delete_column requiere column_id');
                            continue;
                        }
                        $columnId = $this->parseId($action['column_id']);
                        if (!$columnId || !isset($existingColumnIdSet[$columnId])) {
                            $recordFailure($index, 'column_id invalido');
                            continue;
                        }
                        Card::where('board_column_id', $columnId)->delete();
                        BoardColumn::destroy($columnId);
                        unset($existingColumnIdSet[$columnId]);
                        $appliedCount += 1;
                    }

                    if ($type === 'reorder_columns') {
                        if (!isset($action['sorted_column_ids']) || !is_array($action['sorted_column_ids'])) {
                            $recordFailure($index, 'reorder_columns requiere sorted_column_ids');
                            continue;
                        }
                        $ids = $this->parseIdList($action['sorted_column_ids']);
                        if (!$ids) {
                            $recordFailure($index, 'sorted_column_ids vacio o invalido');
                            continue;
                        }
                        if ($this->hasDuplicateIds($ids)) {
                            $recordFailure($index, 'sorted_column_ids contiene IDs duplicados');
                            continue;
                        }
                        $missing = array_filter($ids, fn ($id) => !isset($existingColumnIdSet[$id]));
                        if ($missing) {
                            $recordFailure($index, 'sorted_column_ids contiene IDs invalidos');
                            continue;
                        }
                        BoardColumn::whereIn('id', $ids)->lockForUpdate()->get();
                        BoardColumn::whereIn('id', $ids)
                            ->update(['position' => DB::raw('id + 9000000')]);
                        $pos = 0;
                        foreach ($ids as $id) {
                            BoardColumn::where('id', $id)->update(['position' => $pos]);
                            $pos += 1000;
                        }
                        $appliedCount += 1;
                    }

                    if ($type === 'create_column') {
                        if (!isset($action['name']) || !is_string($action['name']) || trim($action['name']) === '') {
                            $recordFailure($index, 'create_column requiere name');
                            continue;
                        }
                        $maxColPos = BoardColumn::where('project_id', $projectId)
                            ->max('position') ?? 0;
                        $newCol = BoardColumn::create([
                            'project_id' => $projectId,
                            'name' => $action['name'],
                            'position' => $maxColPos + 1000,
                        ]);
                        $columnMap[strtolower($action['name'])] = $newCol->id;
                        $existingColumnIdSet[$newCol->id] = true;
                        $appliedCount += 1;
                    }

                    if ($type === 'move') {
                        if (!isset($action['card_id'])) {
                            $recordFailure($index, 'move requiere card_id');
                            continue;
                        }
                        $cardId = $this->parseId($action['card_id']);
                        if (!$cardId || !isset($existingCardIdSet[$cardId])) {
                            $recordFailure($index, 'card_id invalido');
                            continue;
                        }
                        $targetId = $this->parseId($action['to_column_id'] ?? null);
                        if (!$targetId && isset($action['to_column_name'])) {
                            $targetId = $columnMap[strtolower($action['to_column_name'])] ?? null;
                        }
                        if (!$targetId || !isset($existingColumnIdSet[$targetId])) {
                            $recordFailure($index, 'to_column_id invalido');
                            continue;
                        }
                        $newPos = Card::where('board_column_id', $targetId)
                            ->max('position') ?? 0;
                        Card::where('id', $cardId)->update([
                            'board_column_id' => $targetId,
                            'position' => $newPos + 1000,
                        ]);
                        $appliedCount += 1;
                    }

                    if ($type === 'create') {
                        if (!isset($action['title']) || !is_string($action['title']) || trim($action['title']) === '') {
                            $recordFailure($index, 'create requiere title');
                            continue;
                        }
                        $targetId = $this->parseId($action['column_id'] ?? null);
                        if (!$targetId && isset($action['column_name'])) {
                            $targetId = $columnMap[strtolower($action['column_name'])] ?? null;
                        }
                        if (!$targetId && $randomColumnIds) {
                            $targetId = $randomColumnIds[$randomIndex % count($randomColumnIds)];
                            $randomIndex += 1;
                        }
                        if (!$targetId || !isset($existingColumnIdSet[$targetId])) {
                            $recordFailure($index, 'column_id invalido');
                            continue;
                        }
                        $maxPos = Card::where('board_column_id', $targetId)
                            ->max('position') ?? 0;
                        $newCard = Card::create([
                            'board_column_id' => $targetId,
                            'title' => $action['title'],
                            'description' => $action['description'] ?? '',
                            'position' => $maxPos + 1000,
                            'priority' => 'normal',
                        ]);
                        $existingCardIdSet[$newCard->id] = true;
                        $appliedCount += 1;
                    }

                    if ($type === 'update') {
                        if (!isset($action['card_id'])) {
                            $recordFailure($index, 'update requiere card_id');
                            continue;
                        }
                        $cardId = $this->parseId($action['card_id']);
                        if (!$cardId || !isset($existingCardIdSet[$cardId])) {
                            $recordFailure($index, 'card_id invalido');
                            continue;
                        }
                        $fields = array_filter(
                            $action,
                            fn ($key) => in_array($key, ['title', 'description'], true),
                            ARRAY_FILTER_USE_KEY
                        );
                        if (!$fields) {
                            $recordFailure($index, 'update sin campos');
                            continue;
                        }
                        Card::where('id', $cardId)->update($fields);
                        $appliedCount += 1;
                    }

                    if ($type === 'delete') {
                        if (!isset($action['card_id'])) {
                            $recordFailure($index, 'delete requiere card_id');
                            continue;
                        }
                        $cardId = $this->parseId($action['card_id']);
                        if (!$cardId || !isset($existingCardIdSet[$cardId])) {
                            $recordFailure($index, 'card_id invalido');
                            continue;
                        }
                        Card::where('id', $cardId)->delete();
                        unset($existingCardIdSet[$cardId]);
                        $appliedCount += 1;
                    }

                    if ($type === 'reorder') {
                        if (!isset($action['sorted_card_ids']) || !is_array($action['sorted_card_ids'])) {
                            $recordFailure($index, 'reorder requiere sorted_card_ids');
                            continue;
                        }
                        $ids = $this->parseIdList($action['sorted_card_ids']);
                        if (!$ids) {
                            $recordFailure($index, 'sorted_card_ids vacio o invalido');
                            continue;
                        }
                        if ($this->hasDuplicateIds($ids)) {
                            $recordFailure($index, 'sorted_card_ids contiene IDs duplicados');
                            continue;
                        }
                        $missing = array_filter($ids, fn ($id) => !isset($existingCardIdSet[$id]));
                        if ($missing) {
                            $recordFailure($index, 'sorted_card_ids contiene IDs invalidos');
                            continue;
                        }
                        Card::whereIn('id', $ids)->lockForUpdate()->get();
                        Card::whereIn('id', $ids)
                            ->update(['position' => DB::raw('id + 9000000')]);
                        $pos = 0;
                        foreach ($ids as $id) {
                            Card::where('id', $id)->update(['position' => $pos]);
                            $pos += 1000;
                        }
                        $appliedCount += 1;
                    }
                }
            });

            $message = $failedOperations
                ? 'Operaciones completadas con advertencias.'
                : 'Operaciones completadas con exito.';

            return $this->withTruncationWarning([
                'status' => 200,
                'body' => [
                    'action' => 'reordered',
                    'message' => $message,
                    'applied_count' => $appliedCount,
                    'failed_operations' => $failedOperations,
                ],
            ], $globalTruncated);
        } catch (\Exception $e) {
            Log::error('AI global error: ' . $e->getMessage());

            return $this->withTruncationWarning([
                'status' => 500,
                'body' => ['message' => 'Error: ' . $e->getMessage()],
            ], $globalTruncated);
        }
    }

    private function callGemini(string $prompt, string $model, bool $withRetry = false): string
    {
        $apiKey = env('GEMINI_API_KEY');
        if (!$apiKey) {
            throw new \Exception('GEMINI_API_KEY no esta configurada');
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $request = Http::withHeaders([
            'Content-Type' => 'application/json',
        ]);

        if ($withRetry) {
            $request = $request->retry(3, 2000, function ($exception) {
                return $exception->response->status() === 429;
            });
        }

        $response = $request->post($url, [
            'contents' => [
                ['parts' => [['text' => $prompt]]],
            ],
            'generationConfig' => [
                'response_mime_type' => 'application/json',
            ],
        ]);

        if ($response->status() === 429) {
            throw new \Exception('AI rate limited (429)');
        }

        if ($response->failed()) {
            throw new \Exception('Gemini Error: ' . $response->body());
        }

        $jsonResponse = $response->json();
        return $jsonResponse['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
    }

    private function decodeJsonResponse(string $rawContent): array
    {
        $cleanJson = str_replace(['```json', '```'], '', $rawContent);
        $cleanJson = trim($cleanJson);
        $data = json_decode($cleanJson, true);

        if (!$data) {
            $extracted = $this->extractJsonBlock($cleanJson);
            if ($extracted !== null) {
                $data = json_decode($extracted, true);
            }
        }

        if (!$data) {
            throw new \Exception('Gemini no devolvio JSON valido: ' . $rawContent);
        }

        return $data;
    }

    private function extractJsonBlock(string $content): ?string
    {
        $firstBrace = strpos($content, '{');
        $lastBrace = strrpos($content, '}');
        if ($firstBrace !== false && $lastBrace !== false && $lastBrace > $firstBrace) {
            return substr($content, $firstBrace, $lastBrace - $firstBrace + 1);
        }

        $firstBracket = strpos($content, '[');
        $lastBracket = strrpos($content, ']');
        if ($firstBracket !== false && $lastBracket !== false && $lastBracket > $firstBracket) {
            return substr($content, $firstBracket, $lastBracket - $firstBracket + 1);
        }

        return null;
    }

    private function buildColumnPrompt(string $currentCards, string $userPrompt): string
    {
        return <<<EOT
Eres Harvis, un gestor de proyectos experto. Tu trabajo es devolver SIEMPRE un JSON valido.
El usuario te dara una instruccion sobre un tablero Kanban.

CONTEXTO ACTUAL DE LA COLUMNA (JSON):
{$currentCards}

TUS INSTRUCCIONES:
Analiza el prompt del usuario y decide si quiere CREAR tareas o REORDENAR las existentes.

CASO 1: CREAR TAREAS
Si pide crear, devuelve este JSON exacto:
{
    "action": "create",
    "tasks": [
        { "title": "Titulo corto", "description": "Descripcion tecnica breve" }
    ]
}

CASO 2: REORDENAR / PRIORIZAR
Si pide ordenar, mover o priorizar, analiza los IDs y devuelve este JSON con el nuevo orden:
{
    "action": "reorder",
    "sorted_ids": [10, 5, 8]
}

REGLAS:
- Responde SOLO con el JSON. Nada de texto antes ni despues.
- No inventes IDs que no existen en el contexto.

USUARIO DICE: {$userPrompt}
EOT;
    }

    private function buildGlobalPrompt(string $boardContext, string $tagsListString, string $userPrompt): string
    {
        return <<<EOT
Eres Harvis, un Project Manager Senior experto en metodologias Agiles.

CONTEXTO ACTUAL DEL TABLERO:
{$boardContext}

ETIQUETAS DISPONIBLES EN EL SISTEMA:
[{$tagsListString}]

TUS HERRAMIENTAS (OPERACIONES):
1. "create_column": { "op": "create_column", "name": "Nombre" }
2. "update_column": { "op": "update_column", "column_id": ID, "name": "Nuevo Nombre" }
3. "delete_column": { "op": "delete_column", "column_id": ID }
4. "reorder_columns": { "op": "reorder_columns", "sorted_column_ids": [IDs...] }

5. "create": { "op": "create", "column_id": ID, "title": "...", "description": "..." }
6. "move": { "op": "move", "card_id": ID, "to_column_id": ID }
7. "delete": { "op": "delete", "card_id": ID }
8. "update": { "op": "update", "card_id": ID, "title": "Nuevo Titulo", "description": "..." }
9. "reorder": { "op": "reorder", "column_id": ID, "sorted_card_ids": [IDs...] }

NUEVAS HERRAMIENTAS DE CLASIFICACION:
10. "set_priority": { "op": "set_priority", "card_id": ID, "priority": "high" }
    (Valores validos: "low", "normal", "high", "urgent").
11. "add_tag": { "op": "add_tag", "card_id": ID, "tag_name": "Bug" }
    (Usa el nombre de la etiqueta de la lista disponible).
12. "remove_tag": { "op": "remove_tag", "card_id": ID, "tag_name": "Bug" }

CAPACIDADES DE RAZONAMIENTO:
- Si piden "Priorizar urgente lo de diseno", busca tareas con "diseno" en el titulo o tag "Design", ponles priority "urgent" y muevelas al principio.
- Si piden "Etiquetar bugs", busca palabras clave como "error", "fix", "fallo" y usa "add_tag" con "Bug".
- Si creas una tarea nueva y sabes el contexto, asignale ya la etiqueta y prioridad correctas en la misma secuencia.
- Si el usuario pide crear tareas "aleatorias" en varias columnas, elige columnas existentes del contexto y especifica "column_id" en cada "create".

INSTRUCCION DEL USUARIO:
"{$userPrompt}"

FORMATO DE RESPUESTA:
JSON con propiedad "operations".
EOT;
    }

    private function buildColumnContext(BoardColumn $column): string
    {
        $totalCards = $column->cards->count();
        $cards = $column->cards
            ->sortBy('position')
            ->take(self::MAX_COLUMN_CARDS)
            ->map(function ($card) {
                return [
                    'id' => $card->id,
                    'title' => $card->title,
                ];
            })
            ->values();

        return json_encode([
            'column_id' => $column->id,
            'total_cards' => $totalCards,
            'truncated' => $totalCards > self::MAX_COLUMN_CARDS,
            'cards' => $cards,
        ]);
    }

    private function buildGlobalContext(Project $project): string
    {
        $totalColumns = $project->columns->count();
        $columns = $project->columns
            ->sortBy('position')
            ->take(self::MAX_GLOBAL_COLUMNS)
            ->map(function ($col) {
                $totalCards = $col->cards->count();
                $cards = $col->cards
                    ->sortBy('position')
                    ->take(self::MAX_GLOBAL_CARDS_PER_COLUMN)
                    ->map(fn ($card) => [
                        'id' => $card->id,
                        'title' => $card->title,
                        'priority' => $card->priority,
                        'tags' => $card->tags->pluck('name')->toArray(),
                    ])
                    ->values();

                return [
                    'column_id' => $col->id,
                    'name' => $col->name,
                    'total_cards' => $totalCards,
                    'truncated' => $totalCards > self::MAX_GLOBAL_CARDS_PER_COLUMN,
                    'cards' => $cards,
                ];
            })
            ->values();

        return json_encode([
            'total_columns' => $totalColumns,
            'truncated' => $totalColumns > self::MAX_GLOBAL_COLUMNS,
            'columns' => $columns,
        ]);
    }

    private function buildTagsListString(array $allTags): string
    {
        $tags = array_keys($allTags);
        if (count($tags) > self::MAX_TAGS_IN_PROMPT) {
            $tags = array_slice($tags, 0, self::MAX_TAGS_IN_PROMPT);
        }

        return implode(', ', $tags);
    }

    private function performCreate(BoardColumn $column, array $tasks): array
    {
        $maxPosition = Card::where('board_column_id', $column->id)->max('position') ?? 0;

        foreach ($tasks as $taskData) {
            $maxPosition += 1000;

            Card::create([
                'board_column_id' => $column->id,
                'title' => $taskData['title'],
                'description' => $taskData['description'] ?? '',
                'position' => $maxPosition,
            ]);
        }

        return [
            'status' => 200,
            'body' => [
                'action' => 'created',
                'message' => 'Tareas creadas con Gemini',
            ],
        ];
    }

    private function performReorder(array $sortedIds): array
    {
        return DB::transaction(function () use ($sortedIds) {
            Card::whereIn('id', $sortedIds)->lockForUpdate()->get();
            $position = 0;
            foreach ($sortedIds as $id) {
                Card::where('id', $id)->update(['position' => $position]);
                $position += 1000;
            }

            return [
                'status' => 200,
                'body' => [
                    'action' => 'reordered',
                    'message' => 'Tablero reordenado',
                ],
            ];
        });
    }

    private function withTruncationWarning(array $response, bool $truncated): array
    {
        if (!$truncated) {
            return $response;
        }

        $body = $response['body'] ?? [];
        $body = is_array($body) ? $body : ['data' => $body];
        $body['context_truncated'] = true;
        $body['context_warning'] = 'El contexto enviado a la IA fue truncado para limitar el tamano.';
        $response['body'] = $body;

        return $response;
    }

    private function parseId(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '' || !ctype_digit($value)) {
                return null;
            }
            $intValue = (int) $value;
            return $intValue > 0 ? $intValue : null;
        }

        return null;
    }

    private function parseIdList(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        $ids = [];
        foreach ($values as $value) {
            $id = $this->parseId($value);
            if (!$id) {
                return [];
            }
            $ids[] = $id;
        }

        return $ids;
    }

    private function hasDuplicateIds(array $ids): bool
    {
        return count($ids) !== count(array_unique($ids));
    }
}
