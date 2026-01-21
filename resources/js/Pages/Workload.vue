<template>
  <div class="h-full flex flex-col">
    <!-- Header -->
    <header class="shrink-0 px-6 py-4 border-b border-gray-200 bg-white">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-xl font-semibold text-gray-900">Agent Workload</h1>
          <p class="text-sm text-gray-500 mt-1">
            Monitor agent performance and task distribution
          </p>
        </div>
        <button
          class="px-3 py-2 text-sm text-gray-500 hover:text-gray-700 hover:bg-white rounded-lg transition-colors"
          @click="refreshWorkload"
        >
          <Icon name="ph:arrows-clockwise" class="w-4 h-4" />
        </button>
      </div>
    </header>

    <!-- Content -->
    <div class="flex-1 overflow-y-auto p-6">
      <!-- Summary Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="p-4 bg-white rounded-xl border border-gray-200">
          <div class="flex items-center gap-3">
            <div class="p-2 rounded-lg bg-gray-100">
              <Icon name="ph:robot" class="w-5 h-5 text-gray-900" />
            </div>
            <div>
              <p class="text-2xl font-bold text-gray-900">{{ summary.activeAgents }}/{{ summary.totalAgents }}</p>
              <p class="text-sm text-gray-500">Active Agents</p>
            </div>
          </div>
        </div>

        <div class="p-4 bg-white rounded-xl border border-gray-200">
          <div class="flex items-center gap-3">
            <div class="p-2 rounded-lg bg-blue-500/20">
              <Icon name="ph:list-checks" class="w-5 h-5 text-blue-400" />
            </div>
            <div>
              <p class="text-2xl font-bold text-gray-900">{{ summary.totalCurrentTasks }}</p>
              <p class="text-sm text-gray-500">Tasks In Progress</p>
            </div>
          </div>
        </div>

        <div class="p-4 bg-white rounded-xl border border-gray-200">
          <div class="flex items-center gap-3">
            <div class="p-2 rounded-lg bg-green-500/20">
              <Icon name="ph:check-circle" class="w-5 h-5 text-green-400" />
            </div>
            <div>
              <p class="text-2xl font-bold text-gray-900">{{ summary.totalCompletedWeek }}</p>
              <p class="text-sm text-gray-500">Completed This Week</p>
            </div>
          </div>
        </div>

        <div class="p-4 bg-white rounded-xl border border-gray-200">
          <div class="flex items-center gap-3">
            <div class="p-2 rounded-lg bg-purple-500/20">
              <Icon name="ph:chart-line-up" class="w-5 h-5 text-purple-400" />
            </div>
            <div>
              <p class="text-2xl font-bold text-gray-900">{{ summary.avgEfficiency }}%</p>
              <p class="text-sm text-gray-500">Avg Efficiency</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Agent Cards -->
      <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4">
        <div
          v-for="item in sortedAgents"
          :key="item.agent.id"
          class="p-5 bg-white rounded-xl border border-gray-200 hover:border-gray-300 transition-colors"
        >
          <!-- Agent Header -->
          <div class="flex items-start gap-3 mb-4">
            <SharedAgentAvatar :user="item.agent" size="md" show-status />
            <div class="flex-1 min-w-0">
              <Link
                :href="`/agent/${item.agent.id}`"
                class="font-medium text-gray-900 hover:text-gray-900 transition-colors"
              >
                {{ item.agent.name }}
              </Link>
              <p class="text-sm text-gray-500 capitalize">{{ item.agent.agentType?.replace('-', ' ') }}</p>
              <p v-if="item.agent.currentTask" class="text-xs text-gray-400 mt-1 truncate">
                {{ item.agent.currentTask }}
              </p>
            </div>
            <SharedStatusBadge :status="item.agent.status" size="sm" />
          </div>

          <!-- Workload Bar -->
          <div class="mb-4">
            <div class="flex items-center justify-between text-xs text-gray-500 mb-1.5">
              <span>Workload</span>
              <span :class="getWorkloadColor(item.metrics.workloadScore)">
                {{ item.metrics.workloadScore }}%
              </span>
            </div>
            <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
              <div
                :class="[
                  'h-full rounded-full transition-all duration-500',
                  getWorkloadBarColor(item.metrics.workloadScore)
                ]"
                :style="{ width: `${item.metrics.workloadScore}%` }"
              />
            </div>
          </div>

          <!-- Efficiency Bar -->
          <div class="mb-4">
            <div class="flex items-center justify-between text-xs text-gray-500 mb-1.5">
              <span>Efficiency</span>
              <span class="text-green-400">{{ item.metrics.efficiencyScore }}%</span>
            </div>
            <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
              <div
                class="h-full rounded-full bg-green-500 transition-all duration-500"
                :style="{ width: `${item.metrics.efficiencyScore}%` }"
              />
            </div>
          </div>

          <!-- Metrics Grid -->
          <div class="grid grid-cols-3 gap-3 pt-3 border-t border-gray-200">
            <div class="text-center">
              <p class="text-lg font-semibold text-gray-900">{{ item.metrics.currentTasks }}</p>
              <p class="text-[10px] text-gray-500 uppercase">In Progress</p>
            </div>
            <div class="text-center">
              <p class="text-lg font-semibold text-gray-900">{{ item.metrics.pendingTasks }}</p>
              <p class="text-[10px] text-gray-500 uppercase">Pending</p>
            </div>
            <div class="text-center">
              <p class="text-lg font-semibold text-gray-900">{{ item.metrics.completedTasksWeek }}</p>
              <p class="text-[10px] text-gray-500 uppercase">This Week</p>
            </div>
          </div>

          <!-- Additional Metrics -->
          <div class="flex items-center justify-between mt-3 pt-3 border-t border-gray-200 text-xs">
            <span class="text-gray-500">
              <Icon name="ph:activity" class="w-3.5 h-3.5 inline mr-1" />
              {{ item.metrics.activitiesThisWeek }} activities
            </span>
            <span v-if="item.metrics.totalCostSpent > 0" class="text-gray-500">
              <Icon name="ph:coins" class="w-3.5 h-3.5 inline mr-1" />
              ${{ item.metrics.totalCostSpent.toFixed(2) }}
            </span>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-if="!workloadData?.agents?.length" class="text-center py-12">
        <Icon name="ph:robot" class="w-12 h-12 mx-auto mb-4 text-gray-400" />
        <p class="text-gray-500">No agents found</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Link } from '@inertiajs/vue3'
