<template>
  <div class="h-full flex flex-col">
    <!-- Mobile Toolbar -->
    <div class="md:hidden flex items-center gap-2 px-3 py-2 border-b border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 shrink-0">
      <button
        type="button"
        class="p-2 -ml-1 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors"
        @click="showMobileChannelList = true"
      >
        <Icon name="ph:list" class="w-5 h-5 text-neutral-600 dark:text-neutral-300" />
      </button>
      <div v-if="selectedChannel" class="flex-1 flex items-center gap-2 min-w-0">
        <Icon :name="channelIcon" class="w-4 h-4 text-neutral-500 dark:text-neutral-400 shrink-0" />
        <span class="font-medium text-neutral-900 dark:text-white truncate">{{ selectedChannel.name }}</span>
      </div>
      <div v-else class="flex-1 text-sm text-neutral-500 dark:text-neutral-400">Select a channel</div>
      <button
        v-if="selectedChannel"
        type="button"
        class="p-2 -mr-1 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors"
        @click="showChannelInfo = true"
      >
        <Icon name="ph:info" class="w-5 h-5 text-neutral-600 dark:text-neutral-300" />
      </button>
    </div>

    <!-- Main Content -->
    <div class="flex-1 flex min-h-0">
      <!-- Desktop Channel List Sidebar -->
      <ChatChannelList
        class="hidden md:flex"
        :channels="channelsData"
        :selected-channel="selectedChannel"
        @select="selectChannel"
        @create="showCreateChannelModal = true"
        @create-dm="showCreateDmModal = true"
        @create-external="showCreateExternalModal = true"
      />

      <!-- Main Chat Area -->
      <ChatArea
        v-if="selectedChannel"
        :channel="selectedChannel"
        :messages="channelMessages"
        :pinned-messages="pinnedMessagesData"
        :typing-users="typingUsersData"
        :current-user-id="currentUserId"
        :active-thread="activeThread"
        class="flex-1"
        @send="handleSendMessage"
        @react="handleReaction"
        @open-thread="handleOpenThread"
        @close-thread="activeThread = null"
        @thread-reply="handleThreadReply"
        @pin="handlePinMessage"
        @typing="handleTyping"
        @toggle-info="showChannelInfo = !showChannelInfo"
      />

      <!-- Empty State when no channel selected -->
      <div v-else class="flex-1 flex items-center justify-center bg-white dark:bg-neutral-900">
        <div class="text-center">
          <Icon name="ph:chat-circle-dots" class="w-16 h-16 text-neutral-300 dark:text-neutral-600 mx-auto mb-4" />
          <h3 class="text-lg font-semibold text-neutral-900 dark:text-white mb-1">No channel selected</h3>
          <p class="text-sm text-neutral-500 dark:text-neutral-400">Select a channel to start chatting</p>
        </div>
      </div>

      <!-- Desktop Channel Info Sidebar -->
      <ChatChannelInfo
        v-if="selectedChannel && showChannelInfo"
        class="hidden md:flex"
        :channel="selectedChannel"
        :viewers="channelViewers"
        @close="showChannelInfo = false"
        @add-member="showAddMemberModal = true"
        @member-remove="handleMemberRemove"
        @member-click="handleMemberClick"
        @member-message="handleMemberMessage"
      />
    </div>

    <!-- Mobile Channel List Slideover -->
    <Slideover v-if="isMobile" v-model:open="showMobileChannelList" side="left" size="sm" :show-close="false">
      <template #header>
        <div class="flex items-center justify-between w-full">
          <span class="font-semibold text-neutral-900 dark:text-white">Channels</span>
          <button
            type="button"
            class="p-2 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors"
            @click="showMobileChannelList = false"
          >
            <Icon name="ph:x" class="w-5 h-5 text-neutral-500 dark:text-neutral-300" />
          </button>
        </div>
      </template>
      <template #body>
        <div class="-mx-6 -my-4 h-full">
          <ChatChannelList
            class="h-full border-r-0 w-full"
            :channels="channelsData"
            :selected-channel="selectedChannel"
            :show-header="false"
            @select="(channel) => { selectChannel(channel); showMobileChannelList = false }"
            @create="showCreateChannelModal = true; showMobileChannelList = false"
            @create-dm="showCreateDmModal = true; showMobileChannelList = false"
            @create-external="showCreateExternalModal = true; showMobileChannelList = false"
          />
        </div>
      </template>
    </Slideover>

    <!-- Mobile Channel Info Slideover -->
    <Slideover v-if="isMobile" v-model:open="showChannelInfo" side="right" size="md" :show-close="false">
      <template #header>
        <div class="flex items-center justify-between w-full">
          <span class="font-semibold text-neutral-900 dark:text-white">Channel Details</span>
          <button
            type="button"
            class="p-2 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors"
            @click="showChannelInfo = false"
          >
            <Icon name="ph:x" class="w-5 h-5 text-neutral-500 dark:text-neutral-300" />
          </button>
        </div>
      </template>
      <template #body>
        <div class="-mx-6 -my-4 h-full">
          <ChatChannelInfo
            v-if="selectedChannel"
            class="h-full border-l-0 w-full"
            :channel="selectedChannel"
            :viewers="channelViewers"
            @close="showChannelInfo = false"
            @add-member="showAddMemberModal = true"
            @member-remove="handleMemberRemove"
            @member-click="handleMemberClick"
            @member-message="handleMemberMessage"
          />
        </div>
      </template>
    </Slideover>

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
import type { Channel, Message, User } from '@/types'
import ChatChannelList from '@/Components/chat/ChannelList.vue'
import ChatArea from '@/Components/chat/Area.vue'
import ChatChannelInfo from '@/Components/chat/ChannelInfo.vue'
import ChatAddMemberModal from '@/Components/chat/AddMemberModal.vue'
import ChatCreateChannelModal from '@/Components/chat/CreateChannelModal.vue'
import ChatCreateDmModal from '@/Components/chat/CreateDmModal.vue'
import Slideover from '@/Components/shared/Slideover.vue'
import Icon from '@/Components/shared/Icon.vue'
import { useApi } from '@/composables/useApi'
import { useRealtime } from '@/composables/useRealtime'
import { useTypingIndicator } from '@/composables/useTypingIndicator'
import { useIsMobile } from '@/composables/useMediaQuery'
import { useWorkspace } from '@/composables/useWorkspace'

