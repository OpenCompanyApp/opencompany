# Workspace Onboarding

> Setup, workspace creation, and invitation acceptance pages outside the normal workspace shell.

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
