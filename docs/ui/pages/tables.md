# Tables List

> Index page displaying all data tables as a card grid, with options to create and delete tables.

---

## Route & Access

| Property | Value |
|----------|-------|
| **Route** | `/tables` |
| **Name** | `tables` |
| **Auth** | Required |
| **Layout** | AppLayout |

---

## Layout

```
┌──────────────────────────────────────────────────────────────────┐
│  Header (sticky, border-b)                                       │
│  ┌────────────────────────────────────────────────────────────┐  │
│  │ Tables                                      [+ New Table]  │  │
│  │ Create and manage structured data tables                   │  │
│  └────────────────────────────────────────────────────────────┘  │
│                                                                  │
│  Content (flex-1, overflow-auto, p-6)                            │
│  ┌──────────────────┐ ┌──────────────────┐ ┌────────────────┐   │
│  │ [icon] Name      │ │ [icon] Name      │ │ [icon] Name    │   │
│  │        Desc...   │ │        Desc...   │ │        Desc... │   │
│  │ ──────────────── │ │ ──────────────── │ │ ────────────── │   │
│  │ 5 columns 42 row│ │ 3 columns 18 row│ │ 8 columns 0 ro│   │
│  └──────────────────┘ └──────────────────┘ └────────────────┘   │
│  ┌──────────────────┐ ┌──────────────────┐ ┌────────────────┐   │
│  │ ...              │ │ ...              │ │ ...            │   │
│  └──────────────────┘ └──────────────────┘ └────────────────┘   │
│                                                                  │
└──────────────────────────────────────────────────────────────────┘
```

### Table Card Detail

```
┌────────────────────────────────────────────────────┐
│                                          [...menu] │  <- hover-only
│  [icon]  Table Name (hover: blue)                  │
│  10x10   Description text, up to 2 lines...        │
│                                                    │
│  ────────────────────────────────────────────────── │
│  [columns icon] 5 columns   [rows icon] 42 rows    │
└────────────────────────────────────────────────────┘
  ^-- entire card is a Link to /tables/{id}
```

---

## Components

| Component | Purpose |
|-----------|---------|
| `TableCreateModal` | Modal for creating a new table with name, description, icon picker, and template selection (Blank, Tasks, Contacts, Inventory) |
| `ConfirmDialog` | Shared danger confirmation dialog for table deletion |
| `Button` | Shared button component for "New Table" actions |
| `DropdownMenu` | Shared dropdown for card context menu (Delete option) |
| `Icon` | Shared Iconify wrapper using Phosphor icons (`ph:` prefix) |

---

## Features & Interactions

### Table Grid Display
- Responsive grid: 1 column on mobile, 2 on `md`, 3 on `lg`
- Cards have 1px border, hover brightens border color
- Card icon defaults to `ph:table` if no custom icon set
- Description truncated to 2 lines (`line-clamp-2`)
- Footer row shows column count and row count

### Create Table
- "New Table" button in header opens `TableCreateModal`
- Modal fields: name (required), description (optional), icon picker (8 options), template selector
- Templates: Blank, Tasks (Title/Status/Due/Assignee), Contacts (Name/Email/Company/Phone), Inventory (Item/Quantity/Price/In Stock)
- On create: `POST /api/tables`, new table prepended to list

### Delete Table
- Hover card to reveal three-dot menu (top-right, opacity transition)
- Menu contains single "Delete" option (red)
- Opens `ConfirmDialog` with danger variant
- On confirm: `DELETE /api/tables/{id}`, table removed from list

### Navigation
- Clicking a table card navigates to `/tables/{id}` (Tables Detail page)

---

## States

| State | Description |
|-------|-------------|
| **Loading** | 6 skeleton cards in a 3-column grid, each `h-32` with `animate-pulse` |
| **Empty** | Centered column: table icon in circle, "No tables yet" heading, description text, "Create Table" button |
| **Populated** | Responsive grid of table cards with metadata footers |

---

## Responsive Behavior

| Breakpoint | Changes |
|------------|---------|
| **Mobile (`<md`)** | Single-column card grid |
| **Tablet (`md`)** | 2-column card grid |
| **Desktop (`lg+`)** | 3-column card grid |

---

## API Calls

| Endpoint | Method | Trigger |
|----------|--------|---------|
| `/api/tables` | GET | `onMounted` -- fetches all tables |
| `/api/tables` | POST | Create table modal submit |
| `/api/tables/{id}` | DELETE | Confirm delete dialog |

---

## Files

| File | Purpose |
|------|---------|
| `resources/js/Pages/Tables.vue` | Page component with grid layout, loading/empty states, and CRUD orchestration |
| `resources/js/Components/tables/TableCreateModal.vue` | Create table modal with name, description, icon picker, and template options |
| `resources/js/Components/shared/ConfirmDialog.vue` | Reusable confirmation dialog |
| `resources/js/Components/shared/Button.vue` | Shared button component |
| `resources/js/Components/shared/DropdownMenu.vue` | Shared dropdown menu component |
| `resources/js/Components/shared/Icon.vue` | Iconify icon wrapper |