const { workspacePath } = useWorkspace()

const page = usePage()
const currentUser = computed(() => (page.props.auth as any)?.user)
const currentUserId = computed(() => currentUser.value?.id ?? '')
const { fetchChannels, fetchMessages, sendMessage, markChannelRead, addMessageReaction, fetchMessageThread, removeChannelMember, pinMessage, fetchPinnedMessages, sendTypingIndicator, uploadMessageAttachment, compactChannel, fetchWorkspaceStatus, fetchDm } = useApi()
const isMobile = useIsMobile()

interface Thread {
  parentMessage: Message
  replies: Message[]
}

const activeThread = ref<Thread | null>(null)
const showAddMemberModal = ref(false)
const showCreateChannelModal = ref(false)
const showCreateDmModal = ref(false)
const showCreateExternalModal = ref(false)
const showChannelInfo = ref(false)
const showMobileChannelList = ref(false)

// Channel icon for mobile toolbar
const channelIcon = computed(() => {
  if (!selectedChannel.value) return 'ph:hash'
  if (selectedChannel.value.type === 'dm') return 'ph:chat-circle'
  if (selectedChannel.value.private) return 'ph:lock-simple'
  return 'ph:hash'
})

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

// Selected channel
const selectedChannel = ref<Channel | null>(null)

// Initialize with first channel or from query
watch(channelsData, (newChannels) => {
  if (!selectedChannel.value && newChannels.length > 0) {
    const url = new URL(window.location.href)
    const channelId = url.searchParams.get('channel')
    const dmUserId = url.searchParams.get('dm')

    let found: Channel | undefined

    // Check for dm parameter first - find DM channel with this user
    if (dmUserId) {
      found = newChannels.find(c =>
        c.type === 'dm' && c.members?.some(m => m.id === dmUserId)
      )
    }

    // Then check for channel parameter
    if (!found && channelId) {
      found = newChannels.find(c => c.id === channelId)
    }

    // Fallback to first channel
    selectedChannel.value = found ?? newChannels[0]
  }
}, { immediate: true })

// Handle channel and dm query param changes
watch(() => {
  const url = new URL(window.location.href)
  return {
    channelId: url.searchParams.get('channel'),
    dmUserId: url.searchParams.get('dm')
  }
}, ({ channelId, dmUserId }) => {
  if (channelsData.value.length === 0) return

  // Handle dm parameter - find DM channel with this user
  if (dmUserId) {
    const found = channelsData.value.find(c =>
      c.type === 'dm' && c.members?.some(m => m.id === dmUserId)
    )
    if (found) selectedChannel.value = found
    return
  }

  // Handle channel parameter
  if (channelId) {
    const found = channelsData.value.find(c => c.id === channelId)
    if (found) selectedChannel.value = found
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
    await refreshMessages()
    await refreshPinnedMessages()
    await markChannelRead(channel.id)
    await refreshChannels()
  }
})

