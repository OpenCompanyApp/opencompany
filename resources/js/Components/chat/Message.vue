<template>
  <div
    :class="containerClasses"
    :data-message-id="message.id"
    @mouseenter="isHovered = true"
    @mouseleave="isHovered = false"
  >
    <!-- Reply Context -->
    <div v-if="message.replyTo" :class="replyContextClasses">
      <div :class="replyLineClasses" />
      <button
        type="button"
        :class="replyButtonClasses"
        @click="handleReplyClick"
      >
        <SharedAgentAvatar
          :user="message.replyTo.author"
          size="xs"
          :show-status="false"
        />
        <span class="font-medium text-neutral-500 dark:text-neutral-300">
          {{ message.replyTo.author.name }}
        </span>
        <span class="truncate text-neutral-400 dark:text-neutral-400">
          {{ truncateReply(message.replyTo.content) }}
        </span>
      </button>
    </div>

    <!-- Approval Card (special message type) -->
    <ChatApprovalCard
      v-if="message.isApprovalRequest && message.approvalRequest"
      :request="message.approvalRequest"
      :author="message.author"
      :timestamp="message.timestamp"
      :size="size"
      @approve="$emit('approve', message)"
      @reject="$emit('reject', message)"
    />

    <!-- Regular Message -->
    <div v-else :class="messageWrapperClasses">
      <!-- Avatar -->
      <SharedAgentAvatar
        v-if="showAvatar"
        :user="message.author"
        :size="avatarSize"
        :show-status="showAvatarStatus"
      />
      <div v-else :class="avatarSpacerClasses" />

      <!-- Content -->
      <div :class="contentClasses">
        <!-- Header -->
        <div v-if="showHeader" :class="headerClasses">
          <!-- Author Name -->
          <Link
            :href="message.author.type === 'agent' ? `/agent/${message.author.id}` : `/profile/${message.author.id}`"
            :class="authorNameClasses"
          >
            {{ message.author.name }}
          </Link>

          <!-- Agent Badge -->
          <Badge
            v-if="message.author.type === 'agent'"
            color="primary"
            variant="subtle"
            size="xs"
          >
            <Icon name="ph:robot" class="w-2.5 h-2.5 mr-0.5" />
            Agent
          </Badge>

          <!-- Status Badge -->
          <SharedStatusBadge
            v-if="message.author.type === 'agent' && message.author.status"
            :status="message.author.status"
            size="xs"
          />

          <!-- Timestamp -->
          <Tooltip :delay-duration="300" :side-offset="5">
            <template #content>{{ formatFullDate(message.timestamp) }}</template>
            <span :class="timestampClasses">
              {{ formatTime(message.timestamp) }}
            </span>
          </Tooltip>

          <!-- Edited indicator -->
          <span v-if="message.editedAt" class="text-xs text-neutral-400 dark:text-neutral-400">
            (edited)
          </span>

          <!-- Pinned indicator -->
          <Tooltip v-if="message.isPinned" :delay-duration="200">
            <template #content>Pinned{{ message.pinnedBy ? ` by ${message.pinnedBy.name}` : '' }}</template>
            <Icon
              name="ph:push-pin-fill"
              class="w-3 h-3 text-amber-500"
            />
          </Tooltip>
        </div>

        <!-- Message Body -->
        <div :class="bodyClasses">
          <!-- Text content -->
          <p v-if="!isEditing" :class="textClasses" v-html="formattedContent" />

          <!-- Edit mode -->
          <div v-else class="space-y-2">
            <textarea
              ref="editInputRef"
              v-model="editContent"
              :class="editInputClasses"
              @keydown.enter.exact.prevent="handleSaveEdit"
              @keydown.escape="cancelEdit"
            />
            <div class="flex items-center gap-2">
              <button
                type="button"
                class="px-3 py-1.5 text-xs font-medium bg-neutral-900 dark:bg-neutral-100 text-white dark:text-neutral-900 rounded-lg transition-colors duration-150 hover:bg-neutral-800 dark:hover:bg-neutral-200"
                @click="handleSaveEdit"
              >
                Save
              </button>
              <button
                type="button"
                class="px-3 py-1.5 text-xs font-medium text-neutral-500 dark:text-neutral-300 rounded-lg transition-colors duration-150 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-700"
                @click="cancelEdit"
              >
                Cancel
              </button>
              <span class="text-xs text-neutral-400 dark:text-neutral-400 ml-auto">
                <kbd class="px-1 py-0.5 bg-neutral-100 dark:bg-neutral-700 border border-neutral-200 dark:border-neutral-600 rounded text-[10px]">Esc</kbd> to cancel,
                <kbd class="px-1 py-0.5 bg-neutral-100 dark:bg-neutral-700 border border-neutral-200 dark:border-neutral-600 rounded text-[10px]">Enter</kbd> to save
              </span>
            </div>
          </div>
        </div>

        <!-- Attachments -->
        <div v-if="message.attachments?.length" :class="attachmentsClasses">
          <template v-for="attachment in message.attachments" :key="attachment.id">
            <!-- Image attachments render inline -->
            <img
              v-if="attachment.mime_type?.startsWith('image/')"
              :src="attachment.url"
              :alt="attachment.original_name || attachment.name"
              class="max-w-full rounded-lg my-2"
              loading="lazy"
            />
            <!-- Non-image attachments render as download links -->
            <div v-else :class="attachmentClasses">
              <Icon :name="getAttachmentIcon(attachment.type)" class="w-4 h-4 text-neutral-500 dark:text-neutral-300" />
              <span class="text-sm truncate flex-1">{{ attachment.name }}</span>
              <span class="text-xs text-neutral-400 dark:text-neutral-400">{{ formatFileSize(attachment.size) }}</span>
              <button
                type="button"
                class="p-1.5 rounded-lg transition-colors duration-150 hover:bg-neutral-100 dark:hover:bg-neutral-700"
                @click="handleDownload(attachment)"
              >
                <Icon name="ph:download-simple" class="w-4 h-4 text-neutral-500 dark:text-neutral-300" />
              </button>
            </div>
          </template>
        </div>

        <!-- Code Blocks -->
        <div v-if="message.codeBlocks?.length" class="space-y-3 mt-2">
          <div
            v-for="(block, index) in message.codeBlocks"
            :key="index"
            :class="codeBlockClasses"
          >
            <div class="flex items-center justify-between px-3 py-2 border-b border-neutral-200 dark:border-neutral-700">
              <span class="text-xs font-medium text-neutral-500 dark:text-neutral-300">{{ block.language }}</span>
              <button
                type="button"
                class="p-1.5 rounded-lg text-neutral-500 dark:text-neutral-300 transition-colors duration-150 hover:bg-neutral-100 dark:hover:bg-neutral-700 hover:text-neutral-900 dark:hover:text-white"
                :class="copiedCode === block.code && 'text-green-600 hover:text-green-600'"
                @click="copyCode(block.code)"
              >
                <Icon :name="copiedCode === block.code ? 'ph:check' : 'ph:copy'" class="w-4 h-4" />
              </button>
            </div>
            <pre class="p-3 text-sm font-mono overflow-x-auto"><code v-html="highlight(block.code, block.language)" /></pre>
          </div>
        </div>

        <!-- Agent Activity Log -->
        <div
          v-if="message.author.type === 'agent' && message.author.status === 'working' && message.author.activityLog?.length"
          class="mt-3"
        >
          <SharedActivityLog :steps="message.author.activityLog" :size="size" />
        </div>

        <!-- Reactions -->
        <div v-if="message.reactions?.length" :class="reactionsClasses">
          <button
            v-for="reaction in groupedReactions"
            :key="reaction.emoji"
            type="button"
            :class="reactionButtonClasses(reaction.hasReacted)"
            @click="handleReaction(reaction.emoji)"
          >
            <span>{{ reaction.emoji }}</span>
            <span class="text-xs">{{ reaction.count }}</span>
          </button>
          <SharedEmojiPicker side="top" align="start" @select="handleReaction">
            <button
              type="button"
              :class="addReactionButtonClasses"
            >
              <Icon name="ph:smiley" class="w-4 h-4" />
            </button>
          </SharedEmojiPicker>
        </div>

        <!-- Thread Preview -->
        <button
          v-if="message.threadCount && message.threadCount > 0"
          type="button"
          :class="threadPreviewClasses"
          @click="$emit('openThread', message)"
        >
          <div class="flex -space-x-1.5">
            <SharedAgentAvatar
              v-for="user in message.threadParticipants?.slice(0, 3)"
              :key="user.id"
              :user="user"
              size="xs"
              :show-status="false"
            />
          </div>
          <span class="text-neutral-900 dark:text-white font-medium">
            {{ message.threadCount }} {{ message.threadCount === 1 ? 'reply' : 'replies' }}
          </span>
          <span v-if="message.lastThreadReplyAt" class="text-neutral-400 dark:text-neutral-400">
            {{ formatRelativeTime(message.lastThreadReplyAt) }}
          </span>
        </button>
      </div>

      <!-- Hover Actions -->
      <Transition name="fade">
        <div v-if="isHovered && !isEditing" :class="hoverActionsClasses">
          <!-- Emoji reaction -->
          <SharedEmojiPicker side="top" align="start" @select="handleReaction">
            <Tooltip :delay-duration="200">
              <template #content>Add reaction</template>
              <button
                type="button"
                :class="actionButtonClasses"
              >
                <Icon name="ph:smiley" class="w-4 h-4" />
              </button>
            </Tooltip>
          </SharedEmojiPicker>

          <!-- Reply -->
          <Tooltip :delay-duration="200">
            <template #content>Reply</template>
            <button
              type="button"
              :class="actionButtonClasses"
              @click="$emit('reply', message)"
            >
              <Icon name="ph:arrow-bend-up-left" class="w-4 h-4" />
            </button>
          </Tooltip>

          <!-- Thread -->
          <Tooltip :delay-duration="200">
            <template #content>Reply in thread</template>
            <button
              type="button"
              :class="actionButtonClasses"
              @click="$emit('openThread', message)"
            >
              <Icon name="ph:chat-circle" class="w-4 h-4" />
            </button>
          </Tooltip>

          <!-- More Actions -->
          <DropdownMenu :items="moreActionsDropdownItems" align="end">
            <button type="button" :class="actionButtonClasses">
              <Icon name="ph:dots-three" class="w-4 h-4" />
            </button>
          </DropdownMenu>
        </div>
      </Transition>
    </div>

    <!-- Delivery Status -->
    <div v-if="showDeliveryStatus" :class="deliveryStatusClasses">
      <Icon :name="deliveryIcon" :class="deliveryIconClasses" />
      <span v-if="showDeliveryLabel">{{ deliveryLabel }}</span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, nextTick } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useHighlight } from '@/composables/useHighlight'
