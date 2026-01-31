<script setup lang="ts">
import { CheckboxRoot, CheckboxIndicator } from 'reka-ui'
import Icon from './Icon.vue'

withDefaults(defineProps<{
  id?: string
  label?: string
  description?: string
  disabled?: boolean
  required?: boolean
}>(), {
  disabled: false,
  required: false,
})

const checked = defineModel<boolean>('checked', { default: false })
const checkboxId = `checkbox-${Math.random().toString(36).substring(2, 9)}`
</script>

<template>
  <div class="flex items-start gap-3">
    <CheckboxRoot
      :id="id || checkboxId"
      v-model:checked="checked"
      :disabled="disabled"
      :required="required"
      class="peer h-4 w-4 shrink-0 rounded border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-400 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 data-[state=checked]:bg-neutral-900 data-[state=checked]:border-neutral-900 dark:data-[state=checked]:bg-white dark:data-[state=checked]:border-white"
    >
      <CheckboxIndicator class="flex items-center justify-center text-white dark:text-neutral-900">
        <Icon name="ph:check" class="h-3 w-3" />
      </CheckboxIndicator>
    </CheckboxRoot>

    <div v-if="label || description || $slots.default" class="flex flex-col">
      <label
        :for="id || checkboxId"
        class="text-sm font-medium text-neutral-700 dark:text-neutral-200 cursor-pointer select-none"
        :class="disabled && 'opacity-50 cursor-not-allowed'"
      >
        <slot>{{ label }}</slot>
        <span v-if="required" class="text-red-500 ml-0.5">*</span>
      </label>
      <p v-if="description" class="text-sm text-neutral-500 dark:text-neutral-300" :class="disabled && 'opacity-50'">
        {{ description }}
      </p>
    </div>
  </div>
</template>
