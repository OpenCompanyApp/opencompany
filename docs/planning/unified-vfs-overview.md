# Unified Virtual Filesystem Overview

Date: 2026-05-25
Status: Planning. This document captures the preferred top-level direction for a full OpenCompany virtual filesystem. Dreaming and memory consolidation are intentionally treated as later consumers, not part of the first implementation scope.
Related: `docs/planning/dream-vfs.md`, `docs/planning/memory-implementation.md`, `docs/planning/vector-optional.md`

---

## Position

OpenCompany should have one virtual filesystem that can contain everything agents need to inspect, search, compare, and carefully edit.

That does not mean storing everything as physical files. The better model is:

```text
Agents see one filesystem.
OpenCompany keeps each domain in its proper model, table, and service.
The VFS maps paths and filesystem operations onto those domain services.
```

Documents should stay `documents`. Tasks should stay `tasks`. Messages should stay `messages`. Uploaded files should stay `workspace_files`. Agent memory should stay system-owned agent documents. Integrations and MCP servers should stay catalog/config records. The VFS is a universal access layer over those sources, not a replacement persistence model.

Domain tools should continue to exist for quick basic actions. The VFS is the best surface for exploration, retrieval, batch inspection, precise content search, and patch-style edits. High-level tools remain better for direct intent such as creating a task, sending a channel message, requesting approval, or running an automation.

---

## Recommended OpenCompany Approach

The clean design is a domain-owned VFS that behaves like a permission-aware unix layer over OpenCompany objects. It should be powerful enough that agents can browse, inspect, search, compare, and patch workspace state without needing one-off tools for every object type, while still keeping domain-specific tools for direct actions.

Recommended shape:

- Build one VFS, not separate half-filesystems for documents, memory, files, tasks, and channels.
- Keep it in the OpenCompany domain, under `app/Domain/Vfs`, but split the reusable engine from OpenCompany-specific adapters so the implementation can be copied to another app later without untangling business logic.
- Treat VFS paths as projections over existing domain models. Do not migrate business data into files just to make the filesystem abstraction work.
- Use stable canonical ID-backed paths for durable references, with friendly aliases for agent ergonomics.
- Keep `vfs_exec`, `vfs_patch`, and `vfs_write` as the primitive direct tools. Expose the same implementation through `app.vfs.*` Lua helpers for programmatic workflows.
- Make unix-style read/navigation commands feel real inside the VFS command engine, but do not expose host shell execution.
- Keep `patch` separate from command execution so edits can have version checks, approval summaries, dry runs, and clear permission behavior.
- Make every operation workspace-scoped, permission-filtered, budgeted, and auditable.
- Use cursor-aware folder semantics for large collections instead of pretending every table or task list is a small directory.
- Keep VFS separate from embeddings. Semantic retrieval tools can return VFS paths, and VFS can then verify the exact source with `cat`, `rg`, `stat`, or `patch`.
- Use Postgres indexes, `pg_trgm`, and FTS as deterministic lexical acceleration. Add a dedicated `vfs_text_chunks` table only later for generated or very large virtual text.
- Keep OCR, Apache Tika, PaddleOCR, document conversion, and transcription outside the VFS. Later services can produce text projections that VFS exposes and searches.
- Keep direct non-VFS tools for quick basic actions, especially create/send/run/approve flows where a filesystem metaphor is slower than the domain action.

This gives OpenCompany a single agent operating layer without turning the VFS into a storage engine, embedding system, OCR system, or replacement for domain tools.

The most important boundary:

```text
VFS owns paths, permissions, projections, unix-like commands, patches, budgets, and lexical verification.
Domain services own business behavior and persistence.
Retrieval services own semantic search and embeddings.
Extraction services later own OCR/Tika/PaddleOCR-style text production.
Direct tools own fast domain actions.
```

---

## Goals

- Give agents one mental model for workspace retrieval.
- Make OpenCompany data browsable with predictable paths.
- Support exact lookup with `grep`, pattern discovery with `glob`, and bounded reads with `head`/`tail`.
- Let VFS paths expose honest capabilities instead of pretending every object is a normal writable file.
- Route every operation through existing workspace, agent, integration, folder, file, channel, and approval permissions.
- Keep business logic in domain services, not in path parsing.
- Make future Dream/background maintenance jobs use the same retrieval abstraction without requiring agent tool calls.

---

## Proposed Namespace

Initial top-level namespace:

```text
/
  agents/
    {agent-slug}/
      identity/
        IDENTITY.md
        INSTRUCTIONS.md
      memory/
        MEMORY.md
        topics/
        logs/
        peers/
      tasks/
      files/
  docs/
    ...
  files/
    uploads/
    generated/
    exports/
  tasks/
    open/
    completed/
    by-agent/
    by-status/
  lists/
    projects/
    by-status/
    by-assignee/
  channels/
    {channel-slug}/
      messages/
        YYYY-MM-DD.md
      threads/
  tables/
    {table-slug}/
      schema.json
      rows.ndjson
      views/
  tools/
    catalog.md
    apps/
    integrations/
    mcp/
  automations/
  approvals/
  workspace/
    members.json
    settings.json
```

The exact namespace can evolve, but the top-level split should be stable enough that agents can learn it.

Friendly paths should not be the only address for durable objects. OpenCompany already builds some paths from mutable names and slugs, such as agent-name slugs and workspace file names. The VFS should expose canonical ID-backed paths plus friendly aliases:

```text
/docs/by-id/{document-id}
/files/by-id/{workspace-file-id}
/agents/by-id/{agent-id}/memory
/tasks/by-id/{task-id}
/lists/by-id/{list-item-id}
/channels/by-id/{channel-id}
```

Friendly paths like `/agents/atlas/memory/MEMORY.md` and `/docs/policy.md` are good AX, but `stat` should always return the canonical path, backend ID, version token, and any aliases. Path routing must also define collision behavior when two objects have the same title, file name, channel slug, or agent slug.

---

## Core VFS Operations

The VFS should feel as much like a unix filesystem as possible for agent experience. That means the common retrieval and navigation surface should be command-shaped, not a large set of JSON tools that mimic individual unix commands.

Recommended tool surface:

| Tool | Purpose |
|---|---|
| `vfs_exec(command, cwd = "/")` | Execute a safe unix-like command against the virtual filesystem. |
| `vfs_patch(path, patch, version = null)` | Apply targeted edits with stale-write protection. |
| `vfs_write(path, content, mode = "create", version = null)` | Create or overwrite content only where raw file-like writes are valid. |

These are primitive tools. They should be directly available to agents as first-class tools, and they should also be exposed through the Lua scripting API as `app.vfs.*` helpers. The direct tools optimize single-step interactive agent work. The Lua library optimizes deterministic scripts, automations, loops, transforms, and repeatable checks.

`vfs_exec` is not shell access. It is a VFS command interpreter with a limited unix grammar. It should parse an argv-style command, route it through the VFS adapters, and return compact structured output. No arbitrary `bash`, host filesystem access, environment access, command substitution, or unrestricted process execution.

Supported commands should cover the unix retrieval model agents already know. These examples are not the full target list; the coverage matrix below is the source of truth:

```bash
pwd
cd /docs
ls
ls -la /docs
tree /agents/atlas/memory -L 3
find /docs -name "*.md"
find /tasks/open -type f
grep -R "Stripe Link" /docs /agents/atlas/memory
rg "payment token" /docs --glob "*.md"
cat /docs/planning/unified-vfs-overview.md
head -80 /agents/atlas/memory/MEMORY.md
tail -50 /channels/general/messages/2026-05-25.md
wc -l /docs/**/*.md
stat /tasks/open/123.md
du -sh /files/generated
file /files/uploads/logo.png
cat /tables/customers/rows.ndjson | jq '.email' | sort | uniq
diff /docs/by-id/old-policy /docs/by-id/new-policy
```

Mutation commands can exist in the command language, but they must still go through capability checks and approval wrapping:

```bash
mkdir /files/generated/reports
mv /tasks/open/123.md /tasks/completed/123.md
cp /docs/policy.md /docs/archive/policy.md
rm /files/generated/old-report.md
```

Those commands are interpreted as domain operations, not raw filesystem operations. For example, moving a task projection from `/tasks/open/` to `/tasks/completed/` should call the task domain service to change status if that projection is supported.

`vfs_patch` stays separate because patching needs stronger semantics than a shell-like command string: version/hash checks, previewable diffs, conflict handling, and explicit approval behavior. `vfs_write` also stays separate because raw create/overwrite should be clearly distinguishable from command-style reads and searches.

Pipes should still be parsed by the VFS engine rather than delegated to a host shell:

```bash
find /docs -name "*.md" | grep "pricing"
```

