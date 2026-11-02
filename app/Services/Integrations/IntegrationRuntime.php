<?php

namespace App\Services\Integrations;

use App\Domain\Authorization\Application\PolicyGrantService;
use App\Agents\Tools\ToolRegistry;
use App\Agents\Tools\Vfs\Contracts\ReturnsVfsEnvelope;
use App\Domain\Vfs\Events\VfsOperationContext;
use App\Domain\Vfs\OpenCompany\Approvals\VfsPendingApproval;
use App\Models\ApprovalRequest;
use App\Models\Channel;
use App\Models\User;
use App\Services\ScriptCallbackReceiptService;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;
use App\Agents\Tools\External\Tool as IntegrationTool;
use App\Domain\CodeMode\Script\ScriptBridgeException;
use App\Domain\CodeMode\Script\ScriptDispatchException;

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

    public function __construct(
        private ToolRegistry $registry,
        private ?ScriptCallbackReceiptService $receipts = null,
    ) {}

    /**
     * Execute one currently-authorized script callback and return a mruby-safe result.
     *
     * Discovery is not an authority grant: the registry evaluates workspace,
     * integration enablement, and tool policy immediately before construction.
     * Approval-required callbacks create an auditable, exact request and stop
     * this program; approval later executes only that callback, never retries
     * the entire Ruby source.
     *
     * VFS tools (ReturnsVfsEnvelope) report failures inside a JSON envelope
     * rather than by throwing. That envelope is unwrapped here: `ok:false`
     * becomes a ScriptBridgeException whose errorType is the VFS error code,
     * and `approval_required` (an approval created by the VFS's own composed
     * policy) becomes the same approval-pending disposition as a gated
     * callback, so CodeBridge latches dispatch. Neither is receipted as a
     * successful write.
     *
     * $receiptContext['effect'] === 'write' upgrades a callback the registry
     * types as a read (vfs_exec running a mutating pipeline) to a durable
     * write receipt. It can only upgrade, never downgrade a registered write.
     *
     * @param  array<string, mixed>  $args
     *
     * @throws ScriptDispatchException for denied or approval-pending callbacks
     * @throws ScriptBridgeException for a VFS envelope with ok:false (errorType = VFS error code)
     * @throws \RuntimeException when an allowed tool fails or returns an unsafe value
     */
    public function call(User $agent, string $toolSlug, array $args, ?string $account = null, ?array $receiptContext = null): mixed
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

        $receipt = null;
        $receiptService = null;
        if (($receiptContext['required'] ?? false) === true
            // A host classification ('write') upgrades a registered read.
            && (($receiptContext['effect'] ?? null) === 'write'
                // Only an explicit read can skip durable write intent. A missing
                // or custom effect type must fail closed rather than becoming an
                // optimistic retryable callback.
                || $this->registry->getToolTypeBySlug($toolSlug) !== 'read')) {
            // This durable intent must happen after authorization but before the
            // first provider-facing method; it is never an idempotency promise.
            $receiptService = $this->receipts ?? new ScriptCallbackReceiptService;
            $receipt = $receiptService->begin(
                $agent,
                $this->registry->getTaskContext(),
                $receiptContext['source_digest'] ?? null,
                $receiptContext['code_invocation_id'] ?? null,
                (int) ($receiptContext['sequence'] ?? 0),
                $toolSlug,
                $args,
                $account,
            );
        }

        // A single-use policy grant (if that is what allowed this callback) is
        // consumed by the attempt, before provider dispatch: a failed external
        // call may still have had effects.
        app(PolicyGrantService::class)->consumeOnce($agent, $toolSlug);

        try {
            if ($tool instanceof IntegrationTool) {
                $result = $tool->execute($args);
                if (! $result->succeeded()) {
                    throw new \RuntimeException($result->error ?? "Tool failed: {$toolSlug}");
                }

                $normalized = $this->normalize($result->data);
            } else {
                // VFS helpers record their audit events with surface `code`.
                $raw = $tool instanceof ReturnsVfsEnvelope
                    ? app(VfsOperationContext::class)->runAs('code', fn () => $tool->handle(new Request($this->snakeToCamel($args))))
                    : $tool->handle(new Request($this->snakeToCamel($args)));

                if (! is_string($raw)) {
                    $normalized = $this->normalize($raw);
                } elseif (str_contains($raw, "\nStructured data:\n")) {
                    $json = trim((string) str($raw)->afterLast("\nStructured data:\n"));
                    $decoded = $this->decodeJson($json);
                    $normalized = $decoded['decoded'] ? $this->normalize($decoded['value']) : $raw;
                } else {
                    $trimmed = ltrim($raw);
                    if (($trimmed[0] ?? '') !== '{'
                        && ($trimmed[0] ?? '') !== '['
                        && ! in_array($trimmed, ['true', 'false', 'null'], true)) {
                        $normalized = $raw;
                    } else {
                        $decoded = $this->decodeJson($raw);
                        $normalized = $decoded['decoded'] ? $this->normalize($decoded['value']) : $raw;
                    }
                }
            }

            if ($tool instanceof ReturnsVfsEnvelope) {
                $this->assertVfsEnvelopeSucceeded($toolSlug, $normalized);
            }

            if ($receipt !== null) {
                $receiptService->succeeded($receipt, $normalized);
            }

            return $normalized;
        } catch (ScriptDispatchException $exception) {
            // Only assertVfsEnvelopeSucceeded raises this inside the try: the
            // VFS policy stopped before mutating and recorded an approval.
            if ($receipt !== null) {
                $receiptService->approvalPending($receipt);
            }

            throw $exception;
        } catch (\Throwable $exception) {
            // A dispatched write with no confirmed receipt is ambiguous. Keep
            // the durable record, but never convert this into an auto-retry.
            if ($receipt !== null) {
                $receiptService->unknown($receipt);
            }

            throw $exception;
        }
    }

    /**
     * Map a VFS `ok:false` envelope to a typed bridge failure.
     *
     * The VFS reports chained `vfs_exec` failures under result.error and
     * direct failures under error; both are honored. Codes are passed through
     * as the bridge errorType (they are stable, lowercase identifiers); an
     * unexpected shape falls back to `tool_error`. internal_error messages may
     * contain raw exception text, so they are replaced with a generic one.
     *
     * @throws ScriptDispatchException approval-pending for a pending_approval envelope or approval_required
     * @throws ScriptBridgeException for every other ok:false
     */
    private function assertVfsEnvelopeSucceeded(string $toolSlug, mixed $envelope): void
    {
        // Invariant: the VFS reports "waiting for a human" as the non-error
        // pending_approval envelope (ok:true). It must still latch dispatch
        // exactly like a gated callback; VfsPendingApproval::isPending is the
        // single definition of that shape.
        if (($approvalId = VfsPendingApproval::isPending($envelope)) !== null) {
            throw ScriptDispatchException::approvalPending($approvalId);
        }

        if (! is_array($envelope) || ($envelope['ok'] ?? null) !== false) {
            return;
        }

        $error = is_array($envelope['error'] ?? null)
            ? $envelope['error']
            : (is_array($envelope['result']['error'] ?? null) ? $envelope['result']['error'] : []);
        $code = is_string($error['code'] ?? null) && preg_match('/^[a-z][a-z0-9_]{0,62}$/D', $error['code']) === 1
            ? $error['code']
            : 'tool_error';
        $context = is_array($error['context'] ?? null) ? $error['context'] : [];

        if ($code === 'approval_required' && is_string($context['approval_id'] ?? null) && $context['approval_id'] !== '') {
            throw ScriptDispatchException::approvalPending($context['approval_id']);
        }

        $message = $code === 'internal_error'
            ? 'The VFS operation failed with an internal error.'
            : Str::limit((string) ($error['message'] ?? "{$toolSlug} failed."), 600);
        // The guest only receives (type, message), so carry the repair hints
        // (minimum_bytes, current_version, lines, ...) in the message.
        $repair = $code === 'internal_error' ? [] : $this->vfsRepairContext($context);
        $suffix = $repair === [] ? '' : ' '.Str::limit((string) json_encode($repair, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 380);

        throw new ScriptBridgeException(
            $code,
            "{$code}: {$message}{$suffix}",
            ['tool' => $toolSlug, 'code' => $code],
            retryable: false,
        );
    }

    /**
     * Keep only scalar (or list-of-scalar) VFS error context. VFS context is
     * agent-facing already (direct tools return it verbatim); bulky values
     * such as captured stdout or nested objects are dropped.
     *
     * @param  array<array-key, mixed>  $context
     * @return array<string, mixed>
     */
    private function vfsRepairContext(array $context): array
    {
        $repair = [];
        foreach ($context as $key => $value) {
            if (! is_string($key) || $key === 'stdout') {
                continue;
            }
            if (is_scalar($value) || (is_array($value) && array_is_list($value) && count(array_filter($value, 'is_scalar')) === count($value))) {
                $repair[$key] = $value;
            }
        }

        return $repair;
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
            'workspace_id' => $agent->workspace_id,
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
