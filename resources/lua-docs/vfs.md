# VFS

The `vfs` namespace exposes OpenCompany's permission-aware virtual filesystem.
It is the same implementation used by the direct `vfs_exec`, `vfs_patch`, and
`vfs_write` tools.

Use VFS for deterministic workspace inspection:

```lua
local root = app.vfs.exec({ command = "ls /" })
local docs = app.vfs.exec({ command = "rg refund /docs", max_matches = 20 })
local file = app.vfs.exec({ command = "cat /tasks/counts.json" })
local chain = app.vfs.exec({ command = "cd /docs && find . -name '*.md' | xargs grep -n refund" })
```

For scripts, prefer structured helpers over parsing command output:

```lua
local docs = app.vfs.ls("/docs", { limit = 20 })
local stat = app.vfs.stat("/docs/by-id/...")
local exists = app.vfs.exists("/docs/by-id/...")
local text = app.vfs.read("/docs/by-id/...", { max_bytes = 4000 })
local hits = app.vfs.rg("refund", { "/docs", "/files" }, {
  max_matches = 20,
  max_files = 100,
})
local found = app.vfs.find({ "/docs" }, {
  name = "*.md",
  type = "file",
  max_depth = 3,
  limit = 50,
})
if found.next_cursor then
  local next_page = app.vfs.find({ "/docs" }, {
    name = "*.md",
    type = "file",
    limit = 50,
    cursor = found.next_cursor,
  })
end
local lexical = app.vfs.search("refund policy", { "/docs" }, {
  max_matches = 20,
})
local count = app.vfs.count("/tasks/by-status/open", { limit = 100 })
```

All VFS Lua helpers return the same tool envelope:

```lua
local res = app.vfs.ls("/docs")
if res.ok then
  local items = res.result.items
  local also_items = res.items
  local countable = #res
end
```

Use `res.result.*` for the canonical payload. For ergonomics, VFS Lua helpers
also mirror payload fields such as `version`, `content`, `items`, `matches`,
`count`, `returned`, `limit`, `truncated`, `skipped`, `count_mode`,
`count_is_exact`, `sampled_count`, `total_count`, and `next_cursor` onto
`res.*`. List-like helpers expose numeric entries too, so `#res` works for
`ls`, `find`, `rg`, and `search`.

Use `patch` for targeted edits:

```lua
app.vfs.patch({
  path = "/docs/by-id/...",
  patch = '{"search":"old text","replace":"new text"}',
  version = "optional-version-from-stat"
})
```

`/docs` patching is stricter than `/docs` reading: the target document must be
inside an explicitly allowed document folder and `stat(path).capabilities` must
include `patch`. Unrestricted read visibility does not imply generic document
write access.

Task and list projections support limited structured patches for status and
basic fields:

```lua
app.vfs.patch("/tasks/by-id/...", '{"status":"completed","priority":"high"}')
app.vfs.patch("/lists/by-id/...", '{"status":"in_progress","priority":"urgent"}')
```

Use `write` only where raw file-like writes are valid:

```lua
app.vfs.mkdir("/files/generated")
app.vfs.write("/files/generated/report.md", "# Report\n\n...")
app.vfs.cp("/files/generated/report.md", "/files/generated/report-copy.md")
app.vfs.mv("/files/generated/report-copy.md", "/files/generated/archive/report.md")
app.vfs.rm("/files/generated/archive/report.md")
```

`app.vfs.write(path, content)` and `app.vfs.write({ path = ..., content = ... })`
default to `mode = "overwrite"` for natural scripting. Pass
`{ mode = "create" }` when the script wants create-only behavior.
`app.vfs.cp` and `app.vfs.mv` accept `source`/`destination`, `src`/`dst`, and
`src`/`dest`.

Important boundaries:

- VFS commands are not host shell commands.
- Unsupported shell commands fail with structured errors.
- All paths are workspace-scoped and permission-checked.
- `app.vfs.*` helpers use the same primitive VFS permissions as `vfs_exec`,
  `vfs_patch`, and `vfs_write`; they do not bypass capability settings.
- `/docs` entries advertise `patch` only when an explicit document-folder scope
  covers the target document.
- Directory-style helpers return `returned`, `limit`, `truncated`, and
  `next_cursor` so scripts can page through bounded results or choose a narrower
  path/filter. Pass `cursor = res.next_cursor` to `app.vfs.ls`,
  `app.vfs.find`, or `app.vfs.count` to continue.
- Directory counts are sampled unless `count_is_exact` says otherwise. Treat
  `count`, `returned`, and `sampled_count` as the page size inspected, not a
  guaranteed total. `total_count` is `null` when the mount cannot prove a true
  count cheaply and safely.
- Broad folders such as tasks, channels, tables, and files are budgeted and
  truncation-oriented. Prefer narrower mounts such as `/tasks/by-agent`,
  `/tasks/by-status`, date-bucketed channel paths, explicit limits, and cursor
  loops with a script-owned maximum page count.
- Semantic search and embeddings are separate retrieval tools. Use VFS to
  verify exact source content after retrieval returns a path.
- `grep` is literal by default; `rg` is regex by default. Both support bounded
  recursive path search, `-i`, `-l`, `-c`, `-n`, `-o`, `--max-count`, and
  `--max-depth`.
- Common shell conveniences are implemented inside the VFS parser: globs in
  path arguments, pipes, `;`, `&&`, `||`, stdout redirection to writable
  `/files`, stderr suppression, and `2>&1`. Host shell expansion is not
  supported: no `$VAR`, command substitution, process substitution, aliases
  outside the command catalog, or arbitrary programs.
- `jq` is a safe subset for JSON projections: `.`, `.field`, `.[0]`,
  `.items[]`, `.items[].name`, `.["key"]`, `length`, sorted `keys`,
  `has("key")`, `map(.field)`, `select(.field == "value")`, simple arithmetic
  against numeric fields, stdin, NDJSON, and one or more file arguments.
  Unsupported expressions fail instead of returning misleading empty output.
- `head`/`tail` support `-n`, `--lines`, `-c`, `--bytes`, `-1`, `-c4`, zero
  counts, and tail `+N` forms. Missing or nonnumeric option values are errors.
- `truncate` requires `-s 0` or `--size=0`; other sizes are intentionally
  unsupported for now.
- `app.vfs.mkdir(path)` is idempotent for existing directories.
- `app.vfs.write(path, content)` defaults to overwrite for Lua ergonomics.
  Direct `vfs_write` defaults to create. Pass `mode = "create"` or
  `mode = "overwrite"` explicitly in generated code.

Discovery paths are also available inside the VFS:

```lua
local commands = app.vfs.read("/tools/vfs/commands.json")
local examples = app.vfs.read("/tools/vfs/examples.md")
```
