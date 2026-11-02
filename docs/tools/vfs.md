# VFS Runtime Reference

The runtime exposes the agent guide at `/tools/vfs.md` (same as `/tools/vfs/README.md`), worked examples at `/tools/vfs/examples.md`, and the generated reference below at `/tools/vfs/reference.md` inside the virtual filesystem.

> **Direction (2026-11-02, locked; not part of the runtime contract below).** Pillar 3 of the
> [Product Direction](../PRODUCT_DIRECTION.md): the VFS is the governed path to company data
> (tables, issues, docs, files).
>
> - **VFS-only agents** (a first-class, per-agent mode) use it as their complete workspace.
> - **Agents with a computer** (Phase 2, [Computer Provider Contract](../architecture/computer-provider-contract.md))
>   get the VFS projected into the computer (mount, sync or CLI bridge). Every read and write still
>   goes through VFS scoping, resource permissions, write policy, approvals and audit; the computer
>   is never a bypass.
> - **Deep-connector mounts** (pillar 9; Google Drive in Phase 1, then OneDrive/SharePoint, Dropbox,
>   Box, S3/R2, WebDAV, SFTP) appear in Files and the VFS, for example under `/files/drive/...`.
>   Writes go through the VFS write path with revision checks, and effective access is the external
>   ACL intersected with OpenCompany grants. SKILL.md skills are planned under a `/skills` mount.
>   See [Connections, Events and Routines](../planning/connections-events-routines.md).
> - The `app.vfs.*` helpers below are described for mruby Code Mode (`code_exec`), the only Code
>   Mode runtime; the agent-facing guide is `resources/code-docs/vfs.md`.
>
> None of these direction items is implemented. Agent-ergonomics findings are in the
> [VFS agent-friendliness audit](../architecture/vfs-agent-friendliness-audit-2026-11.md).

The VFS is a permission-aware, unix-like filesystem over OpenCompany workspace data. It is an interface over existing OpenCompany permissions, not a second permission system, and it is not a host shell. Every agent-facing contract below is declared once in `app/Domain/Vfs/Spec/VfsOperationSpecRegistry.php`; tool descriptions, schemas, provider metadata, Code Mode aliases, `/tools/vfs/code.json`, `/tools/vfs/openapi.json` and the generated section of this file come from it.

## Tool surface

Five tools are model-visible and stable across runs for the same permissions:

| Tool | Use it for |
|---|---|
| `vfs_exec` | Command lines: navigate, search, inspect, and mutations (`mkdir`, `cp`, `mv`, `rm`, `>`). |
| `vfs_read` | One text file with line numbers, `offset`/`limit` paging and its `version`; `cursor` for paged collection files. |
| `vfs_edit` | Exact-text edits of a text file: several edits, all or nothing, `dry_run` diff. |
| `vfs_write` | Whole-content `create`, `overwrite` (needs `version` when it exists) or `append`: files, documents, table rows (`rows/new.json`), list items (`new.md`), your notes. |
| `vfs_patch` | Structured records: task/list fields, table rows with `base`; also one text search/replace. |

`vfs_stat`, `vfs_ls`, `vfs_find`, `vfs_rg`, `vfs_exists`, `vfs_mkdir`, `vfs_cp`, `vfs_mv` and `vfs_rm` are Code Mode helpers only (`app.vfs.*`). The former `vfs_search` (= `vfs_rg` with `regex: false`, now `app.vfs.grep`) and `vfs_count` (= a final `vfs_ls` page, which carries `total_count`) were removed.

Permissions: three capability toggles govern every VFS tool. `vfs_exec` covers all reads (`vfs_exec`, `vfs_read` and the read helpers), `vfs_patch` covers `vfs_edit` and `vfs_patch`, and `vfs_write` covers `vfs_write` and mutations (including shell `mkdir`, `cp`, `mv`, `rm`, `>`). Tools that borrow a permission (`permissionAs` in the provider meta) have no toggle of their own and are hidden from the capabilities list. Record writes also compose the domain permission (the per-path rows in the generated reference below): documents `create_document` / `update_document` / `delete_document`, table rows `add_table_row` / `update_table_row` / `delete_table_row`, list items `create_list_item` / `update_list_item` / `delete_list_item`, file trash `delete_file`, agent identity files `update_agent_identity_file`; `/docs` writes also need an allowed document-folder scope. Agent memory files under `/agents` follow relationship rules: agents patch their own identity and memory, managers patch direct reports' identity files and `memory/MEMORY.md`. Read visibility alone never implies write capability.

Approvals: if any composed permission requires approval, the VFS creates one normal approval request covering every gated permission of the operation (for example `vfs_patch` + `update_table_row`, or `vfs_exec` + `vfs_write`) and stores the call for approved replay; an identical retry while pending returns the same approval id. Denied permissions block immediately, and approved replay revalidates permissions before mutating. `vfs_edit` is never wrapped in `ApprovalWrappedTool`: its write goes through `OpenCompanyVfs::patch()`, whose policy requests the approval (stored as the equivalent `vfs_patch` call), and its `dry_run` needs no approval at all.

Every entry point requires the bound workspace to be the acting agent's workspace; otherwise it fails with `permission_denied` (`context.reason: workspace_binding_mismatch`).

Governance invariants the VFS keeps (regressions in `VfsReviewFixesGovernanceTest`):

