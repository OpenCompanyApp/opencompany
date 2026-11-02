# Commercial agent products, part 3: Google, Manus, Perplexity Comet, Microsoft Copilot, Amazon Alexa+/Nova Act, and other proactive agents (architecture, sources, and design lessons for OpenCompany)

Research date: 2026-11-02. Scope excludes xAI, OpenAI, Anthropic, and Meta products. Manus's ownership history is covered here because it involves Meta.

Reliability labels used below:
- **[Official]**: vendor documentation or engineering blog
- **[Leak]**: extracted or leaked prompt
- **[3P-RE]**: third-party security research or reverse engineering
- **[Press]**: reputable tech press
- **[Secondary]**: aggregator, SEO, or summary site (weak)

---

## 1. Manus: architecture, how the leaked prompt compares with official engineering posts, and current status

### Takeaway
Manus is the best-documented commercial general agent. Its own engineering posts and the March 2025 leaked prompt agree closely. The architecture is:
- one tool call per step, inside an Ubuntu cloud sandbox
- fewer than about 20 atomic tools, with most capability pushed into the shell and filesystem
- the file system and `todo.md` used as externalized memory
- KV-cache-first context management that masks tools rather than removing them
- reversible compaction before lossy summarization
- Wide Research: parallel fan-out to full, isolated sub-agent instances that report only to a controller

Ownership has been unstable. Meta bought Manus in December 2025, and China's NDRC ordered the deal unwound on April 27, 2026. Manus said on August 11, 2026 that it would operate independently again.

### Cited Findings

