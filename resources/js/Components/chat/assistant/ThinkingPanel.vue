<template>
  <div v-if="task" class="mx-auto w-full max-w-3xl px-4 py-3">
    <div class="overflow-hidden rounded-2xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-neutral-900">
      <button
        type="button"
        class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left transition-colors hover:bg-neutral-50 dark:hover:bg-neutral-800/70"
        @click="expanded = !expanded"
      >
        <div class="flex min-w-0 items-center gap-3">
          <div :class="statusIconClasses">
            <Icon :name="statusIcon" class="h-4 w-4" />
          </div>
          <div class="min-w-0">
            <p class="truncate text-sm font-semibold text-neutral-950 dark:text-white">{{ title }}</p>
            <p class="truncate text-xs text-neutral-500 dark:text-neutral-400">{{ subtitle }}</p>
          </div>
        </div>
        <Icon name="ph:caret-down" :class="['h-4 w-4 shrink-0 text-neutral-400 transition-transform', expanded && 'rotate-180']" />
      </button>

      <div v-if="expanded" class="border-t border-neutral-200 px-4 py-3 dark:border-neutral-800">
        <div v-if="task.steps?.length" class="space-y-2">
          <div
            v-for="step in task.steps"
            :key="step.id"
            class="rounded-xl border border-neutral-200 bg-neutral-50 p-3 dark:border-neutral-700 dark:bg-neutral-800/60"
          >
            <div class="flex items-start gap-3">
              <div :class="stepIconClasses(step)">
                <Icon :name="stepIcon(step)" class="h-3.5 w-3.5" />
              </div>
              <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                  <p class="text-sm font-medium text-neutral-900 dark:text-white">{{ step.description }}</p>
                  <span class="rounded-full bg-white px-2 py-0.5 text-[11px] font-medium capitalize text-neutral-500 dark:bg-neutral-900 dark:text-neutral-400">
                    {{ step.status.replace('_', ' ') }}
                  </span>
                </div>
                <p v-if="step.metadata?.tool" class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                  Tool call: <span class="font-mono">{{ step.metadata.tool }}</span>
                </p>
                <details v-if="step.metadata?.arguments || step.metadata?.result || step.metadata?.lua_meta" class="mt-2">
                  <summary class="cursor-pointer text-xs font-medium text-neutral-500 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white">
                    Inspect tool data
                  </summary>
                  <pre class="mt-2 max-h-56 overflow-auto rounded-lg bg-neutral-950 p-3 text-xs text-neutral-100">{{ formatMetadata(step.metadata) }}</pre>
                </details>
              </div>
            </div>
          </div>
        </div>

        <div v-else class="flex items-center gap-2 text-sm text-neutral-500 dark:text-neutral-400">
          <Icon name="ph:spinner-gap" class="h-4 w-4 animate-spin" />
          Waiting for the first runtime step...
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import type { AgentTask, TaskStep } from '@/types'

const props = defineProps<{
  tasks?: AgentTask[]
  task?: AgentTask | null
}>()

const expanded = ref(true)
const task = computed(() => props.task ?? props.tasks?.[0] ?? null)
const isRunning = computed(() => task.value && ['pending', 'active', 'paused'].includes(task.value.status))

watch(task, (next, previous) => {
  if (next?.id !== previous?.id) expanded.value = true
})

const title = computed(() => {
  if (!task.value) return ''
  if (task.value.status === 'pending') return `${task.value.agent?.name ?? 'Assistant'} is queued`
  if (task.value.status === 'active') return `${task.value.agent?.name ?? 'Assistant'} is thinking`
  if (task.value.status === 'paused') return `${task.value.agent?.name ?? 'Assistant'} is waiting`
  if (task.value.status === 'failed') return 'Run failed'
  return 'Run completed'
})

const subtitle = computed(() => {
  if (!task.value) return ''
  const steps = task.value.steps?.length ?? 0
  const toolCalls = task.value.steps?.filter(step => step.metadata?.tool).length ?? 0
  if (isRunning.value) return `${steps} step${steps === 1 ? '' : 's'} recorded${toolCalls ? `, ${toolCalls} tool call${toolCalls === 1 ? '' : 's'}` : ''}`
  return task.value.result?.error ? String(task.value.result.error) : `${steps} runtime step${steps === 1 ? '' : 's'}`
})

const statusIcon = computed(() => {
  if (!task.value) return 'ph:circle'
  if (task.value.status === 'active') return 'ph:spinner-gap'
  if (task.value.status === 'pending') return 'ph:clock'
  if (task.value.status === 'failed') return 'ph:warning-circle'
  if (task.value.status === 'completed') return 'ph:check-circle'
  return 'ph:pause-circle'
})

const statusIconClasses = computed(() => [
  'flex h-8 w-8 shrink-0 items-center justify-center rounded-xl',
  task.value?.status === 'failed'
    ? 'bg-red-50 text-red-600 dark:bg-red-950 dark:text-red-300'
    : isRunning.value
      ? 'bg-blue-50 text-blue-600 dark:bg-blue-950 dark:text-blue-300'
      : 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-300',
  task.value?.status === 'active' && 'animate-pulse',
])

const stepIcon = (step: TaskStep) => {
  if (typeof step.metadata?.icon === 'string') return step.metadata.icon
  if (step.stepType === 'approval') return 'ph:shield-check'
  if (step.metadata?.tool) return 'ph:wrench'
  if (step.stepType === 'message') return 'ph:chat-circle'
  if (step.status === 'completed') return 'ph:check'
  return 'ph:circle'
}

const stepIconClasses = (step: TaskStep) => [
  'mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg',
  step.status === 'completed'
    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
    : step.status === 'in_progress'
      ? 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300'
      : 'bg-neutral-200 text-neutral-600 dark:bg-neutral-700 dark:text-neutral-300',
]

const formatMetadata = (metadata: Record<string, unknown>) => JSON.stringify({
  tool: metadata.tool,
  arguments: metadata.arguments,
  result: metadata.result,
  lua_meta: metadata.lua_meta,
}, null, 2)
</script>
