<template>
  <div class="mx-auto w-full max-w-3xl px-4 pb-3">
    <div class="rounded-2xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
      <div class="flex items-start justify-between gap-3 border-b border-neutral-100 px-4 py-3 dark:border-neutral-800">
        <div class="min-w-0">
          <div class="flex items-center gap-2">
            <Icon :name="runIcon" class="h-4 w-4 text-neutral-500 dark:text-neutral-400" />
            <h2 class="text-sm font-semibold text-neutral-950 dark:text-white">Chat status</h2>
            <span :class="runPillClasses">{{ runState }}</span>
          </div>
          <p class="mt-0.5 truncate text-xs text-neutral-500 dark:text-neutral-400">
            {{ headerSubtitle }}
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

      <div v-if="loading && !status" class="space-y-3 p-4">
        <div v-for="index in 4" :key="index" class="h-14 animate-pulse rounded-lg bg-neutral-100 dark:bg-neutral-800" />
      </div>

      <div v-else-if="error" class="p-4">
        <div class="flex items-center justify-between gap-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 dark:border-rose-500/30 dark:bg-rose-500/10">
          <p class="text-sm text-rose-700 dark:text-rose-200">{{ error }}</p>
          <button type="button" class="shrink-0 text-sm font-medium text-rose-700 hover:text-rose-900 dark:text-rose-200 dark:hover:text-white" @click="$emit('refresh')">
            Retry
          </button>
        </div>
      </div>

      <div v-else class="divide-y divide-neutral-100 dark:divide-neutral-800">
        <section class="px-4 py-3">
          <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
              <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Current run</p>
              <p class="mt-1 truncate text-sm font-medium text-neutral-950 dark:text-white">{{ currentStepLabel }}</p>
              <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">{{ runDetailLabel }}</p>
            </div>
            <a
              v-if="currentTask"
              :href="taskHref(currentTask.id)"
              class="inline-flex shrink-0 items-center gap-1.5 rounded-lg px-2 py-1 text-xs font-medium text-neutral-600 transition-colors hover:bg-neutral-100 hover:text-neutral-950 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:hover:text-white"
            >
              <Icon name="ph:arrow-square-out" class="h-3.5 w-3.5" />
              Task
            </a>
          </div>
        </section>

        <section class="px-4 py-3">
          <div class="mb-2 flex items-center justify-between gap-3">
            <div class="min-w-0">
              <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Context</p>
              <p class="mt-1 truncate text-sm font-medium text-neutral-950 dark:text-white">{{ contextTitle }}</p>
            </div>
            <span :class="compactionPillClasses">{{ compactionLabel }}</span>
          </div>

          <div v-if="contextStats" class="space-y-2">
            <div class="h-2 overflow-hidden rounded-full bg-neutral-100 dark:bg-neutral-800">
              <div :class="contextBarClasses" :style="{ width: `${Math.min(contextStats.pctUsed, 100)}%` }" class="h-full rounded-full transition-all" />
            </div>
            <div class="grid gap-2 text-xs text-neutral-500 dark:text-neutral-400 sm:grid-cols-3">
              <div>
                <span class="block text-neutral-400 dark:text-neutral-500">Used</span>
                <span class="font-mono text-neutral-700 dark:text-neutral-200">{{ formatTokens(contextStats.usedTokens) }} / {{ formatTokens(contextStats.contextWindow) }}</span>
              </div>
              <div>
                <span class="block text-neutral-400 dark:text-neutral-500">Before compaction</span>
                <span class="font-mono text-neutral-700 dark:text-neutral-200">{{ formatTokens(contextStats.remainingToCompaction) }} remaining</span>
              </div>
              <div>
                <span class="block text-neutral-400 dark:text-neutral-500">Basis</span>
                <span class="text-neutral-700 dark:text-neutral-200">{{ contextStats.estimated ? 'Estimated from chat' : 'Last run usage' }}</span>
              </div>
            </div>
          </div>

          <p v-else class="text-sm text-neutral-500 dark:text-neutral-400">
            Context usage will appear after this agent has a channel and model budget.
          </p>
        </section>

        <section class="grid gap-3 px-4 py-3 sm:grid-cols-2">
          <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Conversation</p>
            <dl class="mt-2 space-y-1 text-sm">
              <div class="flex justify-between gap-3">
                <dt class="text-neutral-500 dark:text-neutral-400">Agent</dt>
                <dd class="truncate text-neutral-950 dark:text-white">{{ currentAgent?.name ?? 'None selected' }}</dd>
              </div>
              <div class="flex justify-between gap-3">
                <dt class="text-neutral-500 dark:text-neutral-400">Scope</dt>
                <dd class="truncate text-neutral-950 dark:text-white">{{ scopeLabel }}</dd>
              </div>
              <div class="flex justify-between gap-3">
                <dt class="text-neutral-500 dark:text-neutral-400">Messages in context</dt>
                <dd class="font-mono text-neutral-950 dark:text-white">{{ messagesInBudgetLabel }}</dd>
              </div>
            </dl>
          </div>

          <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Tool activity</p>
            <dl class="mt-2 space-y-1 text-sm">
              <div class="flex justify-between gap-3">
                <dt class="text-neutral-500 dark:text-neutral-400">Current run</dt>
                <dd class="font-mono text-neutral-950 dark:text-white">{{ toolCountLabel }}</dd>
              </div>
              <div class="flex justify-between gap-3">
                <dt class="text-neutral-500 dark:text-neutral-400">Last tool</dt>
                <dd class="truncate text-neutral-950 dark:text-white">{{ lastToolLabel }}</dd>
              </div>
              <div class="flex justify-between gap-3">
                <dt class="text-neutral-500 dark:text-neutral-400">Approvals</dt>
                <dd class="font-mono text-neutral-950 dark:text-white">{{ pendingApprovals }}</dd>
              </div>
            </dl>
          </div>
        </section>

        <section v-if="attentionItems.length" class="space-y-2 px-4 py-3">
          <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Needs attention</p>
          <div v-for="item in attentionItems" :key="item" class="flex items-start gap-2 text-sm text-amber-700 dark:text-amber-200">
            <Icon name="ph:warning-circle" class="mt-0.5 h-4 w-4 shrink-0" />
            <span>{{ item }}</span>
          </div>
        </section>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import { useWorkspace } from '@/composables/useWorkspace'