### Command Classification

`vfs_exec` needs command-level permission classification before execution. The current tool registry evaluates permissions from static tool metadata before a tool runs. If every command is hidden behind one static `vfs_exec` permission, then read commands and destructive commands inherit the same approval behavior, which is wrong.

Classify parsed commands into operation classes:

| Class | Examples | Permission behavior |
|---|---|---|
| `read` | `pwd`, `cd`, `ls`, `tree`, `find`, `grep`, `rg`, `cat`, `head`, `tail`, `wc`, `stat`, `file`, `du`, `realpath`, `dirname`, `basename` | Should be callable with read permission and per-path access checks. |
| `read-transform` | `sort`, `uniq`, `cut`, `tr`, `nl`, `jq`, safe `sed`, `diff`, `cmp`, checksums | Reads input and transforms or compares bounded VFS output. Requires read permission on every source path. |
| `write` | `mkdir`, non-destructive `mv`, non-destructive `cp` | Requires write permission, destination checks, and may require approval in supervised/strict modes. |
| `destructive` | `rm`, overwriting `mv`, recursive deletes | Requires destructive capability and approval unless explicitly exempted by policy. |
| `admin` | workspace settings, integration config, MCP config, agent identity writes | Requires admin-scoped capability and explicit approval behavior. |
| `external-effect` | running automations, sending messages, invoking integrations | Should usually remain a direct domain tool, not a VFS command. |

There are two safe implementation options:

1. Start `vfs_exec` as read-only and keep mutations in `vfs_patch`/`vfs_write` plus direct domain tools.
2. Add a VFS permission preflight inside `vfs_exec` that parses the command, classifies it, evaluates the agent's effective permission for that operation class, and only then executes.

The second option preserves the desired unix-like AX, but it requires runtime permission decisions that are more precise than the current static tool slug/type check.

### Unix Command Coverage Audit

The coverage goal should be: an agent can try the normal unix command it would reach for during inspection, search, comparison, and small safe transformations, and the VFS either executes it with domain permissions or returns a specific compatibility error with the nearest supported command. Avoid a tiny bespoke command set that constantly surprises agents.

The current app already has partial equivalents for some commands:

| Existing tool family | Unix equivalent | Current gap VFS should close |
|---|---|---|
| Files tools: `list_files`, `read_file`, `get_file_info`, `search_files`, write/move/copy/delete tools | `ls`, `cat`, `stat`, name-only `find`, `touch`/`mkdir`/`mv`/`cp`/`rm` | No unified cross-domain paths, no content `grep` over workspace files, no shell-like composition, no `du`/`file`/hash/diff helpers. |
| Document tools: list/tree/search/read/update | `ls`, `tree`, `grep`, `cat`, `patch` | Separate from files and memory, not accessible with common path grammar, and patch/version semantics should be stronger through VFS. |
| Memory tools | domain-specific read/write/forget | Useful direct tools, but agents cannot use normal path search like `rg foo /agents/atlas/memory`. |
| Tasks/lists/table/channel tools | structured query/read/update | Good direct tools, but not consistently navigable/searchable as bounded virtual folders. |

Target command coverage:

| Tier | Commands | Notes |
|---|---|---|
| 0: navigation and read inspection | `pwd`, `cd`, `ls`, `dir`, `ll`, `tree`, `find`, `rg`, `grep`, `egrep`, `fgrep`, `cat`, `head`, `tail`, `wc`, `stat`, `file`, `du`, `realpath`, `dirname`, `basename` | This is the minimum useful unix-like surface. `cd` should update command-local cwd in command sequences and Lua sessions, while direct tools can still pass `cwd`. `dir` and `ll` are aliases. `egrep`/`fgrep` map to `grep -E` and `grep -F`. |
| 1: output shaping and safe pipelines | `sort`, `uniq`, `cut`, `tr`, `nl`, `jq`, `sed` safe subset, `less`, `more` | These operate on bounded VFS output streams, not host pipes. `jq` is important because many projections are JSON/NDJSON. `less` and `more` should behave like paged reads. |
| 2: comparison and integrity | `diff`, `cmp`, `comm`, `sha256sum`, `sha1sum`, `md5sum` | Useful for document/version checks, generated files, and before/after audits. Prefer `sha256sum` in examples. |
| 3: mutations after permission classification | `mkdir`, `touch`, `mv`, `cp`, `rm`, `rmdir`, `tee`, `truncate` | Keep behind write/destructive classification, dry-run summaries where broad, stale-write checks, and approval wrapping. `tee` and redirection should route through `vfs_write`, not raw shell writes. |
| 4: explicit non-goals for VFS exec | `bash`, `sh`, `zsh`, `python`, `node`, `php`, `artisan`, `composer`, `npm`, `git`, `curl`, `wget`, `ssh`, `scp`, `ps`, `kill`, `sudo`, `env`, `export`, `chmod`, `chown`, archive/process tools such as `tar`, `zip`, `gzip` | These are host execution, environment, network, package, process, archive, or OS permission concerns. Use direct domain tools, Lua, web/integration tools, or separate host tools where appropriate. `chmod`/`chown` should become domain permission tools if needed, not unix metadata mutations. |

Compatibility details that should be implemented deliberately:

- Parse argv quoting like a shell: single quotes, double quotes, escaped spaces, and `--` option terminators.
- Support glob syntax: `*`, `?`, `[]`, and `**`. Brace expansion is nice but optional; if not supported, return a specific unsupported-feature error.
- Support simple command sequences for read commands: `cd /docs && rg "policy" .`, `pwd; ls -la`. Do not support background jobs, subshells, command substitution, or host environment variable expansion.
- Support pipes between allowlisted read/process commands: `find /docs -name "*.md" | sort | head -20`, `cat /tables/customers/rows.ndjson | jq '.email' | sort | uniq`.
- Support redirection only when it is explicitly routed through VFS write semantics and permission checks. Until then, return a clear error that says to use `vfs_write` or `vfs_patch`.
- Normalize path behavior: `.` and `..` work inside the VFS root, absolute paths start at `/`, and adapter escapes are impossible.
- Implement common flags before exotic flags: `ls -l -a -h`, `tree -L`, `find -name -type -maxdepth -mtime`, `grep`/`rg` `-n -i -C -A -B --glob --files-with-matches`, `head -n`, `tail -n`, `wc -l -w -c`.
- For unsupported flags, do not fail with a generic "unsupported command". Return the command, unsupported flag, supported flags, and an equivalent example when one exists.

Structured unsupported-command errors are part of the AX contract:

```json
{
  "error": "unsupported_command",
  "command": "awk",
  "reason": "awk is not implemented in the VFS command engine.",
  "supported_alternatives": ["jq", "cut", "sed -E subset", "lua"],
  "examples": [
    "cat /tables/customers/rows.ndjson | jq '.email'",
    "vfs_exec(command: \"cut -d, -f2 /files/report.csv\")"
  ]
}
```

This error shape matters as much as successful output. It lets an agent recover immediately instead of falling back to host commands or guessing which VFS features exist.

---

## Primitive Tools And Lua Library

The VFS should have one implementation and two agent-facing access styles:

| Surface | Shape | Best for |
|---|---|---|
| Primitive tools | `vfs_exec`, `vfs_patch`, `vfs_write` | Interactive agent retrieval, quick inspection, precise one-off edits. |
| Lua library | `app.vfs.exec`, `app.vfs.stat`, `app.vfs.read`, `app.vfs.patch`, `app.vfs.write`, helpers like `app.vfs.rg` | Scripts, automations, loops, batch transforms, scheduled checks. |

Both surfaces must call the same VFS application services, command parser, adapters, permission gate, output limiter, and approval/version handling. The Lua layer should not call lower-level models directly and should not have broader access than the primitive tools.

Target direct tool examples:

```text
vfs_exec(command: "rg \"payment token\" /docs --glob \"*.md\"", cwd: "/")
vfs_exec(command: "stat /docs/by-id/01HV...", cwd: "/")
vfs_patch(path: "/docs/by-id/01HV...", version: "sha256:...", patch: "...")
vfs_write(path: "/files/generated/report.md", mode: "create", content: "...")
```

Target Lua examples:

```lua
local matches = app.vfs.rg("payment token", { "/docs" }, { glob = "*.md", limit = 20 })

local stat = app.vfs.stat("/docs/by-id/01HV...")
app.vfs.patch("/docs/by-id/01HV...", patch, { version = stat.version })

app.vfs.write("/files/generated/report.md", markdown, {
    mode = "create",
})
```

