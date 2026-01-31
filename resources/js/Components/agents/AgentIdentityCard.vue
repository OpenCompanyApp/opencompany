<template>
  <div class="bg-neutral-50 dark:bg-neutral-800 rounded-xl p-5 border border-neutral-200 dark:border-neutral-700">
    <div class="flex items-start gap-4">
      <!-- Avatar -->
      <div class="relative shrink-0">
        <div
          :class="[
            'w-14 h-14 rounded-xl flex items-center justify-center text-2xl',
            agentBgColor
          ]"
        >
          {{ agent.identity?.emoji || '🤖' }}
        </div>
        <div
          :class="[
            'absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 rounded-full border-2 border-neutral-50 dark:border-neutral-800',
            statusColor
          ]"
        />
      </div>

      <!-- Info -->
      <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2">
          <h3 class="text-lg font-semibold text-neutral-900 dark:text-white truncate">
            {{ agent.identity?.name || agent.name }}
          </h3>
          <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-neutral-200 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-300 capitalize">
            {{ agent.identity?.type || agent.agentType }}
          </span>
        </div>
        <p class="text-sm text-neutral-500 dark:text-neutral-400 mt-0.5 truncate">
          {{ statusLabel }}
        </p>
      </div>
    </div>

    <!-- Stats Row -->
    <div v-if="showStats && agent.stats" class="flex items-center gap-6 mt-4 pt-4 border-t border-neutral-200 dark:border-neutral-700">
      <div>
        <p class="text-xs text-neutral-500 dark:text-neutral-400">Efficiency</p>
        <p class="text-sm font-semibold text-neutral-900 dark:text-white">{{ agent.stats.efficiency }}%</p>
      </div>
      <div>
        <p class="text-xs text-neutral-500 dark:text-neutral-400">Tasks</p>
        <p class="text-sm font-semibold text-neutral-900 dark:text-white">{{ agent.stats.tasksCompleted }}</p>
      </div>
      <div>
        <p class="text-xs text-neutral-500 dark:text-neutral-400">Credits</p>
        <p class="text-sm font-semibold text-neutral-900 dark:text-white">${{ agent.stats.creditsUsed }}</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { Agent, AgentType } from '@/types'

const props = withDefaults(defineProps<{
  agent: Agent
  showStats?: boolean
}>(), {
  showStats: true
})

const agentColors: Record<AgentType, string> = {
  manager: 'bg-purple-100 dark:bg-purple-900/30',
  writer: 'bg-green-100 dark:bg-green-900/30',
  analyst: 'bg-cyan-100 dark:bg-cyan-900/30',
  creative: 'bg-pink-100 dark:bg-pink-900/30',
  researcher: 'bg-amber-100 dark:bg-amber-900/30',
  coder: 'bg-indigo-100 dark:bg-indigo-900/30',
  coordinator: 'bg-teal-100 dark:bg-teal-900/30',
}

const agentBgColor = computed(() => {
  const type = props.agent.identity?.type || props.agent.agentType || 'coder'
  return agentColors[type] || 'bg-neutral-100 dark:bg-neutral-700'
})

const statusColor = computed(() => {
  switch (props.agent.status) {
    case 'working':
    case 'online':
      return 'bg-green-500'
    case 'busy':
      return 'bg-amber-500'
    case 'paused':
      return 'bg-yellow-500'
    case 'offline':
      return 'bg-neutral-400'
    default:
      return 'bg-neutral-400'
  }
})

const statusLabel = computed(() => {
  if (props.agent.status === 'working' && props.agent.currentTask) {
    return `Working on ${props.agent.currentTask}`
  }
  switch (props.agent.status) {
    case 'working':
      return 'Working'
    case 'online':
    case 'idle':
      return 'Available'
    case 'busy':
      return 'Busy'
    case 'paused':
      return 'Paused'
    case 'offline':
      return 'Offline'
    default:
      return 'Unknown'
  }
})
</script>
