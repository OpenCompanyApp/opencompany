<?php

namespace App\Domain\Settings\Application;

use App\Domain\Authorization\Application\AuthorizationService;
use App\Domain\Authorization\Engine\PolicyEngine;
use App\Domain\Work\Application\ManageTasks;
use App\Jobs\IndexDocumentJob;
use App\Jobs\RunRoutineJob;
use App\Models\AgentRunRecord;
use App\Models\ConversationSummary;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\EmbeddingCache;
use App\Models\McpServer;
use App\Models\Routine;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Mcp\McpClient;
use App\Services\Memory\DocumentIndexingService;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Workspace settings commands (the former "danger zone" actions), each a
 * separately authorized and audited command (ROADMAP Phase 0 P0 item 8;
 * GIN-1149).
 *
 * Owns: the command catalogue and running one command for the current
 * workspace. run() asks AuthorizationService for
 * `settings.command.<name>`; the capability each command needs is fixed in
 * PolicyEngine::SETTINGS_COMMANDS (operational commands need
 * `settings.operate`, held by admins; data-erasing commands need
 * `settings.danger`, held only by the workspace owner). The decision is
 * recorded in the authorization ledger before the command runs (fail
 * closed), and a `security_change` entry with the outcome after it ran.
 * Unauthorized requests have no side effects.
 *
 * Workspace boundary: every command reads and writes only the current
 * workspace (`forWorkspace()` / `workspace_id`); none touches the global
 * failed-jobs table or another tenant's runs.
 *
 * Does not own: platform-wide operations; those need a platform operator
 * (`settings.diagnostics.platform`), never a workspace role.
 */
class SettingsCommands
{
    /** Command name => method on this class. */
    private const COMMANDS = [
        'pause_agents' => 'pauseAgents',
        'resume_agents' => 'resumeAgents',
        'reset_agents' => 'resetAgents',
        'reset_memory' => 'resetMemory',
        'retry_failed_jobs' => 'retryFailedJobs',
        'flush_failed_jobs' => 'flushFailedJobs',
        'refresh_mcp_tools' => 'refreshMcpTools',
        'test_telegram' => 'testTelegram',
        'reindex_documents' => 'reindexDocumentsIncremental',
        'reindex_documents_fresh' => 'reindexDocumentsFresh',
        'reset_embeddings' => 'resetEmbeddings',
        'clear_embedding_cache' => 'clearEmbeddingCache',
        'clear_conversation_summaries' => 'clearConversationSummaries',
    ];

    public function __construct(private readonly AuthorizationService $authorization) {}

    /** @return list<string> */
    public static function names(): array
    {
        return array_keys(self::COMMANDS);
    }

    /**
     * Authorize, run and record one command.
     *
     * @return array{message: string, status: int}
     *
     * @throws \App\Domain\Authorization\Application\AuthorizationDenied
     * @throws \InvalidArgumentException for unknown commands
     */
    public function run(User $actor, Workspace $workspace, string $command): array
    {
        $method = self::COMMANDS[$command] ?? null;
        if ($method === null || ! array_key_exists($command, PolicyEngine::SETTINGS_COMMANDS)) {
            throw new \InvalidArgumentException("Unknown settings command \"{$command}\".");
        }

        // Invariant: authorize (and record the decision) before any side effect.
        $this->authorization->authorize($actor, 'settings.command.'.$command, $workspace);

        $result = $this->{$method}();

        $this->authorization->recordChange($actor, $workspace, 'settings.command.'.$command, ['type' => 'workspace', 'id' => (string) $workspace->id], [
            'command' => $command,
            'status' => $result['status'],
            'message' => $result['message'],
        ]);

        return $result;
    }

    /** @return array{message: string, status: int} */
    private function reindexDocumentsIncremental(): array
    {
        return $this->reindexDocuments(fresh: false);
    }

    /** @return array{message: string, status: int} */
    private function reindexDocumentsFresh(): array
    {
        return $this->reindexDocuments(fresh: true);
    }

