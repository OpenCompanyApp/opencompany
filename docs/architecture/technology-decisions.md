# Technology Decisions: AI Framework & Orchestration

> Decision document for OpenCompany AI agent system technology stack

---

## Executive Summary

| Component | Choice | Reason |
|-----------|--------|--------|
| **AI Framework** | **Laravel AI SDK (`laravel/ai`)** | Official first-party Laravel package, full multimodal, comprehensive testing |
| **Orchestration** | **Laravel Workflow** | No external infra, familiar patterns, good enough for MVP |
| **Future Option** | Temporal | Upgrade path when scale demands it |

---

## AI Framework: Laravel AI SDK

The official first-party Laravel AI SDK (`laravel/ai`) provides a unified API for interacting with AI providers.

**Why Laravel AI SDK:**
- Official first-party Laravel package (same team as Sanctum, Reverb, Cashier)
- Full multimodal: text, images, audio, TTS, STT, embeddings, reranking, vector stores
- Class-based agents with contracts, traits, and PHP attributes
- Artisan generators: `make:agent`, `make:tool`
- Built-in conversation persistence via `RemembersConversations` trait
- Native streaming + broadcasting (`->stream()`, `->broadcastOnQueue()`)
- Queue support (`->queue()`)
- Provider failover (`provider: ['anthropic', 'openai']`)
- Comprehensive testing: `Agent::fake()`, `assertPrompted()`, `preventStrayPrompts()`
- MCP companion package (`laravel/mcp`)
- Providers: OpenAI, Anthropic, Gemini, Groq, xAI, Cohere, Jina, ElevenLabs

**Feature Matrix:**
| Feature | Laravel AI SDK |
|---------|---------------|
| Laravel Integration | Official first-party |
| API Design | Class-based agents with contracts + attributes |
| LLM Providers | 8+ (OpenAI, Anthropic, Gemini, Groq, xAI, Cohere, Jina, ElevenLabs) |
| Tool/Function Calling | `Tool` contract with `JsonSchema` |
| Conversation Persistence | Built-in `RemembersConversations` trait |
| RAG Support | Built-in `SimilaritySearch` tool + pgvector integration |
| MCP Support | Official `laravel/mcp` companion package |
| Streaming | `->stream()`, SSE, Vercel AI protocol, WebSocket broadcasting |
| Structured Output | `HasStructuredOutput` contract with `JsonSchema` |
| Testing | Comprehensive fakes + assertions per feature type |
| Image Generation | `Image::of()` with OpenAI, Gemini, xAI |
| Audio/TTS | `Audio::of()` with OpenAI, ElevenLabs |
| Transcription/STT | `Transcription::from*()` with OpenAI, ElevenLabs |
| Embeddings | `Embeddings::for()` with caching + pgvector |
| Reranking | `Reranking::of()` with Cohere, Jina |
| File Management | `Files\Document`, `Files\Image` with cloud storage |
| Vector Stores | `Stores::create()` for document collections |

See [Laravel AI SDK Strategy](./laravel-ai-sdk.md) for full integration details.

---

## Orchestration: Laravel Workflow vs Temporal

### Feature Matrix

| Feature | Laravel Workflow | Temporal |
|---------|------------------|----------|
| **Infrastructure** | Uses Laravel queues | External cluster required |
| **State Persistence** | Database + events | Event sourcing + snapshots |
| **Long-Running** | Hours to days | Hours to years |
| **Parallel Execution** | Concurrent workers | Child workflows + activities |
| **Retries** | Laravel retry mechanisms | Built-in policies |
| **Observability** | Waterline UI | Temporal UI + APIs |
| **Learning Curve** | Low | Moderate |
| **Scalability** | Thousands | Millions |
| **Cost** | Just queue/DB | Infrastructure + licensing |
| **Setup Complexity** | Minimal | Significant |

---

### Decision: **Laravel Workflow** (Start Here)

**Why Laravel Workflow for OpenCompany:**

1. **No External Infrastructure**: Uses existing Laravel queues - you already have this

2. **Faster to Production**: Can implement orchestration patterns immediately

3. **Familiar Patterns**: Generator-based yields feel natural to PHP developers

4. **Good Enough for MVP**: Handles thousands of concurrent workflows

5. **Migration Path**: Patterns translate to Temporal if you outgrow it

