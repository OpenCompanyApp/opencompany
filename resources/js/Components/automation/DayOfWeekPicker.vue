<template>
  <div class="space-y-3">
    <div class="flex items-center justify-between">
      <span class="text-sm font-medium text-neutral-700 dark:text-neutral-200">On these days</span>
      <button
        type="button"
        class="text-xs text-neutral-500 hover:text-neutral-700 dark:text-neutral-400 dark:hover:text-neutral-200 transition-colors"
        @click="toggleWeekdays"
      >
        {{ allWeekdaysSelected ? 'Clear weekdays' : 'Weekdays' }}
      </button>
    </div>
    <div class="flex gap-2">
      <button
        v-for="day in days"
        :key="day.value"
        type="button"
        :class="[
          'w-11 h-11 rounded-full text-sm font-medium transition-all',
          modelValue.includes(day.value)
            ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900 shadow-sm ring-2 ring-neutral-900/20 dark:ring-white/20'
            : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-500 dark:text-neutral-400 hover:bg-neutral-200 dark:hover:bg-neutral-700',
        ]"
        @click="toggle(day.value)"
      >
        {{ day.label }}
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  modelValue: number[]
}>()

const emit = defineEmits<{
  'update:modelValue': [value: number[]]
}>()

const days = [
  { value: 1, label: 'Mon' },
  { value: 2, label: 'Tue' },
  { value: 3, label: 'Wed' },
  { value: 4, label: 'Thu' },
  { value: 5, label: 'Fri' },
  { value: 6, label: 'Sat' },
  { value: 0, label: 'Sun' },
]

const weekdays = [1, 2, 3, 4, 5]

const allWeekdaysSelected = computed(() =>
  weekdays.every((d) => props.modelValue.includes(d)),
)

function toggle(day: number) {
  const current = [...props.modelValue]
  const idx = current.indexOf(day)
  if (idx >= 0) {
    current.splice(idx, 1)
  } else {
    current.push(day)
  }
  emit('update:modelValue', current)
}

function toggleWeekdays() {
  if (allWeekdaysSelected.value) {
    emit(
      'update:modelValue',
      props.modelValue.filter((d) => !weekdays.includes(d)),
    )
  } else {
    const merged = new Set([...props.modelValue, ...weekdays])
    emit('update:modelValue', [...merged])
  }
}
</script>
