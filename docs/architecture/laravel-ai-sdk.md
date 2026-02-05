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
