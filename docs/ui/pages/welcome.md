# Welcome

> The public landing page showing Laravel branding, documentation links, and login/register navigation for unauthenticated visitors.

---

## Route & Access

| Property | Value |
|----------|-------|
| **Route** | `/welcome` |
| **Name** | `welcome` |
| **Auth** | Public (no middleware) |
| **Layout** | None (standalone, no AppLayout) |

---

## Layout

```
+------------------------------------------------------------------+
| bg-neutral-50 dark:bg-black, min-h-screen                        |
| centered flex column                                              |
|                                                                    |
| Background SVG (absolute, top-left)                               |
|                                                                    |
| +--------------------------------------------------------------+ |
| | max-w-2xl / lg:max-w-7xl                                     | |
| |                                                                | |
| | Header (grid: 2-col / lg:3-col)                              | |
| | +---------------------------+  +---------------------------+  | |
| | | Laravel Logo (centered)   |  | [Dashboard] or            |  | |
| | |                           |  | [Log in] [Register]       |  | |
| | +---------------------------+  +---------------------------+  | |
| |                                                                | |
| | Main (grid: lg:2-col)                                        | |
| | +---------------------------+  +---------------------------+  | |
| | | Documentation Card        |  | Laracasts Card            |  | |
| | | (row-span-3 on lg)        |  +---------------------------+  | |
| | | Screenshot + description  |  | Laravel News Card         |  | |
| | |                           |  +---------------------------+  | |
| | +---------------------------+  | Vibrant Ecosystem Card    |  | |
| |                                +---------------------------+  | |
| |                                                                | |
| | Footer                                                        | |
| | "Laravel vX.Y (PHP vX.Y)"                                    | |
| +--------------------------------------------------------------+ |
+------------------------------------------------------------------+
```

---

## Components

| Component | Path | Purpose |
|-----------|------|---------|
| `Head` | `@inertiajs/vue3` | Sets page `<title>` to "Welcome" |
| `Link` | `@inertiajs/vue3` | Navigation links (Dashboard, Log in, Register) |

This is the default Laravel welcome page. No custom child components are used.

---

## Props (from Server)

| Prop | Type | Purpose |
|------|------|---------|
| `canLogin` | `boolean` | Whether to show login link (route exists) |
| `canRegister` | `boolean` | Whether to show register link (route exists) |
| `laravelVersion` | `string` | Displayed in footer |
| `phpVersion` | `string` | Displayed in footer |

---

## Features & Interactions

### Navigation
- If user is authenticated: shows "Dashboard" link to `route('dashboard')`
- If not authenticated: shows "Log in" link and optionally "Register" link
- Navigation visibility controlled by `canLogin` and `canRegister` props

### Content Cards
- **Documentation**: Links to `https://laravel.com/docs` with screenshot preview. Screenshot has error handler that hides the image container on failure.
- **Laracasts**: Links to `https://laracasts.com` with description
- **Laravel News**: Links to `https://laravel-news.com` with description
- **Vibrant Ecosystem**: Static card (not a link) listing Laravel tools (Forge, Vapor, Nova, Envoyer, Herd, Cashier, Dusk, Echo, Horizon, Sanctum, Telescope)

### Background
- Decorative SVG positioned absolutely at top-left
- Hidden via `handleImageError` if the background image fails to load

---

## States

| State | Description |
|-------|-------------|
| **Authenticated** | Header shows "Dashboard" link |
| **Unauthenticated** | Header shows "Log in" and optionally "Register" |
| **Image error** | Screenshot container hidden, docs card layout adjusts |

---

## Responsive Behavior

| Breakpoint | Changes |
|------------|---------|
| `< lg` | Header grid is 2-column. Content grid is single column. Max width is `max-w-2xl`. |
| `lg+` | Header grid is 3-column (logo centered). Content grid is 2-column with docs card spanning 3 rows. Max width is `max-w-7xl`. Larger logo. Card padding increases to `p-10`. |

---

## Files

| File | Purpose |
|------|---------|
| `resources/js/Pages/Welcome.vue` | Page component (standalone, no layout) |
