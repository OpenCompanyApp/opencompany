# Embedded Chromium Browser Investigation

Date: 2026-05-24

Status: Investigation / implementation proposal. The external browser-provider
notes were checked on 2026-05-24 against the linked vendor docs; the
OpenCompany-specific classes, models, tools, and phases below are proposed until
matching app code exists.

## Summary

OpenCompany can support a browser that users can see and control through the web UI, but it should not be implemented as a direct iframe of arbitrary websites. Modern sites can prevent embedding with `X-Frame-Options` and `Content-Security-Policy: frame-ancestors`, and even when embedding is allowed, same-origin rules prevent OpenCompany from reliably inspecting or controlling the page.

The chosen direction is a Playwright-controlled browser session. In development
that should start as a local browser process. The production shape should be
adapter-based so OpenCompany can later choose between managed browser providers,
self-hosted Chromium, or a user-owned browser mode without changing agent tools.

The practical architecture is:

1. OpenCompany asks a `BrowserSessionProvider` adapter to create an isolated browser session per workspace/user/agent task.
2. The adapter returns a Playwright-compatible control handle, with CDP treated as a provider detail where needed.
3. The same session exposes a live view through a local headed browser in development, and later through WebRTC, VNC/noVNC, or a provider-hosted iframe so the human can watch, click, type, upload files, or take over.
4. OpenCompany stores only session metadata and encrypted connection secrets, then routes all agent actions through workspace permissions, approvals, allowlists, and audit logs.

This is feasible, but it is closer to building a browser-session runtime than adding a normal integration card.

## What Is And Is Not Possible

### Direct iframe embedding

Not reliable for arbitrary web browsing.

Reasons:

- `X-Frame-Options` lets a site block rendering in frames, iframes, embeds, and objects.
- CSP `frame-ancestors` lets a site define which parents may embed it.
- Cross-origin browser security prevents OpenCompany JavaScript from reading or controlling most iframe content.
- Many auth, payments, and SaaS sites intentionally block iframe embedding.

Direct iframe embedding is fine for a known cooperative site, but not for an "agent browser".

### Local browser inside the user's browser

Not possible in the literal sense. A normal web app cannot embed a native Chromium engine inside Chrome/Safari/Firefox and control arbitrary pages. The browser sandbox does not expose that capability.

There are two partial alternatives:

- A browser extension can control the user's real browser tabs with explicit installation and permissions.
- A desktop app/Electron shell can embed Chromium, but that is not the OpenCompany web app.

Both can be useful later, but they are not the clean SaaS/web path.

### Playwright-controlled browser session

Feasible and industry-standard for this kind of product.

The user sees the active browser session in the OpenCompany UI or, during early development, in a local headed browser window. The agent controls the same browser through Playwright actions. Human input can either go through the local browser, the provider's live view, a VNC/WebRTC stream, or an OpenCompany WebSocket channel that sends mouse/keyboard events to the browser service.

## Architecture Options

### Option A: Local Playwright browser for development

Use a local Playwright-launched Chromium browser for the first implementation.
This is the chosen development path.

Pros:

- Fastest and cheapest path to validate the OpenCompany runtime contract.
- Avoids provider accounts, cloud costs, live-view vendor quirks, and early infrastructure work.
- Easy to debug with a visible headed browser.
- Lets the team build the important app-owned pieces first: session model, runtime service, tool provider, permissions, approvals, and action logs.

Cons:

- Not the final production deployment shape.
- Local headed browser control does not solve multi-user web live view by itself.
- Browser sessions depend on the developer machine process lifecycle.

Good fit for OpenCompany: yes, as phase one.

### Option B: Managed browser provider

Use Browserbase, Cloudflare Browser Run, Scrapybara, Browserless, Steel, or Hyperbrowser for the first serious prototype.

Pros:

- Fastest route to working live sessions.
- Built-in session lifecycle APIs.
- Usually supports Playwright or CDP.
- Some providers already expose live view, human takeover, auth-state persistence, recordings, proxies, file upload support, and debugging UI.
- Keeps browser process isolation and scaling outside the Laravel app.

Cons:

- Provider cost and lock-in.
- Provider data handling needs review for workspace/customer data.
- Live-view URLs and CDP URLs are powerful secrets.
- Harder to guarantee self-hosting parity unless the adapter boundary is designed early.

Good fit for OpenCompany: yes, but after the local Playwright development path proves the app-owned contract.

### Option C: Self-hosted Chromium containers

Run one browser container per session or per small session pool. Control it with Playwright and expose a web view with noVNC, KasmVNC, WebRTC, or a browser-streaming gateway. This could eventually be a Docker Compose service for local/dev or a production browser worker service, but it is not the immediate implementation path.

Pros:

- Full control over data, networking, proxying, retention, and deployment.
- Better for self-hosted OpenCompany.
- Can run in per-workspace network isolation.
- Avoids a vendor dependency for core runtime behavior.

Cons:

- More infrastructure work: process lifecycle, scaling, sandboxing, file cleanup, recordings, auth state, idle timeouts, crashes, rate limits, proxying, and resource quotas.
- Browser containers are heavy compared with normal API tools.
- noVNC works but can feel less polished than provider live views unless tuned.

Good fit for OpenCompany: yes, as a future self-hosted adapter.

### Option D: Browser MCP server

Expose browser actions through an MCP server and register it through OpenCompany's existing MCP client.

Pros:

- Matches the current tool ecosystem.
- Lets agents call `navigate`, `screenshot`, `click`, `type`, `extract`, and similar tools.
- Could work with external browser MCP servers.

Cons:

- A browser session is not just a stateless tool call; it needs lifecycle, UI streaming, locking, auditing, and workspace billing/resource ownership.
- Existing OpenCompany MCP support is HTTP JSON-RPC oriented. Some public browser MCP tools are command/stdio or local-process oriented, which would require a trusted sidecar.
- The live human-controlled view still needs a first-class UI/session model.

Good fit for OpenCompany: useful as an adapter layer, not as the whole product architecture.

### Option E: User-owned browser extension

Ship a browser extension that lets OpenCompany control a user's actual logged-in browser tabs.

Pros:

- Best compatibility with user-authenticated sessions.
- Avoids collecting credentials or storing third-party cookies in OpenCompany infrastructure.
- Useful for sites with bot detection or user-specific browser state.

Cons:

- Requires extension install, permissions UX, browser-store review, and a separate security model.
- Harder to support server-side automation when the user's machine is offline.
- Not ideal for autonomous background tasks.

Good fit for OpenCompany: later, for "use my browser" mode. Not the first server-side embedded browser.

## Current Provider Notes

- Chrome DevTools Protocol is the low-level protocol for inspecting and controlling Chromium/Chrome. It is the common denominator for many browser automation products, but OpenCompany should expose Playwright-level actions internally and keep CDP as a provider/runtime detail.
- Playwright supports persistent browser contexts and remote server connections. This matters for saved auth states, local development, managed providers, and future remote browser containers.
- Browserbase exposes live views that can be watched and controlled in real time, including embedding inside an application.
- Cloudflare Browser Run exposes DevTools/browser-session APIs and added Live View, Human in the Loop, and Session Recordings in April 2026.
- Scrapybara exposes lightweight Chromium browser instances with interactive streaming, computer actions, Playwright CDP control, and auth-state support.
- Browserless exposes managed browsers over WebSocket for Puppeteer/Playwright.
- KasmVNC/noVNC-style infrastructure is viable for self-hosted remote desktop/browser streaming, but OpenCompany would own more polish and operational risk.

Sources:

- MDN `X-Frame-Options`: https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/X-Frame-Options
- MDN CSP `frame-ancestors`: https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Content-Security-Policy/frame-ancestors
- Chrome DevTools Protocol: https://chromedevtools.github.io/devtools-protocol/
- Playwright `BrowserType`: https://playwright.dev/docs/api/class-browsertype
- Playwright Docker/remote server: https://playwright.dev/docs/docker
- Browserbase session live view: https://docs.browserbase.com/platform/browser/observability/session-live-view
- Cloudflare Browser Rendering API: https://developers.cloudflare.com/api/resources/browser_rendering/
- Cloudflare Browser Run Live View/HITL changelog: https://developers.cloudflare.com/changelog/post/2026-04-15-br-observability/
- Scrapybara browser docs: https://docs.scrapybara.com/browser
- Browserless BaaS docs: https://docs.browserless.io/baas/start
- KasmVNC docs: https://docs.kasmvnc.com/
- OpenAI computer use guide: https://developers.openai.com/api/docs/guides/tools-computer-use

