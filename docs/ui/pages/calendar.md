# Calendar

> Full-featured calendar view for scheduling and managing events across month, week, and day views, with a mini calendar sidebar and event creation/editing modal.

---

## Route & Access

| Property | Value |
|----------|-------|
| **Route** | `/calendar` |
| **Name** | `calendar` |
| **Auth** | Required |
| **Layout** | AppLayout |

---

## Layout

```
+------------------------------------------------------------------+
| Header (shrink-0, border-b, px-6 py-4)                          |
| +--------------------------------------------------------------+ |
| | [<] [>]  "February 2026"  [Today]    [Month|Week|Day] [+New] | |
| +--------------------------------------------------------------+ |
+------------------------------------------------------------------+
| Main Area (flex-1, overflow-hidden, flex row)                    |
| +----------+---------------------------------------------------+ |
| | Sidebar  | Calendar View (flex-1, overflow-auto)              | |
| | (w-64)   |                                                   | |
| |          | Month View:                                       | |
| | Mini     | +-----------------------------------------------+ | |
| | Calendar | | Sun  Mon  Tue  Wed  Thu  Fri  Sat              | | |
| | [S M T   | +-------+-------+-------+-------+---+---+-------+ | |
| |  W T F S]| |  27  |  28  |  29  |  30  |  1 | 2 |   3   | | |
| |          | |       |       |       |       |   |   |       | | |
| | Quick    | +-------+-------+-------+-------+---+---+-------+ | |
| | Filters  | |   4  |   5  |   6  |   7  | 8 | 9 |  10   | | |
| |          | | event | event |       |       |   |   |       | | |
| |          | | event |       |       |       |   |   |       | | |
| |          | | +2more|       |       |       |   |   |       | | |
| |          | +-------+-------+-------+-------+---+---+-------+ | |
| |          | | (rows continue for full month...)               | | |
| |          | +-----------------------------------------------+ | |
| |          |                                                   | |
| |          | Week View:                                        | |
| |          | +---+-------+-------+-------+---+---+---+-------+ | |
| |          | |   | Sun 2 | Mon 3 | Tue 4 |...|   |   | Sat 8 | | |
| |          | +---+-------+-------+-------+---+---+---+-------+ | |
| |          | |6am|       |       |       |   |   |   |       | | |
| |          | |7am| event |       |       |   |   |   |       | | |
| |          | |...|       |       |       |   |   |   |       | | |
| |          | +---+-------+-------+-------+---+---+---+-------+ | |
| |          |                                                   | |
| |          | Day View:                                         | |
| |          | +-----------------------------------------------+ | |
| |          | | Hour-by-hour slots with events                  | | |
| |          | +-----------------------------------------------+ | |
| +----------+---------------------------------------------------+ |
+------------------------------------------------------------------+
| Event Modal (overlay)                                            |
| +--------------------------------------------------------------+ |
| | "New Event" / "Edit Event"                                   | |
| | Title, Start + End (2-col, datetime or date for all-day),   | |
| | [x] All day event                                            | |
| | Description, Color picker                                   | |
| | [Delete] (edit only)         [Cancel] [Save]                 | |
| +--------------------------------------------------------------+ |
+------------------------------------------------------------------+
```

---

## Components

| Component | Purpose |
|-----------|---------|
| `CalendarSidebar` | Left sidebar (w-64) with mini calendar, date navigation, and quick filters |
| `CalendarView` | Main calendar grid rendering month, week, or day views with event slots |
| `CalendarEventItem` | Individual event chip displayed within calendar day cells |
| `CalendarEventModal` | Modal form for creating and editing events with title, date/time, all-day toggle, description, and color |
| `Button` (shared) | Shared button component used for "New Event" header button |
| `Icon` (shared) | Iconify wrapper with Phosphor icons |

---

## Features & Interactions

### Period Navigation
- Left/right caret buttons navigate by month, week, or day depending on current view
- "Today" button resets to current date
- Period label dynamically formats:
  - Month view: "February 2026"
  - Week view: "Feb 2-8, 2026" (or cross-month: "Jan 28 - Feb 3, 2026")
  - Day view: "Wednesday, February 4, 2026"

