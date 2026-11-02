# Contributing to OpenCompany

Status: Accepted prospective community contribution policy; publication workflow and automation
remain to be implemented and reviewed.
Last verified: 2026-09-01.

Issues, design discussions, reproducible bug reports, documentation, integrations, and pull
requests are welcome. OpenCompany uses an inbound-equals-outbound model:

- contributions to AGPL-covered application paths are provided under `AGPL-3.0-only`;
- contributions to explicitly Apache-2.0 protocols, schemas, SDKs, integration kits, examples, and
  templates are provided under Apache-2.0; and
- documentation contributions use the license declared for that documentation path.

Contributors retain their copyright. The project does not infer copyright assignment, a private
relicensing grant, or permission to issue proprietary exceptions from a pull request.

## Developer Certificate of Origin

Every commit intended for merge must certify the
[Developer Certificate of Origin 1.1](https://developercertificate.org/) with a Signed-off-by line:

```text
Signed-off-by: Your Name <you@example.com>
```

Use `git commit -s` to add it. Signing off means you have the right to submit the contribution under
the destination's stated license; it is not a copyright assignment.

## Provenance and AI-assisted work

Submit only original work or material whose permission, source, and license are documented and
compatible. Disclose material AI assistance when it affects provenance review. Do not submit:

- credentials, auth state, customer data, private prompts or transcripts, or production logs;
- generated material whose rights or source cannot be explained;
- copied third-party code, tests, assets, datasets, or model output without compatible terms and
  attribution; or
- code that moves a proprietary Cloud implementation into an open-source path or AGPL core code
  into an Apache-only artifact.

Reviewers must verify destination license, third-party notices, test evidence, security impact, and
the Signed-off-by chain before merge. The planned automation should enforce these checks; this
document does not claim that automation exists today.

See [Licensing FAQ](LICENSING.md), [Commercial Use](COMMERCIAL_USE.md), and [Trademark and
Compatibility Policy](TRADEMARKS.md). This policy is not legal advice.

## Product direction and where to start

Read [docs/PRODUCT_DIRECTION.md](docs/PRODUCT_DIRECTION.md) before proposing features. It is the
locked direction ("Grok Bot for companies") and sets the phase order. Check
[docs/CURRENT_STATE.md](docs/CURRENT_STATE.md) for what exists today and
[docs/ROADMAP.md](docs/ROADMAP.md) for priority. Phase 0 security and authorization fixes always
take priority over new surfaces. Proposals that revive superseded directions (Lua scripting,
per-app integration packages, Automations as they were, agent behaviour modes, a visual flow
builder, the embedded in-app browser, Reader-first modules, AI-app-builder positioning) should start
as an issue, not a pull request.

Contribution rules that follow from the direction:

- **API-first UI; Inertia is frozen.** Do not add Inertia pages or page props. New UI, starting with
  the Phase 1 messenger, is a client of the versioned public API and the realtime event stream, so
  web, mobile and desktop clients share one backend. The new client is a Vue 3 + Reka UI SPA with a
  token design system. See
  [docs/architecture/api-first-client-split.md](docs/architecture/api-first-client-split.md) and
  [docs/planning/ui-structure-and-agent-editing.md](docs/planning/ui-structure-and-agent-editing.md).
- **Core primitives only.** Do not add new object kinds, generic pages or nested containers. New
  product areas are add-on apps declared as manifests over the core primitives, with no storage of
  their own.
- **No new per-app integration packages.** The integration package system is being deleted. Deep
  connectors are few, native and app-owned under `app/Domain/Connections/`; everything else goes
  through the generic tier (MCP servers, API manifests, skills, signed webhooks). See
  [docs/planning/connections-events-routines.md](docs/planning/connections-events-routines.md).
- **Routines, not flows.** Automation is Connections → Events → Triggers → Routines → Runs, with
  routines authored in chat. Do not build a visual flow builder. A trigger is never consent.
- **Policy, not modes.** One policy engine with Personal, Team and Company presets, gated by
  reversibility: make actions undoable (change sets, trash, drafts) so they need no approval, and
  gate external sends, spend and exposure. An agent never exceeds the human it acts for. Do not
  reintroduce behaviour modes, and keep secrets behind the credential broker.
- **Computers are optional.** Features must work for agents without a computer. Computer-specific
  behavior goes through the `ComputerProvider` contract, and company data reaches a computer only
  through the VFS.
- **One scripting runtime.** Code Mode work targets mruby only.
- **Honest docs.** Public copy and docs claim only what [CURRENT_STATE.md](docs/CURRENT_STATE.md)
  supports. Add new documents to [docs/INDEX.md](docs/INDEX.md) with a class.

Repository conventions for code, comments, workspace scoping, and routing live in
[AGENTS.md](AGENTS.md).
