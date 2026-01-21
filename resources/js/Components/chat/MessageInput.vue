<template>
  <div :class="containerClasses">
    <!-- Reply Preview -->
    <Transition name="slide-up">
      <div v-if="replyTo" :class="replyPreviewClasses">
        <div class="flex items-center gap-2 flex-1 min-w-0">
          <div class="w-0.5 h-full bg-gray-900 rounded-full shrink-0" />
          <div class="flex-1 min-w-0">
            <p class="text-xs font-medium text-gray-900">
              Replying to {{ replyTo.author.name }}
            </p>
            <p class="text-xs text-gray-500 truncate">
              {{ replyTo.content }}
            </p>
          </div>
        </div>
        <button
          type="button"
          class="p-1.5 rounded-lg text-gray-500 transition-colors duration-150 hover:text-gray-900 hover:bg-gray-100"
          @click="emit('cancelReply')"
        >
          <Icon name="ph:x" class="w-4 h-4" />
        </button>
      </div>
    </Transition>

    <!-- Edit Mode Banner -->
    <Transition name="slide-up">
      <div v-if="editMessage" :class="editBannerClasses">
        <div class="flex items-center gap-2">
          <Icon name="ph:pencil-simple" class="w-4 h-4 text-gray-600" />
          <span class="text-xs font-medium text-gray-600">Editing message</span>
        </div>
        <button
          type="button"
          class="px-2 py-1 text-xs text-gray-500 rounded-lg transition-colors duration-150 hover:text-gray-900 hover:bg-gray-100"
          @click="handleCancelEdit"
        >
          Cancel
        </button>
      </div>
    </Transition>

    <!-- Attachments Preview -->
    <Transition name="slide-up">
      <div v-if="attachments.length > 0" :class="attachmentsPreviewClasses">
        <TransitionGroup name="attachment" tag="div" class="flex gap-2 overflow-x-auto scrollbar-none">
          <div
            v-for="(attachment, index) in attachments"
            :key="attachment.id"
            :class="attachmentItemClasses"
          >
            <!-- Image Preview -->
            <template v-if="attachment.type.startsWith('image/')">
              <img
                :src="attachment.preview"
                :alt="attachment.name"
                class="w-full h-full object-cover rounded-lg"
              >
            </template>

            <!-- File Preview -->
            <template v-else>
              <div class="flex flex-col items-center justify-center h-full p-2">
                <Icon :name="getFileIcon(attachment.type)" :class="fileIconClasses(attachment.type)" />
                <span class="text-[10px] text-gray-500 truncate w-full text-center mt-1">
                  {{ attachment.name }}
                </span>
              </div>
            </template>

            <!-- Upload Progress -->
            <div
              v-if="attachment.uploading"
              class="absolute inset-0 bg-white/80 rounded-lg flex items-center justify-center"
            >
              <div class="w-8 h-8 relative">
                <svg class="w-full h-full -rotate-90" viewBox="0 0 36 36">
                  <circle
                    cx="18"
                    cy="18"
                    r="16"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="3"
                    class="text-gray-200"
                  />
                  <circle
                    cx="18"
                    cy="18"
                    r="16"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="3"
                    stroke-linecap="round"
                    class="text-gray-900"
                    :stroke-dasharray="100"
                    :stroke-dashoffset="100 - (attachment.progress || 0)"
                  />
                </svg>
                <span class="absolute inset-0 flex items-center justify-center text-[10px] font-medium text-gray-900">
                  {{ attachment.progress || 0 }}%
                </span>
              </div>
            </div>

            <!-- Remove Button -->
            <button
              v-if="!attachment.uploading"
              type="button"
              class="absolute -top-1.5 -right-1.5 w-5 h-5 bg-white border border-gray-200 rounded-full flex items-center justify-center text-gray-500 shadow-sm transition-colors duration-150 hover:text-red-600 hover:border-red-300 hover:bg-red-50"
              @click="removeAttachment(index)"
            >
              <Icon name="ph:x" class="w-3 h-3" />
            </button>
          </div>

          <!-- Add More Button -->
          <button
            v-if="attachments.length < maxAttachments"
            type="button"
            :class="addAttachmentButtonClasses"
            @click="openFilePicker"
          >
            <Icon name="ph:plus" class="w-5 h-5" />
          </button>
        </TransitionGroup>
      </div>
    </Transition>

    <!-- Main Input Area -->
    <div :class="inputAreaClasses">
      <!-- Left Actions -->
      <div class="flex items-end gap-0.5 shrink-0">
        <!-- Attach File -->
        <TooltipProvider :delay-duration="300">
          <TooltipRoot>
            <TooltipTrigger as-child>
              <button
                type="button"
                :class="actionButtonClasses"
                :disabled="sending || attachments.length >= maxAttachments"
                @click="openFilePicker"
              >
                <Icon name="ph:plus-circle" class="w-5 h-5" />
              </button>
            </TooltipTrigger>
            <TooltipPortal>
              <TooltipContent :class="tooltipClasses" side="top" :side-offset="5">
                Attach file
                <TooltipArrow class="fill-white" />
              </TooltipContent>
            </TooltipPortal>
          </TooltipRoot>
        </TooltipProvider>
      </div>

      <!-- Input Container -->
      <div class="flex-1 min-w-0 relative">
        <!-- Formatting Toolbar (visible when focused and showFormatting) -->
        <Transition name="slide-down">
          <div v-if="showFormattingToolbar && isFocused" :class="formattingToolbarClasses">
            <button
              v-for="format in formatOptions"
              :key="format.action"
              type="button"
              :class="formatButtonClasses(format.active)"
              :title="format.label"
              @mousedown.prevent="applyFormat(format.action)"
            >
              <Icon :name="format.icon" class="w-4 h-4" />
            </button>
          </div>
        </Transition>

        <!-- Textarea -->
        <textarea
          ref="textarea"
          v-model="message"
          :placeholder="placeholder"
          :rows="1"
          :disabled="sending"
          :maxlength="maxLength"
          :class="textareaClasses"
          @input="handleInput"
          @keydown="handleKeydown"
          @focus="handleFocus"
          @blur="handleBlur"
          @paste="handlePaste"
        />

        <!-- Mentions Popup -->
        <Transition name="fade-scale">
          <div
            v-if="showMentions && mentionResults.length > 0"
            ref="mentionsPopup"
            :class="mentionsPopupClasses"
          >
            <button
              v-for="(user, index) in mentionResults"
              :key="user.id"
              type="button"
              :class="mentionItemClasses(index === selectedMentionIndex)"
              @click="selectMention(user)"
              @mouseenter="selectedMentionIndex = index"
            >
              <SharedAgentAvatar :user="user" size="xs" />
              <div class="flex-1 min-w-0">
                <span class="text-sm text-gray-900 truncate block">{{ user.name }}</span>
                <span v-if="user.type === 'agent'" class="text-xs text-gray-500">Agent</span>
              </div>
            </button>
          </div>
        </Transition>

        <!-- Slash Commands Popup -->
        <Transition name="fade-scale">
          <div
            v-if="showCommands && commandResults.length > 0"
            ref="commandsPopup"
            :class="commandsPopupClasses"
          >
            <div class="text-xs font-medium text-gray-500 uppercase tracking-wider px-3 py-2">
              Commands
            </div>
            <button
              v-for="(command, index) in commandResults"
              :key="command.name"
              type="button"
              :class="commandItemClasses(index === selectedCommandIndex)"
              @click="selectCommand(command)"
              @mouseenter="selectedCommandIndex = index"
            >
              <Icon :name="command.icon" class="w-4 h-4 text-gray-500" />
              <div class="flex-1 min-w-0">
                <span class="text-sm text-gray-900 font-medium">{{ command.name }}</span>
                <span class="text-xs text-gray-500 ml-2">{{ command.description }}</span>
              </div>
            </button>
          </div>
        </Transition>
      </div>

      <!-- Right Actions -->
      <div class="flex items-end gap-0.5 shrink-0">
        <!-- Emoji Picker -->
        <PopoverRoot v-model:open="emojiPickerOpen">
          <PopoverTrigger as-child>
            <TooltipProvider :delay-duration="300">
              <TooltipRoot>
                <TooltipTrigger as-child>
                  <button
                    type="button"
                    :class="actionButtonClasses"
                    :disabled="sending"
                  >
                    <Icon name="ph:smiley" class="w-5 h-5" />
                  </button>
                </TooltipTrigger>
                <TooltipPortal>
                  <TooltipContent :class="tooltipClasses" side="top" :side-offset="5">
                    Add emoji
                    <TooltipArrow class="fill-white" />
                  </TooltipContent>
                </TooltipPortal>
              </TooltipRoot>
            </TooltipProvider>
          </PopoverTrigger>
          <PopoverPortal>
            <PopoverContent :class="emojiPickerClasses" side="top" :side-offset="8" align="end">
              <EmojiPicker @select="insertEmoji" />
            </PopoverContent>
          </PopoverPortal>
        </PopoverRoot>

        <!-- Mention -->
        <TooltipProvider :delay-duration="300">
          <TooltipRoot>
            <TooltipTrigger as-child>
              <button
                type="button"
                :class="actionButtonClasses"
                :disabled="sending"
                @click="insertMention"
              >
                <Icon name="ph:at" class="w-5 h-5" />
              </button>
            </TooltipTrigger>
            <TooltipPortal>
              <TooltipContent :class="tooltipClasses" side="top" :side-offset="5">
                Mention someone
                <TooltipArrow class="fill-white" />
              </TooltipContent>
            </TooltipPortal>
          </TooltipRoot>
        </TooltipProvider>

        <!-- Voice Recording (if enabled) -->
        <TooltipProvider v-if="showVoiceRecording" :delay-duration="300">
          <TooltipRoot>
            <TooltipTrigger as-child>
              <button
                type="button"
                :class="[actionButtonClasses, isRecording && 'text-red-600']"
                :disabled="sending"
                @click="toggleRecording"
              >
                <Icon :name="isRecording ? 'ph:stop-circle-fill' : 'ph:microphone'" class="w-5 h-5" />
              </button>
            </TooltipTrigger>
            <TooltipPortal>
              <TooltipContent :class="tooltipClasses" side="top" :side-offset="5">
                {{ isRecording ? 'Stop recording' : 'Voice message' }}
                <TooltipArrow class="fill-white" />
              </TooltipContent>
            </TooltipPortal>
          </TooltipRoot>
        </TooltipProvider>

        <!-- Send Button -->
        <button
          type="button"
          :class="sendButtonClasses"
          :disabled="!canSend"
          @click="handleSend"
        >
          <Icon v-if="sending" name="ph:spinner" class="w-4 h-4 animate-spin" />
          <Icon v-else-if="editMessage" name="ph:check" class="w-4 h-4" />
          <Icon v-else name="ph:paper-plane-tilt" class="w-4 h-4" />
        </button>
      </div>
    </div>

    <!-- Footer Info -->
    <div :class="footerClasses">
      <div class="flex items-center gap-3">
        <!-- Keyboard Hints -->
        <span v-if="showKeyboardHints" class="text-xs text-gray-400">
          <kbd :class="kbdClasses">Enter</kbd> to send,
          <kbd :class="kbdClasses">Shift+Enter</kbd> for new line
        </span>
      </div>

      <div class="flex items-center gap-3">
        <!-- Character Count -->
        <Transition name="fade">
          <span
            v-if="showCharacterCount && message.length > maxLength * 0.8"
            :class="characterCountClasses"
          >
            {{ message.length }}/{{ maxLength }}
          </span>
        </Transition>

        <!-- Draft Indicator -->
        <Transition name="fade">
          <span
            v-if="hasDraft && !message"
            class="text-xs text-gray-500 flex items-center gap-1"
          >
            <Icon name="ph:note-pencil" class="w-3 h-3" />
            Draft saved
          </span>
        </Transition>
      </div>
    </div>

    <!-- Hidden File Input -->
    <input
      ref="fileInput"
      type="file"
      multiple
      :accept="acceptedFileTypes"
      class="hidden"
      @change="handleFileSelect"
    >
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, nextTick, h, defineComponent } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import {
  TooltipArrow,
  TooltipContent,
  TooltipPortal,
  TooltipProvider,
  TooltipRoot,
  TooltipTrigger,
  PopoverRoot,
  PopoverTrigger,
  PopoverPortal,
  PopoverContent,
} from 'reka-ui'
import type { Channel, User, Message } from '@/types'

