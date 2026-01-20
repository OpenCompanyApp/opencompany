<template>
  <div :class="['flex flex-col gap-1.5', fullWidth && 'w-full']">
    <label
      v-if="label"
      :for="inputId"
      class="text-sm font-medium text-olympus-text"
    >
      {{ label }}
      <span v-if="required" class="text-red-400">*</span>
    </label>

    <div class="relative">
      <Icon
        v-if="iconLeft"
        :name="iconLeft"
        class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-olympus-text-muted pointer-events-none"
      />

      <input
        :id="inputId"
        v-model="model"
        :type="type"
        :placeholder="placeholder"
        :disabled="disabled"
        :readonly="readonly"
        :class="[
          'w-full h-10 px-3 text-sm rounded-lg transition-all',
          'bg-olympus-surface border text-olympus-text placeholder:text-olympus-text-subtle',
          'focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-olympus-bg',
          error
            ? 'border-red-500/50 focus:ring-red-500/30 focus:border-red-500'
            : 'border-olympus-border focus:ring-olympus-primary/30 focus:border-olympus-primary',
          'disabled:opacity-50 disabled:cursor-not-allowed',
          iconLeft && 'pl-10',
          iconRight && 'pr-10',
        ]"
      />

      <Icon
        v-if="iconRight"
        :name="iconRight"
        class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-olympus-text-muted pointer-events-none"
      />
    </div>

    <p v-if="error" class="text-xs text-red-400">
      {{ error }}
    </p>
    <p v-else-if="hint" class="text-xs text-olympus-text-muted">
      {{ hint }}
    </p>
  </div>
</template>

<script setup lang="ts">
const props = withDefaults(defineProps<{
  modelValue?: string
  type?: 'text' | 'email' | 'password' | 'number' | 'search' | 'tel' | 'url'
  label?: string
  placeholder?: string
  hint?: string
  error?: string
  disabled?: boolean
  readonly?: boolean
  required?: boolean
  iconLeft?: string
  iconRight?: string
  fullWidth?: boolean
}>(), {
  modelValue: '',
  type: 'text',
  disabled: false,
  readonly: false,
  required: false,
  fullWidth: true,
})

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const model = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
})

const inputId = useId()
</script>
