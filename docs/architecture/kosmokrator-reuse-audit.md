# KosmoKrator Reuse Audit

> Full list of what OpenCompany should reuse, adapt, or avoid from the `kosmokrator` repo.
> Scope: compare `/Users/rutger/Sites/opencompany` against `/Users/rutger/Sites/kosmokrator` and identify practical reuse opportunities.

---

## Executive Summary

OpenCompany and KosmoKrator already share the correct low-level foundation:

- `prism-php/prism`
- `opencompanyapp/prism-relay`
- `opencompany/prism-codex`
- `opencompanyapp/integration-core`

That means the main reuse opportunity is **agent runtime infrastructure**, not UI or shell code.

The strongest reusable areas from KosmoKrator are:

1. Provider and model cataloging
2. Context management and prompt budgeting
3. Tool result deduplication and output truncation
4. Typed settings schema
5. Skill loading and project-local instruction patterns
6. Subagent orchestration concepts

The weakest reuse areas are:

- Symfony TUI and ANSI renderer code
- CLI-specific shell and filesystem tools
- Local desktop install and self-update flows
- Terminal-specific permission UX

---

## Repo Shape

### OpenCompany

- Product type: Laravel multi-tenant web app
- Main concerns: workspaces, channels, tasks, documents, approvals, integrations, agent collaboration
- Shared agent tool files: `158` files under `app/Agents/Tools`
- Built-in provider classes: `15`
- Test files: `79`

Key files:

- `composer.json`
- `app/Agents/OpenCompanyAgent.php`
- `app/Agents/Tools/ToolRegistry.php`
- `app/Services/Memory/ConversationCompactionService.php`
- `app/Services/Memory/ModelContextRegistry.php`
- `app/Agents/Tools/Agents/ContactAgent.php`

### KosmoKrator

- Product type: local CLI coding agent
- Main concerns: code editing, terminal UX, local permissions, session persistence, tool execution, subagent swarms
- Tool files: `44` files under `src/Tool`
- Total PHP source files: much larger runtime core than OpenCompany's agent layer
- Test files: `196`

Key files:

- `composer.json`
- `src/Agent/ContextManager.php`
- `src/Agent/SubagentOrchestrator.php`
- `src/Settings/SettingsSchema.php`
- `src/LLM/ProviderCatalog.php`
- `src/LLM/PromptFrameBuilder.php`
- `src/Agent/ToolResultDeduplicator.php`
- `src/Agent/OutputTruncator.php`
- `src/Skill/SkillLoader.php`

---

## Shared Foundation Already In Place

These are already shared and should remain the main cross-repo seam.

### 1. Prism / relay / integration core

Both repos depend on:

- `prism-php/prism`
- `opencompanyapp/prism-relay`
- `opencompany/prism-codex`
- `opencompanyapp/integration-core`

Why this matters:

- LLM provider support should converge here, not inside either app
- Tool contracts should converge here, not inside Laravel-only or Symfony-only abstractions
- Model metadata should become shared here

Current issue:

- OpenCompany still keeps provider metadata in `config/integrations.php`
- KosmoKrator keeps richer model/provider metadata in `config/models.yaml`, `config/prism.yaml`, and `src/LLM/ProviderCatalog.php`

Recommendation:

- Move provider and model metadata ownership into `prism-relay`
- Make both repos consume the same metadata source

Priority: `P0`

---

## Reuse Directly Or With Minimal Extraction

These are the best candidates to port first.

### 2. Prompt frame splitting for cacheable system prompts

Source:

- `src/LLM/PromptFrameBuilder.php`

Why reuse:

- OpenCompany already builds prompt sections in `app/Agents/OpenCompanyAgent.php`
- KosmoKrator already splits stable prompt prefix from volatile task content
- This should reduce token waste and improve prompt cache hit rates

OpenCompany fit:

- Add a web-safe version of `PromptFrameBuilder`
- Split static identity/instructions from volatile sections such as current task, channel context, and recent runtime state

