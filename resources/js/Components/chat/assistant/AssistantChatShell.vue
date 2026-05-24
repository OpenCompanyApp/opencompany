<template>
  <div class="flex h-full min-h-0 bg-white dark:bg-neutral-950">
    <ConversationSidebar
      class="hidden md:flex"
      :channels="channels"
      :selected-channel="selectedChannel"
      :agents="agents"
      :current-user-id="currentUserId"
      @select="$emit('selectChannel', $event)"
      @new-agent-chat="$emit('newAgentChat', $event)"
      @open-channels="$emit('openChannels')"
    />

    <AssistantConversation
      :channel="selectedChannel"
      :messages="messages"
      :tasks="tasks"
      :approvals="approvals"
      :agents="agents"
      :current-user-id="currentUserId"
      :selected-agent-id="selectedAgentId"
      :approval-loading-id="approvalLoadingId"
      :approval-loading-action="approvalLoadingAction"
      @send="(content, attachments) => $emit('send', content, attachments)"
      @retry="$emit('retry', $event)"
      @edit="$emit('edit', $event)"
      @stop="$emit('stop')"
      @compact="$emit('compact')"
      @status="$emit('status')"
      @refresh="$emit('refresh')"
      @open-channels="$emit('openChannels')"
      @toggle-sidebar="mobileSidebarOpen = true"
      @update:selected-agent-id="$emit('update:selectedAgentId', $event)"
      @approval="(id, status) => $emit('approval', id, status)"
    />

    <Slideover v-if="isMobile" v-model:open="mobileSidebarOpen" side="left" size="sm" :show-close="false">
      <template #header>
        <div class="flex w-full items-center justify-between">
          <span class="font-semibold text-neutral-900 dark:text-white">Assistant history</span>
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
            @select="selectFromMobile"
            @new-agent-chat="newAgentFromMobile"
            @open-channels="openChannelsFromMobile"
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
import type { ComposerAttachment } from '@/Components/chat/assistant/PromptComposer.vue'
import type { AgentTask, ApprovalRequest, Channel, Message, User } from '@/types'

defineProps<{
  channels: Channel[]
  selectedChannel: Channel | null
  messages: Message[]
  tasks: AgentTask[]
  approvals: ApprovalRequest[]
  agents: User[]
  currentUserId: string
  selectedAgentId?: string
  isMobile: boolean
  approvalLoadingId?: string | null
  approvalLoadingAction?: false | 'approve' | 'reject'
}>()

const emit = defineEmits<{
  selectChannel: [channel: Channel]
  newAgentChat: [agentId: string]
  openChannels: []
  send: [content: string, attachments: ComposerAttachment[]]
  retry: [message: Message]
  edit: [message: Message]
  stop: []
  compact: []
  status: []
  refresh: []
  'update:selectedAgentId': [agentId: string]
  approval: [id: string, status: 'approved' | 'rejected']
}>()

const mobileSidebarOpen = ref(false)

const selectFromMobile = (channel: Channel) => {
  emit('selectChannel', channel)
  mobileSidebarOpen.value = false
}

const newAgentFromMobile = (agentId: string) => {
  emit('newAgentChat', agentId)
  mobileSidebarOpen.value = false
}

const openChannelsFromMobile = () => {
  emit('openChannels')
  mobileSidebarOpen.value = false
}
</script>
