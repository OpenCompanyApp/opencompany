# The `oc` CLI and Connected Hosts

Status: **Accepted** (owner, 2026-11-02). Nothing here is implemented. Binding summary: [Product Direction](../PRODUCT_DIRECTION.md) pillar 2 (connected hosts), pillar 10 (`oc` CLI) and the phase table.
Direction: [Product Direction](../PRODUCT_DIRECTION.md) pillars 2 (computers), 4 (API-first), 5
(governance) and 9 (connections).

Owner requirements (2026-11-02):

- OpenCompany needs an **extremely good `oc` CLI**, written in **Rust**.
- A machine the owner already has, for example **a Raspberry Pi on Tailscale**, must be connectable
  to OpenCompany.

These are one design. The `oc` binary is both the command-line client and the daemon that turns a
machine into a **connected host**.

## 1. Connected hosts ("bring your own machine")

### What a host is

A host is any machine the workspace connects: a Raspberry Pi, a home server, a laptop, a VPS or an
office NUC. It is the **bring-your-own-host `ComputerProvider`** from pillar 2, made concrete. Once
connected, a host offers up to five capabilities. Each capability is granted separately:

| Capability | What agents get |
|---|---|
| **Computer** | `exec`, files, processes, ports, and a browser if one is installed. The host can be an agent's computer, or a second machine next to its cloud computer |
| **Local MCP bridge** | stdio MCP servers running on the host (Home Assistant, a local database, filesystem, GPIO tools), exposed to granted agents as an MCP connection. This is how stdio MCP servers are supported without ever running in the web process |
| **VFS projection** | `oc mount` or sync of the agent's governed VFS view onto the host, with writes going back through VFS policy |
| **Event source** | Host events (file watcher, sensor or webhook relay, cron on the device) emitted into the workspace event log as untrusted events that can trigger routines |
| **ACP runner** | Coding-agent CLIs (Claude Code, Codex CLI, Gemini/Antigravity CLI, Grok Build, OpenCode, Goose) driven over ACP on the host with the user's own subscriptions, as an outside-agent delegate ([pillar 9, agent connections](../PRODUCT_DIRECTION.md#9-connections-events-and-routines)). Granted per agent; Phase 2 |

### How it connects

The rule is **outbound only**, so a home Raspberry Pi needs no open ports:

1. Install `oc`: a single static binary, including aarch64 builds for the Pi.
2. Run `oc host up --name pi-kitchen`. This starts device-flow login, registers the host to the
   workspace, and opens a persistent **outbound** connection (WebSocket/HTTP2) to the OpenCompany
   API.
3. Run `oc host install-service` to set up systemd on Linux (launchd on macOS, a Service on
   Windows).
4. An admin approves the host in Admin → Hosts. That makes it `exposure_widening`: Personal asks,
   Team needs an admin.

This works behind NAT, CGNAT and firewalls, with or without Tailscale, and against self-hosted
OpenCompany or Cloud.

**Tailscale** is supported as a network path, not required:

- **Server on the tailnet.** A self-hosted OpenCompany can run with a Tailscale sidecar, so it
  reaches the host and the host reaches it over MagicDNS names. Nothing is exposed publicly.
- **SSH-only hosts.** For machines where you don't want to install `oc`, an **SSH host** connection
  uses a tailnet address (or any address) with a key from the vault. Tailscale SSH is supported where
  the server is on the tailnet. This mode is limited to exec, files and the computer capability; it
  has no MCP bridge and no events.
- **Hosted Cloud** never joins a customer's tailnet. It uses the outbound `oc host` connection.

### Governance and safety

A host is a **self-managed** machine. OpenCompany cannot guarantee its isolation the way it can for
provider VMs. The UI labels it accordingly, and the rules are:

- **Grants are per agent and per host,** and per capability (computer, MCP server or mount). A host
  is never shared across workspaces.