Priority: `P0`

Reuse level: `Direct logic port`

---

### 3. Tool result deduplication

Source:

- `src/Agent/ToolResultDeduplicator.php`

What it does:

- Replaces stale or repeated tool outputs with short placeholders
- Handles exact duplicates
- Handles stale `file_read` results after edits
- Handles `grep` results later superseded by `file_read`

Why reuse:

- OpenCompany agent context can accumulate repeated tool output
- This is pure context hygiene with low product risk

OpenCompany fit:

- Adapt the deduper for Laravel AI message/value object types
- Run it before building the final message set sent to the model
- Apply it to OpenCompany's document, task, file, and search style tools where outputs repeat

Priority: `P0`

Reuse level: `Adapted port`

---

### 4. Output truncation with persisted full result

Source:

- `src/Agent/OutputTruncator.php`

What it does:

- Caps large tool output by line and byte count
- Saves the full result to disk
- Keeps only a concise truncated version in the model context

Why reuse:

- OpenCompany has many tools that can return oversized payloads
- This prevents context bloat from tables, documents, search results, raw external API payloads, and generated content

OpenCompany fit:

- Replace disk persistence with DB or object-storage backed persistence
- Keep the same policy: short preview in prompt, full result stored elsewhere
- Expose a retrieval path for the agent if it needs to inspect the full output later

Priority: `P0`

Reuse level: `Adapt architecture, not storage implementation`

---

### 5. Provider catalog and richer provider selection layer

Source:

- `src/LLM/ProviderCatalog.php`
- `config/models.yaml`
- `config/prism.yaml`

What it does well:

- Provider labels, descriptions, auth modes, ordering
- Provider and model option generation
- Free-text model support for selected providers
- Pulls metadata from relay registry instead of hardcoding everything locally

Why reuse:

- OpenCompany's provider config is flatter and more static
- Model metadata is split across config and memory-specific lookup logic
- KosmoKrator has a better abstraction for presenting providers and model capabilities

OpenCompany fit:

- Use the same provider catalog pattern for the admin/provider settings UI
- Use shared metadata for model capabilities, pricing, context windows, and default models
- Eliminate duplicate "source of truth" between provider setup and memory budgeting

Priority: `P0`

Reuse level: `Extract to shared package or port pattern`

---

### 6. Settings schema pattern

Source:

- `src/Settings/SettingsSchema.php`

What it does:

- Central typed registry of settings
- Aliases, categories, labels, defaults, effect timing
- Clear separation between storage and schema

Why reuse:

- OpenCompany has many agent/runtime settings but no equally explicit typed schema layer
- A schema-driven settings system would simplify validation, admin UI generation, defaults, and API exposure

OpenCompany fit:

- Build an `AgentSettingsSchema` or `RuntimeSettingsSchema`
- Use it for workspace defaults, per-agent overrides, and feature flags
- Drive admin forms and validation from schema metadata

Priority: `P1`

Reuse level: `Pattern reuse with Laravel implementation`

---

## Pruning, Compaction, And Prompt Caching

This is the main runtime gap between OpenCompany and KosmoKrator.

OpenCompany currently has:

- summary-based conversation compaction in `app/Services/Memory/ConversationCompactionService.php`
- soft-zone memory flushing in `app/Services/Memory/MemoryFlushService.php`
- a local `ModelContextRegistry` for context-window lookup
- basic prompt splitting, checkpoint truncation, and read-tool deduplication

KosmoKrator adds a fuller context pipeline:

- `src/Agent/ContextManager.php` coordinates warning, pruning, compaction, and fallback behaviour
- `src/Agent/ContextPruner.php` does cheap micro-pruning before full compaction
- `src/Agent/ContextCompactor.php` builds a structured compaction plan and extracts durable memories
- `src/Agent/ContextBudget.php` centralises warning, auto-compact, and blocking thresholds
- `src/LLM/PromptFrameBuilder.php` is wired into `PrismService`

`prism-relay` already provides the cache-planning layer:

