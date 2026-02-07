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
