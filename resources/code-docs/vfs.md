# VFS

The `app.vfs` namespace exposes OpenCompany's permission-aware virtual
filesystem inside Code Mode. It is the same implementation as the direct
`vfs_exec`, `vfs_read`, `vfs_edit`, `vfs_write` and `vfs_patch` tools. Use
keyword arguments; snake_case keys map to the tool schema (`max_matches:`
becomes `maxMatches`). Positional arguments bind in the signature order listed
in the reference below.

Use one direct tool call for a quick look. Use Code Mode when you need several
reads, a loop over pages, or a combined summary in one step.

## Command-shaped inspection

`exec` is a reserved name in the Ruby profile, so the `vfs_exec` primitive is
called as `app.vfs.shell(command: ...)` (or `app.call("vfs.exec", command: ...)`).
It returns `{ok:, exit_code:, stdout:, cwd:}`; `stdout` appears once.

```ruby
root = app.vfs.shell(command: "ls /")
refunds = app.vfs.shell(command: "rg -n refund /docs", max_matches: 20)
counts = app.vfs.shell(command: "cat /tasks/counts.json | jq .open")
{root: root[:stdout], refunds: refunds[:stdout], open: counts[:stdout]}
```

## Structured helpers

Prefer structured helpers over parsing command output:

```ruby
docs = app.vfs.ls(path: "/docs", limit: 20)
stat = app.vfs.stat(path: "/docs/by-id/DOC_ID")
exists = app.vfs.exists(path: "/docs/by-id/DOC_ID")
page = app.vfs.read(path: "/docs/by-id/DOC_ID", limit: 200)       # numbered lines + version
text = app.vfs.cat(path: "/docs/by-id/DOC_ID")[:result][:content]  # raw text
hits = app.vfs.rg(pattern: "refund", paths: ["/docs", "/files"], max_matches: 20, max_files: 100)
literal = app.vfs.grep(pattern: "a.b", paths: ["/files"])           # literal match
found = app.vfs.find(paths: ["/docs"], name: "*refund*", type: "file", max_depth: 3, limit: 50)

{
  docs: docs[:result][:items].map { |item| item[:path] },
  matches: hits[:result][:matches].length,
  found: found[:result][:count],
}
```

Every helper returns a hash with `ok` and `result`. Read the payload from
`res[:result]`. A failure (`ok: false`) raises instead: rescue it, and branch on
the VFS error code at the start of the message (`not_found`, `conflict`,
`ambiguous_match`, ...), which is followed by the error context as JSON.
List-like results are `res[:result][:items]` (ls, find) or
`res[:result][:matches]` (rg, grep).

`read` numbers lines (`"     1\tfirst line\n"`) and returns `version`,
`total_lines`, `truncated` and `next_offset`; pass `raw: true` (or use `cat`)
for the text itself.

## Paging with cursors

`ls` and `find` return `truncated` and `next_cursor`; pass `cursor:` back to
continue. The final `ls` page carries `total_count` and `count_is_exact: true`.
`read` pages ordinary files with `offset:` and collection files (channel
transcripts, table-row NDJSON, `/workspace/members.json`) with `cursor:`.
Always cap the loop yourself:

```ruby
path = "/channels/by-id/CHANNEL_ID/messages/2026-08-09.md"
page = app.vfs.read(path: path, limit: 100)
chunks = [page[:result][:content]]
pages = 1
while page[:result][:next_cursor] && pages < 5
  page = app.vfs.read(path: path, limit: 100, cursor: page[:result][:next_cursor])
  chunks << page[:result][:content]
  pages += 1
end
{pages: pages, more: !page[:result][:next_cursor].nil?, bytes: chunks.join.length}
```

Records are never split across pages. If one record cannot fit an empty page,
`read` raises `invalid_request`; retry with the `minimum_bytes` in its message.

## Edits and writes

```ruby
page = app.vfs.read(path: "/docs/by-id/DOC_ID", limit: 1)
v = page[:result][:version]
app.vfs.edit(path: "/docs/by-id/DOC_ID", version: v,
             edits: [{old_text: "old text", new_text: "new text"}])
preview = app.vfs.edit(path: "/docs/by-id/DOC_ID", dry_run: true,
                       edits: [{old_text: "TODO", new_text: "DONE", replace_all: true}])
preview[:result][:diff]
app.vfs.patch(path: "/tasks/by-id/TASK_ID", patch: '{"status":"completed","priority":"high"}')
app.vfs.patch(path: "/tables/customers/rows/by-id/ROW_ID.json",
              patch: '{"data":{"Status":"active"},"base":{"Status":"lead"}}')
```

`edit` applies every edit or none; `old_text` must match exactly once unless
`replace_all: true`. A stale `version` still applies when each `old_text`
still matches exactly once (`stale_base: true`); otherwise it raises
`conflict`. For table rows send only changed columns; `base` holds the values
you read.

Use `write` where raw file writes are valid (`/files`):

