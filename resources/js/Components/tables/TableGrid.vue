<template>
  <div class="min-w-full">
    <table class="w-full border-collapse">
      <!-- Header -->
      <thead class="sticky top-0 z-10">
        <tr class="bg-neutral-50 dark:bg-neutral-800">
          <th class="w-10 px-2 py-2 border-b border-r border-neutral-200 dark:border-neutral-700">
            <div class="flex items-center justify-center">
              <Checkbox
                :checked="allSelected"
                @update:checked="toggleSelectAll"
              />
            </div>
          </th>
          <th
            v-for="column in columns"
            :key="column.id"
            class="px-3 py-2 text-left text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider border-b border-r border-neutral-200 dark:border-neutral-700 min-w-[150px]"
          >
            <DropdownMenu
              :items="getColumnMenuItems(column)"
              side="bottom"
              align="start"
            >
              <button
                type="button"
                class="flex items-center gap-2 w-full text-left hover:text-neutral-700 dark:hover:text-neutral-200 transition-colors"
              >
                <Icon :name="getColumnIcon(column.type)" class="w-4 h-4" />
                <span class="flex-1">{{ column.name }}</span>
                <Icon
                  v-if="sortColumn === column.id"
                  :name="sortDirection === 'asc' ? 'ph:caret-up' : 'ph:caret-down'"
                  class="w-3 h-3 text-blue-500"
                />
                <Icon
                  v-else
                  name="ph:caret-down"
                  class="w-3 h-3 opacity-0 group-hover:opacity-50"
                />
              </button>
            </DropdownMenu>
          </th>
          <!-- Add Column Button -->
          <th class="w-10 px-2 py-2 border-b border-neutral-200 dark:border-neutral-700">
            <button
              type="button"
              class="w-full h-full flex items-center justify-center p-1 rounded hover:bg-neutral-200 dark:hover:bg-neutral-700 transition-colors text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300"
              @click="$emit('addColumn')"
            >
              <Icon name="ph:plus" class="w-4 h-4" />
            </button>
          </th>
        </tr>
      </thead>

      <!-- Body -->
      <tbody>
        <tr
          v-for="row in sortedRows"
          :key="row.id"
          class="group hover:bg-neutral-50 dark:hover:bg-neutral-800/50 transition-colors"
        >
          <td class="px-2 py-2 border-b border-r border-neutral-200 dark:border-neutral-700">
            <div class="flex items-center justify-center">
              <Checkbox
                :checked="selectedRows.has(row.id)"
                @update:checked="toggleRowSelection(row.id)"
              />
            </div>
          </td>
          <td
            v-for="column in columns"
            :key="column.id"
            class="px-3 py-2 border-b border-r border-neutral-200 dark:border-neutral-700"
          >
            <TableCell
              :column="column"
              :value="row.data?.[column.id]"
              @update="(value) => $emit('updateCell', row.id, column.id, value)"
            />
          </td>
          <td class="px-2 py-2 border-b border-neutral-200 dark:border-neutral-700">
            <button
              type="button"
              class="p-1 opacity-0 group-hover:opacity-100 rounded hover:bg-neutral-200 dark:hover:bg-neutral-700 transition-all"
              @click="confirmDeleteRow(row.id)"
            >
              <Icon name="ph:trash" class="w-4 h-4 text-neutral-400 hover:text-red-500 transition-colors" />
            </button>
          </td>
        </tr>

        <!-- Empty state -->
        <tr v-if="rows.length === 0">
          <td
            :colspan="columns.length + 2"
            class="px-3 py-12 text-center"
          >
            <div class="flex flex-col items-center">
              <div class="w-12 h-12 rounded-full bg-neutral-100 dark:bg-neutral-800 flex items-center justify-center mb-3">
                <Icon name="ph:rows" class="w-6 h-6 text-neutral-400" />
              </div>
              <p class="text-sm text-neutral-500 dark:text-neutral-400 mb-3">
                No rows yet
              </p>
              <button
                type="button"
                class="flex items-center gap-1.5 px-3 py-1.5 text-sm text-neutral-500 hover:text-neutral-700 dark:text-neutral-400 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-md transition-colors"
                @click="$emit('addRow')"
              >
                <Icon name="ph:plus" class="w-4 h-4" />
                Add row
              </button>
            </div>
          </td>
        </tr>

        <!-- Add Row Button -->
        <tr v-else>
          <td
            :colspan="columns.length + 2"
            class="px-3 py-2 border-b border-neutral-200 dark:border-neutral-700"
          >
            <button
              type="button"
              class="flex items-center gap-1.5 px-2 py-1 text-sm text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded transition-colors"
              @click="$emit('addRow')"
            >
              <Icon name="ph:plus" class="w-4 h-4" />
              <span>Add row</span>
            </button>
          </td>
        </tr>
      </tbody>
    </table>

    <!-- Delete Row Confirmation -->
    <ConfirmDialog
      :open="!!rowToDelete"
      title="Delete Row"
      description="Are you sure you want to delete this row? This action cannot be undone."
      variant="danger"
      confirm-label="Delete"
      @confirm="handleDeleteRow"
      @update:open="rowToDelete = null"
    />

    <!-- Delete Column Confirmation -->
    <ConfirmDialog
      :open="!!columnToDelete"
      :title="`Delete Column`"
      :description="`Are you sure you want to delete the column '${columnToDelete?.name}'? All data in this column will be permanently removed.`"
      variant="danger"
      confirm-label="Delete Column"
      @confirm="handleDeleteColumn"
      @update:open="columnToDelete = null"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import Checkbox from '@/Components/shared/Checkbox.vue'
