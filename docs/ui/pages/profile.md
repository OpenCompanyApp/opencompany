# Profile

> User profile viewing and editing

---

## Routes & Access

| Page | Route | Name | Auth | Layout |
|------|-------|------|------|--------|
| **View** | `/profile/{id}` | `profile.show` | Required | None (standalone) |
| **Edit** | `/profile` | `profile.edit` | Required | AppLayout |
| **Update** | `PATCH /profile` | `profile.update` | Required | -- |
| **Delete** | `DELETE /profile` | `profile.destroy` | Required | -- |

---

## Profile View (`Show.vue`)

A standalone full-page view for any user or agent profile, loaded by UUID. Fetches data from `/api/users/{id}` and `/api/users/{id}/activity` on mount.

### Layout

```
+--------------------------------------------------------------+
| [<- Back]                                                    |
|                                                              |
| +----------------------------------------------------------+|
| | Profile Header Card                                       ||
| | +--------+  Name  [Agent Badge] [Ephemeral Badge]        ||
| | |        |  email@example.com                             ||
| | | Avatar |  [icon] Current task / Human Team Member       ||
| | | (80px) |  [Send Message] [Manage Agent]                 ||
| | |        |                                    +---------+ ||
| | +--------+                                    | Tasks   | ||
| |                                               | Done: N | ||
| |                                               +---------+ ||
| +----------------------------------------------------------+||
|                                                              |
| [Activity]  [Tasks]            <- Tab buttons                |
|                                                              |
| +----------------------------------------------------------+|
| | Tab Content                                                ||
| |                                                            ||
| | Activity tab:                                              ||
| |  [icon] Step description          started - ended  status ||
| |  [icon] Step description          started - ended  status ||
| |                                                            ||
| | Tasks tab:                                                 ||
| |  [icon] Task title                                 status ||
| |         Task description (1-line clamp)                    ||
| +----------------------------------------------------------+|
+--------------------------------------------------------------+
```

### Features

- **Back navigation** -- button calls `window.history.back()`
- **Avatar** -- renders `<img>` if user has avatar, otherwise colored initial circle; color varies by agent type (manager=purple, writer=green, analyst=cyan, etc.)
- **Status indicator** -- colored dot on avatar for agents (working=green, idle=amber, offline=neutral)
- **Agent badge** -- colored pill showing agent type (e.g. "manager Agent")
- **Ephemeral badge** -- amber pill when `isEphemeral` is true
- **Quick stats** -- completed tasks count in a small card
- **Action buttons** -- "Send Message" links to `/messages/{id}`; "Manage Agent" links to `/agent/{id}` (agents only); current user (`h1`) does not see "Send Message"
- **Tabbed content** -- two tabs: Activity (steps with status icons and timestamps) and Tasks (task list with status badges)
- **Reactive routing** -- watches `props.id` and re-fetches on change

### States

| State | Behavior |
|-------|----------|
| Loading | Skeleton placeholders for avatar (circle), name, and content card |
| Loaded | Full profile header + tabbed content |
| Not found | Centered empty state with `ph:user-circle` icon, "User not found" message |
| No activity | "No activity steps recorded" centered text |
| No tasks | "No tasks assigned" centered text |

---

## Profile Edit (`Edit.vue`)

The authenticated user's own profile settings page. Uses `AppLayout` and renders three stacked `Card` components, each containing a partial form.

### Layout

```
+--------------------------------------------------------------+
|  AppLayout (sidebar + main content)                          |
|  +--------------------------------------------------------+  |
|  |  max-w-2xl centered                                    |  |
|  |                                                        |  |
|  |  +--------------------------------------------------+  |  |
|  |  | Card: Profile Information                         |  |  |
|  |  |                                                   |  |  |
|  |  |  "Profile Information"                            |  |  |
|  |  |  Update your account's profile information...     |  |  |
|  |  |                                                   |  |  |
|  |  |  [Name input          ]                           |  |  |
|  |  |  [Email input         ]                           |  |  |
|  |  |  (Unverified email warning, if applicable)        |  |  |
|  |  |  [Save]  "Saved."                                 |  |  |
|  |  +--------------------------------------------------+  |  |
|  |                                                        |  |
|  |  +--------------------------------------------------+  |  |
|  |  | Card: Update Password                             |  |  |
|  |  |                                                   |  |  |
|  |  |  "Update Password"                                |  |  |
|  |  |  Ensure your account is using a long...           |  |  |
|  |  |                                                   |  |  |
|  |  |  [Current password    ]                           |  |  |
|  |  |  [New password        ]                           |  |  |
|  |  |  [Confirm password    ]                           |  |  |
|  |  |  [Save]  "Saved."                                 |  |  |
|  |  +--------------------------------------------------+  |  |
|  |                                                        |  |
|  |  +--------------------------------------------------+  |  |
|  |  | Card: Delete Account                              |  |  |
|  |  |                                                   |  |  |
|  |  |  "Delete Account"                                 |  |  |
|  |  |  Once your account is deleted, all of its...      |  |  |
|  |  |                                                   |  |  |
|  |  |  [Delete Account]  (danger button)                |  |  |
|  |  +--------------------------------------------------+  |  |
|  |                                                        |  |
|  +--------------------------------------------------------+  |
+--------------------------------------------------------------+
```