```ruby
app.vfs.mkdir(path: "/files/generated")
app.vfs.write(path: "/files/generated/report.md", content: "# Report\n\n...", mode: "create")
v = app.vfs.read(path: "/files/generated/report.md", limit: 1)[:result][:version]
app.vfs.write(path: "/files/generated/report.md", content: "# Report v2\n", mode: "overwrite", version: v)
app.vfs.write(path: "/files/generated/log.md", content: "step done\n", mode: "append")
app.vfs.cp(source: "/files/generated/report.md", destination: "/files/generated/report-copy.md")
app.vfs.mv(source: "/files/generated/report-copy.md", destination: "/files/generated/archive/report.md")
app.vfs.rm(path: "/files/generated/archive/report.md")
```

`write` defaults to `create`. `overwrite` of an existing file needs its
`version`. `mkdir` is idempotent for existing directories.

Writes are external effects. Inside a run, Code Mode records a receipt for each
write, including `shell` commands whose pipeline mutates (`rm`, `mv`, `tee`,
`>` ...); if a script fails after a write, do not re-run it blindly. Read state
first, then write only what is missing.

## Boundaries

- VFS commands are not host shell commands; unsupported commands fail with
  structured errors.
- All paths are workspace-scoped and permission-checked. Reads run under
  `vfs_exec`, `edit`/`patch` under `vfs_patch`, and `write`/`mkdir`/`cp`/`mv`/`rm`
  under `vfs_write`; they never bypass capability settings.
- Domain-backed writes compose permissions: table rows need
  `vfs_patch + update_table_row`; agent identity files need
  `vfs_patch + update_agent_identity_file`.
- If a composed permission requires approval, a normal approval request is
  created (one approval covers every gated permission of the call), the
  execution stops as approval-pending, and no further callbacks run in it,
  even if the script rescues the failure.
- Broad folders (tasks, channels, tables, files) are budgeted. Prefer narrower
  mounts such as `/tasks/by-agent`, `/tasks/by-status`, and date-bucketed
  channel paths.
- Semantic search and embeddings are separate retrieval tools. Use VFS to
  verify exact source content after retrieval returns a path.

Discovery files are also in the VFS: `/tools/vfs/code.json`,
`/tools/vfs/examples.md`, `/tools/vfs/reference.md` and
`/tools/vfs/commands.json`.

<!-- BEGIN GENERATED: php artisan vfs:spec --write-docs -->

## Reference

Generated from `app/Domain/Vfs/Spec/VfsOperationSpecRegistry.php`; edit the spec, then run `php artisan vfs:spec --write-docs`.

| Ruby | Tool | Effect | Summary |
|---|---|---|---|
| `app.vfs.shell(command:, cwd:, cursor:, response_format:, max_entries:, max_depth:, max_bytes:, max_files:, max_matches:, max_line_length:, include_command_spec:)` | `vfs_exec` | per_command | Run unix-like commands over the virtual filesystem (ls, find, rg, cat, jq, ...). |
| `app.vfs.read(path:, offset:, limit:, cursor:, max_bytes:, raw:)` | `vfs_read` | read | Read a text file with line numbers, paging and its version. |
| `app.vfs.edit(path:, edits:, version:, dry_run:)` | `vfs_edit` | write | Edit text files by exact replacement: several edits, all or nothing, with dry-run diff. |
| `app.vfs.write(path:, content:, mode:, version:)` | `vfs_write` | write | Create, overwrite or append whole text content where a path allows writes. |
| `app.vfs.patch(path:, patch:, version:)` | `vfs_patch` | write | Patch structured records: task/list fields and table rows, version-checked. |
| `app.vfs.stat(path:)` | `vfs_stat` | read | Stat a path: type, version, capabilities, canonical path and metadata. |
| `app.vfs.ls(path:, limit:, cursor:, max_files:)` | `vfs_ls` | read | List a directory as structured entries with cursor paging. |
| `app.vfs.find(paths:, name:, type:, limit:, cursor:, max_depth:, max_files:)` | `vfs_find` | read | Find entries by name and type across bounded directory trees (resumable). |
| `app.vfs.rg(pattern:, paths:, regex:, ignore_case:, max_matches:, max_files:, max_depth:)` | `vfs_rg` | read | Search text across VFS paths (regex by default; app.vfs.grep is literal). Deterministic and permission-filtered, not semantic. |
| `app.vfs.exists(path:)` | `vfs_exists` | read | Check whether a path exists without raising not_found. |
| `app.vfs.mkdir(path:)` | `vfs_mkdir` | write | Create a writable directory (idempotent). |
| `app.vfs.cp(source:, destination:)` | `vfs_cp` | write | Copy a writable file. |
| `app.vfs.mv(source:, destination:)` | `vfs_mv` | write | Move or rename a writable file. |
| `app.vfs.rm(path:)` | `vfs_rm` | write | Delete a writable file. |

Aliases:

- `app.vfs.shell` = `app.vfs.exec`
- `app.vfs.cat` = `app.vfs.read` with `raw: true`
- `app.vfs.grep` = `app.vfs.rg` with `regex: false`

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
