<?php

namespace App\Agents\Runtime\Subagents;

use App\Agents\Conversations\ChannelConversationLoader;
use App\Agents\Tools\ToolRegistry;
use App\Models\Channel;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AgentDocumentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\CanActAsTool;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Files\File;
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
        prompt as private promptThroughGateway;
    }

    /** The child task bound only while Laravel AI is executing this prompt. */
    private ?string $activeChildTaskId = null;

    public function __construct(
        private User $agent,
        private string $channelId,
        private ?string $taskId,
        private AgentDocumentService $documents,
        ChannelConversationLoader $conversationLoader,
        private ToolRegistry $tools,
    ) {
        // Keep the established constructor position while delegated prompts no
        // longer load parent conversation history. It must not become hidden
        // runtime state for an isolated child prompt.
        unset($conversationLoader);
    }

    public static function for(User $agent, string $channelId, ?string $taskId = null): self
    {
        return app(self::class, compact('agent', 'channelId', 'taskId'));
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

    /**
     * Run one peer prompt under a task owned by that peer agent.
     *
     * Listing subagent tools never creates a task. A child is created only at
     * this execution boundary, after the parent is proven active and scoped to
     * the peer workspace. The shared registry is restored even when Laravel AI
     * fails while resolving tools or a provider response.
     *
     * @param  array<int, UploadedFile|File>  $attachments
     * @param  array<array-key, Lab|string|null>|Lab|string|null  $provider
     */
    public function prompt(
        string $prompt,
        array $attachments = [],
        Lab|array|string|null $provider = null,
        ?string $model = null,
        ?int $timeout = null,
    ): AgentResponse {
        [$parent, $child] = $this->claimParentAndCreateChild($prompt);
        $previousChannel = $this->tools->getChannelContext();
        $previousTask = $this->tools->getTaskContext();
        $this->activeChildTaskId = $child->id;

        try {
            $this->tools->setChannelContext($this->channelId);
            $this->tools->setTaskContext($child->id);
            $response = $this->promptThroughGateway($prompt, $attachments, $provider, $model, $timeout);

            if (! $this->settleChild($parent, $child, succeeded: true)) {
                // A parent cancellation cascades active children. Never
                // overwrite that evidence with completion after a provider
                // returns, including in the narrow interval after an SDK call.
                throw new \RuntimeException('The parent or delegated task stopped before the subagent completed.');
            }

            return $response;
        } catch (\Throwable $exception) {
            $this->settleChild($parent, $child, succeeded: false);

            throw $exception;
        } finally {
            $this->activeChildTaskId = null;
            $this->tools->setChannelContext($previousChannel);
            $this->tools->setTaskContext($previousTask);
        }
    }

    public function tools(): iterable
    {
        if ($this->activeChildTaskId !== null) {
            $this->tools->setChannelContext($this->channelId);
            $this->tools->setTaskContext($this->activeChildTaskId);
        }

        return $this->tools->getToolsForAgent($this->agent);
    }

    /**
     * Atomically claim an active parent and create its peer-owned child.
     *
     * The transaction ends before Laravel AI/provider work begins. Its only
     * purpose is preventing a child from being inserted after cancellation has
     * locked and transitioned the parent. Receipt checks cover any later
     * cancellation while the prompt is in flight.
     *
     * @return array{Task, Task}
     */
    private function claimParentAndCreateChild(string $prompt): array
    {
        if ($this->taskId === null || $this->taskId === '') {
            throw new \RuntimeException('A parent task is required to execute a subagent.');
        }

        // The active workspace and persisted channel are host authority, not
        // model-provided decoration on a delegated prompt.
        $workspace = app()->bound('currentWorkspace') ? app('currentWorkspace') : null;
        if (! $workspace instanceof Workspace || $workspace->id !== $this->agent->workspace_id) {
            throw new \RuntimeException('The active workspace is unavailable for this subagent.');
        }
        if (! Channel::query()->whereKey($this->channelId)->where('workspace_id', $workspace->id)->exists()) {
            throw new \RuntimeException('The delegated channel is unavailable for this subagent workspace.');
        }

        return DB::transaction(function () use ($prompt): array {
            $parent = Task::query()
                ->whereKey($this->taskId)
                ->where('workspace_id', $this->agent->workspace_id)
                ->where('status', Task::STATUS_ACTIVE)
                ->lockForUpdate()
                ->first();

            if ($parent === null) {
                throw new \RuntimeException('The parent task is unavailable, inactive, or outside the subagent workspace.');
            }

            // Workspace membership alone does not authorize moving a parent's
            // approvals or tool context into another conversation. Chat tasks
            // persist their channel at creation; absent channel authority must
            // be repaired by the host, never inferred from a delegated prompt.
            if ($parent->channel_id !== $this->channelId) {
                throw new \RuntimeException('The delegated channel does not match the parent task.');
            }

            $child = Task::create([
                'id' => Str::uuid()->toString(),
                'workspace_id' => $this->agent->workspace_id,
                'title' => Str::limit($prompt, 120),
                'description' => $prompt,
                'type' => Task::TYPE_CUSTOM,
                'status' => Task::STATUS_ACTIVE,
                'priority' => $parent->priority,
                'source' => Task::SOURCE_AGENT_DELEGATION,
                'agent_id' => $this->agent->id,
                'requester_id' => $parent->requester_id,
                'channel_id' => $this->channelId,
                'parent_task_id' => $parent->id,
                'started_at' => now(),
            ]);

            return [$parent, $child];
        });
    }

    /**
     * Transition a child only while it is still active under a locked parent.
     *
     * The provider has already returned, so this deliberately holds a database
     * transaction only for the state transition. Using the Task lifecycle
     * methods, rather than a query-builder update, preserves model events and
     * keeps cancellation from being overwritten between a stale refresh and
     * completion/failure update.
     */
    private function settleChild(Task $parent, Task $child, bool $succeeded): bool
    {
        return DB::transaction(function () use ($parent, $child, $succeeded): bool {
            $lockedParent = Task::query()
                ->whereKey($parent->id)
                ->where('workspace_id', $this->agent->workspace_id)
                ->lockForUpdate()
                ->first();
            $lockedChild = Task::query()
                ->whereKey($child->id)
                ->where('workspace_id', $this->agent->workspace_id)
                ->where('agent_id', $this->agent->id)
                ->where('parent_task_id', $parent->id)
                ->lockForUpdate()
                ->first();

            if ($succeeded) {
                if ($lockedParent === null || $lockedParent->status !== Task::STATUS_ACTIVE) {
                    return false;
                }
                if ($lockedChild === null) {
                    return false;
                }
                // A host task tool may have completed the child itself while
                // the SDK response was in flight. That terminal success is
                // compatible with returning the response; all other stopped
                // states must fail closed without being overwritten.
                if ($lockedChild->status === Task::STATUS_COMPLETED) {
                    return true;
                }
                if ($lockedChild->status !== Task::STATUS_ACTIVE) {
                    return false;
                }
                $lockedChild->complete();
            } elseif ($lockedChild !== null && $lockedChild->status === Task::STATUS_ACTIVE) {
                // Do not persist provider exception text in task authority.
                $lockedChild->fail('Subagent prompt failed.');
            }

            return true;
        });
    }
}
