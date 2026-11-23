<?php

namespace App\Http\Controllers\Api;

use App\Domain\Authorization\Application\AuthorizationService;
use App\Domain\Settings\Application\SettingsCommands;
use App\Http\Controllers\Controller;
use App\Models\AgentRunRecord;
use App\Models\AppSetting;
use App\Models\ConversationSummary;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\EmbeddingCache;
use App\Models\McpServer;
use App\Models\Routine;
use App\Models\Task;
use App\Models\User;
use App\Services\Memory\DocumentIndexingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Workspace settings, diagnostics and settings commands.
 *
 * Authorization (ROADMAP P0 item 8; GIN-1149): every endpoint asks
 * AuthorizationService (`settings.read`, `settings.update`,
 * `settings.command.<name>`); the `workspace.admin` route middleware stays
 * as defense in depth. Commands are separate, separately authorized and
 * audited (SettingsCommands). Diagnostics are workspace-scoped; the shared
 * application log tail is platform data and is returned only to a platform
 * operator (`settings.diagnostics.platform`, config/authorization.php), never
 * to a workspace admin as such.
 */
class SettingController extends Controller
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    /**
     * Get all settings grouped by category, merged with defaults.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorization->authorize($request->user(), 'settings.read', workspace());

        return response()->json(AppSetting::allWithDefaults());
    }

    /**
     * Update settings for a specific category.
     */
    public function update(Request $request): JsonResponse
    {
        $this->authorization->authorize($request->user(), 'settings.update', workspace(), [
            'category' => (string) $request->input('category'),
            'keys' => array_keys((array) $request->input('settings', [])),
        ]);

        $request->validate([
            'category' => 'required|string|in:organization,agents,notifications,policies,memory,web',
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
     * Legacy single endpoint for the settings UI: dispatches to the same
     * separately authorized command as POST /settings/commands/{command}.
     */
    public function dangerAction(Request $request, SettingsCommands $commands): JsonResponse
    {
        $request->validate([
            'action' => ['required', 'string', Rule::in(SettingsCommands::names())],
        ]);

        return $this->runCommand($request, $commands, (string) $request->input('action'));
    }

    /** Run one settings command (authorized and audited per command). */
    public function command(Request $request, SettingsCommands $commands, string $command): JsonResponse
    {
        if (! in_array($command, SettingsCommands::names(), true)) {
            return response()->json(['message' => 'Unknown command.'], 404);
        }

        return $this->runCommand($request, $commands, $command);
    }

    private function runCommand(Request $request, SettingsCommands $commands, string $command): JsonResponse
    {
        $result = $commands->run($request->user(), workspace(), $command);

        return response()->json(['message' => $result['message']], $result['status']);
    }

    public function debug(Request $request): JsonResponse
    {
        $this->authorization->authorize($request->user(), 'settings.read', workspace());

        // Invariant: the shared log file spans every workspace; only a
        // platform operator sees it (decision recorded when denied).
        $platform = $this->authorization->decide($request->user(), 'settings.diagnostics.platform', workspace())->allowed();

        return response()->json([
            'compaction' => $this->getCompactionHealth(),
            'embedding' => $this->getEmbeddingHealth(),
            'queue' => $this->getQueueHealth(),
            'routines' => $this->getRoutineHealth(),
            'mcp_servers' => $this->getMcpHealth(),
            'agents' => $this->getAgentHealth(),
            'logs' => $platform ? $this->getRecentLogs() : [],
            'logs_scope' => $platform ? 'platform' : 'hidden',
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
        $runs = AgentRunRecord::forWorkspace()->get();
        $open = $runs->whereIn('status', [
            AgentRunRecord::STATUS_QUEUED,
            AgentRunRecord::STATUS_RUNNING,
            AgentRunRecord::STATUS_RETRYING,
            AgentRunRecord::STATUS_WAITING,
        ]);
        $failed = $runs
            ->where('status', AgentRunRecord::STATUS_FAILED)
            ->filter(fn (AgentRunRecord $run) => empty($run->metadata['operator_dismissed_at']));
        $recentFailures = $failed
            ->sortByDesc('finished_at')
            ->take(5)
            ->map(fn (AgentRunRecord $run) => [
                'uuid' => $run->id,
                'job' => $run->kind,
                'queue' => 'default',
                'exception' => Str::limit((string) $run->last_error, 300),
                'failed_at' => $run->finished_at,
                'task_id' => $run->task_id,
                'attempts' => $run->attempt_count,
            ])->values();

        return [
            'pending_jobs' => $open->count(),
            'failed_jobs' => $failed->count(),
            'recent_failures' => $recentFailures,
            'running_jobs' => $open->where('status', AgentRunRecord::STATUS_RUNNING)->count(),
            'oldest_open_at' => $open->min('created_at'),
            'worker_heartbeat' => Cache::get('agent-runtime:queue-worker-heartbeat'),
        ];
    }

    /** @return array<string, mixed> */
    private function getRoutineHealth(): array
    {
        $routines = Routine::forWorkspace()->get([
            'id', 'name', 'is_active', 'last_run_at', 'next_run_at',
            'run_count', 'consecutive_failures', 'last_result',
        ]);

        return [
            'total' => $routines->count(),
            'active' => $routines->where('is_active', true)->count(),
            'failing' => $routines->where('consecutive_failures', '>', 0)->count(),
            'items' => $routines->map(fn ($a) => [
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
