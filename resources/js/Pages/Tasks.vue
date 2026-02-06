<template>
  <div class="h-full flex flex-col">
    <!-- Header -->
    <header class="px-4 md:px-6 py-3 md:py-0 md:h-14 border-b border-neutral-200 dark:border-neutral-700 flex flex-col md:flex-row md:items-center gap-3 bg-white dark:bg-neutral-900 shrink-0">
      <div class="flex items-center justify-between md:gap-4">
        <div class="flex items-center gap-3 md:gap-4">
          <!-- Page tabs -->
          <div class="flex items-center gap-1">
            <span class="text-xl font-bold text-neutral-900 dark:text-white">Tasks</span>
            <Link
              href="/workload"
              class="ml-2 px-2 py-1 text-sm font-medium text-neutral-500 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-md transition-colors"
            >
              Workload
            </Link>
          </div>
          <div class="hidden md:flex items-center gap-2 text-sm text-neutral-500 dark:text-neutral-300">
            <span>{{ taskCounts.total }} tasks</span>
            <span class="text-neutral-200 dark:text-neutral-600">/</span>
            <span class="text-yellow-500">{{ taskCounts.active }} active</span>
            <span class="text-neutral-200 dark:text-neutral-600">/</span>
            <span class="text-green-400">{{ taskCounts.completed }} done</span>
          </div>
        </div>
        <!-- Mobile: New Task button -->
        <button
          class="md:hidden flex items-center gap-2 px-3 py-1.5 bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 text-sm font-medium rounded-lg"
          @click="createModalOpen = true"
        >
          <Icon name="ph:plus-bold" class="w-4 h-4" />
          <span>New</span>
        </button>
      </div>
      <div class="flex items-center gap-3 overflow-x-auto md:overflow-visible md:ml-auto pb-1 md:pb-0 -mx-4 px-4 md:mx-0 md:px-0">
        <!-- Status Filters -->
        <div class="flex items-center gap-1 bg-neutral-100 dark:bg-neutral-800 rounded-lg p-1">
          <button
            v-for="filter in statusFilters"
            :key="filter.value"
            :class="[
              'px-3 py-1 text-sm font-medium rounded-md transition-colors',
              currentFilter === filter.value
                ? 'bg-white dark:bg-neutral-700 text-neutral-900 dark:text-white shadow-sm'
                : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white'
            ]"
            @click="currentFilter = filter.value"
          >
            {{ filter.label }}
          </button>
        </div>
        <button
          class="hidden md:flex items-center gap-2 px-3 py-1.5 bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 text-sm font-medium rounded-lg hover:bg-neutral-800 dark:hover:bg-neutral-100 transition-colors shrink-0"
          @click="createModalOpen = true"
        >
          <Icon name="ph:plus-bold" class="w-4 h-4" />
          <span>New Task</span>
        </button>
      </div>
    </header>

    <!-- Task List -->
    <div class="flex-1 overflow-auto p-4 md:p-6">
      <div class="space-y-3">
        <div
          v-for="task in filteredTasks"
          :key="task.id"
          class="p-4 bg-white dark:bg-neutral-800 rounded-lg border border-neutral-200 dark:border-neutral-700 cursor-pointer hover:border-neutral-300 dark:hover:border-neutral-600 transition-colors"
          @click="openTaskDetail(task)"
        >
          <div class="flex items-start justify-between gap-4 mb-3">
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 mb-1">
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
                  <span :class="['w-1.5 h-1.5 rounded-full', statusDots[task.status]]" />
                  {{ statusLabels[task.status] }}
                </span>
              </div>
              <h4 class="font-medium text-neutral-900 dark:text-white truncate">
                {{ task.title }}
              </h4>
              <p v-if="task.description" class="text-sm text-neutral-500 dark:text-neutral-400 line-clamp-2 mt-1">
                {{ task.description }}
              </p>
            </div>
            <div class="flex flex-col items-end gap-2 shrink-0">
              <span
                :class="[
                  'inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium rounded-full',
                  priorityClasses[task.priority]
                ]"
              >
                <Icon :name="priorityIcons[task.priority]" class="w-3 h-3" />
                {{ task.priority }}
              </span>
              <span class="text-xs text-neutral-400">
                {{ formatDate(task.createdAt) }}
              </span>
            </div>
          </div>
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div v-if="task.agent" class="flex items-center gap-1.5">
                <AgentAvatar :user="task.agent" size="xs" />
                <span class="text-sm text-neutral-600 dark:text-neutral-300">{{ task.agent.name }}</span>
              </div>
              <span v-else class="text-sm text-neutral-400 dark:text-neutral-500">Unassigned</span>
            </div>
            <div class="flex items-center gap-2">
              <span v-if="task.steps && task.steps.length > 0" class="text-xs text-neutral-500 dark:text-neutral-400">
                {{ task.steps.filter(s => s.status === 'completed').length }}/{{ task.steps.length }} steps
              </span>
              <button
                v-if="task.channelId"
                class="p-1 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300"
                @click.stop="goToChannel(task.channelId)"
              >
                <Icon name="ph:chat-circle" class="w-4 h-4" />
              </button>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-if="filteredTasks.length === 0" class="text-center py-12">
          <Icon name="ph:briefcase" class="w-16 h-16 text-neutral-300 dark:text-neutral-600 mx-auto mb-4" />
          <h3 class="text-lg font-medium text-neutral-900 dark:text-white mb-2">No tasks found</h3>
          <p class="text-neutral-500 dark:text-neutral-400 mb-4">
            {{ currentFilter === 'all' ? 'Create your first task to get started' : 'No tasks match the current filter' }}
          </p>
          <button
            class="inline-flex items-center gap-2 px-4 py-2 bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 text-sm font-medium rounded-lg hover:bg-neutral-800 dark:hover:bg-neutral-100 transition-colors"
            @click="createModalOpen = true"
          >
            <Icon name="ph:plus-bold" class="w-4 h-4" />
            <span>Create Task</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Task Detail Drawer -->
    <TaskDetailDrawer
      v-if="selectedTask"
      v-model:open="taskDetailOpen"
      :task="selectedTask"
      @update="handleTaskUpdate"
      @start="handleTaskStart"
      @pause="handleTaskPause"
      @resume="handleTaskResume"
      @complete="handleTaskComplete"
      @cancel="handleTaskCancel"
    />

    <!-- Task Create Modal -->
    <Modal v-model:open="createModalOpen" title="New Task">
      <form @submit.prevent="handleCreateTask" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
            Title
          </label>
          <input
            v-model="newTask.title"
            type="text"
            placeholder="What needs to be done?"
            class="w-full px-3 py-2 bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white placeholder:text-neutral-400 focus:outline-none focus:ring-2 focus:ring-neutral-900 dark:focus:ring-white"
            autofocus
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
            Description
          </label>
          <textarea
            v-model="newTask.description"
            placeholder="Provide details and context..."
            rows="3"
            class="w-full px-3 py-2 bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white placeholder:text-neutral-400 focus:outline-none focus:ring-2 focus:ring-neutral-900 dark:focus:ring-white resize-none"
          />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
              Type
            </label>
            <select
              v-model="newTask.type"
              class="w-full px-3 py-2 bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-neutral-900 dark:focus:ring-white"
            >
              <option value="custom">Custom</option>
              <option value="ticket">Ticket</option>
              <option value="request">Request</option>
              <option value="analysis">Analysis</option>
              <option value="content">Content</option>
              <option value="research">Research</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
              Priority
            </label>
            <select
              v-model="newTask.priority"
              class="w-full px-3 py-2 bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-neutral-900 dark:focus:ring-white"
            >
              <option value="low">Low</option>
              <option value="normal">Normal</option>
              <option value="high">High</option>
              <option value="urgent">Urgent</option>
            </select>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
            Assign to Agent
          </label>
          <select
            v-model="newTask.agentId"
            class="w-full px-3 py-2 bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-neutral-900 dark:focus:ring-white"
          >
            <option value="">Unassigned</option>
            <option v-for="agent in agents" :key="agent.id" :value="agent.id">
              {{ agent.name }}
            </option>
          </select>
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <button
            type="button"
            class="px-4 py-2 text-sm font-medium text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white transition-colors"
            @click="createModalOpen = false"
          >
            Cancel
          </button>
          <button
            type="submit"
            :disabled="!newTask.title.trim()"
            class="px-4 py-2 text-sm font-medium bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 rounded-lg hover:bg-neutral-800 dark:hover:bg-neutral-100 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Create Task
          </button>
        </div>
      </form>
    </Modal>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import type { AgentTask, TaskStatus, TaskType, Priority, User } from '@/types'
