<script setup lang="ts">
import {
  TooltipRoot,
  TooltipTrigger,
  TooltipContent,
  TooltipPortal,
  TooltipArrow
} from 'reka-ui'

type TooltipSide = 'top' | 'right' | 'bottom' | 'left'

withDefaults(defineProps<{
  text?: string
  side?: TooltipSide
  delayDuration?: number
  disabled?: boolean
}>(), {
  side: 'top',
  delayDuration: 300,
  disabled: false,
})
</script>

<template>
  <TooltipRoot v-if="!disabled" :delay-duration="delayDuration">
    <TooltipTrigger as-child>
      <slot />
    </TooltipTrigger>
    <TooltipPortal>
      <TooltipContent
        class="z-50 bg-neutral-900 dark:bg-neutral-100 text-white dark:text-neutral-900 text-xs px-2 py-1 rounded shadow-lg select-none animate-in fade-in-0 zoom-in-95 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95"
        :side="side"
        :side-offset="4"
      >
        <slot name="content">{{ text }}</slot>
        <TooltipArrow class="fill-neutral-900 dark:fill-neutral-100" />
      </TooltipContent>
    </TooltipPortal>
  </TooltipRoot>
  <slot v-else />
</template>
