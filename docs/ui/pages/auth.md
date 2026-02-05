# Authentication

> Login, registration, and account verification pages

---

## Routes & Access

| Page | Route | Name | Auth | Layout |
|------|-------|------|------|--------|
| **Login** | `/login` | `login` | Guest only | GuestLayout |
| **Register** | `/register` | `register` | Guest only | GuestLayout |
| **Forgot Password** | `/forgot-password` | `password.request` | Guest only | GuestLayout |
| **Reset Password** | `/reset-password/{token}` | `password.reset` | Guest only | GuestLayout |
| **Verify Email** | `/verify-email` | `verification.notice` | Required | GuestLayout |
| **Confirm Password** | `/confirm-password` | `password.confirm` | Required | GuestLayout |

---

## Shared Layout (`GuestLayout.vue`)

All authentication pages share a centered card layout on a textured background.

```
+--------------------------------------------------------------+
|                                                              |
|          bg-neutral-100 / dark:bg-neutral-950                |
|          noise texture overlay (SVG feTurbulence)            |
|          dot grid overlay (radial-gradient, 24px spacing)    |
|                                                              |
|                    [O] ApplicationLogo                        |
|                        (links to /)                          |
|                                                              |
|              +--------------------------------+              |
|              | max-w-sm  rounded-xl            |              |
|              | bg-white dark:bg-neutral-900    |              |
|              | border border-neutral-200       |              |
|              | dark:border-neutral-800         |              |
|              | shadow-sm  p-6                  |              |
|              |                                |              |
|              |   <slot />  (page content)     |              |
|              |                                |              |
|              +--------------------------------+              |
|                                                              |
|              <slot name="footer" />                          |
|              (centered, text-sm, neutral-500)                |
|                                                              |
+--------------------------------------------------------------+
```

| Property | Value |
|----------|-------|
| Background | `bg-neutral-100 dark:bg-neutral-950` with noise + dot grid overlays |
| Card width | `max-w-sm` (24rem) |
| Card style | `rounded-xl`, 1px border, `shadow-sm`, `p-6` |
| Logo | `ApplicationLogo` component, wrapped in `<Link href="/">` |
| Footer slot | Centered below card, `text-sm text-neutral-500` |
| Min height | `min-h-screen` with flexbox centering |

---

## Login

### Layout

```
+--------------------------------------------+
|  Welcome back                    <- h1     |
|                                            |
|  (status message, if present)              |
|                                            |
|  Email                                     |
|  +--------------------------------------+  |
|  | email input                          |  |
|  +--------------------------------------+  |
|                                            |
|  Password                                  |
|  +--------------------------------------+  |
|  | password input                       |  |
|  +--------------------------------------+  |
|                                            |
|  [x] Remember me       Forgot password?    |
|                                            |
|  +--------------------------------------+  |
|  |            Log in                    |  |
|  +--------------------------------------+  |
+--------------------------------------------+
  Don't have an account? Sign up
```

### Features

- **Form fields:** Email (autofocus, autocomplete=username), Password (autocomplete=current-password)
- **Remember me:** Checkbox using shared `Checkbox` component
- **Forgot password link:** shown conditionally via `canResetPassword` prop; links to `password.request` route
- **Status message:** displays green success text (e.g. after password reset)
- **Submit:** POSTs to `login` route; resets password field on finish
- **Footer:** link to registration page
- **Props:** `canResetPassword` (boolean), `status` (string)

---

## Register

### Layout

```
+--------------------------------------------+
|  Create an account               <- h1     |
|                                            |
|  Name                                      |
|  +--------------------------------------+  |
|  | name input                           |  |
|  +--------------------------------------+  |
|                                            |
|  Email                                     |
|  +--------------------------------------+  |
|  | email input                          |  |
|  +--------------------------------------+  |
|                                            |
|  Password                                  |
|  +--------------------------------------+  |
|  | password input                       |  |
|  +--------------------------------------+  |
|                                            |
|  Confirm password                          |
|  +--------------------------------------+  |
|  | confirm password input               |  |
|  +--------------------------------------+  |
|                                            |
|  +--------------------------------------+  |
|  |         Create account               |  |
|  +--------------------------------------+  |
+--------------------------------------------+
  Already have an account? Log in
```

### Features

- **Form fields:** Name (autofocus), Email, Password, Confirm password
- **Submit:** POSTs to `register` route; resets password + confirmation on finish
- **Footer:** link to login page
- **No props** -- all data is form-local

---

## Forgot Password

### Layout

```
+--------------------------------------------+
|  Forgot password?                <- h1     |
|  No problem. Enter your email and          |
|  we'll send you a reset link.              |
|                                            |
|  (status message, if present)              |
|                                            |
|  Email                                     |
|  +--------------------------------------+  |
|  | email input                          |  |
|  +--------------------------------------+  |
|                                            |
|  +--------------------------------------+  |
|  |         Send reset link              |  |
|  +--------------------------------------+  |
+--------------------------------------------+
  Back to login
```

### Features

