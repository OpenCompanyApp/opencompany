<template>
  <div class="p-4 overflow-auto h-full">
    <!-- Empty state -->
    <div v-if="rows.length === 0" class="flex flex-col items-center justify-center h-full text-center">
      <Icon name="ph:squares-four" class="w-12 h-12 text-neutral-300 dark:text-neutral-600 mb-3" />
      <p class="text-neutral-500 dark:text-neutral-400 mb-2">No rows yet</p>
      <Button variant="ghost" size="sm" icon-left="ph:plus" @click="emit('addRow')">Add Row</Button>
    </div>

    <!-- Card grid -->
    <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
      <div
        v-for="row in rows"
        :key="row.id"
        class="group bg-white dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700 hover:border-neutral-300 dark:hover:border-neutral-600 hover:shadow-sm transition-all overflow-hidden"
      >
        <div class="p-4">
          <!-- Title -->
          <h3 class="font-medium text-neutral-900 dark:text-white truncate mb-3">
            {{ getRowTitle(row) }}
          </h3>

          <!-- Fields -->
          <div class="space-y-2">
            <div
              v-for="col in displayColumns"
              :key="col.id"
              class="flex items-start gap-2"
            >
              <span class="text-xs text-neutral-500 dark:text-neutral-400 w-20 shrink-0 truncate pt-0.5">
                {{ col.name }}
              </span>
              <div class="flex-1 min-w-0">
                <TableCell
                  :column="col"
                  :value="row.data[col.id]"
                  @update="(val: unknown) => emit('updateCell', row.id, col.id, val)"
                />
              </div>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="px-4 py-2 border-t border-neutral-100 dark:border-neutral-700/50 flex items-center justify-between">
          <span class="text-xs text-neutral-400">
            {{ row.createdAt ? new Date(row.createdAt).toLocaleDateString() : '' }}
          </span>
          <button
            type="button"
            class="p-1 rounded opacity-0 group-hover:opacity-100 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-all"
            @click="emit('deleteRow', row.id)"
          >
            <Icon name="ph:trash" class="w-3.5 h-3.5 text-neutral-400 hover:text-red-500" />
          </button>
        </div>
      </div>

      <!-- Add card -->
      <button
        type="button"
        class="flex flex-col items-center justify-center min-h-[200px] border-2 border-dashed border-neutral-200 dark:border-neutral-700 rounded-xl hover:border-neutral-400 dark:hover:border-neutral-500 hover:bg-neutral-50 dark:hover:bg-neutral-800 transition-all cursor-pointer"
        @click="emit('addRow')"
      >
        <Icon name="ph:plus" class="w-6 h-6 text-neutral-400 mb-2" />
        <span class="text-sm text-neutral-500">Add row</span>
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import Icon from '@/Components/shared/Icon.vue'
import Button from '@/Components/shared/Button.vue'
import TableCell from '@/Components/tables/TableCell.vue'
import type { DataTableColumn, DataTableRow, DataTableViewConfig } from '@/types'
import { computed } from 'vue'

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

const titleColumn = computed(() =>
  props.columns.find(c => c.id === props.config?.titleColumnId)
  || props.columns.find(c => c.type === 'text')
)

const displayColumns = computed(() =>
  props.columns
    .filter(c => c.id !== titleColumn.value?.id)
    .slice(0, 5)
)

const getRowTitle = (row: DataTableRow): string => {
  if (!titleColumn.value) return 'Untitled'
  return String(row.data[titleColumn.value.id] || 'Untitled')
}
</script>
