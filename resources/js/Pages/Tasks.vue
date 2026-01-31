<template>
  <div class="h-full flex flex-col">
    <!-- Header -->
    <header class="h-14 px-6 border-b border-neutral-200 dark:border-neutral-700 flex items-center justify-between bg-white dark:bg-neutral-900 shrink-0">
      <div class="flex items-center gap-4">
        <h1 class="text-xl font-bold text-neutral-900 dark:text-white">Tasks</h1>
        <div class="flex items-center gap-2 text-sm text-neutral-500 dark:text-neutral-300">
          <span>{{ taskCounts.total }} tasks</span>
          <span class="text-neutral-200 dark:text-neutral-600">/</span>
          <span class="text-green-400">{{ taskCounts.done }} done</span>
        </div>
      </div>
      <div class="flex items-center gap-3">
        <TasksTaskFilters
          v-model:filter="currentFilter"
          v-model:view="currentView"
        />
        <button
          class="flex items-center gap-2 px-3 py-1.5 bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 text-sm font-medium rounded-lg hover:bg-neutral-800 dark:hover:bg-neutral-100 transition-colors"
          @click="openCreateModal()"
        >
          <Icon name="ph:plus-bold" class="w-4 h-4" />
          <span>New Task</span>
        </button>
      </div>
    </header>

    <!-- Board View -->
    <TasksTaskBoard
      v-if="currentView === 'board'"
      :tasks="filteredTasks"
      class="flex-1"
      @update="handleTaskUpdate"
      @task-click="openTaskDetail"
      @add-task="openCreateModal"
    />

    <!-- List View -->
    <div v-else-if="currentView === 'list'" class="flex-1 overflow-auto p-6">
      <div class="bg-white dark:bg-neutral-800 rounded-lg border border-neutral-200 dark:border-neutral-700 overflow-hidden">
        <!-- Table Header -->
        <div class="grid grid-cols-12 gap-4 px-4 py-3 bg-neutral-50 dark:bg-neutral-900 border-b border-neutral-200 dark:border-neutral-700 text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
          <div class="col-span-5">Task</div>
          <div class="col-span-2">Status</div>
          <div class="col-span-2">Priority</div>
          <div class="col-span-2">Assignee</div>
          <div class="col-span-1 text-right">Cost</div>
        </div>

        <!-- Task Rows -->
        <div class="divide-y divide-neutral-200 dark:divide-neutral-700">
          <div
            v-for="task in filteredTasks"
            :key="task.id"
            class="grid grid-cols-12 gap-4 px-4 py-3 hover:bg-neutral-50 dark:hover:bg-neutral-700/50 cursor-pointer transition-colors"
            @click="openTaskDetail(task)"
          >
            <!-- Task Title & Description -->
            <div class="col-span-5">
              <h4 :class="['font-medium text-neutral-900 dark:text-white truncate', task.status === 'done' && 'line-through text-neutral-500 dark:text-neutral-400']">
                {{ task.title }}
              </h4>
              <p v-if="task.description" class="text-sm text-neutral-500 dark:text-neutral-400 truncate mt-0.5">
                {{ task.description }}
              </p>
            </div>

            <!-- Status -->
            <div class="col-span-2 flex items-center">
              <span
                :class="[
                  'inline-flex items-center gap-1.5 px-2 py-1 text-xs font-medium rounded-full',
                  statusClasses[task.status]
                ]"
              >
                <span :class="['w-1.5 h-1.5 rounded-full', statusDots[task.status]]" />
                {{ statusLabels[task.status] }}
              </span>
            </div>

            <!-- Priority -->
            <div class="col-span-2 flex items-center">
              <span
                :class="[
                  'inline-flex items-center gap-1.5 px-2 py-1 text-xs font-medium rounded-full',
                  priorityClasses[task.priority]
                ]"
              >
                <span :class="['w-1.5 h-1.5 rounded-full', priorityDots[task.priority]]" />
                {{ task.priority }}
              </span>
            </div>

            <!-- Assignee -->
            <div class="col-span-2 flex items-center">
              <div v-if="task.assignee" class="flex items-center gap-2">
                <AgentAvatar :user="task.assignee" size="xs" />
                <span class="text-sm text-neutral-700 dark:text-neutral-300 truncate">{{ task.assignee.name }}</span>
              </div>
              <span v-else class="text-sm text-neutral-400 dark:text-neutral-500">Unassigned</span>
            </div>

            <!-- Cost -->
            <div class="col-span-1 flex items-center justify-end">
              <CostBadge
                v-if="task.cost || task.estimatedCost"
                :cost="task.cost || task.estimatedCost!"
                :variant="task.cost ? 'actual' : 'estimated'"
                size="xs"
              />
              <span v-else class="text-sm text-neutral-400 dark:text-neutral-500">-</span>
            </div>
          </div>

          <!-- Empty State -->
          <div v-if="filteredTasks.length === 0" class="px-4 py-12 text-center">
            <Icon name="ph:list-checks" class="w-12 h-12 text-neutral-300 dark:text-neutral-600 mx-auto mb-3" />
            <p class="text-neutral-500 dark:text-neutral-400">No tasks found</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Timeline View (placeholder) -->
    <div v-else-if="currentView === 'timeline'" class="flex-1 flex items-center justify-center">
      <div class="text-center">
        <Icon name="ph:chart-line" class="w-16 h-16 text-neutral-300 dark:text-neutral-600 mx-auto mb-4" />
        <h3 class="text-lg font-medium text-neutral-900 dark:text-white mb-2">Timeline View</h3>
        <p class="text-neutral-500 dark:text-neutral-400">Coming soon</p>
      </div>
    </div>

    <!-- Task Detail Drawer -->
    <TasksTaskDetail
      v-if="selectedTask"
      v-model:open="taskDetailOpen"
      :task="selectedTask"
      @update="handleTaskDetailUpdate"
      @delete="handleTaskDelete"
    />

    <!-- Task Create Modal -->
    <TasksTaskCreateModal
      v-model:open="createModalOpen"
      :initial-status="createInitialStatus"
      @created="handleTaskCreated"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import type { Task, TaskStatus, Priority } from '@/types'
