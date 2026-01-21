<template>
  <div :class="wrapperClasses">
    <!-- Label -->
    <label
      v-if="label && !floatingLabel"
      :for="inputId"
      :class="labelClasses"
    >
      {{ label }}
      <span v-if="required" class="text-red-400 ml-0.5">*</span>
      <span v-if="optional" class="text-gray-500 ml-1 text-xs font-normal">(optional)</span>
    </label>

    <!-- Input Container -->
    <div :class="containerClasses">
      <!-- Prefix -->
      <div
        v-if="prefix || $slots.prefix"
        :class="prefixClasses"
      >
        <slot name="prefix">{{ prefix }}</slot>
      </div>

      <!-- Left Icon -->
      <div
        v-if="iconLeft || loading"
        :class="leftIconClasses"
      >
        <Icon
          v-if="loading"
          name="ph:spinner"
          class="w-4 h-4 animate-spin"
        />
        <Icon
          v-else-if="iconLeft"
          :name="iconLeft"
          class="w-4 h-4"
        />
      </div>

      <!-- Input Wrapper (for floating label) -->
      <div class="relative flex-1">
        <!-- Floating Label -->
        <label
          v-if="label && floatingLabel"
          :for="inputId"
          :class="floatingLabelClasses"
        >
          {{ label }}
          <span v-if="required" class="text-red-400 ml-0.5">*</span>
        </label>

        <!-- Input Element -->
        <component
          :is="multiline ? 'textarea' : 'input'"
          :id="inputId"
          ref="inputRef"
          v-model="model"
          :type="computedType"
          :name="name"
          :placeholder="floatingLabel ? ' ' : placeholder"
          :disabled="disabled"
          :readonly="readonly"
          :required="required"
          :autocomplete="autocomplete"
          :autofocus="autofocus"
          :min="min"
          :max="max"
          :minlength="minLength"
          :maxlength="maxLength"
          :step="step"
          :pattern="pattern"
          :rows="multiline ? rows : undefined"
          :aria-invalid="!!error"
          :aria-describedby="describedBy"
          :class="inputClasses"
          :style="multiline && autoResize ? { height: textareaHeight } : undefined"
          @input="handleInput"
          @focus="handleFocus"
          @blur="handleBlur"
          @keydown="handleKeydown"
          @keydown.enter="handleEnter"
          @keydown.escape="handleEscape"
        />
      </div>

      <!-- Right Actions Container -->
      <div v-if="hasRightContent" class="flex items-center gap-1">
        <!-- Clear Button -->
        <button
          v-if="clearable && model && !disabled && !readonly"
          type="button"
          class="p-1 rounded-md text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors duration-150 outline-none focus-visible:ring-2 focus-visible:ring-gray-900/50"
          :aria-label="clearLabel"
          @click="handleClear"
        >
          <Icon name="ph:x" class="w-4 h-4" />
        </button>

        <!-- Password Toggle -->
        <button
          v-if="type === 'password' && showPasswordToggle"
          type="button"
          class="p-1 rounded-md text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors duration-150 outline-none focus-visible:ring-2 focus-visible:ring-gray-900/50"
          :aria-label="showPassword ? 'Hide password' : 'Show password'"
          @click="showPassword = !showPassword"
        >
          <Icon :name="showPassword ? 'ph:eye-slash' : 'ph:eye'" class="w-4 h-4" />
        </button>

        <!-- Copy Button -->
        <button
          v-if="copyable && model"
          type="button"
          class="p-1 rounded-md text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors duration-150 outline-none focus-visible:ring-2 focus-visible:ring-gray-900/50"
          :aria-label="copied ? 'Copied!' : 'Copy to clipboard'"
          @click="handleCopy"
        >
          <Icon :name="copied ? 'ph:check' : 'ph:copy'" class="w-4 h-4" :class="copied && 'text-green-500'" />
        </button>

        <!-- Success Icon -->
        <Icon
          v-if="success && !error"
          name="ph:check-circle-fill"
          class="w-4 h-4 text-green-500 shrink-0"
        />

        <!-- Error Icon -->
        <Icon
          v-if="error && showErrorIcon"
          name="ph:warning-circle-fill"
          class="w-4 h-4 text-red-500 shrink-0"
        />
      </div>

      <!-- Right Icon (static) -->
      <div
        v-if="iconRight && !hasRightContent"
        :class="rightIconClasses"
      >
        <Icon :name="iconRight" class="w-4 h-4" />
      </div>

      <!-- Suffix -->
      <div
        v-if="suffix || $slots.suffix"
        :class="suffixClasses"
      >
        <slot name="suffix">{{ suffix }}</slot>
      </div>
    </div>

    <!-- Bottom Row: Error/Hint + Counter -->
    <div v-if="error || hint || showCounter" class="flex items-start justify-between gap-2">
      <!-- Error or Hint -->
      <Transition
        enter-active-class="transition-opacity duration-150 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-opacity duration-100 ease-out"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
        mode="out-in"
      >
        <p
          v-if="error"
          :id="`${inputId}-error`"
          class="text-xs text-red-400 flex items-center gap-1"
        >
          <Icon v-if="!showErrorIcon" name="ph:warning-circle" class="w-3 h-3 shrink-0" />
          {{ error }}
        </p>
        <p
          v-else-if="hint"
          :id="`${inputId}-hint`"
          class="text-xs text-gray-500"
        >
          {{ hint }}
        </p>
      </Transition>

      <!-- Character Counter -->
      <span
        v-if="showCounter && maxLength"
        :class="[
          'text-xs shrink-0',
          isOverLimit ? 'text-red-400' : 'text-gray-500',
        ]"
      >
        {{ model?.length || 0 }}/{{ maxLength }}
      </span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import Icon from '@/Components/shared/Icon.vue'

