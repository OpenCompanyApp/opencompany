<template>
  <section class="relative flex h-full min-w-0 flex-1 flex-col bg-white dark:bg-neutral-950">
    <header class="flex h-14 shrink-0 items-center justify-between gap-3 border-b border-neutral-200 px-4 dark:border-neutral-800">
      <div class="flex min-w-0 items-center gap-3">
        <button
          type="button"
          class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-neutral-500 transition-colors hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:hover:text-white"
          :title="isMobile || sidebarCollapsed ? 'Show conversations' : 'Hide conversations'"
          @click="$emit('toggleSidebar')"
        >
          <Icon name="ph:sidebar-simple" class="h-5 w-5" />
        </button>
        <SharedAgentAvatar v-if="agent" :user="agent" size="sm" :show-status="true" class="shrink-0" />
        <div v-else-if="channelIcon" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-neutral-100 text-neutral-500 dark:bg-neutral-900 dark:text-neutral-300">
          <Icon :name="channelIcon" class="h-4 w-4" />
        </div>
        <div class="min-w-0">
          <h1 class="truncate text-sm font-semibold text-neutral-950 dark:text-white">{{ title }}</h1>
          <p class="truncate text-xs text-neutral-500 dark:text-neutral-400">{{ subtitle }}</p>
        </div>
      </div>

      <div class="flex items-center gap-1">
        <button
          type="button"
          class="inline-flex h-9 items-center gap-1.5 rounded-xl px-3 text-sm text-neutral-600 transition-colors hover:bg-neutral-100 hover:text-neutral-950 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:hover:text-white"
          @click="$emit('refresh')"
        >
          <Icon name="ph:arrow-clockwise" class="h-4 w-4" />
          <span class="hidden sm:inline">Refresh</span>
        </button>
      </div>
    </header>

    <div ref="scrollContainer" class="flex-1 overflow-y-auto">
      <div v-if="!channel" class="mx-auto flex min-h-full max-w-3xl flex-col justify-center px-4 py-10">
        <EmptyState
          :agents="agents"
          :selected-agent-id="selectedAgentId"
          @update:selected-agent-id="$emit('update:selectedAgentId', $event)"
          @prompt="sendSuggestedPrompt"
        />
      </div>

      <template v-else>
        <div v-if="messages.length === 0 && !hasRuntimeActivity && isAssistantChannel" class="mx-auto flex min-h-[calc(100vh-18rem)] max-w-3xl flex-col justify-center px-4 py-10">
          <EmptyState
            :agents="agents"
            :selected-agent-id="agent?.id ?? selectedAgentId"
            @update:selected-agent-id="$emit('update:selectedAgentId', $event)"
            @prompt="sendSuggestedPrompt"
          />
        </div>
        <div v-else-if="messages.length === 0 && !hasRuntimeActivity" class="mx-auto flex min-h-[calc(100vh-18rem)] max-w-3xl flex-col items-center justify-center px-4 py-10 text-center">
          <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-neutral-100 text-neutral-500 dark:bg-neutral-900 dark:text-neutral-300">
            <Icon :name="channelIcon ?? 'ph:chat-circle'" class="h-6 w-6" />
          </div>
          <h2 class="text-2xl font-semibold tracking-normal text-neutral-950 dark:text-white">Start the conversation</h2>
          <p class="mt-2 max-w-md text-sm leading-6 text-neutral-500 dark:text-neutral-400">
            Messages, files, and replies stay in this same OpenCompany chat surface.
          </p>
        </div>
        <div v-else-if="messages.length === 0" class="mx-auto w-full max-w-3xl px-4 py-6">
          <div class="flex items-center gap-3 rounded-lg border border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-800 dark:bg-neutral-900/70">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white text-neutral-500 shadow-sm dark:bg-neutral-950 dark:text-neutral-300">
              <Icon name="ph:sparkle" class="h-5 w-5" />
            </div>
            <div class="min-w-0">
              <p class="text-sm font-semibold text-neutral-950 dark:text-white">Runtime activity</p>
              <p class="text-sm text-neutral-600 dark:text-neutral-300">
                {{ agent?.name ?? 'The assistant' }} is working before the first visible chat response.
              </p>
            </div>
          </div>
        </div>

        <template v-else>
          <AssistantMessage
            v-for="message in messages"
            :key="message.id"
            :message="message"
            :current-user-id="currentUserId"
            @retry="$emit('retry', message)"
          />
        </template>

        <div v-if="channelApprovals.length" class="mx-auto w-full max-w-3xl space-y-3 px-4 py-4">
          <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-neutral-500 dark:text-neutral-400">
            <Icon name="ph:shield-check" class="h-4 w-4" />
            Approval queue
          </div>
          <ChatApprovalCard
            v-for="approval in channelApprovals"
            :key="approval.id"
            :request="approval"
            :author="approval.requester ?? agent ?? fallbackAgent"
            :timestamp="approval.createdAt ?? approval.requestedAt ?? new Date()"
            variant="detailed"
            size="md"
            :loading="approvalLoadingId === approval.id ? approvalLoadingAction : false"
            @approve="respond(approval.id, 'approved')"
            @reject="respond(approval.id, 'rejected')"
          />
        </div>

        <ThinkingPanel :tasks="tasks" />
      </template>
    </div>

    <PromptComposer
      :agents="agents"
      :selected-agent-id="agent?.id ?? selectedAgentId"
      :disabled="!channel && !selectedAgentId"
      :running="isRunning"
      :placeholder="composerPlaceholder"
      :show-agent-picker="isAssistantChannel"
      :show-ai-actions="isAssistantChannel"
      @update:selected-agent-id="$emit('update:selectedAgentId', $event)"
      @send="(content, attachments) => $emit('send', content, attachments)"
      @stop="$emit('stop')"
      @compact="$emit('compact')"
      @status="$emit('status')"
    />
  </section>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import SharedAgentAvatar from '@/Components/shared/AgentAvatar.vue'
