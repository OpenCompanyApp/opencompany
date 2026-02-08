<template>
  <Tooltip
    v-if="showTooltip && tooltipContent"
    :side="tooltipSide"
    :delay-duration="tooltipDelay"
  >
    <template #content>
      <div class="space-y-1.5 max-w-xs">
        <div class="flex items-center gap-2">
          <span
            :class="[
              'w-2.5 h-2.5 rounded-full shrink-0',
              dotColors[status],
            ]"
          />
          <p class="font-semibold text-neutral-900 dark:text-white text-sm">{{ labels[status] }}</p>
        </div>
        <p v-if="statusDescriptions[status]" class="text-neutral-500 dark:text-neutral-300 text-xs leading-relaxed">
          {{ statusDescriptions[status] }}
        </p>
        <p v-if="lastActivity" class="text-neutral-500 dark:text-neutral-300 text-xs mt-2 pt-2 border-t border-neutral-200 dark:border-neutral-700 flex items-center gap-1.5">
          <Icon name="ph:clock" class="w-3 h-3 text-neutral-400 dark:text-neutral-400" />
          Last activity: {{ lastActivity }}
        </p>
        <p v-if="currentTask && status === 'working'" class="text-neutral-500 dark:text-neutral-300 text-xs mt-1.5 line-clamp-2 flex items-start gap-1.5">
          <Icon name="ph:arrow-right" class="w-3 h-3 text-neutral-500 dark:text-neutral-300 shrink-0 mt-0.5" />
          {{ currentTask }}
        </p>
      </div>
    </template>

    <component
      :is="interactive ? 'button' : 'span'"
      :type="interactive ? 'button' : undefined"
      :class="badgeClasses"
      @click="handleClick"
    >
      <BadgeContent />
    </component>
  </Tooltip>

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
import { computed, h, type VNode } from 'vue'
import type { AgentStatus } from '@/types'
import Icon from '@/Components/shared/Icon.vue'
import Tooltip from '@/Components/shared/Tooltip.vue'

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
    idle: 'bg-neutral-500 text-white',
    working: 'bg-neutral-600 text-white',
    offline: 'bg-neutral-400 text-neutral-100',
    sleeping: 'bg-indigo-500 text-white',
    awaiting_approval: 'bg-amber-500 text-white',
    awaiting_delegation: 'bg-indigo-500 text-white',
  },
  soft: {
    idle: 'bg-neutral-100 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-200',
    working: 'bg-neutral-100 dark:bg-neutral-700 text-neutral-700 dark:text-neutral-200',
    offline: 'bg-neutral-100 dark:bg-neutral-700 text-neutral-500 dark:text-neutral-300',
    sleeping: 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-300',
    awaiting_approval: 'bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-300',
    awaiting_delegation: 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-300',
  },
  outline: {
    idle: 'bg-transparent border border-neutral-300 dark:border-neutral-600 text-neutral-600 dark:text-neutral-200',
    working: 'bg-transparent border border-neutral-400 dark:border-neutral-500 text-neutral-700 dark:text-neutral-200',
    offline: 'bg-transparent border border-neutral-300 dark:border-neutral-600 text-neutral-500 dark:text-neutral-300',
    sleeping: 'bg-transparent border border-indigo-300 dark:border-indigo-600 text-indigo-600 dark:text-indigo-300',
    awaiting_approval: 'bg-transparent border border-amber-300 dark:border-amber-600 text-amber-600 dark:text-amber-300',
    awaiting_delegation: 'bg-transparent border border-indigo-300 dark:border-indigo-600 text-indigo-600 dark:text-indigo-300',
  },
  ghost: {
    idle: 'bg-transparent text-neutral-600 dark:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-700',
    working: 'bg-transparent text-neutral-700 dark:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-700',
    offline: 'bg-transparent text-neutral-500 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-700',
    sleeping: 'bg-transparent text-indigo-600 dark:text-indigo-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/20',
    awaiting_approval: 'bg-transparent text-amber-600 dark:text-amber-300 hover:bg-amber-50 dark:hover:bg-amber-900/20',
    awaiting_delegation: 'bg-transparent text-indigo-600 dark:text-indigo-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/20',
  },
  'dot-only': {
    idle: '',
    working: '',
    offline: '',
    sleeping: '',
    awaiting_approval: '',
    awaiting_delegation: '',
  },
  minimal: {
    idle: 'text-neutral-600 dark:text-neutral-200',
    working: 'text-neutral-700 dark:text-neutral-200',
    offline: 'text-neutral-500 dark:text-neutral-300',
    sleeping: 'text-indigo-600 dark:text-indigo-300',
    awaiting_approval: 'text-amber-600 dark:text-amber-300',
    awaiting_delegation: 'text-indigo-600 dark:text-indigo-300',
  },
}

// Dot colors - simple green for working, gray for others
const dotColors: Record<AgentStatus, string> = {
  idle: 'bg-neutral-400',
  working: 'bg-green-500',
  offline: 'bg-neutral-300',
  sleeping: 'bg-indigo-400',
  awaiting_approval: 'bg-amber-500',
  awaiting_delegation: 'bg-indigo-500',
}

// Status icons
const statusIcons: Record<AgentStatus, string> = {
  idle: 'ph:pause-circle',
  working: 'ph:spinner',
  offline: 'ph:prohibit',
  sleeping: 'ph:moon',
  awaiting_approval: 'ph:shield-check',
  awaiting_delegation: 'ph:users-three',
}

// Labels
const labels: Record<AgentStatus, string> = {
  idle: 'Idle',
  working: 'Working',
  offline: 'Offline',
  sleeping: 'Sleeping',
  awaiting_approval: 'Awaiting Approval',
  awaiting_delegation: 'Delegating',
}

// Status descriptions for tooltip
const statusDescriptions: Record<AgentStatus, string> = {
  idle: 'Agent is available and waiting for tasks',
  working: 'Agent is actively processing a task',
  offline: 'Agent is currently unavailable',
  sleeping: 'Agent is sleeping and will wake at a scheduled time',
  awaiting_approval: 'Agent is waiting for human approval to proceed',
  awaiting_delegation: 'Agent is waiting for delegated subtasks to complete',
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
    classes.push('focus:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900/50 dark:focus-visible:ring-white/50 focus-visible:ring-offset-1 focus-visible:ring-offset-white dark:focus-visible:ring-offset-neutral-900')
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
      h('span', {
        class: ['i-ph:spinner animate-spin', iconSizes[props.size]],
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
        h('span', {
          class: [statusIconClasses[props.status], ...iconClasses],
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

// Status icon classes for iconify
const statusIconClasses: Record<AgentStatus, string> = {
  idle: 'i-ph:pause-circle',
  working: 'i-ph:spinner',
  offline: 'i-ph:prohibit',
  sleeping: 'i-ph:moon',
  awaiting_approval: 'i-ph:shield-check',
  awaiting_delegation: 'i-ph:users-three',
}
</script>
