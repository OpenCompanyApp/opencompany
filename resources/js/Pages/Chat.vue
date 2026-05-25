<template>
  <div class="h-full flex flex-col">
    <div class="flex-1 flex min-h-0">
      <AssistantChatShell
        class="flex-1"
        :channels="channelsData"
        :selected-channel="selectedChannel"
        :messages="channelMessages"
        :tasks="assistantTasks"
        :approvals="channelApprovals"
        :agents="agentsData"
        :current-user-id="currentUserId"
        :selected-agent-id="selectedAgentId"
        :is-mobile="isMobile"
        :is-assistant-channel="isAssistantChannel(selectedChannel)"
        :approval-loading-id="approvalLoadingId"
        :approval-loading-action="approvalLoadingAction"
        :status-panel-open="statusPanelOpen"
        :workspace-status="workspaceStatus"
        :workspace-status-loading="workspaceStatusLoading"
        :workspace-status-error="workspaceStatusError"
        :workspace-status-last-refreshed-at="workspaceStatusLastRefreshedAt"
        :composer-error="composerError"
        v-model:composer-draft="composerDraft"
        :composer-focus-request-key="composerFocusRequestKey"
        :empty-state-notice="emptyStateNotice"
        @select-channel="selectChannel"
        @new-agent-chat="startNewAssistantChat"
        @create-channel="showCreateChannelModal = true"
        @create-dm="showCreateDmModal = true"
        @send="handleUnifiedSend"
        @retry="handleRetryMessage"
        @stop="handleStopResponse"
        @compact="handleCompactCommand"
        @status="handleStatusCommand"
        @prefill="handleSuggestedPrompt"
        @refresh="refreshAssistantRuntime"
        @refresh-status="refreshWorkspaceStatusPanel"
        @close-status="statusPanelOpen = false"
        @update:selected-agent-id="selectedAgentId = $event"
        @approval="handleApprovalResponse"
      />
    </div>

    <!-- Add Member Modal -->
    <ChatAddMemberModal
      v-model:open="showAddMemberModal"
      :channel="selectedChannel"
      @members-added="handleMembersAdded"
    />

    <!-- Create Channel Modal -->
    <ChatCreateChannelModal
      v-model:open="showCreateChannelModal"
      @channel-created="handleChannelCreated"
    />

    <!-- Create DM Modal -->
    <ChatCreateDmModal
      v-model:open="showCreateDmModal"
      @dm-created="handleDmCreated"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import type { AgentTask, ApprovalRequest, Channel, Message, User } from '@/types'
import ChatAddMemberModal from '@/Components/chat/AddMemberModal.vue'
import ChatCreateChannelModal from '@/Components/chat/CreateChannelModal.vue'
import ChatCreateDmModal from '@/Components/chat/CreateDmModal.vue'
import AssistantChatShell from '@/Components/chat/assistant/AssistantChatShell.vue'
import type { ComposerAttachment } from '@/Components/chat/assistant/PromptComposer.vue'
import type { WorkspaceStatus } from '@/Components/chat/assistant/AssistantStatusPanel.vue'
import { useApi } from '@/composables/useApi'
import { useRealtime } from '@/composables/useRealtime'
import { useTypingIndicator } from '@/composables/useTypingIndicator'
import { useIsMobile } from '@/composables/useMediaQuery'
import { useWorkspace } from '@/composables/useWorkspace'

const { memberUrl } = useWorkspace()

const page = usePage()
const currentUser = computed(() => (page.props.auth as any)?.user)
const currentUserId = computed(() => currentUser.value?.id ?? '')
const { fetchChannels, fetchMessages, sendMessage, markChannelRead, addMessageReaction, fetchMessageThread, removeChannelMember, pinMessage, fetchPinnedMessages, sendTypingIndicator, uploadMessageAttachment, compactChannel, fetchWorkspaceStatus, fetchDm, fetchAgents, fetchAgentTasks, fetchApprovals, respondToApproval, cancelAgentTask } = useApi()
const isMobile = useIsMobile()

