<template>
  <div class="mx-auto w-full max-w-3xl px-4 pb-3">
    <div class="rounded-2xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
      <div class="flex items-start justify-between gap-3 border-b border-neutral-100 px-4 py-3 dark:border-neutral-800">
        <div class="min-w-0">
          <div class="flex items-center gap-2">
            <Icon name="ph:pulse" class="h-4 w-4 text-neutral-500 dark:text-neutral-400" />
            <h2 class="text-sm font-semibold text-neutral-950 dark:text-white">Workspace status</h2>
          </div>
          <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">
            {{ refreshedLabel }}
          </p>
        </div>

        <div class="flex items-center gap-1">
          <button
            type="button"
            class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-neutral-500 transition-colors hover:bg-neutral-100 hover:text-neutral-950 disabled:opacity-50 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:hover:text-white"
            :disabled="loading"
            title="Refresh status"
            @click="$emit('refresh')"
          >
            <Icon name="ph:arrow-clockwise" class="h-4 w-4" :class="loading && 'animate-spin'" />
          </button>
          <button
            type="button"
            class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-neutral-500 transition-colors hover:bg-neutral-100 hover:text-neutral-950 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:hover:text-white"
            title="Dismiss status"
            @click="$emit('dismiss')"
          >
            <Icon name="ph:x" class="h-4 w-4" />
          </button>
        </div>
      </div>

      <div v-if="loading && !status" class="space-y-4 p-4">
        <div class="grid gap-2 sm:grid-cols-4">
          <div v-for="index in 4" :key="index" class="h-16 animate-pulse rounded-xl bg-neutral-100 dark:bg-neutral-800" />
        </div>
        <div class="space-y-2">
          <div v-for="index in 3" :key="index" class="h-10 animate-pulse rounded-lg bg-neutral-100 dark:bg-neutral-800" />
        </div>
      </div>

      <div v-else-if="error" class="p-4">
        <div class="flex items-center justify-between gap-3 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 dark:border-rose-500/30 dark:bg-rose-500/10">
          <p class="text-sm text-rose-700 dark:text-rose-200">{{ error }}</p>
          <button type="button" class="shrink-0 text-sm font-medium text-rose-700 hover:text-rose-900 dark:text-rose-200 dark:hover:text-white" @click="$emit('refresh')">
            Retry
          </button>
        </div>
      </div>

      <div v-else-if="status" class="space-y-4 p-4">
        <div class="grid gap-2 sm:grid-cols-4">
          <StatusMetric label="Agents" :value="`${status.agents_online}/${status.agents_total}`" />
          <StatusMetric label="Active tasks" :value="status.tasks_active" />
          <StatusMetric label="Pending approvals" :value="status.pending_approvals ?? 0" />
          <StatusMetric label="Messages today" :value="status.messages_today" />
        </div>

        <div class="grid gap-4 lg:grid-cols-[1.4fr_1fr]">
          <section class="min-w-0">
            <div class="mb-2 flex items-center justify-between gap-2">
              <h3 class="text-xs font-semibold uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Agent activity</h3>
              <span class="text-xs text-neutral-400 dark:text-neutral-500">{{ activeAgents.length }} shown</span>
            </div>

            <div v-if="activeAgents.length" class="space-y-1">
              <div
                v-for="agent in activeAgents"
                :key="agent.id"
                class="flex items-center gap-3 rounded-xl px-2.5 py-2 transition-colors hover:bg-neutral-50 dark:hover:bg-neutral-800"
              >
                <SharedAgentAvatar :user="agentUser(agent)" size="sm" variant="soft" :show-tooltip="false" />
                <div class="min-w-0 flex-1">
                  <div class="flex items-center gap-2">
                    <p class="truncate text-sm font-medium text-neutral-950 dark:text-white">{{ agent.name }}</p>
                    <span :class="statusPillClasses(agent.status)">{{ formatStatus(agent.status) }}</span>
                  </div>
                  <p class="mt-0.5 truncate text-xs text-neutral-500 dark:text-neutral-400">
                    {{ agent.current_task || 'No active task' }}
                  </p>
                </div>
              </div>
            </div>

            <p v-else class="rounded-xl bg-neutral-50 px-3 py-2 text-sm text-neutral-500 dark:bg-neutral-800 dark:text-neutral-400">
              No agents are currently working.
            </p>
          </section>

          <section class="space-y-3">
            <div>
              <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Current chat</h3>
              <div class="rounded-xl bg-neutral-50 px-3 py-2 dark:bg-neutral-800">
                <div class="flex items-center justify-between gap-3">
                  <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-neutral-950 dark:text-white">{{ currentAgent?.name ?? 'No agent selected' }}</p>
                    <p class="mt-0.5 text-xs capitalize text-neutral-500 dark:text-neutral-400">{{ currentAgent?.status ?? 'idle' }}</p>
                  </div>
                  <span :class="runPillClasses">{{ runState }}</span>
                </div>
                <a
                  v-if="currentTask"
                  :href="taskHref(currentTask.id)"
                  class="mt-2 inline-flex max-w-full items-center gap-1.5 text-xs font-medium text-neutral-600 hover:text-neutral-950 dark:text-neutral-300 dark:hover:text-white"
                >
                  <Icon name="ph:arrow-square-out" class="h-3.5 w-3.5 shrink-0" />
                  <span class="truncate">{{ currentTask.title }}</span>
                </a>
              </div>
            </div>

            <div>
              <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Work queue</h3>
              <div class="space-y-1 rounded-xl bg-neutral-50 p-2 text-sm dark:bg-neutral-800">
                <QueueRow label="Completed today" :value="status.tasks_today" />
                <QueueRow label="Failed" :value="status.tasks_failed ?? 0" />
                <QueueRow label="All completed" :value="status.tasks_completed" />
              </div>
            </div>
          </section>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, defineComponent, h } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import SharedAgentAvatar from '@/Components/shared/AgentAvatar.vue'
