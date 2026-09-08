# Automation context

Script automations receive a frozen, data-only `ctx` Record. It is not executable
bootstrap source and cannot override the host's workspace or actor authority.

Fields are `automation_id`, `automation_name`, `agent_id`, `channel_id`,
`run_number`, `last_run_at`, `last_result`, `trigger_type`, `schedule`, `timezone`
and `script_runtime`. Optional fields may be `nil`; timestamps are ISO strings.
Changing a context value never persists application state.

```ruby
puts("Running", ctx[:automation_name], ctx[:run_number])
{run_number: ctx[:run_number], previous: ctx[:last_run_at], runtime: ctx[:script_runtime]}
```

Saving a script requires explicit Ruby submission and no-execution validation.
Admission binds the exact source, profile and engine artifact. A source/build
change requires validation again. Legacy source is preserved and schedules are
disabled; metadata edits do not infer or translate its language.

Do not log context or provider data wholesale. Return only the fields needed for
the task, particularly when a prior result may contain private information.
