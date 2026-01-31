<template>
  <Tooltip v-if="tooltip" :text="tooltip" :side="tooltipSide">
    <span
      :class="[
        // Base styles
        'inline-flex items-center gap-1.5 font-medium',
        // Size
        sizeClasses[size],
        // Shape
        shapeClasses[shape],
        // Variant colors
        variantClasses[variant][badgeStyle],
        // States
        interactive && !disabled && 'cursor-pointer',
        disabled && 'opacity-50 cursor-not-allowed',
        uppercase && 'uppercase tracking-wider',
      ]"
      @click="handleClick"
    >
      <!-- Loading spinner -->
      <Icon v-if="loading" name="ph:spinner" class="animate-spin" :class="iconSizeClasses[size]" />

      <!-- Dot indicator (left) -->
      <span
        v-if="!loading && dot && dotPosition === 'left'"
        :class="['rounded-full shrink-0', dotSizeClasses[size], dotColorClasses[variant]]"
      />

      <!-- Avatar -->
      <img
        v-if="!loading && avatar"
        :src="avatar"
        :alt="avatarFallback || ''"
        :class="['rounded-full object-cover shrink-0', avatarSizeClasses[size]]"
      />

      <!-- Left icon -->
      <Icon v-if="!loading && icon" :name="icon" :class="iconSizeClasses[size]" />

      <!-- Label or count -->
      <span v-if="!iconOnly" :class="truncate ? 'truncate' : ''">
        <slot>{{ label || displayCount }}</slot>
      </span>

      <!-- Right icon -->
      <Icon v-if="!loading && iconRight && !removable" :name="iconRight" :class="iconSizeClasses[size]" />

      <!-- Dot indicator (right) -->
      <span
        v-if="!loading && dot && dotPosition === 'right'"
        :class="['rounded-full shrink-0', dotSizeClasses[size], dotColorClasses[variant]]"
      />

      <!-- Remove button -->
      <button
        v-if="removable && !loading"
        type="button"
        class="ml-0.5 -mr-1 p-0.5 rounded-full transition-colors hover:bg-black/10 dark:hover:bg-white/10 focus:outline-none"
        aria-label="Remove"
        @click.stop="handleRemove"
      >
        <Icon name="ph:x" class="w-3 h-3" />
      </button>
    </span>
  </Tooltip>

  <!-- Badge without tooltip -->
  <span
    v-else
    :class="[
      // Base styles
      'inline-flex items-center gap-1.5 font-medium',
      // Size
      sizeClasses[size],
      // Shape
      shapeClasses[shape],
      // Variant colors
      variantClasses[variant][badgeStyle],
      // States
      interactive && !disabled && 'cursor-pointer',
      disabled && 'opacity-50 cursor-not-allowed',
      uppercase && 'uppercase tracking-wider',
    ]"
    @click="handleClick"
  >
    <!-- Loading spinner -->
    <Icon v-if="loading" name="ph:spinner" class="animate-spin" :class="iconSizeClasses[size]" />

    <!-- Dot indicator (left) -->
    <span
      v-if="!loading && dot && dotPosition === 'left'"
      :class="['rounded-full shrink-0', dotSizeClasses[size], dotColorClasses[variant]]"
    />

    <!-- Avatar -->
    <img
      v-if="!loading && avatar"
      :src="avatar"
      :alt="avatarFallback || ''"
      :class="['rounded-full object-cover shrink-0', avatarSizeClasses[size]]"
    />

    <!-- Left icon -->
    <Icon v-if="!loading && icon" :name="icon" :class="iconSizeClasses[size]" />

    <!-- Label or count -->
    <span v-if="!iconOnly" :class="truncate ? 'truncate' : ''">
      <slot>{{ label || displayCount }}</slot>
    </span>

    <!-- Right icon -->
    <Icon v-if="!loading && iconRight && !removable" :name="iconRight" :class="iconSizeClasses[size]" />

    <!-- Dot indicator (right) -->
    <span
      v-if="!loading && dot && dotPosition === 'right'"
      :class="['rounded-full shrink-0', dotSizeClasses[size], dotColorClasses[variant]]"
    />

    <!-- Remove button -->
    <button
      v-if="removable && !loading"
      type="button"
      class="ml-0.5 -mr-1 p-0.5 rounded-full transition-colors hover:bg-black/10 dark:hover:bg-white/10 focus:outline-none"
      aria-label="Remove"
      @click.stop="handleRemove"
    >
      <Icon name="ph:x" class="w-3 h-3" />
    </button>
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import Icon from './Icon.vue'
import Tooltip from './Tooltip.vue'

type BadgeVariant = 'default' | 'primary' | 'secondary' | 'success' | 'warning' | 'error' | 'info'
type BadgeStyle = 'soft' | 'solid' | 'outline' | 'ghost'
type BadgeSize = 'xs' | 'sm' | 'md' | 'lg' | 'xl'
type BadgeShape = 'rounded' | 'pill' | 'square'
type TooltipSide = 'top' | 'right' | 'bottom' | 'left'

