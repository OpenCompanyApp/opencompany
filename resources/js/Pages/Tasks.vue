<template>
  <div class="h-full flex flex-col">
    <!-- Header -->
    <header class="px-4 md:px-6 h-14 border-b border-neutral-200 dark:border-neutral-700 flex items-center gap-4 bg-white dark:bg-neutral-900 shrink-0">
      <div class="flex items-center gap-1">
        <span class="text-lg font-semibold text-neutral-900 dark:text-white">Tasks</span>
        <Link
          :href="workspacePath('/workload')"
          class="ml-1 px-2 py-1 text-sm font-medium text-neutral-500 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-md transition-colors"
        >
          Workload
        </Link>
        <Link
          :href="workspacePath('/activity')"
          class="px-2 py-1 text-sm font-medium text-neutral-500 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-md transition-colors"
        >
          Activity
        </Link>
      </div>
      <span class="hidden md:inline-flex items-center gap-1 text-xs tabular-nums shrink-0" title="Total / Active / Done">
        <span class="text-neutral-500 dark:text-neutral-400">{{ taskCounts.total }}</span>
        <span class="text-neutral-300 dark:text-neutral-600">/</span>
        <span class="text-blue-500 dark:text-blue-400">{{ taskCounts.active }}</span>
        <span class="text-neutral-300 dark:text-neutral-600">/</span>
        <span class="text-green-500 dark:text-green-400">{{ taskCounts.completed }}</span>
      </span>
      <div class="ml-auto flex items-center gap-2">
        <SearchInput
          v-model="searchQuery"
          placeholder="Search tasks..."
          variant="ghost"
          size="sm"
          :clearable="true"
          :debounce="300"
          class="w-36 lg:w-48 shrink-0"
        />
        <DropdownMenu side="bottom" align="end">
          <button class="inline-flex items-center gap-1.5 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg px-2.5 py-1.5 text-sm text-neutral-700 dark:text-neutral-300 hover:border-neutral-300 dark:hover:border-neutral-600 transition-colors shrink-0">
            <span v-if="currentFilter !== 'all'" :class="['w-2 h-2 rounded-full shrink-0', statusFilterDots[currentFilter]]" />
            <span>{{ activeStatusLabel }}</span>
            <Icon name="ph:caret-down" class="w-3 h-3 text-neutral-400" />
          </button>
          <template #content>
            <button
              v-for="filter in statusFilters"
              :key="filter.value"
              class="flex items-center gap-2 w-full px-2 py-1.5 text-sm rounded cursor-pointer outline-none select-none hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors"
              :class="currentFilter === filter.value ? 'text-neutral-900 dark:text-white font-medium' : 'text-neutral-600 dark:text-neutral-300'"
              @click="currentFilter = filter.value"
            >
              <span v-if="filter.dot" :class="['w-2 h-2 rounded-full shrink-0', filter.dot]" />
              <span v-else class="w-2" />
              <span>{{ filter.label }}</span>
            </button>
          </template>
        </DropdownMenu>
        <select
          v-model="agentFilter"
          class="hidden md:block bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg px-2.5 py-1.5 text-sm text-neutral-700 dark:text-neutral-300 outline-none focus:border-neutral-300 shrink-0"
        >
          <option value="">All agents</option>
          <option v-for="agent in agents" :key="agent.id" :value="agent.id">
            {{ agent.name }}
          </option>
        </select>
        <select
          v-model="sourceFilter"
          class="hidden lg:block bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg px-2.5 py-1.5 text-sm text-neutral-700 dark:text-neutral-300 outline-none focus:border-neutral-300 shrink-0"
        >
          <option value="">All sources</option>
          <option value="chat">Chat</option>
          <option value="manual">Manual</option>
          <option value="automation">Automation</option>
          <option value="agent_delegation">Delegation</option>
          <option value="agent_ask">Agent Ask</option>
          <option value="agent_notify">Notification</option>
        </select>
      </div>
    </header>

    <!-- Compact Task List -->
    <div class="flex-1 overflow-auto">
      <!-- Table Header -->
      <div class="sticky top-0 bg-neutral-50 dark:bg-neutral-800 border-b border-neutral-200 dark:border-neutral-700 px-4 md:px-6 z-10">
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
      <div v-if="!loading && treeifiedTasks.length === 0" class="text-center py-12">
        <Icon name="ph:briefcase" class="w-12 h-12 text-neutral-300 dark:text-neutral-600 mx-auto mb-3" />
        <h3 class="text-sm font-medium text-neutral-900 dark:text-white mb-1">
          {{ currentFilter === 'all' && !agentFilter && !sourceFilter && !searchQuery ? 'No tasks yet' : 'No tasks match the current filters' }}
        </h3>
        <p class="text-sm text-neutral-500 dark:text-neutral-400">
          {{ currentFilter === 'all' && !agentFilter && !sourceFilter && !searchQuery ? 'Tasks will appear here when created via chat or automation.' : 'Try adjusting your filters.' }}
        </p>
      </div>
    </div>

    <!-- Pagination Bar -->
    <div
      v-if="paginationTotal > 0"
      class="shrink-0 border-t border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 px-4 md:px-6 py-2 flex items-center justify-between gap-4"
    >
      <!-- Left: showing range -->
      <span class="text-xs text-neutral-500 dark:text-neutral-400 whitespace-nowrap">
        {{ paginationFrom }}–{{ paginationTo }} of {{ paginationTotal }}
      </span>

      <!-- Center: page navigation -->
      <div v-if="totalPages > 1" class="flex items-center gap-1">
        <button
          :disabled="currentPage <= 1"
          class="px-2 py-1 text-xs font-medium rounded-md transition-colors text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 disabled:opacity-30 disabled:cursor-not-allowed"
          @click="goToPage(currentPage - 1)"
        >
          <Icon name="ph:caret-left" class="w-3.5 h-3.5" />
        </button>
        <template v-for="page in visiblePages" :key="page">
          <span v-if="page === '...'" class="px-1 text-xs text-neutral-400">...</span>
          <button
            v-else
            :class="[
              'px-2 py-1 text-xs font-medium rounded-md transition-colors',
              page === currentPage
                ? 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900'
                : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800'
            ]"
            @click="goToPage(page as number)"
          >
            {{ page }}
          </button>
        </template>
        <button
          :disabled="currentPage >= totalPages"
          class="px-2 py-1 text-xs font-medium rounded-md transition-colors text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 disabled:opacity-30 disabled:cursor-not-allowed"
          @click="goToPage(currentPage + 1)"
        >
          <Icon name="ph:caret-right" class="w-3.5 h-3.5" />
        </button>
      </div>

      <!-- Right: per-page selector -->
      <select
        v-model.number="perPage"
        class="h-7 px-2 text-xs bg-neutral-100 dark:bg-neutral-800 border-0 rounded-md text-neutral-600 dark:text-neutral-300 focus:outline-none focus:ring-2 focus:ring-neutral-900 dark:focus:ring-white cursor-pointer"
      >
        <option :value="25">25 / page</option>
        <option :value="50">50 / page</option>
        <option :value="100">100 / page</option>
      </select>
    </div>

  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onUnmounted } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import { useWorkspace } from '@/composables/useWorkspace'