### Profile Information Form (`UpdateProfileInformationForm.vue`)

- **Fields:** Name (text), Email (email)
- **Pre-filled** from `usePage().props.auth.user`
- **Submit:** `PATCH` to `profile.update` route
- **Email verification:** if `mustVerifyEmail` prop is true and email is unverified, shows warning with "Click here to re-send" link that POSTs to `verification.send`
- **Success feedback:** "Saved." text fades in via `<Transition>` when `form.recentlySuccessful` is true

### Update Password Form (`UpdatePasswordForm.vue`)

- **Fields:** Current password, New password, Confirm password
- **Submit:** `PUT` to `password.update` route
- **Error handling:** on error, resets the relevant field and focuses it (password field errors focus password; current_password errors focus current_password)
- **Preserves scroll** on submit
- **Success feedback:** "Saved." text fades in, form resets on success

### Delete Account Form (`DeleteUserForm.vue`)

- **Trigger:** "Delete Account" danger button opens a confirmation modal
- **Modal:** title "Are you sure you want to delete your account?" with explanatory text, password input, Cancel button, and Delete Account danger button
- **Submit:** `DELETE` to `profile.destroy` route
- **Behavior:** modal closes on success; password input auto-focused on modal open; form resets on close

---

## Components

| Component | Source | Usage |
|-----------|--------|-------|
| `Icon` | `@/Components/shared/Icon.vue` | All icons (arrows, status, tabs) in Show |
| `SharedSkeleton` | `@/Components/shared/Skeleton.vue` | Loading state placeholders in Show |
| `Card` | `@/Components/shared/Card.vue` | Section wrappers in Edit |
| `Input` | `@/Components/shared/Input.vue` | All form fields in Edit partials |
| `Button` | `@/Components/shared/Button.vue` | Submit/action buttons in Edit partials |
| `Modal` | `@/Components/shared/Modal.vue` | Delete confirmation dialog |
| `Link` | `@inertiajs/vue3` | Navigation links (messages, agent, verification) |
| `AppLayout` | `@/Layouts/AppLayout.vue` | Page layout wrapper for Edit |

---

## Features & Interactions

| Feature | Description |
|---------|-------------|
| Inertia forms | Edit page uses `useForm` for all three sections with automatic error handling and processing state |
| API fetching | Show page uses raw `fetch()` to load user + activity data from REST API |
| Tab switching | Client-side tab toggle between Activity and Tasks in Show |
| Confirmation modal | Delete action requires password re-entry in a modal dialog |
| Transition animations | "Saved." confirmation text uses Vue `<Transition>` with ease-in-out |

---

## Responsive Behavior

| Breakpoint | Behavior |
|------------|----------|
| Desktop | Show: `max-w-4xl` centered layout; Edit: `max-w-2xl` centered within AppLayout |
| Mobile | Both pages use responsive padding (`p-6`); profile header stacks naturally with flexbox; AppLayout sidebar collapses on mobile for Edit |

---

## Files

| File | Purpose |
|------|---------|
| `resources/js/Pages/Profile/Show.vue` | Public profile view (any user/agent) |
| `resources/js/Pages/Profile/Edit.vue` | Authenticated user's own profile settings |
| `resources/js/Pages/Profile/Partials/UpdateProfileInformationForm.vue` | Name and email form |
| `resources/js/Pages/Profile/Partials/UpdatePasswordForm.vue` | Password change form |
| `resources/js/Pages/Profile/Partials/DeleteUserForm.vue` | Account deletion with confirmation modal |
| `app/Http/Controllers/ProfileController.php` | Backend controller for edit/update/destroy |
