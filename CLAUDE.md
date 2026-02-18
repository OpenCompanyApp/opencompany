# OpenCompany Project Rules

## Local Development

- **Local URL**: `http://opencompany.test` (Laravel Valet domain, no SSL)
- **Ngrok URL**: `https://your-subdomain.ngrok-free.dev` (used for Telegram webhooks and external integrations; set up your own via `ngrok http 80`)
- When testing or navigating to the app locally, always use `http://opencompany.test`

## Tech Stack

- **Backend**: Laravel 12
- **Frontend**: Vue 3 + Inertia.js
- **Styling**: Tailwind CSS v4 + Reka UI (headless primitives)
- **Icons**: @iconify/vue with Phosphor icons (`ph:` prefix)

## Multi-Workspace Architecture

- The app supports multiple workspaces. All data is workspace-scoped.
- **URL structure**: `/w/{workspace_slug}/...` — the slug identifies the active workspace.
- **Middleware**: `ResolveWorkspace` resolves the workspace from URL slug, `X-Workspace-Id` header, session, or user's first workspace (fallback). Binds it to the container as `currentWorkspace`.
- **Helper**: `workspace()` returns the current `Workspace` model from the container.
- **Model scoping**: Models use the `BelongsToWorkspace` trait which provides `scopeForWorkspace()` (explicit, not a global scope). Use `Model::forWorkspace()->...` in queries.
- **Humans** belong to workspaces via the `workspace_members` pivot table (many-to-many). **Agents** have a direct `workspace_id` column (belong to one workspace).
- **Frontend**: `useWorkspace()` composable provides `workspace`, `workspaces`, `workspacePath()`, and `isAdmin`. Workspace switcher is in the sidebar header.
- When adding new queries, always scope by workspace. For models with `workspace_id`, use `forWorkspace()`. For related models (e.g., messages via channels), use `whereHas` to filter through the relation.

## Component Structure

- Shared components are in `resources/js/Components/shared/`
- Use the wrapper components (Button, Modal, Badge, etc.) instead of native elements for consistency
- Dark mode is supported via the `useColorMode` composable
