# OpenCompany Design System

> The master UI/UX reference for OpenCompany. This document defines every visual token,
> pattern, and convention used across the application. A new developer should be able to
> read this guide end-to-end and fully understand the visual language of the product.

---

## 1. Design Philosophy

OpenCompany follows a **Slack-meets-Notion** aesthetic: clean surfaces, neutral palettes,
and deliberate restraint. The interface should feel like a professional tool that gets
out of the way and lets content speak.

### Core Principles

| Principle | What it means in practice |
|---|---|
| Quiet confidence | No gradients, no glow effects, no animated flourishes. Every element earns its place. |
| Border-based cards | Cards are defined by 1px borders, not drop shadows. Elevation is communicated through subtle border color shifts and minimal shadow on hover. |
| Single accent color | One blue (`oklch(0.55 0.15 250)`) carries all interactive affordance. Nothing else competes for attention. |
| Neutral hierarchy | Text importance is communicated through lightness alone (black > muted gray > subtle gray), never through color. |
| No spring animations | All transitions use simple CSS easing. No bounce, no overshoot, no physics-based spring curves. |
| Content density | Comfortable but compact. Enough whitespace to breathe, not so much that it feels empty. |

### What We Avoid

- Gradients of any kind (linear, radial, conic)
- Colored glow/bloom shadows
- Glassmorphism blur effects (glass surfaces are solid with high opacity)
- Heavy drop shadows as a primary card treatment
- Multiple accent colors or color-coded sections
- Animated SVG illustrations or loading lottie files

---

## 2. Color System

All colors use the **OKLCH** color space for perceptual uniformity. The system is built
around a pure neutral palette (chroma 0) with a single blue accent hue at 250 degrees.

### 2.1 Background Colors

| Token | Light Mode | Dark Mode | Usage |
|---|---|---|---|
| `--color-olympus-bg` | `oklch(0.985 0 0)` | `oklch(0.13 0 0)` | Page background |
| `--color-olympus-sidebar` | `oklch(0.97 0 0)` | `oklch(0.11 0 0)` | Sidebar panel |
| `--color-olympus-surface` | `oklch(1 0 0)` | `oklch(0.16 0 0)` | Card/panel surfaces |
| `--color-olympus-elevated` | `oklch(1 0 0)` | `oklch(0.18 0 0)` | Modals, popovers, elevated surfaces |

### 2.2 Glass Surfaces

Glass surfaces are **solid backgrounds with high opacity**, not blurred backdrops.

| Token | Light Mode | Dark Mode | Usage |
|---|---|---|---|
| `--color-olympus-glass` | `oklch(1 0 0 / 0.98)` | `oklch(0.16 0 0 / 0.98)` | Floating headers, sticky elements |
| `--color-olympus-glass-hover` | `oklch(0.98 0 0)` | `oklch(0.20 0 0)` | Glass surface hover state |

### 2.3 Border Colors

| Token | Light Mode | Dark Mode | Usage |
|---|---|---|---|
| `--color-olympus-border` | `oklch(0.90 0 0)` | `oklch(0.28 0 0)` | Standard borders, card outlines |
| `--color-olympus-border-subtle` | `oklch(0.94 0 0)` | `oklch(0.22 0 0)` | Subtle dividers, inactive borders |

### 2.4 Primary (Blue Accent)

The single interactive color. Used for buttons, links, focus rings, and active states.

| Token | Light Mode | Dark Mode | Usage |
|---|---|---|---|
| `--color-olympus-primary` | `oklch(0.55 0.15 250)` | `oklch(0.60 0.15 250)` | Primary buttons, links, active indicators |
| `--color-olympus-primary-hover` | `oklch(0.50 0.15 250)` | `oklch(0.65 0.15 250)` | Hover state of primary elements |
| `--color-olympus-primary-muted` | `oklch(0.55 0.15 250 / 0.08)` | `oklch(0.60 0.15 250 / 0.12)` | Selection highlight, subtle backgrounds |
| `--color-olympus-primary-glow` | `oklch(0.55 0.15 250 / 0.15)` | `oklch(0.60 0.15 250 / 0.2)` | Subtle emphasis backgrounds |

### 2.5 Accent Colors

| Token | Value | Usage |
|---|---|---|
| `--color-olympus-accent` | `oklch(0.55 0.15 250)` | Alias for primary |
| `--color-olympus-accent-cyan` | `oklch(0.55 0.10 220)` | Alternate accent (rare usage) |

### 2.6 Text Colors

```
Light Mode Hierarchy          Dark Mode Hierarchy
========================      ========================
--text     oklch(0.15 0 0)    --text     oklch(0.95 0 0)
  |  Nearly black               |  Nearly white
  v                              v
--muted    oklch(0.45 0 0)    --muted    oklch(0.70 0 0)
  |  Mid gray                    |  Mid gray
  v                              v
--subtle   oklch(0.60 0 0)    --subtle   oklch(0.50 0 0)
     Light gray                      Dark gray
```

