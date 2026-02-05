# Tables Detail

> Full data table editor with spreadsheet-style grid, multi-view support (grid, kanban, gallery, calendar), and inline cell editing.

---

## Route & Access

| Property | Value |
|----------|-------|
| **Route** | `/tables/{id}` |
| **Name** | `tables.show` |
| **Auth** | Required |
| **Layout** | AppLayout |
| **Props** | `tableId: string` (table UUID) |

---

## Layout

```
┌──────────────────────────────────────────────────────────────────┐
│  TableHeader (border-b)                                          │
│  ┌────────────────────────────────────────────────────────────┐  │
│  │ [<-] [icon] Table Name         [Import] [Export] [gear][.]│  │
│  │             Description...                                 │  │
│  └────────────────────────────────────────────────────────────┘  │
│                                                                  │
│  Toolbar (border-b, bg-neutral-50)                               │
│  ┌────────────────────────────────────────────────────────────┐  │
│  │ [Grid][Kanban][Gallery][Calendar][+] | [Search] |  [F][S] │  │
│  │                                       ^filter ^sort        │  │
│  │                              (selection: "3 selected [del])│  │
│  └────────────────────────────────────────────────────────────┘  │
│                                                                  │
│  TableGrid (flex-1, overflow-auto)                               │
│  ┌────────────────────────────────────────────────────────────┐  │
│  │ [x] │ Name      │ Email     │ Status   │ Due Date │ [+]  │  │
│  ├─────┼───────────┼───────────┼──────────┼──────────┼───────┤  │
│  │ [ ] │ Alice     │ a@co.com  │ Active   │ 2025-02  │ [del]│  │
│  │ [ ] │ Bob       │ b@co.com  │ Done     │ 2025-01  │ [del]│  │
│  │     │                                                      │  │
│  │ [+ Add row]                                                │  │
│  └────────────────────────────────────────────────────────────┘  │
│                                                                  │
│  Footer (border-t, bg-neutral-50)                                │
│  ┌────────────────────────────────────────────────────────────┐  │
│  │ 42 rows                                                    │  │
│  └────────────────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────────────┘
```

### TableHeader Detail

```
┌────────────────────────────────────────────────────────────────┐
│ [<- /tables]  [icon picker]  [editable name input]             │
│                              [editable description input]      │
│                                                                │
│              [Import v]  [Export v]  |  [gear]  [... menu]     │
│               CSV/JSON    CSV/JSON      ^settings  ^duplicate  │
│                                                      delete    │
└────────────────────────────────────────────────────────────────┘
```

### Toolbar Detail

```
┌────────────────────────────────────────────────────────────────┐
│  View Tabs          │  Search        │  Controls   │Selection  │
│ ┌────┐┌──────┐┌───┐│ ┌────────────┐ │ ┌───┐┌───┐ │ 3 sel [x] │
│ │Grid││Kanban││...││ │Search...   │ │ │ F ││ S │ │           │
│ └────┘└──────┘└───┘│ └────────────┘ │ └───┘└───┘ │           │
│ [+ add view]       │                │             │           │
└────────────────────────────────────────────────────────────────┘
```

### TableGrid Detail

```
┌─────┬───────────────┬───────────────┬───────────────┬─────┐
│ [x] │ [T] Name    v │ [#] Amount  v │ [D] Date    v │ [+] │
├─────┼───────────────┼───────────────┼───────────────┼─────┤
│ [ ] │ inline edit   │ inline edit   │ date picker   │ [x] │  <- hover
│ [ ] │ inline edit   │ inline edit   │ date picker   │ [x] │
│ [ ] │ inline edit   │ inline edit   │ date picker   │ [x] │
├─────┴───────────────┴───────────────┴───────────────┴─────┤
│ [+ Add row]                                               │
└───────────────────────────────────────────────────────────┘

Column header dropdown:
  Sort Ascending / Sort Descending
  ─────────────────────────────────
  Edit Column / Delete Column
```

