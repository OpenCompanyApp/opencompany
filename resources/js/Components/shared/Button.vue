<template>
  <TooltipRoot v-if="tooltip" :delay-duration="300">
    <TooltipTrigger as-child>
      <component
        :is="computedElement"
        ref="buttonRef"
        :type="computedElement === 'button' ? type : undefined"
        :href="href"
        :to="to"
        :disabled="isDisabled"
        :aria-disabled="isDisabled"
        :aria-busy="loading"
        :aria-label="ariaLabel || (iconOnly ? String(tooltip) : undefined)"
        :class="buttonClasses"
        @click="handleClick"
      >
        <!-- Button content wrapper -->
        <span class="relative z-10 inline-flex items-center justify-center gap-2">
          <!-- Leading icon / spinner -->
          <Icon
            v-if="loading"
            :name="loadingIcon"
            class="animate-spin"
            :class="iconSizeClasses[size]"
          />
          <Icon
            v-else-if="iconLeft"
            :name="iconLeft"
            :class="iconSizeClasses[size]"
          />

          <!-- Button text -->
          <span
            v-if="!iconOnly"
            class="truncate"
          >
            <slot>{{ loading && loadingText ? loadingText : undefined }}</slot>
          </span>

          <!-- Trailing icon -->
          <Icon
            v-if="iconRight && !loading"
            :name="iconRight"
            :class="iconSizeClasses[size]"
          />

          <!-- Keyboard shortcut badge -->
          <kbd
            v-if="shortcut && !loading && !iconOnly"
            class="ml-1 px-1.5 py-0.5 text-[10px] font-mono rounded border opacity-60"
            :class="shortcutClasses"
          >
            {{ shortcut }}
          </kbd>

          <!-- Notification badge -->
          <span
            v-if="badge !== undefined"
            class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 flex items-center justify-center text-[10px] font-bold rounded-full"
            :class="badgeClasses"
          >
            {{ badge > 99 ? '99+' : badge }}
          </span>

          <!-- Dot indicator -->
          <span
            v-if="dot"
            class="absolute -top-0.5 -right-0.5 w-2.5 h-2.5 rounded-full border-2 border-white"
            :class="dotClasses"
          />
        </span>
      </component>
    </TooltipTrigger>
    <TooltipPortal>
      <TooltipContent
        class="z-50 px-3 py-1.5 text-xs font-medium bg-white border border-gray-200 rounded-lg shadow-md"
        :side="tooltipSide"
        :side-offset="5"
      >
        {{ tooltip }}
        <span v-if="shortcut" class="ml-2 opacity-60 font-mono">{{ shortcut }}</span>
        <TooltipArrow class="fill-white" />
      </TooltipContent>
    </TooltipPortal>
  </TooltipRoot>

  <!-- Button without tooltip -->
  <component
    v-else
    :is="computedElement"
    ref="buttonRef"
    :type="computedElement === 'button' ? type : undefined"
    :href="href"
    :to="to"
    :disabled="isDisabled"
    :aria-disabled="isDisabled"
    :aria-busy="loading"
    :aria-label="ariaLabel"
    :class="buttonClasses"
    @click="handleClick"
  >
    <!-- Button content wrapper -->
    <span class="relative z-10 inline-flex items-center justify-center gap-2">
      <!-- Leading icon / spinner -->
      <Icon
        v-if="loading"
        :name="loadingIcon"
        class="animate-spin"
        :class="iconSizeClasses[size]"
      />
      <Icon
        v-else-if="iconLeft"
        :name="iconLeft"
        :class="iconSizeClasses[size]"
      />

      <!-- Button text -->
      <span
        v-if="!iconOnly"
        class="truncate"
      >
        <slot>{{ loading && loadingText ? loadingText : undefined }}</slot>
      </span>

      <!-- Trailing icon -->
      <Icon
        v-if="iconRight && !loading"
        :name="iconRight"
        :class="iconSizeClasses[size]"
      />

      <!-- Keyboard shortcut badge -->
      <kbd
        v-if="shortcut && !loading && !iconOnly"
        class="ml-1 px-1.5 py-0.5 text-[10px] font-mono rounded border opacity-60"
        :class="shortcutClasses"
      >
        {{ shortcut }}
      </kbd>

      <!-- Notification badge -->
      <span
        v-if="badge !== undefined"
        class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 flex items-center justify-center text-[10px] font-bold rounded-full"
        :class="badgeClasses"
      >
        {{ badge > 99 ? '99+' : badge }}
      </span>

      <!-- Dot indicator -->
      <span
        v-if="dot"
        class="absolute -top-0.5 -right-0.5 w-2.5 h-2.5 rounded-full border-2 border-white"
        :class="dotClasses"
      />
    </span>
  </component>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import Icon from '@/Components/shared/Icon.vue'
import {
  TooltipArrow,
  TooltipContent,
  TooltipPortal,
  TooltipRoot,
  TooltipTrigger,
} from 'reka-ui'
import type { RouteLocationRaw } from 'vue-router'

// Types
type ButtonVariant = 'primary' | 'secondary' | 'ghost' | 'danger' | 'link' | 'outline' | 'success'
type ButtonSize = 'xs' | 'sm' | 'md' | 'lg' | 'xl'
type TooltipSide = 'top' | 'right' | 'bottom' | 'left'