| Token | Light Mode | Dark Mode | Usage |
|---|---|---|---|
| `--color-olympus-text` | `oklch(0.15 0 0)` | `oklch(0.95 0 0)` | Headings, primary body text |
| `--color-olympus-text-muted` | `oklch(0.45 0 0)` | `oklch(0.70 0 0)` | Secondary labels, descriptions |
| `--color-olympus-text-subtle` | `oklch(0.60 0 0)` | `oklch(0.50 0 0)` | Placeholders, timestamps, metadata |

### 2.7 Semantic / Status Colors

| Token | Light Mode | Dark Mode | Usage |
|---|---|---|---|
| `--color-olympus-success` | `oklch(0.50 0.10 145)` | `oklch(0.60 0.12 145)` | Success states, completed items |
| `--color-olympus-warning` | `oklch(0.55 0.08 70)` | `oklch(0.65 0.10 70)` | Warning states, attention needed |
| `--color-olympus-error` | `oklch(0.50 0.12 25)` | `oklch(0.60 0.14 25)` | Error states, destructive actions |

### 2.8 Agent Status Colors

| Token | Light Mode | Dark Mode | Usage |
|---|---|---|---|
| `--color-agent-working` | `oklch(0.50 0.10 145)` | `oklch(0.60 0.12 145)` | Agent actively processing |
| `--color-agent-idle` | `oklch(0.60 0 0)` | `oklch(0.50 0 0)` | Agent available but idle |
| `--color-agent-offline` | `oklch(0.75 0 0)` | `oklch(0.35 0 0)` | Agent unavailable |

### 2.9 Agent Type Colors

All agent types use the same neutral gray (`oklch(0.45 0 0)`) to avoid visual noise.
There is no color-based distinction between manager, writer, analyst, creative,
researcher, coder, or coordinator agents.

---

## 3. Typography

### 3.1 Font Stack

```css
font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont,
             'Segoe UI', Roboto, sans-serif;
```

### 3.2 Font Feature Settings

Inter is configured with specific OpenType feature flags for improved readability:

| Feature | Code | Effect |
|---|---|---|
| Alternate digits | `cv02` | Open style 4 |
| Alternate lowercase a | `cv03` | Single-storey a |
| Alternate lowercase l | `cv04` | Serif on lowercase l |
| Single-storey g | `cv11` | Simplified g glyph |

Applied globally via:
```css
font-feature-settings: 'cv02', 'cv03', 'cv04', 'cv11';
```

### 3.3 Letter Spacing

All text uses a slight negative tracking for a tighter, more modern feel:

```css
letter-spacing: -0.01em;
```

### 3.4 Font Rendering

```css
-webkit-font-smoothing: antialiased;
-moz-osx-font-smoothing: grayscale;
```

### 3.5 Type Scale

| Token | Size | Pixels (at 16px base) | Typical Usage |
|---|---|---|---|
| `--font-size-xs` | `0.75rem` | 12px | Timestamps, metadata, badges |
| `--font-size-sm` | `0.875rem` | 14px | Body text, form labels, nav items |
| `--font-size-base` | `1rem` | 16px | Default body, large form fields |
| `--font-size-lg` | `1.125rem` | 18px | Section headings, modal titles |
| `--font-size-xl` | `1.25rem` | 20px | Page sub-headings |
| `--font-size-2xl` | `1.5rem` | 24px | Page titles |
| `--font-size-3xl` | `1.875rem` | 30px | Dashboard hero numbers |

### 3.6 Font Weights

| Weight | Tailwind class | Usage |
|---|---|---|
| 400 | `font-normal` | Body text, descriptions |
| 500 | `font-medium` | Navigation items, buttons, form labels |
| 600 | `font-semibold` | Card titles, section headings, modal titles |
| 700 | `font-bold` | Page titles (rare) |

---

## 4. Spacing

### 4.1 Base Unit

All spacing derives from a `0.25rem` (4px) base unit. Tailwind spacing utilities map
directly to these tokens.

### 4.2 Spacing Scale

| Token | Value | Pixels | Tailwind | Common Usage |
|---|---|---|---|---|
| `--spacing-0` | `0` | 0px | `p-0`, `m-0` | Reset |
| `--spacing-1` | `0.25rem` | 4px | `p-1`, `gap-1` | Tight inline gaps, icon offsets |
| `--spacing-2` | `0.5rem` | 8px | `p-2`, `gap-2` | Button icon gaps, compact padding |
| `--spacing-3` | `0.75rem` | 12px | `p-3`, `gap-3` | Small card padding, nav item padding |
| `--spacing-4` | `1rem` | 16px | `p-4`, `gap-4` | Standard card padding, grid gaps |
| `--spacing-5` | `1.25rem` | 20px | `p-5`, `gap-5` | Medium section padding |
| `--spacing-6` | `1.5rem` | 24px | `p-6`, `gap-6` | Large card padding, section gaps |
| `--spacing-8` | `2rem` | 32px | `p-8`, `gap-8` | Page margins, large section gaps |
| `--spacing-10` | `2.5rem` | 40px | `p-10` | Extra-large padding |
| `--spacing-12` | `3rem` | 48px | `p-12` | Page-level vertical spacing |
| `--spacing-16` | `4rem` | 64px | `p-16` | Maximum page padding |

