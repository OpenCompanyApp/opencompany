<template>
  <div class="h-full flex flex-col">
    <!-- Header -->
    <header class="shrink-0 px-6 py-4 border-b border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
          <div class="flex items-center gap-2">
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
          <h1 class="text-xl font-semibold text-neutral-900 dark:text-white">
            {{ currentPeriodLabel }}
          </h1>
          <button
            type="button"
            class="px-3 py-1.5 text-sm text-neutral-600 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-lg transition-colors"
            @click="goToToday"
          >
            Today
          </button>
        </div>
        <div class="flex items-center gap-3">
          <!-- View toggle -->
          <div class="flex rounded-lg border border-neutral-200 dark:border-neutral-700 overflow-hidden">
            <button
              v-for="v in ['month', 'week', 'day'] as const"
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
          <Button @click="showCreateModal = true">
            <Icon name="ph:plus" class="w-4 h-4 mr-1.5" />
            New Event
          </Button>
        </div>
      </div>
    </header>

    <!-- Calendar Grid -->
    <div class="flex-1 overflow-hidden flex">
      <!-- Sidebar with mini calendar and filters -->
      <CalendarSidebar
        :current-date="currentDate"
        :selected-date="selectedDate"
        @select-date="handleDateSelect"
      />

      <!-- Main calendar view -->
      <div class="flex-1 overflow-auto bg-white dark:bg-neutral-900">
        <CalendarView
          :view="view"
          :current-date="currentDate"
          :events="events"
          :loading="loading"
          @event-click="handleEventClick"
          @slot-click="handleSlotClick"
        />
      </div>
    </div>

    <!-- Create/Edit Event Modal -->
    <CalendarEventModal
      v-if="showCreateModal"
      :event="selectedEvent"
      :initial-date="selectedDate"
      @close="closeModal"
      @save="handleSaveEvent"
      @delete="handleDeleteEvent"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import Button from '@/Components/shared/Button.vue'
import CalendarSidebar from '@/Components/calendar/CalendarSidebar.vue'
import CalendarView from '@/Components/calendar/CalendarView.vue'
import CalendarEventModal from '@/Components/calendar/CalendarEventModal.vue'
import type { CalendarEvent } from '@/types'

type ViewType = 'month' | 'week' | 'day'

const view = ref<ViewType>('month')
const currentDate = ref(new Date())
const selectedDate = ref<Date | null>(null)
const selectedEvent = ref<CalendarEvent | null>(null)
const showCreateModal = ref(false)
const events = ref<CalendarEvent[]>([])
const loading = ref(false)

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

  return date.toLocaleDateString('en-US', options)
})

const prevPeriod = () => {
  const date = new Date(currentDate.value)
  if (view.value === 'month') {
    date.setMonth(date.getMonth() - 1)
  } else if (view.value === 'week') {
    date.setDate(date.getDate() - 7)
  } else {
    date.setDate(date.getDate() - 1)
  }
  currentDate.value = date
  fetchEvents()
}

const nextPeriod = () => {
  const date = new Date(currentDate.value)
  if (view.value === 'month') {
    date.setMonth(date.getMonth() + 1)
  } else if (view.value === 'week') {
    date.setDate(date.getDate() + 7)
  } else {
    date.setDate(date.getDate() + 1)
  }
  currentDate.value = date
  fetchEvents()
}

const goToToday = () => {
  currentDate.value = new Date()
  fetchEvents()
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
    // Calculate date range based on view
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
    }

    const response = await fetch(`/api/calendar/events?start=${start.toISOString()}&end=${end.toISOString()}`)
    events.value = await response.json()
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
      // Update existing event
      await fetch(`/api/calendar/events/${selectedEvent.value.id}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(eventData),
      })
    } else {
      // Create new event
      await fetch('/api/calendar/events', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(eventData),
      })
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
    await fetch(`/api/calendar/events/${selectedEvent.value.id}`, {
      method: 'DELETE',
    })
    closeModal()
    fetchEvents()
  } catch (error) {
    console.error('Failed to delete event:', error)
  }
}

onMounted(() => {
  fetchEvents()
})
</script>