interface Thread {
  parentMessage: Message
  replies: Message[]
}

interface CompactChannelResponse {
  message?: string
  results?: Array<{
    agent: string
    messages_summarized: number
    tokens_before: number
    tokens_after: number
  }>
}

const activeThread = ref<Thread | null>(null)
const showAddMemberModal = ref(false)
const showCreateChannelModal = ref(false)
const showCreateDmModal = ref(false)
const showChannelInfo = ref(false)
const selectedAgentId = ref<string>('')
const draftingNewAssistantChat = ref(false)
const assistantTasks = ref<AgentTask[]>([])
const approvals = ref<ApprovalRequest[]>([])
const approvalLoadingId = ref<string | null>(null)
const approvalLoadingAction = ref<false | 'approve' | 'reject'>(false)
const statusPanelOpen = ref(false)
const workspaceStatus = ref<WorkspaceStatus | null>(null)
const workspaceStatusLoading = ref(false)
const workspaceStatusError = ref<string | null>(null)
const workspaceStatusLastRefreshedAt = ref<Date | null>(null)
const composerError = ref<string | null>(null)
const composerDraft = ref('')
const composerFocusRequestKey = ref(0)
const invalidAgentSelection = ref<string | null>(null)
const agentsLoaded = ref(false)
let runtimePoll: number | null = null

// Channels data
const channels = ref<Channel[]>([])
const refreshChannels = async () => {
  const result = fetchChannels()
  await result.promise
  channels.value = result.data.value ?? []
}

const channelsData = computed<Channel[]>(() =>
  (channels.value ?? []).map(c => {
    const mapped = { ...c } as any

    // Normalize DM names
    if (mapped.type === 'dm' && mapped.members?.length) {
      const other = mapped.members.find((m: any) => m.id !== currentUserId.value) ?? mapped.members[0]
      mapped.name = other?.name ?? mapped.name
    }

    // Normalize latest_message (snake_case from API) to latestMessage
    if (mapped.latest_message) {
      mapped.latestMessage = mapped.latest_message
      mapped.lastMessageAt = mapped.latest_message.timestamp
      delete mapped.latest_message
    }

    return mapped as Channel
  })
)

// Agent data powers the assistant-first chat shell. Existing DMs can reference
// agents that are not returned by the lightweight agent endpoint, so selectors
// merge both sources before rendering labels or selected values.
const agents = ref<User[]>([])
const refreshAgents = async () => {
  const result = fetchAgents()
  await result.promise
  agents.value = result.data.value ?? []
  agentsLoaded.value = true
  if (!selectedAgentId.value && agents.value.length > 0) {
    selectedAgentId.value = agents.value[0].id
  }
}

const agentsData = computed<User[]>(() => {
  const byId = new Map<string, User>()

  for (const agent of agents.value) {
    byId.set(agent.id, agent)
  }

  for (const channel of channelsData.value) {
    for (const member of channel.members ?? []) {
      if (member.type === 'agent') {
        byId.set(member.id, member)
      }
    }
  }

  return Array.from(byId.values())
})

const isAssistantChannel = (channel: Channel | null | undefined): channel is Channel =>
  Boolean(channel?.type === 'dm' && channel.members?.some(member => member.type === 'agent'))

const isWorkspaceAgentId = (agentId: string | null | undefined): agentId is string =>
  Boolean(agentId && agents.value.some(agent => agent.id === agentId))

const clearAgentQueryParam = () => {
  const url = new URL(window.location.href)
  if (!url.searchParams.has('agent')) return

  url.searchParams.delete('agent')
  window.history.replaceState(window.history.state, '', `${url.pathname}${url.search}${url.hash}`)
}

const markInvalidAgentSelection = (agentId: string) => {
  invalidAgentSelection.value = agentId
  composerError.value = null
  selectedAgentId.value = ''
  selectedChannel.value = null
  draftingNewAssistantChat.value = true
  clearAgentQueryParam()
}

