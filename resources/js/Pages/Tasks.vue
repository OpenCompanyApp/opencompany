<template>
  <div class="h-full flex flex-col">
    <!-- Header -->
    <header class="h-14 px-6 border-b border-gray-200 flex items-center justify-between bg-white shrink-0">
      <div class="flex items-center gap-4">
        <h1 class="text-xl font-bold text-gray-900">Tasks</h1>
        <div class="flex items-center gap-2 text-sm text-gray-500">
          <span>{{ taskCounts.total }} tasks</span>
          <span class="text-gray-200">/</span>
          <span class="text-green-400">{{ taskCounts.done }} done</span>
        </div>
      </div>
      <div class="flex items-center gap-3">
        <TasksTaskFilters v-model:filter="currentFilter" />
        <button
          class="flex items-center gap-2 px-3 py-1.5 bg-gray-900 text-white text-sm font-medium rounded-lg hover:bg-gray-800 transition-colors"
          @click="openCreateModal()"
        >
          <Icon name="ph:plus-bold" class="w-4 h-4" />
          <span>New Task</span>
        </button>
      </div>
    </header>

    <!-- Board -->
    <TasksTaskBoard
      :tasks="filteredTasks"
      class="flex-1"
      @update="handleTaskUpdate"
      @task-click="openTaskDetail"
      @add-task="openCreateModal"
    />

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
import type { Task, TaskStatus } from '@/types'
import TasksTaskFilters from '@/Components/tasks/TaskFilters.vue'
import TasksTaskBoard from '@/Components/tasks/TaskBoard.vue'
import TasksTaskDetail from '@/Components/tasks/TaskDetail.vue'
import TasksTaskCreateModal from '@/Components/tasks/TaskCreateModal.vue'
import { useApi } from '@/composables/useApi'

const { fetchTasks, updateTask, reorderTasks } = useApi()

// Fetch tasks from API
const { data: tasksData, refresh: refreshTasks } = fetchTasks()

const currentFilter = ref('all')
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
