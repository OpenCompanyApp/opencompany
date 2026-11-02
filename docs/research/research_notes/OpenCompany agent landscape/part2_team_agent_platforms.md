# Team / Company-Level Agent Platforms (OSS) — Comparables to OpenCompany

Research date: 2026-11-02. GitHub stars, `pushed_at`, SPDX license and repo descriptions were pulled live via the GitHub REST API (`gh api repos/<owner>/<repo>`) on 2026-11-02; each figure cites the corresponding `https://api.github.com/repos/<owner>/<repo>` endpoint. License details for repos the API reports as "Other"/NOASSERTION come from each repo's LICENSE file, read on 2026-11-02.

OpenCompany reference profile, for comparison only (from the brief, not external research): self-hosted Laravel 12 + Vue 3 + Inertia workspace. Humans and durable AI agents share chat, channels, tasks, docs, tools/MCP, approvals, Telegram, scheduled automations, subagent delegation and memory. Licensed AGPL-3.0-only, with a proprietary Cloud.

## Key Question 1: "AI company" orchestrators — which are positioned as persistent agent teams with roles, org charts, budgets, approvals, and tickets?

### Takeaway
Paperclip (MIT, ~110k stars, launched March 2026) is the dominant OSS "AI company" product. It has org charts, titles, budgets, goals, tickets, approval gates, heartbeats/routines, multi-company tenancy and an MCP tool gateway, which makes it the closest conceptual competitor to OpenCompany's agent-governance layer. The classic "AI software company" research projects (ChatDev, MetaGPT, AgentVerse) and dev frameworks (CrewAI, AutoGen/AG2, Microsoft Agent Framework, CAMEL/Eigent, Agency Swarm, Swarms) are libraries or one-shot pipelines rather than persistent human+agent workspaces. Dify, Langflow, n8n, Flowise and Activepieces are workflow/agent builders, not org-shaped workspaces.

### Cited Findings

