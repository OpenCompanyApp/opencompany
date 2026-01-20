<template>
  <span
    :class="[
      'inline-flex items-center gap-1.5 rounded-full font-medium',
      sizeClasses[size],
      statusClasses[status]
    ]"
  >
    <span
      :class="[
        'rounded-full',
        dotSizes[size],
        dotColors[status],
        status === 'working' && 'animate-pulse'
      ]"
    />
    <span v-if="showLabel">{{ labels[status] }}</span>
  </span>
</template>

<script setup lang="ts">
import type { AgentStatus } from '~/types'

withDefaults(defineProps<{
  status: AgentStatus
  size?: 'xs' | 'sm' | 'md'
  showLabel?: boolean
}>(), {
  size: 'sm',
  showLabel: true,
})

const sizeClasses = {
  xs: 'px-1.5 py-0.5 text-[10px]',
  sm: 'px-2 py-0.5 text-xs',
  md: 'px-2.5 py-1 text-sm',
}

const dotSizes = {
  xs: 'w-1.5 h-1.5',
  sm: 'w-2 h-2',
  md: 'w-2.5 h-2.5',
}

const statusClasses = {
  idle: 'bg-gray-500/20 text-gray-400',
  working: 'bg-green-500/20 text-green-400',
  offline: 'bg-gray-500/20 text-gray-500',
}

const dotColors = {
  idle: 'bg-gray-400',
  working: 'bg-green-400',
  offline: 'bg-gray-500',
}

const labels = {
  idle: 'Idle',
  working: 'Working',
  offline: 'Offline',
}
</script>
