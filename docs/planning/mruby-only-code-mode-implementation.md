# mruby-only Code Mode implementation plan

Status: Implementation in progress; publication is not complete. See the
[current evidence and remaining gates](mruby-implementation-progress.md).
Verified: 2026-09-08 against the primary OpenCompany checkout, the separate QuickJS worktrees,
the OpenFactory/Bowerbird Ruby specifications and crate inventory, and current upstream mruby docs.

## Decision and scope

Replace Lua/Luau and the proposed QuickJS runtime with one restricted Ruby scripting profile,
`opencompany-code-v1`, implemented by a pinned, hardened mruby guest. This covers agent Code Mode,
script automations, the Code Console, validation, discovery, generated examples and runtime packaging.
There is no language selector, compatibility executor, automatic source translation or fallback VM.

Baseline decision (confirmed by the user): keep the running checkout's uncommitted
VFS/run-control work separate. The mruby feature is based on `dev`; references
below to newer lifecycle/VFS contracts describe later reconciliation boundaries,
not authorization to import that work or switch the running checkout implicitly.

Laravel/PHP remains the application host. Vue/TypeScript, browser JavaScript, Node build tooling and
the bounded VFS command interface remain; they are not alternate Code Mode engines. This project
does not introduce CRuby, Rails, Bundler, arbitrary gems or the full Bowerbird durable control plane.

The accepted [Bowerbird relationship](../architecture/bowerbird-durable-automation-integration.md)
requires a shared engine and separate hosts. OpenCompany must work without a Bowerbird service,
account, database or network connection. Build the reusable engine first; do not build an unrelated
in-process PHP mruby extension. A PHP subprocess adapter installs the engine into the PHP application
workflow without tying interpreter artifacts to each PHP ABI.

## Verified starting point

- The primary checkout is `feat/vfs`, with extensive existing, uncommitted VFS, run-control and
  documentation work. Existing execution paths still reference `LuaSandboxService` and `LuaBridge`.
- `../opencompany-quickjs` is a clean `feat/quickjs` worktree at `d7bd4eb`; it contains useful
  engine-neutral Code Mode/AX work. `../integrations-quickjs` is at `e83193d0c`.
- On this inspection OpenCompany PR #7 is open and **not draft**; integrations PR #3 is open and
  draft. Their status is time-sensitive. Neither was modified for this plan.
- `../quickjs-sandbox` contains the Rust/PHP QuickJS extension. It is not the proposed Ruby engine.
- `/Users/rutger/Projects/openfactory` contains accepted Bowerbird Ruby architecture, schemas and
  fixtures. Its `EMBEDDED_RUBY_RUNTIME.md` explicitly identifies the guest as unimplemented;
  the inspected crate inventory does not contain the proposed mruby runtime crates.
- The primary automation executor has newer `ActiveAgentRun`, pause/cancel, run identity and
  semantic-retry behavior than the QuickJS branch. Porting that branch wholesale would regress it.

## Target boundary

```text
Agent tools / Code Console / script automations
                 |
OpenCompany Code service (PHP)
  workspace + actor + run identity + policy + approvals + trace
                 |
bounded, versioned subprocess protocol
                 |
shared Ruby runner/supervisor
                 |
fresh OS-isolated mruby guest per validation/execution
                 |
typed app.* request -> PHP authorization -> existing tool/integration invoker
```

The guest receives neither credentials nor trusted workspace/actor selectors. PHP owns authority;
guest requests cannot override it. The engine owns compilation, value conversion, metering,
diagnostics and guest lifetime, not OpenCompany permissions or Bowerbird durability.

## Phase 0 — freeze contracts and repository boundaries

1. Inventory every executable script path, persisted script field, controller, job, tool, prompt,
   config key, dependency, editor, fixture, installer and image reference. Include MCP, VFS,
   app-local tools and integration docs. Produce a checked migration manifest with owners.
2. Recheck `dev` and both QuickJS PRs. Plan a new OpenCompany `feat/mruby` worktree against current
   `dev`, plus a coordinated integrations branch against its current base. Reconcile dependency
   on the in-flight run-control/VFS changes explicitly; do not collect the dirty primary checkout
   into the migration. Reuse neutral QuickJS work selectively.
