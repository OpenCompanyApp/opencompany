<?php

namespace App\Http\Controllers\Api\AiGateway;

use App\Domain\Ai\Gateway\AiGatewayModelRegistry;
use App\Domain\Ai\Gateway\GatewayKeyAuthorizer;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * OpenAI-compatible model list for gateway clients, filtered to the
 * calling key's model scope.
 */
class ModelsController extends Controller
{
    public function __invoke(Request $request, AiGatewayModelRegistry $models, GatewayKeyAuthorizer $keys): JsonResponse
    {
        if (($denied = $keys->deny($request, 'models.list')) !== null) {
            return $denied;
        }

        return response()->json([
            'object' => 'list',
            'data' => collect($keys->filterModels($request, $models->models()))->map(fn (array $model) => [
                'id' => $model['id'],
                'object' => 'model',
                'created' => 0,
                'owned_by' => 'opencompany',
            ])->values()->all(),
        ]);
    }
}