const emptyStateNotice = computed(() =>
  invalidAgentSelection.value ? 'Agent not available in this workspace.' : null
)

const channelFromDmResponse = (response: any): Channel | undefined => {
  const channelId = response?.data?.channelId ?? response?.data?.channel_id
  if (!channelId) return undefined

  return channelsData.value.find(channel => channel.id === channelId)
}

const findDmChannelForUser = (userId: string): Channel | undefined =>
  channelsData.value.find(channel =>
    channel.type === 'dm' && channel.members?.some(member => member.id === userId)
  )

const resolveDmChannel = async (userId: string): Promise<Channel | undefined> => {
  const response = await fetchDm(userId)
  await refreshChannels()

  // Trust the API's canonical channel id first. Membership-based lookup is only
  // a fallback for older response shapes and prevents orphan DMs from winning
  // when a cached duplicate happens to appear earlier in the sidebar payload.
  return channelFromDmResponse(response) ?? findDmChannelForUser(userId)
}

const channelApprovals = computed(() => {
  const channelId = selectedChannel.value?.id
  if (!channelId) return []

  return approvals.value.filter(approval => {
    const raw = approval as any
    return raw.channel_id === channelId || raw.channelId === channelId
  })
})

// Selected channel
const selectedChannel = ref<Channel | null>(null)

// Initialize with first channel or from query
watch([channelsData, agentsLoaded], async ([newChannels, loaded]) => {
  if (!loaded) return

  const url = new URL(window.location.href)
  const agentUserId = url.searchParams.get('agent')

  if (agentUserId && !isWorkspaceAgentId(agentUserId)) {
    markInvalidAgentSelection(agentUserId)
    return
  }

  if (!selectedChannel.value && !draftingNewAssistantChat.value && newChannels.length > 0) {
    const channelId = url.searchParams.get('channel')
    const dmUserId = url.searchParams.get('dm')

    let found: Channel | undefined

    // Check for dm parameter first - find DM channel with this user
    if (agentUserId) {
      selectedAgentId.value = agentUserId
      try {
        found = await resolveDmChannel(agentUserId)
      } catch (e) {
        console.error('Failed to create agent DM:', e)
      }
    }

    if (!found && dmUserId) {
      try {
        found = await resolveDmChannel(dmUserId)
      } catch (e) {
        console.error('Failed to create DM:', e)
      }
    }

    // Then check for channel parameter
    if (!found && channelId) {
      found = newChannels.find(c => c.id === channelId)
    }

    // Prefer an assistant conversation on first load, but keep channels and
    // human DMs in the same surface instead of falling back to a second UI.
    selectedChannel.value = found ?? newChannels.find(isAssistantChannel) ?? newChannels[0] ?? null
  }
}, { immediate: true })

// Handle channel and dm query param changes
watch(() => {
  const url = new URL(window.location.href)
  return {
    channelId: url.searchParams.get('channel'),
    dmUserId: url.searchParams.get('dm'),
    agentUserId: url.searchParams.get('agent')
  }
}, async ({ channelId, dmUserId, agentUserId }) => {
  if (channelsData.value.length === 0 || !agentsLoaded.value) return

  if (agentUserId) {
    if (!isWorkspaceAgentId(agentUserId)) {
      markInvalidAgentSelection(agentUserId)
      return
    }

    await openAgentChat(agentUserId)
    return
  }

  // Handle dm parameter - find DM channel with this user
  if (dmUserId) {
    let found: Channel | undefined
    try {
      found = await resolveDmChannel(dmUserId)
    } catch (e) {
      console.error('Failed to create DM:', e)
    }
    if (found) {
      selectedChannel.value = found
    }
    return
  }

  // Handle channel parameter
  if (channelId) {
    const found = channelsData.value.find(c => c.id === channelId)
    if (found) {
      selectedChannel.value = found
    }
  }
}, { deep: true })