3. Establish the shared engine's repository/package ownership and explicit license boundary with
   the Bowerbird architecture. Suggested deliverable: independently distributable
   `bowerbird-ruby-engine`, with raw C bindings, safe Rust ownership, compiler, guest, supervisor,
   protocol and conformance fixtures. Package names remain proposals until that boundary is frozen.
4. Pin upstream source commit, hardening patch set, mrbgems/dependencies, build flags, compiler,
   guest protocol, host ABI and `opencompany-code-v1` profile. mruby 4.0.0 is the current upstream
   stable candidate, not an instruction to override a qualified shared-engine pin.
5. Define a local protocol profile sharing the Bowerbird value/guest core. Do not label a local
   execution as a durable Run, fabricate a Program Lock, or silently weaken the existing
   `bwb-ruby-guest-v1` schema. Add a separately versioned local envelope where required.
6. Freeze the supported OS/architecture matrix and enforceable sandbox backend for each. Require
   Linux production and this Mac's architecture. No unsupported platform gets an unsafe fallback.
7. Freeze numeric limits and performance/AX thresholds before implementation benchmarking.

Exit: reviewed contracts, complete migration manifest and executable acceptance-test specifications.

## Phase 1 — implement and qualify the shared mruby foundation

- Build a minimal pinned mruby distribution from an explicit mrbgem allowlist; audit transitive
  dependencies. Exclude ambient I/O, sockets, environment, processes, native extension loading,
  dynamic `require`/`load`/`eval`, uncontrolled threads/fibers and reflection into host authority.
- Support ordinary bounded Ruby expressions, methods, blocks, Enumerable operations, arrays,
  hashes, interpolation and exception handling needed by the published profile. Explicitly qualify
  regex and other potentially long-running native operations before exposing them.
- Enforce the restricted profile using compiler/admission checks and a capability-minimal runtime,
  not regex blacklists. Prevent modification of host API authority objects. No user bytecode input.
- Compile in a fresh bounded no-authority guest. Validation must not run user class bodies,
  callbacks or startup hooks. Execution uses a separate fresh guest and the exact admitted build.
- Isolate C/FFI in one component: protected mruby error boundaries, no C longjmp or Rust panic
  across unsafe language boundaries, correct GC roots, allocator ownership and reliable teardown.
- Meter compilation and execution: instruction/CPU, heap and total process memory, stack/recursion,
  symbol/string/collection growth, source, input, output, diagnostic and protocol bytes. Include
  native operations and serialization in the threat model, not just VM instruction dispatch.
- The supervisor enforces hard wall deadlines, process memory limits, cancellation and cleanup.
  Apply OS-level filesystem/network/process denial in addition to interpreter restrictions.
  A child process alone is crash containment, not sufficient sandboxing.
- Sanitize environment, working directory and inherited descriptors; never invoke source through
  a shell command. Separate protocol output from bounded user logs and diagnostics.
- Use framed, size-bounded protocol messages with build/profile identity, execution identity,
  monotonic call sequence and validated payload schemas. Reject stale/cross-execution/duplicate
  messages, malformed lengths, protocol desynchronization and unknown commands.
- Fuzz parser/compiler, value bridge and protocol. Run sanitizers and adversarial tests for VM
  crashes, allocation bombs, recursion, rescue loops, hostile strings and cross-run state leakage.

Exit: a real runnable guest, passing containment tests on each supported platform, reproducible
artifacts and standalone smoke/conformance tests. Bowerbird need not implement durable execution
before OpenCompany can use this shared artifact.

## Phase 2 — define the Ruby authoring and value contract

- Keep simple top-level scripts: final expression is the result. Do not require workflow classes
  for ordinary Code Mode. Any internal wrapper must preserve user source locations.
- Calls use discoverable namespaces and keyword arguments, for example
  `app.integrations.nocodb.records.list(connection: :operations, table_id: "releases", limit: 20)`.
  Generate method names from the real catalog; provide an explicit string-addressed call for
  reserved words or non-Ruby identifiers, using the same policy path.