type MessageInputSize = 'sm' | 'md' | 'lg'
type MessageInputVariant = 'default' | 'compact' | 'minimal'

interface Attachment {
  id: string
  file: File
  name: string
  type: string
  size: number
  preview?: string
  uploading?: boolean
  progress?: number
}

interface SlashCommand {
  name: string
  description: string
  icon: string
  action: () => void
}

interface ReplyMessage {
  id: string
  content: string
  author: User
}

const props = withDefaults(defineProps<{
  // Core
  channel: Channel
  members?: User[]

  // Editing/Replying
  editMessage?: Message | null
  replyTo?: ReplyMessage | null

  // Appearance
  size?: MessageInputSize
  variant?: MessageInputVariant

  // Features
  showFormatting?: boolean
  showKeyboardHints?: boolean
  showCharacterCount?: boolean
  showVoiceRecording?: boolean
  showCommands?: boolean

  // Limits
  maxLength?: number
  maxAttachments?: number
  maxFileSize?: number

  // State
  sending?: boolean
}>(), {
  members: () => [],
  editMessage: null,
  replyTo: null,
  size: 'md',
  variant: 'default',
  showFormatting: true,
  showKeyboardHints: true,
  showCharacterCount: true,
  showVoiceRecording: false,
  showCommands: true,
  maxLength: 4000,
  maxAttachments: 10,
  maxFileSize: 10 * 1024 * 1024, // 10MB
  sending: false,
})