- **Sandbox mode** is chosen at `oc host up`:

  | Mode | Behavior |
  |---|---|
  | `container` (default) | Podman or Docker |
  | `bwrap` | bubblewrap on Linux |
  | `none` | Requires `--i-understand-no-isolation` and is shown in red in Admin |

  Allowed paths, CPU, memory and time limits, and a local egress allowlist are enforced by the daemon
  on a best-effort basis.
- **Governance applies unchanged:** policy, approvals, the hard-deny floor, on-behalf-of access and
  audit all apply to host actions exactly as they do to cloud computers. Every command and file write
  is a run event.
- **Credentials:**
  - The vault never syncs secrets to a host.
  - The broker injects them per call.
  - A host token is scoped to that host, revocable, rotated automatically, and stored in the OS
    keychain or a root-only file.
- **Kill switch.** Admin → Hosts → Disconnect revokes the token, and the daemon stops all agent
  processes.
- **Presence.** Hosts report online and offline state plus resource use. Agents see an offline host
  as a clear tool error, not a hang.

### Raspberry Pi example

1. On the Pi: `curl -fsSL https://get.opencompany.dev | sh`, then `oc host up --name pi-garage`,
   then `oc host install-service`.
2. In Admin → Hosts, approve `pi-garage`. Grant the agent "Ops" the computer capability and the
   `homeassistant` MCP server running on the Pi.
3. In chat, ask Ops: "When the garage temperature goes above 30 °C, turn on the fan and tell me."
   This becomes a routine triggered by the Pi's sensor event. The fan action is an external write,
   so it asks the first time and then offers "always".

## 2. The `oc` CLI

### Principles

These follow Bowerbird's accepted agent-first CLI decisions (bwb-cloud decision 0011 and the
openfactory `cli-and-mcp.md` design), and share the same reasoning.

1. **Agents are the first user; humans the close second.**
   - A **self-describing command schema** (`oc schema`, `oc guide agents`).
   - **Errors that name the next command.**
   - Compact output by default, `--json` / NDJSON everywhere, stable exit codes.
   - `--wait` instead of polling.
   - `--dry-run` that returns the change set.
   - Pending approvals returned as structured results with the approval ID and how to answer.
2. **An agent-experience eval suite gates releases.** A change fails if agents complete fewer tasks
   or read more output.
3. **One API.** `oc` is a client of the versioned public API and realtime stream (pillar 4), like the
   web and mobile clients. It has no private endpoints. The Rust API client is generated from
   OpenAPI.
4. **Great for humans too:**
   - a `ratatui` TUI for chat and Home;
   - colored, aligned tables;
   - shell completions and man pages;
   - fuzzy pickers;
   - instant startup.
5. **Contexts:** many instances and workspaces (self-hosted and Cloud), with `oc context use`.
   Login is through the device flow, and tokens live in the OS keychain.

### Command map

| Area | Commands (examples) |
|---|---|
| Auth and contexts | `oc login`, `oc logout`, `oc context list/use`, `oc whoami` |
| Home and approvals | `oc home`, `oc approvals list/approve/deny`, `oc approvals watch` |
| Chat | `oc chat <agent>` (TUI), `oc ask <agent> "…" [--wait] [--json]`, `oc threads` |
| Agents | `oc agents list/show/create`, `oc agents pause/resume/kill`, `oc agents autonomy <agent> balanced` |
| Work objects | `oc issues`, `oc tables query/rows/import/export`, `oc docs`, `oc files`, `oc forms`, `oc artifacts` |
| Changes | `oc changes list/show/apply/reject`, `oc runs list/tail/replay/revert` |
| Routines and events | `oc routines list/run/logs`, `oc events tail [--type …]` |
| VFS | `oc fs ls/cat/rg/edit/patch`, `oc mount <dir>` (FUSE, governed), `oc sync` |
| Code Mode | `oc code run script.rb`, `oc code docs` |
| Artifacts | `oc artifact new`, `oc artifact dev` (local preview with hot reload against a draft), `oc artifact publish` |
| Apps and skills | `oc app validate/install/upgrade`, `oc skill import/list`, `oc plugin import` |
| Connections | `oc connections list/add/test`, `oc mcp add <url>` |
| MCP server | `oc mcp serve`: exposes the user's OpenCompany workspace as a local MCP server, so Claude Code, Codex, Cursor and others can use Issues, Tables, Docs and approvals with the user's own access |
| Hosts | `oc host up/down/status/install-service`, `oc host logs`, `oc host mcp add <stdio command>` |
| Export and portability | `oc export <object> [--format md\|pdf\|docx\|csv\|xlsx\|json]` for docs, tables, issues, threads, files, artifact bundles, form submissions and agent packs ([Data sovereignty](data-sovereignty.md)) |
| Self-hosting admin | `oc admin doctor` (including warnings when backups are missing, failing or unverified), `oc admin upgrade`, `oc admin backup/restore`, `oc admin export/import` (`.ocarchive`), `oc admin import --from cloud` (Cloud → self-host migration), `oc admin users` |
| Raw | `oc api <method> <path>` |

