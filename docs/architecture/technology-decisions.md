# Technology Decisions: AI Framework & Orchestration

> Decision document for Olympus AI agent system technology stack

---

## Executive Summary

| Component | Choice | Reason |
|-----------|--------|--------|
| **AI Framework** | **Prism** | Laravel-native, better DX, stronger ecosystem |
| **Orchestration** | **Laravel Workflow** | No external infra, familiar patterns, good enough for MVP |
| **Future Option** | Temporal | Upgrade path when scale demands it |

---

## AI Framework Comparison: Neuron AI vs Prism

### Feature Matrix

| Feature | Neuron AI | Prism |
|---------|-----------|-------|
| **Laravel Integration** | Good (separate package) | Excellent (feels native) |
| **API Design** | Class-based agents | Fluent builder pattern |
| **LLM Providers** | 10+ (Anthropic, OpenAI, Gemini, Ollama, etc.) | 10+ (same coverage) |
| **Tool/Function Calling** | Schema-based with ToolProperty | Fluent Tool::as() builder |
| **Memory/Context** | Built-in ChatHistory with context window management | Via messages + Converse package |
| **RAG Support** | Built-in RAG base class | Via extensions |
| **MCP Support** | Built-in MCP connectors | Via Prism Relay package |
| **Multi-Agent** | Supported via composition | PrismAgents package |
| **Streaming** | Supported | Excellent (SSE, WebSocket) |
| **Structured Output** | Supported | Excellent (schema system) |
| **Testing** | Basic | Comprehensive (mocking, assertions) |
| **Documentation** | Good | Excellent |
| **Community** | Growing | Strong Laravel community |
| **Maintenance** | Active | Very active (Laravel team adjacent) |

---

### Neuron AI Strengths

1. **Built-in Chat History Management**
   - Automatic context window optimization
   - Eloquent-backed persistence out of box
   - Prevents context overflow errors

2. **RAG as First-Class Citizen**
   - Extend `RAG` base class
   - Built-in vector store support
   - Document loading utilities

3. **Agent-First Design**
   ```php
   class DataAnalystAgent extends Agent {
       protected function provider(): AIProviderInterface { ... }
       protected function instructions(): string { ... }
       protected function tools(): array { ... }
   }
   ```

4. **MCP Integration Built-in**
   - Direct MCP server connections
   - Tool filtering with `exclude()` / `only()`

---

### Prism Strengths

1. **Laravel-Native Feel**
   - Fluent API matches Laravel conventions
   - Featured on official Laravel blog
   - Feels like a first-party package

2. **Superior Developer Experience**
   ```php
   Prism::text()
       ->using(Provider::Anthropic, 'claude-sonnet-4-20250514')
       ->withSystemPrompt("You are an agent")
       ->withTools([$weatherTool])
       ->withMaxSteps(5)
       ->asText()
   ```

3. **Comprehensive Streaming**
   - `asStream()` for chunk iteration
   - `asEventStreamResponse()` for SSE
   - WebSocket broadcasting support

4. **Structured Output Excellence**
   - Schema system (Object, Array, Enum, etc.)
   - Provider-specific strict validation
   - Type-safe response handling

5. **Better Testing Story**
   - Response faking
   - Detailed assertion helpers
   - Mock tool calls

6. **Ecosystem Packages**
   - **PrismAgents**: Multi-agent orchestration with guardrails
   - **Prism Relay**: MCP integration
   - **Converse Prism**: Conversation persistence

---

### Decision: **Prism**

**Why Prism over Neuron AI for Olympus:**

1. **Laravel Alignment**: Prism follows Laravel conventions exactly - your team already knows the patterns

2. **Ecosystem Strength**: Converse Prism for persistence, PrismAgents for multi-agent, Prism Relay for MCP - all work together

3. **Testing**: Comprehensive testing utilities critical for production agent systems

4. **Streaming**: Better streaming support for real-time chat UI you already have

5. **Community**: Stronger Laravel community backing, more likely to receive updates

6. **Flexibility**: Can build agents with tools OR use PrismAgents for more complex orchestration

**Neuron AI would be better if:**
- You needed built-in RAG immediately
- Context window management was critical from day one
- You preferred class-based agent definitions

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

**Why Laravel Workflow for Olympus:**

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
│              Olympus Frontend               │
│         (Vue 3 + Inertia.js)                │
└─────────────────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────┐
│           Laravel Backend                   │
│  ┌─────────────────────────────────────┐    │
│  │            Prism                    │    │
│  │  - LLM Integration (Anthropic, etc) │    │
│  │  - Tool/Function Calling            │    │
│  │  - Streaming Responses              │    │
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
# Prism (AI/LLM integration)
composer require prism-php/prism

# Laravel Workflow (orchestration)
composer require laravel-workflow/laravel-workflow

# Optional: Prism Agents (if needed for complex multi-agent)
composer require grpaiva/prism-agents

# Optional: Converse Prism (conversation persistence)
composer require elliottlawson/converse-prism
```

---

## How They Work Together

```php
// 1. Define Agent Tools using Prism
$analysisTool = Tool::as('analyze_data')
    ->for('Analyze provided data and return insights')
    ->withStringParameter('data', 'The data to analyze')
    ->using(fn($data) => $this->analysisService->analyze($data));

// 2. Create Agent execution as Workflow Activity
class ExecuteAgentActivity extends Activity
{
    public function execute(AgentConfig $config, string $prompt): AgentResult
    {
        return Prism::text()
            ->using(Provider::Anthropic, 'claude-sonnet-4-20250514')
            ->withSystemPrompt($config->personality . "\n" . $config->instructions)
            ->withTools($config->getEnabledTools())
            ->withMaxSteps(5)
            ->withPrompt($prompt)
            ->asText();
    }
}

// 3. Orchestrate with Laravel Workflow
class AgentTaskWorkflow extends Workflow
{
    public function execute(AgentTask $task)
    {
        $config = yield Activity::make(FetchAgentConfig::class, $task->agentId);
        $result = yield Activity::make(ExecuteAgentActivity::class, $config, $task->prompt);

        if ($result->requiresApproval) {
            yield Activity::make(CreateApprovalRequest::class, $result);
            // Workflow pauses - resumes when approval webhook fires
        }

        return $result;
    }
}
```

---

## Links & Resources

### Prism
- [Prism Official Website](https://prismphp.com/)
- [Prism GitHub](https://github.com/prism-php/prism)
- [Laravel Blog - Prism](https://laravel.com/blog/prism-makes-ai-feel-laravel-native-the-artisan-of-the-day-is-tj-miller)
- [PrismAgents](https://github.com/grpaiva/prism-agents)
- [Prism Relay (MCP)](https://github.com/prism-php/relay)
- [Converse Prism](https://github.com/elliottlawson/converse-prism)

### Laravel Workflow
- [Laravel Workflow GitHub](https://github.com/laravel-workflow/laravel-workflow)
- [Laravel Workflow Documentation](https://laravel-workflow.com/docs/introduction/)
- [Waterline UI](https://github.com/laravel-workflow/waterline)

### Neuron AI (Alternative)
- [Neuron AI Docs](https://docs.neuron-ai.dev)
- [Neuron AI GitHub](https://github.com/neuron-core/neuron-ai)

### Temporal (Future Upgrade)
- [Temporal.io](https://temporal.io/)
- [Laravel Temporal](https://github.com/keepsuit/laravel-temporal)
