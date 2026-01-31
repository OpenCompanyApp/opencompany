<script setup lang="ts">
import {
  PopoverRoot,
  PopoverTrigger,
  PopoverPortal,
  PopoverContent,
  PopoverClose,
  PopoverArrow,
} from 'reka-ui'
import Icon from './Icon.vue'

withDefaults(defineProps<{
  side?: 'top' | 'right' | 'bottom' | 'left'
  align?: 'start' | 'center' | 'end'
  sideOffset?: number
  showArrow?: boolean
  showClose?: boolean
  mode?: 'click' | 'hover'
  openDelay?: number
  closeDelay?: number
}>(), {
  side: 'bottom',
  align: 'center',
  sideOffset: 4,
  showArrow: false,
  showClose: false,
  mode: 'click',
  openDelay: 0,
  closeDelay: 0,
})

const open = defineModel<boolean>('open', { default: false })
</script>

<template>
  <PopoverRoot v-model:open="open">
    <PopoverTrigger as-child>
      <slot />
    </PopoverTrigger>

    <PopoverPortal>
      <PopoverContent
        class="z-50 bg-white dark:bg-neutral-800 rounded-lg border border-neutral-200 dark:border-neutral-700 shadow-lg p-4 animate-in fade-in-0 zoom-in-95 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95"
        :side="side"
        :align="align"
        :side-offset="sideOffset"
      >
        <!-- Close button -->
        <PopoverClose
          v-if="showClose"
          class="absolute right-2 top-2 rounded-sm opacity-70 ring-offset-white transition-opacity hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-neutral-400 focus:ring-offset-2"
        >
          <Icon name="ph:x" class="h-4 w-4 text-neutral-500 dark:text-neutral-300" />
          <span class="sr-only">Close</span>
        </PopoverClose>

        <slot name="content" />

        <PopoverArrow v-if="showArrow" class="fill-white dark:fill-neutral-800" />
      </PopoverContent>
    </PopoverPortal>
  </PopoverRoot>
</template>