**When to Upgrade to Temporal:**
- Need to orchestrate across multiple services (not just Laravel)
- Require enterprise-grade durability (years-long workflows)
- Scale to millions of concurrent agents
- Need Temporal's advanced observability

---

## Recommended Stack Architecture

```
┌─────────────────────────────────────────────┐
│            OpenCompany Frontend             │
│         (Vue 3 + Inertia.js)                │
└─────────────────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────┐
│           Laravel Backend                   │
│  ┌─────────────────────────────────────┐    │
│  │        Laravel AI SDK              │    │
│  │  - LLM Integration (all providers) │    │
│  │  - Tool/Function Calling           │    │
│  │  - Streaming + Broadcasting        │    │
│  │  - Embeddings + Vector Search      │    │
│  │  - Image, Audio, Transcription     │    │
│  └─────────────────────────────────────┘    │
│  ┌─────────────────────────────────────┐    │
│  │       Laravel Workflow              │    │
│  │  - Agent Task Orchestration         │    │
│  │  - Approval Workflows               │    │
│  │  - Retry & Recovery                 │    │
│  └─────────────────────────────────────┘    │
│  ┌─────────────────────────────────────┐    │
│  │       Agent System (Custom)         │    │
│  │  - Agent Configuration (DB)         │    │
│  │  - Memory/Sessions (DB)             │    │
│  │  - Capabilities Management          │    │
│  └─────────────────────────────────────┘    │
└─────────────────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────┐
│            PostgreSQL                       │
│  - Agent configurations                     │
│  - Sessions & memories                      │
│  - Workflow state                           │
│  - Chat history                             │
└─────────────────────────────────────────────┘
```

---

## Installation Commands

```bash
# Laravel AI SDK (official first-party)
composer require laravel/ai

# Laravel MCP (expose app as MCP server)
composer require laravel/mcp

# Laravel Workflow (orchestration)
composer require laravel-workflow/laravel-workflow

# Optional: Waterline UI for workflow monitoring
composer require laravel-workflow/waterline
```

---

## How They Work Together

```php
// 1. Define Agent Tools using Laravel AI SDK
class AnalyzeDataTool implements Tool
{
    public function description(): string
    {
        return 'Analyze provided data and return insights';
    }

    public function handle(Request $request): string
    {
        return app(AnalysisService::class)->analyze($request['data']);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'data' => $schema->string()->description('The data to analyze')->required(),
        ];
    }
}

// 2. Agent execution uses OpenCompanyAgent
class ExecuteAgentTask implements ShouldQueue
{
    public function handle(DynamicProviderResolver $resolver): void
    {
        $config = $resolver->resolveForAgent($this->task->agent);
        $agent = OpenCompanyAgent::for($this->task->agent);

        $response = $agent->prompt(
            $this->task->description,
            provider: $config['provider'],
            model: $config['model'],
        );

        $this->task->complete(['response' => (string) $response]);
    }
}

// 3. Orchestrate with Laravel Workflow
class AgentTaskWorkflow extends Workflow
{
    public function execute(AgentTask $task)
    {
        $config = yield Activity::make(FetchAgentConfig::class, $task->agentId);

        // Dispatch agent execution via SDK
        ExecuteAgentTask::dispatch($task)->onQueue('agents');

        if ($result->requiresApproval) {
            yield Activity::make(CreateApprovalRequest::class, $result);
        }

        return $result;
    }
}
```

---

## Links & Resources

### Laravel AI SDK
- [Laravel AI SDK Docs](https://laravel.com/docs/12.x/ai-sdk)
- [Laravel MCP Docs](https://laravel.com/docs/12.x/mcp)
- [Laravel Boost Docs](https://laravel.com/docs/12.x/boost)
- [OpenCompany AI SDK Strategy](./laravel-ai-sdk.md)

### Laravel Workflow
- [Laravel Workflow GitHub](https://github.com/laravel-workflow/laravel-workflow)
- [Laravel Workflow Documentation](https://laravel-workflow.com/docs/introduction/)
- [Waterline UI](https://github.com/laravel-workflow/waterline)

### Temporal (Future Upgrade)
- [Temporal.io](https://temporal.io/)
- [Laravel Temporal](https://github.com/keepsuit/laravel-temporal)