import type { AgentTask, TaskStatus, User } from '@/types'
import type { PaginatedResponse } from '@/composables/useApi'
import DropdownMenu from '@/Components/shared/DropdownMenu.vue'
import Icon from '@/Components/shared/Icon.vue'
import AgentAvatar from '@/Components/shared/AgentAvatar.vue'
import SearchInput from '@/Components/shared/SearchInput.vue'
import { useApi } from '@/composables/useApi'
import { useRealtime } from '@/composables/useRealtime'

const { workspacePath } = useWorkspace()
const {
  fetchAgentTasks,
  fetchAgents,
} = useApi()

// ── Filters & pagination state ──────────────────────────────────
const currentFilter = ref<'all' | 'pending' | 'active' | 'completed'>('all')
const agentFilter = ref('')
const sourceFilter = ref('')
const searchQuery = ref('')
const currentPage = ref(1)
const perPage = ref(50)

// Map UI status filter to server-side status values
const statusMap: Record<string, string | string[] | undefined> = {
  all: undefined,
  pending: 'pending',
  active: ['active', 'paused'],
  completed: ['completed', 'failed', 'cancelled'],
}

// Fetch tasks with current filters + page
const tasksResponse = ref<PaginatedResponse<AgentTask> | null>(null)
const loading = ref(true)

