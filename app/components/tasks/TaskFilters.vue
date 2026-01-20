<template>
  <div class="flex items-center gap-3">
    <!-- Filter Dropdown -->
    <SelectRoot v-model="selectedFilter">
      <SelectTrigger
        class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-olympus-surface border border-olympus-border hover:border-olympus-primary/50 transition-colors text-sm"
      >
        <Icon name="ph:funnel" class="w-4 h-4 text-olympus-text-muted" />
        <SelectValue placeholder="All tasks" />
        <Icon name="ph:caret-down" class="w-4 h-4 text-olympus-text-muted" />
      </SelectTrigger>

      <SelectPortal>
        <SelectContent
          class="bg-olympus-elevated border border-olympus-border rounded-xl p-1.5 shadow-xl z-50 min-w-40"
          position="popper"
          :side-offset="8"
        >
          <SelectViewport>
            <SelectItem
              v-for="option in filterOptions"
              :key="option.value"
              :value="option.value"
              class="flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-olympus-surface cursor-pointer outline-none text-sm data-[highlighted]:bg-olympus-surface"
            >
              <SelectItemIndicator class="w-4">
                <Icon name="ph:check" class="w-4 h-4 text-olympus-primary" />
              </SelectItemIndicator>
              <SelectItemText>{{ option.label }}</SelectItemText>
            </SelectItem>
          </SelectViewport>
        </SelectContent>
      </SelectPortal>
    </SelectRoot>

    <!-- New Task Button -->
    <button
      class="flex items-center gap-2 px-4 py-2 bg-olympus-primary hover:bg-olympus-primary-hover text-white rounded-xl text-sm font-medium transition-colors"
    >
      <Icon name="ph:plus" class="w-4 h-4" />
      New Task
    </button>
  </div>
</template>

<script setup lang="ts">
import {
  SelectContent,
  SelectItem,
  SelectItemIndicator,
  SelectItemText,
  SelectPortal,
  SelectRoot,
  SelectTrigger,
  SelectValue,
  SelectViewport,
} from 'reka-ui'

const props = defineProps<{
  filter: string
}>()

const emit = defineEmits<{
  'update:filter': [value: string]
}>()

const selectedFilter = computed({
  get: () => props.filter,
  set: (value) => emit('update:filter', value),
})

const filterOptions = [
  { value: 'all', label: 'All tasks' },
  { value: 'agents', label: 'Agent tasks' },
  { value: 'humans', label: 'Human tasks' },
]
</script>
