# Anthropic's Always-On / Agentic Claude Products: Architecture, Teardowns, and Lessons for OpenCompany

Research date: 2026-11-02. Reliability legend used throughout:
- **[OFFICIAL]**: Anthropic engineering blog, news, docs, or support pages. High reliability for what Anthropic chooses to disclose.
- **[OFFICIAL-QUOTE]**: an Anthropic employee quoted by a reputable third party, such as Simon Willison. High reliability.
- **[LEAK]**: the March 31, 2026 Claude Code source-map leak, as reported by third parties. Medium reliability: the code was real, but the readings are secondary and sometimes contradict each other. Flagged features may never ship.
- **[3P-RE]**: third-party reverse engineering or testing. Medium reliability.
- **[PRESS]**: secondary press coverage. Medium to low reliability: it often repeats itself and some outlets publish template-like copy.
- **[KNOWN-OFFICIAL, not re-fetched]**: official Anthropic posts from 2024–2025 that I know well but did not re-fetch this session. The URLs are canonical. The figures match the published posts as I remember them, but a writer quoting them should spot-check first.

---

## Q1. Which current Anthropic product is closest to an "always-on agentic assistant", and why?

### Takeaway
**Claude Duet** (research preview, launched Oct 21, 2026) is the closest match. It is a standalone, proactive agent. It runs on its own cloud VM with its own browser, checks the user's connected accounts every few hours, sends a daily brief, messages the user first, and asks before it sends, pays, signs, or deletes. For developer and ops workloads the closest match is **Claude Code cloud scheduled tasks** (formerly "Routines"): cron, API, and GitHub triggers start autonomous cloud sessions. Both run on the cloud-execution substrate of the unified "Claude" app, which absorbed Cowork in September 2026 and moved Cowork's VM to the cloud in October 2026. The leaked, unshipped Claude Code "KAIROS" daemon mode shows Anthropic was also building an always-on agent into the coding tool itself.

