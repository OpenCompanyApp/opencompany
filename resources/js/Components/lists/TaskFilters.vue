<template>
  <div class="flex items-center gap-2">
    <!-- View Toggle -->
    <div class="flex items-center bg-neutral-100 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg p-0.5">
      <Tooltip
        v-for="view in viewOptions"
        :key="view.value"
        :text="view.label"
        :delay-open="300"
      >
        <button
          :class="[
            'p-1.5 rounded-md transition-colors duration-150',
            selectedView === view.value
              ? 'bg-white dark:bg-neutral-700 text-neutral-900 dark:text-white shadow-sm'
              : 'text-neutral-500 dark:text-neutral-300 hover:text-neutral-900 dark:hover:text-white'
          ]"
          @click="selectedView = view.value"
        >
          <Icon :name="view.icon" class="w-4 h-4" />
        </button>
      </Tooltip>
    </div>

    <!-- Divider -->
    <div class="w-px h-5 bg-neutral-200 dark:bg-neutral-700" />

    <!-- Status Filter -->
    <Select
      v-model="selectedFilter"
      :items="filterOptions"
      value-key="value"
      placeholder="All tasks"
      icon="ph:funnel"
      size="sm"
    />

    <!-- Priority Filter -->
    <Select
      v-model="selectedPriority"
      :items="priorityOptions"
      value-key="value"
      placeholder="Any priority"
      icon="ph:flag"
      size="sm"
    />

    <!-- Assignee Filter -->
    <Select
      v-model="selectedAssignee"
      :items="assigneeOptions"
      value-key="value"
      placeholder="Anyone"
      icon="ph:user"
      size="sm"
    />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import Tooltip from '@/Components/shared/Tooltip.vue'
import Select from '@/Components/shared/Select.vue'

type ViewType = 'board' | 'list'

const props = withDefaults(defineProps<{
  filter?: string
  view?: ViewType
}>(), {
  filter: 'all',
  view: 'list',
})

const emit = defineEmits<{
  'update:filter': [value: string]
  'update:view': [value: ViewType]
}>()

const selectedFilter = computed({
  get: () => props.filter,
  set: (value) => emit('update:filter', value),
})

const selectedView = computed({
  get: () => props.view,
  set: (value) => emit('update:view', value),
})

// These are local state for the filter dropdowns (not emitted for now, but keep the UI)
const selectedPriority = computed({
  get: () => 'any',
  set: () => {},
})

const selectedAssignee = computed({
  get: () => 'any',
  set: () => {},
})

const viewOptions: { value: ViewType; label: string; icon: string }[] = [
  { value: 'list', label: 'List view', icon: 'ph:list' },
  { value: 'board', label: 'Board view', icon: 'ph:kanban' },
]

const filterOptions = [
  { value: 'all', label: 'All tasks' },
  { value: 'agents', label: 'Agent tasks' },
  { value: 'humans', label: 'Human tasks' },
]

const priorityOptions = [
  { value: 'any', label: 'Any priority' },
  { value: 'urgent', label: 'Urgent' },
  { value: 'high', label: 'High' },
  { value: 'medium', label: 'Medium' },
  { value: 'low', label: 'Low' },
]

const assigneeOptions = [
  { value: 'any', label: 'Anyone' },
  { value: 'unassigned', label: 'Unassigned' },
]
</script>
