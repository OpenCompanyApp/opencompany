# JavaScript examples

These examples demonstrate bounded orchestration patterns. Read each function's
current page with `code_read_doc` before adapting it; enabled integrations and
schemas vary by workspace.

## Inspect, transform, return

```js
var rows = app.tables.get_rows({
  table_id: "00000000-0000-4000-8000-000000000001",
  limit: 50,
});

var summary = rows.reduce((counts, row) => {
  var status = row.status || "unknown";
  counts[status] = (counts[status] || 0) + 1;
  return counts;
}, {});

console.log(summary);
return summary;
```

## Page a read before acting

```js
var cursor = null;
var selected = [];

for (var pageNumber = 0; pageNumber < 3; pageNumber++) {
  var page = app.integrations.example.list_records({
    limit: 100,
    cursor,
  });

  selected.push(...(page.data || []).filter((record) => record.status === "ready"));
  cursor = page.next_cursor || null;
  if (!cursor) break;
}

return selected.slice(0, 20).map(({ id, name }) => ({ id, name }));
```

`app.integrations.example` is illustrative. Search for the connected provider's
actual namespace and paging fields before use.

## Validate all inputs before writes

```js
var candidates = app.tables.get_rows({
  table_id: "00000000-0000-4000-8000-000000000001",
  limit: 20,
});

var invalid = candidates.filter((row) => !row.id || !row.title);
if (invalid.length > 0) {
  throw new Error(`Refusing writes: ${invalid.length} rows are incomplete.`);
}

for (const row of candidates) {
  app.tables.update_row({
    table_id: "00000000-0000-4000-8000-000000000001",
    row_id: row.id,
    data: { reviewed: true },
  });
}

return { updated: candidates.length };
```

## Automation summary

```js
var rows = app.tables.get_rows({
  table_id: "00000000-0000-4000-8000-000000000001",
  limit: 50,
});

var done = rows.filter((row) => row.status === "done");
var open = rows.filter((row) => row.status !== "done");
var lines = [
  `**${ctx.automation_name} — run ${ctx.run_number}**`,
  `Completed: ${done.length}`,
  `Open: ${open.length}`,
];

if (ctx.channel_id) {
  app.chat.send_channel_message({
    channel_id: ctx.channel_id,
    content: lines.join("\n"),
  });
}

return { completed: done.length, open: open.length };
```

## Read-only fallback

```js
var result;
try {
  result = app.integrations.example.get_record({ id: "fake-record-id" });
} catch (error) {
  if (error.name !== "OpenCompanyError" || !error.retryable) throw error;
  console.warn("Primary read failed; returning an empty result.");
  result = null;
}

return { record: result };
```

Do not add an in-script retry loop around writes unless the provider operation
has a documented idempotency key and the same key is reused for every attempt.
