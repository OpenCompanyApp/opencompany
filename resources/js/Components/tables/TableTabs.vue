<template>
  <div class="shrink-0 px-6 py-2 border-b border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-900/50 flex items-center justify-between">
    <!-- Left: View Tabs -->
    <div class="flex items-center gap-1">
      <button
        v-for="view in views"
        :key="view.id"
        type="button"
        :class="[
          'flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium transition-all',
          activeViewId === view.id
            ? 'bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white shadow-sm border border-neutral-200 dark:border-neutral-700'
            : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-white/50 dark:hover:bg-neutral-800/50',
        ]"
        @click="emit('select-view', view.id)"
      >
        <Icon :name="getViewIcon(view.type)" class="w-4 h-4" />
        <span>{{ view.name }}</span>
        <DropdownMenu v-if="views.length > 1" :items="getViewOptions(view)">
          <button
            type="button"
            class="p-0.5 rounded hover:bg-neutral-100 dark:hover:bg-neutral-700 opacity-0 group-hover:opacity-100 transition-opacity"
            @click.stop
          >
            <Icon name="ph:caret-down" class="w-3 h-3" />
          </button>
        </DropdownMenu>
      </button>

      <!-- Add View -->
      <DropdownMenu :items="addViewOptions">
        <button
          type="button"
          class="flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-sm text-neutral-500 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-white dark:hover:bg-neutral-800 transition-all"
        >
          <Icon name="ph:plus" class="w-4 h-4" />
          <span>Add View</span>
        </button>
      </DropdownMenu>
    </div>

    <!-- Right: View Controls -->
    <div class="flex items-center gap-1">
      <Button
        variant="ghost"
        size="sm"
        :class="{ 'bg-neutral-100 dark:bg-neutral-800': hasActiveFilters }"
        @click="emit('toggle-filter')"
      >
        <Icon name="ph:funnel" class="w-4 h-4" />
        <span class="ml-1.5">Filter</span>
        <span
          v-if="filterCount > 0"
          class="ml-1 px-1.5 py-0.5 text-xs rounded-full bg-neutral-900 dark:bg-white text-white dark:text-neutral-900"
        >
          {{ filterCount }}
        </span>
      </Button>

      <Button variant="ghost" size="sm" @click="emit('toggle-hide')">
        <Icon name="ph:eye-slash" class="w-4 h-4" />
        <span class="ml-1.5">Hide</span>
        <span
          v-if="hiddenCount > 0"
          class="ml-1 px-1.5 py-0.5 text-xs rounded-full bg-neutral-200 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-300"
        >
          {{ hiddenCount }}
        </span>
      </Button>

      <Button variant="ghost" size="sm" @click="emit('toggle-sort')">
        <Icon name="ph:sort-ascending" class="w-4 h-4" />
        <span class="ml-1.5">Sort</span>
      </Button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import Button from '@/Components/shared/Button.vue'
import DropdownMenu from '@/Components/shared/DropdownMenu.vue'
import type { DataTableView, DataTableViewType } from '@/types'

const props = withDefaults(defineProps<{
  views: DataTableView[]
  activeViewId: string | null
  filterCount?: number
  hiddenCount?: number
  hasActiveFilters?: boolean
}>(), {
  filterCount: 0,
  hiddenCount: 0,
  hasActiveFilters: false,
})

const emit = defineEmits<{
  'select-view': [viewId: string]
  'add-view': [type: DataTableViewType]
  'rename-view': [viewId: string]
  'duplicate-view': [viewId: string]
  'delete-view': [viewId: string]
  'toggle-filter': []
  'toggle-hide': []
  'toggle-sort': []
}>()

const viewTypeIcons: Record<DataTableViewType, string> = {
  grid: 'ph:table',
  kanban: 'ph:kanban',
  gallery: 'ph:squares-four',
  calendar: 'ph:calendar',
}

const viewTypeLabels: Record<DataTableViewType, string> = {
  grid: 'Grid',
  kanban: 'Kanban',
  gallery: 'Gallery',
  calendar: 'Calendar',
}

const getViewIcon = (type: DataTableViewType) => {
  return viewTypeIcons[type] || 'ph:table'
}

const addViewOptions = computed(() => [
  Object.entries(viewTypeLabels).map(([type, label]) => ({
    label: `${label} View`,
    icon: viewTypeIcons[type as DataTableViewType],
    click: () => emit('add-view', type as DataTableViewType),
  })),
])

const getViewOptions = (view: DataTableView) => [
  [
    {
      label: 'Rename',
      icon: 'ph:pencil-simple',
      click: () => emit('rename-view', view.id),
    },
    {
      label: 'Duplicate',
      icon: 'ph:copy',
      click: () => emit('duplicate-view', view.id),
    },
  ],
  [
    {
      label: 'Delete',
      icon: 'ph:trash',
      color: 'error' as const,
      click: () => emit('delete-view', view.id),
    },
  ],
]
</script>
