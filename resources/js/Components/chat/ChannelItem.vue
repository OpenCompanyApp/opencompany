<template>
  <!-- With Context Menu -->
  <button
    type="button"
    :class="containerClasses"
    @click="handleClick"
    @contextmenu="handleContextMenu"
  >
    <!-- Left: Avatar / Icon -->
    <div class="shrink-0">
      <!-- DM: Show user avatar -->
      <AgentAvatar
        v-if="channel.type === 'dm' && otherMember"
        :user="otherMember"
        size="md"
        :show-status="true"
        :show-tooltip="false"
      />
      <!-- Channel / External: Colored icon circle -->
      <div v-else :class="iconCircleClasses">
        <Icon :name="channelIcon" :class="iconInnerClasses" />
      </div>
    </div>

    <!-- Center: Name + Preview -->
    <div class="flex-1 min-w-0">
      <!-- Row 1: Name + indicators + timestamp -->
      <div class="flex items-center justify-between gap-2">
        <div class="flex items-center gap-1.5 min-w-0">
          <span :class="nameClasses">{{ channel.name }}</span>
          <!-- Type indicator for channels -->
          <Icon
            v-if="channel.type === 'external' && !channelProviderIcon"
            name="ph:plug"
            class="w-3 h-3 text-neutral-400 shrink-0"
          />
          <Icon
            v-if="channel.pinned"
            name="ph:push-pin-fill"
            class="w-3 h-3 text-neutral-400 shrink-0"
          />
          <Icon
            v-if="channel.muted"
            name="ph:speaker-x-fill"
            class="w-3 h-3 text-neutral-400 shrink-0"
          />
        </div>
        <span :class="timestampClasses">{{ formattedTimestamp }}</span>
      </div>

      <!-- Row 2: Message preview + unread badge -->
      <div class="flex items-center justify-between gap-2 mt-0.5">
        <span class="text-[13px] leading-tight text-neutral-500 dark:text-neutral-400 truncate">
          <!-- Typing indicator -->
          <template v-if="isTyping">
            <span class="text-blue-500 dark:text-blue-400 flex items-center gap-1">
              <span class="flex gap-0.5">
                <span class="w-1 h-1 bg-blue-500 dark:bg-blue-400 rounded-full animate-bounce" style="animation-delay: 0ms" />
                <span class="w-1 h-1 bg-blue-500 dark:bg-blue-400 rounded-full animate-bounce" style="animation-delay: 150ms" />
                <span class="w-1 h-1 bg-blue-500 dark:bg-blue-400 rounded-full animate-bounce" style="animation-delay: 300ms" />
              </span>
              {{ typingText }}
            </span>
          </template>
          <!-- Draft indicator -->
          <template v-else-if="draft">
            <span class="text-red-500 dark:text-red-400 font-medium">Draft: </span>
            <span class="text-neutral-400 dark:text-neutral-500">{{ draftPreview }}</span>
          </template>
          <!-- Message preview -->
          <template v-else-if="channel.latestMessage">
            <span v-if="showAuthorName" class="font-medium text-neutral-600 dark:text-neutral-300">{{ previewAuthorName }}: </span>{{ truncatedContent }}
          </template>
          <template v-else>
            <span class="italic text-neutral-400 dark:text-neutral-500">No messages yet</span>
          </template>
        </span>

        <!-- Unread badge -->
        <span
          v-if="hasUnread"
          :class="unreadBadgeClasses"
        >
          {{ unreadLabel }}
        </span>
      </div>
    </div>
  </button>

  <!-- Context Menu -->
  <ContextMenu v-model:open="contextMenuOpen" :items="contextMenuDropdownItems">
    <div
      ref="contextMenuRef"
      :style="contextMenuStyle"
      class="fixed w-px h-px pointer-events-none"
    />
  </ContextMenu>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Icon from '@/Components/shared/Icon.vue'
import AgentAvatar from '@/Components/shared/AgentAvatar.vue'
import ContextMenu from '@/Components/shared/ContextMenu.vue'
import type { Channel, User, ChannelMessagePreview } from '@/types'

const currentUserId = computed(() => (usePage().props.auth as any)?.user?.id ?? '')

interface ContextMenuItem {
  label: string
  icon?: string
  shortcut?: string
  variant?: 'default' | 'danger'
  disabled?: boolean
  action?: string
}