### 4.3 Common Spacing Patterns

| Pattern | Value | Where |
|---|---|---|
| Section vertical gaps | `space-y-6` | Between major page sections |
| Card body padding | `p-4` (md) or `p-6` (xl) | Card component default |
| Grid gaps | `gap-4` or `gap-6` | Card grids, form layouts |
| Nav item padding | `px-3 py-2` | Sidebar navigation links |
| Modal padding | `px-6 pt-6 pb-4` (header), `px-6 pb-6` (body) | Modal component |
| Sidebar section dividers | `my-2.5 mx-3` | Between nav groups |
| Form field spacing | `mb-1.5` (label), `mt-1.5` (hint) | Input component |
| Inline icon gap | `gap-2` (md) or `gap-1.5` (sm) | Buttons, badges |

---

## 5. Borders and Radius

### 5.1 Border Radius Scale

| Token | Value | Pixels | Usage |
|---|---|---|---|
| `--radius-sm` | `0.375rem` | 6px | Small badges, inline tags |
| `--radius-md` | `0.5rem` | 8px | Buttons, inputs, dropdowns |
| `--radius-lg` | `0.75rem` | 12px | Cards, panels (default card radius) |
| `--radius-xl` | `1rem` | 16px | Modals, large panels |
| `--radius-full` | `9999px` | Full | Avatars, pills, dot indicators |

### 5.2 Border Color Tokens

See Section 2.3. Borders use `--color-olympus-border` (standard) and
`--color-olympus-border-subtle` (light dividers). In components, these map to:

- Light: `border-neutral-200` (standard) / `border-neutral-300` (emphasis)
- Dark: `border-neutral-700` (standard) / `border-neutral-600` (emphasis)

### 5.3 Border-Based Card Design

Cards in OpenCompany rely on borders rather than shadows for definition.

```
+----------------------------------------------+
|  .card-gradient                               |
|                                               |
|  background: var(--color-olympus-surface)      |
|  border: 1px solid var(--color-olympus-        |
|          border-subtle)                        |
|  border-radius: var(--radius-lg)              |
|                                               |
|  NO gradient. NO shadow.                      |
+----------------------------------------------+
```

Interactive cards add a subtle transition on hover:

```
Resting:   border-color: border-subtle    shadow: none
Hover:     border-color: border           shadow: shadow-sm
```

The `card-interactive` CSS class handles this:
```css
.card-interactive {
  transition: border-color 150ms ease-out, box-shadow 150ms ease-out;
}
.card-interactive:hover {
  border-color: var(--color-olympus-border);
  box-shadow: var(--shadow-sm);
}
```

---

## 6. Shadows and Elevation

### 6.1 Shadow Scale

Shadows are minimal and use black with low opacity. No colored glows.

```
  shadow-sm   Barely visible      ░          Subtle cards, hover states
  shadow-md   Soft lift           ░░         Dropdowns, popovers
  shadow-lg   Moderate float      ░░░        Modals, slideovers
  shadow-xl   Maximum elevation   ░░░░       Command palettes (rare)
```

| Token | Light Mode | Dark Mode |
|---|---|---|
| `--shadow-sm` | `0 1px 2px 0 oklch(0 0 0 / 0.05)` | `0 1px 2px 0 oklch(0 0 0 / 0.2)` |
| `--shadow-md` | `0 4px 6px -1px oklch(0 0 0 / 0.07), 0 2px 4px -2px oklch(0 0 0 / 0.05)` | `0 4px 6px -1px oklch(0 0 0 / 0.3), 0 2px 4px -2px oklch(0 0 0 / 0.2)` |
| `--shadow-lg` | `0 10px 15px -3px oklch(0 0 0 / 0.08), 0 4px 6px -4px oklch(0 0 0 / 0.05)` | `0 10px 15px -3px oklch(0 0 0 / 0.35), 0 4px 6px -4px oklch(0 0 0 / 0.2)` |
| `--shadow-xl` | `0 20px 25px -5px oklch(0 0 0 / 0.08), 0 8px 10px -6px oklch(0 0 0 / 0.05)` | `0 20px 25px -5px oklch(0 0 0 / 0.4), 0 8px 10px -6px oklch(0 0 0 / 0.25)` |

