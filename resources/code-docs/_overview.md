# Code Mode: restricted Ruby

The only scripting profile is `opencompany-code-v1`, using the pinned mruby
engine. Submit an ordinary top-level Ruby script. Its final expression is the
structured result. Calls are synchronous; blocks and small helper methods work.
This is not CRuby, Rails, Bundler, or a durable workflow engine.

## Discover, describe, validate, execute

1. Use `code_list_docs` and `code_search_docs` to find permitted capabilities.
2. Read the exact function contract with `code_read_doc` before writing a call.
3. Use `code_exec` with `mode: "validate"` for compilation/profile admission.
   Validation never evaluates source or dispatches a tool. It is not proof that
   a provider accepts the arguments, an account exists, or a write is authorized.
4. Execute a bounded batch and return a small summary.
5. Inspect diagnostics and confirmed/ambiguous effects before any retry.

```ruby
rows = [{id: 1, active: true}, {id: 2, active: false}]
selected = rows.select { |row| row[:active] }
puts("Selected", selected.length)
{count: selected.length, ids: selected.map { |row| row[:id] }}
```

## Host capabilities

Discoverable paths start with `app`, for example `app.tables.get_rows` or
`app.integrations.<provider>.<function>`. Use keyword arguments. Account aliases
are granted by the host; a guessed alias never grants credentials or authority.
For Ruby-reserved or hyphenated names, use `app.call("exact.catalog.path", key: value)`.
Omit the leading `app.` inside that string. This uses the same permission path.
Function docs also expose an exact tool-slug capability path. Prefer it in saved
scripts: it remains stable when permissions change the visible set of short
names. When several endpoints share a display name, discovery uses their exact
slugs instead of guessing which endpoint you intended. Exact paths retain the
same keyword schema, account scope and authorization checks as short names.

The host rechecks workspace, actor, connection and tool permissions. Credentials
are not injected into the guest. Never print secrets obtained from a permitted
tool: logs and final results are visible to the execution's audience.

## Values and supported Ruby

Use `nil`, booleans, signed 64-bit integers, finite floats, UTF-8 strings, arrays
and hashes. `{}` and `[]` remain distinct. Symbol keys normalize to strings;
duplicate string/symbol keys are rejected. Cycles, non-finite numbers and objects
such as procs cannot cross the boundary. Large unsupported integers are rejected.

Incoming objects are frozen `Record` values: use `record[:id]`, `record["id"]`,
`fetch`, `keys`, `values` or `to_a`. A missing `[]` returns `nil`; `fetch` without a
fallback raises. Dot fields are convenient only when they do not collide with
Ruby methods; brackets always address data. Nested input arrays and strings are
also frozen. Build a new hash/array when transforming data.

Bounded `JSON.parse` and `JSON.generate` are pure helpers. `puts` and `p` capture
structured values separately from the final result. Unsupported values raise;
they are not silently stringified through user-defined serialization hooks.

There is no filesystem, direct network, process, environment, clock, randomness,
dynamic loading/evaluation, threads, fibers, class definitions, monkey-patching
or arbitrary gems. Regular expressions are not part of this qualified profile.
Supply timestamps as explicit data and use approved `app.*` tools for I/O.

## Host-owned budgets

All profiles have a 32 MiB VM allocation budget and a separate 256 MiB process
ceiling. CPU time is independently limited to 1 second, including compiler and
native work. Wall limits are 45 seconds for agents/automations and 5 seconds for
the no-capability developer console. Agents get 50 calls; automations get 100.
Instruction, source, output, result and protocol byte limits apply as well.
Budget exhaustion cannot be rescued to extend execution. Split large work into
bounded pages, preserve cursors explicitly, and return summaries rather than raw
provider responses. Host configuration may tighten these ceilings.

Read `errors`, `context`, `examples` and `web` for focused guides.