type InputSize = 'xs' | 'sm' | 'md' | 'lg' | 'xl'
type InputVariant = 'default' | 'filled' | 'outlined' | 'ghost' | 'underlined'
type InputType = 'text' | 'email' | 'password' | 'number' | 'search' | 'tel' | 'url' | 'date' | 'time' | 'datetime-local'

const props = withDefaults(defineProps<{
  // Value
  modelValue?: string | number
  name?: string

  // Type & Variant
  type?: InputType
  variant?: InputVariant
  size?: InputSize

  // Labels & Text
  label?: string
  placeholder?: string
  hint?: string
  error?: string
  prefix?: string
  suffix?: string

  // Icons
  iconLeft?: string
  iconRight?: string

  // States
  disabled?: boolean
  readonly?: boolean
  required?: boolean
  optional?: boolean
  loading?: boolean
  success?: boolean

  // Features
  clearable?: boolean
  clearLabel?: string
  copyable?: boolean
  showPasswordToggle?: boolean
  showCounter?: boolean
  showErrorIcon?: boolean
  floatingLabel?: boolean
  fullWidth?: boolean
  autoFocus?: boolean

  // Validation
  minLength?: number
  maxLength?: number
  min?: number | string
  max?: number | string
  step?: number | string
  pattern?: string

  // Textarea
  multiline?: boolean
  rows?: number
  autoResize?: boolean
  maxRows?: number

  // Behavior
  autocomplete?: string
  autofocus?: boolean
  selectOnFocus?: boolean
  debounce?: number
  validateOnBlur?: boolean
  validateOnInput?: boolean

  // Accessibility
  ariaLabel?: string
}>(), {
  modelValue: '',
  type: 'text',
  variant: 'default',
  size: 'md',
  disabled: false,
  readonly: false,
  required: false,
  optional: false,
  loading: false,
  success: false,
  clearable: false,
  clearLabel: 'Clear',
  copyable: false,
  showPasswordToggle: true,
  showCounter: false,
  showErrorIcon: true,
  floatingLabel: false,
  fullWidth: true,
  autoFocus: false,
  multiline: false,
  rows: 3,
  autoResize: false,
  maxRows: 10,
  selectOnFocus: false,
  debounce: 0,
  validateOnBlur: false,
  validateOnInput: false,
  autocomplete: 'off',
  autofocus: false,
})

const emit = defineEmits<{
  'update:modelValue': [value: string | number]
  'focus': [event: FocusEvent]
  'blur': [event: FocusEvent]
  'input': [event: Event]
  'enter': [event: KeyboardEvent]
  'escape': [event: KeyboardEvent]
  'clear': []
  'copy': []
}>()

