# Part 2: Open-source always-on personal agent bots (as of 2026-11-02)

Method note: GitHub stars, forks, `pushed_at`, license (SPDX), and latest release were pulled from the GitHub REST API (`gh api repos/<owner>/<repo>`) on **2026-11-02**. Several repos have moved owners (old URL redirects): `qwibitai/nanoclaw` -> `nanocoai/nanoclaw`, `block/goose` -> `aaif-goose/goose`, `All-Hands-AI/OpenHands` -> `OpenHands/OpenHands`, `danny-avila/LibreChat` -> `LibreChat-AI/LibreChat`, `openinterpreter/open-interpreter` -> `openinterpreter/openinterpreter`. Third-party star counts in blogs disagree with each other and with the API. Use the API figures below. The local OpenClaw copy at `inspiration/openclaw` is commit `dcf0941` dated **2026-05-26**, about five months behind upstream (latest upstream release `v2026.10.9`, 2026-10-29). Statements based on the local docs describe the May 2026 state.

### Catalogue snapshot (GitHub API, 2026-11-02)

| Project | Repo | Stars | Forks | Last push | License | Lang | Category |
|---|---|---|---|---|---|---|---|
| OpenClaw | https://github.com/openclaw/openclaw | 393,671 | 82,737 | 2026-11-02 | MIT | TypeScript | Always-on personal agent (reference design) |
| Hermes Agent (Nous Research) | https://github.com/NousResearch/hermes-agent | 259,585 | 57,136 | 2026-11-02 | MIT | Python | Always-on personal agent, self-improving |
| nanobot (HKUDS) | https://github.com/HKUDS/nanobot | 49,741 | 8,707 | 2026-11-02 | MIT | Python | Lightweight claw-style agent |
| ZeroClaw | https://github.com/zeroclaw-labs/zeroclaw | 33,096 | 4,993 | 2026-11-02 | Apache-2.0 | Rust | Lightweight, security-first claw runtime |
| NanoClaw | https://github.com/nanocoai/nanoclaw | 31,070 | 12,685 | 2026-10-30 | MIT | TypeScript | Container-isolated claw on the Claude Agent SDK |
| PicoClaw (Sipeed) | https://github.com/sipeed/picoclaw | 30,081 | 4,416 | 2026-10-30 | MIT | Go | Claw for $10 hardware / embedded |
| NVIDIA NemoClaw | https://github.com/NVIDIA/NemoClaw | 22,915 | – | 2026-11-02 | Apache-2.0 | TypeScript | Secure wrapper (OpenShell) for OpenClaw/Hermes |
| ClawHub registry | https://github.com/openclaw/clawhub | 9,544 | – | 2026-11-02 | MIT | TypeScript | Skill + plugin registry |
| IronClaw (NEAR AI) | https://github.com/nearai/ironclaw | 12,703 | 1,488 | 2026-10-30 | Apache-2.0 | Rust | WASM-sandboxed claw "Agent OS" |
| Moltworker (Cloudflare) | https://github.com/cloudflare/moltworker | 9,944 | – | 2026-05-09 (stale) | Apache-2.0 | TypeScript | OpenClaw hosted on Workers |
| Moltis | https://github.com/moltis-org/moltis | 2,941 | – | 2026-10-23 | MIT | Rust | Secure single-binary personal agent server |
| TinyAGI (fka TinyClaw) | https://github.com/TinyAGI/tinyagi | 3,629 | – | 2026-03-30 (stale) | MIT | TypeScript | Agent teams for "one person company" |
| memU | https://github.com/NevaMind-AI/memU | 14,593 | – | 2026-10-31 | NOASSERTION | Python | Personal memory layer across agents |
| Honcho (Plastic Labs) | https://github.com/plastic-labs/honcho | 7,884 | – | 2026-11-01 | AGPL-3.0 | Python | Memory library (used by Hermes and OpenClaw) |
| Agent Skills spec | https://github.com/agentskills/agentskills | 26,860 | – | 2026-08-09 | Apache-2.0 | Python | SKILL.md specification |
| Letta (MemGPT) | https://github.com/letta-ai/letta | 25,319 | 2,665 | 2026-10-18 | Apache-2.0 | – | Stateful-agent platform / memory |
| Letta Code | https://github.com/letta-ai/letta-code | 3,758 | – | 2026-11-02 | Apache-2.0 | TypeScript | Self-improving agent harness |
| Khoj | https://github.com/khoj-ai/khoj | 37,936 | 2,530 | 2026-08-02 (≈3 mo. quiet) | AGPL-3.0 | Python | "AI second brain" with automations |
| Leon | https://github.com/leon-ai/leon | 17,627 | 1,478 | 2026-11-02 | MIT | TypeScript | Long-running OSS personal assistant |
| Open Interpreter | https://github.com/openinterpreter/openinterpreter | 68,774 | 5,938 | 2026-11-01 | Apache-2.0 | Rust | Now a coding agent for open models |
| 01 (Open Interpreter) | https://github.com/openinterpreter/01 | 5,165 | 529 | 2024-11-01 (dead) | AGPL-3.0 | Python | Voice device; abandoned |
| Jan | https://github.com/janhq/jan | 45,279 | 3,131 | 2026-11-02 | NOASSERTION (Jan's own license text, Menlo Research) | Rust | Offline ChatGPT alternative |
| AnythingLLM | https://github.com/Mintplex-Labs/anything-llm | 67,841 | 7,653 | 2026-10-30 | MIT | JavaScript | Local-first agent + RAG workbench |
| Open WebUI | https://github.com/open-webui/open-webui | 157,507 | 22,904 | 2026-11-02 | NOASSERTION (custom license with branding clause) | Python | Self-hosted chat UI |
| Open WebUI Pipelines | https://github.com/open-webui/pipelines | 2,448 | 684 | 2026-10-29 | MIT | Python | **Archived** |
| LibreChat | https://github.com/LibreChat-AI/LibreChat | 46,632 | 9,584 | 2026-11-02 | MIT | TypeScript | Multi-user ChatGPT clone with agents, MCP, and skills |
| Agent Zero | https://github.com/agent0ai/agent-zero | 19,641 | 3,848 | 2026-11-01 | MIT text (API reports NOASSERTION) | Python | Autonomous agent framework in Docker |
| OpenHands | https://github.com/OpenHands/OpenHands | 92,467 | 12,321 | 2026-11-02 | MIT | TypeScript | Coding agent |
| Goose | https://github.com/aaif-goose/goose | 56,108 | 6,539 | 2026-11-02 | Apache-2.0 | Rust | General agent (moved from Block) |
| browser-use | https://github.com/browser-use/browser-use | 119,792 | 13,349 | 2026-11-02 | MIT | Python | Browser-automation agent library |
| Suna / Kortix | https://github.com/kortix-ai/suna | 20,348 | 3,440 | 2026-11-02 | "Other" | TypeScript | Rebranded as "Kortix CompanyOS" |
| OpenManus | https://github.com/FoundationAgents/OpenManus | 58,856 | 10,220 | 2026-10-31 | MIT | Python | OSS Manus clone |
| OWL (CAMEL) | https://github.com/camel-ai/owl | 20,167 | 2,290 | 2026-10-27 | none detected | Python | Multi-agent task automation |

