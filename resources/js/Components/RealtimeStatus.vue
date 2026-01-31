<template>
  <Tooltip :text="isConnected ? 'Real-time updates active' : 'Reconnecting...'" :delay-duration="300">
    <div class="flex items-center gap-1.5 px-2 py-1 rounded-lg hover:bg-neutral-50 dark:hover:bg-neutral-800 transition-colors cursor-default">
      <span
        :class="[
          'w-2 h-2 rounded-full transition-colors',
          isConnected ? 'bg-green-500' : 'bg-red-500',
        ]"
      />
      <span v-if="showLabel" class="text-xs text-neutral-500 dark:text-neutral-300">
        {{ isConnected ? 'Connected' : 'Disconnected' }}
      </span>
    </div>
  </Tooltip>
</template>

<script setup lang="ts">
import { useRealtime } from '@/composables/useRealtime'
import Tooltip from '@/Components/shared/Tooltip.vue'

defineProps<{
  showLabel?: boolean
}>()

const { isConnected } = useRealtime()
</script>
