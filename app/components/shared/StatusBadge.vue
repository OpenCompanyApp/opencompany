<template>
  <TooltipProvider v-if="showTooltip && tooltipContent" :delay-duration="tooltipDelay">
    <TooltipRoot>
      <TooltipTrigger as-child>
        <component
          :is="interactive ? 'button' : 'span'"
          :type="interactive ? 'button' : undefined"
          :class="badgeClasses"
          :style="badgeStyles"
          @click="handleClick"
          @mouseenter="isHovered = true"
          @mouseleave="isHovered = false"
        >
          <BadgeContent />
        </component>
      </TooltipTrigger>
      <TooltipPortal>
        <TooltipContent
          :side="tooltipSide"
          :side-offset="5"
          class="z-50 bg-olympus-elevated border border-olympus-border rounded-lg px-3 py-2 shadow-xl max-w-xs animate-in fade-in-0 zoom-in-95 duration-150"
        >
          <div class="space-y-1">
            <div class="flex items-center gap-2">
              <span
                :class="[
                  'w-2 h-2 rounded-full shrink-0',
                  dotColors[status],
                  status === 'working' && 'animate-pulse',
                ]"
              />
              <p class="font-medium text-olympus-text text-sm">{{ labels[status] }}</p>
            </div>
            <p v-if="statusDescriptions[status]" class="text-olympus-text-muted text-xs">
              {{ statusDescriptions[status] }}
            </p>
            <p v-if="lastActivity" class="text-olympus-text-muted text-xs mt-1.5 pt-1.5 border-t border-olympus-border">
              Last activity: {{ lastActivity }}
            </p>
            <p v-if="currentTask && status === 'working'" class="text-olympus-text-muted text-xs mt-1 line-clamp-2">
              {{ currentTask }}
            </p>
          </div>
          <TooltipArrow class="fill-olympus-elevated" />
        </TooltipContent>
      </TooltipPortal>
    </TooltipRoot>
  </TooltipProvider>

  <component
    v-else
    :is="interactive ? 'button' : 'span'"
    :type="interactive ? 'button' : undefined"
    :class="badgeClasses"
    :style="badgeStyles"
    @click="handleClick"
    @mouseenter="isHovered = true"
    @mouseleave="isHovered = false"
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
import type { AgentStatus } from '~/types'

type StatusBadgeSize = 'xs' | 'sm' | 'md' | 'lg' | 'xl'
type StatusBadgeVariant = 'filled' | 'soft' | 'outline' | 'ghost' | 'dot-only' | 'minimal'
type TooltipSide = 'top' | 'right' | 'bottom' | 'left'

const props = withDefaults(defineProps<{
  status: AgentStatus
  size?: StatusBadgeSize
  variant?: StatusBadgeVariant
  showLabel?: boolean
  showDot?: boolean
  showIcon?: boolean

  // Interactive
  interactive?: boolean
  disabled?: boolean

  // Tooltip
  showTooltip?: boolean
  tooltipSide?: TooltipSide
  tooltipDelay?: number
  customTooltip?: string

  // Additional info
  lastActivity?: string
  currentTask?: string

  // Visual effects
  animated?: boolean
  glow?: boolean
  pulse?: boolean

  // Custom labels
  customLabel?: string

  // Pill style
  pill?: boolean

  // Loading
  loading?: boolean
}>(), {
  size: 'sm',
  variant: 'soft',
  showLabel: true,
  showDot: true,
  showIcon: false,
  interactive: false,
  disabled: false,
  showTooltip: true,
  tooltipSide: 'top',
  tooltipDelay: 300,
  animated: true,
  glow: false,
  pulse: false,
  pill: true,
  loading: false,
})

const emit = defineEmits<{
  click: [status: AgentStatus]
  statusChange: [newStatus: AgentStatus]
}>()

const isHovered = ref(false)

// Size classes
const sizeClasses: Record<StatusBadgeSize, string> = {
  xs: 'px-1.5 py-0.5 text-[10px] gap-1',
  sm: 'px-2 py-0.5 text-xs gap-1.5',
  md: 'px-2.5 py-1 text-sm gap-1.5',
  lg: 'px-3 py-1.5 text-sm gap-2',
  xl: 'px-4 py-2 text-base gap-2',
}

// Dot sizes
const dotSizes: Record<StatusBadgeSize, string> = {
  xs: 'w-1.5 h-1.5',
  sm: 'w-2 h-2',
  md: 'w-2.5 h-2.5',
  lg: 'w-3 h-3',
  xl: 'w-3.5 h-3.5',
}

// Icon sizes
const iconSizes: Record<StatusBadgeSize, string> = {
  xs: 'w-2.5 h-2.5',
  sm: 'w-3 h-3',
  md: 'w-3.5 h-3.5',
  lg: 'w-4 h-4',
  xl: 'w-5 h-5',
}

// Variant + Status combinations
const variantStatusClasses: Record<StatusBadgeVariant, Record<AgentStatus, string>> = {
  filled: {
    idle: 'bg-gray-500 text-white',
    working: 'bg-green-500 text-white',
    offline: 'bg-gray-600 text-gray-200',
  },
  soft: {
    idle: 'bg-gray-500/20 text-gray-400',
    working: 'bg-green-500/20 text-green-400',
    offline: 'bg-gray-500/20 text-gray-500',
  },
  outline: {
    idle: 'bg-transparent border border-gray-500/50 text-gray-400',
    working: 'bg-transparent border border-green-500/50 text-green-400',
    offline: 'bg-transparent border border-gray-600/50 text-gray-500',
  },
  ghost: {
    idle: 'bg-transparent text-gray-400 hover:bg-gray-500/10',
    working: 'bg-transparent text-green-400 hover:bg-green-500/10',
    offline: 'bg-transparent text-gray-500 hover:bg-gray-500/10',
  },
  'dot-only': {
    idle: '',
    working: '',
    offline: '',
  },
  minimal: {
    idle: 'text-gray-400',
    working: 'text-green-400',
    offline: 'text-gray-500',
  },
}