const inputRef = ref<HTMLInputElement | HTMLTextAreaElement | null>(null)
const inputId = `input-${Math.random().toString(36).substring(2, 9)}`
const isFocused = ref(false)
const showPassword = ref(false)
const copied = ref(false)
const textareaHeight = ref('auto')

// Debounce timer
let debounceTimer: ReturnType<typeof setTimeout> | null = null

// Model
const model = computed({
  get: () => props.modelValue,
  set: (value) => {
    if (props.debounce > 0) {
      if (debounceTimer) clearTimeout(debounceTimer)
      debounceTimer = setTimeout(() => {
        emit('update:modelValue', value)
      }, props.debounce)
    } else {
      emit('update:modelValue', value)
    }
  },
})

// Computed type (for password toggle)
const computedType = computed(() => {
  if (props.type === 'password' && showPassword.value) {
    return 'text'
  }
  return props.type
})

// Is over character limit
const isOverLimit = computed(() => {
  if (!props.maxLength) return false
  return (model.value?.toString().length || 0) > props.maxLength
})

// Has right content (affects padding)
const hasRightContent = computed(() => {
  return (props.clearable && model.value) ||
    (props.type === 'password' && props.showPasswordToggle) ||
    props.copyable ||
    props.success ||
    (props.error && props.showErrorIcon)
})

// Described by (for accessibility)
const describedBy = computed(() => {
  const ids: string[] = []
  if (props.error) ids.push(`${inputId}-error`)
  else if (props.hint) ids.push(`${inputId}-hint`)
  return ids.length > 0 ? ids.join(' ') : undefined
})

// Size classes
const sizeClasses: Record<InputSize, { height: string; text: string; padding: string }> = {
  xs: { height: 'h-7', text: 'text-xs', padding: 'px-2' },
  sm: { height: 'h-8', text: 'text-sm', padding: 'px-2.5' },
  md: { height: 'h-10', text: 'text-sm', padding: 'px-3' },
  lg: { height: 'h-12', text: 'text-base', padding: 'px-4' },
  xl: { height: 'h-14', text: 'text-lg', padding: 'px-5' },
}

// Variant classes
const variantClasses = computed(() => {
  const base = 'transition-colors duration-150 ease-out'
  const variants: Record<InputVariant, string> = {
    default: `${base} bg-white border rounded-lg`,
    filled: `${base} bg-gray-50 border-0 rounded-lg hover:bg-gray-100`,
    outlined: `${base} bg-transparent border rounded-lg`,
    ghost: `${base} bg-transparent border-0 hover:bg-gray-50 rounded-lg`,
    underlined: `${base} bg-transparent border-0 border-b rounded-none`,
  }
  return variants[props.variant]
})

// Border color classes based on state
const borderStateClasses = computed(() => {
  if (props.error) {
    return 'border-red-300 focus-within:border-red-500 focus-within:ring-red-500/20'
  }
  if (props.success) {
    return 'border-green-300 focus-within:border-green-500 focus-within:ring-green-500/20'
  }
  if (isFocused.value) {
    return 'border-gray-900 focus-within:ring-gray-900/20'
  }
  return 'border-gray-300 hover:border-gray-400 focus-within:border-gray-900 focus-within:ring-gray-900/20'
})

// Wrapper classes
const wrapperClasses = computed(() => [
  'flex flex-col gap-1.5',
  props.fullWidth && 'w-full',
])

// Label classes
const labelClasses = computed(() => [
  'text-sm font-medium text-gray-900 flex items-center',
  props.disabled && 'opacity-50',
])

// Container classes
const containerClasses = computed(() => [
  'relative flex items-center gap-2',
  variantClasses.value,
  borderStateClasses.value,
  sizeClasses[props.size].padding,
  !props.multiline && sizeClasses[props.size].height,
  props.multiline && 'py-2',
  props.disabled && 'opacity-50 cursor-not-allowed',
  props.variant !== 'ghost' && props.variant !== 'underlined' && 'focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-offset-white',
])

