# mruby migration implementation progress

Status: in progress. This is an evidence log, not a release certificate. The
accepted scope remains [the implementation plan](mruby-only-code-mode-implementation.md).
Verified: 2026-09-08 against the isolated working trees and focused evidence
listed below.

## Ownership, publication, and boundaries

- `../opencompany-mruby` is the isolated `feat/mruby` application worktree.
  Draft application PR #8 targets `dev`; publication is not a merge, release, or
  local cutover.
- `../integrations-mruby` is the isolated `feat/mruby` integration worktree.
  Draft integration PR #4 is pinned at
  `d8666a50966d51d8420d9908c5e7c1beca3dfdfd` for the coordinated application
  workflow.
- The public engine [v0.1.0-rc.1 prerelease](https://github.com/OpenCompanyApp/bowerbird-ruby-engine/releases/tag/v0.1.0-rc.1)
  targets `772e2b46c37a4bcf4cffbc57e6d9214493c11473`. The app consumes its
  versioned minimal PHP adapter archive; app CI consumes its SHA-256-pinned
  Linux AMD64 native archive rather than rebuilding mutable local sources.
- The primary `../opencompany` checkout remains on `feat/vfs`. Its existing,
  uncommitted VFS/run-control/licensing/documentation work is outside this
  migration and has not been imported. The plan's wider VFS/run-control ideas
  remain future work, not delivered mruby scope.
- No migration has run against the real local application database and no
  domain, worker, extension configuration, PHP service, or browser session has
  been cut over. There are no live provider calls in the focused contract tests.
- Integrations still use coordinated development path repositories. The engine
  adapter no longer uses a path symlink or mirrors a Rust target directory.
  Do not run overlapping Composer mutations.

## Implemented, bounded evidence

### Engine and execution boundary

The engine pins mruby 4.0.0 source commit
`831da26b9021de0369d17b71b5667e2941a1a32d`, uses an explicit mrbgem allowlist,
and keeps a C-only protected VM boundary behind a Rust supervisor. Each run gets
a fresh guest, bounded framed IPC, empty environment, wall/CPU/allocation and
instruction limits, and host-typed failures. Linux seccomp and macOS Seatbelt
deny filesystem, network, and process access; unsupported backends fail closed.

The bridge preserves supported JSON-like Ruby values, rejects cycles, duplicate
normalized keys and unsupported values, and provides bounded pure JSON helpers.
Diagnostics use native source locations. The PHP client verifies the engine
digest, observes cancellation, and redacts provider exceptions before returning
typed failures.

Qualification commit `c642775d2d1a7a023011f6785f0b8983ed83b0e3` has a green
full Linux AMD64/Linux ARM64/macOS ARM64 run of 28 Rust tests plus seven PHP
smoke scenarios (GitHub Actions run `34234660653`). This is useful portability
evidence only. Follow-up run `34235637370` at `9b82a41` also passed the bounded
AddressSanitizer corpus, with instrumented mruby/shim verification and a distinct
sanitizer failure exit code. Coverage-guided protocol/admission fuzzing is now
green in hosted runs `34236675933` and `34237150197`. These short protocol/request
targets do not cover the native compiler or C value bridge. Sustained native
fuzz/load and independent ABI/GC security review remain outstanding.

RC packaging run `34237642178` passed all three platforms. All downloaded archive
checksums verified; provenance and SPDX attestations for the downloaded Mac
archive verified using `gh attestation verify`. Those exact archives and evidence
bundles are now published in the prerelease, not represented as a stable release.

The normal macOS release candidate is installed only for this isolated PHP
worktree at `~/.local/libexec/opencompany/ruby-engine/v0.1.0-rc.1/ruby-engine`.
SHA-256: `120c90bb38ac52c03b4c91518ebb43fcfa8f25cab8c3e9d3c684d9754ff40688`.
The hosted seven PHP smoke scenarios and extracted adapter/binary smoke pass;
the real local web/queue checkout has not been switched. Previous candidates are
retained for recovery.

After the RC artifact installation, the combined focused application run passes
122 tests / 3,026 assertions, including real queue execution, stale/redelivered queue fencing,
permission/approval callbacks, cancellation/deadline rescue, source history,
catalog/value contracts and deterministic AX cases. Type checking and Vite build
also pass; existing bundle-size/annotation warnings remain.

### OpenCompany runtime, authority, and agent experience

The language-neutral `code_*` surface, bridge/catalog services, execution DTOs,
and `MrubySandboxService` are connected in this worktree. Admission binds an
explicit source submission to its profile, source SHA-256, and installed engine
SHA-256. Metadata-only updates cannot relabel legacy source; invalid/stale
admission is rejected. Legacy source is retained in workspace-owned revisions
and script schedules are disabled during migration without changing prompt
schedules or previous output.

Script capability discovery is filtered by enabled integrations and current tool
permission before schemas are instantiated; the Developer catalog remains
unfiltered for browsing. Every script callback rechecks workspace, enabled
integration, and current permission before dispatch. A raw slug-instantiation
path remains restricted to the post-approval execution path. Denied and
approval-required callbacks become typed host effects, and the bridge latches a
pending/denied disposition so rescued guest exceptions cannot continue issuing
callbacks in that run.

Approval execution claims and locks the request, refreshes its authoritative
context, rechecks the current policy, and records a durable callback receipt.
It permits only the exact approved request to cross the post-approval boundary;
revoked permission, disabled integration, wrong workspace/account/context, and
duplicate execution fail closed. This does not introduce an automatic retry of a
pending script.

Task claiming fences stale queued automation work. Default agent/automation
writes now require an active same-agent/workspace Task and persist a TaskStep
intent before dispatch. Source/invocation/sequence and canonical request/result
digests are retained without raw provider payloads. Unknown outcomes explicitly
prohibit automatic replay. This is not an atomic provider/database transaction.

Delegated peer prompts create a same-workspace, peer-owned child Task only when
the SDK prompt actually executes. Short transactions lock parent/child lifecycle
transitions, never provider work. Inactive parents cannot authorize a delegated
write receipt; stopped or missing children cannot be reported as completed
delegations. Tool construction/provider failures persist a sanitized failure and
restore the parent registry context. These tests use a fake SDK, not an LLM.
The locked parent must also own the exact delegated channel; a different channel
in the same workspace or a missing parent channel fails before SDK/tool work.
The follow-up delegated/receipt run passes 23 tests / 130 assertions, overlapping
the combined run rather than adding to its total.

Host callback/checkpoint failures share a per-execution typed latch, so guest
rescue cannot clear the first control failure or permit later callbacks. Missing
receipt intent fails closed rather than manufacturing a successful outcome.
The six focused host-boundary PHPStan targets pass at repository level 6 and are
now enforced in the dedicated mruby workflow.

Cancellation and fixed callback deadlines reach Laravel HTTP (including MCP)
and the app's native SVG renderer. Silent renderer children are polled and
stopped on cancellation/expiry; shorter configured process timeouts remain
enforced. Killing local work cannot undo a dispatched remote write.

Paged history and editor behavior have passed the
[mocked-API browser fixture](../testing/mruby-history-browser.md): exact source,
escaping, deliberate load, pagination, dark theme, and no save/run side effects.
This uses real built Vue assets but is not authenticated live Laravel evidence.

Ruby highlighting, snippets, signature help, source-bound diagnostic markers,
safe snippet/HTML escaping, and workspace-invalidated completions are present.
Wayfinder sources were regenerated. The legacy Lua-specific application tests
and guide pages (five of each) were removed only after Ruby parity replacements
landed; this is not a runnable Lua fallback or proof of production parity.

### Integration ecosystem and documentation

The shared ScriptBridge uses exact tool-slug aliases and collision-safe function
names, so same-display-name operations do not overwrite parameter, account, or
permission mappings. Generated function documentation exposes stable exact
paths. Provider interfaces use `scriptDocsPath`.

Shared bridge/catalog/docs treat only an explicit `read` as read-only. Missing,
custom and legacy `action` classifications become conservative writes; successful
write ledger entries are non-retryable. Focused core/conformance tests pass
15 tests / 97 assertions. Existing explicit reads/writes retain their semantics;
183 legacy action entries now receive conservative write handling.

Integration CI now installs the same versioned RC PHP adapter and verifies the
Linux AMD64 native archive before extraction. Native source qualification stays
in the engine repository; documentation checks no longer build an older engine
source revision or require a mutable Rust toolchain.

A development-only AST converter migrated 600 repository-owned pages containing
3,942 Ruby examples. The examples passed real mruby compilation-only sweeps; the
converter is neither a stored-script translator nor a runtime dependency.
Reviewed provider examples use actual source schemas and exact operation slugs,
including the Microsoft documentation repairs. The integration PR pin above is
the source used by the application workflow.

### Focused validation and CI

A focused release-artifact application run passed 122 tests / 3,026 assertions,
including real-registry write classification, a real local document write followed
by Ruby failure, and renderer timeout/cleanup. Do not add overlapping subset
totals. Hosted app run `34240660848` is green at `db895ed`, using the published
RC native/PHP artifacts and including receipts and renderer budgets; integration
run `34243098751` is green at `d8666a5`, now compiling all Ruby examples with
the released native/PHP artifacts. The new delegated boundary also passes the
combined local run above; the historical app run does not prove untested changes
at a later head.

The dedicated [mruby workflow](../../.github/workflows/mruby.yml) downloads the
pinned released engine, verifies and exports its SHA-256, installs the pinned integration
source, and runs the bounded PHP contract set. That set includes sandbox,
documentation, integration value, source migration, agent experience, stale
automation queue, execution budget, script history, admission, callback
authorization, catalog, bridge, integration runtime, and CodeExec tests. It
also runs the UI type-check/build. It intentionally does not substitute the
entire unrelated application suite for targeted mruby evidence.

`docs/INDEX.md` already links this evidence log beside the implementation plan;
no additional index entry is needed.

## Remaining gates — do not claim these are shipped

1. Release-artifact app CI and both Linux Docker targets are green: hosted AMD64
   run `34240660770` executes real Ruby as `www-data` with networking disabled
   and a read-only filesystem. Cross-workspace
   and mocked-browser coverage exists; real worker/browser cutover still needs
   a safe target that does not replace the user's separate VFS/run-control work.
2. Implement schema-aware static API validation from an actual Ruby parser/AST
   and authoritative catalog schemas. A regex or a compile-only sweep must not
   be misrepresented as capability analysis. Runtime dispatch remains the
   authority until then.
3. Establish a matched-model AX task corpus and baselines; current editor and
   agent-experience tests are not a matched-model measurement.
4. Complete native parser/compiler/value fuzzing, sustained cancellation/memory
   benchmarks and ABI/GC adversarial review. Protocol fuzzing, bounded ASAN,
   provenance, SBOM/notices and attested/checksummed RC artifacts are delivered;
   they do not eliminate the deeper qualification gates.
5. Replace remaining integration path coupling with released immutable dependencies,
   triage baseline Composer advisories (29 advisories across nine existing packages),
   and complete release review of the draft application/integration PRs. Draft PRs
   and development source do not authorize a merge or demonstrate a release.
6. Prepare recoverable database/source export and worker drain, then perform
   the explicitly approved browser and local-runtime
   cutover at `http://opencompany.test`. No live provider or production cutover
   has been performed.
