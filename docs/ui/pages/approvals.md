# Approvals

> A focused review queue for managing pending approval requests with status filtering and inline approve/reject actions.

Status: "Direction" below follows the locked direction (aligned 2026-11-02); everything after it describes the page as it exists today.

## Direction (accepted, not implemented)

Approvals follow pillar 5 of the [Product Direction](../../PRODUCT_DIRECTION.md): one policy engine with three presets (Personal, Team, Company), **gated by reversibility, not by "write"** ([Governance Model](../../architecture/governance-model.md)). Reversible internal edits (change sets with "Revert this run"), trash and external drafts mostly auto-apply; approvals are kept for external sends, spend, exposure, purges and, depending on the preset, bulk or schema changes. The hard-deny floor is never approvable, and an agent never approves anything.

- **Where cards appear:** inline at the point in the run where they were raised, and in the needs-you group on [Home](../layouts.md#home); Personal also on Telegram. They go only to the responsible human's DM or Home, never to group chats. Each card states what will happen (rendered from the arguments), why, the source, an untrusted-content flag and a run link.
- **Who approves (per preset):** Personal: the owner is the only approver. Team: the agent's manager is the default approver; members may grant "always" for their own personal connections, while shared connections need an admin. Company: approver policies, two approvers on high-risk classes (for example purge, spend above a threshold, exposure).
- **First external send:** the first send, post or submit to a new target asks once, then offers one-tap **"always for this recipient / domain"**. In Company, routine-started runs may only reach admin-allowlisted targets.
- **Learned exceptions:** after repeated approvals of the same pattern (default 3), the card offers a scoped, exact-match "always allow" (for example "Ada may email @acme.com"). Admins can restrict who may accept.
- **Batched cards:** "Ada wants to send 12 follow-ups" is one card with a toggle per item.
- **Non-blocking:** a pending approval parks only its step; the agent continues other work.
- **Exception scopes:** once, this run, this task, time-limited, always; matched exactly on later calls. Runs that read untrusted content lose automatic approval for external actions, and a trigger is never consent.

This page stays as the full review queue and history. Approver policies and two-approver rules are Phase 3.

See [UI Structure and Agent Editing](../../planning/ui-structure-and-agent-editing.md), [Messenger UI](../../planning/messenger-ui.md) and the [sidebar mockup](../mockups/sidebar-2026-11.html). API-first (pillar 4): Inertia is frozen (no new Inertia pages or page props); new UI is built in the new Vue 3 + Reka UI client on the versioned public API and realtime stream, and old pages are deleted as it reaches parity ([API-First Client Split](../../architecture/api-first-client-split.md)).

---

## Route & Access

| Property | Value |
|----------|-------|
| **Route** | `/w/{workspace}/approvals` |
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
| `PATCH /api/approvals/{id}` | fetch | Update approval status (body: `{ status }`); the backend records `responded_by_id` from `auth()->id()` |

API calls go through `useApi()` as `fetchApprovals()` and `respondToApproval()`.

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
- The responder is resolved server-side from the authenticated user; the page does not send a responder ID

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
