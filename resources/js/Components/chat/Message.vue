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
        <span class="font-medium text-gray-500">
          {{ message.replyTo.author.name }}
        </span>
        <span class="truncate text-gray-400">
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
            :href="`/profile/${message.author.id}`"
            :class="authorNameClasses"
          >
            {{ message.author.name }}
          </Link>

          <!-- Agent Badge -->
          <SharedBadge
            v-if="message.author.type === 'agent'"
            variant="primary"
            size="xs"
          >
            <Icon name="ph:robot" class="w-2.5 h-2.5 mr-0.5" />
            Agent
          </SharedBadge>

          <!-- Status Badge -->
          <SharedStatusBadge
            v-if="message.author.type === 'agent' && message.author.status"
            :status="message.author.status"
            size="xs"
          />

          <!-- Timestamp -->
          <TooltipProvider :delay-duration="300">
            <TooltipRoot>
              <TooltipTrigger as-child>
                <span :class="timestampClasses">
                  {{ formatTime(message.timestamp) }}
                </span>
              </TooltipTrigger>
              <TooltipPortal>
                <TooltipContent
                  class="z-50 bg-white border border-gray-200 rounded-lg px-2.5 py-1.5 text-xs shadow-md animate-in fade-in-0 duration-150"
                  :side-offset="5"
                >
                  {{ formatFullDate(message.timestamp) }}
                  <TooltipArrow class="fill-white" />
                </TooltipContent>
              </TooltipPortal>
            </TooltipRoot>
          </TooltipProvider>

          <!-- Edited indicator -->
          <span v-if="message.editedAt" class="text-xs text-gray-400">
            (edited)
          </span>

          <!-- Pinned indicator -->
          <TooltipProvider v-if="message.isPinned" :delay-duration="200">
            <TooltipRoot>
              <TooltipTrigger as-child>
                <Icon
                  name="ph:push-pin-fill"
                  class="w-3 h-3 text-amber-500"
                />
              </TooltipTrigger>
              <TooltipPortal>
                <TooltipContent class="z-50 bg-white border border-gray-200 rounded-lg px-2.5 py-1.5 text-xs shadow-md animate-in fade-in-0 duration-150">
                  Pinned{{ message.pinnedBy ? ` by ${message.pinnedBy.name}` : '' }}
                  <TooltipArrow class="fill-white" />
                </TooltipContent>
              </TooltipPortal>
            </TooltipRoot>
          </TooltipProvider>
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
                class="px-3 py-1.5 text-xs font-medium bg-gray-900 text-white rounded-lg transition-colors duration-150 hover:bg-gray-800"
                @click="handleSaveEdit"
              >
                Save
              </button>
              <button
                type="button"
                class="px-3 py-1.5 text-xs font-medium text-gray-500 rounded-lg transition-colors duration-150 hover:text-gray-900 hover:bg-gray-100"
                @click="cancelEdit"
              >
                Cancel
              </button>
              <span class="text-xs text-gray-400 ml-auto">
                <kbd class="px-1 py-0.5 bg-gray-100 border border-gray-200 rounded text-[10px]">Esc</kbd> to cancel,
                <kbd class="px-1 py-0.5 bg-gray-100 border border-gray-200 rounded text-[10px]">Enter</kbd> to save
              </span>
            </div>
          </div>
        </div>

        <!-- Attachments -->
        <div v-if="message.attachments?.length" :class="attachmentsClasses">
          <div
            v-for="attachment in message.attachments"
            :key="attachment.id"
            :class="attachmentClasses"
          >
            <Icon :name="getAttachmentIcon(attachment.type)" class="w-4 h-4 text-gray-500" />
            <span class="text-sm truncate flex-1">{{ attachment.name }}</span>
            <span class="text-xs text-gray-400">{{ formatFileSize(attachment.size) }}</span>
            <button
              type="button"
              class="p-1.5 rounded-lg transition-colors duration-150 hover:bg-gray-100"
              @click="handleDownload(attachment)"
            >
              <Icon name="ph:download-simple" class="w-4 h-4 text-gray-500" />
            </button>
          </div>
        </div>

        <!-- Code Blocks -->
        <div v-if="message.codeBlocks?.length" class="space-y-3 mt-2">
          <div
            v-for="(block, index) in message.codeBlocks"
            :key="index"
            :class="codeBlockClasses"
          >
            <div class="flex items-center justify-between px-3 py-2 border-b border-gray-200">
              <span class="text-xs font-medium text-gray-500">{{ block.language }}</span>
              <button
                type="button"
                class="p-1.5 rounded-lg text-gray-500 transition-colors duration-150 hover:bg-gray-100 hover:text-gray-900"
                :class="copiedCode === block.code && 'text-green-600 hover:text-green-600'"
                @click="copyCode(block.code)"
              >
                <Icon :name="copiedCode === block.code ? 'ph:check' : 'ph:copy'" class="w-4 h-4" />
              </button>
            </div>
            <pre class="p-3 text-sm font-mono overflow-x-auto"><code>{{ block.code }}</code></pre>
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
          <span class="text-gray-900 font-medium">
            {{ message.threadCount }} {{ message.threadCount === 1 ? 'reply' : 'replies' }}
          </span>
          <span v-if="message.lastThreadReplyAt" class="text-gray-400">
            {{ formatRelativeTime(message.lastThreadReplyAt) }}
          </span>
        </button>
      </div>

      <!-- Hover Actions -->
      <Transition name="fade">
        <div v-if="isHovered && !isEditing" :class="hoverActionsClasses">
          <!-- Emoji reaction -->
          <SharedEmojiPicker side="top" align="start" @select="handleReaction">
            <TooltipProvider :delay-duration="200">
              <TooltipRoot>
                <TooltipTrigger as-child>
                  <button
                    type="button"
                    :class="actionButtonClasses"
                  >
                    <Icon name="ph:smiley" class="w-4 h-4" />
                  </button>
                </TooltipTrigger>
                <TooltipPortal>
                  <TooltipContent class="z-50 bg-white border border-gray-200 rounded-lg px-2.5 py-1.5 text-xs shadow-md animate-in fade-in-0 duration-150">
                    Add reaction
                    <TooltipArrow class="fill-white" />
                  </TooltipContent>
                </TooltipPortal>
              </TooltipRoot>
            </TooltipProvider>
          </SharedEmojiPicker>

          <!-- Reply -->
          <TooltipProvider :delay-duration="200">
            <TooltipRoot>
              <TooltipTrigger as-child>
                <button
                  type="button"
                  :class="actionButtonClasses"
                  @click="$emit('reply', message)"
                >
                  <Icon name="ph:arrow-bend-up-left" class="w-4 h-4" />
                </button>
              </TooltipTrigger>
              <TooltipPortal>
                <TooltipContent class="z-50 bg-white border border-gray-200 rounded-lg px-2.5 py-1.5 text-xs shadow-md animate-in fade-in-0 duration-150">
                  Reply
                  <TooltipArrow class="fill-white" />
                </TooltipContent>
              </TooltipPortal>
            </TooltipRoot>
          </TooltipProvider>

          <!-- Thread -->
          <TooltipProvider :delay-duration="200">
            <TooltipRoot>
              <TooltipTrigger as-child>
                <button
                  type="button"
                  :class="actionButtonClasses"
                  @click="$emit('openThread', message)"
                >
                  <Icon name="ph:chat-circle" class="w-4 h-4" />
                </button>
              </TooltipTrigger>
              <TooltipPortal>
                <TooltipContent class="z-50 bg-white border border-gray-200 rounded-lg px-2.5 py-1.5 text-xs shadow-md animate-in fade-in-0 duration-150">
                  Reply in thread
                  <TooltipArrow class="fill-white" />
                </TooltipContent>
              </TooltipPortal>
            </TooltipRoot>
          </TooltipProvider>

          <!-- More Actions -->
          <DropdownMenuRoot>
            <DropdownMenuTrigger as-child>
              <button type="button" :class="actionButtonClasses">
                <Icon name="ph:dots-three" class="w-4 h-4" />
              </button>
            </DropdownMenuTrigger>
            <DropdownMenuPortal>
              <DropdownMenuContent
                :class="moreMenuClasses"
                :side-offset="5"
                align="end"
              >
                <DropdownMenuItem
                  v-for="action in moreActions"
                  :key="action.label"
                  :class="menuItemClasses(action)"
                  :disabled="action.disabled"
                  @click="handleMoreAction(action)"
                >
                  <Icon :name="action.icon" class="w-4 h-4" />
                  <span>{{ action.label }}</span>
                  <span v-if="action.shortcut" class="ml-auto text-xs opacity-50">
                    {{ action.shortcut }}
                  </span>
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenuPortal>
          </DropdownMenuRoot>
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
import Icon from '@/Components/shared/Icon.vue'
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
  DropdownMenuTrigger,
} from 'reka-ui'
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
    if (!groups[reaction.emoji]) {
      groups[reaction.emoji] = {
        emoji: reaction.emoji,
        count: 0,
        users: [],
        hasReacted: false,
      }
    }
    groups[reaction.emoji].count += reaction.users.length
    groups[reaction.emoji].users.push(...reaction.users)
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

