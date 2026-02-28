<template>
  <div class="h-full flex flex-col">
    <!-- Header -->
    <header class="px-4 md:px-6 py-3 md:py-0 md:h-14 border-b border-neutral-200 dark:border-neutral-700 flex flex-col md:flex-row md:items-center gap-3 bg-white dark:bg-neutral-900 shrink-0">
      <div class="flex items-center justify-between md:gap-4 flex-1">
        <div class="flex items-center gap-3 md:gap-4">
          <div class="flex items-center gap-1">
            <Link
              :href="workspacePath('/tasks')"
              class="px-2 py-1 text-sm font-medium text-neutral-500 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-md transition-colors"
            >
              Tasks
            </Link>
            <Link
              :href="workspacePath('/workload')"
              class="px-2 py-1 text-sm font-medium text-neutral-500 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-md transition-colors"
            >
              Workload
            </Link>
            <span class="text-xl font-bold text-neutral-900 dark:text-white">Activity</span>
            <Link
              :href="workspacePath('/tasks/analytics')"
              class="px-2 py-1 text-sm font-medium text-neutral-500 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-md transition-colors"
            >
              Analytics
            </Link>
          </div>
          <span v-if="totalCount > 0" class="hidden md:inline text-sm text-neutral-400">{{ totalCount }} activities</span>
        </div>
        <button
          class="p-2 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-lg transition-colors"
          title="Refresh"
          @click="reloadActivities"
        >
          <Icon name="ph:arrows-clockwise" class="w-4 h-4" />
        </button>
      </div>
    </header>

    <!-- Filters -->
    <div class="px-4 md:px-6 py-2.5 border-b border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 shrink-0 flex items-center gap-3 overflow-x-auto">
      <!-- Type Filter -->
      <div class="flex items-center gap-1 p-1 bg-neutral-50 dark:bg-neutral-800 rounded-lg shrink-0">
        <button
          v-for="type in activityTypes"
          :key="type.value"
          :class="[
            'px-3 py-1.5 text-sm rounded-md transition-colors whitespace-nowrap',
            selectedType === type.value
              ? 'bg-neutral-900 dark:bg-neutral-600 text-white'
              : 'text-neutral-500 dark:text-neutral-300 hover:text-neutral-700 dark:hover:text-neutral-200'
          ]"
          @click="selectedType = type.value"
        >
          {{ type.label }}
        </button>
      </div>

      <!-- User Filter -->
      <select
        v-model="selectedUserId"
        class="bg-neutral-50 dark:bg-neutral-800 rounded-lg px-3 py-2 text-sm outline-none border border-neutral-200 dark:border-neutral-700 focus:border-neutral-300 dark:text-white shrink-0"
      >
        <option value="">All users</option>
        <option v-for="user in users" :key="user.id" :value="user.id">
          {{ user.name }}
        </option>
      </select>

      <!-- Date Filter -->
      <select
        v-model="selectedDateRange"
        class="bg-neutral-50 dark:bg-neutral-800 rounded-lg px-3 py-2 text-sm outline-none border border-neutral-200 dark:border-neutral-700 focus:border-neutral-300 dark:text-white shrink-0"
      >
        <option value="today">Today</option>
        <option value="week">This Week</option>
        <option value="month">This Month</option>
        <option value="all">All Time</option>
      </select>

      <!-- Clear Filters -->
      <button
        v-if="hasActiveFilters"
        class="px-3 py-2 text-sm text-neutral-500 dark:text-neutral-300 hover:text-neutral-700 dark:hover:text-neutral-200 transition-colors whitespace-nowrap shrink-0"
        @click="clearFilters"
      >
        Clear filters
      </button>
    </div>

    <!-- Activity List -->
    <div class="flex-1 overflow-y-auto">
      <!-- Loading -->
      <div v-if="loading && activities.length === 0" class="flex items-center justify-center py-20">
        <Icon name="ph:circle-notch" class="w-6 h-6 text-neutral-400 animate-spin" />
      </div>

      <div v-else class="max-w-4xl mx-auto px-6 py-6">
        <!-- Activity Timeline -->
        <div class="relative">
          <!-- Timeline line -->
          <div v-if="activities.length" class="absolute left-[19px] top-0 bottom-0 w-0.5 bg-neutral-200 dark:bg-neutral-700" />

          <!-- Activity Items -->
          <div class="space-y-4">
            <component
              :is="activity.url ? Link : 'div'"
              v-for="activity in activities"
              :key="activity.id"
              :href="activity.url ? workspacePath(activity.url) : undefined"
              class="relative flex gap-4 group"
            >
              <!-- Timeline dot -->
              <div
                :class="[
                  'relative z-10 w-10 h-10 rounded-full flex items-center justify-center shrink-0',
                  getActivityIconBg(activity.type)
                ]"
              >
                <Icon :name="getActivityIcon(activity.type)" class="w-5 h-5 text-white" />
              </div>

              <!-- Activity Content -->
              <div class="flex-1 min-w-0 pb-4">
                <div :class="[
                  'p-4 bg-neutral-50 dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700 transition-colors',
                  activity.url ? 'group-hover:border-neutral-300 dark:group-hover:border-neutral-600' : ''
                ]">
                  <div class="flex items-start justify-between gap-3">
                    <div class="flex-1 min-w-0">
                      <div class="flex items-center gap-2 mb-1">
                        <span v-if="activity.actor" class="font-medium text-neutral-700 dark:text-neutral-200">
                          {{ activity.actor.name }}
                        </span>
                        <span class="text-neutral-500 dark:text-neutral-300">{{ getActivityVerb(activity.type) }}</span>
                      </div>
                      <p class="text-sm text-neutral-700 dark:text-neutral-200">
                        {{ activity.description }}
                      </p>
                      <div v-if="activity.metadata" class="mt-2 flex flex-wrap gap-1.5">
                        <span
                          v-if="activity.metadata.taskTitle"
                          class="inline-flex items-center gap-1.5 px-2 py-1 bg-neutral-100 dark:bg-neutral-700 rounded-lg text-xs text-neutral-500 dark:text-neutral-300"
                        >
                          <Icon name="ph:check-square" class="w-3.5 h-3.5" />
                          {{ activity.metadata.taskTitle }}
                        </span>
                        <span
                          v-if="activity.metadata.amount"
                          class="inline-flex items-center gap-1.5 px-2 py-1 bg-neutral-100 dark:bg-neutral-700 rounded-lg text-xs text-neutral-500 dark:text-neutral-300"
                        >
                          <Icon name="ph:coins" class="w-3.5 h-3.5" />
                          ${{ activity.metadata.amount }}
                        </span>
                        <span
                          v-if="activity.metadata.channelName"
                          class="inline-flex items-center gap-1.5 px-2 py-1 bg-neutral-100 dark:bg-neutral-700 rounded-lg text-xs text-neutral-500 dark:text-neutral-300"
                        >
                          <Icon name="ph:hash" class="w-3.5 h-3.5" />
                          {{ activity.metadata.channelName }}
                        </span>
                      </div>
                    </div>
                    <span class="text-xs text-neutral-500 dark:text-neutral-300 whitespace-nowrap">
                      {{ formatTimeAgo(activity.timestamp) }}
                    </span>
                  </div>
                </div>
              </div>
            </component>

            <!-- Load More -->
            <div v-if="hasMore" class="flex justify-center pt-4">
              <button
                class="px-4 py-2 text-sm text-neutral-500 dark:text-neutral-300 hover:text-neutral-700 dark:hover:text-neutral-200 hover:bg-neutral-50 dark:hover:bg-neutral-800 rounded-lg transition-colors"
                :disabled="loadingMore"
                @click="loadMore"
              >
                <Icon v-if="loadingMore" name="ph:circle-notch" class="w-4 h-4 animate-spin inline mr-2" />
                {{ loadingMore ? 'Loading...' : 'Load more' }}
              </button>
            </div>

            <!-- Empty State -->
            <div v-if="activities.length === 0 && !loading" class="text-center py-12">
              <Icon name="ph:activity" class="w-12 h-12 mx-auto mb-4 text-neutral-300 dark:text-neutral-600" />
              <p class="text-neutral-500 dark:text-neutral-400">No activities found</p>
              <p v-if="hasActiveFilters" class="text-sm text-neutral-400 dark:text-neutral-500 mt-1">
                Try adjusting your filters
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useWorkspace } from '@/composables/useWorkspace'
import type { User, Activity } from '@/types'
import Icon from '@/Components/shared/Icon.vue'
import { useApi } from '@/composables/useApi'
import { useRealtime } from '@/composables/useRealtime'