import Icon from '@/Components/shared/Icon.vue'
import Badge from '@/Components/shared/Badge.vue'
import Tooltip from '@/Components/shared/Tooltip.vue'
import DropdownMenu from '@/Components/shared/DropdownMenu.vue'
import SharedAgentAvatar from '@/Components/shared/AgentAvatar.vue'
import SharedStatusBadge from '@/Components/shared/StatusBadge.vue'
import SharedEmojiPicker from '@/Components/shared/EmojiPicker.vue'
import SharedActivityLog from '@/Components/shared/ActivityLog.vue'
import ChatApprovalCard from '@/Components/chat/ApprovalCard.vue'
import type { Message, User } from '@/types'

type MessageSize = 'sm' | 'md' | 'lg'
type MessageVariant = 'default' | 'compact' | 'bubble'
type DeliveryStatus = 'sending' | 'sent' | 'delivered' | 'read' | 'failed'

interface MessageAttachment {
  id: string
  name: string
  type: string
  size: number
  url?: string
}

interface MessageReaction {
  emoji: string
  users: User[]
}

interface MoreAction {
  label: string
  icon: string
  shortcut?: string
  variant?: 'default' | 'danger'
  disabled?: boolean
  action: string
}

const props = withDefaults(defineProps<{
  // Core
  message: Message

  // Appearance
  size?: MessageSize
  variant?: MessageVariant

  // Display options
  showAvatar?: boolean
  showAvatarStatus?: boolean
  showHeader?: boolean
  showDeliveryStatus?: boolean
  showDeliveryLabel?: boolean

  // Context
  isOwn?: boolean
  isFirstInGroup?: boolean
  isLastInGroup?: boolean
  compact?: boolean
  highlighted?: boolean

  // Search
  searchQuery?: string

  // State
  deliveryStatus?: DeliveryStatus
}>(), {
  size: 'md',
  variant: 'default',
  showAvatar: true,
  showAvatarStatus: true,
  showHeader: true,
  showDeliveryStatus: false,
  showDeliveryLabel: false,
  isOwn: false,
  isFirstInGroup: true,
  isLastInGroup: true,
  compact: false,
  highlighted: false,
  searchQuery: '',
  deliveryStatus: 'delivered',
})

