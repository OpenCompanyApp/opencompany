# Errors, repair, and retries

Capability failures throw `OpenCompanyError` inside JavaScript. Catch errors
only when the program has a meaningful local fallback:

```js
try {
  return app.docs.get_document({
    document_id: "00000000-0000-4000-8000-000000000002",
  });
} catch (error) {
  if (error.name !== "OpenCompanyError") throw error;

  console.warn({
    type: error.type,
    message: error.message,
    retryable: error.retryable,
    details: error.details,
  });
  return { found: false };
}
```

The outer `code_exec` result reports:

- `type`: stable category such as `syntax_error`, `unknown_function`,
  `invalid_arguments`, `timeout`, `memory_limit`, or `tool_error`
- `line` and `column`: direct user-source location when available
- `suggestion`: one concrete repair action
- `retryable`: whether re-running the whole program is safe
- `effectStatus`: `none`, `succeeded`, or `unknown` for write effects
- `executionId`: durable trace correlation ID

## Pre-effect validation

The bridge validates known required fields, unknown fields, primitive types,
enum values, and portable size/range constraints before calling a provider:

```js
// Fails as invalid_arguments before an external call if `state` is not allowed.
app.tasks.update_task({ task_id: "fake-task-id", state: "invented" });
```

Use `code_read_doc` to repair the named object. Error details never echo argument
values, which keeps credentials and customer data out of diagnostics.

## Runtime limits

CPU, memory, stack, source, result, console, callback-count, and callback-wall
limits are enforced independently. JavaScript cannot catch native CPU, memory,
or stack termination and continue running. Split large work into bounded pages
and return compact summaries.

## Retry decision

- Read-only execution with no writes: retry only after repairing the cause.
- Validation failure: safe to edit and validate again; nothing ran.
- Confirmed write success followed by a later error: do not rerun blindly.
- Failed write with `effectStatus: unknown`: inspect provider state first.
- Use provider-supported idempotency keys for create/payment/message operations.

Code Mode never automatically retries a whole script automation. This prevents
an interrupted run from duplicating external effects.
