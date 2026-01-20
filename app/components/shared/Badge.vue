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
          class="z-50 px-2 py-1 text-xs font-medium bg-olympus-elevated border border-olympus-border rounded-md shadow-lg animate-in fade-in-0 zoom-in-95 duration-150"
        >
          {{ tooltip }}
          <TooltipArrow class="fill-olympus-elevated" />
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
import {
  TooltipArrow,
  TooltipContent,
  TooltipPortal,
  TooltipProvider,
  TooltipRoot,
  TooltipTrigger,
} from 'reka-ui'
import type { RouteLocationRaw } from 'vue-router'

type BadgeVariant = 'default' | 'primary' | 'secondary' | 'success' | 'warning' | 'error' | 'info' | 'purple' | 'pink' | 'cyan' | 'orange'
type BadgeStyle = 'soft' | 'solid' | 'outline' | 'ghost' | 'gradient'
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
  dotPulse?: boolean

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
  glow?: boolean
  pulse?: boolean
  animate?: boolean
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
  dotPulse: false,
  interactive: false,
  removable: false,
  disabled: false,
  loading: false,
  as: 'span',
  glow: false,
  pulse: false,
  animate: false,
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
    return resolveComponent('NuxtLink')
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

// Soft variant classes (default badge style)
const softVariantClasses: Record<BadgeVariant, string> = {
  default: 'bg-olympus-surface text-olympus-text-muted border border-olympus-border',
  primary: 'bg-olympus-primary/15 text-olympus-primary border border-olympus-primary/20',
  secondary: 'bg-olympus-text-muted/15 text-olympus-text-muted border border-olympus-text-muted/20',
  success: 'bg-green-500/15 text-green-400 border border-green-500/20',
  warning: 'bg-amber-500/15 text-amber-400 border border-amber-500/20',
  error: 'bg-red-500/15 text-red-400 border border-red-500/20',
  info: 'bg-blue-500/15 text-blue-400 border border-blue-500/20',
  purple: 'bg-purple-500/15 text-purple-400 border border-purple-500/20',
  pink: 'bg-pink-500/15 text-pink-400 border border-pink-500/20',
  cyan: 'bg-cyan-500/15 text-cyan-400 border border-cyan-500/20',
  orange: 'bg-orange-500/15 text-orange-400 border border-orange-500/20',
}

// Solid variant classes
const solidVariantClasses: Record<BadgeVariant, string> = {
  default: 'bg-olympus-surface text-olympus-text border border-olympus-border',
  primary: 'bg-olympus-primary text-white border border-olympus-primary',
  secondary: 'bg-olympus-text-muted text-white border border-olympus-text-muted',
  success: 'bg-green-500 text-white border border-green-500',
  warning: 'bg-amber-500 text-white border border-amber-500',
  error: 'bg-red-500 text-white border border-red-500',
  info: 'bg-blue-500 text-white border border-blue-500',
  purple: 'bg-purple-500 text-white border border-purple-500',
  pink: 'bg-pink-500 text-white border border-pink-500',
  cyan: 'bg-cyan-500 text-white border border-cyan-500',
  orange: 'bg-orange-500 text-white border border-orange-500',
}

// Outline variant classes
const outlineVariantClasses: Record<BadgeVariant, string> = {
  default: 'bg-transparent text-olympus-text-muted border-2 border-olympus-border',
  primary: 'bg-transparent text-olympus-primary border-2 border-olympus-primary',
  secondary: 'bg-transparent text-olympus-text-muted border-2 border-olympus-text-muted',
  success: 'bg-transparent text-green-400 border-2 border-green-500',
  warning: 'bg-transparent text-amber-400 border-2 border-amber-500',
  error: 'bg-transparent text-red-400 border-2 border-red-500',
  info: 'bg-transparent text-blue-400 border-2 border-blue-500',
  purple: 'bg-transparent text-purple-400 border-2 border-purple-500',
  pink: 'bg-transparent text-pink-400 border-2 border-pink-500',
  cyan: 'bg-transparent text-cyan-400 border-2 border-cyan-500',
  orange: 'bg-transparent text-orange-400 border-2 border-orange-500',
}

// Ghost variant classes
const ghostVariantClasses: Record<BadgeVariant, string> = {
  default: 'bg-transparent text-olympus-text-muted border-0',
  primary: 'bg-transparent text-olympus-primary border-0',
  secondary: 'bg-transparent text-olympus-text-muted border-0',
  success: 'bg-transparent text-green-400 border-0',
  warning: 'bg-transparent text-amber-400 border-0',
  error: 'bg-transparent text-red-400 border-0',
  info: 'bg-transparent text-blue-400 border-0',
  purple: 'bg-transparent text-purple-400 border-0',
  pink: 'bg-transparent text-pink-400 border-0',
  cyan: 'bg-transparent text-cyan-400 border-0',
  orange: 'bg-transparent text-orange-400 border-0',
}

// Gradient variant classes
const gradientVariantClasses: Record<BadgeVariant, string> = {
  default: 'bg-gradient-to-r from-olympus-surface to-olympus-elevated text-olympus-text border border-olympus-border',
  primary: 'bg-gradient-to-r from-olympus-primary to-purple-500 text-white border-0',
  secondary: 'bg-gradient-to-r from-gray-500 to-gray-600 text-white border-0',
  success: 'bg-gradient-to-r from-green-500 to-emerald-500 text-white border-0',
  warning: 'bg-gradient-to-r from-amber-500 to-orange-500 text-white border-0',
  error: 'bg-gradient-to-r from-red-500 to-rose-500 text-white border-0',
  info: 'bg-gradient-to-r from-blue-500 to-cyan-500 text-white border-0',
  purple: 'bg-gradient-to-r from-purple-500 to-violet-500 text-white border-0',
  pink: 'bg-gradient-to-r from-pink-500 to-rose-500 text-white border-0',
  cyan: 'bg-gradient-to-r from-cyan-500 to-teal-500 text-white border-0',
  orange: 'bg-gradient-to-r from-orange-500 to-amber-500 text-white border-0',
}

