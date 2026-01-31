<template>
  <div class="h-full">
    <!-- Loading state -->
    <div v-if="loading" class="flex items-center justify-center h-full">
      <Icon name="ph:spinner" class="w-8 h-8 animate-spin text-neutral-400" />
    </div>

    <!-- Month view -->
    <div v-else-if="view === 'month'" class="h-full flex flex-col">
      <!-- Day headers -->
      <div class="grid grid-cols-7 border-b border-neutral-200 dark:border-neutral-700">
        <div
          v-for="day in ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']"
          :key="day"
          class="px-2 py-2 text-xs font-medium text-neutral-500 dark:text-neutral-400 text-center border-r border-neutral-200 dark:border-neutral-700 last:border-r-0"
        >
          {{ day }}
        </div>
      </div>

      <!-- Calendar grid -->
      <div class="flex-1 grid grid-cols-7 grid-rows-6">
        <div
          v-for="day in monthDays"
          :key="day.key"
          :class="[
            'min-h-[100px] border-r border-b border-neutral-200 dark:border-neutral-700 p-1 cursor-pointer transition-colors',
            day.isCurrentMonth ? 'bg-white dark:bg-neutral-900' : 'bg-neutral-50 dark:bg-neutral-900/50',
            'hover:bg-neutral-50 dark:hover:bg-neutral-800/50'
          ]"
          @click="handleSlotClick(day.date)"
        >
          <div class="flex items-center justify-between mb-1">
            <span
              :class="[
                'inline-flex items-center justify-center w-6 h-6 text-sm rounded-full',
                day.isToday && 'bg-blue-500 text-white',
                !day.isToday && day.isCurrentMonth && 'text-neutral-900 dark:text-white',
                !day.isToday && !day.isCurrentMonth && 'text-neutral-400 dark:text-neutral-600'
              ]"
            >
              {{ day.day }}
            </span>
          </div>
          <div class="space-y-0.5 overflow-hidden">
            <CalendarEventItem
              v-for="event in day.events.slice(0, 3)"
              :key="event.id"
              :event="event"
              size="sm"
              @click.stop="handleEventClick(event)"
            />
            <button
              v-if="day.events.length > 3"
              type="button"
              class="text-xs text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-300"
              @click.stop="showMoreEvents(day)"
            >
              +{{ day.events.length - 3 }} more
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Week view -->
    <div v-else-if="view === 'week'" class="h-full flex flex-col">
      <!-- Day headers with dates -->
      <div class="grid grid-cols-8 border-b border-neutral-200 dark:border-neutral-700">
        <div class="w-16 shrink-0" />
        <div
          v-for="day in weekDays"
          :key="day.key"
          class="px-2 py-2 text-center border-l border-neutral-200 dark:border-neutral-700"
        >
          <div class="text-xs font-medium text-neutral-500 dark:text-neutral-400">
            {{ day.dayName }}
          </div>
          <div
            :class="[
              'inline-flex items-center justify-center w-8 h-8 text-lg font-semibold rounded-full mt-1',
              day.isToday && 'bg-blue-500 text-white',
              !day.isToday && 'text-neutral-900 dark:text-white'
            ]"
          >
            {{ day.day }}
          </div>
        </div>
      </div>

      <!-- Time grid -->
      <div class="flex-1 overflow-auto">
        <div class="grid grid-cols-8 min-h-full">
          <!-- Time column -->
          <div class="w-16 shrink-0">
            <div
              v-for="hour in hours"
              :key="hour"
              class="h-12 text-xs text-neutral-400 dark:text-neutral-500 text-right pr-2 pt-0 relative"
            >
              <span class="absolute -top-2 right-2">{{ formatHour(hour) }}</span>
            </div>
          </div>

          <!-- Day columns -->
          <div
            v-for="day in weekDays"
            :key="day.key"
            class="border-l border-neutral-200 dark:border-neutral-700 relative"
          >
            <div
              v-for="hour in hours"
              :key="hour"
              class="h-12 border-b border-neutral-100 dark:border-neutral-800 cursor-pointer hover:bg-neutral-50 dark:hover:bg-neutral-800/50"
              @click="handleSlotClick(getDateAtHour(day.date, hour))"
            />
            <!-- Events -->
            <CalendarEventItem
              v-for="event in getEventsForDay(day.date)"
              :key="event.id"
              :event="event"
              :style="getEventStyle(event)"
              class="absolute left-0.5 right-0.5"
              @click.stop="handleEventClick(event)"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- Day view -->
    <div v-else class="h-full flex flex-col">
      <div class="flex-1 overflow-auto">
        <div class="flex min-h-full">
          <!-- Time column -->
          <div class="w-16 shrink-0">
            <div
              v-for="hour in hours"
              :key="hour"
              class="h-14 text-xs text-neutral-400 dark:text-neutral-500 text-right pr-2 relative"
            >
              <span class="absolute -top-2 right-2">{{ formatHour(hour) }}</span>
            </div>
          </div>

          <!-- Day column -->
          <div class="flex-1 border-l border-neutral-200 dark:border-neutral-700 relative">
            <div
              v-for="hour in hours"
              :key="hour"
              class="h-14 border-b border-neutral-100 dark:border-neutral-800 cursor-pointer hover:bg-neutral-50 dark:hover:bg-neutral-800/50"
              @click="handleSlotClick(getDateAtHour(currentDate, hour))"
            />
            <!-- Events -->
            <CalendarEventItem
              v-for="event in dayEvents"
              :key="event.id"
              :event="event"
              :style="getEventStyleDay(event)"
              class="absolute left-1 right-1"
              @click.stop="handleEventClick(event)"
            />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import CalendarEventItem from '@/Components/calendar/CalendarEventItem.vue'
