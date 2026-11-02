<?php

namespace App\Domain\Vfs\Spec;

/**
 * Agent-facing VFS guidance text: the system-prompt orientation block and the
 * in-VFS guides served under `/tools/vfs*`.
 *
 * Invariants:
 * - promptBlock() is byte-stable: constant text, no timestamps, ids, counts
 *   or per-run data, so it stays inside the cacheable stable prompt frame.
 *   Keep it under ~200 tokens; details belong in `/README.md` and the
 *   `/tools/vfs*` files it points to. A test asserts stability and size.
 * - Everything here describes behavior the code actually has. When a
 *   contract changes, change this text in the same commit.
 *
 * `/README.md` (root) is Stream C1's; this class owns the tool-level guides.
 */
final class VfsGuide
{
    public static function promptBlock(): string
    {
        return <<<'TXT'
## VFS

Company data is a permission-filtered virtual filesystem: /docs /files /tables /tasks /lists /channels /agents /approvals /workspace /tools. /me is your own area (memory, notes, todo.md, scratch, results).
Loop: find the path (vfs_exec ls, find, rg) -> vfs_read (numbered lines + version) -> vfs_edit for text or vfs_patch for records, passing that version -> vfs_read to verify. vfs_write creates or replaces whole files.
conflict = changed since your read: re-read, re-apply. pending_approval or approval_required is not an error: keep working; the outcome is posted back. Do not retry.
Truncated output says how to continue (cursor, offset); a truncated count is not a total.
Start with cat /README.md or help.
TXT;
    }

    public static function readme(): string
    {
        return <<<'MD'
# VFS tools

The VFS is a permission-aware, unix-like view of OpenCompany workspace data. It is not a host shell: every path is checked against your permissions and writes go through the same policy, approvals and audit as the app's own tools.

## Tools

- `vfs_exec`: run a command line. `ls`, `tree`, `find`, `rg` (regex), `grep` (literal), `cat`, `head`, `tail`, `wc`, `stat`, `jq`, `diff` and more; pipes, `;`, `&&`, `||`, globs, `>` and `>>` into writable files. `rm` moves to the trash; `mv` out of a `.trash/` folder restores. Returns `{ok, exit_code, stdout, cwd}`.
- `vfs_read`: read one file with numbered lines (`     N<TAB>text`), `offset`/`limit` paging, `version` and `total_lines`; paged collection files (`rows.ndjson`, channel day transcripts, `members.json`) page with `cursor`. `raw: true` drops the numbers.
- `vfs_edit`: change text by exact replacement. `edits: [{old_text, new_text, replace_all?}]`, applied in order, all or nothing; `dry_run: true` returns a unified diff.
- `vfs_write`: `create` (default), `overwrite` (needs `version` when it exists) or `append` whole content: files, documents (`/docs/<folder>/<Title>.md`), table rows (`rows/new.json` with `{"data":{...}}`), list items (`new.md` in a project) and your `/me/notes`, `/me/todo.md`. Optional `idempotency_key` makes a retry safe.
- `vfs_patch`: records. Task and list fields `{"status":"completed"}`; table rows `{"data":{"Status":"active"},"base":{"Status":"lead"}}` (send only changed columns; `base` turns a concurrent change into `conflict`).

In Code Mode (`code_exec`) the same operations are `app.vfs.*`: see `/tools/vfs/code.json` and `code_read_doc("vfs")`.

## Edit loop

1. Find the path: `ls`, `find`, `rg -l`. Prefer canonical `by-id` paths for writes.
2. `vfs_read` it and keep the `version`.
3. `vfs_edit` (text) or `vfs_patch` (records) with that `version`.
4. `vfs_read` again to verify.

If the object changed since your read, record patches and overwrites fail with `conflict` (`context.current_version`). Text edits still apply when every `old_text` still matches exactly once (`stale_base: true`); otherwise `conflict`. Read again and re-apply; never overwrite blindly.

## Results

Paths in output (`find`, `grep -r`, `rg`, `vfs_rg`) are readable friendly paths; duplicate names carry a `--1a2b3c4d` suffix, and structured results add the stable `canonical_path`.

Success is `{"ok":true,"result":{...}}` (`vfs_exec` is flat). Failure is `{"ok":false,"error":{"code","message","context"}}`; branch on `error.code`: `not_found`, `not_readable`, `not_writable`, `permission_denied`, `unsupported`, `approval_required`, `invalid_request`, `conflict`, `ambiguous_match`, `internal_error`. `context.hint` says what to do next. Full table: `/tools/vfs/reference.md`.

## Approvals

When a permission needs a human, the write is not performed and an approval is created (`approval_id`). That is not a failure: continue other work; the approved change runs later and its outcome is posted back to the conversation. Do not retry the same call (an identical retry returns the same approval).

## Truncation, cursors and large results

Budgets bound every call (`maxEntries`, `maxBytes`, ...). When output is cut, `truncated` is true and the last stdout line (or `hint`) says how to continue: pass `next_cursor` back as `cursor`, read the next `offset`, or narrow the path. A truncated listing is not a complete count. Very large direct results are saved to your results folder (`/me/results`, i.e. `/files/agents/<you>/.results/`); the reply carries a preview and `offloaded_path` to read with `vfs_read`.

## Permissions

Three capability toggles govern the tools: `vfs_exec` (all reads: exec, read and the Code Mode read helpers), `vfs_patch` (vfs_edit and vfs_patch) and `vfs_write` (vfs_write and mutations, including shell `mkdir`/`cp`/`mv`/`rm`/`>`). Records also need their domain permission: documents `create_document`/`update_document`/`delete_document`, table rows `add_table_row`/`update_table_row`/`delete_table_row`, list items `create_list_item`/`update_list_item`/`delete_list_item`, agent identity files `update_agent_identity_file`. `/docs` writes need an allowed document folder.

## Discovery

`help`, `help <command>`, `<command> --help`, `/tools/vfs/commands/<command>.json`, `/tools/vfs/examples.md`, `/tools/vfs/reference.md`, `/tools/vfs/openapi.json`, `/tools/index.json` (your tool surface).
MD;
    }