// Messages data
const messages = ref<Message[]>([])
const refreshMessages = async () => {
  if (selectedChannel.value) {
    const result = fetchMessages(selectedChannel.value.id)
    await result.promise
    messages.value = result.data.value ?? []
  }
}

const refreshAssistantRuntime = async () => {
  if (!selectedChannel.value || !isAssistantChannel(selectedChannel.value)) {
    assistantTasks.value = []
    approvals.value = []
    return
  }

  const [tasksResult, approvalsResult] = [
    fetchAgentTasks({ channelId: selectedChannel.value.id, source: 'chat', perPage: 8 }),
    fetchApprovals(),
  ]
  await Promise.all([tasksResult.promise, approvalsResult.promise])
  assistantTasks.value = tasksResult.data.value?.data ?? []
  approvals.value = approvalsResult.data.value ?? []
}

// Fetch pinned messages for selected channel
const pinnedMessagesData = ref<Message[]>([])

// Typing indicator state
const channelIdRef = computed(() => selectedChannel.value?.id ?? null)
const {
  typingUsers,
  typingText,
  startTyping,
  stopTyping,
  init: initTyping,
  cleanup: cleanupTyping,
} = useTypingIndicator(channelIdRef, currentUserId.value)

// Convert typing users to User objects
const typingUsersData = computed(() => {
  return typingUsers.value.map(t => ({
    id: t.userId,
    name: t.userName,
    type: 'human' as const,
  }))
})

const handleTyping = () => {
  startTyping()
}

const refreshPinnedMessages = async () => {
  if (selectedChannel.value) {
    const result = fetchPinnedMessages(selectedChannel.value.id)
    await result.promise
    pinnedMessagesData.value = result.data.value ?? []
  }
}

// Refresh messages when channel changes
watch(selectedChannel, async (channel) => {
  if (channel) {
    draftingNewAssistantChat.value = false
    await refreshMessages()
    await refreshPinnedMessages()
    await markChannelRead(channel.id)
    await refreshChannels()
    if (isAssistantChannel(channel)) {
      const agent = channel.members?.find(member => member.type === 'agent')
      if (agent) selectedAgentId.value = agent.id
      await refreshAssistantRuntime()
    } else {
      assistantTasks.value = []
      approvals.value = []
    }
  } else {
    messages.value = []
    pinnedMessagesData.value = []
    assistantTasks.value = []
    approvals.value = []
  }
})

const channelMessages = computed<Message[]>(() => {
  if (!selectedChannel.value) return []
  // Messages are already filtered by channelId on the API side
  // Use channel_id (snake_case) since that's what Laravel returns
  return messages.value.filter(m => (m as any).channel_id === selectedChannel.value?.id || m.channelId === selectedChannel.value?.id)
})

// Get first few members as viewers
const selectChannel = async (channel: Channel) => {
  draftingNewAssistantChat.value = false
  composerError.value = null
  invalidAgentSelection.value = null
  selectedChannel.value = channel
}

const openAgentChat = async (agentId: string) => {
  if (!isWorkspaceAgentId(agentId)) {
    markInvalidAgentSelection(agentId)
    return
  }

  draftingNewAssistantChat.value = false
  selectedAgentId.value = agentId
  composerError.value = null
  invalidAgentSelection.value = null
  let channel: Channel | undefined

  try {
    channel = await resolveDmChannel(agentId)
  } catch (e) {
    console.error('Failed to open agent chat:', e)
    composerError.value = 'Could not open that assistant chat. Try again.'
  }

  selectedChannel.value = channel ?? null
}

const startNewAssistantChat = (agentId: string) => {
  // Keep agent DMs as the durable backing conversation, but make the plus
  // button visibly reset to a draft state instead of reselecting the current DM.
  draftingNewAssistantChat.value = true
  selectedAgentId.value = agentId
  invalidAgentSelection.value = null
  selectedChannel.value = null
  composerFocusRequestKey.value++
}

