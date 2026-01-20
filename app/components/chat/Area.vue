<template>
  <div :class="containerClasses">
    <!-- Header -->
    <header :class="headerClasses">
      <div class="flex items-center gap-3 min-w-0">
        <!-- Channel Icon -->
        <TooltipProvider :delay-duration="300">
          <TooltipRoot>
            <TooltipTrigger as-child>
              <button
                type="button"
                :class="channelIconClasses"
                @click="emit('toggleInfo')"
              >
                <Icon :name="channelIcon" :class="channelIconInnerClasses" />
              </button>
            </TooltipTrigger>
            <TooltipPortal>
              <TooltipContent :class="tooltipClasses" side="bottom" :side-offset="4">
                {{ channel.type === 'agent' ? 'AI Agent Channel' : channel.private ? 'Private Channel' : 'Public Channel' }}
                <TooltipArrow class="fill-olympus-elevated" />
              </TooltipContent>
            </TooltipPortal>
          </TooltipRoot>
        </TooltipProvider>

        <!-- Channel Info -->
        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-2">
            <h2 :class="titleClasses">{{ channel.name }}</h2>
            <SharedBadge v-if="channel.private" size="xs" variant="secondary">
              <Icon name="ph:lock-simple" class="w-2.5 h-2.5" />
            </SharedBadge>
            <SharedBadge v-if="channel.type === 'agent'" size="xs" variant="info">
              AI
            </SharedBadge>
          </div>
          <p v-if="channel.description && size !== 'sm'" :class="descriptionClasses">
            {{ channel.description }}
          </p>
        </div>

        <!-- Presence Indicator -->
        <div v-if="showPresence && channel.members?.length" :class="presenceClasses">
          <div class="flex -space-x-1.5">
            <div
              v-for="member in onlineMembers.slice(0, 3)"
              :key="member.id"
              class="w-6 h-6 rounded-full ring-2 ring-olympus-bg"
            >
              <SharedAgentAvatar :user="member" size="xs" :show-status="false" />
            </div>
          </div>
          <span class="text-xs text-olympus-text-muted">
            {{ onlineMembers.length }} online
          </span>
        </div>
      </div>

      <!-- Header Actions -->
      <div class="flex items-center gap-1">
        <!-- Search Toggle -->
        <TooltipProvider :delay-duration="300">
          <TooltipRoot>
            <TooltipTrigger as-child>
              <button
                type="button"
                :class="[headerButtonClasses, searchOpen && 'bg-olympus-surface text-olympus-text']"
                @click="toggleSearch"
              >
                <Icon name="ph:magnifying-glass" :class="headerIconClasses" />
              </button>
            </TooltipTrigger>
            <TooltipPortal>
              <TooltipContent :class="tooltipClasses" side="bottom" :side-offset="4">
                Search messages
                <span class="ml-2 text-olympus-text-subtle">⌘F</span>
                <TooltipArrow class="fill-olympus-elevated" />
              </TooltipContent>
            </TooltipPortal>
          </TooltipRoot>
        </TooltipProvider>

        <!-- Pinned Messages -->
        <TooltipProvider :delay-duration="300">
          <TooltipRoot>
            <TooltipTrigger as-child>
              <button
                type="button"
                :class="[headerButtonClasses, showPinned && 'bg-olympus-surface text-olympus-text']"
                @click="showPinned = !showPinned"
              >
                <Icon name="ph:push-pin" :class="headerIconClasses" />
                <span
                  v-if="pinnedCount > 0"
                  class="absolute -top-0.5 -right-0.5 w-4 h-4 rounded-full bg-olympus-primary text-[9px] font-medium text-white flex items-center justify-center"
                >
                  {{ pinnedCount > 9 ? '9+' : pinnedCount }}
                </span>
              </button>
            </TooltipTrigger>
            <TooltipPortal>
              <TooltipContent :class="tooltipClasses" side="bottom" :side-offset="4">
                Pinned messages
                <TooltipArrow class="fill-olympus-elevated" />
              </TooltipContent>
            </TooltipPortal>
          </TooltipRoot>
        </TooltipProvider>

        <!-- Thread Panel Toggle -->
        <TooltipProvider v-if="threadCount > 0" :delay-duration="300">
          <TooltipRoot>
            <TooltipTrigger as-child>
              <button
                type="button"
                :class="[headerButtonClasses, showThreads && 'bg-olympus-surface text-olympus-text']"
                @click="showThreads = !showThreads"
              >
                <Icon name="ph:chat-teardrop-text" :class="headerIconClasses" />
                <span
                  class="absolute -top-0.5 -right-0.5 w-4 h-4 rounded-full bg-blue-500 text-[9px] font-medium text-white flex items-center justify-center"
                >
                  {{ threadCount > 9 ? '9+' : threadCount }}
                </span>
              </button>
            </TooltipTrigger>
            <TooltipPortal>
              <TooltipContent :class="tooltipClasses" side="bottom" :side-offset="4">
                Active threads
                <TooltipArrow class="fill-olympus-elevated" />
              </TooltipContent>
            </TooltipPortal>
          </TooltipRoot>
        </TooltipProvider>

        <!-- Channel Info Toggle -->
        <TooltipProvider :delay-duration="300">
          <TooltipRoot>
            <TooltipTrigger as-child>
              <button
                type="button"
                :class="headerButtonClasses"
                @click="emit('toggleInfo')"
              >
                <Icon name="ph:sidebar-simple" :class="headerIconClasses" />
              </button>
            </TooltipTrigger>
            <TooltipPortal>
              <TooltipContent :class="tooltipClasses" side="bottom" :side-offset="4">
                Channel details
                <TooltipArrow class="fill-olympus-elevated" />
              </TooltipContent>
            </TooltipPortal>
          </TooltipRoot>
        </TooltipProvider>

        <!-- More Actions -->
        <DropdownMenuRoot>
          <DropdownMenuTrigger as-child>
            <button type="button" :class="headerButtonClasses">
              <Icon name="ph:dots-three" :class="headerIconClasses" />
            </button>
          </DropdownMenuTrigger>
          <DropdownMenuPortal>
            <DropdownMenuContent :class="dropdownClasses" align="end" :side-offset="4">
              <DropdownMenuItem :class="dropdownItemClasses" @click="emit('markAllRead')">
                <Icon name="ph:check-circle" class="w-4 h-4" />
                <span>Mark all as read</span>
              </DropdownMenuItem>
              <DropdownMenuItem :class="dropdownItemClasses" @click="emit('muteChannel')">
                <Icon name="ph:speaker-x" class="w-4 h-4" />
                <span>Mute channel</span>
              </DropdownMenuItem>
              <DropdownMenuSeparator class="h-px bg-olympus-border my-1" />
              <DropdownMenuItem :class="dropdownItemClasses" @click="emit('copyLink')">
                <Icon name="ph:link" class="w-4 h-4" />
                <span>Copy link</span>
                <span class="ml-auto text-xs text-olympus-text-subtle">⌘L</span>
              </DropdownMenuItem>
              <DropdownMenuItem :class="dropdownItemClasses" @click="emit('openSettings')">
                <Icon name="ph:gear" class="w-4 h-4" />
                <span>Channel settings</span>
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenuPortal>
        </DropdownMenuRoot>
      </div>
    </header>

    <!-- Search Panel -->
    <Transition name="slide-down">
      <div v-if="searchOpen" :class="searchPanelClasses">
        <div class="relative flex-1">
          <Icon
            name="ph:magnifying-glass"
            class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-olympus-text-muted"
          />
          <input
            ref="searchInputRef"
            v-model="searchQuery"
            type="text"
            placeholder="Search in channel..."
            :class="searchInputClasses"
            @keydown.escape="toggleSearch"
          />
          <div v-if="searchQuery" class="absolute right-3 top-1/2 -translate-y-1/2 flex items-center gap-2">
            <span class="text-xs text-olympus-text-muted">
              {{ searchResults.length }} results
            </span>
            <button
              type="button"
              class="p-0.5 rounded hover:bg-olympus-surface"
              @click="searchQuery = ''"
            >
              <Icon name="ph:x" class="w-3.5 h-3.5 text-olympus-text-muted" />
            </button>
          </div>
        </div>
        <div class="flex items-center gap-1">
          <button
            type="button"
            :class="searchNavButtonClasses"
            :disabled="searchResults.length === 0"
            @click="navigateSearch('prev')"
          >
            <Icon name="ph:caret-up" class="w-4 h-4" />
          </button>
          <button
            type="button"
            :class="searchNavButtonClasses"
            :disabled="searchResults.length === 0"
            @click="navigateSearch('next')"
          >
            <Icon name="ph:caret-down" class="w-4 h-4" />
          </button>
        </div>
      </div>
    </Transition>

    <!-- Main Content Area -->
    <div class="flex-1 flex overflow-hidden">
      <!-- Messages Area -->
      <div :class="messagesContainerClasses">
        <!-- Load More Button -->
        <Transition name="fade">
          <button
            v-if="hasMoreMessages && !loading"
            type="button"
            :class="loadMoreButtonClasses"
            :disabled="loadingMore"
            @click="emit('loadMore')"
          >
            <Icon v-if="loadingMore" name="ph:spinner" class="w-4 h-4 animate-spin" />
            <Icon v-else name="ph:arrow-up" class="w-4 h-4" />
            <span>{{ loadingMore ? 'Loading...' : 'Load older messages' }}</span>
          </button>
        </Transition>

        <!-- Loading State -->
        <template v-if="loading">
          <MessageSkeleton v-for="i in 5" :key="i" />
        </template>

        <!-- Empty State -->
        <SharedEmptyState
          v-else-if="messages.length === 0"
          :icon="emptyStateIcon"
          :title="emptyStateTitle"
          :description="emptyStateDescription"
          class="my-auto"
        >
          <template v-if="channel.type !== 'dm'" #action>
            <SharedButton size="sm" @click="emit('invite')">
              <Icon name="ph:user-plus" class="w-4 h-4" />
              Invite people
            </SharedButton>
          </template>
        </SharedEmptyState>

        <!-- Messages Content -->
        <template v-else>
          <TransitionGroup name="message-list" tag="div" class="space-y-1">
            <template v-for="(group, groupIndex) in groupedMessages" :key="group.date">
              <!-- Date Divider -->
              <div :class="dateDividerClasses">
                <div class="h-px flex-1 bg-olympus-border" />
                <span :class="dateDividerTextClasses">{{ formatDateDivider(group.date) }}</span>
                <div class="h-px flex-1 bg-olympus-border" />
              </div>

              <!-- Unread Divider -->
              <div
                v-if="unreadDividerIndex === groupIndex"
                :class="unreadDividerClasses"
              >
                <div class="h-px flex-1 bg-red-500/50" />
                <span class="px-3 text-xs font-medium text-red-400 bg-red-500/10 rounded-full py-0.5">
                  New messages
                </span>
                <div class="h-px flex-1 bg-red-500/50" />
              </div>

              <!-- Messages in Group -->
              <ChatMessage
                v-for="(message, messageIndex) in group.messages"
                :key="message.id"
                :message="message"
                :compact="isCompactMessage(group.messages, messageIndex)"
                :highlighted="highlightedMessageId === message.id"
                :show-avatar="shouldShowAvatar(group.messages, messageIndex)"
                @reply="emit('reply', message)"
                @edit="emit('edit', message)"
                @delete="emit('delete', message)"
                @react="emit('react', message, $event)"
                @thread="emit('openThread', message)"
              />
            </template>
          </TransitionGroup>
        </template>

        <!-- Typing Indicator -->
        <Transition name="slide-up">
          <ChatTypingIndicator
            v-if="typingUsers.length > 0"
            :users="typingUsers"
            :class="typingIndicatorClasses"
          />
        </Transition>

        <!-- Scroll Anchor -->
        <div ref="scrollAnchor" class="h-0" />
      </div>

      <!-- Thread Panel -->
      <Transition name="slide-left">
        <div v-if="showThreads && activeThread" :class="threadPanelClasses">
          <div class="flex items-center justify-between p-3 border-b border-olympus-border">
            <div class="flex items-center gap-2">
              <Icon name="ph:chat-teardrop-text" class="w-4 h-4 text-olympus-text-muted" />
              <span class="font-medium text-sm">Thread</span>
            </div>
            <button
              type="button"
              class="p-1.5 rounded-lg hover:bg-olympus-surface transition-colors"
              @click="showThreads = false"
            >
              <Icon name="ph:x" class="w-4 h-4 text-olympus-text-muted" />
            </button>
          </div>
          <div class="flex-1 overflow-y-auto p-3 space-y-2">
            <ChatMessage
              v-for="reply in activeThread.replies"
              :key="reply.id"
              :message="reply"
              compact
            />
          </div>
        </div>
      </Transition>

      <!-- Pinned Messages Panel -->
      <Transition name="slide-left">
        <div v-if="showPinned" :class="pinnedPanelClasses">
          <div class="flex items-center justify-between p-3 border-b border-olympus-border">
            <div class="flex items-center gap-2">
              <Icon name="ph:push-pin-fill" class="w-4 h-4 text-amber-400" />
              <span class="font-medium text-sm">Pinned messages</span>
              <SharedBadge size="xs">{{ pinnedMessages.length }}</SharedBadge>
            </div>
            <button
              type="button"
              class="p-1.5 rounded-lg hover:bg-olympus-surface transition-colors"
              @click="showPinned = false"
            >
              <Icon name="ph:x" class="w-4 h-4 text-olympus-text-muted" />
            </button>
          </div>
          <div v-if="pinnedMessages.length > 0" class="flex-1 overflow-y-auto p-3 space-y-2">
            <div
              v-for="message in pinnedMessages"
              :key="message.id"
              class="p-3 rounded-lg bg-olympus-surface/50 border border-olympus-border cursor-pointer hover:bg-olympus-surface transition-colors"
              @click="scrollToMessage(message.id)"
            >
              <div class="flex items-center gap-2 mb-1">
                <SharedAgentAvatar :user="message.author" size="xs" />
                <span class="text-xs font-medium">{{ message.author.name }}</span>
                <span class="text-xs text-olympus-text-subtle">
                  {{ formatMessageTime(message.timestamp) }}
                </span>
              </div>
              <p class="text-sm text-olympus-text-muted line-clamp-2">{{ message.content }}</p>
            </div>
          </div>
          <SharedEmptyState
            v-else
            icon="ph:push-pin"
            title="No pinned messages"
            description="Pin important messages to find them later"
            class="flex-1"
          />
        </div>
      </Transition>
    </div>

    <!-- New Messages Banner -->
    <Transition name="slide-up">
      <button
        v-if="newMessagesCount > 0 && !isAtBottom"
        type="button"
        :class="newMessagesBannerClasses"
        @click="scrollToBottom"
      >
        <Icon name="ph:arrow-down" class="w-4 h-4" />
        <span>{{ newMessagesCount }} new message{{ newMessagesCount > 1 ? 's' : '' }}</span>
      </button>
    </Transition>

    <!-- Jump to Latest Button -->
    <Transition name="scale">
      <button
        v-if="!isAtBottom && newMessagesCount === 0"
        type="button"
        :class="jumpToLatestClasses"
        @click="scrollToBottom"
      >
        <Icon name="ph:arrow-down" class="w-4 h-4" />
      </button>
    </Transition>

    <!-- Message Input -->
    <ChatMessageInput
      :channel="channel"
      :sending="sending"
      :reply-to="replyTo"
      :edit-message="editMessage"
      @send="handleSend"
      @cancel-reply="emit('cancelReply')"
      @cancel-edit="emit('cancelEdit')"
    />
  </div>
