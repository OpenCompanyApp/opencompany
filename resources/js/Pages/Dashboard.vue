<template>
  <div class="h-full overflow-y-auto">
    <div class="max-w-7xl mx-auto p-8">
      <!-- Header -->
      <header class="mb-8">
        <h1 class="text-3xl font-bold mb-2 text-gradient">Dashboard</h1>
        <p class="text-gray-500">
          Welcome back! Here's what's happening at Bloom Agency.
        </p>
      </header>

      <!-- Pending Approvals Banner -->
      <DashboardPendingApprovals
        :approvals="pendingApprovals"
        @approve="handleApprove"
        @reject="handleReject"
      />

      <!-- Stats Grid -->
      <DashboardStatsOverview :stats="statsData" class="mb-8" />

      <!-- Main Content Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Activity Feed -->
        <div class="lg:col-span-2">
          <DashboardActivityFeed :activities="activitiesData" />
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
          <!-- Quick Actions -->
          <DashboardQuickActions
            :actions="quickActions"
            @action-click="handleQuickAction"
          />

          <!-- Working Agents -->
          <DashboardWorkingAgents :agents="workingAgents" />
        </div>
      </div>
    </div>

    <!-- Spawn Agent Modal -->
    <AgentsSpawnAgentModal
      v-model:open="showSpawnModal"
      @spawn="handleAgentSpawned"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import type { ApprovalRequest, Stats, Activity, User } from '@/types'
import type { QuickAction } from '@/Components/dashboard/QuickActions.vue'
import DashboardPendingApprovals from '@/Components/dashboard/PendingApprovals.vue'
import DashboardStatsOverview from '@/Components/dashboard/StatsOverview.vue'
import DashboardActivityFeed from '@/Components/dashboard/ActivityFeed.vue'
import DashboardQuickActions from '@/Components/dashboard/QuickActions.vue'
import DashboardWorkingAgents from '@/Components/dashboard/WorkingAgents.vue'
import AgentsSpawnAgentModal from '@/Components/agents/SpawnAgentModal.vue'
import { useApi } from '@/composables/useApi'

const { fetchStats, fetchActivities, fetchAgents, fetchApprovals, respondToApproval } = useApi()

// Fetch data from API (useFetch returns synchronously with reactive refs)
const { data: stats, refresh: refreshStats } = fetchStats()
const { data: activities, refresh: refreshActivities } = fetchActivities(20)
const { data: agents, refresh: refreshAgents } = fetchAgents()
const { data: approvals, refresh: refreshApprovals } = fetchApprovals('pending')

// Modal state
const showSpawnModal = ref(false)

// Quick Actions with handlers
const quickActions = computed<QuickAction[]>(() => [
  {
    id: 'new-channel',
    label: 'New Channel',
    description: 'Create a collaboration space',
    icon: 'ph:chats-circle',
    bgClass: 'bg-blue-500/20',
    iconClass: 'text-blue-400',
    shortcut: '⌘N',
  },
  {
    id: 'spawn-agent',
    label: 'Spawn Agent',
    description: 'Deploy a new AI worker',
    icon: 'ph:robot-fill',
    bgClass: 'bg-cyan-500/20',
    iconClass: 'text-cyan-400',
    shortcut: '⌘A',
  },
  {
    id: 'create-task',
    label: 'Create Task',
    description: 'Assign work to your team',
    icon: 'ph:check-square-fill',
    bgClass: 'bg-green-500/20',
    iconClass: 'text-green-400',
    shortcut: '⌘T',
  },
  {
    id: 'new-document',
    label: 'New Document',
    description: 'Write a new document',
    icon: 'ph:file-plus-fill',
    bgClass: 'bg-amber-500/20',
    iconClass: 'text-amber-400',
    shortcut: '⌘D',
  },
])

const handleQuickAction = (action: QuickAction) => {
  switch (action.id) {
    case 'spawn-agent':
      showSpawnModal.value = true
      break
    case 'new-channel':
      router.visit('/chat')
      break
    case 'create-task':
      router.visit('/tasks')
      break
    case 'new-document':
      router.visit('/docs')
      break
  }
}

const handleAgentSpawned = async () => {
  await Promise.all([refreshAgents(), refreshActivities(), refreshStats()])
}

// Computed values
const statsData = computed<Stats>(() => stats.value ?? {
  agentsOnline: 0,
  totalAgents: 0,
  tasksCompleted: 0,
  tasksToday: 0,
  messagesTotal: 0,
  messagesToday: 0,
  creditsUsed: 0,
  creditsRemaining: 0,
})

const activitiesData = computed<Activity[]>(() => activities.value ?? [])

const workingAgents = computed<User[]>(() =>
  (agents.value ?? []).filter(a => a.status === 'working')
)

const pendingApprovals = computed<ApprovalRequest[]>(() => approvals.value ?? [])

const handleApprove = async (approval: ApprovalRequest) => {
  await respondToApproval(approval.id, 'approved', 'h1')
  await Promise.all([refreshApprovals(), refreshStats(), refreshActivities()])
}

const handleReject = async (approval: ApprovalRequest) => {
  await respondToApproval(approval.id, 'rejected', 'h1')
  await Promise.all([refreshApprovals(), refreshActivities()])
}
</script>
