<template>
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    <SharedStatCard
      label="Agents Online"
      :value="stats.agentsOnline"
      :sub-value="`of ${stats.totalAgents}`"
      icon="ph:robot-fill"
      icon-color="success"
      :trend="12"
    />
    <SharedStatCard
      label="Tasks Completed"
      :value="stats.tasksCompleted"
      :sub-value="`+${stats.tasksToday} today`"
      icon="ph:check-circle-fill"
      icon-color="primary"
      :trend="8"
    />
    <SharedStatCard
      label="Messages"
      :value="stats.messagesTotal"
      :sub-value="`+${stats.messagesToday} today`"
      icon="ph:chat-circle-fill"
      :trend="23"
    />
    <SharedStatCard
      label="Credits Used"
      :value="stats.creditsUsed"
      :sub-value="`$${stats.creditsRemaining.toFixed(0)} remaining`"
      icon="ph:coins-fill"
      icon-color="warning"
      prefix="$"
    />
  </div>
</template>

<script setup lang="ts">
import type { Stats } from '~/types'

defineProps<{
  stats: Stats
}>()
</script>
