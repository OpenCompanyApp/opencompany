<template>
  <div class="min-h-screen bg-white dark:bg-neutral-900 flex flex-col">
    <!-- Floating Header -->
    <div class="sticky top-0 z-10 p-4 pt-4 bg-gradient-to-b from-white dark:from-neutral-900 via-white/80 dark:via-neutral-900/80 to-transparent pb-8">
      <div class="max-w-4xl mx-auto">
        <div class="bg-white dark:bg-neutral-800 rounded-2xl shadow-lg border border-neutral-200 dark:border-neutral-700 px-4 py-3">
          <div class="flex items-center gap-4">
            <!-- Back button -->
            <Link
              :href="workspacePath('/messages')"
              class="p-2 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors text-neutral-500 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white"
            >
              <Icon name="ph:arrow-left" class="w-5 h-5" />
            </Link>

            <!-- User info -->
            <div v-if="conversation" class="flex items-center gap-3 flex-1">
              <AgentAvatar
                :user="conversation.otherUser"
                :src="conversation.otherUser.avatar"
                size="md"
                :show-status="true"
              />
              <div>
                <Link
                  :href="workspacePath(conversation.otherUser.type === 'agent' ? `/agent/${conversation.otherUser.id}` : `/profile/${conversation.otherUser.id}`)"
                  class="font-medium text-neutral-900 dark:text-white hover:text-neutral-900 dark:hover:text-white transition-colors"
                >
                  {{ conversation.otherUser.name }}
                </Link>
                <p class="text-xs text-neutral-500 dark:text-neutral-400">
                  {{ conversation.otherUser.type === 'agent' ? `${conversation.otherUser.agentType} Agent` : 'Team Member' }}
                  <span v-if="conversation.otherUser.status === 'working'" class="text-green-400"> - Active</span>
                </p>
              </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-1">
              <Link
                :href="workspacePath(conversation?.otherUser.type === 'agent' ? `/agent/${conversation.otherUser.id}` : `/profile/${userId}`)"
                class="p-2 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors text-neutral-500 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white"
                :title="conversation?.otherUser.type === 'agent' ? 'View agent' : 'View profile'"
              >
                <Icon :name="conversation?.otherUser.type === 'agent' ? 'ph:robot' : 'ph:user'" class="w-5 h-5" />
              </Link>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="flex-1 p-6">
      <div class="max-w-4xl mx-auto">
        <Skeleton preset="message" :count="5" />
      </div>
    </div>

    <!-- Messages -->
    <div
      v-else-if="conversation"
      ref="messagesContainer"
      class="flex-1 overflow-y-auto px-4 md:px-6 pt-4 pb-24"
    >
      <div class="max-w-4xl mx-auto space-y-4">
        <!-- Empty state -->
        <div v-if="!messages.length" class="text-center py-20">
          <div class="flex justify-center mb-4">
            <AgentAvatar
              :user="conversation.otherUser"
              :src="conversation.otherUser.avatar"
              size="xl"
              :show-status="false"
              :show-tooltip="false"
            />
          </div>
          <h2 class="text-xl font-semibold text-neutral-900 dark:text-white mb-2">{{ conversation.otherUser.name }}</h2>
          <p class="text-sm text-neutral-500 dark:text-neutral-400">
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
          <AgentAvatar
            v-if="!isSameAuthor(index)"
            :user="msg.author"
            :src="msg.author.avatar"
            size="sm"
            :show-status="false"
            :show-tooltip="true"
          />
          <div v-else class="w-8 shrink-0" />

          <!-- Message bubble -->
          <div
            :class="[
              'max-w-[85%] md:max-w-[70%] rounded-2xl px-4 py-2',
              msg.author.id === currentUserId
                ? 'bg-neutral-900 text-white'
                : 'bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white',
            ]"
          >
            <p class="text-sm whitespace-pre-wrap break-words" v-html="formatContent(msg.content)" />
            <p
              :class="[
                'text-xs mt-1',
                msg.author.id === currentUserId ? 'text-white/60' : 'text-neutral-500 dark:text-neutral-400',
              ]"
            >
              {{ formatTime(msg.timestamp) }}
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Typing Indicator -->
    <div v-if="typingUsers.length > 0" class="px-6 pb-2 bg-white dark:bg-neutral-900">
      <div class="max-w-4xl mx-auto">
        <TypingIndicator :users="typingUsers" size="sm" variant="minimal" />
      </div>
    </div>

    <!-- Floating Input -->
    <div class="sticky bottom-0 p-4 pb-6 bg-gradient-to-t from-white dark:from-neutral-900 via-white/80 dark:via-neutral-900/80 to-transparent pt-8">
      <div class="max-w-4xl mx-auto">
        <div class="bg-white dark:bg-neutral-800 rounded-2xl shadow-lg border border-neutral-200 dark:border-neutral-700 p-3">
          <form @submit.prevent="sendMessage" class="flex items-end gap-3">
            <div class="flex-1 relative">
              <textarea
                ref="inputRef"
                v-model="newMessage"
                placeholder="Type a message..."
                rows="1"
                class="w-full px-4 py-3 bg-neutral-50 dark:bg-neutral-700/50 border-0 rounded-xl text-neutral-900 dark:text-white placeholder-neutral-500 dark:placeholder-neutral-400 focus:outline-none focus:ring-2 focus:ring-neutral-300 dark:focus:ring-neutral-600 resize-none"
                @keydown.enter.exact.prevent="sendMessage"
                @input="autoResize"
              />
            </div>
            <Button
              type="submit"
              :disabled="!newMessage.trim() || sending"
              :loading="sending"
              icon="ph:paper-plane-tilt"
            />
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { apiFetch } from '@/utils/apiFetch'
import Icon from '@/Components/shared/Icon.vue'
import Button from '@/Components/shared/Button.vue'
import AgentAvatar from '@/Components/shared/AgentAvatar.vue'
import Skeleton from '@/Components/shared/Skeleton.vue'
import TypingIndicator from '@/Components/chat/TypingIndicator.vue'
import { useRealtime } from '@/composables/useRealtime'
import { useHighlight } from '@/composables/useHighlight'
import { useWorkspace } from '@/composables/useWorkspace'