const emit = defineEmits<{
  reply: [message: Message]
  openThread: [message: Message]
  reaction: [message: Message, emoji: string]
  edit: [message: Message, content: string]
  delete: [message: Message]
  pin: [message: Message]
  approve: [message: Message]
  reject: [message: Message]
  authorClick: [user: User]
  replyClick: [message: Message]
}>()

const { highlight } = useHighlight()

const isHovered = ref(false)
const isEditing = ref(false)
const editContent = ref('')
const editInputRef = ref<HTMLTextAreaElement | null>(null)
const showEmojiPicker = ref(false)
const copiedCode = ref<string | null>(null)

// Size configurations
const sizeConfig: Record<MessageSize, {
  avatar: 'sm' | 'md'
  text: string
  gap: string
  padding: string
}> = {
  sm: {
    avatar: 'sm',
    text: 'text-xs',
    gap: 'gap-2',
    padding: 'py-0.5 px-1',
  },
  md: {
    avatar: 'md',
    text: 'text-sm',
    gap: 'gap-3',
    padding: 'py-1 px-2',
  },
  lg: {
    avatar: 'md',
    text: 'text-base',
    gap: 'gap-4',
    padding: 'py-2 px-3',
  },
}

const avatarSize = computed(() => sizeConfig[props.size].avatar)