- Hidden stays hidden. `/docs/.trash` applies the full document read policy (folder denies, folder allows, sharing rows) over the parent chain including trashed ancestors. `not_found` suggestions (`did_you_mean`, `by_id`) and `ambiguous_match` lists only name documents you may read, at every path segment.
- Recursive `rm`, `mv` and trash restore of a `/docs` folder are all or nothing: if any descendant is denied, shared without you, read-only for you or a system document, nothing changes and `permission_denied` carries `context.blocked_descendants` (and `first_blocked` only when you can read it).
- Reads never serve more than the backing tool would without a human: when `list_members` or `get_routine` is allowed only with approval, `/workspace/members.json` redacts human emails (`email_redaction` names the tool) and `/routines/{id}.json` redacts bodies. Reads never create approval requests.
- `/approvals` and `/me/pending` are scoped by the approval's own workspace, not the requester's current one. `/approvals/{id}.json` redacts credentials in quoted (JSON, nested JSON) and unquoted forms; the stored replay parameters are never rewritten.
- Approval wrappers and replay resolve `/me/…` to the backing subject the policy sees (`OpenCompanyVfs::policySubject`). A replay that returns another `pending_approval` is recorded as `execution.disposition: approval_pending` with `pending_approval_id`, says so in the conversation, and keeps the agent parked on the new approval.
- Audit: every denial is its own `vfs.denied` row (read denials are never aggregated or dropped); `vfs.tool_call` rows carry the caller's surface (`tool`, `code`, `computer`, `approval_replay`, …); created objects carry `backend_type`/`backend_id`; parent folders a write creates implicitly get their own `vfs.directory.created` (`payload.implicit: true`).

## Result envelope

All VFS tools return compact JSON (no pretty printing) and never an empty string.

- Success: `{"ok":true,"result":{...}}`.
- `vfs_exec` is flat: `{"ok","exit_code","stdout","cwd"}` plus `truncated` (with a `hint` on how to continue), `next_cursor`, `output_truncated`, `stderr` when present, `data` only with `responseFormat: "structured"`, and `diagnostics.command_spec` only with `includeCommandSpec`. `stdout` appears exactly once.
- Failure: `{"ok":false,"error":{"code","message","context"}}`. `error` is the only failure location: a chained failure (`a && b`, `;`, `||`) is reported there too, never as `result.error`. `vfs_exec` failures also carry `exit_code` (2 for `invalid_request`/`unsupported`, else 1) and, with `2>&1`, the merged `stdout`.
- `internal_error` never contains exception text. The exception is logged with a `correlation_id`, which is the only thing returned in `context`.
- Invalid UTF-8 is replaced with U+FFFD and the payload gains `encoding_note`; a payload that still cannot be encoded becomes an `internal_error` envelope.
- Approval outcomes pass through unchanged from the VFS policy and the approval wrapper; tools do not reinterpret them.

## Error codes and contexts

| Code | Typical context | Raised when |
|---|---|---|
| `not_found` | `path` | The path does not exist or is not visible. |
| `not_readable` | `path`, `hint` | Reading a directory as content (`vfs_read` hints `ls`). |
| `not_writable` | `path` | Generated or read-only paths, unsupported write roots. |
| `permission_denied` | `path`, reason in message, `reason: workspace_binding_mismatch` | A composed permission or scope is missing. |
| `unsupported` | `field`, `hint`, `supported_flags`, `text_roots` | Unsupported command, flag or syntax; `vfs_edit` on a record root; `vfs_exec` `cursor` before the executor supports it. |
| `approval_required` | `approval_id`, `path`, `tool`, `tools`, `required_tools`, `wait_for_approval` | A gated permission created (or reused) an approval. Not a failure. |
| `invalid_request` | `field`, `path`, `hint`, `edit_index`, `minimum_bytes`, `bytes`, `max_content_bytes` | Missing/invalid input, bad budget or cursor, search text not found, identical edit, binary content, missing `version` for an overwrite, oversized record page. |
| `conflict` | `path`, `current_version`, `given_version`, `hint`; rows add `changed_fields`, `current` | The object changed since the caller's read. |
| `ambiguous_match` | `path`, `count`, `lines` (1-based), `hint`, `edit_index` | `old_text`/`search` matched more than once without `replace_all`. |
| `internal_error` | `correlation_id` | Unexpected failure. |

## Reading

`vfs_read` serves ordinary files from one full content read (never the truncated display read), so `version` describes exactly the bytes returned and `total_lines` is exact. Lines come numbered like `cat -n` (`%6d<TAB>line`); the prefix is not part of the file. `offset` is a 1-based line, `limit` a line count (default 2000), `maxBytes` (default 50,000) caps the page on whole lines; a single longer line is cut UTF-8-safely: the page is `truncated` with `line_cut: true`, and `next_offset` stays on that line with `next_line_byte_offset`, so passing both back (`offset`, `line_byte_offset`) reads the rest of the line without skipping bytes. A truncated page returns `next_offset` and a `hint` ("Showing lines 1-200 of 5310. Continue with offset=201."). `raw: true` returns byte-exact text without numbers (`app.vfs.cat` defaults to it). Directories fail with `not_readable`; binary or non-UTF-8 content fails with `invalid_request` and a hint to use `xxd`, `base64` or `iconv`.

Paged collection files (`/tables/{table}/rows.ndjson`, `/channels/{channel}/messages/YYYY-MM-DD.md`, `/workspace/members.json`, stat metadata `paged_collection: true`) page by `cursor` instead: `limit` is a record count, records are returned raw and never split, `next_cursor` is always present (null on the last page). Using `offset` there, or `cursor` on an ordinary file, is `invalid_request`.

## Editing

