<?php

namespace App\Agents\Runtime\Subagents;

use App\Agents\Conversations\ChannelConversationLoader;
use App\Agents\Runtime\ActiveAgentRun;
use App\Agents\Tools\System\RunGuardedTool;
use App\Agents\Tools\ToolRegistry;
use App\Events\TaskUpdated;
use App\Models\AgentRunRecord;
use App\Models\Channel;
use App\Models\Task;
use App\Models\User;
use App\Services\AgentDocumentService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\CanActAsTool;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Laravel\Ai\Responses\AgentResponse;
use Stringable;

/**
 * Wraps a workspace agent so Laravel AI can expose it as a callable subagent.
 *
 * The wrapper gives each peer agent its own instructions and tool set while
 * deliberately withholding the parent conversation. Parent agents must pass the
 * necessary task context explicitly when delegating.
 */
class GenericSubagent implements Agent, CanActAsTool, Conversational, HasTools
{
    use Promptable {
        prompt as private promptWithoutRunControl;
    }

    public function __construct(
        private User $agent,
        private string $channelId,
        private ?string $taskId,
        private AgentDocumentService $documents,
        private ChannelConversationLoader $conversationLoader,
        private ToolRegistry $tools,
    ) {}

    public static function for(User $agent, string $channelId, ?string $taskId = null): self
    {
        return app(self::class, compact('agent', 'channelId', 'taskId'));
    }

    /**
     * Execute a direct SDK subagent under the same per-agent lock as queued
     * work, and persist a child task/run when invoked from a parent agent run.
     *
     * @param  array<int, mixed>  $attachments
     */
    public function prompt(
        string $prompt,
        array $attachments = [],
        Lab|array|string|null $provider = null,
        ?string $model = null,
        ?int $timeout = null,
    ): AgentResponse {
        $this->agent->refresh();
        if (in_array($this->agent->status, ['paused', 'offline'], true)) {
            throw new \RuntimeException("Subagent {$this->agent->name} is {$this->agent->status}.");
        }
        if (! Channel::query()
            ->whereKey($this->channelId)
            ->where('workspace_id', $this->agent->workspace_id)
            ->exists()) {
            throw new \DomainException('Subagent channel is outside the target agent workspace.');
        }

        $lock = Cache::lock("laravel-queue-overlap:agent-execution:{$this->agent->id}", 1900);
        if (! $lock->get()) {
            throw new \RuntimeException("Subagent {$this->agent->name} is already executing another run.");
        }

        $parentRun = app()->bound(ActiveAgentRun::class) ? app(ActiveAgentRun::class) : null;
        $childTask = null;
        $childRun = null;

        try {
            $parentRun?->checkpoint(force: true);

            if ($parentRun) {
                $parentRecord = $parentRun->record();
                if ($parentRecord->workspace_id !== $this->agent->workspace_id) {
                    throw new \DomainException('Subagent target is outside the parent run workspace.');
                }
                $childTask = Task::query()->create([
                    'id' => (string) Str::uuid(),
                    'workspace_id' => $this->agent->workspace_id,
                    'title' => Str::limit($prompt, 80),
                    'description' => $prompt,
                    'type' => Task::TYPE_CUSTOM,
                    'status' => Task::STATUS_ACTIVE,
                    'priority' => Task::PRIORITY_NORMAL,
                    'source' => Task::SOURCE_AGENT_DELEGATION,
                    'agent_id' => $this->agent->id,
                    'requester_id' => $parentRecord->agent_id,
                    'channel_id' => $this->channelId,
                    'parent_task_id' => $parentRecord->task_id,
                    'started_at' => now(),
                ]);
                $childRun = AgentRunRecord::ensureForTask($childTask, AgentRunRecord::KIND_SUBAGENT, 1);
                $childRun->beginAttempt();
                app()->instance(ActiveAgentRun::class, new ActiveAgentRun($childRun->id));
                safeBroadcast(new TaskUpdated($childTask, 'started'), 'subagent task started');
            }

            $this->agent->update(['status' => 'working']);
            $response = $this->promptWithoutRunControl($prompt, $attachments, $provider, $model, $timeout);

            if ($childTask && $childRun) {
                $childTask->complete(['response' => $response->text]);
                $childRun->markCompleted();
                safeBroadcast(new TaskUpdated($childTask, 'completed'), 'subagent task completed');
            }

            return $response;
        } catch (\Throwable $exception) {
            if ($childTask && ! $childTask->isTerminal()) {
                $childTask->fail($exception->getMessage());
                $childRun?->markFailed($exception);
                safeBroadcast(new TaskUpdated($childTask, 'failed'), 'subagent task failed');
            }

            throw $exception;
        } finally {
            $this->agent->resolveIdleStatus();
            if ($parentRun) {
                app()->instance(ActiveAgentRun::class, $parentRun);
            } else {
                app()->forgetInstance(ActiveAgentRun::class);
            }
            $lock->release();
        }
    }

    public function name(): string
    {
        $id = str($this->agent->id)->replace('-', '_')->toString();

        // Include the database ID so two agents with the same display name do
        // not collide in the Laravel AI tool registry.
        return 'subagent_'.str($this->agent->name)->slug('_')->toString().'_'.$id;
    }

    public function description(): Stringable|string
    {
        return "Delegate a clear, self-contained task to {$this->agent->name}. The subagent runs with isolated context and reports back a concise result.";
    }

    public function instructions(): string
    {
        return implode("\n\n", array_filter([
            "You are {$this->agent->name}, a generic OpenCompany subagent.",
            'Complete only the delegated task. Keep the result concise, factual, and directly usable by the parent agent.',
            'You run in isolated context. Do not assume access to the parent conversation except what is included in the task.',
            $this->documents->getIdentityFiles($this->agent)
                ->map(fn ($document) => "# {$document->title}\n\n{$document->content}")
                ->implode("\n\n"),
        ]));
    }

    public function messages(): iterable
    {
        // Keep delegated runs isolated. Pulling parent channel history here
        // would make subagent behavior harder to audit and could leak context
        // the parent did not intentionally include in the task.
        return [];
    }

    public function tools(): iterable
    {
        $this->tools->setChannelContext($this->channelId);
        $this->tools->setTaskContext($this->taskId);

        $tools = $this->tools->getToolsForAgent($this->agent);

        if (! app()->bound(ActiveAgentRun::class)) {
            return $tools;
        }

        return array_map(fn ($tool) => new RunGuardedTool($tool), $tools);
    }
}
