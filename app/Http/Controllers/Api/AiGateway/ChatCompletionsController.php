<?php

namespace App\Http\Controllers\Api\AiGateway;

use App\Domain\Ai\Gateway\AiGateway;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * OpenAI-compatible non-streaming chat completions endpoint.
 */
class ChatCompletionsController extends Controller
{
    public function __invoke(Request $request, AiGateway $gateway): JsonResponse
    {
        $validated = $request->validate([
            'model' => 'required|string',
            'messages' => 'required|array|min:1',
            'messages.*.role' => 'required|string',
            'messages.*.content' => 'present',
            'max_tokens' => 'nullable|integer|min:1',
            'temperature' => 'nullable|numeric|min:0|max:2',
            'stream' => 'nullable|boolean',
        ]);

        if ((bool) ($validated['stream'] ?? false)) {
            return response()->json([
                'error' => [
                    'message' => 'Streaming chat completions are not implemented by the OpenCompany AI Gateway yet.',
                    'type' => 'invalid_request_error',
                    'code' => 'unsupported_streaming',
                ],
            ], 400);
        }

        try {
            $response = $gateway->chatCompletion(
                requestedModel: $validated['model'],
                messages: $validated['messages'],
                maxTokens: $validated['max_tokens'] ?? null,
                temperature: isset($validated['temperature']) ? (float) $validated['temperature'] : null,
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => [
                    'message' => $e->getMessage(),
                    'type' => 'invalid_request_error',
                    'code' => 'model_not_available',
                ],
            ], 400);
        }

        $usage = $response->usage->toArray();
        $usage['total_tokens'] = $response->usage->promptTokens + $response->usage->completionTokens;

        return response()->json([
            'id' => 'chatcmpl_'.Str::uuid()->toString(),
            'object' => 'chat.completion',
            'created' => now()->timestamp,
            'model' => $validated['model'],
            'choices' => [[
                'index' => 0,
                'message' => [
                    'role' => 'assistant',
                    'content' => $response->text,
                ],
                'finish_reason' => 'stop',
            ]],
            'usage' => $usage,
        ]);
    }
}