import type { AgentTask, ApprovalRequest, Channel, TaskStep, User } from '@/types'
import { humanizeToolName } from '@/utils/toolDisplay'

interface StatusAgent {
  id: string
  name: string
  status: string
  current_task?: string | null
}

interface ContextBudgetSnapshot {
  provider?: string | null
  model?: string | null
  context_window: number
  effective_window: number
  raw_message_tokens: number
  adjusted_message_tokens: number
  compaction_threshold: number
  percent_left: number
  is_above_warning: boolean
  is_above_compaction: boolean
  is_at_blocking_limit: boolean
}

interface TokenBreakdown {
  context_window: number
  output_reserve?: number
  system_prompt?: { total?: number }
  volatile_prompt_context?: { total?: number }
  messages?: { total?: number; count?: number }
  compaction?: {
    threshold?: number
    adjusted_tokens?: number
    remaining?: number
    pct_used?: number
  }
  actual_prompt_tokens?: number
}

interface ConversationStatus {
  channel_id?: string | null
  agent_id?: string | null
  provider?: string | null
  model?: string | null
  context?: ContextBudgetSnapshot | null
  messages_total?: number
  messages_in_budget?: number
  summary?: {
    tokens_after?: number | null
    messages_summarized?: number | null
    compaction_count?: number | null
    last_compacted_at?: string | null
    circuit_open_until?: string | null
    last_error?: string | null
  } | null
  last_run?: {
    id: string
    title?: string | null
    status?: string | null
    started_at?: string | null
    completed_at?: string | null
    updated_at?: string | null
    token_breakdown?: TokenBreakdown | null
    result?: {
      prompt_tokens?: number | null
      completion_tokens?: number | null
      tool_calls_count?: number | null
      generation_time_ms?: number | null
    }
  } | null
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
  conversation?: ConversationStatus | null
}

const props = defineProps<{
  status: WorkspaceStatus | null
  loading?: boolean
  error?: string | null
  lastRefreshedAt?: Date | string | null
  currentAgent?: User | null
  currentTask?: AgentTask | null
  currentChannel?: Channel | null
  tasks?: AgentTask[]
  approvals?: ApprovalRequest[]
}>()

defineEmits<{
  refresh: []
  dismiss: []
}>()

const { taskUrl } = useWorkspace()

