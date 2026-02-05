# Component Library

> API reference for all 26 shared components in `resources/js/Components/shared/`.

---

## Actions

### Button

> Versatile button component with variants, sizes, icons, loading states, tooltip integration, and router link support.

**File:** `resources/js/Components/shared/Button.vue`

**Props:**

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `variant` | `'primary' \| 'secondary' \| 'ghost' \| 'danger' \| 'link' \| 'outline' \| 'success'` | `'primary'` | Visual style variant |
| `size` | `'xs' \| 'sm' \| 'md' \| 'lg' \| 'xl'` | `'md'` | Button size |
| `type` | `'button' \| 'submit' \| 'reset'` | `'button'` | Native button type attribute |
| `disabled` | `boolean` | `false` | Disables the button |
| `loading` | `boolean` | `false` | Shows a loading spinner and disables the button |
| `loadingText` | `string` | -- | Text to display while loading (replaces slot content) |
| `loadingIcon` | `string` | `'ph:spinner'` | Icon shown during loading state |
| `iconLeft` | `string` | -- | Iconify icon name rendered before the label |
| `iconRight` | `string` | -- | Iconify icon name rendered after the label |
| `iconOnly` | `boolean` | `false` | Renders as a square icon-only button |
| `fullWidth` | `boolean` | `false` | Stretches to fill container width |
| `rounded` | `boolean` | `false` | Uses fully rounded (pill) border radius |
| `square` | `boolean` | `false` | Removes border radius entirely |
| `as` | `'button' \| 'a' \| 'div' \| 'span'` | `'button'` | Root element tag (overridden by `href` or `to`) |
| `href` | `string` | -- | Renders as an anchor element |
| `to` | `RouteLocationRaw` | -- | Renders as a Vue Router `<RouterLink>` |
| `tooltip` | `string` | -- | Wraps the button in a Tooltip with this text |
| `tooltipSide` | `'top' \| 'right' \| 'bottom' \| 'left'` | `'top'` | Tooltip placement side |
| `shortcut` | `string` | -- | Keyboard shortcut hint shown inside the tooltip |

**Slots:**

| Slot | Description |
|------|-------------|
| `default` | Button label content |

**Events:**

| Event | Payload | Description |
|-------|---------|-------------|
| `click` | `MouseEvent` | Emitted on click (suppressed when disabled or loading) |

**Exposed Methods:** `focus()`, `blur()`, `el` (ref to DOM element)

**Usage:**

```vue
<Button variant="primary" icon-left="ph:plus" @click="handleCreate">
  Create Item
</Button>

<Button variant="danger" loading :loading-text="'Deleting...'" />

<Button icon-only icon-left="ph:gear" variant="ghost" tooltip="Settings" />
```

---

### DropdownMenu

> Dropdown menu built on Reka UI primitives, supporting grouped items, submenus, icons, shortcuts, and custom header slots.

**File:** `resources/js/Components/shared/DropdownMenu.vue`

**Props:**

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `items` | `(MenuItem \| MenuItem[])[]` | `[]` | Menu items. Nest arrays to create visually separated groups. |
| `side` | `'top' \| 'right' \| 'bottom' \| 'left'` | `'bottom'` | Preferred side for the dropdown |
| `align` | `'start' \| 'center' \| 'end'` | `'start'` | Alignment along the side axis |
| `sideOffset` | `number` | `4` | Pixel offset from the trigger |

**MenuItem Interface:**

| Field | Type | Description |
|-------|------|-------------|
| `label` | `string` | Display text |
| `icon` | `string` | Iconify icon name |
| `shortcut` | `string` | Keyboard shortcut label |
| `disabled` | `boolean` | Disables the item |
| `color` | `'default' \| 'error'` | Text color variant |
| `slot` | `string` | When set to `'header'`, renders the `header` slot instead |
| `click` | `() => void` | Callback when the item is selected |
| `children` | `MenuItem[]` | Creates a submenu |

**Slots:**

| Slot | Description |
|------|-------------|
| `default` | Trigger element (rendered with `as-child`) |
| `header` | Custom content rendered when a MenuItem has `slot: 'header'` |
| `content` | Appended after all items inside the dropdown panel |

**Usage:**

```vue
<DropdownMenu :items="[
  { label: 'Edit', icon: 'ph:pencil', click: handleEdit },
  { label: 'Delete', icon: 'ph:trash', color: 'error', click: handleDelete },
]">
  <Button variant="ghost" icon-only icon-left="ph:dots-three" />
</DropdownMenu>
```

---

### ContextMenu

> Right-click context menu with grouped items, submenus, and an open model.

**File:** `resources/js/Components/shared/ContextMenu.vue`

**Props:**

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `items` | `(MenuItem \| MenuItem[])[]` | `[]` | Menu items (same interface as DropdownMenu) |

**Models:**

| Model | Type | Default | Description |
|-------|------|---------|-------------|
| `open` | `boolean` | `false` | Controls open state (two-way via `v-model:open`) |

**Slots:**

| Slot | Description |
|------|-------------|
| `default` | The element that responds to right-click |
| `content` | Custom content appended inside the menu panel |

**Usage:**

```vue
<ContextMenu v-model:open="menuOpen" :items="contextItems">
  <div class="p-4">Right-click me</div>
</ContextMenu>
```

---

## Forms

### Input

> Full-featured text input with label, icons, validation states, password toggle, copy-to-clipboard, character counter, and debounce support.

**File:** `resources/js/Components/shared/Input.vue`

**Props:**

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `modelValue` | `string \| number` | `''` | Bound value (v-model) |
| `name` | `string` | -- | Input name attribute |
| `type` | `'text' \| 'email' \| 'password' \| 'number' \| 'search' \| 'tel' \| 'url' \| 'date' \| 'time' \| 'datetime-local'` | `'text'` | Input type |
| `size` | `'xs' \| 'sm' \| 'md' \| 'lg' \| 'xl'` | `'md'` | Input height and text size |
| `label` | `string` | -- | Label text above the input |
| `placeholder` | `string` | -- | Placeholder text |
| `hint` | `string` | -- | Helper text below the input |
| `error` | `string` | -- | Error message (replaces hint, changes border to red) |
| `prefix` | `string` | -- | Static text prefix inside the input |
| `suffix` | `string` | -- | Static text suffix inside the input |
| `iconLeft` | `string` | -- | Leading icon name |
| `iconRight` | `string` | -- | Trailing icon name |
| `disabled` | `boolean` | `false` | Disables the input |
| `readonly` | `boolean` | `false` | Makes the input read-only |
| `required` | `boolean` | `false` | Marks as required (shows asterisk) |
| `optional` | `boolean` | `false` | Shows "(optional)" after the label |
| `loading` | `boolean` | `false` | Shows a spinner in the trailing area |
| `success` | `boolean` | `false` | Shows a green check and green border |
| `clearable` | `boolean` | `false` | Shows a clear button when input has a value |
| `copyable` | `boolean` | `false` | Shows a copy-to-clipboard button |
| `showPasswordToggle` | `boolean` | `true` | Shows eye icon for password fields |
| `showCounter` | `boolean` | `false` | Shows character count when `maxLength` is set |
| `showErrorIcon` | `boolean` | `true` | Shows a warning icon on error |
| `fullWidth` | `boolean` | `true` | Makes the input fill its container |
| `autoFocus` | `boolean` | `false` | Focuses the input on mount |
| `autofocus` | `boolean` | `false` | Alias for `autoFocus` |
| `minLength` | `number` | -- | Minimum character length |
| `maxLength` | `number` | -- | Maximum character length |
| `min` | `number \| string` | -- | Minimum value (number/date inputs) |
| `max` | `number \| string` | -- | Maximum value (number/date inputs) |
| `step` | `number \| string` | -- | Step increment (number inputs) |
| `pattern` | `string` | -- | Regex validation pattern |
| `autocomplete` | `string` | `'off'` | Autocomplete attribute |
| `selectOnFocus` | `boolean` | `false` | Selects all text on focus |
| `debounce` | `number` | `0` | Debounce delay in ms for `update:modelValue` |