### 6.2 Glow Shadows (Deprecated Legacy Tokens)

The `--shadow-glow-*` tokens exist for backward compatibility but are now identical
to standard shadows. They do **not** produce any colored glow effect.

| Token | Light Mode | Dark Mode |
|---|---|---|
| `--shadow-glow-sm` | `0 1px 3px 0 oklch(0 0 0 / 0.06)` | `0 1px 3px 0 oklch(0 0 0 / 0.25)` |
| `--shadow-glow-md` | `0 4px 6px -1px oklch(0 0 0 / 0.07)` | `0 4px 6px -1px oklch(0 0 0 / 0.3)` |
| `--shadow-glow-lg` | `0 10px 15px -3px oklch(0 0 0 / 0.08)` | `0 10px 15px -3px oklch(0 0 0 / 0.35)` |

### 6.3 Focus Ring

The focus ring uses a double box-shadow: an inner white ring and an outer primary ring.

```css
--shadow-focus: 0 0 0 2px oklch(1 0 0), 0 0 0 4px var(--color-olympus-primary);
```

```
+----------------------------------+
|  +--(4px primary ring)--------+  |
|  |  +--(2px white ring)----+  |  |
|  |  |                      |  |  |
|  |  |    Focused Element   |  |  |
|  |  |                      |  |  |
|  |  +----------------------+  |  |
|  +----------------------------+  |
+----------------------------------+
```

For error states:
```css
--shadow-focus-error: 0 0 0 2px oklch(1 0 0), 0 0 0 4px var(--color-olympus-error);
```

### 6.4 Card Shadows

Cards use a 1px inset ring via `box-shadow` rather than the `border` property in some contexts:

| Token | Light Mode | Dark Mode |
|---|---|---|
| `--shadow-card` | `0 0 0 1px var(--color-olympus-border-subtle)` | `0 0 0 1px var(--color-olympus-border-subtle)` |
| `--shadow-card-hover` | `0 0 0 1px var(--color-olympus-border), 0 1px 3px 0 oklch(0 0 0 / 0.06)` | `0 0 0 1px var(--color-olympus-border), 0 2px 4px 0 oklch(0 0 0 / 0.2)` |

---

## 7. Transitions and Animations

### 7.1 Duration Scale

| Token | Value | Usage |
|---|---|---|
| `--duration-fast` | `100ms` | Checkbox toggles, color changes on small elements |
| `--duration-normal` | `150ms` | Button hover, nav item hover, border color transitions |
| `--duration-slow` | `200ms` | Modal open/close, dropdown appearance |
| `--duration-slower` | `300ms` | Slideover panel enter/leave, collapsible expand |

### 7.2 Easing Functions

| Token | Value | Usage |
|---|---|---|
| `--ease-default` | `cubic-bezier(0.4, 0, 0.2, 1)` | General-purpose transitions |
| `--ease-out` | `cubic-bezier(0, 0, 0.2, 1)` | Elements entering the screen, card hover |

### 7.3 No-Spring Policy

All animations use simple CSS easing curves. Do **not** use:
- Spring-based easing (`spring()`, `cubic-bezier` that overshoots)
- Bounce effects
- Physics-based animation libraries for UI chrome

### 7.4 Standard Transition Pattern

Most interactive elements use this transition shorthand:
```css
transition: border-color 150ms ease-out, box-shadow 150ms ease-out;
```

Or via Tailwind:
```html
class="transition-colors duration-150"
```

### 7.5 Vue Transition Patterns

**Fade (opacity)**
```html
<Transition
  enter-active-class="transition-opacity duration-150"
  enter-from-class="opacity-0"
  leave-active-class="transition-opacity duration-150"
  leave-from-class="opacity-100"
  leave-to-class="opacity-0"
>
```

**Collapse (max-height + opacity)**
```html
<Transition
  enter-active-class="transition-all duration-150 overflow-hidden"
  enter-from-class="max-h-0 opacity-0"
  enter-to-class="max-h-[2000px] opacity-100"
  leave-active-class="transition-all duration-150 overflow-hidden"
  leave-from-class="max-h-[2000px] opacity-100"
  leave-to-class="max-h-0 opacity-0"
>
```

**Reka UI data-state animations** (used in Modal, Slideover, Tooltip):
```html
class="data-[state=open]:animate-in data-[state=closed]:animate-out
       data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0
       data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95"
```

---

## 8. Icons

### 8.1 Icon System

Icons use the **Phosphor** icon set via `@iconify/vue`. All icon names are prefixed
with `ph:`.

```vue
<script setup>
import { Icon } from '@iconify/vue'
// or use the shared wrapper:
import Icon from '@/Components/shared/Icon.vue'
</script>

<template>
  <Icon name="ph:house" class="w-5 h-5" />
</template>
```