const conversation = computed(() => props.status?.conversation ?? null)
const taskSteps = computed(() => props.currentTask?.steps ?? [])
const pendingApprovals = computed(() => props.approvals?.filter(approval => approval.status === 'pending').length ?? 0)

const runState = computed(() => {
  const status = props.currentTask?.status
  if (status === 'pending') return 'Queued'
  if (status === 'active') {
    return lastToolStep.value ? 'Using tools' : 'Thinking'
  }
  if (status === 'paused') return pendingApprovals.value > 0 ? 'Waiting for approval' : 'Paused'
  if (status === 'failed') return 'Failed'
  return 'Idle'
})

const runIcon = computed(() => {
  if (runState.value === 'Using tools') return 'ph:wrench'
  if (runState.value === 'Thinking') return 'ph:spinner-gap'
  if (runState.value === 'Waiting for approval') return 'ph:shield-check'
  if (runState.value === 'Failed') return 'ph:warning-circle'
  if (runState.value === 'Queued') return 'ph:clock'
  return 'ph:pulse'
})

const runPillClasses = computed(() => [
  'shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium',
  ['Thinking', 'Using tools'].includes(runState.value) && 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
  ['Queued', 'Waiting for approval', 'Paused'].includes(runState.value) && 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
  runState.value === 'Failed' && 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
  runState.value === 'Idle' && 'bg-neutral-100 text-neutral-600 dark:bg-neutral-700 dark:text-neutral-300',
])

const headerSubtitle = computed(() => {
  const parts = [
    props.currentAgent?.name ?? 'No agent selected',
    modelLabel.value,
    refreshedLabel.value,
  ].filter(Boolean)

  return parts.join(' · ')
})

const refreshedLabel = computed(() => {
  if (props.loading) return 'refreshing'
  if (!props.lastRefreshedAt) return null

  return `refreshed ${new Date(props.lastRefreshedAt).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })}`
})

const currentStepLabel = computed(() => {
  if (!props.currentTask) return 'No active run'
  const latest = latestStep.value
  if (latest?.description) return latest.description
  if (props.currentTask.status === 'pending') return 'Waiting for the worker'
  if (props.currentTask.status === 'active') return 'Generating response'
  if (props.currentTask.status === 'paused') return 'Paused before continuing'
  if (props.currentTask.status === 'failed') return 'Run failed'
  return 'Last run completed'
})

const runDetailLabel = computed(() => {
  if (!props.currentTask) return 'Ready for the next message.'

  const details = [
    elapsedLabel.value,
    taskSteps.value.length ? `${taskSteps.value.length} step${taskSteps.value.length === 1 ? '' : 's'}` : null,
    toolSteps.value.length ? `${toolSteps.value.length} tool${toolSteps.value.length === 1 ? '' : 's'}` : null,
  ].filter(Boolean)

  return details.join(' · ') || props.currentTask.title
})

const latestStep = computed(() => taskSteps.value.at(-1) ?? null)
const toolSteps = computed(() => taskSteps.value.filter(step => toolName(step) !== null))
const lastToolStep = computed(() => toolSteps.value.at(-1) ?? null)

const elapsedLabel = computed(() => {
  const started = props.currentTask?.startedAt ?? props.currentTask?.createdAt
  if (!started) return null

  const startMs = new Date(started).getTime()
  if (Number.isNaN(startMs)) return null

  const end = props.currentTask?.completedAt ? new Date(props.currentTask.completedAt).getTime() : Date.now()
  const seconds = Math.max(0, Math.round((end - startMs) / 1000))
  if (seconds < 60) return `${seconds}s`

  return `${Math.floor(seconds / 60)}m ${seconds % 60}s`
})

const modelLabel = computed(() => conversation.value?.model ?? props.currentAgent?.brain ?? 'model unknown')