## Recommended OpenCompany Shape

Build this as app-owned runtime infrastructure, with Playwright as the initial control layer and provider adapters behind a narrow interface.

Suggested backend components:

- `BrowserSessionProvider`: creates, resumes, closes, and inspects browser sessions. The first adapter should be local Playwright. Later adapters can target Browserbase, Cloudflare Browser Run, Scrapybara, Browserless, Steel, Hyperbrowser, or self-hosted Chromium.
- `BrowserSession`: workspace-scoped Eloquent model storing provider, provider session id, current URL/title/status, owner user, optional agent, expiry, policy, and encrypted connection metadata.
- `BrowserRuntime`: the only service allowed to send browser actions. It should enforce workspace scope, session ownership, active-control locks, permission policy, and approvals.
- `BrowserToolProvider`: exposes a hybrid tool surface. Keep high-level safe tools such as `browser_create_session`, `browser_navigate`, `browser_screenshot`, `browser_click`, `browser_type`, `browser_extract_text`, `browser_request_human_takeover`, and `browser_close`, plus an advanced `browser_playwright` tool that runs a constrained Playwright JavaScript block against an existing session.
- `BrowserPlaywrightWorker`: Node/Playwright worker that receives `{session_id, input, code, timeout_ms}`, attaches to the selected session/page, wraps the code in an async function with controlled globals, and returns only JSON-serializable output.
- `BrowserActionLog`: append-only action/event log with actor type (`human`, `agent`, `system`), target session, URL, tool/action, params redaction, result status, and screenshots/recording references where allowed.
- Queue jobs for session cleanup, idle timeout, recording finalization, and stuck-session recovery.

## Lua Runtime Shape

Lua should expose browser control through the existing `app.*` bridge, not
through a separate runtime. The default API should be convenient and safe, but
the flexible path should let agents write real Playwright JavaScript because
LLMs have strong training priors for Playwright examples.

High-level Lua calls:

```lua
local session = app.browser.create_session({
    provider = "local",
    viewport = { width = 1280, height = 900 },
})

app.browser.navigate({
    session_id = session.id,
    url = "https://example.com",
})

local text = app.browser.extract_text({
    session_id = session.id,
})
```

Advanced Playwright block:

```lua
local result = app.browser.playwright({
    session_id = session.id,
    input = { url = "https://example.com" },
    timeout_ms = 30000,
    code = [[
        await page.goto(input.url);
        await page.getByRole('link', { name: 'Pricing' }).click();
        await page.waitForLoadState('networkidle');

        return {
            title: await page.title(),
            url: page.url(),
            text: await page.locator('main').innerText(),
        };
    ]],
})
```

Execution flow:

```text
Lua script
  -> app.browser.playwright(...)
  -> LuaBridge
  -> OpenCompanyLuaToolInvoker
  -> IntegrationRuntime / ToolRegistry
  -> BrowserRuntime
  -> BrowserPlaywrightWorker
  -> Playwright page/context
```

The Playwright block should be powerful enough to preserve Playwright's normal
ergonomics, but it should still run as an OpenCompany browser action. It should
not become arbitrary Node execution. The worker should expose `page`, `context`,
`input`, and a controlled logger; it should not expose `require`, `import`,
`process.env`, shell access, filesystem access, or raw provider credentials.

Suggested frontend components:

- Workspace browser page or side panel.
- Live view iframe/video/canvas from the provider or self-hosted stream.
- Address bar and navigation controls.
- "Agent has control" / "Human has control" lock state.
- Take-over and hand-back controls.
- Action timeline with redacted inputs.
- Approval prompts for sensitive actions.

Suggested database fields:

- `browser_sessions.workspace_id`
- `browser_sessions.agent_id`
- `browser_sessions.user_id`
- `browser_sessions.provider`
- `browser_sessions.provider_session_id`
- `browser_sessions.status`
- `browser_sessions.current_url`
- `browser_sessions.current_title`
- `browser_sessions.expires_at`
- `browser_sessions.last_activity_at`
- `browser_sessions.policy`
- `browser_sessions.encrypted_connection`
- `browser_sessions.recording_url`