import { useWorkspace } from '@/composables/useWorkspace'
import type { AgentStatus, AgentTask, User } from '@/types'

interface StatusAgent {
  id: string
  name: string
  status: AgentStatus
  current_task?: string | null
}

export interface WorkspaceStatus {
  agents: StatusAgent[]
  agents_online: number
  agents_total: number
  tasks_active: number
  tasks_completed: number
  tasks_today: number
  tasks_failed?: number
  pending_approvals?: number
  messages_total: number
  messages_today: number
}

const props = defineProps<{
  status: WorkspaceStatus | null
  loading?: boolean
  error?: string | null
  lastRefreshedAt?: Date | string | null
  currentAgent?: User | null
  currentTask?: AgentTask | null
}>()

defineEmits<{
  refresh: []
  dismiss: []
}>()

const { taskUrl } = useWorkspace()

const activeAgents = computed(() => (props.status?.agents ?? [])
  .filter(agent => agent.status !== 'offline' || agent.current_task)
  .slice(0, 5))

const runState = computed(() => {
  const status = props.currentTask?.status
  if (status === 'active' || status === 'pending') return 'Running'
  if (status === 'paused') return 'Paused'
  if (status === 'failed') return 'Failed'
  if (status === 'completed') return 'Complete'
  return 'Idle'
})

const runPillClasses = computed(() => [
  'shrink-0 rounded-full px-2 py-1 text-[11px] font-medium',
  runState.value === 'Running' && 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
  runState.value === 'Paused' && 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
  runState.value === 'Failed' && 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
  ['Idle', 'Complete'].includes(runState.value) && 'bg-neutral-100 text-neutral-600 dark:bg-neutral-700 dark:text-neutral-300',
])

const refreshedLabel = computed(() => {
  if (props.loading) return 'Refreshing...'
  if (!props.lastRefreshedAt) return 'Not refreshed yet'

  return `Refreshed ${new Date(props.lastRefreshedAt).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })}`
})

const agentUser = (agent: StatusAgent): User => ({
  id: agent.id,
  name: agent.name,
  type: 'agent',
  status: agent.status,
})

const formatStatus = (status: AgentStatus) => status.replace(/_/g, ' ')

const statusPillClasses = (status: AgentStatus) => [
  'shrink-0 rounded-full px-1.5 py-0.5 text-[10px] font-medium capitalize leading-none',
  ['working', 'online', 'busy'].includes(status) && 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
  ['awaiting_approval', 'awaiting_delegation', 'paused'].includes(status) && 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
  ['offline', 'sleeping'].includes(status) && 'bg-neutral-100 text-neutral-500 dark:bg-neutral-700 dark:text-neutral-400',
  ['idle'].includes(status) && 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
]

const taskHref = (taskId: string) => taskUrl(taskId)

const StatusMetric = defineComponent({
  props: {
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
  },
  setup(metricProps) {
    return () => h('div', { class: 'rounded-xl bg-neutral-50 px-3 py-2 dark:bg-neutral-800' }, [
      h('p', { class: 'text-[11px] font-medium uppercase tracking-wide text-neutral-400 dark:text-neutral-500' }, metricProps.label),
      h('p', { class: 'mt-1 text-lg font-semibold text-neutral-950 dark:text-white' }, String(metricProps.value)),
    ])
  },
})

const QueueRow = defineComponent({
  props: {
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
  },
  setup(rowProps) {
    return () => h('div', { class: 'flex items-center justify-between gap-3 px-1 py-1' }, [
      h('span', { class: 'text-neutral-500 dark:text-neutral-400' }, rowProps.label),
      h('span', { class: 'font-medium text-neutral-950 dark:text-white' }, String(rowProps.value)),
    ])
  },
})
</script>
