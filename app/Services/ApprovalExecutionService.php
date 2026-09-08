<?php

namespace App\Services;

use App\Agents\Tools\ToolRegistry;
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
    ) {}

    /**
     * Execute exactly one approved callback after a human decision.
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

        // Queue and webhook callbacks may not have ResolveWorkspace middleware.
        // Bind from the requesting agent before instantiating tools that rely on
        // workspace() or forWorkspace().
        $this->bindWorkspaceContext($agent);

        $account = $isScriptCallback && is_string($context['account'] ?? null)
            ? $context['account']
            : null;
        $parameters = is_array($context['parameters'] ?? null) ? $context['parameters'] : [];

        if ($isScriptCallback) {
            // Approval is permission to execute this exact callback once, not
            // a permanent capability grant. Re-check current workspace,
            // integration, and deny policy before raw post-approval creation.
            $dispatch = $this->toolRegistry->resolveScriptToolForDispatch($toolSlug, $agent, $account);
            if ($dispatch['decision'] === 'deny') {
                $this->recordScriptCallbackDisposition($approval->id, $context, 'denied');

                return;
            }

            // A still-required approval is satisfied only by this locked row.
            // Do not route through IntegrationRuntime, which would create a
            // second pending request instead of executing the approved callback.
            $tool = $dispatch['decision'] === 'allow'
                ? ($dispatch['tool'] ?? null)
                : $this->toolRegistry->instantiateToolBySlug($toolSlug, $agent, $account);
        } else {
            $tool = $this->toolRegistry->instantiateToolBySlug($toolSlug, $agent, $account);
        }

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
                $result = $tool->handle($toolRequest);
            }

            if ($isScriptCallback) {
                $this->recordScriptCallbackDisposition($approval->id, $context, 'succeeded');
            }

            // Post the result as a normal channel message so the resumed agent
            // sees the approved side effect in conversation history.
            $channelId = $approval->channel_id ?? ($context['parameters']['channelId'] ?? null);
            if ($channelId) {
                $channel = Channel::find($channelId);
                if ($channel) {
                    $resultMessage = Message::create([
                        'id' => Str::uuid()->toString(),
                        'content' => "**Approved action executed:** {$result}",
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
                $channel = Channel::find($channelId);
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
    public function handleRejectedTool(ApprovalRequest $approval): void
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
            $channel = Channel::find($channelId);
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
}