// Format message content with mentions, links, etc.
const formattedContent = computed(() => {
  let content = props.message.content

  // Convert @mentions to styled spans
  content = content.replace(/@(\w+)/g, '<span class="text-gray-900 font-medium cursor-pointer hover:underline">@$1</span>')

  // Convert URLs to links
  content = content.replace(
    /(https?:\/\/[^\s]+)/g,
    '<a href="$1" target="_blank" rel="noopener" class="text-gray-900 hover:underline">$1</a>'
  )

  // Convert **bold** text
  content = content.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')

  // Convert _italic_ text
  content = content.replace(/_(.+?)_/g, '<em>$1</em>')

  // Convert `code` text
  content = content.replace(/`(.+?)`/g, '<code class="px-1.5 py-0.5 bg-gray-100 rounded text-gray-900 text-xs font-mono">$1</code>')

  // Highlight search matches
  if (props.searchQuery && props.searchQuery.trim()) {
    const query = props.searchQuery.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') // Escape regex special chars
    const regex = new RegExp(`(${query})`, 'gi')
    content = content.replace(regex, '<mark class="bg-amber-200 text-amber-900 rounded px-0.5">$1</mark>')
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
    ? 'bg-amber-50 ring-1 ring-amber-200'
    : 'hover:bg-gray-50',
  props.isOwn && 'flex-row-reverse',
])

// Reply context classes
const replyContextClasses = computed(() => [
  'flex items-center gap-2 ml-12 mb-1',
])

// Reply line classes
const replyLineClasses = computed(() => [
  'w-4 h-4 border-l-2 border-t-2 border-gray-200 rounded-tl',
])

// Reply button classes
const replyButtonClasses = computed(() => [
  'flex items-center gap-2 text-xs text-gray-500 max-w-xs',
  'cursor-pointer rounded-lg px-2 py-1 -mx-2',
  'transition-colors duration-150',
  'hover:text-gray-900 hover:bg-gray-50',
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
      ? 'bg-gray-900 text-white rounded-br-sm'
      : 'bg-gray-100 rounded-bl-sm',
  ],
])

