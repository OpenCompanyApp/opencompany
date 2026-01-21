<template>
  <AlertDialogRoot :open="open" @update:open="handleOpenChange">
    <AlertDialogPortal>
      <!-- Overlay -->
      <AlertDialogOverlay :class="overlayClasses" />

      <!-- Content -->
      <AlertDialogContent :class="contentClasses">
        <!-- Close Button (for non-blocking dialogs) -->
        <button
          v-if="closable && !blocking"
          type="button"
          class="absolute top-4 right-4 p-1.5 rounded-md text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors duration-150"
          @click="handleCancel"
        >
          <Icon name="ph:x" class="w-4 h-4" />
        </button>

        <!-- Custom Header Slot -->
        <slot name="header">
          <!-- Icon -->
          <div :class="iconContainerClasses">
            <div :class="iconWrapperClasses">
              <Icon :name="computedIcon" :class="iconClasses" />
            </div>
          </div>

          <!-- Title -->
          <AlertDialogTitle :class="titleClasses">
            {{ title }}
          </AlertDialogTitle>

          <!-- Description -->
          <AlertDialogDescription :class="descriptionClasses">
            {{ description }}
          </AlertDialogDescription>
        </slot>

        <!-- Custom Body Content -->
        <div v-if="$slots.default" class="mt-4">
          <slot />
        </div>

        <!-- Input Field (for confirm dialogs requiring input) -->
        <div v-if="requireInput" class="mt-4">
          <label :for="inputId" class="block text-sm font-medium text-gray-900 mb-1.5">
            {{ inputLabel }}
          </label>
          <input
            :id="inputId"
            ref="inputRef"
            v-model="inputValue"
            type="text"
            :placeholder="inputPlaceholder"
            :class="inputClasses"
            @keydown.enter="handleConfirm"
          />
          <p v-if="inputHint" class="mt-1 text-xs text-gray-500">
            {{ inputHint }}
          </p>
        </div>

        <!-- Checkbox (for "Don't show again" etc.) -->
        <label
          v-if="showCheckbox"
          class="flex items-center gap-2 mt-4 cursor-pointer group"
        >
          <div
            :class="[
              'w-4 h-4 rounded border-2 flex items-center justify-center transition-colors duration-150',
              checkboxChecked
                ? 'bg-gray-900 border-gray-900'
                : 'border-gray-300 group-hover:border-gray-400',
            ]"
            @click="checkboxChecked = !checkboxChecked"
          >
            <Icon
              v-if="checkboxChecked"
              name="ph:check-bold"
              class="w-2.5 h-2.5 text-white"
            />
          </div>
          <span class="text-sm text-gray-500 group-hover:text-gray-700 transition-colors duration-150">{{ checkboxLabel }}</span>
        </label>

        <!-- Warning Message -->
        <div
          v-if="warningMessage"
          class="mt-4 p-3 rounded-lg bg-amber-50 border border-amber-200"
        >
          <div class="flex items-start gap-2">
            <Icon name="ph:warning-fill" class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
            <p class="text-sm text-amber-700">{{ warningMessage }}</p>
          </div>
        </div>

        <!-- Actions -->
        <div :class="actionsClasses">
          <!-- Cancel Button -->
          <AlertDialogCancel v-if="!hideCancel" as-child>
            <SharedButton
              :variant="cancelVariant"
              :size="buttonSize"
              :disabled="loading"
              full-width
              @click="handleCancel"
            >
              <Icon v-if="cancelIcon" :name="cancelIcon" class="w-4 h-4 mr-1.5" />
              {{ cancelLabel }}
            </SharedButton>
          </AlertDialogCancel>

          <!-- Confirm Button -->
          <AlertDialogAction as-child>
            <SharedButton
              :variant="computedConfirmVariant"
              :size="buttonSize"
              :loading="loading"
              :disabled="!canConfirm"
              full-width
              @click="handleConfirm"
            >
              <Icon v-if="confirmIcon" :name="confirmIcon" class="w-4 h-4 mr-1.5" />
              {{ confirmLabel }}
              <span v-if="countdown && countdown > 0" class="ml-1 opacity-70">
                ({{ countdown }})
              </span>
            </SharedButton>
          </AlertDialogAction>
        </div>

        <!-- Footer Slot -->
        <div v-if="$slots.footer" class="mt-4 pt-4 border-t border-gray-200">
          <slot name="footer" />
        </div>
      </AlertDialogContent>
    </AlertDialogPortal>
  </AlertDialogRoot>
