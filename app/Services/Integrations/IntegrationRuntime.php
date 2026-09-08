<?php

namespace App\Services\Integrations;

use App\Agents\Tools\ToolRegistry;
use App\Models\ApprovalRequest;
use App\Models\Channel;
use App\Models\User;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;
use OpenCompany\IntegrationCore\Contracts\Tool as IntegrationTool;
use OpenCompany\IntegrationCore\Script\ScriptDispatchException;

/**
 * App-owned execution boundary for registered integration tools.
 *
 * The registry continues to own tool discovery, credentials, authorization,
 * and provider dispatch. This service re-checks callback authority before
 * construction, records a bounded approval request when required, and adapts
 * successful results to the restricted mruby value contract.
 */
class IntegrationRuntime
{
    private const MAX_VALUE_DEPTH = 64;

    public function __construct(private ToolRegistry $registry) {}

    /**
     * Execute one currently-authorized script callback and return a mruby-safe result.
     *
     * Discovery is not an authority grant: the registry evaluates workspace,
     * integration enablement, and tool policy immediately before construction.
     * Approval-required callbacks create an auditable, exact request and stop
     * this program; approval later executes only that callback, never retries
     * the entire Ruby source.
     *
     * @param  array<string, mixed>  $args
     *
     * @throws ScriptDispatchException for denied or approval-pending callbacks
     * @throws \RuntimeException when an allowed tool fails or returns an unsafe value
     */
    public function call(User $agent, string $toolSlug, array $args, ?string $account = null): mixed
    {
        $dispatch = $this->registry->resolveScriptToolForDispatch($toolSlug, $agent, $account);

        if ($dispatch['decision'] === 'deny') {
            throw ScriptDispatchException::denied($dispatch['reason']);
        }

        if ($dispatch['decision'] === 'approval_required') {
            $approval = $this->requestScriptApproval($agent, $toolSlug, $args, $account);

            throw ScriptDispatchException::approvalPending($approval->id);
        }

        $tool = $dispatch['tool'] ?? null;

        if ($tool === null) {
            throw new \RuntimeException("Tool not available: {$toolSlug}");
        }

        if ($tool instanceof IntegrationTool) {
            $result = $tool->execute($args);
            if (! $result->succeeded()) {
                throw new \RuntimeException($result->error ?? "Tool failed: {$toolSlug}");
            }

            return $this->normalize($result->data);
        }

        $raw = $tool->handle(new Request($this->snakeToCamel($args)));

        if (! is_string($raw)) {
            return $this->normalize($raw);
        }

        if (str_contains($raw, "\nStructured data:\n")) {
            $json = trim((string) str($raw)->afterLast("\nStructured data:\n"));
            $decoded = $this->decodeJson($json);

            if ($decoded['decoded']) {
                return $this->normalize($decoded['value']);
            }
        }

        $trimmed = ltrim($raw);
        if (($trimmed[0] ?? '') !== '{'
            && ($trimmed[0] ?? '') !== '['
            && ! in_array($trimmed, ['true', 'false', 'null'], true)) {
            return $raw;
        }

        $decoded = $this->decodeJson($raw);

        return $decoded['decoded'] ? $this->normalize($decoded['value']) : $raw;
    }

    /**
     * Persist one exact callback approval without scheduling a whole-program retry.
     *
     * The context is deliberately limited to the approved operation and its
     * account/parameters. A repeated callback while that identical request is
     * pending returns the same ID, preventing a rescued Ruby exception from
     * flooding approvers with duplicate side-effect requests.
     *
     * @param  array<string, mixed>  $args
     */
    private function requestScriptApproval(User $agent, string $toolSlug, array $args, ?string $account): ApprovalRequest
    {
        $channelId = $this->approvalChannelFor($agent);
        $context = [
            'kind' => 'script_callback',
            'tool_slug' => $toolSlug,
            'parameters' => $args,
            'account' => $account,
            'workspace_id' => $agent->workspace_id,
        ];

        $existing = ApprovalRequest::query()
            ->where('requester_id', $agent->id)
            ->where('status', 'pending')
            ->get()
            ->first(static fn (ApprovalRequest $approval): bool => $approval->tool_execution_context === $context);

        if ($existing !== null) {
            return $existing;
        }

        return ApprovalRequest::create([
            'id' => Str::uuid()->toString(),
            'type' => 'action',
            'title' => "Script callback: {$toolSlug}",
            'description' => "Agent {$agent->name} requested approval for one Code Mode callback.",
            'requester_id' => $agent->id,
            'status' => 'pending',
            'tool_execution_context' => $context,
            'channel_id' => $channelId,
        ]);
    }

