# mruby migration implementation progress

Status: In progress. Development source publication is underway; no qualified
release is published or installed as the live `opencompany.test` runtime.
This is an evidence log, not a completion
certificate. The accepted scope remains [the implementation plan](mruby-only-code-mode-implementation.md).
Verified: 2026-09-08 against the isolated working trees and tests listed below.

## Ownership and checkout boundaries

- `../opencompany-mruby`: `feat/mruby`, based on `origin/dev` at `93895a1`.
- `../integrations-mruby`: `feat/mruby`, based on `origin/main` at `4f8f636e4`.
- `../bowerbird-ruby-engine`: standalone engine/package repository, `main` at
  `316852148b739fa22bbcfe1c4fca5e75fb7116bb`, published as development source at
  https://github.com/OpenCompanyApp/bowerbird-ruby-engine. No release tag exists.
- The primary `opencompany` checkout remains on `feat/vfs`. Its pre-existing
  uncommitted VFS/run-control/licensing/documentation work has not been gathered
  into this feature. The existing QuickJS worktrees are also preserved.
- The user explicitly chose to keep VFS/run-control separate. Implement against
  the `dev` lifecycle; do not import those changes or switch the running checkout
  as an implicit part of this migration. Reconciliation is a separate change.
- No migration has run against the real local application database. No domain,
  worker, extension configuration or PHP service has been cut over. Dependencies
  are installed only inside the isolated mruby worktree.
- The engine path repository uses a development source symlink. A mirrored
  reinstall was found to copy the live Rust target directory despite Composer
  archive exclusions and failed during an incremental build. Do not run
  overlapping Composer mutations or mirror a mutable Rust build tree. Immutable
  release archives remain a separate publication gate.
- Composer's reinstall/update reported 29 security advisories affecting nine
  dependencies. No unrelated package versions were upgraded. Baseline comparison
  and advisory triage remain necessary before claiming release qualification.
- A dedicated Colima VM named `mruby-qualification` was created for Linux ARM64
  checks (2 CPUs, 4 GiB RAM, 20 GiB disk, no host-directory mounts). The default
  Docker context was not changed. It remains available for further qualification.

## Implemented, with focused evidence

### Shared engine

Pinned upstream mruby 4.0.0 source commit
`831da26b9021de0369d17b71b5667e2941a1a32d`; explicit mrbgem allowlist; a Rust
supervisor and C-only protected VM boundary; fresh guest per invocation;
bounded framed IPC; empty environment; kernel wall/CPU timers; allocation and
instruction termination outside Ruby rescue; Linux seccomp and macOS Seatbelt
filesystem/network/process denial. Unsupported backends fail closed.

The value bridge preserves empty objects/lists, nil/false, UTF-8, finite floats
and signed 64-bit integers; it rejects cycles, duplicate normalized keys and
unsupported values. Frozen Records support bracket/fetch access and collection
projection. JSON helpers are pure and bounded. Diagnostics use native source
locations. Unknown functions provide nearby catalog paths without dispatch.
The PHP subprocess client supports artifact digest checks, cancellation checks,
safe typed host failures and provider-exception redaction.

On this Mac, the current focused engine run passes 28 Rust tests (19 execution,
seven supervision and two protocol tests), including independent CPU exhaustion.
Seven PHP smoke scenarios pass. Linux ARM64 qualification passes the same 28
Rust tests and seven PHP 8.4 smoke scenarios, rerun explicitly in the built image.
`cargo clippy --all-targets -- -D warnings` passes. This is not fuzz/sanitizer,
Linux AMD64 or complete threat-model qualification.

The supervisor now owns all framed I/O in one bounded nonblocking loop. Partial
host frames, a stopped output reader, unsolicited replies and cancellation cannot
suspend its watchdog. Host EOF kills/reaps the guest. The PHP client allows a
bounded cleanup grace period before escalating. Linux parent-death signaling and
a macOS kernel parent watch terminate guests on abrupt supervisor loss; a real
process test covers this on both platforms. No detached stdio reader threads
retain pipes or global stdio locks after completion.

### OpenCompany integration and AX

Language-neutral `code_*` tools, bridge/catalog services and execution DTOs have
been ported and connected to `MrubySandboxService`. Agent execution, automation
execution, validation and the capability-empty developer console use that
adapter in this worktree. Authority remains in the existing PHP invoker.

Ruby highlighting, keyword snippets, signature help, source-bound diagnostic
markers, safe snippet/HTML escaping and workspace-invalidated completions are
implemented. Wayfinder sources have been regenerated. `npm run typecheck` and
`npm run build` pass; the build still reports existing chunk/annotation warnings.

Admission now binds explicit source submission to profile, source SHA-256 and
installed engine SHA-256. Metadata-only updates cannot relabel legacy source.
The migration preserves exact legacy source in workspace-owned revisions and
disables script schedules without changing prompt schedules or previous output.
New source revisions are persisted transactionally. Execution rejects stale or
missing admission. Tests cover both ambiguous legacy text and source/build drift.

