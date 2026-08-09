# Code Mode overview

OpenCompany Code Mode runs synchronous JavaScript function bodies in an
isolated QuickJS runtime. It is designed for deterministic orchestration:
inspect a small amount of data, transform it, call permission-scoped tools, and
return a compact result.

## Discovery-to-execution loop

1. `code_list_docs` discovers the namespaces visible to the active agent.
2. `code_search_docs` finds likely functions by intent.
3. `code_read_doc` provides exact inputs, effects, return notes, and examples.
4. `code_exec` with `mode: "validate"` compiles without running code or exposing
   `app.*`.
5. `code_exec` with `mode: "execute"` runs the validated program.
6. Inspect the execution ID, error contract, and write-effect status before any
   retry.

Do not guess raw upstream API shapes. When return metadata is incomplete, make
one minimal read-only call and inspect it before writing multi-step code.

## Program shape

The source is a strict JavaScript function body. Top-level `return` is valid:

```js
var rows = app.tables.get_rows({
  table_id: "00000000-0000-4000-8000-000000000001",
  limit: 10,
});

console.log({ count: rows.length });
return rows.map((row) => ({ id: row.id, title: row.title }));
```

All capability calls are synchronous. `app.*` functions accept one named object
unless their documentation explicitly describes a positional form.

## Namespaces

```text
app.{namespace}.*            OpenCompany workspace capabilities
app.integrations.{name}.*    Connected integration capabilities
app.mcp.{server}.*           Enabled MCP server capabilities
```

Namespaces and functions are filtered by the active agent's workspace and
permissions. The sandbox does not bypass OpenCompany authorization, approval,
credential, or account-alias checks.

## Standard output

Use standard console methods:

```js
console.log("ordinary output", { count: 3 });
console.info("progress");
console.warn("recoverable condition");
console.error("handled failure");
```

`print(...values)` aliases `console.log`. `dump(value)` logs a readable value and
returns it. Circular objects, functions, symbols, BigInts, and errors receive
safe inspection strings. Output and log counts are bounded by the active host
profile; truncation is explicit in the result.

JavaScript's native `JSON.parse`, `JSON.stringify`, and `RegExp` are available.

## Runtime boundaries

The runtime intentionally has no filesystem, network, process, environment,
module loader, dynamic native module, bytecode, timer, or Promise-job authority.
Use documented `app.*` functions for approved external operations.

| Profile | JS CPU | Memory | Stack | Capability calls | Console output |
|---|---:|---:|---:|---:|---:|
| Agent | 5 s | 32 MiB | 512 KiB | 50 | 64 KiB |
| Automation | 10 s | 32 MiB | 512 KiB | 100 | 128 KiB |
| Developer console | 5 s | 32 MiB | 512 KiB | none | 128 KiB |

Provider-call wall time is measured separately because it is not JavaScript CPU
time. Agents cannot raise these limits in `code_exec`; profiles are host-owned.

## Values crossing the boundary

Use JSON-compatible values: `null`, booleans, finite numbers, strings, arrays,
and plain objects. Supported 64-bit integers may cross as BigInt. Functions,
symbols, Promises, proxies, Date, Map, Set, cycles, accessors, symbol-keyed data,
and oversized/deep graphs are rejected rather than silently flattened.

## Effects and retries

Every capability call records its read/write effect. A failed write can be
reported as `unknown` when the provider did not confirm whether the external
effect happened. Never retry the whole program when write effects are
`succeeded` or `unknown`; inspect external state or use an idempotency key first.

See `errors`, `context`, `examples`, and `web` for focused guides.
