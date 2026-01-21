<template>
  <div class="min-h-screen bg-white flex flex-col">
    <!-- Header -->
    <div class="bg-gray-50 border-b border-gray-200 px-6 py-4">
      <div class="max-w-4xl mx-auto flex items-center gap-4">
        <!-- Back button -->
        <Link
          href="/messages"
          class="p-2 rounded-lg hover:bg-white transition-colors text-gray-500 hover:text-gray-900"
        >
          <Icon name="ph:arrow-left" class="w-5 h-5" />
        </Link>

        <!-- User info -->
        <div v-if="conversation" class="flex items-center gap-3 flex-1">
          <div class="relative">
            <div
              v-if="conversation.otherUser.avatar"
              class="w-10 h-10 rounded-full overflow-hidden"
            >
              <img :src="conversation.otherUser.avatar" :alt="conversation.otherUser.name" class="w-full h-full object-cover" />
            </div>
            <div
              v-else
              :class="[
                'w-10 h-10 rounded-full flex items-center justify-center text-white font-bold',
                conversation.otherUser.type === 'human' ? 'bg-blue-500' : agentColorMap[conversation.otherUser.agentType || 'default'],
              ]"
            >
              {{ conversation.otherUser.name.charAt(0) }}
            </div>
            <span
              v-if="conversation.otherUser.type === 'agent'"
              :class="[
                'absolute bottom-0 right-0 w-3 h-3 rounded-full border-2 border-gray-50',
                statusColorMap[conversation.otherUser.status || 'offline'],
              ]"
            />
          </div>
          <div>
            <Link
              :href="`/profile/${conversation.otherUser.id}`"
              class="font-medium text-gray-900 hover:text-gray-900 transition-colors"
            >
              {{ conversation.otherUser.name }}
            </Link>
            <p class="text-xs text-gray-500">
              {{ conversation.otherUser.type === 'agent' ? `${conversation.otherUser.agentType} Agent` : 'Team Member' }}
              <span v-if="conversation.otherUser.status === 'working'" class="text-green-400"> - Active</span>
            </p>
          </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-2">
          <Link
            v-if="conversation?.otherUser.type === 'agent'"
            :href="`/agent/${conversation.otherUser.id}`"
            class="p-2 rounded-lg hover:bg-white transition-colors text-gray-500 hover:text-gray-900"
            title="View agent details"
          >
            <Icon name="ph:gear" class="w-5 h-5" />
          </Link>
          <Link
            :href="`/profile/${userId}`"
            class="p-2 rounded-lg hover:bg-white transition-colors text-gray-500 hover:text-gray-900"
            title="View profile"
          >
            <Icon name="ph:user" class="w-5 h-5" />
          </Link>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="flex-1 flex items-center justify-center">
      <Icon name="ph:spinner" class="w-8 h-8 text-gray-900 animate-spin" />
    </div>

    <!-- Messages -->
    <div
      v-else-if="conversation"
      ref="messagesContainer"
      class="flex-1 overflow-y-auto p-6"
    >
      <div class="max-w-4xl mx-auto space-y-4">
        <!-- Empty state -->
        <div v-if="!messages.length" class="text-center py-20">
          <div
            v-if="conversation.otherUser.avatar"
            class="w-20 h-20 rounded-full overflow-hidden mx-auto mb-4"
          >
            <img :src="conversation.otherUser.avatar" :alt="conversation.otherUser.name" class="w-full h-full object-cover" />
          </div>
          <div
            v-else
            :class="[
              'w-20 h-20 rounded-full flex items-center justify-center text-white font-bold text-2xl mx-auto mb-4',
              conversation.otherUser.type === 'human' ? 'bg-blue-500' : agentColorMap[conversation.otherUser.agentType || 'default'],
            ]"
          >
            {{ conversation.otherUser.name.charAt(0) }}
          </div>
          <h2 class="text-xl font-semibold text-gray-900 mb-2">{{ conversation.otherUser.name }}</h2>
          <p class="text-sm text-gray-500">
            This is the beginning of your conversation with {{ conversation.otherUser.name }}.
          </p>
        </div>

        <!-- Message list -->
        <div
          v-for="(msg, index) in messages"
          :key="msg.id"
          :class="[
            'flex gap-3',
            msg.author.id === currentUserId ? 'flex-row-reverse' : '',
          ]"
        >
          <!-- Avatar (only show if different from previous message) -->
          <div
            v-if="!isSameAuthor(index)"
            class="shrink-0"
          >
            <div
              v-if="msg.author.avatar"
              class="w-8 h-8 rounded-full overflow-hidden"
            >
              <img :src="msg.author.avatar" :alt="msg.author.name" class="w-full h-full object-cover" />
            </div>
            <div
              v-else
              :class="[
                'w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold',
                msg.author.type === 'human' ? 'bg-blue-500' : agentColorMap[msg.author.agentType || 'default'],
              ]"
            >
              {{ msg.author.name.charAt(0) }}
            </div>
          </div>
          <div v-else class="w-8 shrink-0" />

          <!-- Message bubble -->
          <div
            :class="[
              'max-w-[70%] rounded-2xl px-4 py-2',
              msg.author.id === currentUserId
                ? 'bg-gray-900 text-white'
                : 'bg-gray-50 border border-gray-200 text-gray-900',
            ]"
          >
            <p class="text-sm whitespace-pre-wrap break-words">{{ msg.content }}</p>
            <p
              :class="[
                'text-xs mt-1',
                msg.author.id === currentUserId ? 'text-white/60' : 'text-gray-500',
              ]"
            >
              {{ formatTime(msg.timestamp) }}
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Input -->
    <div class="bg-gray-50 border-t border-gray-200 px-6 py-4">
      <div class="max-w-4xl mx-auto">
        <form @submit.prevent="sendMessage" class="flex items-end gap-3">
          <div class="flex-1 relative">
            <textarea
              ref="inputRef"
              v-model="newMessage"
              placeholder="Type a message..."
              rows="1"
              class="w-full px-4 py-3 bg-white border border-gray-200 rounded-xl text-gray-900 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-gray-300 resize-none"
              @keydown.enter.exact.prevent="sendMessage"
              @input="autoResize"
            />
          </div>
          <button
            type="submit"
            :disabled="!newMessage.trim() || sending"
            class="p-3 rounded-xl bg-gray-900 text-white hover:bg-gray-800 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <Icon v-if="sending" name="ph:spinner" class="w-5 h-5 animate-spin" />
            <Icon v-else name="ph:paper-plane-tilt" class="w-5 h-5" />
          </button>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useRealtime } from '@/composables/useRealtime'

