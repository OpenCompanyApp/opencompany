<template>
  <div class="h-full flex flex-col">
    <!-- Header -->
    <header class="px-4 md:px-6 py-3 md:py-0 md:h-14 border-b border-neutral-200 dark:border-neutral-700 flex flex-col md:flex-row md:items-center gap-3 bg-white dark:bg-neutral-900 shrink-0">
      <div class="flex items-center justify-between md:gap-4">
        <div class="flex items-center gap-3 md:gap-4">
          <div class="flex items-center gap-1">
            <span class="text-xl font-bold text-neutral-900 dark:text-white">Tasks</span>
            <Link
              href="/workload"
              class="ml-2 px-2 py-1 text-sm font-medium text-neutral-500 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-md transition-colors"
            >
              Workload
            </Link>
            <Link
              href="/activity"
              class="px-2 py-1 text-sm font-medium text-neutral-500 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-md transition-colors"
            >
              Activity
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
              'px-3 py-1 text-sm font-medium rounded-md transition-colors whitespace-nowrap',
              currentFilter === filter.value
                ? 'bg-white dark:bg-neutral-700 text-neutral-900 dark:text-white shadow-sm'
                : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white'
            ]"
            @click="currentFilter = filter.value"
          >
            {{ filter.label }}
          </button>
        </div>
        <!-- Agent Filter -->
        <select
          v-model="agentFilter"
          class="px-2 py-1 text-sm bg-neutral-100 dark:bg-neutral-800 border-0 rounded-lg text-neutral-700 dark:text-neutral-300 focus:outline-none focus:ring-2 focus:ring-neutral-900 dark:focus:ring-white"
        >
          <option value="">All agents</option>
          <option v-for="agent in agents" :key="agent.id" :value="agent.id">
            {{ agent.name }}
          </option>
        </select>
        <!-- Source Filter -->
        <select
          v-model="sourceFilter"
          class="px-2 py-1 text-sm bg-neutral-100 dark:bg-neutral-800 border-0 rounded-lg text-neutral-700 dark:text-neutral-300 focus:outline-none focus:ring-2 focus:ring-neutral-900 dark:focus:ring-white"
        >
          <option value="">All sources</option>
          <option value="chat">Chat</option>
          <option value="manual">Manual</option>
          <option value="automation">Automation</option>
          <option value="agent_delegation">Delegation</option>
          <option value="agent_ask">Agent Ask</option>
          <option value="agent_notify">Notification</option>
        </select>
        <button
          class="hidden md:flex items-center gap-2 px-3 py-1.5 bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 text-sm font-medium rounded-lg hover:bg-neutral-800 dark:hover:bg-neutral-100 transition-colors shrink-0"
          @click="createModalOpen = true"
        >
          <Icon name="ph:plus-bold" class="w-4 h-4" />
          <span>New Task</span>
        </button>
      </div>
    </header>

    <!-- Compact Task List -->
    <div class="flex-1 overflow-auto">
      <!-- Table Header -->
      <div class="sticky top-0 bg-neutral-50 dark:bg-neutral-800/50 border-b border-neutral-200 dark:border-neutral-700 px-4 md:px-6">
        <div class="flex items-center gap-3 h-8 text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
          <span class="w-4" />
          <span class="flex-1 min-w-0">Task</span>
          <span class="w-32 hidden md:block">Agent</span>
          <span class="w-16 text-center hidden sm:block">Source</span>
          <span class="w-16 text-center hidden sm:block">Steps</span>
          <span class="w-20 text-right">Time</span>
        </div>
      </div>

      <!-- Task Rows -->
      <div class="divide-y divide-neutral-100 dark:divide-neutral-800">
        <div
          v-for="{ task, depth, childCount } in treeifiedTasks"
          :key="task.id"
          :class="[
            'flex items-center gap-2 md:gap-3 h-10 cursor-pointer hover:bg-neutral-50 dark:hover:bg-neutral-800/50 transition-colors',
            depth > 0 && 'bg-neutral-50/40 dark:bg-neutral-800/20'
          ]"
          :style="{ paddingLeft: `${16 + depth * 20}px`, paddingRight: '16px' }"
          @click="openTaskDetail(task)"
        >
          <!-- Collapse/expand toggle for parent tasks -->
          <button
            v-if="childCount > 0"
            class="w-4 h-4 flex items-center justify-center shrink-0 rounded hover:bg-neutral-200 dark:hover:bg-neutral-700 transition-colors"
            @click.stop="toggleCollapsed(task.id)"
          >
            <Icon
              :name="collapsedIds.has(task.id) ? 'ph:caret-right' : 'ph:caret-down'"
              class="w-3 h-3 text-neutral-400"
            />
          </button>
          <span v-else class="w-4 shrink-0" />

          <!-- Status dot -->
          <span :class="['w-2 h-2 rounded-full shrink-0', statusDots[task.status]]" />

          <!-- Nesting arrows + Title -->
          <span class="flex-1 min-w-0 text-sm text-neutral-900 dark:text-white truncate flex items-center gap-0.5">
            <Icon
              v-for="i in depth"
              :key="i"
              name="ph:arrow-bend-down-right"
              class="w-3.5 h-3.5 text-indigo-400/60 shrink-0"
            />
            <span class="truncate" :class="depth > 0 && 'ml-0.5'">{{ task.title }}</span>
            <span v-if="childCount > 0" class="ml-1 flex items-center gap-0.5 text-xs text-indigo-400/80 shrink-0" :title="`${childCount} subtask${childCount > 1 ? 's' : ''}`">
              <Icon name="ph:tree-structure" class="w-3 h-3" />
              {{ childCount }}
            </span>
          </span>

          <!-- Agent (with delegation flow for delegated tasks) -->
          <div class="w-32 hidden md:flex items-center gap-1 shrink-0">
            <template v-if="['agent_delegation', 'agent_ask', 'agent_notify'].includes(task.source) && task.requester && task.agent">
              <AgentAvatar :user="task.requester" size="xs" :show-status="false" />
              <Icon name="ph:arrow-right" class="w-3 h-3 text-neutral-400 shrink-0" />
              <AgentAvatar :user="task.agent" size="xs" :show-status="false" />
              <span class="text-xs text-neutral-600 dark:text-neutral-400 truncate">{{ task.agent.name }}</span>
            </template>
            <template v-else-if="task.agent">
              <AgentAvatar :user="task.agent" size="xs" :show-status="false" />
              <span class="text-xs text-neutral-600 dark:text-neutral-400 truncate">{{ task.agent.name }}</span>
            </template>
            <span v-else class="text-xs text-neutral-400">—</span>
          </div>

          <!-- Source icon -->
          <div class="w-16 hidden sm:flex items-center justify-center shrink-0">
            <Icon
              v-if="task.source === 'chat'"
              name="ph:chat-circle"
              class="w-4 h-4 text-neutral-400"
              title="Chat"
            />
            <Icon
              v-else-if="task.source === 'automation'"
              name="ph:lightning"
              class="w-4 h-4 text-neutral-400"
              title="Automation"
            />
            <Icon
              v-else-if="task.source === 'agent_delegation'"
              name="ph:users-three"
              class="w-4 h-4 text-indigo-400"
              title="Delegation"
            />
            <Icon
              v-else-if="task.source === 'agent_ask'"
              name="ph:question"
              class="w-4 h-4 text-indigo-400"
              title="Agent Ask"
            />
            <Icon
              v-else-if="task.source === 'agent_notify'"
              name="ph:megaphone"
              class="w-4 h-4 text-amber-400"
              title="Notification"
            />
            <Icon
              v-else
              name="ph:hand"
              class="w-4 h-4 text-neutral-400"
              title="Manual"
            />
          </div>

          <!-- Steps count -->
          <div class="w-16 hidden sm:flex items-center justify-center shrink-0">
            <span v-if="task.steps && task.steps.length > 0" class="text-xs text-neutral-500 dark:text-neutral-400">
              {{ task.steps.filter(s => s.status === 'completed').length }}/{{ task.steps.length }}
            </span>
            <span v-else class="text-xs text-neutral-400">—</span>
          </div>

          <!-- Time ago -->
          <span class="w-20 text-right text-xs text-neutral-400 shrink-0">
            {{ timeAgo(task.createdAt) }}
          </span>
        </div>
      </div>

      <!-- Empty State -->
      <div v-if="treeifiedTasks.length === 0" class="text-center py-12">
        <Icon name="ph:briefcase" class="w-12 h-12 text-neutral-300 dark:text-neutral-600 mx-auto mb-3" />
        <h3 class="text-sm font-medium text-neutral-900 dark:text-white mb-1">No tasks found</h3>
        <p class="text-sm text-neutral-500 dark:text-neutral-400 mb-4">
          {{ currentFilter === 'all' && !agentFilter && !sourceFilter ? 'Create your first task to get started' : 'No tasks match the current filters' }}
        </p>
        <button
          class="inline-flex items-center gap-2 px-3 py-1.5 bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 text-sm font-medium rounded-lg hover:bg-neutral-800 dark:hover:bg-neutral-100 transition-colors"
          @click="createModalOpen = true"
        >
          <Icon name="ph:plus-bold" class="w-4 h-4" />
          <span>Create Task</span>
        </button>
      </div>
    </div>

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
import { ref, computed, onUnmounted } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import type { AgentTask, TaskStatus, TaskType, Priority, User } from '@/types'
import Icon from '@/Components/shared/Icon.vue'
import AgentAvatar from '@/Components/shared/AgentAvatar.vue'
import Modal from '@/Components/shared/Modal.vue'
import { useApi } from '@/composables/useApi'
import { useRealtime } from '@/composables/useRealtime'