- `vfs_edit`: `edits: [{old_text, new_text, replace_all?}]` apply in order to the result of the previous edit, all or nothing. Matching is the Phase 1 exact-match engine (`VfsTextPatch`): byte-exact, 0 matches is `invalid_request` ("Patch search text was not found"), more than one without `replace_all` is `ambiguous_match` with 1-based `lines`; failures name `context.edit_index`. With `version` from `vfs_read`, a stale base still applies when every `old_text` still matches exactly once (`stale_base: true`, `note`), else `conflict`. `dry_run: true` returns a unified `diff` and writes nothing. Otherwise the plan is reduced to one search/replace anchored on the smallest unique region covering every change and sent through `OpenCompanyVfs::patch()` with the version of the content the plan was computed on, so the locked compare-and-write, approvals and audit are the `vfs_patch` path. Text roots: `/docs`, `/files`, `/agents` identity and memory (and `/me` once mounted); records go to `vfs_patch`.
- `vfs_patch`: task and list item fields as flat JSON (`{"status":"completed"}`); table rows as `{"data":{"Column":"value"},"base":{"Column":"value you read"}}` on `/tables/{table}/rows/by-id/{row}.json`, where keys are column names or ids, only supplied columns are written (merged into the row as it is at write time), and `base` turns a concurrent change into `conflict` (`context.changed_fields`, `context.current`). Text paths also accept one `{"search","replace","replace_all"?}` or a `SEARCH/REPLACE` block. `version` is checked atomically; stale record patches are `conflict`.
- `vfs_write`: `mode` is `create` (default; fails if it exists), `overwrite` or `append`. Overwriting an existing object without `version` is `invalid_request` with the hint to read it first (`vfs_read` with `limit: 1` returns the version). `append` reads the full content and writes `current + content` anchored on the version it read, so a concurrent writer causes `conflict`, never a lost update; a missing file is created. The request is forwarded unchanged as the tool parameters, so approvals replay the same mode and an `idempotency_key` reaches the write path.
- Patches and appends always use full content reads up to `config('vfs.max_content_bytes')` (20 MB); above it they fail with `invalid_request`. Display reads may end with `[... truncated]`, and a redirect or `tee` that would persist that line fails instead.

## Commands

- Discover support with `help`, `help <command>`, `man <command>`, `<command> --help`, `which <command>`, `type <command>`, `command -v <command>`, `/tools/vfs/commands.json`, `/tools/vfs/commands/<command>.json` (or `/tools/vfs/commands/<command>/<command>.json`).
- `grep` is literal and `rg` is regex, in the shell and in Code Mode (`app.vfs.grep` defaults `regex: false`).
- Unquoted path globs expand `*`, `?` and character classes. Quoted globs stay literal. `$VAR`, command substitution, process substitution and host programs are unsupported.
- Redirection: `<` reads bounded stdin from a VFS path; `>`, `>>`, `1>`, `1>>` write stdout to any writable path (the same targets as `vfs_write`); `/dev/null` discards; `2>/dev/null` suppresses stderr; `2>&1` merges stderr into stdout.
- `jq` is a safe subset; binary-adjacent inspection uses bounded `xxd`, `strings`, `iconv`, `gzip`/`gunzip`/`zcat`/`zgrep`; `tar`, `zip`, `unzip` extract only under `/files` and require `vfs_write`.
- Mutating commands compose `vfs_exec` + `vfs_write` for the whole line (one approval covers the line). `rm` trashes files, documents, table rows, list items and your notes; `mv` moves/renames files and documents within one mount and restores from `<mount>/.trash/`; `mkdir` creates `/files` and `/docs` folders; `cp`, `touch`, `mktemp`, `tee`, `truncate`, `split`, `csplit` and non-symlink `ln` work on `/files`. Record operations also compose their domain tool. The shell `patch` command is rejected; use `vfs_edit` or `vfs_patch`.
- Recursive walks (`find`, `grep -r`, `rg`, `du`, `diff -r`, `vfs_rg`, `app.vfs.find`) starting at `/` skip the `/me` alias (its objects are walked under `/agents` and `/files`); `tree` and `ls -R` show `me/` without descending. Walks that start at `/me` work normally.

Command semantics are owned by `VfsCommandSpecRegistry` and documented per command in `/tools/vfs/commands*`.

## Budgets, truncation and cursors

`vfs_exec` accepts `maxEntries` (alias `limit`), `maxDepth`, `maxBytes`, `maxFiles`, `maxMatches` and `maxLineLength`; helpers use `limit` for page size. Integers are validated strictly (`invalid_request` names the field). When output is cut, `truncated` is true and the result says how to continue: `next_cursor` (pass it back as `cursor`), `next_offset` for `vfs_read`, or narrow the path. A truncated listing is not a complete count; the final `vfs_ls` page carries `total_count` and `count_is_exact: true`.

`vfs_exec` accepts `cursor` and forwards it to the command executor, which hands it to the first pageable command of the line (a listing or a paged collection file).

Listing mounts (`/docs`, `/files`, `/tasks`, `/lists`, `/agents`, `/channels`, `/tables`, `/approvals`, `/routines`) use mount-owned keyset pages: authorization happens before an entry consumes the visible `limit`, so a sparse page can be empty with `truncated: true` and a `next_cursor`; continue until it is null. The effective backend scan ceiling is `max(limit, maxFiles)`. Cursors are encrypted and bound to the normalized path, mount adapter, workspace and acting agent. Inserts and deletes before the cursor do not duplicate or skip rows; changes to an active sort field can move a row across the cursor boundary.

## Result offloading

Direct (model-facing) VFS results larger than `config('vfs.tool_result_max_bytes')` (100,000 bytes, about 25k tokens) are written in full to `/files/agents/{agent-slug}/.results/{run id or Y-m-d}/{tool}-{uuid}.txt` (`.json` when no single text field dominates) through the normal governed `OpenCompanyVfs::write()` path, and the reply carries a preview (`tool_result_preview_bytes`, 8,000), `offloaded_path`, `offloaded_bytes`, `truncated: true` and a hint to read it with `vfs_read`. If the agent's `vfs_write` is denied or approval-gated, or the write fails, nothing is written and no approval is created: the result is cut to the preview with `truncated: true` and a hint. `vfs_read` is never offloaded (it is the paging tool), and Code Mode callbacks receive full results (`VfsOffloadingTool` decorates only the model-facing tool list). The agent home folder is the existing file-permission convention; `/me/results` aliases the `.results` folder.

## Governance metadata

Each operation declares an `effect` (`read`, `write`, or `per_command` for `vfs_exec`, which Code Mode classifies per pipeline) and a governance `actionClass` (`read`, `reversible_edit`, `delete`, `structural`, `governance`; see `docs/architecture/governance-model.md`). `VfsOperationSpecRegistry::actionClassFor($toolSlug, $path, $command)` resolves the per-call class: agent identity and memory paths are `governance`, `rm` is `delete` (deletes move to trash, never purge), other writes are `reversible_edit`.

