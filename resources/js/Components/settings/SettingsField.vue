<template>
  <div class="space-y-2">
    <div class="flex items-center justify-between gap-4">
      <div>
        <label class="block text-sm font-medium text-gray-900">
          {{ label }}
          <span v-if="required" class="text-red-500 ml-0.5">*</span>
        </label>
        <p v-if="description" class="text-xs text-gray-500 mt-0.5">
          {{ description }}
        </p>
      </div>
      <slot name="aside" />
    </div>
    <div class="relative">
      <slot />
    </div>
    <Transition
      enter-active-class="transition-opacity duration-150 ease-out"
      leave-active-class="transition-opacity duration-100 ease-out"
      enter-from-class="opacity-0"
      leave-to-class="opacity-0"
    >
      <p
        v-if="error"
        class="text-xs text-red-500 flex items-center gap-1"
      >
        <Icon name="ph:warning-circle" class="w-3.5 h-3.5" />
        {{ error }}
      </p>
    </Transition>
    <Transition
      enter-active-class="transition-opacity duration-150 ease-out"
      leave-active-class="transition-opacity duration-100 ease-out"
      enter-from-class="opacity-0"
      leave-to-class="opacity-0"
    >
      <p
        v-if="hint && !error"
        class="text-xs text-gray-400 flex items-center gap-1"
      >
        <Icon name="ph:info" class="w-3.5 h-3.5" />
        {{ hint }}
      </p>
    </Transition>
  </div>
</template>

<script setup lang="ts">
import { Icon } from '@iconify/vue'

withDefaults(defineProps<{
  label: string
  description?: string
  hint?: string
  error?: string
  required?: boolean
}>(), {
  required: false,
})
</script>
