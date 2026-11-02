<template>
  <div class="flex items-center gap-2">
    <span class="text-sm text-neutral-500 dark:text-neutral-400">at</span>
    <select
      :value="hour"
      class="bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg px-3 py-2 text-sm text-neutral-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-neutral-400 dark:focus:ring-neutral-500 appearance-none cursor-pointer"
      @change="$emit('update:hour', Number(($event.target as HTMLSelectElement).value))"
    >
      <option v-for="h in hourOptions" :key="h.value" :value="h.value">
        {{ h.label }}
      </option>
    </select>
    <span class="text-neutral-400 dark:text-neutral-500 font-mono">:</span>
    <select
      :value="minute"
      class="bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg px-3 py-2 text-sm text-neutral-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-neutral-400 dark:focus:ring-neutral-500 appearance-none cursor-pointer"
      @change="$emit('update:minute', Number(($event.target as HTMLSelectElement).value))"
    >
      <option v-for="m in minuteOptions" :key="m" :value="m">
        {{ String(m).padStart(2, '0') }}
      </option>
    </select>
    <span class="text-xs text-neutral-400 dark:text-neutral-500">
      {{ period }}
    </span>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

defineProps<{
  hour: number
  minute: number
}>()

defineEmits<{
  'update:hour': [value: number]
  'update:minute': [value: number]
}>()

const hourOptions = computed(() =>
  Array.from({ length: 24 }, (_, i) => ({
    value: i,
    label: i === 0 ? '12' : i > 12 ? String(i - 12) : String(i),
  })),
)

const minuteOptions = [0, 5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 55]

const period = computed(() => {
  // This is just a label, not used in props
  return ''
})
</script>
