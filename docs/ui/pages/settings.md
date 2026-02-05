# Settings

> Manage organization configuration, agent behavior defaults, action policies, notifications, and destructive operations.

---

## Route & Access

| Property | Value |
|----------|-------|
| **Route** | `/settings` |
| **Name** | `settings` |
| **Auth** | Required |
| **Layout** | AppLayout |

---

## Layout

```
+--------------------------------------------------------------------+
| h-full overflow-y-auto                                             |
| ┌────────────────────────────────────────────────────────────────┐ |
| │ max-w-3xl mx-auto p-6                                         │ |
| │                                                                │ |
| │ Header: "Settings"                                             │ |
| │ "Manage your organization and agent configuration"             │ |
| │                                                                │ |
| │ ┌──────────────────────────────────────────────────────────┐   │ |
| │ │ [icon] Organization                                      │   │ |
| │ ├──────────────────────────────────────────────────────────┤   │ |
| │ │  Organization Name    [___________________]              │   │ |
| │ │  Organization Email   [___________________]              │   │ |
| │ │  Timezone             [___________________]              │   │ |
| │ └──────────────────────────────────────────────────────────┘   │ |
| │                                                                │ |
| │ ┌──────────────────────────────────────────────────────────┐   │ |
| │ │ [icon] Agent Defaults                                    │   │ |
| │ ├──────────────────────────────────────────────────────────┤   │ |
| │ │  Default Agent Behavior  [Supervised________]            │   │ |
| │ │  Auto-spawn Agents       [==O ] toggle                   │   │ |
| │ └──────────────────────────────────────────────────────────┘   │ |
| │                                                                │ |
| │ ┌──────────────────────────────────────────────────────────┐   │ |
| │ │ [icon] Action Policies                    [+ Add policy] │   │ |
| │ ├──────────────────────────────────────────────────────────┤   │ |
| │ │  Document Operations                                     │   │ |
| │ │  write:documents/*   Approval > $10  [edit] [del]        │   │ |
| │ │                                                          │   │ |
| │ │  External API Calls                                      │   │ |
| │ │  execute:external/*  Require approval  [edit] [del]      │   │ |
| │ │                                                          │   │ |
| │ │  Read Operations                                         │   │ |
| │ │  read:*              Allowed           [edit] [del]      │   │ |
| │ └──────────────────────────────────────────────────────────┘   │ |
| │                                                                │ |
| │ ┌──────────────────────────────────────────────────────────┐   │ |
| │ │ [icon] Notifications                                     │   │ |
| │ ├──────────────────────────────────────────────────────────┤   │ |
| │ │  [x] Email Notifications                                │   │ |
| │ │  [ ] Slack Integration                                   │   │ |
| │ │  [x] Daily Summary                                       │   │ |
| │ └──────────────────────────────────────────────────────────┘   │ |
| │                                                                │ |
| │ ┌──────────────────────────────────────────────────────────┐   │ |
| │ │ [icon] Danger Zone                                       │   │ |
| │ ├──────────────────────────────────────────────────────────┤   │ |
| │ │  Pause All Agents                         [Pause All]    │   │ |
| │ │  ─────────────────────────────────────────────────────   │   │ |
| │ │  Reset Agent Memory                       [Reset]        │   │ |
| │ │  ─────────────────────────────────────────────────────   │   │ |
| │ │  Delete Organization                      [Delete]       │   │ |
| │ └──────────────────────────────────────────────────────────┘   │ |
| │                                                                │ |
| │                                    [Save Changes]              │ |
| └────────────────────────────────────────────────────────────────┘ |
+--------------------------------------------------------------------+
```

---

## Components

| Component | Path | Purpose |
|-----------|------|---------|
| `SettingsSection` | `Components/settings/SettingsSection.vue` | Bordered card section with icon header, title, description, actions slot |
| `SettingsField` | `Components/settings/SettingsField.vue` | Label + description + input slot + error/hint messages |
| `SharedButton` (Button) | `Components/shared/Button.vue` | Primary "Save Changes" button |
| `Modal` | `Components/shared/Modal.vue` | Policy create/edit modal |
| `Icon` | `Components/shared/Icon.vue` | Phosphor icon wrapper |

---

## Sections