interface ExtendedActivity extends Activity {
  actor?: User
  url?: string | null
  metadata?: {
    taskTitle?: string
    taskId?: string
    amount?: number
    channelName?: string
    channelId?: string
    [key: string]: unknown
  }
}

const { workspacePath } = useWorkspace()
const { fetchActivities, fetchUsers } = useApi()
const { on } = useRealtime()

const activityTypes = [
  { value: '', label: 'All' },
  { value: 'message', label: 'Messages' },
  { value: 'task_completed', label: 'Completed' },
  { value: 'task_started', label: 'Started' },
  { value: 'task_failed', label: 'Failed' },
  { value: 'approval_needed', label: 'Approvals' },
  { value: 'agent_spawned', label: 'Agents' },
]

const selectedType = ref('')
const selectedUserId = ref('')
const selectedDateRange = ref('week')
const loadingMore = ref(false)
const activities = ref<ExtendedActivity[]>([])
const totalCount = ref(0)
const hasMore = ref(false)
const loading = ref(true)
const offset = ref(0)
const LIMIT = 50

// Users list for filter dropdown
const { data: usersData } = fetchUsers()
const users = computed<User[]>(() => usersData.value ?? [])

const hasActiveFilters = computed(() =>
  selectedType.value !== '' ||
  selectedUserId.value !== '' ||
  selectedDateRange.value !== 'all'
)

