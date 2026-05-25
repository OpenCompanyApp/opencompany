<template>
  <aside :class="sidebarClasses">
    <div class="border-b border-neutral-200 p-3 dark:border-neutral-800">
      <div class="mb-3 flex items-center justify-between gap-2">
        <div v-if="!collapsed" class="min-w-0">
          <h2 class="truncate text-sm font-semibold text-neutral-950 dark:text-white">OpenCompany</h2>
          <p class="truncate text-xs text-neutral-500 dark:text-neutral-400">Conversations</p>
        </div>
        <div class="flex items-center gap-1">
          <button
            type="button"
            :class="iconButtonClasses"
            title="New assistant chat"
            aria-label="New assistant chat"
            @click="newChat"
          >
            <Icon name="ph:plus" class="h-4 w-4" />
          </button>
          <button
            v-if="canCollapse"
            type="button"
            :class="ghostIconButtonClasses"
            :title="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
            :aria-label="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
            @click="$emit('toggleCollapse')"
          >
            <Icon :name="collapsed ? 'ph:sidebar-simple' : 'ph:sidebar-simple-duotone'" class="h-4 w-4" />
          </button>
        </div>
      </div>

      <div v-if="!collapsed" class="relative">
        <Icon name="ph:magnifying-glass" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" />
        <input
          v-model="search"
          type="search"
          class="h-10 w-full rounded-lg border border-neutral-200 bg-white pl-9 pr-3 text-sm text-neutral-900 outline-none transition-colors placeholder:text-neutral-400 focus:border-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white dark:focus:border-neutral-500"
          placeholder="Search conversations..."
        >
      </div>

      <div v-if="!collapsed" class="mt-2 grid grid-cols-2 gap-2">
        <button type="button" :class="secondaryButtonClasses" @click="$emit('createDm')">
          <Icon name="ph:chat-circle" class="h-4 w-4" />
          DM
        </button>
        <button type="button" :class="secondaryButtonClasses" @click="$emit('createChannel')">
          <Icon name="ph:hash" class="h-4 w-4" />
          Channel
        </button>
      </div>
    </div>

    <div class="flex-1 overflow-y-auto p-2">
      <div v-if="filteredConversations.length" class="space-y-1">
        <button
          v-for="conversation in filteredConversations"
          :key="conversation.id"
          type="button"
          :class="conversationClasses(conversation.id === selectedChannel?.id)"
          :title="conversation.title"
          :aria-label="conversationAriaLabel(conversation)"
          @click="$emit('select', conversation.channel)"
        >
          <div :class="avatarFrameClasses(conversation.kind)">
            <SharedAgentAvatar
              v-if="conversation.agent"
              :user="conversation.agent"
              size="sm"
              :show-status="conversation.kind === 'agent'"
            />
            <Icon v-else-if="conversation.kind === 'channel'" name="ph:hash" class="h-4 w-4" />
            <span v-else class="text-xs font-semibold">{{ conversation.initials }}</span>
          </div>

          <div v-if="!collapsed" class="min-w-0 flex-1">
            <div class="flex items-center justify-between gap-2">
              <p class="truncate text-sm font-medium">{{ conversation.title }}</p>
              <span v-if="conversation.time" class="shrink-0 text-[11px] opacity-60">{{ conversation.time }}</span>
            </div>
            <p class="mt-0.5 truncate text-xs opacity-70">{{ conversation.preview }}</p>
          </div>
        </button>
      </div>

      <div v-else-if="!collapsed" class="flex h-full flex-col items-center justify-center px-6 text-center">
        <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-lg bg-white text-neutral-400 dark:bg-neutral-900">
          <Icon name="ph:chat-circle-dots" class="h-6 w-6" />
        </div>
        <p class="text-sm font-medium text-neutral-900 dark:text-white">No conversations</p>
        <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">Start with an agent, DM, or channel.</p>
      </div>
    </div>
  </aside>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import SharedAgentAvatar from '@/Components/shared/AgentAvatar.vue'
import type { Channel, User } from '@/types'

type ConversationKind = 'agent' | 'dm' | 'channel'

const props = defineProps<{
  channels: Channel[]
  selectedChannel: Channel | null
  agents: User[]
  currentUserId: string
  collapsed?: boolean
  collapsible?: boolean
}>()

const emit = defineEmits<{
  select: [channel: Channel]
  newAgentChat: [agentId: string]
  createChannel: []
  createDm: []
  toggleCollapse: []
}>()

const search = ref('')
const canCollapse = computed(() => props.collapsible ?? true)

