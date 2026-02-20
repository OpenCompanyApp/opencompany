<template>
  <div class="h-full flex flex-col bg-white dark:bg-neutral-900">
    <!-- Header -->
    <TableHeader
      v-if="table"
      :table="table"
      @update:name="handleUpdateName"
      @update:description="handleUpdateDescription"
      @update:icon="handleUpdateIcon"
      @export="handleExport"
      @import="handleImport"
      @settings="showSettingsModal = true"
      @duplicate="handleDuplicateTable"
      @delete="showDeleteConfirm = true"
    />

    <!-- Loading Header Placeholder -->
    <div v-else class="shrink-0 px-6 py-4 border-b border-neutral-200 dark:border-neutral-700">
      <div class="flex items-center gap-4">
        <div class="w-11 h-11 rounded-xl bg-neutral-100 dark:bg-neutral-800 animate-pulse" />
        <div class="space-y-2">
          <div class="h-5 w-32 bg-neutral-100 dark:bg-neutral-800 rounded animate-pulse" />
          <div class="h-4 w-48 bg-neutral-100 dark:bg-neutral-800 rounded animate-pulse" />
        </div>
      </div>
    </div>

    <!-- Toolbar (combined with tabs) -->
    <div v-if="table" class="shrink-0 px-6 py-2 border-b border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/50 flex items-center gap-3">
      <!-- View Tabs -->
      <div class="flex items-center gap-1">
        <button
          v-for="view in views"
          :key="view.id"
          type="button"
          :class="[
            'flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-sm font-medium transition-all',
            activeViewId === view.id
              ? 'bg-white dark:bg-neutral-700 text-neutral-900 dark:text-white shadow-sm'
              : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-white/50 dark:hover:bg-neutral-700/50',
          ]"
          @click="handleSelectView(view.id)"
        >
          <Icon :name="getViewIcon(view.type)" class="w-4 h-4" />
          <span>{{ view.name }}</span>
        </button>

        <!-- Add View -->
        <DropdownMenu :items="addViewOptions">
          <button
            type="button"
            class="flex items-center gap-1 px-2 py-1.5 rounded-md text-sm text-neutral-500 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-white/50 dark:hover:bg-neutral-700/50 transition-all"
          >
            <Icon name="ph:plus" class="w-4 h-4" />
          </button>
        </DropdownMenu>
      </div>

      <div class="w-px h-5 bg-neutral-300 dark:bg-neutral-600" />

      <!-- Search -->
      <SearchInput
        v-model="searchQuery"
        placeholder="Search..."
        class="w-48"
      />

      <div class="flex-1" />

      <!-- View Controls -->
      <div class="flex items-center gap-0.5">
        <Button variant="ghost" size="sm" icon-left="ph:funnel" icon-only @click="showFilterPanel = !showFilterPanel" />
        <Button variant="ghost" size="sm" icon-left="ph:sort-ascending" icon-only @click="showSortPanel = !showSortPanel" />
      </div>

      <!-- Selection Actions -->
      <template v-if="selectedRowIds.length > 0 && (!activeView || activeView.type === 'grid')">
        <div class="w-px h-5 bg-neutral-300 dark:bg-neutral-600" />
        <div class="flex items-center gap-2">
          <span class="text-sm text-neutral-600 dark:text-neutral-300">
            {{ selectedRowIds.length }} selected
          </span>
          <Button variant="ghost" size="sm" icon-left="ph:trash" icon-only @click="confirmBulkDelete" />
        </div>
      </template>
    </div>

    <!-- Table Grid -->
    <div class="flex-1 overflow-auto">
      <div v-if="loading" class="flex items-center justify-center h-full">
        <Icon name="ph:spinner" class="w-8 h-8 animate-spin text-neutral-400" />
      </div>

      <div v-else-if="!table" class="flex items-center justify-center h-full">
        <div class="text-center">
          <Icon name="ph:warning" class="w-12 h-12 text-neutral-300 dark:text-neutral-600 mx-auto mb-3" />
          <p class="text-neutral-500 dark:text-neutral-400">Table not found</p>
          <Link :href="workspacePath('/tables')" class="text-sm text-neutral-900 dark:text-white hover:underline mt-2 inline-block">
            Back to Tables
          </Link>
        </div>
      </div>

      <TableGrid
        v-else-if="!activeView || activeView.type === 'grid'"
        ref="tableGridRef"
        :columns="visibleColumns"
        :rows="filteredRows"
        @update-cell="handleUpdateCell"
        @delete-row="handleDeleteRow"
        @update-column="handleUpdateColumn"
        @delete-column="handleDeleteColumn"
        @edit-column="handleEditColumn"
        @selection-change="handleSelectionChange"
        @add-row="handleAddRow"
        @add-column="showAddColumnModal = true"
      />

      <TableKanban
        v-else-if="activeView.type === 'kanban'"
        :columns="visibleColumns"
        :rows="filteredRows"
        :config="activeView.config"
        @update-cell="handleUpdateCell"
        @delete-row="handleDeleteRow"
        @add-row="handleAddRow"
        @config-change="(config: DataTableViewConfig) => handleViewConfigChange(activeView!.id, config)"
      />

      <TableGallery
        v-else-if="activeView.type === 'gallery'"
        :columns="visibleColumns"
        :rows="filteredRows"
        :config="activeView.config"
        @update-cell="handleUpdateCell"
        @delete-row="handleDeleteRow"
        @add-row="handleAddRow"
        @config-change="(config: DataTableViewConfig) => handleViewConfigChange(activeView!.id, config)"
      />

      <TableCalendar
        v-else-if="activeView.type === 'calendar'"
        :columns="visibleColumns"
        :rows="filteredRows"
        :config="activeView.config"
        @update-cell="handleUpdateCell"
        @delete-row="handleDeleteRow"
        @add-row="handleAddRow"
        @config-change="(config: DataTableViewConfig) => handleViewConfigChange(activeView!.id, config)"
      />
    </div>

    <!-- Footer with row count -->
    <div v-if="table && !loading" class="shrink-0 px-6 py-2 border-t border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/50">
      <span class="text-xs text-neutral-500 dark:text-neutral-400">
        {{ filteredRows.length }} {{ filteredRows.length === 1 ? 'row' : 'rows' }}
      </span>
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

    <!-- Delete Table Confirmation -->
    <ConfirmDialog
      :open="showDeleteConfirm"
      title="Delete Table"
      :description="`Are you sure you want to delete '${table?.name}'? This will permanently delete all data and cannot be undone.`"
      variant="danger"
      confirm-label="Delete Table"
      @confirm="handleDeleteTable"
      @update:open="showDeleteConfirm = false"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { apiFetch } from '@/utils/apiFetch'
