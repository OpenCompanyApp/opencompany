# Memory & Learning Systems Research

Goal: agents that learn from experience, get better at their work over time, and function like a human organization — developing expertise, sharing knowledge, and improving collectively.

---

## Current System

OpenCompany has a two-tier memory architecture:

| Layer | What | How |
|-------|------|-----|
| **STM** | Conversation history | Auto-compacted at ~75% context, summary stored in `conversation_summaries` |
| **LTM Core** | `MEMORY.md` | Always in system prompt, agent-editable via `save_memory(target: "core")` |
| **LTM Logs** | Daily logs (`YYYY-MM-DD.md`) | On-demand via `recall_memory`, timestamped entries with category tags |
| **Retrieval** | Hybrid search | pgvector cosine (70%) + tsvector FTS (30%), merged via RRF |
| **Flush** | Pre-compaction promotion | Silent agent turn to `save_memory` before STM is compressed |
| **Reranking** | Post-retrieval scoring | Ollama Qwen3-Reranker or LLM-based pointwise relevance |

Key services: `EmbeddingService`, `DocumentIndexingService`, `ChunkingService`, `ConversationCompactionService`, `MemoryFlushService`, `RerankingService`, `MemoryScopeGuard`.

### What's missing

**Memory gaps:**
1. No temporal decay (old and new memories weighted equally)
2. No consolidation (daily logs grow unbounded, never summarized)
3. No contradiction resolution (conflicting facts coexist silently)
4. No cross-agent shared memory (each agent is siloed)
5. No entity/relationship tracking
6. No importance scoring