const handleSuggestedPrompt = (prompt: string) => {
  composerDraft.value = prompt
  composerError.value = null
  composerFocusRequestKey.value++
}

interface MessageAttachment {
  id: string
  file: File
  name: string
  type: string
  size: number
  preview?: string
  uploading?: boolean
  progress?: number
}

const handleSendMessage = async (content: string, attachments?: MessageAttachment[]) => {
  if (!selectedChannel.value) {
    composerError.value = 'Choose a conversation before sending.'
    return
  }

  composerError.value = null

  // Intercept /compact command
  if (content.trim() === '/compact') {
    try {
      const { data } = await compactChannel(selectedChannel.value.id)
      const compactResult = data as CompactChannelResponse
      const resultText = compactResult.results
        ? compactResult.results?.map(r =>
            `**${r.agent}**: ${r.messages_summarized} messages compacted (${r.tokens_before} → ${r.tokens_after} tokens)`
          ).join('\n')
        : compactResult.message
      messages.value = [...messages.value, {
        id: `system-${Date.now()}`,
        content: `🗜️ ${resultText}`,
        channelId: selectedChannel.value.id,
        channel_id: selectedChannel.value.id,
        authorId: 'system',
        author: { id: 'system', name: 'System', type: 'system' },
        timestamp: new Date().toISOString(),
        reactions: [],
        attachments: [],
      } as any]
    } catch (error: any) {
      const msg = error.response?.data?.message || 'Compaction failed'
      messages.value = [...messages.value, {
        id: `system-${Date.now()}`,
        content: `⚠️ ${msg}`,
        channelId: selectedChannel.value.id,
        channel_id: selectedChannel.value.id,
        authorId: 'system',
        author: { id: 'system', name: 'System', type: 'system' },
        timestamp: new Date().toISOString(),
        reactions: [],
        attachments: [],
      } as any]
    }
    return
  }

  // Intercept /status command
  if (content.trim() === '/status') {
    await openStatusPanel()
    return
  }

  let attachmentIds: string[] = []

  // Upload attachments first if any
  if (attachments && attachments.length > 0) {
    try {
      const uploadPromises = attachments.map(async (attachment) => {
        const result = await uploadMessageAttachment(
          attachment.file,
          selectedChannel.value!.id,
          currentUserId.value
        )
        return result.id
      })
      attachmentIds = await Promise.all(uploadPromises)
    } catch (error) {
      console.error('Failed to upload attachments:', error)
      composerError.value = 'Attachment upload failed. Try again.'
      return
    }
  }

  // Optimistic: show message immediately
  const tempId = `temp-${Date.now()}`
  const optimisticMsg = {
    id: tempId,
    content,
    channelId: selectedChannel.value.id,
    channel_id: selectedChannel.value.id,
    authorId: currentUserId.value,
    author: { id: currentUserId.value, name: currentUser.value?.name ?? 'You', type: 'human' },
    timestamp: new Date(),
    reactions: [],
    attachments: [],
  } as unknown as Message
  messages.value = [...messages.value, optimisticMsg]

  // Send in background, then reconcile
  try {
    await sendMessage({
      content,
      channelId: selectedChannel.value.id,
      authorId: currentUserId.value,
      attachmentIds: attachmentIds.length > 0 ? attachmentIds : undefined,
    })
    await refreshMessages()
  } catch (error) {
    messages.value = messages.value.filter(m => m.id !== tempId)
    console.error('Failed to send message:', error)
    composerError.value = 'Message failed to send. Try again.'
  }
}

const handleUnifiedSend = async (content: string, attachments?: ComposerAttachment[]) => {
  if (!selectedChannel.value && selectedAgentId.value) {
    await openAgentChat(selectedAgentId.value)
  }

  if (!selectedChannel.value) {
    composerError.value = 'Could not open an assistant chat for this message.'
    return
  }

  await handleSendMessage(content, attachments as MessageAttachment[] | undefined)
  await refreshAssistantRuntime()
}

