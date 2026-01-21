<template>
  <TooltipProvider :delay-duration="300">
    <TooltipRoot>
      <TooltipTrigger as-child>
      <div class="flex items-center gap-1.5 px-2 py-1 rounded-lg hover:bg-gray-50 transition-colors cursor-default">
        <span
          :class="[
            'w-2 h-2 rounded-full transition-colors',
            isConnected ? 'bg-green-500' : 'bg-red-500',
          ]"
        />
        <span v-if="showLabel" class="text-xs text-gray-500">
          {{ isConnected ? 'Connected' : 'Disconnected' }}
        </span>
      </div>
    </TooltipTrigger>
    <TooltipPortal>
      <TooltipContent
        side="bottom"
        :side-offset="8"
        class="bg-white border border-gray-200 px-3 py-2 rounded-lg shadow-md"
      >
        <p class="text-sm text-gray-900">
          {{ isConnected ? 'Real-time updates active' : 'Reconnecting...' }}
        </p>
        <TooltipArrow class="fill-white" />
      </TooltipContent>
    </TooltipPortal>
  </TooltipRoot>
  </TooltipProvider>
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
import { useRealtime } from '@/composables/useRealtime'

defineProps<{
  showLabel?: boolean
}>()

const { isConnected } = useRealtime()
</script>
