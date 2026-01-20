<template>
  <div class="card-gradient bg-olympus-surface rounded-2xl p-4">
    <SharedCardHeader
      title="Working Now"
      :subtitle="loading ? undefined : `${agents.length} active`"
      icon="ph:robot-fill"
      icon-color="text-olympus-primary"
      icon-bg="bg-olympus-primary/20"
      gradient
    />

    <!-- Loading State -->
    <div v-if="loading" class="space-y-3 mt-4">
      <div
        v-for="i in 3"
        :key="i"
        class="p-3 rounded-xl bg-olympus-bg border border-olympus-border-subtle"
      >
        <div class="flex items-center gap-3 mb-2">
          <SharedSkeleton variant="avatar" />
          <div class="flex-1 space-y-2">
            <SharedSkeleton custom-class="h-3 w-24" />
            <SharedSkeleton custom-class="h-2 w-16" />
          </div>
        </div>
        <SharedSkeleton custom-class="h-2 w-full mb-2" />
        <div class="flex gap-1">
          <SharedSkeleton custom-class="h-1.5 w-8" rounded="full" />
          <SharedSkeleton custom-class="h-1.5 w-12" rounded="full" />
          <SharedSkeleton custom-class="h-1.5 w-6" rounded="full" />
        </div>
      </div>
    </div>

    <!-- Content -->
    <div v-else-if="agents.length > 0" class="space-y-3 mt-4">
      <div
        v-for="agent in agents"
        :key="agent.id"
        class="p-3 rounded-xl bg-olympus-bg border border-olympus-border-subtle hover:border-olympus-border transition-colors duration-200"
      >
        <div class="flex items-center gap-3 mb-2">
          <SharedAgentAvatar :user="agent" size="sm" />
          <div class="flex-1 min-w-0">
            <p class="font-medium text-sm">{{ agent.name }}</p>
            <p class="text-xs text-olympus-text-muted capitalize">{{ agent.agentType }}</p>
          </div>
          <SharedStatusBadge :status="agent.status!" size="xs" />
        </div>

        <p class="text-xs text-olympus-text-muted mb-2 line-clamp-1">
          {{ agent.currentTask }}
        </p>

        <SharedActivityLog v-if="agent.activityLog?.length" :steps="agent.activityLog" />
      </div>
    </div>

    <!-- Empty State -->
    <SharedEmptyState
      v-else
      icon="ph:moon-stars"
      title="All agents idle"
      description="Agents will appear here when they're working"
      size="sm"
      class="mt-4"
    />
  </div>
</template>

<script setup lang="ts">
import type { User } from '~/types'

withDefaults(defineProps<{
  agents: User[]
  loading?: boolean
}>(), {
  loading: false,
})
</script>
