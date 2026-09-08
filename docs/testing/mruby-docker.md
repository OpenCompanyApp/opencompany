# mruby production image qualification

This record covers Docker packaging only. It does not authorize a database,
worker, browser, provider, or local-runtime cutover.

## Immutable inputs

The production image downloads `bowerbird-ruby-engine` `v0.1.0-rc.1` as a
platform-specific release archive and verifies its archive SHA-256 before
extracting the executable:

- Linux AMD64: `3c4b1d629d4813b5ffa977fc1559844c58ea94ffd391a6ffce9e7ac0b016605c`
- Linux ARM64: `5d52067f4f25301925cdf92507fbadc11e987ac2c8ea3cd347ac81b7fd7691ad`

The PHP adapter is installed by Composer from its pinned RC distribution. It is
not copied from a sibling engine checkout. The image calculates the installed
binary digest at build time; `config/code.php` reads that file when no explicit
`RUBY_ENGINE_SHA256` deployment value is supplied.

Composer's integration, astronomy, and ChatOgrator path repositories require
clean named contexts. Supply archives at their locked revisions rather than
sibling worktrees, which may contain Git metadata, credentials, dependencies, or
build artifacts. For the coordinated integration revision
`c0047a4a2496f85927ce40761ec573677846fb5a`, an ARM qualification build is:

```sh
integrations_context=$(mktemp -d /tmp/opencompany-mruby-integrations.XXXXXX)
astronomy_context=$(mktemp -d /tmp/opencompany-mruby-astronomy.XXXXXX)
chatogrator_context=$(mktemp -d /tmp/opencompany-mruby-chatogrator.XXXXXX)

git -C ../integrations-mruby archive c0047a4a2496f85927ce40761ec573677846fb5a | tar -x -C "$integrations_context"
git -C ../opencompany-quickjs/tmp/astronomy-bundle-php archive 7d2f0924657c52d61ca4fa576b71df8c7be72a17 | tar -x -C "$astronomy_context"
git -C ../opencompany-quickjs/tmp/chatogrator archive a2fe13415f5d1229a6c06a8ea04ddc3663c58b01 | tar -x -C "$chatogrator_context"

docker --context colima-mruby-qualification build --load --platform linux/arm64 \
  --build-arg RUNTIME_PLATFORM=linux/arm64 \
  --build-context integrations="$integrations_context" \
  --build-context astronomy="$astronomy_context" \
  --build-context chatogrator="$chatogrator_context" \
  --tag opencompany-mruby-packaging:arm64 .
```

The qualification context is intentionally named; do not change the global
Docker context. `.dockerignore` excludes `.env`, `vendor`, `.auth`,
`.playwright-cli`, and `.runtime` from the application context.

## ARM64 evidence

On 2026-09-08, the final clean-context ARM64 image loaded as
`opencompany-mruby-packaging:arm64`:

- image digest: `sha256:de23ce754ac4c383956def9fc8bbe339ed19f02a116a83aaf874e19903b6d535`
- image size: 482,941,140 bytes
- binary archive: verified against the Linux ARM64 SHA above before extraction
- runtime user: `www-data`

The following non-network, credential-free checks passed as `www-data`:

1. `/usr/local/share/ruby-engine.sha256` matched the installed executable.
2. Laravel configuration loaded that digest through its container fallback.
3. The release PHP adapter constructed `Bowerbird\RubyEngine\Client` and
   executed `6 * 7` with `validateOnly: false`, returning the integer `42`.
4. `MrubySandboxService` executed the same console-profile source and returned
   `42`; its `engineDigest()` and Laravel's configured digest both matched the
   installed artifact checksum.
5. A separate compile-only adapter request also completed, confirming the
   validation path without introducing an execution-only substitute.
6. The final image did not contain `/app/.env`, `/app/.auth`,
   `/app/.playwright-cli`, or `/app/.runtime`.

The first ARM build exposed two qualification-profile constraints that are now
handled in the Dockerfile: Typst and Mermaid binaries are selected by target
architecture, and Vite's asset build is serialized before the memory-intensive
native PHP extension layer. The asset stage has a 2.5 GiB Node heap cap; it is
not inherited by the final runtime. A scoped BuildKit cache prune on the
disposable `colima-mruby-qualification` context reclaimed 10.43 GB after failed
attempts; no containers, volumes, default-context images, or application data
were removed.

## Remaining packaging qualification

- Build and smoke the Linux AMD64 image in its hosted target environment.
- Validate release provenance/SBOM/signature policy and the installer before a
  production release claim.
- Run the approved browser, database, worker-drain, and live cutover procedure
  separately. This packaging evidence includes no live provider calls.