const conversations = computed(() => props.channels.map(channel => {
  const members = channel.members ?? []
  const agent = members.find(member => member.type === 'agent')
  const other = members.find(member => member.id !== props.currentUserId)
  const latest = channel.latestMessage ?? channel.lastMessage
  const kind: ConversationKind = agent ? 'agent' : channel.type === 'dm' ? 'dm' : 'channel'
  const title = channel.type === 'dm'
    ? (agent ?? other)?.name ?? channel.name ?? 'Direct message'
    : channel.name ?? 'Channel'

  return {
    id: channel.id,
    channel,
    kind,
    agent: kind === 'agent' ? agent : null,
    title,
    initials: initials(title),
    preview: latest?.content || previewFor(kind),
    time: latest?.timestamp ? formatRelativeTime(latest.timestamp) : '',
  }
}).sort((a, b) => {
  const aTime = new Date((a.channel.latestMessage ?? a.channel.lastMessage)?.timestamp ?? a.channel.lastMessageAt ?? 0).getTime()
  const bTime = new Date((b.channel.latestMessage ?? b.channel.lastMessage)?.timestamp ?? b.channel.lastMessageAt ?? 0).getTime()

  return bTime - aTime
}))

const filteredConversations = computed(() => {
  const query = search.value.trim().toLowerCase()
  if (!query || props.collapsed) return conversations.value

  return conversations.value.filter(conversation =>
    conversation.title.toLowerCase().includes(query) ||
    conversation.preview.toLowerCase().includes(query)
  )
})

const duplicateTitleCounts = computed(() => {
  const counts = new Map<string, number>()
  for (const conversation of conversations.value) {
    counts.set(conversation.title, (counts.get(conversation.title) ?? 0) + 1)
  }
  return counts
})

const preferredAgentId = computed(() => {
  const currentAgent = props.selectedChannel?.members?.find(member => member.type === 'agent')
  const preferred = currentAgent ?? props.agents.find(agent => agent.status !== 'offline') ?? props.agents[0]
  return preferred?.id ?? ''
})

const newChat = () => {
  if (preferredAgentId.value) emit('newAgentChat', preferredAgentId.value)
}

const conversationAriaLabel = (conversation: any) => {
  const base = conversation.kind === 'agent'
    ? `Assistant chat with ${conversation.title}`
    : conversation.kind === 'dm'
      ? `Direct message with ${conversation.title}`
      : `Channel ${conversation.title}`

  if ((duplicateTitleCounts.value.get(conversation.title) ?? 0) <= 1) return base

  const suffix = [
    conversation.preview,
    conversation.time ? `updated ${conversation.time} ago` : null,
    `id ${conversation.id.slice(0, 8)}`,
  ].filter(Boolean).join(', ')

  return `${base}, ${suffix}`
}

const sidebarClasses = computed(() => [
  'flex h-full flex-col border-r border-neutral-200 bg-neutral-50 transition-[width] duration-200 dark:border-neutral-800 dark:bg-neutral-950',
  props.collapsed ? 'w-16' : 'w-full md:w-80',
])

const iconButtonClasses = [
  'inline-flex h-9 w-9 items-center justify-center rounded-lg bg-neutral-900 text-white transition-colors hover:bg-neutral-800 dark:bg-neutral-100 dark:text-neutral-900 dark:hover:bg-neutral-200',
]

const ghostIconButtonClasses = [
  'inline-flex h-9 w-9 items-center justify-center rounded-lg text-neutral-500 transition-colors hover:bg-white hover:text-neutral-950 dark:text-neutral-300 dark:hover:bg-neutral-900 dark:hover:text-white',
]

const secondaryButtonClasses = [
  'inline-flex h-9 items-center justify-center gap-2 rounded-lg border border-neutral-200 bg-white px-3 text-sm font-medium text-neutral-700 transition-colors hover:border-neutral-300 hover:text-neutral-950 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-200 dark:hover:border-neutral-600 dark:hover:text-white',
]

const conversationClasses = (selected: boolean) => [
  'flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left transition-colors',
  props.collapsed && 'justify-center px-2',
  selected
    ? 'bg-white text-neutral-950 shadow-sm ring-1 ring-neutral-200 dark:bg-neutral-900 dark:text-white dark:ring-neutral-700'
    : 'text-neutral-600 hover:bg-white hover:text-neutral-950 dark:text-neutral-300 dark:hover:bg-neutral-900 dark:hover:text-white',
]

const avatarFrameClasses = (kind: ConversationKind) => [
  'flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-lg',
  kind === 'agent' && 'bg-transparent',
  kind === 'dm' && 'bg-neutral-200 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-200',
  kind === 'channel' && 'bg-white text-neutral-500 ring-1 ring-neutral-200 dark:bg-neutral-900 dark:text-neutral-300 dark:ring-neutral-700',
]

const previewFor = (kind: ConversationKind) => {
  if (kind === 'agent') return 'Assistant chat'
  if (kind === 'dm') return 'Direct message'
  return 'Channel'
}

const initials = (value: string) => value
  .split(/\s+/)
  .map(part => part[0])
  .join('')
  .slice(0, 2)
  .toUpperCase()

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