The shared `Icon.vue` wrapper accepts both `name` and `icon` props for backwards
compatibility.

### 8.2 Icon Sizing Scale

| Name | Size | Tailwind Class | Usage |
|---|---|---|---|
| xs | 12px | `w-3 h-3` | Badge icons, remove buttons |
| sm | 14px | `w-3.5 h-3.5` | Inline metadata, small badges |
| md | 16px | `w-4 h-4` | Buttons, form inputs, nav items |
| lg | 20px | `w-5 h-5` | Card headers, modal icons, stat cards |
| xl | 24px | `w-6 h-6` | Page headers (rare) |
| 2xl | 32px-48px | `w-8 h-8` to `w-12 h-12` | Empty states, loading spinners |

Sidebar navigation icons use a custom size: `w-[18px] h-[18px]`.

### 8.3 Active/Inactive Icon Pattern

Navigation items use outline icons by default and filled icons when active:

| State | Icon Style | Example |
|---|---|---|
| Inactive | Outline (default) | `ph:house` |
| Active | Filled | `ph:house-fill` |

### 8.4 Commonly Used Icons by Category

**Navigation**

| Icon | Name | Usage |
|---|---|---|
| Home | `ph:house` / `ph:house-fill` | Dashboard |
| Tasks | `ph:check-square` / `ph:check-square-fill` | Agent tasks |
| Approvals | `ph:seal-check` / `ph:seal-check-fill` | Approval queue |
| Organization | `ph:tree-structure` / `ph:tree-structure-fill` | Org chart |
| Chat | `ph:chat-circle` / `ph:chat-circle-fill` | Messaging |
| Docs | `ph:file-text` / `ph:file-text-fill` | Documents |
| Tables | `ph:table` / `ph:table-fill` | Database tables |
| Calendar | `ph:calendar` / `ph:calendar-fill` | Calendar |
| Lists | `ph:kanban` / `ph:kanban-fill` | Kanban boards |
| Activity | `ph:activity` / `ph:activity-fill` | Activity feed |

**Actions**

| Icon | Name | Usage |
|---|---|---|
| Add | `ph:plus` or `ph:plus-bold` | Create new items |
| Close | `ph:x` | Close modals, dismiss, remove |
| Edit | `ph:pencil` | Edit actions |
| Delete | `ph:trash` | Delete actions |
| Search | `ph:magnifying-glass` | Search inputs |
| Send | `ph:paper-plane-tilt` | Send message |
| Copy | `ph:copy` | Copy to clipboard |
| Check | `ph:check` | Confirmation, copied state |
| Refresh | `ph:arrows-clockwise` | Refresh/reload data |
| More | `ph:dots-three` | Overflow menus |
| Drag | `ph:dots-six-vertical` | Drag handle |
| Collapse | `ph:caret-down` | Collapsible sections |
| Navigate | `ph:caret-left` / `ph:caret-right` | Pagination, navigation |
| Arrow | `ph:arrow-left` / `ph:arrow-right` | Back navigation, flow |

**Status and Feedback**

| Icon | Name | Usage |
|---|---|---|
| Spinner | `ph:spinner` | Loading states (add `animate-spin`) |
| Warning | `ph:warning` | Urgent priority |
| Error | `ph:warning-circle-fill` | Input error icon |
| Success | `ph:check-circle-fill` | Input success icon |
| Info | `ph:info` | Info tooltips |
| Empty | `ph:file-dashed` | Empty state illustrations |

**Content Types**

| Icon | Name | Usage |
|---|---|---|
| Robot | `ph:robot` | Agent-related items |
| User | `ph:user` | User/person references |
| Gear | `ph:gear` | Settings |
| Lightning | `ph:lightning` | Automation triggers |
| Coins | `ph:coins` | Cost/credit display |
| Chart | `ph:chart-line` / `ph:chart-bar` | Analytics |
| Eye | `ph:eye` / `ph:eye-slash` | Show/hide password |
| Paperclip | `ph:paperclip` | Attachments |
| Toggle | `ph:toggle-right-fill` / `ph:toggle-left` | Boolean on/off |

---

## 9. Dark Mode

### 9.1 Implementation Strategy

OpenCompany supports a **three-mode** color scheme: light, dark, and system (follows OS preference).

Dark mode is implemented via two parallel mechanisms:
1. **Class-based** (`.dark` on `<html>`) -- for explicit user choice
2. **`prefers-color-scheme` media query** on `:root:not(.light)` -- for system mode

When the user selects "light", the `.light` class is added to `<html>` to override
the media query. When "dark" is selected, the `.dark` class is added. When "system"
is selected, neither class is added, letting the media query take effect.

### 9.2 useColorMode Composable

**Location:** `resources/js/composables/useColorMode.ts`

