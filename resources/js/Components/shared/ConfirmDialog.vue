<template>
  <Modal
    v-model:open="isOpen"
    :size="size"
    :close-on-escape="closable && !blocking"
  >
    <template #header>
      <slot name="header">
        <div class="flex flex-col items-center text-center">
          <!-- Icon -->
          <div :class="iconWrapperClasses">
            <Icon :name="computedIcon" :class="iconClasses" />
          </div>

          <!-- Title -->
          <h3 class="text-lg font-semibold text-neutral-900 dark:text-white mt-4">
            {{ title }}
          </h3>

          <!-- Description -->
          <p class="text-sm text-neutral-500 dark:text-neutral-300 mt-2 leading-relaxed">
            {{ description }}
          </p>
        </div>
      </slot>
    </template>

    <template #default>
      <!-- Custom Body Content -->
      <div v-if="$slots.default" class="mt-4">
        <slot />
      </div>

      <!-- Input Field (for confirm dialogs requiring input) -->
      <div v-if="requireInput" class="mt-4">
        <Input
          ref="inputRef"
          v-model="inputValue"
          :label="inputLabel"
          :placeholder="inputPlaceholder"
          :hint="inputHint"
          @enter="handleConfirm"
        />
      </div>

      <!-- Checkbox (for "Don't show again" etc.) -->
      <Checkbox
        v-if="showCheckbox"
        v-model:checked="checkboxChecked"
        :label="checkboxLabel"
        class="mt-4"
      />

      <!-- Warning Message -->
      <div
        v-if="warningMessage"
        class="mt-4 p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800"
      >
        <div class="flex items-start gap-2">
          <Icon name="ph:warning-fill" class="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
          <p class="text-sm text-amber-700 dark:text-amber-300">{{ warningMessage }}</p>
        </div>
      </div>
    </template>

    <template #footer>
      <div :class="actionsClasses">
        <!-- Cancel Button -->
        <Button
          v-if="!hideCancel"
          :variant="cancelVariant === 'ghost' ? 'ghost' : 'secondary'"
          :size="buttonSize"
          :disabled="loading"
          full-width
          @click="handleCancel"
        >
          <Icon v-if="cancelIcon" :name="cancelIcon" class="w-4 h-4 mr-1.5" />
          {{ cancelLabel }}
        </Button>

        <!-- Confirm Button -->
        <Button
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
        </Button>
      </div>

      <!-- Footer Slot -->
      <div v-if="$slots.footer" class="mt-4 pt-4 border-t border-neutral-200 dark:border-neutral-700">
        <slot name="footer" />
      </div>
    </template>
  </Modal>
</template>

<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue'
import Modal from './Modal.vue'
import Button from './Button.vue'
import Icon from './Icon.vue'
import Input from './Input.vue'
import Checkbox from './Checkbox.vue'

type DialogVariant = 'default' | 'danger' | 'warning' | 'success' | 'info'
type DialogSize = 'sm' | 'md' | 'lg'
type ButtonVariant = 'primary' | 'secondary' | 'ghost' | 'danger'

const props = withDefaults(defineProps<{
  open: boolean
  title: string
  description: string
  variant?: DialogVariant
  size?: DialogSize
  icon?: string
  confirmIcon?: string
  cancelIcon?: string
  confirmLabel?: string
  cancelLabel?: string
  confirmVariant?: ButtonVariant
  cancelVariant?: ButtonVariant
  hideCancel?: boolean
  loading?: boolean
  countdown?: number
  requireInput?: boolean
  inputLabel?: string
  inputPlaceholder?: string
  inputHint?: string
  expectedInput?: string
  showCheckbox?: boolean
  checkboxLabel?: string
  checkboxChecked?: boolean
  warningMessage?: string
  closable?: boolean
  blocking?: boolean
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
})

const emit = defineEmits<{
  'update:open': [value: boolean]
  'update:checkboxChecked': [value: boolean]
  'confirm': [data: { inputValue?: string; checkboxChecked?: boolean }]
  'cancel': []
}>()

const inputRef = ref<any>(null)
const inputValue = ref('')
const checkboxChecked = ref(props.checkboxChecked)

const isOpen = computed({
  get: () => props.open,
  set: (value) => {
    if (!value && props.blocking && props.loading) return
    emit('update:open', value)
  },
})

const buttonSize = computed(() => {
  if (props.size === 'sm') return 'sm'
  if (props.size === 'lg') return 'lg'
  return 'md'
})

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

const computedConfirmVariant = computed(() => {
  if (props.confirmVariant === 'danger' || props.variant === 'danger') return 'danger'
  return 'primary'
})

const canConfirm = computed(() => {
  if (props.countdown && props.countdown > 0) return false
  if (!props.requireInput) return true
  if (!props.expectedInput) return inputValue.value.length > 0
  return inputValue.value === props.expectedInput
})

const iconSizeClasses: Record<DialogSize, { container: string; icon: string }> = {
  sm: { container: 'w-10 h-10', icon: 'w-5 h-5' },
  md: { container: 'w-12 h-12', icon: 'w-6 h-6' },
  lg: { container: 'w-14 h-14', icon: 'w-7 h-7' },
}

const variantColors: Record<DialogVariant, { bg: string; text: string }> = {
  default: {
    bg: 'bg-neutral-100 dark:bg-neutral-700',
    text: 'text-neutral-600 dark:text-neutral-200',
  },
  danger: {
    bg: 'bg-red-50 dark:bg-red-900/20',
    text: 'text-red-600 dark:text-red-400',
  },
  warning: {
    bg: 'bg-amber-50 dark:bg-amber-900/20',
    text: 'text-amber-600 dark:text-amber-400',
  },
  success: {
    bg: 'bg-green-50 dark:bg-green-900/20',
    text: 'text-green-600 dark:text-green-400',
  },
  info: {
    bg: 'bg-blue-50 dark:bg-blue-900/20',
    text: 'text-blue-600 dark:text-blue-400',
  },
}

const iconWrapperClasses = computed(() => [
  'rounded-lg flex items-center justify-center',
  iconSizeClasses[props.size].container,
  variantColors[props.variant].bg,
])

const iconClasses = computed(() => [
  iconSizeClasses[props.size].icon,
  variantColors[props.variant].text,
])

const actionsClasses = computed(() => [
  'flex gap-3',
  props.hideCancel && 'justify-center',
])

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

watch(() => props.open, (isOpen) => {
  if (isOpen && props.requireInput) {
    nextTick(() => {
      inputRef.value?.focus()
    })
  }

  if (!isOpen) {
    inputValue.value = ''
  }
})

watch(() => props.checkboxChecked, (value) => {
  checkboxChecked.value = value
})

watch(checkboxChecked, (value) => {
  emit('update:checkboxChecked', value)
})
</script>
