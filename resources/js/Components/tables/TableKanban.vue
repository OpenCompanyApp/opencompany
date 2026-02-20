<template>
  <div class="h-full flex flex-col">
    <!-- Config prompt: no group-by column selected -->
    <div v-if="!groupByColumn" class="flex flex-col items-center justify-center h-full text-center p-8">
      <Icon name="ph:kanban" class="w-12 h-12 text-neutral-300 dark:text-neutral-600 mb-3" />
      <p class="text-sm text-neutral-600 dark:text-neutral-400 mb-4">
        Select a column to group rows into columns
      </p>
      <div v-if="groupableColumns.length === 0" class="text-sm text-neutral-400">
        No select or multiselect columns available. Add one to use Kanban view.
      </div>
      <div v-else class="flex flex-wrap gap-2 justify-center">
        <button
          v-for="col in groupableColumns"
          :key="col.id"
          type="button"
          class="px-3 py-1.5 rounded-lg border border-neutral-200 dark:border-neutral-700 text-sm text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors"
          @click="selectGroupByColumn(col.id)"
        >
          {{ col.name }}
        </button>
      </div>
    </div>

    <!-- Kanban board -->
    <div v-else class="flex-1 flex overflow-x-auto gap-4 p-4">
      <div
        v-for="group in groups"
        :key="group.value"
        class="w-72 shrink-0 flex flex-col bg-neutral-50 dark:bg-neutral-800/50 rounded-xl"
        @dragover.prevent="handleDragOver($event, group.value)"
        @dragleave="handleDragLeave(group.value)"
        @drop="handleDrop($event, group.value)"
      >
        <!-- Column header -->
        <div class="flex items-center gap-2 px-3 py-2.5 border-b border-neutral-200/50 dark:border-neutral-700/50">
          <span class="text-sm font-medium text-neutral-900 dark:text-white truncate flex-1">
            {{ group.value || 'Uncategorized' }}
          </span>
          <span class="text-xs text-neutral-500 bg-neutral-200 dark:bg-neutral-700 px-1.5 py-0.5 rounded-full">
            {{ group.rows.length }}
          </span>
        </div>

        <!-- Cards -->
        <div class="flex-1 overflow-y-auto p-2 space-y-2">
          <!-- Drop indicator -->
          <div
            v-if="dragOverGroup === group.value && group.rows.length === 0"
            class="border-2 border-dashed border-blue-300 dark:border-blue-600 rounded-lg py-8 flex items-center justify-center"
          >
            <span class="text-xs text-blue-500 font-medium">Drop here</span>
          </div>

          <div
            v-for="row in group.rows"
            :key="row.id"
            draggable="true"
            class="bg-white dark:bg-neutral-800 rounded-lg border border-neutral-200 dark:border-neutral-700 p-3 cursor-grab active:cursor-grabbing hover:shadow-sm transition-shadow"
            @dragstart="handleDragStart($event, row)"
          >
            <!-- Card title -->
            <p class="text-sm font-medium text-neutral-900 dark:text-white truncate mb-1">
              {{ getRowTitle(row) }}
            </p>

            <!-- Card fields (up to 3 non-title, non-groupby fields) -->
            <div class="space-y-1">
              <div
                v-for="col in getCardFields(row)"
                :key="col.id"
                class="flex items-center gap-1.5"
              >
                <span class="text-xs text-neutral-400 w-16 shrink-0 truncate">{{ col.name }}</span>
                <span class="text-xs text-neutral-600 dark:text-neutral-300 truncate">
                  {{ formatCellValue(row.data[col.id], col) }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Add row -->
        <div class="p-2 border-t border-neutral-200/50 dark:border-neutral-700/50">
          <button
            type="button"
            class="w-full flex items-center justify-center gap-1 py-1.5 text-xs text-neutral-500 hover:text-neutral-700 dark:hover:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-700 rounded-md transition-colors"
            @click="handleAddRowToGroup(group.value)"
          >
            <Icon name="ph:plus" class="w-3.5 h-3.5" />
            Add
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import type { DataTableColumn, DataTableRow, DataTableViewConfig } from '@/types'

const props = defineProps<{
  columns: DataTableColumn[]
  rows: DataTableRow[]
  config?: DataTableViewConfig
}>()

const emit = defineEmits<{
  updateCell: [rowId: string, columnId: string, value: unknown]
  deleteRow: [rowId: string]
  addRow: []
  configChange: [config: DataTableViewConfig]
}>()

const dragOverGroup = ref<string | null>(null)
const draggingRowId = ref<string | null>(null)

const groupableColumns = computed(() =>
  props.columns.filter(c => c.type === 'select' || c.type === 'multiselect')
)

const groupByColumn = computed(() =>
  props.columns.find(c => c.id === props.config?.groupByColumnId)
)

const titleColumn = computed(() =>
  props.columns.find(c => c.type === 'text' && c.id !== groupByColumn.value?.id)
)

const groups = computed(() => {
  const col = groupByColumn.value
  if (!col) return []

  const choices = (col.options?.choices as string[]) || []
  const map = new Map<string, DataTableRow[]>()

  // Initialize with all defined choices
  for (const val of choices) {
    map.set(val, [])
  }
  map.set('', []) // uncategorized

  for (const row of props.rows) {
    const cellVal = row.data[col.id]
    if (col.type === 'multiselect' && Array.isArray(cellVal)) {
      const val = cellVal[0] || ''
      if (!map.has(String(val))) map.set(String(val), [])
      map.get(String(val))!.push(row)
    } else {
      const val = String(cellVal || '')
      if (!map.has(val)) map.set(val, [])
      map.get(val)!.push(row)
    }
  }

  return Array.from(map.entries()).map(([value, rows]) => ({ value, rows }))
})

const getRowTitle = (row: DataTableRow): string => {
  if (!titleColumn.value) return 'Untitled'
  return String(row.data[titleColumn.value.id] || 'Untitled')
}

const getCardFields = (row: DataTableRow): DataTableColumn[] => {
  return props.columns
    .filter(c => c.id !== titleColumn.value?.id && c.id !== groupByColumn.value?.id)
    .filter(c => row.data[c.id] !== undefined && row.data[c.id] !== '' && row.data[c.id] !== null)
    .slice(0, 3)
}

const formatCellValue = (value: unknown, col: DataTableColumn): string => {
  if (value === null || value === undefined) return '-'
  if (col.type === 'checkbox') return value ? 'Yes' : 'No'
  if (col.type === 'multiselect' && Array.isArray(value)) return value.join(', ')
  if (col.type === 'date') {
    try { return new Date(String(value)).toLocaleDateString() } catch { return String(value) }
  }
  return String(value)
}

const selectGroupByColumn = (columnId: string) => {
  emit('configChange', { ...props.config, groupByColumnId: columnId })
}

const handleAddRowToGroup = (groupValue: string) => {
  // Emit addRow — parent will create empty row, then we update the group-by cell
  emit('addRow')
}

// Drag and drop
const handleDragStart = (event: DragEvent, row: DataTableRow) => {
  if (event.dataTransfer) {
    event.dataTransfer.setData('rowId', row.id)
    event.dataTransfer.effectAllowed = 'move'
    draggingRowId.value = row.id
  }
}

const handleDragOver = (event: DragEvent, groupValue: string) => {
  event.dataTransfer!.dropEffect = 'move'
  dragOverGroup.value = groupValue
}

const handleDragLeave = (groupValue: string) => {
  if (dragOverGroup.value === groupValue) {
    dragOverGroup.value = null
  }
}

const handleDrop = (event: DragEvent, newGroupValue: string) => {
  const rowId = event.dataTransfer?.getData('rowId')
  dragOverGroup.value = null
  draggingRowId.value = null

  if (!rowId || !groupByColumn.value) return

  // Update the cell value to move the card to the new group
  const value = newGroupValue === '' ? null : newGroupValue
  emit('updateCell', rowId, groupByColumn.value.id, value)
}
</script>