The Lua API can expose ergonomic helpers such as `app.vfs.ls`, `app.vfs.cat`, `app.vfs.head`, `app.vfs.tail`, `app.vfs.rg`, `app.vfs.find`, `app.vfs.jq`, `app.vfs.diff`, `app.vfs.du`, and `app.vfs.file`, but those helpers are convenience wrappers over `ExecuteCommand`, not separate semantics. If a helper cannot preserve unix semantics clearly, prefer `app.vfs.exec("...")`.

The Lua docs should include a dedicated `vfs` namespace page so agents can discover the unix command grammar, output shapes, version tokens, permission behavior, and safe scripting patterns through `lua_read_doc("vfs")`.

### Programmatic Lua AX Audit

The current plan is strong for interactive agent AX, but it needs a more explicit contract for programmatic Lua use. Lua scripts should not have to parse unix-like text output. They should get stable Lua tables, typed helper arguments, cursors, limits, and version tokens.

Keep `app.vfs.exec("rg ...")` for shell-compatible work, but make typed helpers the preferred script API:

```lua
local hits = app.vfs.rg("payment token", { "/docs", "/agents/atlas/memory" }, {
    glob = "*.md",
    line_numbers = true,
    context = 2,
    limit = 50,
})

for _, hit in ipairs(hits.matches) do
    print(hit.path .. ":" .. hit.line .. " " .. hit.text)
end
```

Programmatic result shapes should be stable and documented:

| Helper | Return shape |
|---|---|
| `app.vfs.ls(path, opts)` | `{ path, items, count, returned, limit, next_cursor, truncated, capabilities }` |
| `app.vfs.read(path, opts)` / `app.vfs.cat(path, opts)` | `{ path, content, mime, encoding, bytes, version, truncated, next_cursor }` |
| `app.vfs.rg(pattern, paths, opts)` / `app.vfs.find(paths, opts)` | `{ matches/items, count, returned, limit, next_cursor, skipped, truncated }` |
| `app.vfs.stat(path)` | `{ path, type, exists, version, capabilities, size, modified_at, collection }` |
| `app.vfs.patch(path, patch, opts)` | `{ path, version_before, version_after, changed, diff_summary, approval }` |
| `app.vfs.write(path, content, opts)` | `{ path, created, overwritten, version_after, approval }` |
| `app.vfs.exec(command, opts)` | `{ command, cwd, exit_code, stdout, stderr, data, diagnostics, truncated }` |

Important Lua-specific additions:

- Add cursor helpers so scripts can safely handle large folders without manual loop bugs: `app.vfs.pages(path, opts)`, `app.vfs.each(path, opts, fn)`, or an equivalent iterator shape.
- Require explicit maxima for iteration helpers: `max_pages`, `max_items`, `max_bytes`, and `deadline_ms`. The helper should stop with `truncated = true` rather than exhausting the Lua sandbox.
- Add a `dry_run` option for mutations and bulk-ish helpers so Lua can build approval summaries without changing state.
- Add `app.vfs.batch(ops, opts)` only if it preserves per-operation permissions, version checks, partial-failure reporting, and approval summaries. Without those guarantees, scripts should loop explicitly.
- Make all helpers accept `cwd` where path-relative behavior matters. `app.vfs.cd()` can be convenient, but hidden mutable cwd is risky in long scripts; explicit `cwd` is better for repeatability.
- Return structured permission and unsupported-feature errors. The current Lua bridge raises tool errors at the callsite, so docs should teach `pcall` around VFS writes, large scans, and permission-sensitive reads.
- Include `capabilities` and `version` in read/stat results so scripts can decide whether to patch, write, move, or skip without guessing.
- Keep Lua helper names boring and predictable. Do not invent app-specific verbs when a unix-like helper name or direct domain tool already exists.

The desired split:

```text
Interactive agent:
  vfs_exec("find /docs -name '*.md' | sort | head -20")

Programmatic Lua:
  local page = app.vfs.find({ "/docs" }, { name = "*.md", sort = "path", limit = 20 })
  for _, item in ipairs(page.items) do ... end
```

This keeps the unix AX without making Lua scripts brittle. `vfs_exec` can remain universal, but `app.vfs.*` should feel like a small, typed standard library over the same VFS primitives.

---

## Before And After Examples

### Find A Policy Across Docs

Before VFS, an agent has to choose between high-level document search and manual document reads:

```text
search_documents(query: "payment token", mode: "auto")
get_document(documentId: "...", includeVersions: false)
get_document(documentId: "...", includeVersions: false)
```

With VFS primitives:

```text
vfs_exec(command: "rg \"payment token\" /docs --glob \"*.md\" -n")
vfs_exec(command: "head -120 /docs/by-id/01HV...")
```

With Lua:

```lua
local hits = app.vfs.rg("payment token", { "/docs" }, {
    glob = "*.md",
    line_numbers = true,
    limit = 20,
})

for _, hit in ipairs(hits.matches) do
    print(hit.path .. ":" .. hit.line .. " " .. hit.text)
end
```

### Inspect Agent Memory Without Leaking Scope

Before VFS, memory access is split across memory tools and document tools:

```text
recall_memory(query: "Stripe Link")
get_document(documentId: "...")
```

With VFS primitives:

```text
vfs_exec(command: "tree /agents/atlas/memory -L 3")
vfs_exec(command: "rg \"Stripe Link\" /agents/atlas/memory")
vfs_exec(command: "stat /agents/by-id/01AG.../memory/MEMORY.md")
```

With Lua:

```lua
local memory = app.vfs.rg("Stripe Link", { "/agents/atlas/memory" }, { limit = 10 })
print(memory.summary)
```

The same `MemoryScopeGuard` and channel context rules must still apply. If memory tools are not available in a group channel, `app.vfs.rg(..., { "/agents/atlas/memory" })` must also be denied or filtered consistently.

### Patch A Document Safely

Before VFS, a precise edit usually becomes a full-content update:

```text
get_document(documentId: "...")
update_document(documentId: "...", content: "<entire edited document>", saveVersion: true)
```

With VFS primitives:

```text
vfs_exec(command: "stat /docs/by-id/01HV...")
vfs_patch(path: "/docs/by-id/01HV...", version: "sha256:abc123", patch: "<unified diff>")
```

With Lua:

```lua
local doc = app.vfs.stat("/docs/by-id/01HV...")

app.vfs.patch("/docs/by-id/01HV...", [[
@@
-Old sentence.
+New sentence.
]], {
    version = doc.version,
})
```

The patch path should automatically create a document version, re-check the version token at execution time, and fail cleanly if the document changed after the agent read it.

### Batch Check Large Workspace Files

Before VFS, file tools are name/path oriented and content checks require manual reads:

```text
search_files(query: "invoice")
read_file(path: "/uploads/invoices/q1.txt")
read_file(path: "/uploads/invoices/q2.txt")
```

With VFS primitives:

```text
vfs_exec(command: "find /files/uploads -name \"*.txt\"")
vfs_exec(command: "rg \"overdue\" /files/uploads --glob \"*.txt\"")
```

With Lua:

```lua
local overdue = app.vfs.rg("overdue", { "/files/uploads" }, {
    glob = "*.txt",
    max_files = 100,
    max_bytes_per_file = 250000,
})

if overdue.count > 0 then
    app.chat.send_channel_message({
        channel_id = ctx.channel_id,
        content = "Found " .. overdue.count .. " overdue invoice references.",
    })
end
```

The VFS service still owns MIME checks, streaming limits, skipped-file counts, and permission filtering.

### Use Structured Data As Files Without Losing Structure

Before VFS, a script uses table-specific calls:

```lua
local rows = app.tables.get_rows({ tableId = "leads", limit = 100 })
```

With VFS Lua, the same data can be consumed as a bounded export when that shape is better:

```lua
local page = app.vfs.read("/tables/leads/rows.ndjson", {
    cursor = nil,
    limit = 100,
})

for _, row in ipairs(page.rows) do
    -- deterministic transform or export logic
end
```

The direct table tools remain better for row mutations. VFS projections are best for inspection, export, grep, comparison, and read-heavy scripts.

### Move A Task Projection

Before VFS:

```text
set_task_status(taskId: "01TASK...", status: "completed")
```

With VFS primitives:

```text
vfs_exec(command: "mv /tasks/open/01TASK.md /tasks/completed/01TASK.md")
```

This only works if the task adapter explicitly maps that move to the task domain service. If the projection cannot round-trip safely, `stat` should report no `move` capability and the agent should use `set_task_status`.

### Browse A Large Task Set

Before VFS, runtime tasks already have a paginated service-level query:

```text
tasks.list(status: "completed", perPage: 50, page: 1)
tasks.list(status: "completed", perPage: 50, page: 2)
```

The VFS should preserve that bounded behavior. It must not turn `/tasks/completed/` into a directory with 30,000 materialized entries.

