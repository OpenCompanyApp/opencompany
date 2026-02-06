<template>
  <!-- Backdrop -->
  <Transition
    enter-active-class="transition ease-out duration-200"
    enter-from-class="opacity-0"
    enter-to-class="opacity-100"
    leave-active-class="transition ease-in duration-150"
    leave-from-class="opacity-100"
    leave-to-class="opacity-0"
  >
    <div
      v-if="open"
      class="fixed inset-0 bg-black/30 z-40"
      @click="$emit('update:open', false)"
    />
  </Transition>

  <!-- Modal -->
  <Transition
    enter-active-class="transition ease-out duration-200"
    enter-from-class="opacity-0 scale-95"
    enter-to-class="opacity-100 scale-100"
    leave-active-class="transition ease-in duration-150"
    leave-from-class="opacity-100 scale-100"
    leave-to-class="opacity-0 scale-95"
  >
    <div
      v-if="open"
      class="fixed inset-0 z-50 flex items-center justify-center p-4"
      @click.self="$emit('update:open', false)"
    >
      <div class="bg-white dark:bg-neutral-900 rounded-xl border border-neutral-200 dark:border-neutral-700 shadow-2xl w-full max-w-2xl max-h-[85vh] flex flex-col">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-neutral-200 dark:border-neutral-700 flex items-center justify-between shrink-0">
          <div class="flex items-center gap-3">
            <span
              :class="[
                'inline-flex items-center gap-1.5 px-2 py-0.5 text-xs font-medium rounded-full',
                typeClasses[task.type]
              ]"
            >
              <Icon :name="typeIcons[task.type]" class="w-3 h-3" />
              {{ typeLabels[task.type] }}
            </span>
            <span
              :class="[
                'inline-flex items-center gap-1.5 px-2 py-0.5 text-xs font-medium rounded-full',
                statusClasses[task.status]
              ]"
            >
              <span :class="['w-1.5 h-1.5 rounded-full', task.status === 'active' ? 'animate-pulse' : '', statusDots[task.status]]" />
              {{ statusLabels[task.status] }}
            </span>
          </div>
          <button
            class="p-1.5 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors"
            @click="$emit('update:open', false)"
          >
            <Icon name="ph:x" class="w-5 h-5" />
          </button>
        </div>

        <!-- Content -->
        <div class="flex-1 overflow-y-auto p-6 space-y-6">
          <!-- Title & Description -->
          <div>
            <h2 class="text-lg font-semibold text-neutral-900 dark:text-white mb-2">
              {{ task.title }}
            </h2>
            <p v-if="task.description" class="text-neutral-600 dark:text-neutral-400">
              {{ task.description }}
            </p>
          </div>

          <!-- Metadata -->
          <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
            <div>
              <label class="block text-xs font-medium text-neutral-500 dark:text-neutral-400 mb-1">Priority</label>
              <span
                :class="[
                  'inline-flex items-center gap-1.5 px-2 py-1 text-sm font-medium rounded-full',
                  priorityClasses[task.priority]
                ]"
              >
                <Icon :name="priorityIcons[task.priority]" class="w-3.5 h-3.5" />
                {{ task.priority }}
              </span>
            </div>
            <div>
              <label class="block text-xs font-medium text-neutral-500 dark:text-neutral-400 mb-1">Assigned Agent</label>
              <div v-if="task.agent" class="flex items-center gap-2">
                <AgentAvatar :user="task.agent" size="sm" />
                <span class="text-sm text-neutral-700 dark:text-neutral-300">{{ task.agent.name }}</span>
              </div>
              <span v-else class="text-sm text-neutral-400">Unassigned</span>
            </div>
            <div>
              <label class="block text-xs font-medium text-neutral-500 dark:text-neutral-400 mb-1">Requester</label>
              <div v-if="task.requester" class="flex items-center gap-2">
                <AgentAvatar :user="task.requester" size="sm" />
                <span class="text-sm text-neutral-700 dark:text-neutral-300">{{ task.requester.name }}</span>
              </div>
            </div>
            <div>
              <label class="block text-xs font-medium text-neutral-500 dark:text-neutral-400 mb-1">Created</label>
              <span class="text-sm text-neutral-700 dark:text-neutral-300">
                {{ formatDateTime(task.createdAt) }}
              </span>
            </div>
            <div v-if="task.startedAt">
              <label class="block text-xs font-medium text-neutral-500 dark:text-neutral-400 mb-1">Started</label>
              <span class="text-sm text-neutral-700 dark:text-neutral-300">
                {{ formatDateTime(task.startedAt) }}
              </span>
            </div>
            <div v-if="task.completedAt">
              <label class="block text-xs font-medium text-neutral-500 dark:text-neutral-400 mb-1">Completed</label>
              <span class="text-sm text-neutral-700 dark:text-neutral-300">
                {{ formatDateTime(task.completedAt) }}
              </span>
            </div>
          </div>

          <!-- Steps Timeline -->
          <div v-if="task.steps && task.steps.length > 0">
            <h3 class="text-sm font-medium text-neutral-900 dark:text-white mb-3">Progress</h3>
            <div class="space-y-3">
              <div
                v-for="(step, index) in task.steps"
                :key="step.id"
                class="flex items-start gap-3"
              >
                <div class="flex flex-col items-center">
                  <div
                    :class="[
                      'w-6 h-6 rounded-full flex items-center justify-center text-xs font-medium',
                      step.status === 'completed' && 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400',
                      step.status === 'in_progress' && 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400',
                      step.status === 'pending' && 'bg-neutral-100 dark:bg-neutral-700 text-neutral-500',
                      step.status === 'skipped' && 'bg-neutral-100 dark:bg-neutral-700 text-neutral-400 line-through',
                    ]"
                  >
                    <Icon
                      v-if="step.status === 'completed'"
                      name="ph:check-bold"
                      class="w-3.5 h-3.5"
                    />
                    <Icon
                      v-else-if="step.status === 'in_progress'"
                      name="ph:circle-notch"
                      class="w-3.5 h-3.5 animate-spin"
                    />
                    <span v-else>{{ index + 1 }}</span>
                  </div>
                  <div
                    v-if="index < task.steps.length - 1"
                    :class="[
                      'w-0.5 h-8 mt-1',
                      step.status === 'completed' ? 'bg-green-300 dark:bg-green-700' : 'bg-neutral-200 dark:bg-neutral-700',
                    ]"
                  />
                </div>
                <div class="flex-1 min-w-0 pb-3">
                  <p
                    :class="[
                      'text-sm',
                      step.status === 'completed' && 'text-neutral-700 dark:text-neutral-300',
                      step.status === 'in_progress' && 'text-neutral-900 dark:text-white font-medium',
                      step.status === 'pending' && 'text-neutral-500 dark:text-neutral-400',
                      step.status === 'skipped' && 'text-neutral-400 line-through',
                    ]"
                  >
                    {{ step.description }}
                  </p>
                  <span
                    v-if="step.completedAt"
                    class="text-xs text-neutral-400 mt-0.5"
                  >
                    {{ formatDateTime(step.completedAt) }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Result -->
          <div v-if="task.result && task.status === 'completed'">
            <h3 class="text-sm font-medium text-neutral-900 dark:text-white mb-2">Result</h3>
            <div class="p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
              <pre class="text-sm text-green-800 dark:text-green-300 whitespace-pre-wrap">{{ JSON.stringify(task.result, null, 2) }}</pre>
            </div>
          </div>

          <!-- Error -->
          <div v-if="task.result?.error && task.status === 'failed'">
            <h3 class="text-sm font-medium text-neutral-900 dark:text-white mb-2">Error</h3>
            <div class="p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
              <p class="text-sm text-red-800 dark:text-red-300">{{ task.result.error }}</p>
            </div>
          </div>
        </div>

        <!-- Actions Footer -->
        <div class="px-6 py-4 border-t border-neutral-200 dark:border-neutral-700 flex items-center justify-between shrink-0">
          <div class="flex items-center gap-2">
            <button
              v-if="task.channelId"
              class="flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-lg transition-colors"
              @click="goToChannel"
            >
              <Icon name="ph:chat-circle" class="w-4 h-4" />
              <span>View Chat</span>
            </button>
          </div>
          <div class="flex items-center gap-2">
            <button
              v-if="task.status === 'pending'"
              class="flex items-center gap-2 px-3 py-1.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
              @click="$emit('start', task.id)"
            >
              <Icon name="ph:play-fill" class="w-4 h-4" />
              <span>Start</span>
            </button>
            <button
              v-if="task.status === 'active'"
              class="flex items-center gap-2 px-3 py-1.5 bg-amber-600 text-white text-sm font-medium rounded-lg hover:bg-amber-700 transition-colors"
              @click="$emit('pause', task.id)"
            >
              <Icon name="ph:pause-fill" class="w-4 h-4" />
              <span>Pause</span>
            </button>
            <button
              v-if="task.status === 'paused'"
              class="flex items-center gap-2 px-3 py-1.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
              @click="$emit('resume', task.id)"
            >
              <Icon name="ph:play-fill" class="w-4 h-4" />
              <span>Resume</span>
            </button>
            <button
              v-if="task.status === 'active' || task.status === 'paused'"
              class="flex items-center gap-2 px-3 py-1.5 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors"
              @click="$emit('complete', task.id)"
            >
              <Icon name="ph:check-bold" class="w-4 h-4" />
              <span>Complete</span>
            </button>
            <button
              v-if="!['completed', 'failed', 'cancelled'].includes(task.status)"
              class="flex items-center gap-2 px-3 py-1.5 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 text-sm font-medium rounded-lg transition-colors"
              @click="$emit('cancel', task.id)"
            >
              <Icon name="ph:x" class="w-4 h-4" />
              <span>Cancel</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </Transition>
</template>

<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import type { AgentTask, TaskStatus, TaskType, Priority } from '@/types'
import Icon from '@/Components/shared/Icon.vue'
import AgentAvatar from '@/Components/shared/AgentAvatar.vue'

const props = defineProps<{
  open: boolean
  task: AgentTask
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  'update': []
  'start': [taskId: string]
  'pause': [taskId: string]
  'resume': [taskId: string]
  'complete': [taskId: string, result?: Record<string, unknown>]
  'cancel': [taskId: string]
}>()

// Type styling
const typeClasses: Record<TaskType, string> = {
  ticket: 'bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-400',
  request: 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400',
  analysis: 'bg-cyan-100 dark:bg-cyan-900/30 text-cyan-700 dark:text-cyan-400',
  content: 'bg-pink-100 dark:bg-pink-900/30 text-pink-700 dark:text-pink-400',
  research: 'bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-400',
  custom: 'bg-neutral-100 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-300',
}

const typeIcons: Record<TaskType, string> = {
  ticket: 'ph:ticket',
  request: 'ph:paper-plane-tilt',
  analysis: 'ph:chart-bar',
  content: 'ph:note-pencil',
  research: 'ph:magnifying-glass',
  custom: 'ph:clipboard-text',
}

const typeLabels: Record<TaskType, string> = {
  ticket: 'Ticket',
  request: 'Request',
  analysis: 'Analysis',
  content: 'Content',
  research: 'Research',
  custom: 'Custom',
}

// Status styling
const statusClasses: Record<TaskStatus, string> = {
  pending: 'bg-neutral-100 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-300',
  active: 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400',
  paused: 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400',
  completed: 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400',
  failed: 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400',
  cancelled: 'bg-neutral-100 dark:bg-neutral-700 text-neutral-500 dark:text-neutral-400',
}

const statusDots: Record<TaskStatus, string> = {
  pending: 'bg-neutral-400',
  active: 'bg-blue-500',
  paused: 'bg-amber-500',
  completed: 'bg-green-500',
  failed: 'bg-red-500',
  cancelled: 'bg-neutral-400',
}

const statusLabels: Record<TaskStatus, string> = {
  pending: 'Pending',
  active: 'Active',
  paused: 'Paused',
  completed: 'Completed',
  failed: 'Failed',
  cancelled: 'Cancelled',
}

// Priority styling
const priorityClasses: Record<Priority, string> = {
  low: 'bg-neutral-100 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-300',
  normal: 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400',
  medium: 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400',
  high: 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400',
  urgent: 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400',
}

const priorityIcons: Record<Priority, string> = {
  low: 'ph:arrow-down',
  normal: 'ph:minus',
  medium: 'ph:minus',
  high: 'ph:arrow-up',
  urgent: 'ph:warning',
}

const formatDateTime = (date: Date | string | undefined) => {
  if (!date) return ''
  const d = new Date(date)
  return d.toLocaleString()
}

const goToChannel = () => {
  if (props.task.channelId) {
    router.visit(`/chat?channel=${props.task.channelId}`)
  }
}
</script>