const { workspacePath } = useWorkspace()

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

const page = usePage()
const currentUserId = computed(() => (page.props.auth as any)?.user?.id ?? '')

const { highlight } = useHighlight()

// Format message content with full markdown support
const formatContent = (content: string): string => {
  // Parse markdown code blocks with syntax highlighting
  content = content.replace(/```(\w+)?\n([\s\S]*?)```/g, (_, lang, code) => {
    const highlighted = highlight(code.trim(), lang)
    const langLabel = lang ? `<div class="text-xs text-neutral-400 mb-1 font-mono">${lang}</div>` : ''
    return `<div class="my-3">${langLabel}<pre class="p-3 rounded-lg bg-neutral-800 dark:bg-neutral-900 overflow-x-auto"><code class="text-sm hljs">${highlighted}</code></pre></div>`
  })

  // Convert headers (### Header)
  content = content.replace(/^### (.+)$/gm, '<h3 class="text-base font-semibold mt-4 mb-2">$1</h3>')
  content = content.replace(/^## (.+)$/gm, '<h2 class="text-lg font-semibold mt-4 mb-2">$1</h2>')
  content = content.replace(/^# (.+)$/gm, '<h1 class="text-xl font-bold mt-4 mb-2">$1</h1>')

  // Convert horizontal rules
  content = content.replace(/^---$/gm, '<hr class="my-4 border-neutral-300 dark:border-neutral-600" />')

  // Convert **bold** text
  content = content.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')

  // Convert *italic* text
  content = content.replace(/\*(.+?)\*/g, '<em>$1</em>')

  // Convert inline `code` (not inside code blocks)
  content = content.replace(/`([^`\n]+?)`/g, '<code class="px-1.5 py-0.5 bg-neutral-200 dark:bg-neutral-700 rounded text-xs font-mono">$1</code>')

  // Convert URLs to links
  content = content.replace(
    /(https?:\/\/[^\s<]+)/g,
    '<a href="$1" target="_blank" rel="noopener" class="text-blue-500 hover:underline">$1</a>'
  )

  // Convert numbered lists (1. item)
  content = content.replace(/^(\d+)\.\s+(.+)$/gm, '<li class="ml-4 list-decimal">$2</li>')

  // Convert bullet lists (* item or - item)
  content = content.replace(/^[\*\-]\s+(.+)$/gm, '<li class="ml-4 list-disc">$1</li>')

  // Wrap consecutive list items
  content = content.replace(/(<li class="ml-4 list-decimal">.+<\/li>\n?)+/g, '<ol class="my-2 pl-4">$&</ol>')
  content = content.replace(/(<li class="ml-4 list-disc">.+<\/li>\n?)+/g, '<ul class="my-2 pl-4">$&</ul>')

  // Convert line breaks to <br> for paragraphs (but not in code blocks)
  content = content.replace(/\n\n/g, '</p><p class="mt-2">')

  return content
}

const loading = ref(true)
const conversation = ref<Conversation | null>(null)
const messages = ref<Message[]>([])
const newMessage = ref('')
const sending = ref(false)
const messagesContainer = ref<HTMLElement | null>(null)
const inputRef = ref<HTMLTextAreaElement | null>(null)
const typingUsers = ref<User[]>([])

const isSameAuthor = (index: number) => {
  if (index === 0) return false
  return messages.value[index].author.id === messages.value[index - 1].author.id
}

const fetchConversation = async () => {
  loading.value = true
  try {
    const response = await apiFetch(`/api/dm/${props.userId}`)
    const data = await response.json()
    conversation.value = data
    messages.value = data.messages

    // Mark as read
    await apiFetch(`/api/dm/${props.userId}/read`, { method: 'POST' })

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
    const response = await apiFetch(`/api/dm/${props.userId}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ content }),
    })
    const data = await response.json()

    // Add user's message
    if (data.userMessage) {
      messages.value.push(data.userMessage)
    }

    // Add agent's response if present
    if (data.agentMessage) {
      messages.value.push(data.agentMessage)
    }

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
let typingUnsubscribe: (() => void) | null = null

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
      apiFetch(`/api/dm/${props.userId}/read`, { method: 'POST' })
    }
  })

  // Listen for typing indicator
  typingUnsubscribe = on('dm:typing', (data: { user: User }) => {
    if (data.user.id === props.userId) {
      typingUsers.value = [data.user]
      // Clear after 3 seconds
      setTimeout(() => {
        typingUsers.value = []
      }, 3000)
    }
  })
})

onUnmounted(() => {
  unsubscribe?.()
  typingUnsubscribe?.()
})

watch(() => props.userId, () => {
  fetchConversation()
})
</script>
