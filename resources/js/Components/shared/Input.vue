<template>
  <div :class="fullWidth && 'w-full'">
    <!-- Label -->
    <label v-if="label" :for="inputId" class="block text-sm font-medium text-neutral-700 dark:text-neutral-200 mb-1.5">
      {{ label }}
      <span v-if="required" class="text-red-500 ml-0.5">*</span>
      <span v-if="optional" class="text-neutral-400 dark:text-neutral-400 ml-1 font-normal">(optional)</span>
    </label>

    <!-- Input wrapper -->
    <div class="relative">
      <!-- Prefix/Leading icon -->
      <div v-if="prefix || iconLeft || $slots.prefix" class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
        <slot name="prefix">
          <Icon v-if="iconLeft" :name="iconLeft" class="w-4 h-4 text-neutral-400" />
          <span v-else-if="prefix" class="text-neutral-500 dark:text-neutral-300 text-sm">{{ prefix }}</span>
        </slot>
      </div>

      <!-- Input element -->
      <input
        :id="inputId"
        ref="inputRef"
        :value="modelValue"
        :type="computedType"
        :name="name"
        :placeholder="placeholder"
        :disabled="disabled"
        :readonly="readonly"
        :required="required"
        :autocomplete="autocomplete"
        :minlength="minLength"
        :maxlength="maxLength"
        :min="min"
        :max="max"
        :step="step"
        :pattern="pattern"
        :class="[
          // Base styles
          'block w-full rounded-md border bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white',
          'placeholder:text-neutral-400 dark:placeholder:text-neutral-500',
          'transition-colors duration-150',
          // Focus styles
          'focus:outline-none focus:ring-2 focus:ring-neutral-400 focus:border-neutral-400',
          // Size
          sizeClasses[size],
          // Padding adjustments for icons
          (prefix || iconLeft || $slots.prefix) && 'pl-10',
          hasTrailingContent && 'pr-10',
          // States
          error
            ? 'border-red-500 focus:ring-red-500 focus:border-red-500'
            : success
              ? 'border-green-500 focus:ring-green-500 focus:border-green-500'
              : 'border-neutral-300 dark:border-neutral-600',
          disabled && 'opacity-50 cursor-not-allowed bg-neutral-50 dark:bg-neutral-900',
          readonly && 'bg-neutral-50 dark:bg-neutral-900',
        ]"
        @input="handleInput"
        @focus="handleFocus"
        @blur="handleBlur"
        @keydown.enter="handleEnter"
        @keydown.escape="handleEscape"
      />

      <!-- Trailing content -->
      <div v-if="hasTrailingContent" class="absolute inset-y-0 right-0 pr-3 flex items-center gap-1">
        <!-- Loading spinner -->
        <Icon v-if="loading" name="ph:spinner" class="w-4 h-4 text-neutral-400 animate-spin" />

        <!-- Clear button -->
        <button
          v-if="clearable && modelValue && !disabled && !readonly && !loading"
          type="button"
          class="p-0.5 rounded text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 transition-colors"
          @click="handleClear"
        >
          <Icon name="ph:x" class="w-4 h-4" />
        </button>

        <!-- Password toggle -->
        <button
          v-if="type === 'password' && showPasswordToggle && !loading"
          type="button"
          class="p-0.5 rounded text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 transition-colors"
          @click="showPassword = !showPassword"
        >
          <Icon :name="showPassword ? 'ph:eye-slash' : 'ph:eye'" class="w-4 h-4" />
        </button>

        <!-- Copy button -->
        <button
          v-if="copyable && modelValue && !loading"
          type="button"
          class="p-0.5 rounded transition-colors"
          :class="copied ? 'text-green-500' : 'text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300'"
          @click="handleCopy"
        >
          <Icon :name="copied ? 'ph:check' : 'ph:copy'" class="w-4 h-4" />
        </button>

        <!-- Success icon -->
        <Icon v-if="success && !error && !loading" name="ph:check-circle-fill" class="w-4 h-4 text-green-500" />

        <!-- Error icon -->
        <Icon v-if="error && showErrorIcon && !loading" name="ph:warning-circle-fill" class="w-4 h-4 text-red-500" />

        <!-- Suffix -->
        <slot name="suffix">
          <Icon v-if="iconRight && !loading" :name="iconRight" class="w-4 h-4 text-neutral-400" />
          <span v-else-if="suffix" class="text-neutral-500 dark:text-neutral-300 text-sm">{{ suffix }}</span>
        </slot>
      </div>
    </div>

    <!-- Hint/Error/Counter -->
    <div v-if="hint || error || (showCounter && maxLength)" class="mt-1.5 flex justify-between gap-2">
      <p v-if="error" class="text-sm text-red-500">{{ error }}</p>
      <p v-else-if="hint" class="text-sm text-neutral-500 dark:text-neutral-300">{{ hint }}</p>
      <span v-else />

      <span
        v-if="showCounter && maxLength"
        :class="[
          'text-xs',
          isOverLimit ? 'text-red-500' : 'text-neutral-500 dark:text-neutral-300',
        ]"
      >
        {{ modelValue?.toString().length || 0 }}/{{ maxLength }}
      </span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import Icon from './Icon.vue'

