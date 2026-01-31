<template>
  <Modal :open="true" size="md" @close="$emit('close')">
    <template #title>
      {{ event ? 'Edit Event' : 'New Event' }}
    </template>

    <form class="space-y-4" @submit.prevent="handleSubmit">
      <!-- Title -->
      <div>
        <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
          Title
        </label>
        <Input
          v-model="form.title"
          placeholder="Event title"
          required
        />
      </div>

      <!-- Date/Time -->
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
            Start
          </label>
          <Input
            v-model="form.startAt"
            :type="form.allDay ? 'date' : 'datetime-local'"
            required
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
            End
          </label>
          <Input
            v-model="form.endAt"
            :type="form.allDay ? 'date' : 'datetime-local'"
          />
        </div>
      </div>

      <!-- All day toggle -->
      <label class="flex items-center gap-2 cursor-pointer">
        <input
          v-model="form.allDay"
          type="checkbox"
          class="rounded border-neutral-300 dark:border-neutral-600 text-blue-500 focus:ring-blue-500"
        >
        <span class="text-sm text-neutral-700 dark:text-neutral-300">All day event</span>
      </label>

      <!-- Description -->
      <div>
        <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
          Description
        </label>
        <textarea
          v-model="form.description"
          rows="3"
          class="w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm text-neutral-900 dark:text-white placeholder:text-neutral-400 focus:outline-none focus:ring-2 focus:ring-blue-500"
          placeholder="Add a description..."
        />
      </div>

      <!-- Location -->
      <div>
        <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
          Location
        </label>
        <Input
          v-model="form.location"
          placeholder="Add location"
        />
      </div>

      <!-- Color -->
      <div>
        <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
          Color
        </label>
        <div class="flex gap-2">
          <button
            v-for="color in colors"
            :key="color"
            type="button"
            :class="[
              'w-6 h-6 rounded-full transition-transform',
              form.color === color && 'ring-2 ring-offset-2 ring-neutral-900 dark:ring-white dark:ring-offset-neutral-900 scale-110'
            ]"
            :style="{ backgroundColor: colorValues[color] }"
            @click="form.color = color"
          />
        </div>
      </div>

      <!-- Attendees (simplified) -->
      <div>
        <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
          Attendees
        </label>
        <Input
          v-model="attendeeInput"
          placeholder="Search users to add..."
          @keydown.enter.prevent="addAttendee"
        />
        <div v-if="form.attendeeIds.length > 0" class="flex flex-wrap gap-2 mt-2">
          <span
            v-for="id in form.attendeeIds"
            :key="id"
            class="inline-flex items-center gap-1 px-2 py-1 bg-neutral-100 dark:bg-neutral-800 rounded-full text-sm"
          >
            {{ id }}
            <button
              type="button"
              class="text-neutral-400 hover:text-neutral-600"
              @click="removeAttendee(id)"
            >
              <Icon name="ph:x" class="w-3 h-3" />
            </button>
          </span>
        </div>
      </div>
    </form>

    <template #footer>
      <div class="flex items-center justify-between w-full">
        <div>
          <Button
            v-if="event"
            variant="ghost"
            class="text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20"
            @click="$emit('delete')"
          >
            Delete
          </Button>
        </div>
        <div class="flex gap-2">
          <Button variant="secondary" @click="$emit('close')">
            Cancel
          </Button>
          <Button @click="handleSubmit">
            {{ event ? 'Save' : 'Create' }}
          </Button>
        </div>
      </div>
    </template>
  </Modal>
</template>

<script setup lang="ts">
import { ref, reactive, watch } from 'vue'
import Modal from '@/Components/shared/Modal.vue'
import Button from '@/Components/shared/Button.vue'
import Input from '@/Components/shared/Input.vue'
import Icon from '@/Components/shared/Icon.vue'
import type { CalendarEvent } from '@/types'

const props = defineProps<{
  event: CalendarEvent | null
  initialDate: Date | null
}>()

const emit = defineEmits<{
  close: []
  save: [data: Partial<CalendarEvent>]
  delete: []
}>()

const colors = ['blue', 'green', 'red', 'purple', 'yellow', 'orange', 'pink', 'indigo'] as const
const colorValues: Record<string, string> = {
  blue: '#3b82f6',
  green: '#22c55e',
  red: '#ef4444',
  purple: '#a855f7',
  yellow: '#eab308',
  orange: '#f97316',
  pink: '#ec4899',
  indigo: '#6366f1',
}

const attendeeInput = ref('')

const formatDateForInput = (date: Date | null, allDay: boolean): string => {
  if (!date) return ''
  if (allDay) {
    return date.toISOString().split('T')[0]
  }
  return date.toISOString().slice(0, 16)
}

const form = reactive({
  title: '',
  description: '',
  startAt: '',
  endAt: '',
  allDay: false,
  location: '',
  color: 'blue' as string,
  attendeeIds: [] as string[],
})

// Initialize form
watch(
  () => props.event,
  (event) => {
    if (event) {
      form.title = event.title
      form.description = event.description || ''
      form.startAt = formatDateForInput(new Date(event.startAt), event.allDay)
      form.endAt = event.endAt ? formatDateForInput(new Date(event.endAt), event.allDay) : ''
      form.allDay = event.allDay
      form.location = event.location || ''
      form.color = event.color || 'blue'
      form.attendeeIds = event.attendees?.map((a) => a.userId) || []
    } else if (props.initialDate) {
      form.startAt = formatDateForInput(props.initialDate, false)
    }
  },
  { immediate: true }
)

const addAttendee = () => {
  if (attendeeInput.value && !form.attendeeIds.includes(attendeeInput.value)) {
    form.attendeeIds.push(attendeeInput.value)
    attendeeInput.value = ''
  }
}

const removeAttendee = (id: string) => {
  const index = form.attendeeIds.indexOf(id)
  if (index > -1) {
    form.attendeeIds.splice(index, 1)
  }
}

const handleSubmit = () => {
  emit('save', {
    title: form.title,
    description: form.description || undefined,
    startAt: form.startAt,
    endAt: form.endAt || undefined,
    allDay: form.allDay,
    location: form.location || undefined,
    color: form.color,
  })
}
</script>