// Input classes
const inputClasses = computed(() => [
  'w-full bg-transparent outline-none text-gray-900 placeholder:text-gray-400',
  sizeClasses[props.size].text,
  props.disabled && 'cursor-not-allowed',
  props.floatingLabel && 'peer placeholder-transparent',
  props.multiline && 'resize-none',
])

// Floating label classes
const floatingLabelClasses = computed(() => {
  const hasValue = model.value !== '' && model.value !== undefined && model.value !== null
  return [
    'absolute left-0 transition-all duration-200 pointer-events-none text-gray-500',
    // Positioned state (when focused or has value)
    (isFocused.value || hasValue)
      ? '-top-6 text-xs text-gray-900'
      : 'top-1/2 -translate-y-1/2 text-sm',
    // Peer focus (CSS fallback)
    'peer-focus:-top-6 peer-focus:text-xs peer-focus:text-gray-900 peer-focus:translate-y-0',
    'peer-[:not(:placeholder-shown)]:-top-6 peer-[:not(:placeholder-shown)]:text-xs peer-[:not(:placeholder-shown)]:translate-y-0',
  ]
})

// Prefix classes
const prefixClasses = computed(() => [
  'flex items-center text-gray-500 shrink-0',
  sizeClasses[props.size].text,
  props.variant === 'default' && '-ml-1 pr-2 border-r border-gray-200 mr-2',
])

// Suffix classes
const suffixClasses = computed(() => [
  'flex items-center text-gray-500 shrink-0',
  sizeClasses[props.size].text,
  props.variant === 'default' && '-mr-1 pl-2 border-l border-gray-200 ml-2',
])

// Left icon classes
const leftIconClasses = computed(() => [
  'flex items-center justify-center text-gray-500 shrink-0',
])

// Right icon classes
const rightIconClasses = computed(() => [
  'flex items-center justify-center text-gray-500 shrink-0',
])

// Event handlers
const handleInput = (event: Event) => {
  emit('input', event)

  // Auto-resize textarea
  if (props.multiline && props.autoResize && inputRef.value) {
    const el = inputRef.value as HTMLTextAreaElement
    el.style.height = 'auto'
    const scrollHeight = el.scrollHeight
    const lineHeight = parseInt(getComputedStyle(el).lineHeight) || 20
    const maxHeight = lineHeight * props.maxRows
    textareaHeight.value = `${Math.min(scrollHeight, maxHeight)}px`
  }
}

const handleFocus = (event: FocusEvent) => {
  isFocused.value = true
  emit('focus', event)

  if (props.selectOnFocus && inputRef.value) {
    inputRef.value.select()
  }
}

const handleBlur = (event: FocusEvent) => {
  isFocused.value = false
  emit('blur', event)
}

const handleKeydown = (event: KeyboardEvent) => {
  // Allow Ctrl/Cmd+A to select all
  if ((event.ctrlKey || event.metaKey) && event.key === 'a') {
    return
  }
}

const handleEnter = (event: KeyboardEvent) => {
  if (!props.multiline) {
    emit('enter', event)
  }
}

const handleEscape = (event: KeyboardEvent) => {
  emit('escape', event)
  inputRef.value?.blur()
}

const handleClear = () => {
  model.value = ''
  emit('clear')
  nextTick(() => {
    inputRef.value?.focus()
  })
}

const handleCopy = async () => {
  if (!model.value) return

  try {
    await navigator.clipboard.writeText(model.value.toString())
    copied.value = true
    emit('copy')
    setTimeout(() => {
      copied.value = false
    }, 2000)
  } catch {
    console.error('Failed to copy to clipboard')
  }
}

// Focus on mount if autoFocus
onMounted(() => {
  if (props.autoFocus || props.autofocus) {
    nextTick(() => {
      inputRef.value?.focus()
    })
  }
})

// Cleanup debounce timer
onUnmounted(() => {
  if (debounceTimer) {
    clearTimeout(debounceTimer)
  }
})

// Expose methods
defineExpose({
  focus: () => inputRef.value?.focus(),
  blur: () => inputRef.value?.blur(),
  select: () => inputRef.value?.select(),
  clear: handleClear,
  getInputElement: () => inputRef.value,
})
</script>
