<template>
  <div class="h-full flex">
    <!-- Channel List Sidebar -->
    <ChatChannelList
      :channels="channelsData"
      :selected-channel="selectedChannel"
      @select="selectChannel"
      @create="showCreateChannelModal = true"
    />

    <!-- Main Chat Area -->
    <ChatArea
      v-if="selectedChannel"
      :channel="selectedChannel"
      :messages="channelMessages"
      :pinned-messages="pinnedMessagesData"
      :typing-users="typingUsersData"
      :current-user-id="'h1'"
      :active-thread="activeThread"
      class="flex-1"
      @send="handleSendMessage"
      @react="handleReaction"
      @open-thread="handleOpenThread"
      @close-thread="activeThread = null"
      @thread-reply="handleThreadReply"
      @pin="handlePinMessage"
      @typing="handleTyping"
    />

    <!-- Channel Info Sidebar -->
    <ChatChannelInfo
      v-if="selectedChannel"
      :channel="selectedChannel"
      :viewers="channelViewers"
      @add-member="showAddMemberModal = true"
      @member-remove="handleMemberRemove"
      @member-click="handleMemberClick"
      @member-message="handleMemberMessage"
    />

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
import { useApi } from '@/composables/useApi'
import { useRealtime } from '@/composables/useRealtime'
import { useTypingIndicator } from '@/composables/useTypingIndicator'

const page = usePage()
const { fetchChannels, fetchMessages, sendMessage, markChannelRead, addMessageReaction, fetchMessageThread, removeChannelMember, pinMessage, fetchPinnedMessages, sendTypingIndicator, uploadMessageAttachment } = useApi()

interface Thread {
  parentMessage: Message
  replies: Message[]
}

const activeThread = ref<Thread | null>(null)
const showAddMemberModal = ref(false)
const showCreateChannelModal = ref(false)

// Channels data
const channels = ref<Channel[]>([])
const refreshChannels = async () => {
  const result = fetchChannels()
  await result.promise
  channels.value = result.data.value ?? []
}

const channelsData = computed<Channel[]>(() => channels.value ?? [])

// Selected channel
const selectedChannel = ref<Channel | null>(null)

// Initialize with first channel or from query
watch(channelsData, (newChannels) => {
  if (!selectedChannel.value && newChannels.length > 0) {
    const url = new URL(window.location.href)
    const channelId = url.searchParams.get('channel')
    const found = channelId ? newChannels.find(c => c.id === channelId) : newChannels[2]
    selectedChannel.value = found ?? newChannels[0]
  }
}, { immediate: true })

// Handle channel query param changes
watch(() => {
  const url = new URL(window.location.href)
  return url.searchParams.get('channel')
}, (channelId) => {
  if (channelId && channelsData.value.length > 0) {
    const found = channelsData.value.find(c => c.id === channelId)
    if (found) selectedChannel.value = found
  }
})

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
} = useTypingIndicator(channelIdRef, 'h1')

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

  let attachmentIds: string[] = []

  // Upload attachments first if any
  if (attachments && attachments.length > 0) {
    try {
      const uploadPromises = attachments.map(async (attachment) => {
        const result = await uploadMessageAttachment(
          attachment.file,
          selectedChannel.value!.id,
          'h1'
        )
        return result.id
      })
      attachmentIds = await Promise.all(uploadPromises)
    } catch (error) {
      console.error('Failed to upload attachments:', error)
      return
    }
  }

  await sendMessage({
    content,
    channelId: selectedChannel.value.id,
    authorId: 'h1',
    attachmentIds: attachmentIds.length > 0 ? attachmentIds : undefined,
  })

  await refreshMessages()
}

const handleReaction = async (message: Message, emoji: string) => {
  try {
    await addMessageReaction(message.id, { emoji, userId: 'h1' })
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
      authorId: 'h1',
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
  router.visit(`/users/${member.id}`)
}

const handleMemberMessage = (member: User) => {
  // TODO: Navigate to DM with this user or open DM modal
  console.log('Message member:', member.name)
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

// Message pinning
const handlePinMessage = async (message: Message) => {
  try {
    await pinMessage(message.id, 'h1')
    await refreshMessages()
    await refreshPinnedMessages()
  } catch (error) {
    console.error('Failed to pin/unpin message:', error)
  }
}

// Real-time updates
const { on } = useRealtime()
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
