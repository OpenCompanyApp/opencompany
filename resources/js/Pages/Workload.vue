<template>
  <div class="h-full flex flex-col">
    <!-- Header -->
    <header class="shrink-0 px-6 py-4 border-b border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-1">
          <Link
            :href="workspacePath('/tasks')"
            class="px-2 py-1 text-sm font-medium text-neutral-500 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-md transition-colors"
          >
            Tasks
          </Link>
          <span class="text-xl font-semibold text-neutral-900 dark:text-white">Workload</span>
          <Link
            :href="workspacePath('/activity')"
            class="px-2 py-1 text-sm font-medium text-neutral-500 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-md transition-colors"
          >
            Activity
          </Link>
          <Link
            :href="workspacePath('/tasks/analytics')"
            class="px-2 py-1 text-sm font-medium text-neutral-500 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-md transition-colors"
          >
            Analytics
          </Link>
        </div>
        <button
          class="p-2 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-lg transition-colors"
          title="Refresh"
          @click="refresh"
        >
          <Icon name="ph:arrows-clockwise" class="w-4 h-4" />
        </button>
      </div>
    </header>

    <!-- Content -->
    <div class="flex-1 overflow-y-auto p-6">
      <!-- Loading -->
      <div v-if="loading" class="flex items-center justify-center py-20">
        <Icon name="ph:circle-notch" class="w-6 h-6 text-neutral-400 animate-spin" />
      </div>

      <template v-else-if="workload">
        <!-- Summary Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
          <div class="p-4 bg-white dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700">
            <div class="flex items-center gap-3">
              <div class="p-2 rounded-lg bg-neutral-100 dark:bg-neutral-700">
                <Icon name="ph:robot" class="w-5 h-5 text-neutral-600 dark:text-neutral-300" />
              </div>
              <div>
                <p class="text-2xl font-bold text-neutral-900 dark:text-white tabular-nums">
                  {{ workload.summary.activeAgents }}<span class="text-base font-normal text-neutral-400">/{{ workload.summary.totalAgents }}</span>
                </p>
                <p class="text-xs text-neutral-500 dark:text-neutral-400">Active Agents</p>
              </div>
            </div>
          </div>

          <div class="p-4 bg-white dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700">
            <div class="flex items-center gap-3">
              <div class="p-2 rounded-lg bg-blue-50 dark:bg-blue-900/30">
                <Icon name="ph:list-checks" class="w-5 h-5 text-blue-600 dark:text-blue-400" />
              </div>
              <div>
                <p class="text-2xl font-bold text-neutral-900 dark:text-white tabular-nums">{{ workload.summary.totalActiveTasks }}</p>
                <p class="text-xs text-neutral-500 dark:text-neutral-400">Active Tasks</p>
              </div>
            </div>
          </div>

          <div class="p-4 bg-white dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700">
            <div class="flex items-center gap-3">
              <div class="p-2 rounded-lg bg-green-50 dark:bg-green-900/30">
                <Icon name="ph:check-circle" class="w-5 h-5 text-green-600 dark:text-green-400" />
              </div>
              <div>
                <p class="text-2xl font-bold text-neutral-900 dark:text-white tabular-nums">{{ workload.summary.completedThisWeek }}</p>
                <p class="text-xs text-neutral-500 dark:text-neutral-400">Completed This Week</p>
              </div>
            </div>
          </div>

          <div class="p-4 bg-white dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700">
            <div class="flex items-center gap-3">
              <div :class="['p-2 rounded-lg', workload.summary.failedThisWeek > 0 ? 'bg-red-50 dark:bg-red-900/30' : 'bg-neutral-100 dark:bg-neutral-700']">
                <Icon name="ph:warning" :class="['w-5 h-5', workload.summary.failedThisWeek > 0 ? 'text-red-600 dark:text-red-400' : 'text-neutral-400']" />
              </div>
              <div>
                <p :class="['text-2xl font-bold tabular-nums', workload.summary.failedThisWeek > 0 ? 'text-red-600 dark:text-red-400' : 'text-neutral-900 dark:text-white']">
                  {{ workload.summary.failedThisWeek }}
                </p>
                <p class="text-xs text-neutral-500 dark:text-neutral-400">Failed This Week</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Agent Cards -->
        <div v-if="workload.agents.length" class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4">
          <div
            v-for="item in workload.agents"
            :key="item.agent.id"
            class="p-5 bg-white dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700 hover:border-neutral-300 dark:hover:border-neutral-600 transition-colors"
          >
            <!-- Agent Header -->
            <div class="flex items-start gap-3 mb-4">
              <SharedAgentAvatar :user="item.agent" size="md" show-status />
              <div class="flex-1 min-w-0">
                <Link
                  :href="workspacePath(`/agent/${item.agent.id}`)"
                  class="font-medium text-neutral-900 dark:text-white hover:underline transition-colors"
                >
                  {{ item.agent.name }}
                </Link>
                <p class="text-sm text-neutral-500 dark:text-neutral-400 capitalize">
                  {{ item.agent.agentType?.replace('-', ' ') || 'Agent' }}
                </p>
                <p v-if="item.currentTaskTitle" class="text-xs text-blue-600 dark:text-blue-400 mt-1 truncate" :title="item.currentTaskTitle">
                  {{ item.currentTaskTitle }}
                </p>
              </div>
              <SharedStatusBadge :status="item.agent.status" size="sm" />
            </div>

            <!-- Metrics Grid -->
            <div class="grid grid-cols-4 gap-2 pt-3 border-t border-neutral-200 dark:border-neutral-700">
              <div class="text-center">
                <p class="text-lg font-semibold text-neutral-900 dark:text-white tabular-nums">{{ item.metrics.currentTasks }}</p>
                <p class="text-[10px] text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">Active</p>
              </div>
              <div class="text-center">
                <p class="text-lg font-semibold text-neutral-900 dark:text-white tabular-nums">{{ item.metrics.pendingTasks }}</p>
                <p class="text-[10px] text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">Pending</p>
              </div>
              <div class="text-center">
                <p class="text-lg font-semibold text-neutral-900 dark:text-white tabular-nums">{{ item.metrics.completedToday }}</p>
                <p class="text-[10px] text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">Today</p>
              </div>
              <div class="text-center">
                <p class="text-lg font-semibold text-neutral-900 dark:text-white tabular-nums">{{ item.metrics.completedThisWeek }}</p>
                <p class="text-[10px] text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">Week</p>
              </div>
            </div>

            <!-- Bottom Row: Duration + Failures -->
            <div class="flex items-center justify-between mt-3 pt-3 border-t border-neutral-100 dark:border-neutral-700/50 text-xs">
              <span v-if="item.metrics.avgDurationSeconds" class="text-neutral-500 dark:text-neutral-400 flex items-center gap-1">
                <Icon name="ph:timer" class="w-3.5 h-3.5" />
                Avg {{ formatDuration(item.metrics.avgDurationSeconds) }}
              </span>
              <span v-else class="text-neutral-400 dark:text-neutral-500 italic">No duration data</span>
              <span
                v-if="item.metrics.failedThisWeek > 0"
                class="text-red-500 dark:text-red-400 flex items-center gap-1"
              >
                <Icon name="ph:warning" class="w-3.5 h-3.5" />
                {{ item.metrics.failedThisWeek }} failed
              </span>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-else class="text-center py-12">
          <Icon name="ph:robot" class="w-12 h-12 mx-auto mb-4 text-neutral-300 dark:text-neutral-600" />
          <p class="text-neutral-500 dark:text-neutral-400">No agents found</p>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useWorkspace } from '@/composables/useWorkspace'
import { useApi } from '@/composables/useApi'
import Icon from '@/Components/shared/Icon.vue'
import SharedAgentAvatar from '@/Components/shared/AgentAvatar.vue'
import SharedStatusBadge from '@/Components/shared/StatusBadge.vue'

const { workspacePath } = useWorkspace()
const { fetchWorkload } = useApi()

const { data: workload, loading, refresh } = fetchWorkload()

function formatDuration(seconds: number): string {
  if (seconds < 60) return `${Math.round(seconds)}s`
  if (seconds < 3600) return `${Math.round(seconds / 60)}m`
  const h = Math.floor(seconds / 3600)
  const m = Math.round((seconds % 3600) / 60)
  return m > 0 ? `${h}h ${m}m` : `${h}h`
}

// Auto-refresh every 30 seconds
let interval: ReturnType<typeof setInterval> | null = null

onMounted(() => {
  interval = setInterval(refresh, 30000)
})

onUnmounted(() => {
  if (interval) clearInterval(interval)
})
</script>
