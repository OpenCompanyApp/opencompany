<template>
  <div class="card-gradient bg-olympus-surface rounded-xl overflow-hidden">
    <div class="p-4 border-b border-olympus-border flex items-center justify-between">
      <h2 class="font-semibold text-sm">Recent Activity</h2>
      <button class="text-sm text-olympus-primary hover:text-olympus-primary-hover transition-colors duration-150 outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50 rounded px-2 py-1 -mr-2">
        View all
      </button>
    </div>

    <div class="divide-y divide-olympus-border">
      <div
        v-for="activity in activities"
        :key="activity.id"
        class="px-4 py-3 flex items-start gap-3 hover:bg-olympus-elevated/50 transition-colors duration-150 cursor-pointer"
      >
        <SharedAgentAvatar :user="activity.actor" size="sm" />

        <div class="flex-1 min-w-0">
          <p class="text-sm">{{ activity.description }}</p>
          <p class="text-xs text-olympus-text-subtle mt-0.5">
            {{ formatTime(activity.timestamp) }}
          </p>
        </div>

        <div
          :class="[
            'w-8 h-8 rounded-lg flex items-center justify-center shrink-0',
            activityBgClasses[activity.type]
          ]"
        >
          <Icon
            :name="activityIcons[activity.type]"
            :class="['w-4 h-4', activityIconClasses[activity.type]]"
          />
        </div>
      </div>
    </div>

    <div v-if="activities.length === 0" class="p-8 text-center">
      <div class="w-12 h-12 rounded-xl bg-olympus-elevated flex items-center justify-center mx-auto mb-3">
        <Icon name="ph:check-circle" class="w-6 h-6 text-olympus-text-subtle" />
      </div>
      <p class="font-medium text-sm text-olympus-text">All caught up!</p>
      <p class="text-xs text-olympus-text-muted mt-1">Your recent activity will appear here</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { Activity, ActivityType } from '~/types'

defineProps<{
  activities: Activity[]
}>()

const activityIcons: Record<ActivityType, string> = {
  message: 'ph:chat-circle-fill',
  task_completed: 'ph:check-circle-fill',
  task_started: 'ph:play-circle-fill',
  agent_spawned: 'ph:robot-fill',
  approval_needed: 'ph:warning-circle-fill',
  approval_granted: 'ph:check-circle-fill',
  error: 'ph:x-circle-fill',
}

const activityBgClasses: Record<ActivityType, string> = {
  message: 'bg-blue-500/20',
  task_completed: 'bg-green-500/20',
  task_started: 'bg-olympus-primary/20',
  agent_spawned: 'bg-cyan-500/20',
  approval_needed: 'bg-amber-500/20',
  approval_granted: 'bg-green-500/20',
  error: 'bg-red-500/20',
}

const activityIconClasses: Record<ActivityType, string> = {
  message: 'text-blue-400',
  task_completed: 'text-green-400',
  task_started: 'text-olympus-primary',
  agent_spawned: 'text-cyan-400',
  approval_needed: 'text-amber-400',
  approval_granted: 'text-green-400',
  error: 'text-red-400',
}

const formatTime = (date: Date) => {
  const now = new Date()
  const diff = now.getTime() - new Date(date).getTime()
  const minutes = Math.floor(diff / 60000)
  const hours = Math.floor(diff / 3600000)

  if (minutes < 1) return 'Just now'
  if (minutes < 60) return `${minutes}m ago`
  if (hours < 24) return `${hours}h ago`
  return new Date(date).toLocaleDateString()
}
</script>