    /** @return array{message: string, status: int} */
    private function pauseAgents(): array
    {
        $count = User::where('type', 'agent')
            ->where('workspace_id', workspace()->id)
            ->whereNotIn('status', ['offline', 'paused'])
            ->update(['status' => 'paused']);

        $tasks = Task::forWorkspace()
            ->whereIn('status', [Task::STATUS_PENDING, Task::STATUS_ACTIVE])
            ->whereNotNull('agent_id')
            ->get();
        foreach ($tasks as $task) {
            app(ManageTasks::class)->transition($task->id, 'pause');
        }

        return ['message' => "{$count} agent(s) and {$tasks->count()} task(s) paused.", 'status' => 200];
    }

    /** @return array{message: string, status: int} */
    private function resumeAgents(): array
    {
        $count = User::where('type', 'agent')
            ->where('workspace_id', workspace()->id)
            ->whereIn('status', ['paused', 'sleeping'])
            ->update(['status' => 'idle']);

        $tasks = Task::forWorkspace()
            ->where('status', Task::STATUS_PAUSED)
            ->whereNotNull('agent_id')
            ->get();
        foreach ($tasks as $task) {
            app(ManageTasks::class)->transition($task->id, 'resume');
        }

        return ['message' => "{$count} agent(s) and {$tasks->count()} task(s) resumed.", 'status' => 200];
    }

    /** @return array{message: string, status: int} */
    private function resetAgents(): array
    {
        $agents = User::where('type', 'agent')
            ->where('workspace_id', workspace()->id)
            ->where('status', '!=', 'offline')
            ->get();

        foreach ($agents as $agent) {
            $agent->update([
                'status' => 'idle',
                'sleeping_until' => null,
                'sleeping_reason' => null,
                'awaiting_approval_id' => null,
                'awaiting_delegation_ids' => null,
            ]);
        }

        $tasks = Task::forWorkspace()
            ->whereIn('agent_id', $agents->pluck('id'))
            ->whereIn('status', [Task::STATUS_ACTIVE, Task::STATUS_PENDING, Task::STATUS_PAUSED])
            ->get();
        foreach ($tasks as $task) {
            app(ManageTasks::class)->transition($task->id, 'cancel');
        }

        return ['message' => "{$agents->count()} agent(s) reset to idle, {$tasks->count()} task(s) cancelled.", 'status' => 200];
    }

    /** @return array{message: string, status: int} */
    private function resetMemory(): array
    {
        User::where('type', 'agent')->where('workspace_id', workspace()->id)->each(function (User $agent) {
            $memoryFile = storage_path("app/agents/{$agent->id}/memory.md");
            if (file_exists($memoryFile)) {
                file_put_contents($memoryFile, '');
            }
        });

        return ['message' => 'Agent memory has been reset.', 'status' => 200];
    }

