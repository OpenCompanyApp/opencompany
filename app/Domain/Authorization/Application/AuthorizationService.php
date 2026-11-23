<?php

namespace App\Domain\Authorization\Application;

use App\Domain\Authorization\Engine\Decision;
use App\Domain\Authorization\Engine\PolicyEngine;
use App\Models\AiGatewayApiKey;
use App\Models\User;
use App\Models\Workspace;

/**
 * Central authorization decision service (GIN-1197; ROADMAP Phase 0 P0
 * item 1). Every "may X do Y to Z?" question in HTTP controllers, agent
 * permission checks and the AI Gateway goes through here.
 *
 * Input shape (one for every caller): actor (a human, an agent, or an AI
 * Gateway key), optional on-behalf-of human (for an agent), action name,
 * resource (a model, a Workspace, or prebuilt facts) and workspace. Output:
 * an engine Decision (allow, deny, approval_required + reason code).
 *
 * Owns: assembling facts (AuthorizationFacts), calling the pure
 * PolicyEngine, recording security-relevant decisions in the ledger
 * (AuthorizationLedger, per PolicyEngine::auditMode()), and mapping denials
 * to AuthorizationDenied for HTTP callers.
 *
 * Workspace: the explicit argument, else the bound `currentWorkspace`, else
 * the agent's or key's own workspace. A human with no workspace context is
 * denied (fail closed).
 *
 * Does not own: the rules themselves. Do not add "quick" checks around
 * this service in controllers; add a rule and a conformance vector instead.
 */
class AuthorizationService
{
    public function __construct(
        private readonly PolicyEngine $engine,
        private readonly AuthorizationFacts $facts,
        private readonly AuthorizationLedger $ledger,
    ) {}

    /**
     * Decide without throwing.
     *
     * @param  mixed  $resource  model, Workspace, prebuilt facts array, or null
     * @param  array<string, mixed>  $context  action-specific facts (see README)
     * @param  bool  $record  false for bulk list filtering (one ledger row per
     *                        row of a list would be noise, not an audit)
     */
    public function decide(
        User|AiGatewayApiKey $actor,
        string $action,
        mixed $resource = null,
        array $context = [],
        ?User $onBehalfOf = null,
        ?Workspace $workspace = null,
        bool $record = true,
    ): Decision {
        $workspace ??= $this->workspaceFor($actor);
        if ($workspace === null) {
            return new Decision(Decision::DENY, 'workspace_unknown', 'No workspace context for this decision.', ['deny:workspace_unknown'], PolicyEngine::VERSION);
        }

        $resourceFacts = $this->facts->resource($resource);
        $actorId = $this->actorId($actor);
        $actors = [$actorId => $this->facts->actor($actor, $workspace)];
        if ($onBehalfOf !== null) {
            $actors[(string) $onBehalfOf->id] = $this->facts->actor($onBehalfOf, $workspace);
        }

        $decision = $this->engine->decide(
            [
                'workspace' => (string) $workspace->id,
                'actor' => $actorId,
                'on_behalf_of' => $onBehalfOf !== null ? (string) $onBehalfOf->id : null,
                'action' => $action,
            ],
            [
                'now' => now()->utc()->toIso8601String(),
                'workspace' => $this->facts->workspace($workspace),
                'actors' => $actors,
                'resource' => $resourceFacts,
                'context' => $context,
            ],
        );

        if ($record) {
            $this->record($workspace, $actor, $action, $decision, $onBehalfOf, $resourceFacts, $context);
        }

        return $decision;
    }

    /**
     * Decide and throw AuthorizationDenied unless the decision is allow.
     * approval_required is a denial for direct (HTTP) callers: only the
     * agent tool pipeline turns it into an approval card.
     *
     * @param  array<string, mixed>  $context
     */
    public function authorize(
        User|AiGatewayApiKey $actor,
        string $action,
        mixed $resource = null,
        array $context = [],
        ?User $onBehalfOf = null,
        ?Workspace $workspace = null,
    ): Decision {
        $decision = $this->decide($actor, $action, $resource, $context, $onBehalfOf, $workspace);
        if (! $decision->allowed()) {
            throw new AuthorizationDenied($action, $decision);
        }

        return $decision;
    }

