<template>
  <div class="h-full flex flex-col">
    <!-- Header -->
    <header class="shrink-0 border-b border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900">
      <!-- Top row: nav + actions -->
      <div class="flex items-center justify-between px-4 md:px-6 py-3 md:py-4">
        <div class="flex items-center gap-2 md:gap-4 min-w-0">
          <!-- Mobile sidebar toggle -->
          <button
            v-if="isMobile"
            type="button"
            class="p-1.5 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors"
            @click="showMobileSidebar = true"
          >
            <Icon name="ph:funnel" class="w-5 h-5 text-neutral-600 dark:text-neutral-300" />
          </button>

          <div class="flex items-center gap-1 md:gap-2">
            <button
              type="button"
              class="p-1.5 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors"
              @click="prevPeriod"
            >
              <Icon name="ph:caret-left" class="w-5 h-5 text-neutral-600 dark:text-neutral-300" />
            </button>
            <button
              type="button"
              class="p-1.5 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors"
              @click="nextPeriod"
            >
              <Icon name="ph:caret-right" class="w-5 h-5 text-neutral-600 dark:text-neutral-300" />
            </button>
          </div>
          <h1 class="text-base md:text-xl font-semibold text-neutral-900 dark:text-white truncate">
            {{ currentPeriodLabel }}
          </h1>
          <button
            type="button"
            class="hidden md:inline-flex px-3 py-1.5 text-sm text-neutral-600 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-lg transition-colors"
            @click="goToToday"
          >
            Today
          </button>
        </div>
        <div class="flex items-center gap-2 md:gap-3">
          <!-- View toggle: hidden on mobile, shown in bottom row -->
          <div class="hidden md:flex rounded-lg border border-neutral-200 dark:border-neutral-700 overflow-hidden">
            <button
              v-for="v in viewOptions"
              :key="v"
              type="button"
              :class="[
                'px-3 py-1.5 text-sm font-medium transition-colors capitalize',
                view === v
                  ? 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900'
                  : 'text-neutral-600 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800'
              ]"
              @click="view = v"
            >
              {{ v }}
            </button>
          </div>
          <div class="flex rounded-lg border border-neutral-200 dark:border-neutral-700 overflow-hidden">
            <button
              type="button"
              class="px-2.5 md:px-3 py-1.5 text-sm font-medium text-neutral-600 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors"
              title="Subscribe / Import URL"
              @click="showFeedModal = true"
            >
              <Icon name="ph:rss" class="w-4 h-4" />
            </button>
            <button
              type="button"
              class="px-2.5 md:px-3 py-1.5 text-sm font-medium text-neutral-600 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors border-l border-neutral-200 dark:border-neutral-700"
              title="Export .ics"
              @click="handleExport"
            >
              <Icon name="ph:download-simple" class="w-4 h-4" />
            </button>
            <button
              type="button"
              class="px-2.5 md:px-3 py-1.5 text-sm font-medium text-neutral-600 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors border-l border-neutral-200 dark:border-neutral-700"
              title="Import .ics file"
              @click="fileInput?.click()"
            >
              <Icon name="ph:upload-simple" class="w-4 h-4" />
            </button>
          </div>
          <input
            ref="fileInput"
            type="file"
            accept=".ics"
            class="hidden"
            @change="handleImport"
          >
          <Button size="sm" @click="showCreateModal = true">
            <Icon name="ph:plus" class="w-4 h-4" />
            <span class="hidden md:inline ml-1.5">New Event</span>
          </Button>
        </div>
      </div>

      <!-- Mobile bottom row: view toggle + today -->
      <div v-if="isMobile" class="flex items-center gap-2 px-4 pb-3">
        <div class="flex-1 grid grid-cols-4 rounded-lg border border-neutral-200 dark:border-neutral-700 overflow-hidden">
          <button
            v-for="v in viewOptions"
            :key="v"
            type="button"
            :class="[
              'px-2 py-1.5 text-xs font-medium transition-colors capitalize text-center',
              view === v
                ? 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900'
                : 'text-neutral-600 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800'
            ]"
            @click="view = v"
          >
            {{ v }}
          </button>
        </div>
        <button
          type="button"
          class="px-3 py-1.5 text-xs font-medium text-neutral-600 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-lg border border-neutral-200 dark:border-neutral-700 transition-colors"
          @click="goToToday"
        >
          Today
        </button>
      </div>
    </header>

    <!-- Calendar Grid -->
    <div class="flex-1 overflow-hidden flex">
      <!-- Desktop Sidebar -->
      <CalendarSidebar
        class="hidden md:block"
        :current-date="currentDate"
        :selected-date="selectedDate"
        v-model:filters="filters"
        @select-date="handleDateSelect"
      />

      <!-- Main calendar view -->
      <div class="flex-1 overflow-auto bg-white dark:bg-neutral-900">
        <CalendarView
          :view="view"
          :current-date="currentDate"
          :events="filteredEvents"
          :loading="loading"
          :is-mobile="isMobile"
          @event-click="handleEventClick"
          @slot-click="handleSlotClick"
        />
      </div>
    </div>

    <!-- Mobile Sidebar Slideover -->
    <Slideover v-if="isMobile" v-model:open="showMobileSidebar" side="left" size="sm" :show-close="false">
      <template #header>
        <div class="flex items-center justify-between w-full">
          <span class="font-semibold text-neutral-900 dark:text-white">Filters</span>
          <button
            type="button"
            class="p-2 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors"
            @click="showMobileSidebar = false"
          >
            <Icon name="ph:x" class="w-5 h-5 text-neutral-500 dark:text-neutral-300" />
          </button>
        </div>
      </template>
      <template #body>
        <div class="-mx-6 -my-4 h-full">
          <CalendarSidebar
            :current-date="currentDate"
            :selected-date="selectedDate"
            v-model:filters="filters"
            :embedded="true"
            @select-date="(date: Date) => { handleDateSelect(date); showMobileSidebar = false }"
          />
        </div>
      </template>
    </Slideover>

    <!-- Create/Edit Event Modal -->
    <CalendarEventModal
      v-if="showCreateModal"
      :event="selectedEvent"
      :initial-date="selectedDate"
      @close="closeModal"
      @save="handleSaveEvent"
      @delete="handleDeleteEvent"
    />

    <!-- Feed Management Modal -->
    <CalendarFeedModal
      v-if="showFeedModal"
      @close="showFeedModal = false"
      @imported="fetchEvents"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import Button from '@/Components/shared/Button.vue'