- `src/Relay.php`
- `src/Caching/PromptCachePlanner.php`
- `src/Caching/PromptCacheOrchestrator.php`
- `src/Meta/ProviderMeta.php`

### What OpenCompany should change now

#### 7. Replace `ModelContextRegistry` with relay-backed metadata

Current problem:

- OpenCompany keeps a separate context-window registry in `app/Services/Memory/ModelContextRegistry.php`
- `prism-relay` already knows model context windows via `ProviderMeta::contextWindow()`

Recommendation:

- Make `ModelContextRegistry` a thin adapter over `OpenCompany\\PrismRelay\\Meta\\ProviderMeta`
- Keep `AppSetting` overrides on top
- Remove most of the duplicated built-in model registry over time

Why:

- One source of truth for context windows
- Better alignment between provider selection, budgeting, and pricing/cache capability

Priority: `P0`

#### 8. Introduce a real context budget service

Current problem:

- OpenCompany repeats threshold math across `ConversationCompactionService`, `MemoryFlushService`, and `AgentRespondJob`
- The thresholds are estimated ad hoc instead of coming from one snapshot object

Recommendation:

- Add an OpenCompany `ContextBudget` service modeled after KosmoKrator's `src/Agent/ContextBudget.php`
- Use it for:
  - warning threshold
  - flush threshold
  - compaction threshold
  - hard blocking threshold
  - observability snapshots

Why:

- Consistent trigger behavior
- Easier tuning per model/provider
- Cleaner logs and debugging

Priority: `P0`

#### 9. Add micro-pruning before compaction

Current problem:

- OpenCompany jumps from "normal history" to full summarization
- It now truncates checkpointed tool results and deduplicates identical read results, but it still lacks a cheap middle step

Recommendation:

- Add an OpenCompany-specific `ContextPruner`
- Scope it to old, large, read-heavy tool results:
  - file reads
  - search results
  - thread/message reads
  - document fetches
  - table/list reads
- Protect recent turns and recent tool outputs
- Only accept a prune pass if the savings cross a minimum threshold

Do not port directly:

- KosmoKrator's `grep`, `glob`, `shell_read`, `bash` assumptions

Why:

- Reduces compaction frequency
- Saves tokens without paying an LLM summarization cost
- Fits OpenCompany's many structured read tools well

Priority: `P0`

#### 10. Make compaction more structured and failure-aware

Current problem:

- OpenCompany compaction is functional but simple: summarize older messages, store summary, continue
- It has no circuit breaker, no hard fallback path, and no structured compaction plan object

Recommendation:

- Keep the current `ConversationSummary` persistence model
- Add KosmoKrator-style concepts:
  - explicit compaction plan object
  - protected context
  - compaction failure counter / circuit breaker
  - hard fallback when compaction repeatedly fails
  - summary-to-memory extraction pass

Why:

- More resilient under long-running channels
- Better preservation of durable facts
- Less risk of repeated compaction thrashing

Priority: `P1`

#### 11. Wire prompt caching through `prism-relay`, not just prompt splitting

**Status: DONE** — Resolved via `CachingPrismGateway` in `prism-relay/src/Bridge/`.

OpenCompany now uses `CachingPrismGateway` (extends `PrismGateway`) for all AI SDK drivers. Before each `prompt()` call, a `SystemPromptBag` with split `[stable, volatile]` prompts is bound in the container. The gateway reads the bag and calls `Relay::planPromptCache()` to annotate system prompts and messages with provider-specific cache control (Anthropic ephemeral, Gemini dedicated, OpenAI auto, OpenRouter ephemeral). No `laravel/ai` vendor patches required.

Priority: `P0` — ~~resolved~~

### What OpenCompany should not copy directly

#### 12. Do not copy KosmoKrator's pruning rules literally

Skip direct ports of:

- `grep`
- `glob`
- `bash`
- `shell_read`
- filesystem-specific stale-read heuristics

Reason:

- OpenCompany is not a local coding shell
- Its equivalent high-volume context comes from workspace tools, not Unix tools

