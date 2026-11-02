# Installing the Ruby engine release candidate

The application does not compile or fetch an engine at request time. Install
the reviewed `v0.1.0-rc.1` artifact explicitly from the repository root:

```sh
scripts/install-ruby-engine.sh
```

The installer selects only macOS ARM64, Linux AMD64, or Linux ARM64, downloads
the RC archive over HTTPS, verifies its pinned SHA-256 before extraction, and
installs its sole executable under `.runtime/ruby-engine/v0.1.0-rc.1/`. It
prints configuration values but never writes `.env`. Set the printed values in
your deployment environment, for example:

```dotenv
RUBY_ENGINE_BINARY=/absolute/path/to/.runtime/ruby-engine/v0.1.0-rc.1/ruby-engine
RUBY_ENGINE_SHA256=<installer output>
```

For an isolated destination, set `RUBY_ENGINE_INSTALL_ROOT` before running the
installer. Existing binaries are never replaced automatically. This RC is not
a stable-production certificate; see the engine release note for the bounded
qualification evidence and remaining review gates.