```typescript
import { useColorMode } from '@/composables/useColorMode'

const {
  colorMode,         // Ref<'light' | 'dark' | 'system'>
  isDark,            // ComputedRef<boolean> -- resolved dark state
  systemPrefersDark, // Ref<boolean> -- raw OS preference
  setColorMode,      // (mode: ColorMode) => void
  toggleDark,        // () => void -- flips between light/dark
  cycleColorMode,    // () => void -- cycles system > light > dark
} = useColorMode()
```

**Key behaviors:**
- State is **global** (shared across all component instances via module-level refs)
- Persisted to `localStorage` under the key `color-mode`
- Applied immediately on import (watchers fire with `{ immediate: true }`)
- Listens for OS-level `prefers-color-scheme` changes in real-time

### 9.3 Styling for Dark Mode

Use Tailwind's `dark:` variant prefix. The custom variant is configured as:

```css
@custom-variant dark (&:where(.dark, .dark *));
```

Standard pattern:
```html
<div class="bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white
            border-neutral-200 dark:border-neutral-700">
```

### 9.4 Dark Mode Testing Checklist

When adding or modifying a component, verify:

- [ ] Background colors have proper dark variants
- [ ] Text colors maintain readable contrast in both modes
- [ ] Border colors use the appropriate dark variant
- [ ] Hover states are visible but not too bright in dark mode
- [ ] Focus rings are visible against dark backgrounds
- [ ] Shadows are increased in opacity for dark mode (handled by tokens)
- [ ] Scrollbar thumbs use dark-appropriate colors
- [ ] Empty states and placeholder content remain visible
- [ ] Status colors (success/warning/error) are legible in both modes

---

## 10. Responsive Design

### 10.1 Breakpoints

| Name | Width | CSS | Usage |
|---|---|---|---|
| Mobile | < 768px | `max-width: 767px` | Single column, stacked layouts |
| Tablet | 768px -- 1024px | `min-width: 768px` | Two-column layouts, collapsible sidebar |
| Desktop | > 1024px | `min-width: 1024px` | Full multi-column layout, expanded sidebar |

### 10.2 useMediaQuery Composable

**Location:** `resources/js/composables/useMediaQuery.ts`

```typescript
import { useMediaQuery, useIsMobile } from '@/composables/useMediaQuery'

// General-purpose media query
const isTablet = useMediaQuery('(min-width: 768px) and (max-width: 1024px)')

// Shorthand for mobile detection
const isMobile = useIsMobile()  // equivalent to (max-width: 767px)
```

**Key behaviors:**
- Returns a reactive `Ref<boolean>`
- Attaches a `change` event listener on `MediaQueryList` (not polling)
- Properly cleans up listeners on component unmount

### 10.3 Mobile-First Patterns

- Default styles target mobile
- Use Tailwind responsive prefixes (`md:`, `lg:`) to layer on desktop styles
- Sidebar collapses on mobile, expands on desktop
- Touch targets are minimum **44px** (11 Tailwind units / `h-11`)
- Modals become full-width on small screens (`max-w-[calc(100%-2rem)]`)

### 10.4 Responsive Component Patterns

| Component | Mobile | Desktop |
|---|---|---|
| Sidebar | Hidden or overlay | Fixed, visible |
| Modal | Near full-width | Centered with max-width |
| Slideover | Full-width | Max-width constrained |
| Card grid | Single column | 2-3 column grid |
| Nav items | Larger touch targets | Standard sizing |
| Tables | Horizontal scroll | Full display |

---

## 11. Accessibility

### 11.1 Standards

The application targets **WCAG 2.1 Level AA** compliance.

### 11.2 Focus Management

All interactive elements receive a visible focus ring via `:focus-visible`:

```css
:focus-visible {
  outline: none;
  box-shadow: 0 0 0 2px oklch(1 0 0), 0 0 0 4px var(--color-olympus-primary);
}
```

Buttons use a slightly different ring via Tailwind:
```html
class="focus-visible:outline-none focus-visible:ring-2
       focus-visible:ring-neutral-400 focus-visible:ring-offset-2
       dark:focus-visible:ring-offset-neutral-900"
```

Navigation items use a lighter treatment:
```html
class="focus-visible:ring-1 focus-visible:ring-neutral-400"
```

### 11.3 ARIA Patterns (via Reka UI)

OpenCompany uses **Reka UI** (headless primitives) for accessible interactive components.
These components handle ARIA attributes, keyboard navigation, and focus trapping
automatically:

| Pattern | Reka UI Component | ARIA Role |
|---|---|---|
| Modal dialog | `DialogRoot` / `DialogContent` | `dialog` with `aria-modal` |
| Slideover panel | `DialogRoot` (side variant) | `dialog` with `aria-modal` |
| Tooltip | `TooltipRoot` / `TooltipContent` | `tooltip` |
| Dropdown menu | `PopoverRoot` / `PopoverContent` | `menu` |
| Collapsible section | `CollapsibleRoot` / `CollapsibleContent` | `region` with `aria-expanded` |

