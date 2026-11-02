# OpenCompany Documentation

Status: Current documentation policy.
Verified: 2026-11-02 (authority order and document classes); implementation documentation
2026-08-09 against the active local checkout; licensing navigation 2026-09-01.

This directory is the canonical source for OpenCompany product direction and implementation truth: the locked direction, current runtime behavior, code-owned architecture, active engineering plans, UI references, QA evidence, and historical technical decisions.

Start here:

- [Product Direction](PRODUCT_DIRECTION.md) — the locked direction: what OpenCompany is becoming
  ("Grok Bot for companies"), its eleven pillars, the nuclear decisions, and the phase order
  (Phases 0–5).
- [Connections, Events and Routines](planning/connections-events-routines.md) — the accepted
  pillar 9 design for integrations, file sync, automations, triggers and proactive behaviour.
- [UI Structure and Agent Editing](planning/ui-structure-and-agent-editing.md) — the accepted
  pillars 4 and 7 design: Home, the one rail, three panes, change sets, Spaces, core primitives and
  add-on apps, with [mockups](ui/mockups/sidebar-2026-11.html).
- [Artifacts and Forms](planning/artifacts-and-forms.md) — the accepted pillar 7 design for agent-built
  Pages, interactive artifacts, the publishing ladder, and form views on any Table.
- [`oc` CLI and hosts](planning/oc-cli-and-hosts.md) — the accepted pillars 2 and 10 design for the
  agent-first Rust CLI, `oc mcp serve`, and connected hosts through the outbound `oc host` daemon.
- [Data sovereignty](planning/data-sovereignty.md) — the accepted pillar 11 design for object
  downloads, the `.ocarchive` workspace archive, customer-owned encrypted backups, the Git mirror and
  Cloud exit guarantees.
- [OpenCompany × Bowerbird](planning/bowerbird-synergy.md) — the accepted portfolio design: workload
  split, shared mruby engine and CLI crates, Bowerbird computers and deep connection, sequencing.
- [Current State](CURRENT_STATE.md) — what exists, what is partial, and what is not implemented.
- [Roadmap and Open Audits](ROADMAP.md) — prioritized work by phase and unresolved audit findings.
- [Documentation Index](INDEX.md) — the complete navigation map, with every document classed as
  active, historical, or superseded.
- [Licensing FAQ](../LICENSING.md) — prospective AGPL-3.0-only scope, complete self-hosting,
  Apache-2.0 integration edges, separate Cloud, and historical-release terms.
- [Commercial Use](../COMMERCIAL_USE.md) — open-source commercial-use, hosting, source-obligation,
  support, and official Cloud guidance.
- [Trademark and Compatibility Policy](../TRADEMARKS.md) — fork, compatibility, and official-brand
  boundaries.
- [Contributing](../CONTRIBUTING.md) — inbound-equals-outbound contribution and DCO policy.
- [Agent Run Queue Control Plane](architecture/agent-run-queue-control-plane.md) — current durable execution and retry model.
- [AI Provider Runtime](architecture/ai-provider-runtime-architecture.md) — provider, model, gateway, caching, and cost ownership.
- [VFS Runtime](tools/vfs.md) — current virtual-filesystem commands and contracts.

## Authority order

| Document | Owns | Does not own |
|---|---|---|
| [PRODUCT_DIRECTION.md](PRODUCT_DIRECTION.md) | What the product is becoming, pillars, phase order, what earlier directions are superseded | Implementation claims, task-level priority |
| [CURRENT_STATE.md](CURRENT_STATE.md) | What is implemented, partial, or absent in the checkout | Product intent or sequencing |
| [ROADMAP.md](ROADMAP.md) | Engineering priority, open audits, and which phase each item belongs to | Direction; it must follow PRODUCT_DIRECTION.md |

When these conflict, fix the document that is out of its lane. Every other document defers to them.
Phase 0 security and authorization work always outranks new product surfaces.

## Source-of-truth boundary

