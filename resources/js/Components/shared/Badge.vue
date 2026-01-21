<template>
  <TooltipProvider v-if="tooltip" :delay-duration="200">
    <TooltipRoot>
      <TooltipTrigger as-child>
        <component
          :is="computedComponent"
          ref="badgeRef"
          :type="computedType"
          :href="href"
          :to="to"
          :disabled="disabled || loading"
          :aria-disabled="disabled || loading"
          :aria-busy="loading"
          :aria-label="ariaLabel"
          :class="badgeClasses"
          @click="handleClick"
        >
          <BadgeContent />
        </component>
      </TooltipTrigger>
      <TooltipPortal>
        <TooltipContent
          :side="tooltipSide"
          :side-offset="4"
          class="z-50 px-2.5 py-1.5 text-xs font-medium bg-white border border-gray-200 rounded-lg shadow-md"
        >
          {{ tooltip }}
          <TooltipArrow class="fill-white" />
        </TooltipContent>
      </TooltipPortal>
    </TooltipRoot>
  </TooltipProvider>

  <component
    v-else
    :is="computedComponent"
    ref="badgeRef"
    :type="computedType"
    :href="href"
    :to="to"
    :disabled="disabled || loading"
    :aria-disabled="disabled || loading"
    :aria-busy="loading"
    :aria-label="ariaLabel"
    :class="badgeClasses"
    @click="handleClick"
  >
    <BadgeContent />
  </component>
</template>

<script setup lang="ts">
import { ref, computed, h, resolveComponent } from 'vue'
import { Link } from '@inertiajs/vue3'
import {
  TooltipArrow,
  TooltipContent,
  TooltipPortal,
  TooltipProvider,
  TooltipRoot,
  TooltipTrigger,
} from 'reka-ui'
import type { RouteLocationRaw } from 'vue-router'

type BadgeVariant = 'default' | 'primary' | 'secondary' | 'success' | 'warning' | 'error' | 'info'
type BadgeStyle = 'soft' | 'solid' | 'outline' | 'ghost'
type BadgeSize = 'xs' | 'sm' | 'md' | 'lg' | 'xl'
type BadgeShape = 'rounded' | 'pill' | 'square'
type TooltipSide = 'top' | 'right' | 'bottom' | 'left'

const props = withDefaults(defineProps<{
  // Variant & Style
  variant?: BadgeVariant
  badgeStyle?: BadgeStyle
  size?: BadgeSize
  shape?: BadgeShape

  // Content
  label?: string
  count?: number
  maxCount?: number

  // Icons
  icon?: string
  iconRight?: string
  iconOnly?: boolean

  // Avatar
  avatar?: string
  avatarFallback?: string

  // Dot indicator
  dot?: boolean
  dotPosition?: 'left' | 'right'

  // Interactive
  interactive?: boolean
  removable?: boolean
  disabled?: boolean
  loading?: boolean

  // Link/Button
  as?: 'span' | 'button' | 'a' | 'div'
  href?: string
  to?: RouteLocationRaw

  // Visual effects
  uppercase?: boolean
  truncate?: boolean
  maxWidth?: string

  // Tooltip
  tooltip?: string
  tooltipSide?: TooltipSide

  // Accessibility
  ariaLabel?: string
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
  as: 'span',
  uppercase: false,
  truncate: false,
  maxCount: 99,
  tooltipSide: 'top',
})

const emit = defineEmits<{
  click: [event: MouseEvent]
  remove: []
}>()

const badgeRef = ref<HTMLElement | null>(null)

// Computed component
const computedComponent = computed(() => {
  if (props.to) {
    return Link
  }
  if (props.href) {
    return 'a'
  }
  if (props.interactive || props.removable) {
    return 'button'
  }
  return props.as
})

const computedType = computed(() => {
  if ((props.interactive || props.removable) && !props.to && !props.href) {
    return 'button'
  }
  return undefined
})

// Display count
const displayCount = computed(() => {
  if (props.count === undefined) return null
  if (props.count > props.maxCount) return `${props.maxCount}+`
  return props.count.toString()
})

// Size classes
const sizeClasses: Record<BadgeSize, string> = {
  xs: 'h-4 px-1 text-[10px] gap-0.5',
  sm: 'h-5 px-1.5 text-xs gap-1',
  md: 'h-6 px-2 text-xs gap-1.5',
  lg: 'h-7 px-2.5 text-sm gap-1.5',
  xl: 'h-8 px-3 text-sm gap-2',
}

