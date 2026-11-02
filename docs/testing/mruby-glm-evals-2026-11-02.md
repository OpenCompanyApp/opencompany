# mruby Code Mode live evals on GLM (2026-11-02)

Status: Evidence log for one dated run. Re-run before relying on it for a release.

## What this measures

`tests/Evals/CodeModeGlmEvalTest.php` drives the **real production pipeline** end to end:
human chat message → `AgentRespondJob` → `RespondToChatMessage` → durable `AgentRun` →
`OpenCompanyAgent` on `z:glm-5.1` (Z.AI coding plan), with the agent's normal tool set,
the real pinned mruby engine (`bowerbird-ruby-engine` v0.1.0-rc.1, Linux AMD64), real
`app.*` capabilities and a seeded in-memory workspace. Nothing is mocked except broadcast
events.

Each case is scored by an **objective check against the seeded data** (exact totals, exact
document set, exact created records, exactly one row changed), never by model
self-assessment. Transcripts (every tool call, Ruby source, bridge calls, errors, final
reply) are written to `storage/app/evals/code-mode/<run>/<case>.json`.

The suite is outside the `Unit`/`Feature` testsuites, so CI never calls a paid model. Run it
explicitly:

```sh
GLM_API_KEY=... RUBY_ENGINE_BINARY=/abs/path/ruby-engine php artisan test tests/Evals
# optional: EVAL_MODEL=provider:model to evaluate another catalog model
```

## Cases

| Case | Capability exercised |
|---|---|
| `table_aggregate` | Find a table by name, page all rows, filter and sum in Ruby |
| `table_group_by_owner` | Group-by and sort over records |
| `docs_search` | Find documents by content (VFS or Code Mode) |
| `create_followup_tasks` | Read + one write per matching record, with task-owned write receipts |
| `update_table_row` | Targeted write to exactly one row |
| `large_table_paging` | Correct count over a 260-row table (multi-page) |

## Results

| Run | Pass | `code_exec` errors | Notes |
|---|---:|---:|---|
| First run (before fixes) | 0/1 attempted | 3 in one case | Correct number computed, but reply lost |
| Final run (after fixes) | **6/6** | 1 (recovered) | 1 doc read per case typical |

Final-run detail: `table_aggregate` 2 executions / 0 errors; `table_group_by_owner` 3 / 0;
`docs_search` used `vfs_exec` directly; `create_followup_tasks` 6 / 0 (created a board
project and three items); `update_table_row` 3 / 0; `large_table_paging` 3 / 1 (iterated a
Record with `each`, recovered next execution).

## Defects and gaps the evals found, and the fixes

| Finding | Fix |
|---|---|
| Agent marked its own running chat task `completed` via `set_task_status`; the next checkpoint treated it as external cancellation and **the reply was dropped** | `SetTaskStatus` records the reported result but never terminates the current run's task; tool description clarifies; task tools now workspace-scoped |
| Code Mode traces missing from task steps inside durable runs (tools are wrapped by `RunGuardedTool`) | `CheckpointToolCall` unwraps wrappers before reading `lastExecutionMetadata()` |
| Docs said `get_rows` returns "provider-defined JSON-compatible value"; GLM spent 3 failed executions discovering the shape | Table tools declare `returns` contracts (array of rows keyed by column name + `_id`, page size, paging rule) |
| CRuby habits fail in the restricted profile (`&:sym`, `format`, `%`, Regexp, blockless enumerators, `Record#each/dig/to_h/merge`, `puts` of non-JSON values) | "Ruby pitfalls" table in `resources/code-docs/_overview.md`; targeted repair hints from `MrubySandboxService` for each error |
| `app.vfs.exec` is rejected by the profile (`exec` is reserved) | `app.vfs.shell` alias (and `app.call("vfs.exec", ...)`) |
| `rg --files-with-matches` unsupported in the VFS shell | GNU long-flag synonyms for `grep`/`rg` |
| Table paging recipe absent | Paging + group-by recipes in `resources/code-docs/examples.md` |

## Limits of this evidence

- One model (GLM 5.1), one run after fixes; no variance estimate. A second full run was
  deferred because the shared GLM 5-hour quota was at 91%.
- Seeded synthetic data; no external integrations, MCP servers or approval-required writes
  were exercised live (those paths are covered by deterministic tests).
- Latency per case was 35–115 s on the coding-plan endpoint without streaming.