### 1. Organization
- **Icon**: `ph:buildings`
- **Fields**:
  - Organization Name: text input, defaults to "Bloom Agency"
  - Organization Email: email input, defaults to "team@bloomagency.com"
  - Timezone: select dropdown with UTC, Eastern, Pacific, London, Amsterdam, Tokyo options

### 2. Agent Defaults
- **Icon**: `ph:robot`
- **Fields**:
  - Default Agent Behavior: select with options "Autonomous (minimal supervision)", "Supervised (ask before actions)", "Strict (require approval for everything)"
  - Auto-spawn Agents: custom toggle switch (not native checkbox); allows manager agents to spawn temporary agents

### 3. Action Policies
- **Icon**: `ph:shield-check`
- **Header action**: "+ Add policy" button opens the policy modal
- **Policy list**: each policy shown as a card with name, pattern (monospace), level description, edit/delete buttons
- **Policy levels**: Allow without approval, Require approval (with optional cost threshold), Block entirely
- **Modal fields**: Name (text), Pattern (monospace text, wildcard support), Policy Level (radio buttons), Cost Threshold (number, shown only for `require_approval` level)
- **Empty state**: shield icon, "No action policies configured" message

### 4. Notifications
- **Icon**: `ph:bell`
- **Fields** (all checkboxes):
  - Email Notifications: receive email for approval requests
  - Slack Integration: send notifications to Slack channel
  - Daily Summary: receive daily agent activity summary

### 5. Danger Zone
- **Icon**: `ph:warning`
- **Actions** (each with description and red-styled button):
  - Pause All Agents: immediately pause all running agent tasks
  - Reset Agent Memory: clear all agent memory and learned behaviors
  - Delete Organization: permanently delete organization and all data
- Separated by subtle dividers (`border-neutral-100`)

### Save Button
- Right-aligned `SharedButton` with `variant="primary"` and `size="lg"`
- Floppy disk icon + "Save Changes" text
- Currently logs settings to console (placeholder implementation)

---

## Features & Interactions

### Policy Management
- **Create**: "Add policy" opens Modal with empty form; "Create Policy" saves and closes
- **Edit**: pencil icon opens Modal pre-filled with policy data; button text changes to "Save Changes"
- **Delete**: trash icon removes the policy from the list directly
- **Form reset**: closing the modal resets form state and clears `editingPolicy` ref
- **Pattern syntax**: wildcard `*` supported (e.g., `write:documents/*`, `read:*`, `execute:external/*`)

### Toggle Switch (Auto-spawn)
- Custom-built toggle: hidden `sr-only` checkbox with styled div overlay
- Active: dark background (`bg-neutral-900 dark:bg-white`) with translated knob
- Inactive: muted background (`bg-neutral-200 dark:bg-neutral-700`)

### SettingsSection Component
- Supports `variant` prop: `'default'` or `'danger'`
- Danger variant changes header border to red, icon background to red tint, title text to red
- Hover border color shifts based on variant
- `#actions` slot in header for section-level action buttons

### SettingsField Component
- Supports `label`, `description`, `hint`, `error`, `required` props
- Error message shown with warning icon; hint shown when no error present
- `#aside` slot for extra content next to the label

---

## States

| State | Description |
|-------|-------------|
| **Default** | All sections rendered with current values; policies listed |
| **No policies** | Shield icon with "No action policies configured" message and hint |
| **Policy modal open** | Modal with form fields; title adapts to create/edit mode |
| **Saving** | Console log (placeholder); no visible loading indicator yet |

---

## Responsive Behavior

| Breakpoint | Changes |
|------------|---------|
| **All sizes** | Single-column layout constrained to `max-w-3xl`; all sections stack vertically; page scrolls vertically |

---

## Files

| File | Purpose |
|------|---------|
| `resources/js/Pages/Settings.vue` | Page component with all settings sections and policy modal |
| `resources/js/Components/settings/SettingsSection.vue` | Reusable section card with icon header and actions slot |
| `resources/js/Components/settings/SettingsField.vue` | Reusable field wrapper with label, hint, and error display |
| `resources/js/Components/shared/Button.vue` | Save button |
| `resources/js/Components/shared/Modal.vue` | Policy create/edit modal |