    /**
     * Convenience: decide()->allowed() without ledger noise.
     *
     * @param  array<string, mixed>  $context
     */
    public function allows(User|AiGatewayApiKey $actor, string $action, mixed $resource = null, array $context = [], ?Workspace $workspace = null): bool
    {
        return $this->decide($actor, $action, $resource, $context, null, $workspace, record: false)->allowed();
    }

    /**
     * Decide many resources with prebuilt facts (list filtering). Never
     * records; the list endpoint itself is not a security change.
     *
     * @param  array<string, array<string, mixed>>  $resourceFacts  keyed by id
     * @return array<string, Decision> keyed by the same ids
     */
    public function decideMany(User $actor, string $action, array $resourceFacts, Workspace $workspace): array
    {
        $actorId = (string) $actor->id;
        $base = [
            'now' => now()->utc()->toIso8601String(),
            'workspace' => $this->facts->workspace($workspace),
            'actors' => [$actorId => $this->facts->actor($actor, $workspace)],
            'context' => [],
        ];
        $request = ['workspace' => (string) $workspace->id, 'actor' => $actorId, 'on_behalf_of' => null, 'action' => $action];

        $decisions = [];
        foreach ($resourceFacts as $id => $facts) {
            $decisions[$id] = $this->engine->decide($request, [...$base, 'resource' => $facts]);
        }

        return $decisions;
    }

    /**
     * Record a security change after it was made (role change, grants
     * replaced, key created/revoked, settings command run).
     *
     * @param  array{type: string, id: string|null}|null  $resource
     * @param  array<string, mixed>  $details
     */
    public function recordChange(User $actor, Workspace $workspace, string $action, ?array $resource, array $details = []): void
    {
        $this->ledger->recordChange(
            (string) $workspace->id,
            $action,
            ['type' => $actor->type === 'agent' ? 'agent' : 'human', 'id' => (string) $actor->id],
            $resource,
            $details,
        );
    }

    /**
     * @param  array<string, mixed>|null  $resourceFacts
     * @param  array<string, mixed>  $context
     */
    private function record(Workspace $workspace, User|AiGatewayApiKey $actor, string $action, Decision $decision, ?User $onBehalfOf, ?array $resourceFacts, array $context): void
    {
        $mode = PolicyEngine::auditMode($action);
        if ($mode === PolicyEngine::AUDIT_NEVER || ($mode === PolicyEngine::AUDIT_DENY && $decision->allowed())) {
            return;
        }

        $this->ledger->recordDecision(
            (string) $workspace->id,
            $action,
            $decision,
            ['type' => $this->actorType($actor), 'id' => $this->actorId($actor)],
            $onBehalfOf?->id !== null ? (string) $onBehalfOf->id : null,
            $resourceFacts !== null ? ['type' => (string) ($resourceFacts['type'] ?? 'unknown'), 'id' => isset($resourceFacts['id']) ? (string) $resourceFacts['id'] : null] : null,
            ['context' => $context],
        );
    }

    private function workspaceFor(User|AiGatewayApiKey $actor): ?Workspace
    {
        if (app()->bound('currentWorkspace') && app('currentWorkspace') instanceof Workspace) {
            return app('currentWorkspace');
        }
        if ($actor instanceof AiGatewayApiKey) {
            return Workspace::query()->find($actor->workspace_id);
        }
        if ($actor->type === 'agent' && $actor->workspace_id !== null) {
            return Workspace::query()->find($actor->workspace_id);
        }

        return null;
    }

    private function actorId(User|AiGatewayApiKey $actor): string
    {
        return (string) $actor->id;
    }

    private function actorType(User|AiGatewayApiKey $actor): string
    {
        return $actor instanceof AiGatewayApiKey ? 'api_key' : ($actor->type === 'agent' ? 'agent' : 'human');
    }
}
