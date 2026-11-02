<?php

namespace App\Services;

use App\Agents\Runtime\ApprovedToolReplay;
use App\Agents\Support\UntrustedContent;
use App\Agents\Tools\ToolRegistry;
use App\Domain\Authorization\Application\ApprovalResolutionDenied;
use App\Domain\Authorization\Application\ApprovalResolutionPolicy;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsTextPatch;
use App\Domain\Vfs\Events\VfsOperationContext;
use App\Domain\Vfs\OpenCompany\Approvals\VfsApprovalRequestFactory;
use App\Domain\Vfs\OpenCompany\Approvals\VfsApprovedExecutionContext;
use App\Domain\Vfs\OpenCompany\Approvals\VfsDirectToolOperation;
use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Jobs\AgentRespondJob;
use App\Models\AgentPermission;
use App\Models\ApprovalRequest;
use App\Models\Channel;
use App\Models\Message;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request as ToolRequest;
use OpenCompany\IntegrationCore\Contracts\Tool as IntegrationTool;

/**
 * Applies approval decisions to pending tool/access requests.
 *
 * Approval callbacks can arrive from external chat buttons or in-app flows after
 * the original agent job has paused. This service re-binds workspace context,
 * executes the approved side effect once, records a message result, and resumes
 * the waiting agent when appropriate.
 */
class ApprovalExecutionService
{
    public function __construct(
        private ToolRegistry $toolRegistry,
        private ?AgentPermissionService $permissions = null,
        private ?VfsApprovedExecutionContext $vfsApprovedContext = null,
        private ?VfsApprovalRequestFactory $vfsApprovals = null,
    ) {}

    /**
     * Atomically record a human decision and apply its side effects once.
     *
     * Approval buttons can be delivered through several transports and may be
     * clicked concurrently. The conditional update is the single claim point:
     * only the request that changes `pending` may execute the approved action
     * or resume the waiting agent. Side effects deliberately run after the
     * decision is durable so a slow provider call does not hold a DB lock.
     *
     * Returns false when the approval was no longer pending (lost the race).
     * Throws ApprovalResolutionDenied (403-mapped AuthorizationException) when
     * the responder may not resolve it: agents, self-approval, unattributed
     * callers and workspace members without the admin/approver role. Every
     * transport funnels through here, so this is the enforcement point.
     *
     * @throws ApprovalResolutionDenied
     */
    public function resolve(ApprovalRequest $approval, string $status, ?User $responder = null): bool
    {
        if (! in_array($status, ['approved', 'rejected'], true)) {
            throw new \InvalidArgumentException('Approval status must be approved or rejected.');
        }

        // Invariant: authorization is checked BEFORE the atomic pending->status
        // claim below. A refused responder must leave the row pending with no
        // side effects. Do not move this after the claim or into a transport.
        app(ApprovalResolutionPolicy::class)->authorize($approval, $responder);

        $claimed = ApprovalRequest::query()
            ->whereKey($approval->getKey())
            ->where('status', 'pending')
            ->update([
                'status' => $status,
                'responded_by_id' => $responder?->id,
                'responded_at' => now(),
            ]);

        if ($claimed !== 1) {
            $approval->refresh();

            return false;
        }

        $approval->refresh();

        /** @var User|null $agent */
        $agent = $approval->requester;
        $agentIsWaiting = $agent
            && $agent->type === 'agent'
            && $agent->awaiting_approval_id === $approval->id;

        if ($status === 'approved' && $approval->tool_execution_context) {
            if ($approval->type === 'access') {
                $this->executeApprovedAccess($approval, $agentIsWaiting);
            } else {
                $this->executeApprovedTool($approval, $agentIsWaiting);
            }
        } elseif ($status === 'rejected' && $agentIsWaiting) {
            $this->handleRejectedTool($approval);
        }

        return true;
    }

