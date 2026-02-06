<template>
  <div
    :class="[
      'shrink-0 p-4 bg-neutral-50 dark:bg-neutral-900/50',
      embedded ? 'w-full' : 'w-64 border-r border-neutral-200 dark:border-neutral-700'
    ]"
  >
    <!-- Mini Calendar -->
    <div class="mb-6">
      <div class="flex items-center justify-between mb-3">
        <span class="text-sm font-medium text-neutral-900 dark:text-white">
          {{ miniCalendarLabel }}
        </span>
        <div class="flex gap-1">
          <button
            type="button"
            class="p-1 rounded hover:bg-neutral-200 dark:hover:bg-neutral-700 transition-colors"
            @click="prevMonth"
          >
            <Icon name="ph:caret-left" class="w-4 h-4 text-neutral-500 dark:text-neutral-400" />
          </button>
          <button
            type="button"
            class="p-1 rounded hover:bg-neutral-200 dark:hover:bg-neutral-700 transition-colors"
            @click="nextMonth"
          >
            <Icon name="ph:caret-right" class="w-4 h-4 text-neutral-500 dark:text-neutral-400" />
          </button>
        </div>
      </div>

      <!-- Day headers -->
      <div class="grid grid-cols-7 gap-0.5 mb-1">
        <div
          v-for="day in ['S', 'M', 'T', 'W', 'T', 'F', 'S']"
          :key="day"
          class="text-center text-xs font-medium text-neutral-400 dark:text-neutral-500 py-1"
        >
          {{ day }}
        </div>
      </div>

      <!-- Calendar grid -->
      <div class="grid grid-cols-7 gap-0.5">
        <button
          v-for="day in calendarDays"
          :key="day.key"
          type="button"
          :class="[
            'aspect-square flex items-center justify-center text-xs rounded-full transition-colors',
            day.isCurrentMonth
              ? 'text-neutral-900 dark:text-white'
              : 'text-neutral-300 dark:text-neutral-600',
            day.isToday && 'bg-blue-500 text-white',
            day.isSelected && !day.isToday && 'bg-neutral-200 dark:bg-neutral-700',
            !day.isToday && !day.isSelected && 'hover:bg-neutral-200 dark:hover:bg-neutral-700'
          ]"
          @click="selectDate(day.date)"
        >
          {{ day.day }}
        </button>
      </div>
    </div>

    <!-- Quick filters -->
    <div class="space-y-2">
      <h3 class="text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wide">
        Calendars
      </h3>
      <label class="flex items-center gap-2 cursor-pointer">
        <input
          :checked="filters.myEvents"
          type="checkbox"
          class="rounded border-neutral-300 dark:border-neutral-600 text-blue-500 focus:ring-blue-500"
          @change="updateFilter('myEvents', ($event.target as HTMLInputElement).checked)"
        >
        <span class="text-sm text-neutral-700 dark:text-neutral-300">My Events</span>
      </label>
      <label class="flex items-center gap-2 cursor-pointer">
        <input
          :checked="filters.attending"
          type="checkbox"
          class="rounded border-neutral-300 dark:border-neutral-600 text-green-500 focus:ring-green-500"
          @change="updateFilter('attending', ($event.target as HTMLInputElement).checked)"
        >
        <span class="text-sm text-neutral-700 dark:text-neutral-300">Attending</span>
      </label>
      <label class="flex items-center gap-2 cursor-pointer">
        <input
          :checked="filters.agentEvents"
          type="checkbox"
          class="rounded border-neutral-300 dark:border-neutral-600 text-purple-500 focus:ring-purple-500"
          @change="updateFilter('agentEvents', ($event.target as HTMLInputElement).checked)"
        >
        <span class="text-sm text-neutral-700 dark:text-neutral-300">Agent Events</span>
      </label>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import Icon from '@/Components/shared/Icon.vue'

interface CalendarFilters {
  myEvents: boolean
  attending: boolean
  agentEvents: boolean
}

const props = withDefaults(defineProps<{
  currentDate: Date
  selectedDate: Date | null
  filters: CalendarFilters
  embedded?: boolean
}>(), {
  embedded: false,
})

const emit = defineEmits<{
  selectDate: [date: Date]
  'update:filters': [filters: CalendarFilters]
}>()

const miniCalendarDate = ref(new Date())

const updateFilter = (key: keyof CalendarFilters, value: boolean) => {
  emit('update:filters', { ...props.filters, [key]: value })
}

const miniCalendarLabel = computed(() => {
  return miniCalendarDate.value.toLocaleDateString('en-US', { month: 'long', year: 'numeric' })
})

const calendarDays = computed(() => {
  const year = miniCalendarDate.value.getFullYear()
  const month = miniCalendarDate.value.getMonth()

  const firstDay = new Date(year, month, 1)
  const lastDay = new Date(year, month + 1, 0)

  const days: Array<{
    key: string
    day: number
    date: Date
    isCurrentMonth: boolean
    isToday: boolean
    isSelected: boolean
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
      isToday: false,
      isSelected: isSameDay(date, props.selectedDate),
    })
  }

  // Current month days
  const today = new Date()
  for (let i = 1; i <= lastDay.getDate(); i++) {
    const date = new Date(year, month, i)
    days.push({
      key: `current-${i}`,
      day: i,
      date,
      isCurrentMonth: true,
      isToday: isSameDay(date, today),
      isSelected: isSameDay(date, props.selectedDate),
    })
  }

  // Next month days
  const remainingDays = 42 - days.length
  for (let i = 1; i <= remainingDays; i++) {
    const date = new Date(year, month + 1, i)
    days.push({
      key: `next-${i}`,
      day: i,
      date,
      isCurrentMonth: false,
      isToday: false,
      isSelected: isSameDay(date, props.selectedDate),
    })
  }

  return days
})

const isSameDay = (date1: Date, date2: Date | null): boolean => {
  if (!date2) return false
  return (
    date1.getFullYear() === date2.getFullYear() &&
    date1.getMonth() === date2.getMonth() &&
    date1.getDate() === date2.getDate()
  )
}

const prevMonth = () => {
  const date = new Date(miniCalendarDate.value)
  date.setMonth(date.getMonth() - 1)
  miniCalendarDate.value = date
}

const nextMonth = () => {
  const date = new Date(miniCalendarDate.value)
  date.setMonth(date.getMonth() + 1)
  miniCalendarDate.value = date
}

const selectDate = (date: Date) => {
  emit('selectDate', date)
}
</script>