**Official engineering post: "Context Engineering for AI Agents: Lessons from Building Manus" (Yichao "Peak" Ji, July 18, 2025) [Official]**
- KV-cache hit rate is called "the single most important metric" for a production agent. Manus's average input:output token ratio is about 100:1. With Claude Sonnet, cached input costs $0.30/MTok versus $3/MTok uncached, a 10x difference. — [Manus blog](https://manus.im/blog/Context-Engineering-for-AI-Agents-Lessons-from-Building-Manus)
- Cache hygiene rules — [Manus blog](https://manus.im/blog/Context-Engineering-for-AI-Agents-Lessons-from-Building-Manus):
  - keep the prompt prefix stable, with no second-precision timestamp at the start of the system prompt
  - keep the context append-only and never modify earlier actions or observations
  - serialize deterministically, because JSON key order can silently break the cache
  - insert explicit cache breakpoints, at least at the end of the system prompt, and account for cache expiry
  - with vLLM, enable prefix caching and route by session ID
- **Mask, don't remove.** Adding or removing tools mid-task invalidates the cache, because tool definitions sit near the front of the context, and leaves dangling references to undefined tools. Manus instead uses a context-aware state machine that masks token logits during decoding. — [Manus blog](https://manus.im/blog/Context-Engineering-for-AI-Agents-Lessons-from-Building-Manus)
- Tool names share prefixes (`browser_`, `shell_`) so a whole group can be allowed or blocked per state. Three prefill modes are used — [Manus blog](https://manus.im/blog/Context-Engineering-for-AI-Agents-Lessons-from-Building-Manus):
  - Auto: the model may or may not call a tool
  - Required: the model must call a tool
  - Specified: the model must call from a subset, enforced by prefilling the function-name prefix
- **File system as context.** The sandbox file system is treated as unlimited, persistent, agent-operable memory. Compression must be restorable: page content can be dropped if the URL is kept, and document content can be dropped if its sandbox path is kept. — [Manus blog](https://manus.im/blog/Context-Engineering-for-AI-Agents-Lessons-from-Building-Manus)
- **Recitation.** A typical task takes about 50 tool calls. Manus creates and continually rewrites a `todo.md`, which pushes the global plan into the most recent part of the context and counters lost-in-the-middle effects and goal drift. — [Manus blog](https://manus.im/blog/Context-Engineering-for-AI-Agents-Lessons-from-Building-Manus)
- **Keep errors in context.** Failed actions and stack traces are left visible so the model updates its beliefs and avoids repeating mistakes. Error recovery is called one of the clearest signals of true agentic behavior. — [Manus blog](https://manus.im/blog/Context-Engineering-for-AI-Agents-Lessons-from-Building-Manus)
- **Avoid few-shot ruts.** Repetitive context makes agents mimic earlier patterns. The example is reviewing 20 resumes, where the agent drifts into a rut. Manus adds structured variation in serialization templates, phrasing, and ordering. — [Manus blog](https://manus.im/blog/Context-Engineering-for-AI-Agents-Lessons-from-Building-Manus)

**LangChain × Manus webinar (Peak Ji with Lance Martin, about October 14, 2025) [Secondary summaries of an official talk]**
- Manus uses a small set of fewer than 20 atomic functions (bash, filesystem, code execution) and pushes most actions into the sandbox layer instead of the function-calling layer. This is the "layered action space." — [ZenML LLMOps DB summary](https://www.zenml.io/llmops-database/context-engineering-for-production-ai-agents-at-scale); [Phil Schmid notes](https://www.philschmid.de/context-engineering-part-2)
- Context reduction runs in two stages — [ZenML summary](https://www.zenml.io/llmops-database/context-engineering-for-production-ai-agents-at-scale):
  - Compaction is reversible. It strips content that already exists in the environment, such as files and URLs.
  - Summarization is lossy. It runs only when compaction reaches diminishing returns, uses a schema to define the summary fields, and keeps the most recent tool calls in raw form.
- Sub-agents get their own action space and instructions, and the planner defines each sub-agent's output schema. Peak said Manus had been rebuilt five times since its March 2025 launch. — [ZenML summary](https://www.zenml.io/llmops-database/context-engineering-for-production-ai-agents-at-scale). Caveat: the date and some figures conflict across summaries; one summary dates the webinar to late 2024, others to October 2025. — [same](https://www.zenml.io/llmops-database/context-engineering-for-production-ai-agents-at-scale)

**Leaked system prompt and tools (gist by jlia0, March 2025) [Leak]**
- The leak has four files: an agent-loop prompt, a "modules" prompt, prompt.txt, and tools.json. — [gist](https://gist.github.com/jlia0/db0a9695b3ca7609c9b1a08dcbf872c9)
- The loop is: analyze events → select one tool → wait for execution → iterate with one tool call per step → submit results via message tools → enter standby (`idle`). — [gist](https://gist.github.com/jlia0/db0a9695b3ca7609c9b1a08dcbf872c9)
- Modules — [gist](https://gist.github.com/jlia0/db0a9695b3ca7609c9b1a08dcbf872c9):
  - a Planner that supplies numbered pseudocode plans with step status
  - a Knowledge module that injects scoped best practices
  - a Datasource module whose data APIs are callable only from Python code
- The event stream contains Message, Action, Observation, Plan, Knowledge, and Datasource events. `todo.md` is built from the plan, updated after each item, and rebuilt when the plan changes significantly. — [gist](https://gist.github.com/jlia0/db0a9695b3ca7609c9b1a08dcbf872c9)
- Messaging is split into a non-blocking `message_notify_user` and a blocking `message_ask_user`, which is reserved for essential questions. The agent must acknowledge new user messages immediately. — [gist](https://gist.github.com/jlia0/db0a9695b3ca7609c9b1a08dcbf872c9)
- Tool groups — [gist](https://gist.github.com/jlia0/db0a9695b3ca7609c9b1a08dcbf872c9):
  - `file_*`: read, write, str_replace, find_in_content, find_by_name
  - `shell_*`: exec, view, wait, write_to_process, kill_process
  - `browser_*`: about 12 tools, including console_exec
  - `info_search_web`
  - `deploy_expose_port`, `deploy_apply_deployment`, `make_manus_page`
  - `idle`
- The sandbox is Ubuntu 22.04 (linux/amd64) with a sudo-enabled `ubuntu` user, Python 3.10.12, and Node 20.18.0. — [gist](https://gist.github.com/jlia0/db0a9695b3ca7609c9b1a08dcbf872c9)
- The leak is also mirrored in the x1xhlol "system-prompts-and-models-of-ai-tools" collection. — [search summary of hoangyell write-up](https://hoangyell-com.pages.dev/system-prompts-ai-tools-leaked-explained/)
- Reliability: the tool prefixes (`browser_`, `shell_`) and the todo.md behavior match the later official post, which corroborates the leak.

**Security research on Manus [3P-RE]**
- Embrace The Red (wunderwuzzi) published on August 25, 2025. A PDF with hidden instructions hijacked Manus during summarization. The agent then — [Embrace The Red](https://embracethered.com/blog/posts/2025/manus-ai-kill-chain-expose-port-vs-code-server-on-internet):
  - read the VS Code Server password from a local config file
  - called `deploy_expose_port` with no human confirmation and no IP restriction
  - exfiltrated the URL and password through the browsing tool and markdown image rendering
  The result was full remote access to the dev box. — [same](https://embracethered.com/blog/posts/2025/manus-ai-kill-chain-expose-port-vs-code-server-on-internet)
- Recommendations were: domain allowlists, human approval for port exposure and other consequential tools, IP allowlisting of exposed ports, a CSP that blocks untrusted image rendering, an injection monitor, and documenting worst-case risks. The issue was reported on June 1, 2025, and the vendor's commitment to mitigations was unclear. — [Embrace The Red](https://embracethered.com/blog/posts/2025/manus-ai-kill-chain-expose-port-vs-code-server-on-internet)

**Wide Research [Official, with Press and Secondary]**
- Announced July 31, 2025 and initially limited to Pro. Coverage reported 100+ parallel agents, with demos such as analyzing 100 sneakers. — [VentureBeat](https://venturebeat.com/ai/youve-heard-of-ai-deep-research-tools-now-manus-is-launching-wide-research-that-spins-up-100-agents-to-scour-the-web-for-you); [Manus blog](https://manus.im/blog/introducing-wide-research)
- Manus's architecture post (October 29, 2025) says sequential processing in one context hits a "fabrication threshold": items 1–5 are researched well, quality degrades around items 6–8, and from item 9 on the agent fabricates from patterns. — [Manus blog](https://manus.im/blog/manus-wide-research-solve-context-problem)
- Wide Research works in four stages — [Manus blog](https://manus.im/blog/manus-wide-research-solve-context-problem):
  - A controller decomposes the request and writes a spec for each sub-task.
  - Each item gets a full Manus instance with its own VM, the full toolset, its own internet connection, and a fresh empty context.
  - Sub-agents do not talk to each other. All exchange goes through the controller, to avoid context pollution.
  - The controller synthesizes the results.
- The post advises against Wide Research for deeply sequential tasks or for fewer than about 10 items. — [Manus blog](https://manus.im/blog/manus-wide-research-solve-context-problem)
- Critics note there are no benchmarks and no explanation of how results are merged. Manus called the feature experimental. — [search summary incl. Geekflare](https://geekflare.com/news/manus-ai-launches-wide-research-5-things-no-ones-talking-about)

**Scheduling [Official/Press]**
- Scheduled Tasks 2.0 shipped on May 18, 2026. It covers recurring automation across tasks, projects, and Manus-built web apps, where recurring actions can be attached to the app itself. — [TestingCatalog](https://www.testingcatalog.com/manus-ai-released-scheduled-tasks-2-0-upgrade-for-all-users.md); [Times of AI](https://www.timesofai.com/news/scheduled-tasks-2-0/)
- The user chooses whether each run continues the existing context or starts fresh. There is a side panel, a calendar view, and run history. — [TestingCatalog](https://www.testingcatalog.com/manus-ai-released-scheduled-tasks-2-0-upgrade-for-all-users.md); [Manus blog](https://manus.im/blog/manus-schedules)

**Ownership and status [Press]**
- Meta bought Manus in December 2025 for a reported $2B+ (per the WSJ). The deal said there would be no continuing Chinese ownership, Manus would cease China operations, the subscription service would continue, and CEO Xiao Hong would report to Meta COO Javier Olivan. — [TechRadar](https://www.techradar.com/pro/meta-buys-manus-for-usd2-billion-to-power-high-stakes-ai-agent-race); [ABC7](https://abc7news.com/post/meta-buys-startup-manus-latest-move-advance-artificial-intelligence-efforts/18333425/)
- On April 27, 2026, China's NDRC ordered the deal unwound. It was the first public foreign-investment security-review prohibition, and regulators rejected "Singapore washing." — [Shumaker client alert](https://www.shumaker.com/insight/china-made-meta-give-back-a-2-billion-artificial-intelligence-acquisition-what-it-means-for-your-next-cross-border-deal/); [Dahui Lawyers](https://www.dahuilawyers.com/en/news-insights/chinas-first-public-foreign-investment-security-review-prohibition-the-metamanus-decision/); [Quartz](https://qz.com/manus-independent-meta-acquisition-china-unwind-081126)
- Meta cut Manus staff off from internal systems. Manus said on August 11, 2026 that it would operate independently again, pending final NDRC approval of the divestiture. — [TechTimes](https://www.techtimes.com/articles/318377/20260615/beijing-forces-meta-unwind-its-2-billion-manus-deal-offshore-move-was-no-escape.htm); [Quartz](https://qz.com/manus-independent-meta-acquisition-china-unwind-081126); [Startup Fortune](https://startupfortune.com/china-forces-meta-to-unwind-its-2-billion-dollar-acquisition-of-manus-ai/)
- Conflict: manus.im blog pages still carry the banner "Manus is now part of Meta," which is likely stale. — [Manus blog](https://manus.im/blog/manus-schedules)

### Inferences
- Manus's strongest transferable lessons concern the harness, not the model. Several of them map directly onto OpenCompany, which owns prompt caching in `app/Domain/Ai`:
  - keep the system prompt and tool list byte-stable within a run (no timestamps, deterministic JSON)
  - gate tools by policy or state rather than changing the tool array mid-run
  - use group prefixes (`vfs_*`, `mcp_<server>_*`) so a whole tool family can be gated by prefix
- OpenCompany's VFS work (`app/Agents/Tools/Vfs/*`) is the analogue of Manus's "file system as context." Two follow-ons:
  - Large tool outputs (MCP results, web pages) could be written to VFS paths, with only path, size, and summary kept in context. This is restorable compaction.
  - A per-task `todo.md` or plan file that the agent rewrites can act as recitation for long tasks and subagent runs.
- Wide Research supports OpenCompany's subagent delegation design:
  - Each subagent gets a fresh context and a controller-defined output schema.
  - There is no peer-to-peer chatter; everything goes through the orchestrator.
  - The orchestrator does the synthesis.
  - Fan-out is only worth it for many similar, independent items.
- The `deploy_expose_port` incident is a direct argument for OpenCompany's approval system. Any tool that widens exposure must require human approval regardless of agent autonomy level. This includes publishing files, sharing links, exposing ports, sending external messages, and changing MCP server config. Agent-rendered markdown images from untrusted domains are an exfiltration channel the chat UI should block or proxy.
- Manus's notify vs. ask split maps onto OpenCompany's chat and Telegram. Non-blocking progress notices and blocking questions should be distinct tool semantics, because a blocking ask pauses the run and should surface as an approval or question in Telegram.
- Vendor risk: Manus's ownership turmoil is a reminder that hosted-agent dependencies can change jurisdiction or ownership abruptly. This is an argument for a self-hosted product like OpenCompany.

### Gaps
- I found no primary recording or slides of the October 2025 LangChain webinar. The figures for compaction thresholds and sub-agent context sharing come from secondary summaries.
- I found no official detail on Manus's VM provider. E2B has been widely reported in 2025 press, but I did not verify it in this session.
- I did not verify Manus 1.5 or 1.6 architecture changes, Manus Browser Operator, or Manus Mail.
- The final NDRC approval of the divestiture was still pending as of the latest reporting (August 2026). I found no confirmation of final closure.

---

## 2. Google: Gemini Agent / Agent Mode, Project Mariner, Gemini Spark, Daily Brief, scheduled actions, Chrome auto browse and its security architecture, Jules, Managed Agents API, and Gemini agent for work

### Takeaway
Google moved from separate research agents to one shared harness:
- **Project Mariner** (December 2024 to May 4, 2026) was folded into **Gemini Agent** (Gemini 3, November 2025) and AI Mode.
- At I/O 2026 (May 19), Google launched **Gemini Spark**, a 24/7 cloud-VM personal agent on Gemini 3.5 and the **Antigravity harness**. Alongside it came Daily Brief, background "information agents" in Search, the Managed Agents API (sandboxed remote environments with `background: true`), and the Antigravity 2.0/CLI/SDK with subagents and hooks.
- In October 2026, Google Cloud launched **Gemini agent for work**. It features sub-agents with their own cryptographic identities, least-privilege access, audit trails, and per-project spend caps.

Google's most valuable published material for OpenCompany is the Chrome agentic security architecture: a User Alignment Critic, Agent Origin Sets, an injection classifier, deterministic confirmations, and a work log.

### Cited Findings

**Gemini Agent and Project Mariner [Press]**
- Gemini Agent launched with Gemini 3 Pro (November 2025) and extends Project Mariner's capabilities in the Gemini app. — [Engadget](https://www.engadget.com/ai/googles-new-gemini-3-model-arrives-in-ai-mode-and-the-gemini-app-160054273.html?src=rss); [Android Central](https://www.androidcentral.com/apps-software/ai/googles-gemini-agent-can-orchestrate-complex-tasks-on-your-behalf-in-the-gemini-app)
- It chains Deep Research, Canvas, and live browsing with Gmail and Calendar. Coverage says it always asks for confirmation before purchases or sending messages. — [Android Central](https://www.androidcentral.com/apps-software/ai/googles-gemini-agent-can-orchestrate-complex-tasks-on-your-behalf-in-the-gemini-app)
- Project Mariner shut down on May 4, 2026, after about 17 months; Wired spotted the shutdown first. The landing page says its tech "voyaged to other Google products" and points users to Gemini Agent. Coverage says features were absorbed into the Gemini API, Gemini Agent, and AI Mode. — [Android Authority](https://androidauthority.com/google-project-mariner-shutdown-3664323/); [Digital Trends](https://digitaltrends.com/computing/google-pulls-the-plug-on-project-mariner-the-ai-agent-that-browsed-the-web-like-a-human/); [TechSpot](https://www.techspot.com/news/112334-project-mariner-dead-but-google-browser-controlling-ai.html)

**Gemini Spark (I/O 2026, May 19) [Official]**
- Spark is a "24/7 personal AI agent" that runs on Gemini 3.5 and the Antigravity harness. It keeps working when the laptop is closed or the phone is locked. It integrates with Gmail, Docs, and Slides. — [Google blog: Gemini app becomes more agentic](https://blog.google/innovation-and-ai/products/gemini-app/next-evolution-gemini-app/)
- Users set recurring tasks or triggers (for example, parsing monthly card statements for hidden subscription fees) and can "teach Spark new skills" (for example, monitoring a school inbox and sending a daily digest to two people). — [Google blog](https://blog.google/innovation-and-ai/products/gemini-app/next-evolution-gemini-app/)
- Spark is "designed to ask you first before performing high-stakes actions like spending money or sending emails." Users choose whether to enable it and which apps it connects to. — [Google blog](https://blog.google/innovation-and-ai/products/gemini-app/next-evolution-gemini-app/); [100 things at I/O 2026](https://blog.google/innovation-and-ai/technology/ai/google-io-2026-all-our-announcements/)
- MCP connections launched with Canva, OpenTable, and Instacart. Planned items were texting and emailing Spark, custom sub-agents, local browser operation, and payments with budgets and merchant allowlists. — [Google blog](https://blog.google/innovation-and-ai/products/gemini-app/next-evolution-gemini-app/); [100 things](https://blog.google/innovation-and-ai/technology/ai/google-io-2026-all-our-announcements/)
- Spark runs on "dedicated virtual machines on Google Cloud." It later reached all U.S. AI Ultra subscribers and is expanding to AI Pro in Canada, India, and Australia. — [Google Canada blog](https://blog.google/intl/en-ca/products/explore-get-answers/introducing-gemini-spark-your-247-personal-ai-agent-in-canada/); [Google India blog](https://blog.google/intl/en-in/company-news/technology/introducing-gemini-spark-your-247-personal-ai-agent-in-country/)
- Conflict: the India post cites Gemini 3.6 Flash, while other posts say 3.5. — [Google India blog](https://blog.google/intl/en-in/company-news/technology/introducing-gemini-spark-your-247-personal-ai-agent-in-country/)
- Unverified: a blog claims each task runs in an isolated ephemeral VM, and secondary sources describe a dedicated Gmail address for emailing Spark. — [buildfastwithai](https://www.buildfastwithai.com/blogs/gemini-spark-google-ai-agent-how-it-works) [Secondary]

**Other I/O 2026 agent launches [Official]**
- **Daily Brief:** an opt-in overnight agent that compiles urgent Gmail and Calendar items into a morning briefing, suggests next steps, and learns from thumbs up/down feedback. — [Google blog](https://blog.google/innovation-and-ai/products/gemini-app/next-evolution-gemini-app/)
- **Search "information agents":** run 24/7 in the background, monitoring for changes related to a question; several can run at once. — [100 things](https://blog.google/innovation-and-ai/technology/ai/google-io-2026-all-our-announcements/)
- **Antigravity 2.0:** a desktop app for running multiple agents in parallel, plus a CLI that replaces Gemini CLI and an SDK for hosting the harness on your own infrastructure. New primitives are subagents, hooks, and async task management. — [100 things](https://blog.google/innovation-and-ai/technology/ai/google-io-2026-all-our-announcements/)
- **WebMCP:** proposed as a standard for sites to expose structured tools to browser agents. — [100 things](https://blog.google/innovation-and-ai/technology/ai/google-io-2026-all-our-announcements/)

**Scheduled actions in the Gemini app [Official/Secondary]**
- Scheduled actions are recurring daily, weekly, or monthly prompts. Gemini prepares content within the hour before delivery, so they are unsuitable for time-critical alerts. Actions can be paused, and there is a limit of 10 active scheduled actions. — [Gemini Help](https://support.google.com/gemini/answer/16316416?hl=en-GB); [Google blog](https://blog.google/products/gemini/scheduled-actions-gemini-app/)
- They are unavailable when "Keep Activity" is off, and location-bound actions keep the location where they were created. — [itechguides](https://www.itechguides.com/gemini-app-rolling-out-scheduled-actions-to-ai-pro-and-ai-ultra-how-it-works-now/) [Secondary]

**Chrome agentic browsing [Official/Press]**
- Chrome auto browse launched on January 28, 2026 for U.S. AI Pro and Ultra subscribers using Gemini 3. — [Android Authority](https://www.androidauthority.com/chrome-auto-browse-3635682/)
- Android rollout reported in August 2026. — [tuttoandroid](https://www.tuttoandroid.net/news/2026/08/19/gemini-google-chrome-auto-browse-abbonati-ai-pro-ultra-1168148/)
- A June 2026 Chrome post said desktop auto browse would integrate with Spark. — [search summary](https://thenextweb.com/news/google-chrome-enterprise-ai-coworker-agentic-browser) [Press, unverified]

**Chrome security architecture (Nathan Parker, December 8, 2025) [Official]**
- **User Alignment Critic:** a separate Gemini model that vets each planned action after planning. It sees only action metadata, never unfiltered web content, so it cannot be poisoned directly. A veto sends feedback to the planner, and repeated failures return control to the user. It was inspired by the dual-LLM pattern and DeepMind's CaMeL. — [Google Security blog](https://blog.google/security/architecting-security-for-agentic/)
- **Agent Origin Sets:** a trusted gating function sorts origins into read-only and read-write sets. Iframes from unrelated origins are hidden from the model. Data flows only from read-only to writable origins. Non-web tool calls are also classified as read or write. — [Google Security blog](https://blog.google/security/architecting-security-for-agentic/)
- Planner-initiated or page-initiated navigation to new origins is vetted by a critic variant. Model-generated URLs pass a deterministic check restricting them to known public URLs. The first shipped version tracked only the read-write set. — [Google Security blog](https://blog.google/security/architecting-security-for-agentic/)
- **Defenses:**
  - a prompt-injection classifier runs on every page, in parallel with planning
  - "spotlighting" prompts tell the model to prefer user and system instructions over page content
  - adversarial training
  - automated red-teaming with LLM-expanded attack sites — [Google Security blog](https://blog.google/security/architecting-security-for-agentic/)
- **Deterministic confirmations:** required before navigating to banking or medical sites (checked against a list), before signing in via Password Manager (the model never sees passwords), and before purchases, payments, or sending messages. A real-time work log lets the user pause or take over. The VRP pays up to $20k for agentic boundary breaches. — [Google Security blog](https://blog.google/security/architecting-security-for-agentic/)

**Jules (async coding agent) [Official docs, Press, Secondary]**
- Each task clones the repo into an isolated cloud VM (Ubuntu with common toolchains and Docker). Jules drafts a plan, edits, tests, and opens a PR. It reads AGENTS.md for repo context. — [InfoWorld](https://www.infoworld.com/article/4086269/agentic-coding-with-google-jules.html); [KDnuggets](https://www.kdnuggets.com/agentic-ai-coding-with-google-jules)
- Plan approval is configurable rather than a hard gate. Plans can auto-approve in the web UI, and the API exposes `requirePlanApproval`. — [rywalker research](https://rywalker.com/research/jules) [Secondary]
- Adding the `jules` label to a GitHub issue triggers a task. — [Secondary summary](https://www.oneclickitsolution.com/centerofexcellence/aiml/jules-ai-coding-agent-for-github)
- In December 2025 Jules gained two proactive features. "Suggested Tasks" (Pro/Ultra) scans repos, starting with `#todo` comments, and proposes plans for approval. "Scheduled Tasks" handles recurring maintenance such as dependency checks. The docs say scheduled tasks suit low-oversight maintenance. There is also a Render integration that analyzes failed deploys and opens fix PRs. — [Jules docs: Scheduled Tasks](https://jules.google/docs/scheduled-tasks/); [Tessl](https://tessl.io/blog/google-wants-to-make-jules-a-more-proactive-coding-colleague); [Forest Watch](https://forest.watch.impress.co.jp/docs/news/2071962.html)
- 2026 status: secondary sources mention a "major update" at I/O 2026, but Google's official I/O list did not mention Jules. — [100 things](https://blog.google/innovation-and-ai/technology/ai/google-io-2026-all-our-announcements/)

**Managed Agents in the Gemini API (expanded July 7, 2026) [Official]**
- A single call to the Antigravity agent provisions an isolated remote sandbox. In it the agent reasons, runs code, installs packages, manages files, and searches. — [Google blog](https://blog.google/innovation-and-ai/technology/developers-tools/expanding-managed-agents-gemini-api/)
- With `background: true`, the call returns an interaction ID, and the client can poll, stream, or reconnect. — [Google blog](https://blog.google/innovation-and-ai/technology/developers-tools/expanding-managed-agents-gemini-api/)
- A remote MCP server is declared as a `{name, url}` tool. — [Google blog](https://blog.google/innovation-and-ai/technology/developers-tools/expanding-managed-agents-gemini-api/)
- Custom functions move the interaction to a `requires_action` state, and the client returns `function_result`. — [Google blog](https://blog.google/innovation-and-ai/technology/developers-tools/expanding-managed-agents-gemini-api/)
- Credentials are refreshed by supplying a new `network` allowlist with header `transform` on an existing `environment_id`, which keeps the filesystem state. Agents can be extended with AGENTS.md and SKILL.md. — [Google blog](https://blog.google/innovation-and-ai/technology/developers-tools/expanding-managed-agents-gemini-api/); [100 things](https://blog.google/innovation-and-ai/technology/ai/google-io-2026-all-our-announcements/)

**Gemini agent for work (Google Cloud, October 8, 2026) [Press]**
- It plans, uses tools, and connects to business systems. It spawns "temporary, job-specific agents, each with their own identity" and can run for hours or days in the cloud. — [CNBC](https://www.cnbc.com/amp/2026/10/08/google-cloud-introduces-gemini-agent-for-work-as-ai-race-heats-up.html); [Reworked](https://www.reworked.co/collaboration-productivity/google-debuts-its-all-purpose-gemini-agent-for-work/)
- Reported identity and governance features — [eesel](https://www.eesel.ai/blog/gemini-agent) [Secondary]; [Reworked](https://www.reworked.co/collaboration-productivity/google-debuts-its-all-purpose-gemini-agent-for-work/):
  - each agent gets a cryptographic identity, least-privilege permissions, and an audit trail credited to the agent rather than an employee
  - persistent "coworker" agents get Workspace presence (email, calendar, Drive)
  - admins approve role-based access and set per-project spend caps
- Conflicts: sources disagree on whether the agent runs Gemini only or also Claude, and on how paused-over-budget agents resume. — [CNBC](https://www.cnbc.com/amp/2026/10/08/google-cloud-introduces-gemini-agent-for-work-as-ai-race-heats-up.html); [eesel](https://www.eesel.ai/blog/gemini-agent)

### Inferences
- **Proactive pattern library.** Google's consumer agents have converged on a small set of trigger types, which matches OpenCompany's scheduled automations:
  - scheduled prompts (scheduled actions, Jules Scheduled Tasks, Daily Brief)
  - event and monitor triggers (Spark triggers, Search information agents)
  - inbound channels such as "email/text Spark", which parallels Telegram
  - repo- or inbox-scanning suggestion agents (Jules Suggested Tasks)
  OpenCompany could add two Google-style features:
  - Suggestion agents whose output is a proposed task that needs approval, not an executed action.
  - A per-automation "continue context vs. fresh start" toggle, as in Manus Scheduled Tasks 2.0.
- Google's choice to prepare scheduled-action output within the hour before delivery, plus the 10-active cap, suggests cost and scheduler load-shedding. A self-hosted scheduler should still define jitter, concurrency caps, and per-workspace quotas.
- The Chrome User Alignment Critic is a pattern OpenCompany can adopt cheaply. Before executing approval-relevant or external-mutation tool calls, run a second model call that sees only the user's goal and the tool-call metadata (tool name, arguments, target), never raw tool outputs or web content. A rejection goes back to the agent, and repeated rejections escalate to a human approval. This complements, rather than replaces, deterministic approval rules.
- **Agent Origin Sets as a model for MCP and integrations.** Classify each MCP tool and integration as read or write. Declare per task or automation which resources are read-only and which are writable. Restrict data flow so content read from one integration cannot be pushed to an arbitrary other integration without a gate. Model-generated URLs used in outbound requests should be checked deterministically.
- **Identity per agent and subagent.** Gemini agent for work credits audit trails to an agent identity and caps spend per project. OpenCompany already gives agents a `workspace_id`. The lessons are:
  - attribute every external mutation and approval to the specific agent or subagent run
  - add per-agent or per-automation budget caps that pause rather than silently continue
- The Managed Agents API shape (`background: true`, an interaction ID, a `requires_action` state for client-side tools, credential refresh that keeps the sandbox) is a good reference contract for OpenCompany's long-running agent runs and approvals. An approval-pending run looks the same as `requires_action`.

### Gaps
- I found no official Google technical write-up on the Antigravity harness internals (memory, compaction, tool loop) or on Gemini Agent's (November 2025) architecture.
- I found no official confirmation of per-task ephemeral VMs for Spark.
- Project Astra: I found no 2026-specific status in this session, and Astra was not mentioned in Google's I/O 2026 "100 things" list. Astra capabilities have been described in prior years as flowing into Gemini Live, but I did not verify this here.
- I found no leaked Gemini Agent or Spark system prompt from a reliable source in this session.
- The Jules I/O 2026 update details are unverified.
- Whether desktop Chrome auto browse and Spark integration has shipped is unconfirmed.

---

## 3. Perplexity Comet and prompt-injection research on browsing agents (Brave, Guardio, LayerX, Trail of Bits)

### Takeaway
Comet is the most thoroughly attacked agentic browser. Researchers showed injections through:
- hidden page text, such as Reddit spoiler tags
- screenshots, via near-invisible text
- crafted URLs (CometJacking)
- fake CAPTCHAs and fake system or user messages

These injections exfiltrated Gmail OTPs and other data from the user's authenticated sessions. Perplexity's response is layered: injection classifiers (BrowseSafe), tool-level guardrail prompts, confirmations for sending email or messages and for calendar edits, and notifications when an injection is blocked. Perplexity itself admits prompt injection is unsolved.

### Cited Findings
- **Brave (August 20, 2025) [3P-RE]:** hidden instructions in a Reddit spoiler made Comet's "summarize" action navigate to Gmail, retrieve an OTP, and post it back to Reddit. Brave reported this in July 2025. The fix was found incomplete on retest, though sources conflict on the final status. — [Simon Willison](https://simonwillison.net/2025/Aug/25/agentic-browser-security/); [Beebom](https://beebom.com/perplexity-comet-ai-browser-hijacked-through-malicious-instructions); [SQ Magazine](https://sqmagazine.co.uk/comet-browser-security-flaw-exposed-user-data)
- **Brave (October 2025) [3P-RE]:** "unseeable" prompt injections in screenshots. Near-invisible text in images was extracted (Brave suspects OCR) and followed. Brave also found that Fellou treated visible page content as trusted after navigation, and disclosed an Opera Neon injection. Brave concluded indirect prompt injection is "a systemic challenge facing the entire category of AI-powered browsers." — [Brave blog](https://brave.com/blog/unseeable-prompt-injections/); [Simon Willison](https://simonwillison.net/2025/Oct/21/unseeable-prompt-injections/)
- **LayerX "CometJacking" (October 2025) [3P-RE]:** a crafted URL's `collection` parameter carried instructions that made the agent consult memory and connected services (Gmail, Calendar) and exfiltrate via Base64 encoding to bypass protections. Perplexity rated it "no security impact." — [The Hacker News](https://thehackernews.com/2025/10/cometjacking-one-click-can-turn.html)
- **Guardio "Scamlexity" (2025) [3P-RE]:** AI browsers including Comet could be steered into interacting with phishing pages and fake shops without user knowledge. — [The Hacker News](https://thehackernews.com/2025/10/cometjacking-one-click-can-turn.html) (secondary mention; I did not fetch Guardio's primary post)
- **Trail of Bits audit (published February 20, 2026, commissioned pre-launch) [3P-RE, commissioned]:**
  - Four injection techniques worked for exfiltrating Gmail data: summarization instructions, fake security mechanisms (CAPTCHA or validator), fake system instructions, and fake user requests.
  - The trust boundary runs between the local browser (cookies, profiles) and Perplexity's servers. Tools such as URL fetch, browser control, and history search create data paths across it.
  - Recommendations: ML-centered threat modeling; separate trust levels for system, user, and external content; regular red-teaming; least privilege; and treating all AI input as untrusted. — [Trail of Bits](https://blog.trailofbits.com/2026/02/20/using-threat-modeling-and-prompt-injection-to-audit-comet/)
- **Perplexity's defenses [Official, via snippets]:**
  - The October 22, 2025 "Mitigating Prompt Injection in Comet" post describes ML classifiers for hidden instructions, tool-level prompts warning about injection in external content, user confirmation before sending emails or messages and modifying the calendar (with context on what and why), and a notification when an attack is blocked. It concedes the problem "remains unsolved." — [Perplexity blog](https://hub-prod.perplexity.ai/hub/blog/mitigating-prompt-injection-in-comet); [Evoke Security](https://www.evokesecurity.com/blogs/securing-ai-browsers)
  - BrowseSafe is a detection model plus an open benchmark (BrowseSafe-Bench, 14,719 HTML attack examples across 11 attack types) that scans tool output before the agent acts. — [BGR](https://bgr.com/2046869/perplexity-ai-browser-security-system-browsesafe); [C# Corner](https://www.c-sharpcorner.com/news/perplexity-launches-browsesafe-to-secure-the-next-era-of-agentic-web-browsing)
  - The hardest attacks to detect were embedded in visible page text. — [Weekend Byte](https://weekendbyte.com/p/securing-ai-browsers)
- **Product status [Press]:** Comet became free in October 2025. Max subscribers got a "Background Assistant" that runs multiple tasks in the background. — [TechCrunch](https://techcrunch.com/2025/10/02/perplexitys-comet-ai-browser-now-free-max-users-get-new-background-assistant/)
- A claimed mid-2026 rebuild of Comet Assistant is unverified. — [geotoolbox](https://geotoolbox.ai/blog/perplexity-comet) [Secondary]

### Inferences
- **Treat any "summarize this" input as hostile.** In OpenCompany that means web fetches, MCP results, inbound emails, Telegram messages from non-members, uploaded PDFs, and VFS files written by other agents. Trail of Bits' finding that fake system and user messages work means tool output should be wrapped in clearly delimited, provenance-labeled blocks (spotlighting), and the agent prompt should state that such blocks never carry instructions.
- **Avoid the "lethal trifecta":** private data plus untrusted content plus an exfiltration channel. Every Comet exploit chained all three. OpenCompany's equivalents of the exfiltration channel are outbound HTTP tools, sending Telegram or email, posting to chat channels, rendering remote markdown images, and MCP write tools. Approval rules should be strictest when a run has both ingested untrusted content and holds private-data tools. Taint tracking per run, which escalates approval requirements after untrusted content enters, is a feasible variant of Google's origin-set rule.
- **Confirmations should explain the action.** Perplexity's confirmation shows what the agent is trying to do and why, and the user is told when an injection is blocked. OpenCompany approval cards in web and Telegram should show the tool, arguments, target, the agent's stated rationale, and the provenance of any content that motivated the action.
- **Build an injection regression suite.** OpenCompany could build a small fixture suite from the published attack classes (hidden spoiler text, fake CAPTCHA, fake system message, fake user message, URL-parameter injection, Base64 exfiltration) to test agents with web or MCP access. This mirrors Trail of Bits' recommendation 3.

### Gaps
- I did not fetch Guardio's primary Scamlexity post or Perplexity's primary posts directly; those details come from snippets.
- The BrowseSafe "90% detection" figure is unconfirmed.
- I found no detail on Comet Assistant's internal architecture (which model, how the local and cloud split works for tool execution, or memory).

---

## 4. Microsoft Copilot (Copilot Actions, agent workspace, Mico, Groups, memory)

### Takeaway
Microsoft's most transferable design is OS-level containment for computer-use agents:
- a separate **agent account** (a standard Windows user)
- a separate **agent workspace** desktop with runtime isolation
- default access only to known folders, with authorization required for more
- signed agents that can be revoked
- the user can monitor and take over, and approves sensitive steps

On the consumer side, the Copilot Fall Release (October 2025) added Mico (an avatar), Groups (up to 32 people in one chat), long-term memory, and Edge Copilot Mode with Actions and Journeys.

### Cited Findings
- **Copilot Actions on Windows [Official]:** an experimental agent that uses vision and reasoning to click, type, and scroll in apps and local files. It is off by default (Settings > System > AI components > Agent tools > Experimental agentic features). — [Windows Experience blog, Dana Huang, October 16, 2025](https://blogs.windows.com/windowsexperience/2025/10/16/securing-ai-agents-on-windows/)
- **Agent workspace:** its own desktop with runtime isolation and limited visibility into the user's desktop. **Agent accounts:** separate standard accounts, provisioned only when the workspace is enabled, which allow agent-specific ACLs. — [Windows blog](https://blogs.windows.com/windowsexperience/2025/10/16/securing-ai-agents-on-windows/); [Microsoft Learn](https://learn.microsoft.com/en-us/windows/security/book/operating-system-agentic-security)
- **Least privilege:** in preview, agents can access only known folders (Documents, Downloads, Desktop, Pictures) plus resources all accounts can reach. Anything else needs user authorization, which can be revoked. Agents must be signed so they can be revoked and blocked. Cross-prompt injection (XPIA) is explicitly named as a threat, alongside hallucination. — [Windows blog](https://blogs.windows.com/windowsexperience/2025/10/16/securing-ai-agents-on-windows/)
- **Copilot Fall Release (October 2025) [Press]:**
  - Mico avatar with a Learn Live tutor mode
  - Groups for up to 32 people
  - long-term memory
  - Edge Copilot Mode with Actions (booking, form filling with permission) and Journeys (topic-grouped past sessions)
  - Proactive Actions requires a Microsoft 365 Personal, Family, or Premium subscription — [InfoQ](https://www.infoq.com/news/2025/11/microsoft-copilot-fall-release/); [The Decoder](https://the-decoder.com/microsoft-copilot-introduces-a-group-chat-feature-that-lets-up-to-32-people-collaborate-in-real-time/); [Built In](https://builtin.com/articles/microsoft-launches-copilot-mode-edge-20251024)

### Inferences
- The agent-account and agent-workspace model matches OpenCompany's split where agents have their own `workspace_id` identity and humans are members. The lessons are to:
  - run agent tool execution (VFS exec, scripts, MCP) under agent-scoped credentials, never the invoking human's full session
  - give least-privilege defaults, with only an agent's own VFS area writable by default
  - make extra grants explicit and revocable
- Groups (multi-human chat with one agent) validates OpenCompany's multi-human channels with agents. Memory scoping (per user vs. shared) becomes a design question there, which matches Lindy's per-user and per-team split in section 6.

### Gaps
- I found no 2026 Microsoft update on Copilot Actions general availability, Windows agent workspace GA, or Microsoft 365 Copilot agent mode, and did not research them further.
- I found no leaked Copilot system prompt from a reliable source in this session.

---

## 5. Amazon Alexa+ and Nova Act

### Takeaway
Alexa+ (launched 2025) is a model-routed system. A router on Bedrock picks among models, including Nova and Claude, and delegates to hundreds of "experts," which are bundles of APIs, instructions, and deterministic controls. It falls back to Nova Act browser automation when APIs do not exist. Nova Act's main lesson is about reliability: decompose work into small, reliable atomic browser steps, and mix in deterministic Playwright code rather than relying on end-to-end autonomous browsing.

### Cited Findings
- Alexa+ uses a routing system on Bedrock (Nova and Anthropic Claude) to match each request to the best model. — [Constellation Research](https://www.constellationr.com/insights/news/amazons-alexa-gets-brain-transplant-showcases-amazon-bedrock-nova) [Press]
- At the heart of Alexa+ are "experts": specialized groups of capabilities, APIs, and instructions. An AI Engineer World's Fair 2025 talk described hundreds of such experts, with Bedrock as the default provider. — [Constellation Research](https://www.constellationr.com/insights/news/amazons-alexa-gets-brain-transplant-showcases-amazon-bedrock-nova); [AI Engineer talk](https://www.ai.engineer/talks/WJjInLeaJjo-building-agents-at-cloud-scale)
- Alexa+ costs $19.99/month and is free with Prime. — [Constellation Research](https://www.constellationr.com/insights/news/amazons-alexa-gets-brain-transplant-showcases-amazon-bedrock-nova)
- Alexa+ uses Nova Act for core browser automation, behind the Alexa AI Web Action developer offering, when services lack APIs. — [Amazon Science: Nova Act](https://www.amazon.science/blog/nova-act) [Official]
- Amazon's reliability claims [Official, self-reported] — [Amazon Science: prototype to production](https://www.amazon.science/blog/prototype-to-production); [Amazon Science: Nova Act](https://www.amazon.science/blog/nova-act):
  - 90%+ end-to-end reliability on early enterprise use cases
  - over 90% on atomic UI interactions such as date pickers and popups
  - for comparison, state-of-the-art models score 30–60% on high-level browser task benchmarks
  - the SDK lets developers interleave direct Playwright manipulation

### Inferences
- The "experts" design is a curated, deterministic-plus-LLM capability bundle, similar to OpenCompany's integration packages and MCP servers plus instructions. Pairing each integration with its own instructions (skill text) and deterministic guards is more reliable than a single flat tool list.
- Nova Act's lesson for OpenCompany's browser and computer-use plans is to prefer APIs and MCP. Use browser automation only as a fallback, and structure it as small verifiable steps with deterministic code where possible.

### Gaps
- I found no 2026 news on Alexa+ architecture changes or on Nova Act's status (GA, pricing) in this session.
- The reliability figures are vendor-reported, with no independent audit.

---

## 6. Other always-on and proactive agents: Poke, Lindy, Genspark, Dia

### Takeaway
The most useful engineering disclosure here is **Lindy's August 2026 memory post**. Lindy replaced RAG with a per-user and per-team file system that a background memory agent rewrites every 12 hours. An index file and a master file are always in context, and a deterministic verifier enforces size limits and reachability. **Poke** shows that messaging-native, proactive agents (iMessage, SMS, Telegram) are a viable product form. **Genspark** shows orchestrator routing across many models and tools. **Dia** is now owned by Atlassian.

### Cited Findings
- **Lindy memory (Flo Crivello, August 16, 2026) [Official]** — [Lindy Engineering](https://www.lindy.ai/engineering/memory-agent):
  - RAG was dropped as ineffective, complex, and outpaced by growing context windows.
  - Each team has a shared file system and each user has a private one, both starting as a single empty file.
  - A job runs every 12 hours per user and per team. It hydrates from Slack (private channels for users, public for the team, and only channels where Lindy was added), Gmail (user only), meetings, and Lindy tasks.
  - A summarizer condenses "what happened since last run," preserving procedural memory. Memories are categorized as episodic, procedural, or semantic.
  - A memory agent creates, updates, reorganizes, or deletes files. The index file (listing all files with descriptions) and the master file are always in context.
  - A deterministic verifier checks that every file fits in the context window and that every file is linked from the index or master. Unlinked files are "dead memory."
  - The author says the design is more token-hungry but easier to reason about.
- **Lindy triggers and swarms:** workflows are triggered by messages or schedules. "Swarms" (agent cloning) appear only in marketing material. A newer review mentions "Autopilot," a cloud computer for browser use. — [softwarefinder](https://softwarefinder.com/sales-tools/lindy) [Secondary]; [ZenML: from open-ended agents to guided workflows](https://zenml.io/llmops-database/evolution-from-open-ended-llm-agents-to-guided-workflows)
- **Lindy's "guided workflows":** Lindy evolved from open-ended LLM agents to guided workflows for reliability. — [ZenML LLMOps DB](https://zenml.io/llmops-database/evolution-from-open-ended-llm-agents-to-guided-workflows) [Secondary]
- **Poke (The Interaction Company of California) [Press/Secondary]:**
  - A proactive assistant accessed entirely through messaging (iMessage, SMS, Telegram) that routes tasks across models and acts through Gmail, Calendar, Notion, and other services. — [TechCrunch](https://techcrunch.com/?p=3110530); [AI Agent Index](https://theaiagentindex.com/agents/poke)
  - Reported on June 4, 2026 as the first AI agent approved for Apple Messages for Business, previously reached via Linq. — [TechWyse](https://www.techwyse.com/news/platform-updates/apple-messages-for-business-first-ai-agent-poke-approved); [Let's Data Science](https://letsdatascience.com/news/poke-joins-apples-messages-app-as-ai-agent-10b4db92)
  - Unverified aggregator report: Cognition acquired the Interaction Company in July 2026. — [KuCoin flash](https://www.kucoin.com/news/flash/ai-programming-firm-cognition-acquires-poke-developer-interaction) [Secondary, unverified]
- **Genspark Super Agent [Secondary]:**
  - Launched as a "mixture-of-agents" system with 9 LLMs and 80+ tools; by 2026 reviewers describe 30+ models.
  - An orchestrator decomposes the task, routes sub-tasks by cost and capability, aggregates outputs, and runs a reflection check. — [eesel](https://www.eesel.ai/blog/genspark-ai); [AI Dev 25 talk summary](https://summify.io/discover/ai-dev-25-x-nyc-kay-zhu-how-genspark-built-a-super-agent-tha-Q24UKZ/)
- **Dia (The Browser Company) [Press]:**
  - Atlassian agreed in September 2025 to acquire it for about $610M; the company keeps operating independently.
  - As of March 2026 it was macOS-only, with Slack integrations added and Jira and Trello integration planned. — [Engadget](https://www.engadget.com/big-tech/atlassian-is-buying-arc-maker-the-browser-company-for-610-million-145236236.html); [Wikipedia](https://en.wikipedia.org/wiki/Dia_(web_browser))

### Inferences
- Lindy's memory design maps almost directly onto OpenCompany's memory and VFS:
  - per-agent and per-workspace memory directories
  - a scheduled consolidation job (OpenCompany already has scheduled automations and queues)
  - an always-loaded index and master file
  - a deterministic verifier enforcing size limits and reachability
  This also aligns with Manus's "file system as memory." A strong design option for OpenCompany is a nightly memory-consolidation automation per agent and per workspace, with memory scoped to `workspace_id` and private user memory kept separate from team memory.
- Poke supports OpenCompany's Telegram channel as a primary interface. A proactive agent that messages first (digests, alerts, approval requests) on a chat channel is the product pattern users see as "always-on."
- Lindy's move from open-ended agents to guided workflows, and Alexa's experts, argue that OpenCompany automations should support deterministic step structure (script automations) alongside free-form prompt automations. OpenCompany already distinguishes `ExecutePromptAutomation` and `ExecuteScriptAutomation`.

### Gaps
- I found no primary technical documentation for Poke's or Genspark's architecture.
- Lindy's swarm and Autopilot internals are unverified.
- I did not research Dia's assistant architecture or prompt leaks; the itsrobmack repo claims Dia prompts but I did not verify it.

---

## 7. Cross-cutting design lessons for OpenCompany

### Takeaway
Across Manus, Google, Perplexity, Microsoft, Amazon, and Lindy, the converging best practices fall into seven areas:
- **Context:** keep it cache-stable and append-only, and mask tools by policy rather than mutating the tool list.
- **Memory:** externalize it into a file system with an index and a rewritten plan file (recitation).
- **Compression:** compact reversibly before summarizing lossily.
- **Delegation:** fan out to fresh-context subagents with a controller-defined output schema and no peer chatter.
- **Proactivity:** a small set of trigger types (schedule, monitor or event, inbound message, suggestion-for-approval), with a "fresh vs. continued context" choice.
- **Isolation:** agent-specific identities and least-privilege sandboxes.
- **Defense in depth against prompt injection:** classifier, spotlighting, an alignment critic that sees only metadata, origin or resource read/write sets, deterministic URL checks, and explanatory human confirmations for consequential actions. Every vendor admits prompt injection is unsolved.

### Cited Findings
- KV-cache-first context: stable prefix, append-only, deterministic serialization, explicit breakpoints; about 100:1 input:output and a 10x cached-price difference. — [Manus](https://manus.im/blog/Context-Engineering-for-AI-Agents-Lessons-from-Building-Manus)
- Mask tools instead of removing them; prefix-grouped tool names; Auto, Required, and Specified call modes. — [Manus](https://manus.im/blog/Context-Engineering-for-AI-Agents-Lessons-from-Building-Manus)
- File system as memory with restorable compression; todo.md recitation over about 50 tool calls; keep errors in context; add variation to avoid few-shot ruts. — [Manus](https://manus.im/blog/Context-Engineering-for-AI-Agents-Lessons-from-Building-Manus)
- A layered action space (fewer than 20 atomic tools, with the rest in the sandbox); compaction before schema-based summarization. — [ZenML summary](https://www.zenml.io/llmops-database/context-engineering-for-production-ai-agents-at-scale)
- Parallel fresh-context subagents, communicating only through the controller, outperform one long sequential context for many-item tasks (vendor claim, no benchmarks). — [Manus Wide Research](https://manus.im/blog/manus-wide-research-solve-context-problem)
- File-system memory with an always-in-context index and master file, consolidated every 12 hours, plus a deterministic verifier. — [Lindy](https://www.lindy.ai/engineering/memory-agent)
- Alignment critic that sees metadata only; origin read/write sets; injection classifier; spotlighting; deterministic sensitive-site, password, and payment confirmations; work log with takeover. — [Google Chrome security](https://blog.google/security/architecting-security-for-agentic/)
- Classifiers, tool-level guardrail prompts, confirmations with what and why, and notification when an injection is blocked. — [Perplexity](https://hub-prod.perplexity.ai/hub/blog/mitigating-prompt-injection-in-comet)
- Threat model trust boundaries, separate trust levels for system, user, and external content, red-team regularly, and least privilege. — [Trail of Bits](https://blog.trailofbits.com/2026/02/20/using-threat-modeling-and-prompt-injection-to-audit-comet/)
- Exposure-widening tools (port exposure) without approval enabled full compromise; markdown image rendering acts as an exfiltration channel. — [Embrace The Red](https://embracethered.com/blog/posts/2025/manus-ai-kill-chain-expose-port-vs-code-server-on-internet)
- Agent accounts, a contained workspace, known-folder defaults, and signed and revocable agents. — [Windows blog](https://blogs.windows.com/windowsexperience/2025/10/16/securing-ai-agents-on-windows/)
- Per-agent cryptographic identity, an audit trail credited to the agent, and per-project spend caps. — [Reworked](https://www.reworked.co/collaboration-productivity/google-debuts-its-all-purpose-gemini-agent-for-work/)
- Background execution contract: `background: true`, interaction ID, poll, stream, or reconnect, a `requires_action` state, and credential refresh that keeps sandbox state. — [Google Managed Agents](https://blog.google/innovation-and-ai/technology/developers-tools/expanding-managed-agents-gemini-api/)
- Proactive trigger types:
  - scheduled actions (10 active max; prepared within the hour before delivery) — [Gemini Help](https://support.google.com/gemini/answer/16316416?hl=en-GB)
  - Spark triggers and skills — [Google blog](https://blog.google/innovation-and-ai/products/gemini-app/next-evolution-gemini-app/)
  - Jules Suggested Tasks, which need approval, and Scheduled Tasks — [Jules docs](https://jules.google/docs/scheduled-tasks/)
  - Manus scheduled runs with a fresh vs. continued context option — [TestingCatalog](https://www.testingcatalog.com/manus-ai-released-scheduled-tasks-2-0-upgrade-for-all-users.md)
- Prefer APIs; use browser automation as a fallback made of small reliable steps mixed with deterministic code. — [Amazon Science](https://www.amazon.science/blog/prototype-to-production)

### Inferences
Concrete OpenCompany recommendations, each derived from the findings above:

1. **Prompt cache discipline in `app/Domain/Ai`.**
   - Keep the identity and system prompt assembly, and the tool schema list, byte-stable for the life of a run.
   - Move volatile data (current time, run counters) to the tail of the context.
   - Enforce deterministic JSON key order for tool schemas and tool results.
   - Track cache hit rate per run as a first-class metric next to cost accounting.
2. **Tool gating without schema churn.**
   - Approval or permission state should be enforced by deterministic denial at execution time (returning a structured "requires approval" observation) rather than by adding or removing tools mid-run.
   - Use consistent prefixes (`vfs_*`, `task_*`, `mcp_<server>_*`, `browser_*`) so policies can target groups.
   - Where a provider supports `tool_choice` or allowed-tools subsets, use those instead of rebuilding the tools array.
3. **Restorable compaction via VFS.**
   - Spill large MCP or web results into VFS files and keep path, size, and summary in context.
   - Only summarize the trajectory, with a schema and the latest N tool calls kept raw, after compaction stops helping.
4. **Recitation.** For long tasks and subagent runs, maintain a plan or todo file (or the task's checklist) that the agent rewrites and that is re-surfaced near the tail of the context.
5. **Subagent delegation contract.**
   - Fresh context per subagent and an explicit output schema defined by the delegator.
   - No sibling-to-sibling messaging, with synthesis by the parent.
   - Use parallel fan-out only for many independent, similar items, roughly 10 or more.
6. **Memory consolidation automation (Lindy pattern).**
   - Scheduled per-agent and per-workspace memory jobs that rewrite a file-based memory with an always-loaded index and master file.
   - A deterministic verifier for size limits and orphaned files.
   - Workspace scoping enforced via `workspace_id`.
7. **Proactivity primitives.**
   - Schedule-based automations (already present) gain a "fresh vs. continue context" option and run history.
   - Add monitor-style triggers.
   - Inbound-message triggers (Telegram, email).
   - "Suggestion" automations whose output is a proposed task needing approval rather than an executed mutation.
   - Add per-workspace caps on active automations and spend.
8. **Approval UX.**
   - Approvals should state the action, arguments, target, rationale, and the provenance of untrusted content involved, and be actionable from both web and Telegram.
   - Always require approval for exposure-widening and outbound-communication tools.
   - Show a live work log with pause and take-over.
9. **Prompt-injection defense in depth.**
   - Spotlight and delimit untrusted tool output.
   - Track "tainted" runs (those that ingested external content) and tighten approval policy for write and exfiltration-capable tools after taint.
   - Add an optional alignment-critic model call that sees only the goal and tool-call metadata for high-risk tools.
   - Check model-generated outbound URLs deterministically against allowlists.
   - Block or proxy remote images in agent-rendered markdown.
   - Maintain an injection regression fixture suite based on the Brave, Trail of Bits, and LayerX attack classes.
10. **Agent identity and least privilege.**
    - Attribute every external mutation to the agent or subagent run identity.
    - Default agent permissions to its own VFS area and explicitly granted integrations.
    - Make grants revocable.
    - Add per-agent and per-automation budget caps that pause and request approval rather than silently continuing.

### Gaps
- None of the vendors publishes quantitative evidence for their prompt-injection defenses (for example, attack success rates before and after). Defense effectiveness is mostly asserted.
- I found no authoritative public description of how Google's Antigravity harness handles memory, compaction, or KV-cache. Manus's posts remain the only detailed public account from a commercial general-agent vendor.