Every policy evaluation carries a class. Callers declare one (a shell line passes the strictest `VfsCommandSpec::actionClass()` of its commands; record operations pass `delete` for trash, `reversible_edit` for create/overwrite/move/restore and doc-folder `mkdir`, `governance` for agent identity and memory edits), and `VfsMutationPolicy` keeps the strictest of the declared class and the per-path classifier (`VfsActionClass::strictest`), so a declared class is a floor and never a downgrade. Enforcement is still today's tool permissions and approvals; the class is carried on the decision, the approval context and the audit event for the future policy engine.

## Paths in search and find output

One rule for `find`, `grep -r` / `rg` (shell), `vfs_rg` / `app.vfs.rg` / `app.vfs.grep` and `app.vfs.find`: output paths are readable friendly paths, the path under the requested root (`/docs/policies/travel/per-diem`, `/tables/leads/rows/by-id/<row>.json`). Friendly names are already unambiguous: two siblings with the same slug are listed as `name--1a2b3c4d`. Index hits are mapped to the same friendly path (documents, files, table rows and schemas); objects without one friendly path (tasks, list items, channel days, documents under system folders) keep their by-id path. Structured data always carries the stable by-id path too: `canonical_path` on every `vfs_rg` match, `result.canonical_paths` (display path => by-id path) on shell `grep -r` / `rg`, and `canonical_path` on find items. Visiting and de-duplication use the canonical path, so an object reachable by slug and by id is reported once. Table row index candidates are `/tables/by-id/<table>/rows/by-id/<row>.json` internally (table slugs may collide) and are displayed with the table's disambiguated friendly token.

`rows.ndjson` ends every record with a newline (an empty page is empty), so `wc -l` equals the record count and concatenated cursor pages stay valid NDJSON. Row JSON (`rows/by-id/*.json`, `rows.ndjson`, history views) keeps non-ASCII text unescaped (`Café`, not `Caf\u00e9`), so a text search for it matches.

## Mounts

The VFS is a set of mounts resolved by longest path prefix (`app/Domain/Vfs/OpenCompany/Mounts/`):

- `VfsMount` is the contract: `prefix()`, `description()` (its `ls /` line and README mount-map line), `isAlias()`, `list`, `read`, `entry` (direct stat), cursor adapters (`pageAdapter`/`page`, `readPageAdapter`/`readPage`), `write`, `patch`, `remove` (trash), `move` (within the mount and restore), `makeDirectory`, `searchCandidates`, plus `governsMutations()` and `mutationTools()`. `AbstractVfsMount` gives read-only defaults that throw the same `not_writable` errors the facade always used.
- `VfsMountRegistry` resolves whole-segment prefixes, longest first, so nested mounts such as `/files/drive` are possible; duplicate prefixes are rejected. Top-level mounts are listed in registration order. Nested mounts are routed by the registry but must be surfaced in their parent's listing by the parent mount.
- Built-in mounts (`/me`, `/agents`, `/docs`, `/files`, `/tables`, `/lists`, `/tasks`, `/channels`, `/tools`, `/routines`, `/approvals`, `/workspace`) are declared in `RegistersVfsBuiltinMounts` as `BuiltinVfsMount` adapters over the existing mount traits; behaviour and cursor adapter names are unchanged.
- Additional mounts register on the container singleton: `app(VfsMountRegistry::class)->register($mount)`.

Governance runs around the mount, never inside it, so a registered mount needs no governance code of its own: `OpenCompanyVfs` asserts the workspace binding, normalizes and rewrites `/me`, serves `.history/` and `/README.md`, applies idempotency and, for mounts whose `governsMutations()` is false, composes `mutationTools()` through `VfsMutationPolicy` before the mount runs (denial, one approval, replay). `AuditedOpenCompanyVfs` audits and emits events for every public operation. Built-in record mounts return `governsMutations() = true` because their record paths call the policy at a precise point (after eligibility and preflight, before the locked write); a mount that claims it must do the same. `VfsMountGovernanceTest` proves a test mount is listed, routed, workspace-checked, policy-gated and audited.

Planned mounts plug in here: `/files/drive/<connection>` (external storage, nested under `/files`), `/connections/<name>` (read-only MCP resources), `/skills` (progressive disclosure) and `/apps/<app>` (see `docs/architecture/vfs-agent-friendliness-audit-2026-11.md` §5.6).

## Code Mode

`app.vfs.*` in mruby (`code_exec`) calls the same tools with keyword arguments; snake_case keys map to the schema. `exec` is reserved in Ruby, so it is `app.vfs.shell`; `app.vfs.cat` is `read` with `raw: true`; `app.vfs.grep` is `rg` with `regex: false`. A result with `ok: false` raises a typed failure whose type is the VFS error code; an approval created by VFS policy stops the execution as approval-pending. `app.vfs.shell` calls whose pipeline mutates get a durable write receipt. See `resources/code-docs/vfs.md` (served by `code_read_doc("vfs")`).

## Discovery and generated artifacts

- `/tools/index.json` and `/tools/catalog.md`: the agent's effective tool surface (direct tools with approval state, Code Mode groups), from `VfsToolCatalogSource` (today ToolRegistry; later connections, MCP tool definitions and API manifests).
- `/tools/vfs/code.json`: Code Mode signatures and aliases. `/tools/vfs/openapi.json`: OpenAPI 3.1 description for a future CLI, MCP or HTTP transport (there is no HTTP route yet).
- `php artisan vfs:spec --format=openapi|markdown|code` prints an artifact; `php artisan vfs:spec --write-docs` regenerates the reference sections of this file and `resources/code-docs/vfs.md`. `VfsToolSurfaceTest` fails when they drift.
- The agent system prompt carries a short, byte-stable VFS orientation block (`VfsGuide::promptBlock()`) whenever the agent has any VFS tool.

## Readiness Checklist