**Learning gaps:**
7. No feedback capture (approvals, corrections, user edits are not stored)
8. No procedural memory (agents don't learn multi-step workflows)
9. No self-reflection (agents don't evaluate their own output quality)
10. No skill distillation (repeated successful patterns aren't codified)
11. No performance tracking (no metrics on whether an agent is improving)
12. No organizational learning (agents don't learn from each other)

---

## Part 1: Memory Storage Systems

### Graph-Based Memory

Entities (nodes) + relationships (edges). Multi-hop reasoning.

- **Zep/Graphiti** — Temporal knowledge graph on Neo4j. Bi-temporal: tracks when facts were learned vs. when valid. 94.8% DMR benchmark.
- **MAGMA** — Four orthogonal graphs (semantic, temporal, causal, entity). 45.5% higher reasoning, 95% fewer tokens.

Strengths: relationship queries, contradiction detection. Weaknesses: requires graph DB, entity resolution is hard.

### Hierarchical Memory

Working memory (context) + episodic (experiences) + semantic (facts) + procedural (skills).

- **MIRIX** — Six memory types. 85.4% LOCOMO.
- **CoALA** — Reference architecture from Stanford.

Strengths: mirrors cognition, natural separation. Weaknesses: complex, classification costs LLM calls.

### Memory Agents

Dedicated agent manages what to store/retrieve/organize.

- **Letta/MemGPT** (29k stars) — Context as OS RAM. Core memory (mutable prompt), archival (disk), recall (history). Agent pages info in/out.
- **A-MEM** (NeurIPS 2025) — Zettelkasten-inspired. New memories link to existing, can trigger updates to old memories.
- **Mastra Observational** — Observer compresses, Reflector synthesizes. No vector DB. 94.87% LongMemEval, 10x cost cut.

### Temporal Memory

- **Park et al.** — `score = recency_decay + importance_rating + semantic_relevance`
- **Graphiti** — Four timestamps per edge, old facts invalidated but preserved.

### Structured Memory

- **Hindsight** (91.4% LongMemEval) — Four networks: World (facts), Bank (experiences), Opinion (beliefs with confidence), Observation (summaries). Separates evidence from inference.
- **Zep Entity Types** — Custom schemas per domain.

### Hybrid (Mem0-style)

- **Mem0** (41k stars, $24M) — Extract candidates from conversation → compare against existing → ADD/UPDATE/DELETE/NOOP. Graph variant adds entity extraction. 26% improvement over OpenAI memory.

---

## Part 2: Self-Learning Systems

### Reflexion & Self-Critique

Agents evaluate their own outputs, store reflections, and improve on retry.

- **Reflexion** (Shinn et al.) — Actor generates, Evaluator scores, Self-Reflection produces verbal critique stored as episodic memory. Performance jumps 73% → 93% on ALFWorld.
- **Self-Refine** — Generate → critique → revise cycle until convergence.
- **Multi-Agent Reflexion (MAR)** — Agents critique each other's reasoning.

**Applicability to OC:** After completing a task, an agent runs a self-critique step against known criteria (task requirements, user preferences from memory). The reflection is stored as episodic memory for future similar tasks. Low cost (one extra LLM call), high impact.

### Procedural Memory (Learning Workflows)

Agents store and retrieve multi-step task execution patterns.

- **LEGOMem** (Microsoft, AAMAS 2026) — Decomposes trajectories into full-task memories (plans/reasoning) and subtask memories (tool interactions). Even small models benefit substantially. Orchestrator memory is critical for task decomposition.
- **Memp** — Distills trajectories into step-by-step instructions AND higher-level script abstractions. Success rates improve steadily as the repository is refined.
- **PRAXIS** — Stores consequences of actions indexed by (env state, task state, action, result). Retrieves by matching current state against stored states.
- **AgentRR (Record & Replay)** — Record trace → Summarize into experience → Replay for similar future tasks. Multi-level abstraction: low-level (precise action sequences) and high-level (generalized procedures).

**Applicability to OC:** When an agent runs a weekly report for the 10th time, each execution trajectory is stored. Successful steps become subtask memories. The overall flow becomes a full-task memory. On run #11, the agent retrieves procedural memories and executes with higher accuracy. Failed steps generate "lessons from failure."

### Dynamic Context Evolution (Learning Without Fine-Tuning)

These are the most directly implementable — they improve agent behavior by evolving the prompt/context, not the model weights.

- **SCOPE** — Frames context as an optimization problem. Dual-stream: tactical guidelines (immediate, task-specific) and strategic guidelines (persist across tasks, require confidence ≥ 0.85). HLE benchmark: 14.23% → 38.64%.
- **Dynamic Cheatsheet** (Suzgun et al.) — Agent maintains a persistent notebook of strategies/snippets during inference. Self-curated, focused on transferable insights. Claude 3.5 Sonnet accuracy doubled on AIME. GPT-4o Game of 24: 10% → 99%.
- **EvolveR** — Two phases: (1) offline distill trajectories into strategic principles, (2) online retrieve principles for new tasks. Each principle carries `score = (successes + 1) / (uses + 2)`. Low-scoring principles are pruned.
- **GEPA** (ICLR 2026 Oral) — Reflective prompt evolution outperforms RL. Reads full execution traces to diagnose failures. Outperforms GRPO by 6-20% with 35x fewer rollouts.

**Applicability to OC:** This is the core mechanism. Agents maintain evolving guidelines in their identity files (MEMORY.md or a new GUIDELINES.md). Tactical rules for specific tasks, strategic principles for general behavior. Each guideline carries an effectiveness score. Periodic review prunes underperforming guidelines. No model fine-tuning needed — just better prompts over time.

### Feedback Loops

How agents incorporate user corrections and approvals.

- **PAHF** (Personalized Agents from Human Feedback) — Three-step: pre-action clarification, preference-grounded action, post-action feedback integration. Dual feedback channels learn substantially faster than single-channel.
- **RLUF** (Reinforcement Learning from User Feedback) — Framework for implicit binary feedback in production (e.g., emoji reactions as satisfaction proxies).
- **ICML 2025** — Contents of user feedback (what they wanted changed), not just polarity (happy/unhappy), improve performance. Direct edits to agent output are the highest-signal feedback.

**Practical signals in OC:**
- User re-doing what agent did → strong negative signal
- User editing agent output → preference correction (capture the diff)
- User accepting without changes → positive signal
- Time-to-accept → confidence proxy (immediate = strong positive)
- Thumbs up/down → explicit binary
- Approval workflow approve/reject → direct training signal
- Follow-up corrections in natural language → richest signal

### Skill Distillation

Turning repeated successful patterns into reusable skills.

- **Voyager** — First LLM-powered lifelong learning agent. Automatic curriculum (proposes increasingly complex tasks) + skill library (ever-growing executable code). Skills transfer to new environments. 15.3x faster milestones.
- **SkillRL** (2026) — Experience-based distillation: successful trajectories → strategic patterns, failed → "lessons from failure." Hierarchical SkillBank: general skills + task-specific skills. Skills co-evolve with the agent via RL.
- **Anthropic Agent Skills** — Open standard. Skills are instruction packages agents can discover and load dynamically. Future: agents create, edit, and evaluate skills themselves.

**Applicability to OC:** This connects directly to the planned skill system (`$skill-name`). The loop: agent performs a task repeatedly → recognizes a pattern → creates a skill via `manage_skill` tool → skill becomes available to other agents. The `$create-skill` builtin teaches agents how to codify their own patterns.

---

## Part 3: Organizational Learning

### Cross-Agent Knowledge Sharing

- **Collaborative Memory** (ICML 2025) — Two bipartite graphs (user↔agent, agent↔resource) with access control. Private + shared memory tiers. Each memory fragment carries immutable provenance (who created, which agent, what resources). 61% resource reduction while maintaining >90% accuracy.
- **SiriuS** (NeurIPS 2025) — Shared experience library. Successful dialogues logged, failed ones repaired. All agents fine-tune on shared library — one agent's discovery becomes available to all.
- **LEGOMem** — Orchestrator memory benefits all task agents it coordinates.

### Apprenticeship / Mentoring

- **Multi-Agent Evolve (MAE)** — Three roles from one LLM: Proposer (creates challenges), Solver (attempts), Judge (evaluates). Closed-loop curriculum. Outperforms supervised fine-tuning with ground-truth answers.
- **Selective social learning** — Agents compute whether another agent's approach would benefit them under their own preferences. Don't blindly copy — evaluate context fit first.

### Performance Tracking

- **Goal completion rate** — Did the agent accomplish what was asked?
- **Tool usage efficiency** — Steps taken vs. minimum necessary
- **Human approval rate** — How often humans accept agent output
- **Improvement over time** — Are metrics trending up?
- **EvolveR metric score** — `s(p) = (success_count + 1) / (usage_count + 2)` per learned guideline

### Generative Agents (Simulated Organizations)

- **Park et al.** (Stanford) — 25 agents in simulated town. Three emergent behaviors: information spreading, relationship formation, spontaneous cooperation. Architecture: memory stream + reflection + planning.
- **AgentSociety** (2025) — Up to 10,000 agents, 500 interactions/day each. Reproduced real social phenomena (polarization, policy effects).

Key insight: agents with memory, reflection, and planning develop emergent collaborative behaviors without being explicitly programmed to do so. Organizational culture and institutional knowledge emerge from bottom-up interactions.

### Real Products Building "AI Workforces"

| Product | Learning Mechanism | Key Metric |
|---------|-------------------|------------|
| **Devin** (Cognition) | RL + playbooks, fleet learns in parallel | PR merge: 34% → 67% YoY |
| **Cursor 2.0** | MoE model trained via RL in real codebases | Learns to run tests, fix linters |
| **OpenHands** | Critic model with TD learning + rejection fine-tuning | SOTA on SWE-Bench |
| **Letta** | Self-editing memory, sleep-time compute | Agent file (.af) for stateful agents |
| **CrewAI** | Composite scoring (similarity 0.5 + recency 0.3 + importance 0.2), auto-extraction, dedup | LanceDB backend |
| **Lindy AI** | Learns from feedback/examples over time | 30+ hour autonomous tasks |
| **11x (Alice)** | Multi-agent SDR, adapts messaging in real-time | Reply rates match human SDRs |

---

## Recommendations for OpenCompany

Organized into three tiers: memory improvements, learning mechanisms, and organizational systems.

### Tier 1: Memory Improvements

#### 1a. Temporal Decay on Retrieval (LOW effort)

Modify `DocumentIndexingService::search()` to weight results by recency: `score = semantic_score * (0.995 ^ hours_since_creation)`. Drop-in change to existing RRF scoring.

#### 1b. Memory Consolidation Job (MEDIUM effort)

Scheduled `ConsolidateMemoryJob` runs weekly per agent. Reads logs older than 7 days, extracts key facts via LLM, writes to `CONSOLIDATED.md`, archives raw logs. Bounds growth, improves retrieval quality.

#### 1c. Mem0-Style Auto-Extract (MEDIUM effort)

Post-response hook in `AgentRespondJob`. Lightweight LLM call extracts candidate facts → compares against existing memories → ADD/UPDATE/DELETE/NOOP. Handles contradiction resolution. Replaces reliance on agent explicitly calling `save_memory`.

#### 1d. Cross-Agent Shared Memory (LOW effort)

Add `workspace` collection to `DocumentChunk`. New `save_memory(target: "shared")`. `recall_memory` searches both agent-specific and workspace collections.

### Tier 2: Learning Mechanisms

#### 2a. Feedback Capture & Integration (MEDIUM effort)

Capture signals from existing workflows:
- Approval approve/reject → store as preference in agent memory
- User edits to agent output → capture diff, store as correction
- Task completion/failure → store outcome with context

New `feedback` category in daily logs. Agent retrieves relevant feedback before similar tasks. Over time, agents learn what users want without being told repeatedly.

#### 2b. Evolving Guidelines (MEDIUM effort)

Add a `GUIDELINES.md` identity file per agent (alongside MEMORY.md). Two tiers:
- **Tactical** — Task-specific rules: "When user asks for a report, always include date range"
- **Strategic** — Cross-task principles: "Always confirm assumptions before irreversible actions"

Each guideline carries an effectiveness score (EvolveR pattern): `(successes + 1) / (uses + 2)`. Periodic review prunes low-scoring guidelines. Agents can add guidelines via a new `save_guideline` tool or automatically during self-reflection.

#### 2c. Post-Task Self-Reflection (LOW effort)

After completing a task, agent runs a brief self-critique:
- Did I accomplish the goal?
- What worked well?
- What would I do differently?
- Store reflection as episodic memory

Triggered automatically for tasks (not every message). One extra LLM call per task. Reflections are retrievable by future similar tasks via `recall_memory`.

#### 2d. Procedural Memory (HIGH effort)

Record task execution trajectories:
- Task type + context → steps taken → tools used → outcome
- Successful sequences become retrievable procedures
- Failed sequences become "lessons from failure"

New `procedures` collection in `DocumentChunk`. Before executing a task, agent searches for relevant procedures. LEGOMem-style: full-task plans + subtask memories.

### Tier 3: Organizational Systems

#### 3a. Workspace Knowledge Base (MEDIUM effort)

A shared memory tier accessible to all agents in a workspace:
- Workspace procedures ("how we do things here")
- Client/project context
- Cross-agent decisions

Provenance tracking: who stored it, when, which agent contributed. Permission-respecting: uses existing `AgentPermissionService` patterns.

#### 3b. Agent Performance Metrics (MEDIUM effort)

Track per agent over time:
- Task completion rate
- Average task duration
- Human approval rate (from approval system)
- Guideline effectiveness scores
- Memory retrieval hit rate

Stored in a new `agent_metrics` table. Surfaced in the agent detail page. Used to identify which agents need attention and which are improving.

#### 3c. Skill Auto-Creation (HIGH effort, builds on skill system)

When an agent completes the same type of task successfully N times:
1. Detect the pattern (same task type, similar steps)
2. Distill into a skill template
3. Propose the skill to the user/admin for approval
4. Published skill becomes available to all agents

Connects the procedural memory system (#2d) to the skill system (`$skill-name`). The loop: experience → procedure → skill → shared capability.

#### 3d. Knowledge Transfer on Agent Creation (LOW effort)

When a new agent joins a workspace, automatically:
1. Load workspace shared memory into its context
2. Copy relevant guidelines from similar-role agents
3. Provide access to the workspace skill library

New agents start with organizational knowledge instead of from zero.

---

## Implementation Roadmap

### Phase 1: Foundation
- Temporal decay on retrieval (1a)
- Feedback capture (2a)
- Post-task self-reflection (2c)
- Cross-agent shared memory (1d)

### Phase 2: Learning Loop
- Evolving guidelines with effectiveness scores (2b)
- Memory consolidation job (1b)
- Mem0-style auto-extract (1c)
- Agent performance metrics (3b)

### Phase 3: Organizational Intelligence
- Procedural memory (2d)
- Workspace knowledge base (3a)
- Knowledge transfer on agent creation (3d)
- Skill auto-creation (3c)

---

## The Learning Loop (Summary)

```
Agent performs task
    │
    ├── Feedback captured (approval, edits, corrections)
    ├── Self-reflection stored (what worked, what didn't)
    ├── Facts auto-extracted (Mem0-style ADD/UPDATE/DELETE)
    │
    ▼
Memory consolidation (weekly)
    │
    ├── Raw logs → distilled facts
    ├── Reflections → updated guidelines (with scores)
    ├── Procedures → refined workflows
    │
    ▼
Next similar task
    │
    ├── Retrieve relevant guidelines (scored)
    ├── Retrieve procedural memory
    ├── Retrieve past feedback for similar work
    │
    ▼
Agent performs task better
    │
    ├── If pattern repeats N times → propose as skill
    ├── If knowledge is general → share to workspace
    └── Cycle continues
```

---

## Key Files

| File | Role |
|------|------|
| `app/Services/Memory/DocumentIndexingService.php` | Hybrid search — add temporal decay, importance scoring |
| `app/Services/Memory/MemoryFlushService.php` | Pre-compaction flush — extend with auto-extraction |
| `app/Services/Memory/ConversationCompactionService.php` | STM management |
| `app/Services/AgentDocumentService.php` | Memory logs — add shared memory, guidelines |
| `app/Agents/Tools/Memory/SaveMemory.php` | Save tool — add shared target, guidelines |
| `app/Agents/Tools/Memory/RecallMemory.php` | Recall tool — surface temporal/importance signals |
| `app/Jobs/AgentRespondJob.php` | Post-response hook for auto-extraction, feedback capture |
| `app/Agents/OpenCompanyAgent.php` | System prompt — add guidelines section, procedural memory |
| `config/memory.php` | Configuration for all subsystems |

## Sources

**Memory Systems:**
- [Mem0](https://mem0.ai/) — Hybrid vector+graph, $24M raised
- [Letta/MemGPT](https://github.com/letta-ai/letta) — Self-editing context window
- [Zep/Graphiti](https://github.com/getzep/graphiti) — Temporal knowledge graph
- [Hindsight](https://github.com/vectorize-io/hindsight) — 91.4% LongMemEval
- [A-MEM](https://github.com/agiresearch/A-mem) — NeurIPS 2025, Zettelkasten-inspired
- [MAGMA](https://arxiv.org/abs/2601.03236) — Multi-graph, 95% token reduction
- [Mastra Observational Memory](https://venturebeat.com/data/observational-memory-cuts-ai-agent-costs-10x-and-outscores-rag-on-long) — 10x cost reduction
- [Memory in the Age of AI Agents (survey)](https://arxiv.org/abs/2512.13564)
- [Collaborative Memory (ICML 2025)](https://arxiv.org/abs/2505.18279)

**Self-Learning:**
- [Reflexion](https://arxiv.org/abs/2303.11366) — Verbal reinforcement learning
- [Stanford CS329A: Self-Improving AI Agents](https://cs329a.stanford.edu/)
- [Self-Evolving AI Agents (survey)](https://arxiv.org/abs/2508.07407)
- [Yohei Nakajima: Building Self-Improving AI Agents](https://yoheinakajima.com/better-ways-to-build-self-improving-ai-agents/)
- [SCOPE](https://arxiv.org/abs/2512.15374) — Dual-stream context optimization
- [Dynamic Cheatsheet](https://arxiv.org/abs/2504.07952) — Test-time learning with adaptive memory
- [EvolveR](https://arxiv.org/abs/2510.16079) — Experience-driven lifecycle with scored principles
- [GEPA (ICLR 2026 Oral)](https://arxiv.org/abs/2507.19457) — Reflective prompt evolution
- [PAHF](https://arxiv.org/abs/2602.16173) — Personalized agents from human feedback

**Procedural Memory:**
- [LEGOMem (AAMAS 2026)](https://arxiv.org/abs/2510.04851) — Modular procedural memory for multi-agent
- [Memp](https://arxiv.org/abs/2508.06433) — Task-agnostic procedural memory
- [PRAXIS](https://arxiv.org/html/2511.22074v2) — Real-time procedural learning
- [AgentRR](https://arxiv.org/abs/2505.17716) — Record & replay

**Skill Distillation:**
- [Voyager](https://arxiv.org/abs/2305.16291) — Lifelong learning with skill library
- [SkillRL](https://arxiv.org/abs/2602.08234) — Recursive skill evolution
- [Anthropic Agent Skills](https://www.anthropic.com/engineering/equipping-agents-for-the-real-world-with-agent-skills)

**Organizational Learning:**
- [Park et al. Generative Agents](https://dl.acm.org/doi/fullHtml/10.1145/3586183.3606763)
- [Multi-Agent Evolve (MAE)](https://arxiv.org/abs/2510.23595)
- [SiriuS (NeurIPS 2025)](https://arxiv.org/abs/2512.02731) — Shared experience library
- [Agent-as-a-Judge](https://arxiv.org/html/2508.02994v1)
- [Devin 2025 Performance Review](https://cognition.ai/blog/devin-annual-performance-review-2025)
- [CrewAI Memory](https://docs.crewai.com/en/concepts/memory)
