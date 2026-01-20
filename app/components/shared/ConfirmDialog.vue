<template>
  <AlertDialogRoot :open="open" @update:open="$emit('update:open', $event)">
    <AlertDialogPortal>
      <AlertDialogOverlay
        class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0"
      />
      <AlertDialogContent
        class="fixed left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 z-50 w-full max-w-md bg-olympus-elevated border border-olympus-border rounded-2xl p-6 shadow-2xl data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[state=closed]:slide-out-to-left-1/2 data-[state=closed]:slide-out-to-top-[48%] data-[state=open]:slide-in-from-left-1/2 data-[state=open]:slide-in-from-top-[48%] duration-200"
      >
        <!-- Icon -->
        <div
          :class="[
            'w-12 h-12 rounded-xl flex items-center justify-center mx-auto mb-4',
            variant === 'danger' ? 'bg-red-500/20' : 'bg-olympus-primary/20'
          ]"
        >
          <Icon
            :name="variant === 'danger' ? 'ph:warning-circle-fill' : 'ph:question-fill'"
            :class="[
              'w-6 h-6',
              variant === 'danger' ? 'text-red-400' : 'text-olympus-primary'
            ]"
          />
        </div>

        <!-- Title -->
        <AlertDialogTitle class="text-lg font-semibold text-olympus-text text-center">
          {{ title }}
        </AlertDialogTitle>

        <!-- Description -->
        <AlertDialogDescription class="text-sm text-olympus-text-muted text-center mt-2 leading-relaxed">
          {{ description }}
        </AlertDialogDescription>

        <!-- Actions -->
        <div class="flex gap-3 mt-6">
          <AlertDialogCancel as-child>
            <SharedButton
              variant="secondary"
              full-width
              :disabled="loading"
              @click="$emit('cancel')"
            >
              {{ cancelLabel }}
            </SharedButton>
          </AlertDialogCancel>

          <AlertDialogAction as-child>
            <SharedButton
              :variant="variant === 'danger' ? 'danger' : 'primary'"
              full-width
              :loading="loading"
              @click="$emit('confirm')"
            >
              {{ confirmLabel }}
            </SharedButton>
          </AlertDialogAction>
        </div>
      </AlertDialogContent>
    </AlertDialogPortal>
  </AlertDialogRoot>
</template>

<script setup lang="ts">
import {
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogOverlay,
  AlertDialogPortal,
  AlertDialogRoot,
  AlertDialogTitle,
} from 'reka-ui'

withDefaults(defineProps<{
  open: boolean
  title: string
  description: string
  confirmLabel?: string
  cancelLabel?: string
  variant?: 'default' | 'danger'
  loading?: boolean
}>(), {
  confirmLabel: 'Confirm',
  cancelLabel: 'Cancel',
  variant: 'default',
  loading: false,
})

defineEmits<{
  'update:open': [value: boolean]
  'confirm': []
  'cancel': []
}>()
</script>