const emit = defineEmits<{
  send: [message: string, attachments?: Attachment[]]
  edit: [message: string]
  cancelEdit: []
  cancelReply: []
  command: [command: string]
  typing: []
  recordingStart: []
  recordingStop: [blob: Blob]
}>()

// Refs
const textarea = ref<HTMLTextAreaElement | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)
const mentionsPopup = ref<HTMLElement | null>(null)
const commandsPopup = ref<HTMLElement | null>(null)

// State
const message = ref('')
const isFocused = ref(false)
const attachments = ref<Attachment[]>([])
const emojiPickerOpen = ref(false)
const isRecording = ref(false)
const hasDraft = ref(false)

// Mentions state
const showMentions = ref(false)
const mentionQuery = ref('')
const mentionStartIndex = ref(0)
const selectedMentionIndex = ref(0)

// Commands state
const showCommandsPopup = ref(false)
const commandQuery = ref('')
const selectedCommandIndex = ref(0)

// Size configurations
const sizeConfig: Record<MessageInputSize, {
  container: string
  input: string
  button: string
  sendButton: string
}> = {
  sm: {
    container: 'p-2',
    input: 'px-3 py-2 text-sm',
    button: 'p-1',
    sendButton: 'h-7 px-3 text-xs',
  },
  md: {
    container: 'p-4',
    input: 'px-4 py-3 text-sm',
    button: 'p-1.5',
    sendButton: 'h-9 px-4 text-sm',
  },
  lg: {
    container: 'p-5',
    input: 'px-5 py-4 text-base',
    button: 'p-2',
    sendButton: 'h-11 px-5 text-base',
  },
}