- Recommended data projection: frozen capability-free `Record` objects for schema objects,
  explicit string/symbol `[]`/`fetch` access and arrays for lists. Schema-declared fields can have
  generated readers only when collision-free. Arbitrary keys remain data, never dynamic authority.
  Freeze the exact projection jointly with the shared engine in phase 0.
- Canonical wire objects have string keys; symbols are authoring conveniences, not a storage
  format. Normalize outgoing keyword hashes, rejecting duplicate string/symbol key collisions.
  Never blindly intern external keys as symbols.
- Specify and test `{}` versus `[]`, missing versus `nil`, `false`, integer bounds, decimals,
  non-finite numbers, UTF-8, binary references, timestamps, cycles and excessive nesting. Reject
  unsupported values with source-aware errors; no silent coercion or arbitrary Ruby serialization.
- Provide explicit bounded JSON/log helpers where needed. No ambient clock or randomness; any
  supported host values are explicit and documented. Do not promise durable determinism locally.
- Publish a short supported/unsupported Ruby reference. Generated signatures and editor stubs
  describe this exact profile, not the entire Ruby/Rails ecosystem.

Exit: golden examples, positive/negative fixtures and shared value-contract tests all execute on
the pinned mruby guest, not merely on CRuby.

## Phase 3 — integrate the PHP host and existing permission boundary

- Add language-neutral `CodeSandbox`/execution interfaces with one mruby implementation, a runner
  client and typed request/result/error/usage DTOs. Keep public tools named `code_*`.
- Port useful catalog, bridge, trace and structured result work from QuickJS; replace all engine
  and JavaScript-specific assumptions. Never patch durable integration fixes into `vendor/`.
- Every callback re-enters the real ToolRegistry/invoker, workspace scope, connection selection,
  actor permission and approval checks. Enforce argument/result schemas, tool call count, byte
  limits and per-call/aggregate deadlines. Discovery must not leak inaccessible tools or data.
- Preserve approval semantics: an awaiting approval is not completed execution. Resume/re-entry
  cannot blindly rerun an effectful script from the beginning or duplicate the approved write.
- Check cancellation before each dispatch. Use actual provider/transport deadlines: detecting
  elapsed time after a call returns is not hard timeout enforcement. Killing a guest does not
  cancel or undo a remote write already dispatched.
- Persist call intent in the existing run/tool-execution authority before mutations; correlate
  sequence, source digest, canonical request, receipt and disposition. Reuse idempotency machinery
  instead of creating an independent competing execution journal.
- Distinguish confirmed, rejected and ambiguous effects. Preserve confirmed partial effects when
  later Ruby code fails. Unknown write outcomes require reconciliation, never generic retry advice.
- Return stable diagnostics with code, message, source location where available, call identity,
  repair suggestion, retryability, effect status, redacted logs and usage. Do not invent locations.

Exit: the same authorization, isolation and effect-safety fixtures pass through direct tools and
Ruby, including native app tools, integrations, MCP and VFS.

## Phase 4 — complete agent experience (AX)

- Keep the discover -> describe -> validate -> execute workflow with compact, searchable tool
  names, precise keyword schemas, return shapes, permissions, effect classification and bounds.
- Generate Ruby snippets, signatures, completion metadata and documentation from one catalog.
  Include real pagination, empty results, missing fields, result inspection and mutation examples.
- Validation checks syntax/profile and statically resolvable API/schema mistakes with no tool
  dispatch. Label dynamic checks as runtime-only; validation is not proof of permission or success.
- Provide errors agents can repair: nearest valid method/keyword, expected type, missing required
  arguments, useful source excerpt and a small valid replacement example where reliable.
- Include runtime/profile identity and remaining budgets in context/results. Bound returned data;
  report truncation explicitly with supported cursor/reference recovery rather than silent loss.
- Clearly explain synchronous execution, final-expression results, frozen input data, available
  helpers, no npm/gems/imports, and safe retry behavior. Update system-context assembly, not a
  fictional single static system prompt.