With VFS primitives:

```text
vfs_exec(command: "stat /tasks/completed")
vfs_exec(command: "ls /tasks/completed --limit 50 --sort updated_at:desc")
vfs_exec(command: "find /tasks/completed -mtime -7 -name \"*billing*\" --limit 50")
vfs_exec(command: "rg \"approval failed\" /tasks/completed --since 2026-05-01 --limit 25")
```

With Lua:

```lua
local page = app.vfs.ls("/tasks/completed", {
    limit = 50,
    sort = "updated_at:desc",
})

while page.next_cursor and should_continue(page) do
    page = app.vfs.ls("/tasks/completed", {
        limit = 50,
        cursor = page.next_cursor,
        sort = "updated_at:desc",
    })
end
```

The result should include `count`, `returned`, `next_cursor`, `default_limited = true`, and query hints. For example, `stat /tasks/completed` can report that the folder has 30,000 entries and suggest `/tasks/completed/by-month/2026-05`, `/tasks/by-agent/{agent}/completed`, or `find /tasks/completed -mtime -7`.

---

## Path Capabilities

Every virtual node should expose capabilities. Agents should be able to ask `vfs_exec("stat ...")` before editing.

Example:

```json
{
  "path": "/tasks/open/123.md",
  "type": "task",
  "backend": "tasks",
  "capabilities": ["read", "patch", "move"],
  "canonicalActions": ["set_task_status", "update_task"]
}
```

Capability classes:

- Read-only: tool catalogs, historical messages, approval records, generated reports.
- Read and patch: documents, memory files, selected task projections.
- File-like write: workspace files and generated artifacts.
- Action-backed: paths where edits translate into domain commands, such as task status changes.
- Admin-gated: workspace settings, MCP server config, integration config, agent identity.

The VFS should never imply that a path is writable just because it has a `.md` or `.json` projection.

---

## Domain Adapters

Build a real subsystem with adapters per domain:

```text
app/Domain/Vfs/
  Core/
    Contracts/
      VirtualFilesystem.php
      VfsAdapter.php
      VfsAuthorizer.php
      VfsMountProvider.php
    Data/
      VfsContext.php
      VfsCommand.php
      VfsPath.php
      VfsNode.php
      VfsStat.php
      VfsListing.php
      VfsReadResult.php
      VfsCollectionPage.php
      VfsCapabilities.php
      VfsSearchQuery.php
      VfsPatch.php
    Application/
      VirtualFilesystemService.php
      ExecuteCommand.php
      ListPath.php
      ReadPath.php
      StatPath.php
      PatchPath.php
      WritePath.php
      SearchPath.php
    Support/
      PathRouter.php
      VfsCommandParser.php
      VfsProjectionRenderer.php
      VfsCollectionLimiter.php
      VfsVersionToken.php
      MarkdownProjection.php
  OpenCompany/
    Adapters/
      DocumentsVfsAdapter.php
      WorkspaceFilesVfsAdapter.php
      AgentMemoryVfsAdapter.php
      TasksVfsAdapter.php
      ListsVfsAdapter.php
      ChannelsVfsAdapter.php
      TablesVfsAdapter.php
      ToolsVfsAdapter.php
      AutomationsVfsAdapter.php
      ApprovalsVfsAdapter.php
      WorkspaceVfsAdapter.php
    Authorization/
      OpenCompanyVfsAuthorizer.php
    Providers/
      OpenCompanyVfsMountProvider.php
    Projection/
      DocumentMarkdownProjection.php
      TaskMarkdownProjection.php
      ListItemMarkdownProjection.php
app/Agents/Tools/Vfs/
  ExecuteVfsCommand.php
  PatchVfsPath.php
  WriteVfsPath.php
app/Agents/Tools/Providers/
  VfsToolProvider.php
resources/lua-docs/
  vfs.md
```

Adapter contract shape:

```php
interface VfsAdapter
{
    public function list(string $path, VfsContext $context): VfsListing;
    public function read(string $path, VfsContext $context): VfsReadResult;
    public function write(string $path, string $content, VfsContext $context): VfsWriteResult;
    public function patch(string $path, VfsPatch $patch, VfsContext $context): VfsWriteResult;
    public function delete(string $path, VfsContext $context): VfsDeleteResult;
    public function move(string $from, string $to, VfsContext $context): VfsMoveResult;
    public function stat(string $path, VfsContext $context): VfsStat;
    public function search(VfsSearchQuery $query, VfsContext $context): VfsSearchResult;
}
```

`PathRouter` chooses the adapter from the path prefix. `OpenCompanyVfsAuthorizer` applies the same authorization rules as the underlying domain tools and controllers. Adapters must call existing domain services wherever possible.

`VfsContext` must carry the acting agent, workspace, current channel ID, current task ID, approval/session metadata, and output budget. Current memory tools rely on channel context to decide whether memory is available, and task-aware tools can receive current task context from the registry. VFS tools need the same context so `/agents/{slug}/memory` does not accidentally bypass memory scope rules and so background jobs can use the service with an explicit non-agent context.

`ExecuteCommand` owns unix-like command parsing and dispatch. It should map commands such as `ls`, `find`, `rg`, `cat`, `head`, `tail`, `wc`, and `stat` onto the same lower-level VFS actions used by direct application code. That keeps the agent AX unix-like while keeping the implementation permission-aware and auditable.

The VFS tool provider must be registered as a direct agent-callable group and as a Lua namespace. Today, the agent-visible registry only registers a short list of direct tool groups and leaves most app tools behind the Lua API. VFS improves AX only if `vfs_exec`, `vfs_patch`, and `vfs_write` are direct primitive tools with compact schemas, while the same primitives are also available from `app.vfs.*` for scripts and automations.

---

## Implementation Architecture

Implement VFS as an app-owned domain subsystem. Do not put path parsing, command parsing, permission expansion, or projection logic inside individual agent tools.

Keep it under `app/Domain/Vfs`, but split it into `Core` and `OpenCompany` namespaces. The goal is not a package now; the goal is package-ready domain code that could be copied into another app later.

```text
app/Domain/Vfs/Core          # reusable VFS engine, no OpenCompany model knowledge
app/Domain/Vfs/OpenCompany   # OpenCompany mounts, adapters, projections, authorizer
```

The boundary rule:

```text
If a class can work in another Laravel app with different models, put it in Core.
If a class mentions Document, Task, User, workspace(), AgentDocumentService,
AgentPermissionService, Channel, WorkspaceFile, or OpenCompany policy, put it
in OpenCompany.
```

The core rule:

```text
Agents and Lua call thin surfaces.
Thin surfaces call one VFS application service.
The VFS service routes to domain adapters.
Adapters call existing domain services and models.
```

Request flow for a read command:

```text
vfs_exec("ls /tasks/completed --limit 50")
  -> ExecuteVfsCommand tool
  -> VirtualFilesystemService::execute()
  -> VfsCommandParser parses argv safely
  -> ExecuteCommand classifies read/write/destructive/admin
  -> PathRouter resolves /tasks to TasksVfsAdapter
  -> OpenCompanyVfsAuthorizer checks agent/workspace/context/path
  -> TasksVfsAdapter queries the task domain with limit/cursor
  -> VfsCollectionLimiter shapes page metadata
  -> tool returns compact structured output
```

Lua follows the same service path:

```text
app.vfs.ls("/tasks/completed", { limit = 50 })
  -> Lua bridge
  -> VFS Lua function binding
  -> VirtualFilesystemService::list()
  -> same router, adapter, permission, limiter, and result DTOs
```

The Lua layer should be wrappers over the primitives, not a second implementation. For example, `app.vfs.rg(...)` can build a `VfsSearchQuery` or call `ExecuteCommand`, but it must not query `Document`, `Task`, `WorkspaceFile`, or `Message` directly.

### Layer Responsibilities

| Layer | Owns | Must not own |
|---|---|---|
| Agent tools | JSON schemas, tool names, request-to-service mapping, approval wrapper compatibility | Domain queries, path routing, permission expansion, projection rendering |
| Lua namespace | Script-friendly wrappers, docs, argument normalization | Direct model access or broader permissions than tools |
| `Core\Application\VirtualFilesystemService` | Orchestration, context creation, command dispatch, adapter routing | Domain-specific query details |
| `ExecuteCommand` | Unix-like grammar dispatch, command classification, pipe/flag semantics | Host shell execution |
| `PathRouter` | Prefix matching, canonical path resolution, friendly alias lookup | Authorization decisions |
| `Core\Contracts\VfsAuthorizer` | Generic authorization interface and decision shape | OpenCompany policy details |
| `OpenCompanyVfsAuthorizer` | Workspace, agent, folder, file, channel, integration, admin, approval, and command-class checks | Domain mutation side effects |
| OpenCompany adapters | Domain-specific list/read/stat/search/patch/write behavior | Cross-domain policy or global command parsing |
| `VfsCollectionLimiter` | Page defaults, max limits, cursors, output budgets, safe truncation | Business filtering semantics |
| Projection renderers | Markdown/JSON/NDJSON views and round-trip contracts | Deciding whether a caller is allowed |