// Grouped reactions
const groupedReactions = computed(() => {
  if (!props.message.reactions) return []

  const groups: Record<string, { emoji: string; count: number; users: User[]; hasReacted: boolean }> = {}

  props.message.reactions.forEach((reaction: MessageReaction) => {
    if (!reaction?.emoji) return
    if (!groups[reaction.emoji]) {
      groups[reaction.emoji] = {
        emoji: reaction.emoji,
        count: 0,
        users: [],
        hasReacted: false,
      }
    }
    const users = reaction.users || []
    groups[reaction.emoji].count += users.length
    groups[reaction.emoji].users.push(...users)
  })

  return Object.values(groups)
})

// More actions menu
const moreActions = computed((): MoreAction[] => [
  { label: 'Edit message', icon: 'ph:pencil-simple', action: 'edit', shortcut: 'E' },
  { label: props.message.isPinned ? 'Unpin message' : 'Pin message', icon: props.message.isPinned ? 'ph:push-pin-slash' : 'ph:push-pin', action: 'pin' },
  { label: 'Copy text', icon: 'ph:copy', action: 'copy', shortcut: '⌘C' },
  { label: 'Copy link', icon: 'ph:link', action: 'copyLink' },
  { label: 'Mark unread', icon: 'ph:circle', action: 'markUnread' },
  { label: 'Delete message', icon: 'ph:trash', action: 'delete', variant: 'danger' },
])

// More actions dropdown items for UDropdownMenu
const moreActionsDropdownItems = computed(() => [
  moreActions.value.map(action => ({
    label: action.label,
    icon: action.icon,
    shortcut: action.shortcut,
    disabled: action.disabled,
    color: action.variant === 'danger' ? 'error' as const : undefined,
    click: () => handleMoreAction(action),
  })),
])

