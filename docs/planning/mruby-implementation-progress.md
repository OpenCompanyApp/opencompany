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
  `c0047a4a2496f85927ce40761ec573677846fb5a` for the coordinated application
  workflow.
- The public engine repository's `main` has advanced beyond `9b82`. The workflow is
  deliberately pinned to qualification commit
  `9b82a41ad11d7aca8cf49b210603a2c2ffe67277`, rather than following a mutable
  branch.
- The primary `../opencompany` checkout remains on `feat/vfs`. Its existing,
  uncommitted VFS/run-control/licensing/documentation work is outside this
  migration and has not been imported. The plan's wider VFS/run-control ideas
  remain future work, not delivered mruby scope.
- No migration has run against the real local application database and no
  domain, worker, extension configuration, PHP service, or browser session has
  been cut over. There are no live provider calls in the focused contract tests.
- The Composer path repository still uses development source. Do not run
  overlapping Composer mutations or mirror a mutable Rust target directory;
  immutable released artifacts are a remaining release gate.

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
checked in but its hosted run, sustained-load, ABI/GC adversarial review and
signed-release qualification remain pending.

The normal macOS release candidate is installed only for this isolated PHP
worktree at `~/.local/libexec/opencompany/ruby-engine/9b82a41ad11d7aca8cf49b210603a2c2ffe67277/ruby-engine`.
SHA-256: `03c8457b696db29ab6efc7355bc092a16e612b1c7438a99d711da22cc3f4099a`.
Its seven PHP smoke scenarios pass; the real local web/queue checkout has not
been switched. The previous candidate is retained for recovery.

After this installation, the combined focused application run passes 96 tests /
2,885 assertions, including real queue execution, stale/redelivered queue fencing,
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

Task claiming now fences stale queued automation work. Cancellation and resource
budget wiring, paged script history, and the editor/history experience are in
the current feature worktree. Their final focused test run is still in progress;
they are not yet browser or production-cutover evidence.

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

A development-only AST converter migrated 600 repository-owned pages containing
3,942 Ruby examples. The examples passed real mruby compilation-only sweeps; the
converter is neither a stored-script translator nor a runtime dependency.
Reviewed provider examples use actual source schemas and exact operation slugs,
including the Microsoft documentation repairs. The integration PR pin above is
the source used by the application workflow.

### Focused validation and CI

A prior non-browser focused application run reported 71 tests / 2,763
assertions. It predates the latest queue/history/budget and approval wiring, so
it is deliberately not presented as the final total. Earlier focused subsets
also covered source migration, sandboxing, catalog generation, bridge dispatch,
and CodeExec. Do not add overlapping totals.

The dedicated [mruby workflow](../../.github/workflows/mruby.yml) builds the
pinned engine, verifies and exports its SHA-256, installs the pinned integration
source, and runs the bounded PHP contract set. That set includes sandbox,
documentation, integration value, source migration, agent experience, stale
automation queue, execution budget, script history, admission, callback
authorization, catalog, bridge, integration runtime, and CodeExec tests. It
also runs the UI type-check/build. It intentionally does not substitute the
entire unrelated application suite for targeted mruby evidence.

`docs/INDEX.md` already links this evidence log beside the implementation plan;
no additional index entry is needed.

## Remaining gates — do not claim these are shipped

1. Finish and record the current focused queue/history/budget/approval run;
   add cross-workspace and browser coverage for the new paths. Validate stale
   queue fencing, partial effects, cancellation, and cleanup under real worker
   conditions.
2. Implement schema-aware static API validation from an actual Ruby parser/AST
   and authoritative catalog schemas. A regex or a compile-only sweep must not
   be misrepresented as capability analysis. Runtime dispatch remains the
   authority until then.
3. Establish a matched-model AX task corpus and baselines; current editor and
   agent-experience tests are not a matched-model measurement.
4. Complete engine release qualification: parser/compiler/value/protocol fuzzing,
   ASAN and other sanitizer runs, sustained cancellation/memory benchmarks,
   ABI/GC adversarial review, provenance, SBOM/notices, and signed/checksummed
   immutable artifacts.
5. Replace local Composer path coupling with released immutable dependencies and
   complete release review of the draft application/integration PRs. Draft PRs
   and development source do not authorize a merge or demonstrate a release.
6. Qualify production Docker, recoverable database/source export and worker
   drain, then perform the explicitly approved browser and local-runtime
   cutover at `http://opencompany.test`. No live provider or production cutover
   has been performed.