</template>

<script setup lang="ts">
import {
  TooltipArrow,
  TooltipContent,
  TooltipPortal,
  TooltipProvider,
  TooltipRoot,
  TooltipTrigger,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuPortal,
  DropdownMenuRoot,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from 'reka-ui'
import type { Channel, Message, User } from '~/types'

type AreaSize = 'sm' | 'md' | 'lg'
type AreaVariant = 'default' | 'compact' | 'split'

interface MessageGroup {
  date: string
  messages: Message[]
}

interface Thread {
  parentMessage: Message
  replies: Message[]
}

const props = withDefaults(defineProps<{
  // Core
  channel: Channel
  messages: Message[]

  // Appearance
  size?: AreaSize
  variant?: AreaVariant

  // State
  loading?: boolean
  loadingMore?: boolean
  sending?: boolean
  hasMoreMessages?: boolean

  // Display options
  showPresence?: boolean
  showDateDividers?: boolean
  compactMessages?: boolean

  // Message state
  replyTo?: Message | null
  editMessage?: Message | null
  highlightedMessageId?: string | null

  // Typing
  typingUsers?: User[]

  // Pinned
  pinnedMessages?: Message[]

  // Thread
  activeThread?: Thread | null

  // Unread
  unreadCount?: number
  lastReadMessageId?: string | null
}>(), {
  size: 'md',
  variant: 'default',
  loading: false,
  loadingMore: false,
  sending: false,
  hasMoreMessages: false,
  showPresence: true,
  showDateDividers: true,
  compactMessages: true,
  replyTo: null,
  editMessage: null,
  highlightedMessageId: null,
  typingUsers: () => [],
  pinnedMessages: () => [],
  activeThread: null,
  unreadCount: 0,
  lastReadMessageId: null,
})

const emit = defineEmits<{
  send: [content: string, attachments?: File[]]
  loadMore: []
  reply: [message: Message]
  edit: [message: Message]
  delete: [message: Message]
  react: [message: Message, emoji: string]
  openThread: [message: Message]
  toggleInfo: []
  markAllRead: []
  muteChannel: []
  copyLink: []
  openSettings: []
  invite: []
  cancelReply: []
  cancelEdit: []
}>()

// Refs
const scrollAnchor = ref<HTMLElement | null>(null)
const searchInputRef = ref<HTMLInputElement | null>(null)

// State
const searchOpen = ref(false)
const searchQuery = ref('')
const searchResultIndex = ref(0)
const showPinned = ref(false)
const showThreads = ref(false)
const isAtBottom = ref(true)
const newMessagesCount = ref(0)

// Size configuration
const sizeConfig: Record<AreaSize, {
  header: string
  title: string
  padding: string
  icon: string
}> = {
  sm: {
    header: 'h-12 px-3',
    title: 'text-sm',
    padding: 'p-3',
    icon: 'w-3.5 h-3.5',
  },
  md: {
    header: 'h-14 px-4',
    title: 'text-sm',
    padding: 'p-4',
    icon: 'w-4 h-4',
  },
  lg: {
    header: 'h-16 px-5',
    title: 'text-base',
    padding: 'p-5',
    icon: 'w-5 h-5',
  },
}

// Computed values
const pinnedCount = computed(() => props.pinnedMessages.length)
const threadCount = computed(() => props.activeThread?.replies.length || 0)

const onlineMembers = computed(() => {
  return props.channel.members?.filter(m => m.status === 'online') || []
})

const searchResults = computed(() => {
  if (!searchQuery.value.trim()) return []
  const query = searchQuery.value.toLowerCase()
  return props.messages.filter(m => m.content.toLowerCase().includes(query))
})

// Group messages by date
const groupedMessages = computed((): MessageGroup[] => {
  if (!props.showDateDividers) {
    return [{ date: 'all', messages: props.messages }]
  }

  const groups: MessageGroup[] = []
  let currentDate = ''
  let currentGroup: Message[] = []

  for (const message of props.messages) {
    const messageDate = new Date(message.timestamp).toDateString()
    if (messageDate !== currentDate) {
      if (currentGroup.length > 0) {
        groups.push({ date: currentDate, messages: currentGroup })
      }
      currentDate = messageDate
      currentGroup = [message]
    } else {
      currentGroup.push(message)
    }
  }

  if (currentGroup.length > 0) {
    groups.push({ date: currentDate, messages: currentGroup })
  }

  return groups
})

// Find unread divider position
const unreadDividerIndex = computed(() => {
  if (!props.lastReadMessageId || props.unreadCount === 0) return -1

  for (let i = 0; i < groupedMessages.value.length; i++) {
    const group = groupedMessages.value[i]
    const foundIndex = group.messages.findIndex(m => m.id === props.lastReadMessageId)
    if (foundIndex !== -1) {
      return i
    }
  }
  return -1
})

// Channel icon
const channelIcon = computed(() => {
  if (props.channel.type === 'agent') return 'ph:robot-fill'
  if (props.channel.type === 'dm') return 'ph:chat-circle-fill'
  if (props.channel.private) return 'ph:lock-simple-fill'
  return 'ph:hash'
})

// Empty state content
const emptyStateIcon = computed(() => {
  if (props.channel.type === 'agent') return 'ph:robot'
  if (props.channel.type === 'dm') return 'ph:chat-circle-dots'
  return 'ph:chats'
})

const emptyStateTitle = computed(() => {
  if (props.channel.type === 'agent') return 'Start a conversation with AI'
  if (props.channel.type === 'dm') return 'No messages yet'
  return `Welcome to #${props.channel.name}`
})

const emptyStateDescription = computed(() => {
  if (props.channel.type === 'agent') return 'Ask questions, get help, or just chat'
  if (props.channel.type === 'dm') return 'Send a message to start the conversation'
  return 'This is the beginning of the channel. Start by saying hello!'
})

// Container classes
const containerClasses = computed(() => [
  'flex flex-col bg-olympus-bg h-full relative',
  props.variant === 'compact' && 'max-w-4xl mx-auto',
])

// Header classes
const headerClasses = computed(() => [
  'border-b border-olympus-border flex items-center justify-between shrink-0',
  sizeConfig[props.size].header,
])

// Channel icon classes
const channelIconClasses = computed(() => [
  'rounded-lg flex items-center justify-center transition-all duration-150',
  'outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50',
  'hover:scale-105 cursor-pointer',
  props.size === 'sm' ? 'w-7 h-7' : props.size === 'lg' ? 'w-10 h-10' : 'w-8 h-8',
  props.channel.type === 'agent'
    ? 'bg-blue-500/20 hover:bg-blue-500/30'
    : 'bg-olympus-surface hover:bg-olympus-elevated',
])

const channelIconInnerClasses = computed(() => [
  sizeConfig[props.size].icon,
  props.channel.type === 'agent' ? 'text-blue-400' : 'text-olympus-text-muted',
])

// Title classes
const titleClasses = computed(() => [
  'font-semibold truncate',
  sizeConfig[props.size].title,
])

// Description classes
const descriptionClasses = computed(() => [
  'text-xs text-olympus-text-muted line-clamp-1',
])

// Presence classes
const presenceClasses = computed(() => [
  'flex items-center gap-2 px-3 py-1.5 rounded-lg bg-olympus-surface/50',
  props.size === 'sm' && 'hidden md:flex',
])

// Header button classes
const headerButtonClasses = computed(() => [
  'p-2 rounded-lg transition-colors duration-150 outline-none relative',
  'hover:bg-olympus-surface focus-visible:ring-2 focus-visible:ring-olympus-primary/50',
])

const headerIconClasses = computed(() => [
  sizeConfig[props.size].icon,
  'text-olympus-text-muted',
])

// Tooltip classes
const tooltipClasses = computed(() => [
  'z-50 bg-olympus-elevated border border-olympus-border rounded-lg',
  'px-2 py-1 text-xs shadow-lg',
  'animate-in fade-in-0 zoom-in-95 duration-100',
])

// Dropdown classes
const dropdownClasses = computed(() => [
  'min-w-48 bg-olympus-elevated border border-olympus-border rounded-xl',
  'shadow-xl p-1 z-50',
  'animate-in fade-in-0 zoom-in-95 duration-150',
])

const dropdownItemClasses = computed(() => [
  'flex items-center gap-2 px-3 py-2 text-sm rounded-lg cursor-pointer outline-none',
  'text-olympus-text-muted hover:bg-olympus-surface hover:text-olympus-text',
  'focus:bg-olympus-surface focus:text-olympus-text transition-colors duration-150',
])

// Search panel classes
const searchPanelClasses = computed(() => [
  'flex items-center gap-2 px-4 py-2 border-b border-olympus-border bg-olympus-surface/30',
])

const searchInputClasses = computed(() => [
  'w-full pl-9 pr-20 py-2 bg-olympus-bg border border-olympus-border rounded-lg',
  'text-sm text-olympus-text placeholder:text-olympus-text-subtle',
  'outline-none focus:border-olympus-primary/50 focus:ring-2 focus:ring-olympus-primary/20',
  'transition-all duration-150',
])

const searchNavButtonClasses = computed(() => [
  'p-1.5 rounded-lg transition-colors duration-150 outline-none',
  'hover:bg-olympus-surface disabled:opacity-50 disabled:cursor-not-allowed',
  'focus-visible:ring-2 focus-visible:ring-olympus-primary/50',
])

// Messages container classes
const messagesContainerClasses = computed(() => [
  'flex-1 overflow-y-auto flex flex-col',
  sizeConfig[props.size].padding,
  'space-y-1 scroll-smooth',
])

// Load more button classes
const loadMoreButtonClasses = computed(() => [
  'flex items-center justify-center gap-2 px-4 py-2 rounded-lg mb-4',
  'text-sm text-olympus-text-muted bg-olympus-surface/50',
  'hover:bg-olympus-surface hover:text-olympus-text',
  'transition-colors duration-150 w-full',
  'disabled:opacity-50 disabled:cursor-not-allowed',
])

// Date divider classes
const dateDividerClasses = computed(() => [
  'flex items-center gap-3 py-4',
])

const dateDividerTextClasses = computed(() => [
  'text-xs font-medium text-olympus-text-subtle px-2',
])

// Unread divider classes
const unreadDividerClasses = computed(() => [
  'flex items-center gap-3 py-2',
])

// Typing indicator classes
const typingIndicatorClasses = computed(() => [
  'mt-2',
])

// Thread panel classes
const threadPanelClasses = computed(() => [
  'w-80 border-l border-olympus-border bg-olympus-bg flex flex-col',
])

// Pinned panel classes
const pinnedPanelClasses = computed(() => [
  'w-80 border-l border-olympus-border bg-olympus-bg flex flex-col',
])

// New messages banner classes
const newMessagesBannerClasses = computed(() => [
  'absolute bottom-20 left-1/2 -translate-x-1/2 z-10',
  'flex items-center gap-2 px-4 py-2 rounded-full',
  'bg-olympus-primary text-white text-sm font-medium',
  'shadow-lg shadow-olympus-primary/30',
  'hover:bg-olympus-primary/90 transition-colors duration-150',
  'cursor-pointer',
])

// Jump to latest classes
const jumpToLatestClasses = computed(() => [
  'absolute bottom-20 right-4 z-10',
  'p-2 rounded-full',
  'bg-olympus-surface border border-olympus-border',
  'text-olympus-text-muted hover:text-olympus-text',
  'shadow-lg hover:bg-olympus-elevated',
  'transition-all duration-150 cursor-pointer',
])

// Helper functions
const isCompactMessage = (messages: Message[], index: number): boolean => {
  if (!props.compactMessages || index === 0) return false
  const current = messages[index]
  const previous = messages[index - 1]
  const timeDiff = new Date(current.timestamp).getTime() - new Date(previous.timestamp).getTime()
  return current.author.id === previous.author.id && timeDiff < 5 * 60 * 1000 // 5 minutes
}

const shouldShowAvatar = (messages: Message[], index: number): boolean => {
  return !isCompactMessage(messages, index)
}

const formatDateDivider = (dateStr: string): string => {
  if (dateStr === 'all') return ''
  const date = new Date(dateStr)
  const today = new Date()
  const yesterday = new Date(today)
  yesterday.setDate(yesterday.getDate() - 1)

  if (date.toDateString() === today.toDateString()) return 'Today'
  if (date.toDateString() === yesterday.toDateString()) return 'Yesterday'

  return date.toLocaleDateString('en-US', {
    weekday: 'long',
    month: 'long',
    day: 'numeric',
  })
}

const formatMessageTime = (date: Date): string => {
  return new Date(date).toLocaleTimeString('en-US', {
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  })
}

// Actions
const toggleSearch = () => {
  searchOpen.value = !searchOpen.value
  if (searchOpen.value) {
    nextTick(() => {
      searchInputRef.value?.focus()
    })
  } else {
    searchQuery.value = ''
  }
}

const navigateSearch = (direction: 'prev' | 'next') => {
  if (searchResults.value.length === 0) return
  if (direction === 'next') {
    searchResultIndex.value = (searchResultIndex.value + 1) % searchResults.value.length
  } else {
    searchResultIndex.value = searchResultIndex.value === 0
      ? searchResults.value.length - 1
      : searchResultIndex.value - 1
  }
  scrollToMessage(searchResults.value[searchResultIndex.value].id)
}

const scrollToMessage = (messageId: string) => {
  const element = document.querySelector(`[data-message-id="${messageId}"]`)
  element?.scrollIntoView({ behavior: 'smooth', block: 'center' })
}

const scrollToBottom = () => {
  scrollAnchor.value?.scrollIntoView({ behavior: 'smooth' })
  newMessagesCount.value = 0
  isAtBottom.value = true
}

const handleSend = (content: string, attachments?: File[]) => {
  emit('send', content, attachments)
  scrollToBottom()
}

// Message Skeleton component
const MessageSkeleton = defineComponent({
  name: 'MessageSkeleton',
  setup() {
    return () => h('div', { class: 'flex items-start gap-3 animate-pulse' }, [
      h(resolveComponent('SharedSkeleton'), { variant: 'avatar' }),
      h('div', { class: 'flex-1 space-y-2' }, [
        h('div', { class: 'flex items-center gap-2' }, [
          h(resolveComponent('SharedSkeleton'), { customClass: 'h-3 w-24' }),
          h(resolveComponent('SharedSkeleton'), { customClass: 'h-2 w-12' }),
        ]),
        h(resolveComponent('SharedSkeleton'), { customClass: 'h-4 w-full' }),
        h(resolveComponent('SharedSkeleton'), { customClass: 'h-4 w-3/4' }),
      ]),
    ])
  },
})

// Keyboard shortcuts
onMounted(() => {
  const handleKeydown = (e: KeyboardEvent) => {
    if ((e.metaKey || e.ctrlKey) && e.key === 'f') {
      e.preventDefault()
      toggleSearch()
    }
  }
  window.addEventListener('keydown', handleKeydown)
  onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown)
  })
})