import Icon from '@/Components/shared/Icon.vue'
import { useWorkspace } from '@/composables/useWorkspace'
import Button from '@/Components/shared/Button.vue'
import SearchInput from '@/Components/shared/SearchInput.vue'
import ConfirmDialog from '@/Components/shared/ConfirmDialog.vue'
import DropdownMenu from '@/Components/shared/DropdownMenu.vue'
import TableHeader from '@/Components/tables/TableHeader.vue'
import TableGrid from '@/Components/tables/TableGrid.vue'
import TableKanban from '@/Components/tables/TableKanban.vue'
import TableGallery from '@/Components/tables/TableGallery.vue'
import TableCalendar from '@/Components/tables/TableCalendar.vue'
import ColumnTypeModal from '@/Components/tables/ColumnTypeModal.vue'
import type { DataTable, DataTableRow, DataTableColumn, DataTableView, DataTableViewType, DataTableViewConfig } from '@/types'

const { workspacePath } = useWorkspace()

const props = defineProps<{
  tableId: string
}>()

const table = ref<DataTable | null>(null)
const rows = ref<DataTableRow[]>([])
const views = ref<DataTableView[]>([])
const activeViewId = ref<string | null>(null)
const loading = ref(true)
const searchQuery = ref('')
const showAddColumnModal = ref(false)
const showEditColumnModal = ref(false)
const columnToEdit = ref<DataTableColumn | null>(null)
const selectedRowIds = ref<string[]>([])
const showBulkDeleteConfirm = ref(false)
const showDeleteConfirm = ref(false)
const showSettingsModal = ref(false)
const showFilterPanel = ref(false)
const showHidePanel = ref(false)
const showSortPanel = ref(false)
const hiddenColumns = ref<string[]>([])
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

const visibleColumns = computed(() => {
  if (!table.value?.columns) return []
  return table.value.columns.filter(col => !hiddenColumns.value.includes(col.id))
})