## Security Requirements

This feature is risky because the browser can reach the same sites and forms a human can reach.

Minimum requirements:

- Per-workspace isolation and `forWorkspace()` scoping everywhere.
- One active control owner at a time: human, agent, or system.
- Domain allowlist/blocklist policy per workspace/agent/session.
- Human approval for purchases, auth delegation, destructive actions, credential entry, file upload/download, cross-domain navigation into sensitive sites, and any external mutation.
- Secrets and live/CDP URLs encrypted at rest and never exposed to the model.
- Advanced `browser_playwright` execution should have its own permission bit so a workspace can allow high-level browser tools while denying arbitrary Playwright JS.
- Redaction for typed values, cookies, headers, local storage, auth tokens, and payment data.
- No reuse of browser profiles across workspaces.
- Aggressive cleanup of downloads, uploads, screenshots, recordings, and auth state.
- Network egress policy, rate limits, concurrency limits, max session duration, and idle timeout.
- Clear audit trail tied to workspace, user, agent, task, and approval request.

OpenAI's computer-use guidance aligns with this: isolate the browser/container, keep allowlists/blocklists, and keep a human in the loop for high-impact or hard-to-reverse actions.

## Web Search Versus Browser Control

Web search should be a separate capability from browser control.

For most research tasks, OpenCompany should prefer:

1. Search API or hosted web-search tool.
2. Fetch/read/extract HTML or markdown.
3. Use full browser only when JS rendering, login, interaction, screenshots, file upload, or human takeover is required.

This avoids burning expensive browser sessions for simple search/fetch work and keeps the permission model cleaner.

## Phased Implementation

### Phase 1: Local Playwright runtime

- Implement a local Playwright `BrowserSessionProvider`.
- Launch a headed Chromium browser for development.
- Add a simple workspace browser page or developer panel that can create and inspect sessions, even if the first live view is the local browser window.
- Add minimal create/navigate/screenshot/close actions.
- Confirm a human and the agent can control the same session without corrupting state.
- Confirm workspace isolation and session cleanup.

### Phase 2: Agent tools and approvals

- Add `BrowserRuntime` and `BrowserToolProvider`.
- Add the hybrid Lua surface: high-level `app.browser.*` functions plus `app.browser.playwright(...)` for raw Playwright JavaScript blocks.
- Route agent browser actions through existing permissions and approval flow.
- Add browser action logs.
- Add allowlist/blocklist policy.
- Add user takeover/handoff events.

### Phase 3: Provider abstraction and self-host path

- Add a managed provider adapter or self-hosted Playwright/KasmVNC adapter.
- Move provider-specific live-view details behind signed OpenCompany URLs.
- Add recordings, auth-state lifecycle, downloads/uploads, and billing/resource accounting.
- Consider a Docker Compose Chromium service after the local process implementation proves the contract.

### Phase 4: Advanced modes

- Add "use my browser" extension mode if needed.
- Add computer-use model integration for visual workflows.
- Add browser-session replay and comparison tools.
- Add per-agent browser profiles with explicit retention and consent.

## Recommendation

Start with local Playwright. Build the browser feature around an app-owned `BrowserRuntime` and a `BrowserSessionProvider` interface from day one, but do not introduce a Docker Compose Chromium service or managed browser provider until the local runtime proves the contract. Keep the OpenCompany boundary app-owned from the start:

- Browser sessions are first-class workspace resources.
- Provider URLs, Playwright endpoints, and CDP endpoints are secrets.
- Agents never get raw connection strings.
- All actions go through `BrowserRuntime`.
- Use a hybrid browser API: typed high-level tools for common actions, plus a permissioned Playwright JS executor for maximum flexibility.
- Browser tools integrate with the existing permission/approval model.

Do not start with direct iframe embedding, a provider-specific integration, a Docker Compose browser service, or a pure MCP server configuration. MCP can expose the actions later, but OpenCompany needs to own session lifecycle, UI live view, workspace isolation, approvals, and auditability.