const props = withDefaults(defineProps<{
  variant?: BadgeVariant
  badgeStyle?: BadgeStyle
  size?: BadgeSize
  shape?: BadgeShape
  label?: string
  count?: number
  maxCount?: number
  icon?: string
  iconRight?: string
  iconOnly?: boolean
  avatar?: string
  avatarFallback?: string
  dot?: boolean
  dotPosition?: 'left' | 'right'
  interactive?: boolean
  removable?: boolean
  disabled?: boolean
  loading?: boolean
  uppercase?: boolean
  truncate?: boolean
  tooltip?: string
  tooltipSide?: TooltipSide
}>(), {
  variant: 'default',
  badgeStyle: 'soft',
  size: 'sm',
  shape: 'rounded',
  dotPosition: 'left',
  interactive: false,
  removable: false,
  disabled: false,
  loading: false,
  uppercase: false,
  truncate: false,
  maxCount: 99,
  tooltipSide: 'top',
})

const emit = defineEmits<{
  click: [event: MouseEvent]
  remove: []
}>()

const displayCount = computed(() => {
  if (props.count === undefined) return null
  if (props.count > props.maxCount) return `${props.maxCount}+`
  return props.count.toString()
})

// Size classes
const sizeClasses: Record<BadgeSize, string> = {
  xs: 'text-[10px] px-1.5 py-0.5',
  sm: 'text-xs px-2 py-0.5',
  md: 'text-sm px-2.5 py-1',
  lg: 'text-sm px-3 py-1.5',
  xl: 'text-base px-3.5 py-1.5',
}

const iconSizeClasses: Record<BadgeSize, string> = {
  xs: 'w-3 h-3',
  sm: 'w-3.5 h-3.5',
  md: 'w-4 h-4',
  lg: 'w-4 h-4',
  xl: 'w-5 h-5',
}

// Shape classes
const shapeClasses: Record<BadgeShape, string> = {
  rounded: 'rounded-md',
  pill: 'rounded-full',
  square: 'rounded-sm',
}

// Variant colors for each style
const variantClasses: Record<BadgeVariant, Record<BadgeStyle, string>> = {
  default: {
    soft: 'bg-neutral-100 text-neutral-700 dark:bg-neutral-700 dark:text-neutral-200',
    solid: 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900',
    outline: 'border border-neutral-300 text-neutral-700 dark:border-neutral-600 dark:text-neutral-200',
    ghost: 'text-neutral-700 dark:text-neutral-200',
  },
  primary: {
    soft: 'bg-neutral-100 text-neutral-900 dark:bg-neutral-700 dark:text-white',
    solid: 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900',
    outline: 'border border-neutral-900 text-neutral-900 dark:border-white dark:text-white',
    ghost: 'text-neutral-900 dark:text-white',
  },
  secondary: {
    soft: 'bg-neutral-100 text-neutral-600 dark:bg-neutral-700 dark:text-neutral-300',
    solid: 'bg-neutral-500 text-white',
    outline: 'border border-neutral-400 text-neutral-600 dark:border-neutral-500 dark:text-neutral-300',
    ghost: 'text-neutral-600 dark:text-neutral-300',
  },
  success: {
    soft: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
    solid: 'bg-green-600 text-white',
    outline: 'border border-green-500 text-green-700 dark:text-green-400',
    ghost: 'text-green-700 dark:text-green-400',
  },
  warning: {
    soft: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    solid: 'bg-amber-500 text-white',
    outline: 'border border-amber-500 text-amber-700 dark:text-amber-400',
    ghost: 'text-amber-700 dark:text-amber-400',
  },
  error: {
    soft: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    solid: 'bg-red-600 text-white',
    outline: 'border border-red-500 text-red-700 dark:text-red-400',
    ghost: 'text-red-700 dark:text-red-400',
  },
  info: {
    soft: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    solid: 'bg-blue-600 text-white',
    outline: 'border border-blue-500 text-blue-700 dark:text-blue-400',
    ghost: 'text-blue-700 dark:text-blue-400',
  },
}

// Dot sizes
const dotSizeClasses: Record<BadgeSize, string> = {
  xs: 'w-1 h-1',
  sm: 'w-1.5 h-1.5',
  md: 'w-2 h-2',
  lg: 'w-2 h-2',
  xl: 'w-2.5 h-2.5',
}

// Avatar sizes
const avatarSizeClasses: Record<BadgeSize, string> = {
  xs: 'w-3 h-3 -ml-0.5',
  sm: 'w-4 h-4 -ml-0.5',
  md: 'w-5 h-5 -ml-1',
  lg: 'w-5 h-5 -ml-1',
  xl: 'w-6 h-6 -ml-1',
}

// Dot colors
const dotColorClasses: Record<BadgeVariant, string> = {
  default: 'bg-neutral-400 dark:bg-neutral-500',
  primary: 'bg-neutral-900 dark:bg-white',
  secondary: 'bg-neutral-400 dark:bg-neutral-500',
  success: 'bg-green-500',
  warning: 'bg-amber-500',
  error: 'bg-red-500',
  info: 'bg-blue-500',
}

const handleClick = (event: MouseEvent) => {
  if (props.disabled || props.loading) {
    event.preventDefault()
    return
  }
  emit('click', event)
}

const handleRemove = () => {
  if (props.disabled || props.loading) return
  emit('remove')
}
</script>