**Slots:**

| Slot | Description |
|------|-------------|
| `prefix` | Custom leading content (replaces `iconLeft`/`prefix` prop) |
| `suffix` | Custom trailing content (replaces `iconRight`/`suffix` prop) |

**Events:**

| Event | Payload | Description |
|-------|---------|-------------|
| `update:modelValue` | `string \| number` | Value changed |
| `focus` | `FocusEvent` | Input focused |
| `blur` | `FocusEvent` | Input blurred |
| `input` | `Event` | Raw input event |
| `enter` | `KeyboardEvent` | Enter key pressed |
| `escape` | `KeyboardEvent` | Escape key pressed (also blurs) |
| `clear` | -- | Clear button clicked |
| `copy` | -- | Value copied to clipboard |

**Exposed Methods:** `focus()`, `blur()`, `select()`, `clear()`, `getInputElement()`

**Usage:**

```vue
<Input
  v-model="email"
  type="email"
  label="Email"
  placeholder="you@example.com"
  icon-left="ph:envelope"
  required
  clearable
/>
```

---

### Checkbox

> Checkbox built on Reka UI with label, description, and required indicator.

**File:** `resources/js/Components/shared/Checkbox.vue`

**Props:**

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `id` | `string` | auto-generated | Custom id for the checkbox element |
| `label` | `string` | -- | Label text |
| `description` | `string` | -- | Helper description below the label |
| `disabled` | `boolean` | `false` | Disables the checkbox |
| `required` | `boolean` | `false` | Shows a required asterisk |

**Models:**

| Model | Type | Default | Description |
|-------|------|---------|-------------|
| `checked` | `boolean` | `false` | Checked state (two-way via `v-model:checked`) |

**Slots:**

| Slot | Description |
|------|-------------|
| `default` | Custom label content (replaces `label` prop) |

**Usage:**

```vue
<Checkbox v-model:checked="agreed" label="I agree to the terms" required />
```

---

### Select

> Select dropdown built on Reka UI with icon support, color dots, and flexible item structures.

**File:** `resources/js/Components/shared/Select.vue`

**Props:**

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `items` | `SelectItem[] \| Record<string, unknown>[]` | -- | Array of option objects |
| `valueKey` | `string` | `'value'` | Key to use as the option value |
| `labelKey` | `string` | `'label'` | Key to use as the option display text |
| `placeholder` | `string` | `'Select...'` | Placeholder when no value is selected |
| `icon` | `string` | -- | Icon shown in the trigger |
| `size` | `'sm' \| 'md' \| 'lg'` | `'md'` | Select size |
| `disabled` | `boolean` | `false` | Disables the select |

**SelectItem Interface:**

| Field | Type | Description |
|-------|------|-------------|
| `value` | `string` | Option value |
| `label` | `string` | Display text |
| `icon` | `string` | Optional icon |
| `color` | `string` | Optional color dot class |
| `disabled` | `boolean` | Disables this option |

**Models:**

| Model | Type | Description |
|-------|------|-------------|
| default | `string` | Selected value (v-model) |

**Usage:**

```vue
<Select
  v-model="status"
  :items="[
    { value: 'active', label: 'Active', icon: 'ph:check-circle' },
    { value: 'archived', label: 'Archived', icon: 'ph:archive' },
  ]"
  placeholder="Choose status"
/>
```

---

### SearchInput

> Feature-rich search input with multiple visual variants, floating label, voice input, recent searches dropdown, results count, and keyboard hints.

**File:** `resources/js/Components/shared/SearchInput.vue`

**Props:**

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `modelValue` | `string` | `''` | Bound value (v-model) |
| `placeholder` | `string` | `'Search...'` | Placeholder text |
| `label` | `string` | -- | Label text (standard or floating) |
| `floatingLabel` | `boolean` | `false` | Enables floating label animation |
| `helperText` | `string` | -- | Helper text below the input |
| `error` | `string` | -- | Error message |
| `size` | `'xs' \| 'sm' \| 'md' \| 'lg' \| 'xl'` | `'md'` | Input size |
| `variant` | `'default' \| 'ghost' \| 'filled' \| 'outlined' \| 'pill' \| 'minimal'` | `'default'` | Visual variant |
| `fullWidth` | `boolean` | `true` | Fills container width |
| `prefixIcon` | `string` | `'ph:magnifying-glass'` | Leading search icon |
| `disabled` | `boolean` | `false` | Disables the input |
| `readonly` | `boolean` | `false` | Makes the input read-only |
| `loading` | `boolean` | `false` | Shows loading spinner as the prefix icon |
| `required` | `boolean` | `false` | Shows required asterisk on label |
| `clearable` | `boolean` | `true` | Shows a clear button |
| `clearLabel` | `string` | `'Clear search'` | Aria label for the clear button |
| `autofocus` | `boolean` | `false` | Focuses on mount |
| `voiceInput` | `boolean` | `false` | Enables Web Speech API voice search |
| `showSearchButton` | `boolean` | `false` | Shows a submit arrow button |
| `searchButtonLabel` | `string` | `'Search'` | Aria label for the search button |
| `showKeyboardHints` | `boolean` | `false` | Shows Enter/Esc hints when focused |
| `showResultsCount` | `boolean` | `false` | Displays results count badge |
| `resultsCount` | `number` | -- | Number of results to display |
| `searchAnimation` | `boolean` | `false` | Reserved for animation effects |
| `showRecentSearches` | `boolean` | `false` | Shows recent searches dropdown |
| `recentSearches` | `string[]` | `[]` | List of recent search strings |
| `debounce` | `number` | `0` | Debounce delay in ms |
| `id` | `string` | auto-generated | Custom input id |

**Slots:**

| Slot | Description |
|------|-------------|
| `prefix` | Custom content replacing the search icon |
| `suffix` | Custom trailing content |

**Events:**

| Event | Payload | Description |
|-------|---------|-------------|
| `update:modelValue` | `string` | Value changed |
| `search` | `string` | Search submitted (Enter or debounce) |
| `clear` | -- | Input cleared |
| `focus` | -- | Input focused |
| `blur` | -- | Input blurred |
| `voiceStart` | -- | Voice recognition started |
| `voiceEnd` | `string` | Voice recognition ended with transcript |
| `clearRecentSearches` | -- | User clicked "Clear all" on recent searches |
| `selectRecentSearch` | `string` | User selected a recent search |