function getSinceDate(): string | undefined {
  if (selectedDateRange.value === 'all') return undefined
  const now = new Date()
  switch (selectedDateRange.value) {
    case 'today': {
      const d = new Date(now.getFullYear(), now.getMonth(), now.getDate())
      return d.toISOString().split('T')[0]
    }
    case 'week': {
      const d = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000)
      return d.toISOString().split('T')[0]
    }
    case 'month': {
      const d = new Date(now.getTime() - 30 * 24 * 60 * 60 * 1000)
      return d.toISOString().split('T')[0]
    }
  }
  return undefined
}

async function loadActivities(append = false) {
  if (!append) {
    loading.value = true
    offset.value = 0
  }

  const { data, promise } = fetchActivities({
    limit: LIMIT,
    offset: offset.value,
    type: selectedType.value || undefined,
    userId: selectedUserId.value || undefined,
    since: getSinceDate(),
  })

  await promise

  if (data.value) {
    if (append) {
      activities.value = [...activities.value, ...data.value.data]
    } else {
      activities.value = data.value.data
    }
    totalCount.value = data.value.total
    hasMore.value = data.value.hasMore
  }

  loading.value = false
}

async function loadMore() {
  loadingMore.value = true
  offset.value += LIMIT
  await loadActivities(true)
  loadingMore.value = false
}

function reloadActivities() {
  loadActivities()
}

const clearFilters = () => {
  selectedType.value = ''
  selectedUserId.value = ''
  selectedDateRange.value = 'all'
}

// Re-fetch when filters change
watch([selectedType, selectedUserId, selectedDateRange], () => {
  loadActivities()
})

const getActivityIcon = (type: string): string => {
  const icons: Record<string, string> = {
    message: 'ph:chat-circle-fill',
    task_completed: 'ph:check-circle-fill',
    task_started: 'ph:play-circle-fill',
    task_failed: 'ph:x-circle-fill',
    agent_spawned: 'ph:robot-fill',
    approval_needed: 'ph:seal-question-fill',
    approval_granted: 'ph:seal-check-fill',
    error: 'ph:warning-circle-fill',
  }
  return icons[type] || 'ph:info-fill'
}

const getActivityIconBg = (type: string): string => {
  const colors: Record<string, string> = {
    message: 'bg-blue-500',
    task_completed: 'bg-green-500',
    task_started: 'bg-yellow-500',
    task_failed: 'bg-red-500',
    agent_spawned: 'bg-purple-500',
    approval_needed: 'bg-orange-500',
    approval_granted: 'bg-green-500',
    error: 'bg-red-500',
  }
  return colors[type] || 'bg-neutral-500'
}

const getActivityVerb = (type: string): string => {
  const verbs: Record<string, string> = {
    message: 'sent a message',
    task_completed: 'completed a task',
    task_started: 'started working on',
    task_failed: 'failed a task',
    agent_spawned: 'was created',
    approval_needed: 'requested approval',
    approval_granted: 'approved',
    error: 'encountered an error',
  }
  return verbs[type] || 'performed an action'
}

const formatTimeAgo = (date: Date | string): string => {
  const d = new Date(date)
  const seconds = Math.floor((Date.now() - d.getTime()) / 1000)
  if (seconds < 60) return 'just now'
  const minutes = Math.floor(seconds / 60)
  if (minutes < 60) return `${minutes}m ago`
  const hours = Math.floor(minutes / 60)
  if (hours < 24) return `${hours}h ago`
  const days = Math.floor(hours / 24)
  if (days < 7) return `${days}d ago`
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

// Real-time updates
let unsubscribe: (() => void) | null = null

onMounted(() => {
  loadActivities()
  unsubscribe = on('activity:new', () => {
    loadActivities()
  })
})

onUnmounted(() => {
  unsubscribe?.()
})
</script>
