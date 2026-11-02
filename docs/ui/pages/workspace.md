# Workspace Onboarding

> Setup, workspace creation, and invitation acceptance pages outside the normal workspace shell.

Status: "Direction" below follows the locked direction (aligned 2026-11-02); everything after it describes the page as it exists today.

## Direction (accepted, not implemented)

Onboarding chooses a governance preset (Personal, Team or Company) that sets defaults only and can be switched later without losing explicit grants (pillar 5; [Governance Model](../../architecture/governance-model.md)). It explains computer access as a per-agent choice (computer or VFS-only) and recommends a computer where one is available, without blocking setup (pillar 2).

- **Permissions live at Space level** (Team and Company). Spaces can be private, and personal items exist. There are no per-document ACLs.
- **Guests** can be given a single object as an explicit exception, never a whole Space by default.
- **Agents are Space members** like teammates ("Add Ada to Sales").
- Creating Spaces is a human decision (governed creation, pillar 7). The workspace switcher sits at the top of the rail.

See [UI Structure and Agent Editing](../../planning/ui-structure-and-agent-editing.md), [Messenger UI](../../planning/messenger-ui.md) and the [sidebar mockup](../mockups/sidebar-2026-11.html). API-first (pillar 4): Inertia is frozen (no new Inertia pages or page props); new UI is built in the new Vue 3 + Reka UI client on the versioned public API and realtime stream, and old pages are deleted as it reaches parity ([API-First Client Split](../../architecture/api-first-client-split.md)).

---

## Routes & Access

| Page | Route | Name | Auth |
|------|-------|------|------|
| First setup | `/setup` | `setup` | Public for first account/workspace; logged-in users with a workspace redirect home |
| Create workspace | `/create-workspace` | `workspace.create` | `auth`, `verified` |
| Accept invite | `/invite/{token}` | `invite.accept` | Public; form changes depending on logged-in state |

---

## Setup

`Workspace/Setup.vue` uses `GuestLayout` and posts through Inertia to `POST /setup`.

| State | Fields |
|-------|--------|
| Logged out | Name, email, password, password confirmation, workspace name |
| Logged in without workspace | Workspace name only |

The route redirects authenticated users who already have a workspace back to `home`.

---

## Create Workspace

`Workspace/Create.vue` is a standalone centered form.

- Shows a live `WorkspaceIcon` preview.
- Captures workspace name, slug, icon, and color.
- Generates slug from workspace name on input.
- Submits to `POST /api/workspaces`.
- Redirects to `/w/{createdSlug}` after success.

---

## Accept Invitation

`Workspace/Invite.vue` handles already-accepted, logged-in, and logged-out invitation states.

| State | Behavior |
|-------|----------|
| Already accepted | Shows success state and link to `/w/{workspace.slug}` |
| Logged in | Calls `POST /api/invitations/{token}/accept`, then visits the workspace |
| Logged out | Collects name/password/password confirmation, calls the same API, then visits the workspace |
| Decline | Visits `/` |

---

## Files

| File | Purpose |
|------|---------|
| `resources/js/Pages/Workspace/Setup.vue` | First account/workspace setup |
| `resources/js/Pages/Workspace/Create.vue` | Authenticated workspace creation |
| `resources/js/Pages/Workspace/Invite.vue` | Invitation acceptance |
| `app/Http/Controllers/SetupController.php` | `/setup` POST handler |
| `app/Http/Controllers/Api/WorkspaceController.php` | `POST /api/workspaces` |
| `app/Http/Controllers/Api/InvitationController.php` | Invitation accept API |
