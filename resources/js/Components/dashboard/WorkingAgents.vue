<template>
  <div class="p-4 rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800">
    <div class="flex items-center justify-between mb-3">
      <h3 class="text-sm font-medium text-neutral-900 dark:text-white">Working Now</h3>
      <span v-if="agents.length > 0" class="flex items-center gap-1.5 text-xs text-neutral-500 dark:text-neutral-400">
        <span class="w-1.5 h-1.5 rounded-full bg-green-500" />
        {{ agents.length }} active
      </span>
    </div>

    <!-- Agent List -->
    <div v-if="agents.length > 0" class="space-y-2">
      <Link
        v-for="agent in agents.slice(0, 5)"
        :key="agent.id"
        :href="`/agent/${agent.id}`"
        class="flex items-center gap-3 p-2 -mx-2 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors duration-150"
      >
        <AgentAvatar :user="agent" size="sm" />
        <div class="flex-1 min-w-0">
          <p class="text-sm font-medium text-neutral-900 dark:text-white truncate">{{ agent.name }}</p>
          <p class="text-xs text-neutral-500 dark:text-neutral-400 truncate">{{ agent.currentTask || 'Working...' }}</p>
        </div>
      </Link>
    </div>

    <!-- Empty State -->
    <div v-else class="py-4 text-center">
      <Icon name="ph:moon-stars" class="w-6 h-6 text-neutral-300 dark:text-neutral-600 mx-auto mb-2" />
      <p class="text-xs text-neutral-500 dark:text-neutral-400">All agents idle</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import type { User } from '@/types'
import AgentAvatar from '@/Components/shared/AgentAvatar.vue'
import Icon from '@/Components/shared/Icon.vue'

defineProps<{
  agents: User[]
}>()
</script>
