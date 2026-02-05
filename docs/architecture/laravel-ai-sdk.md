# Laravel AI SDK Integration Strategy

> Comprehensive strategy for integrating the official Laravel AI SDK into OpenCompany's agent system.

---

## Table of Contents

1. [Why Laravel AI SDK](#why-laravel-ai-sdk)
2. [Architecture Design](#architecture-design)
3. [Agent Architecture](#agent-architecture)
4. [GLM/Zhipu AI Integration](#glmzhipu-ai-integration)
5. [Provider Resolution](#provider-resolution)
6. [Tool System](#tool-system)
7. [Streaming & Broadcasting](#streaming--broadcasting)
8. [Task Execution](#task-execution)
9. [Conversation Management](#conversation-management)
10. [Embeddings & RAG](#embeddings--rag)
11. [MCP Server](#mcp-server)
12. [Testing Strategy](#testing-strategy)
13. [Migration Path](#migration-path)
14. [Provider Support Matrix](#provider-support-matrix)
15. [Events](#events)
16. [Embedding Cache Strategy](#embedding-cache-strategy)
17. [Document Memory Search](#document-memory-search)
18. [Agent Workspace Files Mapping](#agent-workspace-files-mapping)
19. [Heartbeat System](#heartbeat-system)
20. [Agent Execution Loop](#agent-execution-loop)
21. [Context Compaction](#context-compaction)
22. [Pre-Compaction Memory Flush](#pre-compaction-memory-flush)
23. [Enhanced Subagent Patterns](#enhanced-subagent-patterns)
24. [Agent Execution Pipeline](#agent-execution-pipeline)
25. [Links & Resources](#links--resources)

---

## Why Laravel AI SDK

The Laravel AI SDK (`laravel/ai`) is the **official first-party** AI integration package from the Laravel team. It replaces the community `prism-php/prism` package we previously evaluated.

### Decision Rationale

| Factor | Prism (Previous) | Laravel AI SDK (Current) |
|--------|-------------------|--------------------------|
| **Maintainer** | Community (TJ Miller) | Laravel Team (official) |
| **Status** | Third-party package | First-party, like Sanctum/Reverb |
| **Long-term support** | Uncertain | Guaranteed by Laravel |
| **Feature scope** | Text + tools + streaming | Full multimodal: text, image, audio, TTS, STT, embeddings, reranking, vector stores |
| **Agent pattern** | Fluent builder (`Prism::text()`) | Class-based agents with contracts + attributes |
| **Testing** | Basic mocking | Comprehensive fakes + assertions per feature |
| **MCP support** | Via separate Prism Relay package | Official `laravel/mcp` companion package |
| **Conversation persistence** | Via separate Converse Prism package | Built-in `RemembersConversations` trait |
| **Queue/streaming** | Manual implementation | Native `->queue()`, `->stream()`, `->broadcastOnQueue()` |
| **Artisan generators** | None | `make:agent`, `make:tool`, `make:mcp-server`, `make:mcp-tool` |

### Key Advantages

1. **Official first-party**: Same team that builds Laravel Framework, Sanctum, Reverb, Cashier
2. **Unified API**: Single package for text, images, audio, embeddings, reranking, files, vector stores
3. **Laravel-native patterns**: Contracts, traits, middleware, events, queue integration
4. **Artisan generators**: `php artisan make:agent SalesCoach`, `php artisan make:tool SearchDocs`
5. **Built-in conversation management**: `RemembersConversations` trait with `forUser()` / `continue()`
6. **Streaming + Broadcasting**: `->stream()` returns SSE, `->broadcastOnQueue()` pushes to Reverb
7. **Comprehensive testing**: `Agent::fake()`, `Image::fake()`, `Embeddings::fake()` with assertion helpers
8. **Provider failover**: `provider: ['anthropic', 'openai']` - automatic fallback
9. **MCP companion**: `laravel/mcp` for exposing OpenCompany as an MCP server

---

## Architecture Design

### High-Level Architecture

```
+-----------------------------------------------+
|            OpenCompany Frontend                |
|          (Vue 3 + Inertia.js)                  |
|                                                |
|  Echo/Reverb listeners for streaming tokens    |
+-----------------------------------------------+
                     |
                     v
+-----------------------------------------------+
|            Laravel Backend                     |
|                                                |
|  +-------------------------------------------+|
|  |         Laravel AI SDK (laravel/ai)       ||
|  |                                           ||
|  |  OpenCompanyAgent (app/Agents/)            ||
|  |    - Single dynamic agent class           ||
|  |    - Instructions from identity docs      ||
|  |    - Tools resolved from DB capabilities  ||
|  |                                           ||
|  |  DynamicProviderResolver                  ||
|  |    - Reads IntegrationSetting at runtime  ||
|  |    - Maps GLM -> OpenAI-compatible        ||
|  |    - Resolves API keys, URLs, models      ||
|  +-------------------------------------------+|
|                                                |
|  +-------------------------------------------+|
|  |         Jobs (Queue Workers)              ||
|  |                                           ||
|  |  AgentRespondJob                          ||
|  |    - Streaming chat responses             ||
|  |    - Broadcasts to Reverb channels        ||
|  |                                           ||
|  |  ExecuteAgentTask                         ||
|  |    - Task lifecycle management            ||
|  |    - Tool calls logged as TaskSteps       ||
|  |    - Results stored in task.result        ||
|  +-------------------------------------------+|
|                                                |
|  +-------------------------------------------+|
|  |         Laravel MCP (laravel/mcp)         ||
|  |                                           ||
|  |  OpenCompanyServer                        ||
|  |    - Tools: search, create, query         ||
|  |    - Resources: documents, tasks          ||
|  |    - Auth: Sanctum tokens                 ||
|  +-------------------------------------------+|
+-----------------------------------------------+
                     |
                     v
+-----------------------------------------------+
|            PostgreSQL + pgvector               |
|  - Agent conversations (messages table)        |
|  - Document embeddings (vector search)         |
|  - Integration settings (encrypted configs)    |
|  - Task state + steps                          |
+-----------------------------------------------+
```

### Key Architectural Decisions

1. **Keep existing `messages` table** as conversation storage. Do NOT use the SDK's built-in `agent_conversations` table. Our messages table has rich features (reactions, threads, pins, attachments) that the frontend depends on.

2. **Keep `IntegrationSetting` model** for dynamic provider configuration. The SDK's static `config/ai.php` is insufficient since OpenCompany allows runtime configuration of AI providers via the Integrations UI.

3. **Agent classes are thin wrappers**. The actual personality/instructions remain in the Document-based identity system (8 markdown files). Agent classes primarily map `agent_type` to tool sets and SDK attributes.

4. **Async-first**. All agent interactions go through jobs (`AgentRespondJob`, `ExecuteAgentTask`) to leverage streaming and prevent HTTP timeouts. No synchronous AI calls.

5. **pgvector for embeddings**. The embedding/vector search features require PostgreSQL with pgvector extension.

---

## Agent Architecture

### Design Philosophy: Dynamic, Not Hardcoded

Agents in OpenCompany are **entirely user-defined**. Their personality, instructions, capabilities, and behavior are all stored in the database and editable through the UI. There are no hardcoded agent classes per type.

A single `OpenCompanyAgent` class reads everything from:
- **Identity documents** (IDENTITY.md, SOUL.md, etc.) -> system instructions
- **Capabilities** (DB-stored) -> which tools are enabled
- **Settings** (DB-stored) -> temperature, max steps, behavior mode
- **Brain** (User.brain field) -> which LLM provider/model to use

This means users can create any kind of agent without code changes.

### Directory Structure

```
app/Agents/
  OpenCompanyAgent.php       - Single agent class, fully dynamic from DB
  Providers/
    DynamicProviderResolver.php  - Resolves IntegrationSetting -> SDK provider
  Conversations/
    ChannelConversationLoader.php - Loads conversation from messages table
  Tools/
    Internal/                - Workspace tools (always available)
    External/                - SDK built-in tool wrappers (opt-in per agent)
    Memory/                  - Agent memory tools
    ToolRegistry.php         - Resolves enabled tools per agent from DB
```

### OpenCompanyAgent

```php
<?php

namespace App\Agents;

use App\Models\User;
use App\Services\AgentDocumentService;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

class OpenCompanyAgent implements Agent, HasTools
{
    use Promptable;

    public function __construct(
        protected User $agentUser,
        protected ?AgentDocumentService $documentService = null,
        protected ?ToolRegistry $toolRegistry = null,
    ) {
        $this->documentService ??= app(AgentDocumentService::class);
        $this->toolRegistry ??= app(ToolRegistry::class);
    }

    /**
     * Create an agent instance for any User with type='agent'.
     * No factory needed - one class handles all agent types.
     */
    public static function for(User $agentUser): static
    {
        return new static($agentUser);
    }

    /**
     * Build system instructions from the agent's identity markdown files.
     * These are fully user-editable via the Agent/Show.vue UI.
     *
     * The 8 identity files define everything about the agent:
     * - IDENTITY.md: Name, role, personality
     * - SOUL.md: Core values, boundaries, operating principles
     * - USER.md: User preferences, working styles
     * - AGENTS.md: Knowledge about other agents
     * - TOOLS.md: Tool usage guidelines
     * - MEMORY.md: Long-term memories
     * - HEARTBEAT.md: Check-in behavior
     * - BOOTSTRAP.md: Initialization instructions
     */
    public function instructions(): Stringable|string
    {
        $identityFiles = $this->documentService->getIdentityFiles($this->agentUser);

        if ($identityFiles->isEmpty()) {
            return "You are {$this->agentUser->name}, a helpful AI assistant.";
        }

        $prompt = "# Project Context\n\nYou are an AI agent operating within a company workspace.\n\n";

        $order = ['IDENTITY', 'SOUL', 'USER', 'AGENTS', 'TOOLS', 'MEMORY', 'HEARTBEAT', 'BOOTSTRAP'];

        foreach ($order as $type) {
            $file = $identityFiles->firstWhere('title', "{$type}.md");
            if ($file && !empty(trim($file->content))) {
                $prompt .= "## {$type}.md\n\n{$file->content}\n\n";
            }
        }

        return $prompt;
    }

    /**
     * Tools are resolved dynamically from the agent's enabled capabilities in the DB.
     * Users enable/disable tools per agent via the Capabilities tab in the UI.
     */
    public function tools(): iterable
    {
        return $this->toolRegistry->getToolsForAgent($this->agentUser);
    }

    /**
     * Access the underlying User model.
     */
    public function user(): User
    {
        return $this->agentUser;
    }
}
```

### ToolRegistry

The `ToolRegistry` resolves which tools an agent has access to based on their DB-stored capabilities:

```php
<?php

namespace App\Agents;

use App\Models\User;
use Laravel\Ai\Providers\Tools\WebSearch;
use Laravel\Ai\Providers\Tools\WebFetch;

class ToolRegistry
{
    /**
     * All available tools mapped to their capability identifiers.
     * When a capability is enabled for an agent, its tools become available.
     */
    private array $capabilityToolMap = [
        'documents'     => [Tools\Internal\SearchDocuments::class, Tools\Internal\ReadDocument::class, Tools\Internal\UpdateDocument::class],
        'lists'         => [Tools\Internal\CreateListItem::class, Tools\Internal\UpdateListItem::class],
        'messaging'     => [Tools\Internal\SendMessage::class],
        'tasks'         => [Tools\Internal\CreateTaskStep::class],
        'approvals'     => [Tools\Internal\CreateApproval::class],
        'data_tables'   => [Tools\Internal\QueryDataTable::class],
        'web_search'    => [WebSearch::class],
        'web_fetch'     => [WebFetch::class],
    ];

    /**
     * Tools that are always available to every agent (no capability required).
     */
    private array $alwaysAvailable = [
        Tools\Memory\SaveMemory::class,
        Tools\Memory\RecallMemory::class,
    ];

    /**
     * Resolve enabled tools for an agent based on their DB-stored capabilities.
     */
    public function getToolsForAgent(User $agent): array
    {
        $tools = [];

        // Always-available tools
        foreach ($this->alwaysAvailable as $toolClass) {
            $tools[] = app($toolClass, ['agent' => $agent]);
        }

        // Capability-gated tools (read from agent_capabilities table)
        // When capabilities are fully implemented, this reads from the DB.
        // For now, all internal tools are available to all agents.
        foreach ($this->capabilityToolMap as $capability => $toolClasses) {
            foreach ($toolClasses as $toolClass) {
                $tools[] = app($toolClass);
            }
        }

        return $tools;
    }
}
```

---

## GLM/Zhipu AI Integration

GLM (Zhipu AI) is **not a native provider** in the Laravel AI SDK. However, GLM uses an **OpenAI-compatible API format**, which means it can be used through the SDK's OpenAI provider with a custom base URL.

### GLM Provider Details

| Setting | GLM (General) | GLM Coding |
|---------|---------------|------------|
| **Integration ID** | `glm` | `glm-coding` |
| **Base URL** | `https://open.bigmodel.cn/api/paas/v4` | `https://api.z.ai/api/coding/paas/v4` |
| **Models** | `glm-4-plus`, `glm-4`, `glm-4-air`, `glm-4-flash` | `glm-4.7` |
| **API Format** | OpenAI-compatible | OpenAI-compatible |
| **Auth** | Bearer token | Bearer token |

### How It Works

The `DynamicProviderResolver` maps GLM integration IDs to the SDK's OpenAI provider with a custom base URL override:

```php
<?php

namespace App\Agents\Providers;

use App\Models\IntegrationSetting;
use App\Models\User;

class DynamicProviderResolver
{
    /**
     * Resolve provider configuration for an agent from IntegrationSetting.
     *
     * Agent.brain format: "provider:model" (e.g., "glm-coding:glm-4.7")
     */
    public function resolveForAgent(User $agent): array
    {
        $brain = $agent->brain ?? 'anthropic:claude-sonnet-4-20250514';
        [$integrationId, $model] = array_pad(explode(':', $brain, 2), 2, null);

        $integration = IntegrationSetting::where('integration_id', $integrationId)
            ->where('enabled', true)
            ->first();

        if (!$integration || !$integration->hasValidConfig()) {
            throw new ProviderNotConfiguredException(
                "AI provider '{$integrationId}' is not configured. Enable it in Integrations settings."
            );
        }

        return [
            'provider' => $this->mapToSdkProvider($integrationId),
            'model' => $model ?? $integration->getConfigValue('default_model'),
            'api_key' => $integration->getConfigValue('api_key'),
            'base_url' => $integration->getConfigValue('url') ?? $this->getDefaultUrl($integrationId),
        ];
    }

    /**
     * Map internal integration IDs to Laravel AI SDK provider names.
     *
     * GLM/Zhipu AI uses an OpenAI-compatible API, so we route it
     * through the SDK's OpenAI provider with a custom base_url.
     */
    private function mapToSdkProvider(string $integrationId): string
    {
        return match ($integrationId) {
            'glm', 'glm-coding' => 'openai',  // OpenAI-compatible API
            'openai'            => 'openai',
            'anthropic'         => 'anthropic',
            'gemini'            => 'gemini',
            'groq'              => 'groq',
            'xai'               => 'xai',
            'cohere'            => 'cohere',
            'jina'              => 'jina',
            'elevenlabs'        => 'elevenlabs',
            default             => $integrationId,
        };
    }

    /**
     * Default API base URLs per integration.
     */
    private function getDefaultUrl(string $integrationId): ?string
    {
        return match ($integrationId) {
            'glm'        => 'https://open.bigmodel.cn/api/paas/v4',
            'glm-coding' => 'https://api.z.ai/api/coding/paas/v4',
            default      => null,  // SDK uses its own defaults for native providers
        };
    }
}
```

### Usage in Agent Classes

When an agent with `brain: 'glm-coding:glm-4.7'` is prompted, the flow is:

1. `OpenCompanyAgent::for($agentUser)` creates a `CoderAgent`
2. `DynamicProviderResolver::resolveForAgent($agentUser)` returns:
   - `provider: 'openai'` (SDK's OpenAI provider)
   - `model: 'glm-4.7'`
   - `api_key: 'xxx'` (from encrypted IntegrationSetting)
   - `base_url: 'https://api.z.ai/api/coding/paas/v4'`
3. The agent is prompted with the OpenAI provider pointing to GLM's endpoint

```php
// In AgentRespondJob or any caller:
$resolver = app(DynamicProviderResolver::class);
$config = $resolver->resolveForAgent($agentUser);

$agent = OpenCompanyAgent::for($agentUser);
$response = $agent->prompt(
    $userMessage,
    provider: $config['provider'],
    model: $config['model'],
);
```

### Expanding Supported Providers

Update `IntegrationSetting::getAvailableIntegrations()` to include all SDK-supported providers:

```php
public static function getAvailableIntegrations(): array
{
    return [
        'anthropic'  => ['name' => 'Anthropic',   'models' => ['claude-sonnet-4-20250514', 'claude-haiku-4-5-20251001', 'claude-opus-4-20250514']],
        'openai'     => ['name' => 'OpenAI',      'models' => ['gpt-4o', 'gpt-4o-mini', 'o1', 'o3-mini']],
        'gemini'     => ['name' => 'Google Gemini','models' => ['gemini-2.0-flash', 'gemini-2.0-pro']],
        'groq'       => ['name' => 'Groq',        'models' => ['llama-3.3-70b', 'mixtral-8x7b']],
        'xai'        => ['name' => 'xAI',         'models' => ['grok-2', 'grok-2-mini']],
        'glm'        => ['name' => 'GLM (Zhipu)', 'models' => ['glm-4-plus', 'glm-4', 'glm-4-air', 'glm-4-flash']],
        'glm-coding' => ['name' => 'GLM Coding',  'models' => ['glm-4.7']],
    ];
}
```

---

## Provider Resolution

### How Provider Config Flows

```
User.brain ("glm-coding:glm-4.7")
        |
        v
DynamicProviderResolver.resolveForAgent()
        |
        +-- Reads IntegrationSetting from DB
        +-- Maps integration_id to SDK provider name
        +-- Returns: { provider, model, api_key, base_url }
        |
        v
Agent->prompt($message, provider: $config['provider'], model: $config['model'])
        |
        v
Laravel AI SDK handles the HTTP call to the provider API
```

### Why Dynamic Resolution

Static config files (`config/ai.php`) work for single-provider setups. OpenCompany needs dynamic resolution because:

1. **Multiple providers per deployment**: Each agent can use a different provider
2. **Runtime configuration**: Admins enable/disable providers via the Integrations UI
3. **Encrypted credentials**: API keys stored encrypted in DB, not in `.env`
4. **Custom endpoints**: GLM and other OpenAI-compatible providers need custom URLs
5. **Per-workspace isolation**: Future multi-tenancy requires per-workspace provider config

---

## Tool System

### Tool Directory Structure

```
app/Agents/Tools/
  Internal/
    SearchDocuments.php      - Search workspace documents by keyword
    ReadDocument.php         - Read a specific document's content
    UpdateDocument.php       - Edit/update a document
    CreateListItem.php       - Create a kanban board item
    UpdateListItem.php       - Update list item status/details
    CreateTaskStep.php       - Log progress step on current task
    SendMessage.php          - Send message in a channel
    CreateApproval.php       - Request human approval
    QueryDataTable.php       - Query workspace data tables
  External/
    (Use SDK built-ins directly: WebSearch, WebFetch, FileSearch, SimilaritySearch)
  Memory/
    SaveMemory.php           - Write to agent's long-term memory
    RecallMemory.php         - Search agent's memory files
```

### Tool Implementation Pattern

```php
<?php

namespace App\Agents\Tools\Internal;

use App\Models\Document;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SearchDocuments implements Tool
{
    public function description(): Stringable|string
    {
        return 'Search workspace documents by keyword. Returns matching document titles and excerpts.';
    }

    public function handle(Request $request): Stringable|string
    {
        $results = Document::where('is_folder', false)
            ->where('content', 'ilike', "%{$request['query']}%")
            ->limit($request['limit'] ?? 10)
            ->get(['id', 'title', 'content'])
            ->map(fn ($doc) => [
                'id' => $doc->id,
                'title' => $doc->title,
                'excerpt' => str($doc->content)->limit(200),
            ]);

        return json_encode($results);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('Search keywords')
                ->required(),
            'limit' => $schema->integer()
                ->description('Maximum results to return')
                ->min(1)
                ->max(50),
        ];
    }
}
```

### Creating New Tools

```bash
php artisan make:tool CreateListItem
php artisan make:tool SendMessage
php artisan make:tool SaveMemory
```

### Built-in SDK Tools

The SDK provides provider-native tools that require no custom implementation. These are registered in the `ToolRegistry` and enabled per agent via capabilities:

```php
use Laravel\Ai\Providers\Tools\WebSearch;
use Laravel\Ai\Providers\Tools\WebFetch;
use Laravel\Ai\Providers\Tools\FileSearch;
use Laravel\Ai\Tools\SimilaritySearch;

// Registered in ToolRegistry's capabilityToolMap:
'web_search'  => [(new WebSearch)->max(5)],
'web_fetch'   => [(new WebFetch)->max(3)],
'rag_search'  => [SimilaritySearch::usingModel(Document::class, 'embedding')
                    ->withDescription('Search company knowledge base.')],
```

Users enable these per agent through the Capabilities tab in the UI. A researcher agent might have `web_search`, `web_fetch`, and `rag_search` enabled, while a writer agent might only have `documents` and `messaging`.

---

## Streaming & Broadcasting

### Current Problem

`AgentChatService::respond()` makes a **synchronous** HTTP call that blocks the Laravel worker for the full duration of the AI response. This causes HTTP timeouts for long responses and provides no real-time feedback.

### Solution: Streaming via Jobs + Reverb

```php
<?php

namespace App\Jobs;

use App\Agents\AgentFactory;
use App\Agents\Providers\DynamicProviderResolver;
use App\Models\Message;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Laravel\Ai\Responses\StreamedAgentResponse;

class AgentRespondJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public function __construct(
        protected Message $triggerMessage,
        protected User $agentUser,
        protected string $channelId,
    ) {}

    public function handle(DynamicProviderResolver $resolver): void
    {
        $config = $resolver->resolveForAgent($this->agentUser);
        $agent = AgentFactory::for($this->agentUser);

        // Load conversation history from messages table
        $history = $this->loadConversationHistory();

        // Stream response, broadcasting each chunk to Reverb
        $response = $agent
            ->prompt(
                $this->triggerMessage->content,
                provider: $config['provider'],
                model: $config['model'],
            );

        // Save the complete response as a new message
        $agentMessage = Message::create([
            'channel_id' => $this->channelId,
            'author_id' => $this->agentUser->id,
            'content' => (string) $response,
        ]);

        broadcast(new \App\Events\MessageSent($agentMessage));
    }

    private function loadConversationHistory(): array
    {
        return Message::where('channel_id', $this->channelId)
            ->orderBy('created_at', 'desc')
            ->take(20)
            ->get()
            ->reverse()
            ->map(fn (Message $msg) => [
                'role' => $msg->author_id === $this->agentUser->id ? 'assistant' : 'user',
                'content' => $msg->content,
            ])
            ->toArray();
    }
}
```

### Broadcast Channels

Add to `routes/channels.php`:

```php
// Agent streaming channel - broadcasts thinking/typing status
Broadcast::channel('agent.{agentId}.thinking', function ($user, $agentId) {
    return true; // Authenticated users can listen
});

// Task progress channel - broadcasts step updates
Broadcast::channel('tasks.{taskId}', function ($user, $taskId) {
    return true;
});
```

### Frontend Integration

```typescript
// In chat component or composable
import Echo from 'laravel-echo'

Echo.channel(`agent.${agentId}.thinking`)
    .listen('AgentStreamChunk', (event: { chunk: string }) => {
        streamingContent.value += event.chunk
    })
    .listen('AgentResponseComplete', (event: { messageId: string }) => {
        streamingContent.value = ''
        // Message will arrive via normal MessageSent event
    })
```

---

## Task Execution

### ExecuteAgentTask Job

When a task with an assigned agent is started, dispatch `ExecuteAgentTask`:

```php
<?php

namespace App\Jobs;

use App\Agents\AgentFactory;
use App\Agents\Providers\DynamicProviderResolver;
use App\Events\TaskUpdated;
use App\Models\Task;
use App\Models\TaskStep;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExecuteAgentTask implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300; // 5 minutes max

    public function __construct(protected Task $task) {}

    public function handle(DynamicProviderResolver $resolver): void
    {
        $this->task->start();
        broadcast(new TaskUpdated($this->task));

        try {
            $config = $resolver->resolveForAgent($this->task->agent);
            $agent = OpenCompanyAgent::for($this->task->agent);

            $response = $agent->prompt(
                $this->buildTaskPrompt(),
                provider: $config['provider'],
                model: $config['model'],
            );

            $this->task->complete(['response' => (string) $response]);
            broadcast(new TaskUpdated($this->task));

        } catch (\Exception $e) {
            $this->task->fail($e->getMessage());
            broadcast(new TaskUpdated($this->task));
        }
    }

    private function buildTaskPrompt(): string
    {
        $prompt = "## Task: {$this->task->title}\n\n";

        if ($this->task->description) {
            $prompt .= "{$this->task->description}\n\n";
        }

        if ($this->task->context) {
            $prompt .= "## Context\n" . json_encode($this->task->context, JSON_PRETTY_PRINT) . "\n\n";
        }

        $prompt .= "Complete this task and provide your results.";

        return $prompt;
    }
}
```

### TaskController Integration

Modify `TaskController::start()` to dispatch the job when an agent is assigned:

```php
public function start(string $id)
{
    $task = Task::findOrFail($id);

    if ($task->agent_id) {
        ExecuteAgentTask::dispatch($task)->onQueue('agents');
    } else {
        $task->start();
    }

    return response()->json($task->load(['agent', 'requester', 'steps']));
}
```

---

## Conversation Management

### Strategy: Use Existing Messages Table

The SDK provides `RemembersConversations` with its own `agent_conversations` and `agent_conversation_messages` tables. However, OpenCompany already stores all messages in the `messages` table with features the frontend depends on:

- Reactions, threads, pins, attachments
- Real-time sync via Reverb
- Channel membership and permissions
- Direct message support

**Decision**: Do NOT use the SDK's conversation persistence. Instead, load conversation context from the existing `messages` table.

### ChannelConversationLoader

```php
<?php

namespace App\Agents\Conversations;

use App\Models\Message;
use App\Models\User;

class ChannelConversationLoader
{
    /**
     * Load recent conversation from the messages table for a given channel,
     * formatted for the Laravel AI SDK.
     */
    public function load(string $channelId, User $agent, int $limit = 20): array
    {
        return Message::where('channel_id', $channelId)
            ->orderBy('created_at', 'desc')
            ->take($limit)
            ->get()
            ->reverse()
            ->map(fn (Message $msg) => [
                'role' => $msg->author_id === $agent->id ? 'assistant' : 'user',
                'content' => $msg->content,
            ])
            ->toArray();
    }
}
```

---

## Embeddings & RAG

> **Prerequisite**: PostgreSQL with pgvector extension. This is a Phase 2 feature.

### Database Setup

```php
// Migration
Schema::ensureVectorExtensionExists();

Schema::create('document_embeddings', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('document_id')->constrained()->cascadeOnDelete();
    $table->text('chunk_text');
    $table->vector('embedding', dimensions: 1536)->index();
    $table->integer('chunk_index')->default(0);
    $table->timestamps();
});
```

### Embedding Generation

```php
use Laravel\Ai\Embeddings;

// Generate embeddings for a document
$embeddings = Embeddings::for([$document->content])
    ->cache()
    ->generate('openai', 'text-embedding-3-small');

// Store in pgvector column
DocumentEmbedding::create([
    'document_id' => $document->id,
    'chunk_text' => $document->content,
    'embedding' => $embeddings->embeddings[0],
]);
```

### Querying Similar Documents

```php
use App\Models\DocumentEmbedding;

// Find similar documents by text query (auto-embeds the query)
$results = DocumentEmbedding::query()
    ->whereVectorSimilarTo('embedding', 'best practices for agent memory')
    ->limit(10)
    ->get();

// Or with explicit embedding
$queryEmbedding = Embeddings::for(['search query'])->generate();
$results = DocumentEmbedding::query()
    ->whereVectorSimilarTo('embedding', $queryEmbedding->embeddings[0])
    ->limit(10)
    ->get();
```

### SimilaritySearch Tool

Registered in the `ToolRegistry` and enabled per agent via the `rag_search` capability:

```php
use Laravel\Ai\Tools\SimilaritySearch;
use App\Models\DocumentEmbedding;

// In ToolRegistry capabilityToolMap:
'rag_search' => [
    SimilaritySearch::usingModel(
        model: DocumentEmbedding::class,
        column: 'embedding',
        minSimilarity: 0.7,
        limit: 10,
    )->withDescription('Search the company knowledge base for relevant documents.'),
],
```

### Embedding Caching

The SDK includes built-in embedding caching:

```php
// config/ai.php
'caching' => [
    'embeddings' => [
        'cache' => true,
        'store' => env('CACHE_STORE', 'database'),
    ],
],
```

---

## MCP Server

> **Package**: `laravel/mcp`

The MCP (Model Context Protocol) server allows external AI clients (Claude Code, GitHub Copilot, Cursor, etc.) to interact with OpenCompany.

### Installation

```bash
composer require laravel/mcp
php artisan vendor:publish --tag=ai-routes
```

### Server Definition

```php
<?php

namespace App\Mcp;

use Laravel\Mcp\Server;

class OpenCompanyServer extends Server
{
    protected string $name = 'OpenCompany';
    protected string $version = '1.0.0';
    protected string $instructions = 'OpenCompany workspace server. Search documents, manage tasks, and interact with agents.';

    protected array $tools = [
        Tools\SearchDocumentsTool::class,
        Tools\ListTasksTool::class,
        Tools\CreateTaskTool::class,
        Tools\SendMessageTool::class,
    ];

    protected array $resources = [
        Resources\DocumentResource::class,
        Resources\AgentResource::class,
    ];
}
```

### Route Registration

```php
// routes/ai.php
use App\Mcp\OpenCompanyServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp', OpenCompanyServer::class)
    ->middleware(['auth:sanctum']);
```

### Authentication

Uses existing Laravel Sanctum (already installed):

```php
// Users create API tokens with MCP abilities
$token = $user->createToken('mcp-client', ['mcp:read', 'mcp:write']);
```

---

## Testing Strategy

### Agent Testing

```php
use App\Agents\OpenCompanyAgent;
use App\Models\User;
use Laravel\Ai\Prompts\AgentPrompt;

public function test_agent_responds_with_identity(): void
{
    // Fake all agent responses
    OpenCompanyAgent::fake([
        'Here is the code fix...',
    ]);

    $agentUser = User::factory()->create([
        'type' => 'agent',
        'agent_type' => 'coder',
        'brain' => 'anthropic:claude-sonnet-4-20250514',
    ]);

    $agent = OpenCompanyAgent::for($agentUser);
    $response = $agent->prompt('Fix this bug');

    OpenCompanyAgent::assertPrompted(function (AgentPrompt $prompt) {
        return str_contains($prompt->prompt, 'Fix this bug');
    });

    $this->assertEquals('Here is the code fix...', (string) $response);
}
```

### Task Execution Testing

```php
public function test_task_completes_via_agent(): void
{
    OpenCompanyAgent::fake(['Task completed successfully.']);

    $task = Task::factory()->create([
        'status' => 'pending',
        'agent_id' => $this->agent->id,
    ]);

    ExecuteAgentTask::dispatchSync($task);

    $task->refresh();
    $this->assertEquals('completed', $task->status);
    $this->assertNotNull($task->completed_at);
    $this->assertArrayHasKey('response', $task->result);
}

public function test_failed_task_records_error(): void
{
    OpenCompanyAgent::fake(fn () => throw new \Exception('API timeout'));

    $task = Task::factory()->create(['status' => 'pending']);

    ExecuteAgentTask::dispatchSync($task);

    $task->refresh();
    $this->assertEquals('failed', $task->status);
}
```

### Embedding Testing

```php
use Laravel\Ai\Embeddings;

public function test_document_embedding_generated(): void
{
    Embeddings::fake();

    $doc = Document::factory()->create(['content' => 'Test content']);
    GenerateEmbeddingJob::dispatchSync($doc);

    Embeddings::assertGenerated(function ($prompt) {
        return $prompt->contains('Test content');
    });
}
```

### Image Testing

```php
use Laravel\Ai\Image;

public function test_image_generation(): void
{
    Image::fake();

    // Trigger image generation (e.g., via agent tool or direct call)
    $image = Image::of('company logo design')->generate();

    Image::assertGenerated(function ($prompt) {
        return $prompt->contains('logo design');
    });
}
```

### Preventing Stray Calls

```php
protected function setUp(): void
{
    parent::setUp();

    // Ensure no real API calls are made in tests
    OpenCompanyAgent::fake()->preventStrayPrompts();
    Embeddings::fake()->preventStrayEmbeddings();
    Image::fake()->preventStrayImages();
}
```

---

## Migration Path

### Phase 1: Foundation (No Breaking Changes)

1. `composer require laravel/ai`
2. `php artisan vendor:publish --provider="Laravel\Ai\AiServiceProvider"`
3. `php artisan migrate` (creates `agent_conversations`, `agent_conversation_messages`)
4. Set environment variables:
   ```env
   ANTHROPIC_API_KEY=
   OPENAI_API_KEY=
   ```
5. Create `app/Agents/` directory structure
6. Create `OpenCompanyAgent`, `ToolRegistry`, `DynamicProviderResolver`
7. Create internal tools (`SearchDocuments`, `ReadDocument`, etc.)

### Phase 2: Replace AgentChatService

8. Create `AgentRespondJob` with streaming support
9. Modify `MessageController::handleAgentResponse()` to dispatch job
10. Add broadcast channels for streaming
11. Update frontend Echo listeners
12. Test all agent types respond correctly

### Phase 3: Task Execution

13. Create `ExecuteAgentTask` job
14. Wire into `TaskController::start()`
15. Add task broadcast channel for progress
16. Update Tasks.vue and TaskDetailDrawer.vue

### Phase 4: Remove Prism

17. Delete `config/prism.php`
18. Remove Prism registration from `AppServiceProvider`
19. Delete `TestGlmPing` command
20. `composer remove prism-php/prism`

### Phase 5: Advanced Features

21. Embeddings + pgvector (requires PostgreSQL migration)
22. `SimilaritySearch` tool (enabled per agent via capabilities)
23. `laravel/mcp` server installation
24. Multimodal: image generation, audio, transcription
25. Audio/transcription support

---

## Provider Support Matrix

### Text Generation

| Provider | Supported | Chat | Streaming | Tool Calling | Structured Output |
|----------|-----------|------|-----------|-------------|-------------------|
| **OpenAI** | Yes | Yes | Yes | Yes | Yes |
| **Anthropic** | Yes | Yes | Yes | Yes | Yes |
| **Gemini** | Yes | Yes | Yes | Yes | Yes |
| **Groq** | Yes | Yes | Yes | Yes | Yes |
| **xAI** | Yes | Yes | Yes | Yes | Yes |
| **GLM/Zhipu** | Via OpenAI | Yes | Yes | Yes | Yes |

### Multimodal

| Feature | OpenAI | Anthropic | Gemini | xAI | ElevenLabs | Cohere | Jina |
|---------|--------|-----------|--------|-----|------------|--------|------|
| **Image Generation** | Yes | - | Yes | Yes | - | - | - |
| **Text-to-Speech** | Yes | - | - | - | Yes | - | - |
| **Speech-to-Text** | Yes | - | - | - | Yes | - | - |
| **Embeddings** | Yes | - | Yes | - | - | Yes | Yes |
| **Reranking** | - | - | - | - | - | Yes | Yes |
| **File Storage** | Yes | Yes | Yes | - | - | - | - |

### Environment Variables

```env
# Required (at least one text provider)
ANTHROPIC_API_KEY=
OPENAI_API_KEY=

# Optional providers
GEMINI_API_KEY=
GROQ_API_KEY=
XAI_API_KEY=
COHERE_API_KEY=
JINA_API_KEY=
ELEVENLABS_API_KEY=

# GLM/Zhipu AI (configured via IntegrationSetting, not .env)
# API keys stored encrypted in integration_settings table
```

---

## Events

The SDK dispatches events that can be used for logging, monitoring, and cost tracking:

| Event | When | Use in OpenCompany |
|-------|------|--------------------|
| `PromptingAgent` | Before AI call | Log prompt, start timer |
| `AgentPrompted` | After AI call | Log response, track tokens, update activity |
| `StreamingAgent` | Stream started | Update agent status to "working" |
| `AgentStreamed` | Stream complete | Update agent status to "idle" |
| `InvokingTool` | Before tool call | Log tool usage, check permissions |
| `ToolInvoked` | After tool call | Log tool result, track duration |
| `GeneratingImage` | Before image gen | Check budget |
| `ImageGenerated` | After image gen | Store image, track cost |
| `GeneratingEmbeddings` | Before embedding | Track embedding usage |
| `EmbeddingsGenerated` | After embedding | Cache result |

### Event Listeners

```php
// app/Listeners/TrackAgentActivity.php
class TrackAgentActivity
{
    public function handle(AgentPrompted $event): void
    {
        Activity::create([
            'type' => 'agent_response',
            'description' => 'Agent responded to message',
            'actor_id' => $event->agent->agentUser->id,
            'metadata' => [
                'tokens' => $event->response->usage,
                'provider' => $event->prompt->provider,
                'model' => $event->prompt->model,
            ],
        ]);
    }
}
```

---

## Embedding Cache Strategy

> Inspired by OpenClaw's embedding cache pattern for zero-cost re-indexing.

### Cache Architecture

Embedding generation is expensive. OpenClaw solves this with a SHA256-keyed cache that stores embeddings by `(provider, model, content_hash)`. This means:

- Same text with same model = cache hit (no API call)
- Same text with different model = cache miss (different embeddings per model)
- Modified text = new hash = cache miss (re-embed only changed content)

### Cache Seeding During Reindex

When re-indexing is needed (e.g., chunking strategy change), OpenClaw seeds the new cache from the old one:

1. Build new database/index
2. Copy matching `(provider, model, content_hash)` entries from old cache
3. Only re-embed content that has no cache entry
4. Atomic rename: new DB → active, old DB → backup

This reduces re-indexing cost by ~90% since most content hasn't changed.

### OpenCompany Implementation

```php
// Migration: embedding_cache table
Schema::create('embedding_cache', function (Blueprint $table) {
    $table->string('provider', 50);
    $table->string('model', 100);
    $table->string('content_hash', 64); // SHA256
    $table->vector('embedding', dimensions: 1536);
    $table->integer('dims');
    $table->timestamps();

    $table->primary(['provider', 'model', 'content_hash']);
});

// EmbeddingCacheService
class EmbeddingCacheService
{
    public function getOrCreate(string $text, string $provider, string $model): array
    {
        $hash = hash('sha256', $text);

        $cached = EmbeddingCache::where('provider', $provider)
            ->where('model', $model)
            ->where('content_hash', $hash)
            ->first();

        if ($cached) {
            return $cached->embedding;
        }

        $embedding = Embeddings::for([$text])->generate($provider, $model);

        EmbeddingCache::create([
            'provider' => $provider,
            'model' => $model,
            'content_hash' => $hash,
            'embedding' => $embedding->embeddings[0],
            'dims' => count($embedding->embeddings[0]),
        ]);

        return $embedding->embeddings[0];
    }

    /**
     * Seed new cache from old during reindex.
     * Copies matching entries to avoid re-embedding unchanged content.
     */
    public function seedFromExisting(string $provider, string $model, array $contentHashes): int
    {
        return EmbeddingCache::where('provider', $provider)
            ->where('model', $model)
            ->whereIn('content_hash', $contentHashes)
            ->count(); // Already exists, no action needed
    }
}
```

---

## Document Memory Search

> OpenCompany's QMD-equivalent system: PostgreSQL-based collection search with hybrid ranking across agent documents. Adapted from OpenClaw's QMD sidecar architecture for a server-side multi-tenant environment.

### Architecture Overview

OpenClaw uses a SQLite-based QMD subprocess as a sidecar to each agent. OpenCompany replaces this with PostgreSQL-backed services integrated into the existing Document model hierarchy:

| QMD (OpenClaw) | OpenCompany Equivalent |
|---|---|
| SQLite database per agent | PostgreSQL `memory_chunks` table (shared, agent-scoped) |
| QMD subprocess (sidecar) | Laravel queue jobs + services (in-process) |
| Filesystem collections | Document-based collections via `MemoryCollection` model |
| File watcher (inotify, 15s debounce) | Eloquent model observers + debounced queue dispatch |
| Cron-like periodic re-index (5m) | Laravel Scheduler `everyFiveMinutes()` |
| Embedding refresh (60m) | Laravel Scheduler `hourly()` |
| IPC to subprocess | Direct PHP service calls |
| Per-agent SQLite fallback | Single PostgreSQL backend (no fallback needed) |

### Collection System

Collections scope searches to specific document subsets, replacing QMD's filesystem path groups:

```php
// MemoryCollection model
class MemoryCollection extends Model
{
    use HasUuids;

    protected $fillable = ['agent_config_id', 'name', 'type', 'description'];

    // Collection types (matching QMD defaults):
    // 'identity'  → identity/ folder documents (IDENTITY.md, SOUL.md, etc.)
    // 'memory'    → memory/ folder documents (MEMORY.md, daily logs)
    // 'sessions'  → indexed session transcripts
    // 'custom'    → user-defined document sets

    public function agentConfiguration(): BelongsTo
    {
        return $this->belongsTo(AgentConfiguration::class);
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'memory_collection_documents');
    }

    public function scopeForAgent($query, string $agentConfigId)
    {
        return $query->where('agent_config_id', $agentConfigId);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
```

Default collections per agent (created automatically):

| Collection | Type | Contents |
|---|---|---|
| `identity` | identity | All 8 identity files (IDENTITY.md, SOUL.md, USER.md, etc.) |
| `memory-root` | memory | MEMORY.md long-term memory file |
| `memory-logs` | memory | memory/*.md daily log files |
| `sessions` | sessions | Indexed conversation transcripts |

### Integration with AgentDocumentService

The existing `AgentDocumentService` (`app/Services/AgentDocumentService.php`) already manages the document hierarchy. Extensions for QMD search:

```php
// New methods on AgentDocumentService
class AgentDocumentService
{
    // Existing: createAgentDocumentStructure(), createMemoryLog(), getIdentityFiles()

    /**
     * Create default memory collections when agent structure is set up
     */
    public function createDefaultCollections(User $agent, string $agentConfigId): void
    {
        $identityDocs = $this->getIdentityFiles($agent);
        $memoryRoot = $this->getIdentityFile($agent, 'MEMORY');

        // Create 'identity' collection with all identity docs
        $identityCollection = MemoryCollection::create([
            'agent_config_id' => $agentConfigId,
            'name' => 'identity',
            'type' => 'identity',
        ]);
        $identityCollection->documents()->attach($identityDocs->pluck('id'));

        // Create 'memory-root' collection
        if ($memoryRoot) {
            $memoryRootCollection = MemoryCollection::create([
                'agent_config_id' => $agentConfigId,
                'name' => 'memory-root',
                'type' => 'memory',
            ]);
            $memoryRootCollection->documents()->attach($memoryRoot->id);
        }

        // 'memory-logs' and 'sessions' created empty, populated as docs are added
        MemoryCollection::create([
            'agent_config_id' => $agentConfigId,
            'name' => 'memory-logs',
            'type' => 'memory',
        ]);

        MemoryCollection::create([
            'agent_config_id' => $agentConfigId,
            'name' => 'sessions',
            'type' => 'sessions',
        ]);
    }

    /**
     * Get all searchable documents for an agent (across all collections)
     */
    public function getMemoryDocuments(User $agent): Collection
    {
        // Returns identity files + memory logs + session transcripts
    }
}
```

### Hybrid Search on Document Chunks

The core search service combines pgvector cosine similarity with PostgreSQL full-text search:

```php
class HybridDocumentSearch
{
    public function __construct(
        private EmbeddingCacheService $embeddings,
    ) {}

    public function search(
        string $agentConfigId,
        string $query,
        int $maxResults = 6,
        float $minScore = 0.0,
        ?array $collectionNames = null,
    ): SearchResultSet {
        // 1. Generate query embedding via EmbeddingCacheService
        $queryEmbedding = $this->embeddings->getOrGenerate($query);

        // 2. Build base query scoped to agent
        $baseQuery = MemoryChunk::where('agent_config_id', $agentConfigId);

        // 3. Optional collection filtering
        if ($collectionNames) {
            $collectionIds = MemoryCollection::forAgent($agentConfigId)
                ->whereIn('name', $collectionNames)
                ->pluck('id');

            $documentIds = DB::table('memory_collection_documents')
                ->whereIn('collection_id', $collectionIds)
                ->pluck('document_id');

            $baseQuery->whereIn('document_id', $documentIds);
        }

        // 4. Vector search: cosine similarity via pgvector
        $vectorResults = (clone $baseQuery)
            ->selectRaw("*, 1 - (embedding <=> ?) as vector_score", [$queryEmbedding])
            ->orderByRaw("embedding <=> ?", [$queryEmbedding])
            ->limit($maxResults * 2) // Over-fetch for merging
            ->get();

        // 5. FTS search: PostgreSQL full-text ranking
        $tsQuery = DB::raw("plainto_tsquery('english', ?)");
        $ftsResults = (clone $baseQuery)
            ->selectRaw("*, ts_rank(to_tsvector('english', text), plainto_tsquery('english', ?)) as text_score", [$query])
            ->whereRaw("to_tsvector('english', text) @@ plainto_tsquery('english', ?)", [$query])
            ->orderByRaw("ts_rank(to_tsvector('english', text), plainto_tsquery('english', ?)) DESC", [$query])
            ->limit($maxResults * 2)
            ->get();

        // 6. Merge scores: 0.7 * vector + 0.3 * text
        $merged = $this->mergeResults($vectorResults, $ftsResults, 0.7, 0.3);

        // 7. Clamp results
        return $this->clampResults($merged, $maxResults, $minScore);
    }

    private function clampResults(
        Collection $results,
        int $maxResults,
        float $minScore,
    ): SearchResultSet {
        $clamped = $results
            ->filter(fn ($r) => $r->score >= $minScore)
            ->take($maxResults);

        // Enforce maxInjectedChars (4000 total)
        $totalChars = 0;
        $final = $clamped->takeWhile(function ($result) use (&$totalChars) {
            $snippetChars = min(strlen($result->text), 700); // maxSnippetChars
            $totalChars += $snippetChars;
            return $totalChars <= 4000; // maxInjectedChars
        });

        return new SearchResultSet($final);
    }
}
```

### Memory Tools for Agents

```php
// RecallMemory tool — hybrid search across collections
class RecallMemory implements Tool
{
    public function description(): string
    {
        return 'Search your memory for relevant information. Returns semantically matched snippets with source citations.';
    }

    public function handle(Request $request): string
    {
        $scopeGuard = app(MemorySearchScopeGuard::class);
        if (!$scopeGuard->canSearch($this->agent, $this->chatContext)) {
            return 'Memory search is not available in this context.';
        }

        $results = app(HybridDocumentSearch::class)->search(
            agentConfigId: $this->agent->agentConfiguration->id,
            query: $request['query'],
            maxResults: $request['max_results'] ?? 6,
            collectionNames: $request['collections'] ?? null,
        );

        return $results->toJson(); // Includes citations
    }
}

// SaveMemory tool — writes to daily log via AgentDocumentService
class SaveMemory implements Tool
{
    public function description(): string
    {
        return 'Save a note to your persistent memory. Stored in today\'s daily log.';
    }

    public function handle(Request $request): string
    {
        $doc = app(AgentDocumentService::class)
            ->createMemoryLog($this->agent, $request['content']);

        // Trigger re-indexing of the updated document
        IndexAgentMemoryJob::dispatch($doc->id)->delay(15); // 15s debounce

        return "Memory saved to {$doc->title}";
    }
}
```

### Periodic Indexing via Queue Jobs

```php
// Index a single document (dispatched on create/update)
class IndexAgentMemoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $documentId) {}

    public function handle(ChunkingService $chunker, EmbeddingCacheService $embeddings): void
    {
        $document = Document::findOrFail($this->documentId);
        $existingHash = MemoryChunk::where('document_id', $document->id)
            ->value('content_hash');

        $newHash = hash('sha256', $document->content);
        if ($existingHash === $newHash) {
            return; // No changes, skip
        }

        // Re-chunk the document
        $chunks = $chunker->chunk($document->content, maxTokens: 400, overlap: 80);

        // Delete old chunks, insert new
        MemoryChunk::where('document_id', $document->id)->delete();

        foreach ($chunks as $chunk) {
            $embedding = $embeddings->getOrGenerate($chunk['text']);
            MemoryChunk::create([
                'agent_config_id' => $document->agentConfigId(),
                'document_id' => $document->id,
                'document_path' => $document->path(),
                'text' => $chunk['text'],
                'start_line' => $chunk['start_line'],
                'end_line' => $chunk['end_line'],
                'content_hash' => hash('sha256', $chunk['text']),
                'embedding' => $embedding,
            ]);
        }
    }
}

// Scheduled periodic re-index (every 5 minutes)
// In app/Console/Kernel.php:
$schedule->job(new PeriodicReindexJob)->everyFiveMinutes();
$schedule->job(new EmbeddingRefreshJob)->hourly();
```

### Session Transcript Indexing

```php
class ExportSessionTranscriptJob implements ShouldQueue
{
    public function __construct(public string $sessionId) {}

    public function handle(AgentDocumentService $docs): void
    {
        $session = AgentSession::with('messages')->findOrFail($this->sessionId);
        $agent = $session->agentConfiguration->user;

        // Convert messages to markdown
        $markdown = "# Session Transcript - {$session->created_at->format('Y-m-d H:i')}\n\n";
        foreach ($session->messages as $message) {
            $role = ucfirst($message->role);
            $markdown .= "**{$role}:** {$message->content}\n\n";
        }

        // Store as document in memory/ folder
        $doc = $docs->createMemoryLog($agent, $markdown);

        // Add to 'sessions' collection
        $sessionsCollection = MemoryCollection::forAgent($session->agent_config_id)
            ->where('name', 'sessions')
            ->first();
        $sessionsCollection?->documents()->attach($doc->id);

        // Trigger indexing
        IndexAgentMemoryJob::dispatch($doc->id);
    }
}
```

Triggered by:
- Session archival (daily reset, idle timeout, manual reset)
- Pre-compaction memory flush
- Manual export via API

### Scope Rules

```php
class MemorySearchScopeGuard
{
    /**
     * Determine if memory search is allowed in the current context.
     * DM-only by default (matching QMD's default scope rules).
     */
    public function canSearch(User $agent, ChatContext $context): bool
    {
        $settings = $agent->agentConfiguration?->settings;
        $scope = $settings?->memory_search_scope ?? 'dm_only';

        return match ($scope) {
            'dm_only' => $context->channel->type === 'dm',
            'all' => true,
            'none' => false,
            default => $context->channel->type === 'dm',
        };
    }
}
```

### Citation Support

Search results include source attribution for verification and navigation:

```php
class SearchResult
{
    public function __construct(
        public string $text,           // Chunk content (max 700 chars)
        public float $score,           // Combined hybrid score (0.0 - 1.0)
        public string $documentPath,   // e.g., "memory/2026-02-05.md"
        public ?string $documentId,    // UUID for navigation
        public ?int $startLine,        // Source line range start
        public ?int $endLine,          // Source line range end
        public string $source,         // "memory" | "sessions" | "identity"
    ) {}

    public function citation(): string
    {
        $citation = $this->documentPath;
        if ($this->startLine) {
            $citation .= "#L{$this->startLine}";
            if ($this->endLine && $this->endLine !== $this->startLine) {
                $citation .= "-L{$this->endLine}";
            }
        }
        return $citation;
    }
}
```

### Result Clamping Configuration

Matching QMD's proven defaults to prevent context bloat:

```php
// config/memory.php
return [
    'search' => [
        'max_results' => 6,             // Top-K results
        'max_snippet_chars' => 700,     // Per-result snippet limit
        'max_injected_chars' => 4000,   // Total context injection limit
        'timeout_ms' => 4000,           // Query timeout
        'vector_weight' => 0.7,         // Hybrid score: vector weight
        'text_weight' => 0.3,           // Hybrid score: text weight
    ],
    'indexing' => [
        'chunk_size' => 400,            // Tokens per chunk
        'chunk_overlap' => 80,          // Overlap between chunks
        'periodic_interval' => 5,       // Minutes between re-index
        'embedding_interval' => 60,     // Minutes between embedding refresh
        'debounce_seconds' => 15,       // Debounce for file change triggers
    ],
    'scope' => [
        'default' => 'dm_only',         // Default scope rule
    ],
];
```

---

## Agent Workspace Files Mapping

> How OpenClaw's file-based agent identity translates to OpenCompany's database-backed system.

### OpenClaw → OpenCompany Translation

OpenClaw stores agent identity and behavior as markdown files in a workspace directory. OpenCompany replaces this with structured database fields and the existing `AgentDocumentService`.

| OpenClaw File | Purpose | OpenCompany Field/Service |
|--------------|---------|--------------------------|
| `IDENTITY.md` | Name, emoji, avatar, vibe | `agent_configs.name`, `users.avatar`, `agent_configs.emoji` |
| `SOUL.md` | Personality, boundaries, core truths | `agent_configs.personality` (text field) |
| `AGENTS.md` | Operating instructions, memory guidelines | `agent_configs.instructions` (text field) |
| `TOOLS.md` | Tool notes, platform conventions | Auto-generated from registered capabilities |
| `USER.md` | User profile, timezone, preferences | Runtime injection from authenticated `User` model |
| `HEARTBEAT.md` | Periodic check-in checklist | `agent_configs.heartbeat_prompt` (text field) |
| `BOOTSTRAP.md` | One-time setup ritual | `BootstrapAgentJob` (runs once on agent creation) |
| `BOOT.md` | Gateway restart checklist | Not needed (Laravel handles app lifecycle) |
| `MEMORY.md` | Curated long-term memory | `AgentDocumentService` memory folder documents |
| `memory/*.md` | Daily append-only logs | `AgentDocumentService::createMemoryLog()` |
| `skills/` | Workspace-specific skill overrides | Agent capability/tool registry in DB |

### System Prompt Assembly

OpenClaw injects workspace files in order: `IDENTITY → SOUL → USER → AGENTS → TOOLS → MEMORY → HEARTBEAT → BOOTSTRAP`. OpenCompany replicates this with `AgentPromptBuilder`:

```php
class AgentPromptBuilder
{
    public function build(AgentConfig $agent, User $user, Channel $channel): string
    {
        $sections = [];

        // 1. Identity (IDENTITY.md equivalent)
        $sections[] = "You are {$agent->name} ({$agent->emoji}).";

        // 2. Personality (SOUL.md equivalent)
        if ($agent->personality) {
            $sections[] = "## Personality\n{$agent->personality}";
        }

        // 3. User context (USER.md equivalent)
        $sections[] = "## User\nYou are working with {$user->name}.";

        // 4. Instructions (AGENTS.md equivalent)
        if ($agent->instructions) {
            $sections[] = "## Instructions\n{$agent->instructions}";
        }

        // 5. Tools (TOOLS.md equivalent — auto-generated)
        $tools = $this->getToolDocumentation($agent);
        if ($tools) {
            $sections[] = "## Available Tools\n{$tools}";
        }

        // 6. Memory (MEMORY.md + daily logs)
        $memory = $this->getRelevantMemory($agent, $channel);
        if ($memory) {
            $sections[] = "## Memory\n{$memory}";
        }

        // 7. Heartbeat context (only for heartbeat sessions)
        // 8. Bootstrap context (only for first run)

        return implode("\n\n---\n\n", $sections);
    }
}
```

### Sub-Agent Prompt Restrictions

When building prompts for sub-agents, only sections 1 (identity name only) and 4 (instructions) are included. Personality, user context, heartbeat, and bootstrap are excluded to keep sub-agents task-focused.

---

## Heartbeat System

> Periodic agent health checks adapted from OpenClaw's heartbeat runner.

### Architecture

OpenClaw uses a Node.js `setInterval` to wake agents periodically. OpenCompany replaces this with Laravel's Scheduler dispatching jobs per active agent.

```php
// app/Console/Kernel.php
$schedule->call(function () {
    AgentConfig::where('status', 'active')
        ->whereNotNull('heartbeat_prompt')
        ->where('heartbeat_enabled', true)
        ->each(function ($agent) {
            HeartbeatJob::dispatch($agent);
        });
})->everyThirtyMinutes();
```

### HeartbeatJob

```php
class HeartbeatJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private AgentConfig $agent
    ) {}

    public function handle(AiManager $ai): void
    {
        // Check active hours
        if (!$this->isWithinActiveHours()) {
            return;
        }

        // Build minimal prompt with heartbeat checklist
        $prompt = $this->agent->heartbeat_prompt;

        // Call AI provider
        $response = $ai->chat()
            ->withModel($this->agent->model)
            ->withSystemPrompt("You are {$this->agent->name}. Perform your heartbeat check.")
            ->send($prompt);

        $text = $response->text();

        // Skip posting if just an ack (nothing to report)
        if ($this->isAckOnly($text)) {
            return;
        }

        // Post to agent's primary channel
        $channel = $this->agent->user->channels()
            ->where('type', 'dm')
            ->first();

        if ($channel) {
            Message::create([
                'content' => $text,
                'author_id' => $this->agent->user_id,
                'channel_id' => $channel->id,
            ]);

            broadcast(new MessageSent($channel, $message));
        }
    }

    private function isAckOnly(string $text): bool
    {
        return str_contains(strtolower($text), 'heartbeat_ok')
            || strlen(trim($text)) < 30;
    }

    private function isWithinActiveHours(): bool
    {
        if (!$this->agent->heartbeat_active_start || !$this->agent->heartbeat_active_end) {
            return true;
        }

        $now = now()->setTimezone($this->agent->timezone ?? 'UTC');
        return $now->between(
            $now->copy()->setTimeFromTimeString($this->agent->heartbeat_active_start),
            $now->copy()->setTimeFromTimeString($this->agent->heartbeat_active_end)
        );
    }
}
```

### Configuration Fields

Add to `agent_configs` migration:

```php
$table->text('heartbeat_prompt')->nullable();
$table->boolean('heartbeat_enabled')->default(false);
$table->string('heartbeat_interval')->default('30m');
$table->string('heartbeat_active_start')->nullable(); // "09:00"
$table->string('heartbeat_active_end')->nullable();   // "18:00"
$table->string('heartbeat_timezone')->nullable();
```

---

## Agent Execution Loop

> The complete flow for running an agent in OpenCompany, adapted from OpenClaw's embedded runner.

### Overview

OpenClaw's agent execution is a 10-step async pipeline running in a Node.js gateway process. OpenCompany replaces this with Laravel queue jobs, the AI SDK, and Reverb WebSocket broadcasting.

### Execution Flow

```
1. Message arrives (WebSocket event or API POST)
      ↓
2. MessageController stores message, checks if agent is mentioned
      ↓
3. Dispatches ProcessAgentMessageJob to agent-specific queue
      ↓
4. Job loads: AgentConfig, channel context, identity documents, memory
      ↓
5. AgentPromptBuilder assembles system prompt (personality + instructions + tools + memory)
      ↓
6. Laravel AI SDK sends to provider: chat()->withTools($tools)->stream()
      ↓
7. Tool calls? → AgentToolExecutor handles registered tools, results fed back
      ↓
8. Response streamed via Reverb broadcast to connected clients
      ↓
9. Final response stored as Message in database
      ↓
10. Post-processing: memory indexing, compaction check, activity logging
```

### ProcessAgentMessageJob

```php
class ProcessAgentMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private Message $message,
        private AgentConfig $agent,
        private Channel $channel
    ) {
        $this->onQueue('agent-' . $agent->id);
    }

    public function handle(
        AgentPromptBuilder $promptBuilder,
        AgentToolExecutor $toolExecutor,
        AiManager $ai
    ): void {
        // 1. Build system prompt
        $systemPrompt = $promptBuilder->build(
            $this->agent,
            $this->message->author,
            $this->channel
        );

        // 2. Load conversation context
        $history = $this->loadConversationHistory();

        // 3. Send to AI provider with streaming
        $stream = $ai->chat()
            ->withModel($this->agent->model)
            ->withSystemPrompt($systemPrompt)
            ->withMessages($history)
            ->withTools($toolExecutor->getToolsFor($this->agent))
            ->stream();

        // 4. Process stream
        $fullResponse = '';
        foreach ($stream as $chunk) {
            if ($chunk->isToolCall()) {
                $result = $toolExecutor->execute($chunk->toolCall());
                // Feed result back to LLM (continue loop)
            } else {
                $fullResponse .= $chunk->text();
                // Broadcast partial response via Reverb
                broadcast(new AgentTyping($this->channel, $this->agent, $chunk->text()));
            }
        }

        // 5. Store final response
        $response = Message::create([
            'content' => $fullResponse,
            'author_id' => $this->agent->user_id,
            'channel_id' => $this->channel->id,
        ]);

        broadcast(new MessageSent($this->channel, $response));

        // 6. Post-processing
        $this->triggerMemoryIndexing($response);
        $this->checkCompactionThreshold();
    }
}
```

### Queue Architecture

| Queue | Purpose | Concurrency |
|-------|---------|-------------|
| `agent-{id}` | Per-agent message processing | 1 (serialized) |
| `agent-subagent` | Sub-agent task execution | Configurable (default: 8) |
| `agent-heartbeat` | Heartbeat runs | 1 per agent |

This mirrors OpenClaw's lane system: per-session serialization prevents race conditions, while sub-agent lanes allow parallel background work.

---

## Context Compaction

> Adapted from OpenClaw's multi-stage context management system.

### The Problem

LLMs have fixed context windows. As conversations grow, they eventually hit the limit. Without management, agents either fail or lose important context.

### OpenClaw's Three-Stage Approach

**Stage 1: Context Pruning (per-request, in-memory)**
- Only old `toolResult` messages are pruned
- User and assistant messages are never modified
- Last 3 assistant messages are always protected
- Pruning modes:
  - **Soft trim**: Keep first 1,500 chars + last 1,500 chars (max 4,000 chars total)
  - **Hard clear**: Replace with `[Old tool result content cleared]`
- TTL-based: tool results older than 5 minutes (configurable) are eligible

**Stage 2: Pre-Compaction Memory Flush (see next section)**
- Silent agentic turn to persist durable memories before compaction

**Stage 3: Auto-Compaction (triggered by threshold)**
- Triggered when `contextTokens > contextWindow - reserveTokens`
- Also triggered on context overflow error (compact → retry)
- Progressive summarization of older messages
- Keeps recent tokens intact (default: 20,000 tokens)
- Compaction count tracked per session

### Configuration

```php
// In AgentSettings model
'reserve_tokens' => 16384,           // Tokens reserved for compaction operations
'reserve_tokens_floor' => 20000,     // Minimum safety floor
'keep_recent_tokens' => 20000,       // Tokens to keep after compaction
'pruning_ttl_minutes' => 5,          // TTL before tool results are prunable
'soft_threshold_tokens' => 4000,     // Buffer before triggering memory flush
```

### OpenCompany Implementation

Context management is handled in `AgentRespondJob` via a `ContextWindowGuard` service:

```php
class ContextWindowGuard
{
    public function shouldTriggerCompaction(AgentSession $session): bool
    {
        $settings = $session->agentConfiguration->settings;
        $threshold = $session->max_tokens - $settings->reserve_tokens;

        return $session->token_count >= $threshold;
    }

    public function shouldPrune(AgentSession $session): bool
    {
        if (!$session->last_api_call_at) return false;

        $ttl = $session->agentConfiguration->settings->pruning_ttl_minutes;
        return $session->last_api_call_at->addMinutes($ttl)->isPast();
    }
}
```

---

## Pre-Compaction Memory Flush

> Directly adopted from OpenClaw's `memory-flush.ts` pattern.

### How It Works

Before compaction erases older context, a **silent agentic turn** runs to persist durable memories. This prevents "amnesia" — the agent saves important facts, decisions, and learnings before they're summarized away.

### Threshold Calculation

```
flushThreshold = contextWindow - reserveTokensFloor - softThresholdTokens
```

With defaults:
- Context window: 200,000 tokens (Claude)
- Reserve floor: 20,000 tokens
- Soft threshold: 4,000 tokens
- **Flush triggers at: 176,000 tokens**

### Flush Protocol

1. **Check threshold**: `totalTokens >= flushThreshold`
2. **Check dedup**: `memoryFlushCompactionCount !== compactionCount` (haven't flushed this cycle)
3. **Run silent turn**: Agent receives special prompt to persist memories
4. **NO_REPLY convention**: Response starts with `NO_REPLY` token — user sees nothing
5. **Update tracker**: Set `memoryFlushCompactionCount = compactionCount`

### System Prompt for Memory Flush

```
Pre-compaction memory flush.
Store durable memories now (use agent memory tools).
If nothing to store, reply with NO_REPLY.
```

### OpenCompany Implementation

```php
class MemoryFlushService
{
    public function shouldRunMemoryFlush(AgentSession $session): bool
    {
        $settings = $session->agentConfiguration->settings;
        $contextWindow = $session->max_tokens;
        $reserveFloor = $settings->reserve_tokens_floor;
        $softThreshold = $settings->soft_threshold_tokens;

        $threshold = max(0, $contextWindow - $reserveFloor - $softThreshold);

        if ($threshold <= 0 || $session->token_count < $threshold) {
            return false;
        }

        // Prevent duplicate flushes per compaction cycle
        return $session->memory_flush_compaction_count !== $session->compaction_count;
    }

    public function runMemoryFlush(AgentSession $session): void
    {
        $agent = OpenCompanyAgent::for($session->agentConfiguration->user);

        $response = $agent->prompt(
            'Pre-compaction memory flush. Store durable memories now. If nothing to store, reply with NO_REPLY.',
            systemPrompt: 'Pre-compaction memory flush turn. Capture durable memories to persistent storage. You may reply, but usually NO_REPLY is correct.',
        );

        // Update flush tracker
        $session->update([
            'memory_flush_at' => now(),
            'memory_flush_compaction_count' => $session->compaction_count,
        ]);
    }
}
```

---

## Enhanced Subagent Patterns

> Extends the subagent section with detailed lifecycle and queue management from OpenClaw.

### SubagentRegistry Lifecycle

OpenClaw's `SubagentRegistry` (429 lines) manages the full subagent lifecycle:

```
Spawn Request → Permission Check → Session Creation → Execute → Monitor → Announce → Cleanup
```

**Lifecycle stages:**

| Stage | Description |
|-------|-------------|
| **Spawn** | Validate permissions, create isolated session key `agent:{id}:subagent:{uuid}` |
| **Execute** | Run in dedicated lane (`AGENT_LANE_SUBAGENT`), track via `SubagentRunRecord` |
| **Monitor** | Heartbeat system with `HEARTBEAT_OK` token for long-running tasks |
| **Announce** | Queue results for delivery to parent with stats (runtime, tokens, cost) |
| **Cleanup** | Auto-archive after timeout (default 60 minutes), or immediate on `cleanup: "delete"` |

### Queue Modes

When a subagent completes, results can be delivered to the parent in different modes:

| Mode | Behavior |
|------|----------|
| **followup** | Default. Queue result as next message to parent |
| **steer** | Inject into parent's active run (interrupts current processing) |
| **collect** | Batch results until parent explicitly requests them |
| **interrupt** | Immediately interrupt parent and deliver result |

### Concurrency Control

- Max concurrent subagents: 8 (configurable per agent)
- Per-session lanes prevent message interleaving
- Global lanes for system-wide concurrency limits
- Subagents cannot spawn other subagents (single level of nesting)

### Heartbeat System

For long-running subagent tasks, OpenClaw uses a heartbeat mechanism:
- Agent periodically emits `HEARTBEAT_OK` token
- Parent monitors heartbeat to detect stuck agents
- Timeout triggers automatic cancellation and error announcement

### Tool Policy for Subagents

Subagents are excluded from session management tools by default:
- No `sessions_list` (can't see other sessions)
- No `sessions_history` (can't read transcripts)
- No `sessions_send` (can't message other sessions)
- No `sessions_spawn` (can't spawn further subagents)

### OpenCompany Implementation

```php
// SubagentRegistry service
class SubagentRegistry
{
    const MAX_CONCURRENT = 8;

    public function spawn(User $parent, User $child, string $task, array $options = []): SubagentRun
    {
        // Check permissions
        $permission = SubagentSpawnPermission::where('parent_agent_id', $parent->id)->first();
        if (!$permission || !$permission->canSpawn($child->id)) {
            throw new UnauthorizedSpawnException();
        }

        // Check concurrency
        $activeRuns = SubagentRun::where('parent_agent_id', $parent->id)
            ->where('status', 'running')
            ->count();

        if ($activeRuns >= ($permission->max_concurrent ?? self::MAX_CONCURRENT)) {
            throw new MaxConcurrencyException();
        }

        // Create run record
        $run = SubagentRun::create([
            'parent_agent_id' => $parent->id,
            'child_agent_id' => $child->id,
            'task_description' => $task,
            'label' => $options['label'] ?? null,
            'status' => 'running',
            'runtime_config' => $options,
        ]);

        // Dispatch execution job
        ExecuteSubagentJob::dispatch($run)
            ->onQueue('subagents')
            ->timeout($options['timeout'] ?? 300);

        return $run;
    }

    public function announce(SubagentRun $run): void
    {
        $run->update([
            'status' => 'success',
            'completed_at' => now(),
        ]);

        // Deliver result to parent based on queue mode
        $mode = $run->runtime_config['queue_mode'] ?? 'followup';

        match ($mode) {
            'followup' => $this->queueFollowup($run),
            'steer' => $this->steerParent($run),
            'collect' => $this->collectResult($run),
            'interrupt' => $this->interruptParent($run),
        };
    }
}
```

---

## Agent Execution Pipeline

> Detailed execution flow based on OpenClaw's agent runtime.

### Full Pipeline

```
User Message
    ↓
Session Resolution (find or create session for this conversation)
    ↓
Context Loading (load conversation history from messages table)
    ↓
Context Pruning (trim old tool results if TTL expired)
    ↓
Memory Flush Check (save durable memories if near compaction threshold)
    ↓
Model Selection (resolve provider + model from DynamicProviderResolver)
    ↓
Agent Execution (run prompt with tools via Laravel AI SDK)
    ↓
  ├── Tool Calls → Security Check → Execute/Request Approval
  ├── Streaming → Broadcast to Reverb channel
  └── Heartbeat (for long-running tasks)
    ↓
Response Handling
  ├── NO_REPLY → Silent message (hidden from user)
  └── Normal → Save message + broadcast to channel
    ↓
Post-Execution
  ├── Update token counts
  ├── Update session activity timestamp
  └── Check compaction threshold
```

### Model Fallback Chain

The `DynamicProviderResolver` supports automatic failover:

```php
// Agent.brain = "anthropic:claude-sonnet-4-20250514"
// If Anthropic fails, try OpenAI, then Gemini
$config = $resolver->resolveForAgent($agentUser);

// Built-in SDK failover
$response = $agent->prompt(
    $message,
    provider: [$config['provider'], 'openai', 'gemini'], // Failover chain
    model: $config['model'],
);
```

### Tool Security (Three-Tier Model)

Adopted from OpenClaw's tool security architecture:

| Tier | Mode | Behavior |
|------|------|----------|
| 1 | **deny** | Block all tool execution. Agent can only generate text. |
| 2 | **allowlist** | Only pre-approved tools/commands execute. Others prompt for approval. |
| 3 | **full** | All tools execute without approval. Use for trusted agents only. |

### ExecAsk Modes

Controls when to prompt the user for approval:

| Mode | Behavior |
|------|----------|
| **off** | Never ask — execute based on security tier only |
| **on-miss** | Ask when tool/command is not in the allowlist (default) |
| **always** | Always ask before executing any tool |

### Safe Bins (Auto-Approved)

Common read-only tools that bypass approval in allowlist mode:

```php
private array $safeBins = [
    'jq', 'grep', 'cut', 'sort', 'uniq', 'head', 'tail', 'tr', 'wc',
    'cat', 'ls', 'find', 'which', 'echo', 'date', 'env', 'pwd',
];
```

Configurable via `auto_allow_skills` setting (default: true).

---

## Links & Resources

### Laravel AI SDK
- [Official Documentation](https://laravel.com/docs/12.x/ai-sdk)
- [Laravel AI Package](https://github.com/laravel/ai)

### Laravel MCP
- [Official Documentation](https://laravel.com/docs/12.x/mcp)
- [Laravel MCP Package](https://github.com/laravel/mcp)

### Laravel Boost (MCP Server for Development)
- [Official Documentation](https://laravel.com/docs/12.x/boost)

### Related Packages (Already Installed)
- [Laravel Reverb](https://laravel.com/docs/12.x/reverb) - WebSocket broadcasting
- [Laravel Sanctum](https://laravel.com/docs/12.x/sanctum) - API token authentication
