<template>
  <div
    :class="[
      'bg-olympus-surface rounded-2xl p-4 border border-olympus-border transition-all duration-200 cursor-grab active:cursor-grabbing group',
      'hover:border-olympus-primary/50 hover:shadow-lg hover:shadow-olympus-primary/5',
      task.status === 'done' && 'opacity-75'
    ]"
  >
    <!-- Header -->
    <div class="flex items-start justify-between gap-2 mb-3">
      <span
        :class="[
          'px-2 py-0.5 text-xs font-semibold rounded-lg',
          priorityClasses[task.priority]
        ]"
      >
        {{ task.priority }}
      </span>
      <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
        <button class="p-1 rounded hover:bg-olympus-border transition-colors">
          <Icon name="ph:pencil-simple" class="w-3.5 h-3.5 text-olympus-text-muted" />
        </button>
        <button class="p-1 rounded hover:bg-olympus-border transition-colors">
          <Icon name="ph:dots-three" class="w-3.5 h-3.5 text-olympus-text-muted" />
        </button>
      </div>
    </div>

    <!-- Title -->
    <h4
      :class="[
        'font-medium mb-2 leading-snug',
        task.status === 'done' && 'line-through text-olympus-text-muted'
      ]"
    >
      {{ task.title }}
    </h4>

    <!-- Description -->
    <p class="text-sm text-olympus-text-muted line-clamp-2 mb-4">
      {{ task.description }}
    </p>

    <!-- Footer -->
    <div class="flex items-center justify-between">
      <!-- Assignee + Collaborators -->
      <div class="flex items-center">
        <div class="flex -space-x-2">
          <SharedAgentAvatar :user="task.assignee" size="sm" />
          <SharedAgentAvatar
            v-for="collab in (task.collaborators || []).slice(0, 2)"
            :key="collab.id"
            :user="collab"
            size="sm"
          />
        </div>
        <span
          v-if="task.collaborators && task.collaborators.length > 2"
          class="ml-1 text-xs text-olympus-text-muted"
        >
          +{{ task.collaborators.length - 2 }}
        </span>
      </div>

      <!-- Cost -->
      <SharedCostBadge
        v-if="task.cost || task.estimatedCost"
        :cost="task.cost || task.estimatedCost!"
        :variant="task.cost ? 'actual' : 'estimated'"
        size="xs"
      />
    </div>

    <!-- Completion indicator -->
    <div
      v-if="task.status === 'done' && task.completedAt"
      class="mt-3 pt-3 border-t border-olympus-border flex items-center gap-2 text-xs text-olympus-text-muted"
    >
      <Icon name="ph:check-circle-fill" class="w-4 h-4 text-green-400" />
      <span>Completed {{ formatDate(task.completedAt) }}</span>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { Task, Priority } from '~/types'

defineProps<{
  task: Task
}>()

const priorityClasses: Record<Priority, string> = {
  low: 'bg-gray-500/20 text-gray-400',
  medium: 'bg-blue-500/20 text-blue-400',
  high: 'bg-amber-500/20 text-amber-400',
  urgent: 'bg-red-500/20 text-red-400',
}

const formatDate = (date: Date) => {
  const d = new Date(date)
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}
</script>
