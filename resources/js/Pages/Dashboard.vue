<template>
  <div class="h-full overflow-y-auto">
    <div class="max-w-5xl mx-auto p-4 md:p-6">
      <!-- Simple Header -->
      <header class="mb-6">
        <h1 class="text-xl font-semibold text-neutral-900 dark:text-white">Dashboard</h1>
        <p class="text-sm text-neutral-500 dark:text-neutral-400 mt-1">
          Welcome back. Here's what's happening.
        </p>
      </header>

      <!-- Pending Approvals (if any) -->
      <DashboardPendingApprovals
        v-if="pendingApprovals.length > 0"
        :approvals="pendingApprovals"
        @approve="handleApprove"
        @reject="handleReject"
        class="mb-6"
      />

      <!-- Stats Row -->
      <DashboardStatsOverview :stats="statsData" class="mb-6" />

      <!-- Main Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Activity Feed -->
        <div class="lg:col-span-2">
          <DashboardActivityFeed :activities="activitiesData" />
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
          <DashboardQuickActions @action-click="handleQuickAction" />
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
import { useWorkspace } from '@/composables/useWorkspace'
import type { ApprovalRequest, Stats, Activity, User } from '@/types'
import DashboardPendingApprovals from '@/Components/dashboard/PendingApprovals.vue'
import DashboardStatsOverview from '@/Components/dashboard/StatsOverview.vue'
import DashboardActivityFeed from '@/Components/dashboard/ActivityFeed.vue'
import DashboardQuickActions from '@/Components/dashboard/QuickActions.vue'
import DashboardWorkingAgents from '@/Components/dashboard/WorkingAgents.vue'
import AgentsSpawnAgentModal from '@/Components/agents/SpawnAgentModal.vue'
import { useApi } from '@/composables/useApi'

const { workspacePath } = useWorkspace()
const { fetchStats, fetchActivities, fetchAgents, fetchApprovals, respondToApproval } = useApi()

const { data: stats, refresh: refreshStats } = fetchStats()
const { data: activities, refresh: refreshActivities } = fetchActivities(20)
const { data: agents, refresh: refreshAgents } = fetchAgents()
const { data: approvals, refresh: refreshApprovals } = fetchApprovals('pending')

const showSpawnModal = ref(false)

const handleQuickAction = (actionId: string) => {
  switch (actionId) {
    case 'spawn-agent':
      showSpawnModal.value = true
      break
    case 'new-channel':
      router.visit(workspacePath('/chat'))
      break
    case 'create-task':
      router.visit(workspacePath('/tasks'))
      break
    case 'new-document':
      router.visit(workspacePath('/docs'))
      break
  }
}

const handleAgentSpawned = async () => {
  await Promise.all([refreshAgents(), refreshActivities(), refreshStats()])
}

const statsData = computed<Stats>(() => stats.value ?? {
  agentsOnline: 0,
  totalAgents: 0,
  tasksCompleted: 0,
  tasksToday: 0,
  messagesTotal: 0,
  messagesToday: 0,
})

const activitiesData = computed<Activity[]>(() => activities.value ?? [])

const workingAgents = computed<User[]>(() =>
  (agents.value ?? []).filter(a => a.status === 'working')
)

const pendingApprovals = computed<ApprovalRequest[]>(() => approvals.value ?? [])

const handleApprove = async (approval: ApprovalRequest) => {
  await respondToApproval(approval.id, 'approved')
  await Promise.all([refreshApprovals(), refreshStats(), refreshActivities()])
}

const handleReject = async (approval: ApprovalRequest) => {
  await respondToApproval(approval.id, 'rejected')
  await Promise.all([refreshApprovals(), refreshActivities()])
}
</script>
