<?php

namespace App\Services;

use App\Agents\Tools\ToolRegistry;
use App\Models\User;
use App\Services\Integrations\IntegrationRuntime;
use OpenCompany\IntegrationCore\Contracts\ScriptToolInvoker;

/**
 * Routes Code Mode bridge calls into OpenCompany's integration runtime.
 *
 * Ruby programs must not instantiate tools directly. This invoker keeps
 * calls on the same permission, account-alias, and result-normalization path as
 * agent SDK tool calls.
 */
class OpenCompanyScriptToolInvoker implements ScriptToolInvoker
{
    public function __construct(
        private User $agent,
        private ToolRegistry $registry,
        private ?IntegrationRuntime $runtime = null,
        private readonly bool $requireTaskReceiptForWrites = true,
    ) {}

    /** Bind one engine execution to all callbacks it makes, in call order. */
    public function bindExecution(string $sourceDigest, string $codeInvocationId): void
    {
        $this->sourceDigest = $sourceDigest;
        $this->codeInvocationId = $codeInvocationId;
        $this->sequence = 0;
    }

    private ?string $sourceDigest = null;

    private ?string $codeInvocationId = null;

    private int $sequence = 0;

    /**
     * Host-classified effect for the next dispatch of one tool slug.
     *
     * ScriptBridge asks getToolMeta() for the effect before it normalizes
     * arguments, so it cannot see that `app.vfs.shell("rm ...")` writes while
     * `app.vfs.shell("ls")` reads. CodeBridge classifies the call from its raw
     * arguments and records the result here immediately before dispatching;
     * the hint is consumed by the next invoke() (or cleared by the next
     * CodeBridge call), so it never leaks to a different callback.
     *
     * @var array{slug: string, effect: 'read'|'write'}|null
     */
    private ?array $nextCallEffect = null;

    /**
     * Classify the immediately following dispatch of $toolSlug. Only CodeBridge
     * calls this, with an effect it derived itself; null clears the hint.
     *
     * @param  'read'|'write'|null  $effect
     */
    public function classifyNextCall(string $toolSlug, ?string $effect): void
    {
        $this->nextCallEffect = $effect === null ? null : ['slug' => $toolSlug, 'effect' => $effect];
    }

    public function invoke(string $toolSlug, array $args, ?string $account = null): mixed
    {
        $runtime = $this->runtime ?? new IntegrationRuntime($this->registry);
        $effect = $this->classifiedEffect($toolSlug);
        $this->nextCallEffect = null;

        // IntegrationRuntime handles permission evaluation and package/MCP
        // dispatch. Do not bypass it here just because Code Mode resolved a
        // function name to a tool slug.
        return $runtime->call($this->agent, $toolSlug, $args, $account, [
            'required' => $this->requireTaskReceiptForWrites,
            'source_digest' => $this->sourceDigest,
            'code_invocation_id' => $this->codeInvocationId,
            'sequence' => ++$this->sequence,
            // Upgrade-only: IntegrationRuntime treats 'write' as "receipt
            // required" and ignores anything else in favor of the registry.
            'effect' => $effect,
        ]);
    }

    /** @return 'read'|'write'|null */
    private function classifiedEffect(string $toolSlug): ?string
    {
        return ($this->nextCallEffect['slug'] ?? null) === $toolSlug ? $this->nextCallEffect['effect'] : null;
    }

    public function getToolMeta(string $toolSlug): array
    {
        $meta = $this->registry->getToolMetaBySlug($toolSlug);
        // Display metadata intentionally contains only name/icon. ScriptBridge
        // needs effect semantics for its retry ledger, so obtain them from the
        // dedicated registry accessor rather than trusting a display shape.
        // Unknown/custom providers are treated as writes: optimistic reads can
        // cause an ambiguous external effect to become retryable.
        $meta['type'] = $this->registry->getToolTypeBySlug($toolSlug) === 'read' ? 'read' : 'write';
        // A host classification may only make a registered read stricter.
        if ($this->classifiedEffect($toolSlug) === 'write') {
            $meta['type'] = 'write';
        }

        return $meta;
    }
}