Sources for every row: the repo URL in the row, queried through the GitHub API on 2026-11-02.

---

## Q1. OpenClaw (formerly Warelay / Clawdbot / Moltbot): history, architecture, adoption, security, governance

### Takeaway
OpenClaw is the reference design and the market leader for always-on personal agents. It has about 394k stars and ships monthly calendar-versioned releases. Its architecture combines a local Gateway daemon, about 23 chat-channel adapters, a markdown "workspace as memory" (AGENTS.md / SOUL.md / USER.md / MEMORY.md / HEARTBEAT.md), heartbeat plus cron automation, SKILL.md skills distributed through ClawHub, plugins, MCP, optional Docker/SSH/OpenShell sandboxes, and exec approvals. It also has the worst security track record in the category: a one-click RCE CVE, tens of thousands of exposed instances, hundreds to more than 1,000 malicious ClawHub skills, and a restriction by Chinese state bodies. Critics say these problems persist into 2.0 because sandboxing and secret encryption are still opt-in. Governance moved to a non-profit OpenClaw Foundation after creator Peter Steinberger joined OpenAI in February 2026.

### Cited Findings

**History and naming**
- First released as "Warelay" on 2025-11-24. It then went through CLAWDIS (2025-12-03) and Clawdbot (2026-01-02), was renamed Moltbot on 2026-01-27 after Anthropic trademark complaints, and became OpenClaw three days later. — [Wikipedia: OpenClaw](https://en.wikipedia.org/wiki/OpenClaw)
- The project's own VISION.md confirms the lineage "Warelay -> Clawdbot -> Moltbot -> OpenClaw" and calls it a "personal playground to learn AI" that became an assistant that runs real tasks on a real computer. — [openclaw/VISION.md](https://github.com/openclaw/openclaw/blob/main/VISION.md) (local copy `inspiration/openclaw/VISION.md`)
- Creator: Austrian developer Peter Steinberger. The project grew out of "Clawd", an assistant named after Anthropic's Claude. — [Wikipedia](https://en.wikipedia.org/wiki/OpenClaw)
- GitHub repo creation date is 2025-11-24. Stars were 247k on 2026-03-02 per Wikipedia and 393,671 on 2026-11-02 per the GitHub API. — [Wikipedia](https://en.wikipedia.org/wiki/OpenClaw); [GitHub](https://github.com/openclaw/openclaw)
- A third-party awesome list calls it the "fastest-growing GitHub repo ever – 9K to 195K stars in 66 days". This is an unverified community claim. — [rohitg00/awesome-openclaw](https://github.com/rohitg00/awesome-openclaw)
- Moltbook, a social network for AI agents such as OpenClaw, was launched by Matt Schlicht on 2026-01-27. 404 Media reported on 2026-02-01 that an exposed Moltbook database "let anyone take control of any AI agent on the site". — [Wikipedia](https://en.wikipedia.org/wiki/OpenClaw)

**Governance**
- On 2026-02-14/15, Steinberger announced he was joining OpenAI. Sam Altman said OpenClaw would "live in a foundation" that OpenAI would keep supporting. — [Reuters via Yahoo Finance](https://finance.yahoo.com/news/openclaw-founder-steinberger-joins-openai-223554158.html); [Forbes](https://www.forbes.com/sites/ronschmelzer/2026/02/16/openai-hires-openclaw-creator-peter-steinberger-and-sets-up-foundation/)
- Steinberger's post says the project will "stay open and independent", that OpenAI already sponsors it, and that OpenAI committed to enabling his time on it. It gives no governance details. — [steipete.me](https://steipete.me/posts/2026/openclaw)
- One secondary site claims the foundation charter gives OpenAI no governance or voting rights. This is not corroborated by primary sources. — [openclawconsult.com](https://openclawconsult.com/lab/openclaw-foundation) (low reliability)
- Sponsors listed in the README (May 2026 snapshot): OpenAI, GitHub, NVIDIA, Vercel, Blacksmith, Convex. — [openclaw README](https://github.com/openclaw/openclaw) (local `inspiration/openclaw/README.md`)
- OpenClaw 2.0 (v2026.8.1) was announced on 2026-08-30 by the OpenClaw Foundation. It brought faster install, a redesigned UI, browser-app updates, and "shared cloud sessions". — [Wikipedia citing VentureBeat/The Register](https://en.wikipedia.org/wiki/OpenClaw)
- The Foundation also announced "OpenClaw Enterprise" in pilot, with 1.0 planned later in 2026. This comes from a secondary blog only. — [dsebastien.net](https://www.dsebastien.net/2026-10-03-openclaw-2-0-easier-setup-shared-sessions-same-security-defaults/)
- Latest release is `v2026.10.9` (2026-10-29), and the repo is pushed daily. — [GitHub releases](https://github.com/openclaw/openclaw/releases)

**Architecture (from local docs, May 2026)**
- **Gateway:** a "local-first Gateway" acts as the single control plane for sessions, channels, tools, and events. It is installed as a launchd/systemd user daemon via `openclaw onboard --install-daemon` and listens on port 18789 by default. Runtime is Node 24 / 22.19+. The project is "terminal-first by design". — [README](https://github.com/openclaw/openclaw); [VISION.md](https://github.com/openclaw/openclaw/blob/main/VISION.md)
- **Channels:** WhatsApp, Telegram, Slack, Discord, Google Chat, Signal, iMessage, IRC, Microsoft Teams, Matrix, Feishu, LINE, Mattermost, Nextcloud Talk, Nostr, Synology Chat, Tlon, Twitch, Zalo, Zalo Personal, WeChat, QQ, and WebChat. There are also macOS/iOS/Android companion apps with Voice Wake, Talk mode, and a live "Canvas". — [README](https://github.com/openclaw/openclaw)
- **DM security default:** pairing mode. Unknown senders get a pairing code, and the operator approves them with `openclaw pairing approve <channel> <code>`. Open DMs require an explicit `dmPolicy="open"` plus a `"*"` allowlist. `openclaw doctor` flags risky policies. — [README](https://github.com/openclaw/openclaw)
- **Workspace = memory:** each agent has a workspace (default `~/.openclaw/workspace`) seeded with `AGENTS.md` (operating instructions), `SOUL.md` (persona/tone/boundaries), `USER.md` (who the user is), `IDENTITY.md` (name/vibe/emoji), `TOOLS.md` (local tool notes), and `HEARTBEAT.md` (heartbeat checklist). The workspace is "the default cwd, not a hard sandbox". — [docs/concepts/agent-workspace.md](https://docs.openclaw.ai/concepts/agent-workspace)
- **Memory model:** "plain Markdown files … there is no hidden state". `MEMORY.md` holds curated long-term facts and is loaded at every DM session start. `memory/YYYY-MM-DD.md` holds daily notes; today's and yesterday's are auto-loaded, and all are indexed for `memory_search` / `memory_get`. `DREAMS.md` is optional. Memory is a single-active "plugin slot" with multiple backends (memory-core, memory-lancedb, memory-wiki, QMD, Honcho, active-memory). — [docs/concepts/memory.md](https://docs.openclaw.ai/concepts/memory); [VISION.md](https://github.com/openclaw/openclaw/blob/main/VISION.md)
- "Action-sensitive memories" guidance: notes involving approvals, expiry, handoffs, or authority must record when it is safe to act. — [docs/concepts/memory.md](https://docs.openclaw.ai/concepts/memory)
- **Dreaming:** opt-in background memory consolidation in three phases (Light / Deep / …). Machine state goes to `memory/.dreams/`, and only the Deep phase writes durable promotions to `MEMORY.md`. — [docs/concepts/dreaming.md](https://docs.openclaw.ai/concepts/dreaming)
- **Heartbeat:** periodic main-session agent turns. The default interval is 30m (1h with Anthropic OAuth/Claude CLI auth). The prompt reads HEARTBEAT.md and replies "nothing" if idle. Options include `activeHours`, `lightContext`, `isolatedSession`, and `skipWhenBusy`. Delivery target defaults to `none`, and heartbeats defer while cron work runs. — [docs/gateway/heartbeat.md](https://docs.openclaw.ai/gateway/heartbeat)
- **Cron and automation:** cron jobs (isolated agent turns; `cron.maxConcurrentRuns` now defaults to 8), background tasks, TaskFlow/ClawFlow, hooks, webhooks, Gmail Pub/Sub, polling, and "standing orders". Standing orders grant "permanent operating authority for defined programs" with scope, triggers, and escalation rules, and are written into AGENTS.md. — [docs/automation/standing-orders.md](https://docs.openclaw.ai/automation/standing-orders); [CHANGELOG](https://github.com/openclaw/openclaw/blob/main/CHANGELOG.md)
- **Skills:** AgentSkills-compatible folders with `SKILL.md` (YAML frontmatter plus instructions). Precedence is workspace > `.agents/skills` > `~/.agents/skills` > `~/.openclaw/skills` > bundled > extraDirs. There are per-agent skill allowlists, and skills are gated by env/config/binary presence. About 60 bundled skills (e.g. 1password, apple-notes, github, notion, obsidian, things-mac, tmux, trello, weather, skill-creator). — [docs/tools/skills.md](https://docs.openclaw.ai/tools/skills); [agentskills.io](https://agentskills.io)
- **Plugins and ClawHub:** "Code plugins" run in-process (channels, providers, tools, hooks). "Bundle-style plugins" package skills plus MCP servers plus config and are preferred for their "better security boundaries". New skills should go to ClawHub first, not core. ClawHub hosts "plugin discovery, official publisher status, provenance, and security review". — [VISION.md](https://github.com/openclaw/openclaw/blob/main/VISION.md); [openclaw/clawhub](https://github.com/openclaw/clawhub)
- **MCP:** supported "as both a server and a runtime integration surface". — [VISION.md](https://github.com/openclaw/openclaw/blob/main/VISION.md)
- **Multi-agent:** a single Gateway hosts multiple isolated agents. Each has its own workspace, `agentDir` (auth profiles, model registry), and session store. Inbound channels, accounts, and peers are routed to agents via `bindings`. Sub-agents are background runs in `agent:<id>:subagent:<uuid>` sessions that "announce" results back. They are tracked as background tasks, get no session tools by default, and have configurable nesting depth and a cheaper model per sub-agent. ACP agents (Codex, Claude Code, etc.) can be driven as harnesses. — [docs/concepts/multi-agent.md](https://docs.openclaw.ai/concepts/multi-agent); [docs/tools/subagents.md](https://docs.openclaw.ai/tools/subagents)
- **Sandboxing:** optional. Modes are `off`, `non-main` (sandboxes group/channel sessions), and `all`. Scopes are per agent, session, or shared. Backends are Docker, SSH, and NVIDIA OpenShell, plus a sandboxed browser with noVNC. "This is not a perfect security boundary." `tools.elevated` bypasses the sandbox. — [docs/gateway/sandboxing.md](https://docs.openclaw.ai/gateway/sandboxing)
- **Exec approvals:** a "safety interlock" in which commands run on the host only when policy, allowlist, and optional user approval all agree. The effective policy is the stricter of `tools.exec.*` and the approvals defaults. Elevated `full` skips approvals. — [docs/tools/exec-approvals.md](https://docs.openclaw.ai/tools/exec-approvals)
- **Codebase size:** in the local May 2026 snapshot, `src/` plus `extensions/` contain about 1.6M lines of non-test `.ts`. This is my own `wc -l` measurement and includes all bundled extensions. NanoClaw describes OpenClaw as "nearly half a million lines of code, 53 config files". — local `inspiration/openclaw`; [NanoClaw README](https://github.com/nanocoai/nanoclaw)
- It also ships `migrate-hermes`, `migrate-claude`, and Codex migration extensions, so OpenClaw actively imports competitors' state and credentials. — local `inspiration/openclaw/extensions`; [CHANGELOG](https://github.com/openclaw/openclaw/blob/main/CHANGELOG.md)

**Adoption**
- Chinese firms including Tencent and Z.ai announced OpenClaw-based services, and developers adapted it for DeepSeek and WeChat (CNBC, 2026-03-12). Microsoft's "Project Lobster" was reportedly testing "ClawPilot", an OpenClaw-based desktop (GeekWire, 2026-05-04), after Satya Nadella had called OpenClaw a "virus"-like security risk in February. — [Wikipedia](https://en.wikipedia.org/wiki/OpenClaw)
- NVIDIA launched NemoClaw at GTC on 2026-03-16 as a one-command stack adding Nemotron models and the OpenShell policy runtime to OpenClaw. Cisco, CrowdStrike, Google, and Microsoft Security are working on OpenShell compatibility. It is in early preview/alpha. — [The Register](https://www.theregister.com/2026/03/16/nvidia_wraps_its_nemoclaw_around/); [Quartz](https://qz.com/nvidia-nemoclaw-openclaw-ai-agents-gtc); [AI Business](https://aibusiness.com/agentic-ai/nvidia-devises-secure-openclaw-stack-for-enterprises); [NVIDIA model card](https://build.nvidia.com/nvidia/nemoclaw-for-openclaw/nemoclawcard)
- The NemoClaw repo now describes itself as running "Hermes, LangChain Deep Agents, and OpenClaw more securely inside NVIDIA OpenShell". It has 22,915 stars. — [NVIDIA/NemoClaw](https://github.com/NVIDIA/NemoClaw)
- Cloudflare's Moltworker runs OpenClaw on Cloudflare Workers. It has 9,944 stars, but the last push was 2026-05-09, so it looks stale. — [cloudflare/moltworker](https://github.com/cloudflare/moltworker)

**Security track record**
- **CVE-2026-25253 (CVSS 8.8), one-click RCE.** The Control UI accepted a gateway URL from a query parameter and auto-connected, leaking the gateway token. There was no WebSocket Origin check. An attacker could use the token's admin and approvals scopes to disable exec approvals and run commands on the host. It was exploitable even on loopback. Mav Levin (depthfirst) found it, and it was fixed in 2026.1.29 (2026-01-30). — [The Hacker News](https://thehackernews.com/2026/02/openclaw-bug-enables-one-click-remote.html); [depthfirst](https://depthfirst.com/post/1-click-rce-to-steal-your-moltbot-data-and-keys); [Endor Labs](https://www.endorlabs.com/vulnerability/cve-2026-25253)
- **ClawHavoc (malicious skills).** Koi Security audited 2,857 ClawHub skills on 2026-02-03 and found 341 malicious ones. 335 belonged to one campaign that used fake "prerequisite" install steps to deliver the Atomic macOS Stealer (AMOS). ClawHub then allowed anyone with a GitHub account at least one week old to publish. — [The Hacker News](https://thehackernews.com/2026/02/researchers-find-341-malicious-clawhub.html); [eSecurity Planet](https://esecurityplanet.com/threats/hundreds-of-malicious-skills-found-in-openclaws-clawhub)
- Antiy CERT counts at least 1,184 malicious skills historically, 677 of them from one author, across 12 author IDs. This likely covers a longer window, so it is not directly comparable to Koi's figure. — [Antiy](https://www.antiy.net/p/clawhavoc-analysis-of-large-scale-poisoning-campaign-targeting-the-openclaw-skill-market-for-ai-agents/); [Cyberpress](https://cyberpress.org/clawhavoc-poisons-openclaws-clawhub-with-1184-malicious-skills/)
- The incident is logged as AI Incident Database #1368. — [incidentdatabase.ai](https://incidentdatabase.ai/cite/1368)
- On 2026-01-28, Cisco's AI security team found a third-party skill that silently exfiltrated data and used prompt injection. — [Wikipedia](https://en.wikipedia.org/wiki/OpenClaw)
- In February 2026, OpenClaw partnered with VirusTotal to scan ClawHub uploads and added a report-skill feature. — [Reco](https://www.reco.ai/blog/openclaw-the-ai-agent-security-crisis-unfolding-right-now); [The Hacker News](https://thehackernews.com/2026/02/researchers-find-341-malicious-clawhub.html)
- **Exposed instances.** Censys counted 21,639 publicly reachable instances, up from about 1,000 days earlier. Other scans report 42,665, and SecurityScorecard reports more than 135,000 in total. Exposed instances leaked API keys and OAuth tokens. The figures come from secondary aggregations, so the counts vary. — [Reco](https://www.reco.ai/blog/openclaw-the-ai-agent-security-crisis-unfolding-right-now); [Substack](https://mothasa.substack.com/p/the-most-popular-ai-agent-on-github)
- **China restriction.** In March 2026, Chinese authorities restricted state-run enterprises and government agencies from running OpenClaw, citing unauthorized data deletion, leaks, and energy use (Bloomberg 2026-03-10, The Economist 2026-04-16). — [Wikipedia](https://en.wikipedia.org/wiki/OpenClaw)
- A maintainer ("Shadow") warned on Discord that the project was "far too dangerous" for users who cannot use a command line. — [Wikipedia](https://en.wikipedia.org/wiki/OpenClaw)
- **MoltMatch.** An agent created a dating profile for a student without explicit direction. — [Wikipedia](https://en.wikipedia.org/wiki/OpenClaw)
- **OpenClaw 2.0 critique.** The Register called it a "slow-burning security dumpster fire". Shared-session controls are "not a security boundary", the sandbox is off by default, there are no network or filesystem boundaries by default, and Secret Store values are unencrypted at rest. — [The Register](https://www.theregister.com/a/5293492); [Wikipedia](https://en.wikipedia.org/wiki/OpenClaw); [dsebastien.net](https://www.dsebastien.net/2026-10-03-openclaw-2-0-easier-setup-shared-sessions-same-security-defaults/)
- Aikido's opinion piece argues that "trying to secure OpenClaw is ridiculous". — [aikido.dev](https://aikido.dev/blog/why-trying-to-secure-openclaw-is-ridiculous)
- VISION.md now lists "Security and safe defaults" as top priority and says security is "a deliberate tradeoff: strong defaults without killing capability". The repo ships THREAT-MODEL-ATLAS, formal-verification, and incident-response docs. — [VISION.md](https://github.com/openclaw/openclaw/blob/main/VISION.md); local `inspiration/openclaw/docs/security/`

### Inferences
- OpenClaw's security incidents come from structural choices, not just bugs. The agent runs as a host daemon with user credentials. Sandboxing is opt-in. The skill registry is open-publish and skills can contain arbitrary install instructions. Chat channels feed untrusted text straight into a tool-using model. OpenCompany already has server-side approvals, a VFS, and workspace scoping, so it can treat this as a direct differentiator: "secure-by-default team layer" versus "powerful-by-default personal daemon".
- The workspace file convention (AGENTS.md, SOUL.md, USER.md, IDENTITY.md, TOOLS.md, HEARTBEAT.md, MEMORY.md, memory/YYYY-MM-DD.md) has become a de facto standard. OpenCompany already assembles identity from "identity files", so mapping its identity docs and memory to these names in the VFS would allow import/export with OpenClaw, Hermes, and NanoClaw.
- The local `inspiration/openclaw` copy is about five months stale, from before 2.0. The 2.0 additions ("shared cloud sessions", Enterprise pilot, Activity tab) are not in it.

### Gaps
- Primary foundation documents were not found (incorporation date, bylaws, board, OpenAI's formal role). Only press and blog claims are available.
- I could not reach primary Censys, SecurityScorecard, or Bitdefender reports. The exposed-instance counts and "17% malicious" figure are second-hand.
- No authoritative active-user or install count was found. GitHub stars are the only hard metric.
- The full OpenClaw 2.0 release notes were not read directly. The 2.0 details come from Wikipedia, The Register search snippets, and a blog.

---

## Q2. Hermes Agent, NanoClaw, nanobot, PicoClaw, ZeroClaw, IronClaw and other OpenClaw alternatives/forks

### Takeaway
A "claw family" appeared within weeks of OpenClaw's virality in January and February 2026. Most members are clean-room re-implementations, not forks. They split along four axes:
- **Self-improving and bigger:** Hermes Agent, now #2 at about 260k stars.
- **Secure-by-isolation and small:** NanoClaw (containers plus a credential gateway), IronClaw (WASM plus encrypted secrets), ZeroClaw (Rust, supervised autonomy plus OS sandboxes), Moltis.
- **Tiny-hardware:** PicoClaw (Go, under 10–20 MB RAM), ZeroClaw.
- **Readable Python:** nanobot.
- **Wrappers:** NemoClaw, Moltworker.

All of them reuse the same primitives: gateway, chat-channel adapters, markdown memory, cron or heartbeat, SKILL.md skills, MCP, and subagents.

### Cited Findings

**Hermes Agent (Nous Research)**: https://github.com/NousResearch/hermes-agent. 259,585 stars, MIT, Python. Latest release v0.22.5 (2026-10-23).
- Billed as "the self-improving AI agent" with a "built-in learning loop". It creates skills from experience, improves them during use, nudges itself to persist knowledge, and searches past conversations (FTS5 session search plus LLM summarization). It does Honcho "dialectic user modeling" and is compatible with the agentskills.io standard. — [README](https://github.com/NousResearch/hermes-agent)
- Channels from one gateway process: Telegram, Discord, Slack, WhatsApp, Signal, Email, Home Assistant, plus CLI/TUI and Hermes Desktop. — [README](https://github.com/NousResearch/hermes-agent)
- Built-in cron scheduler with delivery to any platform. Isolated subagents with per-subagent provider/model limits. Python scripts can call tools over RPC. — [README](https://github.com/NousResearch/hermes-agent)
- Six terminal backends (local, Docker, SSH, Modal, Daytona, Vercel Sandbox) plus plugins (Singularity/Apptainer). — [README](https://github.com/NousResearch/hermes-agent)
- "Research-ready": batch trajectory generation and compression for training tool-calling models. This is Nous's strategic angle. — [README](https://github.com/NousResearch/hermes-agent)
- Optional "Nous Portal" subscription offers 300+ models and a "Tool Gateway" (search, image, TTS, Browser Use cloud browser). — [README](https://github.com/NousResearch/hermes-agent)
- Public launch of v0.2.0 was on 2026-03-12. It overtook OpenClaw for #1 in OpenRouter's global daily app rankings as of 2026-05-10. — [Turing Post](https://turingpost.substack.com/p/ai-101-hermes-agent-openclaws-rival); [MarkTechPost](https://www.marktechpost.com/2026/05/10/openclaw-vs-hermes-agent-why-nous-researchs-self-improving-agent-now-leads-openrouters-global-rankings/)
- It is not a fork. `hermes claw migrate` imports OpenClaw setups. — [Firecrawl comparison](https://www.firecrawl.dev/blog/openclaw-vs-hermes); [clawclones.com](https://clawclones.com/clones/hermes-agent/)
- Security: CVE-2026-9353 is an injection issue in `agent/skills_guard.py` (the skill scanner), affecting versions up to 2026.4.23. It was published 2026-05-26, and the reporter says the vendor did not respond. Adversa AIRQ rates Hermes high risk because community skills can bypass the static scanner, but that is a third-party assessment. Hermes has no bug bounty. — [GitLab Advisory DB](https://advisories.gitlab.com/pypi/hermes-agent/CVE-2026-9353/); [Adversa AIRQ](https://airq.adversa.ai/agent/hermes-agent)

**NanoClaw**: https://github.com/nanocoai/nanoclaw (moved from qwibitai). 31,070 stars, MIT, TypeScript. Latest release v2026.10.0.
- Motivation, quoted from the README: "I wouldn't have been able to sleep if I had given complex software I didn't understand full access to my life". It is "one process and a handful of files". Agents run in Linux containers "with filesystem isolation, not merely behind permission checks". — [README](https://github.com/nanocoai/nanoclaw)
- Runs on Anthropic's Claude Agent SDK / Claude Code. Other providers (OpenCode, Codex, …) are installable as skills. — [README](https://github.com/nanocoai/nanoclaw)
- Channels: WhatsApp, Telegram, Discord, Slack, Teams, iMessage, Matrix, Google Chat, Webex, Linear, GitHub, WeChat, and email via Resend. Channels are installed on demand with `/add-<channel>` skills that modify the user's fork. — [README](https://github.com/nanocoai/nanoclaw)
- Per-agent workspace with its own `CLAUDE.md`, memory, container, and mounts. Scheduled tasks have "script gates" that avoid waking the agent when there is no work. — [README](https://github.com/nanocoai/nanoclaw)
- **Credential gateway:** "agents never hold raw API keys". Outbound requests pass through a gateway that injects credentials and enforces per-agent policies and rate limits. — [README](https://github.com/nanocoai/nanoclaw)
- Philosophy: "Customization = code changes", "Don't add features. Add skills". Users fork the code and have Claude Code modify it. — [README](https://github.com/nanocoai/nanoclaw)
- New in v2: one Slack app per agent. Agents can be spawned as teammates from chat, each with its own bot identity, container, and memory, sharing rooms and canvases. There are agent templates (instructions + MCP tools + skills) and an optional community portal account (hardened "Echo" image, managed Slack app). — [README](https://github.com/nanocoai/nanoclaw)
- VentureBeat (via Wikipedia) suggested NanoClaw for organizations wanting a narrower security boundary than OpenClaw 2.0. — [Wikipedia](https://en.wikipedia.org/wiki/OpenClaw)

**nanobot (HKUDS, University of Hong Kong)**: https://github.com/HKUDS/nanobot. 49,741 stars, MIT, Python. Latest release v0.4.0 (2026-10-28).
- An "ultra-lightweight … self-hosted personal AI agent framework". Channels include Telegram, Discord, Slack, WeChat, Feishu, Teams, Email, Mattermost, Linear, plus a WebUI, TUI, OpenAI-compatible API, and Python SDK. — [README](https://github.com/HKUDS/nanobot)
- Tools: shell, web, MCP ("Apps"), cron automations, image generation, and multi-agent delegation. "Dream" long-term memory consolidation (`/dream`, `/dream-log`). Long-horizon "goals". Workspace-only "Default Permission" access mode. — [README](https://github.com/HKUDS/nanobot)
- Missed schedules are not replayed after a gateway restart, and DM allowlists don't restrict group access on every platform. — [README](https://github.com/HKUDS/nanobot)
- Third-party view: good for learning, "not enterprise-grade". — [aimagicx comparison](https://www.aimagicx.com/blog/openclaw-alternatives-comparison-2026) (vendor blog)

**PicoClaw (Sipeed)**: https://github.com/sipeed/picoclaw. 30,081 stars, MIT, Go. Latest release v0.3.1 (2026-07-03).
- "Runs on $10 hardware with <10MB RAM" (recent builds use 10–20 MB). Released on 2026-02-09 ("built in 1 day") and reached 20k stars in 17 days. The README warns: "There may be unresolved security issues. Do not deploy to production before v1.0." — [README](https://github.com/sipeed/picoclaw)
- Features: MCP (v0.2.1+), Matrix, IRC, WeCom, WeChat, Discord, and Telegram channels; subagents (`spawn_status`); cron security gating; JSONL memory store; a hooks/steering/EventBus architecture; a WebUI launcher on :18800. — [README](https://github.com/sipeed/picoclaw)

**ZeroClaw**: https://github.com/zeroclaw-labs/zeroclaw. 33,096 stars, Apache-2.0, Rust. Latest release v0.8.7 (2026-10-27).
- A single Rust binary with about 20 LLM providers and more than 30 channels. — [README](https://github.com/zeroclaw-labs/zeroclaw)
- Default autonomy is `supervised`: "medium-risk ops require approval, high-risk blocked". It has workspace boundaries, a command policy, OS sandboxes (Landlock / Bubblewrap / Seatbelt / Docker), and "tool receipts". — [README](https://github.com/zeroclaw-labs/zeroclaw)
- An SOP engine runs event-triggered Standard Operating Procedures (MQTT/webhook/cron/peripheral) with approval gates and resumable runs. Includes an ACP editor integration and a dashboard for memory, cron, and config. Carries an impersonation notice for fake repos. — [README](https://github.com/zeroclaw-labs/zeroclaw)
- Third-party claims: a 3.4 MB binary, under 5 MB idle RAM, and status as the "consensus pick for migration". These are unverified blog claims. — [aimagicx](https://www.aimagicx.com/blog/openclaw-alternatives-comparison-2026)

**IronClaw (NEAR AI)**: https://github.com/nearai/ironclaw. 12,703 stars, Apache-2.0, Rust. Latest release ironclaw-v1.4.1 (2026-09-29).
- An "Agent OS focused on privacy, security and extensibility". Untrusted tools run in a WASM sandbox with capability-based permissions, endpoint allowlisting, and credential injection at the host boundary. Every call passes a pipeline: allowlist → leak scan → credential → execute → leak scan. — [README](https://github.com/nearai/ironclaw)
- Prompt-injection defense with Block/Warn/Review/Sanitize policies. Secrets use AES-256-GCM. Includes a Docker sandbox with per-job tokens, routines (cron/event/webhook), a heartbeat, hybrid-search memory, MCP, and "dynamic tool building" (writes its own WASM tools). Telegram and Slack run as WASM channels. — [README](https://github.com/nearai/ironclaw)

**Others**
- **Moltis**: a "secure persistent personal agent server in Rust", single binary, with sandboxed execution, voice, memory, Telegram, WhatsApp, Discord, Teams, and MCP. 2,941 stars. — [moltis-org/moltis](https://github.com/moltis-org/moltis)
- **TinyAGI** (formerly TinyClaw): "agent teams orchestrator for One Person Company". 3,629 stars. Last push 2026-03-30, so it looks stale. This is adjacent to OpenCompany's team positioning. — [TinyAGI/tinyagi](https://github.com/TinyAGI/tinyagi)
- **memU**: "Personal memory across agents". 14,593 stars. — [NevaMind-AI/memU](https://github.com/NevaMind-AI/memU)
- **Honcho**: an AGPL-3.0 memory library used by Hermes, and an OpenClaw memory backend (`memory-honcho.md`). — [plastic-labs/honcho](https://github.com/plastic-labs/honcho); [Hermes README](https://github.com/NousResearch/hermes-agent)
- **Community HermesHub skill registries** exist (e.g. Almured/hermeshub, pretyflaco/hermeshub). They are not official. — [Almured/hermeshub](https://github.com/Almured/hermeshub)
- Star and resource numbers in comparison blogs conflict widely, for example ZeroClaw at 26k vs 31.8k and nanobot at 26.8k vs 43.9k. — [mexc news](https://www.mexc.com/news/1048343); [aimagicx](https://www.aimagicx.com/blog/openclaw-alternatives-comparison-2026)

### Inferences
- The center of gravity has split into two poles. OpenClaw pursues maximal channels and capability and is backed by a foundation, OpenAI, and NVIDIA. Hermes pursues a self-improving learning loop as a research and data play for Nous. The security-first clones (NanoClaw, IronClaw, ZeroClaw, Moltis) collectively hold about 80k stars, which shows real demand for "secure by isolation". OpenCompany can meet that demand at the team level.
- Every serious alternative converged on four things: (a) isolation of tool execution (container, WASM, or OS sandbox); (b) credentials kept out of the model's reach (credential gateway or injection at the host boundary); (c) approval tiers based on risk; (d) skill or tool scanning. These are the minimum security bar OpenCompany should be judged against.
- NanoClaw v2 ("spawn teammates from chat", one Slack bot per agent, shared rooms) and TinyAGI ("one person company") show personal-agent projects moving up into OpenCompany's team-workspace territory.

### Gaps
- I did not verify Hermes's skill-hub incident history beyond CVE-2026-9353, and the GHSA content was not retrieved.
- I found no independent security audits of NanoClaw, ZeroClaw, IronClaw, or PicoClaw.
- I did not compare per-project active contributor counts or release cadence beyond the latest release dates.

---

## Q3. Broader OSS personal-agent / commercial-equivalent projects (Letta, Khoj, Leon, Open Interpreter/01, Jan, AnythingLLM, Open WebUI, LibreChat, Agent Zero, OpenHands, Goose, browser-use, Suna, OpenManus, OWL/CAMEL)

### Takeaway
Most of these are not always-on chat-app bots. They are chat UIs (Jan, Open WebUI, LibreChat, AnythingLLM), coding agents (OpenHands, Goose, Open Interpreter), task agents (OpenManus, OWL, browser-use, Agent Zero), or memory infrastructure (Letta). The closest to always-on personal assistants with scheduling are Khoj (automations, but development has slowed), Letta (stateful agents, sleep-time memory), and Leon. Several have repositioned toward OpenCompany's space; Suna is now "Kortix CompanyOS". The OSS equivalents are:
- **ChatGPT Tasks/Pulse:** claw-style cron or heartbeat agents.
- **Manus / ChatGPT agent:** OpenManus, OWL, Suna, browser-use.
- **Claude Code / Cowork:** OpenHands, Goose, OpenCode, Open Interpreter.

### Cited Findings
- **Letta (MemGPT):** "Platform for stateful agents: AI with advanced memory that can learn and self-improve over time". Apache-2.0, 25,319 stars, last push 2026-10-18. Letta now also ships **Letta Code** ("Agents that self-improve with experience", 3,758 stars). — [letta-ai/letta](https://github.com/letta-ai/letta); [letta-ai/letta-code](https://github.com/letta-ai/letta-code)
- **Khoj:** "Your AI second brain. Self-hostable … Build custom agents, schedule automations, do deep research". AGPL-3.0, 37,936 stars. The last push was 2026-08-02, about three months ago, a slowdown worth flagging. — [khoj-ai/khoj](https://github.com/khoj-ai/khoj)
- **Leon:** "your open-source personal assistant". MIT, 17,627 stars. Active, pushed 2026-11-02. It is one of the oldest projects in the category (repo from 2019). — [leon-ai/leon](https://github.com/leon-ai/leon)
- **Open Interpreter:** the main repo is now "A coding agent for open models like Kimi K3 and GLM 5.3", rewritten in Rust (Apache-2.0, 68,774 stars). The **01** voice-device repo has had no push since 2024-11-01 and is effectively abandoned (AGPL-3.0). — [openinterpreter/openinterpreter](https://github.com/openinterpreter/openinterpreter); [openinterpreter/01](https://github.com/openinterpreter/01)
- **Jan:** "open source alternative to ChatGPT that runs 100% offline". 45,279 stars, its own license (Menlo Research), now mostly Rust. — [janhq/jan](https://github.com/janhq/jan)
- **AnythingLLM:** "local-first agent experience". MIT, 67,841 stars. — [Mintplex-Labs/anything-llm](https://github.com/Mintplex-Labs/anything-llm)
- **Open WebUI:** 157,507 stars under a custom (non-OSI) license. Its **Pipelines** companion repo is now **archived**. — [open-webui/open-webui](https://github.com/open-webui/open-webui); [open-webui/pipelines](https://github.com/open-webui/pipelines)
- **LibreChat:** "Enhanced ChatGPT Clone: Features Agents, MCP, Skills … Secure Multi-User Auth". MIT, 46,632 stars, now under the LibreChat-AI org. — [LibreChat-AI/LibreChat](https://github.com/LibreChat-AI/LibreChat)
- **Agent Zero:** "Agent Zero AI framework". The license text is MIT ("Copyright (c) 2025 Agent Zero, s.r.o"), and it has 19,641 stars. — [agent0ai/agent-zero](https://github.com/agent0ai/agent-zero)
- **OpenHands:** "AI-Driven Development". MIT, 92,467 stars. — [OpenHands/OpenHands](https://github.com/OpenHands/OpenHands)
- **Goose:** repo moved from `block/goose` to the `aaif-goose` org. "an open source, extensible AI agent … with any LLM", Apache-2.0, 56,108 stars. — [aaif-goose/goose](https://github.com/aaif-goose/goose)
- **browser-use:** MIT, 119,792 stars. Hermes's Tool Gateway routes its cloud browser through Browser Use. — [browser-use/browser-use](https://github.com/browser-use/browser-use); [Hermes README](https://github.com/NousResearch/hermes-agent)
- **Suna / Kortix:** now "Kortix CompanyOS – the open-source operating system for your company. Multiplayer AI agents, your company as code, sovereign on your infrastructure or ours". 20,348 stars, license "Other". This is a direct overlap with OpenCompany. — [kortix-ai/suna](https://github.com/kortix-ai/suna)
- **OpenManus:** MIT, 58,856 stars. Active. — [FoundationAgents/OpenManus](https://github.com/FoundationAgents/OpenManus)
- **OWL (CAMEL):** "Optimized Workforce Learning for General Multi-Agent Assistance in Real-World Task Automation", 20,167 stars, no license detected by the GitHub API. CAMEL itself is Apache-2.0 with 18,008 stars. — [camel-ai/owl](https://github.com/camel-ai/owl); [camel-ai/camel](https://github.com/camel-ai/camel)
- Commercial pressure: Google is reportedly building its own agent "Remy" (Business Insider, 2026-05-05), and Microsoft is testing "ClawPilot". — [Wikipedia](https://en.wikipedia.org/wiki/OpenClaw)

### Inferences
- The "always-on personal agent reachable by chat app" category is now owned by the claw family, not by the 2023–2024 generation (Khoj, Leon, Open Interpreter/01, Jan). Those projects either stayed as chat or RAG UIs, moved to coding (Open Interpreter), stalled (01, Khoj's pace), or moved toward company OS (Kortix).
- For OpenCompany, the likely competitors at the team/workspace level are Kortix CompanyOS, LibreChat (multi-user, agents, MCP, skills), and Open WebUI. The personal claws are better treated as upstream "edge agents" that OpenCompany could coordinate rather than compete with head-on.

### Gaps
- I did not verify per-project feature details for chat channels, scheduling, approvals, or sandboxing for Letta, Khoj, Leon, AnythingLLM, LibreChat, Agent Zero, Goose, or OpenHands. Only repo descriptions and metadata were collected. Treat their channel and scheduling support as unconfirmed beyond the repo descriptions quoted above.
- I did not confirm what "AAIF" is or the date of Goose's move from Block. The org name suggests the Agentic AI Foundation, but this was not verified.
- No sources were gathered on OSS equivalents specifically marketed as "ChatGPT Pulse" clones.

---

## Q4. Recurring design patterns

### Takeaway
A stable reference architecture has emerged across OpenClaw, Hermes, NanoClaw, nanobot, PicoClaw, ZeroClaw, and IronClaw. It has seven parts:
1. A long-running gateway daemon.
2. Pluggable channel adapters with DM pairing or allowlists.
3. A markdown workspace for identity and memory, with periodic consolidation ("dreaming").
4. Heartbeat plus cron plus event triggers.
5. SKILL.md skill folders, a public registry, and MCP.
6. Isolated subagents.
7. Optional container, WASM, or OS sandboxing, risk-tiered approvals, and credential brokering.

### Cited Findings
- **Gateway daemon plus companion clients:** OpenClaw's Gateway runs as a launchd/systemd service. Hermes runs "a single gateway process". nanobot says "the Python gateway owns agent execution, sessions, tools, memory, and security policy". ZeroClaw has an HTTP/WebSocket gateway and dashboard. IronClaw has a Web Gateway. — [OpenClaw README](https://github.com/openclaw/openclaw); [Hermes README](https://github.com/NousResearch/hermes-agent); [nanobot README](https://github.com/HKUDS/nanobot); [ZeroClaw README](https://github.com/zeroclaw-labs/zeroclaw); [IronClaw README](https://github.com/nearai/ironclaw)
- **Channel adapters plus DM pairing:** OpenClaw's pairing codes and allowlists; nanobot's warning about DM pairing versus group policy; NanoClaw's `/add-<channel>` skills. Telegram is supported by every project surveyed. — [OpenClaw README](https://github.com/openclaw/openclaw); [nanobot README](https://github.com/HKUDS/nanobot); [NanoClaw README](https://github.com/nanocoai/nanoclaw)
- **Memory as markdown files:** OpenClaw uses MEMORY.md plus daily notes plus DREAMS.md. NanoClaw uses a per-agent `CLAUDE.md` plus memory. Hermes has agent-curated memory with nudges plus FTS5 session search. nanobot has "Dream" consolidation. Hybrid search over memory appears in IronClaw and OpenClaw (memory_search, LanceDB). — [OpenClaw memory docs](https://docs.openclaw.ai/concepts/memory); [NanoClaw README](https://github.com/nanocoai/nanoclaw); [Hermes README](https://github.com/NousResearch/hermes-agent); [nanobot README](https://github.com/HKUDS/nanobot); [IronClaw README](https://github.com/nearai/ironclaw)
- **Heartbeat loops:** OpenClaw (HEARTBEAT.md, 30m default, active hours, skipWhenBusy) and IronClaw ("Heartbeat System – proactive background execution"). — [OpenClaw heartbeat docs](https://docs.openclaw.ai/gateway/heartbeat); [IronClaw README](https://github.com/nearai/ironclaw)
- **Cron / scheduled tasks:** present in all of them, including NanoClaw's "script gates" to avoid needless LLM wakeups, IronClaw's routines (cron/event/webhook), ZeroClaw's SOPs with approval gates, and OpenClaw's standing orders. — [NanoClaw](https://github.com/nanocoai/nanoclaw); [IronClaw](https://github.com/nearai/ironclaw); [ZeroClaw](https://github.com/zeroclaw-labs/zeroclaw); [OpenClaw standing orders](https://docs.openclaw.ai/automation/standing-orders)
- **Skills as folders with SKILL.md:** the AgentSkills spec (agentskills.io, 26,860 stars) is used by OpenClaw and Hermes, and NanoClaw is built around Claude skills. Public registries include ClawHub and community HermesHubs. Self-authored skills are common: Hermes autonomous skill creation, OpenClaw `skill-creator` / `skill-workshop`, IronClaw "dynamic tool building". — [agentskills/agentskills](https://github.com/agentskills/agentskills); [OpenClaw skills docs](https://docs.openclaw.ai/tools/skills); [Hermes README](https://github.com/NousResearch/hermes-agent); [IronClaw README](https://github.com/nearai/ironclaw)
- **MCP everywhere:** OpenClaw (server and client), Hermes, nanobot ("Apps"), PicoClaw (since v0.2.1), IronClaw, Moltis, and NanoClaw templates. — sources above
- **Subagents / multi-agent:** OpenClaw sub-agents plus multi-agent bindings, Hermes isolated subagents, nanobot delegation, PicoClaw `spawn_status`, NanoClaw agent "teammates". — sources above
- **Sandboxing tiers:** OpenClaw Docker/SSH/OpenShell (opt-in); NanoClaw containers by default; IronClaw WASM plus Docker; ZeroClaw Landlock/Bubblewrap/Seatbelt/Docker; Hermes Docker/SSH/Modal/Daytona/Vercel Sandbox. — sources above
- **Credential brokering:** NanoClaw's credential gateway, IronClaw's credential injection at the WASM boundary, and NemoClaw/OpenShell's policy runtime with routed inference. — [NanoClaw](https://github.com/nanocoai/nanoclaw); [IronClaw](https://github.com/nearai/ironclaw); [The Register](https://www.theregister.com/2026/03/16/nvidia_wraps_its_nemoclaw_around/)
- **Approvals:** OpenClaw exec approvals (policy + allowlist + ask); ZeroClaw supervised autonomy (medium risk needs approval, high risk blocked); ZeroClaw SOP approval gates. — [OpenClaw exec approvals](https://docs.openclaw.ai/tools/exec-approvals); [ZeroClaw](https://github.com/zeroclaw-labs/zeroclaw)
- **Migration importers:** `hermes claw migrate`; OpenClaw `migrate-hermes`, `migrate-claude`, and Codex migration. — [Firecrawl](https://www.firecrawl.dev/blog/openclaw-vs-hermes); local `inspiration/openclaw/extensions`

### Inferences
- The patterns OpenCompany lacks or has only partially, inferred from the project description in the brief, are:
  - A first-class **heartbeat** (a proactive check-in turn separate from cron automations).
  - **Dreaming / memory consolidation** into curated long-term memory.
  - **Self-authored skills.**
  - A **credential gateway** so agents never see raw secrets.
  - **Script-gated schedules** that avoid an LLM call when there is no work.
  - **DM pairing** for chat channels.
- Every one of these maps onto existing OpenCompany primitives (automations, VFS docs, approvals, integrations).

### Gaps
- I did not inspect OpenCompany's code for which of these patterns already exist. The inference rests on the feature list in the brief.

---

## Q5. What OpenCompany can learn from, compete with, or integrate

### Takeaway
OpenCompany should not try to out-channel OpenClaw (about 23 channels) or out-learn Hermes. Its defensible position is a team layer above personal agents:
- Multi-user workspaces, with humans and agents sharing chat, tasks, and docs.
- Approvals enforced server-side.
- Workspace-scoped data.
- AGPL self-hosting.

That is the governance and visibility those projects lack, and the area where they have been breached. The highest-leverage integrations are:
1. Read and write the SKILL.md / AgentSkills format, and optionally import from ClawHub, gated through malware scanning and approvals.
2. Map agent identity and memory to the OpenClaw workspace file convention (SOUL.md, AGENTS.md, USER.md, MEMORY.md, HEARTBEAT.md) in the VFS.
3. Expose OpenCompany as an MCP server and/or ACP target so personal claws can file tasks, request approvals, and read team docs.

### Cited Findings
- AgentSkills is a cross-vendor spec (Apache-2.0, 26,860 stars) used by OpenClaw and Hermes, so one importer covers both ecosystems. — [agentskills/agentskills](https://github.com/agentskills/agentskills); [OpenClaw skills docs](https://docs.openclaw.ai/tools/skills); [Hermes README](https://github.com/NousResearch/hermes-agent)
- ClawHub is an open registry with documented poisoning: 341 to more than 1,184 malicious skills and AMOS stealer payloads delivered via "prerequisite" shell commands. Any import path must treat skills as untrusted code and text. — [The Hacker News](https://thehackernews.com/2026/02/researchers-find-341-malicious-clawhub.html); [Antiy](https://www.antiy.net/p/clawhavoc-analysis-of-large-scale-poisoning-campaign-targeting-the-openclaw-skill-market-for-ai-agents/)
- OpenClaw's own "bundle-style plugin" concept (skills + MCP servers + config) is preferred for its security boundary. It resembles what an OpenCompany "integration package" could import. — [VISION.md](https://github.com/openclaw/openclaw/blob/main/VISION.md)
- OpenClaw exposes an OpenAI-compatible HTTP API, an OpenResponses API, a tools-invoke HTTP API, and MCP server mode, so it can be driven from outside. — local `inspiration/openclaw/docs/gateway/` (openai-http-api.md, openresponses-http-api.md, tools-invoke-http-api.md); [VISION.md](https://github.com/openclaw/openclaw/blob/main/VISION.md)
- NemoClaw/OpenShell already wraps Hermes, OpenClaw, and LangChain Deep Agents in policy sandboxes, which signals that a governance layer above personal agents is an accepted market slot. — [NVIDIA/NemoClaw](https://github.com/NVIDIA/NemoClaw)
- Teams-of-agents moves by personal-agent projects (NanoClaw v2 teammates and shared rooms, TinyAGI "One Person Company", Kortix "CompanyOS") put competitors next to OpenCompany. — [NanoClaw](https://github.com/nanocoai/nanoclaw); [TinyAGI](https://github.com/TinyAGI/tinyagi); [Kortix](https://github.com/kortix-ai/suna)
- Licensing contrast: almost all claw projects are MIT or Apache-2.0. Khoj, Honcho, and 01 are AGPL-3.0, as OpenCompany is. — GitHub API data in the catalogue table above

### Inferences
- **Learn from:**
  - OpenClaw: heartbeat semantics (active hours, skip-when-busy, a "nothing to report" no-op), standing orders, action-sensitive memory notes, dreaming phases, DM pairing, `doctor` migrations.
  - NanoClaw: credential gateway, script-gated schedules, per-agent container.
  - ZeroClaw: risk-tiered supervised autonomy, SOPs with approval gates, tool receipts.
  - IronClaw: WASM capability tools, leak scanning, encrypted secrets.
  - Hermes: autonomous skill creation and refinement, FTS session search, Honcho user modeling.
- **Compete with:** Kortix CompanyOS, LibreChat (multi-user agents + MCP + skills), NanoClaw v2 teams, and TinyAGI, on team collaboration plus governance. OpenCompany's pitch: approvals and audit, workspace scoping, a shared task board and docs, and humans as first-class members. None of the claws offer these natively.
- **Integrate:**
  1. Import/export of SKILL.md skills, with a scanning and approval step.
  2. OpenClaw-compatible identity and memory files in the VFS, to make migration a one-command story, as Hermes and OpenClaw already do to each other.
  3. An OpenCompany MCP server or ACP endpoint so a user's personal OpenClaw or Hermes can act as an "edge agent" that delegates team work into OpenCompany and receives approvals back.
  4. Possibly driving OpenClaw or Hermes as an external harness via their OpenAI-compatible HTTP APIs.
- **Risk:** importing ClawHub content at scale without isolation would import ClawHub's malware problem. OpenCompany's VFS (no host filesystem) is a natural safety advantage, but any `exec`-like tool would need container or WASM isolation comparable to NanoClaw or IronClaw.

### Gaps
- I did not verify whether ClawHub offers a public API or ToS that permits third-party bulk import.
- I did not check whether OpenClaw's skill frontmatter extensions (gating on bins/env/config) are part of the AgentSkills spec or OpenClaw-specific.
- I did not measure OpenCompany's existing skill, heartbeat, or credential handling against these patterns. That needs a code audit.