### Cited Findings
**Claude Duet (closest match)**
- Duet launched as a research preview on Oct 21, 2026. It is a standalone app where Claude acts as a proactive agent: it surfaces items that need attention, works on tasks in the background, and asks permission before acting. [PRESS] — [VKTR](https://www.vktr.com/ai-platforms/claude-duet-anthropic-previews-an-ai-agent-that-messages-you-first/); [WOWTALE](https://en.wowtale.net/2026/10/22/235671/)
- You message Duet "the way you'd message a person" by typing or speaking. It works in your connected apps, tells you when something needs you, and "is designed to ask before it sends, pays, signs, or deletes." [OFFICIAL] — [Claude Help Center: Get started with Claude Duet](https://support.claude.com/en/articles/17631495-get-started-with-claude-duet)
- Duet "runs on its own cloud computer and keeps working after you close the app." It uses its own cloud browser for sites that have no connector. [OFFICIAL] — [Claude Help Center](https://support.claude.com/en/articles/17631495-get-started-with-claude-duet)
- Proactivity loop: each morning Duet reviews email and calendar, highlights priorities, and can start first steps such as drafting replies. During the day it sends progress updates and follows up on quiet threads. It "checks your accounts at set intervals, so urgent items can arrive between checks" (polling, not push). [OFFICIAL] — [Claude Help Center](https://support.claude.com/en/articles/17631495-get-started-with-claude-duet). Press reports the interval as "every few hours." [PRESS] — [VKTR](https://www.vktr.com/ai-platforms/claude-duet-anthropic-previews-an-ai-agent-that-messages-you-first/)
- Interaction model: a main thread plus separate topic "channels" that share one memory. Duet posts to the main thread when a task finishes or needs a decision. A **Tasks** tab shows active work and items needing input, and "only you can mark a task complete." Larger jobs produce editable files in a **Make** tab. [OFFICIAL] — [Claude Help Center](https://support.claude.com/en/articles/17631495-get-started-with-claude-duet)
- Duet runs on Opus 5.5 and sets its own effort level. Users can't pick the model, watch the browser, or take it over. Mike Krieger is quoted saying Opus 5.5 "was our first model with the judgment to be proactive in the right ways." [OFFICIAL] for the model and limits; [PRESS] for the quote — [Claude Help Center](https://support.claude.com/en/articles/17631495-get-started-with-claude-duet); [VKTR](https://www.vktr.com/ai-platforms/claude-duet-anthropic-previews-an-ai-agent-that-messages-you-first/)
- Availability: US only, Pro/Max/Ultra waitlist, rolling out in waves. Not yet available on Team or Enterprise. Background work (the brief and check-ins) counts against the 5-hour and weekly usage limits. [OFFICIAL] — [Claude Help Center](https://support.claude.com/en/articles/17631495-get-started-with-claude-duet)
- Duet was reported before launch, possibly under a different name. Early-2026 reporting described a project called "Conway" as a persistent background-agent platform in testing. Its relationship to Duet is unverified. [PRESS, speculative] — [AY Automate](https://www.ayautomate.com/blog/anthropic-conway-claude-always-on-agent-2026)

**Unified Claude / Cowork (the general-agent substrate)**
- Cowork launched in January 2026 as Anthropic's "general agent." [3P] — [Simon Willison, First impressions of Claude Cowork (Jan 12, 2026)](https://simonwillison.net/2026/Jan/12/claude-cowork/)
- On Sep 16, 2026, "Claude Cowork and chat are merging into one Claude." Tasks keep running "even after you've closed your laptop," and users can schedule recurring work such as a weekly report. By default Claude asks before acting, but users can let it keep working and check in only when needed. [OFFICIAL] — [Claude blog: Cowork is now Claude](https://claude.com/blog/cowork-is-now-claude); commentary in [Simon Willison](https://simonwillison.net/2026/Sep/16/one-claude/)
- Since Oct 6, 2026, new Cowork tasks on Pro/Max/Ultra run in the cloud on every surface, and the "Only on your computer" option was removed. New scheduled tasks run in the cloud and don't need the computer online. [OFFICIAL] — [Claude Help Center: Use Claude Cowork on web, desktop, and mobile](https://support.claude.com/en/articles/15520349-use-claude-cowork-on-web-desktop-and-mobile)

**Claude Code cloud scheduled tasks (always-on for dev workloads)**
- "A cloud scheduled task, formerly a routine, is a saved Claude Code configuration: a prompt, one or more repositories, and a set of connectors… run automatically" on Anthropic cloud or a self-hosted environment, "so they keep working when your laptop is closed." Triggers are Schedule, API, and GitHub. [OFFICIAL] — [Claude Code docs: scheduled tasks](https://code.claude.com/docs/en/routines)

**KAIROS (unshipped always-on Claude Code mode)**
- The leaked source contains KAIROS, which is gated behind PROACTIVE/KAIROS compile-time flags and absent from external builds. It receives periodic `<tick>` prompts and decides on its own whether to act. It has a 15-second blocking budget per cycle and writes append-only daily logs that the agent can't erase. It has exclusive tools (push notifications, file delivery, PR subscriptions), subscribes to GitHub webhooks, and persists across restarts. [LEAK] — [claudefa.st leak analysis](https://claudefa.st/blog/guide/mechanics/claude-code-source-leak); [oldeucryptoboi Substack](https://oldeucryptoboi.substack.com/p/claude-code-leak). Reports differ on maturity: some call KAIROS fully built, others list "Daemon Mode" as shelved. — [techsy.io](https://techsy.io/el/blog/claude-code-leaked-source-kairos-ultraplan-buddy)

### Inferences
- Duet is the only Anthropic product that is proactive by design. It initiates contact, runs an inbox and calendar triage loop on a timer, and holds a persistent conversational relationship through threads and channels. The others (Cowork/Claude, Claude Code, routines) start from a user prompt or a stored trigger. Duet is "always-on" in practice but implemented as interval polling: Anthropic says outright that it cannot "monitor accounts 24/7."
- Duet's components are already-shipped pieces: unified Claude memory, skills, and connectors; cloud VM sessions; a browser agent; and the Managed Agents credential model (see Q5/Q8). That suggests Anthropic's always-on assistant is mostly a **scheduler + proactive policy + approval UX** on top of the general-agent runtime, not a new runtime.
- For OpenCompany, the best reference points are Duet for the human-facing proactive UX (brief, threads/channels, tasks tab, approvals) and Claude Code scheduled tasks for the trigger and execution contract.

### Gaps
- I found no anthropic.com/news post or engineering write-up for Duet: `anthropic.com/news/claude-duet` returned 404. The architecture details come from the support article and press. Anthropic said more safety information is coming "soon." [PRESS] — [VKTR](https://www.vktr.com/ai-platforms/claude-duet-anthropic-previews-an-ai-agent-that-messages-you-first/)
- The exact check interval, the urgency model, and how Duet decides to message first are not documented.
- Whether Duet is the shipped form of KAIROS or "Conway" is unverified speculation.

---

## Q2. Tool loop and runtime architecture

### Takeaway
Anthropic's agents share one simple loop: the model calls tools in a loop, using feedback from the environment. Anthropic's 2026 production architecture (Managed Agents) splits that loop into three decoupled parts: a stateless **harness** ("brain"), a disposable **sandbox/tools** layer ("hands"), and a durable append-only **session event log**. Cowork moved its agent loop out of the VM for resilience, then moved the VM to the cloud. The Claude Code harness is the engine inside Cowork.

### Cited Findings
- Anthropic separates "workflows" (predefined code paths) from "agents" (LLMs that dynamically direct their own tool use). It recommends the simplest pattern that works: prompt chaining, routing, parallelization, orchestrator-workers, evaluator-optimizer, then autonomous agents. It also stresses designing the agent-computer interface (ACI). [KNOWN-OFFICIAL, not re-fetched] — [Building effective agents (Dec 2024)](https://www.anthropic.com/engineering/building-effective-agents)
- **Managed Agents (Apr 8, 2026):** the session is an append-only event log outside the harness and sandbox. The harness is a stateless loop that calls Claude and routes tool calls, and it can be restarted. Each sandbox ("hand") is just a tool behind `execute(name, input) → string`. Other primitives: `provision({resources})`, `wake(sessionId)`/`getSession(id)` to restart a crashed harness from the log, `emitEvent`, and `getEvents()` for positional slices. A failed container surfaces as a tool error that Claude can retry with a new container. A failed harness resumes from the last logged event. [OFFICIAL] — [Anthropic Engineering: Scaling Managed Agents](https://www.anthropic.com/engineering/managed-agents)
- The first Managed Agents design put session, harness, and sandbox in one container, so a failed container lost the session (the "pet" problem). Lazy container provisioning cut p50 time-to-first-token by about 60% and p95 by more than 90%. Brains are stateless, scale out, and can pass hands to one another. [OFFICIAL] — [Scaling Managed Agents](https://www.anthropic.com/engineering/managed-agents)
- Cowork runs the Claude Code harness inside a Linux VM with extra sandboxing, network controls, and filesystem mounts. [OFFICIAL-QUOTE, Felix Rieseberg via podcast/HN] — [Latent Space: Felix Rieseberg](https://www.latent.space/p/felix-anthropic); [HN discussion](https://hn.svelte.dev/item/47218288)
- Cowork originally ran the agent loop inside the guest VM. The loop later moved to the host so Claude could respond even when the VM failed. Code execution stayed in the VM, and local MCP servers moved to the host. [OFFICIAL] — [How we contain Claude across products (May 25, 2026)](https://www.anthropic.com/engineering/how-we-contain-claude)
- In October 2026, "The 'new' version of Cowork runs model inference and the VM in the cloud. Each session gets its own sandbox, not sharing state with other sessions." When the cloud VM needs a file from the device, the desktop app serves that file-access tool call. [OFFICIAL-QUOTE] — [Simon Willison quoting Felix Rieseberg (Oct 5, 2026)](https://simonwillison.net/2026/Oct/5/felix-rieseberg/)
- Leaked Claude Code internals: a React/Ink terminal UI, a query engine of about 46k lines, 40+ tools, 25+ hook events, 44 feature flags, and 14 tracked prompt-cache-break vectors. [LEAK] — [claudefa.st](https://claudefa.st/blog/guide/mechanics/claude-code-source-leak); [Randal Olson, "forty tools"](https://randalolson.com/2026/04/02/claude-code-leak-four-charts). Reported line counts vary between about 163k and 512k across outlets. — [Daniel Vaughan](https://codex.danielvaughan.com/2026/04/01/claude-code-source-leak-what-it-reveals/); [Kilo timeline](https://blog.kilo.ai/p/claude-code-source-leak-a-timeline)
- The leak came from npm package `@anthropic-ai/claude-code` v2.1.88 (Mar 31, 2026), which shipped a 60 MB `cli.js.map`. A Bun bundler bug that emits source maps in production is the likely cause. The CLI source was exposed; model weights were not. [PRESS/3P] — [Kilo timeline](https://blog.kilo.ai/p/claude-code-source-leak-a-timeline); [WeAreDevelopers](https://www.wearedevelopers.com/magazine/725-the-claude-code-source-leak)
- Tool design guidance: namespace tools, return high-signal and token-efficient output (pagination, truncation, filtering), and evaluate tools with agents. [KNOWN-OFFICIAL, not re-fetched] — [Writing effective tools for agents (Sep 2025)](https://www.anthropic.com/engineering/writing-tools-for-agents)
- "Code execution with MCP": present MCP servers to the agent as code APIs on a filesystem instead of loading every tool definition into context. Anthropic's example reduced token use from about 150k to about 2k (98.7%). Intermediate data stays in the execution environment, which enables privacy-preserving flows and persistent "skills" code. [KNOWN-OFFICIAL, not re-fetched] — [Code execution with MCP (Nov 2025)](https://www.anthropic.com/engineering/code-execution-with-mcp)

### Inferences
- In Anthropic's 2026 view, durability belongs to the session log, not the process. That matches a queue-based Laravel runtime: an agent run should be re-entrant from persisted events, and a crashed worker should not lose the run.
- Treating every execution environment (container, MCP server, custom tool) as `execute(name, input) → string` is the abstraction that let Anthropic swap, lazily provision, and isolate hands.

### Gaps
- No official, detailed description of Claude Code's inner loop (TodoWrite, compaction triggers, tool-result truncation thresholds) was fetched this session. The leak analyses don't name the "five compaction strategies."

---

## Q3. Memory (memory tool, CLAUDE.md, chat memory, project memory, Duet)

### Takeaway
Anthropic uses several layers. (1) File-based instruction memory: CLAUDE.md for Claude Code, and the `.claude` folder mounted into Cowork. (2) A developer-hosted, client-side **memory tool** (a file directory). (3) Consumer **chat memory**, which in 2026 stores memories as individual topics written during chats, scoped per project, alongside RAG **chat search** exposed as visible tool calls. (4) The leaked **autoDream**, an idle-time memory-consolidation job. Duet reuses the account's memory, skills, and connectors, and every Duet channel shares one memory.

### Cited Findings
- Memory tool (Sep 29, 2025): Claude can create, read, update, and delete files in a memory directory that persists across conversations. "The memory tool operates entirely client-side through tool calls," so developers control storage. Context editing plus the memory tool improved an internal agentic-search eval by 39%, and context editing alone by 29%. In a 100-turn web-search eval, context editing cut token use by 84%. [OFFICIAL] — [Claude blog: Managing context on the Claude Developer Platform](https://claude.com/blog/context-management)
- Original consumer chat memory (Sep 2025, reverse engineered): two model-invoked tools, `conversation_search` and `recent_chats`, over raw chat history. Memory activated only when invoked. ChatGPT, by contrast, auto-injects context about past chats. [3P-RE] — [Simon Willison on Shlok Khemani's analysis (Sep 12, 2025)](https://simonwillison.net/2025/Sep/12/claude-memory/)
- Current chat memory (2026): Claude "saves memory as individual topics while you chat" and saves on its own or on request. Each project has its own memory, and inside a project Claude uses project memory plus non-project account memory, never other projects' memory. Sensitive categories are excluded by default, and some data (government IDs, financial account numbers, and others) is never saved. Users can view, edit, or delete topics, pause or reset memory, and import or export it. Deleting a conversation does **not** delete memories derived from it. [OFFICIAL] — [Claude Help Center: Understanding Claude's memory](https://support.claude.com/en/articles/11817273-understanding-claude-s-memory)
- Chat search uses RAG and "shows each search as a tool call." Non-project chats and each project's chats are searched separately. Incognito chats are never searched. Enterprise customer-managed keys block search. [OFFICIAL] — [Understanding Claude's memory](https://support.claude.com/en/articles/11817273-understanding-claude-s-memory)
- In the legacy memory model (still used by a few Team and Enterprise orgs), a summary is synthesized from non-project chats and refreshed every 24 hours, with a separate summary per project. [OFFICIAL] — [Understanding Claude's memory](https://support.claude.com/en/articles/11817273-understanding-claude-s-memory)
- Owner controls: turning memory off org-wide permanently deletes all members' memory. Owners can't view members' memories. Audit logs record owner toggles. [OFFICIAL] — [Understanding Claude's memory](https://support.claude.com/en/articles/11817273-understanding-claude-s-memory)
- Cowork cloud tasks share memory with chat, but local Cowork sessions don't use it. A per-task "Memory" toggle runs a task without reading or writing memory. [OFFICIAL] — [Understanding Claude's memory](https://support.claude.com/en/articles/11817273-understanding-claude-s-memory); [Use Claude Cowork on web, desktop, and mobile](https://support.claude.com/en/articles/15520349-use-claude-cowork-on-web-desktop-and-mobile)
- Duet uses "your Claude memory, skills, and connectors from your first message," searches past chats (governed by the "Search and reference chats" setting), and its channels share the same memory. Disconnecting a connector stops future access, but memory of earlier information is kept. [OFFICIAL] — [Get started with Claude Duet](https://support.claude.com/en/articles/17631495-get-started-with-claude-duet)
- Cowork's VM mounts only the user-selected workspace and the `.claude` folder, which carries file-based config and memory into the sandbox. [OFFICIAL] — [How we contain Claude](https://www.anthropic.com/engineering/how-we-contain-claude)
- autoDream (unshipped/flagged): a background memory-consolidation subprocess that runs while the user is idle. It is gated on at least 24h since the last run, at least 5 completed sessions, and a consolidation lock. It has four phases (Orient, Gather Recent Signal, Consolidate, Prune and Index) and keeps MEMORY.md under 200 lines (about 25KB). [LEAK] — [claudefa.st](https://claudefa.st/blog/guide/mechanics/claude-code-source-leak). Other outlets describe its structure differently. — [oldeucryptoboi](https://oldeucryptoboi.substack.com/p/claude-code-leak)
- The multi-agent research system saves its plan to external memory because context beyond 200k tokens is truncated. Fresh subagents can then retrieve the plan. [OFFICIAL] — [How we built our multi-agent research system (Jun 13, 2025)](https://www.anthropic.com/engineering/multi-agent-research-system)

### Inferences
- Anthropic moved from tool-retrieved memory toward retrieval plus a curated, user-editable topic store. Over the same period the scoping got stricter (project isolation, sensitive-category exclusion, never-save categories) and admin semantics got clearer.
- autoDream's gates (time, session-count, and lock) are a cheap, practical pattern for periodic memory compaction in a queued system.

### Gaps
- No official engineering write-up of the 2026 "topic" memory internals (extraction prompts, dedup, conflict resolution) was found.
- Claude Code's CLAUDE.md hierarchy and auto-memory details were not re-fetched. See [code.claude.com/docs/en/memory](https://code.claude.com/docs/en/memory) [KNOWN-OFFICIAL, not re-fetched].

---

## Q4. Scheduling and triggers (scheduled tasks/routines, hooks, Duet check-ins)

### Takeaway
Anthropic has three trigger planes. (1) **Cloud scheduled tasks**: schedule (recurring or one-off), API `/fire` with a per-task bearer token, and GitHub events. Each fire starts a fresh, autonomous, auto-mode session that runs under the user's identity. (2) Consumer scheduled tasks in unified Claude, plus Duet's daily brief and interval checks. (3) **Hooks** inside the Claude Code loop for deterministic policy. A notable security design: fired prompts are treated as pre-authorized assignments and **not as live consent**, and API payload text arrives wrapped as untrusted data.

### Cited Findings
- A scheduled task can combine triggers: a nightly schedule, an API call from a deploy script, and a reaction to every new PR. Tasks are managed at claude.ai/code/routines or with `/schedule` (alias `/routines`). They are a research preview on Pro/Max/Ultra/Team/Enterprise. Owners can disable them org-wide. [OFFICIAL] — [Claude Code docs: scheduled tasks](https://code.claude.com/docs/en/routines)
- "Scheduled tasks run autonomously as full Claude Code cloud sessions." Tasks belong to the individual account and are not shared. "Anything a scheduled task does through your connected GitHub identity or connectors appears as you." [OFFICIAL] — [scheduled tasks](https://code.claude.com/docs/en/routines)
- Consent semantics: "When a trigger fires, the session receives the saved prompt as its assignment… The trigger attests only that the prompt was stored ahead of time by an authorized session… The fired prompt is therefore not live user input and can't act as approval or consent for actions during the run." [OFFICIAL] — [scheduled tasks](https://code.claude.com/docs/en/routines)
- API trigger: POST to `/fire` with a per-task bearer token (shown once, rotatable, scoped to that task) and an optional free-text `text` field. The text arrives inside a `<routine-fire-payload>` block labeled as untrusted data, and Claude does not follow instructions in it unless the saved prompt opts in. The response returns a session ID and URL. The endpoint sits behind the beta header `experimental-cc-routine-2026-04-01`. [OFFICIAL] — [scheduled tasks](https://code.claude.com/docs/en/routines)
- GitHub triggers never reuse a session: two PR updates start two independent sessions. Webhook events are subject to per-task and per-account hourly caps, and excess events are dropped. PR filters include base/head branch and labels. [OFFICIAL] — [scheduled tasks](https://code.claude.com/docs/en/routines)
- Each run clones the repos fresh and pushes to `claude/`-prefixed branches by default. Branch protection is delegated to GitHub rulesets. [OFFICIAL] — [scheduled tasks](https://code.claude.com/docs/en/routines)
- Run outcomes (Succeeded, Failed, Cancelled) describe how the session ended, "not whether Claude completed the work." Blocked network requests and missing tools show up only in the transcript. `/schedule why did my nightly review do nothing…` reads run logs and explains them. [OFFICIAL] — [scheduled tasks](https://code.claude.com/docs/en/routines)
- Connectors: all connected connectors are included by default, including write access, and users are told to remove the ones they don't need. Connector traffic routes through Anthropic servers and bypasses the sandbox egress allowlist. [OFFICIAL] — [scheduled tasks](https://code.claude.com/docs/en/routines)
- Third-party guides report a one-hour minimum interval and a daily run cap. [3P] — [MakerKit guide](https://makerkit.dev/blog/tutorials/claude-code-routines-guide); [agentsroom.dev](https://agentsroom.dev/blog/claude-code-routines-cloud-vs-local-scheduled-tasks). Local alternatives (Desktop scheduled tasks, `/loop`) run only while the machine is awake. [3P] — [agentsroom.dev](https://agentsroom.dev/blog/claude-code-routines-cloud-vs-local-scheduled-tasks)
- Consumer: unified Claude supports recurring work, such as a weekly report "that Claude starts without being prompted." [OFFICIAL] — [Cowork is now Claude](https://claude.com/blog/cowork-is-now-claude). Duet has a morning brief plus interval checks. [OFFICIAL] — [Get started with Claude Duet](https://support.claude.com/en/articles/17631495-get-started-with-claude-duet)
- Hooks: deterministic shell or handler callbacks at lifecycle events (PreToolUse, PostToolUse, UserPromptSubmit, Stop, SubagentStop, SessionStart, PreCompact, Notification, and others) that can block, allow, or annotate. [KNOWN-OFFICIAL, not re-fetched] — [Claude Code hooks docs](https://code.claude.com/docs/en/hooks). In 2026 a PostToolUse hook can attach `classifierContext` that the auto-mode classifier reads. [OFFICIAL] — [Permission modes](https://code.claude.com/docs/en/permission-modes)
- Security failure: project-local `.claude/settings.json` hooks could run before the "Do you trust this folder?" prompt. The fix deferred parsing and execution of project config until after the user accepts trust. [OFFICIAL] — [How we contain Claude](https://www.anthropic.com/engineering/how-we-contain-claude)
- The leaked KAIROS used `<tick>` prompts, GitHub webhook subscriptions, and push notifications. [LEAK] — [claudefa.st](https://claudefa.st/blog/guide/mechanics/claude-code-source-leak)

### Inferences
- The "trigger is not consent" rule plus the "payload is untrusted data" wrapper is the most transferable idea for OpenCompany's scheduled automations and Telegram/webhook triggers.
- Anthropic chose a fresh session per fire, not a long-lived conversation, for coding triggers. Duet, by contrast, keeps a persistent thread-and-memory relationship. These are two distinct models, and OpenCompany probably needs both: stateless automation runs and stateful assistant threads.

### Gaps
- Official minimum intervals and caps were not found in the fetched portion of the docs. The 1h minimum is third-party.

---

## Q5. Sandboxing (Cowork VM, Claude Code bubblewrap/Seatbelt, web sandbox, Managed Agents)

### Takeaway
Anthropic publishes three isolation patterns: (1) ephemeral **gVisor** containers server-side (claude.ai code execution); (2) an OS-level **Seatbelt/bubblewrap** sandbox with human-in-the-loop on the user's machine (Claude Code); (3) a full **VM** (Cowork, originally local via Apple Virtualization/HCS, cloud since Oct 2026). The cross-cutting lessons are to keep credentials outside the sandbox and reach them only through a proxy, that every allowlisted domain is attack surface, and that custom glue (proxies) is the weakest layer.

### Cited Findings
- claude.ai: gVisor container on isolated infrastructure. "The agent is entirely server-side; no code runs on the local machine." Per-session ephemeral filesystem. Lesson: "the weakest layer is the one you built yourself." The custom proxy failed in the most consequential incident. [OFFICIAL] — [How we contain Claude across products (May 25, 2026)](https://www.anthropic.com/engineering/how-we-contain-claude); link post in [Simon Willison (May 30, 2026)](https://simonwillison.net/2026/May/30/how-we-contain-claude/)
- Claude Code: Seatbelt (macOS) or bubblewrap (Linux). Reads allowed, writes only inside the workspace, network denied by default. This cut permission prompts by 84%, and the runtime is open source. A reference devcontainer supports unattended operation. [OFFICIAL] — [How we contain Claude](https://www.anthropic.com/engineering/how-we-contain-claude); original detail in [Claude Code sandboxing (Oct 2025)](https://www.anthropic.com/engineering/claude-code-sandboxing) [KNOWN-OFFICIAL, not re-fetched]
- Red team (Feb 2026): a phished employee ran a prompt that led Claude to read `~/.aws/credentials` and POST it externally. It succeeded in 24 of 25 retries, and "only egress controls and filesystem boundaries would have stopped it." Anthropic added canary strings to Slack threads to detect agents ingesting the payload. [OFFICIAL] — [How we contain Claude](https://www.anthropic.com/engineering/how-we-contain-claude)
- Cowork local VM: Apple Virtualization (macOS) or HCS (Windows), with its own kernel, filesystem, and process table. Only the selected workspace and `.claude` are mounted. "Credentials stay in the host's keychain and never enter the guest machine," and each session gets a revocable scoped token. File modes are read-only, read-write, and read-write-no-delete. Symlinks are resolved before path validation. Enterprises can set MDM mount allowlists. [OFFICIAL] — [How we contain Claude](https://www.anthropic.com/engineering/how-we-contain-claude)
- Cowork exfiltration: the egress allowlist permitted api.anthropic.com. An attacker's file plus an attacker-owned API key uploaded workspace files through the Files API. The fix was an in-VM MITM proxy that passes only the VM's own session token. "Every function reachable through any domain on an allowlist is now an attack surface." [OFFICIAL] — [How we contain Claude](https://www.anthropic.com/engineering/how-we-contain-claude); the public disclosure appears to be [Simon Willison, "Claude Cowork Exfiltrates Files" (Jan 14, 2026)](https://simonwillison.net/2026/Jan/14/claude-cowork-exfiltrates-files/) (I did not fetch the full post; the link between the two is my inference)
- Visibility trade-off: "the same isolation keeping Claude contained also kept host-based endpoint detection and response out." The mitigation was pull-based OTLP log export. [OFFICIAL] — [How we contain Claude](https://www.anthropic.com/engineering/how-we-contain-claude)
- Third-party RE: Claude Code, asked to report on its own Cowork environment, found an Ubuntu 22.04 VM with bubblewrap inside the VM (own network and PID namespaces) and socat proxy forwarders. [3P-RE] — [Simon Willison gist](https://gist.github.com/simonw/35732f187edbe4fbd0bf976d013f22c8). Users complained the local VM bundle was about 10GB on macOS. [3P] — [lilting.ch](https://lilting.ch/en/articles/claude-code-cowork-vm-macos)
- Cloud Cowork (Oct 2026): inference and the VM run in the cloud, with a per-session sandbox. Device files are fetched as copies through the desktop app and deleted with the session. Local MCP servers and local connectors work only in the desktop app. [OFFICIAL-QUOTE + OFFICIAL] — [Simon Willison / Felix Rieseberg](https://simonwillison.net/2026/Oct/5/felix-rieseberg/); [Use Claude Cowork on web, desktop, and mobile](https://support.claude.com/en/articles/15520349-use-claude-cowork-on-web-desktop-and-mobile)
- Cloud Claude Code environments: the default "Trusted" network allows only a default allowlist. Blocked hosts return `403` with `x-deny-reason: host_not_allowed`. Secrets should be stored as "network secrets," not environment variables, because env vars are visible to anyone using the environment. [OFFICIAL] — [scheduled tasks](https://code.claude.com/docs/en/routines)
- Managed Agents: "tokens are never reachable from the sandbox." Git tokens are used only at init time to clone. MCP OAuth tokens live in a vault, and a proxy uses a session-scoped token to call services, so the harness never sees credentials. Rationale: in the coupled design, a prompt injection only had to get Claude to read its environment. [OFFICIAL] — [Scaling Managed Agents](https://www.anthropic.com/engineering/managed-agents)
- Duet: passwords are typed into a separate on-device form and sent to the browser, which keeps them out of chat. [OFFICIAL] — [Get started with Claude Duet](https://support.claude.com/en/articles/17631495-get-started-with-claude-duet). Press says Duet's credential handling is modeled on Managed Agents. [PRESS] — [VKTR](https://www.vktr.com/ai-platforms/claude-duet-anthropic-previews-an-ai-agent-that-messages-you-first/)
- Anthropic did not ship Claude Mythos Preview in April 2026 because its blast radius was judged too high. [OFFICIAL, per fetched summary] — [How we contain Claude](https://www.anthropic.com/engineering/how-we-contain-claude)

### Inferences
- Anthropic's sandbox story has converged on two rules: **secrets outside the blast radius, behind a proxy**, and **default-deny egress plus audited exceptions**. The OS/VM layer is commodity. The proxies and allowlists are where things fail.

### Gaps
- The cloud Cowork VM technology (gVisor? Firecracker? hypervisor type) is not documented. The support page refers to a separate architecture overview that I did not locate.

---

## Q6. Multi-agent setup (subagents, orchestrator-worker)

### Takeaway
Anthropic's canonical multi-agent design is **orchestrator-worker**: a lead agent plans, spawns parallel subagents with clean contexts and detailed task briefs, and synthesizes the results, with a separate citation pass. It is expensive (about 15x chat tokens) and suits breadth-first, parallelizable tasks. Claude Code subagents add per-subagent tool restrictions, and in auto mode a classifier checks each subagent at spawn, at every action, and at completion.

### Cited Findings
- Research system: an Opus 4 lead with Sonnet 4 subagents beat single-agent Opus 4 by 90.2% on an internal eval. Token usage explained 80% of BrowseComp variance (95% together with tool-call count and model). Agents use about 4x chat tokens, multi-agent about 15x. Running 3–5 subagents in parallel, each making 3+ parallel tool calls, cut research time by up to 90%. [OFFICIAL] — [How we built our multi-agent research system](https://www.anthropic.com/engineering/multi-agent-research-system)
- Lessons: give subagents detailed task descriptions (objective, output format, tool guidance, boundaries) to avoid duplicate work; scale effort to complexity (simple tasks get 1 agent and 3–10 tool calls); have subagents write outputs to external storage and pass back lightweight references; use durable execution with checkpoints and resumption, plus rainbow deploys. [OFFICIAL] — [multi-agent research system](https://www.anthropic.com/engineering/multi-agent-research-system)
- Stated limitations: subagents run synchronously, so the lead can't steer them, they can't coordinate, and one slow subagent blocks the rest. The approach fits poorly with tasks that need shared context or have many dependencies, "such as most coding work." [OFFICIAL] — [multi-agent research system](https://www.anthropic.com/engineering/multi-agent-research-system)
- Auto-mode classifier on subagents: it evaluates the delegated task description at spawn, checks each action during execution (a subagent's frontmatter `permissionMode` is ignored), and reviews the work and final report on completion. A flagged report is still delivered, with a security warning prepended. [OFFICIAL] — [Claude Code permission modes](https://code.claude.com/docs/en/permission-modes)
- `Agent` allow rules are dropped when entering auto mode, so subagent spawning always goes through the classifier. [OFFICIAL] — [permission modes](https://code.claude.com/docs/en/permission-modes)
- Leak: three subagent execution models, Fork, Teammate, and Worktree. [LEAK] — [claudefa.st](https://claudefa.st/blog/guide/mechanics/claude-code-source-leak). ULTRAPLAN offloads planning to a remote container for up to 30 minutes; the local client polls every 3s and the user approves in a browser UI. [LEAK] — [claudefa.st](https://claudefa.st/blog/guide/mechanics/claude-code-source-leak)
- Managed Agents: "many brains, many hands," and brains can pass hands to each other. [OFFICIAL] — [Scaling Managed Agents](https://www.anthropic.com/engineering/managed-agents)
- Claude Code subagents are defined as Markdown with YAML frontmatter in `.claude/agents/`. Each has its own context window, system prompt, and tool allowlist. [KNOWN-OFFICIAL, not re-fetched] — [Claude Code subagents docs](https://code.claude.com/docs/en/sub-agents)

### Inferences
- Delegation is a security boundary as well as a context boundary. Anthropic reviews the delegated brief and the returned report, and treats subagent output as potentially tainted data.

### Gaps
- Claude Code "agent teams" and cross-session messaging (the permission-modes page mentions "cross-session messaging safeguards") were not researched in depth.

---

## Q7. Context management (compaction, context editing, skills, context engineering)

### Takeaway
Anthropic treats context as a scarce resource. Its tools are compaction or summarization, **context editing** (automatic removal of stale tool results), external memory files and structured notes, sub-agents with clean contexts, just-in-time retrieval, **progressive disclosure through Skills**, and presenting MCP tools as code. The 2026 Managed Agents design adds the session log as a context object outside the window, so trimming decisions are reversible.

### Cited Findings
- Context editing automatically clears stale tool calls and results as the agent nears its limit (84% token reduction in a 100-turn eval). Sonnet 4.5 added built-in context awareness. [OFFICIAL] — [Claude blog: context management](https://claude.com/blog/context-management)
- Managed Agents: the session log "lives outside Claude's context window." The harness can transform fetched events before they reach the model (for example, to improve prompt-cache hits). Separating storage from context management "avoids irreversible compaction or trimming decisions." [OFFICIAL] — [Scaling Managed Agents](https://www.anthropic.com/engineering/managed-agents)
- Effective context engineering: "context rot," the smallest set of high-signal tokens, compaction, structured note-taking (for example a NOTES.md), sub-agent architectures, and just-in-time retrieval through lightweight identifiers. [KNOWN-OFFICIAL, not re-fetched] — [Effective context engineering for AI agents (Sep 2025)](https://www.anthropic.com/engineering/effective-context-engineering-for-ai-agents)
- Agent Skills: folders with a `SKILL.md`. Only the name and description sit in the system prompt, and the body and extra files load on demand ("progressive disclosure"). [KNOWN-OFFICIAL, not re-fetched] — [Equipping agents for the real world with Agent Skills (Oct 2025)](https://www.anthropic.com/engineering/equipping-agents-for-the-real-world-with-agent-skills)
- Code execution with MCP reduced tool-definition and intermediate-result overhead from about 150k to about 2k tokens. [KNOWN-OFFICIAL, not re-fetched] — [Code execution with MCP](https://www.anthropic.com/engineering/code-execution-with-mcp)
- Leak: five distinct compaction strategies (unnamed in the analysis) and 14 cache-break vectors tracked. [LEAK] — [claudefa.st](https://claudefa.st/blog/guide/mechanics/claude-code-source-leak)
- Multi-agent research: plans are persisted to memory before the 200k-token truncation, and fresh subagents continue the work. [OFFICIAL] — [multi-agent research system](https://www.anthropic.com/engineering/multi-agent-research-system)

### Inferences
- The progression runs from summarizing the transcript (lossy) to editing tool results to keeping a durable log and building the model view per call. That last step is the most robust design for a database-backed runtime like OpenCompany's.

### Gaps
- Official per-surface compaction thresholds and algorithms for Claude Code were not found.

---

## Q8. Safety (permission modes, approvals, prompt-injection defenses)

### Takeaway
Anthropic uses layered defenses: OS/VM containment, permission rules, an **auto-mode classifier that cannot see tool results**, repeated-block circuit breakers, human approval for irreversible actions (Duet: send, pay, sign, delete), hard-blocked action classes even with approval, RL training plus classifiers against prompt injection in browser agents, and per-site permissions. Anthropic says plainly that each layer, the classifier included, is insufficient on its own.

### Cited Findings
- Claude Code at launch: about 93% of permission prompts were approved, which caused approval fatigue. The sandbox cut prompts by 84%. Auto mode's classifier blocks about 0.4% of benign commands and catches about 83% of overeager actions. It is "one layer inside a sandbox, not a replacement." [OFFICIAL] — [How we contain Claude](https://www.anthropic.com/engineering/how-we-contain-claude)
- Modes: `default`, `acceptEdits`, `plan`, `auto`, `dontAsk` (CI: anything that would prompt is denied), and `bypassPermissions` (isolated containers or VMs only). Deny rules block in every mode. Protected paths (`.git`, `.claude`, `.mcp.json`, and others) are never auto-approved outside bypass. [OFFICIAL] — [Claude Code permission modes](https://code.claude.com/docs/en/permission-modes)
- Auto-mode evaluation order: rules first, then read-only and in-workspace edits auto-approved, then the classifier. A blocked action returns the reason (for example `[Data Exfiltration]`) to Claude. The classifier sees user messages, tool calls, and CLAUDE.md, but **tool results are stripped**, "so hostile content in a file or web page can't directly manipulate the classifier." [OFFICIAL] — [permission modes](https://code.claude.com/docs/en/permission-modes)
- Circuit breaker: 3 consecutive blocks or 20 total pauses auto mode and resumes prompting. The thresholds are not configurable. Broad allow rules (`Bash(*)`, wildcard interpreters, `Agent`) are dropped on entry to auto mode. Default blocks include `curl | bash`, production deploys, force push, `terraform destroy`, merging unapproved PRs, and sending secrets outside the repository. [OFFICIAL] — [permission modes](https://code.claude.com/docs/en/permission-modes)
- Auto mode launched around March 24, 2026. [PRESS] — [TechRadar](https://techradar.com/pro/anthropic-gives-claude-code-new-auto-mode-which-lets-it-choose-its-own-permissions)
- Scheduled runs start in auto mode and stop for ask-rule matches or repeated blocks, waiting for a human in the run's session. [OFFICIAL] — [scheduled tasks](https://code.claude.com/docs/en/routines)
- Claude for Chrome (Aug 2025): 123 test cases across 29 attack scenarios. Attack success was 23.6% without mitigations and 11.2% with them. On a set of four browser-specific attack types it fell from 35.7% to 0%. Defenses: per-site permissions, confirmations for high-risk actions, and blocked categories of sites. [OFFICIAL] — [Anthropic: Piloting Claude for Chrome](https://www.anthropic.com/news/claude-for-chrome)
- Prompt-injection defenses (Nov 2025): RL training on simulated injected content, classifiers that scan untrusted content entering context, and human red teaming. Against an adaptive Best-of-N attacker (100 attempts), about a 1% attack success rate remained, which "still represents meaningful risk." [OFFICIAL] — [Anthropic: prompt injection defenses](https://www.anthropic.com/research/prompt-injection-defenses)
- A third-party summary claims a 0–0.3% attack success rate at Chrome GA. [PRESS, unverified] — [AI Understanding](https://aiunderstanding.org/news/anthropic-makes-claude-in-chrome-generally-available-with-autonomous-browser-actio)
- Duet asks before sending messages, paying, signing, or deleting. If the user can't approve right away, it finishes the rest of the task and returns the approval request later. It cites the source email or file for any numbers it reports. Some actions are off-limits even with approval: trading securities, moving money between accounts, and accessing someone else's account. Each purchase uses a one-time card. It treats email and web content as information but "can still be affected by prompt injection, so approvals matter," and it aims to follow sites' agent rules. [OFFICIAL] — [Get started with Claude Duet](https://support.claude.com/en/articles/17631495-get-started-with-claude-duet)
- Duet also has a classifier and safety system prompt to refuse sharing card numbers or government IDs, prompt-injection defenses drawn from browser-use research, and red teaming plus bug bounty. [PRESS] — [VKTR](https://www.vktr.com/ai-platforms/claude-duet-anthropic-previews-an-ai-agent-that-messages-you-first/)
- Tool outputs in Claude Code and Cowork pass through proxies that can inspect results before they reach the model. Remote connectors outside the reviewed directory should be treated as untrusted. [OFFICIAL] — [How we contain Claude](https://www.anthropic.com/engineering/how-we-contain-claude)
- Leaked: `bashSecurity.ts` has 23 numbered checks on every shell command, and anti-distillation "fake tools" are gated by a GrowthBook flag. [LEAK] — [claudefa.st](https://claudefa.st/blog/guide/mechanics/claude-code-source-leak)

### Inferences
- Two of Anthropic's cheapest and most transferable ideas: **non-blocking approvals** (Duet finishes everything else and queues the approval) and a **policy judge that never sees untrusted tool output**.

### Gaps
- No primary-source Chrome GA safety numbers were found this session.

---

## Q9. Leaks, system prompts, and reverse-engineering sources: what's reliable?

### Takeaway
The main 2026 leak is the Claude Code v2.1.88 source map (Mar 31, 2026). It is real, but it is known only through secondary analyses that disagree on figures, and flagged features (KAIROS, autoDream, ULTRAPLAN, Undercover Mode) are unconfirmed product plans. Anthropic publishes claude.ai system prompts officially, and community repositories mirror extracted prompts for other surfaces.

### Cited Findings
- Leak facts and the timeline are covered in Q2. The leak also exposed internal model codenames (Capybara, Fennec, Numbat) and "Undercover Mode," which strips Co-Authored-By attribution from commits. [LEAK/PRESS] — [Let's Data Science](https://letsdatascience.com/news/anthropic-leaks-claude-code-source-exposes-roadmap-e8ec5d23); [oldeucryptoboi](https://oldeucryptoboi.substack.com/p/claude-code-leak)
- The community "claude-code-analysis" project is an 18-chapter walkthrough pinned to the leaked snapshot. [3P-RE] — [x-cmd claude-code-analysis](https://x-cmd.com/install/claude-code-analysis)
- Anthropic publishes claude.ai/app system prompts in its release notes. [KNOWN-OFFICIAL, not re-fetched] — [System prompt release notes](https://docs.claude.com/en/release-notes/system-prompts). Community-extracted prompts for other surfaces are collected in [CL4R1T4S](https://github.com/elder-plinius/CL4R1T4S) [LEAK, not re-fetched; reliability varies by file].
- Simon Willison reverse-engineered his Cowork environment by having Claude introspect it. [3P-RE] — [gist](https://gist.github.com/simonw/35732f187edbe4fbd0bf976d013f22c8)

### Inferences
- Treat leaked flags as signals of intent. The design patterns in them (tick-driven proactive loops, an append-only agent log the agent can't erase, gated idle-time memory consolidation) are worth copying whether or not they ship.

### Gaps
- I did not obtain a Duet system prompt, Cowork system prompt, or 2026 claude.ai system prompt text this session.

---

## Q10. Design lessons for OpenCompany

### Takeaway
OpenCompany's feature set (chat, tasks, approvals, Telegram, scheduled automations, MCP tools, memory, subagent delegation) already matches the union of Duet, unified Claude, and Claude Code scheduled tasks. The lessons below are mostly about **contracts and boundaries**, not new features.

### Cited Findings
- (See the citations in Q1–Q8. Each lesson below points to the finding it draws on.)

### Inferences
1. **Durable session log as source of truth; stateless runner** (Managed Agents). Persist every agent-run event (model turn, tool call, tool result, approval request) in an append-only table. Make runs resumable with `wake(runId)` from the last event after a queue failure, and build the context window per turn from that log, so compaction never destroys data. [Q2/Q7]
2. **Trigger is not consent** (scheduled tasks). A scheduled automation, Telegram message, or webhook can *start* a run, but it never counts as approval for gated actions during the run. Wrap trigger payloads (webhook bodies, forwarded messages, alert text) in a labeled untrusted-data block that the agent acts on only if the stored automation prompt opts in. [Q4]
3. **Two run shapes**: stateless automation runs (fresh session per fire, Claude Code-style, with per-run transcripts and an outcome that is not success) and stateful assistant threads (Duet-style main thread plus topic channels over one shared memory). Keep both explicit in the data model. [Q1/Q4]
4. **Non-blocking approvals** (Duet). When an action needs approval, the agent should finish everything else, park the approval in the approvals inbox and Telegram, and resume on decision. Keep a hard-deny list (money movement, cross-account access) that no approval can unlock. A task should be marked complete only by a human where appropriate. [Q8]
5. **A policy judge that never sees tool results** (auto mode). If OpenCompany adds an LLM-based auto-approval layer, feed it the user intent, the tool call, and workspace policy, and strip tool outputs so injected content can't steer it. Add circuit breakers (e.g., 3 consecutive or 20 total denials → pause and escalate to a human) and drop broad allow rules while auto-approval is on. [Q8]
6. **Credentials never in the agent's reach** (Managed Agents, Cowork, Duet). MCP OAuth tokens and integration secrets go in a vault. Tools call through a server-side proxy with run-scoped tokens. Never inject secrets into the prompt, VFS, or sandbox env. Workspace scoping applies at the proxy. [Q5]
7. **Default-deny egress, and audit allowlisted domains for "functions reachable"** (the Cowork Files API exfiltration). Any tool or VFS exec that can make HTTP calls should go through an allowlist proxy that pins credentials to the workspace. Attacker-supplied keys must not work through it. [Q5]
8. **Trust before config execution** (the pre-trust hooks bug). Workspace-supplied automations, hooks, or MCP configs must not execute until the workspace or the user has explicitly trusted them. [Q4]
9. **Subagent delegation is a security boundary**. Evaluate the delegated brief at spawn, enforce the parent's policy on subagent actions (ignore subagent-declared escalations), and review or label subagent reports as untrusted before the parent consumes them. Use detailed briefs and lightweight references to stored outputs. [Q6]
10. **Proactivity is a scheduler plus a judgment policy, not a daemon**. Duet and KAIROS both use periodic ticks or checks with a bounded budget per tick. OpenCompany can implement "always-on" as scheduled automations that run a triage prompt over connected sources, with an explicit urgency or escalation policy, a daily brief, and push to Telegram. Bill or meter background work the same way as interactive work. [Q1/Q4]
11. **Memory governance**: per-project or per-workspace scoping, user-visible and editable memory topics, never-save categories, a per-run "no memory" toggle, and gated idle-time consolidation (autoDream-style: time, count, and lock gates, with a size cap on the index). Note that deleting a conversation does not delete derived memories, so the UX must say so. [Q3]
12. **Progressive disclosure for tools and skills**. Put only names and descriptions in the system prompt and load bodies on demand. Consider MCP-as-code or VFS-exposed tool APIs to keep large MCP catalogs out of context. [Q7]
13. **Make visibility a first-class feature**. Isolation hides agents from monitoring (the Cowork EDR gap). Export run telemetry (OTLP-style) and keep per-run transcripts, as scheduled tasks do with run history and the "why did my run do nothing" explainer. [Q4/Q5]

### Gaps
- These lessons are my inferences from Anthropic's disclosures. I did not check them against OpenCompany's current code in this note.