// Props
const props = withDefaults(defineProps<{
  // Core props
  variant?: ButtonVariant
  size?: ButtonSize
  type?: 'button' | 'submit' | 'reset'
  disabled?: boolean
  loading?: boolean
  loadingText?: string
  loadingIcon?: string

  // Icons
  iconLeft?: string
  iconRight?: string
  iconOnly?: boolean

  // Layout
  fullWidth?: boolean
  rounded?: boolean
  square?: boolean

  // Element type
  as?: 'button' | 'a' | 'div' | 'span'
  href?: string
  to?: RouteLocationRaw

  // Additional features
  tooltip?: string
  tooltipSide?: TooltipSide
  shortcut?: string
  badge?: number
  dot?: boolean
  dotColor?: 'primary' | 'success' | 'warning' | 'danger'

  // Accessibility
  ariaLabel?: string
}>(), {
  variant: 'primary',
  size: 'md',
  type: 'button',
  disabled: false,
  loading: false,
  loadingIcon: 'ph:spinner',
  iconOnly: false,
  fullWidth: false,
  rounded: false,
  square: false,
  as: 'button',
  tooltipSide: 'top',
  dotColor: 'primary',
})

// Emits
const emit = defineEmits<{
  click: [event: MouseEvent]
}>()

// Refs
const buttonRef = ref<HTMLElement | null>(null)

// Computed
const isDisabled = computed(() => props.disabled || props.loading)

const computedElement = computed(() => {
  if (props.to) return Link
  if (props.href) return 'a'
  return props.as
})

const buttonClasses = computed(() => [
  // Base styles
  'relative inline-flex items-center justify-center font-medium',
  'transition-colors duration-150',
  'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-offset-white',

  // Disabled state
  isDisabled.value && 'opacity-50 cursor-not-allowed pointer-events-none',

  // Size classes
  sizeClasses[props.size],

  // Icon only adjustments
  props.iconOnly && iconOnlySizeClasses[props.size],

  // Variant classes
  variantClasses[props.variant],

  // Shape modifiers
  props.rounded && '!rounded-full',
  props.square && '!rounded-none',
  props.fullWidth && 'w-full',
])

const shortcutClasses = computed(() => {
  if (props.variant === 'primary') {
    return 'bg-white/10 border-white/20 text-white/80'
  }
  return 'bg-gray-100 border-gray-200 text-gray-500'
})

const badgeClasses = computed(() => {
  if (props.variant === 'primary') {
    return 'bg-white text-gray-900'
  }
  return 'bg-gray-600 text-white'
})

const dotClasses = computed(() => {
  const colors: Record<string, string> = {
    primary: 'bg-gray-900',
    success: 'bg-gray-500',
    warning: 'bg-gray-500',
    danger: 'bg-gray-500',
  }
  return colors[props.dotColor]
})

// Size classes
const sizeClasses: Record<ButtonSize, string> = {
  xs: 'h-6 px-2 text-xs rounded',
  sm: 'h-8 px-3 text-sm rounded-md',
  md: 'h-10 px-4 text-sm rounded-lg',
  lg: 'h-12 px-6 text-base rounded-lg',
  xl: 'h-14 px-8 text-lg rounded-xl',
}

const iconOnlySizeClasses: Record<ButtonSize, string> = {
  xs: 'h-6 w-6 p-0 rounded',
  sm: 'h-8 w-8 p-0 rounded-md',
  md: 'h-10 w-10 p-0 rounded-lg',
  lg: 'h-12 w-12 p-0 rounded-lg',
  xl: 'h-14 w-14 p-0 rounded-xl',
}

const iconSizeClasses: Record<ButtonSize, string> = {
  xs: 'w-3 h-3',
  sm: 'w-3.5 h-3.5',
  md: 'w-4 h-4',
  lg: 'w-5 h-5',
  xl: 'w-6 h-6',
}

// Variant classes - clean, minimal
const variantClasses: Record<ButtonVariant, string> = {
  primary: [
    'bg-gray-900 text-white',
    'hover:bg-gray-700',
    'focus-visible:ring-gray-900/50',
  ].join(' '),

  secondary: [
    'bg-white text-gray-700 border border-gray-300',
    'hover:bg-gray-50 hover:border-gray-400',
    'focus-visible:ring-gray-400/50',
  ].join(' '),

  ghost: [
    'text-gray-600 bg-transparent',
    'hover:bg-gray-100 hover:text-gray-900',
    'focus-visible:ring-gray-400/50',
  ].join(' '),

  danger: [
    'bg-white text-red-600 border border-red-200',
    'hover:bg-red-50 hover:border-red-300',
    'focus-visible:ring-red-500/50',
  ].join(' '),

  link: [
    'text-gray-900 bg-transparent underline-offset-4',
    'hover:underline',
    'focus-visible:ring-gray-900/50',
    'h-auto px-0 py-0',
  ].join(' '),

  outline: [
    'bg-transparent text-gray-900 border border-gray-900',
    'hover:bg-gray-900 hover:text-white',
    'focus-visible:ring-gray-900/50',
  ].join(' '),

  success: [
    'bg-green-600 text-white',
    'hover:bg-green-700',
    'focus-visible:ring-green-500/50',
  ].join(' '),
}

// Methods
const handleClick = (event: MouseEvent) => {
  if (isDisabled.value) {
    event.preventDefault()
    event.stopPropagation()
    return
  }
  emit('click', event)
}

// Expose for parent components
defineExpose({
  focus: () => buttonRef.value?.focus(),
  blur: () => buttonRef.value?.blur(),
  el: buttonRef,
})
</script>