    /** @return array{message: string, status: int} */
    private function retryFailedJobs(): array
    {
        $runs = AgentRunRecord::forWorkspace()
            ->where('status', AgentRunRecord::STATUS_FAILED)
            ->with('task')
            ->oldest()
            ->get()
            ->filter(fn (AgentRunRecord $run) => empty($run->metadata['operator_dismissed_at']) && empty($run->metadata['retried_at']));

        $retried = 0;
        foreach ($runs as $run) {
            $task = $run->task;
            if (! $task) {
                continue;
            }

            try {
                if (in_array($run->kind, [AgentRunRecord::KIND_ROUTINE_AGENT, AgentRunRecord::KIND_ROUTINE_SCRIPT], true)) {
                    $routine = Routine::forWorkspace()->find($run->metadata['routine_id'] ?? $task->context['routine_id'] ?? null);
                    if (! $routine) {
                        continue;
                    }
                    RunRoutineJob::dispatch($routine);
                    $newTaskId = null;
                } else {
                    $retryTask = $task->replicate();
                    $retryTask->forceFill([
                        'id' => (string) Str::uuid(),
                        'status' => Task::STATUS_PENDING,
                        'started_at' => null,
                        'completed_at' => null,
                        'result' => null,
                        'context' => array_merge($task->context ?? [], [
                            'retry_of_task_id' => $task->id,
                            'retry_of_agent_run_id' => $run->id,
                        ]),
                    ])->save();
                    app(ManageTasks::class)->start($retryTask->id);
                    $newTaskId = $retryTask->id;
                }

                $run->update(['metadata' => array_merge($run->metadata ?? [], [
                    'retried_at' => now()->toIso8601String(),
                    'retry_task_id' => $newTaskId,
                ])]);
                $retried++;
            } catch (\Throwable $exception) {
                Log::warning('Failed to retry workspace agent run', [
                    'agent_run_id' => $run->id,
                    'workspace_id' => $run->workspace_id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return ['message' => "{$retried} failed workspace run(s) queued as new logical runs.", 'status' => 200];
    }

    /** @return array{message: string, status: int} */
    private function flushFailedJobs(): array
    {
        $runs = AgentRunRecord::forWorkspace()
            ->where('status', AgentRunRecord::STATUS_FAILED)
            ->get();
        foreach ($runs as $run) {
            $run->update(['metadata' => array_merge($run->metadata ?? [], [
                'operator_dismissed_at' => now()->toIso8601String(),
            ])]);
        }

        return ['message' => "{$runs->count()} failed workspace run alert(s) dismissed; audit history was retained.", 'status' => 200];
    }

    /** @return array{message: string, status: int} */
    private function refreshMcpTools(): array
    {
        $servers = McpServer::forWorkspace()->where('enabled', true)->get();

        if ($servers->isEmpty()) {
            return ['message' => 'No enabled MCP servers found.', 'status' => 200];
        }

        $refreshed = 0;
        $errors = [];

        foreach ($servers as $server) {
            try {
                $client = McpClient::fromServer($server);
                $tools = $client->listTools();
                $server->update([
                    'discovered_tools' => $tools,
                    'tools_discovered_at' => now(),
                ]);
                $refreshed++;
            } catch (\Throwable $e) {
                $errors[] = "{$server->name}: {$e->getMessage()}";
            }
        }

        $message = "{$refreshed}/{$servers->count()} server(s) refreshed.";
        if (! empty($errors)) {
            $message .= ' Errors: '.implode('; ', array_map(fn ($e) => Str::limit($e, 80), $errors));
        }

        return ['message' => $message, 'status' => 200];
    }

    /** @return array{message: string, status: int} */
    private function testTelegram(): array
    {
        try {
            $telegram = app(TelegramService::class);

            if (! $telegram->isConfigured()) {
                return ['message' => 'Telegram is not configured. Add a bot token in integration settings.', 'status' => 200];
            }

            $result = $telegram->getMe();
            $botName = $result['result']['username'] ?? 'unknown';

            return ['message' => "Connected to @{$botName}.", 'status' => 200];
        } catch (\Throwable $e) {
            return ['message' => 'Telegram test failed: '.Str::limit($e->getMessage(), 150), 'status' => 422];
        }
    }

    /** @return array{message: string, status: int} */
    private function reindexDocuments(bool $fresh): array
    {
        $workspaceId = workspace()->id;

        if ($fresh) {
            DocumentIndexingService::resetEmbeddings($workspaceId);
        }

        $documents = Document::forWorkspace()
            ->where('is_folder', false)
            ->whereNotNull('content')
            ->where('content', '!=', '')
            ->get();

        if ($documents->isEmpty()) {
            return ['message' => 'No documents to index.', 'status' => 200];
        }

        foreach ($documents as $document) {
            IndexDocumentJob::dispatch($document);
        }

        $prefix = $fresh ? 'Fresh reindex' : 'Reindex';

        return ['message' => "{$prefix}: {$documents->count()} document(s) queued for indexing.", 'status' => 200];
    }

    /** @return array{message: string, status: int} */
    private function resetEmbeddings(): array
    {
        $workspaceId = workspace()->id;
        $chunkCount = DocumentChunk::forWorkspace()->count();

        DocumentIndexingService::resetEmbeddings($workspaceId);

        return ['message' => "{$chunkCount} chunk(s) and embedding cache cleared.", 'status' => 200];
    }

    /** @return array{message: string, status: int} */
    private function clearEmbeddingCache(): array
    {
        $count = EmbeddingCache::forWorkspace()->count();
        EmbeddingCache::forWorkspace()->delete();

        return ['message' => "{$count} cache entries cleared.", 'status' => 200];
    }

    /** @return array{message: string, status: int} */
    private function clearConversationSummaries(): array
    {
        $count = ConversationSummary::forWorkspace()->count();
        ConversationSummary::forWorkspace()->delete();

        return ['message' => "{$count} conversation summary(ies) cleared.", 'status' => 200];
    }
}
