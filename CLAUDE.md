# Olympus Project Rules

## Local Development

- **Local URL**: `http://olympus.test` (Laravel Valet domain, no SSL)
- When testing or navigating to the app locally, always use `http://olympus.test`

## Tech Stack

- **Backend**: Laravel 12
- **Frontend**: Vue 3 + Inertia.js
- **Styling**: Tailwind CSS v4 + Reka UI (headless primitives)
- **Icons**: @iconify/vue with Phosphor icons (`ph:` prefix)

## Component Structure

- Shared components are in `resources/js/Components/shared/`
- Use the wrapper components (Button, Modal, Badge, etc.) instead of native elements for consistency
- Dark mode is supported via the `useColorMode` composable