// View helpers
const viewTypeIcons: Record<DataTableViewType, string> = {
  grid: 'ph:table',
  kanban: 'ph:kanban',
  gallery: 'ph:squares-four',
  calendar: 'ph:calendar',
}

const getViewIcon = (type: DataTableViewType) => viewTypeIcons[type] || 'ph:table'

const activeView = computed(() =>
  views.value.find(v => v.id === activeViewId.value)
)

const addViewOptions = computed(() => [
  [
    { label: 'Grid View', icon: 'ph:table', click: () => handleAddView('grid') },
    { label: 'Kanban View', icon: 'ph:kanban', click: () => handleAddView('kanban') },
    { label: 'Gallery View', icon: 'ph:squares-four', click: () => handleAddView('gallery') },
    { label: 'Calendar View', icon: 'ph:calendar', click: () => handleAddView('calendar') },
  ],
])

const fetchTable = async () => {
  loading.value = true
  try {
    const [tableResponse, rowsResponse, viewsResponse] = await Promise.all([
      apiFetch(`/api/tables/${props.tableId}`),
      apiFetch(`/api/tables/${props.tableId}/rows`),
      apiFetch(`/api/tables/${props.tableId}/views`),
    ])
    table.value = await tableResponse.json()
    rows.value = await rowsResponse.json()
    const fetchedViews: DataTableView[] = await viewsResponse.json()

    if (fetchedViews.length > 0) {
      views.value = fetchedViews
      activeViewId.value = fetchedViews[0].id
    } else {
      views.value = [{
        id: 'default-grid',
        tableId: props.tableId,
        name: 'Grid',
        type: 'grid',
      }]
      activeViewId.value = 'default-grid'
    }
  } catch (error) {
    console.error('Failed to fetch table:', error)
    table.value = null
    rows.value = []
  } finally {
    loading.value = false
  }
}

// Table update handlers
const handleUpdateName = async (name: string) => {
  if (!table.value) return
  try {
    await apiFetch(`/api/tables/${props.tableId}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ name }),
    })
    table.value.name = name
  } catch (error) {
    console.error('Failed to update table name:', error)
  }
}

const handleUpdateDescription = async (description: string) => {
  if (!table.value) return
  try {
    await apiFetch(`/api/tables/${props.tableId}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ description }),
    })
    table.value.description = description
  } catch (error) {
    console.error('Failed to update table description:', error)
  }
}

const handleUpdateIcon = async (icon: string) => {
  if (!table.value) return
  try {
    await apiFetch(`/api/tables/${props.tableId}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ icon }),
    })
    table.value.icon = icon
  } catch (error) {
    console.error('Failed to update table icon:', error)
  }
}

// Export/Import handlers
const handleExport = async (format: 'csv' | 'json') => {
  if (!table.value) return

  try {
    const response = await apiFetch(`/api/tables/${props.tableId}/export?format=${format}`)
    const blob = await response.blob()
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `${table.value.name}.${format}`
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    URL.revokeObjectURL(url)
  } catch (error) {
    console.error('Failed to export table:', error)
  }
}

const handleImport = async (file: File, format: 'csv' | 'json') => {
  const formData = new FormData()
  formData.append('file', file)
  formData.append('format', format)

  try {
    const response = await apiFetch(`/api/tables/${props.tableId}/import`, {
      method: 'POST',
      body: formData,
    })
    const result = await response.json()
    if (result.rows) {
      rows.value = [...rows.value, ...result.rows]
    }
  } catch (error) {
    console.error('Failed to import data:', error)
  }
}

// View handlers
const handleSelectView = (viewId: string) => {
  activeViewId.value = viewId
}

const handleAddView = async (type: DataTableViewType) => {
  // Auto-detect sensible default config
  let config: DataTableViewConfig = {}
  if (type === 'kanban') {
    const selectCol = table.value?.columns?.find(c => c.type === 'select' || c.type === 'multiselect')
    if (selectCol) config.groupByColumnId = selectCol.id
  } else if (type === 'calendar') {
    const dateCol = table.value?.columns?.find(c => c.type === 'date')
    if (dateCol) config.dateColumnId = dateCol.id
  } else if (type === 'gallery') {
    const textCol = table.value?.columns?.find(c => c.type === 'text')
    if (textCol) config.titleColumnId = textCol.id
  }

  const name = `${type.charAt(0).toUpperCase() + type.slice(1)} View`

  try {
    const response = await apiFetch(`/api/tables/${props.tableId}/views`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ name, type, config }),
    })
    const newView: DataTableView = await response.json()
    views.value.push(newView)
    activeViewId.value = newView.id
  } catch (error) {
    console.error('Failed to create view:', error)
    // Fallback to client-side view
    const newView: DataTableView = {
      id: `view-${Date.now()}`,
      tableId: props.tableId,
      name,
      type,
      config,
    }
    views.value.push(newView)
    activeViewId.value = newView.id
  }
}

const handleRenameView = async (viewId: string) => {
  const view = views.value.find(v => v.id === viewId)
  if (!view) return
  const newName = window.prompt('Rename view', view.name)
  if (!newName || newName === view.name) return
  try {
    await apiFetch(`/api/tables/${props.tableId}/views/${viewId}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ name: newName }),
    })
    view.name = newName
  } catch (error) {
    console.error('Failed to rename view:', error)
  }
}