import Icon from '@/Components/shared/Icon.vue'
import SharedAgentAvatar from '@/Components/shared/AgentAvatar.vue'
import SharedStatusBadge from '@/Components/shared/StatusBadge.vue'

interface AgentWorkload {
  agent: {
    id: string
    name: string
    avatar: string | null
    agentType: string | null
    status: string
    currentTask: string | null
  }
  metrics: {
    currentTasks: number
    completedTasksWeek: number
    pendingTasks: number
    totalCostSpent: number
    stepsCompletedToday: number
    activitiesThisWeek: number
    workloadScore: number
    efficiencyScore: number
  }
}

interface WorkloadData {
  agents: AgentWorkload[]
  summary: {
    totalAgents: number
    activeAgents: number
    totalCurrentTasks: number
    totalCompletedWeek: number
    avgWorkload: number
    avgEfficiency: number
  }
}

const workloadData = ref<WorkloadData | null>(null)

const fetchWorkload = async () => {
  try {
    const response = await fetch('/api/agents/workload')
    workloadData.value = await response.json()
  } catch (error) {
    console.error('Failed to fetch workload:', error)
  }
}

const refreshWorkload = () => {
  fetchWorkload()
}

const summary = computed(() => workloadData.value?.summary ?? {
  totalAgents: 0,
  activeAgents: 0,
  totalCurrentTasks: 0,
  totalCompletedWeek: 0,
  avgWorkload: 0,
  avgEfficiency: 0,
})

const sortedAgents = computed(() => {
  if (!workloadData.value?.agents) return []
  return [...workloadData.value.agents].sort((a, b) => {
    // Sort by status (working first), then by workload score
    if (a.agent.status === 'working' && b.agent.status !== 'working') return -1
    if (a.agent.status !== 'working' && b.agent.status === 'working') return 1
    return b.metrics.workloadScore - a.metrics.workloadScore
  })
})

const getWorkloadColor = (score: number): string => {
  if (score >= 80) return 'text-red-400'
  if (score >= 60) return 'text-yellow-400'
  return 'text-green-400'
}

const getWorkloadBarColor = (score: number): string => {
  if (score >= 80) return 'bg-red-500'
  if (score >= 60) return 'bg-yellow-500'
  return 'bg-green-500'
}

// Auto-refresh every 30 seconds
let refreshInterval: ReturnType<typeof setInterval> | null = null

onMounted(() => {
  fetchWorkload()
  refreshInterval = setInterval(() => {
    refreshWorkload()
  }, 30000)
})

onUnmounted(() => {
  if (refreshInterval) {
    clearInterval(refreshInterval)
  }
})
</script>