// Interactive hover classes for soft style
const interactiveHoverClasses: Record<BadgeVariant, string> = {
  default: 'hover:bg-olympus-elevated hover:border-olympus-border-hover',
  primary: 'hover:bg-olympus-primary/25 hover:border-olympus-primary/30',
  secondary: 'hover:bg-olympus-text-muted/25 hover:border-olympus-text-muted/30',
  success: 'hover:bg-green-500/25 hover:border-green-500/30',
  warning: 'hover:bg-amber-500/25 hover:border-amber-500/30',
  error: 'hover:bg-red-500/25 hover:border-red-500/30',
  info: 'hover:bg-blue-500/25 hover:border-blue-500/30',
  purple: 'hover:bg-purple-500/25 hover:border-purple-500/30',
  pink: 'hover:bg-pink-500/25 hover:border-pink-500/30',
  cyan: 'hover:bg-cyan-500/25 hover:border-cyan-500/30',
  orange: 'hover:bg-orange-500/25 hover:border-orange-500/30',
}

// Dot color classes
const dotColorClasses: Record<BadgeVariant, string> = {
  default: 'bg-olympus-text-muted',
  primary: 'bg-olympus-primary',
  secondary: 'bg-olympus-text-muted',
  success: 'bg-green-500',
  warning: 'bg-amber-500',
  error: 'bg-red-500',
  info: 'bg-blue-500',
  purple: 'bg-purple-500',
  pink: 'bg-pink-500',
  cyan: 'bg-cyan-500',
  orange: 'bg-orange-500',
}

// Glow classes
const glowClasses: Record<BadgeVariant, string> = {
  default: 'shadow-[0_0_10px_rgba(255,255,255,0.1)]',
  primary: 'shadow-[0_0_10px_rgba(var(--color-olympus-primary),0.4)]',
  secondary: 'shadow-[0_0_10px_rgba(128,128,128,0.3)]',
  success: 'shadow-[0_0_10px_rgba(34,197,94,0.4)]',
  warning: 'shadow-[0_0_10px_rgba(245,158,11,0.4)]',
  error: 'shadow-[0_0_10px_rgba(239,68,68,0.4)]',
  info: 'shadow-[0_0_10px_rgba(59,130,246,0.4)]',
  purple: 'shadow-[0_0_10px_rgba(168,85,247,0.4)]',
  pink: 'shadow-[0_0_10px_rgba(236,72,153,0.4)]',
  cyan: 'shadow-[0_0_10px_rgba(6,182,212,0.4)]',
  orange: 'shadow-[0_0_10px_rgba(249,115,22,0.4)]',
}

// Get variant classes based on style
const getVariantClasses = computed(() => {
  const styleMap: Record<BadgeStyle, Record<BadgeVariant, string>> = {
    soft: softVariantClasses,
    solid: solidVariantClasses,
    outline: outlineVariantClasses,
    ghost: ghostVariantClasses,
    gradient: gradientVariantClasses,
  }
  return styleMap[props.badgeStyle][props.variant]
})

// Badge classes
const badgeClasses = computed(() => [
  // Base
  'inline-flex items-center justify-center font-medium transition-all duration-150 whitespace-nowrap',

  // Size
  sizeClasses[props.size],

  // Shape
  shapeClasses[props.shape],

  // Variant & Style
  getVariantClasses.value,

  // Interactive
  (props.interactive || props.removable || props.href || props.to) && !props.disabled && [
    'cursor-pointer outline-none',
    'focus-visible:ring-2 focus-visible:ring-olympus-primary/50 focus-visible:ring-offset-1 focus-visible:ring-offset-olympus-bg',
    interactiveHoverClasses[props.variant],
    'active:scale-95',
  ],

  // Disabled
  props.disabled && 'opacity-50 cursor-not-allowed pointer-events-none',

  // Loading
  props.loading && 'cursor-wait',

  // Uppercase
  props.uppercase && 'uppercase tracking-wider',

  // Truncate
  props.truncate && 'overflow-hidden',

  // Glow
  props.glow && glowClasses[props.variant],

  // Pulse animation
  props.pulse && 'animate-pulse',

  // Custom animation
  props.animate && 'badge-animate',

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
        props.dotPulse && 'animate-pulse',
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
        'flex items-center justify-center rounded-full bg-olympus-surface text-olympus-text-muted shrink-0 text-[0.6em] font-semibold uppercase',
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
        props.dotPulse && 'animate-pulse',
      ],
    }),

    // Remove button
    props.removable && !props.loading && h('button', {
      type: 'button',
      class: [
        'ml-0.5 -mr-1 p-0.5 rounded-full transition-colors duration-150',
        'hover:bg-black/10 dark:hover:bg-white/10',
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
@keyframes badge-bounce {
  0%, 100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(-2px);
  }
}

.badge-animate {
  animation: badge-bounce 1s ease-in-out infinite;
}

/* Inherit gap from parent */
.gap-inherit {
  gap: inherit;
}
</style>
