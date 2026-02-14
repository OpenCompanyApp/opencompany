<template>
  <div class="space-y-3">
    <div class="flex items-center justify-between">
      <span class="text-sm font-medium text-neutral-700 dark:text-neutral-200">On these dates</span>
      <button
        type="button"
        class="text-xs text-neutral-500 hover:text-neutral-700 dark:text-neutral-400 dark:hover:text-neutral-200 transition-colors"
        @click="toggleFirstOfMonth"
      >
        {{ modelValue.includes(1) && modelValue.length === 1 ? 'Clear' : '1st only' }}
      </button>
    </div>
    <div class="grid grid-cols-7 gap-1.5">
      <button
        v-for="date in 31"
        :key="date"
        type="button"
        :class="[
          'w-9 h-9 rounded-lg text-xs font-medium transition-all',
          modelValue.includes(date)
            ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900 shadow-sm'
            : 'bg-neutral-50 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-700',
        ]"
        @click="toggle(date)"
      >
        {{ date }}
      </button>
    </div>
    <p v-if="modelValue.some(d => d > 28)" class="text-xs text-amber-600 dark:text-amber-400">
      Dates 29-31 may be skipped in shorter months.
    </p>
  </div>
</template>

<script setup lang="ts">
const props = defineProps<{
  modelValue: number[]
}>()

const emit = defineEmits<{
  'update:modelValue': [value: number[]]
}>()

function toggle(date: number) {
  const current = [...props.modelValue]
  const idx = current.indexOf(date)
  if (idx >= 0) {
    current.splice(idx, 1)
  } else {
    current.push(date)
  }
  emit('update:modelValue', current)
}

function toggleFirstOfMonth() {
  if (props.modelValue.includes(1) && props.modelValue.length === 1) {
    emit('update:modelValue', [])
  } else {
    emit('update:modelValue', [1])
  }
}
</script>
