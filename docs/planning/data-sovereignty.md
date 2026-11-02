# Data Sovereignty: Export, Backup and Portability

Status: **Accepted** (owner, 2026-11-02). Nothing here is implemented. Binding summary: [Product Direction](../PRODUCT_DIRECTION.md) pillar 11 and the phase table.
Owner requirement (2026-11-02): data must be easy to export, back up and download. Full data
sovereignty, including on hosted versions.
Direction: [Product Direction](../PRODUCT_DIRECTION.md) pillars 5 (governance), 8 (open source and
hosting) and 10 (`oc` CLI). Related: [Versioned workspace](versioned-workspace-master-overview.md),
[Connections, Events and Routines](connections-events-routines.md).

## Promise

> **Your workspace is yours.** You can download any object, export the whole workspace, back it up
> to storage you own, and move between OpenCompany Cloud and self-hosting in either direction, at
> any time, on any plan, at no extra cost, in documented open formats.

This is a product guarantee, not an enterprise feature. It is a direct consequence of the AGPL and
self-host-first position (pillar 8).

## 1. Object-level download (everyone, everywhere)

Every object can be downloaded from the new client, from `oc`, and through the API:

| Object | Formats |
|---|---|
| Doc | Markdown (canonical projection), PDF (Typst), DOCX, HTML |
| Table and records | CSV, XLSX, JSON/NDJSON (with schema) |
| Issues | CSV, JSON, Markdown |
| Thread | Markdown, JSON (including run activity and approvals) |
| Files | Originals; a folder as a ZIP |
| Artifact | Bundle ZIP (manifest, source, assets, versions) |
| Form | Definition JSON plus submissions as CSV/JSON |
| Routine, app or skill | Manifest or SKILL.md bundle |
| Agent | Identity, instructions, memory (with sources), skills, routines and grants as an "agent pack" |

Downloads respect permissions: you can export what you can see. Agents can download objects within
policy like any other read. Sending data outside the workspace is still an external action.

## 2. Workspace archive (full export, round-trippable)

One portable archive, the **OpenCompany Workspace Archive** (`.ocarchive`, a zstd-compressed tar):

- **Contents:**
  - `manifest.json`: format version, source instance and version, time, and checksums.
  - One NDJSON file per primitive and record type, including add-on app data, with a JSON Schema for
    each.
  - Version and history data: doc versions, change sets, artifact versions.
  - The **event log and audit trail**, including run records and approvals.
  - Files as content-addressed blobs.
  - Agents (identity, memory, skills, routines, grants), Spaces, members, roles, policies, presets.
  - App, artifact and connection definitions.
- **Secrets are excluded by default.** Connections import as "needs re-authorization". An owner may
  choose "include secrets", which encrypts them with a passphrase (age). Agents can never do this:
  credential export stays in the hard-deny floor for agents.
- **Open spec.** The format is documented and versioned with JSON Schemas, and published under
  Apache-2.0 (the protocol boundary in pillar 8) so third parties can read it.
- **Import:**
  - The same archive imports into a self-hosted or Cloud instance: `oc admin import`, or Admin →
    Import.
  - Import supports full restore, clone to a new workspace, and Cloud → self-host migration (and
    back).
  - Re-import must preserve the semantics of IDs, relations, history and the event log.
  - Round-trip tests are part of CI.
- **Partial exports** for one Space, a date range, or selected primitives.

## 3. Backups to storage you own

- **Scheduled backups** to customer-owned destinations through Connections: S3-compatible (S3, R2,
  B2, MinIO), WebDAV, SFTP, a connected host (`oc host`), or a Drive or OneDrive mount.
  - Encrypted client-side with a key the customer holds (age recipient). OpenCompany Cloud cannot
    read customer-held backups.
  - Incremental and deduplicated, using content-addressed blobs and an append-only event log.
  - Configurable retention, with verification runs that test-restore a sample.