**Exposed Methods:** `focus()`, `blur()`, `select()`

**Usage:**

```vue
<SearchInput
  v-model="query"
  variant="pill"
  :debounce="300"
  show-results-count
  :results-count="42"
  clearable
  @search="performSearch"
/>
```

---

### EmojiPicker

> Emoji picker popover with categorized grids and quick reaction row.

**File:** `resources/js/Components/shared/EmojiPicker.vue`

**Props:**

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `side` | `'top' \| 'right' \| 'bottom' \| 'left'` | `'top'` | Popover placement side |
| `align` | `'start' \| 'center' \| 'end'` | `'start'` | Popover alignment |

**Slots:**

| Slot | Description |
|------|-------------|
| `default` | Trigger element for the popover |

**Events:**

| Event | Payload | Description |
|-------|---------|-------------|
| `select` | `string` | The selected emoji character |

**Usage:**

```vue
<EmojiPicker @select="addReaction">
  <Button variant="ghost" icon-only icon-left="ph:smiley" />
</EmojiPicker>
```

---

## Overlays

### Modal

> Centered dialog overlay built on Reka UI with header, footer, close button, and multiple size options.

**File:** `resources/js/Components/shared/Modal.vue`

**Props:**

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `title` | `string` | -- | Dialog title text |
| `description` | `string` | -- | Subtitle below the title |
| `icon` | `string` | -- | Icon shown in the header beside the title |
| `size` | `'sm' \| 'md' \| 'lg' \| 'xl' \| 'full'` | `'md'` | Maximum width of the dialog |
| `closeOnEscape` | `boolean` | `true` | Whether pressing Escape closes the modal |

**Size Values:** `sm` = max-w-sm, `md` = max-w-lg, `lg` = max-w-2xl, `xl` = max-w-4xl, `full` = near-viewport

**Models:**

| Model | Type | Default | Description |
|-------|------|---------|-------------|
| `open` | `boolean` | `false` | Controls visibility (two-way via `v-model:open`) |

**Slots:**

| Slot | Description |
|------|-------------|
| `default` | Main body content |
| `header` | Replaces the default title/icon header |
| `footer` | Footer area below the body (rendered with a top border) |

**Events:**

| Event | Payload | Description |
|-------|---------|-------------|
| `close` | -- | Emitted when the modal is closed |

**Usage:**

```vue
<Modal v-model:open="showDialog" title="Confirm Action" icon="ph:warning">
  <p>Are you sure you want to proceed?</p>
  <template #footer>
    <Button variant="secondary" @click="showDialog = false">Cancel</Button>
    <Button variant="danger" @click="confirm">Confirm</Button>
  </template>
</Modal>
```

---

### Slideover

> Side panel overlay with header, scrollable body, and footer. Slides in from the left or right.

**File:** `resources/js/Components/shared/Slideover.vue`

**Props:**

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `title` | `string` | -- | Panel title |
| `description` | `string` | -- | Subtitle text |
| `side` | `'left' \| 'right'` | `'right'` | Side the panel slides in from |
| `size` | `'sm' \| 'md' \| 'lg' \| 'xl' \| 'full'` | `'md'` | Panel width |
| `showClose` | `boolean` | `true` | Shows the close button in the header |
| `closeOnEscape` | `boolean` | `true` | Whether Escape closes the panel |

**Size Values:** `sm` = max-w-sm, `md` = max-w-lg, `lg` = max-w-2xl, `xl` = max-w-4xl, `full` = full width

**Models:**

| Model | Type | Default | Description |
|-------|------|---------|-------------|
| `open` | `boolean` | `false` | Controls visibility (two-way via `v-model:open`) |

**Slots:**

| Slot | Description |
|------|-------------|
| `default` | Body content (also accessible via `body` slot) |
| `header` | Replaces the default header |
| `body` | Alias for default slot, scrollable area |
| `footer` | Footer area with top border |

**Events:**

| Event | Payload | Description |
|-------|---------|-------------|
| `close` | -- | Emitted when the panel closes |

**Usage:**

```vue
<Slideover v-model:open="detailOpen" title="Task Details" side="right" size="lg">
  <template #body>
    <p>Panel content here.</p>
  </template>
  <template #footer>
    <Button @click="save">Save</Button>
  </template>
</Slideover>
```

---

### Popover

> Popover panel built on Reka UI with optional arrow and close button.

**File:** `resources/js/Components/shared/Popover.vue`

**Props:**

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `side` | `'top' \| 'right' \| 'bottom' \| 'left'` | `'bottom'` | Preferred placement side |
| `align` | `'start' \| 'center' \| 'end'` | `'center'` | Alignment along the side axis |
| `sideOffset` | `number` | `4` | Pixel offset from the trigger |
| `showArrow` | `boolean` | `false` | Renders a directional arrow |
| `showClose` | `boolean` | `false` | Renders a close button inside the popover |
| `mode` | `'click' \| 'hover'` | `'click'` | Trigger mode |
| `openDelay` | `number` | `0` | Delay before opening (ms) |
| `closeDelay` | `number` | `0` | Delay before closing (ms) |

**Models:**

| Model | Type | Default | Description |
|-------|------|---------|-------------|
| `open` | `boolean` | `false` | Controls visibility (two-way via `v-model:open`) |

**Slots:**

| Slot | Description |
|------|-------------|
| `default` | Trigger element (rendered with `as-child`) |
| `content` | Popover body content |

**Usage:**

```vue
<Popover show-arrow>
  <Button variant="ghost">Open Popover</Button>
  <template #content>
    <p>Popover content here.</p>
  </template>
</Popover>
```

---

### Tooltip

> Lightweight tooltip wrapper built on Reka UI.

**File:** `resources/js/Components/shared/Tooltip.vue`

**Props:**

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `text` | `string` | -- | Tooltip text (used when `content` slot is not provided) |
| `side` | `'top' \| 'right' \| 'bottom' \| 'left'` | `'top'` | Placement side |
| `sideOffset` | `number` | `4` | Pixel offset from the trigger |
| `delayDuration` | `number` | `300` | Delay before showing (ms) |
| `delayOpen` | `number` | -- | Alias for `delayDuration` (takes precedence) |
| `disabled` | `boolean` | `false` | When true, renders only the trigger without tooltip behavior |

**Slots:**

| Slot | Description |
|------|-------------|
| `default` | Trigger element |
| `content` | Custom tooltip content (replaces `text` prop) |

**Usage:**

```vue
<Tooltip text="Copy to clipboard" side="bottom">
  <Button icon-only icon-left="ph:copy" variant="ghost" />
</Tooltip>
```

---

### ConfirmDialog

> Pre-built confirmation modal with variant-based styling, optional text input confirmation, checkbox, countdown, and warning messages.

**File:** `resources/js/Components/shared/ConfirmDialog.vue`

