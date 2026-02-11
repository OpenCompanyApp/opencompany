<template>
  <div class="flex-1 flex flex-col h-full bg-white dark:bg-neutral-900">
    <!-- Header (hidden on mobile - parent has mobile toolbar) -->
    <div class="hidden md:flex items-center justify-between px-4 py-3 border-b border-neutral-200 dark:border-neutral-700">
      <div class="flex items-center gap-3">
        <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-neutral-100 dark:bg-neutral-700">
          <Icon :name="channelIcon" class="w-4 h-4 text-neutral-500 dark:text-neutral-300" />
        </div>
        <div>
          <h2 class="font-semibold text-neutral-900 dark:text-white">{{ channel.name }}</h2>
          <p v-if="channel.description" class="text-xs text-neutral-500 dark:text-neutral-400 truncate max-w-xs">
            {{ channel.description }}
          </p>
        </div>
      </div>

      <!-- Header Actions -->
      <div class="flex items-center gap-1">
        <!-- Pinned Messages -->
        <Tooltip v-if="pinnedMessages.length > 0" text="Pinned messages" :delay-duration="300" side="bottom">
          <button
            type="button"
            class="relative p-2 rounded-lg text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors"
            @click="showPinnedPanel = !showPinnedPanel"
          >
            <Icon name="ph:push-pin" class="w-4 h-4" />
            <span class="absolute -top-1 -right-1 w-4 h-4 text-[10px] font-medium bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 rounded-full flex items-center justify-center">
              {{ pinnedMessages.length }}
            </span>
          </button>
        </Tooltip>

        <!-- Members / Toggle Info -->
        <Tooltip text="Channel details" :delay-duration="300" side="bottom">
          <button
            type="button"
            class="flex items-center gap-1.5 px-2 py-1.5 rounded-lg text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors"
            @click="emit('toggleInfo')"
          >
            <Icon name="ph:users" class="w-4 h-4" />
            <span class="text-xs font-medium">{{ channel.members?.length || 0 }}</span>
          </button>
        </Tooltip>
      </div>
    </div>

    <!-- Pinned Messages Panel -->
    <Transition name="slide-down">
      <div v-if="showPinnedPanel && pinnedMessages.length > 0" class="border-b border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/50">
        <div class="px-4 py-2">
          <div class="flex items-center justify-between mb-2">
            <h4 class="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
              Pinned Messages
            </h4>
            <button
              type="button"
              class="p-1 rounded text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 transition-colors"
              @click="showPinnedPanel = false"
            >
              <Icon name="ph:x" class="w-3 h-3" />
            </button>
          </div>
          <div class="space-y-2 max-h-32 overflow-y-auto">
            <button
              v-for="message in pinnedMessages.slice(0, 3)"
              :key="message.id"
              type="button"
              class="w-full text-left p-2 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors"
              @click="scrollToMessage(message.id)"
            >
              <div class="flex items-center gap-2">
                <span class="text-xs font-medium text-neutral-900 dark:text-white">{{ message.author?.name }}</span>
                <span class="text-xs text-neutral-400">{{ formatRelativeTime(message.timestamp) }}</span>
              </div>
              <p class="text-xs text-neutral-500 dark:text-neutral-400 line-clamp-1">{{ message.content }}</p>
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Messages Area -->
    <div
      ref="messagesContainer"
      class="flex-1 overflow-y-auto px-4 md:px-8 py-4 scrollbar-thin scrollbar-thumb-neutral-200 dark:scrollbar-thumb-neutral-600 scrollbar-track-transparent"
    >
      <div class="max-w-5xl mx-auto w-full">
        <!-- Empty State -->
        <div v-if="messages.length === 0" class="flex flex-col items-center justify-center h-full text-center">
          <div class="w-16 h-16 rounded-full bg-neutral-100 dark:bg-neutral-700 flex items-center justify-center mb-4">
            <Icon name="ph:chat-circle" class="w-8 h-8 text-neutral-400" />
          </div>
          <h3 class="text-lg font-semibold text-neutral-900 dark:text-white mb-1">No messages yet</h3>
          <p class="text-sm text-neutral-500 dark:text-neutral-400">Be the first to send a message in #{{ channel.name }}</p>
        </div>

        <!-- Messages List -->
        <template v-else>
          <div
            v-for="(message, index) in messages"
            :key="message.id"
            :id="`message-${message.id}`"
            :class="isFirstInGroup(index) ? 'mt-4 first:mt-0' : 'mt-0.5'"
          >
            <!-- Date Separator -->
            <div
              v-if="shouldShowDateSeparator(index)"
              class="flex items-center gap-4 my-4"
            >
              <div class="flex-1 h-px bg-neutral-200 dark:bg-neutral-700" />
              <span class="text-xs font-medium text-neutral-400 dark:text-neutral-500 select-none">
                {{ formatMessageDate(message.timestamp) }}
              </span>
              <div class="flex-1 h-px bg-neutral-200 dark:bg-neutral-700" />
            </div>

            <!-- Message -->
            <ChatMessage
              :message="message"
              variant="bubble"
              :is-own="message.author?.id === currentUserId"
              :show-avatar="shouldShowAvatar(index)"
              :show-header="shouldShowName(index)"
              :is-first-in-group="isFirstInGroup(index)"
              :is-last-in-group="isLastInGroup(index)"
              :show-delivery-status="message.author?.id === currentUserId && isLastInGroup(index)"
              @reaction="(msg, emoji) => emit('react', msg, emoji)"
              @open-thread="emit('openThread', message)"
              @pin="emit('pin', message)"
            />
          </div>
        </template>
      </div>
    </div>

    <!-- Thread Panel -->
    <Transition name="slide-right">
      <div
        v-if="activeThread"
        class="absolute right-0 top-0 h-full w-80 bg-white dark:bg-neutral-900 border-l border-neutral-200 dark:border-neutral-700 flex flex-col z-10"
      >
        <!-- Thread Header -->
        <div class="flex items-center justify-between px-4 py-3 border-b border-neutral-200 dark:border-neutral-700">
          <h3 class="font-semibold text-neutral-900 dark:text-white">Thread</h3>
          <button
            type="button"
            class="p-1.5 rounded-lg text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors"
            @click="emit('closeThread')"
          >
            <Icon name="ph:x" class="w-4 h-4" />
          </button>
        </div>

        <!-- Parent Message -->
        <div class="px-4 py-3 border-b border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/50">
          <ChatMessage
            :message="activeThread.parentMessage"
            variant="bubble"
            :is-own="activeThread.parentMessage.author?.id === currentUserId"
            :show-avatar="true"
            :show-header="true"
            :is-first-in-group="true"
            :is-last-in-group="true"
            :compact="true"
          />
        </div>

        <!-- Thread Replies -->
        <div class="flex-1 overflow-y-auto px-4 py-4 space-y-3">
          <div class="text-xs text-neutral-500 dark:text-neutral-400 mb-2">
            {{ activeThread.replies?.length || 0 }} {{ activeThread.replies?.length === 1 ? 'reply' : 'replies' }}
          </div>
          <ChatMessage
            v-for="reply in activeThread.replies"
            :key="reply.id"
            :message="reply"
            variant="bubble"
            :is-own="reply.author?.id === currentUserId"
            :show-avatar="true"
            :show-header="true"
            :is-first-in-group="true"
            :is-last-in-group="true"
            :compact="true"
          />
        </div>

        <!-- Thread Input -->
        <div class="p-3 border-t border-neutral-200 dark:border-neutral-700">
          <ChatMessageInput
            :channel="channel"
            variant="compact"
            @send="(content) => emit('threadReply', activeThread.parentMessage.id, content)"
          />
        </div>
      </div>
    </Transition>

    <!-- Typing Indicator (inline bubble like ChatGPT/Telegram) -->
    <Transition name="fade">
      <div v-if="typingUsers.length > 0" class="px-4 md:px-8 py-2">
        <div class="max-w-5xl mx-auto flex items-end gap-2">
          <SharedAgentAvatar
            v-if="typingUsers[0]"
            :user="typingUsers[0]"
            size="sm"
            :show-status="false"
            class="shrink-0 mb-0.5"
          />
          <div class="bg-neutral-100 dark:bg-neutral-800 rounded-2xl rounded-bl-md px-4 py-2.5">
            <ChatTypingIndicator
              :users="typingUsers"
              variant="minimal"
              size="sm"
              :show-avatars="false"
              :show-typing-ring="false"
            />
          </div>
        </div>
      </div>
    </Transition>

    <!-- Message Input -->
    <div class="border-t border-neutral-200 dark:border-neutral-700">
      <ChatMessageInput
        :channel="channel"
        @send="handleSendMessage"
        @typing="emit('typing')"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import Tooltip from '@/Components/shared/Tooltip.vue'