// Header classes
const headerClasses = computed(() => [
  'flex items-center gap-2 mb-0.5 flex-wrap',
])

// Author name classes
const authorNameClasses = computed(() => [
  'font-semibold text-sm text-gray-900',
  'hover:underline cursor-pointer',
])

// Timestamp classes
const timestampClasses = computed(() => [
  'text-xs text-gray-400',
])

// Body classes
const bodyClasses = computed(() => [
  'min-w-0',
])

// Text classes
const textClasses = computed(() => [
  'text-gray-900 leading-relaxed',
  sizeConfig[props.size].text,
])

// Edit input classes
const editInputClasses = computed(() => [
  'w-full px-3 py-2 rounded-lg',
  'bg-white border border-gray-200',
  'text-gray-900 text-sm',
  'focus:outline-none focus:border-gray-400 focus:ring-1 focus:ring-gray-400',
  'resize-none',
])

// Attachments classes
const attachmentsClasses = computed(() => [
  'space-y-1.5 mt-2',
])

// Attachment classes
const attachmentClasses = computed(() => [
  'flex items-center gap-2 p-2 rounded-lg',
  'bg-gray-50 border border-gray-200',
  'transition-colors duration-150',
  'hover:border-gray-300 hover:bg-gray-100',
])

// Code block classes
const codeBlockClasses = computed(() => [
  'rounded-lg overflow-hidden',
  'bg-gray-50 border border-gray-200',
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
    ? 'bg-gray-100 border-gray-300 text-gray-900'
    : 'bg-white border-gray-200 text-gray-500 hover:border-gray-300 hover:bg-gray-50',
]

// Add reaction button classes
const addReactionButtonClasses = computed(() => [
  'p-1.5 rounded-full',
  'text-gray-500',
  'transition-colors duration-150',
  'hover:text-gray-900 hover:bg-gray-100',
])

// Thread preview classes
const threadPreviewClasses = computed(() => [
  'flex items-center gap-2 mt-2 py-1.5 px-2 -ml-2 rounded-lg',
  'text-sm cursor-pointer',
  'transition-colors duration-150',
  'hover:bg-gray-50',
])

// Hover actions classes
const hoverActionsClasses = computed(() => [
  'absolute -top-3 right-2',
  'flex items-center gap-0.5 p-1',
  'bg-white border border-gray-200 rounded-lg',
  'shadow-md',
])

// Action button classes
const actionButtonClasses = computed(() => [
  'p-1.5 rounded-lg',
  'text-gray-500',
  'transition-colors duration-150',
  'hover:text-gray-900 hover:bg-gray-100',
  'focus:outline-none focus-visible:ring-1 focus-visible:ring-gray-400',
])

// More menu classes
const moreMenuClasses = computed(() => [
  'min-w-48 bg-white border border-gray-200 rounded-lg',
  'shadow-md p-1 z-50',
  'animate-in fade-in-0 duration-150',
])

// Menu item classes
const menuItemClasses = (action: MoreAction) => [
  'flex items-center gap-2 px-3 py-2 text-sm rounded-md cursor-pointer outline-none',
  'transition-colors duration-150',
  action.variant === 'danger'
    ? 'text-red-600 hover:bg-red-50 focus:bg-red-50'
    : 'text-gray-500 hover:bg-gray-50 focus:bg-gray-50 hover:text-gray-900 focus:text-gray-900',
  action.disabled && 'opacity-50 cursor-not-allowed',
]

// Delivery status classes
const deliveryStatusClasses = computed(() => [
  'flex items-center gap-1 mt-1 ml-12 text-xs',
  props.deliveryStatus === 'failed' ? 'text-red-600' : 'text-gray-400',
])

// Delivery icon classes
const deliveryIconClasses = computed(() => [
  'w-3 h-3',
  props.deliveryStatus === 'sending' && 'animate-spin',
  props.deliveryStatus === 'read' && 'text-gray-600',
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
const getAttachmentIcon = (type: string) => {
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
