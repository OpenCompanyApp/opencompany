<template>
  <div class="h-full flex flex-col">
    <!-- Header -->
    <header class="shrink-0 px-6 py-4 border-b border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-xl font-semibold text-neutral-900 dark:text-white">Tables</h1>
          <p class="text-sm text-neutral-500 dark:text-neutral-400 mt-1">
            Create and manage structured data tables
          </p>
        </div>
        <Button @click="showCreateModal = true">
          <Icon name="ph:plus" class="w-4 h-4 mr-1.5" />
          New Table
        </Button>
      </div>
    </header>

    <!-- Content -->
    <div class="flex-1 overflow-auto p-6">
      <!-- Loading state -->
      <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div
          v-for="i in 6"
          :key="i"
          class="h-32 bg-neutral-100 dark:bg-neutral-800 rounded-xl animate-pulse"
        />
      </div>

      <!-- Empty state -->
      <div
        v-else-if="tables.length === 0"
        class="flex flex-col items-center justify-center h-full text-center"
      >
        <div class="w-16 h-16 rounded-full bg-neutral-100 dark:bg-neutral-800 flex items-center justify-center mb-4">
          <Icon name="ph:table" class="w-8 h-8 text-neutral-400" />
        </div>
        <h2 class="text-lg font-medium text-neutral-900 dark:text-white mb-2">
          No tables yet
        </h2>
        <p class="text-neutral-500 dark:text-neutral-400 mb-4 max-w-sm">
          Create your first table to start organizing data. Tables can be used by agents to store and query structured information.
        </p>
        <Button @click="showCreateModal = true">
          <Icon name="ph:plus" class="w-4 h-4 mr-1.5" />
          Create Table
        </Button>
      </div>

      <!-- Tables grid -->
      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div
          v-for="table in tables"
          :key="table.id"
          class="group relative p-4 bg-white dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700 hover:border-neutral-300 dark:hover:border-neutral-600 transition-colors"
        >
          <!-- Menu button -->
          <div class="absolute top-3 right-3 opacity-0 group-hover:opacity-100 transition-opacity">
            <DropdownMenu
              :items="[
                { label: 'Delete', icon: 'ph:trash', color: 'error', click: () => confirmDeleteTable(table) }
              ]"
              side="bottom"
              align="end"
            >
              <button
                type="button"
                class="p-1.5 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors"
                @click.prevent.stop
              >
                <Icon name="ph:dots-three" class="w-4 h-4 text-neutral-500" />
              </button>
            </DropdownMenu>
          </div>

          <Link
            :href="`/tables/${table.id}`"
            class="block"
          >
            <div class="flex items-start gap-3">
              <div class="w-10 h-10 rounded-lg bg-neutral-100 dark:bg-neutral-700 flex items-center justify-center shrink-0">
                <Icon :name="table.icon || 'ph:table'" class="w-5 h-5 text-neutral-600 dark:text-neutral-300" />
              </div>
              <div class="flex-1 min-w-0">
                <h3 class="font-medium text-neutral-900 dark:text-white group-hover:text-blue-500 transition-colors truncate">
                  {{ table.name }}
                </h3>
                <p v-if="table.description" class="text-sm text-neutral-500 dark:text-neutral-400 mt-1 line-clamp-2">
                  {{ table.description }}
                </p>
              </div>
            </div>

            <div class="flex items-center gap-4 mt-4 pt-3 border-t border-neutral-100 dark:border-neutral-700">
              <div class="flex items-center gap-1.5 text-sm text-neutral-500 dark:text-neutral-400">
                <Icon name="ph:columns" class="w-4 h-4" />
                <span>{{ table.columns?.length || 0 }} columns</span>
              </div>
              <div class="flex items-center gap-1.5 text-sm text-neutral-500 dark:text-neutral-400">
                <Icon name="ph:rows" class="w-4 h-4" />
                <span>{{ getRowCount(table) }} rows</span>
              </div>
            </div>
          </Link>
        </div>
      </div>
    </div>

    <!-- Create Table Modal -->
    <TableCreateModal
      v-if="showCreateModal"
      @close="showCreateModal = false"
      @create="handleCreateTable"
    />

    <!-- Delete Table Confirmation -->
    <ConfirmDialog
      :open="!!tableToDelete"
      title="Delete Table"
      :description="`Are you sure you want to delete '${tableToDelete?.name}'? This will permanently delete all columns, rows, and data in this table.`"
      variant="danger"
      confirm-label="Delete Table"
      @confirm="handleDeleteTable"
      @update:open="tableToDelete = null"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Link } from '@inertiajs/vue3'
import Icon from '@/Components/shared/Icon.vue'
import Button from '@/Components/shared/Button.vue'
import DropdownMenu from '@/Components/shared/DropdownMenu.vue'
import ConfirmDialog from '@/Components/shared/ConfirmDialog.vue'
import TableCreateModal from '@/Components/tables/TableCreateModal.vue'
import type { DataTable } from '@/types'

const tables = ref<DataTable[]>([])
const loading = ref(true)
const showCreateModal = ref(false)
const tableToDelete = ref<DataTable | null>(null)

// Handle both snake_case (from Laravel API) and camelCase
const getRowCount = (table: DataTable): number => {
  // @ts-expect-error - API may return snake_case
  return table.rows_count ?? table.rowsCount ?? 0
}

const fetchTables = async () => {
  loading.value = true
  try {
    const response = await fetch('/api/tables')
    tables.value = await response.json()
  } catch (error) {
    console.error('Failed to fetch tables:', error)
    tables.value = []
  } finally {
    loading.value = false
  }
}

const handleCreateTable = async (data: { name: string; description?: string; icon?: string }) => {
  try {
    const response = await fetch('/api/tables', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data),
    })
    const newTable = await response.json()
    tables.value.unshift(newTable)
    showCreateModal.value = false
  } catch (error) {
    console.error('Failed to create table:', error)
  }
}

const confirmDeleteTable = (table: DataTable) => {
  tableToDelete.value = table
}

const handleDeleteTable = async () => {
  if (!tableToDelete.value) return

  try {
    await fetch(`/api/tables/${tableToDelete.value.id}`, {
      method: 'DELETE',
    })
    tables.value = tables.value.filter(t => t.id !== tableToDelete.value!.id)
    tableToDelete.value = null
  } catch (error) {
    console.error('Failed to delete table:', error)
  }
}

onMounted(() => {
  fetchTables()
})
</script>