Priority: `P0`

### Recommended implementation order for this area

1. Replace context-window lookup with relay-backed metadata.
2. Introduce a shared `ContextBudget` service and move all threshold math there.
3. Add OpenCompany-specific micro-pruning for old read-tool outputs.
4. Improve compaction with a plan object, failure handling, and memory extraction.
5. Wire real provider prompt caching through `prism-relay` at the Laravel AI / Prism gateway layer.

### Summary judgment

For OpenCompany:

- `prism-relay` should become the source of truth for model context windows and prompt-cache planning
- KosmoKrator should inform the context-budget, pruning, and compaction architecture
- The exact pruning heuristics must be rewritten around OpenCompany's read tools and multi-channel/task workflow

---

## Reuse With Meaningful Adaptation

These are strong ideas, but they need web-native implementations.

### 7. Full context management pipeline

Source:

- `src/Agent/ContextManager.php`
- plus related classes such as `ContextBudget`, `ContextPruner`, `ProtectedContextBuilder`, `MemoryInjector`

What it does well:

- Pre-flight context pressure checks
- Micro-pruning before full compaction
- Compaction circuit breaker after repeated failures
- Protected runtime context
- Memory extraction from summaries
- Session-aware context shaping

Why reuse:

- OpenCompany's current compaction in `app/Services/Memory/ConversationCompactionService.php` is materially simpler
- KosmoKrator's pipeline is more resilient under pressure

What to port:

- Budget snapshots and preflight checks
- Compaction failure circuit breaker
- Protected context concept
- Post-compaction memory extraction
- Distinction between lightweight pruning and expensive compaction

What not to port as-is:

- TUI display calls
- local session memory assumptions
- CLI-specific project and directory context

Priority: `P1`

Reuse level: `Concept and core logic`

---

### 8. Protected context builder

Source:

- `src/Agent/ProtectedContextBuilder.php`

What it does:

- Injects runtime facts the model should always see and should not override

Why reuse:

- OpenCompany already assembles many system prompt sections, but not all runtime facts are clearly treated as protected
- This helps separate stable policy from mutable user/task context

OpenCompany fit:

- Protected facts could include:
  - workspace ID and name
  - channel ID and type
  - acting agent ID and role
  - approval mode
  - current task ID
  - current user visibility scope

Priority: `P1`

Reuse level: `Pattern reuse`

---

### 9. Session persistence ideas

Source:

- `src/Session/SessionManager.php`

What it does well:

- Central session facade
- Message persistence
- auto-title
- history reconstruction
- deduplication on load
- settings and memory scope coordination

Why reuse:

- OpenCompany already has first-class message/task/channel persistence, so it does not need the same storage model
- But it can reuse the patterns around resume, checkpointing, and reconstructed history hygiene

What to reuse:

- History reconstruction pass
- resume semantics
- checkpoint-aware continuation
- session-level metadata around compaction and recall

What not to reuse:

- project-path based scoping
- local SQLite storage assumptions

Priority: `P2`

Reuse level: `Patterns only`

---

### 10. Skill system

Source:

- `src/Skill/SkillLoader.php`

What it does well:

- Loads skills from multiple scopes
- Clear precedence rules
- Lightweight frontmatter-based format

Why reuse:

- OpenCompany currently has no real code-level skill system
- Workspace-local or agent-local skills could become a powerful product feature

Potential OpenCompany adaptation:

- Workspace skills
- Agent role packs
- Team playbooks
- Department-specific instructions
- Shared procedural knowledge in a structured format

Suggested storage options:

- database-backed skill records
- document-backed skills with frontmatter
- repo/project attached skills for external workspaces

Priority: `P2`

Reuse level: `Strong feature pattern, not direct file loader copy`

---

## Reuse The Concept, Not The Implementation

These should influence OpenCompany design, but should not be copied directly.

### 11. Subagent swarm orchestration

Source:

- `src/Agent/SubagentOrchestrator.php`

