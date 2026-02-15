<template>
  <div class="rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 overflow-hidden">
    <!-- Header -->
    <div class="px-4 py-3 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between">
      <h3 class="text-sm font-medium text-neutral-900 dark:text-white">Recent Activity</h3>
      <Link :href="workspacePath('/activity')" class="text-xs text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200">
        View all
      </Link>
    </div>

    <!-- Activity List -->
    <div v-if="activities.length > 0" class="divide-y divide-neutral-100 dark:divide-neutral-800">
      <div
        v-for="activity in activities.slice(0, 8)"
        :key="activity.id"
        class="px-4 py-3 flex items-start gap-3 hover:bg-neutral-50 dark:hover:bg-neutral-800/50 transition-colors duration-150"
      >
        <AgentAvatar :user="activity.actor" size="sm" />
        <div class="flex-1 min-w-0">
          <p class="text-sm text-neutral-900 dark:text-white">
            <span class="font-medium">{{ activity.actor.name }}</span>
            <span class="text-neutral-500 dark:text-neutral-400">{{ getActionText(activity) }}</span>
            <span v-if="activity.target" class="font-medium"> {{ activity.target.name }}</span>
          </p>
          <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
            {{ formatRelativeTime(activity.timestamp) }}
          </p>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="px-4 py-8 text-center">
      <Icon name="ph:check-circle" class="w-8 h-8 text-neutral-300 dark:text-neutral-600 mx-auto mb-2" />
      <p class="text-sm text-neutral-500 dark:text-neutral-400">No recent activity</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import type { Activity, ActivityType } from '@/types'
import AgentAvatar from '@/Components/shared/AgentAvatar.vue'
import Icon from '@/Components/shared/Icon.vue'
import { useWorkspace } from '@/composables/useWorkspace'

const { workspacePath } = useWorkspace()

defineProps<{
  activities: Activity[]
}>()

const getActionText = (activity: Activity): string => {
  const actionMap: Record<ActivityType, string> = {
    message: ' sent a message in',
    task_completed: ' completed',
    task_started: ' started working on',
    agent_spawned: ' spawned',
    approval_needed: ' requested approval for',
    approval_granted: ' approved',
    error: ' encountered an error in',
  }
  return actionMap[activity.type] || ' performed action'
}

const formatRelativeTime = (date: Date): string => {
  const now = new Date()
  const diff = now.getTime() - new Date(date).getTime()
  const seconds = Math.floor(diff / 1000)
  const minutes = Math.floor(seconds / 60)
  const hours = Math.floor(minutes / 60)
  const days = Math.floor(hours / 24)

  if (seconds < 60) return 'Just now'
  if (minutes < 60) return `${minutes}m ago`
  if (hours < 24) return `${hours}h ago`
  if (days < 7) return `${days}d ago`
  return new Date(date).toLocaleDateString()
}
</script>