// Format options
const formatOptions = computed(() => [
  { action: 'bold', icon: 'ph:text-b', label: 'Bold', active: false },
  { action: 'italic', icon: 'ph:text-italic', label: 'Italic', active: false },
  { action: 'strikethrough', icon: 'ph:text-strikethrough', label: 'Strikethrough', active: false },
  { action: 'code', icon: 'ph:code', label: 'Code', active: false },
  { action: 'codeblock', icon: 'ph:code-block', label: 'Code block', active: false },
  { action: 'quote', icon: 'ph:quotes', label: 'Quote', active: false },
  { action: 'link', icon: 'ph:link', label: 'Link', active: false },
])

// Slash commands
const slashCommands = computed<SlashCommand[]>(() => [
  { name: '/giphy', description: 'Search for a GIF', icon: 'ph:gif', action: () => {} },
  { name: '/poll', description: 'Create a poll', icon: 'ph:chart-bar', action: () => {} },
  { name: '/remind', description: 'Set a reminder', icon: 'ph:alarm', action: () => {} },
  { name: '/status', description: 'Set your status', icon: 'ph:user-circle', action: () => {} },
  { name: '/invite', description: 'Invite someone', icon: 'ph:user-plus', action: () => {} },
])

// Filtered commands
const commandResults = computed(() => {
  if (!commandQuery.value) return slashCommands.value
  return slashCommands.value.filter(cmd =>
    cmd.name.toLowerCase().includes(commandQuery.value.toLowerCase()),
  )
})

// Filtered mentions
const mentionResults = computed(() => {
  if (!mentionQuery.value) return props.members.slice(0, 8)
  return props.members
    .filter(m => m.name.toLowerCase().includes(mentionQuery.value.toLowerCase()))
    .slice(0, 8)
})

// Computed
const canSend = computed(() =>
  (message.value.trim() || attachments.value.length > 0) && !props.sending,
)

const placeholder = computed(() => {
  if (props.editMessage) return 'Edit your message...'
  if (props.replyTo) return `Reply to ${props.replyTo.author.name}...`
  return `Message #${props.channel.name}`
})