const props = withDefaults(defineProps<{
  channel: Channel
  selected?: boolean
  muted?: boolean
  pinned?: boolean
  draft?: boolean
  draftPreview?: string
  typingUsers?: User[]
  contextMenuItems?: ContextMenuItem[]
}>(), {
  selected: false,
  muted: false,
  pinned: false,
  draft: false,
  draftPreview: '',
  contextMenuItems: () => [
    { label: 'Mark as read', icon: 'ph:check', action: 'markRead' },
    { label: 'Pin chat', icon: 'ph:push-pin', action: 'pin' },
    { label: 'Mute', icon: 'ph:speaker-x', action: 'mute' },
    { label: 'Copy link', icon: 'ph:link', action: 'copyLink', shortcut: '⌘L' },
    { label: 'Leave', icon: 'ph:sign-out', variant: 'danger', action: 'leave' },
  ],
})

const emit = defineEmits<{
  click: [channel: Channel]
  contextAction: [action: string, channel: Channel]
}>()

// Context menu state
const contextMenuOpen = ref(false)
const contextMenuPosition = ref({ x: 0, y: 0 })
const contextMenuRef = ref<HTMLElement | null>(null)

// DM: extract the other member
const otherMember = computed(() => {
  if (props.channel.type !== 'dm') return null
  return props.channel.members?.find(m => m.id !== currentUserId.value) ?? props.channel.members?.[0] ?? null
})

// Channel icon
const channelProviderIcon = computed(() => {
  if (props.channel.type !== 'external') return null
  const providerIcons: Record<string, string> = {
    telegram: 'ph:telegram-logo',
    slack: 'ph:slack-logo',
    discord: 'ph:discord-logo',
    google_chat: 'ph:google-logo',
    whatsapp: 'ph:whatsapp-logo',
  }
  const provider = props.channel.externalProvider || (props.channel as any).external_provider || ''
  return providerIcons[provider] || null
})

const channelIcon = computed(() => {
  if (props.channel.type === 'dm') return 'ph:chat-circle'
  if (props.channel.type === 'external') {
    return channelProviderIcon.value || 'ph:plug'
  }
  if (props.channel.private) return 'ph:lock-simple'
  return 'ph:hash'
})

// Icon circle colors
const iconCircleClasses = computed(() => {
  const base = 'w-10 h-10 rounded-full flex items-center justify-center shrink-0'
  if (props.channel.type === 'external') {
    return [base, 'bg-violet-100 dark:bg-violet-900/30']
  }
  if (props.channel.private) {
    return [base, 'bg-amber-100 dark:bg-amber-900/30']
  }
  return [base, 'bg-blue-100 dark:bg-blue-900/30']
})

const iconInnerClasses = computed(() => {
  const base = 'w-5 h-5'
  if (props.channel.type === 'external') {
    return [base, 'text-violet-600 dark:text-violet-400']
  }
  if (props.channel.private) {
    return [base, 'text-amber-600 dark:text-amber-400']
  }
  return [base, 'text-blue-600 dark:text-blue-400']
})

// Unread state
const hasUnread = computed(() => (props.channel.unreadCount || 0) > 0)
const unreadLabel = computed(() => {
  const count = props.channel.unreadCount || 0
  if (count > 99) return '99+'
  return String(count)
})

// Typing state
const isTyping = computed(() => (props.typingUsers?.length || 0) > 0)
const typingText = computed(() => {
  if (!props.typingUsers?.length) return ''
  if (props.typingUsers.length === 1) {
    return props.typingUsers[0].name.split(' ')[0] + ' is typing...'
  }
  return `${props.typingUsers.length} people typing...`
})

// Message preview
const showAuthorName = computed(() => {
  if (!props.channel.latestMessage?.author) return false
  // Show author name for channels (not DMs, since you know who you're talking to)
  return props.channel.type !== 'dm'
})

const previewAuthorName = computed(() => {
  const author = props.channel.latestMessage?.author
  if (!author) return ''
  return author.name.split(' ')[0]
})

