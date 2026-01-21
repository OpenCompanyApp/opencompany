<template>
  <DialogRoot :open="open" @update:open="$emit('update:open', $event)">
    <DialogPortal>
      <DialogOverlay class="fixed inset-0 bg-black/50 z-40" />
      <DialogContent
        class="fixed left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-lg bg-white border border-gray-200 rounded-xl z-50 overflow-hidden flex flex-col animate-in fade-in-0 zoom-in-95 duration-200"
      >
        <DialogTitle class="sr-only">Create New Task</DialogTitle>
        <DialogDescription class="sr-only">Fill in task details to create a new task</DialogDescription>

        <!-- Header -->
        <div class="shrink-0 px-6 py-4 border-b border-gray-200 flex items-center justify-between">
          <h2 class="text-lg font-semibold text-gray-900">Create New Task</h2>
          <DialogClose
            class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-50 rounded-lg transition-colors"
          >
            <Icon name="ph:x" class="w-5 h-5" />
          </DialogClose>
        </div>

        <!-- Content -->
        <form class="flex-1 overflow-y-auto p-6" @submit.prevent="createTask">
          <div class="space-y-5">
            <!-- Title -->
            <div>
              <label class="block text-sm font-medium text-gray-500 mb-2">
                Title <span class="text-red-400">*</span>
              </label>
              <input
                v-model="form.title"
                type="text"
                class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-gray-900 placeholder:text-gray-500 focus:border-gray-300 focus:outline-none"
                placeholder="Enter task title..."
                required
              />
            </div>

            <!-- Description -->
            <div>
              <label class="block text-sm font-medium text-gray-500 mb-2">
                Description
              </label>
              <textarea
                v-model="form.description"
                rows="3"
                class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-gray-700 placeholder:text-gray-500 resize-none focus:border-gray-300 focus:outline-none"
                placeholder="Add a description..."
              />
            </div>

            <!-- Status & Priority -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-500 mb-2">
                  Status
                </label>
                <select
                  v-model="form.status"
                  class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-gray-700 focus:border-gray-300 focus:outline-none"
                >
                  <option value="backlog">Backlog</option>
                  <option value="in_progress">In Progress</option>
                  <option value="done">Done</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-500 mb-2">
                  Priority
                </label>
                <select
                  v-model="form.priority"
                  class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-gray-700 focus:border-gray-300 focus:outline-none"
                >
                  <option value="low">Low</option>
                  <option value="medium">Medium</option>
                  <option value="high">High</option>
                  <option value="urgent">Urgent</option>
                </select>
              </div>
            </div>

            <!-- Assignee -->
            <div>
              <label class="block text-sm font-medium text-gray-500 mb-2">
                Assignee <span class="text-red-400">*</span>
              </label>
              <select
                v-model="form.assigneeId"
                class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-gray-700 focus:border-gray-300 focus:outline-none"
                required
              >
                <option value="" disabled>Select assignee...</option>
                <optgroup label="Agents">
                  <option v-for="agent in agents" :key="agent.id" :value="agent.id">
                    {{ agent.name }} ({{ agent.agentType }})
                  </option>
                </optgroup>
                <optgroup label="Humans">
                  <option v-for="human in humans" :key="human.id" :value="human.id">
                    {{ human.name }}
                  </option>
                </optgroup>
              </select>
            </div>

            <!-- Estimated Cost -->
            <div>
              <label class="block text-sm font-medium text-gray-500 mb-2">
                Estimated Cost
              </label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">$</span>
                <input
                  v-model.number="form.estimatedCost"
                  type="number"
                  step="0.01"
                  min="0"
                  class="w-full pl-7 pr-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-gray-700 placeholder:text-gray-500 focus:border-gray-300 focus:outline-none"
                  placeholder="0.00"
                />
              </div>
            </div>

            <!-- Channel -->
            <div>
              <label class="block text-sm font-medium text-gray-500 mb-2">
                Channel
              </label>
              <select
                v-model="form.channelId"
                class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-gray-700 focus:border-gray-300 focus:outline-none"
              >
                <option value="">No channel</option>
                <option v-for="channel in channels" :key="channel.id" :value="channel.id">
                  #{{ channel.name }}
                </option>
              </select>
            </div>
          </div>
        </form>

        <!-- Footer -->
        <div class="shrink-0 px-6 py-4 border-t border-gray-200 flex items-center justify-end gap-3">
          <button
            type="button"
            class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700 transition-colors"
            @click="$emit('update:open', false)"
          >
            Cancel
          </button>
          <button
            class="px-4 py-2 text-sm bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="creating || !form.title.trim() || !form.assigneeId"
            @click="createTask"
          >
            {{ creating ? 'Creating...' : 'Create Task' }}
          </button>
        </div>
      </DialogContent>
    </DialogPortal>
  </DialogRoot>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import type { TaskStatus, Priority, User } from '@/types'
import {
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogOverlay,
  DialogPortal,
  DialogRoot,
  DialogTitle,
} from 'reka-ui'

interface Channel {
  id: string
  name: string
}

const props = defineProps<{
  open: boolean
  initialStatus?: TaskStatus
  users?: User[]
  channels?: Channel[]
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  'created': [data: {
    title: string
    description: string
    status: TaskStatus
    priority: Priority
    assigneeId: string
    estimatedCost: number | null
    channelId: string | null
  }]
}>()

const agents = computed(() => (props.users ?? []).filter((u: User) => u.type === 'agent'))
const humans = computed(() => (props.users ?? []).filter((u: User) => u.type === 'human'))
const channels = computed<Channel[]>(() => props.channels ?? [])

const creating = ref(false)
const form = ref({
  title: '',
  description: '',
  status: props.initialStatus || 'backlog' as TaskStatus,
  priority: 'medium' as Priority,
  assigneeId: '',
  estimatedCost: null as number | null,
  channelId: '',
})

// Reset form when modal opens
watch(() => props.open, (open) => {
  if (open) {
    form.value = {
      title: '',
      description: '',
      status: props.initialStatus || 'backlog',
      priority: 'medium',
      assigneeId: '',
      estimatedCost: null,
      channelId: '',
    }
  }
})

const createTask = async () => {
  if (!form.value.title.trim() || !form.value.assigneeId) return

  creating.value = true
  try {
    emit('created', {
      title: form.value.title.trim(),
      description: form.value.description.trim(),
      status: form.value.status,
      priority: form.value.priority,
      assigneeId: form.value.assigneeId,
      estimatedCost: form.value.estimatedCost,
      channelId: form.value.channelId || null,
    })
    emit('update:open', false)
  } finally {
    creating.value = false
  }
}
</script>
