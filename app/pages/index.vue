<template>
  <div class="h-full overflow-y-auto">
    <div class="max-w-7xl mx-auto p-8">
      <!-- Header -->
      <header class="mb-8">
        <h1 class="text-3xl font-bold mb-2 text-gradient">Dashboard</h1>
        <p class="text-olympus-text-muted">
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
      <DashboardStatsOverview :stats="stats" class="mb-8" />

      <!-- Main Content Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Activity Feed -->
        <div class="lg:col-span-2">
          <DashboardActivityFeed :activities="activities" />
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
          <!-- Quick Actions -->
          <DashboardQuickActions />

          <!-- Working Agents -->
          <DashboardWorkingAgents :agents="workingAgents" />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { ApprovalRequest } from '~/types'

const { stats, activities, agents, pendingApprovals } = useMockData()

const workingAgents = computed(() =>
  agents.filter(a => a.status === 'working')
)

const handleApprove = (approval: ApprovalRequest) => {
  console.log('Approved:', approval.id, approval.title)
}

const handleReject = (approval: ApprovalRequest) => {
  console.log('Rejected:', approval.id, approval.title)
}
</script>