    public static function examples(): string
    {
        return <<<'MD'
# VFS examples

## Look around (vfs_exec)

```sh
ls /
cat /README.md
tree /docs -L 2
find /docs -type f -name "*refund*"
rg -n "refund" /docs /files
grep -rn "net 30" /docs
ls /tables/customers/rows/by-id | head -20
cat /tasks/counts.json | jq .open
find /files -name "*.md" | xargs grep -l TODO
```

## Read and edit a document

```json
vfs_read  {"path": "/docs/handbook/refunds"}
          -> {"ok":true,"result":{"content":"     1\t# Refunds\n     2\tRefunds within 14 days.\n","version":"v-abc","total_lines":2,...}}
vfs_edit  {"path": "/docs/handbook/refunds", "version": "v-abc",
           "edits": [{"old_text": "within 14 days", "new_text": "within 30 days"}]}
vfs_read  {"path": "/docs/handbook/refunds"}
```

Preview first with `"dry_run": true` (returns a unified diff, writes nothing). Several edits in one call apply in order, all or nothing.

## Long files

```json
vfs_read {"path": "/files/logs/app.log", "limit": 200}
         -> {..., "truncated": true, "next_offset": 201, "hint": "Showing lines 1-200 of 5310. Continue with offset=201."}
vfs_read {"path": "/files/logs/app.log", "offset": 201, "limit": 200}
```

## Records (vfs_patch)

```json
vfs_patch {"path": "/tasks/by-id/TASK_ID", "patch": "{\"status\":\"completed\"}", "version": "<version>"}
vfs_patch {"path": "/tables/customers/rows/by-id/ROW_ID.json",
           "patch": "{\"data\":{\"Status\":\"active\"},\"base\":{\"Status\":\"lead\"}}"}
```

Send only the columns you change; `base` holds the values you read, so a concurrent change fails with `conflict` instead of being overwritten.

## Files (vfs_write)

```json
vfs_write {"path": "/files/reports/q3.md", "content": "# Q3\n"}
vfs_write {"path": "/files/reports/q3.md", "mode": "overwrite", "version": "<version>", "content": "# Q3 final\n"}
vfs_write {"path": "/files/logs/run.log", "mode": "append", "content": "step 2 done\n"}
```

## Paged collections

```json
vfs_read {"path": "/tables/customers/rows.ndjson", "limit": 100}
         -> {..., "truncated": true, "next_cursor": "c1..."}
vfs_read {"path": "/tables/customers/rows.ndjson", "limit": 100, "cursor": "c1..."}
```

## Code Mode (code_exec, mruby)

```ruby
page = app.vfs.read(path: "/docs/by-id/DOC_ID", limit: 400)
v = page[:result][:version]
app.vfs.edit(path: "/docs/by-id/DOC_ID", version: v, edits: [{old_text: "draft", new_text: "final"}])
hits = app.vfs.rg(pattern: "refund", paths: ["/docs"], max_matches: 20)
text = app.vfs.cat(path: "/files/report.md")[:result][:content]   # raw text
app.vfs.write(path: "/files/generated/summary.md", content: "# Summary\n", mode: "create")
```
MD;
    }
}
