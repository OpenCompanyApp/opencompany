# Open-source clones and approximations of xAI Grok experiences (reply bot, companions, Tasks/agent mode)

Research date: 2026-11-02. Star counts, `pushed_at`, license and archive state come from the GitHub REST API (`gh api repos/<owner>/<repo>`) queried on this date. Each repo URL is the source for its own stats. "Last push" means the latest push to any branch, so it shows activity but not release cadence. A project is flagged **stale** if its last push was more than 12 months ago and **abandoned** if the repo is archived or deleted.

Terminology warning for the report writer: "Grok Bot" now means two different things.
1. **The @grok reply account on X.** Users tag it in a thread and it replies publicly.
2. **"Grok Bot", a separate xAI/SpaceXAI agent product launched around August 2026.** Users chat with named agents that each run on their own cloud VM, remember past work, and can be grouped into multi-bot chats.

Many 2026 GitHub repos call themselves an "open-source Grok Bot alternative". Nearly all of them clone **(2), the agent product, not the X reply bot**. This distinction matters for OpenCompany, because product (2) overlaps heavily with OpenCompany's "durable AI teammates" positioning.

## Q1: Which OSS X/Twitter mention-reply bots exist, and how do they handle thread context, polling vs streaming, rate limits, X API costs and anti-spam?

### Takeaway
No well-known OSS project literally clones the @grok reply account. The production-grade mention-reply implementations are now channel plugins inside general agent runtimes. **OpenClaw's bundled X channel** is the most complete reference design found: stream or poll with backfill, 50-post thread context, allowlist-first authorization, guest caps and dollar budgets. **ElizaOS's `@elizaos/plugin-x`** is the other main one. The 2024–25 "AI agent / memecoin" Twitter stacks are mostly stale: ZerePy, the scraper-based agent-twitter-client and Virtuals GAME. X's 2026 pay-per-use pricing (about $0.005 per post read and $0.015 per reply) and its rule that most tiers can only reply when the bot is @mentioned or quoted are now the main design constraints.