const contextStats = computed(() => {
  const breakdown = conversation.value?.last_run?.token_breakdown
  if (breakdown?.context_window) {
    const usedTokens = breakdown.actual_prompt_tokens
      ?? ((breakdown.system_prompt?.total ?? 0) + (breakdown.volatile_prompt_context?.total ?? 0) + (breakdown.messages?.total ?? 0))
    const threshold = breakdown.compaction?.threshold ?? breakdown.context_window
    const adjusted = breakdown.compaction?.adjusted_tokens ?? usedTokens
    const remainingToCompaction = breakdown.compaction?.remaining ?? Math.max(0, threshold - adjusted)
    const pctUsed = breakdown.compaction?.pct_used ?? (threshold > 0 ? Math.round((adjusted / threshold) * 100) : 0)

    return {
      contextWindow: breakdown.context_window,
      usedTokens,
      remainingToCompaction,
      pctUsed,
      estimated: false,
      aboveWarning: pctUsed >= 70,
      aboveCompaction: pctUsed >= 100,
    }
  }

  const budget = conversation.value?.context
  if (!budget) return null

  const usedTokens = budget.adjusted_message_tokens
  const threshold = budget.compaction_threshold

  return {
    contextWindow: budget.context_window,
    usedTokens,
    remainingToCompaction: Math.max(0, threshold - usedTokens),
    pctUsed: threshold > 0 ? Math.round((usedTokens / threshold) * 100) : 0,
    estimated: true,
    aboveWarning: budget.is_above_warning,
    aboveCompaction: budget.is_above_compaction || budget.is_at_blocking_limit,
  }
})

const contextTitle = computed(() => {
  if (!contextStats.value) return modelLabel.value
  return `${modelLabel.value} · ${formatTokens(contextStats.value.contextWindow)} window`
})

const compactionLabel = computed(() => {
  if (!contextStats.value) return 'Unknown'
  if (contextStats.value.aboveCompaction) return 'Compact now'
  if (contextStats.value.aboveWarning) return 'Compaction soon'
  return 'Healthy'
})

const compactionPillClasses = computed(() => [
  'shrink-0 rounded-full px-2 py-1 text-[11px] font-medium',
  compactionLabel.value === 'Healthy' && 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
  compactionLabel.value === 'Compaction soon' && 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
  compactionLabel.value === 'Compact now' && 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
  compactionLabel.value === 'Unknown' && 'bg-neutral-100 text-neutral-600 dark:bg-neutral-700 dark:text-neutral-300',
])

const contextBarClasses = computed(() => [
  contextStats.value?.aboveCompaction
    ? 'bg-rose-500'
    : contextStats.value?.aboveWarning
      ? 'bg-amber-500'
      : 'bg-emerald-500',
])

const scopeLabel = computed(() => {
  if (!props.currentChannel) return 'Draft chat'
  if (props.currentChannel.type === 'dm') return 'Agent DM'
  return props.currentChannel.name ?? 'Channel'
})

const messagesInBudgetLabel = computed(() => {
  const inBudget = conversation.value?.messages_in_budget
  const total = conversation.value?.messages_total
  if (inBudget == null && total == null) return 'unknown'
  if (inBudget != null && total != null && inBudget !== total) return `${inBudget}/${total}`
  return String(total ?? inBudget)
})

const toolCountLabel = computed(() => String(toolSteps.value.length || conversation.value?.last_run?.result?.tool_calls_count || 0))
const lastToolLabel = computed(() => lastToolStep.value ? humanizeToolName(toolName(lastToolStep.value)) : 'None')

const attentionItems = computed(() => {
  const items: string[] = []
  if (pendingApprovals.value > 0) items.push(`${pendingApprovals.value} approval${pendingApprovals.value === 1 ? '' : 's'} waiting in this chat.`)
  if (props.currentTask?.status === 'failed') items.push(String(props.currentTask.result?.error ?? 'The last run failed.'))
  if (contextStats.value?.aboveCompaction) items.push('Conversation context is at or past the compaction threshold.')
  if (conversation.value?.summary?.circuit_open_until) items.push('Conversation compaction is temporarily paused after a previous failure.')
  if (conversation.value?.summary?.last_error) items.push(`Last compaction error: ${conversation.value.summary.last_error}`)
  return items
})

const taskHref = (taskId: string) => taskUrl(taskId)

const formatTokens = (value: number | null | undefined) => {
  if (value == null || Number.isNaN(Number(value))) return 'unknown'
  if (value >= 1_000_000) return `${(value / 1_000_000).toFixed(value >= 10_000_000 ? 0 : 1)}M`
  if (value >= 1_000) return `${(value / 1_000).toFixed(value >= 10_000 ? 0 : 1)}k`
  return String(Math.round(value))
}

const toolName = (step: TaskStep): string | null => {
  const metadata = step.metadata ?? {}
  const value = metadata.tool_name ?? metadata.tool ?? metadata.name
  return typeof value === 'string' && value.length > 0 ? value : null
}

</script>