- **Self-host:** `oc admin backup` and `oc admin restore` cover the database dump, file storage,
  config and the encrypted app key. There is documented point-in-time recovery guidance for Postgres.
  `oc admin doctor` warns when no backup has succeeded recently.
- **Cloud:** provider-side snapshots for disaster recovery, plus the same customer-owned
  destinations. Backups to the customer's own bucket are included on every plan.

## 4. Live access and mirrors

- **Always available:** the API, `oc`, `oc mount` (FUSE), `oc mcp serve` and webhooks.
- **Continuous Git mirror** (optional, building on the
  [versioned workspace](versioned-workspace-master-overview.md) plan): Docs as Markdown, Tables as
  CSV, Issues as Markdown and JSON, artifacts as source, pushed on change or on a schedule to the
  customer's Git host (GitHub, GitLab, Gitea, Forgejo). The result is a human-readable, diffable copy
  that stays useful without OpenCompany.
- **Later, for Company:** a read-only SQL export or analytics replica (Parquet or Postgres) for BI
  tools.

## 5. Hosted (Cloud) guarantees

- **Same code.** Cloud runs the AGPL application. Nothing in Cloud stores customer data in a
  proprietary format, so the workspace archive is complete on Cloud too.
- **Exit guarantee:**
  - Export and backups are free and unlimited on every plan.
  - After a subscription lapses or is cancelled, the workspace stays **read-only and exportable for
    at least 90 days** before deletion. Owners are warned before deletion.
  - One-command migration to self-hosting: `oc admin import --from cloud`.
- **Data residency.** The customer chooses the region (EU first). Later: customer-managed keys
  (BYOK/KMS) for encryption at rest.
- **Verified deletion.** Deleting a workspace purges primary data and schedules removal from
  provider backups within a documented window, with a deletion receipt.

## 6. Sovereignty beyond data

- **Models.**
  - Bring your own provider keys, or run local models (for example Ollama or OpenAI-compatible
    local servers through the app-owned provider catalog).
  - A workspace can be configured so that no data leaves for third-party models.
  - The run log shows which provider handled which run.
- **No phone-home.**
  - Self-hosted instances send no telemetry by default.
  - Any opt-in telemetry is documented and inspectable.
  - There are no remotely flippable feature flags or egress switches. This is a lesson from the Grok
    Build repository-upload incident in the landscape report.
- **Open formats everywhere.** Markdown, CSV and JSON are canonical where possible, and SKILL.md,
  MCP and app manifests are documented.

## 7. Governance

| Action | Gate |
|---|---|
| Download an object you can see | Read; audited for bulk downloads |
| Agent downloads or exports objects to an external destination | External action under normal policy |
| Full workspace export or archive | Owner and admin only. Personal: auto. Team: admin. Company: admin plus a second approver. Always audited; the archive link expires |
| Export including secrets | Owner only, passphrase-encrypted, re-authentication required. **Never by an agent** (floor) |
| Add or change a backup destination, or Git mirror target | `governance` class; agents cannot do it |
| Member's personal data export (GDPR access and portability) | Any member, for their own data |
| Workspace deletion | Owner, with an export prompt first and a deletion receipt |

## Phasing

| Phase | Work |
|---|---|
| 0 | Design the archive format alongside the squashed baseline schema; add a no-telemetry, no-remote-flags check to CI |
| 1 | Object downloads in the new client and `oc export` (Docs, Tables, Issues, Threads, Files) |
| 2 | Workspace archive export and import (`oc admin export/import`), artifact and form exports, `oc admin backup/restore` for self-host |
| 3 | Scheduled encrypted backups to customer-owned destinations; Git mirror; governance gates; GDPR member export; backup verification |
| 5 | Cloud exit guarantees (90-day read-only export window, Cloud → self-host migration), region choice, verified deletion; later BYOK and an analytics replica |

## Open questions

- Archive file extension and whether to sign archives with an instance key.
- Whether the Git mirror is two-way (import edits back as change sets) or one-way only. The
  recommendation is one-way first.
- Default backup retention and verification frequency.