| Material | Canonical home |
|---|---|
| Product direction and phase order | [PRODUCT_DIRECTION.md](PRODUCT_DIRECTION.md) |
| Current application behavior and code architecture | This repository under `docs/` |
| Software license and commercial-use interpretation | Root `LICENSE.md`, `LICENSING.md`, and `COMMERCIAL_USE.md`; the license text controls |
| Trademark and contribution policy | Root `TRADEMARKS.md` and `CONTRIBUTING.md` |
| Active implementation plans and code-backed audits | This repository under `docs/planning`, `docs/architecture`, and `docs/qa` |
| Private business strategy, sales material, market research, corporate planning, and cross-repository thinking | Sibling `../oc-docs` vault |
| Runtime behavior | Code, migrations, routes, configuration, and tests; documentation must follow them |
| Provider capabilities, deployment state, credentials, and external service health | Live verification; never infer these from a catalog or old document |

The sibling vault contains migrated snapshots of older OpenCompany technical documents. Those copies are historical context unless its OpenCompany index explicitly identifies a vault-owned strategy document. Do not maintain two independent implementation descriptions.

## Status vocabulary

Every document should be interpreted using one of these states:

- **Current** — checked against the active code path and intended as an implementation reference.
- **Implemented in working tree** — code exists locally but may be uncommitted, undeployed, or awaiting full verification.
- **Partial** — foundations exist, but the complete product claim does not.
- **Planning** — proposed work; file paths and examples are targets unless marked present.
- **Research** — external-system or market input, not OpenCompany behavior.
- **Historical** — retained decision/audit context; current behavior lives elsewhere.
- **Superseded** — replaced by the product direction or a later decision (for example Lua
  scripting, the integration package system and catalog, Automations and visual programming, agent
  behaviour modes, Bowerbird-owned triggers, the Reader-first modular OS, the AI-app-builder
  framing, the embedded app browser).
  Kept for context; do not plan from it.

[INDEX.md](INDEX.md) groups these into four navigation classes: active, active but partially
superseded, historical, and superseded.

When a long historical document contains obsolete examples, its status guard and the current-state document take precedence. Historical text should not be silently rewritten to pretend it was always current.

## Directory guide

| Directory | Purpose |
|---|---|
| `architecture/` | Current architecture, dated audits, investigations, and historical decisions |
| `planning/` | Active or explicitly historical implementation plans |
| `qa/` | Dated browser/manual QA evidence; findings need resolution annotations before being treated as current |
| `testing/` | Test strategy and coverage guidance |
| `tools/` | Current agent/tool runtime references |
| `ui/` | Page, component, layout, and design-system references for today's Inertia UI; `ui/mockups/` holds the accepted new-client mockups |
| `ecosystem/` | Imported external-project references and the superseded integration-package docs; not current app behavior |
| `market/` | Product/market research, not shipped capability |
| `research/` | Dated landscape and competitor evidence behind the product direction |
| `guides/` | Operator and developer setup guides |

## Maintenance rules

1. Update [CURRENT_STATE.md](CURRENT_STATE.md) when a material feature crosses between planned, partial, and implemented.
2. Change [PRODUCT_DIRECTION.md](PRODUCT_DIRECTION.md) only with an owner decision; when it changes, re-check the Roadmap phases and the INDEX classes.
3. Update [ROADMAP.md](ROADMAP.md) when an audit item is fixed, superseded, accepted, or rejected.
4. Add `Status` and `Verified` lines to new technical documents.
5. Keep counts labeled as dated snapshots; regenerate them instead of hand-carrying old values.
6. Distinguish a catalog entry from configured credentials, a passing local test from a live provider check, and local working-tree code from deployed behavior.
7. Keep `AGENTS.md` and `CLAUDE.md` byte-identical.
8. Add every new document to [INDEX.md](INDEX.md) with a class; mark retired ones superseded rather than deleting their history.
9. Never copy secrets, production/customer data, auth state, or raw provider payloads into documentation.
10. Preserve historical release licenses; do not rewrite a past BSL or FSL record as though it
    shipped under the prospective AGPL policy.
