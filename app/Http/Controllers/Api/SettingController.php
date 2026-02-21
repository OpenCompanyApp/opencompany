<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\IndexDocumentJob;
use App\Models\AppSetting;
use App\Models\ConversationSummary;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\EmbeddingCache;
use App\Models\McpServer;
use App\Models\Automation;
use App\Models\User;
use App\Services\Mcp\McpClient;
use App\Services\Memory\DocumentIndexingService;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SettingController extends Controller
{
    /**
     * Get all settings grouped by category, merged with defaults.
     */
    public function index(): \Illuminate\Http\JsonResponse
    {
        return response()->json(AppSetting::allWithDefaults());
    }

    /**
     * Update settings for a specific category.
     */
    public function update(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'category' => 'required|string|in:organization,agents,notifications,policies,memory',
            'settings' => 'required|array',
        ]);

        $category = $request->input('category');
        $settings = $request->input('settings');
        $defaults = AppSetting::defaults();

        // Only allow known keys for the category
        $allowedKeys = array_keys($defaults[$category] ?? []);
        $filtered = array_intersect_key($settings, array_flip($allowedKeys));

        // Detect if the embedding model is being changed
        $embeddingModelChanged = false;
        if ($category === 'memory' && isset($filtered['memory_embedding_model'])) {
            $currentModel = AppSetting::getValue('memory_embedding_model');
            if ($currentModel !== null && $currentModel !== $filtered['memory_embedding_model']) {
                $embeddingModelChanged = true;
            }
        }

        AppSetting::setMany($filtered, $category);

        // Reset embedding data when the model changes — old vectors are
        // incompatible with the new model's vector space.
        if ($embeddingModelChanged) {
            DocumentIndexingService::resetEmbeddings();
            Log::info('Embedding model changed, embedding data reset', [
                'new_model' => $filtered['memory_embedding_model'],
            ]);
        }

        return response()->json([
            'message' => $embeddingModelChanged
                ? 'Settings updated. Embedding data has been reset for the new model.'
                : 'Settings updated.',
            'settings' => AppSetting::getByCategory($category),
        ]);
    }

    /**
     * Handle danger zone actions.
     */
    public function dangerAction(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'action' => 'required|string|in:pause_agents,reset_memory,resume_agents,retry_failed_jobs,refresh_mcp_tools,test_telegram,reindex_documents,reindex_documents_fresh,flush_failed_jobs,reset_embeddings,clear_embedding_cache,clear_conversation_summaries',
        ]);

        $action = $request->input('action');

        return match ($action) {
            'pause_agents' => $this->actionPauseAgents(),
            'resume_agents' => $this->actionResumeAgents(),
            'reset_memory' => $this->actionResetMemory(),
            'retry_failed_jobs' => $this->actionRetryFailedJobs(),
            'flush_failed_jobs' => $this->actionFlushFailedJobs(),
            'refresh_mcp_tools' => $this->actionRefreshMcpTools(),
            'test_telegram' => $this->actionTestTelegram(),
            'reindex_documents' => $this->actionReindexDocuments(fresh: false),
            'reindex_documents_fresh' => $this->actionReindexDocuments(fresh: true),
            'reset_embeddings' => $this->actionResetEmbeddings(),
            'clear_embedding_cache' => $this->actionClearEmbeddingCache(),
            'clear_conversation_summaries' => $this->actionClearConversationSummaries(),
            default => response()->json(['message' => 'Unknown action.'], 400),
        };
    }

    private function actionPauseAgents(): JsonResponse
    {
        $count = User::where('type', 'agent')
            ->where('workspace_id', workspace()->id)
            ->whereNotIn('status', ['offline', 'paused'])
            ->update(['status' => 'paused']);

        return response()->json(['message' => "{$count} agent(s) paused."]);
    }

    private function actionResumeAgents(): JsonResponse
    {
        $count = User::where('type', 'agent')
            ->where('workspace_id', workspace()->id)
            ->whereIn('status', ['paused', 'sleeping'])
            ->update(['status' => 'idle']);

        return response()->json(['message' => "{$count} agent(s) resumed."]);
    }

    private function actionResetMemory(): JsonResponse
    {
        User::where('type', 'agent')->where('workspace_id', workspace()->id)->each(function (User $agent) {
            $memoryFile = storage_path("app/agents/{$agent->id}/memory.md");
            if (file_exists($memoryFile)) {
                file_put_contents($memoryFile, '');
            }
        });

        return response()->json(['message' => 'Agent memory has been reset.']);
    }

    private function actionRetryFailedJobs(): JsonResponse
    {
        $count = DB::table('failed_jobs')->count();
        if ($count === 0) {
            return response()->json(['message' => 'No failed jobs to retry.']);
        }

        Artisan::call('queue:retry', ['id' => ['all']]);

        return response()->json(['message' => "{$count} failed job(s) queued for retry."]);
    }

    private function actionFlushFailedJobs(): JsonResponse
    {
        $count = DB::table('failed_jobs')->count();
        DB::table('failed_jobs')->truncate();

        return response()->json(['message' => "{$count} failed job(s) cleared."]);
    }

    private function actionRefreshMcpTools(): JsonResponse
    {
        $servers = McpServer::forWorkspace()->where('enabled', true)->get();

        if ($servers->isEmpty()) {
            return response()->json(['message' => 'No enabled MCP servers found.']);
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
            $message .= ' Errors: ' . implode('; ', array_map(fn ($e) => Str::limit($e, 80), $errors));
        }

        return response()->json(['message' => $message]);
    }

    private function actionTestTelegram(): JsonResponse
    {
        try {
            $telegram = app(TelegramService::class);

            if (! $telegram->isConfigured()) {
                return response()->json(['message' => 'Telegram is not configured. Add a bot token in integration settings.']);
            }

            $result = $telegram->getMe();
            $botName = $result['result']['username'] ?? 'unknown';

            return response()->json(['message' => "Connected to @{$botName}."]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Telegram test failed: ' . Str::limit($e->getMessage(), 150)], 422);
        }
    }

    private function actionReindexDocuments(bool $fresh): JsonResponse
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
            return response()->json(['message' => 'No documents to index.']);
        }

        foreach ($documents as $document) {
            IndexDocumentJob::dispatch($document);
        }

        $prefix = $fresh ? 'Fresh reindex' : 'Reindex';

        return response()->json(['message' => "{$prefix}: {$documents->count()} document(s) queued for indexing."]);
    }

    private function actionResetEmbeddings(): JsonResponse
    {
        $workspaceId = workspace()->id;
        $chunkCount = DocumentChunk::forWorkspace()->count();

        DocumentIndexingService::resetEmbeddings($workspaceId);

        return response()->json(['message' => "{$chunkCount} chunk(s) and embedding cache cleared."]);
    }

    private function actionClearEmbeddingCache(): JsonResponse
    {
        $count = EmbeddingCache::forWorkspace()->count();
        EmbeddingCache::forWorkspace()->delete();

        return response()->json(['message' => "{$count} cache entries cleared."]);
    }

    private function actionClearConversationSummaries(): JsonResponse
    {
        $count = ConversationSummary::forWorkspace()->count();
        ConversationSummary::forWorkspace()->delete();

        return response()->json(['message' => "{$count} conversation summary(ies) cleared."]);
    }

    public function debug(): JsonResponse
    {
        return response()->json([
            'compaction' => $this->getCompactionHealth(),
            'embedding' => $this->getEmbeddingHealth(),
            'queue' => $this->getQueueHealth(),
            'automations' => $this->getAutomationHealth(),
            'mcp_servers' => $this->getMcpHealth(),
            'agents' => $this->getAgentHealth(),
            'logs' => $this->getRecentLogs(),
        ]);
    }

    /** @return array<string, mixed> */
    private function getCompactionHealth(): array
    {
        $summaries = ConversationSummary::forWorkspace();
        $count = $summaries->count();
        $latest = $summaries->max('updated_at');
        $avgCompression = $count > 0
            ? $summaries->where('tokens_before', '>', 0)
                ->selectRaw('AVG(1.0 - (tokens_after * 1.0 / tokens_before)) * 100 as ratio')
                ->value('ratio')
            : null;

        return [
            'summary_count' => $count,
            'latest_at' => $latest,
            'avg_compression_pct' => $avgCompression ? round((float) $avgCompression, 1) : null,
        ];
    }

    /** @return array<string, mixed> */
    private function getEmbeddingHealth(): array
    {
        $chunks = DocumentChunk::forWorkspace();
        $total = $chunks->count();
        $indexed = DocumentChunk::forWorkspace()->whereNotNull('embedding')->count();
        $cacheCount = EmbeddingCache::forWorkspace()->count();

        return [
            'total_chunks' => $total,
            'indexed_chunks' => $indexed,
            'unindexed_chunks' => $total - $indexed,
            'cache_entries' => $cacheCount,
        ];
    }

    /** @return array<string, mixed> */
    private function getQueueHealth(): array
    {
        $pending = DB::table('jobs')->count();
        $failedCount = DB::table('failed_jobs')->count();
        $recentFailures = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->limit(5)
            ->get(['uuid', 'queue', 'payload', 'exception', 'failed_at'])
            ->map(function ($job) {
                $payload = json_decode($job->payload, true);
                $jobClass = $payload['displayName'] ?? 'Unknown';

                return [
                    'uuid' => $job->uuid,
                    'job' => class_basename($jobClass),
                    'queue' => $job->queue,
                    'exception' => Str::limit($job->exception, 300),
                    'failed_at' => $job->failed_at,
                ];
            });

        return [
            'pending_jobs' => $pending,
            'failed_jobs' => $failedCount,
            'recent_failures' => $recentFailures,
        ];
    }

    /** @return array<string, mixed> */
    private function getAutomationHealth(): array
    {
        $automations = Automation::forWorkspace()->get([
            'id', 'name', 'is_active', 'last_run_at', 'next_run_at',
            'run_count', 'consecutive_failures', 'last_result',
        ]);

        return [
            'total' => $automations->count(),
            'active' => $automations->where('is_active', true)->count(),
            'failing' => $automations->where('consecutive_failures', '>', 0)->count(),
            'items' => $automations->map(fn ($a) => [
                'name' => $a->name,
                'is_active' => $a->is_active,
                'last_run_at' => $a->last_run_at?->toIso8601String(),
                'next_run_at' => $a->next_run_at?->toIso8601String(),
                'run_count' => $a->run_count,
                'consecutive_failures' => $a->consecutive_failures,
                'last_error' => $a->last_result['error'] ?? null,
            ])->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function getMcpHealth(): array
    {
        $servers = McpServer::forWorkspace()->get();

        return [
            'total' => $servers->count(),
            'enabled' => $servers->where('enabled', true)->count(),
            'stale' => $servers->filter->isToolDiscoveryStale()->count(),
            'items' => $servers->map(fn ($s) => [
                'name' => $s->name,
                'enabled' => $s->enabled,
                'tool_count' => count($s->discovered_tools ?? []),
                'tools_discovered_at' => $s->tools_discovered_at?->toIso8601String(),
                'is_stale' => $s->isToolDiscoveryStale(),
            ])->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function getAgentHealth(): array
    {
        $agents = User::where('type', 'agent')
            ->where('workspace_id', workspace()->id)
            ->get(['id', 'name', 'status', 'updated_at']);

        return [
            'total' => $agents->count(),
            'items' => $agents->map(fn ($a) => [
                'name' => $a->name,
                'status' => $a->status,
                'last_active' => $a->updated_at?->toIso8601String(),
            ])->values(),
        ];
    }

    /** @return list<array{level: string, message: string, timestamp: string}> */
    private function getRecentLogs(): array
    {
        $logFile = storage_path('logs/laravel.log');

        if (! file_exists($logFile)) {
            return [];
        }

        // Read last ~64KB to get recent entries
        $handle = fopen($logFile, 'r');
        if (! $handle) {
            return [];
        }

        $fileSize = filesize($logFile);
        $readSize = min($fileSize, 65536);
        fseek($handle, -$readSize, SEEK_END);
        $tail = fread($handle, $readSize);
        fclose($handle);

        if (! $tail) {
            return [];
        }

        $entries = [];
        // Match Laravel log lines: [YYYY-MM-DD HH:MM:SS] environment.LEVEL: message
        preg_match_all(
            '/\[(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}[^\]]*)\]\s+\w+\.(ERROR|WARNING|CRITICAL|EMERGENCY):\s+(.+?)(?=\n\[|\z)/s',
            $tail,
            $matches,
            PREG_SET_ORDER
        );

        foreach (array_slice($matches, -30) as $match) {
            $entries[] = [
                'timestamp' => trim($match[1]),
                'level' => $match[2],
                'message' => Str::limit(trim($match[3]), 500),
            ];
        }

        return array_reverse($entries);
    }
}