const handleCompactCommand = () => handleUnifiedSend('/compact', [])
const handleStatusCommand = () => {
  if (statusPanelOpen.value) {
    statusPanelOpen.value = false
    return
  }

  openStatusPanel()
}

const openStatusPanel = async () => {
  statusPanelOpen.value = true
  await refreshWorkspaceStatusPanel()
}

const handleGlobalKeydown = (event: KeyboardEvent) => {
  if (event.key === 'Escape' && statusPanelOpen.value) {
    statusPanelOpen.value = false
  }
}

const refreshWorkspaceStatusPanel = async () => {
  workspaceStatusLoading.value = true
  workspaceStatusError.value = null

  try {
    const { data } = await fetchWorkspaceStatus({
      channelId: selectedChannel.value?.id,
      agentId: selectedChannel.value?.members?.find(member => member.type === 'agent')?.id ?? selectedAgentId.value,
    })
    workspaceStatus.value = data as WorkspaceStatus
    workspaceStatusLastRefreshedAt.value = new Date()
  } catch (error: any) {
    workspaceStatusError.value = error.response?.data?.message || error.message || 'Failed to fetch workspace status.'
  } finally {
    workspaceStatusLoading.value = false
  }
}

const handleStopResponse = async () => {
  const activeTask = assistantTasks.value.find(task => ['pending', 'active', 'paused'].includes(task.status))
  if (!activeTask) return

  try {
    await cancelAgentTask(activeTask.id)
    await refreshAssistantRuntime()
  } catch (error) {
    console.error('Failed to stop response:', error)
  }
}

const handleRetryMessage = async (message: Message) => {
  await handleUnifiedSend(`Please retry this response and improve it:\n\n${message.content}`, [])
}

const handleApprovalResponse = async (id: string, status: 'approved' | 'rejected') => {
  approvalLoadingId.value = id
  approvalLoadingAction.value = status === 'approved' ? 'approve' : 'reject'
  try {
    await respondToApproval(id, status)
    await refreshAssistantRuntime()
    await refreshMessages()
  } catch (error) {
    console.error('Failed to respond to approval:', error)
  } finally {
    approvalLoadingId.value = null
    approvalLoadingAction.value = false
  }
}

const handleReaction = async (message: Message, emoji: string) => {
  try {
    await addMessageReaction(message.id, { emoji, userId: currentUserId.value })
    await refreshMessages()
  } catch (error) {
    console.error('Failed to add reaction:', error)
  }
}

const handleOpenThread = async (message: Message) => {
  try {
    const result = fetchMessageThread(message.id)
    await result.promise
    const threadData = result.data.value as any
    if (threadData) {
      activeThread.value = {
        parentMessage: threadData.parentMessage as Message,
        replies: threadData.replies as Message[],
      }
    }
  } catch (error) {
    console.error('Failed to load thread:', error)
  }
}

const handleThreadReply = async (parentMessageId: string, content: string) => {
  if (!selectedChannel.value) return

  try {
    await sendMessage({
      content,
      channelId: selectedChannel.value.id,
      authorId: currentUserId.value,
      replyToId: parentMessageId,
    })

    // Refresh the thread to show the new reply
    await handleOpenThread({ id: parentMessageId } as Message)
    await refreshMessages()
  } catch (error) {
    console.error('Failed to send thread reply:', error)
  }
}

// Member management
const handleMembersAdded = async () => {
  await refreshChannels()
  // Update selected channel with new members
  if (selectedChannel.value) {
    const updated = channelsData.value.find(c => c.id === selectedChannel.value?.id)
    if (updated) {
      selectedChannel.value = updated
    }
  }
}

const handleMemberRemove = async (member: User) => {
  if (!selectedChannel.value) return

  try {
    await removeChannelMember(selectedChannel.value.id, member.id)
    await refreshChannels()
    // Update selected channel
    const updated = channelsData.value.find(c => c.id === selectedChannel.value?.id)
    if (updated) {
      selectedChannel.value = updated
    }
  } catch (error) {
    console.error('Failed to remove member:', error)
  }
}

