# OpenCompany Documentation

Status: Current documentation policy.
Verified: implementation documentation 2026-08-09 against the active local checkout; licensing
navigation refreshed 2026-09-01.

This directory is the canonical source for OpenCompany implementation truth: current runtime behavior, code-owned architecture, active engineering plans, UI references, QA evidence, and historical technical decisions.

Start here:

- [Current State](CURRENT_STATE.md) — what exists, what is partial, and what is not implemented.
- [Roadmap and Open Audits](ROADMAP.md) — prioritized work and unresolved audit findings.
- [Documentation Index](INDEX.md) — the complete navigation map.
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

## Source-of-truth boundary

| Material | Canonical home |
|---|---|
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

When a long historical document contains obsolete examples, its status guard and the current-state document take precedence. Historical text should not be silently rewritten to pretend it was always current.

## Directory guide

| Directory | Purpose |
|---|---|
| `architecture/` | Current architecture, dated audits, investigations, and historical decisions |
| `planning/` | Active or explicitly historical implementation plans |
| `qa/` | Dated browser/manual QA evidence; findings need resolution annotations before being treated as current |
| `testing/` | Test strategy and coverage guidance |
| `tools/` | Current agent/tool runtime references |
| `ui/` | Page, component, layout, and design-system references |
| `ecosystem/` | Imported package and external-project references; not automatically current app behavior |
| `market/` | Product/market research, not shipped capability |

## Maintenance rules

1. Update [CURRENT_STATE.md](CURRENT_STATE.md) when a material feature crosses between planned, partial, and implemented.
2. Update [ROADMAP.md](ROADMAP.md) when an audit item is fixed, superseded, accepted, or rejected.
3. Add `Status` and `Verified` lines to new technical documents.
4. Keep counts labeled as dated snapshots; regenerate them instead of hand-carrying old values.
5. Distinguish a catalog entry from configured credentials, a passing local test from a live provider check, and local working-tree code from deployed behavior.
6. Keep `AGENTS.md` and `CLAUDE.md` byte-identical.
7. Never copy secrets, production/customer data, auth state, or raw provider payloads into documentation.
8. Preserve historical release licenses; do not rewrite a past BSL or FSL record as though it
   shipped under the prospective AGPL policy.