### Cited Findings
**What @grok on X officially is**
- xAI publishes the system prompts for "the Grok chat assistant and various product features across X and grok.com" in `xai-org/grok-prompts`. This includes `ask_grok_system_prompt.j2`, described as the prompt for the Grok bot on X, and `grok_analyze_button.j2` for "Grok Explain". Stats: 4,560 stars, AGPL-3.0, last push 2025-11-17. Official. — [xai-org/grok-prompts](https://github.com/xai-org/grok-prompts)
- xAI's server-side `x_search` tool provides keyword and semantic search, user lookup and **thread fetching** on X. Its parameters include `allowed_x_handles`, `excluded_x_handles`, `from_date` and `to_date`. xAI's servers run the agent loop autonomously, and server-side tools cannot be mixed with client-side tools in the Responses API. This is the closest official building block for a @grok-style reply bot. — [xAI docs: tools overview](https://docs.x.ai/docs/guides/tools/overview); [xAI docs: search tools](https://docs.x.ai/docs/guides/tools/search-tools)

**OpenClaw X channel (`@openclaw/x`, bundled with OpenClaw)**
- OpenClaw: 393,671 stars, MIT, TypeScript, last push 2026-11-02. — [openclaw/openclaw](https://github.com/openclaw/openclaw)
- The X plugin covers "allowlisted X mentions and public replies" and ships as `@openclaw/x`. — [OpenClaw X plugin reference](https://docs.openclaw.ai/plugins/reference/x)
- **Intake.** There are three modes. `auto` uses X's Activity API stream (`post.mention.create`) when an app-only bearer token works and polls otherwise. `stream` uses the stream and falls back to polling. `poll` polls the mentions endpoint at 60 s by default (minimum 15 s), 10 mentions per request, and follows pagination. In stream mode a backfill poll runs every `pollSeconds × 4` (default 240 s). Inbound posts are queued before the cursor advances and are deduplicated. — [OpenClaw docs: X channel](https://docs.openclaw.ai/channels/x)
- **Thread context.** The agent receives the triggering mention plus reply ancestors, recent conversation posts (recent search covers 7 days) and quoted posts, oldest first. `threadContext.maxPosts` defaults to 50 (range 2–100) and keeps the root and the newest posts. The agent is told when the context was truncated. — [OpenClaw docs: X channel](https://docs.openclaw.ai/channels/x)
- **Anti-spam and authorization.**
  - Only allowlisted numeric author IDs trigger replies, and unknown authors are silently dropped.
  - Handles, display names, post text and model output never grant access.
  - Thread content is treated as quoted data.
  - Optional guest mode is capped at 5 mentions per author per UTC day and uses restricted tools.
  - DMs are disabled.
  - Work-session replies require manual approval.
  - Replies only post when the model output starts with a success tag; failures can be routed to another channel.
  - Long replies become a self-reply chain of up to 280 weighted characters per post.
  — [OpenClaw docs: X channel](https://docs.openclaw.ai/channels/x)
- **Cost controls.**
  - Default budgets are $100 per UTC day and $1,000 per billing cycle.
  - Budget is reserved before each paid call and settled afterwards.
  - Streaming drops to gated polling when less than $0.50 remains.
  - When the budget is exhausted, ingress pauses without advancing the cursor.
  - Estimated prices: $0.005 per post read, $0.01 per user read, $0.015 per reply, $0.20 per reply containing a URL.
  - Auth is OAuth2 with the scopes `tweet.read`, `tweet.write`, `users.read`, `offline.access` and `media.write`.
  — [OpenClaw docs: X channel](https://docs.openclaw.ai/channels/x)

**ElizaOS (`@elizaos/plugin-x`, formerly `@elizaos/plugin-twitter`)**
- elizaOS/eliza: 19,756 stars, MIT, TypeScript, last push 2026-11-02. It now describes itself as an "Open source agentic operating system". — [elizaOS/eliza](https://github.com/elizaOS/eliza)
- The X connector now lives in the monorepo at `plugins/plugin-x` (last commit 2026-10-29). It covers "posting, mentions, replies, DMs, timeline actions, and autonomous content discovery". `TWITTER_AUTH_MODE` can be `env` (API key and access tokens), `oauth`, or `broker` (a "managed account transport"). Autonomous posting is opt-in and DMs default to pairing. — [elizaOS/eliza plugins/plugin-x README](https://github.com/elizaOS/eliza/tree/develop/plugins/plugin-x)
- The older `@elizaos/plugin-twitter` npm package (latest 1.2.22, modified 2026-06-16) points to `elizaos-plugins/plugin-twitter`, which now returns 404. The Eliza docs say the integration moved to `@elizaos/plugin-x`. — [npm @elizaos/plugin-twitter](https://npmjs.com/package/%40elizaos/plugin-twitter); [Eliza docs (connectors/twitter)](https://elizaos.github.io/knowledge/docs/connectors/twitter.md)
- **Polling.** `TWITTER_POLL_INTERVAL` is the time between timeline checks (default 120 s), and `TWITTER_MAX_TWEETS_PER_POLL` defaults to 10. One example config uses 60 s. — [ElizaOS docs: Twitter examples](https://docs.elizaos.ai/plugin-registry/platform/twitter/examples)
- **Abandoned.** The scraper-based `agent-twitter-client`, originally `ai16z/agent-twitter-client`, now returns 404 on GitHub. Its npm package was last modified 2025-06-15 (v0.0.18). Only forks remain, such as `romeoscript/agent-twitter-client` (89 stars). — [npm agent-twitter-client](https://www.npmjs.com/package/agent-twitter-client); [romeoscript/agent-twitter-client](https://github.com/romeoscript/agent-twitter-client)

**Other X-bot frameworks and repos**
- Virtuals GAME SDK: `game-python` has 100 stars (MIT, last push 2025-10-01, **stale**) and `game-node` has 93 stars (MIT, last push 2026-03-06). — [game-by-virtuals/game-python](https://github.com/game-by-virtuals/game-python); [game-by-virtuals/game-node](https://github.com/game-by-virtuals/game-node)
- ZerePy (Python agent framework with Twitter connections): 576 stars, MIT, last push 2025-03-10. **Stale/abandoned.** — [blorm-network/ZerePy](https://github.com/blorm-network/ZerePy)
- Rig (Rust LLM framework, sometimes used for X agents): 9,040 stars, MIT, last push 2026-11-02. It is a library, not an X bot. — [0xPlaygrounds/rig](https://github.com/0xPlaygrounds/rig)
- `gkamradt/twitter-reply-bot` ("Bot to auto reply to mentions using LLMs"): 67 stars, no license, last push 2023-05-31. **Abandoned**, but a useful minimal tutorial-style reference. — [gkamradt/twitter-reply-bot](https://github.com/gkamradt/twitter-reply-bot)
- `groupthinking/MyXstack`: 0 stars, last push 2026-10-27. Its Tweepy listener polls mentions and posts a placeholder reply, with Grok/xMCP wiring planned. Toy-level. — [groupthinking/MyXstack](https://github.com/groupthinking/MyXstack)
- `getbotcoin/botcoin`: an autonomous agent that replies to X mentions as a memecoin persona. 1 star. — [GitHub search results](https://github.com/getbotcoin/botcoin)
- X's official MCP server for the X API: 865 stars, no license file, last push 2026-04-09. — [xdevplatform/xmcp](https://github.com/xdevplatform/xmcp)

**Costs and platform rules**
- A vendor article states the following prices, with a conflict of interest because TwitterAPI.io sells X data:
  - Official X API pay-per-use: $5 per 1,000 posts read.
  - xAI X Search tool: $5 per 1,000 posts plus tokens.
  - The third-party scraper API twitterapi.io: $0.15 per 1,000.
  - X caps pay-per-use at 3M post reads per month.
  - Acting through a logged-in (non-API) account risks rate limits, locks or suspension.
  — [TwitterAPI.io blog: Grok Bot alternatives](https://twitterapi.io/blog/grok-bot-alternatives)
- A vendor blog dated September 2026 claims that Free, Basic, Pro and pay-per-use accounts can only reply when the original author @mentions or quotes the account. This is unverified against X's own developer terms. — [OpenTweet blog](https://opentweet.io/blog/does-grok-post-to-x)
- TwitterAPI.io says Hermes Agent reads X via an `xurl` skill on the official API or via xAI `x_search`. It also says OpenClaw has an X channel plugin on the official API that handles mention replies. — [TwitterAPI.io blog](https://twitterapi.io/blog/grok-bot-alternatives)

### Inferences
- OpenClaw's X channel is the best design template for an OpenCompany X adapter: numeric-ID allowlists, a durable cursor with queue-before-advance, thread-as-quoted-data, dollar budgets reserved before each call, and approval-gated publishing. OpenCompany already has the matching primitives (Telegram webhook pipeline, approvals, queue jobs, workspace scoping), so an X adapter could mirror the Telegram channel structure.
- Scraper-based clients such as agent-twitter-client have been effectively retired. A durable integration should use the official API v2 (OAuth2 user context plus an optional app-only bearer token for streaming) and budget per-post costs per workspace.
- Instead of fetching thread context through the X API, an adapter could delegate it to xAI's `x_search`. That trades X API read costs for xAI tool costs, but it couples the adapter to one provider, which conflicts with OpenCompany's provider-agnostic catalog rule.

### Gaps
- I could not read the exact behavior in xAI's `ask_grok_system_prompt.j2`, because the file contents were not visible in the fetch.
- I found no source on how ElizaOS `plugin-x` assembles thread context, handles rate-limit backoff, or applies anti-spam. Source paths exist (`src/client/search.ts`, `src/services/x.service.ts`) but were not read.
- I did not verify X's official 2026 developer pricing and reply restrictions on developer.x.com. The figures above come from vendors.

## Q2: Which OSS projects clone Grok companions (Ani, Rudi, Valentine), and what are their persona, memory and affection mechanics?

### Takeaway
**AIRI (moeru-ai/airi, about 51k stars, MIT)** is the flagship OSS project that explicitly brands itself a "self hosted, you-owned Grok Companion". It is closer to a Neuro-sama recreation (VRM/Live2D, voice, games, Discord/Telegram) and its long-term memory is still in progress. **Utsuwa (AGPL-3.0, small but active)** is the only project found that explicitly replicates a Grok-style multi-dimensional affection and relationship system. Open-LLM-VTuber, Amica, SillyTavern and Bella cover avatar, voice or persona pieces. None of them is a durable workspace agent.

### Cited Findings
**The Grok features being cloned (mostly third-party sources)**
- Companions soft-launched on iOS around 14 July 2025, starting with Ani and Rudi. Valentine followed around July 18, and Mika was added later. Sources conflict on exact dates. — [Grok Companion Mode 2026 update (blog)](https://ai-companion-blog-6p6.pages.dev/blog/grok-companion-mode-2026-update/); [3dnews](https://3dnews.ru/1127138)
- The reported affection system has 5 levels, with each message scored from -10 to +15, and higher levels unlock more intimate content. This comes from a review blog, is unverified against xAI, and thresholds conflict between sources. — [Grok Ani review (blog)](https://ai-companion-blog-6p6.pages.dev/blog/grok-ani-review/); [Shapes fan wiki](https://shapes.inc/fandom/ani-grok/deep-dive)

**OSS projects**
- **AIRI.** 51,005 stars, MIT, TypeScript/Vue, last push 2026-11-02.
  - The tagline calls it a "Self hosted, you-owned Grok Companion", and the README says it is "Re-creating Neuro-sama".
  - Avatar and voice: VRM and Live2D with auto blink and look-at; browser and Discord audio input with client-side ASR; multi-provider TTS.
  - Platforms and games: Telegram and Discord chat; Minecraft supported, Factorio work-in-progress.
  - Providers via xsAI, including xAI.
  - Memory: in-browser DuckDB WASM or pglite, with the "Memory Alaya" memory system still in progress.
  - MCP is optional (MCP Launcher sub-project).
  — [moeru-ai/airi](https://github.com/moeru-ai/airi)
- **Utsuwa.** 140 stars, AGPL-3.0-or-later (releases up to 0.12.0 were MIT), last push 2026-11-02. It calls itself an "open-source alternative to Grok Companion".
  - Affection and relationship:
    - Tracks "affection, trust, intimacy, comfort, and respect separately".
    - "8 Relationship Stages" from Stranger to Soulmate.
    - Mood causality tracking.
    - A "Hybrid system combining app heuristics + LLM suggestions".
    - Time-based mood and relationship decay/recovery.
    - "Visual Novel Events" for milestones.
  - Memory and persona: semantic memory via Transformers.js, a memory graph, and `{{char}}`/`{{user}}` macros.
  - Avatar and voice: VRM via Three.js/Threlte; Live2D planned; STT via Whisper/Groq/Web Speech; TTS via ElevenLabs/OpenAI/Fish/Kokoro.
  - Stack: SvelteKit plus Tauri, with IndexedDB via Dexie.
  - 8 LLM providers, including xAI.
  — [JuiceBoxxGames/utsuwa](https://github.com/JuiceBoxxGames/utsuwa)
- **Aikeya.** 24 stars, MIT, Svelte, last push 2026-02-14. It uses the same "open-source alternative to Grok Companion" tagline as Utsuwa and is likely its predecessor or a related project (inferred from the identical wording). — [aikeyaorg/aikeya](https://github.com/aikeyaorg/aikeya)
- **Open-LLM-VTuber.** 14,521 stars. GitHub reports the license as NOASSERTION, while the README refers to "the MIT license of this project" and Live2D samples are under separate terms. Last push 2026-05-15.
  - Hands-free voice with interruption.
  - Live2D expressions with backend emotion mapping.
  - Desktop-pet mode.
  - Proactive speaking.
  - An `mcp_servers.json` file.
  - Long-term memory "currently removed… coming back soon".
  — [Open-LLM-VTuber/Open-LLM-VTuber](https://github.com/Open-LLM-VTuber/Open-LLM-VTuber)
- **Amica.** 1,617 stars, MIT (models under their authors' licenses), last push 2026-09-22.
  - VRM characters and an "emotion engine".
  - Whisper plus Silero VAD for speech input; many TTS options.
  - Bakllava for vision.
  - Next.js plus Tauri.
  - Backends: llama.cpp, Ollama, KoboldCpp, OpenRouter and others.
  - The README does not mention memory or affection.
  — [semperai/amica](https://github.com/semperai/amica)
- **SillyTavern.** "LLM Frontend for Power Users". 35,214 stars, AGPL-3.0, last push 2026-10-02. It is the de facto persona and roleplay front end; the feature details were not fetched in this pass. — [SillyTavern/SillyTavern](https://github.com/SillyTavern/SillyTavern)
- **Bella** ("Bella is best"): 6,342 stars, no license, last push 2026-02-05. — [Jackywine/Bella](https://github.com/Jackywine/Bella)
- **Handcrafted Persona Engine.** Live2D plus LLM, ASR, TTS and RVC for VTubing. 1,394 stars, no license, C#, last push 2026-05-20. The repo has moved from `fagenorn` to `elevenyellow`. — [elevenyellow/handcrafted-persona-engine](https://github.com/elevenyellow/handcrafted-persona-engine)
- **Lobe Vidol** (virtual idols): 995 stars, Apache-2.0, last push 2026-10-15. — [lobehub/lobe-vidol](https://github.com/lobehub/lobe-vidol)
- **Neuro** ("A recreation of Neuro-sama"): 2,108 stars, MIT, last push 2025-01-17. **Stale.** — [kimjammer/Neuro](https://github.com/kimjammer/Neuro)
- **Small "Ani"-named VRM companions** (all with fewer than 30 stars):
  - `catsmice/AniCompanion` ("gives your AI agent a face"): 30 stars. — [catsmice/AniCompanion](https://github.com/catsmice/AniCompanion)
  - `fguilleme/ani-companion` (VRM PWA): 0 stars. — [fguilleme/ani-companion](https://github.com/fguilleme/ani-companion)
  - `NathanHufft/anima` (VRM desktop companion with mood): 0 stars. — [NathanHufft/anima](https://github.com/NathanHufft/anima)
- **Animus** is a Python engine for persistent companion personalities with pluggable LLMs, including xAI. Its listed repo URL `shefys/grok-animus` resolved via the GitHub API to an unrelated repo (`asda23acs/Emblem-Vault`), so treat it as unverified or possibly hijacked. — [p-peg search result](https://github.com/shefys/p-peg)

**Memory layers often paired with companions**
- mem0: 68,261 stars, Apache-2.0, last push 2026-10-30. — [mem0ai/mem0](https://github.com/mem0ai/mem0)
- Letta: 25,319 stars, Apache-2.0, last push 2026-10-18. — [letta-ai/letta](https://github.com/letta-ai/letta)

### Inferences
- The companion OSS niche is consumer and entertainment-focused: avatars, TTS and affection meters. OpenCompany is not competing here. The transferable ideas are Utsuwa's separate relationship dimensions, decay over time and event causality, which could inform agent-to-human "relationship" or trust state, and AIRI's multi-channel presence (Telegram plus Discord plus voice).
- Integration is low-value for a work-oriented product. At most, an avatar or voice front end such as AIRI or Open-LLM-VTuber could act as a client on an OpenCompany agent via an OpenAI-compatible gateway, which OpenCompany already owns.
- Licensing caveat: SillyTavern and Utsuwa are AGPL like OpenCompany, which makes code reuse compatible. AIRI, Amica and Open-LLM-VTuber are MIT.

### Gaps
- No official xAI documentation on the companion affection system was found. The mechanics above come from blogs and fan wikis.
- SillyTavern's lorebook, vector memory and extension details were not fetched in this pass.
- Amica's memory or "Amica Life" idle behavior is not in its README; its docs site was not checked.

## Q3: Which OSS projects replicate Grok Tasks (scheduled jobs), Grok agent mode / DeepSearch, and the new "Grok Bot" agent product?

### Takeaway
- **Deep research** has mature OSS clones: gpt-researcher, STORM, dzhng/deep-research, Jina node-DeepResearch, smolagents' open_deep_research and the now-archived LangChain open_deep_research.
- **Scheduled prompts** are best covered by Khoj (AGPL, "schedule automations") and by general agent runtimes such as OpenClaw and Hermes Agent.
- **Grok Bot** (around August 2026: named agents on cloud VMs, memory, multi-bot group chats, confirmations) spawned a wave of high-star 2026 clones such as OpenMausBot, Rakazo and Codync. These are OpenCompany's most direct competitors in this whole landscape.

### Cited Findings
**xAI feature definitions**
- **DeepSearch**, announced with Grok 3 on 2025-02-19, is xAI's "first agent… a lightning-fast AI agent built to be useful across the entire corpus of human knowledge". It synthesizes facts, weighs conflicting claims and produces a summary. — [xAI: Grok 3 Beta](https://x.ai/news/grok-3)
- **Grok Tasks** was first reported in development in May 2025. It rolled out to SuperGrok users with Once/Daily/Weekly/Monthly/Yearly schedules. In June 2026 it was reported as merging into "Automations", and coverage dated 2026-07-16 describes scheduled and email-triggered automations. These are third-party sources; I found no official xAI post. — [TestingCatalog](https://testingcatalog.com/grok-set-to-gain-tasks-feature-for-periodical-execution); [LatestLY](https://www.latestly.com/socially/technology/grok-new-feature-update-elon-musks-xai-rolling-out-tasks-for-supergrok-users-allowing-them-to-automate-tasks-workflows-6932858.html); [TestingCatalog: Automations](https://testingcatalog.com/xai-is-working-on-automations-feature-for-grok/); [Blockchain.news](https://blockchain.news/news/grok-automations-scheduled-tasks)
- **Grok Bot**, reported 2026-08-12 as launched by "SpaceXAI":
  - Users give tasks in a messenger-style chat, and bots return results or ask for confirmation.
  - Each agent gets its own cloud VM and keeps running when the user's computer is off.
  - Agents operate app interfaces directly instead of APIs.
  - Users can teach a task by demonstration and save it as a template.
  - Bots have long-term memory.
  - Multiple bots can be grouped into a chat with roles and task hand-offs.
  - Access requires SuperGrok Heavy, Cursor Ultra or Cursor Teams Premium, on desktop and iOS.
  — [Forklog](https://forklog.com/en/news/spacexai-launches-grok-bot-messenger-with-ai-agents); [KuCoin news](https://www.kucoin.com/news/flash/xai-launches-grok-bot-ai-agent-with-its-own-computer-for-cross-app-task-automation)
- Grok Bot "can watch X for you". Its X integration ("Grok Bot now works with X") was announced 2026-08-29, and usage counts toward a weekly allowance. — [TwitterAPI.io blog](https://twitterapi.io/blog/grok-bot-alternatives)

**Grok Bot clones (agent product)**
- **OpenMausBot.** 10,817 stars, Apache-2.0 (the enterprise folder is source-available), created 2026-08-11, last push 2026-11-02. "Open-source Grok Bot alternative with a virtual machine that bots can use". Bots run on the local `claude`, `codex` or `grok` CLIs on a cloud Linux desktop, a local VM or the host. — [milind-soni/OpenMausBot](https://github.com/milind-soni/OpenMausBot); [TwitterAPI.io blog](https://twitterapi.io/blog/grok-bot-alternatives)
- **Rakazo.** 6,613 stars, Apache-2.0, created 2026-08-13, last push 2026-11-02.
  - Persistent bots with their own conversations, memory, routines and history.
  - Team or private sandboxes (Docker, E2B, Daytona and others) with browser, terminal and desktop access.
  - Delegation to peer bots or subagents.
  - Voice mode.
  - Integrations through Composio, Pipedream, MCP and OpenAPI.
  - Stack: React 19, Hono/oRPC, PostgreSQL/Prisma, Graphile Worker, Electron/Expo.
  - The README does not mention channels such as X, Telegram or Slack, or approvals.
  — [elie222/rakazo](https://github.com/elie222/rakazo)
- **Codync.** 1,636 stars, Apache-2.0, Rust, last push 2026-11-02. "Open source Grok Bot alternative, Bring your own agent and subscription: message Claude Code, Codex, Gemini CLI…" — [leepokai/Codync](https://github.com/leepokai/Codync)
- **invisible_dots.** 32,345 stars, MIT, last push 2026-10-31. "Open-source, self-hosted alternative to OpenAI Dots, Grok Bot. Built to be undetectable by anti-bot systems." The repo was created 2024-08-04, before either product existed, so it was likely renamed or repurposed and its stars may predate the current scope. **Flag.** — [feder-cr/invisible_dots](https://github.com/feder-cr/invisible_dots)
- **open-duet.** 6,493 stars, Apache-2.0. Pitched as an alternative to Claude Duet: "AI coworkers (named agents) with instructions, memory, routines, their own isolated com[puters]". The repo was created 2023-05-25, so it is likely repurposed. **Flag.** — [Anil-matcha/open-duet](https://github.com/Anil-matcha/open-duet)
- **nightly-labs/openbot.** 4,577 stars, NOASSERTION license, created 2026-08-12. "A local-first desktop workspace for persistent AI teammates… dedicated workspaces, task queues, file sharing". — [nightly-labs/openbot](https://github.com/nightly-labs/openbot)
- **amplifthq/openmeld.** 1,407 stars, MIT. "The open-source collaboration system for people and AI agents. Share context, coordinate tasks…" — [amplifthq/openmeld](https://github.com/amplifthq/openmeld)
- **Literal name matches** (all small):
  - `rrs301/grok-bot-agent-clone`: 5 stars, no license, last push 2026-09-23. — [rrs301/grok-bot-agent-clone](https://github.com/rrs301/grok-bot-agent-clone)
  - `DatoBHJ/grok-clone`: 15 stars, MIT. — [DatoBHJ/grok-clone](https://github.com/DatoBHJ/grok-clone)
- **General runtimes cited as Grok Bot alternatives:**
  - Hermes Agent: 259,585 stars, MIT, last push 2026-11-02. — [NousResearch/hermes-agent](https://github.com/NousResearch/hermes-agent)
  - OpenClaw: 393,671 stars, MIT. — [openclaw/openclaw](https://github.com/openclaw/openclaw)
  - The vendor article also names both, plus CrewAI and n8n. — [TwitterAPI.io blog](https://twitterapi.io/blog/grok-bot-alternatives)

**Deep research clones (DeepSearch / agent-mode research)**
- gpt-researcher: 30,422 stars, Apache-2.0, last push 2026-10-10. — [assafelovic/gpt-researcher](https://github.com/assafelovic/gpt-researcher)
- STORM (LLM knowledge curation with cited reports): 31,768 stars, MIT, last push 2025-09-30. **Stale.** — [stanford-oval/storm](https://github.com/stanford-oval/storm)
- dzhng/deep-research: 19,868 stars, MIT, last push 2026-04-11. — [dzhng/deep-research](https://github.com/dzhng/deep-research)
- LangChain open_deep_research: 12,680 stars, MIT, **archived**. — [langchain-ai/open_deep_research](https://github.com/langchain-ai/open_deep_research)
- nickscamara/open-deep-research (Firecrawl-based): 6,296 stars, last push 2025-05-07. **Stale.** — [nickscamara/open-deep-research](https://github.com/nickscamara/open-deep-research)
- Jina node-DeepResearch: 5,244 stars, Apache-2.0, last push 2026-05-01. — [jina-ai/node-DeepResearch](https://github.com/jina-ai/node-DeepResearch)
- smolagents, which contains HF's open_deep_research example: 30,182 stars, Apache-2.0, last push 2026-10-29. — [huggingface/smolagents](https://github.com/huggingface/smolagents)

**Scheduled-prompt tools**
- Khoj: 37,936 stars, AGPL-3.0, last push 2026-08-02. "Your AI second brain. Self-hostable… Build custom agents, schedule auto[mations]". — [khoj-ai/khoj](https://github.com/khoj-ai/khoj)

### Inferences
- The "Grok Bot alternative" cohort (OpenMausBot, Rakazo, openbot, openmeld, open-duet) is the competitor set closest to OpenCompany: named, durable agents with memory, routines, delegation and their own computers.
  - What these projects emphasize that OpenCompany does not foreground: per-agent sandbox VMs and desktops, and teach-by-demonstration.
  - What OpenCompany emphasizes that they mostly lack:
    - A multi-workspace, human-plus-agent shared surface (chat, tasks, docs).
    - Approvals.
    - Telegram channel delivery.
    - Laravel self-hosting.
- Grok Tasks maps directly onto OpenCompany's scheduled automations. xAI's move toward event triggers ("email matching filters") suggests event-triggered automations, such as an inbound X mention or email, as a competitive gap to check.
- Deep research is a commodity. OpenCompany should integrate it (as a tool, MCP server or subagent pattern) rather than compete with it. gpt-researcher (Apache-2.0) and Jina node-DeepResearch (Apache-2.0) are license-compatible candidates as MCP or tool backends.

### Gaps
- I found no official xAI announcement page for Grok Tasks, Automations or Grok Bot. All details come from secondary press.
- Star counts in the hundreds of thousands (OpenClaw, Hermes Agent) and repurposed-repo counts (invisible_dots, open-duet) were reported as-is by the GitHub API. I did not audit them for star inflation.
- Feature-level READMEs for OpenMausBot, Codync, openbot and openmeld were not fetched. Their descriptions above are repo taglines only.

## Q4: Are there repos literally named grok-clone / open-grok / grok-ui, and what has xAI itself open-sourced (official vs third-party)?

### Takeaway
xAI's official open-source output is weights (Grok-1 Apache-2.0, Grok-2 on Hugging Face), prompts (grok-prompts, AGPL-3.0), SDKs (Python and TS), the X "For You" algorithm, and a coding-agent CLI (grok-build, about 27.7k stars). Literal "grok-clone" repos are tiny UI toys. `superagent-ai/grok-cli` is third-party, not official.

### Cited Findings
**Official (xai-org)**
- Grok-1 open release: 52,224 stars, Apache-2.0, last push 2024-08-30. **Stale**, as expected for a weights release. — [xai-org/grok-1](https://github.com/xai-org/grok-1); [HF xai-org/grok-1](https://huggingface.co/xai-org/grok-1)
- Grok-2 weights are on Hugging Face (`xai-org/grok-2`, created 2025-08-22, 1,207 likes). There is no GitHub repo. — [HF xai-org/grok-2](https://huggingface.co/xai-org/grok-2)
- grok-prompts: 4,560 stars, AGPL-3.0, last push 2025-11-17. Covers system prompts for Grok chat and X features, including the X bot. — [xai-org/grok-prompts](https://github.com/xai-org/grok-prompts)
- x-algorithm ("Algorithm powering the For You feed on X"): 34,014 stars, Apache-2.0, last push 2026-10-31. — [xai-org/x-algorithm](https://github.com/xai-org/x-algorithm)
- grok-build ("SpaceXAI's coding agent harness and TUI"): 27,653 stars, Apache-2.0, created 2026-07-14, last push 2026-09-29. — [xai-org/grok-build](https://github.com/xai-org/grok-build)
- grok-build-plugin-cc (a Claude Code plugin that delegates reviews and session transfer to the Grok Build CLI): 271 stars, Apache-2.0. — [xai-org/grok-build-plugin-cc](https://github.com/xai-org/grok-build-plugin-cc)
- SDKs:
  - xai-sdk-python ("official Python SDK for the SpaceXAI API"): 595 stars, Apache-2.0, last push 2026-10-30. — [xai-org/xai-sdk-python](https://github.com/xai-org/xai-sdk-python)
  - xai-sdk-ts: 68 stars. — [xai-org/xai-sdk-ts](https://github.com/xai-org/xai-sdk-ts)
  - xai-proto: 154 stars. — [xai-org/xai-proto](https://github.com/xai-org/xai-proto)
  - xai-cookbook: 637 stars. — [xai-org/xai-cookbook](https://github.com/xai-org/xai-cookbook)
  - plugin-marketplace: 317 stars. — [xai-org/plugin-marketplace](https://github.com/xai-org/plugin-marketplace)

**Third-party**
- `superagent-ai/grok-cli` ("An open-source coding agent for the Grok API"): 3,504 stars, MIT, last push 2026-07-06. Third-party, likely eclipsed by the official grok-build (inference). — [superagent-ai/grok-cli](https://github.com/superagent-ai/grok-cli)
- Literal "grok clone" GitHub search results are tiny:
  - `DatoBHJ/grok-clone`: 15 stars. — [DatoBHJ/grok-clone](https://github.com/DatoBHJ/grok-clone)
  - `rrs301/grok-bot-agent-clone`: 5 stars. — [rrs301/grok-bot-agent-clone](https://github.com/rrs301/grok-bot-agent-clone)
  - `nat-build-with-oracle/idea-9sep-wed2026-grok-clone` (a SwiftUI "AI-teammate workspace"): 18 stars. — [nat-build-with-oracle/idea-9sep-wed2026-grok-clone](https://github.com/nat-build-with-oracle/idea-9sep-wed2026-grok-clone)
- Corporate naming: recent official repo descriptions use "SpaceXAI" (xai-sdk-python, grok-build), and press also uses "SpaceXAI". — [xai-org/grok-build](https://github.com/xai-org/grok-build); [Forklog](https://forklog.com/en/news/spacexai-launches-grok-bot-messenger-with-ai-agents)

### Inferences
- For OpenCompany, the relevant official assets are xai-sdk and the xAI API's `x_search`/`web_search` server-side tools as a provider capability, plus grok-prompts as a design reference for a public reply persona. Grok weights are irrelevant to a hosted-provider runtime.
- grok-prompts is AGPL-3.0. Copying prompt text into OpenCompany (also AGPL-3.0-only) is license-compatible, but attribution and notices would still be needed.

### Gaps
- The exact Grok-2 license name (reportedly the "Grok 2 Community License") was not confirmed, because the HF API returned no license tag.
- The grok-build README was not fetched, so its feature set and whether it supports scheduled tasks are unknown.

## Q5: What could OpenCompany learn from, compete with, or integrate (e.g. an X channel adapter alongside Telegram)?

### Takeaway
- **Integrate:** an X channel adapter modelled on OpenClaw's X channel, plus deep-research and memory backends as tools.
- **Learn from:** OpenClaw's budget and allowlist design, Grok Automations' event triggers, Utsuwa's multi-dimensional relationship state, and Grok Bot clones' per-agent sandboxes.
- **Compete with:** the 2026 "Grok Bot alternative" cohort (OpenMausBot, Rakazo, openbot, openmeld) and general runtimes (OpenClaw, Hermes, ElizaOS).
- **Ignore:** companions, except as optional front ends.

### Cited Findings
- OpenClaw's X channel provides:
  - Stream-or-poll with backfill and a cursor that only advances after enqueue.
  - 50-post thread context passed as quoted data.
  - Numeric-ID allowlists and guest daily caps.
  - Success-tag gating of replies.
  - Approval for work-session publishing.
  - Per-day and per-cycle dollar budgets with reservation.
  — [OpenClaw docs: X channel](https://docs.openclaw.ai/channels/x)
- ElizaOS `plugin-x` supports env, OAuth and broker auth modes, opt-in autonomous posting, and DM pairing. — [elizaOS/eliza plugin-x](https://github.com/elizaOS/eliza/tree/develop/plugins/plugin-x)
- Grok Bot pairs chat-assigned tasks with confirmation requests, cloud VMs, memory and multi-bot group chats. — [Forklog](https://forklog.com/en/news/spacexai-launches-grok-bot-messenger-with-ai-agents)
- Rakazo offers persistent bots with routines, team or private sandboxes, delegation to peers or subagents, and MCP/OpenAPI tools. — [elie222/rakazo](https://github.com/elie222/rakazo)
- Grok Automations add event triggers (email filters) alongside schedules. — [Blockchain.news](https://blockchain.news/news/grok-automations-scheduled-tasks)
- Utsuwa tracks five relationship dimensions with decay and recovery and hybrid heuristic-plus-LLM progression. — [JuiceBoxxGames/utsuwa](https://github.com/JuiceBoxxGames/utsuwa)
- xAI `x_search` offers X search and thread fetching as a server-side tool. — [xAI docs](https://docs.x.ai/docs/guides/tools/overview)
- xdevplatform/xmcp is an MCP server for the X API. — [xdevplatform/xmcp](https://github.com/xdevplatform/xmcp)

### Inferences
- **X channel adapter (high value, moderate effort).** OpenCompany's existing Telegram pipeline (`app/Domain/Chat/Telegram/...`) is the natural template. Recommended invariants, borrowed from OpenClaw:
  - A per-workspace X account binding.
  - Allowlist by numeric author ID, never by handle.
  - Durable queue-before-cursor intake.
  - Thread context injected as untrusted quoted data, to defend against prompt injection from public posts.
  - Every public reply routed through OpenCompany's approvals system by default, with per-agent opt-out.
  - Per-workspace dollar budgets, because X charges per read and per reply.
  - Polling first, with Activity API streaming as an optional upgrade.

  A quicker path is mounting `xdevplatform/xmcp` as an MCP tool server, which gives agents read and post tools but no mention-triggered inbound channel.
- **Event-triggered automations.** Grok's Tasks-to-Automations evolution, Rakazo's "routines" and OpenClaw's channels all point to automations triggered by channel events (mention, DM, email) and not only by cron. This is worth checking against OpenCompany's `RunDueAutomations`.
- **Positioning.** The Grok Bot cohort sells "named AI teammates with their own computer". OpenCompany's differentiators are the shared human-plus-agent workspace (tasks, docs, approvals, multi-workspace scoping) and self-hosted AGPL. Its gap is per-agent sandboxed compute and desktops. Sandbox providers (E2B, Daytona, Docker) used by Rakazo show what integration options exist.
- **Do not chase companions.** At most, expose OpenCompany agents through the OpenAI-compatible gateway so that AIRI or Open-LLM-VTuber could act as a voice or avatar front end.

### Gaps
- I did not verify whether ElizaOS, Rakazo or OpenMausBot have a Telegram or Slack channel parity comparable to OpenCompany's; only OpenClaw's X channel was documented in depth.
- I found no public data on the real-world spam or abuse incidents of OSS X reply bots, or on X enforcement against them.
