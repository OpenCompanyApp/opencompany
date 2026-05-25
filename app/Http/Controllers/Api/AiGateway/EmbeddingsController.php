<?php

namespace App\Http\Controllers\Api\AiGateway;

use App\Domain\Ai\Gateway\AiGateway;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * OpenAI-compatible embeddings endpoint backed by OpenCompany's embedding client.
 */
class EmbeddingsController extends Controller
{
    public function __invoke(Request $request, AiGateway $gateway): JsonResponse
    {
        $validated = $request->validate([
            'model' => 'required|string',
            'input' => 'required',
        ]);

        $inputs = is_array($validated['input'])
            ? array_values(array_map('strval', $validated['input']))
            : [(string) $validated['input']];

        try {
            $vectors = $gateway->embeddings($validated['model'], $inputs);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => [
                    'message' => $e->getMessage(),
                    'type' => 'invalid_request_error',
                    'code' => 'model_not_available',
                ],
            ], 400);
        }

        $promptTokens = (int) ceil(strlen(implode("\n", $inputs)) / 4);

        return response()->json([
            'object' => 'list',
            'model' => $validated['model'],
            'data' => collect($vectors)->map(fn (array $embedding, int $index) => [
                'object' => 'embedding',
                'index' => $index,
                'embedding' => $embedding,
            ])->values()->all(),
            'usage' => [
                'prompt_tokens' => $promptTokens,
                'total_tokens' => $promptTokens,
            ],
        ]);
    }
}