import Slideover from '@/Components/shared/Slideover.vue'
import CalendarSidebar from '@/Components/calendar/CalendarSidebar.vue'
import CalendarView from '@/Components/calendar/CalendarView.vue'
import CalendarEventModal from '@/Components/calendar/CalendarEventModal.vue'
import CalendarFeedModal from '@/Components/calendar/CalendarFeedModal.vue'
import { useApi } from '@/composables/useApi'
import { useIsMobile } from '@/composables/useMediaQuery'
import type { CalendarEvent } from '@/types'

type ViewType = 'month' | 'week' | 'day' | 'schedule'

const viewOptions: ViewType[] = ['month', 'week', 'day', 'schedule']

const { fetchCalendarEvents, createCalendarEvent, updateCalendarEvent, deleteCalendarEvent, importCalendarEvents } = useApi()

const isMobile = useIsMobile()
const fileInput = ref<HTMLInputElement | null>(null)
const view = ref<ViewType>('month')
const currentDate = ref(new Date())
const selectedDate = ref<Date | null>(null)
const selectedEvent = ref<CalendarEvent | null>(null)
const showCreateModal = ref(false)
const showFeedModal = ref(false)
const showMobileSidebar = ref(false)
const events = ref<CalendarEvent[]>([])
const loading = ref(false)

const filters = reactive({
  myEvents: true,
  attending: true,
  agentEvents: true,
})

const filteredEvents = computed(() => {
  if (filters.myEvents && filters.attending && filters.agentEvents) {
    return events.value
  }
  return events.value.filter((event) => {
    const isAgentEvent = event.creator?.type === 'agent'
    if (isAgentEvent) return filters.agentEvents

    // Check if current user is an attendee
    const isAttending = event.attendees?.some((a) => a.userId !== event.createdBy)
    if (isAttending && !filters.attending) return false

    return filters.myEvents
  })
})