- Static analysis: `./vendor/bin/phpstan analyse app/Domain/Vfs app/Agents/Tools/Vfs app/Console/Commands/VfsAtlasCommand.php --memory-limit=1G --no-progress`.
- Tool surface, envelopes, read/edit/write semantics, offloading, prompt block and spec drift: `php artisan test tests/Feature/Vfs/VfsToolSurfaceTest.php`.
- Capability policy, pagination, integrity, approval coverage, isolation and Code Mode: `VfsCapabilityTruthTest`, `VfsPaginationTest`, `VfsContentIntegrityTest`, `VfsApprovalCoverageTest`, `VfsWorkspaceIsolationTest`, `VfsCodeModeEffectsTest`, `VfsFeatureTest` under `tests/Feature/Vfs/`.
- Atlas smoke (local/testing only): `php artisan vfs:atlas "ls /tools/vfs"`, `php artisan vfs:atlas --primitive=read --arg=path=/tools/vfs.md`.
- Intentional limits: generated snapshots are not patchable, stderr redirection to files is rejected, symbolic links are recognized but not created, and extraction/OCR/semantic embeddings stay outside the VFS.

<!-- BEGIN GENERATED: php artisan vfs:spec --write-docs -->

## Reference

Generated from `app/Domain/Vfs/Spec/VfsOperationSpecRegistry.php`; edit the spec, then run `php artisan vfs:spec --write-docs`.

| Tool | Model-visible | Ruby | CLI verb | Effect | Action class | Permission |
|---|---|---|---|---|---|---|
| `vfs_exec` | yes | `app.vfs.shell` | `exec` | per_command | per_command | `vfs_exec` |
| `vfs_read` | yes | `app.vfs.read` | `read` | read | read | `vfs_exec` |
| `vfs_edit` | yes | `app.vfs.edit` | `edit` | write | per_path | `vfs_patch` |
| `vfs_write` | yes | `app.vfs.write` | `write` | write | per_path | `vfs_write` |
| `vfs_patch` | yes | `app.vfs.patch` | `patch` | write | per_path | `vfs_patch` |
| `vfs_stat` | Code Mode only | `app.vfs.stat` | `stat` | read | read | `vfs_exec` |
| `vfs_ls` | Code Mode only | `app.vfs.ls` | `ls` | read | read | `vfs_exec` |
| `vfs_find` | Code Mode only | `app.vfs.find` | `find` | read | read | `vfs_exec` |
| `vfs_rg` | Code Mode only | `app.vfs.rg` | `rg` | read | read | `vfs_exec` |
| `vfs_exists` | Code Mode only | `app.vfs.exists` | `exists` | read | read | `vfs_exec` |
| `vfs_mkdir` | Code Mode only | `app.vfs.mkdir` | `mkdir` | write | reversible_edit | `vfs_write` |
| `vfs_cp` | Code Mode only | `app.vfs.cp` | `cp` | write | reversible_edit | `vfs_write` |
| `vfs_mv` | Code Mode only | `app.vfs.mv` | `mv` | write | reversible_edit | `vfs_write` |
| `vfs_rm` | Code Mode only | `app.vfs.rm` | `rm` | write | delete | `vfs_write` |

### `vfs_exec`

Run a unix-like command in the OpenCompany virtual filesystem (not a host shell). Navigate and inspect with ls, tree, find, cat, head, tail, grep (literal), rg (regex), wc, stat, jq, diff and more; pipes, ;, &&, ||, globs and > or >> into writable files work. Mutations (mkdir, touch, tee, cp, mv, rm, >) also need vfs_write; rm moves to the trash. Returns {ok, exit_code, stdout, cwd}; when truncated is true, hint says how to continue (pass next_cursor as cursor, raise a budget, or narrow the path). Start with `cat /README.md` or `help`. Use vfs_read and vfs_edit for file content.

| Parameter | Type | Required | Default | Description |
|---|---|---|---|---|
| `command` | string | yes |  | Command line, e.g. `ls /docs`, `rg -n "refund" /docs`, `cat /tasks/counts.json \| jq .open`. |
| `cwd` | string | no | `/` | Virtual working directory. |
| `cursor` | string | no |  | next_cursor from a previous truncated result (same command) to continue the listing or paged file. |
| `responseFormat` | string (default\|structured) | no | `default` | structured adds parsed data (paths, matches, stat) next to stdout. |
| `maxEntries` | integer | no | `100` | Entries per listing command. Aliases: limit. |
| `maxDepth` | integer | no | `3` | Recursion depth for tree/find/recursive search. |
| `maxBytes` | integer | no | `100000` | Bytes read from one file. |
| `maxFiles` | integer | no | `100` | Files scanned by search commands. |
| `maxMatches` | integer | no | `100` | Matches returned by search commands. |
| `maxLineLength` | integer | no | `2000` | Characters per output line before it is shortened. |
| `includeCommandSpec` | boolean | no | `false` | Add the primary command spec under diagnostics.command_spec. |

Result: `ok` (false when the command line failed (error holds the reason)), `exit_code` (status of the last command (0 success)), `stdout` (command output, once), `cwd` (working directory after the command), `truncated` (present and true when output was cut), `hint` (with truncated: how to continue), `next_cursor` (pass back as cursor to continue), `error` ({code, message, context} when ok is false), `data` (responseFormat=structured only: parsed result), `offloaded_path` (set when the full output was saved to a file (see offloading)).

Permissions: read commands: `vfs_exec`; mutations (mkdir, touch, tee, cp, mv, rm, >, >>): `vfs_exec` + `vfs_write`.

Errors: `not_found`, `not_readable`, `not_writable`, `permission_denied`, `unsupported`, `approval_required`, `invalid_request`, `conflict`, `internal_error`.

- grep is literal and rg is regex, in the shell and in Code Mode.
- Record mutations (documents, rows, list items) also compose the same domain tool as vfs_write, vfs_rm and vfs_mv.
- No $VAR, command substitution, process substitution or host programs.
- A command that cannot run fails the whole line with error; a later group after ; or || still runs and exit_code reflects the last command.