import Icon from '@/Components/shared/Icon.vue'
import AgentAvatar from '@/Components/shared/AgentAvatar.vue'
import Modal from '@/Components/shared/Modal.vue'
import TaskDetailDrawer from '@/Components/tasks/TaskDetailDrawer.vue'
import { useApi } from '@/composables/useApi'

const {
  fetchAgentTasks,
  fetchAgents,
  createAgentTask,
  startAgentTask,
  pauseAgentTask,
  resumeAgentTask,
  completeAgentTask,
  cancelAgentTask,
} = useApi()

// Current user (would come from auth in real app)
const currentUserId = ref('h1')

// Fetch data from API
const { data: tasksData, refresh: refreshTasks } = fetchAgentTasks()
const { data: agentsData } = fetchAgents()

const currentFilter = ref<'all' | 'pending' | 'active' | 'completed'>('all')
const selectedTask = ref<AgentTask | null>(null)
const taskDetailOpen = ref(false)
const createModalOpen = ref(false)

const newTask = ref({
  title: '',
  description: '',
  type: 'custom' as TaskType,
  priority: 'normal' as Priority,
  agentId: '',
})

const statusFilters = [
  { label: 'All', value: 'all' },
  { label: 'Pending', value: 'pending' },
  { label: 'Active', value: 'active' },
  { label: 'Completed', value: 'completed' },
]

