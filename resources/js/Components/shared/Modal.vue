<template>
  <DialogRoot v-model:open="isOpen">
    <DialogPortal>
      <DialogOverlay class="fixed inset-0 z-50 bg-black/50 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0" />
      <DialogContent
        :class="[
          'fixed left-1/2 top-1/2 z-50 -translate-x-1/2 -translate-y-1/2',
          'w-full bg-white dark:bg-neutral-800 shadow-lg',
          'border border-neutral-200 dark:border-neutral-700 rounded-lg',
          'data-[state=open]:animate-in data-[state=closed]:animate-out',
          'data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0',
          'data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95',
          'data-[state=closed]:slide-out-to-left-1/2 data-[state=closed]:slide-out-to-top-[48%]',
          'data-[state=open]:slide-in-from-left-1/2 data-[state=open]:slide-in-from-top-[48%]',
          'duration-200',
          sizeClasses[size],
        ]"
        @escape-key-down="closeOnEscape ? undefined : $event.preventDefault()"
      >
        <!-- Header -->
        <div v-if="icon || $slots.header || title" class="px-6 pt-6 pb-4">
          <slot name="header">
            <div class="flex items-center gap-3">
              <div
                v-if="icon"
                class="w-10 h-10 rounded-lg flex items-center justify-center bg-neutral-100 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-200"
              >
                <Icon :name="icon" class="w-5 h-5" />
              </div>
              <div>
                <DialogTitle class="text-lg font-semibold text-neutral-900 dark:text-white">
                  {{ title }}
                </DialogTitle>
                <DialogDescription v-if="description" class="text-sm text-neutral-500 dark:text-neutral-300 mt-0.5">
                  {{ description }}
                </DialogDescription>
              </div>
            </div>
          </slot>
        </div>

        <!-- Content -->
        <div class="px-6 pb-6" :class="{ 'pt-6': !icon && !$slots.header && !title }">
          <slot />
        </div>

        <!-- Footer -->
        <div v-if="$slots.footer" class="px-6 pb-6 pt-2 border-t border-neutral-200 dark:border-neutral-700">
          <slot name="footer" />
        </div>

        <!-- Close button -->
        <DialogClose
          class="absolute right-4 top-4 rounded-sm opacity-70 ring-offset-white transition-opacity hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-neutral-400 focus:ring-offset-2 disabled:pointer-events-none dark:ring-offset-neutral-800"
        >
          <Icon name="ph:x" class="h-4 w-4 text-neutral-500 dark:text-neutral-300" />
          <span class="sr-only">Close</span>
        </DialogClose>
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

type ModalSize = 'sm' | 'md' | 'lg' | 'xl' | 'full'

withDefaults(defineProps<{
  title?: string
  description?: string
  icon?: string
  size?: ModalSize
  closeOnEscape?: boolean
}>(), {
  size: 'md',
  closeOnEscape: true,
})

defineEmits<{
  close: []
}>()

const isOpen = defineModel<boolean>('open', { default: false })

const sizeClasses: Record<ModalSize, string> = {
  sm: 'max-w-sm',
  md: 'max-w-lg',
  lg: 'max-w-2xl',
  xl: 'max-w-4xl',
  full: 'max-w-[calc(100%-2rem)] h-[calc(100%-2rem)]',
}
</script>