### Integration Points

- Register `VfsToolProvider` from `AppServiceProvider::registerBuiltInToolProviders()`.
- Add `vfs` to `ToolRegistry::DIRECT_TOOL_GROUPS` so `vfs_exec`, `vfs_patch`, and `vfs_write` are model-visible.
- Register a Lua `vfs` namespace so scripts can call `app.vfs.*` through the existing Lua bridge.
- Add `resources/lua-docs/vfs.md` so `lua_read_doc("vfs")` explains commands, helpers, output shapes, cursors, version tokens, and permission behavior.
- Extend permission evaluation with VFS command classification. A single static `vfs_exec` tool permission is not enough because `ls` and `rm` have different risk.
- Bind `Core\Contracts\VfsAuthorizer` to `OpenCompanyVfsAuthorizer`.
- Use `OpenCompanyVfsMountProvider` to mount `/docs`, `/files`, `/agents`, `/tasks`, `/lists`, `/channels`, `/tables`, `/tools`, `/automations`, `/approvals`, and `/workspace`.
- Reuse existing services wherever possible: `ManageTasks` for runtime tasks, `FileSystemService` for workspace files, `AgentDocumentService` for identity/memory, document services/models for documents, channel/message queries for channels, and table services/models for data tables.

Mounting should happen in OpenCompany glue, not in core:

```php
final class OpenCompanyVfsMountProvider implements VfsMountProvider
{
    public function mounts(): array
    {
        return [
            '/docs' => app(DocumentsVfsAdapter::class),
            '/files' => app(WorkspaceFilesVfsAdapter::class),
            '/agents' => app(AgentMemoryVfsAdapter::class),
            '/tasks' => app(TasksVfsAdapter::class),
            '/lists' => app(ListsVfsAdapter::class),
            '/channels' => app(ChannelsVfsAdapter::class),
            '/tables' => app(TablesVfsAdapter::class),
        ];
    }
}
```

In another app, the copyable `Core` can stay the same while that app replaces only the mount provider, adapters, authorizer, and projections.

### Adapter Guidelines

Adapters should be small and boring. Each adapter maps a VFS path family onto one backing domain:

```php
final class TasksVfsAdapter implements VfsAdapter
{
    public function stat(string $path, VfsContext $context): VfsStat
    {
        // Resolve collection or task ID, return capabilities, counts, indexes,
        // version token, and canonical path.
    }

    public function list(string $path, VfsContext $context): VfsListing
    {
        // Use paged task queries. Never materialize every task in a status folder.
    }

    public function read(string $path, VfsContext $context): VfsReadResult
    {
        // Render one task projection or a bounded collection export.
    }
}
```

Adapter-specific rules:

- `DocumentsVfsAdapter` should preserve document versions and system document guardrails.
- `WorkspaceFilesVfsAdapter` should use `FileSystemService` for metadata and bytes.
- `AgentMemoryVfsAdapter` should use `AgentDocumentService` and memory scope rules, not generic document write paths.
- `TasksVfsAdapter` should preserve the paged `ManageTasks` query style and avoid unbounded status directories.
- `ListsVfsAdapter` should use capped collection queries even if UI endpoints currently load all board items.
- `ChannelsVfsAdapter` should search only permitted channels and date-bucket message history.
- `TablesVfsAdapter` should expose bounded schema/view/row projections while keeping row mutations in structured table tools.

### First Milestone Boundary

The first implementation should be read-heavy:

1. Direct primitive tools: `vfs_exec` read commands only, plus `vfs_patch` skeleton behind strict version checks.
2. Lua namespace: `app.vfs.exec`, `app.vfs.stat`, `app.vfs.ls`, `app.vfs.read`, `app.vfs.rg`, `app.vfs.find`, and cursor-safe page helpers over the same service.
3. Adapters: `/docs`, `/files`, `/tasks`, `/lists`, and `/agents/{slug}/memory`.
4. Commands: `pwd`, `cd`, `ls`, `dir`, `ll`, `tree`, `find`, `rg`, `grep`, `egrep`, `fgrep`, `cat`, `head`, `tail`, `wc`, `stat`, `file`, `du`, `realpath`, `dirname`, `basename`, `sort`, `uniq`, `cut`, `tr`, `nl`, `jq`, safe `sed`, `less`, `more`, `diff`, `cmp`, `comm`, `sha256sum`, `sha1sum`, and `md5sum`.
5. Large-folder collection semantics before broad task/list/channel/table exposure.

Mutation commands such as `mkdir`, `touch`, `mv`, `cp`, `rm`, `rmdir`, `tee`, and `truncate` should wait until command classification, approval summaries, dry-run previews, and stale-write handling are all implemented.

---

## Domain Mapping

| Namespace | Backing domain | Typical operations | Notes |
|---|---|---|---|
| `/docs` | `Document` model and document services | list, read, grep, patch, write, delete | Main DB-backed knowledge tree. |
| `/files` | `WorkspaceFile` + `FileSystemService` | list, read, grep text files, write, move, copy, delete | Physical bytes remain on workspace disks. |
| `/agents/{slug}/identity` | `AgentDocumentService` | read, patch, admin-gated write | Prompt-critical; must preserve system-file guardrails. |
| `/agents/{slug}/memory` | `AgentDocumentService` + memory services | read, grep, patch, write topic/log/peer files | Best fit for VFS and later Dream passes. |
| `/tasks` | `Task` models/services | list, read, grep, patch, move/status projection | Runtime/delegated work items; keep direct task tools for quick actions. |
| `/lists` | `ListItem` models/services | list, read, grep, patch, move/status projection | Kanban/project board items are separate from runtime tasks. |
| `/channels` | Channel and message models | read, grep, bounded date reads | Sensitive; likely read-only for history. |
| `/tables` | Data table services | read schema/views, export rows, grep exports | Structured APIs remain primary for row mutations. |
| `/tools` | `ToolRegistry`, Lua docs, MCP catalog | read, grep, glob | Discovery only; no raw writes. |
| `/automations` | Automation models/services | read, patch selected config, run via domain tool | Running remains an explicit action. |
| `/approvals` | Approval models/services | read, maybe decide via domain action | Do not make approval decisions via raw file edits. |
| `/workspace` | Workspace settings and members | read, admin-gated patch | High-risk writes should require explicit capability and approvals. |

---

## Large Virtual Folders

Some VFS folders can represent thousands or tens of thousands of records. The VFS must treat those as queryable collections, not ordinary fully materialized directories.

High-risk paths:

| Namespace | Scale risk | Required behavior |
|---|---|---|
| `/tasks/open`, `/tasks/completed`, `/tasks/by-agent/*` | Runtime tasks can grow quickly through chats, automations, delegation, retries, and history. | Always page. Default to latest-first windows. Expose counts and cursors. |
| `/lists/projects/*`, `/lists/by-status/*`, `/lists/by-assignee/*` | Kanban/list items can become large and the current UI controller can load all items. | VFS adapter must use explicit limits even if UI endpoints do not. |
| `/channels/*/messages/*` | Message history can be unbounded. | Date-bucket and cursor by message timestamp/id. |
| `/tables/*/rows.ndjson` | Data tables can be large. | Stream/export in bounded pages. |
| `/approvals`, `/automations/*/runs` | Audit/history records grow over time. | Default to recent windows and filtered views. |

Directory operations need consistent collection semantics:

- `ls` defaults to a small page, such as 50 or 100 entries, and returns structured pagination metadata.
- `tree` refuses or truncates large directories unless `--limit`, `--max-depth`, and filters make the result safe.
- `find` and `rg` push filters into the backing query where possible before scanning projected content.
- `stat` on a collection returns counts, supported indexes, default sort, maximum page size, and suggested narrower paths.
- `cat` on a collection path should fail with a clear "directory is too large; use ls/find/rg or a paged export" message.
- Lua helpers must expose cursor arguments and must not loop through every page unless the script gives an explicit maximum.

Suggested collection result shape:

```json
{
  "path": "/tasks/completed",
  "type": "collection",
  "count": 30412,
  "returned": 50,
  "limit": 50,
  "next_cursor": "eyJ1cGRhdGVkX2F0IjoiMjAyNi0wNS0yNVQ...",
  "sort": "updated_at:desc",
  "default_limited": true,
  "suggested_filters": [
    "find /tasks/completed -mtime -7",
    "ls /tasks/completed/by-month/2026-05",
    "ls /tasks/by-agent/atlas/completed"
  ]
}
```