import DropdownMenu from '@/Components/shared/DropdownMenu.vue'
import ConfirmDialog from '@/Components/shared/ConfirmDialog.vue'
import TableCell from '@/Components/tables/TableCell.vue'
import type { DataTableColumn, DataTableRow } from '@/types'

const props = defineProps<{
  columns: DataTableColumn[]
  rows: DataTableRow[]
}>()

const emit = defineEmits<{
  updateCell: [rowId: string, columnId: string, value: unknown]
  deleteRow: [rowId: string]
  updateColumn: [columnId: string, updates: Partial<DataTableColumn>]
  deleteColumn: [columnId: string]
  editColumn: [column: DataTableColumn]
  selectionChange: [selectedIds: string[]]
  addRow: []
  addColumn: []
}>()

// Selection state
const selectedRows = ref(new Set<string>())

// Sorting state
const sortColumn = ref<string | null>(null)
const sortDirection = ref<'asc' | 'desc'>('asc')

// Delete confirmation state
const rowToDelete = ref<string | null>(null)
const columnToDelete = ref<DataTableColumn | null>(null)

const allSelected = computed(() => {
  if (props.rows.length === 0) return false
  return props.rows.every((row) => selectedRows.value.has(row.id))
})

const sortedRows = computed(() => {
  if (!sortColumn.value) return props.rows

  const col = props.columns.find(c => c.id === sortColumn.value)
  if (!col) return props.rows

  return [...props.rows].sort((a, b) => {
    const aVal = a.data?.[sortColumn.value!]
    const bVal = b.data?.[sortColumn.value!]

    // Handle null/undefined
    if (aVal == null && bVal == null) return 0
    if (aVal == null) return sortDirection.value === 'asc' ? 1 : -1
    if (bVal == null) return sortDirection.value === 'asc' ? -1 : 1

    // Type-aware comparison
    let comparison = 0
    if (col.type === 'number') {
      comparison = Number(aVal) - Number(bVal)
    } else if (col.type === 'date') {
      comparison = new Date(String(aVal)).getTime() - new Date(String(bVal)).getTime()
    } else if (col.type === 'checkbox') {
      comparison = (aVal ? 1 : 0) - (bVal ? 1 : 0)
    } else {
      comparison = String(aVal).localeCompare(String(bVal))
    }

    return sortDirection.value === 'asc' ? comparison : -comparison
  })
})

const getColumnIcon = (type: string): string => {
  const icons: Record<string, string> = {
    text: 'ph:text-aa',
    number: 'ph:hash',
    date: 'ph:calendar',
    select: 'ph:list',
    multiselect: 'ph:list-checks',
    checkbox: 'ph:check-square',
    url: 'ph:link',
    email: 'ph:envelope',
    user: 'ph:user',
    attachment: 'ph:paperclip',
  }
  return icons[type] || 'ph:text-aa'
}

const getColumnMenuItems = (column: DataTableColumn) => [
  [
    {
      label: 'Sort Ascending',
      icon: 'ph:sort-ascending',
      click: () => {
        sortColumn.value = column.id
        sortDirection.value = 'asc'
      },
    },
    {
      label: 'Sort Descending',
      icon: 'ph:sort-descending',
      click: () => {
        sortColumn.value = column.id
        sortDirection.value = 'desc'
      },
    },
  ],
  [
    {
      label: 'Edit Column',
      icon: 'ph:pencil',
      click: () => emit('editColumn', column),
    },
    {
      label: 'Delete Column',
      icon: 'ph:trash',
      color: 'error' as const,
      click: () => {
        columnToDelete.value = column
      },
    },
  ],
]

const toggleSelectAll = () => {
  if (allSelected.value) {
    selectedRows.value.clear()
  } else {
    props.rows.forEach((row) => selectedRows.value.add(row.id))
  }
  emitSelectionChange()
}

const toggleRowSelection = (rowId: string) => {
  if (selectedRows.value.has(rowId)) {
    selectedRows.value.delete(rowId)
  } else {
    selectedRows.value.add(rowId)
  }
  emitSelectionChange()
}

const emitSelectionChange = () => {
  emit('selectionChange', Array.from(selectedRows.value))
}

const confirmDeleteRow = (rowId: string) => {
  rowToDelete.value = rowId
}

const handleDeleteRow = () => {
  if (rowToDelete.value) {
    emit('deleteRow', rowToDelete.value)
    selectedRows.value.delete(rowToDelete.value)
    rowToDelete.value = null
  }
}

const handleDeleteColumn = () => {
  if (columnToDelete.value) {
    emit('deleteColumn', columnToDelete.value.id)
    // Clear sort if this column was sorted
    if (sortColumn.value === columnToDelete.value.id) {
      sortColumn.value = null
    }
    columnToDelete.value = null
  }
}

// Reset selection when rows change
watch(() => props.rows, () => {
  const rowIds = new Set(props.rows.map(r => r.id))
  for (const id of selectedRows.value) {
    if (!rowIds.has(id)) {
      selectedRows.value.delete(id)
    }
  }
}, { deep: true })

// Expose selection for parent components
defineExpose({
  selectedRows,
  clearSelection: () => {
    selectedRows.value.clear()
    emitSelectionChange()
  },
})
</script>