const showFormattingToolbar = computed(() =>
  props.showFormatting && props.variant !== 'minimal',
)

const acceptedFileTypes = computed(() =>
  'image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar',
)

// Initialize from edit message
watch(() => props.editMessage, (editMsg) => {
  if (editMsg) {
    message.value = editMsg.content
    nextTick(() => {
      textarea.value?.focus()
      autoResize()
    })
  }
}, { immediate: true })

// Container classes
const containerClasses = computed(() => {
  const classes = [
    'border-t border-gray-200 shrink-0 bg-white',
    sizeConfig[props.size].container,
  ]

  if (props.variant === 'compact') {
    classes.push('border-0 bg-transparent')
  }

  return classes
})

// Reply preview classes
const replyPreviewClasses = computed(() => [
  'flex items-center gap-2 mb-2 px-3 py-2 bg-gray-50 rounded-lg',
])

// Edit banner classes
const editBannerClasses = computed(() => [
  'flex items-center justify-between mb-2 px-3 py-2 bg-gray-100 border border-gray-200 rounded-lg',
])

// Attachments preview classes
const attachmentsPreviewClasses = computed(() => [
  'mb-3 px-3 py-2 bg-gray-50 rounded-lg',
])

// Attachment item classes
const attachmentItemClasses = computed(() => [
  'group/attach relative w-20 h-20 bg-white rounded-lg shrink-0 overflow-hidden',
  'border border-gray-200',
  'transition-colors duration-150',
  'hover:border-gray-300',
])

// Add attachment button classes
const addAttachmentButtonClasses = computed(() => [
  'group/addattach w-20 h-20 flex items-center justify-center shrink-0',
  'bg-gray-50 rounded-lg',
  'border-2 border-dashed border-gray-200',
  'text-gray-500',
  'transition-colors duration-150',
  'hover:bg-gray-100 hover:border-gray-300 hover:text-gray-900',
])

// Input area classes
const inputAreaClasses = computed(() => {
  const classes = [
    'flex items-end gap-3 bg-white rounded-lg border',
    'transition-colors duration-150',
    sizeConfig[props.size].input,
  ]

  if (isFocused.value) {
    classes.push(
      'border-gray-400 ring-1 ring-gray-200',
    )
  } else {
    classes.push('border-gray-200 hover:border-gray-300')
  }

  if (props.sending) {
    classes.push('opacity-75')
  }

  return classes
})

// Formatting toolbar classes
const formattingToolbarClasses = computed(() => [
  'absolute bottom-full left-0 mb-2 flex items-center gap-1 px-2 py-1.5',
  'bg-white border border-gray-200 rounded-lg',
  'shadow-md',
])

// Format button classes
const formatButtonClasses = (active: boolean) => [
  'p-1.5 rounded-lg transition-colors duration-150',
  active
    ? 'bg-gray-100 text-gray-900'
    : 'text-gray-500 hover:text-gray-900 hover:bg-gray-50',
]

// Textarea classes
const textareaClasses = computed(() => [
  'w-full bg-transparent outline-none text-gray-900',
  'placeholder:text-gray-400 resize-none max-h-32',
  'disabled:opacity-50 disabled:cursor-not-allowed',
  props.size === 'lg' ? 'text-base' : 'text-sm',
])

// Action button classes
const actionButtonClasses = computed(() => [
  'rounded-lg text-gray-500',
  'transition-colors duration-150',
  'hover:text-gray-900 hover:bg-gray-100',
  'outline-none focus-visible:ring-1 focus-visible:ring-gray-400',
  'disabled:opacity-50 disabled:cursor-not-allowed',
  sizeConfig[props.size].button,
])

// Send button classes
const sendButtonClasses = computed(() => {
  const classes = [
    'ml-2 rounded-lg font-medium outline-none',
    'transition-colors duration-150',
    'focus-visible:ring-1 focus-visible:ring-gray-400',
    sizeConfig[props.size].sendButton,
  ]

  if (canSend.value) {
    classes.push(
      'bg-gray-900 hover:bg-gray-800 text-white',
    )
  } else {
    classes.push('bg-gray-100 text-gray-400 cursor-not-allowed')
  }

  return classes
})

// Mentions popup classes
const mentionsPopupClasses = computed(() => [
  'absolute bottom-full left-0 mb-2 w-64 max-h-64 overflow-y-auto',
  'bg-white border border-gray-200 rounded-lg',
  'shadow-lg',
  'z-50',
])

