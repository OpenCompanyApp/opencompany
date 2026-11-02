# VFS Runtime Reference

The runtime also exposes this reference at `/tools/vfs.md` and `/tools/vfs/README.md` inside the virtual filesystem.

The VFS is a permission-aware unix-like filesystem over OpenCompany workspace data. Use `vfs_exec` for command-shaped inspection, `vfs_patch` for targeted edits, `vfs_write` for raw `/files` writes, and `app.vfs.*` Lua helpers for structured workflows.

VFS is an interface over existing OpenCompany permissions, not a second permission system. `/docs` patching requires an explicit allowed document-folder scope. Table row patching requires both `vfs_patch` and the normal `update_table_row` tool permission. Agent identity patching requires `vfs_patch` plus the normal `update_agent_identity_file` permission. Agent memory files under `/agents` use relationship-aware rules: agents can patch their own identity and memory files, managers can patch direct report identity files and direct report `memory/MEMORY.md`, and broader memory folders stay self-owned. Read visibility alone does not imply patch capability.

If any composed permission requires approval, VFS creates a normal approval request and stores the original VFS operation for approved replay. One approval covers every approval-gated permission the operation needs (for example `vfs_patch` + `update_table_row`, or `vfs_exec` + `vfs_write`), and retrying an identical call while it is pending returns the same approval id. Denied permissions still block immediately, and approved replay revalidates permissions before mutating.

Every entry point requires the bound workspace to be the acting agent's workspace; otherwise it fails with `permission_denied` (`context.reason: workspace_binding_mismatch`).

## Commands

- Discover support with `help`, `help <command>`, `man <command>`, `<command> --help`, `which <command>`, `type <command>`, `command -v <command>`, `/tools/vfs/commands.json`, and `/tools/vfs/commands/<command>/<command>.json`.
- `grep` is literal; `rg` is regex.
- Unquoted path globs expand with normal `*`, `?`, and character classes against one parent directory. Quoted globs stay literal. `**`, brace expansion, `$VAR`, command substitution, process substitution, and host programs are intentionally unsupported.
- Redirection support is explicit: `<` reads bounded stdin from a VFS path; `>`, `>>`, `1>`, and `1>>` write stdout to `/files`; `/dev/null` discards stdout; `2>/dev/null` suppresses stderr; `2>&1` merges stderr into stdout with shell-like order sensitivity. Stderr file targets such as `2>/files/error.txt`, heredocs, and process substitution are rejected.
- `jq` is a safe subset: `.`, `.field`, `.[0]`, `.items[]`, `.items[].name`, `.["key"]`, `length`, sorted `keys`, `has("key")`, `map(.field)`, `select(.field == "value")`, simple numeric arithmetic, stdin, NDJSON, and file arguments. `-r` is accepted; scalar output is raw by default. `-c` emits compact JSON.
- Safe binary-adjacent inspection is available through bounded text APIs: `xxd` supports hex dump/plain hex/reverse plain hex, `strings` extracts printable runs, `iconv` converts text encodings, and `gzip`/`gunzip`/`zcat`/`zgrep` support compressed-text pipelines.
- `tar`, `zip`, and `unzip` support bounded archive create/list/print/extract flows. Archive extraction writes only under `/files`, rejects unsafe paths, and requires `vfs_write`.
- `cp`, `mv`, `rm`, `mkdir`, `touch`, `mktemp`, `tee`, `truncate`, `split`, `csplit`, and non-symlink `ln` mutate `/files` only and require `vfs_write` permission. `ln -s` is recognized but rejected until VFS has first-class symlink entries.

## Patching

- Use `vfs_patch` for targeted edits; the shell `patch` command is intentionally rejected.
- Text-backed paths use JSON `{"search":"old","replace":"new"}` (optionally `"replace_all": true`) or a SEARCH/REPLACE block. The search text must match exactly once: no match is `invalid_request` ("Patch search text was not found in {path}"), several matches are `ambiguous_match` with `context.count` and 1-based `context.lines`, unless `replace_all` is set. Patches always edit the full stored content, never a truncated read.
- `version` is optional; when given it is checked atomically with the write. A stale version is `conflict` (`context.path`, `current_version`, `given_version`, `hint`). For text patches only, a stale version still applies when the search text matches exactly once in the current content; the result then carries `stale_base: true` and a `note`.
- Table row paths use `{"data": {...}}` on `/tables/{table}/rows/by-id/{row}.json`; keys must be existing column names or UUIDs. Only the supplied columns are written, merged into the row as it is at write time. Add `"base": {"Column": "value you read"}` to reject the patch with `conflict` (`context.changed_fields`, `context.current`) if any of those fields changed.
- `vfs_stat` and `vfs_read` expose `capabilities` and mutation metadata so agents can see whether a path is patchable, denied, or approval-gated before attempting a write. A mutation capability is present only when the exact path is domain-eligible and its required tools are allowed or approval-gated.
- `metadata.mutation_actions.{patch|write}` contains action-specific `required_tools`, `permission`, `requires_approval`, `approval_tool` or `denied_tool`, an optional `denied_reason`, and a usage `hint`. This matters for `/files`, where targeted patch and raw write permissions are independent. Single-action entries also retain the flat `mutation_required_tools`, `mutation_permission`, `mutation_approval_tool`, `mutation_denied_tool`, `mutation_denied_reason`, and `mutation_hint` fields.

## Budgets