// Icon size classes
const iconSizeClasses: Record<BadgeSize, string> = {
  xs: 'w-2.5 h-2.5',
  sm: 'w-3 h-3',
  md: 'w-3.5 h-3.5',
  lg: 'w-4 h-4',
  xl: 'w-4.5 h-4.5',
}

// Avatar size classes
const avatarSizeClasses: Record<BadgeSize, string> = {
  xs: 'w-3 h-3 -ml-0.5',
  sm: 'w-4 h-4 -ml-0.5',
  md: 'w-5 h-5 -ml-1',
  lg: 'w-5 h-5 -ml-1',
  xl: 'w-6 h-6 -ml-1',
}

// Dot size classes
const dotSizeClasses: Record<BadgeSize, string> = {
  xs: 'w-1 h-1',
  sm: 'w-1.5 h-1.5',
  md: 'w-2 h-2',
  lg: 'w-2 h-2',
  xl: 'w-2.5 h-2.5',
}

// Shape classes
const shapeClasses: Record<BadgeShape, string> = {
  rounded: 'rounded-md',
  pill: 'rounded-full',
  square: 'rounded-sm',
}

// Soft variant classes - neutral palette
const softVariantClasses: Record<BadgeVariant, string> = {
  default: 'bg-gray-100 text-gray-700 border border-gray-200',
  primary: 'bg-gray-100 text-gray-900 border border-gray-200',
  secondary: 'bg-gray-100 text-gray-600 border border-gray-200',
  success: 'bg-gray-100 text-gray-700 border border-gray-200',
  warning: 'bg-gray-100 text-gray-700 border border-gray-200',
  error: 'bg-gray-100 text-gray-700 border border-gray-200',
  info: 'bg-gray-100 text-gray-700 border border-gray-200',
}

// Solid variant classes
const solidVariantClasses: Record<BadgeVariant, string> = {
  default: 'bg-gray-200 text-gray-800 border border-gray-300',
  primary: 'bg-gray-900 text-white border border-gray-900',
  secondary: 'bg-gray-500 text-white border border-gray-500',
  success: 'bg-gray-600 text-white border border-gray-600',
  warning: 'bg-gray-600 text-white border border-gray-600',
  error: 'bg-gray-600 text-white border border-gray-600',
  info: 'bg-gray-600 text-white border border-gray-600',
}

// Outline variant classes
const outlineVariantClasses: Record<BadgeVariant, string> = {
  default: 'bg-transparent text-gray-600 border border-gray-300',
  primary: 'bg-transparent text-gray-900 border border-gray-900',
  secondary: 'bg-transparent text-gray-600 border border-gray-400',
  success: 'bg-transparent text-gray-600 border border-gray-400',
  warning: 'bg-transparent text-gray-600 border border-gray-400',
  error: 'bg-transparent text-gray-600 border border-gray-400',
  info: 'bg-transparent text-gray-600 border border-gray-400',
}

// Ghost variant classes
const ghostVariantClasses: Record<BadgeVariant, string> = {
  default: 'bg-transparent text-gray-600 border-0',
  primary: 'bg-transparent text-gray-900 border-0',
  secondary: 'bg-transparent text-gray-500 border-0',
  success: 'bg-transparent text-gray-600 border-0',
  warning: 'bg-transparent text-gray-600 border-0',
  error: 'bg-transparent text-gray-600 border-0',
  info: 'bg-transparent text-gray-600 border-0',
}

// Interactive hover classes
const interactiveHoverClasses: Record<BadgeVariant, string> = {
  default: 'hover:bg-gray-200 hover:border-gray-300',
  primary: 'hover:bg-gray-200 hover:border-gray-300',
  secondary: 'hover:bg-gray-200 hover:border-gray-300',
  success: 'hover:bg-gray-200 hover:border-gray-300',
  warning: 'hover:bg-gray-200 hover:border-gray-300',
  error: 'hover:bg-gray-200 hover:border-gray-300',
  info: 'hover:bg-gray-200 hover:border-gray-300',
}

// Dot color classes - neutral
const dotColorClasses: Record<BadgeVariant, string> = {
  default: 'bg-gray-400',
  primary: 'bg-gray-900',
  secondary: 'bg-gray-400',
  success: 'bg-gray-500',
  warning: 'bg-gray-500',
  error: 'bg-gray-500',
  info: 'bg-gray-500',
}

// Get variant classes based on style
const getVariantClasses = computed(() => {
  const styleMap: Record<BadgeStyle, Record<BadgeVariant, string>> = {
    soft: softVariantClasses,
    solid: solidVariantClasses,
    outline: outlineVariantClasses,
    ghost: ghostVariantClasses,
  }
  return styleMap[props.badgeStyle][props.variant]
})