For tasks and lists, prefer virtual index folders over one giant status folder:

```text
/tasks/
  open/                    # windowed, latest-first
  completed/               # windowed, latest-first
  by-agent/{agent}/open/    # narrower indexed view
  by-agent/{agent}/completed/
  by-status/{status}/
  by-source/{source}/
  by-month/YYYY-MM/
  recent.md                # small generated summary, not every task
  counts.json

/lists/
  projects/                # projects/folders, windowed if large
  by-status/{status}/
  by-assignee/{user}/
  by-project/{project-id}/
  due/YYYY-MM-DD/
  counts.json
```

The first VFS implementation should make `/tasks` and `/lists` read projections conservative: `stat`, `ls`, `find`, `rg`, `head`, and direct ID reads are enough. Mutating thousands of task/list projections through bulk `mv`, `rm`, or `patch` should wait until collection pagination, dry-run previews, and approval summaries are solid.

Implementation notes from the current codebase:

- `ManageTasks::list()` already paginates root tasks with a default `perPage` of 50 and includes counts for total/pending/active/completed. The VFS task adapter should reuse this style rather than querying all tasks.
- Agent list tools cap list item reads at 100, but the UI list item controller currently returns all items for the board. The VFS list adapter should follow the capped agent-tool behavior, not the unbounded UI listing.
- Search surfaces already use small limits for tasks. VFS `find`/`rg` should preserve that default and require explicit limits for broader scans.

## Keep Non-VFS Tools

The VFS should sit alongside direct domain tools.

Keep fast, intentional tools such as:

- `create_task`
- `set_task_status`
- `create_list_item`
- `update_list_item`
- `send_channel_message`
- `create_document`
- `update_agent`
- `run_automation`
- `request_approval`
- `contact_agent`
- integration-specific tools through Lua or direct providers

These tools express intent better than editing a virtual file. For example, setting a task to done should usually be `set_task_status`, even if moving `/tasks/open/123.md` to `/tasks/completed/123.md` is also supported later as a projection.

---

## Permissions And Safety

The VFS must preserve existing OpenCompany boundaries:

- Workspace scope is absolute.
- Agent tool permissions still apply.
- Document folder permissions still apply.
- Workspace file folder permissions still apply.
- Channel access still applies.
- Integration access still applies.
- Admin-only settings stay admin-only.
- Approval-required operations should be wrapped the same way as direct tools.
- Private agent memory and peer cards must not leak into public channels.

Recursive operations need a shared permission-expansion primitive. Existing file-folder permissions already check descendants for many file paths, while document tools mostly check the immediate parent folder. Unix-like operations such as `tree`, `find`, `rg -R`, and `grep -R` need consistent behavior across domains:

1. Resolve the starting paths to candidate nodes.
2. Expand recursive candidates through the adapter.
3. Filter each candidate through the domain permission gate.
4. Execute only on permitted candidates.
5. Return permitted results plus safe omitted counts when useful.

Do not reveal sensitive names merely because a recursive search skipped them. For channels in particular, expand the channel set from the agent's channel access before searching messages. A broad `/channels` search must not query all workspace messages first and apply channel checks only after a result exists.

Writes need extra care:

- Prefer `patch` over raw overwrite for domain-backed nodes.
- Include stale-read detection with versions, timestamps, hashes, or ETags.
- Require version tokens for `vfs_patch` where possible, and strongly prefer them for overwrites. A token can be `updated_at`, a content hash, a row version, an ETag, or a domain-specific revision number.
- Create document versions automatically for VFS-backed document edits unless a domain-specific policy says otherwise. Current document versioning is optional in some direct tools, but VFS patching should be safer by default.
- Revalidate permissions, path resolution, and version tokens at the moment an approved mutation actually executes. Approval can happen later than the original tool call, so the target may have moved or changed.
- Return clear errors when a path projection is read-only.
- Do not allow path traversal or adapter escape through slugs.
- Treat generated markdown/JSON projections as views over models, not as authority unless the adapter explicitly supports round-tripping.
- Parse `vfs_exec` commands with a strict allowlist. Shell conveniences are acceptable only when implemented inside the VFS command engine.
- Apply authorization after command expansion. For example, `rg "secret" /channels` must check each channel/message candidate before returning any match.
- For partially permitted searches, return permitted results and safe omitted counts. Do not reveal hidden path names when the path itself is sensitive.

Text search over workspace files must be streaming and size-aware. Uploaded files can be large and may live on non-local disks. `grep` and `rg` should:

- Search only text-safe MIME types unless an external text projection already exists for the file.
- Enforce max bytes scanned per file, max files scanned, max matches, max returned lines, and max line length.
- Return line-context windows instead of full file contents.
- Skip binary files safely and report a non-sensitive skipped count.
- Avoid reading whole remote files into memory when the storage adapter can stream.

Structured projections need pagination and budget limits. `/tables/{table}/rows.ndjson`, `/channels/{channel}/messages/YYYY-MM-DD.md`, and list/task exports should return bounded windows with cursor metadata rather than pretending every backing table is a small file that can always be read in one `cat`.

Task and list projections need extra scale guardrails:

- Never materialize every task or list item in a status folder by default.
- Prefer latest-first pages for operational folders and date/month buckets for history.
- Require explicit filters or cursors for scans over large historical collections.
- Return summaries and counts before rows when a folder is huge.
- Make bulk operations opt-in with dry-run output, expected affected count, and approval requirements.

---

## Remaining Design Decisions

The plan should stay strict and boring: one VFS service, typed DTOs, domain adapters, no hidden shell, and no duplicate policy logic.

### Observability And Audit Trail

Add a `VfsOperationLog` domain event for every VFS operation. It does not need to become a database table immediately, but the event shape should be stable from the start.

Log:

- `operation_id`
- `workspace_id`
- `agent_id` or background actor id
- surface: `tool`, `lua`, `automation`, `dream`, or internal app call
- command/helper name
- `cwd`
- canonical paths touched
- command class
- permission decision
- approval id when present
- result counts, skipped counts, truncation flags
- duration, bytes scanned, bytes returned
- error code when failed

Persist all writes, destructive/admin operations, failed permission attempts, approved operations, and large scans. Plain reads can be sampled or kept in normal application logs.

### Formal Error Contract

Use one structured error shape across direct tools, Lua helpers, command execution, and internal callers:

```json
{
  "ok": false,
  "error": {
    "code": "stale_version",
    "message": "Document changed since it was read.",
    "path": "/docs/by-id/...",
    "canonical_path": "/docs/by-id/...",
    "retryable": true,
    "suggestions": [
      "stat /docs/by-id/...",
      "retry patch with latest version"
    ]
  }
}
```

Keep codes small and stable:

```text
not_found
permission_denied
hidden_by_permission
stale_version
too_large
binary_file
partial_results
unsupported_command
unsupported_flag
unsupported_projection
approval_required
conflict
invalid_path
```

Lua may raise these at the callsite through the bridge, but the underlying VFS result and metadata should still preserve the structured code and suggestions.

### Test And Certification Matrix

Every adapter should pass a shared adapter contract suite. This is how OpenCompany avoids adding a mount that looks file-like but quietly breaks permissions, pagination, or output shape.

Required contract coverage:

- `stat`, `ls`, `read`, `find`, `rg`, and canonical path resolution.
- Permission filtering and hidden-by-permission behavior.
- Pagination, cursor, truncation, and output budget behavior.
- Error shape consistency.
- Version token and capability reporting.

Command and Lua tests should be separate:

- Parser tests for quoting, globs, flags, pipes, and read-only command sequences.
- Unix command compatibility tests.
- Unsupported command and unsupported flag suggestion tests.
- Large-folder refusal/truncation tests.
- Lua helper result-shape tests.
- Stale-write and approval revalidation tests.

### Projection Round-Trip Rules

Every markdown, JSON, or NDJSON projection should declare a projection contract instead of relying on ad hoc parsing.

```php
final class TaskMarkdownProjection
{
    public function render(Task $task): VfsProjection;
    public function patch(Task $task, VfsPatch $patch): VfsPatchPlan;
    public function writableFields(): array;
}
```

Each projection contract should define:

- readable fields
- writable fields
- read-only generated sections
- whether raw overwrite is allowed
- whether patching is field-aware or text-diff only
- version token source
- conflict behavior

For OpenCompany, prefer patching structured fields through domain services where possible. Markdown projections are a great read/edit interface, but the backing model fields remain the authority.