const handleMemberClick = (member: User) => {
  // Navigate to user profile
  router.visit(memberUrl(member))
}

const handleMemberMessage = async (member: User) => {
  try {
    const newDm = await resolveDmChannel(member.id)
    if (newDm) {
      composerError.value = null
      selectedChannel.value = newDm
    }
  } catch (e) {
    console.error('Failed to open DM:', e)
    composerError.value = 'Could not open that direct message. Try again.'
  }
}

// Channel creation
const handleChannelCreated = async (channelId: string) => {
  await refreshChannels()
  // Select the newly created channel
  const newChannel = channelsData.value.find(c => c.id === channelId)
  if (newChannel) {
    selectedChannel.value = newChannel
  }
}

// DM creation
const handleDmCreated = async (channelId: string) => {
  await refreshChannels()
  const newDm = channelsData.value.find(c => c.id === channelId)
  if (newDm) {
    selectedChannel.value = newDm
  }
}

// Message pinning
const handlePinMessage = async (message: Message) => {
  try {
    await pinMessage(message.id, currentUserId.value)
    await refreshMessages()
    await refreshPinnedMessages()
  } catch (error) {
    console.error('Failed to pin/unpin message:', error)
  }
}

// Real-time updates — subscribe to Echo private chat channel
const { on, emit: emitEvent, privateChannel, leaveChannel } = useRealtime()

// Bridge Echo channel events into the internal event bus
watch(selectedChannel, (newChannel, oldChannel) => {
  if (oldChannel) leaveChannel(`chat.${oldChannel.id}`)
  if (newChannel) {
    console.log('[Realtime] Subscribing to chat.' + newChannel.id)
    const ch = privateChannel(`chat.${newChannel.id}`)
    ch?.listen('.MessageSent', (data: { message: Message }) => {
      console.log('[Realtime] MessageSent on channel', newChannel.id, data)
      emitEvent('message:new', { channelId: newChannel.id, message: data.message })
    })
    ch?.listen('.text_start', (data: StreamEventPayload) => {
      emitEvent('message:stream:start', { channelId: newChannel.id, event: data })
    })
    ch?.listen('.text_delta', (data: StreamEventPayload) => {
      emitEvent('message:stream:delta', { channelId: newChannel.id, event: data })
    })
    ch?.listen('.text_end', (data: StreamEventPayload) => {
      emitEvent('message:stream:end', { channelId: newChannel.id, event: data })
    })
    ch?.listen('.stream_end', (data: StreamEventPayload) => {
      emitEvent('message:stream:end', { channelId: newChannel.id, event: data })
    })
    ch?.listen('.stream_failed', (data: StreamEventPayload) => {
      emitEvent('message:stream:error', { channelId: newChannel.id, event: data })
    })
  }
}, { immediate: true })

let unsubscribeMessage: (() => void) | null = null
let unsubscribeStreamStart: (() => void) | null = null
let unsubscribeStreamDelta: (() => void) | null = null
let unsubscribeStreamEnd: (() => void) | null = null
let unsubscribeStreamError: (() => void) | null = null
let unsubscribeReactionAdded: (() => void) | null = null
let unsubscribeReactionRemoved: (() => void) | null = null
let unsubscribePinned: (() => void) | null = null
let unsubscribeUnpinned: (() => void) | null = null

interface StreamEventPayload {
  invocation_id?: string
  message_id?: string
  delta?: string
  message?: string
}

const streamMessageId = (event: StreamEventPayload) =>
  `stream-${event.message_id ?? event.invocation_id ?? 'response'}`

const streamAuthor = () =>
  selectedChannel.value?.members?.find(member => member.type === 'agent')
  ?? agentsData.value[0]
  ?? { id: 'assistant', name: 'Assistant', type: 'agent' as const }