---

## Components

| Component | Purpose |
|-----------|---------|
| `TableHeader` | Header with back link, icon picker dropdown, inline-editable name and description inputs, import/export dropdowns, settings button, and more-options menu (duplicate, delete) |
| `TableGrid` | Spreadsheet-style table with sticky header, row checkboxes, sortable columns, inline-editable cells via `TableCell`, add-row/add-column buttons, and delete confirmations |
| `TableCell` | Polymorphic cell renderer handling 8 column types: text, number, date, checkbox, select, multiselect, URL, email -- each with inline editing |
| `TableTabs` | Standalone view tab bar (not used directly in Show.vue, but available); view switching, filter/hide/sort controls |
| `ColumnTypeModal` | Modal for adding or editing a column: name input, type grid selector (8 types), select/multiselect option editor, required toggle, type-change warning |
| `ConfirmDialog` | Shared confirmation dialog used for row deletion, bulk deletion, and table deletion |
| `SearchInput` | Shared search input component with icon |
| `Button` | Shared button component |
| `DropdownMenu` | Shared dropdown for view creation, column menus |
| `Icon` | Shared Iconify wrapper using Phosphor icons (`ph:` prefix) |

---

## Features & Interactions

### View Modes

The toolbar supports 4 view types. Views are managed client-side as an array.

| View | Icon | Description |
|------|------|-------------|
| **Grid** | `ph:table` | Default spreadsheet view with `TableGrid` component |
| **Kanban** | `ph:kanban` | Board view (defined in view options, not yet rendered) |
| **Gallery** | `ph:squares-four` | Card gallery view (defined in view options, not yet rendered) |
| **Calendar** | `ph:calendar` | Calendar view (defined in view options, not yet rendered) |

- Active view highlighted with white bg and shadow
- "+" button opens dropdown to add Grid, Kanban, Gallery, or Calendar view
- A default "Grid" view is auto-created if none exist

### Inline Cell Editing

`TableCell` renders different editors per column type:

| Column Type | Editor | Behavior |
|-------------|--------|----------|
| `text` | Plain text input | Saves on blur or Enter |
| `number` | Number input | Saves on blur or Enter |
| `date` | Native date picker | Saves on change |
| `checkbox` | `Checkbox` component | Saves immediately on toggle |
| `select` | Native `<select>` | Saves on change |
| `multiselect` | Badge list + dropdown | Add/remove items via dropdown and remove badges |
| `url` | Display as link, edit on pencil click | Clickable link, edit mode on icon click, saves on blur |
| `email` | Display as mailto link, edit on pencil click | Same pattern as URL |

### Column Management
- **Add column**: "+" button in last header column opens `ColumnTypeModal`
- **Edit column**: Column header dropdown menu item opens `ColumnTypeModal` with existing data
- **Delete column**: Column header dropdown confirms via `ConfirmDialog`
- **Sort column**: Column header dropdown provides ascending/descending sort (client-side)
- Column type icons: text (`ph:text-aa`), number (`ph:hash`), date (`ph:calendar`), checkbox (`ph:check-square`), select (`ph:list`), multiselect (`ph:list-checks`), url (`ph:link`), email (`ph:envelope`)

### Row Management
- **Add row**: Button at bottom of table body, creates row with default values per type
- **Delete row**: Trash icon on hover per row, confirmed via `ConfirmDialog`
- **Bulk delete**: Select rows via checkboxes, "N selected" appears in toolbar with delete button, confirmed via `ConfirmDialog`
- **Select all**: Header checkbox toggles all rows

### Search & Filter
- `SearchInput` in toolbar filters rows client-side by matching any cell value
- Filter and sort panel toggles (buttons present, panels not yet implemented)

