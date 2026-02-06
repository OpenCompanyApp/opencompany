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
          v-for="(day, i) in dayHeaders"
          :key="i"
          class="px-1 md:px-2 py-2 text-xs font-medium text-neutral-500 dark:text-neutral-400 text-center border-r border-neutral-200 dark:border-neutral-700 last:border-r-0"
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
            'min-h-[60px] md:min-h-[100px] border-r border-b border-neutral-200 dark:border-neutral-700 p-0.5 md:p-1 cursor-pointer transition-colors',
            day.isCurrentMonth ? 'bg-white dark:bg-neutral-900' : 'bg-neutral-50 dark:bg-neutral-900/50',
            'hover:bg-neutral-50 dark:hover:bg-neutral-800/50'
          ]"
          @click="handleSlotClick(day.date)"
        >
          <div class="flex items-center justify-between mb-0.5 md:mb-1">
            <span
              :class="[
                'inline-flex items-center justify-center w-5 h-5 md:w-6 md:h-6 text-xs md:text-sm rounded-full',
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
              v-for="event in day.events.slice(0, maxEventsPerCell)"
              :key="event.id"
              :event="event"
              size="sm"
              @click.stop="handleEventClick(event)"
            />
            <button
              v-if="day.events.length > maxEventsPerCell"
              type="button"
              class="text-xs text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-300 relative"
              @click.stop="openMorePopover(day, $event)"
            >
              +{{ day.events.length - maxEventsPerCell }} more
            </button>
          </div>
        </div>
      </div>

      <!-- More events popover -->
      <Teleport to="body">
        <div
          v-if="morePopover"
          class="fixed inset-0 z-40"
          @click="morePopover = null"
        />
        <div
          v-if="morePopover"
          class="fixed z-50 bg-white dark:bg-neutral-800 rounded-lg shadow-lg border border-neutral-200 dark:border-neutral-700 p-2 w-56"
          :style="{ top: `${morePopover.y}px`, left: `${morePopover.x}px` }"
        >
          <div class="text-xs font-medium text-neutral-500 dark:text-neutral-400 mb-1.5 px-1">
            {{ morePopover.date.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' }) }}
          </div>
          <div class="space-y-0.5 max-h-48 overflow-auto">
            <CalendarEventItem
              v-for="event in morePopover.events"
              :key="event.id"
              :event="event"
              size="sm"
              :show-time="true"
              @click="handleEventClick(event); morePopover = null"
            />
          </div>
        </div>
      </Teleport>
    </div>

    <!-- Week view -->
    <div v-else-if="view === 'week'" class="h-full flex flex-col">
      <!-- Day headers with dates -->
      <div class="overflow-x-auto">
        <div class="grid grid-cols-8 border-b border-neutral-200 dark:border-neutral-700" :style="weekGridStyle">
          <div class="w-12 md:w-16 shrink-0" />
          <div
            v-for="day in weekDays"
            :key="day.key"
            class="px-1 md:px-2 py-2 text-center border-l border-neutral-200 dark:border-neutral-700"
          >
            <div class="text-xs font-medium text-neutral-500 dark:text-neutral-400">
              {{ day.dayName }}
            </div>
            <div
              :class="[
                'inline-flex items-center justify-center w-7 h-7 md:w-8 md:h-8 text-base md:text-lg font-semibold rounded-full mt-1',
                day.isToday && 'bg-blue-500 text-white',
                !day.isToday && 'text-neutral-900 dark:text-white'
              ]"
            >
              {{ day.day }}
            </div>
          </div>
        </div>
      </div>

      <!-- All-day events banner -->
      <div
        v-if="weekAllDayEvents.length > 0"
        class="overflow-x-auto"
      >
        <div class="grid grid-cols-8 border-b border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-900/50" :style="weekGridStyle">
          <div class="w-12 md:w-16 shrink-0 flex items-center justify-end pr-2">
            <span class="text-[10px] text-neutral-400 dark:text-neutral-500">all-day</span>
          </div>
          <div
            v-for="day in weekDays"
            :key="`allday-${day.key}`"
            class="border-l border-neutral-200 dark:border-neutral-700 px-0.5 py-1 space-y-0.5"
          >
            <CalendarEventItem
              v-for="event in getAllDayEventsForDay(day.date)"
              :key="event.id"
              :event="event"
              size="sm"
              @click.stop="handleEventClick(event)"
            />
          </div>
        </div>
      </div>

      <!-- Time grid -->
      <div class="flex-1 overflow-auto">
        <div class="grid grid-cols-8 min-h-full relative" :style="weekGridStyle">
          <!-- Time column -->
          <div class="w-12 md:w-16 shrink-0">
            <div
              v-for="hour in hours"
              :key="hour"
              class="h-12 text-[10px] md:text-xs text-neutral-400 dark:text-neutral-500 text-right pr-1 md:pr-2 pt-0 relative"
            >
              <span class="absolute -top-2 right-1 md:right-2">{{ formatHour(hour) }}</span>
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
              v-for="event in getTimedEventsForDay(day.date)"
              :key="event.id"
              :event="event"
              :style="getEventStyle(event)"
              class="absolute left-0.5 right-0.5"
              @click.stop="handleEventClick(event)"
            />
            <!-- Current time indicator -->
            <div
              v-if="day.isToday"
              class="absolute left-0 right-0 z-10 pointer-events-none"
              :style="{ top: `${currentTimePosition}px` }"
            >
              <div class="flex items-center">
                <div class="w-2 h-2 rounded-full bg-red-500 -ml-1" />
                <div class="flex-1 h-px bg-red-500" />
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Day view -->
    <div v-else-if="view === 'day'" class="h-full flex flex-col">
      <!-- All-day events banner -->
      <div
        v-if="dayAllDayEvents.length > 0"
        class="flex border-b border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-900/50"
      >
        <div class="w-12 md:w-16 shrink-0 flex items-center justify-end pr-2">
          <span class="text-[10px] text-neutral-400 dark:text-neutral-500">all-day</span>
        </div>
        <div class="flex-1 border-l border-neutral-200 dark:border-neutral-700 px-1 py-1 space-y-0.5">
          <CalendarEventItem
            v-for="event in dayAllDayEvents"
            :key="event.id"
            :event="event"
            size="sm"
            @click.stop="handleEventClick(event)"
          />
        </div>
      </div>

      <div class="flex-1 overflow-auto">
        <div class="flex min-h-full">
          <!-- Time column -->
          <div class="w-12 md:w-16 shrink-0">
            <div
              v-for="hour in hours"
              :key="hour"
              class="h-14 text-[10px] md:text-xs text-neutral-400 dark:text-neutral-500 text-right pr-1 md:pr-2 relative"
            >
              <span class="absolute -top-2 right-1 md:right-2">{{ formatHour(hour) }}</span>
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
              v-for="event in dayTimedEvents"
              :key="event.id"
              :event="event"
              :style="getEventStyleDay(event)"
              class="absolute left-1 right-1"
              @click.stop="handleEventClick(event)"
            />
            <!-- Current time indicator -->
            <div
              v-if="isViewingToday"
              class="absolute left-0 right-0 z-10 pointer-events-none"
              :style="{ top: `${currentTimePositionDay}px` }"
            >
              <div class="flex items-center">
                <div class="w-2 h-2 rounded-full bg-red-500 -ml-1" />
                <div class="flex-1 h-px bg-red-500" />
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Schedule (agenda) view -->
    <div v-else class="h-full overflow-auto">
      <div v-if="scheduleGroups.length === 0" class="flex flex-col items-center justify-center h-full text-neutral-400 dark:text-neutral-500">
        <Icon name="ph:calendar-blank" class="w-12 h-12 mb-2" />
        <p class="text-sm">No events in this period</p>
      </div>
      <div v-else class="max-w-3xl mx-auto py-3 md:py-4 px-4 md:px-6 space-y-4 md:space-y-6">
        <div v-for="group in scheduleGroups" :key="group.key">
          <!-- Date header -->
          <div class="flex items-center gap-3 mb-2">
            <div
              :class="[
                'w-10 h-10 md:w-12 md:h-12 rounded-xl flex flex-col items-center justify-center shrink-0',
                group.isToday
                  ? 'bg-blue-500 text-white'
                  : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-300'
              ]"
            >
              <span class="text-[9px] md:text-[10px] font-medium uppercase leading-none">{{ group.dayName }}</span>
              <span class="text-base md:text-lg font-semibold leading-tight">{{ group.day }}</span>
            </div>
            <div>
              <span class="text-sm font-medium text-neutral-900 dark:text-white">
                {{ group.label }}
              </span>
              <span v-if="group.isToday" class="ml-2 text-xs font-medium text-blue-500">Today</span>
              <span v-else-if="group.isTomorrow" class="ml-2 text-xs font-medium text-neutral-400 dark:text-neutral-500">Tomorrow</span>
            </div>
          </div>

          <!-- Events for this date -->
          <div class="ml-[52px] md:ml-[60px] space-y-1.5">
            <div
              v-for="event in group.events"
              :key="event.id"
              class="flex items-start gap-3 p-2.5 md:p-3 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800/50 hover:bg-neutral-50 dark:hover:bg-neutral-800 cursor-pointer transition-colors"
              @click="handleEventClick(event)"
            >
              <div
                class="w-1 h-10 rounded-full shrink-0 mt-0.5"
                :style="{ backgroundColor: eventColorValue(event.color) }"
              />
              <div class="flex-1 min-w-0">
                <div class="text-sm font-medium text-neutral-900 dark:text-white truncate">
                  {{ event.title }}
                </div>
                <div class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
                  {{ event.allDay ? 'All day' : formatEventTime(event) }}
                </div>
                <div v-if="event.location" class="text-xs text-neutral-400 dark:text-neutral-500 mt-0.5 flex items-center gap-1">
                  <Icon name="ph:map-pin" class="w-3 h-3" />
                  {{ event.location }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import CalendarEventItem from '@/Components/calendar/CalendarEventItem.vue'
import type { CalendarEvent } from '@/types'
import { getCalendarColorHex } from './calendar-colors'

const props = withDefaults(defineProps<{
  view: 'month' | 'week' | 'day' | 'schedule'
  currentDate: Date
  events: CalendarEvent[]
  loading: boolean
  isMobile?: boolean
}>(), {
  isMobile: false,
})

const emit = defineEmits<{
  eventClick: [event: CalendarEvent]
  slotClick: [date: Date]
}>()

const hours = Array.from({ length: 24 }, (_, i) => i)

// Responsive helpers
const dayHeaders = computed(() => {
  if (props.isMobile) {
    return ['S', 'M', 'T', 'W', 'T', 'F', 'S']
  }
  return ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']
})

const maxEventsPerCell = computed(() => props.isMobile ? 2 : 3)

const weekGridStyle = computed(() => {
  if (props.isMobile) {
    return { minWidth: '700px' }
  }
  return {}
})

// Current time tracking
const now = ref(new Date())
let timeInterval: ReturnType<typeof setInterval> | null = null

onMounted(() => {
  timeInterval = setInterval(() => {
    now.value = new Date()
  }, 60_000)
})

onUnmounted(() => {
  if (timeInterval) clearInterval(timeInterval)
})

const currentTimePosition = computed(() => {
  const hours = now.value.getHours() + now.value.getMinutes() / 60
  return hours * 48 // 48px per hour (h-12)
})

const currentTimePositionDay = computed(() => {
  const hours = now.value.getHours() + now.value.getMinutes() / 60
  return hours * 56 // 56px per hour (h-14)
})

const isViewingToday = computed(() => {
  return isSameDay(props.currentDate, now.value)
})

// More events popover state
const morePopover = ref<{ x: number; y: number; date: Date; events: CalendarEvent[] } | null>(null)

const openMorePopover = (day: { date: Date; events: CalendarEvent[] }, event: MouseEvent) => {
  const target = event.currentTarget as HTMLElement
  const rect = target.getBoundingClientRect()
  morePopover.value = {
    x: Math.min(rect.left, window.innerWidth - 240),
    y: rect.bottom + 4,
    date: day.date,
    events: day.events,
  }
}

// All-day event helpers
const isAllDayEvent = (event: CalendarEvent): boolean => {
  return event.allDay
}

const weekAllDayEvents = computed(() => {
  return props.events.filter(isAllDayEvent)
})

const dayAllDayEvents = computed(() => {
  return getEventsForDay(props.currentDate).filter(isAllDayEvent)
})

const dayTimedEvents = computed(() => {
  return getEventsForDay(props.currentDate).filter((e) => !isAllDayEvent(e))
})

const getAllDayEventsForDay = (date: Date): CalendarEvent[] => {
  return getEventsForDay(date).filter(isAllDayEvent)
}

const getTimedEventsForDay = (date: Date): CalendarEvent[] => {
  return getEventsForDay(date).filter((e) => !isAllDayEvent(e))
}

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

  const top = startHour * 48 // 48px per hour (h-12)
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

  const top = startHour * 56 // 56px per hour (h-14)
  const height = (endHour - startHour) * 56

  return {
    top: `${top}px`,
    height: `${Math.max(height, 28)}px`,
  }
}