// Mention item classes
const mentionItemClasses = (selected: boolean) => [
  'w-full flex items-center gap-2 px-3 py-2',
  'transition-colors duration-150',
  selected
    ? 'bg-gray-100 text-gray-900'
    : 'text-gray-500 hover:bg-gray-50',
]

// Commands popup classes
const commandsPopupClasses = computed(() => [
  'absolute bottom-full left-0 mb-2 w-72 max-h-64 overflow-y-auto',
  'bg-white border border-gray-200 rounded-lg',
  'shadow-lg',
  'z-50',
])

// Command item classes
const commandItemClasses = (selected: boolean) => [
  'w-full flex items-center gap-3 px-3 py-2',
  'transition-colors duration-150',
  selected
    ? 'bg-gray-100'
    : 'hover:bg-gray-50',
]

// Tooltip classes
const tooltipClasses = computed(() => [
  'z-50 bg-white border border-gray-200 rounded-lg',
  'px-2.5 py-1.5 text-xs shadow-md',
  'animate-in fade-in-0 duration-150',
])

// Emoji picker classes
const emojiPickerClasses = computed(() => [
  'bg-white border border-gray-200 rounded-lg',
  'shadow-lg',
  'animate-in fade-in-0 duration-150',
])

// Footer classes
const footerClasses = computed(() => [
  'flex items-center justify-between mt-2 px-1',
])

// Kbd classes
const kbdClasses = computed(() => [
  'inline-flex px-1.5 py-0.5 bg-gray-100 border border-gray-200 rounded text-[10px] font-mono text-gray-500',
])

// Character count classes
const characterCountClasses = computed(() => {
  const percentage = message.value.length / props.maxLength
  const classes = ['text-xs font-medium']

  if (percentage >= 1) {
    classes.push('text-red-600')
  } else if (percentage >= 0.9) {
    classes.push('text-amber-600')
  } else {
    classes.push('text-gray-400')
  }

  return classes
})

// File icon helpers
const getFileIcon = (type: string): string => {
  if (type.startsWith('image/')) return 'ph:image'
  if (type.startsWith('video/')) return 'ph:video'
  if (type.startsWith('audio/')) return 'ph:music-note'
  if (type.includes('pdf')) return 'ph:file-pdf'
  if (type.includes('document') || type.includes('word')) return 'ph:file-doc'
  if (type.includes('sheet') || type.includes('excel')) return 'ph:file-xls'
  if (type.includes('presentation') || type.includes('powerpoint')) return 'ph:file-ppt'
  if (type.includes('zip') || type.includes('rar')) return 'ph:file-zip'
  return 'ph:file'
}

const fileIconClasses = (type: string) => {
  const baseClasses = 'w-8 h-8 text-gray-500'
  return [baseClasses]
}

// Auto resize textarea
const autoResize = () => {
  if (textarea.value) {
    textarea.value.style.height = 'auto'
    textarea.value.style.height = `${Math.min(textarea.value.scrollHeight, 128)}px`
  }
}

// Handle input
const handleInput = () => {
  autoResize()
  emit('typing')
  checkForMention()
  checkForCommand()
}

// Handle keydown
const handleKeydown = (event: KeyboardEvent) => {
  // Enter to send
  if (event.key === 'Enter' && !event.shiftKey) {
    if (showMentions.value && mentionResults.value.length > 0) {
      event.preventDefault()
      selectMention(mentionResults.value[selectedMentionIndex.value])
      return
    }
    if (showCommandsPopup.value && commandResults.value.length > 0) {
      event.preventDefault()
      selectCommand(commandResults.value[selectedCommandIndex.value])
      return
    }
    event.preventDefault()
    handleSend()
    return
  }

  // Navigate mentions/commands
  if (showMentions.value || showCommandsPopup.value) {
    const results = showMentions.value ? mentionResults.value : commandResults.value
    const indexRef = showMentions.value ? selectedMentionIndex : selectedCommandIndex

    if (event.key === 'ArrowDown') {
      event.preventDefault()
      indexRef.value = Math.min(indexRef.value + 1, results.length - 1)
    } else if (event.key === 'ArrowUp') {
      event.preventDefault()
      indexRef.value = Math.max(indexRef.value - 1, 0)
    } else if (event.key === 'Escape') {
      showMentions.value = false
      showCommandsPopup.value = false
    }
  }

  // Escape to cancel edit
  if (event.key === 'Escape' && props.editMessage) {
    handleCancelEdit()
  }
}