**Props:**

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `open` | `boolean` | -- | Controls dialog visibility (required, two-way via `v-model:open`) |
| `title` | `string` | -- | Dialog title (required) |
| `description` | `string` | -- | Description text (required) |
| `variant` | `'default' \| 'danger' \| 'warning' \| 'success' \| 'info'` | `'default'` | Color scheme and default icon |
| `size` | `'sm' \| 'md' \| 'lg'` | `'md'` | Dialog size |
| `icon` | `string` | auto (per variant) | Custom icon (overrides variant default) |
| `confirmIcon` | `string` | -- | Icon for the confirm button |
| `cancelIcon` | `string` | -- | Icon for the cancel button |
| `confirmLabel` | `string` | `'Confirm'` | Confirm button text |
| `cancelLabel` | `string` | `'Cancel'` | Cancel button text |
| `confirmVariant` | `'primary' \| 'secondary' \| 'ghost' \| 'danger'` | -- | Confirm button style (auto-selects `danger` for danger variant) |
| `cancelVariant` | `'secondary' \| 'ghost'` | `'secondary'` | Cancel button style |
| `hideCancel` | `boolean` | `false` | Hides the cancel button |
| `loading` | `boolean` | `false` | Shows loading state on the confirm button |
| `countdown` | `number` | -- | Seconds countdown before confirm becomes enabled |
| `requireInput` | `boolean` | `false` | Requires text input before confirming |
| `inputLabel` | `string` | `'Type to confirm'` | Label for the confirmation input |
| `inputPlaceholder` | `string` | `'Type here...'` | Placeholder for the confirmation input |
| `inputHint` | `string` | -- | Hint text below the confirmation input |
| `expectedInput` | `string` | -- | Exact string the user must type to enable confirm |
| `showCheckbox` | `boolean` | `false` | Shows a checkbox (e.g., "Don't show again") |
| `checkboxLabel` | `string` | `"Don't show this again"` | Checkbox label text |
| `checkboxChecked` | `boolean` | `false` | Initial checkbox state (two-way via `v-model:checkboxChecked`) |
| `warningMessage` | `string` | -- | Warning box shown in the dialog body |
| `closable` | `boolean` | `true` | Whether the dialog can be closed |
| `blocking` | `boolean` | `false` | Prevents closing while loading |

**Variant Default Icons:** `default` = question, `danger` = warning-circle, `warning` = warning, `success` = check-circle, `info` = info

**Events:**

| Event | Payload | Description |
|-------|---------|-------------|
| `update:open` | `boolean` | Open state changed |
| `update:checkboxChecked` | `boolean` | Checkbox state changed |
| `confirm` | `{ inputValue?: string; checkboxChecked?: boolean }` | User confirmed |
| `cancel` | -- | User cancelled |

**Slots:**

| Slot | Description |
|------|-------------|
| `default` | Custom body content between description and actions |
| `header` | Replaces the icon/title/description header |
| `footer` | Additional content below the action buttons |

**Usage:**

```vue
<ConfirmDialog
  v-model:open="showDelete"
  variant="danger"
  title="Delete Project"
  description="This action cannot be undone."
  confirm-label="Delete"
  :loading="deleting"
  @confirm="handleDelete"
/>
```

---

## Display

### Badge

> Flexible badge/tag component with variants, styles, icons, dots, avatars, counts, and remove functionality.

**File:** `resources/js/Components/shared/Badge.vue`

**Props:**

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `variant` | `'default' \| 'primary' \| 'secondary' \| 'success' \| 'warning' \| 'error' \| 'info'` | `'default'` | Color variant |
| `badgeStyle` | `'soft' \| 'solid' \| 'outline' \| 'ghost'` | `'soft'` | Visual style |
| `size` | `'xs' \| 'sm' \| 'md' \| 'lg' \| 'xl'` | `'sm'` | Badge size |
| `shape` | `'rounded' \| 'pill' \| 'square'` | `'rounded'` | Border radius shape |
| `label` | `string` | -- | Text content |
| `count` | `number` | -- | Numeric count to display |
| `maxCount` | `number` | `99` | Counts above this show as "99+" |
| `icon` | `string` | -- | Left icon |
| `iconRight` | `string` | -- | Right icon |
| `iconOnly` | `boolean` | -- | Hides the label, shows only the icon |
| `avatar` | `string` | -- | Avatar image URL |
| `avatarFallback` | `string` | -- | Alt text for the avatar |
| `dot` | `boolean` | -- | Shows a colored dot indicator |
| `dotPosition` | `'left' \| 'right'` | `'left'` | Dot placement |
| `interactive` | `boolean` | `false` | Makes the badge clickable |
| `removable` | `boolean` | `false` | Shows a remove (x) button |
| `disabled` | `boolean` | `false` | Disables interactions |
| `loading` | `boolean` | `false` | Shows a spinner |
| `uppercase` | `boolean` | `false` | Uppercases the label text |
| `truncate` | `boolean` | `false` | Truncates long text |
| `tooltip` | `string` | -- | Wraps badge in a Tooltip |
| `tooltipSide` | `'top' \| 'right' \| 'bottom' \| 'left'` | `'top'` | Tooltip side |

**Slots:**

| Slot | Description |
|------|-------------|
| `default` | Custom label content (replaces `label` and `count`) |

**Events:**

| Event | Payload | Description |
|-------|---------|-------------|
| `click` | `MouseEvent` | Badge clicked (when interactive) |
| `remove` | -- | Remove button clicked |

**Usage:**

```vue
<Badge variant="success" badge-style="soft" icon="ph:check">Completed</Badge>
<Badge variant="error" badge-style="solid" :count="5" />
<Badge label="Tag" removable @remove="removeTag" />
```

---

### Card

> Highly configurable card container with variants, collapsible sections, loading overlay, media slot, drag handle, and badge indicators.

**File:** `resources/js/Components/shared/Card.vue`

**Props:**

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `variant` | `'default' \| 'elevated' \| 'outlined' \| 'ghost'` | `'default'` | Visual style |
| `padding` | `'none' \| 'xs' \| 'sm' \| 'md' \| 'lg' \| 'xl'` | `'md'` | Content padding |
| `radius` | `'none' \| 'sm' \| 'md' \| 'lg' \| 'xl' \| '2xl'` | `'lg'` | Border radius |
| `shadow` | `'none' \| 'sm' \| 'md' \| 'lg'` | `'none'` | Box shadow |
| `noPadding` | `boolean` | `false` | Removes all padding |
| `title` | `string` | -- | Header title text |
| `subtitle` | `string` | -- | Header subtitle text |
| `headerIcon` | `string` | -- | Icon in the header |
| `headerDivider` | `boolean` | `false` | Adds a border below the header |
| `footerDivider` | `boolean` | `false` | Adds a border above the footer |
| `mediaAspect` | `'auto' \| 'square' \| 'video' \| 'wide' \| 'portrait'` | `'auto'` | Aspect ratio for the media slot |
| `hoverable` | `boolean` | `false` | Adds hover border effect |
| `interactive` | `boolean` | `false` | Makes the card clickable |
| `clickable` | `boolean` | `false` | Alias for interactive (shows pointer cursor) |
| `selected` | `boolean` | `false` | Adds a selection ring |
| `disabled` | `boolean` | `false` | Disables the card |
| `as` | `'div' \| 'article' \| 'section' \| 'aside' \| 'button' \| 'a'` | `'div'` | Root element tag |
| `href` | `string` | -- | Link URL |
| `to` | `RouteLocationRaw` | -- | Vue Router destination |
| `collapsible` | `boolean` | `false` | Makes the content collapsible |
| `defaultExpanded` | `boolean` | `true` | Initial expanded state when collapsible |
| `loading` | `boolean` | `false` | Shows a loading overlay |
| `loadingText` | `string` | -- | Text shown in the loading overlay |
| `loadingIcon` | `string` | `'ph:spinner'` | Loading spinner icon |
| `badge` | `number` | -- | Numeric badge in the top-right corner |
| `dot` | `boolean` | -- | Small dot indicator (when no badge) |
| `closable` | `boolean` | `false` | Shows a close button |
| `draggable` | `boolean` | `false` | Enables drag behavior |
| `showDragHandle` | `boolean` | `true` | Shows the drag handle icon |

