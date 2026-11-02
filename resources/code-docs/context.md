# Routine context

Script routines receive a frozen, data-only `ctx` Record. It is not executable
bootstrap source and cannot override the host's workspace or actor authority.

Fields are `routine_id`, `routine_name`, `agent_id`, `channel_id`,
`run_number`, `last_run_at`, `last_result`, `routine_version`, `schedule`,
`timezone` and `script_runtime`. Optional fields may be `nil`; timestamps are ISO strings.
Changing a context value never persists application state.

```ruby
puts("Running", ctx[:routine_name], ctx[:run_number])
{run_number: ctx[:run_number], previous: ctx[:last_run_at], runtime: ctx[:script_runtime]}
```

Saving a script requires explicit Ruby submission and no-execution validation.
Admission binds the exact source, profile and engine artifact. A source/build
change requires validation again, and any source change increments the routine
version. Stale source is preserved and its schedule disabled; metadata edits do
not infer or translate its language.

A routine's schedule is a trigger, not consent: the script runs with the
agent's ordinary permissions and approval policy.

Do not log context or provider data wholesale. Return only the fields needed for
the task, particularly when a prior result may contain private information.