### Search And Indexing Strategy

VFS should be separate from embeddings. The major benefit of VFS is deterministic, exact, permission-aware inspection that often beats embeddings for operational work. Do not turn VFS into RAG.

The rule:

```text
VFS is deterministic workspace filesystem access.
Embeddings and reranking are separate retrieval layers.
Retrieval services may return VFS paths and may use VFS to inspect sources.
VFS itself must not depend on embeddings, vector search, reranking, or provider settings.
```

This keeps command semantics honest:

- `grep` and `rg` mean literal/regex text search.
- `find` means path and metadata discovery.
- `ls`, `tree`, `cat`, `head`, `tail`, `wc`, and `stat` remain deterministic filesystem-style operations.
- VFS `search`, if included, should mean lexical search only: live scan, PostgreSQL FTS, trigram, metadata search, or later externally extracted text search. It should not silently become semantic search.

Embeddings stay in separate explicit tools and services:

```text
recall_memory(...)
search_documents(mode: "semantic")
semantic_search(...)
app.retrieval.semantic_search(...)
app.retrieval.hybrid_search(...)
```

Those tools can return VFS paths:

```json
{
  "query": "how do we handle refunds",
  "results": [
    {
      "path": "/docs/by-id/01HV...",
      "score": 0.88,
      "snippet": "Return requests are handled..."
    }
  ]
}
```

Then the agent uses VFS to verify and inspect precisely:

```bash
vfs_exec("cat /docs/by-id/01HV...")
vfs_exec("rg \"refund|return\" /docs/by-id/01HV...")
```

### VFS Text Index

VFS can still use indexes, but indexes should be an implementation detail for deterministic text operations, not the identity of the VFS itself. Do not start by putting every virtual file into a giant `vfs_text_chunks` table. Use an indexing ladder:

| Tier | Mechanism | Use |
|---|---|---|
| 0 | Live adapter scan | Small documents, bounded paths, recent messages, exact `rg`/`grep`, and any operation where freshness and exact command semantics matter most. |
| 1 | Source-table PostgreSQL indexes | Fast path/name/title/status/date filtering on the real domain tables before VFS projection. |
| 2 | Source-table PostgreSQL FTS/trigram | Broad lexical search over source-owned text columns where the source table already has the content in searchable form. |
| 3 | Dedicated `vfs_text_chunks` | Generated projections, very large historical collections, externally extracted text, and cases that need stable line/byte offsets across virtual content. |

`pg_trgm` should be an early part of the plan. It is excellent for path/title/name discovery, partial matches, typo-tolerant metadata search, and speeding up existing `LIKE`/`ILIKE` surfaces. Use GIN trigram indexes on the source tables where agents naturally search by partial text, such as document titles, file names, task/list titles, channel names, and selected message/content columns where the cardinality and write rate make sense.

Trigram is not a replacement for `rg`. It should be used as a candidate prefilter for large search spaces, then VFS must verify the exact literal or regex match against the projected text before returning `rg`/`grep` results. It also does not solve generated virtual files, line offsets, byte offsets, or stable search over external text projections. Those are the reasons to add `vfs_text_chunks` later.

FTS and trigram are complementary:

| Index type | Best for | Example VFS behavior |
|---|---|---|
| B-tree/source filters | Exact IDs, workspace scope, status, dates, foreign keys | `find /tasks -status open`, `/channels/{id}/messages?after=...` |
| `pg_trgm` | Substrings, filenames, path fragments, fuzzy-ish matching, `ILIKE '%foo%'` | `find /docs -name '*invoice*'`, `find /files -path '*contracts*'` |
| PostgreSQL FTS | Tokenized lexical search, ranking, stemming, broad keyword search | `search "refund policy" /docs`, `app.vfs.search(..., mode = "lexical")` |
| `vfs_text_chunks` | Generated/large virtual text with offsets, plus externally extracted text when available | `rg "termination" /files`, broad search across archived channel history |

When a dedicated text chunk table is needed, keep it generic and embedding-free.

Recommended table shape:

```text
vfs_text_chunks
  id
  workspace_id
  mount                         # docs, files, agents, tasks, lists, channels, tables
  source_type                   # Document, WorkspaceFile, Message, Task, ListItem, DataTableRow, ...
  source_id
  canonical_path
  path_hash
  collection                    # general, identity, memory, topic, peer, channel, task, file, table, ...
  agent_id                      # for private agent memory/identity scopes
  channel_id                    # for channel/message scopes
  content
  content_hash
  line_start
  line_end
  byte_start
  byte_end
  content_format                # markdown, plain, html, pdf-text, docx-text, csv-preview, ndjson
  source_updated_at
  indexed_at
  external_text_version
  projection_version
  search_vector
  metadata jsonb
```

No embedding columns. No embedding provider/model/dimension columns. No vector index. No reranking metadata.

Search planning should be explicit:

```text
VfsTextSearchService::search(pattern, paths, mode, options, context)
  -> resolve path scopes
  -> expand only permitted candidate collections, or build permission-safe source filters
  -> choose backend: live, fts, trigram, metadata, or external-text index
  -> fetch candidate text chunks or live source windows
  -> revalidate each source through the adapter authorizer
  -> return path/snippet/line/chunk metadata with backend diagnostics
```

Backend choices:

| Backend | Best for | Notes |
|---|---|---|
| Live scan | Small docs, memory files, recent task/list pages, bounded channel date buckets, exact `rg` on narrow paths | Reads current adapter content. Best freshness. Must honor VFS budgets. |
| PostgreSQL FTS | Medium/large text corpora and keyword search | Cheap lexical retrieval. Good for `search` or broad keyword queries, not regex equivalence. |
| Trigram / LIKE metadata search | Filename/title/path discovery and fuzzy-ish lexical matching | Useful for `find -name`, path search, and typo-tolerant metadata search. |
| External-text index | PDFs, DOCX, spreadsheets, large uploaded text files, older messages after a separate extraction service has produced text | VFS consumes available text projections. It does not own OCR/Tika-style extraction. `rg` can use index prefiltering but must preserve literal/regex semantics. |

Indexing pipeline:

```text
source changed or external text projection changed
  -> VfsTextIndexSourceJob(source_type, source_id, mount)
  -> adapter renders indexable text projections
  -> adapter includes externally extracted text only when another service already produced it
  -> chunker creates chunks with line/byte offsets
  -> store new chunks with search_vector and metadata
  -> swap old chunks after new text index is ready
  -> emit VfsOperationLog / VfsTextIndexUpdated event
```

Keep all-or-nothing index replacement: generate/store the replacement text chunks before deleting the last good searchable copy. Missing or failed external extraction should not erase existing searchability.

Per-domain indexing policy:

| VFS path | Primary search behavior | Index policy |
|---|---|---|
| `/docs` | live `rg` for narrow paths, FTS/text index for broad lexical search | Index document text and metadata. Preserve folder permission rechecks. |
| `/agents/{slug}/memory` | live `rg` for exact memory lookup, text index for broad lexical recall | Use collections `memory`, `topic`, `peer`, `identity` with `agent_id` scope. Preserve `MemoryScopeGuard`. |
| `/files` | live scan for small text files, external-text index only when another service has produced text for large/binary office docs | Index available text projections and metadata, not raw binary bytes. |
| `/channels` | date-bucket live search for recent messages, indexed text search for history | Source filters must start from channels the agent can access. Never search all messages first. |
| `/tasks` and `/lists` | live/indexed lexical search over title, description, comments, status metadata | Keep latest-first windows and indexed status/agent/month filters. |
| `/tables` | structured filters first, bounded NDJSON text search second | Prefer table APIs for row predicates. Index row exports only when useful and capped. |
| `/tools` | live/generated docs search | Generated docs are small and deterministic. |

Permission filtering must happen before result disclosure. For indexed text search, the index can store source ids and safe filter columns, but VFS result assembly must re-check the source through the adapter authorizer before returning canonical path, friendly path, title, snippet, line context, or metadata. This fixes current broad-search risks such as message search that only checks channel access when a channel id is provided.

Search result shape:

```json
{
  "query": "refund policy",
  "mode": "lexical",
  "backend": "fts",
  "match_type": "keyword",
  "count": 12,
  "returned": 10,
  "truncated": false,
  "results": [
    {
      "path": "/docs/by-id/01HV...",
      "canonical_path": "/docs/by-id/01HV...",
      "source_type": "Document",
      "source_id": "01HV...",
      "chunk_id": "01HW...",
      "line_start": 42,
      "line_end": 44,
      "snippet": "Refund requests are handled through...",
      "version": "sha256:..."
    }
  ],
  "diagnostics": {
    "permission_filtered": 3,
    "binary_skipped": 4,
    "bytes_scanned": 184320,
    "index_stale": false
  }
}
```

