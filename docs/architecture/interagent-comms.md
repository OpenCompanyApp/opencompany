# Inter-Agent Communication Protocol

## Overview

This document defines how agents communicate with each other in OpenCompany. The protocol introduces a single new tool — `contact_agent` — and leverages existing infrastructure (DM channels, `AgentRespondJob`, permissions) to enable three communication patterns:

| Pattern | Behavior | Use when... |
|---------|----------|-------------|
| **ask** | Synchronous — caller waits, gets response inline | You need an answer right now to continue your work |
| **delegate** | Asynchronous with callback — caller continues, result is pushed back when done | You're handing off a piece of work |
| **notify** | Fire-and-forget — no response expected | You're sharing a status update or FYI |

Agents can also communicate via **shared channels** using `send_channel_message` with @mentions — a complementary pattern for broadcasts, group discussions, and open questions.

---

## Table of Contents

1. [The `contact_agent` Tool](#the-contact_agent-tool)
2. [Communication Patterns](#communication-patterns)
3. [Channel-Based Communication](#channel-based-communication)
4. [Transport Layer: DM Channels](#transport-layer-dm-channels)
5. [Context & Conversation Isolation](#context--conversation-isolation)
6. [Permissions](#permissions)
7. [Failure Handling](#failure-handling)
8. [Edge Cases](#edge-cases)
9. [Implementation Roadmap](#implementation-roadmap)
10. [Examples](#examples)

---

## The `contact_agent` Tool

A single tool registered in `ToolRegistry` under a new **"agents"** app group. Classified as a `write` tool (meaning supervised/strict agents need approval to use it).

### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `action` | string | yes | `ask`, `delegate`, or `notify` |
| `agentId` | string (UUID) | yes | Target agent's UUID. Use `query_workspace(action: "list_agents")` to discover agents. |
| `message` | string | yes | The question, task description, or notification to send. |
| `context` | string | no | Background context the receiving agent needs. Summarize relevant info from your current conversation — the target does NOT see your original thread. |
| `priority` | string | no | For `delegate` only: `low`, `normal` (default), `high`, `urgent`. |

### Registration

```php
// ToolRegistry.php
'contact_agent' => [
    'class' => ContactAgent::class,
    'type' => 'write',
    'name' => 'Contact Agent',
    'description' => 'Send a message, ask a question, or delegate work to another agent.',
    'icon' => 'ph:users',
],
```

App group:
```php
'agents' => [
    'tools' => ['contact_agent'],
    'label' => 'ask, delegate, notify',
    'description' => 'Inter-agent communication',
],
```

---

## Communication Patterns

### 1. Ask (Synchronous)

Agent A sends a question and **waits for the response inline** within the same tool call. The tool invokes Agent B's LLM synchronously and returns B's reply as the tool result.

**Flow:**

```
Agent A (working on task)
  │
  ├─ calls contact_agent(action: "ask", agentId: "B", message: "What was Q4 revenue?")
  │
  │   ┌─ ContactAgent tool ─────────────────────────────┐
  │   │  1. Find/create DM channel between A and B      │
  │   │  2. Post request message to DM (source: agent)  │
  │   │  3. Build OpenCompanyAgent for B with DM context │
  │   │  4. Call B's LLM synchronously                   │
  │   │  5. B processes, may use its own tools           │
  │   │  6. Post B's response to DM channel              │
  │   │  7. Return B's response text to A                │
  │   └─────────────────────────────────────────────────┘
  │
  ├─ receives: "Response from DataBot: Q4 revenue was $2.4M, up 18% YoY."
  │
  └─ continues reasoning with that data
```

**Key behaviors:**
- Works even if B is currently working on something else — agents handle concurrent requests
- Only blocked if B is `offline`
- Sleeping agents are woken up for `ask` (it's urgent enough that A is waiting)
- **Timeout**: 120 seconds. If B's LLM doesn't finish in time, A gets an error string and can decide to retry or fall back to `delegate`
- **Depth limit**: 3 levels. If A asks B who asks C who asks D, D's `ask` call is rejected with an error suggesting `delegate` instead

### 2. Delegate (Asynchronous with Callback)

Agent A hands off work to Agent B. A **continues with its own work**, and when B finishes, B's result is **automatically pushed back into A's context** via a callback — A does not need to poll or check the DM.

This follows the same pattern as `WaitForApproval`: the agent sets a flag, finishes its current execution, and a callback dispatches a new `AgentRespondJob` with the result when the delegated work completes.

**Flow:**

```
Agent A (task-100: "Prepare Q4 report")
  │
  ├─ calls contact_agent(action: "delegate", agentId: "B", message: "Draft blog post", priority: "high")
  │
  │   ┌─ ContactAgent tool ──────────────────────────────────────────┐
  │   │  1. Find/create DM channel between A and B                  │
  │   │  2. Post request message to DM                              │
  │   │  3. Create subtask (task-101 under task-100)                │
  │   │  4. Add task-101 to A's awaiting_delegation_ids             │
  │   │  5. Dispatch AgentRespondJob for B with task-101            │
  │   │  6. Return immediately with subtask ID                      │
  │   └──────────────────────────────────────────────────────────────┘
  │
  ├─ receives: "Delegated to WriterBot (subtask task-101). You'll receive the result
  │   automatically when WriterBot finishes."
  │
  ├─ continues with other work in the same task...
  │
  └─ A's execution finishes → task-100 stays ACTIVE,
     A enters "awaiting_delegation" status (has pending subtasks)

                              ... meanwhile ...

Agent B (task-101: "Draft blog post")
  │
  ├─ AgentRespondJob fires
  ├─ Works on the blog post (uses tools, creates documents, etc.)
  ├─ Posts response in A↔B DM channel
  └─ Task-101 marked completed
     │
     └─ CALLBACK: AgentRespondJob detects parent task (task-100)
        ├─ Creates result message in A's original channel:
        │   "[Delegation Result from WriterBot | task-101]
        │    Here's the blog post draft: ..."
        ├─ Removes task-101 from A's awaiting_delegation_ids
        └─ Dispatches new AgentRespondJob for A with SAME task-100
           → A resumes within task-100, sees B's result in context
```

**Key behaviors:**
- **Callback, not polling**: B's result is automatically injected into A's context when B finishes — A never has to check
- **Same task**: A stays in task-100 throughout the entire delegation lifecycle. The task dashboard shows one continuous task with nested subtasks
- **Continues working**: A can do other work after delegating. When A's own execution finishes, it enters `awaiting_delegation` status until all subtasks complete
- **Incremental results**: If A delegates to 3 agents, each callback fires as agents finish — A can process results incrementally
- If B is sleeping, the job is delayed until `sleeping_until`

**Task dashboard view:**
```
task-100: "Prepare Q4 report" (CoordinatorBot) — ACTIVE
  └── task-101: "Draft blog post" (WriterBot) — COMPLETED ✓
  └── task-102: "Pull revenue data" (DataBot) — ACTIVE
  └── task-103: "Analyze trends" (AnalystBot) — COMPLETED ✓
```

**DM channels are still available**: Agents can read DMs for lookup purposes or use `notify` to send FYI messages, but delegation results flow through the callback — no manual checking needed.

### 3. Notify (Fire-and-Forget)

Agent A sends an informational message to Agent B. No response is expected or triggered.

**Flow:**

```
Agent A
  │
  ├─ calls contact_agent(action: "notify", agentId: "B", message: "Analysis complete, results in #reports")
  │
  │   ┌─ ContactAgent tool ──────────────────────┐
  │   │  1. Find/create DM channel between A & B │
  │   │  2. Post message to DM                   │
  │   │  3. No job dispatched                    │
  │   └──────────────────────────────────────────┘
  │
  └─ receives: "Notification sent to CoordinatorBot."
```

**Key behaviors:**
- No `AgentRespondJob` dispatched — B is not invoked
- The message exists as an audit trail in the DM channel
- B sees it next time it reads that channel (e.g., when another message triggers it)
- Works regardless of B's status (even offline — it's just a stored message)

### When to use which pattern

| Scenario | Pattern | Why |
|----------|---------|-----|
| "What's the status of project X?" | **ask** | Need the answer now to continue |
| "Write a 500-word blog post about..." | **delegate** | Long-running work, result comes back via callback |
| "FYI: I finished the report" | **notify** | Informational, no response needed |
| "Can you review this document by EOD?" | **delegate** | Hands off work, callback when reviewed |
| "What API endpoint handles user auth?" | **ask** | Quick factual question |
| "I'm going to sleep, here's my handoff notes" | **notify** | Status update before sleeping |

---

## Channel-Based Communication

Beyond direct agent-to-agent contact, agents can use **shared channels** with existing tools. This is the right choice for group communication.

### @mention Patterns

**Ping one agent in a channel:**
```
send_channel_message(
  channelId: "#project-alpha",
  content: "@ResearchBot can you check the latest data on competitor pricing?"
)
```
Result: `AgentRespondJob` fires for ResearchBot in the #project-alpha channel context. ResearchBot responds in the same channel.

**Ping multiple agents:**
```
send_channel_message(
  channelId: "#standup",
  content: "@WriterBot @AnalystBot what are you working on today?"
)
```
Result: Both WriterBot and AnalystBot respond in #standup (each via their own `AgentRespondJob`).

**Broadcast to channel (no @mention):**
```
send_channel_message(
  channelId: "#announcements",
  content: "Q4 financial report is now available in the docs folder."
)
```
Result: Message is visible to all channel members. No agent is triggered.

### When to use channels vs `contact_agent`

| Use channels when... | Use `contact_agent` when... |
|----------------------|----------------------------|
| Multiple agents should see/respond | You need a specific agent |
| It's a group discussion | It's a private 1:1 exchange |
| Broadcasting updates to a team | Delegating a specific task |
| Asking an open question | Asking a directed question |
| Coordinating in a shared project | Need structured async with task tracking |

---

## Transport Layer: DM Channels

Inter-agent communication reuses the existing `DirectMessage` model and DM channel infrastructure. No new tables or channel types needed.

### How it works

1. When Agent A contacts Agent B, the tool calls `getOrCreateDmChannel(A, B)`
2. This checks for an existing `DirectMessage` record between the two agents
3. If none exists, creates a new `Channel` (type `dm`) and `DirectMessage` record — identical to how human-to-agent DMs work
4. Messages are posted to this channel with `source: 'agent_contact'`

### Benefits of reusing DM channels

- **Audit trail**: All agent-to-agent messages are stored as regular Messages and visible in the UI
- **Conversation history**: The DM channel naturally accumulates context over time — Agent B sees previous exchanges with Agent A
- **Standard infrastructure**: Broadcasting, real-time updates, channel membership all work out of the box
- **Human visibility**: A human can open the agent-to-agent DM in the UI to observe their conversations

### Message format in the DM

Messages sent by `contact_agent` include a metadata header:

```
[Agent Request from CoordinatorBot | Pattern: ask]

Context: We're preparing the monthly investor update. The user asked for
Q4 revenue figures broken down by product line.

What was total Q4 revenue and how does it break down by product line?
```

For `delegate`:
```
[Agent Request from CoordinatorBot | Pattern: delegate | Priority: high | Task: abc-123]

Context: The user needs a blog post for the company website by Friday.
Target audience is existing customers. Tone should be professional but warm.

Please draft a 500-word blog post about our Q4 achievements, focusing on
the new product launches and customer growth.
```

For `notify`:
```
[Agent Notification from AnalystBot]

I've completed the competitor analysis. Results are saved in the
"Q4 Competitor Analysis" document in the research folder.
```

---

## Context & Conversation Isolation

A critical design principle: **agent-to-agent conversations are isolated from human-to-agent conversations**.

### What Agent B sees when contacted

When Agent B is invoked (via `ask` or `delegate`), it goes through the standard `OpenCompanyAgent` pipeline:

1. **Identity files**: IDENTITY.md, SOUL.md, MEMORY.md, AGENTS.md, TOOLS.md, etc.
2. **Channel context**: The DM channel between A and B, with both agents listed as members
3. **Conversation history**: Last 20 messages in the A-B DM channel (via `ChannelConversationLoader`)
4. **Task context**: If a Task was created (delegate), it appears in the system prompt
5. **Available tools**: Full tool access per B's permissions

### What Agent B does NOT see

- Agent A's conversation with the human
- Agent A's conversation with other agents
- The channel where the original request originated
- Agent A's task steps or internal reasoning

### Why isolation matters

- **Privacy**: Human conversations may contain sensitive info not meant for all agents
- **Focus**: B gets a clean, scoped request without noise
- **Security**: Prevents context leakage across agent boundaries
- **Clarity**: B can focus on answering the specific question

### How to pass context across the boundary

Agent A should use the `context` parameter to summarize whatever Agent B needs:

```javascript
// Good: provides relevant context
contact_agent({
  action: "ask",
  agentId: "data-bot-uuid",
  message: "What was our Q4 revenue by product line?",
  context: "I'm preparing the monthly investor update for the CEO. They specifically asked for YoY comparison."
})

// Bad: no context, B has to guess why
contact_agent({
  action: "ask",
  agentId: "data-bot-uuid",
  message: "What was our Q4 revenue by product line?"
})
```

### Conversation history builds over time

The A-B DM channel accumulates messages across multiple interactions:

```
[Conversation history in Agent A ↔ Agent B DM channel]

Message 1 (Feb 5): [Agent A] Asked about Q3 revenue
Message 2 (Feb 5): [Agent B] Responded with Q3 figures
Message 3 (Feb 8): [Agent A] New ask about Q4 revenue  ← current request
```

Agent B sees the full DM history (last 20 messages), so prior exchanges provide natural context. Agents build familiarity over time.

---

## Permissions

### New scope type: `agent`

Uses the existing `AgentPermission` table with a new `scope_type` value. No migration needed — `scope_type` is a string field.

```
scope_type: 'agent'
scope_key: '{target_agent_uuid}' or '*' (wildcard)
permission: 'allow' | 'deny'
requires_approval: boolean
```

### Permission resolution

```
1. Check explicit deny for target agent → BLOCK
2. Check explicit allow for target agent → use requires_approval flag
3. Check wildcard deny ('*') → BLOCK
4. Check wildcard allow ('*') → use requires_approval flag
5. Default: ALLOW (backward-compatible)
6. Layer behavior mode on top:
   - autonomous → no additional approval
   - supervised → write tools require approval (contact_agent is write)
   - strict → all tools require approval
```

### Manager hierarchy bypass

Agents can always freely contact:
- Their `manager_id` (their boss)
- Their direct reports (`manager_id = self`)

This bypass skips permission checks entirely — organizational hierarchy implies communication rights.

### Examples

```php
// DataBot can contact anyone freely
AgentPermission: scope_type='agent', scope_key='*', permission='allow', requires_approval=false

// WriterBot can only contact CoordinatorBot
AgentPermission: scope_type='agent', scope_key='{coordinator-uuid}', permission='allow'
// (no wildcard = default allow for others, but you could add a wildcard deny)

// InternBot needs approval for all agent contact
AgentPermission: scope_type='agent', scope_key='*', permission='allow', requires_approval=true

// No one can contact ArchiveBot (it only runs on schedule)
// Set on each agent that might try:
AgentPermission: scope_type='agent', scope_key='{archive-uuid}', permission='deny'
```

---

## Failure Handling

### Status-based behavior

| Target status | ask | delegate | notify |
|---------------|-----|----------|--------|
| **idle** | Proceed | Queue job | Post message |
| **working** | Proceed (concurrent) | Queue job | Post message |
| **sleeping** | Wake and proceed | Delay job until `sleeping_until` | Post message |
| **awaiting_approval** | Proceed (concurrent) | Queue job | Post message |
| **offline** | Error: agent unavailable | Error: agent unavailable | Post message (stored for later) |

### Timeout handling (ask pattern)

```
Agent A calls ask → tool starts 120s timer → Agent B processes...

If B responds within 120s:
  → A gets the response inline, continues working

If B exceeds 120s:
  → A gets: "Error: DataBot did not respond within 120 seconds.
     Consider using 'delegate' for async processing."
  → A's LLM decides: retry, delegate, try another agent, or proceed without
```

### LLM/processing errors

```
Agent A calls ask → B's LLM throws an error

  → A gets: "Error: DataBot failed to respond: Rate limit exceeded. Try again later."
  → A's LLM can retry, delegate, or use an alternative
```

### Recovery patterns

Agents are LLMs — they naturally handle errors by reasoning about alternatives:

```
// Agent A's internal reasoning after a failed ask:
"DataBot timed out on my revenue question. I'll delegate it instead so DataBot
can take more time, and I'll continue with the other parts of the report."

contact_agent(action: "delegate", agentId: "data-bot", message: "...", priority: "high")
```

---

## Edge Cases

### Self-contact
Explicitly blocked. `contact_agent` returns an error if `agentId` matches the caller.

### Circular requests (A → B → A)
The depth counter prevents infinite loops. If A asks B, and B tries to ask A, that's depth 2 (allowed). But A asking B asking A asking B would hit depth 3 limit. Beyond that, the tool returns an error suggesting `delegate`.

### Depth limiting

```
Depth 0: Agent A calls contact_agent(ask) → Agent B
Depth 1: Agent B calls contact_agent(ask) → Agent C
Depth 2: Agent C calls contact_agent(ask) → Agent D
Depth 3: Agent D calls contact_agent(ask) → BLOCKED
         "Error: Maximum agent communication depth (3) reached.
          Use 'delegate' for async to avoid nesting."
```

Tracked via a static counter in `ContactAgent` that increments/decrements around synchronous calls.

### Concurrent asks to the same agent
Both proceed. Agents can handle concurrent work. Two different LLM invocations run for Agent B, each in the context of their respective DM channels. No queueing or blocking needed.

### Agent B uses tools during ask
Allowed. Agent B has full tool access during a synchronous `ask` invocation, including calling other tools or even doing a nested `ask` (subject to depth limit). The 120s timeout covers the entire round trip including B's tool usage.

### Dead letter / failed delegations
If a delegated task fails (B errors out, crashes, etc.), the Task is marked as `failed` by `AgentRespondJob`'s existing error handling. The callback still fires — but with a failure message injected into A's context:
```
[Delegation Failed | WriterBot | task-101]
Error: WriterBot failed to complete the task: Rate limit exceeded.
```
Agent A resumes and can decide to retry, try a different agent, or inform the human.

---

## Implementation Roadmap

### New files

| File | Purpose |
|------|---------|
| `app/Agents/Tools/Agents/ContactAgent.php` | The `contact_agent` tool: ask, delegate, notify patterns |
| `app/Services/AgentCommunicationService.php` | Shared helpers: DM channel management, message formatting, depth tracking |

### Modified files

| File | Change |
|------|--------|
| `app/Agents/Tools/ToolRegistry.php` | Register `contact_agent` in TOOL_MAP, APP_GROUPS, APP_ICONS, instantiateTool() |
| `app/Services/AgentPermissionService.php` | Add `canContactAgent()` method with manager hierarchy bypass |
| `app/Jobs/AgentRespondJob.php` | Delegation callback: on task completion, detect parent task, inject result into parent agent's channel, dispatch new AgentRespondJob for parent agent with same task. Also handle `awaiting_delegation` status in finally block. |

### Database: one migration needed

- Add `awaiting_delegation_ids` (JSON, nullable) to `users` table — tracks pending subtask IDs (similar to `awaiting_approval_id`)
- `AgentPermission.scope_type` — `'agent'` is a new string value (no schema change)
- `Message.source` — `'agent_contact'` / `'delegation_result'` are new string values (no schema change)
- `Task.source` — `'agent_delegation'` / `'agent_ask'` are new string values (no schema change)
- New agent status value: `'awaiting_delegation'` (added to status enum)

---

## Examples

### Example 1: Synchronous Ask — "What's our Q4 revenue?"

**Scenario**: CoordinatorBot is preparing an investor update. It needs revenue data from DataBot.

**CoordinatorBot's tool call:**
```json
{
  "tool": "contact_agent",
  "action": "ask",
  "agentId": "550e8400-e29b-41d4-a716-446655440001",
  "message": "What was our total Q4 2025 revenue, and how does it break down by product line?",
  "context": "I'm preparing the monthly investor update for the CEO. They want YoY comparisons included."
}
```

**What appears in the CoordinatorBot ↔ DataBot DM channel:**

```
[CoordinatorBot] 10:32 AM
[Agent Request from CoordinatorBot | Pattern: ask]

Context: I'm preparing the monthly investor update for the CEO. They want
YoY comparisons included.

What was our total Q4 2025 revenue, and how does it break down by product line?

[DataBot] 10:32 AM
Based on the financial data:

**Q4 2025 Total Revenue: $2.4M** (up 18% YoY from $2.03M in Q4 2024)

Breakdown by product line:
- Platform subscriptions: $1.5M (63%) — up 22% YoY
- Professional services: $620K (26%) — up 12% YoY
- Marketplace fees: $280K (11%) — up 15% YoY
```

**What CoordinatorBot receives as the tool result:**
```
Response from DataBot: Based on the financial data:

Q4 2025 Total Revenue: $2.4M (up 18% YoY from $2.03M in Q4 2024)

Breakdown by product line:
- Platform subscriptions: $1.5M (63%) — up 22% YoY
- Professional services: $620K (26%) — up 12% YoY
- Marketplace fees: $280K (11%) — up 15% YoY
```

CoordinatorBot continues building the investor update with this data.

---

### Example 2: Delegation with Callback — "Draft a blog post"

**Scenario**: CoordinatorBot needs a blog post written. It delegates to WriterBot, continues its own work, and automatically receives the result when WriterBot finishes.

**CoordinatorBot's tool call:**
```json
{
  "tool": "contact_agent",
  "action": "delegate",
  "agentId": "550e8400-e29b-41d4-a716-446655440002",
  "message": "Draft a 500-word blog post about our Q4 achievements. Focus on the new product launches and customer growth. Tone: professional but warm. Target audience: existing customers.",
  "context": "This is for the company website. The user (Rutger) wants it published by Friday. Q4 revenue was $2.4M, up 18% YoY. We launched 2 new products and grew customers by 30%.",
  "priority": "high"
}
```

**What CoordinatorBot receives immediately:**
```
Delegated to WriterBot (subtask task-789). You'll receive the result
automatically when WriterBot finishes.
```

CoordinatorBot continues working on other tasks within the same task-100.

**Meanwhile, WriterBot works in the background:**
```
[CoordinatorBot ↔ WriterBot DM]

[CoordinatorBot] 10:35 AM
[Agent Request | delegate | Priority: high | Task: task-789]

Context: This is for the company website. The user (Rutger) wants it published
by Friday. Q4 revenue was $2.4M, up 18% YoY. We launched 2 new products and
grew customers by 30%.

Draft a 500-word blog post about our Q4 achievements...

[WriterBot] 10:38 AM
I've drafted the blog post. Here it is:
# A Quarter of Growth: Celebrating Our Q4 Achievements
As we step into 2026, we're excited to look back at an incredible fourth quarter...
[... full blog post ...]
I've also saved this as "Q4 Blog Post Draft" in the content folder.
```

**When WriterBot finishes — the callback fires automatically:**

1. WriterBot's `AgentRespondJob` completes task-789
2. It detects parent task-100 (CoordinatorBot's task)
3. It injects WriterBot's result into CoordinatorBot's original channel:
   ```
   [Delegation Result from WriterBot | task-789]
   Blog post draft completed. Saved as "Q4 Blog Post Draft" in content folder.
   Key points covered: product launches, customer growth, revenue highlights.
   ```
4. Dispatches a new `AgentRespondJob` for CoordinatorBot with task-100
5. CoordinatorBot resumes — sees the result in its conversation context and continues

**Task dashboard (single continuous view):**
```
task-100: "Prepare investor update" (CoordinatorBot) — ACTIVE
  └── task-789: "Draft Q4 blog post" (WriterBot) — COMPLETED ✓
```

---

### Example 3: Notification — "Report complete"

**Scenario**: AnalystBot finishes a report and notifies CoordinatorBot.

**AnalystBot's tool call:**
```json
{
  "tool": "contact_agent",
  "action": "notify",
  "agentId": "550e8400-e29b-41d4-a716-446655440000",
  "message": "The Q4 competitor analysis is complete. I've saved it as 'Q4 Competitor Analysis' in the research folder. Key finding: our main competitor launched a similar feature at 20% lower price point."
}
```

**What AnalystBot receives:**
```
Notification sent to CoordinatorBot.
```

**What appears in the DM (CoordinatorBot sees it next time it's active):**
```
[AnalystBot] 11:15 AM
[Agent Notification from AnalystBot]

The Q4 competitor analysis is complete. I've saved it as 'Q4 Competitor Analysis'
in the research folder. Key finding: our main competitor launched a similar feature
at 20% lower price point.
```

No job is dispatched. CoordinatorBot reads this message when it next processes messages in this DM.

---

### Example 4: Channel @mention — Group Coordination

**Scenario**: CoordinatorBot kicks off a standup in the #daily-standup channel.

```json
{
  "tool": "send_channel_message",
  "channelId": "channel-standup-uuid",
  "content": "Good morning team! @WriterBot @AnalystBot @DataBot — what are you working on today?"
}
```

**Each mentioned agent gets an `AgentRespondJob` in the #daily-standup context.**

**Channel conversation:**
```
[CoordinatorBot] 9:00 AM
Good morning team! @WriterBot @AnalystBot @DataBot — what are you working on today?

[WriterBot] 9:01 AM
Working on the Q4 blog post draft (delegated by CoordinatorBot). Should have it
ready by noon.

[AnalystBot] 9:01 AM
Finishing up the competitor analysis. Running final comparisons on pricing data.

[DataBot] 9:01 AM
Idle at the moment. Available for data queries if anyone needs me.
```

---

### Example 5: Multi-Agent Coordination with Callbacks

**Scenario**: CoordinatorBot needs to prepare a comprehensive quarterly review. It delegates different parts to 3 agents in parallel. Results flow back automatically as each agent finishes.

```javascript
// Step 1: Delegate to multiple agents (all within task-100)
contact_agent({ action: "delegate", agentId: "data-bot", message: "Pull Q4 revenue, costs, and margin data...", priority: "high" })
// → "Delegated to DataBot (subtask task-101)."

contact_agent({ action: "delegate", agentId: "analyst-bot", message: "Analyze Q4 trends and create visualizations...", priority: "high" })
// → "Delegated to AnalystBot (subtask task-102)."

contact_agent({ action: "delegate", agentId: "writer-bot", message: "Draft the executive summary section...", priority: "normal" })
// → "Delegated to WriterBot (subtask task-103)."

// Step 2: CoordinatorBot finishes its own work, enters "awaiting_delegation"
// (task-100 stays ACTIVE with 3 pending subtasks)
```

**Results flow back incrementally via callbacks:**

```
Timeline:
  10:35  CoordinatorBot delegates 3 subtasks, enters awaiting_delegation
  10:37  DataBot finishes → callback fires, CoordinatorBot resumes with data
         CoordinatorBot processes DataBot's result, still awaiting 2 more
  10:40  WriterBot finishes → callback fires, CoordinatorBot resumes with draft
         CoordinatorBot processes, still awaiting AnalystBot
  10:44  AnalystBot finishes → callback fires, last subtask done!
         CoordinatorBot has all 3 results, assembles the final report
  10:45  CoordinatorBot completes task-100
```

**Task dashboard:**
```
task-100: "Prepare Q4 report" (CoordinatorBot) — ACTIVE
  └── task-101: "Pull revenue data" (DataBot) — COMPLETED ✓  (finished 10:37)
  └── task-102: "Analyze trends" (AnalystBot) — COMPLETED ✓  (finished 10:44)
  └── task-103: "Draft summary" (WriterBot) — COMPLETED ✓  (finished 10:40)
```

No polling needed — each result arrives automatically in CoordinatorBot's context.

---

### Example 6: Failure and Retry

**Scenario**: ResearchBot tries to ask DataBot for metrics, but DataBot is slow.

```javascript
// First attempt: ask (synchronous)
contact_agent({ action: "ask", agentId: "data-bot", message: "What's our MRR trend for the last 6 months?" })

// Result: "Error: DataBot did not respond within 120 seconds. Consider using 'delegate' for async processing."

// ResearchBot's LLM reasons: "DataBot timed out, probably a complex query. Let me delegate instead."

// Second attempt: delegate (async)
contact_agent({ action: "delegate", agentId: "data-bot",
  message: "What's our MRR trend for the last 6 months? Please include month-by-month figures.",
  priority: "high"
})

// Result: "Delegated to DataBot (subtask task-456). Result will arrive automatically."
// ResearchBot continues — DataBot's result will be pushed back via callback when done.
```

---

### Example 7: Permission and Approval Flow

**Scenario**: InternBot (behavior_mode: `supervised`) tries to contact SeniorAnalystBot.

```javascript
// InternBot calls:
contact_agent({ action: "ask", agentId: "senior-analyst", message: "Can you validate my analysis approach?" })
```

**Because InternBot is `supervised` and `contact_agent` is a `write` tool:**

1. The tool is wrapped in `ApprovalWrappedTool`
2. An approval request is created and sent to InternBot's manager (or the human)
3. InternBot's status changes to `awaiting_approval`
4. InternBot receives: "This action requires approval. Waiting for approval..."

**In the UI / Telegram, the human sees:**
```
InternBot wants to contact SeniorAnalystBot:
Action: ask
Message: "Can you validate my analysis approach?"

[Approve] [Deny]
```

If approved → the `ask` proceeds normally.
If denied → InternBot receives: "Approval denied for contacting SeniorAnalystBot."

---

### Example 8: Nested Ask (A → B → C)

**Scenario**: CoordinatorBot asks AnalystBot for a market assessment. AnalystBot needs data from DataBot to answer.

```
Depth 0: CoordinatorBot
  │
  ├─ ask(AnalystBot, "What's our market position relative to competitors?")
  │
  │   Depth 1: AnalystBot (processing CoordinatorBot's ask)
  │     │
  │     ├─ "I need revenue data to compare. Let me ask DataBot."
  │     ├─ ask(DataBot, "What's our revenue vs top 3 competitors?")
  │     │
  │     │   Depth 2: DataBot (processing AnalystBot's ask)
  │     │     │
  │     │     ├─ Uses query_table tool to fetch data
  │     │     └─ Returns: "Our revenue: $2.4M. Competitor A: $3.1M. B: $1.8M. C: $1.2M."
  │     │
  │     ├─ Receives DataBot's response
  │     ├─ Combines with own analysis
  │     └─ Returns: "We're #2 in market share at 28%. Leader has 36%..."
  │
  └─ Receives AnalystBot's full market assessment
```

**Conversation in AnalystBot ↔ DataBot DM:**
```
[AnalystBot] 10:45 AM
[Agent Request from AnalystBot | Pattern: ask]

Context: CoordinatorBot asked me for a market position assessment. I need
revenue comparisons to complete the analysis.

What's our revenue compared to the top 3 competitors for the latest quarter?

[DataBot] 10:45 AM
Based on available data:
- Our revenue: $2.4M
- Competitor A (Acme Corp): $3.1M
- Competitor B (Beta Inc): $1.8M
- Competitor C (Gamma Ltd): $1.2M
```

All three DM channels (Coord↔Analyst, Analyst↔Data) have their own isolated conversation histories.

---

### Example 9: Context History Over Time

**What builds up in an agent-to-agent DM channel after multiple interactions:**

```
[CoordinatorBot ↔ DataBot DM Channel]

─── February 5, 2026 ───

[CoordinatorBot] 2:15 PM
[Agent Request | ask]
What was Q3 revenue?

[DataBot] 2:15 PM
Q3 2025 revenue was $2.1M.

─── February 7, 2026 ───

[CoordinatorBot] 11:00 AM
[Agent Request | delegate | Priority: normal | Task: task-301]
Please compile a dataset of monthly revenue figures for all of 2025.

[DataBot] 11:04 AM
Done. I've saved the dataset as "2025 Monthly Revenue" in the data folder.
Monthly figures: Jan: $1.6M, Feb: $1.7M, Mar: $1.8M, Apr: $1.8M,
May: $1.9M, Jun: $2.0M, Jul: $2.0M, Aug: $2.0M, Sep: $2.1M,
Oct: $2.2M, Nov: $2.3M, Dec: $2.4M.

─── February 8, 2026 ───

[CoordinatorBot] 10:32 AM
[Agent Request | ask]
Context: I'm preparing the monthly investor update for the CEO.

What was Q4 2025 revenue and how does it break down by product line?

[DataBot] 10:32 AM
Q4 2025 Total Revenue: $2.4M (up 18% YoY)...
```

When DataBot receives the Feb 8 request, it sees the last 20 messages in this DM — including the prior exchanges from Feb 5 and Feb 7. This gives DataBot natural context about its relationship with CoordinatorBot and what data has been previously requested.