</template>

<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import SharedButton from '@/Components/shared/Button.vue'
import {
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogOverlay,
  AlertDialogPortal,
  AlertDialogRoot,
  AlertDialogTitle,
} from 'reka-ui'

type DialogVariant = 'default' | 'danger' | 'warning' | 'success' | 'info'
type DialogSize = 'sm' | 'md' | 'lg'
type ButtonVariant = 'primary' | 'secondary' | 'ghost' | 'danger'

const props = withDefaults(defineProps<{
  // Core
  open: boolean
  title: string
  description: string

  // Variant & Size
  variant?: DialogVariant
  size?: DialogSize

  // Icons
  icon?: string
  confirmIcon?: string
  cancelIcon?: string

  // Labels
  confirmLabel?: string
  cancelLabel?: string

  // Buttons
  confirmVariant?: ButtonVariant
  cancelVariant?: ButtonVariant
  hideCancel?: boolean

  // Loading
  loading?: boolean

  // Countdown (for destructive actions)
  countdown?: number

  // Input confirmation
  requireInput?: boolean
  inputLabel?: string
  inputPlaceholder?: string
  inputHint?: string
  expectedInput?: string

  // Checkbox
  showCheckbox?: boolean
  checkboxLabel?: string
  checkboxChecked?: boolean

  // Warning
  warningMessage?: string

  // Behavior
  closable?: boolean
  blocking?: boolean
  animated?: boolean
}>(), {
  variant: 'default',
  size: 'md',
  confirmLabel: 'Confirm',
  cancelLabel: 'Cancel',
  cancelVariant: 'secondary',
  hideCancel: false,
  loading: false,
  requireInput: false,
  inputLabel: 'Type to confirm',
  inputPlaceholder: 'Type here...',
  showCheckbox: false,
  checkboxLabel: "Don't show this again",
  checkboxChecked: false,
  closable: true,
  blocking: false,
  animated: true,
})

const emit = defineEmits<{
  'update:open': [value: boolean]
  'update:checkboxChecked': [value: boolean]
  'confirm': [data: { inputValue?: string; checkboxChecked?: boolean }]
  'cancel': []
}>()

const inputRef = ref<HTMLInputElement | null>(null)
const inputValue = ref('')
const checkboxChecked = ref(props.checkboxChecked)

// Generate unique ID for input
const inputId = computed(() => `confirm-input-${Math.random().toString(36).substring(2, 9)}`)

// Button size mapping
const buttonSize = computed(() => {
  if (props.size === 'sm') return 'sm'
  if (props.size === 'lg') return 'lg'
  return 'md'
})

// Computed icon based on variant
const computedIcon = computed(() => {
  if (props.icon) return props.icon

  const variantIcons: Record<DialogVariant, string> = {
    default: 'ph:question-fill',
    danger: 'ph:warning-circle-fill',
    warning: 'ph:warning-fill',
    success: 'ph:check-circle-fill',
    info: 'ph:info-fill',
  }

  return variantIcons[props.variant]
})

// Computed confirm variant
const computedConfirmVariant = computed((): ButtonVariant => {
  if (props.confirmVariant) return props.confirmVariant
  return props.variant === 'danger' ? 'danger' : 'primary'
})

// Can confirm (considering input validation)
const canConfirm = computed(() => {
  if (props.countdown && props.countdown > 0) return false
  if (!props.requireInput) return true
  if (!props.expectedInput) return inputValue.value.length > 0
  return inputValue.value === props.expectedInput
})