```json
{"command": "ls /"}
{"command": "rg -n \"refund\" /docs"}
{"command": "find /docs -name \"*.md\" | head -20"}
{"command": "ls /tables/customers/rows/by-id", "cursor": "<next_cursor>"}
```

### `vfs_read`

Read a text file. Lines come numbered like cat -n ("     N<TAB>text"); the prefix is not part of the file. Page with offset (1-based line) and limit (lines, default 2000); paged collection files (rows.ndjson, channel day transcripts, members.json) page with cursor instead. Returns version (pass it to vfs_edit, vfs_patch or vfs_write), total_lines, and truncated with a next_offset hint. raw: true returns the text without line numbers. Directories and binary files fail; use vfs_exec ls or xxd.

| Parameter | Type | Required | Default | Description |
|---|---|---|---|---|
| `path` | string | yes |  | File path, e.g. /docs/handbook/onboarding or /files/report.md. |
| `offset` | integer | no | `1` | First line to return (1-based). |
| `line_byte_offset` | integer | no | `0` | Byte position inside line `offset` to start at; pass next_line_byte_offset to continue a line that was cut. |
| `limit` | integer | no | `2000` | Lines to return (records for paged collection files). |
| `cursor` | string | no |  | next_cursor from the previous page of a paged collection file. |
| `maxBytes` | integer | no | `50000` | Byte cap for the returned text. |
| `raw` | boolean | no | `false` | Return text without line numbers. |

Result: `path` (normalized path), `canonical_path` (stable path to reuse (by-id where available)), `content` (the requested lines (numbered unless raw)), `bytes` (byte length of content), `version` (version of exactly these bytes; null for generated files), `offset` (first line returned), `returned_lines` (lines returned), `total_lines` (lines in the whole file (ordinary files)), `truncated` (more content exists), `next_offset` (offset to continue with (ordinary files)), `line_cut` (true when the last line in content was cut at maxBytes), `next_line_byte_offset` (with line_cut: line_byte_offset to continue the cut line with), `next_cursor` (cursor to continue with, null on the last page (paged collection files)), `returned` (records returned (paged collection files)), `hint` (how to continue when truncated), `capabilities` (what you may do with the path (read, patch, write, ...)).

Permissions: all paths: `vfs_exec`.

Errors: `not_found`, `not_readable`, `permission_denied`, `invalid_request`, `internal_error`.

- version describes the bytes returned by this same read.
- Paged collection files return raw records; offset applies to ordinary files only.
- A line longer than maxBytes is cut at maxBytes on a character boundary: truncated is true, line_cut is true, and next_offset stays on that line with next_line_byte_offset; pass both back (offset, line_byte_offset) to read the rest of the line, or raise maxBytes.

```json
{"path": "/docs/handbook/onboarding"}
{"path": "/files/logs/app.log", "offset": 2001, "limit": 500}
{"path": "/tables/customers/rows.ndjson", "limit": 100, "cursor": "<next_cursor>"}
```

### `vfs_edit`

Edit a text file (docs, /files, agent identity and memory) by exact string replacement. edits: [{old_text, new_text, replace_all?}] apply in order, all or nothing. old_text must match exactly once (copy it from vfs_read without the line-number prefix) unless replace_all; otherwise nothing is written and the error is not found or ambiguous_match with line numbers. Pass version from vfs_read: if the file changed since, edits still apply when each old_text still matches exactly once (result.stale_base), else conflict. dry_run: true returns a unified diff without writing. Table rows and task/list fields use vfs_patch.

| Parameter | Type | Required | Default | Description |
|---|---|---|---|---|
| `path` | string | yes |  | Text file to edit. |
| `edits` | array | yes |  | Edits applied in order to the result of the previous edit. Items: old_text, new_text, replace_all?. |
| `version` | string | no |  | version from vfs_read; enables conflict detection. |
| `dry_run` | boolean | no | `false` | Return the unified diff without writing or requesting approval. |
| `idempotency_key` | string | no |  | Optional key; a retry with the same key returns the first result instead of applying twice. |

Result: `status` (updated, unchanged or dry_run), `path` (normalized path), `canonical_path` (stable path), `version` (new version (current version for dry_run)), `edits_applied` (number of edits), `replacements` (total replacements across edits), `stale_base` (true when applied against content newer than your version), `diff` (dry_run only: unified diff).