    /**
     * Attach an approval only to the registry's currently bound workspace channel.
     *
     * A channel ID is untrusted routing context, not authorization. Resolving it
     * against the agent workspace keeps approval inbox visibility from becoming
     * a cross-workspace data leak.
     */
    private function approvalChannelFor(User $agent): ?string
    {
        $channelId = $this->registry->getChannelContext();
        if ($channelId === null) {
            return null;
        }

        return Channel::query()
            ->whereKey($channelId)
            ->where('workspace_id', $agent->workspace_id)
            ->exists() ? $channelId : null;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function snakeToCamel(array $params): array
    {
        $converted = [];
        foreach ($params as $key => $value) {
            $converted[lcfirst(str_replace('_', '', ucwords((string) $key, '_')))] = $value;
        }

        return $converted;
    }

    /**
     * Decode JSON while retaining enough object identity for normalize() to preserve {} and numeric-key objects.
     *
     * Syntax errors leave ordinary text-tool output untouched; a nesting-limit
     * failure is a rejected structured result rather than a lossy fallback.
     *
     * @return array{decoded: bool, value?: mixed}
     */
    private function decodeJson(string $json): array
    {
        try {
            return ['decoded' => true, 'value' => json_decode($json, false, self::MAX_VALUE_DEPTH, JSON_THROW_ON_ERROR)];
        } catch (\JsonException $exception) {
            if ($exception->getCode() === JSON_ERROR_DEPTH) {
                throw new \RuntimeException('Integration result exceeds the supported nesting depth.');
            }

            return ['decoded' => false];
        }
    }

    /**
     * Normalize a successful provider value into data mruby can represent exactly.
     *
     * Non-empty JSON-style objects remain associative PHP arrays for existing
     * consumers. Empty objects and objects with integer-like keys must remain
     * stdClass, because converting either to an array would make mruby observe
     * an array instead of an object. Integers deliberately stay integers: a
     * float fallback silently corrupts identifiers above 2^53.
     *
     * @throws \RuntimeException when a value is non-finite, unsupported, cyclic, or too deeply nested
     */
    private function normalize(mixed $value, int $depth = 0, ?\SplObjectStorage $seen = null): mixed
    {
        if ($depth > self::MAX_VALUE_DEPTH) {
            throw new \RuntimeException('Integration result exceeds the supported nesting depth.');
        }

        if (is_int($value)) {
            return $value;
        }

        if ($value === null || is_bool($value) || is_string($value)) {
            return $value;
        }

        if (is_float($value)) {
            if (! is_finite($value)) {
                throw new \RuntimeException('Integration result contains a non-finite number.');
            }

            return $value;
        }

        $seen ??= new \SplObjectStorage;

        if ($value instanceof \JsonSerializable) {
            return $this->normalizeObject($value, fn (): mixed => $value->jsonSerialize(), $depth, $seen);
        }

        if ($value instanceof Arrayable) {
            return $this->normalizeObject($value, fn (): mixed => $value->toArray(), $depth, $seen);
        }

        if ($value instanceof \stdClass) {
            return $this->normalizeObject($value, fn (): array => get_object_vars($value), $depth, $seen, preserveObjectShape: true);
        }

        if (is_object($value) || is_resource($value)) {
            throw new \RuntimeException('Integration result contains an unsupported value.');
        }

        if (! is_array($value)) {
            throw new \RuntimeException('Integration result contains an unsupported value.');
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            $normalized[$key] = $this->normalize($item, $depth + 1, $seen);
        }

        return $normalized;
    }

    /**
     * Normalize an object without letting object identity or arbitrary string casts cross the guest boundary.
     *
     * @param  callable(): mixed  $extract  Controlled serialization callback for supported object contracts
     */
    private function normalizeObject(
        object $object,
        callable $extract,
        int $depth,
        \SplObjectStorage $seen,
        bool $preserveObjectShape = false,
    ): mixed {
        if ($seen->contains($object)) {
            throw new \RuntimeException('Integration result contains a cyclic object graph.');
        }

        $seen->attach($object);
        try {
            $properties = $extract();
            $normalized = $this->normalize($properties, $depth + 1, $seen);
        } finally {
            $seen->detach($object);
        }

        if (! is_array($normalized)) {
            return $normalized;
        }

        // PHP arrays cannot distinguish {} from [], nor preserve JSON object key intent.
        if ($preserveObjectShape && ($normalized === [] || $this->hasIntegerLikeKeys($normalized))) {
            return (object) $normalized;
        }

        return $normalized;
    }

    /** @param array<array-key, mixed> $value */
    private function hasIntegerLikeKeys(array $value): bool
    {
        foreach (array_keys($value) as $key) {
            if (is_int($key) || (is_string($key) && preg_match('/^-?(?:0|[1-9][0-9]*)$/', $key) === 1)) {
                return true;
            }
        }

        return false;
    }
}
