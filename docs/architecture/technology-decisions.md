# Technology Decisions: AI Framework & Orchestration

> Decision document for OpenCompany AI agent system technology stack

Status: Updated 2026-11-02 with the locked Product Direction decisions (Code Mode runtime, agent
computers, API-first with Inertia frozen, policy model, and pillar 9 connections, events and
routines).

---

## Executive Summary

| Component | Choice | Reason |
|-----------|--------|--------|
| **Agent SDK** | **Laravel AI SDK (`laravel/ai`)** | Official first-party Laravel package, class-based agents, tool contracts, testing |
| **LLM Provider Layer** | **OpenCompany AI Runtime + Laravel AI SDK** | App-owned provider catalog, workspace credential resolution, embeddings, gateway access, usage/cost ledger |

> **Update (May 2026):** OpenCompany owns the provider runtime in `app/Domain/Ai`. `laravel/ai` remains the agent/provider execution framework, while provider catalog metadata, workspace credentials, Codex auth, embeddings, OpenAI-compatible gateway access, and usage/cost accounting are app-owned.

> **Update (2026-11-02, locked [Product Direction](../PRODUCT_DIRECTION.md)).** The decisions
> below extend or replace parts of this stack. They are direction, not implementation claims; see
> [Current State](../CURRENT_STATE.md) for what exists.
>
> | Area | Decision | Status |
> |------|----------|--------|
> | **Code Mode runtime** | mruby is the only Code Mode (in-process scripting) runtime (pillar 3). Lua and the QuickJS branch are retired. | Phase 0 work in progress in the working tree; see [mruby-only Code Mode](../planning/mruby-only-code-mode-implementation.md) |
> | **Agent computers** | Computers come from providers through a `ComputerProvider` contract (create, start, stop, snapshot, fork, exec, files, browser, screen stream, ports), shaped on Bowerbird's Workspace API `v1alpha` (pillar 2). Provider order: local Docker/Firecracker, boat, E2B/Daytona, Bowerbird sandboxes, bring-your-own SSH. OpenCompany does **not** run a VM fleet itself. Computer access is per agent; VFS-only agents are first-class, and the VFS stays the governed path to company data even when a computer exists (pillar 3). | Planned, Phase 2; see [Computer Provider Contract](computer-provider-contract.md) |
> | **API-first, Inertia frozen** | API-first now (pillar 4): a versioned public API (OpenAPI), token/OAuth auth for native apps, a realtime stream (WebSocket/SSE) and push, shared by web, mobile and desktop clients. **Inertia is frozen: no new Inertia pages or page props.** Wayfinder stays the routing stack for existing pages; Wayfinder versus a generated OpenAPI client for new clients, the web client's repository and the mobile/desktop stack are open. | Decided 2026-11-02; messenger client is Phase 1. See [API-First Client Split](api-first-client-split.md) and [Wayfinder Routing](wayfinder-routing.md) |
> | **Policy model** | Agent behaviour modes (autonomous/supervised/strict) and the autonomous default are deleted. One policy engine with three presets (Personal, Team, Company) that set defaults only; switching keeps explicit grants. Actions are **gated by reversibility, not by "write"**: reversible change sets, trash and drafts mostly auto-apply, while external sends, spend, exposure and purges are gated per preset. Always-on invariants include the hard-deny floor, on-behalf-of access and "an agent never approves anything". Scoped exceptions (once, this run, this task, time-limited, always) match exactly; a per-agent autonomy slider writes grants (pillar 5). | Policy model Phase 0; roles, approver policies and budgets Phase 3. See [Governance Model](governance-model.md) |
> | **Workspace event log** | One durable, append-only workspace event log with a standard envelope (type, source, subject, actor, time, idempotency key, untrusted payload, causality chain and depth) and a replayable dead-letter queue. It replaces scattered per-feature event tables over time and powers audit, replay, the activity feed and budget accounting (pillar 9). | Phase 0 schema; activity feed Phase 1. See [Connections, Events and Routines](../planning/connections-events-routines.md) |
> | **Connections** | Tier 1 deep connectors (storage mounts, mailboxes, calendars) are app-owned under `app/Domain/Connections/`. Tier 2 is generic: MCP, API manifests, SKILL.md skills and plugin import, signed inbound webhooks, optional managed connectors attached as MCP (never a hard dependency). One generic OAuth broker; secrets in the vault behind the credential broker (pillar 9). | Connections model Phase 0; Google Drive Phase 1 |
> | **MCP client** | The MCP client is rebuilt on `laravel/mcp` (MCP 2026-07-28, OAuth 2.1), with pinned tool definitions that need re-approval when they change and server hints treated as hints only. This replaces the current app-owned client in `app/Services/Mcp`. | Phase 1 |
> | **Integration packages** | The integration package system is deleted: the ~600 PHP packages, `integration-core`, the bundle metapackage, the catalog, package OAuth controllers, `IntegrationSetting` forms and `create-integration` scaffolding. The `integrations` repository is archived read-only. | Phase 0 nuclear cleanup |
> | **Routines, no flow builder** | Automations (prompt and script), `RunDueAutomations`, `ListAutomationRule`, `HasTriggers`/`IntegrationWebhook` and hardcoded cron jobs become Routines (agent, script in mruby, hybrid), Triggers and Events, executed as runs on the existing control plane. **There is no visual flow builder.** | Routines from chat Phase 1; script/hybrid Phase 2 |

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
- Provider failover capability when a caller supplies a failover chain (`provider: ['anthropic', 'openai']`)
- Comprehensive testing: `Agent::fake()`, `assertPrompted()`, `preventStrayPrompts()`
- MCP client/server behavior is app-owned today; `laravel/mcp` is not installed in `composer.json` (the direction rebuilds the MCP client on `laravel/mcp`; see the 2026-11-02 update)
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
| MCP Support | App-owned MCP client/runtime in `app/Services/Mcp` today; planned rebuild on `laravel/mcp` |
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
│   (Vue 3 + Inertia.js today; planned:       │
│    web SPA, mobile, desktop on the API)     │
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

# MCP runtime is currently app-owned; no laravel/mcp package is installed.
# Planned (Phase 1): rebuild the MCP client on laravel/mcp.
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