`vfs_exec` accepts `maxEntries`, `maxDepth`, `maxBytes`, `maxFiles`, and `maxMatches`. Structured helpers accept camelCase and snake_case aliases. Treat returned counts as sampled unless `count_is_exact` is true; inspect `returned`, `limit`, `truncated`, `skipped`, `count_mode`, `sampled_count`, and `total_count`. `vfs_ls`, `vfs_find`, and `vfs_count` return `next_cursor` when more bounded results are available. `vfs_read` does the same for `/channels/{channel}/messages/YYYY-MM-DD.md`, `/tables/{table}/rows.ndjson`, and `/workspace/members.json`. Pass `next_cursor` back as `cursor` to continue.

All database-backed listing mounts—`/docs`, `/files`, `/tasks`, `/lists`, `/agents`, `/channels`, `/tables`, `/approvals`, and `/automations`—use mount-owned keyset pages. Documents and files keep folder-first title/name order and authorize each backend row before it consumes the visible `limit`. Tasks, list items, and table rows keep `updated_at DESC, id DESC`; approvals and automations use `created_at DESC, id DESC`; label indexes use `name, id`; saved table views use `created_at, id`; channel day buckets use the distinct day descending. Workspace, permission, relationship, status, agent, assignee, parent, channel, and table predicates remain inside every page query. Only finite synthetic directories retain the bounded compatibility adapter.

The effective backend scan ceiling is `max(limit, maxFiles)`; a sparse permission scope can therefore return zero visible items with `truncated: true` and a non-null `next_cursor`. Continue until `next_cursor` is null. On the final `vfs_count` page, `total_count` is the cumulative visible count and `count_is_exact` is true. Structured `vfs_find` stores resumable depth-first frames and nested mount cursors, so it does not replay prior offsets. One-shot shell traversal, recursive listing, archive, metadata, and glob consumers drain mount pages only up to their explicit operation budgets.

Those cursors are encrypted and bound to the normalized path, mount adapter, workspace, and acting agent. They cannot be reused for another path or principal. Inserts and deletes before the current keyset do not cause offset duplication or skipped existing rows. Changes to an active sort field—including document/file names and task/list `updated_at`—can move a row across the cursor boundary; start a new traversal when a strict current-state view is required during concurrent updates.

The three generated collection files above return independently valid transcript, NDJSON, or JSON chunks, expose `metadata.paged_collection: true`, and never exceed `maxBytes`. Records are admitted whole before their keyset is committed, so a byte boundary cannot corrupt a record or make continuation skip it. If the next complete record cannot fit on an otherwise empty page, `vfs_read` returns `invalid_request` with `context.minimum_bytes`, `context.record_id`, and a retry suggestion. `/workspace/members.json` also reserves the bytes required for its valid JSON envelope. Other budget-limited generated JSON and Markdown can still be truncated at the final returned string boundary; when such a non-paged response reports `truncated`, request a larger `maxBytes` budget before parsing it as a complete machine-readable document.

Write-back paths (`vfs_patch`, `>>`, `tee -a`, archive create/extract, `split`) read full content, never the budgeted display read, up to `config('vfs.max_content_bytes')` (20 MB default); above it they fail with `invalid_request`. Display reads may still end with `[... truncated]`, and a redirect or `tee` that would persist that line fails instead.

## Readiness Checklist

- Broad static-analysis gate: `./vendor/bin/phpstan analyse app/Domain/Vfs/OpenCompany app/Agents/Tools/Vfs app/Console/Commands/VfsAtlasCommand.php --memory-limit=1G --no-progress`.
- Capability-policy gate: `php artisan test tests/Feature/Vfs/VfsCapabilityTruthTest.php`.
- Permission-correct pagination gate: `php artisan test tests/Feature/Vfs/VfsPaginationTest.php`.
- Feature gate: `php artisan test tests/Feature/Vfs/VfsFeatureTest.php`.
- Integrity, approval, isolation and Code Mode gates: `VfsContentIntegrityTest`, `VfsApprovalCoverageTest`, `VfsWorkspaceIsolationTest`, `VfsCodeModeEffectsTest` under `tests/Feature/Vfs/`.
- Capability-truth gate: representative entries across `/docs`, `/files`, `/agents`, `/tasks`, `/lists`, `/tables`, `/tools`, and `/workspace` assert that advertised `patch` and `write` capabilities execute on the exact visible path, while generated/read-only entries expose `metadata.read_only_reason` and no mutation capabilities.
- Atlas smoke gate (local/testing only; resolves the agent inside `--workspace` or the bound workspace and dispatches through the runtime's approval wrapping): `php artisan vfs:atlas` covers root browse, command help/specs, primitive stat/read, generated read truncation, display and canonical `/files` writes, read-only write rejection, unsupported-command help, and `&&`/redirection behavior.
- Intentional limits: generated snapshots are not patchable, stderr redirection to files is rejected, symbolic links are recognized but not created, and extraction/OCR/semantic embeddings remain outside VFS.

## Lua

Code Mode (`app.vfs.*` in mruby): a helper result with `ok: false` raises a typed failure whose type is the VFS error code (for example `not_found`, `conflict`); an approval created by VFS policy stops the execution as approval-pending, like any gated callback. `app.vfs.shell` calls whose pipeline mutates get a durable write receipt. `app.vfs.grep` is literal (`regex: false` unless passed). The legacy Lua notes below are kept until the docs rewrite: Lua helpers returned `{ ok, result = ... }`, mirror result fields at the top level, and expose list-like results as numeric entries so `#res` works. `app.vfs.exec(command, { response_format = "structured", include_command_spec = true })` keeps stdout text but enriches `data` and diagnostics for programmatic workflows. `app.vfs.write(path, content)` defaults to overwrite for script ergonomics; direct `vfs_write` defaults to create. Generated code should pass `mode = "create"` or `mode = "overwrite"` explicitly.
