<template>
  <div class="h-full flex">
    <!-- Project Sidebar (hidden on mobile) -->
    <div class="hidden md:block w-64 shrink-0">
      <TasksProjectList
        :tasks="tasks"
        :selected-id="selectedProjectId"
        @select="handleProjectSelect"
        @create-project="openCreateProjectModal"
        @rename-project="handleRenameProject"
        @delete-project="handleDeleteProject"
      />
    </div>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col min-w-0">
      <!-- Header -->
      <header class="px-4 md:px-6 py-3 md:py-0 md:h-14 border-b border-neutral-200 dark:border-neutral-700 flex flex-col md:flex-row md:items-center gap-3 bg-white dark:bg-neutral-900 shrink-0">
      <div class="flex items-center justify-between md:gap-4">
        <div class="flex items-center gap-3 md:gap-4">
          <h1 class="text-xl font-bold text-neutral-900 dark:text-white">Tasks</h1>
          <div class="hidden md:flex items-center gap-2 text-sm text-neutral-500 dark:text-neutral-300">
            <span>{{ taskCounts.total }} tasks</span>
            <span class="text-neutral-200 dark:text-neutral-600">/</span>
            <span class="text-green-400">{{ taskCounts.done }} done</span>
          </div>
        </div>
        <!-- Mobile: New Task button -->
        <button
          class="md:hidden flex items-center gap-2 px-3 py-1.5 bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 text-sm font-medium rounded-lg"
          @click="openCreateModal()"
        >
          <Icon name="ph:plus-bold" class="w-4 h-4" />
          <span>New</span>
        </button>
      </div>
      <div class="flex items-center gap-3 overflow-x-auto md:overflow-visible md:ml-auto pb-1 md:pb-0 -mx-4 px-4 md:mx-0 md:px-0">
        <TasksTaskFilters
          v-model:filter="currentFilter"
          v-model:view="currentView"
        />
        <button
          class="hidden md:flex items-center gap-2 px-3 py-1.5 bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 text-sm font-medium rounded-lg hover:bg-neutral-800 dark:hover:bg-neutral-100 transition-colors shrink-0"
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
    <div v-else-if="currentView === 'list'" class="flex-1 overflow-auto p-4 md:p-6">
      <!-- Mobile Card View -->
      <div class="md:hidden space-y-3">
        <div
          v-for="task in filteredTasks"
          :key="task.id"
          class="p-4 bg-white dark:bg-neutral-800 rounded-lg border border-neutral-200 dark:border-neutral-700 cursor-pointer active:bg-neutral-50 dark:active:bg-neutral-700/50"
          @click="openTaskDetail(task)"
        >
          <div class="flex items-start justify-between gap-3 mb-2">
            <h4 :class="['font-medium text-neutral-900 dark:text-white', task.status === 'done' && 'line-through text-neutral-500 dark:text-neutral-400']">
              {{ task.title }}
            </h4>
            <span
              :class="[
                'inline-flex items-center gap-1.5 px-2 py-0.5 text-xs font-medium rounded-full shrink-0',
                statusClasses[task.status]
              ]"
            >
              <span :class="['w-1.5 h-1.5 rounded-full', statusDots[task.status]]" />
              {{ statusLabels[task.status] }}
            </span>
          </div>
          <p v-if="task.description" class="text-sm text-neutral-500 dark:text-neutral-400 line-clamp-2 mb-3">
            {{ task.description }}
          </p>
          <div class="flex items-center gap-3 text-sm">
            <span
              :class="[
                'inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium rounded-full',
                priorityClasses[task.priority]
              ]"
            >
              {{ task.priority }}
            </span>
            <div v-if="task.assignee" class="flex items-center gap-1.5">
              <AgentAvatar :user="task.assignee" size="xs" />
              <span class="text-neutral-600 dark:text-neutral-300">{{ task.assignee.name }}</span>
            </div>
            <CostBadge
              v-if="task.cost || task.estimatedCost"
              :cost="task.cost || task.estimatedCost!"
              :variant="task.cost ? 'actual' : 'estimated'"
              size="xs"
              class="ml-auto"
            />
          </div>
        </div>
        <!-- Mobile Empty State -->
        <div v-if="filteredTasks.length === 0" class="text-center py-12">
          <Icon name="ph:list-checks" class="w-12 h-12 text-neutral-300 dark:text-neutral-600 mx-auto mb-3" />
          <p class="text-neutral-500 dark:text-neutral-400">No tasks found</p>
        </div>
      </div>

      <!-- Desktop Table View -->
      <div class="hidden md:block bg-white dark:bg-neutral-800 rounded-lg border border-neutral-200 dark:border-neutral-700 overflow-hidden">
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
      :parent-id="selectedProjectId"
      :users="users"
      :channels="channels"
      @created="handleTaskCreated"
    />

    <!-- Create Project Modal -->
    <Modal v-model:open="createProjectModalOpen" title="New Project">
      <form @submit.prevent="handleCreateProject" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
            Project Name
          </label>
          <input
            v-model="newProjectName"
            type="text"
            placeholder="Enter project name..."
            class="w-full px-3 py-2 bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white placeholder:text-neutral-400 focus:outline-none focus:ring-2 focus:ring-neutral-900 dark:focus:ring-white"
            autofocus
          />
        </div>
        <div class="flex justify-end gap-2">
          <button
            type="button"
            class="px-4 py-2 text-sm font-medium text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white transition-colors"
            @click="createProjectModalOpen = false"
          >
            Cancel
          </button>
          <button
            type="submit"
            :disabled="!newProjectName.trim()"
            class="px-4 py-2 text-sm font-medium bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 rounded-lg hover:bg-neutral-800 dark:hover:bg-neutral-100 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Create Project
          </button>
        </div>
      </form>
    </Modal>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import type { Task, TaskStatus, Priority } from '@/types'
