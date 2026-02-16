<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Ai\AiColumnRequest;
use App\Http\Requests\Ai\AiGlobalRequest;
use App\Services\Ai\HarvisService;
use Illuminate\Support\Facades\Log;

class AiController extends Controller
{
    public function handleRequest(AiColumnRequest $request, HarvisService $harvisService)
    {
        $result = $harvisService->handleColumn($request->validated());

        return $this->respond($result);
    }

    public function handleGlobalRequest(AiGlobalRequest $request, HarvisService $harvisService)
    {
        $result = $harvisService->handleGlobal($request->validated());

        if ($result['status'] === 200) {
            try {
                broadcast(new \App\Events\BoardRefreshed($request->project_id))->toOthers();
            } catch (\Throwable $e) {
                Log::warning('Broadcast failed in AI global request', [
                    'project_id' => $request->project_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->respond($result);
    }

    private function respond(array $result)
    {
        $status = (int) ($result['status'] ?? 500);
        $body = $result['body'] ?? [];
        $body = is_array($body) ? $body : ['data' => $body];

        return response()->json([
            'status' => $status,
            ...$body,
        ], $status);
    }
}