const currentPeriodLabel = computed(() => {
  const date = currentDate.value
  const options: Intl.DateTimeFormatOptions = { year: 'numeric', month: 'long' }

  if (view.value === 'week') {
    const weekStart = new Date(date)
    weekStart.setDate(date.getDate() - date.getDay())
    const weekEnd = new Date(weekStart)
    weekEnd.setDate(weekStart.getDate() + 6)

    if (weekStart.getMonth() === weekEnd.getMonth()) {
      return `${weekStart.toLocaleDateString('en-US', { month: 'long' })} ${weekStart.getDate()}-${weekEnd.getDate()}, ${weekStart.getFullYear()}`
    }
    return `${weekStart.toLocaleDateString('en-US', { month: 'short' })} ${weekStart.getDate()} - ${weekEnd.toLocaleDateString('en-US', { month: 'short' })} ${weekEnd.getDate()}, ${weekStart.getFullYear()}`
  }

  if (view.value === 'day') {
    return date.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })
  }

  if (view.value === 'schedule') {
    const end = new Date(date)
    end.setDate(end.getDate() + 29)
    return `${date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })} — ${end.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}`
  }

  return date.toLocaleDateString('en-US', options)
})

const getDateRange = () => {
  const start = new Date(currentDate.value)
  const end = new Date(currentDate.value)

  if (view.value === 'month') {
    start.setDate(1)
    start.setDate(start.getDate() - start.getDay())
    end.setMonth(end.getMonth() + 1, 0)
    end.setDate(end.getDate() + (6 - end.getDay()))
  } else if (view.value === 'week') {
    start.setDate(start.getDate() - start.getDay())
    end.setDate(start.getDate() + 6)
  } else if (view.value === 'schedule') {
    end.setDate(end.getDate() + 30)
  }

  return { start: start.toISOString(), end: end.toISOString() }
}

const prevPeriod = () => {
  const date = new Date(currentDate.value)
  if (view.value === 'month') {
    date.setMonth(date.getMonth() - 1)
  } else if (view.value === 'week') {
    date.setDate(date.getDate() - 7)
  } else if (view.value === 'schedule') {
    date.setDate(date.getDate() - 30)
  } else {
    date.setDate(date.getDate() - 1)
  }
  currentDate.value = date
}

const nextPeriod = () => {
  const date = new Date(currentDate.value)
  if (view.value === 'month') {
    date.setMonth(date.getMonth() + 1)
  } else if (view.value === 'week') {
    date.setDate(date.getDate() + 7)
  } else if (view.value === 'schedule') {
    date.setDate(date.getDate() + 30)
  } else {
    date.setDate(date.getDate() + 1)
  }
  currentDate.value = date
}

const goToToday = () => {
  currentDate.value = new Date()
}

const handleDateSelect = (date: Date) => {
  selectedDate.value = date
  currentDate.value = date
}

const handleEventClick = (event: CalendarEvent) => {
  selectedEvent.value = event
  showCreateModal.value = true
}

const handleSlotClick = (date: Date) => {
  selectedDate.value = date
  selectedEvent.value = null
  showCreateModal.value = true
}

const closeModal = () => {
  showCreateModal.value = false
  selectedEvent.value = null
}

const fetchEvents = async () => {
  loading.value = true
  try {
    const range = getDateRange()
    const { data, promise } = fetchCalendarEvents(range)
    await promise
    events.value = data.value || []
  } catch (error) {
    console.error('Failed to fetch events:', error)
    events.value = []
  } finally {
    loading.value = false
  }
}

const handleSaveEvent = async (eventData: Partial<CalendarEvent>) => {
  try {
    if (selectedEvent.value) {
      await updateCalendarEvent(selectedEvent.value.id, eventData)
    } else {
      await createCalendarEvent(eventData as { title: string; startAt: string })
    }
    closeModal()
    fetchEvents()
  } catch (error) {
    console.error('Failed to save event:', error)
  }
}

const handleDeleteEvent = async () => {
  if (!selectedEvent.value) return
  try {
    await deleteCalendarEvent(selectedEvent.value.id)
    closeModal()
    fetchEvents()
  } catch (error) {
    console.error('Failed to delete event:', error)
  }
}

const handleExport = () => {
  const range = getDateRange()
  const url = `/api/calendar/events/export.ics?start=${encodeURIComponent(range.start)}&end=${encodeURIComponent(range.end)}`
  window.open(url, '_blank')
}

const handleImport = async (e: Event) => {
  const input = e.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return

  try {
    await importCalendarEvents(file)
    fetchEvents()
  } catch (error) {
    console.error('Failed to import events:', error)
  } finally {
    input.value = ''
  }
}

// Re-fetch when view or date changes
watch([view, currentDate], fetchEvents)

onMounted(() => {
  fetchEvents()
})
</script>