What it does well:

- dependency graphs
- concurrency limits
- sequential groups
- retries
- watchdog cancellation
- background result collection

Current OpenCompany state:

- OpenCompany uses agent-to-agent delegation via tasks and channels in `app/Agents/Tools/Agents/ContactAgent.php`
- This is valid for a multi-actor web platform, but less sophisticated as an orchestration runtime

What to reuse:

- dependency-aware delegation model
- grouped and sequenced sub-work
- per-agent concurrency limits
- watchdogs and stale-work detection
- richer run-state tracking

What not to reuse:

- Amp/Revolt future runtime
- in-process child-agent spawning model
- terminal lifecycle assumptions

OpenCompany-native implementation should use:

- Laravel queues
- tasks and task_steps
- events and broadcasts
- database-backed run graphs

Priority: `P1`

Reuse level: `Architecture model only`

---

### 12. Permission evaluation chain

Source:

- `src/Tool/Permission/PermissionEvaluator.php`

What it does well:

- explicit check chain
- fail-closed default
- stage-based policy composition

Current OpenCompany state:

- `app/Services/AgentPermissionService.php` is domain-aware and correct for workspaces, agents, folders, channels, and approvals
- But its structure is more app-specific and less composable than the staged evaluator pattern

What to reuse:

- explicit evaluation pipeline
- clear deny/ask/allow stages
- central decision object

What not to reuse:

- local file path and shell command rules
- Guardian/Argus/Prometheus model semantics

Priority: `P2`

Reuse level: `Pattern reuse`

---

### 13. Instruction discovery conventions

Source:

- `src/Agent/InstructionLoader.php`

What it does well:

- combines global, repo, and local instruction sources with defined precedence

Why it matters for OpenCompany:

- OpenCompany already has identity and instruction docs per agent
- The concept could extend to project imports, synced repos, or workspace knowledge packs

Good reuse targets:

- imported repository instruction files
- project-specific agent overlays
- workspace-level instruction inheritance

Priority: `P2`

Reuse level: `Concept only`

---

## Low Value Or No Value Reuse

These should stay in KosmoKrator.

### 14. TUI and ANSI renderer stack

Sources:

- `src/UI/Tui/*`
- `src/UI/Ansi/*`

Why not reuse:

- OpenCompany is a web app
- the abstractions are clean, but the actual code is terminal-specific

Possible exception:

- only reuse naming or state-machine ideas for live agent dashboards

Priority: `Skip`

---

### 15. CLI shell and file tools

Sources:

- `src/Tool/Coding/*`

Why not reuse:

- these are for local filesystem editing and shell execution inside a coding agent
- OpenCompany's tool surface is domain tools, integrations, documents, channels, tables, and tasks

Possible exception:

- isolated pieces of patch or diff handling if OpenCompany grows a coding workspace product

Priority: `Skip`

---

### 16. Desktop install, self-update, PHAR, binaries

Sources:

- `install.sh`
- CLI release flow
- PHAR and static binary distribution logic

Why not reuse:

- unrelated to OpenCompany's deployment model

Priority: `Skip`

---

### 17. Terminal-first permission UX

Sources:

- permission prompts and CLI interaction flows

Why not reuse:

- OpenCompany already has approvals, database-backed permission records, and human-in-the-loop flows
- the underlying policy concepts may help, but the UX should remain web-native

Priority: `Skip`

---

## Concrete Reuse List

This is the full list in one place.

### Reuse now

- Shared provider and model metadata ownership
- Prompt frame splitting for prompt cache efficiency
- Tool result deduplication
- Output truncation with persisted full payloads

### Reuse next

- Context budgeting and pre-flight checks
- Compaction circuit breaker
- Protected runtime context
- Typed settings schema
- Subagent orchestration design

### Reuse later

- Skill system
- Instruction source precedence model
- Session reconstruction and resume ideas
- Permission evaluator pipeline pattern

### Do not reuse directly