type InputSize = 'xs' | 'sm' | 'md' | 'lg' | 'xl'
type InputType = 'text' | 'email' | 'password' | 'number' | 'search' | 'tel' | 'url' | 'date' | 'time' | 'datetime-local'

const props = withDefaults(defineProps<{
  modelValue?: string | number
  name?: string
  type?: InputType
  size?: InputSize
  label?: string
  placeholder?: string
  hint?: string
  error?: string
  prefix?: string
  suffix?: string
  iconLeft?: string
  iconRight?: string
  disabled?: boolean
  readonly?: boolean
  required?: boolean
  optional?: boolean
  loading?: boolean
  success?: boolean
  clearable?: boolean
  copyable?: boolean
  showPasswordToggle?: boolean
  showCounter?: boolean
  showErrorIcon?: boolean
  fullWidth?: boolean
  autoFocus?: boolean
  autofocus?: boolean
  minLength?: number
  maxLength?: number
  min?: number | string
  max?: number | string
  step?: number | string
  pattern?: string
  autocomplete?: string
  selectOnFocus?: boolean
  debounce?: number
}>(), {
  modelValue: '',
  type: 'text',
  size: 'md',
  disabled: false,
  readonly: false,
  required: false,
  optional: false,
  loading: false,
  success: false,
  clearable: false,
  copyable: false,
  showPasswordToggle: true,
  showCounter: false,
  showErrorIcon: true,
  fullWidth: true,
  autoFocus: false,
  autofocus: false,
  selectOnFocus: false,
  debounce: 0,
  autocomplete: 'off',
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

const inputRef = ref<HTMLInputElement | null>(null)
const inputId = `input-${Math.random().toString(36).substring(2, 9)}`
const showPassword = ref(false)
const copied = ref(false)

let debounceTimer: ReturnType<typeof setTimeout> | null = null

const computedType = computed(() => {
  if (props.type === 'password' && showPassword.value) {
    return 'text'
  }
  return props.type
})

const sizeClasses: Record<InputSize, string> = {
  xs: 'h-7 px-2 text-xs',
  sm: 'h-8 px-3 text-sm',
  md: 'h-9 px-3 text-sm',
  lg: 'h-10 px-4 text-base',
  xl: 'h-11 px-4 text-base',
}

const hasTrailingContent = computed(() => {
  return props.loading ||
    (props.clearable && props.modelValue) ||
    (props.type === 'password' && props.showPasswordToggle) ||
    (props.copyable && props.modelValue) ||
    (props.success && !props.error) ||
    (props.error && props.showErrorIcon) ||
    props.iconRight ||
    props.suffix
})

const isOverLimit = computed(() => {
  if (!props.maxLength) return false
  return (props.modelValue?.toString().length || 0) > props.maxLength
})

const handleInput = (event: Event) => {
  const target = event.target as HTMLInputElement
  const value = props.type === 'number' ? Number(target.value) : target.value

  if (props.debounce > 0) {
    if (debounceTimer) clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => {
      emit('update:modelValue', value)
    }, props.debounce)
  } else {
    emit('update:modelValue', value)
  }
  emit('input', event)
}

const handleFocus = (event: FocusEvent) => {
  emit('focus', event)
  if (props.selectOnFocus) {
    inputRef.value?.select()
  }
}

const handleBlur = (event: FocusEvent) => {
  emit('blur', event)
}

const handleEnter = (event: KeyboardEvent) => {
  emit('enter', event)
}

const handleEscape = (event: KeyboardEvent) => {
  emit('escape', event)
  inputRef.value?.blur()
}

const handleClear = () => {
  emit('update:modelValue', '')
  emit('clear')
  nextTick(() => {
    inputRef.value?.focus()
  })
}

const handleCopy = async () => {
  if (!props.modelValue) return
  try {
    await navigator.clipboard.writeText(props.modelValue.toString())
    copied.value = true
    emit('copy')
    setTimeout(() => {
      copied.value = false
    }, 2000)
  } catch {
    console.error('Failed to copy to clipboard')
  }
}

onMounted(() => {
  if (props.autoFocus || props.autofocus) {
    nextTick(() => {
      inputRef.value?.focus()
    })
  }
})

onUnmounted(() => {
  if (debounceTimer) {
    clearTimeout(debounceTimer)
  }
})

defineExpose({
  focus: () => inputRef.value?.focus(),
  blur: () => inputRef.value?.blur(),
  select: () => inputRef.value?.select(),
  clear: handleClear,
  getInputElement: () => inputRef.value,
})
</script>