// Size classes
const sizeClasses: Record<DialogSize, string> = {
  sm: 'max-w-sm',
  md: 'max-w-md',
  lg: 'max-w-lg',
}

const iconSizeClasses: Record<DialogSize, { container: string; icon: string }> = {
  sm: { container: 'w-10 h-10', icon: 'w-5 h-5' },
  md: { container: 'w-12 h-12', icon: 'w-6 h-6' },
  lg: { container: 'w-14 h-14', icon: 'w-7 h-7' },
}

// Variant colors - muted, neutral styling
const variantColors: Record<DialogVariant, { bg: string; text: string }> = {
  default: {
    bg: 'bg-gray-100',
    text: 'text-gray-600',
  },
  danger: {
    bg: 'bg-red-50',
    text: 'text-red-600',
  },
  warning: {
    bg: 'bg-amber-50',
    text: 'text-amber-600',
  },
  success: {
    bg: 'bg-green-50',
    text: 'text-green-600',
  },
  info: {
    bg: 'bg-blue-50',
    text: 'text-blue-600',
  },
}

// Overlay classes
const overlayClasses = computed(() => [
  'fixed inset-0 z-50',
  'bg-black/50',
  'data-[state=open]:animate-in data-[state=closed]:animate-out',
  'data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0',
  'duration-150',
])

// Content classes
const contentClasses = computed(() => [
  'fixed left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 z-50',
  'w-full p-6',
  'bg-white border border-gray-200 rounded-lg shadow-lg',
  sizeClasses[props.size],
  'data-[state=open]:animate-in data-[state=closed]:animate-out',
  'data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0',
  'duration-150 ease-out',
])

// Icon container classes
const iconContainerClasses = computed(() => [
  'relative flex items-center justify-center mx-auto mb-4',
])

// Icon wrapper classes
const iconWrapperClasses = computed(() => [
  'rounded-lg flex items-center justify-center',
  iconSizeClasses[props.size].container,
  variantColors[props.variant].bg,
])

// Icon classes
const iconClasses = computed(() => [
  iconSizeClasses[props.size].icon,
  variantColors[props.variant].text,
])

// Title classes
const titleClasses = computed(() => [
  'text-lg font-semibold text-gray-900 text-center',
])

// Description classes
const descriptionClasses = computed(() => [
  'text-sm text-gray-500 text-center mt-2 leading-relaxed',
])

// Input classes
const inputClasses = computed(() => [
  'w-full px-3 py-2 rounded-md',
  'bg-white border border-gray-300',
  'text-gray-900 placeholder:text-gray-400',
  'focus:outline-none focus:border-gray-400 focus:ring-1 focus:ring-gray-400',
  'transition-colors duration-150',
])

// Actions classes
const actionsClasses = computed(() => [
  'flex gap-3 mt-6',
  props.hideCancel && 'justify-center',
])

// Handlers
const handleOpenChange = (value: boolean) => {
  if (!value && props.blocking && props.loading) return
  emit('update:open', value)
}

const handleConfirm = () => {
  if (!canConfirm.value) return

  emit('confirm', {
    inputValue: props.requireInput ? inputValue.value : undefined,
    checkboxChecked: props.showCheckbox ? checkboxChecked.value : undefined,
  })
}

const handleCancel = () => {
  if (props.loading) return
  emit('cancel')
  emit('update:open', false)
}

// Focus input when dialog opens
watch(() => props.open, (isOpen) => {
  if (isOpen && props.requireInput) {
    nextTick(() => {
      inputRef.value?.focus()
    })
  }

  // Reset state when closing
  if (!isOpen) {
    inputValue.value = ''
  }
})

// Sync checkbox state
watch(() => props.checkboxChecked, (value) => {
  checkboxChecked.value = value
})

watch(checkboxChecked, (value) => {
  emit('update:checkboxChecked', value)
})
</script>

<style scoped>
/* Minimal animations */
</style>