// Handle focus
const handleFocus = () => {
  isFocused.value = true
}

// Handle blur
const handleBlur = () => {
  isFocused.value = false
  // Delay hiding popups to allow clicks
  setTimeout(() => {
    showMentions.value = false
    showCommandsPopup.value = false
  }, 200)
}

// Handle paste
const handlePaste = (event: ClipboardEvent) => {
  const items = event.clipboardData?.items
  if (!items) return

  for (const item of items) {
    if (item.kind === 'file') {
      const file = item.getAsFile()
      if (file) {
        event.preventDefault()
        addAttachment(file)
      }
    }
  }
}

// Check for mention
const checkForMention = () => {
  const cursorPos = textarea.value?.selectionStart || 0
  const textBeforeCursor = message.value.slice(0, cursorPos)
  const mentionMatch = textBeforeCursor.match(/@(\w*)$/)

  if (mentionMatch) {
    mentionQuery.value = mentionMatch[1]
    mentionStartIndex.value = cursorPos - mentionMatch[0].length
    showMentions.value = true
    selectedMentionIndex.value = 0
  } else {
    showMentions.value = false
  }
}

// Check for command
const checkForCommand = () => {
  if (message.value.startsWith('/')) {
    const spaceIndex = message.value.indexOf(' ')
    commandQuery.value = spaceIndex > -1 ? message.value.slice(1, spaceIndex) : message.value.slice(1)
    showCommandsPopup.value = true
    selectedCommandIndex.value = 0
  } else {
    showCommandsPopup.value = false
  }
}

// Select mention
const selectMention = (user: User) => {
  const cursorPos = textarea.value?.selectionStart || 0
  const before = message.value.slice(0, mentionStartIndex.value)
  const after = message.value.slice(cursorPos)
  message.value = `${before}@${user.name} ${after}`
  showMentions.value = false
  nextTick(() => {
    const newPos = mentionStartIndex.value + user.name.length + 2
    textarea.value?.setSelectionRange(newPos, newPos)
    textarea.value?.focus()
  })
}

// Select command
const selectCommand = (command: SlashCommand) => {
  message.value = `${command.name} `
  showCommandsPopup.value = false
  textarea.value?.focus()
}

// Insert mention
const insertMention = () => {
  const cursorPos = textarea.value?.selectionStart || message.value.length
  const before = message.value.slice(0, cursorPos)
  const after = message.value.slice(cursorPos)
  message.value = `${before}@${after}`
  nextTick(() => {
    textarea.value?.setSelectionRange(cursorPos + 1, cursorPos + 1)
    textarea.value?.focus()
    checkForMention()
  })
}

// Insert emoji
const insertEmoji = (emoji: string) => {
  const cursorPos = textarea.value?.selectionStart || message.value.length
  const before = message.value.slice(0, cursorPos)
  const after = message.value.slice(cursorPos)
  message.value = `${before}${emoji}${after}`
  emojiPickerOpen.value = false
  nextTick(() => {
    const newPos = cursorPos + emoji.length
    textarea.value?.setSelectionRange(newPos, newPos)
    textarea.value?.focus()
  })
}

// Apply format
const applyFormat = (format: string) => {
  if (!textarea.value) return

  const start = textarea.value.selectionStart
  const end = textarea.value.selectionEnd
  const selectedText = message.value.slice(start, end)

  const formatMap: Record<string, { prefix: string; suffix: string }> = {
    bold: { prefix: '**', suffix: '**' },
    italic: { prefix: '_', suffix: '_' },
    strikethrough: { prefix: '~~', suffix: '~~' },
    code: { prefix: '`', suffix: '`' },
    codeblock: { prefix: '```\n', suffix: '\n```' },
    quote: { prefix: '> ', suffix: '' },
    link: { prefix: '[', suffix: '](url)' },
  }

  const { prefix, suffix } = formatMap[format] || { prefix: '', suffix: '' }
  const before = message.value.slice(0, start)
  const after = message.value.slice(end)

  message.value = `${before}${prefix}${selectedText}${suffix}${after}`

  nextTick(() => {
    const newStart = start + prefix.length
    const newEnd = newStart + selectedText.length
    textarea.value?.setSelectionRange(newStart, newEnd)
    textarea.value?.focus()
  })
}

// File handling
const openFilePicker = () => {
  fileInput.value?.click()
}

const handleFileSelect = (event: Event) => {
  const input = event.target as HTMLInputElement
  const files = input.files
  if (!files) return

  for (const file of files) {
    if (attachments.value.length >= props.maxAttachments) break
    addAttachment(file)
  }

  input.value = ''
}

