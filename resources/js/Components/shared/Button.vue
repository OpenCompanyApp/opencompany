<template>
  <Tooltip v-if="tooltip" :text="tooltip" :side="tooltipSide" :disabled="isDisabled">
    <template #content>
      {{ tooltip }}
      <span v-if="shortcut" class="ml-2 opacity-60 font-mono">{{ shortcut }}</span>
    </template>
    <component
      :is="componentType"
      ref="buttonRef"
      :type="componentType === 'button' ? type : undefined"
      :href="href"
      :to="to"
      :disabled="isDisabled"
      :class="buttonClasses"
      @click="handleClick"
    >
      <!-- Loading spinner -->
      <Icon v-if="loading" :name="loadingIcon" class="animate-spin" :class="iconSizeClasses[size]" />

      <!-- Left icon -->
      <Icon v-if="iconLeft && !loading && !iconOnly" :name="iconLeft" :class="iconSizeClasses[size]" />

      <!-- Icon only -->
      <Icon v-if="iconOnly && !loading" :name="iconLeft || iconRight || ''" :class="iconSizeClasses[size]" />

      <!-- Label -->
      <span v-if="!iconOnly">
        <slot>{{ loading && loadingText ? loadingText : undefined }}</slot>
      </span>

      <!-- Right icon -->
      <Icon v-if="iconRight && !loading && !iconOnly" :name="iconRight" :class="iconSizeClasses[size]" />
    </component>
  </Tooltip>

  <!-- Button without tooltip -->
  <component
    v-else
    :is="componentType"
    ref="buttonRef"
    :type="componentType === 'button' ? type : undefined"
    :href="href"
    :to="to"
    :disabled="isDisabled"
    :class="buttonClasses"
    @click="handleClick"
  >
    <!-- Loading spinner -->
    <Icon v-if="loading" :name="loadingIcon" class="animate-spin" :class="iconSizeClasses[size]" />

    <!-- Left icon -->
    <Icon v-if="iconLeft && !loading && !iconOnly" :name="iconLeft" :class="iconSizeClasses[size]" />

    <!-- Icon only -->
    <Icon v-if="iconOnly && !loading" :name="iconLeft || iconRight || ''" :class="iconSizeClasses[size]" />

    <!-- Label -->
    <span v-if="!iconOnly">
      <slot>{{ loading && loadingText ? loadingText : undefined }}</slot>
    </span>

    <!-- Right icon -->
    <Icon v-if="iconRight && !loading && !iconOnly" :name="iconRight" :class="iconSizeClasses[size]" />
  </component>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { RouterLink, type RouteLocationRaw } from 'vue-router'
import Icon from './Icon.vue'
import Tooltip from './Tooltip.vue'

type ButtonVariant = 'primary' | 'secondary' | 'ghost' | 'danger' | 'link' | 'outline' | 'success'
type ButtonSize = 'xs' | 'sm' | 'md' | 'lg' | 'xl'
type TooltipSide = 'top' | 'right' | 'bottom' | 'left'

const props = withDefaults(defineProps<{
  variant?: ButtonVariant
  size?: ButtonSize
  type?: 'button' | 'submit' | 'reset'
  disabled?: boolean
  loading?: boolean
  loadingText?: string
  loadingIcon?: string
  iconLeft?: string
  iconRight?: string
  iconOnly?: boolean
  fullWidth?: boolean
  rounded?: boolean
  square?: boolean
  as?: 'button' | 'a' | 'div' | 'span'
  href?: string
  to?: RouteLocationRaw
  tooltip?: string
  tooltipSide?: TooltipSide
  shortcut?: string
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
})

const emit = defineEmits<{
  click: [event: MouseEvent]
}>()

const buttonRef = ref<HTMLElement | null>(null)

const isDisabled = computed(() => props.disabled || props.loading)

const componentType = computed(() => {
  if (props.to) return RouterLink
  if (props.href) return 'a'
  return props.as
})

// Size classes
const sizeClasses: Record<ButtonSize, string> = {
  xs: 'h-7 px-2 text-xs gap-1',
  sm: 'h-8 px-3 text-sm gap-1.5',
  md: 'h-9 px-4 text-sm gap-2',
  lg: 'h-10 px-5 text-base gap-2',
  xl: 'h-11 px-6 text-base gap-2',
}

const iconOnlySizeClasses: Record<ButtonSize, string> = {
  xs: 'h-7 w-7',
  sm: 'h-8 w-8',
  md: 'h-9 w-9',
  lg: 'h-10 w-10',
  xl: 'h-11 w-11',
}

const iconSizeClasses: Record<ButtonSize, string> = {
  xs: 'w-3.5 h-3.5',
  sm: 'w-4 h-4',
  md: 'w-4 h-4',
  lg: 'w-5 h-5',
  xl: 'w-5 h-5',
}

// Variant classes
const variantClasses: Record<ButtonVariant, string> = {
  primary: 'bg-neutral-900 text-white hover:bg-neutral-800 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-100',
  secondary: 'border border-neutral-300 bg-white text-neutral-700 hover:bg-neutral-50 dark:border-neutral-600 dark:bg-neutral-800 dark:text-neutral-200 dark:hover:bg-neutral-700',
  ghost: 'text-neutral-700 hover:bg-neutral-100 dark:text-neutral-200 dark:hover:bg-neutral-800',
  danger: 'bg-red-600 text-white hover:bg-red-500 dark:bg-red-600 dark:hover:bg-red-500',
  link: 'text-neutral-900 underline-offset-4 hover:underline dark:text-white p-0 h-auto',
  outline: 'border border-neutral-300 text-neutral-700 hover:bg-neutral-50 dark:border-neutral-600 dark:text-neutral-200 dark:hover:bg-neutral-800',
  success: 'bg-green-600 text-white hover:bg-green-500',
}

const buttonClasses = computed(() => [
  // Base styles
  'inline-flex items-center justify-center font-medium transition-colors',
  'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-400 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-neutral-900',
  // Size
  props.iconOnly ? iconOnlySizeClasses[props.size] : sizeClasses[props.size],
  // Variant
  variantClasses[props.variant],
  // Shape
  props.rounded ? 'rounded-full' : props.square ? 'rounded-none' : 'rounded-md',
  // Full width
  props.fullWidth && 'w-full',
  // Disabled state
  isDisabled.value && 'opacity-50 cursor-not-allowed pointer-events-none',
])

const handleClick = (event: MouseEvent) => {
  if (isDisabled.value) {
    event.preventDefault()
    event.stopPropagation()
    return
  }
  emit('click', event)
}

defineExpose({
  focus: () => (buttonRef.value as HTMLElement)?.focus(),
  blur: () => (buttonRef.value as HTMLElement)?.blur(),
  el: buttonRef,
})
</script>
