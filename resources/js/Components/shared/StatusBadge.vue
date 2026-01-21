<template>
  <TooltipProvider v-if="showTooltip && tooltipContent" :delay-duration="tooltipDelay">
    <TooltipRoot>
      <TooltipTrigger as-child>
        <component
          :is="interactive ? 'button' : 'span'"
          :type="interactive ? 'button' : undefined"
          :class="badgeClasses"
          @click="handleClick"
        >
          <BadgeContent />
        </component>
      </TooltipTrigger>
      <TooltipPortal>
        <TooltipContent
          :side="tooltipSide"
          :side-offset="5"
          class="z-50 bg-white border border-gray-200 rounded-lg px-3 py-2.5 shadow-md max-w-xs"
        >
          <div class="space-y-1.5">
            <div class="flex items-center gap-2">
              <span
                :class="[
                  'w-2.5 h-2.5 rounded-full shrink-0',
                  dotColors[status],
                ]"
              />
              <p class="font-semibold text-gray-900 text-sm">{{ labels[status] }}</p>
            </div>
            <p v-if="statusDescriptions[status]" class="text-gray-500 text-xs leading-relaxed">
              {{ statusDescriptions[status] }}
            </p>
            <p v-if="lastActivity" class="text-gray-500 text-xs mt-2 pt-2 border-t border-gray-200 flex items-center gap-1.5">
              <Icon name="ph:clock" class="w-3 h-3 text-gray-400" />
              Last activity: {{ lastActivity }}
            </p>
            <p v-if="currentTask && status === 'working'" class="text-gray-500 text-xs mt-1.5 line-clamp-2 flex items-start gap-1.5">
              <Icon name="ph:arrow-right" class="w-3 h-3 text-gray-500 shrink-0 mt-0.5" />
              {{ currentTask }}
            </p>
          </div>
          <TooltipArrow class="fill-white" />
        </TooltipContent>
      </TooltipPortal>
    </TooltipRoot>
  </TooltipProvider>

  <component
    v-else
    :is="interactive ? 'button' : 'span'"
    :type="interactive ? 'button' : undefined"
    :class="badgeClasses"
    @click="handleClick"
  >
    <BadgeContent />
  </component>
</template>

<script setup lang="ts">
import { computed, h, resolveComponent, type VNode } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import {
  TooltipArrow,
  TooltipContent,
  TooltipPortal,
  TooltipProvider,
  TooltipRoot,
  TooltipTrigger,
} from 'reka-ui'
import type { AgentStatus } from '@/types'

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
  pill: true,
  loading: false,
})

const emit = defineEmits<{
  click: [status: AgentStatus]
  statusChange: [newStatus: AgentStatus]
}>()

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

// Variant + Status combinations - neutral palette
const variantStatusClasses: Record<StatusBadgeVariant, Record<AgentStatus, string>> = {
  filled: {
    idle: 'bg-gray-500 text-white',
    working: 'bg-gray-600 text-white',
    offline: 'bg-gray-400 text-gray-100',
  },
  soft: {
    idle: 'bg-gray-100 text-gray-600',
    working: 'bg-gray-100 text-gray-700',
    offline: 'bg-gray-100 text-gray-500',
  },
  outline: {
    idle: 'bg-transparent border border-gray-300 text-gray-600',
    working: 'bg-transparent border border-gray-400 text-gray-700',
    offline: 'bg-transparent border border-gray-300 text-gray-500',
  },
  ghost: {
    idle: 'bg-transparent text-gray-600 hover:bg-gray-100',
    working: 'bg-transparent text-gray-700 hover:bg-gray-100',
    offline: 'bg-transparent text-gray-500 hover:bg-gray-100',
  },
  'dot-only': {
    idle: '',
    working: '',
    offline: '',
  },
  minimal: {
    idle: 'text-gray-600',
    working: 'text-gray-700',
    offline: 'text-gray-500',
  },
}

// Dot colors - simple green for working, gray for others
const dotColors: Record<AgentStatus, string> = {
  idle: 'bg-gray-400',
  working: 'bg-green-500',
  offline: 'bg-gray-300',
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
    'inline-flex items-center font-medium transition-colors duration-150',
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

  // Interactive
  if (props.interactive && !props.disabled) {
    classes.push('cursor-pointer')
    classes.push('focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-900/50 focus-visible:ring-offset-1 focus-visible:ring-offset-white')
  }

  // Disabled
  if (props.disabled) {
    classes.push('opacity-50 cursor-not-allowed')
  }

  return classes
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
        'rounded-full shrink-0',
        dotSizes[props.size],
        dotColors[props.status],
      ]

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

      if (props.status === 'working') {
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