### View Toggle
- Three-segment button group: Month, Week, Day
- Active segment uses dark filled style; inactive segments use muted text
- Switching views re-fetches events for the new date range

### Calendar Sidebar
- Mini calendar with month navigation and 7-column day grid
- Current day highlighted in blue; selected date highlighted in neutral
- Clicking a date updates both the mini calendar selection and the main view's `currentDate`
- Quick filters section below mini calendar

### Month View
- 7-column, 6-row grid of day cells
- Current month days styled normally; adjacent month days muted
- Today's date gets blue circle indicator
- Events rendered as `CalendarEventItem` chips (max 3 visible per day)
- "+N more" button when a day has more than 3 events
- Clicking a day cell opens the event creation modal with that date pre-selected
- Clicking an event opens the edit modal

### Week View
- 8-column grid: time labels column (w-16) plus 7 day columns
- Day headers show day name and date number (today highlighted in blue)
- Hourly time slots from early morning to evening
- Events positioned in appropriate time slots
- Click on time slot opens creation modal with that datetime

### Day View
- Single-day detailed view with hourly time slots
- Events shown at their scheduled times

### Event Creation/Editing
- Modal opens for new events (clicking empty slot) or editing (clicking existing event)
- Form fields: title (required), start datetime, end datetime, all-day toggle, description, color
- All-day toggle switches datetime inputs between `datetime-local` and `date` types
- Edit mode shows "Delete" button
- Save calls `POST /api/calendar/events` (create) or `PATCH /api/calendar/events/:id` (update)
- Delete calls `DELETE /api/calendar/events/:id`
- After save/delete: modal closes and events are re-fetched

### Data Fetching
- Events fetched via `fetch()` API directly (not via `useApi()` composable)
- Date range calculated based on current view:
  - Month: first day of first visible week to last day of last visible week
  - Week: Sunday through Saturday of current week
  - Day: current day
- Events re-fetched on period navigation and view changes
- Initial fetch on component mount

---

## States

| State | Description |
|-------|-------------|
| **Empty** | Calendar grid displays normally with empty day cells; no explicit empty state message |
| **Loading** | Centered spinner icon (`ph:spinner` with `animate-spin`) fills the full calendar area |
| **Error** | Console error logged; events array set to empty (graceful degradation to empty calendar) |

---

## Responsive Behavior

| Breakpoint | Changes |
|------------|---------|
| **All sizes** | Calendar sidebar is always present (w-64); no distinct mobile breakpoint handling in the page itself. The parent AppLayout handles mobile sidebar toggling. Calendar grid cells have `min-h-[100px]` for month view. |

---

## API Calls

| Function | Endpoint | Purpose |
|----------|----------|---------|
| `fetch()` | `GET /api/calendar/events?start=...&end=...` | Load events for visible date range |
| `fetch()` | `POST /api/calendar/events` | Create new event |
| `fetch()` | `PATCH /api/calendar/events/:id` | Update existing event |
| `fetch()` | `DELETE /api/calendar/events/:id` | Delete event |

---

## Files

| File | Purpose |
|------|---------|
| `resources/js/Pages/Calendar.vue` | Main page component with header navigation, view state, and event data management |
| `resources/js/Components/calendar/CalendarSidebar.vue` | Left sidebar with mini calendar grid, month navigation, and quick filters |
| `resources/js/Components/calendar/CalendarView.vue` | Main calendar rendering for month, week, and day views with event placement |
| `resources/js/Components/calendar/CalendarEventItem.vue` | Individual event chip displayed within calendar cells |
| `resources/js/Components/calendar/CalendarEventModal.vue` | Modal form for creating/editing events with date, time, and description fields |
| `resources/js/Components/shared/Button.vue` | Shared button used for "New Event" action |
| `resources/js/Components/shared/Icon.vue` | Iconify icon wrapper |
| `resources/js/types/index.ts` | TypeScript type: `CalendarEvent` |
