<template>
  <DialogRoot v-model:open="isOpen">
    <DialogPortal>
      <DialogOverlay
        class="fixed inset-0 z-50 bg-black/50 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 duration-150"
      />
      <DialogContent
        :class="[
          'fixed left-1/2 top-1/2 z-50 -translate-x-1/2 -translate-y-1/2',
          'bg-white border border-gray-200 rounded-lg',
          'shadow-lg',
          'data-[state=open]:animate-in data-[state=closed]:animate-out',
          'data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0',
          'duration-150 ease-out',
          sizeClasses[size],
        ]"
        @escape-key-down="handleEscape"
      >
        <!-- Header -->
        <div
          v-if="title || $slots.header"
          class="flex items-center justify-between px-6 py-4 border-b border-gray-200"
        >
          <slot name="header">
            <div class="flex items-center gap-3">
              <div
                v-if="icon"
                class="w-10 h-10 rounded-lg flex items-center justify-center bg-gray-100 text-gray-600"
              >
                <Icon :name="icon" class="w-5 h-5" />
              </div>
              <div>
                <DialogTitle class="text-lg font-semibold text-gray-900">
                  {{ title }}
                </DialogTitle>
                <DialogDescription v-if="description" class="text-sm text-gray-500 mt-0.5">
                  {{ description }}
                </DialogDescription>
              </div>
            </div>
          </slot>
          <DialogClose
            class="w-8 h-8 rounded-md flex items-center justify-center text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors duration-150"
          >
            <Icon name="ph:x" class="w-5 h-5" />
          </DialogClose>
        </div>

        <!-- Content -->
        <div class="p-6" :class="{ 'pt-0': !title && !$slots.header }">
          <slot />
        </div>

        <!-- Footer -->
        <div
          v-if="$slots.footer"
          class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50"
        >
          <slot name="footer" />
        </div>
      </DialogContent>
    </DialogPortal>
  </DialogRoot>
</template>

<script setup lang="ts">
import Icon from '@/Components/shared/Icon.vue'
import {
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogOverlay,
  DialogPortal,
  DialogRoot,
  DialogTitle,
} from 'reka-ui'

type ModalSize = 'sm' | 'md' | 'lg' | 'xl' | 'full'

const props = withDefaults(defineProps<{
  title?: string
  description?: string
  icon?: string
  size?: ModalSize
  closeOnEscape?: boolean
}>(), {
  size: 'md',
  closeOnEscape: true,
})

const emit = defineEmits<{
  close: []
}>()

const isOpen = defineModel<boolean>('open', { default: false })

const sizeClasses: Record<ModalSize, string> = {
  sm: 'w-full max-w-sm',
  md: 'w-full max-w-lg',
  lg: 'w-full max-w-2xl',
  xl: 'w-full max-w-4xl',
  full: 'w-[calc(100%-2rem)] h-[calc(100%-2rem)]',
}

const handleEscape = (event: Event) => {
  if (!props.closeOnEscape) {
    event.preventDefault()
  }
}
</script>