const {
  fetchAgentTasks,
  fetchAgents,
  createAgentTask,
} = useApi()

const currentUserId = ref('h1')

const { data: tasksData, refresh: refreshTasks } = fetchAgentTasks()

// Real-time task updates
const { on } = useRealtime()
const unsubTask = on('task:updated', () => refreshTasks())
const { data: agentsData } = fetchAgents()

const currentFilter = ref<'all' | 'pending' | 'active' | 'completed'>('all')
const agentFilter = ref('')
const sourceFilter = ref('')
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
  let result = tasks.value

  // Status filter
  if (currentFilter.value === 'pending') {
    result = result.filter(t => t.status === 'pending')
  } else if (currentFilter.value === 'active') {
    result = result.filter(t => t.status === 'active' || t.status === 'paused')
  } else if (currentFilter.value === 'completed') {
    result = result.filter(t => ['completed', 'failed', 'cancelled'].includes(t.status))
  }

  // Agent filter
  if (agentFilter.value) {
    result = result.filter(t => t.agentId === agentFilter.value)
  }

  // Source filter
  if (sourceFilter.value) {
    result = result.filter(t => t.source === sourceFilter.value)
  }

  return result
})

// ── Tree structure for hierarchical nesting ──────────────────────
const collapsedIds = ref(new Set<string>())
let collapsedInitialized = false

