# Automation context (`ctx`)

Every quickjs-v1 automation receives a JSON-compatible `ctx` data object. It is
a per-run copy: changing it in JavaScript does not persist anything.

```js
ctx.automation_id;    // automation UUID
ctx.automation_name;  // display name
ctx.agent_id;         // agent whose permissions own app.* calls
ctx.channel_id;       // output channel UUID, or null
ctx.run_number;       // 1-based run count
ctx.last_run_at;      // previous ISO timestamp, or null
ctx.last_result;      // previous persisted result/error summary, or null
ctx.trigger_type;     // currently "schedule" for scheduled scripts
ctx.schedule;         // five-field cron expression
ctx.timezone;         // configured IANA timezone
ctx.script_runtime;   // "quickjs-v1"
```

Example:

```js
console.info(`Running ${ctx.automation_name} #${ctx.run_number}`);

var rows = app.tables.get_rows({
  table_id: "00000000-0000-4000-8000-000000000001",
  limit: 25,
});

if (ctx.channel_id && rows.length > 0) {
  app.chat.send_channel_message({
    channel_id: ctx.channel_id,
    content: `Found ${rows.length} rows during run #${ctx.run_number}.`,
  });
}

return { count: rows.length, completed_at: new Date().toISOString() };
```

Do not place secrets in console output or returned values. Integration
credentials are resolved behind `app.*` and are never injected into `ctx`.