import type { CalendarEvent } from '@/types'

const props = defineProps<{
  view: 'month' | 'week' | 'day'
  currentDate: Date
  events: CalendarEvent[]
  loading: boolean
}>()

const emit = defineEmits<{
  eventClick: [event: CalendarEvent]
  slotClick: [date: Date]
}>()

const hours = Array.from({ length: 24 }, (_, i) => i)

const monthDays = computed(() => {
  const year = props.currentDate.getFullYear()
  const month = props.currentDate.getMonth()

  const firstDay = new Date(year, month, 1)
  const lastDay = new Date(year, month + 1, 0)

  const days: Array<{
    key: string
    day: number
    date: Date
    isCurrentMonth: boolean
    isToday: boolean
    events: CalendarEvent[]
  }> = []

  const today = new Date()

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
      events: getEventsForDay(date),
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
      events: getEventsForDay(date),
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
      isToday: isSameDay(date, today),
      events: getEventsForDay(date),
    })
  }

  return days
})

const weekDays = computed(() => {
  const weekStart = new Date(props.currentDate)
  weekStart.setDate(props.currentDate.getDate() - props.currentDate.getDay())

  const today = new Date()
  const days = []

  for (let i = 0; i < 7; i++) {
    const date = new Date(weekStart)
    date.setDate(weekStart.getDate() + i)
    days.push({
      key: `week-${i}`,
      day: date.getDate(),
      dayName: date.toLocaleDateString('en-US', { weekday: 'short' }),
      date,
      isToday: isSameDay(date, today),
    })
  }

  return days
})

const dayEvents = computed(() => {
  return getEventsForDay(props.currentDate)
})

const isSameDay = (date1: Date, date2: Date): boolean => {
  return (
    date1.getFullYear() === date2.getFullYear() &&
    date1.getMonth() === date2.getMonth() &&
    date1.getDate() === date2.getDate()
  )
}

const getEventsForDay = (date: Date): CalendarEvent[] => {
  return props.events.filter((event) => {
    const eventDate = new Date(event.startAt)
    return isSameDay(eventDate, date)
  })
}

const formatHour = (hour: number): string => {
  if (hour === 0) return '12 AM'
  if (hour === 12) return '12 PM'
  if (hour < 12) return `${hour} AM`
  return `${hour - 12} PM`
}

const getDateAtHour = (date: Date, hour: number): Date => {
  const newDate = new Date(date)
  newDate.setHours(hour, 0, 0, 0)
  return newDate
}

const getEventStyle = (event: CalendarEvent): Record<string, string> => {
  const startDate = new Date(event.startAt)
  const endDate = event.endAt ? new Date(event.endAt) : new Date(startDate.getTime() + 60 * 60 * 1000)

  const startHour = startDate.getHours() + startDate.getMinutes() / 60
  const endHour = endDate.getHours() + endDate.getMinutes() / 60

  const top = startHour * 48 // 48px per hour (12 * 4)
  const height = (endHour - startHour) * 48

  return {
    top: `${top}px`,
    height: `${Math.max(height, 24)}px`,
  }
}

const getEventStyleDay = (event: CalendarEvent): Record<string, string> => {
  const startDate = new Date(event.startAt)
  const endDate = event.endAt ? new Date(event.endAt) : new Date(startDate.getTime() + 60 * 60 * 1000)

  const startHour = startDate.getHours() + startDate.getMinutes() / 60
  const endHour = endDate.getHours() + endDate.getMinutes() / 60

  const top = startHour * 56 // 56px per hour (14 * 4)
  const height = (endHour - startHour) * 56

  return {
    top: `${top}px`,
    height: `${Math.max(height, 28)}px`,
  }
}

const showMoreEvents = (day: { date: Date; events: CalendarEvent[] }) => {
  // Could open a popover with all events for this day
  console.log('Show more events for', day.date, day.events)
}

const handleEventClick = (event: CalendarEvent) => {
  emit('eventClick', event)
}

const handleSlotClick = (date: Date) => {
  emit('slotClick', date)
}
</script>
