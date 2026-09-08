# Diagnostics and effects

Executions return an execution ID, structured result, captured logs, measured
usage when available, diagnostics and a host effect summary. Missing metrics
are unknown, not zero. Source locations refer to the submitted Ruby file.

`syntax_error` identifies parser failures. `profile_error` identifies unsupported
Ruby capabilities before execution. `ruby_error` identifies an execution or
value-conversion failure. `instruction_limit`, `cpu_limit`, `memory_limit` and
`wall_timeout` terminate work outside Ruby rescue. `engine_unavailable` means an
operator must repair or verify the pinned installation; there is no fallback VM.

Host dispatch may fail because of unknown paths, incorrect keywords, schema
validation, missing permissions, unavailable accounts, approval or provider
errors. Inspect `code_read_doc` and the host trace. Error text must not include
credentials or complete upstream request/response bodies.

```ruby
record = JSON.parse('{"active":false,"count":0}')
{active: record.fetch(:active), missing: record[:optional], count: record[:count]}
```

Use explicit missing checks: `false` and `0` are data. Ruby treats only `nil` and
`false` as falsey. An empty string or array is truthy.

## Safe retry

A failure does not roll back host effects. `writesSucceeded` means confirmed
writes occurred; `writesUnknown` means delivery may have occurred. Do not rerun
an entire effectful script after either disposition. Inspect state, receipts and
supported idempotency keys first. An approval request is not a completed write.
Cancellation and timeout cannot prove that an in-flight remote write was undone.

Validate source repairs without dispatch, then execute only the necessary next
step. Do not catch an error simply to repeat an uncertain mutation in a loop.
