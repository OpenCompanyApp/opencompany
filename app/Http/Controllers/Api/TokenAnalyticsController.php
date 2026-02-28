<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TokenAnalyticsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $period = $request->input('period', '30');
        $workspaceId = workspace()->id;

        $baseQuery = fn () => DB::table('tasks')
            ->where('tasks.workspace_id', $workspaceId)
            ->whereNotNull('tasks.result')
            ->when($period !== 'all', fn ($q) => $q->where('tasks.created_at', '>=', now()->subDays((int) $period)));

        // Use ::numeric to safely cast JSON values that may be floats or ints
        $int = fn (string $field) => "(result->>'$field')::numeric";
        $tInt = fn (string $field) => "(tasks.result->>'$field')::numeric";

        // 1. Summary totals
        $summary = $baseQuery()->selectRaw("
            COUNT(*) as total_tasks,
            COALESCE(SUM({$int('total_prompt_tokens')}), 0)::bigint as total_prompt_tokens,
            COALESCE(SUM({$int('total_completion_tokens')}), 0)::bigint as total_completion_tokens,
            COALESCE(SUM({$int('cache_read_tokens')}), 0)::bigint as total_cache_read_tokens,
            COALESCE(SUM({$int('cache_write_tokens')}), 0)::bigint as total_cache_write_tokens,
            COALESCE(ROUND(AVG(NULLIF({$int('tokens_per_second')}, 0)), 1), 0) as avg_tokens_per_second,
            COALESCE(ROUND(AVG(NULLIF({$int('generation_time_ms')}, 0))), 0)::bigint as avg_generation_time_ms,
            COALESCE(SUM({$int('tool_calls_count')}), 0)::bigint as total_tool_calls
        ")->first();

        // 2. Daily time series
        $timeSeries = $baseQuery()->selectRaw("
            DATE(created_at) as date,
            COALESCE(SUM({$int('total_prompt_tokens')}), 0)::bigint as prompt_tokens,
            COALESCE(SUM({$int('total_completion_tokens')}), 0)::bigint as completion_tokens,
            COALESCE(SUM({$int('cache_read_tokens')}), 0)::bigint as cache_read_tokens,
            COUNT(*) as task_count
        ")
            ->groupByRaw('DATE(created_at)')
            ->orderByRaw('DATE(created_at)')
            ->get();

        // 3. By agent (joined with users for name/avatar)
        $byAgent = $baseQuery()
            ->whereNotNull('tasks.agent_id')
            ->join('users', 'users.id', '=', 'tasks.agent_id')
            ->selectRaw("
                tasks.agent_id,
                users.name as agent_name,
                users.avatar as agent_avatar,
                users.agent_type,
                COUNT(*) as task_count,
                COALESCE(SUM({$tInt('total_prompt_tokens')}), 0)::bigint as prompt_tokens,
                COALESCE(SUM({$tInt('total_completion_tokens')}), 0)::bigint as completion_tokens,
                COALESCE(SUM({$tInt('cache_read_tokens')}), 0)::bigint as cache_read_tokens,
                COALESCE(ROUND(AVG(NULLIF({$tInt('tokens_per_second')}, 0)), 1), 0) as avg_tokens_per_second,
                COALESCE(ROUND(AVG(NULLIF({$tInt('generation_time_ms')}, 0))), 0)::bigint as avg_generation_time_ms
            ")
            ->groupBy('tasks.agent_id', 'users.name', 'users.avatar', 'users.agent_type')
            ->orderByRaw("SUM({$tInt('total_prompt_tokens')}) + SUM({$tInt('total_completion_tokens')}) DESC NULLS LAST")
            ->get();

        // 4. By model (from context JSON)
        $byModel = $baseQuery()
            ->whereNotNull('context')
            ->whereRaw("context->>'model' IS NOT NULL")
            ->selectRaw("
                context->>'model' as model,
                context->>'provider' as provider,
                COUNT(*) as task_count,
                COALESCE(SUM({$int('total_prompt_tokens')}), 0)::bigint as prompt_tokens,
                COALESCE(SUM({$int('total_completion_tokens')}), 0)::bigint as completion_tokens,
                COALESCE(SUM({$int('cache_read_tokens')}), 0)::bigint as cache_read_tokens,
                COALESCE(ROUND(AVG(NULLIF({$int('tokens_per_second')}, 0)), 1), 0) as avg_tokens_per_second,
                COALESCE(ROUND(AVG(NULLIF({$int('generation_time_ms')}, 0))), 0)::bigint as avg_generation_time_ms
            ")
            ->groupByRaw("context->>'model', context->>'provider'")
            ->orderByRaw("SUM({$int('total_prompt_tokens')}) + SUM({$int('total_completion_tokens')}) DESC NULLS LAST")
            ->get();

        // 5. By source
        $bySource = $baseQuery()
            ->whereNotNull('source')
            ->selectRaw("
                source,
                COUNT(*) as task_count,
                COALESCE(SUM({$int('total_prompt_tokens')}), 0)::bigint as prompt_tokens,
                COALESCE(SUM({$int('total_completion_tokens')}), 0)::bigint as completion_tokens
            ")
            ->groupBy('source')
            ->orderByRaw("SUM({$int('total_prompt_tokens')}) + SUM({$int('total_completion_tokens')}) DESC NULLS LAST")
            ->get();

        return response()->json([
            'summary' => $summary,
            'timeSeries' => $timeSeries,
            'byAgent' => $byAgent,
            'byModel' => $byModel,
            'bySource' => $bySource,
        ]);
    }
}