const ensureStreamingMessage = (event: StreamEventPayload) => {
  if (!selectedChannel.value) return null

  const id = streamMessageId(event)
  let message = messages.value.find(existing => existing.id === id)

  if (!message) {
    message = {
      id,
      content: '',
      channelId: selectedChannel.value.id,
      channel_id: selectedChannel.value.id,
      authorId: streamAuthor().id,
      author: streamAuthor(),
      timestamp: new Date().toISOString(),
      reactions: [],
      attachments: [],
      streaming: true,
    } as any
    messages.value = [...messages.value, message]
  }

  return message as any
}

const handleStreamDelta = ({ channelId, event }: { channelId: string; event: StreamEventPayload }) => {
  if (channelId !== selectedChannel.value?.id) return

  const message = ensureStreamingMessage(event)
  if (!message) return

  message.content = `${message.content ?? ''}${event.delta ?? ''}`
  message.streaming = true
}

const handleStreamEnd = ({ channelId, event }: { channelId: string; event: StreamEventPayload }) => {
  if (channelId !== selectedChannel.value?.id) return

  const message = ensureStreamingMessage(event)
  if (message) message.streaming = false
}

const handleStreamError = ({ channelId, event }: { channelId: string; event: StreamEventPayload }) => {
  if (channelId !== selectedChannel.value?.id) return

  const message = ensureStreamingMessage(event)
  if (!message) return

  message.content = event.message ?? 'The response stream failed.'
  message.streaming = false
}

onMounted(async () => {
  // Fetch initial data
  await Promise.all([refreshChannels(), refreshAgents()])
  window.addEventListener('keydown', handleGlobalKeydown)

  // Initialize typing indicator
  initTyping()

  runtimePoll = window.setInterval(() => {
    if (selectedChannel.value) {
      refreshAssistantRuntime()
      if (assistantTasks.value.some(task => ['pending', 'active', 'paused'].includes(task.status))) {
        refreshMessages()
      }
    }
  }, 3000)

  // Listen for new messages
  unsubscribeMessage = on('message:new', (data: { channelId: string; message: Message }) => {
    // If it's for the current channel, refresh messages
    if (data.channelId === selectedChannel.value?.id) {
      refreshMessages()
      refreshAssistantRuntime()
    }
    // Refresh channels to update unread counts
    refreshChannels()
  })

  unsubscribeStreamStart = on('message:stream:start', (data: { channelId: string; event: StreamEventPayload }) => {
    if (data.channelId === selectedChannel.value?.id) ensureStreamingMessage(data.event)
  })

  unsubscribeStreamDelta = on('message:stream:delta', handleStreamDelta)
  unsubscribeStreamEnd = on('message:stream:end', handleStreamEnd)
  unsubscribeStreamError = on('message:stream:error', handleStreamError)

  // Listen for reaction events
  unsubscribeReactionAdded = on('message:reaction:added', () => {
    refreshMessages()
  })

  unsubscribeReactionRemoved = on('message:reaction:removed', () => {
    refreshMessages()
  })

  // Listen for pin events
  unsubscribePinned = on('message:pinned', (data: { channelId: string }) => {
    if (data.channelId === selectedChannel.value?.id) {
      refreshMessages()
      refreshPinnedMessages()
    }
  })

  unsubscribeUnpinned = on('message:unpinned', (data: { channelId: string }) => {
    if (data.channelId === selectedChannel.value?.id) {
      refreshMessages()
      refreshPinnedMessages()
    }
  })
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleGlobalKeydown)
  unsubscribeMessage?.()
  unsubscribeStreamStart?.()
  unsubscribeStreamDelta?.()
  unsubscribeStreamEnd?.()
  unsubscribeStreamError?.()
  unsubscribeReactionAdded?.()
  unsubscribeReactionRemoved?.()
  unsubscribePinned?.()
  unsubscribeUnpinned?.()
  cleanupTyping()
  if (runtimePoll) {
    window.clearInterval(runtimePoll)
    runtimePoll = null
  }
})
</script>
