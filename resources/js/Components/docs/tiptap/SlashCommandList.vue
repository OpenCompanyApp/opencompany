<template>
  <div
    class="w-64 max-h-72 overflow-y-auto rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 shadow-lg py-1"
  >
    <button
      v-for="(item, index) in items"
      :key="item.id"
      type="button"
      :class="[
        'flex items-center gap-3 w-full px-3 py-2 text-left transition-colors duration-75',
        index === selectedIndex
          ? 'bg-neutral-100 dark:bg-neutral-700'
          : 'hover:bg-neutral-50 dark:hover:bg-neutral-700/50',
      ]"
      @click="selectItem(index)"
      @mouseenter="selectedIndex = index"
    >
      <div class="w-8 h-8 rounded-md bg-neutral-100 dark:bg-neutral-700 flex items-center justify-center shrink-0">
        <Icon :name="item.icon" class="w-4 h-4 text-neutral-600 dark:text-neutral-300" />
      </div>
      <div class="min-w-0">
        <p class="text-sm font-medium text-neutral-900 dark:text-white truncate">{{ item.label }}</p>
        <p class="text-xs text-neutral-500 dark:text-neutral-400 truncate">{{ item.description }}</p>
      </div>
    </button>

    <div
      v-if="items.length === 0"
      class="px-3 py-4 text-center text-sm text-neutral-500 dark:text-neutral-400"
    >
      No results
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import type { SlashCommandItem } from './slash-commands'

const props = defineProps<{
  items: SlashCommandItem[]
  command: (item: SlashCommandItem) => void
}>()

const selectedIndex = ref(0)

watch(() => props.items, () => {
  selectedIndex.value = 0
})

const selectItem = (index: number) => {
  const item = props.items[index]
  if (item) {
    props.command(item)
  }
}

const onKeyDown = (event: KeyboardEvent): boolean => {
  if (event.key === 'ArrowUp') {
    selectedIndex.value = (selectedIndex.value + props.items.length - 1) % props.items.length
    return true
  }
  if (event.key === 'ArrowDown') {
    selectedIndex.value = (selectedIndex.value + 1) % props.items.length
    return true
  }
  if (event.key === 'Enter') {
    selectItem(selectedIndex.value)
    return true
  }
  return false
}

defineExpose({ onKeyDown })
</script>