### Table Header Actions
- **Name/Description**: Inline editable text inputs, saved on blur via `PATCH /api/tables/{id}`
- **Icon picker**: Dropdown with 12 icon options, saved via `PATCH /api/tables/{id}`
- **Import**: Dropdown with CSV and JSON options, triggers hidden file input
- **Export**: Dropdown with CSV and JSON options, downloads file as blob
- **Duplicate table**: `POST /api/tables/{id}/duplicate`, navigates to new table
- **Delete table**: `ConfirmDialog`, then `DELETE /api/tables/{id}`, redirects to `/tables`

---

## States

| State | Description |
|-------|-------------|
| **Loading** | Centered spinner icon (`ph:spinner`, `animate-spin`) |
| **Not Found** | Warning icon, "Table not found" text, and "Back to Tables" link |
| **Empty Grid** | Rows icon in circle, "No rows yet" text, and "Add row" button centered in table body |
| **Populated** | Full spreadsheet grid with header, data rows, and add-row button |

---

## Responsive Behavior

| Breakpoint | Changes |
|------------|---------|
| **Desktop** | Full spreadsheet with horizontal scroll; all toolbar controls visible |
| **Mobile** | Same layout but relies on horizontal scrolling for wide tables; toolbar may wrap |

---

## API Calls

| Endpoint | Method | Trigger |
|----------|--------|---------|
| `/api/tables/{id}` | GET | `onMounted` -- fetches table with columns |
| `/api/tables/{id}/rows` | GET | `onMounted` -- fetches all rows |
| `/api/tables/{id}` | PATCH | Inline edit of name, description, or icon |
| `/api/tables/{id}` | DELETE | Delete table confirmation |
| `/api/tables/{id}/duplicate` | POST | Duplicate from header menu |
| `/api/tables/{id}/export?format=csv\|json` | GET | Export button |
| `/api/tables/{id}/import` | POST | Import file upload (FormData) |
| `/api/tables/{id}/rows` | POST | Add row button |
| `/api/tables/{id}/rows/{rowId}` | PATCH | Inline cell edit |
| `/api/tables/{id}/rows/{rowId}` | DELETE | Delete row confirmation |
| `/api/tables/{id}/rows/bulk-delete` | POST | Bulk delete selected rows |
| `/api/tables/{id}/columns` | POST | Add column modal |
| `/api/tables/{id}/columns/{colId}` | PATCH | Edit column modal or inline update |
| `/api/tables/{id}/columns/{colId}` | DELETE | Delete column confirmation |

---

## Files

| File | Purpose |
|------|---------|
| `resources/js/Pages/Tables/Show.vue` | Page component orchestrating header, toolbar, grid, modals, and all CRUD logic |
| `resources/js/Components/tables/TableHeader.vue` | Header with back link, inline-editable name/description, icon picker, import/export, and more-options menu |
| `resources/js/Components/tables/TableGrid.vue` | Spreadsheet grid with sticky header, checkboxes, sortable columns, inline cells, and row/column CRUD |
| `resources/js/Components/tables/TableCell.vue` | Polymorphic cell renderer for 8 column types with inline editing |
| `resources/js/Components/tables/TableTabs.vue` | Standalone view tab bar component with filter/hide/sort controls |
| `resources/js/Components/tables/ColumnTypeModal.vue` | Modal for adding/editing columns with type grid, option editor, and required toggle |
| `resources/js/Components/tables/TableCreateModal.vue` | Modal for creating new tables (used on list page, not here) |
| `resources/js/Components/shared/ConfirmDialog.vue` | Reusable confirmation dialog |
| `resources/js/Components/shared/SearchInput.vue` | Search input with icon |
| `resources/js/Components/shared/Button.vue` | Shared button component |
| `resources/js/Components/shared/DropdownMenu.vue` | Shared dropdown menu component |
| `resources/js/Components/shared/Checkbox.vue` | Shared checkbox component |
| `resources/js/Components/shared/Badge.vue` | Badge for multiselect cell display |
