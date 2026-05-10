# Stripe Link Agents Investigation

Date: 2026-04-30

## Scope

Investigate `https://link.com/en-be/agents` for relevance to OpenCompany's agent runtime, tool system, approvals, and MCP integration path.

## Summary

Stripe Link Agents is not a general agent framework. It is a user-controlled payment credential flow for agents: the agent requests one-time-use credentials from a user's Link wallet, the user approves the purchase, and the agent uses the approved credential at checkout.

The near-term fit for OpenCompany is "agents can request payment credentials under explicit human control", not "agents get an internal company wallet". The current public availability is US-only. Link itself is a broader wallet/app/payment service; the public agent-facing developer path found in this investigation is `@stripe/link-cli`, which can also run as a local MCP server.

Recommendation: do not add this as a default built-in tool yet. Treat it as a high-risk external integration behind a dedicated package/prototype, after OpenCompany supports Link's available agent integration path and has strict payment-credential safeguards. If using the published CLI/MCP path, OpenCompany needs command/stdio MCP support or a controlled sidecar for local CLI-backed MCP servers.

## What Link Agents Provides

- A Link wallet controlled by the user.
- Agent spend requests that trigger user approval in Link.
- One-time-use virtual card credentials for normal checkout forms.
- Shared payment tokens for merchants using Machine Payments Protocol / HTTP 402.
- Purchase history and future controls such as granular agent controls, more payment methods, and saved buying preferences.

Official page notes:

- `link.com/en-be/agents` says agents can spend on the user's behalf, payment credentials are not exposed long-term, and each purchase is approved by the user.
- The page currently says "US only" and "Currently available in the US, coming soon globally."
- The linked GitHub project is `stripe/link-cli`, MIT licensed.

## Public Agent Integration Surface

Link is not CLI-only. The consumer product includes the Link wallet, app/web approval UX, purchase history, saved payment methods, and checkout/payment experiences. For custom agents, the public implementation found here is `@stripe/link-cli`.

Verified current package state on 2026-04-30:

- npm latest: `@stripe/link-cli@0.4.1`
- license: MIT
- runtime: Node >= 18
- GitHub: `https://github.com/stripe/link-cli`
- `@stripe/link-sdk` appears in the GitHub monorepo as a workspace package but was not published on npm when checked on 2026-04-30.

Install and usage patterns:

```bash
npm install -g @stripe/link-cli
link-cli --help
```

The CLI can also be run as a local MCP server:

```json
{
  "mcpServers": {
    "link": {
      "command": "npx",
      "args": ["@stripe/link-cli", "--mcp"]
    }
  }
}
```

Core flow:

1. `link-cli auth status --format json`
2. `link-cli auth login --client-name "OpenCompany" --format json`
3. `link-cli payment-methods list --format json`
4. Inspect merchant checkout and choose `card` or `shared_payment_token`.
5. `link-cli spend-request create ... --request-approval --format json`
6. Poll the returned command until approval reaches a terminal state.
7. Retrieve the approved credential only when needed to complete checkout.

The skill file is explicit that agents must inspect the merchant before creating a spend request. A card credential is appropriate for traditional checkout forms. A shared payment token is only appropriate for HTTP 402 / Machine Payments Protocol flows with a Stripe challenge.

## OpenCompany Fit

### Good Fit

OpenCompany already has primitives that map to Link's risk model:

- Tool-level approval wrapping exists in `ApprovalWrappedTool`, including amount detection for budget-like parameters.
- Agent permissions already distinguish tool access, integrations, and behavior modes.
- MCP servers are represented as workspace-scoped integrations.
- External tools can be registered into the integration provider registry and surfaced in catalogs.

This means the product model is aligned: a workspace agent can be allowed to request a payment, while humans remain in the approval path.

### Compatibility Gaps

1. MCP transport mismatch.

OpenCompany's MCP client currently posts JSON-RPC to a stored HTTP URL. Link's documented MCP configuration starts a local command with `npx @stripe/link-cli --mcp`. That is a command/stdio style integration, not a URL that can be pasted into the current MCP server UI.

Relevant app code:

- `app/Services/Mcp/McpClient.php` constructs an HTTP client and posts JSON-RPC to `$this->url`.
- `app/Models/McpServer.php` stores an encrypted `url`, auth type, headers, and discovered tools.

2. Generic MCP proxy is too loose for payment credentials.

