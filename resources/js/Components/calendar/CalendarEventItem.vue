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
    <Icon v-if="event.recurrenceRule" name="ph:repeat" class="inline w-3 h-3 mr-0.5 opacity-60" />
    <span>{{ event.title }}</span>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import type { CalendarEvent } from '@/types'
import { getCalendarColorClasses } from './calendar-colors'

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

const colorClasses = computed(() => getCalendarColorClasses(props.event.color))

const formattedTime = computed(() => {
  const date = new Date(props.event.startAt)
  return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })
})
</script>
