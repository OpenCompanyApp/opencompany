<template>
  <DialogRoot v-model:open="isOpen">
    <DialogPortal>
      <DialogOverlay class="fixed inset-0 z-50 bg-black/50 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0" />
      <DialogContent
        :class="[
          'fixed z-50 bg-white dark:bg-neutral-800 shadow-lg',
          'border-l border-neutral-200 dark:border-neutral-700',
          'data-[state=open]:animate-in data-[state=closed]:animate-out',
          'data-[state=closed]:duration-300 data-[state=open]:duration-300',
          sideClasses[side],
          sizeClasses[size],
          'flex flex-col h-full',
        ]"
        @escape-key-down="closeOnEscape ? undefined : $event.preventDefault()"
      >
        <!-- Header -->
        <div v-if="$slots.header || title" class="px-6 py-4 border-b border-neutral-200 dark:border-neutral-700 shrink-0">
          <slot name="header">
            <div class="flex items-center justify-between">
              <div>
                <DialogTitle class="text-lg font-semibold text-neutral-900 dark:text-white">
                  {{ title }}
                </DialogTitle>
                <DialogDescription v-if="description" class="text-sm text-neutral-500 dark:text-neutral-300 mt-0.5">
                  {{ description }}
                </DialogDescription>
              </div>
              <DialogClose
                v-if="showClose"
                class="rounded-lg p-2 opacity-70 transition-all hover:opacity-100 hover:bg-neutral-100 dark:hover:bg-neutral-700 focus:outline-none focus:ring-2 focus:ring-neutral-400"
              >
                <Icon name="ph:x" class="h-5 w-5 text-neutral-500 dark:text-neutral-300" />
                <span class="sr-only">Close</span>
              </DialogClose>
            </div>
          </slot>
        </div>

        <!-- Body -->
        <div class="flex-1 overflow-y-auto px-6 py-4">
          <slot name="body">
            <slot />
          </slot>
        </div>

        <!-- Footer -->
        <div v-if="$slots.footer" class="px-6 py-4 border-t border-neutral-200 dark:border-neutral-700 shrink-0">
          <slot name="footer" />
        </div>
      </DialogContent>
    </DialogPortal>
  </DialogRoot>
</template>

<script setup lang="ts">
import {
  DialogRoot,
  DialogPortal,
  DialogOverlay,
  DialogContent,
  DialogTitle,
  DialogDescription,
  DialogClose,
} from 'reka-ui'
import Icon from './Icon.vue'

type SlideoverSide = 'left' | 'right'
type SlideoverSize = 'sm' | 'md' | 'lg' | 'xl' | 'full'

withDefaults(defineProps<{
  title?: string
  description?: string
  side?: SlideoverSide
  size?: SlideoverSize
  showClose?: boolean
  closeOnEscape?: boolean
}>(), {
  side: 'right',
  size: 'md',
  showClose: true,
  closeOnEscape: true,
})

defineEmits<{
  close: []
}>()

const isOpen = defineModel<boolean>('open', { default: false })

const sideClasses: Record<SlideoverSide, string> = {
  right: 'inset-y-0 right-0 data-[state=closed]:slide-out-to-right data-[state=open]:slide-in-from-right',
  left: 'inset-y-0 left-0 data-[state=closed]:slide-out-to-left data-[state=open]:slide-in-from-left border-l-0 border-r',
}

const sizeClasses: Record<SlideoverSize, string> = {
  sm: 'w-full max-w-sm',
  md: 'w-full max-w-lg',
  lg: 'w-full max-w-2xl',
  xl: 'w-full max-w-4xl',
  full: 'w-full',
}
</script>
