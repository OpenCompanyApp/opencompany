<template>
  <div class="h-full flex flex-col">
    <!-- Header -->
    <header class="shrink-0 px-6 py-4 border-b border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900">
      <div class="flex items-center justify-between">
        <div>
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
          </div>
        </div>
        <button
          class="px-3 py-2 text-sm text-neutral-500 dark:text-neutral-300 hover:text-neutral-700 dark:hover:text-neutral-200 hover:bg-white dark:hover:bg-neutral-800 rounded-lg transition-colors"
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
        <div class="p-4 bg-white dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700">
          <div class="flex items-center gap-3">
            <div class="p-2 rounded-lg bg-neutral-100 dark:bg-neutral-700">
              <Icon name="ph:robot" class="w-5 h-5 text-neutral-900 dark:text-white" />
            </div>
            <div>
              <p class="text-2xl font-bold text-neutral-900 dark:text-white">{{ summary.activeAgents }}/{{ summary.totalAgents }}</p>
              <p class="text-sm text-neutral-500 dark:text-neutral-300">Active Agents</p>
            </div>
          </div>
        </div>

        <div class="p-4 bg-white dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700">
          <div class="flex items-center gap-3">
            <div class="p-2 rounded-lg bg-blue-500/20">
              <Icon name="ph:list-checks" class="w-5 h-5 text-blue-400" />
            </div>
            <div>
              <p class="text-2xl font-bold text-neutral-900 dark:text-white">{{ summary.totalCurrentTasks }}</p>
              <p class="text-sm text-neutral-500 dark:text-neutral-300">Tasks In Progress</p>
            </div>
          </div>
        </div>

        <div class="p-4 bg-white dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700">
          <div class="flex items-center gap-3">
            <div class="p-2 rounded-lg bg-green-500/20">
              <Icon name="ph:check-circle" class="w-5 h-5 text-green-400" />
            </div>
            <div>
              <p class="text-2xl font-bold text-neutral-900 dark:text-white">{{ summary.totalCompletedWeek }}</p>
              <p class="text-sm text-neutral-500 dark:text-neutral-300">Completed This Week</p>
            </div>
          </div>
        </div>

        <div class="p-4 bg-white dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700">
          <div class="flex items-center gap-3">
            <div class="p-2 rounded-lg bg-purple-500/20">
              <Icon name="ph:chart-line-up" class="w-5 h-5 text-purple-400" />
            </div>
            <div>
              <p class="text-2xl font-bold text-neutral-900 dark:text-white">{{ summary.avgEfficiency }}%</p>
              <p class="text-sm text-neutral-500 dark:text-neutral-300">Avg Efficiency</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Agent Cards -->
      <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4">
        <div
          v-for="item in sortedAgents"
          :key="item.agent.id"
          class="p-5 bg-white dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700 hover:border-neutral-300 dark:hover:border-neutral-600 transition-colors"
        >
          <!-- Agent Header -->
          <div class="flex items-start gap-3 mb-4">
            <SharedAgentAvatar :user="item.agent" size="md" show-status />
            <div class="flex-1 min-w-0">
              <Link
                :href="workspacePath(`/agent/${item.agent.id}`)"
                class="font-medium text-neutral-900 dark:text-white hover:text-neutral-900 dark:hover:text-white transition-colors"
              >
                {{ item.agent.name }}
              </Link>
              <p class="text-sm text-neutral-500 dark:text-neutral-300 capitalize">{{ item.agent.agentType?.replace('-', ' ') }}</p>
              <p v-if="item.agent.currentTask" class="text-xs text-neutral-400 dark:text-neutral-400 mt-1 truncate">
                {{ item.agent.currentTask }}
              </p>
            </div>
            <SharedStatusBadge :status="item.agent.status" size="sm" />
          </div>

          <!-- Workload Bar -->
          <div class="mb-4">
            <div class="flex items-center justify-between text-xs text-neutral-500 dark:text-neutral-300 mb-1.5">
              <span>Workload</span>
              <span :class="getWorkloadColor(item.metrics.workloadScore)">
                {{ item.metrics.workloadScore }}%
              </span>
            </div>
            <div class="h-2 bg-neutral-100 dark:bg-neutral-700 rounded-full overflow-hidden">
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
            <div class="flex items-center justify-between text-xs text-neutral-500 dark:text-neutral-300 mb-1.5">
              <span>Efficiency</span>
              <span class="text-green-400">{{ item.metrics.efficiencyScore }}%</span>
            </div>
            <div class="h-2 bg-neutral-100 dark:bg-neutral-700 rounded-full overflow-hidden">
              <div
                class="h-full rounded-full bg-green-500 transition-all duration-500"
                :style="{ width: `${item.metrics.efficiencyScore}%` }"
              />
            </div>
          </div>

          <!-- Metrics Grid -->
          <div class="grid grid-cols-3 gap-3 pt-3 border-t border-neutral-200 dark:border-neutral-700">
            <div class="text-center">
              <p class="text-lg font-semibold text-neutral-900 dark:text-white">{{ item.metrics.currentTasks }}</p>
              <p class="text-[10px] text-neutral-500 dark:text-neutral-300 uppercase">In Progress</p>
            </div>
            <div class="text-center">
              <p class="text-lg font-semibold text-neutral-900 dark:text-white">{{ item.metrics.pendingTasks }}</p>
              <p class="text-[10px] text-neutral-500 dark:text-neutral-300 uppercase">Pending</p>
            </div>
            <div class="text-center">
              <p class="text-lg font-semibold text-neutral-900 dark:text-white">{{ item.metrics.completedTasksWeek }}</p>
              <p class="text-[10px] text-neutral-500 dark:text-neutral-300 uppercase">This Week</p>
            </div>
          </div>

          <!-- Additional Metrics -->
          <div class="flex items-center justify-between mt-3 pt-3 border-t border-neutral-200 dark:border-neutral-700 text-xs">
            <span class="text-neutral-500 dark:text-neutral-300">
              <Icon name="ph:activity" class="w-3.5 h-3.5 inline mr-1" />
              {{ item.metrics.activitiesThisWeek }} activities
            </span>
            <span v-if="item.metrics.totalCostSpent > 0" class="text-neutral-500 dark:text-neutral-300">
              <Icon name="ph:coins" class="w-3.5 h-3.5 inline mr-1" />
              ${{ item.metrics.totalCostSpent.toFixed(2) }}
            </span>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-if="!workloadData?.agents?.length" class="text-center py-12">
        <Icon name="ph:robot" class="w-12 h-12 mx-auto mb-4 text-neutral-400 dark:text-neutral-400" />
        <p class="text-neutral-500 dark:text-neutral-300">No agents found</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useWorkspace } from '@/composables/useWorkspace'
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

