# Website Claims Alignment (2026-11)

Status: Change record. Applied 2026-11-02 to the uncommitted working tree of
`~/Projects/opencompany-website` (not committed or deployed).
Sources of truth: [Current State](../CURRENT_STATE.md) (capability matrix, confirmed incomplete
authorization work, explicitly not current features), [Product Direction](../PRODUCT_DIRECTION.md),
[LICENSING.md](../../LICENSING.md), and [COMMERCIAL_USE.md](../../COMMERCIAL_USE.md).

## Why

Phase 0 of the product direction requires "website claims match `CURRENT_STATE.md`". The marketing
site claimed compliance certifications, SSO/SCIM, SLAs, managed multi-region Cloud, user-capped
pricing tiers, Fair Source / Sustainable Use licensing, 24/7 autonomous agents, automatic provider
failover, and "Inspired by OpenClaw" / "A Company in a Box" positioning. None of these are current.

## What changed

- **Positioning:** hero, meta description, footer and FAQ now lead with "Grok Bot for companies: the
  messenger where your team works with named AI teammates". The OpenClaw tagline and "A Company in
  a Box" are gone. Per-teammate computers, the messenger-first UI and OpenCompany Cloud are labelled
  Coming or Planned.
- **License:** every Fair Source, Sustainable Use, BSL and license-key reference was replaced. The
  self-hosted app is AGPL-3.0 with no user, workspace, agent or run limits and no enterprise
  edition. SDKs, protocols and kits are Apache-2.0 where their path notices say so. The commercial
  model is "pay for the service, not for permission".
- **`/enterprise` page:** rewritten as "Self-hosting, Cloud & Services", on the same route. The SOC
  2, GDPR, HIPAA, ISO 27001 and CCPA badges, the Team/Business/Enterprise table (20/100/unlimited
  users, 99.5–99.99% SLA), BYOK, data residency, Helm, air-gapped and Hybrid deployment claims are
  removed. It now shows:
  - what is available today (workspace isolation, admin/member roles, agent tool permissions,
    approvals, usage/cost visibility, 22 providers);
  - three options: Self-hosted (free, available now), Cloud (Coming) and Support & services
    (Contact us);
  - a governance roadmap marked Planned;
  - an FAQ that states plainly that there are no certifications, no SLA and no SSO yet.
- **Features, index and use-cases pages:** these changes were removed or reworded:
  - removed: 24/7 uptime stats, model failover, the SSO/MFA/RBAC/SIEM/immutable-log cards, Cloud
    "99.9% SLA", the Kubernetes and air-gapped self-hosting claims, and unbacked productivity
    multipliers (5x, 10x, "2 humans manage 500+ accounts");
  - "agent spawning, auto-merge" became agents calling peer agents as tracked tasks and runs;
  - "event triggers and multi-step workflows" became scheduled prompt and script automations plus
    webhook receipts.
- **What the site now presents as current:** Telegram as the deepest channel (other adapters
  lighter), the ~590-entry integration catalog (described as a catalog, not as verified), remote MCP
  client, hybrid pgvector memory, the VFS, Codex OAuth, and the non-streaming AI gateway. Scripting is
  presented as "sandboxed Ruby (mruby) Code Mode".
- **Docs tree:**
  - the enterprise pages and sidebar mark SSO and data residency as Planned;
  - access-control lists the confirmed authorization gaps;
  - security states that no attestations are held and no BAAs are signed;
  - the spawning docs drop the parallel and self-scaling claims;
  - the Lua page is retitled "Lua Scripting (Legacy)" with a callout saying Lua is retired in
    favour of mruby Code Mode;
  - the automation index and scheduled pages relabel their Lua links.
- **README:** replaced the Astro starter with a short README that states this claims rule.

## Lua docs pages

- `src/pages/docs/guide/automations/lua.mdx`: legacy/retired callout added.
- `src/pages/docs/guide/automations/index.mdx` and `scheduled.mdx`: links relabelled as legacy.
- `src/pages/docs/resources/changelog.mdx`: historical entry, left unchanged.
- `src/pages/docs/open-source/chatogrator/state.mdx`: Redis Lua lock scripts, not OpenCompany
  scripting; left unchanged.

## Known follow-ups

- `docs/integrations/ai-providers/index.mdx` and `docs/api/endpoints/workspaces.mdx` still describe
  the retired Prism runtime and need a technical rewrite.
- The footer links to `/about`, `/blog`, `/careers`, `/community`, `/contact`, `/press`, `/privacy`,
  `/security` and `/terms`, and several docs link to `/docs/technical/*`. None of these pages exist.
  The broken links predate this change.
- The roadmap docs page still lists the Workflow Builder and Agent Marketplace. They are labelled as
  roadmap items, but PRODUCT_DIRECTION does not prioritise them.
- The "ephemeral agents are cleaned up automatically" claim in the spawning docs is unverified.