- Preserve trace/effect summaries, confirmed partial results and secret redaction. Make denied,
  pending approval, cancelled, timed out and ambiguous outcomes visibly different.
- Build a reproducible AX task corpus using mocked providers: discovery, joining/filtering data,
  bounded pagination, VFS, MCP, approvals, missing connections, schema repair and failure recovery.
  Run it against the QuickJS baseline and mruby with matched models/settings. Record first-pass
  success, bounded-repair success, tool turns, token cost, latency and unsafe retry behavior.
- Proposed quality gate: no material regression on the agreed baseline, all documented examples
  validate, and zero permission bypasses or automatic ambiguous-write retries in the safety corpus.
  Numeric sample sizes and tolerances are fixed in phase 0; do not claim optimal AX from syntax taste.

Exit: generated artifacts agree with runtime contracts and the pinned evaluation report passes.

## Phase 5 — automations, console and persisted source migration

- Route agent Code Mode, automation execution and Code Console through the same engine and
  normalized result contract. Use explicit profiles/budgets, not three divergent implementations.
- Integrate with the current `ExecuteScriptAutomation`, `ResolveAutomationRun`, `ActiveAgentRun`
  and guarded tool execution. Preserve task/run/output correlation, pause/cancel handling,
  duplicate delivery handling and terminal-state reconciliation.
- Separate queue transport retry/lock release from semantic script re-execution. The current
  job's `tries = 0` participates in bounded queue lifecycle; do not mechanically replace it with
  the older QuickJS job behavior. Verify the intended run-attempt and timeout policies together.
- Enforce timeout nesting: guest work plus host callback budget below worker deadline, below
  queue visibility/lock expiry with margin. The inspected QuickJS automation callback allowance
  can exceed the current 60-second job timeout; those values cannot be copied unchanged.
- Update Ruby editor highlighting, completions, validation markers, examples, run controls,
  logs/results, trace display, accessibility, dark mode and responsive behavior. Preserve the
  Console's intended capabilities; do not accidentally grant agent-level tools to a pure console.
- Version persisted source with language/profile/build-validation metadata. Unknown or absent
  legacy language metadata is not evidence that a script is Ruby.
- Inventory old Lua/JavaScript scripts, export or preserve immutable source history, disable their
  schedules and show an explicit migration state. Require reviewed Ruby rewrite and validation
  before re-enabling; never relabel, silently translate, erase or execute legacy text as Ruby.
- Bind admission to source digest/profile so editing invalidates prior validation. Recheck current
  permissions at execution; a past validation result is not an authorization token.
- Drain old workers before cutover, reject stale queued source/profile payloads, and fence versions
  to prevent an old worker executing a new row under a different language.

Exit: end-to-end agent, console and scheduled automation scenarios pass, including upgrades with
realistically shaped legacy rows and duplicate queue delivery.

## Phase 6 — integration ecosystem, documentation and hard removal

- Replace remaining Lua/QuickJS runtime adapters, bindings, aliases, configs, extension requirements,
  INI fragments, health checks, Docker stages, install scripts, fixtures and CI branch wiring.
- Port the shared `ScriptBridge`, catalog and renderer contracts in the integrations source.
  Generate Ruby examples from current tool schemas across every provider; regenerate inventory
  counts instead of carrying forward the older QuickJS snapshot counts.
- Parse every active example with the pinned compiler and execute representative contract/mock
  fixtures across all provider schema shapes. Syntax alone does not prove API behavior.
- Update current product docs, package authoring docs, prompts, seeds, factories and API schemas.
  Mark old Lua/QuickJS plans historical; do not rewrite release history or old execution records.
- Add a narrowly scoped static regression gate rejecting legacy executable/runtime dependencies.
  Allow historical migration provenance and legitimate frontend JavaScript deliberately.
- Align the shared engine/value boundary with Bowerbird docs; do not implement optional connector,
  review, replay or durable workflow profiles as hidden prerequisites of this migration.

Exit: the final shipped tree has exactly one Code Mode runtime, and active docs describe it.