// Dot colors
const dotColors: Record<AgentStatus, string> = {
  idle: 'bg-gray-400',
  working: 'bg-green-400',
  offline: 'bg-gray-500',
}

// Glow colors
const glowColors: Record<AgentStatus, string> = {
  idle: 'shadow-gray-400/30',
  working: 'shadow-green-400/40',
  offline: '',
}

// Status icons
const statusIcons: Record<AgentStatus, string> = {
  idle: 'ph:pause-circle',
  working: 'ph:spinner',
  offline: 'ph:prohibit',
}

// Labels
const labels: Record<AgentStatus, string> = {
  idle: 'Idle',
  working: 'Working',
  offline: 'Offline',
}

// Status descriptions for tooltip
const statusDescriptions: Record<AgentStatus, string> = {
  idle: 'Agent is available and waiting for tasks',
  working: 'Agent is actively processing a task',
  offline: 'Agent is currently unavailable',
}

// Tooltip content
const tooltipContent = computed(() => {
  if (props.customTooltip) return true
  return props.showTooltip
})

// Badge classes
const badgeClasses = computed(() => {
  const classes: string[] = [
    'inline-flex items-center font-medium transition-all duration-200',
  ]

  // Size
  if (props.variant !== 'dot-only') {
    classes.push(sizeClasses[props.size])
  }

  // Variant + Status
  classes.push(variantStatusClasses[props.variant][props.status])

  // Pill or rounded
  if (props.pill && props.variant !== 'dot-only') {
    classes.push('rounded-full')
  } else if (props.variant !== 'dot-only') {
    classes.push('rounded-md')
  }

  // Glow
  if (props.glow && props.status !== 'offline') {
    classes.push('shadow-lg', glowColors[props.status])
  }

  // Interactive
  if (props.interactive && !props.disabled) {
    classes.push('cursor-pointer')
    classes.push('focus:outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50 focus-visible:ring-offset-1 focus-visible:ring-offset-olympus-bg')
  }

  // Disabled
  if (props.disabled) {
    classes.push('opacity-50 cursor-not-allowed')
  }

  // Hover scale
  if (props.interactive && !props.disabled && isHovered.value) {
    classes.push('scale-105')
  }

  return classes
})

// Badge styles
const badgeStyles = computed(() => {
  const styles: Record<string, string> = {}
  return styles
})

// Handle click
const handleClick = () => {
  if (props.interactive && !props.disabled) {
    emit('click', props.status)
  }
}

// Display label
const displayLabel = computed(() => {
  return props.customLabel || labels[props.status]
})

// Badge Content Component
const BadgeContent = () => {
  const elements: VNode[] = []

  // Loading spinner
  if (props.loading) {
    elements.push(
      h(resolveComponent('Icon'), {
        name: 'ph:spinner',
        class: ['animate-spin', iconSizes[props.size]],
      })
    )
  } else {
    // Status dot
    if (props.showDot) {
      const dotClasses = [
        'rounded-full shrink-0 transition-all duration-200',
        dotSizes[props.size],
        dotColors[props.status],
      ]

      // Pulse animation for working
      if (props.animated && props.status === 'working') {
        dotClasses.push('animate-pulse')
      }

      // Custom pulse
      if (props.pulse) {
        dotClasses.push('animate-pulse')
      }

      // Glow on dot
      if (props.glow && props.status === 'working') {
        dotClasses.push('shadow-sm', 'shadow-green-400/50')
      }

      elements.push(
        h('span', { class: dotClasses })
      )
    }

    // Status icon (alternative to dot)
    if (props.showIcon && !props.showDot) {
      const iconClasses = [
        iconSizes[props.size],
        'shrink-0',
      ]

      if (props.animated && props.status === 'working') {
        iconClasses.push('animate-spin')
      }

      elements.push(
        h(resolveComponent('Icon'), {
          name: statusIcons[props.status],
          class: iconClasses,
        })
      )
    }

    // Label
    if (props.showLabel && props.variant !== 'dot-only') {
      elements.push(
        h('span', { class: 'truncate' }, displayLabel.value)
      )
    }
  }

  return elements
}
</script>

<style scoped>
/* Enhanced pulse animation for working status */
@keyframes status-pulse {
  0%, 100% {
    opacity: 1;
    transform: scale(1);
  }
  50% {
    opacity: 0.7;
    transform: scale(1.1);
  }
}

.status-pulse {
  animation: status-pulse 2s ease-in-out infinite;
}

/* Ring animation */
@keyframes status-ring {
  0% {
    box-shadow: 0 0 0 0 currentColor;
    opacity: 0.4;
  }
  100% {
    box-shadow: 0 0 0 6px currentColor;
    opacity: 0;
  }
}

.status-ring::before {
  content: '';
  position: absolute;
  inset: 0;
  border-radius: inherit;
  animation: status-ring 1.5s ease-out infinite;
}
</style>
