<template>
  <button
    :class="[
      'w-full flex items-center gap-2.5 px-3 py-2 rounded-lg transition-all duration-150 text-left group outline-none',
      'focus-visible:ring-2 focus-visible:ring-olympus-primary/50 focus-visible:ring-offset-2 focus-visible:ring-offset-olympus-sidebar',
      selected
        ? 'bg-olympus-primary-muted text-olympus-text border-l-2 border-olympus-primary pl-[10px] shadow-sm shadow-olympus-primary/10'
        : 'hover:bg-olympus-surface text-olympus-text-muted hover:text-olympus-text'
    ]"
  >
    <Icon
      :name="channel.type === 'agent' ? 'ph:robot' : 'ph:hash'"
      :class="[
        'w-4 h-4 flex-shrink-0 transition-colors duration-150',
        selected ? 'text-olympus-primary' : 'text-olympus-text-muted group-hover:text-olympus-text'
      ]"
    />
    <span class="truncate flex-1 text-sm font-medium">{{ channel.name }}</span>
    <span
      v-if="channel.unreadCount > 0 && !selected"
      class="w-2 h-2 rounded-full bg-olympus-primary shrink-0 shadow-sm shadow-olympus-primary/50"
    />
    <span
      v-else-if="channel.unreadCount > 0"
      class="px-1.5 py-0.5 text-xs font-semibold rounded-full min-w-5 text-center bg-olympus-primary/20 text-olympus-primary"
    >
      {{ channel.unreadCount > 99 ? '99+' : channel.unreadCount }}
    </span>
  </button>
</template>

<script setup lang="ts">
import type { Channel } from '~/types'

defineProps<{
  channel: Channel
  selected: boolean
}>()
</script>
