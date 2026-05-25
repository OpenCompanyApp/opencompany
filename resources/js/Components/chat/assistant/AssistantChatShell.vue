<template>
  <div class="flex h-full min-h-0 bg-white dark:bg-neutral-950">
    <ConversationSidebar
      class="hidden md:flex"
      :channels="channels"
      :selected-channel="selectedChannel"
      :agents="agents"
      :current-user-id="currentUserId"
      :collapsed="sidebarCollapsed"
      @select="$emit('selectChannel', $event)"
      @new-agent-chat="$emit('newAgentChat', $event)"
      @create-channel="$emit('createChannel')"
      @create-dm="$emit('createDm')"
      @toggle-collapse="toggleSidebarCollapse"
    />

    <AssistantConversation
      :channel="selectedChannel"
      :messages="messages"
      :tasks="tasks"
      :approvals="approvals"
      :agents="agents"
      :current-user-id="currentUserId"
      :selected-agent-id="selectedAgentId"
      :is-assistant-channel="isAssistantChannel"
      :is-mobile="isMobile"
      :sidebar-collapsed="sidebarCollapsed"
      :approval-loading-id="approvalLoadingId"
      :approval-loading-action="approvalLoadingAction"
      :status-panel-open="statusPanelOpen"
      :workspace-status="workspaceStatus"
      :workspace-status-loading="workspaceStatusLoading"
      :workspace-status-error="workspaceStatusError"
      :workspace-status-last-refreshed-at="workspaceStatusLastRefreshedAt"
      :composer-error="composerError"
      :composer-draft="composerDraft"
      :composer-focus-request-key="composerFocusRequestKey"
      :empty-state-notice="emptyStateNotice"
      @send="(content, attachments) => $emit('send', content, attachments)"
      @retry="$emit('retry', $event)"
      @stop="$emit('stop')"
      @compact="$emit('compact')"
      @status="$emit('status')"
      @prefill="(prompt) => $emit('prefill', prompt)"
      @refresh="$emit('refresh')"
      @refresh-status="$emit('refreshStatus')"
      @close-status="$emit('closeStatus')"
      @toggle-sidebar="handleConversationSidebarToggle"
      @update:selected-agent-id="$emit('update:selectedAgentId', $event)"
      @update:composer-draft="$emit('update:composerDraft', $event)"
      @approval="(id, status) => $emit('approval', id, status)"
    />

    <Slideover v-if="isMobile" v-model:open="mobileSidebarOpen" side="left" size="sm" :show-close="false">
      <template #header>
        <div class="flex w-full items-center justify-between">
          <span class="font-semibold text-neutral-900 dark:text-white">Conversations</span>
          <button
            type="button"
            class="rounded-lg p-2 text-neutral-500 transition-colors hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-700"
            @click="mobileSidebarOpen = false"
          >
            <Icon name="ph:x" class="h-5 w-5" />
          </button>
        </div>
      </template>
      <template #body>
        <div class="-mx-6 -my-4 h-full">
          <ConversationSidebar
            class="h-full border-r-0"
            :channels="channels"
            :selected-channel="selectedChannel"
            :agents="agents"
            :current-user-id="currentUserId"
            :collapsible="false"
            @select="selectFromMobile"
            @new-agent-chat="newAgentFromMobile"
            @create-channel="createChannelFromMobile"
            @create-dm="createDmFromMobile"
          />
        </div>
      </template>
    </Slideover>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import Slideover from '@/Components/shared/Slideover.vue'
import ConversationSidebar from '@/Components/chat/assistant/ConversationSidebar.vue'
import AssistantConversation from '@/Components/chat/assistant/AssistantConversation.vue'
import type { WorkspaceStatus } from '@/Components/chat/assistant/AssistantStatusPanel.vue'
import type { ComposerAttachment } from '@/Components/chat/assistant/PromptComposer.vue'
import type { AgentTask, ApprovalRequest, Channel, Message, User } from '@/types'

const props = defineProps<{
  channels: Channel[]
  selectedChannel: Channel | null
  messages: Message[]
  tasks: AgentTask[]
  approvals: ApprovalRequest[]
  agents: User[]
  currentUserId: string
  selectedAgentId?: string
  isMobile: boolean
  isAssistantChannel: boolean
  approvalLoadingId?: string | null
  approvalLoadingAction?: false | 'approve' | 'reject'
  statusPanelOpen?: boolean
  workspaceStatus?: WorkspaceStatus | null
  workspaceStatusLoading?: boolean
  workspaceStatusError?: string | null
  workspaceStatusLastRefreshedAt?: Date | string | null
  composerError?: string | null
  composerDraft?: string
  composerFocusRequestKey?: number
  emptyStateNotice?: string | null
}>()

const emit = defineEmits<{
  selectChannel: [channel: Channel]
  newAgentChat: [agentId: string]
  createChannel: []
  createDm: []
  send: [content: string, attachments: ComposerAttachment[]]
  retry: [message: Message]
  stop: []
  compact: []
  status: []
  prefill: [prompt: string]
  refresh: []
  refreshStatus: []
  closeStatus: []
  'update:selectedAgentId': [agentId: string]
  'update:composerDraft': [value: string]
  approval: [id: string, status: 'approved' | 'rejected']
}>()

const mobileSidebarOpen = ref(false)
const sidebarCollapsed = ref(localStorage.getItem('opencompany.chat.sidebarCollapsed') === 'true')

const toggleSidebarCollapse = () => {
  sidebarCollapsed.value = !sidebarCollapsed.value
  localStorage.setItem('opencompany.chat.sidebarCollapsed', String(sidebarCollapsed.value))
}

const handleConversationSidebarToggle = () => {
  if (props.isMobile) {
    mobileSidebarOpen.value = true
    return
  }

  toggleSidebarCollapse()
}

const selectFromMobile = (channel: Channel) => {
  emit('selectChannel', channel)
  mobileSidebarOpen.value = false
}

const newAgentFromMobile = (agentId: string) => {
  emit('newAgentChat', agentId)
  mobileSidebarOpen.value = false
}

const createChannelFromMobile = () => {
  emit('createChannel')
  mobileSidebarOpen.value = false
}

const createDmFromMobile = () => {
  emit('createDm')
  mobileSidebarOpen.value = false
}
</script>