const channelMessages = computed<Message[]>(() => {
  if (!selectedChannel.value) return []
  // Messages are already filtered by channelId on the API side
  // Use channel_id (snake_case) since that's what Laravel returns
  return messages.value.filter(m => (m as any).channel_id === selectedChannel.value?.id || m.channelId === selectedChannel.value?.id)
})

// Get first few members as viewers
const channelViewers = computed<User[]>(() => {
  if (!selectedChannel.value) return []
  return selectedChannel.value.members?.slice(0, 3) ?? []
})

const selectChannel = async (channel: Channel) => {
  selectedChannel.value = channel
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
  if (!selectedChannel.value) return

  // Intercept /compact command
  if (content.trim() === '/compact') {
    try {
      const { data } = await compactChannel(selectedChannel.value.id)
      const resultText = data.results
        ? data.results.map((r: any) =>
            `**${r.agent}**: ${r.messages_summarized} messages compacted (${r.tokens_before} → ${r.tokens_after} tokens)`
          ).join('\n')
        : data.message
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
    try {
      const { data } = await fetchWorkspaceStatus()
      const statusIcon = (s: string) => s === 'working' ? '🟢' : s === 'idle' ? '🟡' : '⚫'
      const agentLines = data.agents.map((a: any) => {
        let line = `${statusIcon(a.status)} **${a.name}** — ${a.status}`
        if (a.current_task) line += ` · ${a.current_task}`
        return line
      }).join('\n')

      const text = [
        `**Workspace Status**\n`,
        `🤖 **Agents**: ${data.agents_online}/${data.agents_total} online`,
        agentLines,
        `\n📋 **Tasks**: ${data.tasks_active} active · ${data.tasks_today} completed today · ${data.tasks_completed} total`,
        `💬 **Messages**: ${data.messages_today} today · ${data.messages_total} total`,
      ].join('\n')

      messages.value = [...messages.value, {
        id: `system-${Date.now()}`,
        content: text,
        channelId: selectedChannel.value.id,
        channel_id: selectedChannel.value.id,
        authorId: 'system',
        author: { id: 'system', name: 'System', type: 'system' },
        timestamp: new Date().toISOString(),
        reactions: [],
        attachments: [],
      } as any]
    } catch (error: any) {
      const msg = error.response?.data?.message || 'Failed to fetch status'
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
    timestamp: new Date().toISOString(),
    reactions: [],
    attachments: [],
  } as Message
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
    if (result.data.value) {
      activeThread.value = {
        parentMessage: result.data.value.parentMessage as Message,
        replies: result.data.value.replies as Message[],
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
  router.visit(workspacePath(`/users/${member.id}`))
}

const handleMemberMessage = async (member: User) => {
  // Find existing DM channel with this member
  const existingDm = channelsData.value.find(c =>
    c.type === 'dm' && c.members?.some(m => m.id === member.id)
  )
  if (existingDm) {
    selectedChannel.value = existingDm
    return
  }
  // No existing DM — create one via API, refresh channels, then select
  try {
    const response = await fetchDm(member.id)
    await refreshChannels()
    const newDm = channelsData.value.find(c =>
      c.type === 'dm' && c.members?.some(m => m.id === member.id)
    )
    if (newDm) {
      selectedChannel.value = newDm
    }
  } catch (e) {
    console.error('Failed to open DM:', e)
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
  }
}, { immediate: true })

let unsubscribeMessage: (() => void) | null = null
let unsubscribeReactionAdded: (() => void) | null = null
let unsubscribeReactionRemoved: (() => void) | null = null
let unsubscribePinned: (() => void) | null = null
let unsubscribeUnpinned: (() => void) | null = null

onMounted(async () => {
  // Fetch initial data
  await refreshChannels()

  // Initialize typing indicator
  initTyping()

  // Listen for new messages
  unsubscribeMessage = on('message:new', (data: { channelId: string; message: Message }) => {
    // If it's for the current channel, refresh messages
    if (data.channelId === selectedChannel.value?.id) {
      refreshMessages()
    }
    // Refresh channels to update unread counts
    refreshChannels()
  })

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
  unsubscribeMessage?.()
  unsubscribeReactionAdded?.()
  unsubscribeReactionRemoved?.()
  unsubscribePinned?.()
  unsubscribeUnpinned?.()
  cleanupTyping()
})
</script>
