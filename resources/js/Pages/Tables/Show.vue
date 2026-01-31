<template>
  <div class="h-full flex flex-col">
    <!-- Header -->
    <header class="shrink-0 px-6 py-4 border-b border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
          <Link
            href="/tables"
            class="p-1.5 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors"
          >
            <Icon name="ph:arrow-left" class="w-5 h-5 text-neutral-600 dark:text-neutral-300" />
          </Link>
          <div v-if="table" class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-neutral-100 dark:bg-neutral-800 flex items-center justify-center">
              <Icon :name="table.icon || 'ph:table'" class="w-5 h-5 text-neutral-600 dark:text-neutral-300" />
            </div>
            <div>
              <h1 class="text-xl font-semibold text-neutral-900 dark:text-white">
                {{ table.name }}
              </h1>
              <p v-if="table.description" class="text-sm text-neutral-500 dark:text-neutral-400">
                {{ table.description }}
              </p>
            </div>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <Button variant="secondary" @click="showAddColumnModal = true">
            <Icon name="ph:plus" class="w-4 h-4 mr-1.5" />
            Add Column
          </Button>
          <Button @click="handleAddRow">
            <Icon name="ph:plus" class="w-4 h-4 mr-1.5" />
            Add Row
          </Button>
        </div>
      </div>
    </header>

    <!-- Toolbar -->
    <div class="shrink-0 px-6 py-3 border-b border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-900/50 flex items-center gap-4">
      <SearchInput
        v-model="searchQuery"
        placeholder="Search rows..."
        class="w-64"
      />
      <div class="flex-1" />

      <!-- Bulk actions -->
      <div v-if="selectedRowIds.length > 0" class="flex items-center gap-3">
        <span class="text-sm text-neutral-600 dark:text-neutral-300">
          {{ selectedRowIds.length }} selected
        </span>
        <Button
          variant="danger"
          size="sm"
          @click="confirmBulkDelete"
        >
          <Icon name="ph:trash" class="w-4 h-4 mr-1" />
          Delete
        </Button>
      </div>

      <span class="text-sm text-neutral-500 dark:text-neutral-400">
        {{ filteredRows.length }} rows
      </span>
    </div>

    <!-- Table Grid -->
    <div class="flex-1 overflow-auto">
      <div v-if="loading" class="flex items-center justify-center h-full">
        <Icon name="ph:spinner" class="w-8 h-8 animate-spin text-neutral-400" />
      </div>

      <div v-else-if="!table" class="flex items-center justify-center h-full">
        <p class="text-neutral-500 dark:text-neutral-400">Table not found</p>
      </div>

      <TableGrid
        v-else
        ref="tableGridRef"
        :columns="table.columns || []"
        :rows="filteredRows"
        @update-cell="handleUpdateCell"
        @delete-row="handleDeleteRow"
        @update-column="handleUpdateColumn"
        @delete-column="handleDeleteColumn"
        @edit-column="handleEditColumn"
        @selection-change="handleSelectionChange"
      />
    </div>

    <!-- Add Column Modal -->
    <ColumnTypeModal
      v-if="showAddColumnModal"
      @close="showAddColumnModal = false"
      @save="handleAddColumn"
    />

    <!-- Edit Column Modal -->
    <ColumnTypeModal
      v-if="showEditColumnModal && columnToEdit"
      :column="columnToEdit"
      @close="showEditColumnModal = false; columnToEdit = null"
      @save="handleSaveColumnEdit"
    />

    <!-- Bulk Delete Confirmation -->
    <ConfirmDialog
      :open="showBulkDeleteConfirm"
      title="Delete Rows"
      :description="`Are you sure you want to delete ${selectedRowIds.length} selected rows? This action cannot be undone.`"
      variant="danger"
      confirm-label="Delete Rows"
      @confirm="handleBulkDelete"
      @update:open="showBulkDeleteConfirm = false"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Link } from '@inertiajs/vue3'
import Icon from '@/Components/shared/Icon.vue'
import Button from '@/Components/shared/Button.vue'
import SearchInput from '@/Components/shared/SearchInput.vue'
import ConfirmDialog from '@/Components/shared/ConfirmDialog.vue'
import TableGrid from '@/Components/tables/TableGrid.vue'
import ColumnTypeModal from '@/Components/tables/ColumnTypeModal.vue'
import type { DataTable, DataTableRow, DataTableColumn } from '@/types'

const props = defineProps<{
  tableId: string
}>()

const table = ref<DataTable | null>(null)
const rows = ref<DataTableRow[]>([])
const loading = ref(true)
const searchQuery = ref('')
const showAddColumnModal = ref(false)
const showEditColumnModal = ref(false)
const columnToEdit = ref<DataTableColumn | null>(null)
const selectedRowIds = ref<string[]>([])
const showBulkDeleteConfirm = ref(false)
const tableGridRef = ref<InstanceType<typeof TableGrid> | null>(null)

