<template>
  <div class="flex items-center justify-between px-4 py-1.5 border-t border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-900 shrink-0">
    <span class="text-xs text-neutral-500 dark:text-neutral-400">
      {{ itemLabel }}
    </span>
    <span v-if="selectedCount > 0" class="text-xs text-neutral-500 dark:text-neutral-400">
      {{ selectedCount }} selected
    </span>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  totalItems: number
  selectedCount: number
}>()

const itemLabel = computed(() => {
  if (props.totalItems === 0) return 'Empty folder'
  return `${props.totalItems} item${props.totalItems !== 1 ? 's' : ''}`
})
</script>