    /**
     * Execute exactly one approved tool call after a human decision.
     *
     * Approval is permission to execute this exact call once, not a permanent
     * capability grant. Invariant: before any tool is constructed the FULL
     * permission chain is re-evaluated (workspace binding, integration
     * enablement, explicit deny, tool policy) via
     * ToolRegistry::resolveScriptToolForDispatch(). A current `deny` blocks
     * execution; `approval_required` is satisfied by this approved row only.
     *
     * Script callbacks never resume their Ruby source. Their durable context
     * contains only the single approved slug, account, and parameters; a row
     * lock claims that callback before provider construction so duplicate UI or
     * webhook deliveries cannot execute it twice.
     */
    public function executeApprovedTool(ApprovalRequest $approval, bool $agentIsWaiting): void
    {
        /** @var User|null $agent */
        $agent = $approval->requester;

        if ($approval->status !== 'approved' || ! $agent) {
            return;
        }

        $initialContext = $approval->tool_execution_context ?? [];
        $isScriptCallback = ($initialContext['kind'] ?? null) === 'script_callback';
        $context = $isScriptCallback ? $this->claimScriptCallback($approval, $agent) : $initialContext;
        if ($context === null) {
            return;
        }

        $toolSlug = $context['tool_slug'] ?? null;
        if (! is_string($toolSlug) || $toolSlug === '') {
            return;
        }

        // An approval recorded in one workspace never executes in another,
        // even if the requesting agent row was later moved.
        if ($approval->workspace_id !== null && $approval->workspace_id !== $agent->workspace_id) {
            Log::warning('Approved tool skipped: approval workspace does not match the agent workspace.', [
                'approval_id' => $approval->id,
            ]);
            if ($isScriptCallback) {
                $this->recordScriptCallbackDisposition($approval->id, $context, 'denied');
            }

            return;
        }

        // Queue and webhook callbacks may not have ResolveWorkspace middleware.
        // Bind from the requesting agent before instantiating tools that rely on
        // workspace() or forWorkspace().
        $this->bindWorkspaceContext($agent);

        $account = $isScriptCallback && is_string($context['account'] ?? null)
            ? $context['account']
            : null;
        $parameters = is_array($context['parameters'] ?? null) ? $context['parameters'] : [];

        // VFS approvals (composed `vfs_approval` or wrapper `vfs_meta`) get
        // the VFS replay UX: pinned-base check, outcome posted back to the
        // originating conversation, and a persisted disposition.
        $vfsMeta = $isScriptCallback ? null : $this->vfsApprovalMeta($context);

        // Invariant: full-chain re-evaluation happens before tool construction.
        $dispatch = $this->toolRegistry->resolveScriptToolForDispatch($toolSlug, $agent, $account);
        if ($dispatch['decision'] === 'deny') {
            Log::warning("Approved tool {$toolSlug} was not executed because permission is now denied.", [
                'approval_id' => $approval->id,
                'agent_id' => $agent->id,
                'reason' => $dispatch['reason'],
            ]);
            if ($isScriptCallback) {
                $this->recordScriptCallbackDisposition($approval->id, $context, 'denied');
            }
            if ($vfsMeta !== null) {
                $this->finishVfsReplay($approval, $agent, $agentIsWaiting, 'denied', 'permission_denied', '**Approved action not executed:** '.$toolSlug.' is no longer permitted for this agent ('.$dispatch['reason'].').');

                return;
            }
            if ($agentIsWaiting) {
                $agent->clearAwaitingApproval();
            }

            return;
        }

        // A still-required approval is satisfied only by this approved row.
        // Do not route through IntegrationRuntime, which would create a second
        // pending request instead of executing the approved call.
        $tool = $dispatch['decision'] === 'allow'
            ? ($dispatch['tool'] ?? null)
            : $this->toolRegistry->instantiateToolBySlug($toolSlug, $agent, $account);

        if (! $tool) {
            if ($isScriptCallback) {
                // No provider was constructed, so this is a pre-dispatch
                // unavailable result rather than an ambiguous external write.
                $this->recordScriptCallbackDisposition($approval->id, $context, 'unavailable');
            }
            if ($vfsMeta !== null) {
                $this->finishVfsReplay($approval, $agent, $agentIsWaiting, 'unavailable', 'unavailable', "**Approved action not executed:** {$toolSlug} is not available.");
            }

            return;
        }

        // Invariant (pinned base): the human approved a change to the object
        // as it was when the request was made. If it moved since, only an
        // anchored edit (text search/replace that must still match exactly
        // once, or a row patch with per-field `base`) may re-apply; anything
        // else fails as `conflict` without executing.
        if ($vfsMeta !== null && ($conflict = $this->vfsPinnedBaseConflict($agent, $toolSlug, $parameters, $vfsMeta)) !== null) {
            $this->finishVfsReplay(
                $approval,
                $agent,
                $agentIsWaiting,
                'conflict',
                'conflict',
                '**Approved action not applied (conflict):** '.$conflict->getMessage().' '.json_encode(['ok' => false, 'error' => ['code' => $conflict->errorCode, 'message' => $conflict->getMessage(), 'context' => $conflict->context]], JSON_UNESCAPED_SLASHES),
            );

            return;
        }

        try {
            if ($isScriptCallback && ! $this->markScriptCallbackDispatched($approval->id, $context)) {
                return;
            }

            if ($tool instanceof IntegrationTool) {
                $execution = $tool->execute($parameters);
                if (! $execution->succeeded()) {
                    throw new \RuntimeException($execution->error ?? "Approved tool failed: {$toolSlug}");
                }

                $result = json_encode($execution->data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: 'Approved integration callback completed.';
            } else {
                $toolParameters = $isScriptCallback ? $this->snakeToCamel($parameters) : $parameters;
                $toolRequest = new ToolRequest($toolParameters);
                // Hash exactly what the tool will see: VFS policy hashes
                // $request->toArray(), so a snake_case script payload must be
                // hashed after the same camelCase conversion.
                $vfsContext = $this->vfsReplayContext($agent, $toolSlug, $toolParameters, $context);
                // Mark this exact call as the approved replay so per-call gates
                // inside the tool do not request approval a second time.
                app()->instance(ApprovedToolReplay::class, new ApprovedToolReplay($approval->id, $toolSlug));
                try {
                    // VFS events of the replay carry surface approval_replay
                    // and this approval id.
                    $result = app(VfsOperationContext::class)->runAs('approval_replay', fn () => $vfsContext === null
                        ? $tool->handle($toolRequest)
                        : ($this->vfsApprovedContext ?? app(VfsApprovedExecutionContext::class))->runWith(
                            $vfsContext,
                            fn () => $tool->handle($toolRequest),
                        ), $approval->id);
                } finally {
                    app()->forgetInstance(ApprovedToolReplay::class);
                }
            }
            $result = (string) $result;
            $decodedResult = json_decode($result, true);
            // Results of external tools re-enter conversation history as an
            // agent-authored message; label them so the resumed agent treats
            // them as data, not instructions.
            $displayResult = ($tool instanceof IntegrationTool || str_starts_with($toolSlug, 'mcp_'))
                ? UntrustedContent::wrap($result, 'approved_tool_result', $toolSlug)
                : $result;
            $toolSucceeded = ! (is_array($decodedResult) && ($decodedResult['ok'] ?? true) === false);

            if ($isScriptCallback) {
                $this->recordScriptCallbackDisposition($approval->id, $context, 'succeeded');
            }

            if ($vfsMeta !== null) {
                $errorCode = $toolSucceeded ? null : (string) ($decodedResult['error']['code'] ?? 'tool_error');
                $this->finishVfsReplay(
                    $approval,
                    $agent,
                    $agentIsWaiting,
                    $toolSucceeded ? 'succeeded' : ($errorCode === 'conflict' ? 'conflict' : 'failed'),
                    $errorCode,
                    ($toolSucceeded ? '**Approved action executed:** ' : '**Approved action failed:** ').$displayResult,
                );

                return;
            }

            // Post the result as a normal channel message so the resumed agent
            // sees the approved side effect in conversation history.
            $channelId = $approval->channel_id ?? ($context['parameters']['channelId'] ?? null);
            if ($channelId) {
                $channel = $this->approvalChannel($agent, $channelId);
                if ($channel) {
                    $resultMessage = Message::create([
                        'id' => Str::uuid()->toString(),
                        'content' => ($toolSucceeded ? '**Approved action executed:** ' : '**Approved action failed:** ').$displayResult,
                        'channel_id' => $channelId,
                        'author_id' => $agent->id,
                        'timestamp' => now(),
                    ]);
                    $channel->update(['last_message_at' => now()]);

                    // Resume only the agent that explicitly parked on this
                    // approval. Non-waiting approvals still execute, but they
                    // should not create an extra response loop.
                    if ($agentIsWaiting) {
                        $agent->clearAwaitingApproval();
                        $task = Task::createPending($resultMessage, $agent, $channelId);
                        AgentRespondJob::dispatch($resultMessage, $agent, $channelId, $task->id);
                    }
                }
            } elseif ($agentIsWaiting) {
                $agent->clearAwaitingApproval();
            }
        } catch (\Throwable $e) {
            Log::error('Approved tool execution failed; inspect its recorded disposition before retrying.', [
                'tool_slug' => $toolSlug, 'approval_id' => $approval->id,
                'exception_type' => get_class($e),
            ]);

            if ($isScriptCallback) {
                // The callback reached a provider boundary. Preserve an
                // unknown, non-retryable receipt without storing its raw error.
                $this->recordScriptCallbackDisposition($approval->id, $context, 'unknown');
            }

            if ($vfsMeta !== null) {
                // The write may or may not have committed; say so instead of
                // claiming failure, and never auto-retry.
                $this->finishVfsReplay($approval, $agent, $agentIsWaiting, 'unknown', 'internal_error', "**Approved action outcome unknown:** {$toolSlug} raised an internal error during execution. Verify the current state with a read before retrying.");

                return;
            }

            if ($agentIsWaiting) {
                $agent->clearAwaitingApproval();
            }
        }
    }

    /**
     * VFS display/replay metadata of an approval: the composed policy block
     * (`vfs_approval`) or the wrapper block (`vfs_meta`), else null.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>|null
     */
    private function vfsApprovalMeta(array $context): ?array
    {
        $meta = $context['vfs_approval'] ?? $context['vfs_meta'] ?? null;

        return is_array($meta) ? $meta : null;
    }

    /**
     * Conflict when the approved object moved since the request was made and
     * the change is not anchored (see the invariant at the call site). Null
     * when nothing is pinned, the object is unchanged, or the change carries
     * its own anchor (re-validated by the VFS during replay).
     *
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>  $meta
     */
    private function vfsPinnedBaseConflict(User $agent, string $toolSlug, array $parameters, array $meta): ?VfsError
    {
        $base = $meta['base_version'] ?? null;
        $subject = $meta['subject'] ?? null;
        if (! is_string($base) || $base === '' || ! is_string($subject) || ! str_starts_with($subject, '/')) {
            return null;
        }

        try {
            $current = app(VfsOperationContext::class)->unaudited(fn (): array => app(OpenCompanyVfs::class)->stat($agent, $subject));
            $currentVersion = is_string($current['version'] ?? null) ? $current['version'] : null;
        } catch (VfsError $e) {
            if ($e->errorCode !== 'not_found') {
                // Unreadable now: let the replay produce the authoritative error.
                return null;
            }
            $currentVersion = null;
        }

        if ($currentVersion !== null && hash_equals($base, $currentVersion)) {
            return null;
        }

        if ($currentVersion !== null && $this->vfsChangeIsAnchored($toolSlug, $subject, $parameters)) {
            return null;
        }

        return VfsError::conflict(
            $subject,
            $currentVersion,
            $base,
            'The object changed after this approval was requested, so the approved change was not applied. Read it again and request the change against the current version.',
            ['approval_base_version' => $base],
            "Approved change to {$subject} was not applied: the object changed after approval was requested.",
        );
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function vfsChangeIsAnchored(string $toolSlug, string $subject, array $parameters): bool
    {
        if (! in_array($toolSlug, ['vfs_patch', 'vfs_edit'], true) || ! is_string($parameters['patch'] ?? null)) {
            return false;
        }

        $isRecord = preg_match('#^/(?:tables/.+/rows/|tasks/|lists/|issues/)#', $subject) === 1;
        if ($isRecord) {
            $decoded = json_decode($parameters['patch'], true);

            return is_array($decoded) && is_array($decoded['base'] ?? null) && $decoded['base'] !== [];
        }

        try {
            VfsTextPatch::parse($parameters['patch']);

            return true;
        } catch (VfsError) {
            return false;
        }
    }

    /**
     * Persist the disposition of a VFS approval replay, post the outcome to
     * the originating conversation and resume a parked agent there.
     *
     * Disposition (tool_execution_context.execution): {disposition:
     * succeeded|failed|conflict|denied|unavailable|unknown, error_code,
     * recorded_at}. Without a conversation the outcome is still persisted and
     * a parked agent is released (it can read /approvals/{id}.json).
     */
    private function finishVfsReplay(ApprovalRequest $approval, User $agent, bool $agentIsWaiting, string $disposition, ?string $errorCode, string $message): void
    {
        DB::transaction(function () use ($approval, $disposition, $errorCode): void {
            $locked = ApprovalRequest::query()->lockForUpdate()->find($approval->id);
            if ($locked === null) {
                return;
            }
            $context = $locked->tool_execution_context ?? [];
            $context['execution'] = [
                'disposition' => $disposition,
                'error_code' => $errorCode,
                'recorded_at' => now()->toIso8601String(),
            ];
            $locked->tool_execution_context = $context;
            $locked->save();
        });

        $channelId = $approval->channel_id ?? ($approval->tool_execution_context['parameters']['channelId'] ?? null);
        $channel = is_string($channelId) ? $this->approvalChannel($agent, $channelId) : null;
        if ($channel === null) {
            if ($agentIsWaiting) {
                $agent->clearAwaitingApproval();
            }

            return;
        }

        $resultMessage = Message::create([
            'id' => Str::uuid()->toString(),
            'content' => $message,
            'channel_id' => $channel->id,
            'author_id' => $agent->id,
            'timestamp' => now(),
        ]);
        $channel->update(['last_message_at' => now()]);

        // Resume only the agent that explicitly parked on this approval;
        // non-waiting agents see the outcome in conversation history.
        if ($agentIsWaiting) {
            $agent->clearAwaitingApproval();
            $task = Task::createPending($resultMessage, $agent, $channel->id);
            AgentRespondJob::dispatch($resultMessage, $agent, $channel->id, $task->id);
        }
    }

    /**
     * Atomically reserve one script callback before post-approval preflight.
     *
     * Approval rows are durable audit records, but they do not grant access to
     * a different workspace. The returned array is the locked authoritative
     * context; never dispatch from a stale model payload supplied by a webhook.
     * A claim token prevents duplicate approval deliveries from reaching the
     * provider after preflight succeeds.
     *
     * @return array<string, mixed>|null
     */
    private function claimScriptCallback(ApprovalRequest $approval, User $agent): ?array
    {
        return DB::transaction(function () use ($approval, $agent): ?array {
            $locked = ApprovalRequest::query()->lockForUpdate()->find($approval->id);
            if ($locked === null || $locked->status !== 'approved') {
                return null;
            }

            $context = $locked->tool_execution_context ?? [];
            if (($context['kind'] ?? null) !== 'script_callback'
                || ($context['workspace_id'] ?? null) !== $agent->workspace_id
                || isset($context['script_callback_claim_token'])) {
                return null;
            }

            $context['script_callback_claim_token'] = Str::uuid()->toString();
            $context['script_callback_claimed_at'] = now()->toIso8601String();
            $context['script_callback_disposition'] = 'preflight';
            $locked->tool_execution_context = $context;
            $locked->save();

            return $context;
        });
    }

    /** @param array<string, mixed> $context */
    private function markScriptCallbackDispatched(string $approvalId, array $context): bool
    {
        return DB::transaction(function () use ($approvalId, $context): bool {
            $locked = ApprovalRequest::query()->lockForUpdate()->find($approvalId);
            $lockedContext = $locked?->tool_execution_context ?? [];
            if ($locked === null
                || ($lockedContext['script_callback_claim_token'] ?? null) !== ($context['script_callback_claim_token'] ?? null)
                || ($lockedContext['script_callback_disposition'] ?? null) !== 'preflight') {
                return false;
            }

            $lockedContext['script_callback_dispatched_at'] = now()->toIso8601String();
            $lockedContext['script_callback_disposition'] = 'dispatching';
            $locked->tool_execution_context = $lockedContext;
            $locked->save();

            return true;
        });
    }

    /** @param array<string, mixed> $context */
    private function recordScriptCallbackDisposition(string $approvalId, array $context, string $disposition): void
    {
        DB::transaction(function () use ($approvalId, $context, $disposition): void {
            $locked = ApprovalRequest::query()->lockForUpdate()->find($approvalId);
            $lockedContext = $locked?->tool_execution_context ?? [];
            if ($locked === null
                || ($lockedContext['script_callback_claim_token'] ?? null) !== ($context['script_callback_claim_token'] ?? null)) {
                return;
            }

            $lockedContext['script_callback_disposition'] = $disposition;
            $lockedContext['script_callback_receipt'] = [
                'disposition' => $disposition,
                'recorded_at' => now()->toIso8601String(),
            ];
            $locked->tool_execution_context = $lockedContext;
            $locked->save();
        });
    }

    /** @param array<string, mixed> $params @return array<string, mixed> */
    private function snakeToCamel(array $params): array
    {
        $converted = [];
        foreach ($params as $key => $value) {
            $converted[lcfirst(str_replace('_', '', ucwords((string) $key, '_')))] = $value;
        }

        return $converted;
    }

    /**
     * Grant access after an access-type approval is approved.
     * Creates an AgentPermission record and notifies the agent.
     */
    public function executeApprovedAccess(ApprovalRequest $approval, bool $agentIsWaiting): void
    {
        $context = $approval->tool_execution_context ?? [];
        /** @var User|null $agent */
        $agent = $approval->requester;
        $scopeType = $context['scope_type'] ?? null;
        $scopeKey = $context['scope_key'] ?? null;

        if (! $agent || ! $scopeType || ! $scopeKey) {
            return;
        }

        $this->bindWorkspaceContext($agent);

        try {
            // Approval of access reverses a previous deny for the exact scope
            // before creating the allow record, so future permission resolution
            // has one clear winning rule.
            AgentPermission::forAgent($agent->id)
                ->where('scope_type', $scopeType)
                ->where('scope_key', $scopeKey)
                ->where('permission', 'deny')
                ->delete();

            // Create the allow permission record
            AgentPermission::create([
                'id' => Str::uuid()->toString(),
                'agent_id' => $agent->id,
                'scope_type' => $scopeType,
                'scope_key' => $scopeKey,
                'permission' => 'allow',
                'requires_approval' => false,
            ]);

            // Notify through the original channel when possible. That message
            // becomes the prompt that wakes the agent if it was waiting.
            $channelId = $approval->channel_id;
            if ($channelId) {
                $channel = $this->approvalChannel($agent, $channelId);
                if ($channel) {
                    $message = Message::create([
                        'id' => Str::uuid()->toString(),
                        'content' => "**Access granted:** You now have access to {$scopeType} `{$scopeKey}`.",
                        'channel_id' => $channelId,
                        'author_id' => $agent->id,
                        'timestamp' => now(),
                    ]);
                    $channel->update(['last_message_at' => now()]);

                    if ($agentIsWaiting) {
                        $agent->clearAwaitingApproval();
                        $task = Task::createPending($message, $agent, $channelId);
                        AgentRespondJob::dispatch($message, $agent, $channelId, $task->id);

                        return;
                    }
                }
            }

            if ($agentIsWaiting) {
                $agent->clearAwaitingApproval();
            }
        } catch (\Throwable $e) {
            Log::error("Failed to execute approved access for {$scopeType}/{$scopeKey}: {$e->getMessage()}");

            if ($agentIsWaiting) {
                $agent->clearAwaitingApproval();
            }
        }
    }

    /**
     * Handle a rejected approval when the agent was waiting.
     */
    private function handleRejectedTool(ApprovalRequest $approval): void
    {
        /** @var User|null $agent */
        $agent = $approval->requester;
        if (! $agent) {
            return;
        }

        $this->bindWorkspaceContext($agent);

        $context = $approval->tool_execution_context ?? [];
        $channelId = $approval->channel_id ?? ($context['parameters']['channelId'] ?? null);

        if ($channelId) {
            $channel = $this->approvalChannel($agent, $channelId);
            if ($channel) {
                $denialMessage = Message::create([
                    'id' => Str::uuid()->toString(),
                    'content' => "**Approval denied:** {$approval->title}. The requested action was not approved.",
                    'channel_id' => $channelId,
                    'author_id' => $agent->id,
                    'timestamp' => now(),
                ]);
                $channel->update(['last_message_at' => now()]);

                $agent->clearAwaitingApproval();
                $task = Task::createPending($denialMessage, $agent, $channelId);
                AgentRespondJob::dispatch($denialMessage, $agent, $channelId, $task->id);
            } else {
                $agent->clearAwaitingApproval();
            }
        } else {
            $agent->clearAwaitingApproval();
        }
    }

    /**
     * Bind workspace context from agent so workspace() and forWorkspace() work in queue workers.
     */
    private function bindWorkspaceContext(User $agent): void
    {
        if ($agent->workspace_id) {
            $workspace = Workspace::find($agent->workspace_id);
            if ($workspace) {
                app()->instance('currentWorkspace', $workspace);
            }
        }
    }

    private function approvalChannel(User $agent, string $channelId): ?Channel
    {
        return Channel::query()
            ->where('workspace_id', $agent->workspace_id)
            ->find($channelId);
    }

    /**
     * Build the internal-only approval context used by VFS policy during
     * approved replay.
     *
     * Two origins:
     * - VFS-composed approvals (created by VfsApprovalRequestFactory) carry
     *   their own `vfs_approval` block whose approved_tools already list every
     *   gated tool of the operation.
     * - Wrapper/script approvals of a VFS primitive (ApprovalWrappedTool,
     *   IntegrationRuntime) approve the primitive call as a whole. The human
     *   approved "run this vfs_patch/vfs_exec/vfs_write call", and the call
     *   intrinsically needs its composed backing permissions, so approved_tools
     *   is the full required set. Without that, a second gated permission
     *   (vfs_write behind vfs_exec, update_table_row behind vfs_patch) would
     *   create a new approval on every replay. A current deny still blocks:
     *   policy re-evaluates denial before consulting approvals.
     *
     * Invariant: subject normalization must match the policy exactly
     * (VfsDirectToolOperation uses VfsPath::normalize for paths and
     * VfsPath::commandSubject for commands).
     *
     * @param  array<string, mixed>  $parameters  The parameters exactly as passed to the tool request
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>|null
     */
    private function vfsReplayContext(User $agent, string $toolSlug, array $parameters, array $context): ?array
    {
        if (is_array($context['vfs_approval'] ?? null)) {
            return $context['vfs_approval'];
        }

        if (! in_array($toolSlug, VfsDirectToolOperation::REPLAYABLE_TOOLS, true)) {
            return null;
        }

        $approvals = $this->vfsApprovals ?? app(VfsApprovalRequestFactory::class);
        $operation = VfsDirectToolOperation::fromToolCall($toolSlug, $parameters);
        if ($operation === null) {
            return null;
        }

        return [
            'requester_id' => $agent->id,
            'direct_tool_slug' => $toolSlug,
            'operation' => $operation->operation,
            'subject' => $operation->subject,
            'required_tools' => $operation->requiredTools,
            'approval_tool' => $toolSlug,
            'approved_tools' => $operation->requiredTools,
            'parameters_hash' => $approvals->hashParameters($parameters),
        ];
    }
}