`McpProxyTool` returns text extracted from MCP content. That is fine for low-risk tools, but payment credentials need stricter handling:

- never persist raw card data in messages, task steps, logs, or approval context;
- mask card and address fields by default;
- separate spend-request creation from credential retrieval;
- block credential retrieval unless it is immediately used in a checkout or returned only on direct user request.

3. Double approval needs design.

OpenCompany can require approval before executing a tool. Link also requires approval before provisioning spend credentials. If implemented naively, users could get two approval prompts for the same purchase. The better split is:

- OpenCompany approval: "may this agent initiate a spend request for this merchant and amount?"
- Link approval: "may this exact credential be issued from my Link wallet?"

For most user-facing flows, OpenCompany should require explicit approval on Link tools but should not auto-approve payment tools using a global budget threshold.

4. Direct tool availability is not automatic.

OpenCompany currently keeps only `tasks`, `system`, `agents`, `memory`, and `lua` as direct AI-callable tool groups. Other integrations are exposed through the code-first Lua API path. A Link integration should either:

- intentionally remain code-first with strong docs and guards; or
- add a narrowly scoped direct tool group for payment requests, with no direct raw-credential tool.

5. Availability and product maturity.

The page is US-only today. That makes it a pilot feature for specific users, not a default OpenCompany capability.

## Recommended Implementation Path

### Phase 0: No Product Integration Yet

Track Link as an emerging payments capability. Do not expose it in the default product until the transport and safety controls are explicit.

### Phase 1: Internal Test-Mode Prototype

Build a local-only prototype using Link test mode.

Required work:

- Add command/stdio MCP support, or run a trusted local sidecar that exposes Link's MCP server over Streamable HTTP.
- Mark every Link tool as write/high-risk.
- Disable budget auto-approval for Link tools.
- Store spend request IDs and approval status, never raw card details.
- Add explicit redaction for card, CVC, billing address, and shared payment tokens in logs/messages/task steps.
- Require merchant domain, total amount, currency, line items, and a user-readable purchase context before spend request creation.

Acceptance:

- Can authenticate Link using the verification URL and phrase.
- Can list payment methods without leaking sensitive details.
- Can create a test-mode spend request.
- Can poll until approved/denied/expired.
- Raw credentials are not persisted anywhere in OpenCompany storage.

### Phase 2: Dedicated Integration Package

If the prototype is useful, extract it as a dedicated integration package, not generic app-local code.

Suggested shape:

- Package: `opencompanyapp/ai-tool-link`
- Provider: `LinkToolProvider`
- Tools:
  - `link_auth_status`
  - `link_start_auth`
  - `link_list_payment_methods`
  - `link_create_spend_request`
  - `link_poll_spend_request`
  - `link_pay_mpp`
- Avoid a generic `link_retrieve_card` tool unless it has strict redaction and immediate-use semantics.

This belongs in a package because the behavior is external integration/runtime behavior, not OpenCompany-specific workspace logic. OpenCompany-specific pieces are permissions, approval UX, audit records, and workspace/user account binding.

### Phase 3: Product UX

Only after Phase 2:

- Add Link to the Integrations UI as a high-risk payment integration.
- Bind Link auth per human user, not as a shared workspace secret by default.
- Add a purchase approval surface that shows merchant, amount, line items, requester agent, and target payment method label.
- Add an audit timeline for spend request lifecycle events.
- Add policy controls: allowed merchants, max amount per request, max daily amount, require owner approval, and test-mode only.

## Risk Notes

- Treat payment credentials as secrets with immediate spending power.
- Link's own skill warns agents to respect `/agents.txt`, `/llm.txt`, and site-specific automation rules.
- Merchant inspection is part of the protocol; OpenCompany would need a reliable browser/checkout inspection capability before making production purchase flows dependable.
- Shared payment tokens are one-time-use. Retry logic must create a new spend request after token consumption or failure.
- A generic MCP pass-through is not enough for payment tools because the generic proxy cannot enforce credential-specific redaction and persistence rules.

## Sources

- Link Agents page: `https://link.com/en-be/agents`
- Link skill file: `https://raw.githubusercontent.com/stripe/link-cli/main/skills/create-payment-credential/SKILL.md`
- Link CLI repo: `https://github.com/stripe/link-cli`
- npm package metadata checked locally with `npm view @stripe/link-cli version description license dist-tags --json`
