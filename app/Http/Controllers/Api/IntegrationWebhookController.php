<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IntegrationWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Workspace CRUD API for inbound integration webhooks.
 *
 * The Integrations page uses this controller for persisted endpoint metadata.
 * External delivery is handled by IncomingIntegrationWebhookController because
 * vendor callbacks do not pass through normal workspace/auth middleware.
 */
class IntegrationWebhookController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => IntegrationWebhook::forWorkspace()
                ->orderBy('name')
                ->get()
                ->map(fn (IntegrationWebhook $webhook) => $this->serialize($webhook)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'targetType' => ['required', 'string', 'in:agent,channel,task'],
            'targetId' => ['nullable', 'string', 'max:120'],
            'enabled' => ['nullable', 'boolean'],
        ]);

        $secret = Str::random(48);
        $webhook = IntegrationWebhook::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => workspace()->id,
            'name' => $data['name'],
            'target_type' => $data['targetType'],
            'target_id' => $data['targetId'] ?? null,
            'enabled' => $data['enabled'] ?? true,
            'secret' => $secret,
        ]);

        return response()->json([
            'webhook' => $this->serialize($webhook, includeSecret: true),
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $webhook = IntegrationWebhook::forWorkspace()->findOrFail($id);
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'targetType' => ['sometimes', 'required', 'string', 'in:agent,channel,task'],
            'targetId' => ['nullable', 'string', 'max:120'],
            'enabled' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('name', $data)) {
            $webhook->name = $data['name'];
        }
        if (array_key_exists('targetType', $data)) {
            $webhook->target_type = $data['targetType'];
        }
        if (array_key_exists('targetId', $data)) {
            $webhook->target_id = $data['targetId'];
        }
        if (array_key_exists('enabled', $data)) {
            $webhook->enabled = $data['enabled'];
        }

        $webhook->save();

        return response()->json(['webhook' => $this->serialize($webhook)]);
    }

    public function destroy(string $id): JsonResponse
    {
        $webhook = IntegrationWebhook::forWorkspace()->findOrFail($id);
        $webhook->delete();

        return response()->json(['success' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(IntegrationWebhook $webhook, bool $includeSecret = false): array
    {
        $endpoint = rtrim(config('app.url'), '/').'/api/webhooks/'.$webhook->id;

        return [
            'id' => $webhook->id,
            'name' => $webhook->name,
            'enabled' => $webhook->enabled,
            'targetType' => $webhook->target_type,
            'targetId' => $webhook->target_id,
            'endpoint' => $endpoint,
            'url' => $includeSecret ? $endpoint.'?secret='.$webhook->secret : $endpoint,
            'secret' => $includeSecret ? $webhook->secret : null,
            'lastTriggered' => $webhook->last_triggered_at?->diffForHumans(),
            'callCount' => $webhook->call_count,
        ];
    }
}
