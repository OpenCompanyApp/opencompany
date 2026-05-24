<template>
  <aside class="flex h-full w-full flex-col border-r border-neutral-200 bg-neutral-50 dark:border-neutral-800 dark:bg-neutral-950 md:w-80">
    <div class="border-b border-neutral-200 p-3 dark:border-neutral-800">
      <div class="mb-3 flex items-center justify-between gap-2">
        <div>
          <h2 class="text-sm font-semibold text-neutral-950 dark:text-white">OpenCompany</h2>
          <p class="text-xs text-neutral-500 dark:text-neutral-400">Assistant conversations</p>
        </div>
        <button
          type="button"
          class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-neutral-900 text-white transition-colors hover:bg-neutral-800 dark:bg-neutral-100 dark:text-neutral-900 dark:hover:bg-neutral-200"
          title="New chat"
          @click="newChat"
        >
          <Icon name="ph:plus" class="h-4 w-4" />
        </button>
      </div>

      <div class="relative">
        <Icon name="ph:magnifying-glass" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" />
        <input
          v-model="search"
          type="search"
          class="h-10 w-full rounded-xl border border-neutral-200 bg-white pl-9 pr-3 text-sm text-neutral-900 outline-none transition-colors placeholder:text-neutral-400 focus:border-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white dark:focus:border-neutral-500"
          placeholder="Search chats..."
        >
      </div>
    </div>

    <div class="flex-1 overflow-y-auto p-2">
      <div v-if="filteredConversations.length" class="space-y-1">
        <button
          v-for="conversation in filteredConversations"
          :key="conversation.id"
          type="button"
          :class="conversationClasses(conversation.id === selectedChannel?.id)"
          @click="$emit('select', conversation)"
        >
          <SharedAgentAvatar :user="conversation.agent" size="sm" :show-status="true" class="shrink-0" />
          <div class="min-w-0 flex-1">
            <div class="flex items-center justify-between gap-2">
              <p class="truncate text-sm font-medium">{{ conversation.title }}</p>
              <span v-if="conversation.time" class="shrink-0 text-[11px] opacity-60">{{ conversation.time }}</span>
            </div>
            <p class="mt-0.5 truncate text-xs opacity-70">{{ conversation.preview }}</p>
          </div>
        </button>
      </div>

      <div v-else class="flex h-full flex-col items-center justify-center px-6 text-center">
        <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-neutral-400 dark:bg-neutral-900">
          <Icon name="ph:chat-circle-dots" class="h-6 w-6" />
        </div>
        <p class="text-sm font-medium text-neutral-900 dark:text-white">No assistant chats</p>
        <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">Start a new chat with one of your agents.</p>
      </div>
    </div>

    <div class="border-t border-neutral-200 p-3 dark:border-neutral-800">
      <button
        type="button"
        class="flex w-full items-center justify-center gap-2 rounded-xl border border-neutral-200 bg-white px-3 py-2 text-sm font-medium text-neutral-700 transition-colors hover:border-neutral-300 hover:text-neutral-950 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-200 dark:hover:border-neutral-600 dark:hover:text-white"
        @click="$emit('openChannels')"
      >
        <Icon name="ph:hash" class="h-4 w-4" />
        Channels and human DMs
      </button>
    </div>
  </aside>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import SharedAgentAvatar from '@/Components/shared/AgentAvatar.vue'
import type { Channel, User } from '@/types'

const props = defineProps<{
  channels: Channel[]
  selectedChannel: Channel | null
  agents: User[]
  currentUserId: string
}>()

const emit = defineEmits<{
  select: [channel: Channel]
  newAgentChat: [agentId: string]
  openChannels: []
}>()

const search = ref('')

const agentById = computed(() => new Map(props.agents.map(agent => [agent.id, agent])))

const conversations = computed(() => props.channels
  .filter(channel => channel.type === 'dm' && channel.members?.some(member => member.type === 'agent'))
  .map(channel => {
    const agent = channel.members.find(member => member.type === 'agent') ?? channel.members.find(member => member.id !== props.currentUserId) ?? props.agents[0]
    const latest = channel.latestMessage ?? channel.lastMessage

    return {
      ...channel,
      agent,
      title: agent?.name ?? channel.name,
      preview: latest?.content || 'New assistant chat',
      time: latest?.timestamp ? formatRelativeTime(latest.timestamp) : '',
    }
  }))

const filteredConversations = computed(() => {
  const query = search.value.trim().toLowerCase()
  if (!query) return conversations.value
  return conversations.value.filter(conversation =>
    conversation.title.toLowerCase().includes(query) ||
    conversation.preview.toLowerCase().includes(query)
  )
})

const newChat = () => {
  const preferred = props.agents.find(agent => agent.status !== 'offline') ?? props.agents[0]
  if (preferred) emit('newAgentChat', preferred.id)
}

const conversationClasses = (selected: boolean) => [
  'flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left transition-colors',
  selected
    ? 'bg-white text-neutral-950 shadow-sm ring-1 ring-neutral-200 dark:bg-neutral-900 dark:text-white dark:ring-neutral-700'
    : 'text-neutral-600 hover:bg-white hover:text-neutral-950 dark:text-neutral-300 dark:hover:bg-neutral-900 dark:hover:text-white',
]

const formatRelativeTime = (value: Date | string) => {
  const diff = Date.now() - new Date(value).getTime()
  const minutes = Math.floor(diff / 60000)
  if (minutes < 1) return 'now'
  if (minutes < 60) return `${minutes}m`
  const hours = Math.floor(minutes / 60)
  if (hours < 24) return `${hours}h`
  const days = Math.floor(hours / 24)
  if (days < 7) return `${days}d`
  return new Date(value).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}
</script>