The admission/migration/sandbox-focused command passed 17 tests / 80 assertions:

```sh
RUBY_ENGINE_BINARY=/Users/rutger/Sites/bowerbird-ruby-engine/target/debug/ruby-engine php artisan test --compact \
  tests/Feature/MrubySourceMigrationTest.php tests/Feature/MrubyDocumentationTest.php \
  tests/Feature/MrubySandboxServiceTest.php tests/Feature/ScriptAdmissionTest.php \
  tests/Feature/Tools/CodeExecTest.php
```

Use the corresponding absolute executable path on another host. A combined
44-test app run passed before the collision-safe catalog change. After that
change, the catalog/bridge/CodeExec command passes 33 tests / 1,035,732 assertions
(the generated catalog causes the large assertion count). Its identifier test
now distinguishes exact hyphenated slugs, invoked through `app.call`, from Ruby
dot-method names. Do not sum these overlapping runs into a fabricated total.

```sh
RUBY_ENGINE_BINARY=/Users/rutger/Sites/bowerbird-ruby-engine/target/debug/ruby-engine php artisan test --compact \
  tests/Feature/CodeApiDocGeneratorTest.php tests/Feature/CodeBridgeTest.php \
  tests/Feature/Tools/CodeExecTest.php
```

### Integration ecosystem and documentation

The shared ScriptBridge/catalog/renderer tests pass: ten tests, 45 assertions.
Generated endpoints with identical display names no longer overwrite each
other's capability mapping. Collision-safe names and exact tool-slug aliases
share the same parameter/account/permission maps. Exact paths remain stable when
the visible short-name set changes. Function docs expose those exact paths.
Provider interfaces/implementations use `scriptDocsPath`. A development-only AST
converter migrated 600 repository-owned pages / 3,942 fenced examples; it is not
a persisted-script translator or runtime dependency. All examples passed a real
mruby compilation-only sweep after reserved-variable repairs.
Earlier repeated sweeps intermittently hit a host deadline. Following the
nonblocking supervisor repair, two complete 3,942-example sweeps passed with zero
failures, including the latest macOS parent watcher and collision-safe docs in
the second sweep. This is stronger regression evidence, not proof of the exact
cause of every earlier timeout or a substitute for sustained qualification.

Catalog conformance is **not complete**: the converter still reports 14 outdated
or ambiguous provider-operation paths. It exits unsuccessfully rather than claiming
those calls work. These require individual source/schema review, not blind
renaming or replacing workflows with empty examples. App guides are authored in
Ruby and have separate real-engine compilation tests.

Metadata export now isolates providers that reuse a PHP class name (including
case-only collisions), verifies the loaded source file and records declared
retired aliases separately. It no longer exports canonical ElevenLabs methods
as though they were the legacy provider. The retired Hugging Face package has a
pre-existing case-only self-inheritance wrapper; it is recorded as retired, not
treated as an executable second namespace. Reviewed example repairs cover dbt
v2 retrieval, Eden AI v2 POST, Vonage list messages, WorkOS's actual `id` keyword
and X's current-user operation. Stored scripts are never translated.

## Remaining gates — do not claim these are shipped

1. Preserve the confirmed `dev` baseline and exclusion of uncommitted VFS and
   run-control work. Any live checkout cutover must respect that separation.
2. Complete `dev`-baseline run/approval/cancellation integration and
   enforce provider deadlines at transport dispatch, not only after return.
   Qualify stale queued source fencing, partial effects and immediate cleanup.
3. Finish schema-aware static validation, full AX mock task corpus/baselines,
   revision-history authoring UI and cross-workspace/security/browser tests.
4. Repair every reported docs/catalog mismatch, validate argument/return schemas,
   execute representative mocked workflows, regenerate the public catalog and
   finish active docs/seeds/tests/installer/CI legacy removal. Old tests, package
   adapters/docs and the production Dockerfile still have retirement work.
   Qualify maximum visible catalog sizes with exact aliases against IPC/profile
   ceilings; metadata-only catalog tests do not prove an all-provider invocation
   fits the execution profile.
5. Complete parser/compiler/value/protocol fuzzing, sanitizer checks, ABI and GC
   adversarial review, sustained/cancellation/memory benchmarks, and Linux AMD64
   plus final ARM64/macOS qualification. Add build provenance/SBOM/notices and
   signed/checksummed immutable artifacts. Extend parent-loss/backpressure checks
   to sanitizer and sustained-load qualification, beyond the focused regressions.
6. Replace local Composer path coupling with immutable released dependencies;
   publish engine and integration packages, commit scoped changes and create the
   mruby draft PR against `dev`. Do not merge or close old PRs without direction.
7. Perform a recoverable local database/source export and worker drain, install
   the qualified artifact for actual web/queue users, cut over the approved
   checkout and verify `http://opencompany.test` in the required browser session.
   Inventory other consumers before changing global PHP extensions.

The user explicitly authorized scoped commits and draft-PR publication. Engine
development source is committed/pushed; coordinated application and integrations
draft PRs are being prepared. Publication does not mark the remaining gates done.
