<template>
  <div
    :class="[
      'rounded px-1.5 py-0.5 cursor-pointer truncate transition-colors',
      size === 'sm' ? 'text-xs' : 'text-sm',
      colorClasses
    ]"
    :title="event.title"
    @click="$emit('click')"
  >
    <span v-if="showTime" class="font-medium mr-1">{{ formattedTime }}</span>
    <span>{{ event.title }}</span>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { CalendarEvent } from '@/types'

const props = withDefaults(defineProps<{
  event: CalendarEvent
  size?: 'sm' | 'md'
  showTime?: boolean
}>(), {
  size: 'md',
  showTime: false,
})

defineEmits<{
  click: []
}>()

const colorClasses = computed(() => {
  const color = props.event.color || 'blue'
  const colors: Record<string, string> = {
    blue: 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 hover:bg-blue-200 dark:hover:bg-blue-900/60',
    green: 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300 hover:bg-green-200 dark:hover:bg-green-900/60',
    red: 'bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300 hover:bg-red-200 dark:hover:bg-red-900/60',
    purple: 'bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-300 hover:bg-purple-200 dark:hover:bg-purple-900/60',
    yellow: 'bg-yellow-100 dark:bg-yellow-900/40 text-yellow-700 dark:text-yellow-300 hover:bg-yellow-200 dark:hover:bg-yellow-900/60',
    orange: 'bg-orange-100 dark:bg-orange-900/40 text-orange-700 dark:text-orange-300 hover:bg-orange-200 dark:hover:bg-orange-900/60',
    pink: 'bg-pink-100 dark:bg-pink-900/40 text-pink-700 dark:text-pink-300 hover:bg-pink-200 dark:hover:bg-pink-900/60',
    indigo: 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-200 dark:hover:bg-indigo-900/60',
  }
  return colors[color] || colors.blue
})

const formattedTime = computed(() => {
  const date = new Date(props.event.startAt)
  return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })
})
</script>