const loadTasks = async () => {
  loading.value = true
  const status = statusMap[currentFilter.value]
  const { data, promise } = fetchAgentTasks({
    status: status as string | string[] | undefined,
    agentId: agentFilter.value || undefined,
    source: sourceFilter.value || undefined,
    search: searchQuery.value || undefined,
    page: currentPage.value,
    perPage: perPage.value,
  })
  await promise
  tasksResponse.value = data.value
  loading.value = false
}

// Initial load
loadTasks()

// Reload when filters change (reset to page 1)
watch([currentFilter, agentFilter, sourceFilter, searchQuery, perPage], () => {
  if (currentPage.value !== 1) {
    currentPage.value = 1 // triggers the page watcher which loads
  } else {
    loadTasks()
  }
})

// Reload when page changes
watch(currentPage, () => {
  loadTasks()
})

// Real-time task updates — refresh current page
const { on } = useRealtime()
const unsubTask = on('task:updated', () => loadTasks())
const { data: agentsData } = fetchAgents()

type StatusFilterValue = 'all' | 'pending' | 'active' | 'completed'
const statusFilters: { label: string; value: StatusFilterValue; dot?: string }[] = [
  { label: 'All statuses', value: 'all' },
  { label: 'Pending', value: 'pending', dot: 'bg-neutral-400' },
  { label: 'Active', value: 'active', dot: 'bg-blue-500' },
  { label: 'Completed', value: 'completed', dot: 'bg-green-500' },
]

const statusFilterDots: Record<string, string> = {
  pending: 'bg-neutral-400',
  active: 'bg-blue-500',
  completed: 'bg-green-500',
}

const activeStatusLabel = computed(() =>
  statusFilters.find(f => f.value === currentFilter.value)?.label ?? 'All statuses'
)

const tasks = computed<AgentTask[]>(() => tasksResponse.value?.data ?? [])
const agents = computed<User[]>(() => agentsData.value ?? [])

// Pagination metadata
const totalPages = computed(() => tasksResponse.value?.last_page ?? 1)
const paginationTotal = computed(() => tasksResponse.value?.total ?? 0)
const paginationFrom = computed(() => tasksResponse.value?.from ?? 0)
const paginationTo = computed(() => tasksResponse.value?.to ?? 0)

const taskCounts = computed(() => {
  const counts = tasksResponse.value?.counts
  return {
    total: counts?.total ?? 0,
    active: counts?.active ?? 0,
    completed: counts?.completed ?? 0,
  }
})

// Visible page numbers with ellipsis
const visiblePages = computed<(number | '...')[]>(() => {
  const total = totalPages.value
  const current = currentPage.value
  if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1)

  const pages: (number | '...')[] = [1]
  if (current > 3) pages.push('...')
  for (let i = Math.max(2, current - 1); i <= Math.min(total - 1, current + 1); i++) {
    pages.push(i)
  }
  if (current < total - 2) pages.push('...')
  pages.push(total)
  return pages
})

const goToPage = (page: number) => {
  if (page < 1 || page > totalPages.value) return
  currentPage.value = page
}

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
  const items = tasks.value
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
  router.visit(workspacePath(`/tasks/${task.id}`))
}

onUnmounted(() => unsubTask())
</script>