const handleDuplicateView = (viewId: string) => {
  const view = views.value.find(v => v.id === viewId)
  if (view) {
    const newView: DataTableView = {
      ...view,
      id: `view-${Date.now()}`,
      name: `${view.name} (Copy)`,
    }
    views.value.push(newView)
  }
}

const handleViewConfigChange = async (viewId: string, config: DataTableViewConfig) => {
  const view = views.value.find(v => v.id === viewId)
  if (!view) return

  view.config = { ...view.config, ...config }

  // Persist to backend for server-side views
  if (!viewId.startsWith('view-') && !viewId.startsWith('default-')) {
    try {
      await apiFetch(`/api/tables/${props.tableId}/views/${viewId}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ config: view.config }),
      })
    } catch (error) {
      console.error('Failed to update view config:', error)
    }
  }
}

const handleDeleteView = (viewId: string) => {
  if (views.value.length <= 1) return
  views.value = views.value.filter(v => v.id !== viewId)
  if (activeViewId.value === viewId) {
    activeViewId.value = views.value[0]?.id || null
  }
}

// Table deletion
const handleDeleteTable = async () => {
  try {
    await apiFetch(`/api/tables/${props.tableId}`, { method: 'DELETE' })
    router.visit(workspacePath('/tables'))
  } catch (error) {
    console.error('Failed to delete table:', error)
  }
}

const handleDuplicateTable = async () => {
  if (!table.value) return
  try {
    const response = await apiFetch(`/api/tables/${props.tableId}/duplicate`, {
      method: 'POST',
    })
    const newTable = await response.json()
    router.visit(workspacePath(`/tables/${newTable.id}`))
  } catch (error) {
    console.error('Failed to duplicate table:', error)
  }
}

// Row handlers
const handleAddRow = async () => {
  if (!table.value) return

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
    const response = await apiFetch(`/api/tables/${props.tableId}/rows`, {
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
    await apiFetch(`/api/tables/${props.tableId}/rows/${rowId}`, {
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
    await apiFetch(`/api/tables/${props.tableId}/rows/${rowId}`, {
      method: 'DELETE',
    })
    rows.value = rows.value.filter((r) => r.id !== rowId)
  } catch (error) {
    console.error('Failed to delete row:', error)
  }
}

// Column handlers
const handleAddColumn = async (columnData: { name: string; type: string; options?: Record<string, unknown>; required?: boolean }) => {
  try {
    const response = await apiFetch(`/api/tables/${props.tableId}/columns`, {
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
    await apiFetch(`/api/tables/${props.tableId}/columns/${columnToEdit.value.id}`, {
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
    await apiFetch(`/api/tables/${props.tableId}/columns/${columnId}`, {
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
    await apiFetch(`/api/tables/${props.tableId}/columns/${columnId}`, {
      method: 'DELETE',
    })
    if (table.value) {
      table.value.columns = table.value.columns?.filter((c) => c.id !== columnId)
    }
  } catch (error) {
    console.error('Failed to delete column:', error)
  }
}

// Selection handlers
const handleSelectionChange = (ids: string[]) => {
  selectedRowIds.value = ids
}

const confirmBulkDelete = () => {
  showBulkDeleteConfirm.value = true
}

const handleBulkDelete = async () => {
  try {
    await apiFetch(`/api/tables/${props.tableId}/rows/bulk-delete`, {
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