Permissions: /docs, /files: `vfs_patch`; /agents/{agent}/identity/*: `vfs_patch` + `update_agent_identity_file`; /agents/{agent}/memory/*: `vfs_patch`.

Errors: `not_found`, `not_readable`, `not_writable`, `permission_denied`, `unsupported`, `approval_required`, `invalid_request`, `conflict`, `ambiguous_match`, `internal_error`.

- Line numbers in ambiguous_match are 1-based start lines in the content as it was before that edit.
- A failing edit reports context.edit_index (0-based); no edit is written.
- Governed by the vfs_patch permission: vfs_edit has no separate capability toggle.

```json
{"path": "/docs/handbook/onboarding", "version": "<version>", "edits": [{"old_text": "within 14 days", "new_text": "within 30 days"}]}
{"path": "/files/notes.md", "edits": [{"old_text": "TODO", "new_text": "DONE", "replace_all": true}], "dry_run": true}
```

### `vfs_write`

Create, overwrite or append whole content where the path allows writes (stat lists the write capability): /files, documents (/docs/<folder>/<Title>.md), table rows (rows/new.json creates, rows/by-id/<id>.json overwrites, {"data":{...}}), list items (front-matter markdown; new.md in a project creates) and your /me/notes and /me/todo.md. mode: create (default, fails if it exists), overwrite (requires version from vfs_read when it exists), or append (adds to the end, creating the file if missing). For changes inside an existing file use vfs_edit; for record fields use vfs_patch.

| Parameter | Type | Required | Default | Description |
|---|---|---|---|---|
| `path` | string | yes |  | Path to write, e.g. /files/reports/q3.md. |
| `content` | string | yes |  | Text to write. |
| `mode` | string (create\|overwrite\|append) | no | `create` | create, overwrite or append. |
| `version` | string | no |  | version from vfs_read; required to overwrite an existing file, optional for append. |
| `idempotency_key` | string | no |  | Optional key; a retry with the same key returns the first result instead of applying twice. |

Result: `status` (created or updated), `path` (normalized path), `canonical_path` (stable path), `version` (new version), `bytes` (size after the write).

Permissions: /files: `vfs_write`; /docs (create): `vfs_write` + `create_document`; /docs (overwrite): `vfs_write` + `update_document`; /tables/{table}/rows/new.json: `vfs_write` + `add_table_row`; /tables/{table}/rows/by-id/*: `vfs_write` + `update_table_row`; /lists/projects/{project}/new.md: `vfs_write` + `create_list_item`; /lists items: `vfs_write` + `update_list_item`; /me/notes/*, /me/todo.md: `vfs_write`.

Errors: `not_found`, `not_writable`, `permission_denied`, `approval_required`, `invalid_request`, `conflict`, `internal_error`.

- append is version-checked against the content it read, so concurrent appends never lose data (one fails with conflict).
- Overwriting a document, row, list item or note requires the version from a read; /files keeps it optional.
- Every overwrite stores the previous content as a version under <path>/.history/.

```json
{"path": "/files/reports/q3.md", "content": "# Q3\n"}
{"path": "/files/reports/q3.md", "mode": "overwrite", "version": "<version>", "content": "# Q3 (final)\n"}
{"path": "/files/logs/run.log", "mode": "append", "content": "done\n"}
{"path": "/docs/reports/Weekly.md", "content": "# Weekly\n"}
{"path": "/tables/customers/rows/new.json", "content": "{\"data\":{\"Name\":\"Acme\"}}"}
```

### `vfs_patch`

Patch structured records. Task and list item fields: flat JSON, e.g. {"status":"completed"}. Table rows (/tables/{table}/rows/by-id/{id}.json): {"data":{"Column":"value"},"base":{"Column":"value you read"}}; send only changed columns; base fails the patch with conflict if those fields changed. A single text {"search","replace","replace_all"?} also works, but prefer vfs_edit for text. version (from vfs_read) is checked atomically; stale record patches fail with conflict. Table rows also need update_table_row and agent identity files update_agent_identity_file.

| Parameter | Type | Required | Default | Description |
|---|---|---|---|---|
| `path` | string | yes |  | Record path, preferably canonical (by-id). |
| `patch` | string | yes |  | JSON patch as a string: field map, {"data":...,"base":...} for rows, or {"search","replace"} for text. |
| `version` | string | no |  | version from vfs_read; stale versions fail with conflict for records. |
| `idempotency_key` | string | no |  | Optional key; a retry with the same key returns the first result instead of applying twice. |

Result: `status` (updated), `path` (normalized path), `canonical_path` (stable path), `version` (new version), `stale_base` (text patches only: applied against newer content).

Permissions: /tasks, /docs, /files, /me/notes: `vfs_patch`; /lists items: `vfs_patch` + `update_list_item`; /tables/{table}/rows/by-id/*: `vfs_patch` + `update_table_row`; /agents/{agent}/identity/*: `vfs_patch` + `update_agent_identity_file`.

Errors: `not_found`, `not_writable`, `permission_denied`, `unsupported`, `approval_required`, `invalid_request`, `conflict`, `ambiguous_match`, `internal_error`.

- Row keys are column names or column ids; only supplied columns are written, merged into the row as it is at write time.
- SEARCH/REPLACE blocks (<<<<<<< SEARCH / ======= / >>>>>>> REPLACE) are accepted for text paths.

```json
{"path": "/tasks/by-id/TASK_ID", "patch": "{\"status\":\"completed\"}", "version": "<version>"}
{"path": "/tables/customers/rows/by-id/ROW_ID.json", "patch": "{\"data\":{\"Status\":\"active\"},\"base\":{\"Status\":\"lead\"}}"}
```

### `vfs_stat`

Code Mode helper. Stat a path: type, version, capabilities, canonical path and metadata.

| Parameter | Type | Required | Default | Description |
|---|---|---|---|---|
| `path` | string | yes |  | Path to inspect. |

Result: `type` (file or directory), `version` (version token), `canonical_path` (stable path), `capabilities` (allowed actions), `metadata` (mutation_actions, mime_type, read_only_reason, ...).

Permissions: all paths: `vfs_exec`.

Errors: `not_found`, `not_readable`, `permission_denied`, `invalid_request`, `internal_error`.

### `vfs_ls`

Code Mode helper. List a directory as structured entries with cursor paging.

| Parameter | Type | Required | Default | Description |
|---|---|---|---|---|
| `path` | string | no | `/` | Directory to list. |
| `limit` | integer | no | `100` | Entries per page. Aliases: maxEntries. |
| `cursor` | string | no |  | next_cursor from the previous page. |
| `maxFiles` | integer | no | `100` | Backend scan ceiling when permissions hide entries (never below limit). |

Result: `items` (entries (name, path, type, canonical_path, version, capabilities)), `returned` (entries on this page), `truncated` (more pages exist), `next_cursor` (cursor for the next page), `total_count` (exact visible count on the final page, else null), `count_is_exact` (true only when total_count is exact).

Permissions: all paths: `vfs_exec`.

Errors: `not_found`, `not_readable`, `permission_denied`, `invalid_request`, `internal_error`.

- Counting: page until next_cursor is nil; total_count is exact on the final page.

### `vfs_find`

Code Mode helper. Find entries by name and type across bounded directory trees (resumable).

| Parameter | Type | Required | Default | Description |
|---|---|---|---|---|
| `paths` | array | no |  | Directories to search. |
| `name` | string | no | `*` | fnmatch name filter such as *.md. Aliases: glob. |
| `type` | string (file\|directory) | no |  | file or directory. |
| `limit` | integer | no | `100` | Entries per page. Aliases: maxEntries. |
| `cursor` | string | no |  | next_cursor from the previous page. |
| `maxDepth` | integer | no | `3` | Recursion depth. |
| `maxFiles` | integer | no | `100` | Entries scanned per page. |

Result: `items` (matching entries), `truncated` (more pages exist), `next_cursor` (cursor for the next page).

Permissions: all paths: `vfs_exec`.

Errors: `not_found`, `not_readable`, `permission_denied`, `invalid_request`, `internal_error`.

### `vfs_rg`

Code Mode helper. Search text across VFS paths (regex by default; app.vfs.grep is literal). Deterministic and permission-filtered, not semantic.

| Parameter | Type | Required | Default | Description |
|---|---|---|---|---|
| `pattern` | string | yes |  | Regex, or literal text with regex: false. Aliases: query. |
| `paths` | array | no |  | Paths to search. Aliases: path. |
| `regex` | boolean | no | `true` | Treat pattern as a regular expression. |
| `ignoreCase` | boolean | no | `false` | Case-insensitive. Aliases: caseInsensitive. |
| `maxMatches` | integer | no | `100` | Matches to return. |
| `maxFiles` | integer | no | `100` | Files to scan. |
| `maxDepth` | integer | no | `3` | Recursion depth. |

Result: `matches` (list of {path, line, text}), `count` (matches returned), `truncated` (budgets cut the search), `scanned` (files scanned).

Permissions: all paths: `vfs_exec`.

Errors: `not_found`, `not_readable`, `permission_denied`, `invalid_request`, `internal_error`.

### `vfs_exists`

Code Mode helper. Check whether a path exists without raising not_found.

| Parameter | Type | Required | Default | Description |
|---|---|---|---|---|
| `path` | string | yes |  | Path to check. |

Result: `exists` (boolean), `stat` (stat result when it exists).

Permissions: all paths: `vfs_exec`.

Errors: `not_found`, `not_readable`, `permission_denied`, `invalid_request`, `internal_error`.

### `vfs_mkdir`

Code Mode helper. Create a folder under /files or /docs, with missing parents (idempotent).

| Parameter | Type | Required | Default | Description |
|---|---|---|---|---|
| `path` | string | yes |  | Folder path under /files or /docs. |
| `idempotency_key` | string | no |  | Optional retry key; a repeat returns the first result. |

Result: `status` (operation result), `path` (affected path).

Permissions: /files: `vfs_write`; /docs: `vfs_write` + `create_document`.

Errors: `not_found`, `not_writable`, `permission_denied`, `approval_required`, `invalid_request`, `internal_error`.

### `vfs_cp`

Code Mode helper. Copy a writable file.

| Parameter | Type | Required | Default | Description |
|---|---|---|---|---|
| `source` | string | yes |  | Source path. Aliases: src. |
| `destination` | string | yes |  | Destination path or directory. Aliases: dst, dest. |

Result: `status` (operation result), `path` (affected path).

Permissions: /files: `vfs_write`.

Errors: `not_found`, `not_writable`, `permission_denied`, `approval_required`, `invalid_request`, `internal_error`.

### `vfs_mv`

Code Mode helper. Move or rename a file or document within its mount; restore a trashed object from <mount>/.trash/.

| Parameter | Type | Required | Default | Description |
|---|---|---|---|---|
| `source` | string | yes |  | Source path. Aliases: src. |
| `destination` | string | yes |  | Destination path or directory. Aliases: dst, dest. |
| `idempotency_key` | string | no |  | Optional retry key; a repeat returns the first result. |

Result: `status` (operation result), `path` (affected path).

Permissions: /files: `vfs_write`; /docs (move, rename): `vfs_write` + `update_document`; /files/.trash/* (restore): `vfs_write` + `delete_file`; /docs/.trash/* (restore): `vfs_write` + `delete_document`; /tables/{table}/rows/.trash/* (restore): `vfs_write` + `delete_table_row`; /lists/.trash/* (restore): `vfs_write` + `delete_list_item`.

Errors: `not_found`, `not_writable`, `permission_denied`, `approval_required`, `invalid_request`, `internal_error`.

### `vfs_rm`

Code Mode helper. Move a file, document, table row, list item or your note to the trash (never a hard delete; restore with mv).

| Parameter | Type | Required | Default | Description |
|---|---|---|---|---|
| `path` | string | yes |  | Path of the object to trash. |
| `idempotency_key` | string | no |  | Optional retry key; a repeat returns the first result. |

Result: `status` (operation result), `path` (affected path).

Permissions: /files: `vfs_write` + `delete_file`; /docs: `vfs_write` + `delete_document`; /tables/{table}/rows/by-id/*: `vfs_write` + `delete_table_row`; /lists items: `vfs_write` + `delete_list_item`; /me/notes/*, /me/todo.md: `vfs_write`.

Errors: `not_found`, `not_writable`, `permission_denied`, `approval_required`, `invalid_request`, `internal_error`.

### Error codes

| Code | Meaning and next step |
|---|---|
| `not_found` | The path does not exist or is not visible to you. Check the parent with ls; context may carry did_you_mean. |
| `not_readable` | The path exists but cannot be read as content (for example a directory); list it instead. |
| `not_writable` | The path is read-only or generated (snapshots, indexes, catalogs); write to a canonical writable path. |
| `permission_denied` | A required permission or scope is missing; context names it. Do not retry or work around it; tell the user what is needed. |
| `unsupported` | The command, flag, format or root is not supported; the message names an alternative. |
| `approval_required` | A gated permission created an approval request (context.approval_id). Not a failure: continue other work; the outcome is posted back when a human decides. Do not retry. |
| `invalid_request` | Malformed input: missing field, bad budget or cursor, text not found, binary content, or a missing version; context.hint says what to change. |
| `conflict` | The object changed since your read (context.current_version; rows add changed_fields). Read it again and re-apply your change. |
| `ambiguous_match` | old_text/search matched more than once (context.count, context.lines). Add surrounding text to make it unique or set replace_all. |
| `internal_error` | Unexpected server error; context.correlation_id identifies the log entry. Retrying the same call is unlikely to help. |

<!-- END GENERATED: vfs:spec -->