const tasks = computed<AgentTask[]>(() => tasksData.value ?? [])
const agents = computed<User[]>(() => agentsData.value ?? [])

const filteredTasks = computed(() => {
  if (currentFilter.value === 'all') {
    return tasks.value
  }
  if (currentFilter.value === 'pending') {
    return tasks.value.filter(t => t.status === 'pending')
  }
  if (currentFilter.value === 'active') {
    return tasks.value.filter(t => t.status === 'active' || t.status === 'paused')
  }
  if (currentFilter.value === 'completed') {
    return tasks.value.filter(t => ['completed', 'failed', 'cancelled'].includes(t.status))
  }
  return tasks.value
})

const taskCounts = computed(() => ({
  total: tasks.value.length,
  active: tasks.value.filter(t => t.status === 'active').length,
  completed: tasks.value.filter(t => t.status === 'completed').length,
}))

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

const formatDate = (date: Date | string) => {
  const d = new Date(date)
  const now = new Date()
  const diff = now.getTime() - d.getTime()
  const days = Math.floor(diff / (1000 * 60 * 60 * 24))

  if (days === 0) return 'Today'
  if (days === 1) return 'Yesterday'
  if (days < 7) return `${days} days ago`
  return d.toLocaleDateString()
}

const openTaskDetail = (task: AgentTask) => {
  selectedTask.value = task
  taskDetailOpen.value = true
}

const handleTaskUpdate = async () => {
  await refreshTasks()
  if (selectedTask.value) {
    const updated = tasks.value.find(t => t.id === selectedTask.value!.id)
    if (updated) {
      selectedTask.value = updated
    }
  }
}

const handleTaskStart = async (taskId: string) => {
  await startAgentTask(taskId)
  await handleTaskUpdate()
}

const handleTaskPause = async (taskId: string) => {
  await pauseAgentTask(taskId)
  await handleTaskUpdate()
}

const handleTaskResume = async (taskId: string) => {
  await resumeAgentTask(taskId)
  await handleTaskUpdate()
}

const handleTaskComplete = async (taskId: string, result?: Record<string, unknown>) => {
  await completeAgentTask(taskId, result)
  await handleTaskUpdate()
}

const handleTaskCancel = async (taskId: string) => {
  await cancelAgentTask(taskId)
  await handleTaskUpdate()
}

const handleCreateTask = async () => {
  if (!newTask.value.title.trim()) return

  await createAgentTask({
    title: newTask.value.title.trim(),
    description: newTask.value.description.trim() || undefined,
    type: newTask.value.type,
    priority: newTask.value.priority,
    agentId: newTask.value.agentId || undefined,
    requesterId: currentUserId.value,
  })

  createModalOpen.value = false
  newTask.value = {
    title: '',
    description: '',
    type: 'custom',
    priority: 'normal',
    agentId: '',
  }
  await refreshTasks()
}

const goToChannel = (channelId: string) => {
  router.visit(`/chat?channel=${channelId}`)
}
</script>