Commands and Lua helpers should preserve the split:

```text
rg "invoice-1234" /docs /files              # exact/regex VFS text search
grep -R "invoice-1234" /docs                # exact/regex VFS text search
find /docs -name "*refund*"                 # path/metadata search
```

```lua
local exact = app.vfs.rg("invoice-1234", { "/docs", "/files" }, {
    line_numbers = true,
    limit = 50,
})

local lexical = app.vfs.search("refund policy", { "/docs" }, {
    mode = "lexical",
    limit = 10,
})
```

Semantic retrieval remains separate and explicit:

```lua
local likely = app.retrieval.semantic_search("how do we handle refunds", {
    paths = { "/docs" },
    limit = 10,
})

for _, result in ipairs(likely.results) do
    local verified = app.vfs.rg("refund|return", { result.path }, {
        line_numbers = true,
        limit = 20,
    })
    dump(verified)
end
```

This gives agents both sharp tools and broad recall without muddying the VFS contract: VFS verifies exact facts; embedding tools suggest likely places to inspect.

### Binary, Media, And External Text

Binary behavior should be explicit through `file` and `stat`.

Rules:

- `cat` fails for binary paths unless the adapter exposes a text projection.
- `file` returns MIME type, size, hash, media metadata, preview availability, and whether an external text projection exists.
- `rg` searches external text projections only when they already exist.
- `head` on binary paths returns metadata or a safe preview, not raw bytes.
- Skipped binary files return non-sensitive skipped counts.

Do not make OCR, Apache Tika, PaddleOCR, document conversion, or media transcription part of the VFS implementation. Those should be separate services/jobs later. Once they exist, VFS can expose their outputs as normal permission-checked text projections and can search them through the same lexical paths. The first VFS implementation should avoid dumping opaque binary content into agent context.

### Resource Budgets And Cancellation

Add one `VfsBudget` object to `VfsContext`.

Suggested fields:

```text
max_paths
max_bytes_scanned
max_bytes_returned
max_matches
max_pages
max_line_length
deadline_ms
```

Every adapter receives the budget and must decrement/check it. When a budget is hit, return `truncated = true` with diagnostics instead of timing out silently.

Lua iteration helpers must require explicit `max_pages`, `max_items`, or `max_bytes`. They should not accidentally loop through an entire workspace until the Lua sandbox kills the script.

### Approval UX For Mutations

Risky mutations should create a `VfsMutationPlan` before execution.

The plan should include:

- operation class
- canonical targets
- affected count
- diff summary
- destructive risk
- version tokens
- permission snapshot
- exact domain service/action that will execute
- partial-failure policy

Approval approves the plan, but execution must revalidate permissions, path resolution, versions, and target state. A stale approval should fail cleanly instead of mutating a moved or changed object.

### Path Lifecycle

Canonical paths are durable. Friendly paths are aliases.

Rules:

- `stat` always returns canonical path, backend id, version, and aliases.
- `realpath` resolves friendly paths to canonical paths.
- Deleted canonical ids return `not_found`, with tombstone metadata only if the caller is allowed to know the object existed.
- Renamed friendly paths may be discoverable through `stat`/`realpath`, but writes should prefer canonical paths.
- Collisions between friendly names should use deterministic suffixes or require `/by-id`.

### Concurrency And Idempotency

All writes should accept an optional `idempotency_key`, and patches should require version tokens wherever possible.

For `app.vfs.batch(ops, opts)`:

- Default to per-operation success/failure, not all-or-nothing.
- Return structured results for every operation.
- Support `mode = "transactional"` only when all operations are in one adapter and that adapter can truly transact.
- Include partial-failure behavior in approval previews.

Do not pretend cross-domain transactions exist when they do not.

### Developer And Agent Discovery

Generate VFS discovery docs from the same command/helper registry used at runtime.

Expose:

```text
/tools/vfs.md
/tools/vfs/commands.json
/tools/vfs/lua.json
/tools/vfs/examples.md
```

`lua_read_doc("vfs")` and `/tools/vfs.md` should agree because they are generated from the same registry. Include common recipes so agents learn typed Lua helpers such as `app.vfs.find(...)` instead of scraping `vfs_exec` text output.

### VFS Versus Direct Tools

The clean OpenCompany split:

```text
VFS:
  inspect, search, compare, traverse, patch, bounded export, batch-safe retrieval

Direct domain tools:
  create_task, set_task_status, send_channel_message, run_automation,
  request_approval, contact_agent, integration-specific actions
```

Search/read/list behavior for documents, files, memory, tasks, channels, and tables should be powered by the VFS substrate. Direct tools stay where they express business intent better than editing or moving a virtual file.

---

## Dreaming Later

Dreaming is not part of the first VFS implementation. It is a later background consumer of the same retrieval layer.

Later Dream jobs could use VFS paths such as:

```text
/agents/{slug}/memory/logs/
/agents/{slug}/memory/topics/
/tasks/completed/
/channels/{channel}/messages/
/docs/
```

Potential later passes:

- Memory Dream: consolidate old logs, stale topics, and peer notes.
- Task Dream: extract reusable lessons from completed tasks.
- Channel Dream: extract decisions and durable facts from older conversations.
- Docs Dream: detect stale or conflicting docs.
- Tools Dream: summarize newly added tools and integrations into better agent-facing guidance.

Dream jobs should use the VFS service internally, not call agent tools. The important point is shared semantics: paths, permissions, bounded reads, grep/glob, and patch/write capabilities should mean the same thing whether an agent or a background job uses them.

---

## Suggested Implementation Order

1. Create the VFS subsystem skeleton: contracts, context, router, stat/list/read/search actions.
2. Add canonical ID-backed path support and friendly-alias resolution.
3. Add the shared VFS context and permission gate, including recursive candidate filtering for documents, files, channels, and agent memory.
4. Register a direct VFS tool provider so `vfs_exec`, `vfs_patch`, and `vfs_write` are model-visible without Lua.
5. Add the `app.vfs` Lua namespace and `resources/lua-docs/vfs.md` over the same VFS services, with typed helper result tables, cursor helpers, version/capability fields, and `pcall`-oriented error examples.
6. Add the `vfs_exec` command interpreter with an allowlisted parser, structured result format, read-only command classification, argv quoting, globs, simple read-only command sequences, safe pipelines, and structured unsupported-command suggestions.
7. Implement `/docs` and `/files` adapters first because they already map closely to current services.
8. Support the first broad read command set: `pwd`, `cd`, `ls`, `dir`, `ll`, `tree`, `find`, `grep`, `egrep`, `fgrep`, `rg`, `cat`, `head`, `tail`, `wc`, `stat`, `file`, `du`, `realpath`, `dirname`, `basename`, `sort`, `uniq`, `cut`, `tr`, `nl`, `jq`, safe `sed`, `less`, `more`, `diff`, `cmp`, `comm`, `sha256sum`, `sha1sum`, and `md5sum`.
9. Add explicit unsupported-command and unsupported-flag recovery responses with suggested equivalents.
10. Add streaming/size-aware grep for file contents and line-windowed grep for document content.
11. Add `VfsTextSearchService`, `app.vfs.search`, and the VFS lexical `search` command over live scans plus source-table PostgreSQL indexes first. Add `pg_trgm` where it accelerates path/title/name/content filtering, use existing or new source-table FTS for broad lexical search, and add `vfs_text_chunks` only for generated or very large virtual text. External OCR/Tika/PaddleOCR-style extraction stays outside VFS and can feed searchable text projections later. Keep embeddings in separate retrieval tools/services that can return VFS paths.
12. Add `/agents/{slug}/memory` and `/agents/{slug}/identity` adapters.
13. Add `vfs_patch` with required version/hash checks for domain-backed edits.
14. Add `vfs_write` for valid file-like create/overwrite paths.
15. Add `/tasks` read projection and limited patch/status projection.
16. Add `/lists` read projection and limited patch/status projection.
17. Add large-folder collection semantics for `/tasks`, `/lists`, `/channels`, `/tables`, `/approvals`, and automation histories: counts, cursors, max page sizes, query hints, and safe truncation.
18. Add read-only `/tools`, `/channels`, `/tables`, `/approvals`, and `/workspace` projections with pagination/cursor limits.
19. Add carefully gated command mutations such as `mkdir`, `touch`, `mv`, `cp`, `rm`, `rmdir`, `tee`, and `truncate` only after dynamic command permission classification is implemented.
20. Revisit Dream after the VFS is stable and useful for normal agent retrieval.

This keeps the first milestone useful without building the entire universe at once, while still aiming at a complete VFS rather than a half implementation.
