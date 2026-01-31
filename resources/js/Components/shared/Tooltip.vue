<script setup lang="ts">
import { computed } from 'vue'
import {
  TooltipRoot,
  TooltipTrigger,
  TooltipContent,
  TooltipPortal,
  TooltipArrow
} from 'reka-ui'

type TooltipSide = 'top' | 'right' | 'bottom' | 'left'

const props = withDefaults(defineProps<{
  text?: string
  side?: TooltipSide
  sideOffset?: number
  delayDuration?: number
  delayOpen?: number // Alias for delayDuration
  disabled?: boolean
}>(), {
  side: 'top',
  sideOffset: 4,
  delayDuration: 300,
  disabled: false,
})

// Use delayOpen if provided, otherwise use delayDuration
const delay = computed(() => props.delayOpen ?? props.delayDuration)
</script>

<template>
  <TooltipRoot v-if="!disabled" :delay-duration="delay">
    <TooltipTrigger as-child>
      <slot />
    </TooltipTrigger>
    <TooltipPortal>
      <TooltipContent
        class="z-50 bg-neutral-900 dark:bg-neutral-100 text-white dark:text-neutral-900 text-xs px-2 py-1 rounded shadow-lg select-none animate-in fade-in-0 zoom-in-95 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95"
        :side="side"
        :side-offset="sideOffset"
      >
        <slot name="content">{{ text }}</slot>
        <TooltipArrow class="fill-neutral-900 dark:fill-neutral-100" />
      </TooltipContent>
    </TooltipPortal>
  </TooltipRoot>
  <slot v-else />
</template>
