# Messages

> Legacy direct-message page components and current route behavior.

---

## Current Route Behavior

Direct messages are now part of the unified Chat surface. The web routes keep message URLs for compatibility, but they redirect:

| Route | Name | Current behavior |
|-------|------|------------------|
| `/w/{workspace}/messages` | `messages.index` | Redirects to `/w/{workspace}/chat` |
| `/w/{workspace}/messages/{id}` | `messages.show` | Redirects to `/w/{workspace}/chat?dm={id}` |

The active user-facing DM behavior is documented in [chat.md](chat.md). Profile and channel-info "send DM" links may still point to `/messages/{id}`; the route redirects those links into Chat.

---

## Legacy Vue Components

`resources/js/Pages/Messages/Index.vue` and `resources/js/Pages/Messages/Show.vue` still exist in the tree, but the current route table does not render them.

| Component | Historical behavior |
|-----------|---------------------|
| `Messages/Index.vue` | Direct-message conversation list with `GET /api/direct-messages` and `GET /api/users` |
| `Messages/Show.vue` | Direct conversation view with `GET/POST /api/dm/{userId}` and read markers |

Before reusing those components, cross-check them with the current chat DM path and API names. The current API route table exposes `api/direct-messages/*`; the legacy `api/dm/*` calls in `Messages/Show.vue` are not the route surface used by the current web redirects.

---

## Files

| File | Purpose |
|------|---------|
| `routes/web.php` | Redirects `/messages` and `/messages/{id}` into Chat |
| `resources/js/Pages/Chat.vue` | Current DM-capable chat page |
| `resources/js/Pages/Messages/Index.vue` | Legacy/direct page component, not routed |
| `resources/js/Pages/Messages/Show.vue` | Legacy/direct page component, not routed |
