<?php

namespace App\Domain\Ai\Gateway;

use App\Domain\Authorization\Application\AuthorizationFacts;
use App\Domain\Authorization\Application\AuthorizationService;
use App\Http\Middleware\AuthenticateAiGateway;
use App\Models\AiGatewayApiKey;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Per-call AI Gateway key scope check (GIN-1203).
 *
 * AuthenticateAiGateway has already rejected missing, unknown, revoked and
 * expired keys and bound the key's workspace. This asks the central decision
 * service (`ai_gateway.invoke`) whether the key's scopes cover the operation
 * and, for chat and embeddings, the requested model. Denials are recorded in
 * the authorization ledger by the service.
 *
 * Failure: returns an OpenAI-style 403 error body; never throws for a denial.
 */
class GatewayKeyAuthorizer
{
    public function __construct(
        private readonly AuthorizationService $authorization,
        private readonly AuthorizationFacts $facts,
    ) {}

    /** Null when allowed, else the error response to return. */
    public function deny(Request $request, string $operation, ?string $model = null): ?JsonResponse
    {
        $key = AuthenticateAiGateway::resolveKey($request);
        $workspace = $key !== null ? Workspace::query()->find($key->workspace_id) : null;
        if ($key === null || $workspace === null) {
            return $this->error('Invalid API key.', 'invalid_api_key', 401);
        }

        $resource = $model !== null ? $this->facts->aiModel($model, $workspace) : $workspace;
        $decision = $this->authorization->decide($key, 'ai_gateway.invoke', $resource, ['operation' => $operation], workspace: $workspace);
        if ($decision->allowed()) {
            return null;
        }

        return $this->error($decision->reason, $decision->reasonCode, 403);
    }

    /**
     * Filter a model list to the key's model scope.
     *
     * @param  list<array<string, mixed>>  $models
     * @return list<array<string, mixed>>
     */
    public function filterModels(Request $request, array $models): array
    {
        $key = AuthenticateAiGateway::resolveKey($request);
        $allowed = $key instanceof AiGatewayApiKey ? $key->normalizedScopes()['models'] : [];
        if (in_array('*', $allowed, true)) {
            return $models;
        }

        return array_values(array_filter($models, fn (array $model): bool => in_array((string) ($model['id'] ?? ''), $allowed, true)));
    }

    private function error(string $message, string $code, int $status): JsonResponse
    {
        return response()->json([
            'error' => ['message' => $message, 'type' => 'permission_error', 'code' => $code],
        ], $status);
    }
}