// Format message content with full markdown support
const formattedContent = computed(() => {
  let content = props.message.content

  // Parse markdown code blocks with syntax highlighting (```language ... ```)
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

  // Convert @mentions to styled spans
  content = content.replace(/@(\w+)/g, '<span class="text-neutral-900 dark:text-white font-medium cursor-pointer hover:underline">@$1</span>')

  // Convert markdown images ![alt](url)
  content = content.replace(
    /!\[([^\]]*)\]\(([^)]+)\)/g,
    '<img src="$2" alt="$1" class="max-w-full rounded-lg my-2" loading="lazy" />'
  )

  // Convert markdown links [text](url)
  content = content.replace(
    /\[([^\]]+)\]\(([^)]+)\)/g,
    '<a href="$2" target="_blank" rel="noopener" class="text-blue-500 hover:underline">$1</a>'
  )

  // Convert URLs to links (skip URLs already inside href or src attributes)
  content = content.replace(
    /(?<!="|='|=)(https?:\/\/[^\s<"']+)/g,
    '<a href="$1" target="_blank" rel="noopener" class="text-blue-500 hover:underline">$1</a>'
  )

  // Convert **bold** text
  content = content.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')

  // Convert *italic* text
  content = content.replace(/\*(.+?)\*/g, '<em>$1</em>')

  // Convert `code` text (inline code - not inside code blocks)
  content = content.replace(/`([^`\n]+?)`/g, '<code class="px-1.5 py-0.5 bg-neutral-100 dark:bg-neutral-700 rounded text-neutral-900 dark:text-white text-xs font-mono">$1</code>')

  // Convert numbered lists (1. item)
  content = content.replace(/^(\d+)\.\s+(.+)$/gm, '<li class="ml-4 list-decimal">$2</li>')

  // Convert bullet lists (* item or - item, but not inside code)
  content = content.replace(/^[\-]\s+(.+)$/gm, '<li class="ml-4 list-disc">$1</li>')

  // Wrap consecutive list items
  content = content.replace(/(<li class="ml-4 list-decimal">.+<\/li>\n?)+/g, '<ol class="my-2 pl-4">$&</ol>')
  content = content.replace(/(<li class="ml-4 list-disc">.+<\/li>\n?)+/g, '<ul class="my-2 pl-4">$&</ul>')

  // Highlight search matches
  if (props.searchQuery && props.searchQuery.trim()) {
    const query = props.searchQuery.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') // Escape regex special chars
    const regex = new RegExp(`(${query})`, 'gi')
    content = content.replace(regex, '<mark class="bg-amber-200 dark:bg-amber-800 text-amber-900 dark:text-amber-100 rounded px-0.5">$1</mark>')
  }

  return content
})

// Delivery status
const deliveryIcon = computed(() => {
  const icons: Record<DeliveryStatus, string> = {
    sending: 'ph:spinner',
    sent: 'ph:check',
    delivered: 'ph:checks',
    read: 'ph:checks',
    failed: 'ph:warning',
  }
  return icons[props.deliveryStatus]
})

const deliveryLabel = computed(() => {
  const labels: Record<DeliveryStatus, string> = {
    sending: 'Sending...',
    sent: 'Sent',
    delivered: 'Delivered',
    read: 'Read',
    failed: 'Failed',
  }
  return labels[props.deliveryStatus]
})

// Container classes
const containerClasses = computed(() => [
  'group relative',
  sizeConfig[props.size].padding,
  '-mx-2 rounded-lg',
  'transition-colors duration-150',
  props.highlighted
    ? 'bg-amber-50 dark:bg-amber-950 ring-1 ring-amber-200 dark:ring-amber-700'
    : 'hover:bg-neutral-50 dark:hover:bg-neutral-800',
  props.isOwn && 'flex-row-reverse',
])

// Reply context classes
const replyContextClasses = computed(() => [
  'flex items-center gap-2 ml-12 mb-1',
])

// Reply line classes
const replyLineClasses = computed(() => [
  'w-4 h-4 border-l-2 border-t-2 border-neutral-200 dark:border-neutral-700 rounded-tl',
])

// Reply button classes
const replyButtonClasses = computed(() => [
  'flex items-center gap-2 text-xs text-neutral-500 dark:text-neutral-300 max-w-xs',
  'cursor-pointer rounded-lg px-2 py-1 -mx-2',
  'transition-colors duration-150',
  'hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-50 dark:hover:bg-neutral-800',
])

// Message wrapper classes
const messageWrapperClasses = computed(() => [
  'flex',
  sizeConfig[props.size].gap,
  props.variant === 'bubble' && props.isOwn && 'flex-row-reverse',
])

// Avatar spacer classes
const avatarSpacerClasses = computed(() => {
  const sizes: Record<MessageSize, string> = {
    sm: 'w-6',
    md: 'w-10',
    lg: 'w-10',
  }
  return [sizes[props.size], 'shrink-0']
})

// Content classes
const contentClasses = computed(() => [
  'flex-1 min-w-0',
  props.variant === 'bubble' && [
    'p-3 rounded-lg max-w-md',
    props.isOwn
      ? 'bg-neutral-900 dark:bg-neutral-100 text-white dark:text-neutral-900 rounded-br-sm'
      : 'bg-neutral-100 dark:bg-neutral-700 rounded-bl-sm',
  ],
])

// Header classes
const headerClasses = computed(() => [
  'flex items-center gap-2 mb-0.5 flex-wrap',
])

// Author name classes
const authorNameClasses = computed(() => [
  'font-semibold text-sm text-neutral-900 dark:text-white',
  'hover:underline cursor-pointer',
])

// Timestamp classes
const timestampClasses = computed(() => [
  'text-xs text-neutral-400 dark:text-neutral-400',
])

// Body classes
const bodyClasses = computed(() => [
  'min-w-0',
])

// Text classes
const textClasses = computed(() => [
  'text-neutral-900 dark:text-white leading-relaxed',
  sizeConfig[props.size].text,
])

// Edit input classes
const editInputClasses = computed(() => [
  'w-full px-3 py-2 rounded-lg',
  'bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700',
  'text-neutral-900 dark:text-white text-sm',
  'focus:outline-none focus:border-neutral-400 dark:focus:border-neutral-500 focus:ring-1 focus:ring-neutral-400 dark:focus:ring-neutral-500',
  'resize-none',
])

// Attachments classes
const attachmentsClasses = computed(() => [
  'space-y-1.5 mt-2',
])

// Attachment classes
const attachmentClasses = computed(() => [
  'flex items-center gap-2 p-2 rounded-lg',
  'bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700',
  'transition-colors duration-150',
  'hover:border-neutral-300 dark:hover:border-neutral-600 hover:bg-neutral-100 dark:hover:bg-neutral-700',
])

// Code block classes
const codeBlockClasses = computed(() => [
  'rounded-lg overflow-hidden',
  'bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700',
])

// Reactions classes
const reactionsClasses = computed(() => [
  'flex items-center gap-1.5 mt-2 flex-wrap',
])

// Reaction button classes
const reactionButtonClasses = (hasReacted: boolean) => [
  'flex items-center gap-1 px-2.5 py-1 rounded-full text-sm',
  'border transition-colors duration-150',
  hasReacted
    ? 'bg-neutral-100 dark:bg-neutral-700 border-neutral-300 dark:border-neutral-600 text-neutral-900 dark:text-white'
    : 'bg-white dark:bg-neutral-900 border-neutral-200 dark:border-neutral-700 text-neutral-500 dark:text-neutral-300 hover:border-neutral-300 dark:hover:border-neutral-600 hover:bg-neutral-50 dark:hover:bg-neutral-800',
]

// Add reaction button classes
const addReactionButtonClasses = computed(() => [
  'p-1.5 rounded-full',
  'text-neutral-500 dark:text-neutral-300',
  'transition-colors duration-150',
  'hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-700',
])

// Thread preview classes
const threadPreviewClasses = computed(() => [
  'flex items-center gap-2 mt-2 py-1.5 px-2 -ml-2 rounded-lg',
  'text-sm cursor-pointer',
  'transition-colors duration-150',
  'hover:bg-neutral-50 dark:hover:bg-neutral-800',
])

// Hover actions classes
const hoverActionsClasses = computed(() => [
  'absolute -top-3 right-2',
  'flex items-center gap-0.5 p-1',
  'bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 rounded-lg',
  'shadow-md',
])

// Action button classes
const actionButtonClasses = computed(() => [
  'p-1.5 rounded-lg',
  'text-neutral-500 dark:text-neutral-300',
  'transition-colors duration-150',
  'hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-700',
  'focus:outline-none focus-visible:ring-1 focus-visible:ring-neutral-400 dark:focus-visible:ring-neutral-500',
])

// More menu classes
const moreMenuClasses = computed(() => [
  'min-w-48 bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 rounded-lg',
  'shadow-md p-1 z-50',
  'animate-in fade-in-0 duration-150',
])

// Menu item classes
const menuItemClasses = (action: MoreAction) => [
  'flex items-center gap-2 px-3 py-2 text-sm rounded-md cursor-pointer outline-none',
  'transition-colors duration-150',
  action.variant === 'danger'
    ? 'text-red-600 hover:bg-red-50 dark:hover:bg-red-950 focus:bg-red-50 dark:focus:bg-red-950'
    : 'text-neutral-500 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-800 focus:bg-neutral-50 dark:focus:bg-neutral-800 hover:text-neutral-900 dark:hover:text-white focus:text-neutral-900 dark:focus:text-white',
  action.disabled && 'opacity-50 cursor-not-allowed',
]

// Delivery status classes
const deliveryStatusClasses = computed(() => [
  'flex items-center gap-1 mt-1 ml-12 text-xs',
  props.deliveryStatus === 'failed' ? 'text-red-600' : 'text-neutral-400 dark:text-neutral-400',
])

// Delivery icon classes
const deliveryIconClasses = computed(() => [
  'w-3 h-3',
  props.deliveryStatus === 'sending' && 'animate-spin',
  props.deliveryStatus === 'read' && 'text-neutral-600 dark:text-neutral-200',
])

// Format time
const formatTime = (date: Date) => {
  return new Date(date).toLocaleTimeString('en-US', {
    hour: 'numeric',
    minute: '2-digit',
  })
}

// Format full date
const formatFullDate = (date: Date) => {
  return new Date(date).toLocaleString('en-US', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  })
}

// Format relative time
const formatRelativeTime = (date: Date): string => {
  const now = new Date()
  const diff = now.getTime() - new Date(date).getTime()
  const minutes = Math.floor(diff / 60000)
  const hours = Math.floor(minutes / 60)

  if (minutes < 1) return 'just now'
  if (minutes < 60) return `${minutes}m ago`
  if (hours < 24) return `${hours}h ago`
  return formatTime(date)
}

// Truncate reply
const truncateReply = (content: string, maxLength = 50) => {
  if (content.length <= maxLength) return content
  return content.slice(0, maxLength) + '...'
}

// Get attachment icon
const getAttachmentIcon = (type?: string) => {
  if (!type) return 'ph:file'
  if (type.startsWith('image/')) return 'ph:image'
  if (type.startsWith('video/')) return 'ph:video'
  if (type.startsWith('audio/')) return 'ph:music-note'
  if (type.includes('pdf')) return 'ph:file-pdf'
  if (type.includes('zip') || type.includes('rar')) return 'ph:file-zip'
  return 'ph:file'
}

// Format file size
const formatFileSize = (bytes: number) => {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

// Copy code
const copyCode = async (code: string) => {
  await navigator.clipboard.writeText(code)
  copiedCode.value = code
  setTimeout(() => {
    copiedCode.value = null
  }, 2000)
}

// Handlers
const handleAuthorClick = () => {
  emit('authorClick', props.message.author)
}

const handleReplyClick = () => {
  if (props.message.replyTo) {
    emit('replyClick', props.message.replyTo)
  }
}

const handleReaction = (emoji: string) => {
  emit('reaction', props.message, emoji)
}

const startEdit = () => {
  isEditing.value = true
  editContent.value = props.message.content
  nextTick(() => {
    editInputRef.value?.focus()
    editInputRef.value?.select()
  })
}

const handleSaveEdit = () => {
  if (editContent.value.trim()) {
    emit('edit', props.message, editContent.value.trim())
  }
  isEditing.value = false
}

const cancelEdit = () => {
  isEditing.value = false
  editContent.value = ''
}

const handleDownload = (attachment: MessageAttachment) => {
  if (attachment.url) {
    window.open(attachment.url, '_blank')
  }
}

const handleMoreAction = (action: MoreAction) => {
  switch (action.action) {
    case 'edit':
      startEdit()
      break
    case 'delete':
      emit('delete', props.message)
      break
    case 'pin':
      emit('pin', props.message)
      break
    case 'copy':
      navigator.clipboard.writeText(props.message.content)
      break
  }
}
</script>

<style scoped>
/* Fade transition */
.fade-enter-active {
  transition: opacity 0.15s ease-out;
}

.fade-leave-active {
  transition: opacity 0.1s ease-out;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