## Phase 7 — qualification, publication and local installation

- Run focused tests during development; release CI runs full relevant suites. Gates include Rust
  unit/conformance/adversarial tests, sanitizer/fuzz smoke, PHP host and integrations tests,
  PHPStan, frontend typecheck/build, generated-doc checks and supported production-image builds.
- Exercise workspace isolation, credential redaction, guest escape attempts, compilation limits,
  callback denial, provider timeouts, guest crashes, PHP-worker loss, cancellation and lost receipts.
  Record failures and explicit delivery ambiguity; never count a source-contract fixture as live proof.
- Benchmark cold starts, sustained runs, baseline/peak memory, cancellation latency and leaks on
  this Mac and production Linux. Do not add warm VM pooling before fresh-isolate correctness.
- Perform browser QA at `http://opencompany.test` for discovery, Ruby validation/execution,
  automations, trace/error states and migration warnings. Use sanitized data and safe test providers.
- Produce signed/checksummed, versioned engine artifacts, provenance/SBOM, licenses/notices,
  reproducible build instructions and protocol/profile compatibility checks. Do not fetch latest
  engine binaries at application request time.
- Publish the shared engine and integrations releases in dependency order; pin immutable released
  dependencies in OpenCompany and build images without temporary feature-branch coupling.
- Install the pinned runner for local PHP CLI and the actual web/queue service users; verify file
  permissions, configuration, health checks and real web/worker execution, not CLI alone.
- Inventory other local consumers before disabling QuickJS/Luau PHP extensions. Remove OpenCompany
  requirements immediately at cutover; do not break unrelated projects by deleting shared installs.
- Open the mruby OpenCompany PR as draft against `dev` during implementation, with linked package
  work and acceptance evidence. Supersede the QuickJS PRs explicitly when the replacement is ready.
  Commit, publish, merge and service restarts occur only under the corresponding user authorization.
- Prepare a recoverable migration/export and deployment rollback procedure. A release rollback is
  operational recovery, not a supported dual-language runtime. Never route migrated Ruby rows to
  old executors; if safe rollback is impossible, fail scripting closed and roll forward.

Exit: published dependencies, passing CI, qualified production artifact, local CLI/web/queue smoke
evidence and a complete operator handoff. Distinguish draft PR readiness from merge/deployment.

## Definition of done

All phases above are evidenced, not merely checked off in documentation. Every active scripting
entrypoint uses the same qualified mruby engine; no Lua or QuickJS execution path remains; existing
scripts are safely migrated or explicitly disabled; permission/run-control behavior is preserved;
AX and security gates pass; generated docs match executable contracts; installation and artifacts
work on supported targets; and remaining optional Bowerbird work is clearly outside this release.

## Upstream evidence and implementation references

- [mruby downloads](https://mruby.org/downloads/): stable release baseline, checked 2026-09-08.
- [mruby build configuration](https://github.com/mruby/mruby/blob/master/doc/guides/mrbconf.md):
  configurable interpreter controls, not proof of an application security boundary.
- [mrbgems guide](https://github.com/mruby/mruby/blob/master/doc/guides/mrbgems.md): build-time
  component selection; pin exact revisions and audit dependencies in the actual release build.
- OpenCompany: `app/Domain/Automations/Application/ExecuteScriptAutomation.php`,
  `app/Jobs/RunScriptAutomationJob.php`, `app/Services/LuaBridge.php`, and the newer run-control
  implementation/documentation in this checkout.
- QuickJS worktree: `config/code.php`, Code tools, `CodeBridge`, sandbox integration and the
  coordinated integrations `ScriptBridge`/catalog/renderer work. Reuse is selective, not a rebase
  instruction that discards newer application changes.
- OpenFactory: `docs/EMBEDDED_RUBY_RUNTIME.md`, `docs/LANGUAGE.md`, `docs/SECURITY.md`,
  `docs/MRUBY_UPSTREAM_AND_COMPATIBILITY.md` and
  `schemas/bowerbird-ruby/guest-protocol-v1.schema.json`. These describe target contracts, not a
  working shared engine as of this inspection.
