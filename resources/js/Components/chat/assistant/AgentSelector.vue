<template>
  <PopoverRoot v-model:open="open">
    <PopoverTrigger as-child>
      <button
        type="button"
        :class="triggerClasses"
        :disabled="disabled || agents.length === 0"
        :title="compact ? 'New assistant chat' : 'Choose agent'"
        :aria-label="compact ? 'New assistant chat' : 'Choose agent'"
      >
        <template v-if="compact">
          <Icon name="ph:plus" class="h-4 w-4" />
        </template>

        <template v-else>
          <SharedAgentAvatar
            v-if="selectedAgent"
            :user="selectedAgent"
            size="xs"
            variant="soft"
            :show-status="true"
            :show-tooltip="false"
          />
          <div class="min-w-0 text-left">
            <p class="truncate text-sm font-medium leading-4 text-neutral-900 dark:text-white">
              {{ selectedAgent?.name ?? placeholder }}
            </p>
            <p v-if="selectedAgent" class="mt-0.5 truncate text-[11px] capitalize leading-3 text-neutral-500 dark:text-neutral-400">
              {{ agentSummary(selectedAgent) }}
            </p>
          </div>
          <Icon name="ph:caret-down" class="h-3.5 w-3.5 shrink-0 text-neutral-400 dark:text-neutral-500" />
        </template>
      </button>
    </PopoverTrigger>

    <PopoverPortal>
      <PopoverContent
        class="z-50 w-80 rounded-xl border border-neutral-200 bg-white p-1.5 shadow-xl outline-none animate-in fade-in-0 zoom-in-95 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95 dark:border-neutral-700 dark:bg-neutral-900"
        :side="side"
        :align="align"
        :side-offset="8"
        :avoid-collisions="true"
      >
        <div class="px-2 pb-1 pt-1.5">
          <p class="text-[11px] font-semibold uppercase tracking-wide text-neutral-400 dark:text-neutral-500">
            {{ label }}
          </p>
        </div>

        <div class="max-h-80 overflow-y-auto">
          <button
            v-for="agent in agents"
            :key="agent.id"
            type="button"
            :class="optionClasses(agent.id === selectedAgentId)"
            @click="selectAgent(agent.id)"
          >
            <SharedAgentAvatar
              :user="agent"
              size="sm"
              variant="soft"
              :show-status="true"
              :show-tooltip="false"
            />

            <div class="min-w-0 flex-1">
              <div class="flex items-center gap-2">
                <p class="truncate text-sm font-medium text-neutral-900 dark:text-white">{{ agent.name }}</p>
                <span
                  v-if="agent.status"
                  :class="statusPillClasses(agent.status)"
                >
                  {{ formatStatus(agent.status) }}
                </span>
              </div>
              <p class="mt-0.5 truncate text-xs text-neutral-500 dark:text-neutral-400">
                {{ agentDetail(agent) }}
              </p>
            </div>

            <Icon
              v-if="agent.id === selectedAgentId"
              name="ph:check-circle-fill"
              class="h-4 w-4 shrink-0 text-neutral-900 dark:text-white"
            />
          </button>
        </div>
      </PopoverContent>
    </PopoverPortal>
  </PopoverRoot>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { PopoverRoot, PopoverTrigger, PopoverPortal, PopoverContent } from 'reka-ui'
import Icon from '@/Components/shared/Icon.vue'
import SharedAgentAvatar from '@/Components/shared/AgentAvatar.vue'
import type { AgentStatus, User } from '@/types'

const props = withDefaults(defineProps<{
  agents: User[]
  selectedAgentId?: string
  compact?: boolean
  disabled?: boolean
  placeholder?: string
  label?: string
  side?: 'top' | 'right' | 'bottom' | 'left'
  align?: 'start' | 'center' | 'end'
}>(), {
  compact: false,
  disabled: false,
  placeholder: 'Choose agent',
  label: 'Choose agent',
  side: 'bottom',
  align: 'end',
})

const emit = defineEmits<{
  'update:selectedAgentId': [agentId: string]
}>()

const open = ref(false)

const selectedAgent = computed(() => props.agents.find(agent => agent.id === props.selectedAgentId) ?? props.agents[0] ?? null)

const triggerClasses = computed(() => props.compact
  ? [
      'inline-flex h-9 w-9 items-center justify-center rounded-lg bg-neutral-900 text-white transition-colors hover:bg-neutral-800 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-neutral-100 dark:text-neutral-900 dark:hover:bg-neutral-200',
    ]
  : [
      'inline-flex h-10 min-w-0 max-w-56 items-center gap-2 rounded-xl border border-neutral-200 bg-white px-2.5 text-left transition-colors hover:border-neutral-300 hover:bg-neutral-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-neutral-700 dark:bg-neutral-900 dark:hover:border-neutral-600 dark:hover:bg-neutral-800',
    ])

const optionClasses = (selected: boolean) => [
  'flex w-full items-center gap-3 rounded-lg px-2.5 py-2.5 text-left transition-colors',
  selected
    ? 'bg-neutral-100 text-neutral-950 dark:bg-neutral-800 dark:text-white'
    : 'text-neutral-700 hover:bg-neutral-50 hover:text-neutral-950 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:hover:text-white',
]

const selectAgent = (agentId: string) => {
  emit('update:selectedAgentId', agentId)
  open.value = false
}

const agentSummary = (agent: User) => {
  const type = agent.agentType ? `${agent.agentType} agent` : 'agent'
  return agent.status ? `${type} · ${formatStatus(agent.status)}` : type
}

const agentDetail = (agent: User) => {
  if (agent.currentTask) return agent.currentTask
  return agent.agentType ? `${agent.agentType} agent` : 'assistant agent'
}

const formatStatus = (status: AgentStatus) => status.replace(/_/g, ' ')

const statusPillClasses = (status: AgentStatus) => [
  'shrink-0 rounded-full px-1.5 py-0.5 text-[10px] font-medium capitalize leading-none',
  ['working', 'online', 'busy'].includes(status) && 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
  ['awaiting_approval', 'awaiting_delegation', 'paused'].includes(status) && 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
  ['offline', 'sleeping'].includes(status) && 'bg-neutral-100 text-neutral-500 dark:bg-neutral-800 dark:text-neutral-400',
  ['idle'].includes(status) && 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
]
</script>
