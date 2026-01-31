<template>
  <div class="h-full flex flex-col">
    <!-- Header -->
    <header class="shrink-0 px-6 py-4 border-b border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-xl font-semibold text-neutral-900 dark:text-white">Messages</h1>
          <p class="text-sm text-neutral-500 dark:text-neutral-400 mt-1">
            Direct messages with team members and agents
          </p>
        </div>
        <Button @click="showNewMessage = true">
          <Icon name="ph:plus" class="w-4 h-4 mr-1.5" />
          New Message
        </Button>
      </div>

      <!-- Search -->
      <div class="mt-4 max-w-md">
        <SearchInput
          v-model="searchQuery"
          placeholder="Search conversations..."
        />
      </div>
    </header>

    <!-- Conversations List -->
    <div class="flex-1 overflow-y-auto">
      <div class="max-w-4xl mx-auto px-6 py-6">
        <!-- Loading State -->
        <div v-if="loading" class="space-y-3">
          <Skeleton v-for="i in 5" :key="i" preset="avatar-text" />
        </div>

        <!-- Conversation Items -->
        <div v-else-if="filteredConversations.length > 0" class="space-y-2">
          <Link
            v-for="conversation in filteredConversations"
            :key="conversation.id"
            :href="`/messages/${conversation.otherUser.id}`"
            class="flex items-center gap-4 p-4 rounded-xl border border-neutral-200 dark:border-neutral-700 hover:border-neutral-300 dark:hover:border-neutral-600 hover:bg-neutral-50 dark:hover:bg-neutral-800 transition-colors"
          >
            <!-- Avatar -->
            <AgentAvatar
              :user="conversation.otherUser"
              :src="conversation.otherUser.avatar"
              size="md"
              :show-status="conversation.otherUser.type === 'agent'"
              :show-tooltip="false"
            />

            <!-- Content -->
            <div class="flex-1 min-w-0">
              <div class="flex items-center justify-between mb-1">
                <span class="font-medium text-neutral-900 dark:text-white">{{ conversation.otherUser.name }}</span>
                <span class="text-xs text-neutral-500 dark:text-neutral-400">{{ formatTimeAgo(conversation.lastMessageAt) }}</span>
              </div>
              <p class="text-sm text-neutral-500 dark:text-neutral-400 truncate">
                {{ conversation.lastMessage }}
              </p>
            </div>

            <!-- Unread indicator -->
            <Badge
              v-if="conversation.unreadCount > 0"
              :label="conversation.unreadCount > 9 ? '9+' : String(conversation.unreadCount)"
              variant="primary"
              size="sm"
            />
          </Link>
        </div>

        <!-- Empty State -->
        <div v-else class="text-center py-12">
          <Icon name="ph:chat-circle-dots" class="w-12 h-12 mx-auto mb-4 text-neutral-400" />
          <p class="text-neutral-500 dark:text-neutral-400">No conversations yet</p>
          <p class="text-sm text-neutral-400 mt-1">
            Start a conversation with a team member or agent
          </p>
          <Button class="mt-4" @click="showNewMessage = true">
            Start a conversation
          </Button>
        </div>
      </div>
    </div>

    <!-- New Message Modal -->
    <Modal
      v-model:open="showNewMessage"
      title="New Message"
      size="sm"
    >
      <template #default>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-200 mb-2">Select recipient</label>
            <Select
              v-model="selectedRecipient"
              :items="recipientItems"
              placeholder="Choose a user or agent..."
            />
          </div>
          <div class="flex justify-end gap-3">
            <Button variant="secondary" @click="showNewMessage = false">
              Cancel
            </Button>
            <Button :disabled="!selectedRecipient" @click="startConversation">
              Start Chat
            </Button>
          </div>
        </div>
      </template>
    </Modal>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import Icon from '@/Components/shared/Icon.vue'
import Modal from '@/Components/shared/Modal.vue'
import Button from '@/Components/shared/Button.vue'
import SearchInput from '@/Components/shared/SearchInput.vue'
import Select from '@/Components/shared/Select.vue'
import Badge from '@/Components/shared/Badge.vue'
import AgentAvatar from '@/Components/shared/AgentAvatar.vue'
import Skeleton from '@/Components/shared/Skeleton.vue'

interface User {
  id: string
  name: string
  avatar?: string
  type: 'human' | 'agent'
  agentType?: string
  status?: string
}

interface Conversation {
  id: string
  otherUser: User
  lastMessage: string
  lastMessageAt: string
  unreadCount: number
}

const loading = ref(true)
const conversations = ref<Conversation[]>([])
const searchQuery = ref('')
const showNewMessage = ref(false)
const selectedRecipient = ref('')
const availableRecipients = ref<User[]>([])

const filteredConversations = computed(() => {
  if (!searchQuery.value) return conversations.value
  const query = searchQuery.value.toLowerCase()
  return conversations.value.filter(c =>
    c.otherUser.name.toLowerCase().includes(query) ||
    c.lastMessage.toLowerCase().includes(query)
  )
})

const recipientItems = computed(() =>
  availableRecipients.value.map(u => ({
    value: u.id,
    label: `${u.name} (${u.type === 'agent' ? 'Agent' : 'Team Member'})`,
  }))
)

const formatTimeAgo = (date: string): string => {
  const d = new Date(date)
  const seconds = Math.floor((Date.now() - d.getTime()) / 1000)
  if (seconds < 60) return 'now'
  const minutes = Math.floor(seconds / 60)
  if (minutes < 60) return `${minutes}m`
  const hours = Math.floor(minutes / 60)
  if (hours < 24) return `${hours}h`
  const days = Math.floor(hours / 24)
  if (days < 7) return `${days}d`
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

const fetchConversations = async () => {
  loading.value = true
  try {
    const response = await fetch('/api/direct-messages')
    const data = await response.json()
    conversations.value = data
  } catch (error) {
    console.error('Failed to fetch conversations:', error)
  } finally {
    loading.value = false
  }
}

const fetchRecipients = async () => {
  try {
    const response = await fetch('/api/users')
    const data = await response.json()
    availableRecipients.value = data
  } catch (error) {
    console.error('Failed to fetch recipients:', error)
  }
}

const startConversation = () => {
  if (selectedRecipient.value) {
    router.visit(`/messages/${selectedRecipient.value}`)
  }
}

onMounted(() => {
  fetchConversations()
  fetchRecipients()
})
</script>