**Slots:**

| Slot | Description |
|------|-------------|
| `default` | Main card content |
| `media` | Media area above the header (respects `mediaAspect`) |
| `header` | Replaces the default title/subtitle header |
| `headerActions` | Action buttons in the header row |
| `footer` | Footer content |

**Events:**

| Event | Payload | Description |
|-------|---------|-------------|
| `click` | `MouseEvent` | Card clicked |
| `close` | -- | Close button clicked |
| `expand` | -- | Card expanded |
| `collapse` | -- | Card collapsed |

**Exposed Methods:** `expand()`, `collapse()`, `toggle()`, `isExpanded` (computed ref)

**Usage:**

```vue
<Card title="Project Stats" header-icon="ph:chart-bar" collapsible>
  <p>Card body content.</p>
  <template #footer>
    <Button variant="link">View All</Button>
  </template>
</Card>
```

---

### CardHeader

> Standalone card header with icon/avatar, title, subtitle, description, meta info, tags, badges, status indicators, and configurable action buttons.

**File:** `resources/js/Components/shared/CardHeader.vue`

**Props:**

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `title` | `string` | -- | Header title (required) |
| `subtitle` | `string` | -- | Subtitle text |
| `description` | `string` | -- | Description text (line-clamped to 2 lines) |
| `icon` | `string` | -- | Icon name |
| `iconColor` | `string` | -- | Custom icon color class |
| `iconBg` | `string` | -- | Custom icon background class |
| `iconBadge` | `number \| string` | -- | Badge overlay on the icon |
| `avatar` | `User` | -- | User object for avatar (alternative to icon) |
| `showAvatarStatus` | `boolean` | `true` | Shows status dot on the avatar |
| `gradient` | `boolean` | `false` | Reserved for gradient title styling |
| `titleTag` | `'h1' \| 'h2' \| 'h3' \| 'h4' \| 'h5' \| 'h6' \| 'span'` | `'h2'` | HTML element for the title |
| `badge` | `string` | -- | Badge text beside the title |
| `badgeVariant` | `'default' \| 'primary' \| 'success' \| 'warning' \| 'error'` | `'default'` | Badge color |
| `status` | `AgentStatus` | -- | Status badge beside the title |
| `verified` | `boolean` | -- | Shows a verified seal icon |
| `meta` | `string` | -- | Additional meta text |
| `timestamp` | `string` | -- | Timestamp with clock icon |
| `author` | `string` | -- | Author name with user icon |
| `tags` | `string[]` | -- | Tag badges below the title |
| `maxTags` | `number` | `3` | Maximum visible tags before "+N" |
| `size` | `'sm' \| 'md' \| 'lg'` | `'md'` | Header size |
| `variant` | `'default' \| 'compact' \| 'prominent' \| 'minimal'` | `'default'` | Visual variant |
| `bordered` | `boolean` | `false` | Adds a bottom border |
| `sticky` | `boolean` | `false` | Makes the header sticky with backdrop blur |
| `action` | `CardHeaderAction` | -- | Primary action button |
| `secondaryAction` | `CardHeaderAction` | -- | Secondary action button |
| `quickActions` | `QuickAction[]` | -- | Array of icon-only quick action buttons |
| `menuItems` | `MenuItem[]` | -- | Dropdown menu items for a "more" button |
| `menuIcon` | `string` | `'ph:dots-three'` | Icon for the menu trigger |
| `menuAlign` | `'start' \| 'center' \| 'end'` | `'end'` | Menu dropdown alignment |

**Slots:**

| Slot | Description |
|------|-------------|
| `icon` | Custom icon content |
| `subtitle` | Custom subtitle content |
| `actions` | Additional custom action elements |

**Events:**

| Event | Payload | Description |
|-------|---------|-------------|
| `action` | -- | Primary action triggered |
| `secondaryAction` | -- | Secondary action triggered |

**Usage:**

```vue
<CardHeader
  title="Agent Overview"
  subtitle="Last active 5 min ago"
  icon="ph:robot"
  :action="{ label: 'Configure', icon: 'ph:gear', onClick: openSettings }"
/>
```

---

### StatCard

> Statistics display card with formatted values, trend indicators, sparkline charts, progress bars, and comparison rows.

**File:** `resources/js/Components/shared/StatCard.vue`

**Props:**

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `label` | `string` | -- | Stat label (required) |
| `value` | `string \| number` | -- | Main stat value (required) |
| `subValue` | `string` | -- | Secondary value beside the main value |
| `description` | `string` | -- | Description text below the label |
| `icon` | `string` | -- | Icon in the top-right corner |
| `prefix` | `string` | -- | Text prefix before the value (e.g., "$") |
| `suffix` | `string` | -- | Text suffix after the value (e.g., "users") |
| `precision` | `number` | `0` | Decimal places for numeric values |
| `compact` | `boolean` | `false` | Uses compact number formatting (1K, 1M, 1B) |
| `trend` | `number` | -- | Trend percentage (positive = up, negative = down) |
| `trendLabel` | `string` | -- | Label beside the trend indicator |
| `inverseTrend` | `boolean` | `false` | Inverts trend colors (down = positive) |
| `sparklineData` | `number[]` | -- | Array of values for the sparkline chart |
| `progress` | `number` | -- | Progress bar value (0-100) |
| `progressLabel` | `string` | -- | Label for the progress bar |
| `comparison` | `{ label: string; value: number \| string }` | -- | Comparison row below the main content |
| `badge` | `string` | -- | Badge beside the label |
| `size` | `'sm' \| 'md' \| 'lg'` | `'md'` | Card size |
| `variant` | `'default' \| 'elevated' \| 'outlined'` | `'default'` | Visual style |
| `interactive` | `boolean` | `false` | Makes the card clickable |
| `href` | `string` | -- | Link URL (renders as Inertia Link) |
| `action` | `string` | -- | Action button text in the footer |
| `disabled` | `boolean` | `false` | Disables the card |
| `animated` | `boolean` | `true` | Animates value changes |
| `loading` | `boolean` | `false` | Shows a loading overlay |

**Slots:**

| Slot | Description |
|------|-------------|
| `footer` | Custom footer content |

**Events:**

| Event | Payload | Description |
|-------|---------|-------------|
| `click` | `string \| number` | Card clicked (emits the value) |
| `action` | -- | Footer action button clicked |

**Usage:**