// Watch for new messages
watch(() => props.messages.length, (newLen, oldLen) => {
  if (newLen > oldLen && !isAtBottom.value) {
    newMessagesCount.value += newLen - oldLen
  }
})
</script>

<style scoped>
/* Slide down transition */
.slide-down-enter-active,
.slide-down-leave-active {
  transition: all 0.2s ease;
}

.slide-down-enter-from,
.slide-down-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}

/* Slide up transition */
.slide-up-enter-active,
.slide-up-leave-active {
  transition: all 0.3s ease;
}

.slide-up-enter-from,
.slide-up-leave-to {
  opacity: 0;
  transform: translateY(20px);
}

/* Slide left transition */
.slide-left-enter-active,
.slide-left-leave-active {
  transition: all 0.3s ease;
}

.slide-left-enter-from,
.slide-left-leave-to {
  opacity: 0;
  transform: translateX(100%);
}

/* Scale transition */
.scale-enter-active,
.scale-leave-active {
  transition: all 0.2s ease;
}

.scale-enter-from,
.scale-leave-to {
  opacity: 0;
  transform: scale(0.9);
}

/* Fade transition */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

/* Message list transitions */
.message-list-enter-active,
.message-list-leave-active {
  transition: all 0.3s ease;
}

.message-list-enter-from {
  opacity: 0;
  transform: translateY(10px);
}

.message-list-leave-to {
  opacity: 0;
  transform: translateX(-20px);
}

.message-list-move {
  transition: transform 0.3s ease;
}

/* Custom scrollbar */
::-webkit-scrollbar {
  width: 6px;
}

::-webkit-scrollbar-track {
  background: transparent;
}

::-webkit-scrollbar-thumb {
  background: oklch(var(--olympus-border));
  border-radius: 3px;
}

::-webkit-scrollbar-thumb:hover {
  background: oklch(var(--olympus-text-subtle));
}
</style>
