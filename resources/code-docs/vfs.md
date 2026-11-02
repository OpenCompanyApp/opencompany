# VFS

The `app.vfs` namespace exposes OpenCompany's permission-aware virtual
filesystem inside Code Mode. It is the same implementation used by the direct
`vfs_exec`, `vfs_patch`, and `vfs_write` tools. Use keyword arguments; snake_case
keys map to the tool schema (`max_matches:` becomes `maxMatches`).

Use one direct `vfs_exec` call for a quick look. Use Code Mode when you need
several reads, a loop over pages, or a combined summary in one step.

## Command-shaped inspection

`exec` is a reserved name in the Ruby profile, so the `vfs_exec` primitive is
called as `app.vfs.shell(command: ...)` (or `app.call("vfs.exec", command: ...)`).

```ruby
root = app.vfs.shell(command: "ls /")
refunds = app.vfs.shell(command: "rg refund /docs", max_matches: 20)
counts = app.vfs.shell(command: "cat /tasks/counts.json | jq .open")
{root: root[:stdout], refunds: refunds[:stdout], open: counts[:stdout]}
```

## Structured helpers

Prefer structured helpers over parsing command output:

```ruby
docs = app.vfs.ls(path: "/docs", limit: 20)
stat = app.vfs.stat(path: "/docs/by-id/DOC_ID")
exists = app.vfs.exists(path: "/docs/by-id/DOC_ID")
text = app.vfs.read(path: "/docs/by-id/DOC_ID", max_bytes: 4000)
hits = app.vfs.rg(pattern: "refund", paths: ["/docs", "/files"], max_matches: 20, max_files: 100)
lexical = app.vfs.search(query: "refund policy", paths: ["/docs"], max_matches: 20)
found = app.vfs.find(paths: ["/docs"], name: "*.md", type: "file", max_depth: 3, limit: 50)
count = app.vfs.count(path: "/tasks/by-status/open", limit: 100)

{
  docs: docs[:result][:items].map { |item| item[:path] },
  matches: hits[:result][:matches].length,
  found: found[:result][:count],
}
```

Every helper returns the same envelope as the direct tool: a hash with `ok`,
`result`, and on failure `error`. Read the canonical payload from
`res[:result]`. List-like results are `res[:result][:items]` (ls, find) or
`res[:result][:matches]` (rg, grep, search).

`cat` is an alias for `read`, and `grep` is an alias for `rg` with literal
matching. `grep` is literal by default; `rg` is regex by default.

## Paging with cursors

Directory-style helpers return `returned`, `limit`, `truncated`, and
`next_cursor`. Pass `cursor:` back to continue `ls`, `find`, and `count`.
`read` uses the same shape for channel transcripts, table-row NDJSON exports,
and `/workspace/members.json`. Always cap the loop yourself:

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
retry with the `error[:context][:minimum_bytes]` reported by `read`.

Directory counts are sampled unless `count_is_exact` is true. Treat `count`,
`returned`, and `sampled_count` as the page inspected, not a guaranteed total.
`total_count` is `nil` when the mount cannot prove a true count cheaply.

## Edits and writes

`stat(path:)[:result][:metadata][:mutation_actions]` describes `patch` and
`write` independently: required tools and whether permission is `allowed`,
`requires_approval`, or `denied`.

```ruby
app.vfs.patch(path: "/docs/by-id/DOC_ID", patch: '{"search":"old text","replace":"new text"}')
app.vfs.patch(path: "/tasks/by-id/TASK_ID", patch: '{"status":"completed","priority":"high"}')
app.vfs.patch(path: "/lists/by-id/ITEM_ID", patch: '{"status":"in_progress"}')
```

`/docs` patching is stricter than reading: the document must be inside an
explicitly allowed document folder and `stat` must list the `patch` capability.

Use `write` only where raw file writes are valid (`/files`):

```ruby
app.vfs.mkdir(path: "/files/generated")
app.vfs.write(path: "/files/generated/report.md", content: "# Report\n\n...", mode: "overwrite")
app.vfs.cp(source: "/files/generated/report.md", destination: "/files/generated/report-copy.md")
app.vfs.mv(source: "/files/generated/report-copy.md", destination: "/files/generated/archive/report.md")
app.vfs.rm(path: "/files/generated/archive/report.md")
```

`write` defaults to create-only. Pass `mode: "create"` or `mode: "overwrite"`
explicitly. `mkdir` is idempotent for existing directories.

Writes are external effects. Inside a run, Code Mode records a receipt for each
write; if a script fails after a write, do not re-run it blindly. Read state
first, then write only what is missing.

## Boundaries

- VFS commands are not host shell commands; unsupported commands fail with
  structured errors.
- All paths are workspace-scoped and permission-checked. `app.vfs.*` helpers
  use the primitive permissions (`vfs_exec` for reads, `vfs_write` for writes,
  `vfs_patch` for patches) and never bypass capability settings.
- Domain-backed writes compose permissions: table rows need
  `vfs_patch + update_table_row`; agent identity files need
  `vfs_patch + update_agent_identity_file`.
- If a composed permission requires approval, the helper returns the same
  approval-required envelope as the direct tool, a normal approval request is
  created, and Code Mode stops further callbacks in that execution.
- Broad folders (tasks, channels, tables, files) are budgeted. Prefer narrower
  mounts such as `/tasks/by-agent`, `/tasks/by-status`, and date-bucketed
  channel paths.
- Semantic search and embeddings are separate retrieval tools. Use VFS to
  verify exact source content after retrieval returns a path.
- The shell parser supports globs, pipes, `;`, `&&`, `||`, stdout redirection
  into `/files`, `2>/dev/null`, and `2>&1`. It does not support `$VAR`, command
  substitution, process substitution, or arbitrary programs.
- `jq` is a safe subset: `.`, `.field`, `.[0]`, `.items[]`, `.["key"]`,
  `length`, `keys`, `has("key")`, `map(.field)`, `select(.field == "value")`,
  and simple arithmetic. Unsupported expressions fail.

Discovery files are also in the VFS: `/tools/vfs/commands.json`,
`/tools/vfs/examples.md`, and `/tools/vfs/code.json`.
