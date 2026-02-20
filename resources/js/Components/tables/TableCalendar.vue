<template>
  <div class="h-full flex flex-col">
    <!-- Config prompt: no date column selected -->
    <div v-if="!dateColumn" class="flex flex-col items-center justify-center h-full text-center p-8">
      <Icon name="ph:calendar" class="w-12 h-12 text-neutral-300 dark:text-neutral-600 mb-3" />
      <p class="text-sm text-neutral-600 dark:text-neutral-400 mb-4">
        Select a date column to display on the calendar
      </p>
      <div v-if="dateColumns.length === 0" class="text-sm text-neutral-400">
        No date columns available. Add one to use Calendar view.
      </div>
      <div v-else class="flex flex-wrap gap-2 justify-center">
        <button
          v-for="col in dateColumns"
          :key="col.id"
          type="button"
          class="px-3 py-1.5 rounded-lg border border-neutral-200 dark:border-neutral-700 text-sm text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors"
          @click="selectDateColumn(col.id)"
        >
          {{ col.name }}
        </button>
      </div>
    </div>

    <template v-else>
      <!-- Month navigation -->
      <div class="shrink-0 flex items-center justify-between px-4 py-2 border-b border-neutral-200 dark:border-neutral-700">
        <Button variant="ghost" size="sm" icon-left="ph:caret-left" icon-only @click="prevMonth" />
        <h3 class="text-sm font-medium text-neutral-900 dark:text-white">
          {{ currentMonthLabel }}
        </h3>
        <div class="flex items-center gap-1">
          <Button variant="ghost" size="sm" @click="goToToday">Today</Button>
          <Button variant="ghost" size="sm" icon-left="ph:caret-right" icon-only @click="nextMonth" />
        </div>
      </div>

      <!-- Calendar grid -->
      <div class="flex-1 flex flex-col overflow-hidden">
        <!-- Day headers -->
        <div class="grid grid-cols-7 border-b border-neutral-200 dark:border-neutral-700">
          <div
            v-for="day in dayHeaders"
            :key="day"
            class="px-2 py-2 text-xs font-medium text-neutral-500 dark:text-neutral-400 text-center border-r border-neutral-200 dark:border-neutral-700 last:border-r-0"
          >
            {{ day }}
          </div>
        </div>

        <!-- Month days -->
        <div class="flex-1 grid grid-cols-7 grid-rows-6 overflow-hidden">
          <div
            v-for="day in monthDays"
            :key="day.key"
            :class="[
              'min-h-0 border-r border-b border-neutral-200 dark:border-neutral-700 p-1 overflow-hidden',
              day.isCurrentMonth
                ? 'bg-white dark:bg-neutral-900'
                : 'bg-neutral-50 dark:bg-neutral-900/50',
            ]"
          >
            <!-- Day number -->
            <span :class="[
              'inline-flex items-center justify-center w-6 h-6 text-xs rounded-full mb-0.5',
              day.isToday && 'bg-blue-500 text-white font-medium',
              !day.isToday && day.isCurrentMonth && 'text-neutral-900 dark:text-white',
              !day.isToday && !day.isCurrentMonth && 'text-neutral-400 dark:text-neutral-600',
            ]">
              {{ day.day }}
            </span>

            <!-- Row events -->
            <div class="space-y-0.5">
              <div
                v-for="row in day.rows.slice(0, 3)"
                :key="row.id"
                class="text-xs px-1.5 py-0.5 rounded bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 truncate cursor-default"
                :title="getRowTitle(row)"
              >
                {{ getRowTitle(row) }}
              </div>
              <span v-if="day.rows.length > 3" class="text-xs text-neutral-500 pl-1">
                +{{ day.rows.length - 3 }} more
              </span>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import Button from '@/Components/shared/Button.vue'
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

const currentDate = ref(new Date())
const dayHeaders = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']

const dateColumns = computed(() => props.columns.filter(c => c.type === 'date'))

const dateColumn = computed(() =>
  props.columns.find(c => c.id === props.config?.dateColumnId)
)

const titleColumn = computed(() =>
  props.columns.find(c => c.type === 'text')
)

const currentMonthLabel = computed(() =>
  currentDate.value.toLocaleDateString('en-US', { month: 'long', year: 'numeric' })
)

const isSameDay = (a: Date, b: Date): boolean =>
  a.getFullYear() === b.getFullYear() &&
  a.getMonth() === b.getMonth() &&
  a.getDate() === b.getDate()

const getRowsForDay = (date: Date): DataTableRow[] => {
  if (!dateColumn.value) return []
  return props.rows.filter(row => {
    const val = row.data[dateColumn.value!.id]
    if (!val) return false
    try {
      const rowDate = new Date(String(val))
      return isSameDay(rowDate, date)
    } catch {
      return false
    }
  })
}

const monthDays = computed(() => {
  const year = currentDate.value.getFullYear()
  const month = currentDate.value.getMonth()
  const firstDay = new Date(year, month, 1)
  const lastDay = new Date(year, month + 1, 0)
  const today = new Date()

  const days: Array<{
    key: string
    day: number
    date: Date
    isCurrentMonth: boolean
    isToday: boolean
    rows: DataTableRow[]
  }> = []

  // Previous month days
  const startDayOfWeek = firstDay.getDay()
  for (let i = startDayOfWeek - 1; i >= 0; i--) {
    const date = new Date(year, month, -i)
    days.push({
      key: `prev-${date.getDate()}`,
      day: date.getDate(),
      date,
      isCurrentMonth: false,
      isToday: isSameDay(date, today),
      rows: getRowsForDay(date),
    })
  }

  // Current month days
  for (let i = 1; i <= lastDay.getDate(); i++) {
    const date = new Date(year, month, i)
    days.push({
      key: `current-${i}`,
      day: i,
      date,
      isCurrentMonth: true,
      isToday: isSameDay(date, today),
      rows: getRowsForDay(date),
    })
  }

  // Next month days to fill 42 cells
  const remaining = 42 - days.length
  for (let i = 1; i <= remaining; i++) {
    const date = new Date(year, month + 1, i)
    days.push({
      key: `next-${i}`,
      day: i,
      date,
      isCurrentMonth: false,
      isToday: isSameDay(date, today),
      rows: getRowsForDay(date),
    })
  }

  return days
})

const getRowTitle = (row: DataTableRow): string => {
  if (!titleColumn.value) return 'Row'
  return String(row.data[titleColumn.value.id] || 'Untitled')
}

const selectDateColumn = (columnId: string) => {
  emit('configChange', { ...props.config, dateColumnId: columnId })
}

const prevMonth = () => {
  const d = new Date(currentDate.value)
  d.setMonth(d.getMonth() - 1)
  currentDate.value = d
}

const nextMonth = () => {
  const d = new Date(currentDate.value)
  d.setMonth(d.getMonth() + 1)
  currentDate.value = d
}

const goToToday = () => {
  currentDate.value = new Date()
}
</script>