const { workspacePath } = useWorkspace()
const workloadData = ref<WorkloadData | null>(null)

const fetchWorkload = async () => {
  try {
    // Fetch agents from existing API
    const agentsResponse = await fetch('/api/users/agents')
    const agents = await agentsResponse.json()

    // Fetch tasks to compute metrics
    const tasksResponse = await fetch('/api/tasks')
    const tasks = await tasksResponse.json()

    // Build workload data from agents and tasks
    const agentWorkloads: AgentWorkload[] = agents.map((agent: any) => {
      // Get tasks assigned to this agent
      const agentTasks = tasks.filter((t: any) => t.agentId === agent.id || t.agent?.id === agent.id)
      const currentTasks = agentTasks.filter((t: any) => t.status === 'active').length
      const pendingTasks = agentTasks.filter((t: any) => t.status === 'pending').length
      const completedTasks = agentTasks.filter((t: any) => t.status === 'completed').length

      // Calculate workload score (based on current + pending tasks)
      const workloadScore = Math.min(100, (currentTasks * 30) + (pendingTasks * 10))

      // Calculate efficiency score (based on completed vs total)
      const totalAssigned = currentTasks + pendingTasks + completedTasks
      const efficiencyScore = totalAssigned > 0 ? Math.round((completedTasks / totalAssigned) * 100) : 0

      return {
        agent: {
          id: agent.id,
          name: agent.name,
          avatar: agent.avatar || null,
          agentType: agent.agentType || 'agent',
          status: agent.status || 'idle',
          currentTask: agent.currentTask || null,
        },
        metrics: {
          currentTasks,
          completedTasksWeek: completedTasks,
          pendingTasks,
          totalCostSpent: 0,
          stepsCompletedToday: 0,
          activitiesThisWeek: currentTasks + completedTasks,
          workloadScore,
          efficiencyScore,
        },
      }
    })

    // Calculate summary
    const activeAgents = agentWorkloads.filter(a => a.agent.status === 'working').length
    const totalCurrentTasks = agentWorkloads.reduce((sum, a) => sum + a.metrics.currentTasks, 0)
    const totalCompletedWeek = agentWorkloads.reduce((sum, a) => sum + a.metrics.completedTasksWeek, 0)
    const avgWorkload = agentWorkloads.length > 0
      ? Math.round(agentWorkloads.reduce((sum, a) => sum + a.metrics.workloadScore, 0) / agentWorkloads.length)
      : 0
    const avgEfficiency = agentWorkloads.length > 0
      ? Math.round(agentWorkloads.reduce((sum, a) => sum + a.metrics.efficiencyScore, 0) / agentWorkloads.length)
      : 0

    workloadData.value = {
      agents: agentWorkloads,
      summary: {
        totalAgents: agents.length,
        activeAgents,
        totalCurrentTasks,
        totalCompletedWeek,
        avgWorkload,
        avgEfficiency,
      },
    }
  } catch (error) {
    console.error('Failed to fetch workload:', error)
    workloadData.value = null
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