// Badge classes
const badgeClasses = computed(() => [
  // Base
  'inline-flex items-center justify-center font-medium transition-colors duration-150 whitespace-nowrap',

  // Size
  sizeClasses[props.size],

  // Shape
  shapeClasses[props.shape],

  // Variant & Style
  getVariantClasses.value,

  // Interactive
  (props.interactive || props.removable || props.href || props.to) && !props.disabled && [
    'cursor-pointer outline-none',
    'focus-visible:ring-2 focus-visible:ring-gray-900/50 focus-visible:ring-offset-1 focus-visible:ring-offset-white',
    interactiveHoverClasses[props.variant],
  ],

  // Disabled
  props.disabled && 'opacity-50 cursor-not-allowed pointer-events-none',

  // Loading
  props.loading && 'cursor-wait',

  // Uppercase
  props.uppercase && 'uppercase tracking-wider',

  // Truncate
  props.truncate && 'overflow-hidden',

  // Icon only padding adjustment
  props.iconOnly && 'px-0 aspect-square',
])

// Max width style
const maxWidthStyle = computed(() => {
  if (props.maxWidth) {
    return { maxWidth: props.maxWidth }
  }
  return {}
})

// Handle click
const handleClick = (event: MouseEvent) => {
  if (props.disabled || props.loading) {
    event.preventDefault()
    return
  }
  emit('click', event)
}

// Handle remove
const handleRemove = (event: MouseEvent) => {
  event.stopPropagation()
  if (props.disabled || props.loading) return
  emit('remove')
}

// Badge Content Component
const BadgeContent = () => {
  return h('span', {
    class: 'inline-flex items-center gap-inherit',
    style: maxWidthStyle.value,
  }, [
    // Loading spinner
    props.loading && h(resolveComponent('Icon'), {
      name: 'ph:spinner',
      class: ['animate-spin', iconSizeClasses[props.size]],
    }),

    // Dot indicator (left)
    !props.loading && props.dot && props.dotPosition === 'left' && h('span', {
      class: [
        'rounded-full shrink-0',
        dotSizeClasses[props.size],
        dotColorClasses[props.variant],
      ],
    }),

    // Avatar
    !props.loading && props.avatar && h('img', {
      src: props.avatar,
      alt: props.avatarFallback || '',
      class: ['rounded-full object-cover shrink-0', avatarSizeClasses[props.size]],
    }),

    // Avatar fallback (if no avatar image)
    !props.loading && !props.avatar && props.avatarFallback && h('span', {
      class: [
        'flex items-center justify-center rounded-full bg-gray-200 text-gray-600 shrink-0 text-[0.6em] font-semibold uppercase',
        avatarSizeClasses[props.size],
      ],
    }, props.avatarFallback.charAt(0)),

    // Left icon
    !props.loading && props.icon && h(resolveComponent('Icon'), {
      name: props.icon,
      class: [iconSizeClasses[props.size], 'shrink-0'],
    }),

    // Label or count or slot
    !props.iconOnly && h('span', {
      class: props.truncate ? 'truncate' : undefined,
    }, [
      // Default slot or label or count
      props.label || displayCount.value || h('slot'),
    ]),

    // Right icon
    !props.loading && props.iconRight && !props.removable && h(resolveComponent('Icon'), {
      name: props.iconRight,
      class: [iconSizeClasses[props.size], 'shrink-0'],
    }),

    // Dot indicator (right)
    !props.loading && props.dot && props.dotPosition === 'right' && h('span', {
      class: [
        'rounded-full shrink-0',
        dotSizeClasses[props.size],
        dotColorClasses[props.variant],
      ],
    }),

    // Remove button
    props.removable && !props.loading && h('button', {
      type: 'button',
      class: [
        'ml-0.5 -mr-1 p-0.5 rounded-full transition-colors duration-150',
        'hover:bg-gray-300',
        'focus:outline-none focus-visible:ring-1 focus-visible:ring-current',
      ],
      'aria-label': 'Remove',
      onClick: handleRemove,
    }, [
      h(resolveComponent('Icon'), {
        name: 'ph:x',
        class: iconSizeClasses[props.size],
      }),
    ]),
  ])
}

// Expose methods
defineExpose({
  focus: () => badgeRef.value?.focus(),
  blur: () => badgeRef.value?.blur(),
})
</script>

<style scoped>
/* Inherit gap from parent */
.gap-inherit {
  gap: inherit;
}
</style>