- Symfony TUI renderer
- ANSI terminal renderer
- local shell execution tools
- local filesystem coding tools
- PHAR and binary release tooling
- terminal approval UX

---

## Recommended Migration Order

### Phase 1

- Consolidate provider/model metadata into `prism-relay`
- Make OpenCompany consume shared provider and model definitions
- Port prompt-frame splitting into OpenCompany's agent pipeline

### Phase 2

- Add tool-result deduplication
- Add output truncation and persisted large-result storage
- Add protected context handling

### Phase 3

- Expand OpenCompany compaction into a full context management pipeline
- Add budget snapshots, micro-pruning, and failure circuit breaking
- Move toward schema-driven runtime settings

### Phase 4

- Design web-native subagent orchestration using KosmoKrator's swarm ideas
- Add richer dependency and concurrency controls on top of tasks and queues

### Phase 5

- Introduce skills for workspaces, teams, or agents
- Introduce instruction layering for imported projects or external code contexts

---

## Recommended First Phase We Can Start Soon

If the goal is to start shipping reuse work immediately with low risk, the first phase should be:

### Phase 1A: Prompt and Context Hygiene

Bring over these first:

- Prompt frame splitting from `src/LLM/PromptFrameBuilder.php`
- Tool result deduplication from `src/Agent/ToolResultDeduplicator.php`
- Output truncation from `src/Agent/OutputTruncator.php`

Why this should be first:

- small surface area
- no product UI changes required
- no queue architecture changes required
- immediate token and context efficiency wins
- low coupling to CLI-specific code

OpenCompany target areas:

- `app/Agents/OpenCompanyAgent.php`
- `app/Jobs/AgentRespondJob.php`
- `app/Services/Memory/*`

Expected outcome:

- smaller prompts
- fewer repeated tool payloads
- safer handling of oversized tool outputs
- lower model cost and fewer context-window failures

### Phase 1B: Model Metadata Consolidation

Start immediately after Phase 1A:

- move toward shared provider/model metadata ownership in `prism-relay`
- reduce duplication between:
  - `config/integrations.php`
  - `app/Services/Memory/ModelContextRegistry.php`
  - KosmoKrator's `src/LLM/ProviderCatalog.php`
  - KosmoKrator's `config/models.yaml`

Why this should be second:

- strategically important
- unlocks cleaner provider UI and runtime behavior in both repos
- but touches more shared infrastructure than prompt hygiene does

Expected outcome:

- one source of truth for context windows, pricing, auth mode, defaults, and capabilities
- simpler provider setup and model resolution in OpenCompany

### What Not To Include In First Phase

Do not include these in the first phase:

- subagent swarm orchestration
- permission system redesign
- skill system rollout
- session storage redesign
- TUI or CLI code

Why not:

- these are higher-risk and product-shaping changes
- they need architecture decisions, not just reuse work
- they will slow down the first useful delivery

### Suggested Deliverables For The First Phase

1. Add a prompt-splitting helper for OpenCompany system prompts
2. Add a tool-result dedupe pass before final LLM submission
3. Add large-output truncation plus persisted full-result storage
4. Add instrumentation around prompt size reduction and truncation frequency
5. Open a follow-up shared-infra task for provider/model metadata consolidation

### Concrete Recommendation

If we want the best first phase, start with:

1. `PromptFrameBuilder`
2. `ToolResultDeduplicator`
3. `OutputTruncator`

Then do provider/model catalog consolidation as the next phase.

This gives the fastest path to measurable gains without dragging us into a large refactor.

---

## What This Means In Practice

OpenCompany should treat KosmoKrator as the stronger source of truth for:

- agent runtime mechanics
- model metadata handling
- context management
- prompt hygiene
- subagent orchestration concepts

OpenCompany should **not** treat KosmoKrator as the source of truth for:

- UX
- storage model
- permissions UI
- shell and filesystem tool design

The right strategy is:

- extract shared infrastructure downward into shared packages
- port reusable runtime logic upward into OpenCompany
- leave terminal-specific product code behind