const toggleCollapsed = (id: string) => {
  const next = new Set(collapsedIds.value)
  if (next.has(id)) {
    next.delete(id)
  } else {
    next.add(id)
  }
  collapsedIds.value = next
}

interface TaskWithDepth {
  task: AgentTask
  depth: number
  childCount: number
}

const treeifiedTasks = computed<TaskWithDepth[]>(() => {
  const items = filteredTasks.value
  if (!items.length) return []

  // Build lookup maps
  const byId = new Map<string, AgentTask>()
  const childrenOf = new Map<string, AgentTask[]>()

  for (const t of items) {
    byId.set(t.id, t)
  }

  for (const t of items) {
    if (t.parentTaskId && byId.has(t.parentTaskId)) {
      const siblings = childrenOf.get(t.parentTaskId) ?? []
      siblings.push(t)
      childrenOf.set(t.parentTaskId, siblings)
    }
  }

  // Identify root tasks
  const roots: AgentTask[] = []
  for (const t of items) {
    if (!t.parentTaskId || !byId.has(t.parentTaskId)) {
      roots.push(t)
    }
  }

  // Auto-collapse on first load: collapse all roots except the most recent one
  if (!collapsedInitialized && roots.length > 0) {
    collapsedInitialized = true
    const rootsWithChildren = roots.filter(r => childrenOf.has(r.id))
    if (rootsWithChildren.length > 1) {
      // Most recent root is first in the list (sorted by created_at desc from API)
      const next = new Set(collapsedIds.value)
      for (let i = 1; i < rootsWithChildren.length; i++) {
        next.add(rootsWithChildren[i].id)
      }
      collapsedIds.value = next
    }
  }

  // Count all descendants (not just direct children)
  const countDescendants = (id: string): number => {
    const children = childrenOf.get(id) ?? []
    let count = children.length
    for (const child of children) {
      count += countDescendants(child.id)
    }
    return count
  }

  // Walk depth-first
  const result: TaskWithDepth[] = []
  const visited = new Set<string>()

  const walk = (task: AgentTask, depth: number) => {
    if (visited.has(task.id)) return
    visited.add(task.id)

    const childCount = countDescendants(task.id)
    result.push({ task, depth, childCount })

    const children = childrenOf.get(task.id) ?? []
    if (!collapsedIds.value.has(task.id)) {
      for (const child of children) {
        walk(child, depth + 1)
      }
    } else {
      // Mark collapsed descendants as visited so they don't appear as orphans
      const markVisited = (id: string) => {
        visited.add(id)
        for (const c of childrenOf.get(id) ?? []) {
          markVisited(c.id)
        }
      }
      for (const child of children) {
        markVisited(child.id)
      }
    }
  }

  // Start with root tasks (no parent in filtered set)
  for (const t of roots) {
    walk(t, 0)
  }

  // Any orphans not visited (shouldn't happen, but safety)
  for (const t of items) {
    if (!visited.has(t.id)) {
      walk(t, 0)
    }
  }

  return result
})

const taskCounts = computed(() => ({
  total: tasks.value.length,
  active: tasks.value.filter(t => t.status === 'active').length,
  completed: tasks.value.filter(t => t.status === 'completed').length,
}))

// Status styling
const statusDots: Record<TaskStatus, string> = {
  pending: 'bg-neutral-400',
  active: 'bg-blue-500 animate-pulse',
  paused: 'bg-amber-500',
  completed: 'bg-green-500',
  failed: 'bg-red-500',
  cancelled: 'bg-neutral-400',
}

const timeAgo = (date: Date | string) => {
  const d = new Date(date)
  const now = new Date()
  const seconds = Math.floor((now.getTime() - d.getTime()) / 1000)

  if (seconds < 60) return 'just now'
  const minutes = Math.floor(seconds / 60)
  if (minutes < 60) return `${minutes}m ago`
  const hours = Math.floor(minutes / 60)
  if (hours < 24) return `${hours}h ago`
  const days = Math.floor(hours / 24)
  if (days < 7) return `${days}d ago`
  return d.toLocaleDateString()
}

const openTaskDetail = (task: AgentTask) => {
  router.visit(`/tasks/${task.id}`)
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

onUnmounted(() => unsubTask())
</script>