```vue
<StatCard
  label="Total Revenue"
  :value="125000"
  prefix="$"
  compact
  :trend="12.5"
  trend-label="vs last month"
  :sparkline-data="[10, 20, 15, 30, 25, 40]"
/>
```

---

### Icon

> Thin wrapper around `@iconify/vue` that accepts icon names via either `name` or `icon` prop.

**File:** `resources/js/Components/shared/Icon.vue`

**Props:**

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `name` | `string` | -- | Iconify icon name (e.g., `'ph:house'`) |
| `icon` | `string` | -- | Alias for `name` (for backwards compatibility) |
| `class` | `string \| string[]` | -- | CSS classes passed through to the Iconify component |

**Usage:**

```vue
<Icon name="ph:check-circle" class="w-5 h-5 text-green-500" />
```

---

### EmptyState

> Placeholder component for empty views with icon, title, description, action buttons, help links, and retry functionality.

**File:** `resources/js/Components/shared/EmptyState.vue`

**Props:**

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `icon` | `string` | `'ph:ghost'` | Illustration icon |
| `title` | `string` | -- | Title text (required) |
| `description` | `string` | -- | Description text |
| `size` | `'xs' \| 'sm' \| 'md' \| 'lg' \| 'xl'` | `'md'` | Overall size |
| `variant` | `'default' \| 'minimal' \| 'card' \| 'bordered'` | `'default'` | Visual style |
| `color` | `'default' \| 'primary' \| 'success' \| 'warning' \| 'error' \| 'info'` | `'default'` | Icon and border color theme |
| `centered` | `boolean` | `true` | Centers content horizontally |
| `fullHeight` | `boolean` | `false` | Sets a minimum height |
| `action` | `EmptyStateAction` | -- | Primary action button config |
| `secondaryAction` | `EmptyStateAction` | -- | Secondary action button config |
| `helpLink` | `{ label: string; url: string }` | -- | Help link below actions |
| `showRetry` | `boolean` | `false` | Shows a retry button |
| `retrying` | `boolean` | `false` | Retry button loading state |
| `animated` | `boolean` | `true` | Enables entrance animation |
| `decorative` | `boolean` | `false` | Adds decorative background element |
| `compact` | `boolean` | `false` | Removes padding |

**EmptyStateAction Interface:**

| Field | Type | Description |
|-------|------|-------------|
| `label` | `string` | Button text |
| `icon` | `string` | Button icon |
| `variant` | `'primary' \| 'secondary' \| 'ghost' \| 'danger'` | Button variant |
| `onClick` | `() => void` | Click handler |
| `loading` | `boolean` | Loading state |
| `disabled` | `boolean` | Disabled state |

**Slots:**

| Slot | Description |
|------|-------------|
| `illustration` | Replaces the default icon area |
| `content` | Additional content below the description |
| `actions` | Replaces the default action buttons |
| `footer` | Custom footer content |

**Events:**

| Event | Payload | Description |
|-------|---------|-------------|
| `action` | -- | Primary action triggered |
| `secondaryAction` | -- | Secondary action triggered |
| `retry` | -- | Retry button clicked |

**Usage:**

```vue
<EmptyState
  icon="ph:folder-open"
  title="No projects yet"
  description="Create your first project to get started."
  :action="{ label: 'New Project', icon: 'ph:plus', onClick: createProject }"
/>
```

---

### Skeleton

> Loading placeholder with multiple variants (line, circle, avatar, button, input, image) and rich preset layouts (card, list, table, form, paragraph, profile, stats, message).

**File:** `resources/js/Components/shared/Skeleton.vue`

**Props:**

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `variant` | `'line' \| 'text' \| 'heading' \| 'circle' \| 'square' \| 'avatar' \| 'button' \| 'input' \| 'image' \| 'badge' \| 'icon' \| 'custom'` | `'line'` | Shape variant for single skeletons |
| `size` | `'xs' \| 'sm' \| 'md' \| 'lg' \| 'xl' \| '2xl'` | `'md'` | Size (applies to circle, avatar, button, icon variants) |
| `rounded` | `'none' \| 'sm' \| 'md' \| 'lg' \| 'xl' \| '2xl' \| 'full'` | `'md'` | Border radius |
| `animation` | `'pulse' \| 'wave' \| 'shimmer' \| 'none'` | `'pulse'` | Animation style |
| `width` | `string \| number` | -- | Custom width |
| `height` | `string \| number` | -- | Custom height |
| `aspectRatio` | `'auto' \| 'square' \| 'video' \| 'wide' \| 'portrait'` | -- | Aspect ratio for image variant |
| `delay` | `number` | `0` | Animation delay in ms |
| `customClass` | `string` | -- | Additional CSS class |
| `preset` | `'avatar-text' \| 'card' \| 'card-horizontal' \| 'list' \| 'table' \| 'form' \| 'paragraph' \| 'profile' \| 'stats' \| 'message'` | -- | Renders a multi-element preset layout |
| `count` | `number` | `3` | Number of rows/items in preset layouts |
| `columns` | `number` | `4` | Number of columns for the table preset |
| `showListIcon` | `boolean` | `true` | Shows avatar circles in list preset |
| `showListSubtext` | `boolean` | `true` | Shows secondary text lines in list preset |
| `avatarSize` | `'xs' \| 'sm' \| 'md' \| 'lg' \| 'xl' \| '2xl'` | `'md'` | Avatar size in avatar-text preset |
| `imageAspect` | `'auto' \| 'square' \| 'video' \| 'wide' \| 'portrait'` | `'video'` | Image aspect ratio in card preset |

**Usage:**

```vue
<!-- Single skeleton -->
<Skeleton variant="heading" />
<Skeleton variant="avatar" size="lg" />

<!-- Preset layout -->
<Skeleton preset="card" />
<Skeleton preset="list" :count="5" />
<Skeleton preset="table" :count="8" :columns="5" />
```

---

### StatusBadge

> Agent status indicator badge with configurable dot, icon, label, and rich tooltip showing activity details.

**File:** `resources/js/Components/shared/StatusBadge.vue`

**Props:**

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `status` | `AgentStatus` (`'idle' \| 'working' \| 'offline'`) | -- | Agent status (required) |
| `size` | `'xs' \| 'sm' \| 'md' \| 'lg' \| 'xl'` | `'sm'` | Badge size |
| `variant` | `'filled' \| 'soft' \| 'outline' \| 'ghost' \| 'dot-only' \| 'minimal'` | `'soft'` | Visual style |
| `showLabel` | `boolean` | `true` | Shows the status text label |
| `showDot` | `boolean` | `true` | Shows the colored status dot |
| `showIcon` | `boolean` | `false` | Shows a status icon (alternative to dot) |
| `interactive` | `boolean` | `false` | Makes the badge clickable |
| `disabled` | `boolean` | `false` | Disables interaction |
| `showTooltip` | `boolean` | `true` | Shows a rich tooltip on hover |
| `tooltipSide` | `'top' \| 'right' \| 'bottom' \| 'left'` | `'top'` | Tooltip placement |
| `tooltipDelay` | `number` | `300` | Tooltip show delay in ms |
| `customTooltip` | `string` | -- | Custom tooltip content |
| `lastActivity` | `string` | -- | "Last activity" text shown in tooltip |
| `currentTask` | `string` | -- | Current task text shown in tooltip (when working) |
| `customLabel` | `string` | -- | Overrides the default status label |
| `pill` | `boolean` | `true` | Uses pill (fully rounded) shape |
| `loading` | `boolean` | `false` | Shows a loading spinner |