import ChatMessage from '@/Components/chat/Message.vue'
import ChatMessageInput from '@/Components/chat/MessageInput.vue'
import ChatTypingIndicator from '@/Components/chat/TypingIndicator.vue'
import SharedAgentAvatar from '@/Components/shared/AgentAvatar.vue'
import type { Channel, Message, User } from '@/types'

interface Thread {
  parentMessage: Message
  replies: Message[]
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

const props = withDefaults(defineProps<{
  channel: Channel
  messages: Message[]
  pinnedMessages?: Message[]
  typingUsers?: User[]
  currentUserId: string
  activeThread?: Thread | null
}>(), {
  pinnedMessages: () => [],
  typingUsers: () => [],
  activeThread: null,
})

const emit = defineEmits<{
  send: [content: string, attachments?: MessageAttachment[]]
  react: [message: Message, emoji: string]
  openThread: [message: Message]
  closeThread: []
  threadReply: [parentMessageId: string, content: string]
  pin: [message: Message]
  typing: []
  toggleInfo: []
}>()

// State
const messagesContainer = ref<HTMLElement | null>(null)
const showPinnedPanel = ref(false)

// Channel icon
const channelIcon = computed(() => {
  if (props.channel.type === 'dm') return 'ph:chat-circle'
  if (props.channel.private) return 'ph:lock-simple'
  return 'ph:hash'
})

// Auto-scroll to bottom when new messages arrive
watch(
  () => props.messages.length,
  () => {
    nextTick(() => {
      if (messagesContainer.value) {
        messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
      }
    })
  }
)

// Scroll to specific message
const scrollToMessage = (messageId: string) => {
  const element = document.getElementById(`message-${messageId}`)
  if (element) {
    element.scrollIntoView({ behavior: 'smooth', block: 'center' })
    element.classList.add('bg-yellow-50', 'dark:bg-yellow-900/20')
    setTimeout(() => {
      element.classList.remove('bg-yellow-50', 'dark:bg-yellow-900/20')
    }, 2000)
  }
  showPinnedPanel.value = false
}

// Check if date separator should be shown
const shouldShowDateSeparator = (index: number): boolean => {
  if (index === 0) return true
  const currentMessage = props.messages[index]
  const previousMessage = props.messages[index - 1]
  if (!currentMessage?.timestamp || !previousMessage?.timestamp) return false

  const currentDate = new Date(currentMessage.timestamp).toDateString()
  const previousDate = new Date(previousMessage.timestamp).toDateString()
  return currentDate !== previousDate
}

// Check if message is first in its group (same author within 5 min)
const isFirstInGroup = (index: number): boolean => {
  if (index === 0) return true
  const currentMessage = props.messages[index]
  const previousMessage = props.messages[index - 1]
  if (!currentMessage?.author || !previousMessage?.author) return true
  if (currentMessage.author.id !== previousMessage.author.id) return true
  const timeDiff = new Date(currentMessage.timestamp).getTime() - new Date(previousMessage.timestamp).getTime()
  return timeDiff > 5 * 60 * 1000
}

// Check if message is last in its group
const isLastInGroup = (index: number): boolean => {
  if (index === props.messages.length - 1) return true
  const currentMessage = props.messages[index]
  const nextMessage = props.messages[index + 1]
  if (!currentMessage?.author || !nextMessage?.author) return true
  if (currentMessage.author.id !== nextMessage.author.id) return true
  const timeDiff = new Date(nextMessage.timestamp).getTime() - new Date(currentMessage.timestamp).getTime()
  return timeDiff > 5 * 60 * 1000
}

// Show avatar on last message in group (Telegram convention — avatar at bottom)
const shouldShowAvatar = (index: number): boolean => isLastInGroup(index)

// Show name on first message in group
const shouldShowName = (index: number): boolean => isFirstInGroup(index)

// Format message date
const formatMessageDate = (date: Date | string): string => {
  const d = new Date(date)
  const today = new Date()
  const yesterday = new Date(today)
  yesterday.setDate(yesterday.getDate() - 1)

  if (d.toDateString() === today.toDateString()) return 'Today'
  if (d.toDateString() === yesterday.toDateString()) return 'Yesterday'

  return d.toLocaleDateString('en-US', {
    weekday: 'long',
    month: 'long',
    day: 'numeric',
  })
}

// Format relative time
const formatRelativeTime = (date: Date | string): string => {
  const now = new Date()
  const d = new Date(date)
  const diff = now.getTime() - d.getTime()
  const minutes = Math.floor(diff / 60000)
  const hours = Math.floor(minutes / 60)
  const days = Math.floor(hours / 24)

  if (minutes < 1) return 'now'
  if (minutes < 60) return `${minutes}m ago`
  if (hours < 24) return `${hours}h ago`
  if (days < 7) return `${days}d ago`

  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

// Handle send message
const handleSendMessage = (content: string, attachments?: MessageAttachment[]) => {
  emit('send', content, attachments)
}
</script>

<style scoped>
/* Scrollbar styling */
.scrollbar-thin::-webkit-scrollbar {
  width: 6px;
}

.scrollbar-thin::-webkit-scrollbar-track {
  background: transparent;
}

.scrollbar-thin::-webkit-scrollbar-thumb {
  background-color: #e5e7eb;
  border-radius: 3px;
}

.dark .scrollbar-thin::-webkit-scrollbar-thumb {
  background-color: #525252;
}

.scrollbar-thin::-webkit-scrollbar-thumb:hover {
  background-color: #9ca3af;
}

/* Transitions */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

.slide-down-enter-active,
.slide-down-leave-active {
  transition: all 0.2s ease;
}

.slide-down-enter-from,
.slide-down-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}

.slide-right-enter-active,
.slide-right-leave-active {
  transition: all 0.2s ease;
}

.slide-right-enter-from,
.slide-right-leave-to {
  opacity: 0;
  transform: translateX(100%);
}

/* Line clamp */
.line-clamp-1 {
  display: -webkit-box;
  -webkit-line-clamp: 1;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