### Distribution and engineering

- **Rust, one static binary.** Targets are Linux x86_64 and aarch64 (Raspberry Pi), armv7 where
  cheap, macOS universal, and Windows.
- **Install paths:**
  - the existing `OpenCompanyApp/homebrew-tap`;
  - an install script;
  - `cargo install`;
  - `.deb` and `.rpm` packages;
  - GitHub Releases with checksums and signatures;
  - `oc self-update`.
- **Repository:** a separate `opencompany-cli` repository with a Cargo workspace (`oc` binary,
  `oc-api` generated client, `oc-host` daemon, `oc-tui`).
- **License: Apache-2.0**, under the SDK and client boundary in pillar 8, so the CLI and its crates
  can be embedded by third parties.
- **Shared with Bowerbird:** put contexts, device-flow login, keychain storage, output and error
  formatting, the schema and guide generator, and the agent-experience eval harness into shared crates
  usable by both `oc` and `bwb`. These crates live in Bowerbird's public client track under
  Apache-2.0, and `oc` consumes them as dependencies
  ([OpenCompany × Bowerbird, shared tooling](bowerbird-synergy.md#34-shared-agent-first-tooling)). Grok-build-enhanced's `ratatui` experience is reusable for the TUI.
- **Server work needed:**
  - device-flow OAuth;
  - personal access tokens and host tokens;
  - the host WebSocket gateway;
  - the realtime stream;
  - complete OpenAPI coverage.

## Phasing (proposed)

| Phase | Work |
|---|---|
| 0 | Reserve the API surface: device-flow auth, token types, OpenAPI generation |
| 1 | `oc` v0: login and contexts, home, approvals, ask/chat (TUI), issues, tables, docs, files, fs, runs, `--json` and schema, `oc mcp serve`, Homebrew and install script; `oc export` for objects ([Data sovereignty](data-sovereignty.md#phasing)) |
| 2 | `oc host` v1: outbound daemon, computer capability (container sandbox), systemd service, Admin → Hosts, Raspberry Pi builds; SSH hosts over Tailscale or a direct address; `oc artifact dev`; `oc code run`; `oc admin export/import` and `oc admin backup/restore`; the ACP runner capability on hosts (granted per agent) |
| 3 | Host local MCP bridge and event source; `oc mount` (FUSE); bwrap sandbox; agent-experience eval suite gating releases; `oc admin` for self-hosters, with `oc admin doctor` backup warnings |
| 4–5 | `oc app` and plugin tooling, `oc forms`; Cloud contexts; Windows host support; `oc admin import --from cloud` (Phase 5) |

## Open questions

- Whether Phase 1 really ships `oc mcp serve` before OpenCompany's hosted MCP server, or whether both
  ship together.
- The install domain (`get.opencompany.dev` is a placeholder).
- Whether hosts can be shared by several agents by default, or one agent per host unless an admin
  opts in, mirroring per-agent computers.
