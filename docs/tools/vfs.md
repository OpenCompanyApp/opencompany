# VFS Runtime Reference

The runtime also exposes this reference at `/tools/vfs.md` and `/tools/vfs/README.md` inside the virtual filesystem.

The VFS is a permission-aware unix-like filesystem over OpenCompany workspace data. Use `vfs_exec` for command-shaped inspection, `vfs_patch` for targeted edits, `vfs_write` for raw `/files` writes, and `app.vfs.*` Lua helpers for structured workflows.

`/docs` patching requires an explicit allowed document-folder scope. Read visibility alone does not imply patch capability.

## Commands

- `grep` is literal; `rg` is regex.
- Unquoted path globs expand with normal `*`, `?`, and character classes against one parent directory. Quoted globs stay literal. `**`, brace expansion, `$VAR`, command substitution, process substitution, and host programs are intentionally unsupported.
- Redirection support is explicit: `>`, `>>`, `1>`, and `1>>` write stdout to `/files`; `/dev/null` discards stdout; `2>/dev/null` suppresses stderr; `2>&1` merges stderr into stdout with shell-like order sensitivity. Stderr file targets such as `2>/files/error.txt` are rejected.
- `jq` is a safe subset: `.`, `.field`, `.[0]`, `.items[]`, `.items[].name`, `.["key"]`, `length`, sorted `keys`, `has("key")`, `map(.field)`, `select(.field == "value")`, simple numeric arithmetic, stdin, NDJSON, and file arguments. `-r` is accepted; scalar output is raw by default. `-c` emits compact JSON.
- `cp`, `mv`, `rm`, `mkdir`, `touch`, `tee`, and `truncate` mutate `/files` only and require `vfs_write` permission.

## Budgets

`vfs_exec` accepts `maxEntries`, `maxDepth`, `maxBytes`, `maxFiles`, and `maxMatches`. Structured helpers accept camelCase and snake_case aliases. Treat returned counts as sampled unless `count_is_exact` is true; inspect `returned`, `limit`, `truncated`, `skipped`, `count_mode`, `sampled_count`, and `total_count`. `vfs_ls`, `vfs_find`, and `vfs_count` return `next_cursor` when more bounded results are available; pass it back as `cursor` to continue.

## Lua

Lua helpers return `{ ok, result = ... }`, mirror result fields at the top level, and expose list-like results as numeric entries so `#res` works. `app.vfs.write(path, content)` defaults to overwrite for script ergonomics; direct `vfs_write` defaults to create. Generated code should pass `mode = "create"` or `mode = "overwrite"` explicitly.