- **Description text:** explanatory paragraph below heading
- **Form fields:** Email (autofocus, autocomplete=username)
- **Status message:** displays green success text after link is sent
- **Submit:** POSTs to `password.email` route
- **Footer:** "Back to login" link
- **Props:** `status` (string)

---

## Reset Password

### Layout

```
+--------------------------------------------+
|  Set new password                <- h1     |
|                                            |
|  Email                                     |
|  +--------------------------------------+  |
|  | email input (pre-filled)             |  |
|  +--------------------------------------+  |
|                                            |
|  New password                              |
|  +--------------------------------------+  |
|  | password input                       |  |
|  +--------------------------------------+  |
|                                            |
|  Confirm password                          |
|  +--------------------------------------+  |
|  | confirm password input               |  |
|  +--------------------------------------+  |
|                                            |
|  +--------------------------------------+  |
|  |         Reset password               |  |
|  +--------------------------------------+  |
+--------------------------------------------+
```

### Features

- **Form fields:** Email (pre-filled from props, autofocus), New password, Confirm password
- **Hidden field:** token (from URL parameter, stored in form data)
- **Submit:** POSTs to `password.store` route; resets password fields on finish
- **No footer** -- no additional navigation links
- **Props:** `email` (string), `token` (string)

---

## Verify Email

### Layout

```
+--------------------------------------------+
|  Verify your email               <- h1     |
|  Thanks for signing up! Before getting     |
|  started, please verify your email...      |
|                                            |
|  (verification link sent confirmation)     |
|                                            |
|  +--------------------------------------+  |
|  |     Resend verification email        |  |
|  +--------------------------------------+  |
+--------------------------------------------+
  Log out
```

### Features

- **Description text:** explains verification requirement
- **Success message:** "A new verification link has been sent" shown when `status === 'verification-link-sent'`
- **Submit:** POSTs to `verification.send` route (throttled to 6 requests per minute)
- **Footer:** "Log out" link (POST to `logout` route via Inertia `method="post"`)
- **No form fields** -- only a resend button
- **Props:** `status` (string)

---

## Confirm Password

### Layout

```
+--------------------------------------------+
|  Confirm password                <- h1     |
|  This is a secure area. Please confirm     |
|  your password before continuing.          |
|                                            |
|  Password                                  |
|  +--------------------------------------+  |
|  | password input                       |  |
|  +--------------------------------------+  |
|                                            |
|  +--------------------------------------+  |
|  |            Confirm                   |  |
|  +--------------------------------------+  |
+--------------------------------------------+
```

### Features

- **Description text:** explains this is a secure area
- **Form fields:** Password (autofocus, autocomplete=current-password)
- **Submit:** POSTs to `password.confirm` route; resets form on finish
- **No footer** -- no additional navigation links
- **No props** -- all data is form-local

---

## Components

| Component | Source | Used In |
|-----------|--------|---------|
| `GuestLayout` | `@/Layouts/GuestLayout.vue` | All 6 auth pages |
| `ApplicationLogo` | `@/Components/ApplicationLogo.vue` | GuestLayout header |
| `Input` | `@/Components/shared/Input.vue` | Login, Register, ForgotPassword, ResetPassword, ConfirmPassword |
| `Button` | `@/Components/shared/Button.vue` | All 6 auth pages (submit buttons) |
| `Checkbox` | `@/Components/shared/Checkbox.vue` | Login (remember me) |
| `Link` | `@inertiajs/vue3` | Navigation between auth pages, logout |
| `Head` | `@inertiajs/vue3` | Page title in all 6 pages |

---

## States

| State | Behavior |
|-------|----------|
| Default | Empty form fields, button enabled |
| Processing | Button shows loading state via `:loading="form.processing"` |
| Validation error | Inline error messages below each field via `:error="form.errors.*"` |
| Status message | Green text shown for success messages (password reset sent, verification sent) |
| Password reset on error | Password fields are cleared and the relevant field receives focus |

All forms use Inertia's `useForm` composable, which provides automatic `processing`, `errors`, `reset()`, and `recentlySuccessful` state management.

---

## Responsive Behavior

| Breakpoint | Behavior |
|------------|----------|
| All sizes | Card is `max-w-sm` with `px-4` page padding, naturally responsive |
| Mobile | Full-width card with side padding; all form elements stack vertically |
| Desktop | Centered card with generous whitespace on both sides |

The GuestLayout uses `min-h-screen` with flexbox centering, so the card is always vertically and horizontally centered regardless of viewport size.

---

## Files

| File | Purpose |
|------|---------|
| `resources/js/Layouts/GuestLayout.vue` | Shared centered card layout with textured background |
| `resources/js/Pages/Auth/Login.vue` | Email + password login form |
| `resources/js/Pages/Auth/Register.vue` | Name + email + password registration form |
| `resources/js/Pages/Auth/ForgotPassword.vue` | Email-only form to request password reset link |
| `resources/js/Pages/Auth/ResetPassword.vue` | New password form accessed via reset token |
| `resources/js/Pages/Auth/VerifyEmail.vue` | Email verification prompt with resend button |
| `resources/js/Pages/Auth/ConfirmPassword.vue` | Password confirmation for secure actions |
| `routes/auth.php` | All authentication route definitions |