const filteredRows = computed(() => {
  if (!searchQuery.value) return rows.value
  const query = searchQuery.value.toLowerCase()
  return rows.value.filter((row) => {
    return Object.values(row.data || {}).some((value) =>
      String(value).toLowerCase().includes(query)
    )
  })
})

const fetchTable = async () => {
  loading.value = true
  try {
    const [tableResponse, rowsResponse] = await Promise.all([
      fetch(`/api/tables/${props.tableId}`),
      fetch(`/api/tables/${props.tableId}/rows`),
    ])
    table.value = await tableResponse.json()
    rows.value = await rowsResponse.json()
  } catch (error) {
    console.error('Failed to fetch table:', error)
    table.value = null
    rows.value = []
  } finally {
    loading.value = false
  }
}

const handleAddRow = async () => {
  if (!table.value) return

  // Create empty row with default values
  const data: Record<string, unknown> = {}
  for (const column of table.value.columns || []) {
    if (column.type === 'checkbox') {
      data[column.id] = false
    } else if (column.type === 'number') {
      data[column.id] = 0
    } else if (column.type === 'multiselect') {
      data[column.id] = []
    } else {
      data[column.id] = ''
    }
  }

  try {
    const response = await fetch(`/api/tables/${props.tableId}/rows`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ data }),
    })
    const newRow = await response.json()
    rows.value.unshift(newRow)
  } catch (error) {
    console.error('Failed to add row:', error)
  }
}

const handleUpdateCell = async (rowId: string, columnId: string, value: unknown) => {
  try {
    const row = rows.value.find((r) => r.id === rowId)
    if (!row) return

    const newData = { ...row.data, [columnId]: value }
    await fetch(`/api/tables/${props.tableId}/rows/${rowId}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ data: newData }),
    })
    row.data = newData
  } catch (error) {
    console.error('Failed to update cell:', error)
  }
}

const handleDeleteRow = async (rowId: string) => {
  try {
    await fetch(`/api/tables/${props.tableId}/rows/${rowId}`, {
      method: 'DELETE',
    })
    rows.value = rows.value.filter((r) => r.id !== rowId)
  } catch (error) {
    console.error('Failed to delete row:', error)
  }
}

const handleAddColumn = async (columnData: { name: string; type: string; options?: Record<string, unknown>; required?: boolean }) => {
  try {
    const response = await fetch(`/api/tables/${props.tableId}/columns`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(columnData),
    })
    const newColumn = await response.json()
    if (table.value) {
      table.value.columns = [...(table.value.columns || []), newColumn]
    }
    showAddColumnModal.value = false
  } catch (error) {
    console.error('Failed to add column:', error)
  }
}

const handleEditColumn = (column: DataTableColumn) => {
  columnToEdit.value = column
  showEditColumnModal.value = true
}

const handleSaveColumnEdit = async (columnData: { name: string; type: string; options?: Record<string, unknown>; required?: boolean }) => {
  if (!columnToEdit.value) return

  try {
    await fetch(`/api/tables/${props.tableId}/columns/${columnToEdit.value.id}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(columnData),
    })

    if (table.value) {
      const column = table.value.columns?.find((c) => c.id === columnToEdit.value!.id)
      if (column) {
        Object.assign(column, columnData)
      }
    }

    showEditColumnModal.value = false
    columnToEdit.value = null
  } catch (error) {
    console.error('Failed to update column:', error)
  }
}

const handleUpdateColumn = async (columnId: string, updates: Partial<DataTableColumn>) => {
  try {
    await fetch(`/api/tables/${props.tableId}/columns/${columnId}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(updates),
    })
    if (table.value) {
      const column = table.value.columns?.find((c) => c.id === columnId)
      if (column) {
        Object.assign(column, updates)
      }
    }
  } catch (error) {
    console.error('Failed to update column:', error)
  }
}

const handleDeleteColumn = async (columnId: string) => {
  try {
    await fetch(`/api/tables/${props.tableId}/columns/${columnId}`, {
      method: 'DELETE',
    })
    if (table.value) {
      table.value.columns = table.value.columns?.filter((c) => c.id !== columnId)
    }
  } catch (error) {
    console.error('Failed to delete column:', error)
  }
}

const handleSelectionChange = (ids: string[]) => {
  selectedRowIds.value = ids
}

const confirmBulkDelete = () => {
  showBulkDeleteConfirm.value = true
}

const handleBulkDelete = async () => {
  try {
    await fetch(`/api/tables/${props.tableId}/rows/bulk-delete`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ rowIds: selectedRowIds.value }),
    })
    rows.value = rows.value.filter((r) => !selectedRowIds.value.includes(r.id))
    selectedRowIds.value = []
    tableGridRef.value?.clearSelection()
    showBulkDeleteConfirm.value = false
  } catch (error) {
    console.error('Failed to bulk delete rows:', error)
  }
}

onMounted(() => {
  fetchTable()
})
</script>