**Paperclip (paperclipai/paperclip): the most direct "AI company" competitor**
- 110,010 stars, pushed 2026-11-02, MIT, TypeScript; description: "The open-source app everyone uses to manage agents at work" — [GitHub API](https://api.github.com/repos/paperclipai/paperclip)
- Tagline: "Open-source orchestration for teams of AI agents"; "If OpenClaw is an _employee_, Paperclip is the _company_." Node.js server plus React UI; you bring your own agents — [GitHub README](https://github.com/paperclipai/paperclip)
- Org charts: agents have reporting lines, titles and permissions ("Your agents have a boss, a title, and a job description") — [GitHub README](https://github.com/paperclipai/paperclip)
- Budgets: company, agent and project budgets with alerts; work pauses at limits. Goals: tasks link to org goals so agents get context — [GitHub README](https://github.com/paperclipai/paperclip)
- Tasks/tickets: task threads with comments, plans, files and run history, assignable to agents or people. Approvals/governance: review and approval stages, approving "hires", pause/reassign/stop work — [GitHub README](https://github.com/paperclipai/paperclip)
- Heartbeats ("If it can receive a heartbeat, it's hired"). Routines on cron, webhook or API triggers, each run creating a tracked task. Skill Studio for versioned shared skills. Multi-company ("One deployment, many organizations"). Mobile management — [GitHub README](https://github.com/paperclipai/paperclip)
- Supported agent runtimes: Claude Code, Codex, OpenClaw, Cursor, Gemini CLI, OpenCode, Pi, Hermes, Grok Build, Kimi Code, plus custom processes, HTTP endpoints and adapter plugins. MCP via an "MCP Tool Gateway" and BYO MCP servers. A2A is not mentioned. "acpx" appears only as a repo topic — [GitHub README](https://github.com/paperclipai/paperclip)
- Stack: Node.js 24.11+, pnpm, React, PostgreSQL (embedded for local, BYO in prod), optional Rust native runner — [GitHub README](https://github.com/paperclipai/paperclip)
- Business model: MIT, copyright Paperclip Labs, Inc. (2026). "No Paperclip account required." A hosted "Paperclip Cloud" is on a waitlist — [GitHub README](https://github.com/paperclipai/paperclip)
- Reported launch March 2, 2026 by pseudonymous developer "@dotta" — [Techleap Finder](https://finder.techleap.nl/news/note/paperclip-the-open-source-framework-turning-ai-agents-into-companies). Star counts reported at different dates vary (30k, then 71k in under 4 months) — [fast.io review](https://fast.io/resources/paperclip-ai-review-2026/). One writeup cautions that the "zero-human company" framing sets unrealistic expectations — [MindStudio](https://www.mindstudio.ai/blog/what-is-paperclip-zero-human-ai-company-framework). Many secondary sources are promotional or affiliate content.

**ChatDev / MetaGPT / AgentVerse (research "AI software company" lineage)**
- ChatDev (OpenBMB): 34,639 stars, pushed 2026-10-30, Apache-2.0, Python; "ChatDev 2.0: Dev All through LLM-powered Multi-Agent Collaboration" — [GitHub API](https://api.github.com/repos/OpenBMB/ChatDev)
- MetaGPT: 71,129 stars, last push 2026-01-21 (about 10 months stale), MIT; "The Multi-Agent Framework: First AI Software Company, Towards Natural Language Programming" — [GitHub API](https://api.github.com/repos/FoundationAgents/MetaGPT)
- AgentVerse: 5,168 stars, last push 2024-09-09 (dormant), Apache-2.0 — [GitHub API](https://api.github.com/repos/OpenBMB/AgentVerse)

**CrewAI (+ AMP)**
- crewAI: 60,400 stars, pushed 2026-11-01, MIT; "Framework for orchestrating role-playing, autonomous AI agents" — [GitHub API](https://api.github.com/repos/crewAIInc/crewAI)
- AMP is the hosted deploy/observability/governance product: free Basic tier (about 50 executions/month; one tracker reports a later cut), custom Enterprise (SSO, RBAC, PII redaction), and a self-hosted "Factory" option. This is aggregator-sourced and may be dated — [AutomationAtlas](https://automationatlas.io/answers/crewai-pricing-explained-2026/); [UsagePricing](https://usagepricing.com/blueprint/crewai)
- First-class A2A delegation: agents can delegate to remote A2A agents and serve as A2A servers via `crewai[a2a]` — [CrewAI docs](https://docs.crewai.com/v1.11.0/en/learn/a2a-agent-delegation)

**AutoGen / AG2 / Microsoft Agent Framework**
- microsoft/autogen: 61,669 stars, pushed 2026-10-28, license reported CC-BY-4.0 (docs license as detected by GitHub) — [GitHub API](https://api.github.com/repos/microsoft/autogen)
- AG2 (community fork, "The Open-Source AgentOS"): 5,040 stars, Apache-2.0, active — [GitHub API](https://api.github.com/repos/ag2ai/ag2)
- Microsoft Agent Framework: 14,487 stars, pushed 2026-11-02, MIT, Python + .NET — [GitHub API](https://api.github.com/repos/microsoft/agent-framework). 1.0 GA on April 3, 2026 — [Microsoft Tech Community](https://techcommunity.microsoft.com/blog/azuredevcommunityblog/the-future-of-agentic-ai-inside-microsoft-agent-framework-1-0/4510698). Reported to ship native A2A + MCP — [AgenticWire](https://www.agenticwire.news/article/microsoft-agent-framework-1-0-workflows-mcp). AG-UI compatible — [CopilotKit](https://copilotkit.ai/blog/microsoft-agent-framework-is-now-ag-ui-compatible). Reported to merge Semantic Kernel and AutoGen into one SDK; secondary source only — [Machine Herald](https://machineherald-io.pages.dev/article/2026-04/14-microsoft-ships-agent-framework-10-merging-semantic-kernel-and-autogen-into-a-single-production-ready-sdk)

**CAMEL / Eigent**
- Eigent: 15,714 stars, pushed 2026-11-01, Apache-2.0; "The Open Source Cowork Desktop - Local and Free Alternative to Claude Cowork and Codex" — [GitHub API](https://api.github.com/repos/eigent-ai/eigent)
- Eigent is a desktop "AI workforce" built on CAMEL. It has parallel specialized agents, MCP and skill integration, browser/terminal toolkits, scheduled workflows and local models (vLLM/Ollama/LM Studio). Stack: FastAPI + Electron/React/TS/Tailwind/Radix. Business model: free local, paid cloud and Enterprise (SSO, SLAs) — [GitHub README](https://github.com/eigent-ai/eigent)
- CAMEL: 18,008 stars, Apache-2.0 — [GitHub API](https://api.github.com/repos/camel-ai/camel). OWL: 20,167 stars, no license detected — [GitHub API](https://api.github.com/repos/camel-ai/owl)

**OpenAgents**
- 4,305 stars, pushed 2026-11-02, Apache-2.0, TypeScript; "The collaboration OS for AI agents" — [GitHub API](https://api.github.com/repos/openagents-org/openagents)
- Browser workspace "like Slack, but for agents": humans and agents share threads, files and a shared browser, with @mention delegation, channels/threads, persistent workspace URLs and a daemon that keeps agents running. Runtimes: Claude Code, Codex CLI, Cursor, OpenClaw, Gemini CLI, Aider, Goose. "MCP and A2A protocol support." Hosted workspace at openagents.org; no pricing published — [GitHub README](https://github.com/openagents-org/openagents)

**Other frameworks/orchestrators (stats only)**
- Agency Swarm: 4,625 stars, MIT, active — [GitHub API](https://api.github.com/repos/VRSEN/agency-swarm)
- Swarms: 7,341 stars, Apache-2.0, "Enterprise-Grade Multi-Agent Orchestration Framework" — [GitHub API](https://api.github.com/repos/kyegomez/swarms)
- SuperAGI: 17,725 stars, last push 2025-01-22 (dormant), MIT — [GitHub API](https://api.github.com/repos/TransformerOptimus/SuperAGI)
- AutoGPT: 187,861 stars, active. `autogpt_platform` is under the Polyform Shield License and everything else is MIT — [GitHub API](https://api.github.com/repos/Significant-Gravitas/AutoGPT); [LICENSE](https://github.com/Significant-Gravitas/AutoGPT/blob/HEAD/LICENSE)

**Workflow/agent builders**
- Dify: 160,192 stars, active. Modified Apache 2.0 that forbids operating a multi-tenant environment ("one tenant corresponds to one workspace") without written authorization and forbids removing logos — [GitHub API](https://api.github.com/repos/langgenius/dify); [LICENSE](https://github.com/langgenius/dify/blob/HEAD/LICENSE)
- Langflow: 156,242 stars, MIT — [GitHub API](https://api.github.com/repos/langflow-ai/langflow)
- n8n: 209,320 stars, "Fair-code". Sustainable Use License, with `.ee.` files under a separate enterprise license — [GitHub API](https://api.github.com/repos/n8n-io/n8n); [LICENSE.md](https://github.com/n8n-io/n8n/blob/HEAD/LICENSE.md)
- Flowise: 55,542 stars, last push 2026-08-13. Apache 2.0, except `packages/server/src/enterprise` — [GitHub API](https://api.github.com/repos/FlowiseAI/Flowise); [LICENSE.md](https://github.com/FlowiseAI/Flowise/blob/HEAD/LICENSE.md)
- Activepieces: 25,330 stars. MIT, except `packages/ee` — [GitHub API](https://api.github.com/repos/activepieces/activepieces); [LICENSE](https://github.com/activepieces/activepieces/blob/HEAD/LICENSE)

### Inferences
- **Paperclip: high threat.** It covers the same core metaphor as OpenCompany: agents as employees with roles, approvals, budgets, tickets, routines/schedules, MCP, skills and multi-tenancy. It has a permissive MIT license, a huge star lead and a planned Cloud. The difference is architectural. Paperclip orchestrates external coding-agent CLIs and processes through adapters, whereas OpenCompany owns its runtime (app-owned provider/model catalog, in-process agents, subagent delegation, memory) and has a chat/channel/docs-centric collaboration surface with Telegram. Lessons to take: the budget/pause-at-limit UX, "hire approval", goal linkage, the heartbeat model and Skill Studio versioning. Integration potential: OpenCompany agents could be exposed as a Paperclip adapter via HTTP endpoint, or OpenCompany could import Paperclip org charts.
- **OpenAgents: medium threat.** It is a Slack-for-agents workspace with A2A and MCP but low traction (4.3k stars). It is a plausible interop partner via A2A.
- **CrewAI, Microsoft Agent Framework, AG2, CAMEL, Agency Swarm, Swarms: libraries, not competitors.** They are potential "external agent" sources that OpenCompany could call over A2A or MCP.
- **Dify, n8n, Langflow, Flowise, Activepieces: indirect competitors.** Buyers may compare them for automations and agent building. Activepieces in particular markets about 400 MCP servers, which makes it a tool/MCP supply partner. Dify's ban on multi-tenant use is a notable contrast to OpenCompany's AGPL-plus-Cloud model.
- **ChatDev, MetaGPT, AgentVerse, SuperAGI: low threat.** They are mostly research or stale. MetaGPT's commercial MGX product was not verified in this research.

### Gaps
- Paperclip Cloud pricing and launch date: waitlist only, with no pricing published.
- No Hacker News thread for Paperclip turned up in search.
- CrewAI AMP pricing comes only from aggregators; crewai.com/pricing was not fetched.
- MetaGPT/MGX commercial status, ChatDev 2.0 feature details, and Agency Swarm/Swarms feature details were not fetched in depth.
- Dify, Langflow and n8n A2A support was not confirmed; searches returned nothing.

## Key Question 2: Human+agent collaborative workspaces (Slack/Notion/Linear-like) with first-class agents — which treat agents as teammates?

### Takeaway
Multica (~54.6k stars, source-available "Multica License") is the closest workspace-shaped competitor. It has named agents on an issue board alongside humans, squads, autopilots (cron), skills, workspaces with roles, review gates, cost tracking, and Telegram/Slack/Lark channels. Plane (AGPL, 61.8k stars) shipped native "Agents as workspace members" on 2026-09-18, but it appears gated to paid plans. Mattermost's Agents plugin adds multi-agent, MCP and tool-approval policy to OSS chat. Open WebUI channels put humans and models in one timeline. Gobii is an MIT "always-on AI employee" platform with email/SMS identities.

### Cited Findings

**Multica (multica-ai/multica): most direct workspace competitor**
- 54,581 stars, pushed 2026-11-02, Go; "Make humans and AI agents work as one team — open-source and self-hostable" — [GitHub API](https://api.github.com/repos/multica-ai/multica)
- "Agents that show up on the board." Agents have a name, provider and runtime, and you assign issues to them like colleagues. Squads (agents + people, with a leader routing work). Autopilots run cron jobs such as standups. Projects attach repos and docs. Workspace chat. Skills as reusable playbooks — [GitHub README](https://github.com/multica-ai/multica)
- Daemon drives installed agent CLIs (Claude Code, Codex; 26 CLIs listed); Multica "does not ship a model." Work goes to review ("You decide what ships"). Per-agent and per-issue usage-cost tracking, an inbox, and roles (owner/admin/member) with access scopes. Channels: Slack, Feishu/Lark, DingTalk, WeCom, Telegram. Clients: web, Electron, iOS — [GitHub README](https://github.com/multica-ai/multica)
- Stack: Next.js 16, Go (Chi, sqlc, gorilla/websocket), PostgreSQL 17, Electron, Expo. Hosted cloud at multica.ai plus Docker/Helm self-host — [GitHub README](https://github.com/multica-ai/multica)
- License: "Multica License" = Apache 2.0 plus conditions. No hosted service to third parties and no embedding in commercial products without a commercial license from Index Labs (Hong Kong) Limited. Logo and branding cannot be removed. Contributions carry terms — [LICENSE](https://github.com/multica-ai/multica/blob/HEAD/LICENSE)
- The README does not mention MCP, A2A or ACP — [GitHub README](https://github.com/multica-ai/multica)

**Plane (makeplane/plane): Agents as members**
- 61,838 stars, pushed 2026-10-30, AGPL-3.0 — [GitHub API](https://api.github.com/repos/makeplane/plane)
- Agents launched 2026-09-18. Admins create agents and members assign or @mention them. Triggers fire on work-item changes or schedules. Skills and templates (Triage Agent, Standup Summarizer, Spec Writer). Agents use Plane data, web and "MCP-connected tools." Boundaries return decisions "to a human." Agent credits: Pro 100/seat/month, Business 200/seat/month, Enterprise unmetered with BYO keys. "Plane AI is not currently available on Free workspaces" — [Plane blog](https://plane.so/blog/agents-are-now-live-in-plane)

**Mattermost Agents plugin**
- mattermost/mattermost-plugin-agents: 256 stars, Apache-2.0, Go, active — [GitHub API](https://api.github.com/repos/mattermost/mattermost-plugin-agents)
- Multiple admin-configured agents. Tools menu with MCP tool-provider refresh and per-user OAuth connect. Tool calls require approval under an admin policy ("Only the person who started the conversation can approve or reject tool calls"), and channel results can be kept private. Channel auto-reply requires Enterprise Advanced. Summaries require Professional or higher — [Mattermost docs](https://docs.mattermost.com/end-user-guide/agents)

**Open WebUI**
- 157,507 stars, custom "Open WebUI License" (BSD-style with extra conditions) — [GitHub API](https://api.github.com/repos/open-webui/open-webui); [LICENSE](https://github.com/open-webui/open-webui/blob/HEAD/LICENSE)
- Channels are persistent shared spaces where several humans and AI models participate in one timeline — [Open WebUI docs](https://docs.openwebui.com/features/channels.md)

**Gobii**
- 497 stars, last push 2026-08-21, MIT; "Your easy to use, always-on AI workforce" — [GitHub API](https://api.github.com/repos/gobii-ai/gobii-platform)
- Always-on agents wake on schedules and on email, SMS, webhook and API events, via a durable per-agent queue. Each agent can have its own email and phone identity. Built on browser-use. Agent-to-agent messaging. Kubernetes/gVisor sandboxing. Organization API keys. Hosted MCP server (16 tools). MIT core, but "Proprietary mode and non-MIT components require a commercial agreement." Gobii Cloud is the managed offering — [GitHub README](https://github.com/gobii-ai/gobii-platform)

**Others (stats and licenses)**
- Onyx (ex-Danswer): 33,159 stars, MIT core plus `ee` dirs under the Onyx Enterprise License — [GitHub API](https://api.github.com/repos/onyx-dot-app/onyx); [LICENSE](https://github.com/onyx-dot-app/onyx/blob/HEAD/LICENSE). Positioned around enterprise search with 40+ connectors — [Open WebUI comparison](https://docs.openwebui.com/alternatives/onyx.md)
- LibreChat: 46,632 stars, MIT. The description lists Agents, MCP, Skills, and multi-user auth — [GitHub API](https://api.github.com/repos/LibreChat-AI/LibreChat)
- Twenty: 60,279 stars. AGPLv3, with `@license Enterprise` files under a commercial license and some MIT packages — [GitHub API](https://api.github.com/repos/twentyhq/twenty); [LICENSE](https://github.com/twentyhq/twenty/blob/HEAD/LICENSE). Twenty 2.0 (reported 2026-04-21) added a native MCP server and in-CRM agents; vendor/secondary sources only — [ChatGate](https://chatgate.ai/post/twenty-2-0); [folk](https://www.folk.app/articles/twenty-crm-mcp)
- Huly: 28,037 stars, EPL-2.0; "alternative to Linear, Jira, Slack, Notion, Motion" — [GitHub API](https://api.github.com/repos/hcengineering/platform)
- AppFlowy: 78,084 stars, AGPL-3.0; "the AI collaborative workspace" — [GitHub API](https://api.github.com/repos/AppFlowy-IO/AppFlowy)
- AFFiNE: 74,070 stars. MIT, except `packages/backend` and `packages/common/native` — [GitHub API](https://api.github.com/repos/toeverything/AFFiNE); [LICENSE](https://github.com/toeverything/AFFiNE/blob/HEAD/LICENSE)
- Rocket.Chat: 46,358 stars, with an `ee/` dir under a separate license — [GitHub API](https://api.github.com/repos/RocketChat/Rocket.Chat); [LICENSE](https://github.com/RocketChat/Rocket.Chat/blob/HEAD/LICENSE)
- Zulip: 26,155 stars, Apache-2.0 — [GitHub API](https://api.github.com/repos/zulip/zulip)
- Chatwoot: 38,726 stars, MIT, with `enterprise/` under a separate license — [GitHub API](https://api.github.com/repos/chatwoot/chatwoot); [LICENSE](https://github.com/chatwoot/chatwoot/blob/HEAD/LICENSE)

### Inferences
- **Multica: very high threat.** It is the nearest product-shape match: humans and agents as one team, issues, chat, squads/delegation, cron autopilots, skills, workspaces/roles, cost tracking and Telegram. Differences: Multica delegates execution to local agent CLIs (coding-centric) and does not appear to own an LLM runtime, memory or MCP layer. Its license bans third-party hosting, while OpenCompany's AGPL is truly open source, which is a differentiation point for self-hosters and MSPs. Lessons to take: squads with a leader-router, per-issue cost tracking, the agent inbox, and daemon-based local runtimes.
- **Plane: medium-high threat.** In project management it is direct, and it is a fellow AGPL project. It is also an integration partner, since Plane is already available via mcp-cli in this repo. Agents appear to be a paid/cloud feature, which leaves room for OpenCompany as the fully open self-hosted option.
- **Mattermost, Rocket.Chat, Zulip: chat substrates and mostly integration partners.** Mattermost's tool-approval policy (only the initiator approves; private vs shared results) is a useful pattern for OpenCompany approvals.
- **Open WebUI, LibreChat, Onyx: adjacent "AI chat for teams" products,** not persistent agents with task ownership. They overlap on chat, MCP and knowledge, and will compete on the "self-hosted team AI" query.
- **Gobii: low traction but the conceptual twin** for "AI employee with identity (email/SMS), always-on, schedules." Its per-agent email/phone identity and durable per-agent queue are worth studying.
- **Twenty, Huly, AppFlowy, AFFiNE, Chatwoot: vertical apps adding AI.** They are integration targets (via MCP) more than competitors.

### Gaps
- Agent-as-teammate details for Huly, AppFlowy, AFFiNE, Rocket.Chat, Zulip, Chatwoot (Captain) and LibreChat's agent marketplace were not fetched. I found no verified evidence for or against task assignment to agents in these.
- Whether Plane Agents ship in the AGPL Community edition is unconfirmed; the launch post only mentions paid seats.
- Multica cloud pricing is not disclosed in the README.
- Twenty's official changelog was not verified, and the sources conflict on native vs community MCP.

## Key Question 3: OSS equivalents of commercial team-agent products (Slack/Agentforce, Copilot in Teams, Notion agents, Linear agents, Lindy, Relevance AI, Sierra)

### Takeaway
Commercial incumbents have converged on "agents as workspace teammates" with triggers, schedules, MCP and credit-based billing: Notion Custom Agents (Feb 2026), Linear Agent and agent sessions (Mar 2026), Plane Agents (Sep 2026). The OSS equivalents are Multica (Linear-agents-like), Plane (OSS core), Paperclip (an org-level layer with no direct commercial twin), OpenAgents and Mattermost Agents (Slack-agents-like), and Gobii (Lindy-like AI employee).

### Cited Findings
- Notion Custom Agents shipped Feb 24, 2026 (v3.3) as "autonomous AI teammates" that run on schedules or triggers across Notion, Slack, Mail, Calendar, Figma, Linear and custom MCP servers — [Notion blog](https://www.notion.com/blog/introducing-custom-agents); [Reworked](https://www.reworked.co/digital-workplace/notion-custom-agents-reach-general-availability/)
- Notion credits are an add-on for Business/Enterprise. Agents pause when credits run out, and admins control who can create or run agents — [Notion help](https://www.notion.com/help/custom-agent-pricing). The $10 per 1,000 credits rate (from May 4, 2026) comes from secondary sources — [AlternativeTo](https://alternativeto.net/news/2026/4/notion-3-4-adds-ai-autofill-and-cheaper-custom-agents-for-teams/)
- Linear agent platform: an `AgentSession` is created when an agent is mentioned or delegated an issue, with states (working, awaiting input, error, done), webhooks plus Agent Activities, and `promptContext` — [Linear developer docs](https://linear.app/developers/agent-interaction.md). Linear Agent launched March 24, 2026 — [Linear changelog mirror](https://linear-web.pages.dev/changelog/2026-03-24-introducing-linear-agent)
- Plane positions Agents explicitly as "Assign work to AI agents, like any teammate" — [Product Hunt](https://www.producthunt.com/p/planehq/plane-agents)

### Inferences
- **The common commercial pattern** is agent = workspace member (handle, assignee, comment attribution), triggered by mention, assignment, schedule or event, with credit metering and auto-pause. OpenCompany should match this, including per-agent budgets and credit auto-pause (see Paperclip and Notion).
- **The Linear AgentSession state model** (working/awaiting-input/error/done, plus activity stream) is a de facto interaction contract. OpenCompany could expose a compatible webhook/activity model so its agents can be "Linear agents" too (integration).
- **OSS equivalents by product:**
  - Linear agents → Multica, Plane.
  - Notion agents → AppFlowy/AFFiNE (unverified agent depth).
  - Slack/Teams agents → Mattermost Agents, Open WebUI channels, OpenAgents.
  - Lindy/AI employee → Gobii.
  - Org-level "AI company" → Paperclip.
  - OpenCompany sits at the union: chat, channels, tasks, docs, approvals, automations.

### Gaps
- Slack AI agents/Agentforce in Slack, Microsoft Copilot agents in Teams, Lindy, Relevance AI and Sierra were not researched (search returned nothing on Slack/Agentforce). No citable claims are made here.
- Linear agent pricing conflicts between secondary sources; Linear's pricing page was not fetched.

## Key Question 4: Interoperability standards (MCP, A2A, AG-UI, ACP, Agent Skills) — which do comparable projects support?

### Takeaway
MCP is near-universal among the comparables: Paperclip, Plane, Mattermost, Notion, Eigent, Gobii, OpenAgents, LibreChat. Skills (SKILL.md style) are spreading fast: Paperclip, Multica, Plane, Eigent, LibreChat. A2A support is explicit in CrewAI, Microsoft Agent Framework and OpenAgents. AG-UI is explicit in Microsoft Agent Framework. I found no evidence that Paperclip, Multica or Plane support A2A or ACP.

### Cited Findings
- Spec repos (stars, as of 2026-11-02):
  - A2A: 26,385, Apache-2.0 — [GitHub API](https://api.github.com/repos/a2aproject/A2A)
  - AG-UI: 16,967, MIT — [GitHub API](https://api.github.com/repos/ag-ui-protocol/ag-ui)
  - Agent Client Protocol: 4,564, Apache-2.0, "connecting any editor to any agent" — [GitHub API](https://api.github.com/repos/agentclientprotocol/agent-client-protocol)
  - Agent Skills spec: 26,860, Apache-2.0 — [GitHub API](https://api.github.com/repos/agentskills/agentskills)
  - MCP spec: 9,587 — [GitHub API](https://api.github.com/repos/modelcontextprotocol/modelcontextprotocol)
- Paperclip: MCP Tool Gateway plus skills; no A2A/ACP stated — [README](https://github.com/paperclipai/paperclip)
- Multica: skills, CLI and API; MCP/A2A/ACP not mentioned — [README](https://github.com/multica-ai/multica)
- OpenAgents: "MCP and A2A protocol support" — [README](https://github.com/openagents-org/openagents)
- CrewAI: A2A client and server — [docs](https://docs.crewai.com/v1.11.0/en/learn/a2a-agent-delegation)
- Microsoft Agent Framework: A2A + MCP — [AgenticWire](https://www.agenticwire.news/article/microsoft-agent-framework-1-0-workflows-mcp). AG-UI — [CopilotKit](https://copilotkit.ai/blog/microsoft-agent-framework-is-now-ag-ui-compatible)
- Plane: MCP-connected tools plus Skills — [Plane blog](https://plane.so/blog/agents-are-now-live-in-plane)
- Mattermost: MCP tool providers — [docs](https://docs.mattermost.com/end-user-guide/agents)
- Notion: custom MCP servers — [Notion blog](https://www.notion.com/blog/introducing-custom-agents)
- Eigent: MCP plus skills — [README](https://github.com/eigent-ai/eigent)
- Gobii: hosted MCP server; agent-to-agent messaging, not stated as A2A — [README](https://github.com/gobii-ai/gobii-platform)
- LibreChat: Agents, MCP, Skills — [GitHub API](https://api.github.com/repos/LibreChat-AI/LibreChat)
- Activepieces: "~400 MCP servers for AI agents" — [GitHub API](https://api.github.com/repos/activepieces/activepieces)

### Inferences
- **Table stakes:** MCP client support and SKILL.md-style skills. OpenCompany already has MCP.
- **A2A is the cheapest differentiator against Paperclip and Multica.** An A2A server endpoint per durable agent (Agent Card), plus an A2A client for delegation, would let CrewAI, MAF and OpenAgents agents join OpenCompany workspaces as external teammates.
- **AG-UI** could standardize OpenCompany's streaming/tool-status events for third-party frontends.
- **ACP** mainly matters for driving coding-agent CLIs from editors. It is lower priority unless OpenCompany wants Paperclip/Multica-style adapters for Claude Code or Codex.

### Gaps
- A2A/AG-UI support in Dify, Langflow, n8n, Flowise, Open WebUI and LibreChat was not verified.
- Whether Paperclip's "acpx" topic means ACP support is unverified.

## Key Question 5: Most direct competitors vs. integration partners

### Takeaway
Direct competitors, in order: (1) Multica (same product shape, source-available, strong traction), (2) Paperclip (same "AI company" governance model, MIT, very large community, Cloud coming), (3) Plane Agents (AGPL PM with agent members, paid-gated), (4) OpenAgents and Gobii (smaller). Integration partners: CrewAI, Microsoft Agent Framework, AG2, CAMEL (via A2A/MCP); Activepieces, n8n and Dify (tool/automation supply); Mattermost, Zulip and Rocket.Chat (chat channels); Plane, Twenty and Huly (MCP-connected systems of record).

### Cited Findings
- Traction snapshot (stars, 2026-11-02):
  - Paperclip 110,010 — [API](https://api.github.com/repos/paperclipai/paperclip)
  - Multica 54,581 — [API](https://api.github.com/repos/multica-ai/multica)
  - Plane 61,838 — [API](https://api.github.com/repos/makeplane/plane)
  - Eigent 15,714 — [API](https://api.github.com/repos/eigent-ai/eigent)
  - OpenAgents 4,305 — [API](https://api.github.com/repos/openagents-org/openagents)
  - Gobii 497 — [API](https://api.github.com/repos/gobii-ai/gobii-platform)
- Licensing contrast:
  - Paperclip: MIT — [README](https://github.com/paperclipai/paperclip)
  - Multica: Apache plus no-hosting/no-embedding/branding conditions — [LICENSE](https://github.com/multica-ai/multica/blob/HEAD/LICENSE)
  - Plane: AGPL-3.0 — [API](https://api.github.com/repos/makeplane/plane)
  - Dify: no multi-tenant use — [LICENSE](https://github.com/langgenius/dify/blob/HEAD/LICENSE)
  - n8n: Sustainable Use — [LICENSE.md](https://github.com/n8n-io/n8n/blob/HEAD/LICENSE.md)

### Inferences
- **Threat matrix:**

  | Project | Threat | Overlap | Differentiation for OpenCompany |
  |---|---|---|---|
  | Multica | Very high | Board, chat, squads, autopilots, Telegram | True OSS license; owned runtime and memory; docs; approvals |
  | Paperclip | High | Org chart, budgets, approvals, routines, MCP, multi-company | Chat/channels-first collaboration; native runtime vs CLI adapters; docs and Telegram |
  | Plane | Medium-high | Agents as members, triggers, schedules, skills, MCP | Plane agents look paid/cloud-gated; OpenCompany is a general workspace, not PM-only |
  | OpenAgents | Medium | — | — |
  | Gobii | Low-medium | — | — |
  | Eigent | Low-medium | — | Desktop, single-user-ish |
  | Mattermost Agents, Open WebUI, LibreChat, Onyx | Medium | Self-hosted team AI chat | — |
  | Frameworks (CrewAI, MAF, AG2, CAMEL, Swarms, Agency Swarm) | Low | — | Partners |

- **Learn from Paperclip:** budgets with hard pause, hire approvals, goal linkage, heartbeat wakeups, skill versioning, multi-company.
- **Learn from Multica:** squads with a leader-router, per-issue cost tracking, agent inbox, desktop/mobile clients, daemon for local CLI runtimes.
- **Learn from Mattermost:** initiator-only tool approval; private vs shared tool results in channels.
- **Learn from Linear:** AgentSession states and activity stream.
- **Learn from Gobii:** per-agent email/phone identity.
- **Positioning:** OpenCompany's AGPL-3.0-only license is the most open among the workspace-shaped competitors except Paperclip (MIT). Multica, Dify and n8n restrict hosting. OpenCompany can credibly pitch "fully open, self-hostable human+agent company workspace with an owned runtime", while Paperclip's permissiveness invites forks and clouds that OpenCompany's AGPL would block.

### Gaps
- Commit cadence, contributor counts and funding for Paperclip Labs, Index Labs (Multica) and Gobii, Inc. were not researched.
- No user-adoption metrics beyond GitHub stars were found. Stars can be inflated and are a weak proxy.