const truncatedContent = computed(() => {
  const content = props.channel.latestMessage?.content
  if (!content) return ''
  // Strip markdown syntax for clean preview
  const plain = content
    .replace(/```[\s\S]*?```/g, '[code]')
    .replace(/`[^`]+`/g, '[code]')
    .replace(/!\[[^\]]*\]\([^)]*\)/g, '[image]')
    .replace(/\[[^\]]*\]\([^)]*\)/g, (match) => match.replace(/\[([^\]]*)\]\([^)]*\)/, '$1'))
    .replace(/[#*_~>`]/g, '')
    .replace(/\n+/g, ' ')
    .trim()
  return plain.length > 60 ? plain.substring(0, 60) + '...' : plain
})

// Timestamp formatting (Telegram-style)
const formattedTimestamp = computed(() => {
  const ts = props.channel.latestMessage?.timestamp || props.channel.lastMessageAt
  if (!ts) return ''
  return formatTelegramTimestamp(ts)
})

function formatTelegramTimestamp(date: Date | string): string {
  const d = new Date(date)
  const now = new Date()
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate())
  const messageDay = new Date(d.getFullYear(), d.getMonth(), d.getDate())
  const diffDays = Math.floor((today.getTime() - messageDay.getTime()) / 86400000)

  // Today: show time "14:23"
  if (diffDays === 0) {
    return d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: false })
  }

  // Yesterday
  if (diffDays === 1) {
    return 'Yesterday'
  }

  // This week: day name "Mon"
  if (diffDays < 7) {
    return d.toLocaleDateString('en-US', { weekday: 'short' })
  }

  // This year: "Jan 15"
  if (d.getFullYear() === now.getFullYear()) {
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
  }

  // Older: "Jan 15, 2025"
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

// Name styling
const nameClasses = computed(() => [
  'truncate text-sm',
  hasUnread.value
    ? 'font-semibold text-neutral-900 dark:text-white'
    : 'font-medium text-neutral-700 dark:text-neutral-200',
])

// Timestamp styling
const timestampClasses = computed(() => [
  'text-xs shrink-0',
  hasUnread.value
    ? 'font-medium text-neutral-900 dark:text-white'
    : 'text-neutral-400 dark:text-neutral-500',
])

// Unread badge styling
const unreadBadgeClasses = computed(() => [
  'inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full text-[11px] font-semibold shrink-0',
  props.muted
    ? 'bg-neutral-200 dark:bg-neutral-700 text-neutral-500 dark:text-neutral-400'
    : 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900',
])

// Container classes
const containerClasses = computed(() => {
  const classes = [
    'w-full flex items-center gap-3 px-3 py-3 rounded-xl',
    'transition-all duration-150 text-left group outline-none',
    'focus-visible:ring-1 focus-visible:ring-neutral-400 dark:focus-visible:ring-neutral-500',
  ]

  if (props.selected) {
    classes.push('bg-neutral-100 dark:bg-neutral-800')
  } else {
    classes.push('hover:bg-neutral-50 dark:hover:bg-neutral-800/50')
  }

  if (props.muted && !props.selected) {
    classes.push('opacity-60 hover:opacity-80')
  }

  return classes
})

// Context menu
const contextMenuDropdownItems = computed(() => [
  props.contextMenuItems.map(item => ({
    label: item.label,
    icon: item.icon,
    shortcut: item.shortcut,
    color: item.variant === 'danger' ? 'error' as const : undefined,
    disabled: item.disabled,
    click: () => handleContextAction(item),
  })),
])

const contextMenuStyle = computed(() => ({
  position: 'fixed' as const,
  top: `${contextMenuPosition.value.y}px`,
  left: `${contextMenuPosition.value.x}px`,
}))

// Handlers
const handleClick = () => {
  emit('click', props.channel)
}

const handleContextMenu = (event: MouseEvent) => {
  if (!props.contextMenuItems?.length) return
  event.preventDefault()
  contextMenuPosition.value = { x: event.clientX, y: event.clientY }
  contextMenuOpen.value = true
}

const handleContextAction = (item: ContextMenuItem) => {
  if (item.disabled) return
  if (item.action) {
    emit('contextAction', item.action, props.channel)
  }
  contextMenuOpen.value = false
}
</script>

<style scoped>
@keyframes typing-bounce {
  0%, 60%, 100% {
    transform: translateY(0);
  }
  30% {
    transform: translateY(-3px);
  }
}

.animate-bounce {
  animation: typing-bounce 1s ease-in-out infinite;
}
</style>