interface User {
  id: string
  name: string
  avatar?: string
  type: 'human' | 'agent'
  agentType?: string
  status?: string
}

interface Message {
  id: string
  content: string
  author: User
  timestamp: string
}

interface Conversation {
  id: string
  channelId: string
  otherUser: User
  messages: Message[]
  createdAt: string
}

const props = defineProps<{
  userId: string
}>()

const currentUserId = 'h1' // In a real app, this would come from auth

const loading = ref(true)
const conversation = ref<Conversation | null>(null)
const messages = ref<Message[]>([])
const newMessage = ref('')
const sending = ref(false)
const messagesContainer = ref<HTMLElement | null>(null)
const inputRef = ref<HTMLTextAreaElement | null>(null)

const agentColorMap: Record<string, string> = {
  manager: 'bg-purple-500',
  writer: 'bg-green-500',
  analyst: 'bg-cyan-500',
  creative: 'bg-pink-500',
  researcher: 'bg-amber-500',
  coder: 'bg-indigo-500',
  coordinator: 'bg-teal-500',
  default: 'bg-gray-500',
}

const statusColorMap: Record<string, string> = {
  working: 'bg-green-400',
  idle: 'bg-amber-400',
  offline: 'bg-gray-400',
}

const isSameAuthor = (index: number) => {
  if (index === 0) return false
  return messages.value[index].author.id === messages.value[index - 1].author.id
}

const fetchConversation = async () => {
  loading.value = true
  try {
    const response = await fetch(`/api/dm/${props.userId}`)
    const data = await response.json()
    conversation.value = data
    messages.value = data.messages

    // Mark as read
    await fetch(`/api/dm/${props.userId}/read`, { method: 'POST' })

    // Scroll to bottom
    nextTick(() => {
      scrollToBottom()
    })
  } catch (error) {
    console.error('Failed to fetch conversation:', error)
  } finally {
    loading.value = false
  }
}

const sendMessage = async () => {
  if (!newMessage.value.trim() || sending.value) return

  sending.value = true
  const content = newMessage.value.trim()
  newMessage.value = ''

  try {
    const response = await fetch(`/api/dm/${props.userId}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ content }),
    })
    const msg = await response.json()
    messages.value.push(msg)
    nextTick(() => {
      scrollToBottom()
      autoResize()
    })
  } catch (error) {
    console.error('Failed to send message:', error)
    newMessage.value = content // Restore message on failure
  } finally {
    sending.value = false
  }
}

const scrollToBottom = () => {
  if (messagesContainer.value) {
    messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
  }
}

const autoResize = () => {
  if (inputRef.value) {
    inputRef.value.style.height = 'auto'
    inputRef.value.style.height = Math.min(inputRef.value.scrollHeight, 150) + 'px'
  }
}

const formatTime = (dateString: string) => {
  const date = new Date(dateString)
  const now = new Date()
  const diff = now.getTime() - date.getTime()
  const days = Math.floor(diff / (1000 * 60 * 60 * 24))

  if (days === 0) {
    return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })
  } else if (days === 1) {
    return 'Yesterday ' + date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })
  } else {
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' })
  }
}

// Realtime updates
const { on } = useRealtime()
let unsubscribe: (() => void) | null = null

onMounted(() => {
  fetchConversation()

  // Listen for new messages
  unsubscribe = on('dm:new_message', (data: { message: Message; conversationId: string }) => {
    // Check if this message is for the current conversation
    if (conversation.value && data.message.author.id === props.userId) {
      messages.value.push(data.message)
      nextTick(() => {
        scrollToBottom()
      })

      // Mark as read since we're viewing the conversation
      fetch(`/api/dm/${props.userId}/read`, { method: 'POST' })
    }
  })
})

onUnmounted(() => {
  unsubscribe?.()
})

watch(() => props.userId, () => {
  fetchConversation()
})
</script>
