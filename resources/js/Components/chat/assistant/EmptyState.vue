<template>
  <div class="text-center">
    <div class="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-2xl bg-neutral-950 text-white shadow-sm dark:bg-white dark:text-neutral-950">
      <Icon name="ph:sparkle" class="h-7 w-7" />
    </div>
    <h2 class="text-2xl font-semibold tracking-normal text-neutral-950 dark:text-white">How can OpenCompany help?</h2>
    <p class="mx-auto mt-2 max-w-lg text-sm leading-6 text-neutral-500 dark:text-neutral-400">
      Ask an agent to reason through work, use approved tools, inspect workspace data, or prepare the next action.
    </p>

    <div v-if="agents.length" class="mx-auto mt-5 flex max-w-sm items-center justify-center gap-2">
      <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400" for="assistant-agent">Agent</label>
      <select
        id="assistant-agent"
        :value="selectedAgentId"
        class="h-9 min-w-40 rounded-xl border border-neutral-200 bg-white px-3 text-sm text-neutral-800 outline-none dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100"
        @change="$emit('update:selectedAgentId', ($event.target as HTMLSelectElement).value)"
      >
        <option v-for="agent in agents" :key="agent.id" :value="agent.id">{{ agent.name }}</option>
      </select>
    </div>

    <div class="mt-8 grid gap-2 text-left sm:grid-cols-2">
      <button
        v-for="suggestion in suggestions"
        :key="suggestion.title"
        type="button"
        class="rounded-2xl border border-neutral-200 bg-white p-4 transition-colors hover:border-neutral-300 hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900 dark:hover:border-neutral-600 dark:hover:bg-neutral-800"
        @click="$emit('prompt', suggestion.prompt)"
      >
        <div class="mb-3 flex h-9 w-9 items-center justify-center rounded-xl bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300">
          <Icon :name="suggestion.icon" class="h-4 w-4" />
        </div>
        <p class="text-sm font-semibold text-neutral-950 dark:text-white">{{ suggestion.title }}</p>
        <p class="mt-1 text-xs leading-5 text-neutral-500 dark:text-neutral-400">{{ suggestion.description }}</p>
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import Icon from '@/Components/shared/Icon.vue'
import type { User } from '@/types'

defineProps<{
  agents: User[]
  selectedAgentId?: string
}>()

defineEmits<{
  'update:selectedAgentId': [agentId: string]
  prompt: [prompt: string]
}>()

const suggestions = [
  {
    icon: 'ph:strategy',
    title: 'Plan a task',
    description: 'Break down a workspace goal and identify the next concrete action.',
    prompt: 'Help me plan the next steps for the most important open work in this workspace.',
  },
  {
    icon: 'ph:wrench',
    title: 'Use tools',
    description: 'Ask an agent to inspect data or call an integration with approval gates.',
    prompt: 'Inspect the available tools and tell me what you can safely do in this workspace.',
  },
  {
    icon: 'ph:shield-check',
    title: 'Review approvals',
    description: 'Summarize pending approvals and the risk of approving them.',
    prompt: 'Review pending approval requests and explain what each one would allow.',
  },
  {
    icon: 'ph:brain',
    title: 'Think through context',
    description: 'Use workspace context and memory before producing a recommendation.',
    prompt: 'Think through the relevant workspace context and recommend the best next move.',
  },
]
</script>
