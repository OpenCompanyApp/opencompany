# Observability System

> **Admin-Only** - All observability features require administrator authentication.
>
> This document defines the observability strategy for monitoring, debugging, and improving the Olympus application.

---

## Table of Contents

1. [Overview](#overview)
2. [Metrics Collection](#1-metrics-collection)
3. [Logging Strategy](#2-logging-strategy)
4. [Error Tracking](#3-error-tracking)
5. [Health Checks](#4-health-checks)
6. [Distributed Tracing](#5-distributed-tracing)
7. [Admin Dashboard](#6-admin-dashboard)
8. [Alerting System](#7-alerting-system)
9. [Implementation Plan](#8-implementation-plan)

---

## Overview

### Purpose

The observability system provides administrators with comprehensive visibility into:
- **Performance**: Request latency, database queries, AI API response times
- **Reliability**: Error rates, system health, service availability
- **Cost Optimization**: AI token usage, credit consumption, resource utilization
- **Debugging**: Request tracing, log correlation, error context

### Principles

1. **Admin-Only Access**: All observability endpoints and dashboards require admin authentication
2. **Minimal Overhead**: Observability should not degrade application performance
3. **Actionable Insights**: Every metric should inform a potential action
4. **Privacy-Aware**: Never log sensitive user data (passwords, tokens, PII)

---

## 1. Metrics Collection

### 1.1 Application Metrics

| Metric | Type | Description | Alert Threshold |
|--------|------|-------------|-----------------|
| `http_request_duration_seconds` | Histogram | Request latency by route | p95 > 2s |
| `http_requests_total` | Counter | Total requests by status code | 5xx > 1% |
| `queue_job_duration_seconds` | Histogram | Job processing time | p95 > 30s |
| `queue_jobs_failed_total` | Counter | Failed queue jobs | > 5/min |
| `websocket_connections_active` | Gauge | Active WebSocket connections | - |
| `websocket_messages_total` | Counter | Messages sent/received | - |

#### Implementation

```php
// app/Services/Observability/MetricsService.php

namespace App\Services\Observability;

use Illuminate\Support\Facades\Cache;

class MetricsService
{
    public function recordRequestDuration(string $route, string $method, float $duration): void
    {
        $key = "metrics:http_duration:{$method}:{$route}";
        $this->recordHistogram($key, $duration);
    }

    public function incrementRequestCount(string $route, int $statusCode): void
    {
        $key = "metrics:http_requests:{$route}:{$statusCode}";
        Cache::increment($key);
    }

    public function recordHistogram(string $key, float $value): void
    {
        // Store in time-series format for percentile calculation
        $bucket = now()->format('Y-m-d-H-i'); // 1-minute buckets
        Cache::tags(['metrics', $bucket])->push("{$key}:values", $value);
    }

    public function getPercentile(string $key, int $percentile): ?float
    {
        $values = Cache::tags(['metrics'])->get("{$key}:values", []);
        if (empty($values)) return null;

        sort($values);
        $index = ceil(count($values) * ($percentile / 100)) - 1;
        return $values[$index] ?? null;
    }
}
```

### 1.2 Business Metrics

| Metric | Description | Use Case |
|--------|-------------|----------|
| `agent_tasks_total` | Tasks by agent and status | Agent performance comparison |
| `agent_task_duration_seconds` | Time from start to completion | Identify slow agents |
| `agent_success_rate` | Completed / Total tasks | Agent reliability |
| `ai_tokens_total` | Tokens by provider and model | Cost tracking |
| `ai_cost_dollars` | Estimated cost by provider | Budget monitoring |
| `ai_request_duration_seconds` | LLM API latency | Provider comparison |
| `approval_turnaround_seconds` | Time from request to response | Workflow efficiency |
| `credits_consumed_total` | Credit usage over time | Billing forecasting |

#### AI Cost Tracking

```php
// app/Services/Observability/AICostTracker.php

namespace App\Services\Observability;

use App\Models\AIUsageLog;

class AICostTracker
{
    // Cost per 1K tokens (approximate, update as needed)
    private const COSTS = [
        'anthropic' => [
            'claude-sonnet-4-20250514' => ['input' => 0.003, 'output' => 0.015],
            'claude-opus-4-20250514' => ['input' => 0.015, 'output' => 0.075],
        ],
        'openai' => [
            'gpt-4o' => ['input' => 0.005, 'output' => 0.015],
            'gpt-4o-mini' => ['input' => 0.00015, 'output' => 0.0006],
        ],
        'glm' => [
            'glm-4.7' => ['input' => 0.001, 'output' => 0.002],
        ],
    ];

    public function record(
        string $provider,
        string $model,
        int $inputTokens,
        int $outputTokens,
        ?string $agentId = null
    ): void {
        $costs = self::COSTS[$provider][$model] ?? ['input' => 0, 'output' => 0];
        $totalCost = ($inputTokens / 1000 * $costs['input']) +
                     ($outputTokens / 1000 * $costs['output']);

        AIUsageLog::create([
            'provider' => $provider,
            'model' => $model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'estimated_cost' => $totalCost,
            'agent_id' => $agentId,
            'recorded_at' => now(),
        ]);
    }

    public function getDailyCost(?string $provider = null): float
    {
        $query = AIUsageLog::whereDate('recorded_at', today());

        if ($provider) {
            $query->where('provider', $provider);
        }

        return $query->sum('estimated_cost');
    }

    public function getCostByProvider(string $period = 'day'): array
    {
        $startDate = match($period) {
            'day' => today(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            default => today(),
        };

        return AIUsageLog::where('recorded_at', '>=', $startDate)
            ->selectRaw('provider, SUM(estimated_cost) as total_cost, SUM(input_tokens) as total_input, SUM(output_tokens) as total_output')
            ->groupBy('provider')
            ->get()
            ->keyBy('provider')
            ->toArray();
    }
}
```

### 1.3 Infrastructure Metrics

| Metric | Source | Description |
|--------|--------|-------------|
| `db_query_duration_seconds` | Query log | Slow query detection |
| `db_connections_active` | Database | Connection pool usage |
| `cache_hits_total` | Cache driver | Cache effectiveness |
| `cache_misses_total` | Cache driver | Cache miss rate |
| `memory_usage_bytes` | PHP | Memory consumption |
| `reverb_connections` | Reverb | WebSocket connections |

#### Database Query Monitoring

```php
// app/Providers/ObservabilityServiceProvider.php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ObservabilityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (config('observability.query_logging')) {
            DB::listen(function ($query) {
                $slowThreshold = config('observability.slow_query_threshold', 1000);

                if ($query->time > $slowThreshold) {
                    Log::channel('slow-queries')->warning('Slow query detected', [
                        'sql' => $query->sql,
                        'bindings' => $query->bindings,
                        'time_ms' => $query->time,
                        'connection' => $query->connectionName,
                    ]);
                }

                // Record for metrics
                app(MetricsService::class)->recordHistogram(
                    'db_query_duration',
                    $query->time / 1000 // Convert to seconds
                );
            });
        }
    }
}
```

---

## 2. Logging Strategy

### 2.1 Structured Logging Format

All logs use JSON format with consistent fields:

```json
{
  "timestamp": "2025-01-31T10:30:00.000Z",
  "level": "info",
  "channel": "agent.execution",
  "message": "Agent task completed",
  "context": {
    "correlation_id": "req_abc123",
    "agent_id": "agent_456",
    "task_id": "task_789",
    "duration_ms": 1523,
    "tokens_used": 2450
  },
  "extra": {
    "environment": "production",
    "hostname": "olympus-web-1"
  }
}
```

### 2.2 Log Channels

```php
// config/logging.php (additions)

'channels' => [
    // ... existing channels ...

    'agent' => [
        'driver' => 'daily',
        'path' => storage_path('logs/agent.log'),
        'level' => 'debug',
        'days' => 14,
        'formatter' => \Monolog\Formatter\JsonFormatter::class,
    ],

    'ai' => [
        'driver' => 'daily',
        'path' => storage_path('logs/ai.log'),
        'level' => 'info',
        'days' => 30,
        'formatter' => \Monolog\Formatter\JsonFormatter::class,
    ],

    'slow-queries' => [
        'driver' => 'daily',
        'path' => storage_path('logs/slow-queries.log'),
        'level' => 'warning',
        'days' => 7,
        'formatter' => \Monolog\Formatter\JsonFormatter::class,
    ],

    'security' => [
        'driver' => 'daily',
        'path' => storage_path('logs/security.log'),
        'level' => 'info',
        'days' => 90,
        'formatter' => \Monolog\Formatter\JsonFormatter::class,
    ],

    'observability' => [
        'driver' => 'stack',
        'channels' => ['daily', 'slack'],
        'ignore_exceptions' => false,
    ],
],
```

### 2.3 Log Categories

| Channel | Purpose | Retention |
|---------|---------|-----------|
| `agent.execution` | Agent task lifecycle | 14 days |
| `agent.spawn` | Subagent spawning | 14 days |
| `ai.request` | LLM API calls | 30 days |
| `ai.error` | AI provider errors | 90 days |
| `approval.workflow` | Approval lifecycle | 30 days |
| `realtime.broadcast` | WebSocket events | 7 days |
| `security.auth` | Authentication events | 90 days |
| `security.access` | Authorization failures | 90 days |

### 2.4 Correlation IDs

Every request receives a unique correlation ID that propagates through all logs:

```php
// app/Http/Middleware/CorrelationId.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class CorrelationId
{
    public function handle(Request $request, Closure $next)
    {
        $correlationId = $request->header('X-Correlation-ID') ?? 'req_' . Str::random(12);

        // Store for use throughout request
        app()->instance('correlation_id', $correlationId);

        // Add to all log context
        Log::shareContext([
            'correlation_id' => $correlationId,
        ]);

        $response = $next($request);

        // Include in response headers
        $response->headers->set('X-Correlation-ID', $correlationId);

        return $response;
    }
}
```

### 2.5 Contextual Logging Helper

```php
// app/Services/Observability/Logger.php

namespace App\Services\Observability;

use Illuminate\Support\Facades\Log;

class Logger
{
    public static function agent(string $message, array $context = []): void
    {
        Log::channel('agent')->info($message, self::enrichContext($context, 'agent'));
    }

    public static function ai(string $message, array $context = []): void
    {
        Log::channel('ai')->info($message, self::enrichContext($context, 'ai'));
    }

    public static function security(string $message, array $context = []): void
    {
        Log::channel('security')->info($message, self::enrichContext($context, 'security'));
    }

    private static function enrichContext(array $context, string $category): array
    {
        return array_merge($context, [
            'category' => $category,
            'correlation_id' => app('correlation_id', 'unknown'),
            'timestamp' => now()->toISOString(),
        ]);
    }
}
```

---

## 3. Error Tracking

### 3.1 Custom Exception Classes

```php
// app/Exceptions/AgentException.php

namespace App\Exceptions;

use Exception;

class AgentException extends Exception
{
    public function __construct(
        string $message,
        public readonly ?string $agentId = null,
        public readonly ?string $taskId = null,
        public readonly array $context = [],
        int $code = 0,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function context(): array
    {
        return array_merge($this->context, [
            'agent_id' => $this->agentId,
            'task_id' => $this->taskId,
        ]);
    }
}

// app/Exceptions/AIProviderException.php

namespace App\Exceptions;

use Exception;

class AIProviderException extends Exception
{
    public function __construct(
        string $message,
        public readonly string $provider,
        public readonly ?string $model = null,
        public readonly ?int $statusCode = null,
        public readonly ?array $response = null,
        int $code = 0,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function context(): array
    {
        return [
            'provider' => $this->provider,
            'model' => $this->model,
            'status_code' => $this->statusCode,
            'response' => $this->response,
        ];
    }
}
```

### 3.2 Exception Handler

```php
// bootstrap/app.php (update withExceptions)

->withExceptions(function (Exceptions $exceptions) {
    // Report to external service (Sentry, Rollbar, etc.)
    $exceptions->reportable(function (Throwable $e) {
        if (app()->bound('sentry')) {
            app('sentry')->captureException($e);
        }
    });

    // Custom handling for specific exceptions
    $exceptions->reportable(function (AgentException $e) {
        Log::channel('agent')->error($e->getMessage(), $e->context());
    })->stop();

    $exceptions->reportable(function (AIProviderException $e) {
        Log::channel('ai')->error($e->getMessage(), $e->context());

        // Alert if multiple failures
        app(AlertService::class)->checkAIProviderHealth($e->provider);
    })->stop();
})
```

### 3.3 Sentry Integration (Recommended)

```php
// config/sentry.php

return [
    'dsn' => env('SENTRY_LARAVEL_DSN'),
    'release' => env('APP_VERSION', '1.0.0'),
    'environment' => env('APP_ENV', 'production'),
    'breadcrumbs' => [
        'logs' => true,
        'sql_queries' => true,
        'sql_bindings' => false, // Don't log query values
        'queue_info' => true,
        'command_info' => true,
    ],
    'send_default_pii' => false,
    'traces_sample_rate' => env('SENTRY_TRACES_SAMPLE_RATE', 0.1),
    'profiles_sample_rate' => env('SENTRY_PROFILES_SAMPLE_RATE', 0.1),
];
```

---

## 4. Health Checks

### 4.1 Health Check Endpoints

```php
// routes/api.php (admin routes)

Route::prefix('admin/health')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/', [HealthController::class, 'index']);
    Route::get('/db', [HealthController::class, 'database']);
    Route::get('/cache', [HealthController::class, 'cache']);
    Route::get('/queue', [HealthController::class, 'queue']);
    Route::get('/ai', [HealthController::class, 'ai']);
    Route::get('/websocket', [HealthController::class, 'websocket']);
});
```

### 4.2 Health Check Controller

```php
// app/Http/Controllers/Admin/HealthController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Prism\Prism\Facades\Prism;

class HealthController extends Controller
{
    public function index()
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
            'ai' => $this->checkAI(),
        ];

        $healthy = collect($checks)->every(fn ($check) => $check['status'] === 'healthy');

        return response()->json([
            'status' => $healthy ? 'healthy' : 'degraded',
            'timestamp' => now()->toISOString(),
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }

    public function database()
    {
        return response()->json($this->checkDatabase());
    }

    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $latency = (microtime(true) - $start) * 1000;

            return [
                'status' => 'healthy',
                'latency_ms' => round($latency, 2),
                'connection' => config('database.default'),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkCache(): array
    {
        try {
            $key = 'health_check_' . uniqid();
            Cache::put($key, 'ok', 10);
            $value = Cache::get($key);
            Cache::forget($key);

            return [
                'status' => $value === 'ok' ? 'healthy' : 'unhealthy',
                'driver' => config('cache.default'),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkQueue(): array
    {
        try {
            $size = Queue::size();

            return [
                'status' => 'healthy',
                'driver' => config('queue.default'),
                'pending_jobs' => $size,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkAI(): array
    {
        $providers = ['anthropic', 'openai', 'glm'];
        $results = [];

        foreach ($providers as $provider) {
            try {
                $start = microtime(true);
                // Simple ping - minimal token usage
                Prism::text()
                    ->using($provider, $this->getDefaultModel($provider))
                    ->withPrompt('ping')
                    ->asText();
                $latency = (microtime(true) - $start) * 1000;

                $results[$provider] = [
                    'status' => 'healthy',
                    'latency_ms' => round($latency, 2),
                ];
            } catch (\Exception $e) {
                $results[$provider] = [
                    'status' => 'unhealthy',
                    'error' => $e->getMessage(),
                ];
            }
        }

        $healthyCount = collect($results)->where('status', 'healthy')->count();

        return [
            'status' => $healthyCount > 0 ? 'healthy' : 'unhealthy',
            'providers' => $results,
        ];
    }

    private function getDefaultModel(string $provider): string
    {
        return match($provider) {
            'anthropic' => 'claude-sonnet-4-20250514',
            'openai' => 'gpt-4o-mini',
            'glm' => 'glm-4.7',
            default => throw new \InvalidArgumentException("Unknown provider: $provider"),
        };
    }
}
```

---

## 5. Distributed Tracing

### 5.1 Trace Context

For complex operations spanning multiple services (agent execution, AI calls, database):

```php
// app/Services/Observability/Tracer.php

namespace App\Services\Observability;

use Illuminate\Support\Str;

class Tracer
{
    private array $spans = [];
    private ?string $currentSpanId = null;

    public function startSpan(string $name, array $tags = []): string
    {
        $spanId = 'span_' . Str::random(8);

        $this->spans[$spanId] = [
            'id' => $spanId,
            'name' => $name,
            'parent_id' => $this->currentSpanId,
            'trace_id' => app('correlation_id', 'unknown'),
            'start_time' => microtime(true),
            'tags' => $tags,
        ];

        $this->currentSpanId = $spanId;

        return $spanId;
    }

    public function endSpan(string $spanId, array $tags = []): array
    {
        if (!isset($this->spans[$spanId])) {
            return [];
        }

        $span = &$this->spans[$spanId];
        $span['end_time'] = microtime(true);
        $span['duration_ms'] = ($span['end_time'] - $span['start_time']) * 1000;
        $span['tags'] = array_merge($span['tags'], $tags);

        // Restore parent span as current
        $this->currentSpanId = $span['parent_id'];

        // Log completed span
        Logger::agent("Span completed: {$span['name']}", [
            'span' => $span,
        ]);

        return $span;
    }

    public function addTag(string $spanId, string $key, mixed $value): void
    {
        if (isset($this->spans[$spanId])) {
            $this->spans[$spanId]['tags'][$key] = $value;
        }
    }
}
```

### 5.2 Usage Example

```php
// In agent execution

$tracer = app(Tracer::class);

$agentSpan = $tracer->startSpan('agent.execute', [
    'agent_id' => $agent->id,
    'task_id' => $task->id,
]);

try {
    // AI call
    $aiSpan = $tracer->startSpan('ai.completion', [
        'provider' => 'anthropic',
        'model' => 'claude-sonnet-4-20250514',
    ]);

    $response = $this->callAI($prompt);

    $tracer->endSpan($aiSpan, [
        'tokens_input' => $response->usage->inputTokens,
        'tokens_output' => $response->usage->outputTokens,
    ]);

    // Database operations
    $dbSpan = $tracer->startSpan('db.save_result');
    $this->saveResult($response);
    $tracer->endSpan($dbSpan);

} finally {
    $tracer->endSpan($agentSpan, [
        'status' => $success ? 'success' : 'failed',
    ]);
}
```

---

## 6. Admin Dashboard

### 6.1 Dashboard Page

Create a new admin-only page at `/admin/observability`:

```php
// app/Http/Controllers/Admin/ObservabilityController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Observability\MetricsService;
use App\Services\Observability\AICostTracker;
use Inertia\Inertia;

class ObservabilityController extends Controller
{
    public function __construct(
        private MetricsService $metrics,
        private AICostTracker $costTracker,
    ) {}

    public function index()
    {
        return Inertia::render('Admin/Observability', [
            'metrics' => [
                'requests' => $this->getRequestMetrics(),
                'agents' => $this->getAgentMetrics(),
                'ai' => $this->getAIMetrics(),
                'errors' => $this->getErrorMetrics(),
            ],
        ]);
    }

    private function getRequestMetrics(): array
    {
        return [
            'total_today' => $this->metrics->getTodayRequestCount(),
            'avg_latency_ms' => $this->metrics->getAverageLatency(),
            'p95_latency_ms' => $this->metrics->getPercentile('http_duration', 95),
            'error_rate' => $this->metrics->getErrorRate(),
        ];
    }

    private function getAgentMetrics(): array
    {
        return [
            'active_count' => User::where('type', 'agent')
                ->whereIn('status', ['working', 'idle'])
                ->count(),
            'tasks_today' => Task::whereDate('completed_at', today())->count(),
            'avg_task_duration' => Task::whereDate('completed_at', today())
                ->avg(DB::raw('EXTRACT(EPOCH FROM (completed_at - started_at))')),
            'success_rate' => $this->calculateSuccessRate(),
        ];
    }

    private function getAIMetrics(): array
    {
        return [
            'cost_today' => $this->costTracker->getDailyCost(),
            'cost_by_provider' => $this->costTracker->getCostByProvider('day'),
            'tokens_today' => AIUsageLog::whereDate('recorded_at', today())
                ->sum(DB::raw('input_tokens + output_tokens')),
        ];
    }

    private function getErrorMetrics(): array
    {
        return [
            'total_today' => ErrorLog::whereDate('created_at', today())->count(),
            'by_type' => ErrorLog::whereDate('created_at', today())
                ->selectRaw('exception_type, COUNT(*) as count')
                ->groupBy('exception_type')
                ->pluck('count', 'exception_type'),
        ];
    }
}
```

### 6.2 Dashboard Metrics to Display

| Section | Metrics | Refresh Rate |
|---------|---------|--------------|
| **System Health** | Overall status, DB latency, cache hit rate, queue depth | 30s |
| **Request Performance** | p50/p95/p99 latency, requests/min, error rate | 30s |
| **Agent Performance** | Active agents, tasks/hour, success rate, avg duration | 1m |
| **AI Costs** | Today's cost, cost by provider, token usage chart | 5m |
| **Errors** | Error count, top errors, error trend | 1m |
| **WebSockets** | Active connections, messages/min | 30s |

---

## 7. Alerting System

### 7.1 Alert Rules

```php
// config/observability.php

return [
    'alerts' => [
        'high_error_rate' => [
            'metric' => 'error_rate',
            'condition' => 'gt',
            'threshold' => 5, // 5%
            'window' => 300, // 5 minutes
            'severity' => 'critical',
            'channels' => ['slack', 'email'],
        ],
        'slow_requests' => [
            'metric' => 'http_p95_latency',
            'condition' => 'gt',
            'threshold' => 2000, // 2 seconds
            'window' => 300,
            'severity' => 'warning',
            'channels' => ['slack'],
        ],
        'ai_provider_down' => [
            'metric' => 'ai_health',
            'condition' => 'eq',
            'threshold' => 'unhealthy',
            'window' => 60,
            'severity' => 'critical',
            'channels' => ['slack', 'pagerduty'],
        ],
        'high_ai_cost' => [
            'metric' => 'ai_cost_daily',
            'condition' => 'gt',
            'threshold' => 100, // $100/day
            'window' => 3600,
            'severity' => 'warning',
            'channels' => ['email'],
        ],
        'queue_backlog' => [
            'metric' => 'queue_size',
            'condition' => 'gt',
            'threshold' => 1000,
            'window' => 300,
            'severity' => 'warning',
            'channels' => ['slack'],
        ],
    ],
];
```

### 7.2 Alert Service

```php
// app/Services/Observability/AlertService.php

namespace App\Services\Observability;

use Illuminate\Support\Facades\Notification;
use App\Notifications\ObservabilityAlert;

class AlertService
{
    public function evaluate(): void
    {
        foreach (config('observability.alerts') as $name => $rule) {
            $value = $this->getMetricValue($rule['metric']);

            if ($this->shouldAlert($value, $rule)) {
                $this->sendAlert($name, $rule, $value);
            }
        }
    }

    private function shouldAlert(mixed $value, array $rule): bool
    {
        return match($rule['condition']) {
            'gt' => $value > $rule['threshold'],
            'lt' => $value < $rule['threshold'],
            'eq' => $value === $rule['threshold'],
            'ne' => $value !== $rule['threshold'],
            default => false,
        };
    }

    private function sendAlert(string $name, array $rule, mixed $value): void
    {
        // Check cooldown to prevent alert fatigue
        $cooldownKey = "alert_cooldown:{$name}";
        if (Cache::has($cooldownKey)) {
            return;
        }

        // Set cooldown (don't alert again for 15 minutes)
        Cache::put($cooldownKey, true, 900);

        $alert = new ObservabilityAlert(
            name: $name,
            severity: $rule['severity'],
            metric: $rule['metric'],
            value: $value,
            threshold: $rule['threshold'],
        );

        foreach ($rule['channels'] as $channel) {
            $this->notifyChannel($channel, $alert);
        }
    }

    private function notifyChannel(string $channel, ObservabilityAlert $alert): void
    {
        match($channel) {
            'slack' => Notification::route('slack', config('services.slack.webhook'))
                ->notify($alert),
            'email' => Notification::route('mail', config('observability.alert_email'))
                ->notify($alert),
            default => null,
        };
    }
}
```

---

## 8. Implementation Plan

### Phase 1: Foundation (Week 1)

| Task | Files | Priority |
|------|-------|----------|
| Create MetricsService | `app/Services/Observability/MetricsService.php` | High |
| Add correlation ID middleware | `app/Http/Middleware/CorrelationId.php` | High |
| Configure structured logging | `config/logging.php` | High |
| Create Logger helper | `app/Services/Observability/Logger.php` | Medium |
| Add health check endpoints | `app/Http/Controllers/Admin/HealthController.php` | High |

### Phase 2: Monitoring (Week 2)

| Task | Files | Priority |
|------|-------|----------|
| Create AICostTracker | `app/Services/Observability/AICostTracker.php` | High |
| Add ai_usage_logs migration | `database/migrations/*_create_ai_usage_logs_table.php` | High |
| Create custom exceptions | `app/Exceptions/AgentException.php`, etc. | Medium |
| Update exception handler | `bootstrap/app.php` | Medium |
| Create ObservabilityController | `app/Http/Controllers/Admin/ObservabilityController.php` | High |
| Create Admin/Observability.vue | `resources/js/Pages/Admin/Observability.vue` | High |

### Phase 3: Alerting (Week 3)

| Task | Files | Priority |
|------|-------|----------|
| Create alert configuration | `config/observability.php` | High |
| Create AlertService | `app/Services/Observability/AlertService.php` | High |
| Create ObservabilityAlert notification | `app/Notifications/ObservabilityAlert.php` | High |
| Add alert scheduler | `app/Console/Kernel.php` | Medium |
| Integrate Sentry (optional) | `config/sentry.php` | Low |

### Phase 4: Polish (Week 4)

| Task | Files | Priority |
|------|-------|----------|
| Add Tracer for distributed tracing | `app/Services/Observability/Tracer.php` | Low |
| Create real-time dashboard updates | WebSocket integration | Medium |
| Add historical trend charts | Frontend components | Medium |
| Documentation and runbooks | `docs/runbooks/` | Medium |

---

## Database Migrations

### AI Usage Logs

```php
// database/migrations/xxxx_create_ai_usage_logs_table.php

Schema::create('ai_usage_logs', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('provider', 50);
    $table->string('model', 100);
    $table->integer('input_tokens');
    $table->integer('output_tokens');
    $table->decimal('estimated_cost', 10, 6);
    $table->uuid('agent_id')->nullable();
    $table->uuid('task_id')->nullable();
    $table->string('correlation_id', 50)->nullable();
    $table->timestamp('recorded_at');
    $table->timestamps();

    $table->index(['recorded_at', 'provider']);
    $table->index('agent_id');
});
```

### Error Logs

```php
// database/migrations/xxxx_create_error_logs_table.php

Schema::create('error_logs', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('exception_type');
    $table->text('message');
    $table->text('stack_trace')->nullable();
    $table->json('context')->nullable();
    $table->string('correlation_id', 50)->nullable();
    $table->string('severity', 20)->default('error');
    $table->boolean('resolved')->default(false);
    $table->timestamps();

    $table->index(['created_at', 'exception_type']);
    $table->index('resolved');
});
```

---

## Security Considerations

1. **Admin-Only Access**: All observability endpoints require `admin` middleware
2. **No PII in Logs**: Never log passwords, tokens, or personal data
3. **Rate Limiting**: Apply rate limits to health check endpoints
4. **Log Retention**: Implement automatic log rotation and deletion
5. **Audit Trail**: Log all access to observability dashboard
