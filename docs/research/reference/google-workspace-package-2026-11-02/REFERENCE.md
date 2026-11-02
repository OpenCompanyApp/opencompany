# Google Workspace package — reference copy

Status: **Reference material only. Not loaded, autoloaded or executed by the app.**
Copied 2026-11-02 from `integrations` branch `feat/mruby` (`packages/google`, base `d8666a50`)
before the integration package system was deleted (Product Direction, nuclear decisions).

Purpose: mine the OAuth flow (`src/GoogleOAuthController.php`), the token-refreshing HTTP
client (`src/GoogleClient.php`) and the per-API services (`src/Services/*`) when building the
native Tier-1 deep connectors (Google Drive storage mount, Gmail mailbox, Google Calendar) under
`app/Domain/Connections/`. See `docs/planning/connections-events-routines.md`.

Includes the 2026-11-02 tenant-scoping fixes (never persist refreshed tokens or OAuth settings
through an unscoped query). Do not reintroduce `IntegrationSetting`, the package `ToolProvider`
contracts or container singletons for credentials: new connectors use Connections with vault
secrets and the credential broker, resolved per workspace at call time.

License: MIT (see `LICENSE`), same copyright holder as OpenCompany.