import TasksTaskFilters from '@/Components/tasks/TaskFilters.vue'
import TasksTaskBoard from '@/Components/tasks/TaskBoard.vue'
import TasksTaskDetail from '@/Components/tasks/TaskDetail.vue'
import TasksTaskCreateModal from '@/Components/tasks/TaskCreateModal.vue'
import TasksProjectList from '@/Components/tasks/ProjectList.vue'
import Icon from '@/Components/shared/Icon.vue'
import AgentAvatar from '@/Components/shared/AgentAvatar.vue'
import CostBadge from '@/Components/shared/CostBadge.vue'
import Modal from '@/Components/shared/Modal.vue'
import { useApi } from '@/composables/useApi'

type ViewType = 'board' | 'list' | 'timeline'

const { fetchTasks, fetchUsers, fetchChannels, updateTask, reorderTasks, createTask, deleteTask } = useApi()

// Fetch data from API
const { data: tasksData, refresh: refreshTasks } = fetchTasks()
const { data: usersData } = fetchUsers()
const { data: channelsData } = fetchChannels()

const currentFilter = ref('all')
const currentView = ref<ViewType>('board')
const selectedTask = ref<Task | null>(null)
const taskDetailOpen = ref(false)
const createModalOpen = ref(false)
const createInitialStatus = ref<TaskStatus>('backlog')

// Project state
const selectedProjectId = ref<string | null>(null)
const createProjectModalOpen = ref(false)
const newProjectName = ref('')

const tasks = computed<Task[]>(() => tasksData.value ?? [])
const users = computed(() => usersData.value ?? [])
const channels = computed(() => channelsData.value ?? [])

const filteredTasks = computed(() => {
  // Start with non-folder tasks only
  let result = tasks.value.filter(t => !t.isFolder)

  // Filter by selected project
  if (selectedProjectId.value) {
    result = result.filter(t => t.parentId === selectedProjectId.value)
  }

  // Filter by assignee type
  if (currentFilter.value === 'agents') {
    result = result.filter(t => t.assignee?.type === 'agent')
  } else if (currentFilter.value === 'humans') {
    result = result.filter(t => t.assignee?.type === 'human')
  }

  return result
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

const handleTaskCreated = async (taskData: {
  title: string
  description: string
  status: TaskStatus
  priority: Priority
  assigneeId: string
  estimatedCost: number | null
  channelId: string | null
  parentId: string | null
}) => {
  await createTask(taskData)
  await refreshTasks()
}

// Project handlers
const handleProjectSelect = (projectId: string | null) => {
  selectedProjectId.value = projectId
}

const openCreateProjectModal = () => {
  newProjectName.value = ''
  createProjectModalOpen.value = true
}

const handleCreateProject = async () => {
  if (!newProjectName.value.trim()) return

  await createTask({
    title: newProjectName.value.trim(),
    isFolder: true,
    parentId: selectedProjectId.value,
  })

  createProjectModalOpen.value = false
  newProjectName.value = ''
  await refreshTasks()
}

const handleRenameProject = async (project: Task) => {
  const newName = prompt('Enter new project name:', project.title)
  if (newName && newName.trim() !== project.title) {
    await updateTask(project.id, { title: newName.trim() })
    await refreshTasks()
  }
}

const handleDeleteProject = async (project: Task) => {
  if (confirm(`Delete project "${project.title}"? All tasks in this project will also be deleted.`)) {
    await deleteTask(project.id)
    if (selectedProjectId.value === project.id) {
      selectedProjectId.value = null
    }
    await refreshTasks()
  }
}
</script>
