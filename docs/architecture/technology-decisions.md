# Technology Decisions: AI Framework & Orchestration

> Decision document for OpenCompany AI agent system technology stack

---

## Executive Summary

| Component | Choice | Reason |
|-----------|--------|--------|
| **AI Framework** | **Laravel AI SDK (`laravel/ai`)** | Official first-party Laravel package, full multimodal, comprehensive testing |

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
│  │       Laravel Queues + Jobs         │    │
│  │  - Async Agent Execution            │    │
│  │  - Approval Processing              │    │
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
```

---

## Links & Resources

### Laravel AI SDK
- [Laravel AI SDK Docs](https://laravel.com/docs/12.x/ai-sdk)
- [Laravel MCP Docs](https://laravel.com/docs/12.x/mcp)
- [Laravel Boost Docs](https://laravel.com/docs/12.x/boost)
- [OpenCompany AI SDK Strategy](./laravel-ai-sdk.md)
