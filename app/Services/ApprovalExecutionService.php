<?php

namespace App\Services;

use App\Agents\Tools\ToolRegistry;
use App\Domain\Vfs\OpenCompany\Approvals\VfsApprovalRequestFactory;
use App\Domain\Vfs\OpenCompany\Approvals\VfsApprovedExecutionContext;
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
     */
    public function resolve(ApprovalRequest $approval, string $status, ?User $responder = null): bool
    {
        if (! in_array($status, ['approved', 'rejected'], true)) {
            throw new \InvalidArgumentException('Approval status must be approved or rejected.');
        }

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
                $toolRequest = new ToolRequest($isScriptCallback ? $this->snakeToCamel($parameters) : $parameters);
                $vfsContext = $this->vfsReplayContext($agent, $toolSlug, $parameters, $context);
                $result = $vfsContext === null
                    ? $tool->handle($toolRequest)
                    : ($this->vfsApprovedContext ?? app(VfsApprovedExecutionContext::class))->runWith(
                        $vfsContext,
                        fn () => $tool->handle($toolRequest),
                    );
            }
            $result = (string) $result;
            $decodedResult = json_decode($result, true);
            $toolSucceeded = ! (is_array($decodedResult) && ($decodedResult['ok'] ?? true) === false);

            if ($isScriptCallback) {
                $this->recordScriptCallbackDisposition($approval->id, $context, 'succeeded');
            }

            // Post the result as a normal channel message so the resumed agent
            // sees the approved side effect in conversation history.
            $channelId = $approval->channel_id ?? ($context['parameters']['channelId'] ?? null);
            if ($channelId) {
                $channel = $this->approvalChannel($agent, $channelId);
                if ($channel) {
                    $resultMessage = Message::create([
                        'id' => Str::uuid()->toString(),
                        'content' => ($toolSucceeded ? '**Approved action executed:** ' : '**Approved action failed:** ').$result,
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

            if ($agentIsWaiting) {
                $agent->clearAwaitingApproval();
            }
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
     * approved replay. Direct VFS approvals approve only the visible primitive;
     * dynamic VFS approvals approve the backing tool captured by VFS policy.
     *
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>|null
     */
    private function vfsReplayContext(User $agent, string $toolSlug, array $parameters, array $context): ?array
    {
        if (is_array($context['vfs_approval'] ?? null)) {
            return $context['vfs_approval'];
        }

        if (! in_array($toolSlug, ['vfs_exec', 'vfs_patch', 'vfs_write'], true)) {
            return null;
        }

        $approvals = $this->vfsApprovals ?? app(VfsApprovalRequestFactory::class);
        $operation = match ($toolSlug) {
            'vfs_exec' => 'exec_write',
            'vfs_write' => 'write',
            default => 'patch',
        };
        $subject = match ($toolSlug) {
            'vfs_exec' => trim((string) ($parameters['command'] ?? 'VFS command')),
            default => (string) ($parameters['path'] ?? 'VFS path'),
        };
        if ($toolSlug !== 'vfs_exec' && $subject !== 'VFS path' && ! str_starts_with($subject, '/')) {
            $subject = '/'.$subject;
        }

        return [
            'requester_id' => $agent->id,
            'direct_tool_slug' => $toolSlug,
            'operation' => $operation,
            'subject' => $subject,
            'required_tools' => $this->vfsReplayRequiredTools($toolSlug, $parameters),
            'approval_tool' => $toolSlug,
            'approved_tools' => [$toolSlug],
            'parameters_hash' => $approvals->hashParameters($parameters),
        ];
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return list<string>
     */
    private function vfsReplayRequiredTools(string $toolSlug, array $parameters): array
    {
        if ($toolSlug === 'vfs_exec') {
            return ['vfs_exec', 'vfs_write'];
        }

        if ($toolSlug !== 'vfs_patch') {
            return [$toolSlug];
        }

        $path = (string) ($parameters['path'] ?? '');
        if (str_starts_with($path, '/tables/') && str_contains($path, '/rows/by-id/')) {
            return ['vfs_patch', 'update_table_row'];
        }
        if (str_starts_with($path, '/agents/') && str_contains($path, '/identity/')) {
            return ['vfs_patch', 'update_agent_identity_file'];
        }

        return ['vfs_patch'];
    }
}