const addAttachment = async (file: File) => {
  if (file.size > props.maxFileSize) {
    // TODO: Show error toast
    return
  }

  const attachment: Attachment = {
    id: crypto.randomUUID(),
    file,
    name: file.name,
    type: file.type,
    size: file.size,
    uploading: false,
    progress: 0,
  }

  // Create preview for images
  if (file.type.startsWith('image/')) {
    attachment.preview = URL.createObjectURL(file)
  }

  attachments.value.push(attachment)
}

const removeAttachment = (index: number) => {
  const attachment = attachments.value[index]
  if (attachment.preview) {
    URL.revokeObjectURL(attachment.preview)
  }
  attachments.value.splice(index, 1)
}

// Voice recording
const toggleRecording = async () => {
  if (isRecording.value) {
    isRecording.value = false
    // TODO: Stop recording and emit blob
    emit('recordingStop', new Blob())
  } else {
    isRecording.value = true
    emit('recordingStart')
    // TODO: Start recording
  }
}

// Handle send
const handleSend = () => {
  if (!canSend.value) return

  if (props.editMessage) {
    emit('edit', message.value.trim())
  } else {
    emit('send', message.value.trim(), attachments.value.length > 0 ? attachments.value : undefined)
  }

  message.value = ''
  attachments.value = []
  if (textarea.value) {
    textarea.value.style.height = 'auto'
  }
}

// Handle cancel edit
const handleCancelEdit = () => {
  message.value = ''
  emit('cancelEdit')
}

// Emoji Picker Component (placeholder)
const EmojiPicker = defineComponent({
  name: 'EmojiPicker',
  emits: ['select'],
  setup(_, { emit: pickerEmit }) {
    const categories = [
      { name: 'Smileys', emojis: ['😀', '😃', '😄', '😁', '😆', '😅', '🤣', '😂', '🙂', '😊', '😇', '🥰', '😍', '🤩', '😘', '😗'] },
      { name: 'Gestures', emojis: ['👍', '👎', '👌', '✌️', '🤞', '🤟', '🤘', '🤙', '👋', '🤚', '🖐️', '✋', '👊', '✊', '🤛', '🤜'] },
      { name: 'Objects', emojis: ['💯', '🔥', '✨', '🎉', '🎊', '🏆', '🎯', '💡', '📌', '📎', '✏️', '📝', '💻', '🖥️', '⌨️', '🖱️'] },
    ]

    return () => h('div', { class: 'w-72 max-h-64 overflow-y-auto p-2' },
      categories.map(category => h('div', { key: category.name, class: 'mb-3' }, [
        h('p', { class: 'text-xs font-medium text-gray-500 uppercase tracking-wider mb-2 px-1' }, category.name),
        h('div', { class: 'grid grid-cols-8 gap-1' },
          category.emojis.map(emoji =>
            h('button', {
              key: emoji,
              type: 'button',
              class: 'w-8 h-8 flex items-center justify-center text-lg hover:bg-gray-100 rounded transition-colors duration-150',
              onClick: () => pickerEmit('select', emoji),
            }, emoji),
          ),
        ),
      ])),
    )
  },
})
</script>

<style scoped>
/* Scrollbar styling */
.scrollbar-none::-webkit-scrollbar {
  display: none;
}

.scrollbar-none {
  -ms-overflow-style: none;
  scrollbar-width: none;
}

/* Slide up animation */
.slide-up-enter-active,
.slide-up-leave-active {
  transition: opacity 0.15s ease-out;
}

.slide-up-enter-from,
.slide-up-leave-to {
  opacity: 0;
}

/* Slide down animation */
.slide-down-enter-active,
.slide-down-leave-active {
  transition: opacity 0.15s ease-out;
}

.slide-down-enter-from,
.slide-down-leave-to {
  opacity: 0;
}

/* Fade animation */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

/* Fade scale animation */
.fade-scale-enter-active,
.fade-scale-leave-active {
  transition: opacity 0.15s ease-out;
}

.fade-scale-enter-from,
.fade-scale-leave-to {
  opacity: 0;
}

/* Attachment animation */
.attachment-enter-active,
.attachment-leave-active {
  transition: opacity 0.15s ease-out;
}

.attachment-enter-from,
.attachment-leave-to {
  opacity: 0;
}

.attachment-move {
  transition: transform 0.15s ease-out;
}
</style>
