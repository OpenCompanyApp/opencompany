# Agent Primitives Catalog

Status: Accepted product requirements (owner direction, 2026-11-02). Nothing in this document is
implemented yet. Current implementation truth is in [Current State](../CURRENT_STATE.md).
Direction: [Product Direction](../PRODUCT_DIRECTION.md), pillar 6.
Evidence: the [agent landscape report](../research/2026-11-agent-landscape.md), the
[Grok Bot investigation](../architecture/grok-bot-opencompany-investigation-2026-08-17.md) and the
official sources linked below.

The 2026 agent products (xAI Grok Bot, OpenAI dots, Claude Duet, Meta Muse, Google Gemini Spark and
Chrome) share a set of small, reusable primitives. These primitives are what make an always-on agent
safe and pleasant to work with. OpenCompany should support them as first-class platform features,
adapted to a multi-user, workspace-governed product.

**Design rules that apply to every primitive:**

- **Workspace scope:** primitives are scoped to the workspace and granted per agent. Grok Bot's Vault
  is per account and "everything you save is available to every Bot on your account"
  ([xAI docs](https://docs.x.ai/grok-bot/vault)). OpenCompany grants each secret to specific agents.
- **No secrets in the model:** secrets never enter the model context, prompts, the VFS, mruby globals,
  logs or the agent's computer disk. They are filled or injected at the browser, tool or network
  boundary.
- **Both agent modes:** every primitive works for VFS-only agents (through integration tools, MCP and
  the credential broker) and for computer agents (through the browser and shell).
- **Every surface:** every human-facing primitive works on web, mobile web and Telegram. Telegram must
  use the Mini App for anything secret, because chat messages are not a secure input channel.
- **Audit:** every use is audited, with who, which agent, which secret or action, which run, and the
  outcome. Values are never logged.

## 1. Credentials and identity

| Primitive | What it does | Seen in | OpenCompany requirement |
|---|---|---|---|
| **Vault** | Stores logins, payment cards, contact info and API secrets. Values are write-only; the agent knows which entries exist but never sees the values. | Grok Bot Vault: "A Bot never sees a saved password, card number, or security code" ([xAI](https://docs.x.ai/grok-bot/vault)) | Encrypted per-workspace vault with personal and shared entries, per-agent grants, a usage audit and a rotation reminder. |
| **Secure Form** | The agent asks a human for a value it must not see (password, OTP, card, API key). The value goes straight to the page or tool, and can optionally be saved to the vault. | Grok Bot Secure Forms ([xAI](https://docs.x.ai/grok-bot/vault)); dots secure sign-in (landscape report) | Inline secure-form card in chat (web/mobile) and in the Telegram Mini App. Values are delivered to the browser fill or the credential broker, never to the model. |
| **Password manager connectors** | Lets the agent use credentials from the company's existing password manager, with human approval before a fill. | 1Password for Agents and Secure Agentic Autofill ([1Password blog](https://1password.com/blog/closing-the-credential-risk-gap-for-browser-use-ai-agents), [Android Authority](https://www.androidauthority.com/1password-for-agents-launch-3726912/)); Bitwarden MCP server and Agent Access SDK ([Help Net Security](https://www.helpnetsecurity.com/2025/07/11/bitwarden-mcp-server/), [BusinessWire](https://www.businesswire.com/news/home/20260114341558/en/Bitwarden-Strengthens-Identity-Security-Posture-for-2026-With-New-Enterprise-Capabilities-Passkey-Innovation-and-Secure-AI-Assisted-Workflows)); Grok Bot connects 1Password ([xAI](https://docs.x.ai/grok-bot/vault)) | A `SecretSource` contract with lookup order **vault → connected password manager → secure form** (Grok Bot's order). First connectors: 1Password, then Bitwarden/Vaultwarden (self-host friendly). API-secret managers such as HashiCorp Vault/OpenBao and Infisical come later. CSV import from 1Password, Bitwarden, Chrome, Apple, Firefox and LastPass, with an "unencrypted export, delete it" warning. Verify each vendor's agent API during design. |
| **Credential broker** | Injects credentials at the network or tool boundary; the agent holds only placeholder tokens. | Meta Muse surrogate tokens; Anthropic vault behind a proxy (landscape report) | One broker for integrations, MCP OAuth tokens, computer egress and browser fills, with run-scoped grants. |
| **Agent's own inbox and phone number** | The agent has its own email address and optionally a phone number, for sign-ups, notifications and one-time codes. | Grok Bot reads codes "from its own inbox" ([xAI](https://docs.x.ai/grok-bot/vault)); Meta Muse's own email; Gobii (landscape report) | Optional per-agent email address, routed into the agent's inbox through the VFS, with OTP extraction. Phone/SMS comes later through a provider. Outbound mail is approval-gated by default. |
| **One-time codes** | Reads OTPs from a connected mailbox or the agent's inbox. | Grok Bot ([xAI](https://docs.x.ai/grok-bot/vault)) | OTPs are used by the fill or broker and redacted from model context where possible. |

## 2. Human handoff and supervision

| Primitive | What it does | Seen in | OpenCompany requirement |
|---|---|---|---|
| **Takeover and hand back** | A human takes control for SSO, passkeys, CAPTCHAs, cross-device approvals, QR codes or bank verification, presses "I'm done", and the agent resumes. The signed-in session persists. | Grok Bot "Take over / I'm done" ([xAI](https://docs.x.ai/grok-bot/vault)); Gemini/Chrome work log with takeover | Computer panel with a remote screen (noVNC or similar) available on web and mobile. The agent raises a takeover request card stating the reason. The session persists in the agent's browser profile. |
| **Live view and work log** | Watch what the agent is doing in real time; watching does not grant control. | dots, Chrome, Grok Bot (landscape report) | Live run stream in chat plus the computer panel. Separate view and control permissions. |
| **Approval cards** | Explain what, why and the source; one-tap answers. | Duet, dots, Muse, Perplexity (landscape report) | Scoped grants: once, this run, this task, time-limited, always. Exact-match on later calls (Muse Sentinel pattern). Learned exceptions: after repeated approvals of the same pattern (default 3) the card offers a scoped, exact-match "always allow"; admins can restrict who may accept. Batched review: many similar actions are one card with a toggle per item. Which actions need a card at all is set by reversibility and the workspace preset (Personal, Team, Company). |
| **Autonomy slider** | Per-agent *Ask first · Balanced · Autopilot*. | Pillar 5 of the product direction | Each position is a named bundle of scoped exceptions, capped by the preset and the hard-deny floor; it only writes grants into the policy engine and is not the deleted behaviour modes. New agents start at Balanced. |
| **Non-blocking approvals** | The agent keeps doing other work while an approval is pending, then resumes that step. | Claude Duet (landscape report) | Runs park the blocked step and continue independent steps. |
| **Mandatory handoffs and a hard-deny floor** | Some actions always go back to a human; some are never allowed. | dots: password changes and money transfers handed back; Duet hard-deny list (landscape report) | Platform floor that workspace policy can tighten but never loosen. |
| **Ask versus notify** | Blocking question versus non-blocking progress update. | Manus message tools (landscape report) | Two distinct agent tools and two card types. |
| **File handoff** | Human and agent exchange files mid-task. | All products | Drag-and-drop into chat lands in the VFS; the agent can hand files back as attachments. |

## 3. Payments

| Primitive | What it does | Seen in | OpenCompany requirement |
|---|---|---|---|
| **Single-use virtual cards** | The agent requests a one-time card showing merchant, items and total; the human pays, pays another way, or denies. | Grok Bot with Link ([xAI](https://docs.x.ai/grok-bot/vault)); Meta Muse (landscape report) | Card-issuer connector later; approval card with merchant, items and total. |
| **Saved cards** | Grok Bot fills a saved card "without asking you each time" ([xAI](https://docs.x.ai/grok-bot/vault)). | Grok Bot | **Not copied.** Spend follows the preset: Personal asks or uses a per-agent spend cap; Team uses a budget per agent; Company uses a budget plus two approvers above a threshold. |
| **Spend caps** | Per agent, per workspace, per merchant. | Gemini agent for work, Paperclip, Notion (landscape report) | Shared with token/compute budgets; pause at the limit. |

## 4. Control

| Primitive | What it does | Seen in | OpenCompany requirement |
|---|---|---|---|
| **Distinct pause, stop, cancel-schedule and delete** | "Stopping work does not undo completed actions." | dots controls (landscape report) | Separate controls in UI and API. `ActiveAgentRun` already separates pause and cancel. |
| **Kill switch** | Stop one agent or all agents in a workspace immediately. | Common pattern | Admin-only, audited, also reachable from Telegram. |
| **Trash and purge** | Deletes are reversible so they need no approval. | Pillar 5 of the product direction | Delete moves to trash. Personal: trash (30 days) auto-applies. Team: trash auto-applies, purge needs approval. Company: trash is a proposal, purge needs two approvers. |
| **Budgets that pause** | Token, compute and spend limits pause work and ask for approval. | Paperclip, Notion (landscape report) | Phase 3 governance. |

## 5. Work, routines and memory

| Primitive | What it does | Seen in | OpenCompany requirement |
|---|---|---|---|
| **Routines** | Skill, bot and schedule, as one named recurring workflow. | Grok Bot (landscape report, Grok Bot investigation) | Productize Automations as agent-owned Routines. |
| **Teach by demonstration** | Record a human doing a task (Grok Bot: up to 10 minutes) and turn it into a skill. | Grok Bot (landscape report) | First: "save a successful task as a routine draft" from the run log. Then recording on the agent's computer. |
| **Action items and notes** | A structured list of open items and user notes, maintained by a single writer. | dots `/action_items.md`, `/user_notes/` (landscape report) | Agent-owned VFS areas with a single-writer rule. |
| **Daily brief and monitors** | Morning digest; background watchers that notify only when something is new. | Claude Duet, Google Daily Brief and Search agents, dots Heartbeat Dreamer (landscape report) | Heartbeat routines in read-only mode with "notify only if new" checkpoints. |
| **Memory controls** | View, edit and forget memories, each with its source. | Claude, Muse, Grok (landscape report) | Memory UI in the agent profile; every forget is audited. |

## 6. Computer

| Primitive | What it does | Seen in | OpenCompany requirement |
|---|---|---|---|
| **Persistent browser profile** | Logins survive between tasks. | Grok Bot, dots | One profile per agent computer, never shared across agents by default. |
| **Snapshot, fork and rollback** | Save and restore computer state. | boat, Bowerbird sandboxes | Exposed through `ComputerProvider`. |
| **Ports and preview links** | Share a running app. | Manus (`deploy_expose_port` was exploited with no approval) | Always approval-gated, time-limited and authenticated. |
| **Connect a local computer** | The agent can use one user-connected machine. | dots (landscape report) | Later, through the bring-your-own-host provider. |

## Phasing

- **Phase 2 (Computer v1):** vault, secure form, credential broker, takeover and hand back,
  persistent browser profile, live view, ports with approval; the money-movement and
  password-change hard-deny entries; agent inbox and OTPs via the Mail app (per the direction's
  phase table).
- **Phase 3 (Governance):**
  - password manager connectors (1Password first), scoped approval grants, the full hard-deny floor,
    non-blocking approvals;
  - pause/stop/cancel-schedule/delete, kill switch, spend caps;
  - routines, daily brief and monitors, memory controls.
- **Later:** single-use virtual cards, phone numbers, teach by demonstration, connected local
  computers, CSV vault import.

## Open questions

- Which vendor agent APIs (1Password for Agents, Bitwarden Agent Access SDK) work for a self-hosted
  server rather than only a browser extension or desktop app.
- Whether the vault reuses Laravel encryption with an app key, or supports an external KMS for hosted
  Cloud.
- How takeover streams reach mobile and Telegram with acceptable latency.
