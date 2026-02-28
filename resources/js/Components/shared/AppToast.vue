<script setup lang="ts">
import {
  ToastProvider,
  ToastRoot,
  ToastTitle,
  ToastDescription,
  ToastClose,
  ToastViewport,
} from 'reka-ui'
import Icon from '@/Components/shared/Icon.vue'
import { useToast } from '@/composables/useToast'

const { toasts, dismiss } = useToast()

const iconMap = {
  success: 'ph:check-circle-fill',
  error: 'ph:x-circle-fill',
  info: 'ph:info-fill',
} as const

const colorMap = {
  success: 'text-green-500 dark:text-green-400',
  error: 'text-red-500 dark:text-red-400',
  info: 'text-blue-500 dark:text-blue-400',
} as const
</script>

<template>
  <ToastProvider :duration="4000" swipe-direction="right">
    <ToastRoot
      v-for="toast in toasts"
      :key="toast.id"
      :duration="toast.duration"
      class="bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-xl shadow-lg p-4 flex items-start gap-3 w-[360px] data-[state=open]:animate-in data-[state=open]:slide-in-from-right data-[state=closed]:animate-out data-[state=closed]:fade-out data-[swipe=move]:translate-x-[var(--reka-toast-swipe-move-x)] data-[swipe=cancel]:translate-x-0 data-[swipe=end]:translate-x-[var(--reka-toast-swipe-end-x)]"
      @update:open="(open: boolean) => { if (!open) dismiss(toast.id) }"
    >
      <Icon :name="iconMap[toast.type]" :class="['w-5 h-5 shrink-0 mt-0.5', colorMap[toast.type]]" />
      <div class="flex-1 min-w-0">
        <ToastTitle class="text-sm font-medium text-neutral-900 dark:text-white">
          {{ toast.title }}
        </ToastTitle>
        <ToastDescription
          v-if="toast.description"
          class="text-sm text-neutral-500 dark:text-neutral-400 mt-0.5"
        >
          {{ toast.description }}
        </ToastDescription>
      </div>
      <ToastClose class="shrink-0 p-1 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors">
        <Icon name="ph:x" class="w-4 h-4 text-neutral-400 dark:text-neutral-500" />
      </ToastClose>
    </ToastRoot>

    <ToastViewport class="fixed bottom-4 right-4 z-[100] flex flex-col gap-2 outline-none" />
  </ToastProvider>
</template>