### 11.4 Keyboard Navigation

| Key | Action |
|---|---|
| `Tab` | Move focus forward through interactive elements |
| `Shift+Tab` | Move focus backward |
| `Enter` / `Space` | Activate focused button or link |
| `Escape` | Close modal, slideover, popover, or tooltip |
| `Arrow keys` | Navigate within menus and dropdowns |

### 11.5 Screen Reader Support

- Modals include `DialogTitle` and `DialogDescription` (or a `sr-only` fallback)
- Close buttons include `<span class="sr-only">Close</span>`
- Icon-only buttons always have an `aria-label` or tooltip text
- Form inputs are connected to labels via `id`/`for` attributes
- Loading states use `aria-busy` where applicable

### 11.6 The sr-only Class

Use Tailwind's `sr-only` class for content that should be announced by screen readers
but not visible on screen:

```html
<span class="sr-only">Close</span>
```

---

## 12. Design Tokens Reference

Complete reference of every CSS custom property defined in `app.css`, with values in
both light and dark mode.

### 12.1 Typography Tokens

| Token | Value |
|---|---|
| `--font-size-xs` | `0.75rem` |
| `--font-size-sm` | `0.875rem` |
| `--font-size-base` | `1rem` |
| `--font-size-lg` | `1.125rem` |
| `--font-size-xl` | `1.25rem` |
| `--font-size-2xl` | `1.5rem` |
| `--font-size-3xl` | `1.875rem` |

### 12.2 Spacing Tokens

| Token | Value |
|---|---|
| `--spacing-0` | `0` |
| `--spacing-1` | `0.25rem` |
| `--spacing-2` | `0.5rem` |
| `--spacing-3` | `0.75rem` |
| `--spacing-4` | `1rem` |
| `--spacing-5` | `1.25rem` |
| `--spacing-6` | `1.5rem` |
| `--spacing-8` | `2rem` |
| `--spacing-10` | `2.5rem` |
| `--spacing-12` | `3rem` |
| `--spacing-16` | `4rem` |

### 12.3 Border Radius Tokens

| Token | Value |
|---|---|
| `--radius-sm` | `0.375rem` (6px) |
| `--radius-md` | `0.5rem` (8px) |
| `--radius-lg` | `0.75rem` (12px) |
| `--radius-xl` | `1rem` (16px) |
| `--radius-full` | `9999px` |

### 12.4 Transition Tokens

| Token | Value |
|---|---|
| `--duration-fast` | `100ms` |
| `--duration-normal` | `150ms` |
| `--duration-slow` | `200ms` |
| `--duration-slower` | `300ms` |
| `--ease-default` | `cubic-bezier(0.4, 0, 0.2, 1)` |
| `--ease-out` | `cubic-bezier(0, 0, 0.2, 1)` |

### 12.5 Color Tokens (Light / Dark)

| Token | Light | Dark |
|---|---|---|
| `--color-olympus-bg` | `oklch(0.985 0 0)` | `oklch(0.13 0 0)` |
| `--color-olympus-sidebar` | `oklch(0.97 0 0)` | `oklch(0.11 0 0)` |
| `--color-olympus-surface` | `oklch(1 0 0)` | `oklch(0.16 0 0)` |
| `--color-olympus-elevated` | `oklch(1 0 0)` | `oklch(0.18 0 0)` |
| `--color-olympus-glass` | `oklch(1 0 0 / 0.98)` | `oklch(0.16 0 0 / 0.98)` |
| `--color-olympus-glass-hover` | `oklch(0.98 0 0)` | `oklch(0.20 0 0)` |
| `--color-olympus-border` | `oklch(0.90 0 0)` | `oklch(0.28 0 0)` |
| `--color-olympus-border-subtle` | `oklch(0.94 0 0)` | `oklch(0.22 0 0)` |
| `--color-olympus-primary` | `oklch(0.55 0.15 250)` | `oklch(0.60 0.15 250)` |
| `--color-olympus-primary-hover` | `oklch(0.50 0.15 250)` | `oklch(0.65 0.15 250)` |
| `--color-olympus-primary-muted` | `oklch(0.55 0.15 250 / 0.08)` | `oklch(0.60 0.15 250 / 0.12)` |
| `--color-olympus-primary-glow` | `oklch(0.55 0.15 250 / 0.15)` | `oklch(0.60 0.15 250 / 0.2)` |
| `--color-olympus-accent` | `oklch(0.55 0.15 250)` | -- |
| `--color-olympus-accent-cyan` | `oklch(0.55 0.10 220)` | -- |
| `--color-olympus-text` | `oklch(0.15 0 0)` | `oklch(0.95 0 0)` |
| `--color-olympus-text-muted` | `oklch(0.45 0 0)` | `oklch(0.70 0 0)` |
| `--color-olympus-text-subtle` | `oklch(0.60 0 0)` | `oklch(0.50 0 0)` |
| `--color-olympus-success` | `oklch(0.50 0.10 145)` | `oklch(0.60 0.12 145)` |
| `--color-olympus-warning` | `oklch(0.55 0.08 70)` | `oklch(0.65 0.10 70)` |
| `--color-olympus-error` | `oklch(0.50 0.12 25)` | `oklch(0.60 0.14 25)` |
| `--color-agent-working` | `oklch(0.50 0.10 145)` | `oklch(0.60 0.12 145)` |
| `--color-agent-idle` | `oklch(0.60 0 0)` | `oklch(0.50 0 0)` |
| `--color-agent-offline` | `oklch(0.75 0 0)` | `oklch(0.35 0 0)` |
| `--color-agent-manager` | `oklch(0.45 0 0)` | -- |
| `--color-agent-writer` | `oklch(0.45 0 0)` | -- |
| `--color-agent-analyst` | `oklch(0.45 0 0)` | -- |
| `--color-agent-creative` | `oklch(0.45 0 0)` | -- |
| `--color-agent-researcher` | `oklch(0.45 0 0)` | -- |
| `--color-agent-coder` | `oklch(0.45 0 0)` | -- |
| `--color-agent-coordinator` | `oklch(0.45 0 0)` | -- |

