<template>
  <div
    :class="containerClasses"
    :data-message-id="message.id"
    @mouseenter="isHovered = true"
    @mouseleave="isHovered = false"
  >
    <!-- Approval Card (standalone, not in a bubble) -->
    <ChatApprovalCard
      v-if="message.isApprovalRequest && message.approvalRequest"
      :request="message.approvalRequest"
      :author="message.author"
      :timestamp="message.timestamp"
      :size="size"
      class="max-w-lg"
      @approve="$emit('approve', message)"
      @reject="$emit('reject', message)"
    />

    <!-- Regular Message -->
    <div v-else :class="bubbleRowClasses">
      <!-- Avatar (others only, last-in-group) -->
      <SharedAgentAvatar
        v-if="!isOwn && showAvatar"
        :user="message.author"
        :size="avatarSize"
        :show-status="showAvatarStatus"
        class="shrink-0 self-end mb-0.5"
      />
      <div v-else-if="!isOwn" class="w-8 shrink-0" />

      <!-- Content column (relative for hover actions) -->
      <div class="relative min-w-0 max-w-full">
        <!-- Author name (first in group, others only) -->
        <div v-if="showHeader && !isOwn" class="flex items-center gap-1.5 mb-1 ml-1">
          <Link
            :href="workspacePath(message.author.type === 'agent' ? `/agent/${message.author.id}` : `/profile/${message.author.id}`)"
            class="text-xs font-semibold text-neutral-600 dark:text-neutral-300 hover:underline"
          >
            {{ message.author.name }}
          </Link>
          <Badge
            v-if="message.author.type === 'agent'"
            color="primary"
            variant="subtle"
            size="xs"
          >
            <Icon name="ph:robot" class="w-2.5 h-2.5 mr-0.5" />
            Agent
          </Badge>
          <SharedStatusBadge
            v-if="message.author.type === 'agent' && message.author.status"
            :status="message.author.status"
            size="xs"
          />
        </div>

        <!-- Pinned indicator (floating) -->
        <div v-if="message.isPinned" class="absolute -top-2 z-10" :class="isOwn ? '-left-2' : '-right-2'">
          <Tooltip :delay-duration="200">
            <template #content>Pinned{{ message.pinnedBy ? ` by ${message.pinnedBy.name}` : '' }}</template>
            <div class="w-5 h-5 rounded-full bg-amber-100 dark:bg-amber-900/60 flex items-center justify-center shadow-sm">
              <Icon name="ph:push-pin-fill" class="w-3 h-3 text-amber-600 dark:text-amber-400" />
            </div>
          </Tooltip>
        </div>

        <!-- The Bubble -->
        <div :class="bubbleClasses">
          <!-- Reply context (inside bubble) -->
          <button
            v-if="message.replyTo"
            type="button"
            class="w-full text-left mb-1.5 px-2.5 py-1.5 rounded-md cursor-pointer border-l-2 transition-colors"
            :class="isOwn
              ? 'bg-white/10 border-white/40 hover:bg-white/15'
              : 'bg-black/5 dark:bg-white/5 border-neutral-400 dark:border-neutral-500 hover:bg-black/10 dark:hover:bg-white/10'"
            @click="handleReplyClick"
          >
            <span class="text-xs font-semibold" :class="isOwn ? 'text-white/70' : 'text-neutral-600 dark:text-neutral-300'">
              {{ message.replyTo.author.name }}
            </span>
            <p class="text-xs truncate mt-0.5" :class="isOwn ? 'text-white/50' : 'text-neutral-500 dark:text-neutral-400'">
              {{ truncateReply(message.replyTo.content) }}
            </p>
          </button>

          <!-- Message Body -->
          <div class="min-w-0">
            <!-- Text content -->
            <div v-if="!isEditing" class="relative">
              <p :class="textClasses" v-html="formattedContent" />
              <!-- Inline timestamp (Telegram-style float-right) -->
              <span :class="inlineTimestampClasses">
                <span v-if="message.editedAt" class="italic">edited</span>
                <Tooltip :delay-duration="300">
                  <template #content>{{ formatFullDate(message.timestamp) }}</template>
                  <span>{{ formatTime(message.timestamp) }}</span>
                </Tooltip>
                <Icon
                  v-if="isOwn && showDeliveryStatus"
                  :name="deliveryIcon"
                  :class="['w-3 h-3', deliveryStatus === 'sending' && 'animate-spin', deliveryStatus === 'read' && 'opacity-80']"
                />
              </span>
            </div>

            <!-- Edit mode -->
            <div v-else class="space-y-2">
              <textarea
                ref="editInputRef"
                v-model="editContent"
                class="w-full bg-transparent border-0 outline-none resize-none text-sm leading-relaxed"
                :class="isOwn ? 'text-white placeholder:text-white/40' : 'text-neutral-900 dark:text-white'"
                @keydown.enter.exact.prevent="handleSaveEdit"
                @keydown.escape="cancelEdit"
              />
              <div class="flex items-center gap-2">
                <button
                  type="button"
                  class="px-2.5 py-1 text-xs font-medium rounded-md transition-colors"
                  :class="isOwn
                    ? 'bg-white/20 hover:bg-white/30 text-white'
                    : 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900 hover:opacity-90'"
                  @click="handleSaveEdit"
                >
                  Save
                </button>
                <button
                  type="button"
                  class="px-2.5 py-1 text-xs rounded-md transition-colors"
                  :class="isOwn
                    ? 'text-white/70 hover:text-white hover:bg-white/10'
                    : 'text-neutral-500 hover:text-neutral-900 hover:bg-neutral-200 dark:text-neutral-400 dark:hover:text-white dark:hover:bg-neutral-600'"
                  @click="cancelEdit"
                >
                  Cancel
                </button>
                <span class="text-[10px] ml-auto" :class="isOwn ? 'text-white/40' : 'text-neutral-400'">
                  Esc to cancel · Enter to save
                </span>
              </div>
            </div>
          </div>

          <!-- Attachments -->
          <div v-if="message.attachments?.length" class="mt-1.5">
            <!-- Image attachments -->
            <div
              v-if="imageAttachments.length"
              :class="imageAttachments.length === 1 ? '' : 'grid grid-cols-2 gap-0.5 -mx-3 overflow-hidden'"
            >
              <button
                v-for="attachment in imageAttachments"
                :key="attachment.id"
                type="button"
                class="relative group/img overflow-hidden cursor-pointer focus:outline-none rounded-lg"
                :class="imageAttachments.length === 1 ? 'max-w-sm max-h-80 block' : 'aspect-square'"
                @click="openLightbox(attachment.url, attachment.original_name || attachment.name)"
              >
                <img
                  :src="attachment.url"
                  :alt="attachment.original_name || attachment.name"
                  class="w-full h-full object-cover rounded-lg"
                  :class="imageAttachments.length === 1 ? 'max-h-80' : ''"
                  loading="lazy"
                />
                <div class="absolute inset-0 bg-black/0 group-hover/img:bg-black/20 transition-colors flex items-center justify-center rounded-lg">
                  <Icon name="ph:arrows-out" class="w-6 h-6 text-white opacity-0 group-hover/img:opacity-100 transition-opacity drop-shadow-lg" />
                </div>
              </button>
            </div>

            <!-- File attachments -->
            <div v-if="fileAttachments.length" class="space-y-1" :class="imageAttachments.length ? 'mt-2 mx-0' : ''">
              <button
                v-for="attachment in fileAttachments"
                :key="attachment.id"
                type="button"
                class="w-full flex items-center gap-2.5 p-2.5 rounded-lg transition-colors text-left"
                :class="isOwn
                  ? 'bg-white/10 hover:bg-white/15'
                  : 'bg-white dark:bg-neutral-700 hover:bg-neutral-50 dark:hover:bg-neutral-600'"
                @click="handleDownload(attachment)"
              >
                <div
                  class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0"
                  :class="getFileIconColor(attachment.mime_type || attachment.type, isOwn)"
                >
                  <Icon :name="getAttachmentIcon(attachment.mime_type || attachment.type)" class="w-5 h-5" :class="getFileIconTextColor(attachment.mime_type || attachment.type, isOwn)" />
                </div>
                <div class="flex-1 min-w-0">
                  <span class="text-sm font-medium truncate block">{{ attachment.original_name || attachment.name || 'Untitled' }}</span>
                  <span class="text-xs opacity-50">{{ getFileTypeLabel(attachment.original_name || attachment.name, attachment.mime_type || attachment.type) }} · {{ formatFileSize(attachment.size) }}</span>
                </div>
                <Icon name="ph:download-simple" class="w-4 h-4 shrink-0 opacity-40" />
              </button>
            </div>
          </div>

          <!-- Code Blocks -->
          <div v-if="message.codeBlocks?.length" class="-mx-3 mt-2 space-y-1">
            <div
              v-for="(block, index) in message.codeBlocks"
              :key="index"
              class="overflow-hidden bg-neutral-800 dark:bg-neutral-900"
            >
              <div class="flex items-center justify-between px-3 py-1.5 border-b border-white/10">
                <span class="text-xs font-medium text-neutral-400">{{ block.language }}</span>
                <button
                  type="button"
                  class="p-1 rounded text-neutral-400 hover:text-white transition-colors"
                  :class="copiedCode === block.code && 'text-green-400 hover:text-green-400'"
                  @click="copyCode(block.code)"
                >
                  <Icon :name="copiedCode === block.code ? 'ph:check' : 'ph:copy'" class="w-3.5 h-3.5" />
                </button>
              </div>
              <pre class="px-3 py-2 text-xs font-mono overflow-x-auto text-neutral-100"><code v-html="highlight(block.code, block.language)" /></pre>
            </div>
          </div>

          <!-- Agent Activity Log -->
          <div
            v-if="message.author.type === 'agent' && message.author.status === 'working' && message.author.activityLog?.length"
            class="mt-2 pt-2 border-t"
            :class="isOwn ? 'border-white/10' : 'border-neutral-200 dark:border-neutral-700'"
          >
            <SharedActivityLog :steps="message.author.activityLog" :size="size" />
          </div>
        </div>

        <!-- Reactions (outside bubble) -->
        <div v-if="message.reactions?.length" :class="reactionsClasses">
          <button
            v-for="reaction in groupedReactions"
            :key="reaction.emoji"
            type="button"
            class="flex items-center gap-1 px-2 py-0.5 rounded-full text-xs border transition-colors duration-150"
            :class="reaction.hasReacted
              ? 'bg-neutral-100 dark:bg-neutral-700 border-neutral-300 dark:border-neutral-600 text-neutral-900 dark:text-white'
              : 'bg-white dark:bg-neutral-900 border-neutral-200 dark:border-neutral-700 text-neutral-500 dark:text-neutral-400 hover:border-neutral-300 dark:hover:border-neutral-600'"
            @click="handleReaction(reaction.emoji)"
          >
            <span>{{ reaction.emoji }}</span>
            <span>{{ reaction.count }}</span>
          </button>
          <SharedEmojiPicker side="top" align="start" @select="handleReaction">
            <button
              type="button"
              class="p-1 rounded-full text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors"
            >
              <Icon name="ph:smiley" class="w-3.5 h-3.5" />
            </button>
          </SharedEmojiPicker>
        </div>

        <!-- Thread Preview (outside bubble) -->
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
          <span class="font-medium text-blue-500 dark:text-blue-400">
            {{ message.threadCount }} {{ message.threadCount === 1 ? 'reply' : 'replies' }}
          </span>
          <span v-if="message.lastThreadReplyAt" class="text-neutral-400 dark:text-neutral-500">
            {{ formatRelativeTime(message.lastThreadReplyAt) }}
          </span>
        </button>

        <!-- Hover Actions (floating above bubble) -->
        <Transition name="fade">
          <div v-if="isHovered && !isEditing" :class="hoverActionsClasses">
            <!-- Emoji reaction -->
            <SharedEmojiPicker side="top" align="start" @select="handleReaction">
              <Tooltip :delay-duration="200">
                <template #content>Add reaction</template>
                <button type="button" :class="actionButtonClasses">
                  <Icon name="ph:smiley" class="w-4 h-4" />
                </button>
              </Tooltip>
            </SharedEmojiPicker>

            <!-- Reply -->
            <Tooltip :delay-duration="200">
              <template #content>Reply</template>
              <button type="button" :class="actionButtonClasses" @click="$emit('reply', message)">
                <Icon name="ph:arrow-bend-up-left" class="w-4 h-4" />
              </button>
            </Tooltip>

            <!-- Thread -->
            <Tooltip :delay-duration="200">
              <template #content>Reply in thread</template>
              <button type="button" :class="actionButtonClasses" @click="$emit('openThread', message)">
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
    </div>

    <!-- Image Lightbox -->
    <Teleport to="body">
      <Transition name="lightbox">
        <div
          v-if="lightboxImage"
          class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm"
          @click.self="closeLightbox"
          @mousemove="handleLightboxMouseMove"
          @mouseup="handleLightboxMouseUp"
        >
          <!-- Top toolbar -->
          <div class="absolute top-4 right-4 flex items-center gap-2 z-10">
            <div class="flex items-center gap-1 bg-white/10 rounded-full px-1">
              <button
                type="button"
                class="p-1.5 rounded-full hover:bg-white/10 text-white transition-colors"
                @click="zoomOut"
              >
                <Icon name="ph:minus" class="w-4 h-4" />
              </button>
              <span class="text-xs text-white/80 font-mono w-10 text-center select-none">{{ lightboxZoomPercent }}%</span>
              <button
                type="button"
                class="p-1.5 rounded-full hover:bg-white/10 text-white transition-colors"
                @click="zoomIn"
              >
                <Icon name="ph:plus" class="w-4 h-4" />
              </button>
            </div>
            <button
              type="button"
              class="p-2 rounded-full bg-white/10 hover:bg-white/20 text-white transition-colors"
              @click="closeLightbox"
            >
              <Icon name="ph:x" class="w-5 h-5" />
            </button>
          </div>

          <!-- Image with zoom/pan -->
          <div
            class="overflow-hidden flex items-center justify-center"
            style="width: 90vw; height: 90vh"
          >
            <img
              :src="lightboxImage"
              :alt="lightboxAlt"
              class="max-w-full max-h-full object-contain rounded-lg shadow-2xl select-none transition-transform duration-150"
              :class="lightboxZoom > 1 ? (lightboxDragging ? 'cursor-grabbing' : 'cursor-grab') : 'cursor-zoom-in'"
              :style="{ transform: lightboxTransform }"
              draggable="false"
              @wheel="handleLightboxWheel"
              @dblclick="handleLightboxDoubleClick"
              @mousedown="handleLightboxMouseDown"
            />
          </div>

          <!-- Caption -->
          <p v-if="lightboxAlt" class="absolute bottom-6 text-white/70 text-sm text-center">
            {{ lightboxAlt }}
          </p>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, nextTick, onMounted, onUnmounted } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useWorkspace } from '@/composables/useWorkspace'
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
  message: Message
  size?: MessageSize
  variant?: MessageVariant
  showAvatar?: boolean
  showAvatarStatus?: boolean
  showHeader?: boolean
  showDeliveryStatus?: boolean
  showDeliveryLabel?: boolean
  isOwn?: boolean
  isFirstInGroup?: boolean
  isLastInGroup?: boolean
  compact?: boolean
  highlighted?: boolean
  searchQuery?: string
  deliveryStatus?: DeliveryStatus
}>(), {
  size: 'md',
  variant: 'bubble',
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

const { workspacePath } = useWorkspace()
const { highlight } = useHighlight()

const isHovered = ref(false)
const isEditing = ref(false)
const editContent = ref('')
const editInputRef = ref<HTMLTextAreaElement | null>(null)
const copiedCode = ref<string | null>(null)
const lightboxImage = ref<string | null>(null)
const lightboxAlt = ref('')
const lightboxZoom = ref(1)
const lightboxOffset = ref({ x: 0, y: 0 })
const lightboxDragging = ref(false)
const lightboxDragStart = ref({ x: 0, y: 0 })
const lightboxOffsetStart = ref({ x: 0, y: 0 })

const openLightbox = (url: string, alt: string = '') => {
  lightboxImage.value = url
  lightboxAlt.value = alt
  lightboxZoom.value = 1
  lightboxOffset.value = { x: 0, y: 0 }
}

const closeLightbox = () => {
  lightboxImage.value = null
  lightboxAlt.value = ''
  lightboxZoom.value = 1
  lightboxOffset.value = { x: 0, y: 0 }
}

const zoomIn = () => {
  lightboxZoom.value = Math.min(4, lightboxZoom.value + 0.25)
}

const zoomOut = () => {
  lightboxZoom.value = Math.max(0.5, lightboxZoom.value - 0.25)
  if (lightboxZoom.value <= 1) {
    lightboxOffset.value = { x: 0, y: 0 }
  }
}

const handleLightboxWheel = (e: WheelEvent) => {
  e.preventDefault()
  if (e.deltaY < 0) {
    zoomIn()
  } else {
    zoomOut()
  }
}

const handleLightboxDoubleClick = () => {
  if (lightboxZoom.value === 1) {
    lightboxZoom.value = 2
  } else {
    lightboxZoom.value = 1
    lightboxOffset.value = { x: 0, y: 0 }
  }
}

const handleLightboxMouseDown = (e: MouseEvent) => {
  if (lightboxZoom.value <= 1) return
  e.preventDefault()
  lightboxDragging.value = true
  lightboxDragStart.value = { x: e.clientX, y: e.clientY }
  lightboxOffsetStart.value = { ...lightboxOffset.value }
}

const handleLightboxMouseMove = (e: MouseEvent) => {
  if (!lightboxDragging.value) return
  lightboxOffset.value = {
    x: lightboxOffsetStart.value.x + (e.clientX - lightboxDragStart.value.x) / lightboxZoom.value,
    y: lightboxOffsetStart.value.y + (e.clientY - lightboxDragStart.value.y) / lightboxZoom.value,
  }
}

const handleLightboxMouseUp = () => {
  lightboxDragging.value = false
}

const lightboxTransform = computed(() =>
  `scale(${lightboxZoom.value}) translate(${lightboxOffset.value.x}px, ${lightboxOffset.value.y}px)`,
)

const lightboxZoomPercent = computed(() => Math.round(lightboxZoom.value * 100))

const handleLightboxKeydown = (e: KeyboardEvent) => {
  if (!lightboxImage.value) return
  if (e.key === 'Escape') closeLightbox()
  if (e.key === '+' || e.key === '=') zoomIn()
  if (e.key === '-') zoomOut()
  if (e.key === '0') { lightboxZoom.value = 1; lightboxOffset.value = { x: 0, y: 0 } }
}

onMounted(() => {
  document.addEventListener('keydown', handleLightboxKeydown)
})

onUnmounted(() => {
  document.removeEventListener('keydown', handleLightboxKeydown)
})

const avatarSize = computed(() => props.size === 'sm' ? 'sm' as const : 'md' as const)

// Split attachments into images and files
const imageAttachments = computed(() =>
  (props.message.attachments || []).filter(a => a.type?.startsWith('image/') || (a as any).mime_type?.startsWith('image/')),
)
const fileAttachments = computed(() =>
  (props.message.attachments || []).filter(a => !a.type?.startsWith('image/') && !(a as any).mime_type?.startsWith('image/')),
)

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

  // Parse markdown code blocks with syntax highlighting
  content = content.replace(/```(\w+)?\n([\s\S]*?)```/g, (_, lang, code) => {
    const highlighted = highlight(code.trim(), lang)
    const langLabel = lang ? `<div class="text-xs text-neutral-400 mb-1 font-mono">${lang}</div>` : ''
    return `<div class="my-1">${langLabel}<pre class="p-2 rounded-lg bg-neutral-800 dark:bg-neutral-900 overflow-x-auto"><code class="text-sm text-neutral-100 hljs">${highlighted}</code></pre></div>`
  })

  // Convert headers
  content = content.replace(/^### (.+)$/gm, '<h3 class="text-base font-semibold mt-2 mb-0.5">$1</h3>')
  content = content.replace(/^## (.+)$/gm, '<h2 class="text-lg font-semibold mt-2 mb-0.5">$1</h2>')
  content = content.replace(/^# (.+)$/gm, '<h1 class="text-xl font-bold mt-2 mb-0.5">$1</h1>')

  // Convert horizontal rules
  content = content.replace(/^---$/gm, '<hr class="my-2 border-current opacity-20" />')

  // Convert @mentions
  content = content.replace(/@(\w+)/g, `<span class="${props.isOwn ? 'text-blue-300' : 'text-blue-600 dark:text-blue-400'} font-medium cursor-pointer hover:underline">@$1</span>`)

  // Convert markdown images
  content = content.replace(
    /!\[([^\]]*)\]\(([^)]+)\)/g,
    '<img src="$2" alt="$1" class="max-w-full rounded-lg my-1" loading="lazy" />',
  )

  // Convert markdown links
  content = content.replace(
    /\[([^\]]+)\]\(([^)]+)\)/g,
    `<a href="$2" target="_blank" rel="noopener" class="${props.isOwn ? 'text-blue-300 hover:text-blue-200' : 'text-blue-500 hover:text-blue-600'} underline">$1</a>`,
  )

  // Convert bare URLs
  content = content.replace(
    /(?<!="|='|=)(https?:\/\/[^\s<"']+)/g,
    `<a href="$1" target="_blank" rel="noopener" class="${props.isOwn ? 'text-blue-300 hover:text-blue-200' : 'text-blue-500 hover:text-blue-600'} underline">$1</a>`,
  )

  // Convert **bold**
  content = content.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')

  // Convert *italic*
  content = content.replace(/\*(.+?)\*/g, '<em>$1</em>')

  // Convert `inline code`
  const inlineCodeClasses = props.isOwn
    ? 'px-1 py-0.5 bg-white/15 rounded text-xs font-mono'
    : 'px-1 py-0.5 bg-neutral-200 dark:bg-neutral-700 rounded text-xs font-mono'
  content = content.replace(/`([^`\n]+?)`/g, `<code class="${inlineCodeClasses}">$1</code>`)

  // Convert numbered lists
  content = content.replace(/^(\d+)\.\s+(.+)$/gm, '<li class="ml-3 list-decimal">$2</li>')

  // Convert bullet lists
  content = content.replace(/^[\-]\s+(.+)$/gm, '<li class="ml-3 list-disc">$1</li>')

  // Wrap consecutive list items
  content = content.replace(/(<li class="ml-3 list-decimal">.+<\/li>\n?)+/g, '<ol class="my-0.5 pl-4">$&</ol>')
  content = content.replace(/(<li class="ml-3 list-disc">.+<\/li>\n?)+/g, '<ul class="my-0.5 pl-4">$&</ul>')

  // Highlight search matches
  if (props.searchQuery && props.searchQuery.trim()) {
    const query = props.searchQuery.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
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

// ── Computed Classes ──────────────────────────────────────────────

// Top container: flex row, align left or right
const containerClasses = computed(() => [
  'group relative flex',
  props.isOwn ? 'justify-end' : 'justify-start',
  props.highlighted && 'bg-amber-50/50 dark:bg-amber-950/30 rounded-xl',
])

// Bubble row: avatar + bubble content
const bubbleRowClasses = computed(() => [
  'flex items-end gap-2',
  props.isOwn ? 'flex-row-reverse' : '',
  'max-w-[90%] md:max-w-[80%]',
])

// The bubble itself
const bubbleClasses = computed(() => {
  const base = ['relative px-3 py-2 min-w-[3rem]']

  if (props.isOwn) {
    base.push('bg-neutral-900 dark:bg-blue-950 text-white')

    // Border radius based on grouping
    if (props.isFirstInGroup && props.isLastInGroup) {
      base.push('rounded-2xl rounded-br-md')
    } else if (props.isFirstInGroup) {
      base.push('rounded-2xl rounded-br-md')
    } else if (props.isLastInGroup) {
      base.push('rounded-2xl rounded-br-md')
    } else {
      base.push('rounded-2xl rounded-r-md')
    }
  } else {
    base.push('bg-neutral-100 dark:bg-neutral-800 text-neutral-900 dark:text-white')

    if (props.isFirstInGroup && props.isLastInGroup) {
      base.push('rounded-2xl rounded-bl-md')
    } else if (props.isFirstInGroup) {
      base.push('rounded-2xl rounded-bl-md')
    } else if (props.isLastInGroup) {
      base.push('rounded-2xl rounded-bl-md')
    } else {
      base.push('rounded-2xl rounded-l-md')
    }
  }

  return base
})

// Text classes
const textClasses = computed(() => [
  'leading-normal whitespace-pre-wrap break-words',
  props.size === 'sm' ? 'text-xs' : props.size === 'lg' ? 'text-base' : 'text-sm',
])

// Inline timestamp
const inlineTimestampClasses = computed(() => [
  'inline-flex items-center gap-1 float-right ml-3 mt-1.5 select-none',
  'text-[10px] leading-none',
  props.isOwn
    ? 'text-white/40'
    : 'text-neutral-400 dark:text-neutral-500',
])

// Reactions (outside bubble)
const reactionsClasses = computed(() => [
  'flex items-center gap-1 mt-1 flex-wrap',
  props.isOwn ? 'justify-end' : 'justify-start',
])

// Thread preview (outside bubble)
const threadPreviewClasses = computed(() => [
  'flex items-center gap-2 mt-1 py-1 px-2 rounded-md text-xs',
  'text-neutral-500 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors',
  props.isOwn ? 'ml-auto' : '',
])

// Hover actions (floating above bubble)
const hoverActionsClasses = computed(() => [
  'absolute -top-4 z-10',
  props.isOwn ? 'left-0' : 'right-0',
  'flex items-center gap-0.5 p-0.5',
  'bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 rounded-lg',
  'shadow-md',
])

// Action button in hover toolbar
const actionButtonClasses = computed(() => [
  'p-1.5 rounded-lg',
  'text-neutral-500 dark:text-neutral-300',
  'transition-colors duration-150',
  'hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-700',
  'focus:outline-none focus-visible:ring-1 focus-visible:ring-neutral-400 dark:focus-visible:ring-neutral-500',
])

// ── Time formatting ──────────────────────────────────────────────

const formatTime = (date: Date) => {
  return new Date(date).toLocaleTimeString('en-US', {
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  })
}

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

// ── Utilities ────────────────────────────────────────────────────

const truncateReply = (content: string, maxLength = 60) => {
  if (content.length <= maxLength) return content
  return content.slice(0, maxLength) + '...'
}

const getAttachmentIcon = (type?: string) => {
  if (!type) return 'ph:file'
  if (type.startsWith('image/')) return 'ph:image'
  if (type.startsWith('video/')) return 'ph:video'
  if (type.startsWith('audio/')) return 'ph:music-note'
  if (type.includes('pdf')) return 'ph:file-pdf'
  if (type.includes('zip') || type.includes('rar')) return 'ph:file-zip'
  return 'ph:file'
}

const getFileTypeLabel = (name?: string, type?: string): string => {
  const ext = name?.split('.').pop()?.toUpperCase()
  if (ext && ext.length <= 5) return ext
  if (!type) return 'File'
  if (type.includes('pdf')) return 'PDF'
  if (type.includes('zip')) return 'ZIP'
  if (type.includes('rar')) return 'RAR'
  if (type.startsWith('video/')) return 'Video'
  if (type.startsWith('audio/')) return 'Audio'
  if (type.startsWith('image/')) return 'Image'
  return 'File'
}

const getFileIconColor = (type?: string, isOwn = false): string => {
  if (isOwn) return 'bg-white/10'
  if (!type) return 'bg-neutral-100 dark:bg-neutral-600'
  if (type.includes('pdf')) return 'bg-red-100 dark:bg-red-900/30'
  if (type.includes('zip') || type.includes('rar')) return 'bg-amber-100 dark:bg-amber-900/30'
  if (type.startsWith('image/')) return 'bg-blue-100 dark:bg-blue-900/30'
  if (type.startsWith('audio/')) return 'bg-purple-100 dark:bg-purple-900/30'
  if (type.startsWith('video/')) return 'bg-pink-100 dark:bg-pink-900/30'
  return 'bg-neutral-100 dark:bg-neutral-600'
}

const getFileIconTextColor = (type?: string, isOwn = false): string => {
  if (isOwn) return 'text-white/70'
  if (!type) return 'text-neutral-500 dark:text-neutral-300'
  if (type.includes('pdf')) return 'text-red-600 dark:text-red-400'
  if (type.includes('zip') || type.includes('rar')) return 'text-amber-600 dark:text-amber-400'
  if (type.startsWith('image/')) return 'text-blue-600 dark:text-blue-400'
  if (type.startsWith('audio/')) return 'text-purple-600 dark:text-purple-400'
  if (type.startsWith('video/')) return 'text-pink-600 dark:text-pink-400'
  return 'text-neutral-500 dark:text-neutral-300'
}

const formatFileSize = (bytes: number) => {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

const copyCode = async (code: string) => {
  await navigator.clipboard.writeText(code)
  copiedCode.value = code
  setTimeout(() => {
    copiedCode.value = null
  }, 2000)
}

// ── Handlers ─────────────────────────────────────────────────────

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
/* Fade transition for hover actions */
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

/* Lightbox transition */
.lightbox-enter-active {
  transition: opacity 0.2s ease-out;
}

.lightbox-leave-active {
  transition: opacity 0.15s ease-in;
}

.lightbox-enter-from,
.lightbox-leave-to {
  opacity: 0;
}
</style>