// Schedule view helpers
const eventColorValue = (color?: string): string => getCalendarColorHex(color)

const formatEventTime = (event: CalendarEvent): string => {
  const start = new Date(event.startAt)
  const startStr = start.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })
  if (event.endAt) {
    const end = new Date(event.endAt)
    const endStr = end.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })
    return `${startStr} – ${endStr}`
  }
  return startStr
}

interface ScheduleGroup {
  key: string
  date: Date
  day: number
  dayName: string
  label: string
  isToday: boolean
  isTomorrow: boolean
  events: CalendarEvent[]
}

const scheduleGroups = computed<ScheduleGroup[]>(() => {
  const sorted = [...props.events].sort((a, b) => {
    // All-day events first within same day, then by start time
    const dateA = new Date(a.startAt)
    const dateB = new Date(b.startAt)
    if (isSameDay(dateA, dateB)) {
      if (a.allDay && !b.allDay) return -1
      if (!a.allDay && b.allDay) return 1
    }
    return dateA.getTime() - dateB.getTime()
  })

  const today = new Date()
  const tomorrow = new Date(today)
  tomorrow.setDate(today.getDate() + 1)

  const groupMap = new Map<string, ScheduleGroup>()

  for (const event of sorted) {
    const date = new Date(event.startAt)
    const key = `${date.getFullYear()}-${date.getMonth()}-${date.getDate()}`

    if (!groupMap.has(key)) {
      groupMap.set(key, {
        key,
        date,
        day: date.getDate(),
        dayName: date.toLocaleDateString('en-US', { weekday: 'short' }),
        label: date.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' }),
        isToday: isSameDay(date, today),
        isTomorrow: isSameDay(date, tomorrow),
        events: [],
      })
    }

    groupMap.get(key)!.events.push(event)
  }

  return Array.from(groupMap.values())
})

const handleEventClick = (event: CalendarEvent) => {
  emit('eventClick', event)
}

const handleSlotClick = (date: Date) => {
  emit('slotClick', date)
}
</script>