import TasksTaskFilters from '@/Components/tasks/TaskFilters.vue'
import TasksTaskBoard from '@/Components/tasks/TaskBoard.vue'
import TasksTaskDetail from '@/Components/tasks/TaskDetail.vue'
import TasksTaskCreateModal from '@/Components/tasks/TaskCreateModal.vue'
import Icon from '@/Components/shared/Icon.vue'
import AgentAvatar from '@/Components/shared/AgentAvatar.vue'
import CostBadge from '@/Components/shared/CostBadge.vue'
import { useApi } from '@/composables/useApi'

type ViewType = 'board' | 'list' | 'timeline'

const { fetchTasks, updateTask, reorderTasks } = useApi()

// Fetch tasks from API
const { data: tasksData, refresh: refreshTasks } = fetchTasks()

const currentFilter = ref('all')
const currentView = ref<ViewType>('board')
const selectedTask = ref<Task | null>(null)
const taskDetailOpen = ref(false)
const createModalOpen = ref(false)
const createInitialStatus = ref<TaskStatus>('backlog')

const tasks = computed<Task[]>(() => tasksData.value ?? [])

const filteredTasks = computed(() => {
  if (currentFilter.value === 'all') return tasks.value
  if (currentFilter.value === 'agents') {
    return tasks.value.filter(t => t.assignee?.type === 'agent')
  }
  if (currentFilter.value === 'humans') {
    return tasks.value.filter(t => t.assignee?.type === 'human')
  }
  return tasks.value
})

const taskCounts = computed(() => ({
  total: tasks.value.length,
  done: tasks.value.filter(t => t.status === 'done').length,
}))

// Status styling
const statusClasses: Record<TaskStatus, string> = {
  backlog: 'bg-neutral-100 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-300',
  in_progress: 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400',
  done: 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400',
}

const statusDots: Record<TaskStatus, string> = {
  backlog: 'bg-neutral-400',
  in_progress: 'bg-blue-500',
  done: 'bg-green-500',
}

const statusLabels: Record<TaskStatus, string> = {
  backlog: 'Backlog',
  in_progress: 'In Progress',
  done: 'Done',
}

// Priority styling
const priorityClasses: Record<Priority, string> = {
  low: 'bg-neutral-100 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-300',
  medium: 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400',
  high: 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400',
  urgent: 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400',
}

const priorityDots: Record<Priority, string> = {
  low: 'bg-neutral-400',
  medium: 'bg-blue-500',
  high: 'bg-amber-500',
  urgent: 'bg-red-500',
}

const handleTaskUpdate = async (taskId: string, newStatus: TaskStatus, newIndex: number) => {
  // Get tasks in the target status column
  const targetColumnTasks = tasks.value
    .filter(t => t.status === newStatus && t.id !== taskId)
    .sort((a, b) => ((a as any).position || 0) - ((b as any).position || 0))

  // Insert the moved task at the new position
  const taskOrders: { id: string; position: number; status?: string }[] = []

  // Add the moved task with its new position and status
  taskOrders.push({ id: taskId, position: newIndex, status: newStatus })

  // Reorder remaining tasks in the column
  let position = 0
  for (const task of targetColumnTasks) {
    if (position === newIndex) {
      position++ // Skip the position where we inserted the moved task
    }
    if ((task as any).position !== position) {
      taskOrders.push({ id: task.id, position })
    }
    position++
  }

  // Update via API
  if (taskOrders.length > 0) {
    await reorderTasks(taskOrders)
  }

  await refreshTasks()
}

const openTaskDetail = (task: Task) => {
  selectedTask.value = task
  taskDetailOpen.value = true
}

const handleTaskDetailUpdate = async () => {
  await refreshTasks()
  // Update selected task with refreshed data
  if (selectedTask.value) {
    const updated = tasks.value.find(t => t.id === selectedTask.value!.id)
    if (updated) {
      selectedTask.value = updated
    }
  }
}

const handleTaskDelete = async (taskId: string) => {
  await refreshTasks()
  selectedTask.value = null
  taskDetailOpen.value = false
}

const openCreateModal = (status?: TaskStatus) => {
  createInitialStatus.value = status || 'backlog'
  createModalOpen.value = true
}

const handleTaskCreated = async () => {
  await refreshTasks()
}
</script>