**Events:**

| Event | Payload | Description |
|-------|---------|-------------|
| `click` | `AgentStatus` | Badge clicked |
| `statusChange` | `AgentStatus` | Status change requested |

**Usage:**

```vue
<StatusBadge status="working" last-activity="2 min ago" current-task="Analyzing report" />
<StatusBadge status="idle" variant="dot-only" />
```

---

### AgentAvatar

> Avatar component for both agent and human users with status dots, presence indicators, badges, image support with fallback, tooltips, and stacking for avatar groups.

**File:** `resources/js/Components/shared/AgentAvatar.vue`

**Props:**

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `user` | `User` | -- | User object (required) |
| `size` | `'xs' \| 'sm' \| 'md' \| 'lg' \| 'xl' \| '2xl'` | `'md'` | Avatar size |
| `shape` | `'circle' \| 'rounded' \| 'square'` | `'circle'` | Avatar shape |
| `variant` | `'filled' \| 'soft' \| 'outline'` | `'filled'` | Color fill style |
| `showStatus` | `boolean` | `true` | Shows the status/presence dot |
| `statusPosition` | `'top-left' \| 'top-right' \| 'bottom-left' \| 'bottom-right'` | `'bottom-right'` | Position of the status dot |
| `ring` | `boolean` | `false` | Adds an outer ring |
| `ringColor` | `string` | -- | Custom ring color class |
| `glow` | `boolean` | `false` | Adds a glow effect |
| `animate` | `boolean` | `false` | Enables animation |
| `pulse` | `boolean` | `false` | Adds a pulse effect |
| `badge` | `number \| string` | -- | Notification badge content |
| `badgePosition` | `'top-left' \| 'top-right' \| 'bottom-left' \| 'bottom-right'` | `'top-right'` | Badge position |
| `badgeColor` | `'primary' \| 'success' \| 'warning' \| 'danger' \| 'info'` | `'primary'` | Badge color |
| `presence` | `'typing' \| 'editing' \| 'viewing'` | -- | Presence activity indicator |
| `src` | `string` | -- | Custom avatar image URL |
| `fallbackSrc` | `string` | -- | Fallback image if `src` fails |
| `interactive` | `boolean` | `false` | Makes the avatar clickable |
| `disabled` | `boolean` | `false` | Disables interaction |
| `showTooltip` | `boolean` | `true` | Shows a rich tooltip on hover |
| `tooltipSide` | `'top' \| 'right' \| 'bottom' \| 'left'` | `'top'` | Tooltip placement |
| `tooltipOffset` | `number` | `5` | Tooltip offset in pixels |
| `tooltipDelay` | `number` | `300` | Tooltip show delay in ms |
| `customTooltip` | `string` | -- | Custom tooltip text (human users only) |
| `loading` | `boolean` | `false` | Shows a loading spinner |
| `stacked` | `boolean` | `false` | Enables stacked/overlapping mode for groups |
| `stackIndex` | `number` | `0` | Position index in a stacked group |

**Events:**

| Event | Payload | Description |
|-------|---------|-------------|
| `click` | `User` | Avatar clicked |

**Usage:**

```vue
<AgentAvatar :user="agent" size="lg" show-status />
<AgentAvatar :user="humanUser" variant="soft" :badge="3" />
```

---

### CostBadge

> Cost display badge with currency formatting, change indicators, budget tracking with progress bar, cost breakdown tooltip, and multiple visual styles.

**File:** `resources/js/Components/shared/CostBadge.vue`

**Props:**

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `cost` | `number \| string` | -- | Cost value (required) |
| `size` | `'xs' \| 'sm' \| 'md' \| 'lg' \| 'xl'` | `'sm'` | Badge size |
| `variant` | `'actual' \| 'estimated' \| 'budget' \| 'savings'` | `'actual'` | Cost type (estimated prepends "~") |
| `style` | `'default' \| 'soft' \| 'outline' \| 'ghost' \| 'filled'` | `'default'` | Visual style |
| `currency` | `'usd' \| 'eur' \| 'gbp' \| 'credits'` | `'usd'` | Currency format |
| `showCurrency` | `boolean` | `true` | Shows currency symbol |
| `precision` | `number` | `2` | Decimal places |
| `showIcon` | `boolean` | `true` | Shows a cost-related icon |
| `customIcon` | `string` | -- | Custom icon override |
| `change` | `number` | -- | Percentage change indicator |
| `showChange` | `boolean` | `true` | Shows the change indicator |
| `budget` | `number` | -- | Budget total for progress tracking |
| `showBudgetIndicator` | `boolean` | `false` | Shows a small budget status dot |
| `breakdown` | `{ label: string; value: number }[]` | -- | Cost breakdown items for tooltip |
| `interactive` | `boolean` | `false` | Makes the badge clickable |
| `disabled` | `boolean` | `false` | Disables interaction |
| `showTooltip` | `boolean` | `true` | Shows a rich hover card tooltip |
| `tooltipSide` | `'top' \| 'right' \| 'bottom' \| 'left'` | `'top'` | Tooltip placement |
| `tooltipDelay` | `number` | `300` | Tooltip delay in ms |
| `timestamp` | `string` | -- | Timestamp shown in tooltip footer |
| `animated` | `boolean` | `true` | Enables animation |
| `glow` | `boolean` | `false` | Adds a glow effect |
| `compact` | `boolean` | `false` | Removes padding and pill shape |
| `loading` | `boolean` | `false` | Shows a loading spinner |

**Events:**

| Event | Payload | Description |
|-------|---------|-------------|
| `click` | `number` | Badge clicked |

**Usage:**

```vue
<CostBadge :cost="0.45" variant="actual" style="soft" />
<CostBadge :cost="1.20" variant="estimated" :budget="5.00" show-budget-indicator />
```

---

### PresenceRow

> Displays a row of stacked user avatars with overflow count, names, activity indicators, and a rich tooltip listing all users.

**File:** `resources/js/Components/shared/PresenceRow.vue`

