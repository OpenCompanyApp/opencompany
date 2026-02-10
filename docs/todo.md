# Docs Feature TODO

## Quick wins

- [ ] **Document starring/pinning** — Add backend fields + API endpoints, frontend toggle in tree items. Mark important docs for quick access.
- [ ] **Content search** — Extend search to full-text content, not just titles/authors.
- [ ] **Publish controls** — Wire up existing `is_published`/`published_at` backend fields to frontend UI.

## Medium effort

- [ ] **Permission management UI** — Share modal with user picker for managing viewer/editor roles after doc creation.
- [ ] **Document sharing** — Share button, copy link, public/private toggle.
- [ ] **Fix DocumentAttachments API wiring** — Replace placeholder stub functions with actual `useApi` composable methods.

## Bigger features

- [ ] **Inline/selection-based comments** — Highlight text and comment on specific passages instead of doc-level only.
- [ ] **Document templates** — Pre-built templates for common doc types (meeting notes, specs, proposals, etc.).
- [ ] **Bulk operations** — Multi-select docs for move, delete, export, etc.

---

# Agent System TODO

## Budget Approval Type

The `ApprovalRequest` model already has an `amount` column and `type: 'budget'` is defined in the migration, but the budget approval flow is not yet implemented.

### What it does
Agents requesting approval when the estimated cost of an action exceeds a configurable threshold. This prevents runaway API costs from autonomous agents.

### What needs building
- [ ] **Cost threshold config** — Per-agent or per-workspace `cost_threshold` setting (e.g., $5.00). Could be a column on `users` table or a workspace-level setting.
- [ ] **Cost estimation in tool wrappers** — Before executing an expensive tool (LLM calls, external API calls), estimate the cost and compare against the threshold.
- [ ] **Budget approval creation** — When estimated cost exceeds threshold, create an `ApprovalRequest` with `type: 'budget'` and `amount` set to the estimated cost.
- [ ] **UI amount display** — Show the `amount` field on the Approvals page for budget-type requests, formatted as currency. The Approvals page already has a `formatCurrency` helper and renders `approval.amount` when present.
- [ ] **Running cost tracking** — Track cumulative costs per agent per day/session. Compare running total against threshold, not just individual actions.
- [ ] **Cost threshold UI** — Add cost threshold setting to AgentSettingsPanel (alongside behavior mode and sleep controls).
