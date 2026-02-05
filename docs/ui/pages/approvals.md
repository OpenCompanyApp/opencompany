# Approvals

> A focused review queue for managing pending approval requests with status filtering and inline approve/reject actions.

---

## Route & Access

| Property | Value |
|----------|-------|
| **Route** | `/approvals` |
| **Name** | `approvals` |
| **Auth** | Required (`auth`, `verified`) |
| **Layout** | AppLayout |

---

## Layout

```
+------------------------------------------------------------------+
| h-full overflow-y-auto                                            |
| max-w-3xl mx-auto p-6                                            |
|                                                                    |
| +--------------------------------------------------------------+ |
| | Header                                                        | |
| | h1 "Approvals"                                                | |
| | "Review and manage pending requests"                          | |
| +--------------------------------------------------------------+ |
|                                                                    |
| +--------------------------------------------------------------+ |
| | Filters                                                        | |
| | [All 12] [Pending 3] [Approved 7] [Rejected 2]               | |
| +--------------------------------------------------------------+ |
|                                                                    |
| +--------------------------------------------------------------+ |
| | Approvals List (bordered card, divided rows)                  | |
| |                                                                | |
| | +----------------------------------------------------------+ | |
| | | "Deploy to production"                   [Approve][Reject]| | |
| | | Agent Alpha . 2h ago                                      | | |
| | | "Requesting permission to deploy v2.1"                    | | |
| | | $1,250.00                                                 | | |
| | +----------------------------------------------------------+ | |
| | +----------------------------------------------------------+ | |
| | | "Access customer data"                        Approved    | | |
| | | Agent Beta . 1d ago                                       | | |
| | | Approved by Admin User                                    | | |
| | +----------------------------------------------------------+ | |
| |                                                                | |
| +--------------------------------------------------------------+ |
+------------------------------------------------------------------+
```

---

## Components

| Component | Path | Purpose |
|-----------|------|---------|
| `Icon` | `Components/shared/Icon.vue` | Check-circle icon in empty state |

The page is self-contained with no child components.

---

## Data & API

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `GET /api/approvals` | fetch | Load all approval requests |
| `PATCH /api/approvals/{id}` | fetch | Update approval status (body: `{ status, respondedById }`) |

API calls use raw `fetch()` directly, not the `useApi()` composable.

---

## Features & Interactions

### Status Filters
- Toggle button group: All, Pending (default), Approved, Rejected
- Each filter shows a count badge
- Active filter uses inverted style (`bg-neutral-900 text-white` / dark mode reversed)
- Filtering is client-side on the loaded approvals array

### Approval Actions
- **Pending items**: Show "Approve" and "Reject" buttons on the right side
- Buttons disable while processing (`processing` ref tracks current approval ID)
- On action: calls `PATCH /api/approvals/{id}`, then re-fetches all approvals
- Responder is hardcoded as `h1` (current user)

### Approval Row Content
- Title (bold), requester name, relative timestamp
- Optional description (line-clamp-2)
- Optional amount formatted as USD currency
- For resolved approvals: status text ("Approved" / "Rejected") with responder name

---

## States

| State | Description |
|-------|-------------|
| **Loading** | Three pulse-animated skeleton rectangles |
| **Empty (pending)** | Check-circle icon with "All caught up" |
| **Empty (other filter)** | Check-circle icon with "No approvals found" |
| **Default** | Bordered card with divided approval rows |
| **Processing** | Approve/reject buttons disabled for the item being processed |

---

## Responsive Behavior

| Breakpoint | Changes |
|------------|---------|
| All | Single-column layout. `max-w-3xl` centered container adapts to viewport width. No breakpoint-specific layout changes. |

---

## Files

| File | Purpose |
|------|---------|
| `resources/js/Pages/Approvals.vue` | Page component (self-contained) |
| `resources/js/Components/shared/Icon.vue` | Icon component |
