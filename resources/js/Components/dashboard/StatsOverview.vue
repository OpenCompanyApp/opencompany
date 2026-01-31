<template>
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    <!-- Agents Online -->
    <div class="p-4 rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-lg bg-neutral-100 dark:bg-neutral-800 flex items-center justify-center">
          <Icon name="ph:robot" class="w-5 h-5 text-neutral-600 dark:text-neutral-300" />
        </div>
        <div>
          <p class="text-2xl font-semibold text-neutral-900 dark:text-white">{{ stats.agentsOnline }}</p>
          <p class="text-xs text-neutral-500 dark:text-neutral-400">Agents online</p>
        </div>
      </div>
    </div>

    <!-- Tasks Completed -->
    <div class="p-4 rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-lg bg-neutral-100 dark:bg-neutral-800 flex items-center justify-center">
          <Icon name="ph:check-circle" class="w-5 h-5 text-neutral-600 dark:text-neutral-300" />
        </div>
        <div>
          <p class="text-2xl font-semibold text-neutral-900 dark:text-white">{{ stats.tasksCompleted }}</p>
          <p class="text-xs text-neutral-500 dark:text-neutral-400">Tasks completed</p>
        </div>
      </div>
    </div>

    <!-- Messages -->
    <div class="p-4 rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-lg bg-neutral-100 dark:bg-neutral-800 flex items-center justify-center">
          <Icon name="ph:chat-circle" class="w-5 h-5 text-neutral-600 dark:text-neutral-300" />
        </div>
        <div>
          <p class="text-2xl font-semibold text-neutral-900 dark:text-white">{{ formatNumber(stats.messagesTotal) }}</p>
          <p class="text-xs text-neutral-500 dark:text-neutral-400">Messages</p>
        </div>
      </div>
    </div>

    <!-- Credits -->
    <div class="p-4 rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-lg bg-neutral-100 dark:bg-neutral-800 flex items-center justify-center">
          <Icon name="ph:coins" class="w-5 h-5 text-neutral-600 dark:text-neutral-300" />
        </div>
        <div>
          <p class="text-2xl font-semibold text-neutral-900 dark:text-white">${{ stats.creditsUsed }}</p>
          <p class="text-xs text-neutral-500 dark:text-neutral-400">Credits used</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { Stats } from '@/types'
import Icon from '@/Components/shared/Icon.vue'

defineProps<{
  stats: Stats
}>()

const formatNumber = (value: number): string => {
  if (value >= 1000000) return `${(value / 1000000).toFixed(1)}M`
  if (value >= 1000) return `${(value / 1000).toFixed(1)}K`
  return value.toString()
}
</script>