**Props:**

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `users` | `User[]` | -- | Array of users to display (required) |
| `max` | `number` | `4` | Maximum visible avatars before overflow count |
| `showLabel` | `boolean` | `true` | Shows the label text |
| `label` | `string` | `'viewing'` | Label text (auto-singularized for 1 user) |
| `showNames` | `boolean` | `false` | Shows user names beside avatars |
| `maxNames` | `number` | `2` | Maximum names to display |
| `size` | `'xs' \| 'sm' \| 'md' \| 'lg'` | `'md'` | Component size |
| `variant` | `'default' \| 'compact' \| 'inline' \| 'card'` | `'default'` | Visual variant |
| `showStatus` | `boolean` | `false` | Shows status dots on avatars |
| `showPresenceRing` | `boolean` | `false` | Shows colored presence rings around avatars |
| `tooltipTitle` | `string` | `'Currently viewing'` | Tooltip header text |
| `tooltipSide` | `'top' \| 'right' \| 'bottom' \| 'left'` | `'top'` | Tooltip side |
| `tooltipDelay` | `number` | `300` | Tooltip show delay in ms |
| `maxTooltipUsers` | `number` | `10` | Maximum users listed in the tooltip |
| `showActivity` | `boolean` | `false` | Shows an activity indicator icon |
| `activityType` | `'typing' \| 'editing' \| 'viewing' \| 'idle'` | -- | Activity type |
| `activityText` | `string` | -- | Activity description text |
| `userPresence` | `{ [userId: string]: ActivityType }` | -- | Per-user presence map |
| `live` | `boolean` | `false` | Shows a "LIVE" indicator |
| `interactive` | `boolean` | `false` | Makes the row clickable |
| `userAction` | `{ icon: string; label: string }` | -- | Action button per user in tooltip |
| `viewAllAction` | `() => void` | -- | "View all" callback in tooltip |

**Events:**

| Event | Payload | Description |
|-------|---------|-------------|
| `click` | `User[]` | Row clicked |
| `userAction` | `User` | Per-user action triggered |

**Usage:**

```vue
<PresenceRow :users="activeUsers" :max="3" label="online" show-status />
```

---

### ActivityLog

> Collapsible activity timeline with search, filters, date grouping, multiple view modes (timeline, list, compact), and step status indicators.

**File:** `resources/js/Components/shared/ActivityLog.vue`

**Props:**

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `steps` | `ActivityStep[]` | -- | Array of activity steps (required) |
| `title` | `string` | `'Activity log'` | Header title |
| `icon` | `string` | -- | Header icon |
| `showHeader` | `boolean` | `true` | Shows the header row |
| `showCount` | `boolean` | `true` | Shows step count badge |
| `collapsible` | `boolean` | `true` | Makes the log collapsible |
| `defaultOpen` | `boolean` | `false` | Initial open state |
| `searchable` | `boolean` | `false` | Shows a search input |
| `filterable` | `boolean` | `false` | Shows a filter dropdown |
| `refreshable` | `boolean` | `false` | Shows a refresh button |
| `groupByDate` | `boolean` | `false` | Groups steps by date |
| `showViewToggle` | `boolean` | `false` | Shows timeline/list/compact view toggle |
| `hasMore` | `boolean` | `false` | Shows a "Load more" button |
| `variant` | `'default' \| 'minimal' \| 'detailed' \| 'list'` | `'default'` | Step display variant |
| `showAvatar` | `boolean` | `false` | Shows avatars on steps |
| `showDuration` | `boolean` | `true` | Shows step duration |
| `showTimestamp` | `boolean` | `false` | Shows step timestamps |
| `animate` | `boolean` | `true` | Enables entrance animations |
| `clickable` | `boolean` | `false` | Makes steps clickable |
| `maxHeight` | `string` | -- | Max height with overflow scroll |
| `loading` | `boolean` | `false` | Shows loading skeletons |
| `emptyIcon` | `string` | `'ph:clock'` | Empty state icon |
| `emptyTitle` | `string` | `'No activity'` | Empty state title |
| `emptyDescription` | `string` | `'Activity will appear here as tasks progress.'` | Empty state description |

**Events:**

| Event | Payload | Description |
|-------|---------|-------------|
| `refresh` | -- | Refresh button clicked |
| `loadMore` | -- | Load more button clicked |
| `stepClick` | `ActivityStep` | A step was clicked |

**Usage:**

```vue
<ActivityLog
  :steps="taskSteps"
  collapsible
  :default-open="true"
  searchable
  filterable
  show-view-toggle
/>
```

---

## Layout

### SectionHeader

> Section header with collapsible toggle, count badge, search, filter, sort, view mode toggle, and action buttons.

**File:** `resources/js/Components/shared/SectionHeader.vue`

**Props:**

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `title` | `string` | -- | Section title (required) |
| `count` | `number` | -- | Item count (auto-formatted with "k" suffix) |
| `countVariant` | `'default' \| 'primary' \| 'success' \| 'warning' \| 'error'` | `'default'` | Count badge color |
| `badge` | `'new' \| 'beta' \| string` | -- | Badge label beside the title |
| `icon` | `string` | -- | Custom icon (shown when not collapsible) |
| `iconColor` | `string` | -- | Custom icon color class |
| `collapseIcon` | `string` | `'ph:caret-right-fill'` | Icon for the collapse toggle |
| `size` | `'sm' \| 'md' \| 'lg'` | `'md'` | Header size |
| `uppercase` | `boolean` | `true` | Uppercases the title text |
| `sticky` | `boolean` | `false` | Makes the header sticky with backdrop blur |
| `bordered` | `boolean` | `false` | Adds a bottom border |
| `collapsible` | `boolean` | `false` | Enables collapse toggle |
| `collapsed` | `boolean` | `false` | Current collapsed state (two-way via `v-model:collapsed`) |
| `searchable` | `boolean` | `false` | Shows a search toggle |
| `searchValue` | `string` | -- | Bound search value (two-way via `v-model:searchValue`) |
| `searchPlaceholder` | `string` | `'Search...'` | Search input placeholder |
| `searchLabel` | `string` | `'Toggle search'` | Aria label for search button |
| `filterable` | `boolean` | `false` | Shows a filter button |
| `filterLabel` | `string` | `'Filter'` | Aria label for filter button |
| `hasActiveFilters` | `boolean` | `false` | Highlights the filter button |
| `filterCount` | `number` | -- | Active filter count badge |
| `sortable` | `boolean` | `false` | Shows a sort button |
| `sortLabel` | `string` | `'Sort'` | Aria label for sort button |
| `sortDirection` | `'asc' \| 'desc' \| 'none'` | `'none'` | Current sort direction (changes icon) |
| `viewModes` | `ViewMode[]` | -- | View mode toggle options |
| `viewMode` | `string` | -- | Current view mode (two-way via `v-model:viewMode`) |
| `action` | `SectionHeaderAction` | -- | Primary action button |
| `moreActions` | `MoreAction[]` | -- | Dropdown menu items |
| `loading` | `boolean` | `false` | Shows a loading spinner |

**ViewMode Interface:**

| Field | Type | Description |
|-------|------|-------------|
| `value` | `string` | Mode identifier |
| `label` | `string` | Aria label |
| `icon` | `string` | Toggle button icon |

**Slots:**

| Slot | Description |
|------|-------------|
| `actions` | Custom action elements appended to the right side |

**Events:**

| Event | Payload | Description |
|-------|---------|-------------|
| `update:collapsed` | `boolean` | Collapsed state changed |
| `update:searchValue` | `string` | Search value changed |
| `update:viewMode` | `string` | View mode changed |
| `search` | `string` | Search value changed (debounced) |
| `filter` | -- | Filter button clicked |
| `sort` | -- | Sort button clicked |
| `action` | -- | Primary action triggered |

**Usage:**

```vue
<SectionHeader
  title="Channels"
  :count="12"
  collapsible
  v-model:collapsed="isCollapsed"
  :action="{ icon: 'ph:plus', label: 'Add', onClick: addChannel }"
/>
```