### 12.6 Shadow Tokens (Light / Dark)

| Token | Light | Dark |
|---|---|---|
| `--shadow-sm` | `0 1px 2px 0 oklch(0 0 0 / 0.05)` | `0 1px 2px 0 oklch(0 0 0 / 0.2)` |
| `--shadow-md` | `0 4px 6px -1px oklch(0 0 0 / 0.07), 0 2px 4px -2px oklch(0 0 0 / 0.05)` | `0 4px 6px -1px oklch(0 0 0 / 0.3), 0 2px 4px -2px oklch(0 0 0 / 0.2)` |
| `--shadow-lg` | `0 10px 15px -3px oklch(0 0 0 / 0.08), 0 4px 6px -4px oklch(0 0 0 / 0.05)` | `0 10px 15px -3px oklch(0 0 0 / 0.35), 0 4px 6px -4px oklch(0 0 0 / 0.2)` |
| `--shadow-xl` | `0 20px 25px -5px oklch(0 0 0 / 0.08), 0 8px 10px -6px oklch(0 0 0 / 0.05)` | `0 20px 25px -5px oklch(0 0 0 / 0.4), 0 8px 10px -6px oklch(0 0 0 / 0.25)` |
| `--shadow-glow-sm` | `0 1px 3px 0 oklch(0 0 0 / 0.06)` | `0 1px 3px 0 oklch(0 0 0 / 0.25)` |
| `--shadow-glow-md` | `0 4px 6px -1px oklch(0 0 0 / 0.07)` | `0 4px 6px -1px oklch(0 0 0 / 0.3)` |
| `--shadow-glow-lg` | `0 10px 15px -3px oklch(0 0 0 / 0.08)` | `0 10px 15px -3px oklch(0 0 0 / 0.35)` |
| `--shadow-focus` | `0 0 0 2px oklch(1 0 0), 0 0 0 4px primary` | -- |
| `--shadow-focus-error` | `0 0 0 2px oklch(1 0 0), 0 0 0 4px error` | -- |
| `--shadow-card` | `0 0 0 1px border-subtle` | `0 0 0 1px border-subtle` |
| `--shadow-card-hover` | `0 0 0 1px border, 0 1px 3px 0 oklch(0 0 0 / 0.06)` | `0 0 0 1px border, 0 2px 4px 0 oklch(0 0 0 / 0.2)` |

### 12.7 CSS Utility Classes

| Class | Effect |
|---|---|
| `glass` | Applies `--color-olympus-glass` background (solid, no blur) |
| `glow` | Applies `--shadow-sm` |
| `glow-md` | Applies `--shadow-md` |
| `glow-lg` | Applies `--shadow-lg` |
| `card-gradient` | White surface, subtle border, rounded-lg (no gradient despite name) |
| `card-interactive` | Adds border-color and shadow transition on hover |
| `border-gradient` | Standard border style (no gradient despite name) |

### 12.8 Scrollbar Styling

| Element | Light Mode | Dark Mode |
|---|---|---|
| Scrollbar width | 8px | 8px |
| Track | transparent | transparent |
| Thumb | `oklch(0.85 0 0)` | `oklch(0.30 0 0)` |
| Thumb (hover) | `oklch(0.75 0 0)` | `oklch(0.40 0 0)` |

### 12.9 Selection Styling

Text selection uses the primary muted color:
```css
::selection {
  background-color: var(--color-olympus-primary-muted);
}
```
