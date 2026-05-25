<?php

namespace App\Http\Controllers\Api\AiGateway;

use App\Domain\Ai\Gateway\AiGatewayModelRegistry;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * OpenAI-compatible model list for gateway clients.
 */
class ModelsController extends Controller
{
    public function __invoke(AiGatewayModelRegistry $models): JsonResponse
    {
        return response()->json([
            'object' => 'list',
            'data' => collect($models->models())->map(fn (array $model) => [
                'id' => $model['id'],
                'object' => 'model',
                'created' => 0,
                'owned_by' => 'opencompany',
            ])->values()->all(),
        ]);
    }
}