import ChatApprovalCard from '@/Components/chat/ApprovalCard.vue'
import AssistantMessage from '@/Components/chat/assistant/AssistantMessage.vue'
import PromptComposer, { type ComposerAttachment } from '@/Components/chat/assistant/PromptComposer.vue'
import ThinkingPanel from '@/Components/chat/assistant/ThinkingPanel.vue'
import EmptyState from '@/Components/chat/assistant/EmptyState.vue'
import type { AgentTask, ApprovalRequest, Channel, Message, User } from '@/types'

const props = defineProps<{
  channel: Channel | null
  messages: Message[]
  tasks: AgentTask[]
  approvals: ApprovalRequest[]
  agents: User[]
  currentUserId: string
  selectedAgentId?: string
  isAssistantChannel: boolean
  isMobile?: boolean
  sidebarCollapsed?: boolean
  approvalLoadingId?: string | null
  approvalLoadingAction?: false | 'approve' | 'reject'
}>()

const emit = defineEmits<{
  send: [content: string, attachments: ComposerAttachment[]]
  retry: [message: Message]
  stop: []
  compact: []
  status: []
  refresh: []
  toggleSidebar: []
  'update:selectedAgentId': [agentId: string]
  approval: [id: string, status: 'approved' | 'rejected']
}>()

const scrollContainer = ref<HTMLElement | null>(null)

const agent = computed(() => {
  const channelAgent = props.channel?.members?.find(member => member.type === 'agent') ?? null
  if (channelAgent) return channelAgent
  if (!props.channel) return props.agents.find(agent => agent.id === props.selectedAgentId) ?? null
  return null
})
const otherMember = computed(() => props.channel?.members?.find(member => member.id !== props.currentUserId && member.type !== 'agent') ?? null)
const fallbackAgent = computed<User>(() => agent.value ?? props.agents[0] ?? { id: 'system', name: 'OpenCompany', type: 'agent' } as User)
const title = computed(() => {
  if (!props.channel) return 'New assistant chat'
  if (agent.value) return `Chat with ${agent.value.name}`
  if (props.channel.type === 'dm') return otherMember.value?.name ?? props.channel.name ?? 'Direct message'
  return props.channel.name ?? 'Channel'
})
const subtitle = computed(() => {
  if (props.channel && !props.isAssistantChannel) {
    if (props.channel.type === 'dm') return 'Direct message'
    const count = props.channel.members?.length ?? 0
    return `${props.channel.private ? 'Private channel' : 'Channel'} · ${count} member${count === 1 ? '' : 's'}`
  }
  if (!agent.value) return 'Choose an agent and start with a prompt'
  if (agent.value.status === 'awaiting_approval') return 'Waiting for approval'
  if (agent.value.status === 'working') return 'Working with tools and workspace context'
  return `${agent.value.status ?? 'idle'} · workspace-aware assistant`
})
const isRunning = computed(() => props.tasks.some(task => ['pending', 'active'].includes(task.status)))
const channelApprovals = computed(() => props.approvals.filter(approval => approval.status === 'pending'))
const hasRuntimeActivity = computed(() => channelApprovals.value.length > 0 || props.tasks.length > 0)
const channelIcon = computed(() => {
  if (!props.channel) return null
  if (props.channel.type === 'dm') return 'ph:chat-circle'
  if (props.channel.private) return 'ph:lock-simple'
  return 'ph:hash'
})
const composerPlaceholder = computed(() => {
  if (props.isAssistantChannel && agent.value) return `Ask ${agent.value.name}...`
  if (props.channel?.type === 'dm') return `Message ${otherMember.value?.name ?? 'this DM'}...`
  if (props.channel) return `Message #${props.channel.name ?? 'channel'}...`
  return 'Ask OpenCompany anything...'
})

watch(() => props.messages.length, () => {
  nextTick(() => {
    if (!scrollContainer.value) return
    scrollContainer.value.scrollTop = scrollContainer.value.scrollHeight
  })
})

const sendSuggestedPrompt = (prompt: string) => {
  emit('send', prompt, [])
}

const respond = (id: string, status: 'approved' | 'rejected') => {
  emit('approval', id, status)
}
</script>
