<template>
  <aside :class="containerClasses">
    <!-- Header -->
    <div :class="headerClasses">
      <div class="flex items-center gap-2">
        <div :class="channelIconContainerClasses">
          <Icon :name="channelIcon" :class="channelIconClasses" />
        </div>
        <div class="flex-1 min-w-0">
          <h3 :class="titleClasses">{{ channel.name }}</h3>
          <p v-if="channel.private" class="text-xs text-olympus-text-muted flex items-center gap-1">
            <Icon name="ph:lock-simple" class="w-3 h-3" />
            Private channel
          </p>
        </div>
      </div>

      <!-- Header Actions -->
      <div class="flex items-center gap-1">
        <TooltipProvider :delay-duration="300">
          <TooltipRoot>
            <TooltipTrigger as-child>
              <button
                type="button"
                :class="headerActionButtonClasses"
                @click="handleEditChannel"
              >
                <Icon name="ph:pencil-simple" class="w-4 h-4" />
              </button>
            </TooltipTrigger>
            <TooltipPortal>
              <TooltipContent :class="tooltipClasses" side="bottom" :side-offset="5">
                Edit channel
                <TooltipArrow class="fill-olympus-elevated" />
              </TooltipContent>
            </TooltipPortal>
          </TooltipRoot>
        </TooltipProvider>

        <TooltipProvider :delay-duration="300">
          <TooltipRoot>
            <TooltipTrigger as-child>
              <button
                type="button"
                :class="headerActionButtonClasses"
                @click="emit('close')"
              >
                <Icon name="ph:x" class="w-4 h-4" />
              </button>
            </TooltipTrigger>
            <TooltipPortal>
              <TooltipContent :class="tooltipClasses" side="bottom" :side-offset="5">
                Close panel
                <TooltipArrow class="fill-olympus-elevated" />
              </TooltipContent>
            </TooltipPortal>
          </TooltipRoot>
        </TooltipProvider>
      </div>
    </div>

    <!-- Content -->
    <div class="flex-1 overflow-y-auto scrollbar-thin scrollbar-thumb-olympus-border scrollbar-track-transparent">
      <!-- Presence Row -->
      <Transition name="fade">
        <div v-if="viewers.length > 0" :class="sectionClasses">
          <SharedPresenceRow
            :users="viewers"
            :max="5"
            :show-label="true"
            label="viewing"
            :tooltip-title="'Currently viewing'"
            :interactive="true"
            @click="handleViewersClick"
          />
        </div>
      </Transition>

      <!-- About Section -->
      <CollapsibleSection
        title="About"
        :default-open="true"
        :class="sectionClasses"
      >
        <template #content>
          <div v-if="loading" class="space-y-2">
            <SharedSkeleton class="h-4 w-full" />
            <SharedSkeleton class="h-4 w-3/4" />
          </div>
          <template v-else>
            <p v-if="channel.description" :class="descriptionClasses">
              {{ channel.description }}
            </p>
            <button
              v-else
              type="button"
              class="text-sm text-olympus-primary hover:text-olympus-primary-hover transition-colors"
              @click="handleEditChannel"
            >
              Add a description
            </button>

            <!-- Channel Meta -->
            <div class="mt-3 pt-3 border-t border-olympus-border space-y-2">
              <div class="flex items-center gap-2 text-xs text-olympus-text-muted">
                <Icon name="ph:calendar" class="w-3.5 h-3.5" />
                <span>Created {{ formatDate(channel.createdAt) }}</span>
              </div>
              <div v-if="channel.createdBy" class="flex items-center gap-2 text-xs text-olympus-text-muted">
                <Icon name="ph:user" class="w-3.5 h-3.5" />
                <span>Created by {{ channel.createdBy.name }}</span>
              </div>
            </div>
          </template>
        </template>
      </CollapsibleSection>

      <!-- Pinned Messages Section -->
      <CollapsibleSection
        v-if="pinnedMessages.length > 0 || showAllSections"
        title="Pinned Messages"
        :count="pinnedMessages.length"
        :default-open="false"
        :class="sectionClasses"
      >
        <template #content>
          <div v-if="loading" class="space-y-2">
            <SharedSkeleton v-for="i in 2" :key="i" class="h-12 w-full" />
          </div>
          <div v-else-if="pinnedMessages.length === 0" class="text-sm text-olympus-text-muted text-center py-4">
            No pinned messages
          </div>
          <div v-else class="space-y-2">
            <button
              v-for="message in pinnedMessages.slice(0, maxPinnedMessages)"
              :key="message.id"
              type="button"
              :class="pinnedMessageClasses"
              @click="handlePinnedMessageClick(message)"
            >
              <div class="flex items-center gap-2 mb-1">
                <SharedAgentAvatar :user="message.author" size="xs" />
                <span class="text-xs font-medium text-olympus-text truncate">
                  {{ message.author.name }}
                </span>
                <span class="text-xs text-olympus-text-subtle ml-auto">
                  {{ formatRelativeTime(message.timestamp) }}
                </span>
              </div>
              <p class="text-xs text-olympus-text-muted line-clamp-2">
                {{ message.content }}
              </p>
            </button>

            <button
              v-if="pinnedMessages.length > maxPinnedMessages"
              type="button"
              class="w-full text-xs text-olympus-primary hover:text-olympus-primary-hover transition-colors py-2"
              @click="handleViewAllPinned"
            >
              View all {{ pinnedMessages.length }} pinned messages
            </button>
          </div>
        </template>
      </CollapsibleSection>

      <!-- Shared Files Section -->
      <CollapsibleSection
        v-if="sharedFiles.length > 0 || showAllSections"
        title="Shared Files"
        :count="sharedFiles.length"
        :default-open="false"
        :class="sectionClasses"
      >
        <template #content>
          <div v-if="loading" class="space-y-2">
            <SharedSkeleton v-for="i in 3" :key="i" class="h-10 w-full" />
          </div>
          <div v-else-if="sharedFiles.length === 0" class="text-sm text-olympus-text-muted text-center py-4">
            No shared files
          </div>
          <div v-else class="space-y-1">
            <button
              v-for="file in sharedFiles.slice(0, maxSharedFiles)"
              :key="file.id"
              type="button"
              :class="sharedFileClasses"
              @click="handleFileClick(file)"
            >
              <div :class="fileIconContainerClasses(file)">
                <Icon :name="getFileIcon(file.type)" class="w-4 h-4" />
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-sm text-olympus-text truncate">{{ file.name }}</p>
                <p class="text-xs text-olympus-text-subtle">
                  {{ formatFileSize(file.size) }} · {{ formatRelativeTime(file.uploadedAt) }}
                </p>
              </div>
              <button
                type="button"
                class="p-1 rounded text-olympus-text-muted hover:text-olympus-text hover:bg-olympus-surface transition-colors opacity-0 group-hover:opacity-100"
                @click.stop="handleFileDownload(file)"
              >
                <Icon name="ph:download-simple" class="w-4 h-4" />
              </button>
            </button>

            <button
              v-if="sharedFiles.length > maxSharedFiles"
              type="button"
              class="w-full text-xs text-olympus-primary hover:text-olympus-primary-hover transition-colors py-2"
              @click="handleViewAllFiles"
            >
              View all {{ sharedFiles.length }} files
            </button>
          </div>
        </template>
      </CollapsibleSection>

      <!-- Members Section -->
      <CollapsibleSection
        title="Members"
        :count="filteredMembers.length"
        :default-open="true"
        :class="sectionClasses"
      >
        <template #header-action>
          <TooltipProvider :delay-duration="300">
            <TooltipRoot>
              <TooltipTrigger as-child>
                <button
                  type="button"
                  :class="addMemberButtonClasses"
                  @click="handleAddMember"
                >
                  <Icon name="ph:user-plus" class="w-3.5 h-3.5" />
                </button>
              </TooltipTrigger>
              <TooltipPortal>
                <TooltipContent :class="tooltipClasses" side="left" :side-offset="5">
                  Add members
                  <TooltipArrow class="fill-olympus-elevated" />
                </TooltipContent>
              </TooltipPortal>
            </TooltipRoot>
          </TooltipProvider>
        </template>

        <template #content>
          <!-- Member Search -->
          <div v-if="channel.members && channel.members.length > 5" class="mb-3">
            <SharedSearchInput
              v-model="memberSearch"
              placeholder="Search members..."
              size="sm"
              :clearable="true"
            />
          </div>

          <!-- Member Filters -->
          <div v-if="showMemberFilters" class="flex items-center gap-1 mb-3 overflow-x-auto scrollbar-none">
            <button
              v-for="filter in memberFilters"
              :key="filter.value"
              type="button"
              :class="memberFilterClasses(filter.value)"
              @click="activeMemberFilter = filter.value"
            >
              <Icon v-if="filter.icon" :name="filter.icon" class="w-3 h-3" />
              {{ filter.label }}
              <span
                v-if="getMemberCountByFilter(filter.value) > 0"
                class="ml-1 text-[10px] opacity-70"
              >
                {{ getMemberCountByFilter(filter.value) }}
              </span>
            </button>
          </div>

          <!-- Members List -->
          <div v-if="loading" class="space-y-2">
            <SharedSkeleton v-for="i in 4" :key="i" class="h-12 w-full" />
          </div>
          <div v-else-if="filteredMembers.length === 0" class="text-sm text-olympus-text-muted text-center py-4">
            <template v-if="memberSearch">
              No members matching "{{ memberSearch }}"
            </template>
            <template v-else>
              No members
            </template>
          </div>
          <TransitionGroup
            v-else
            name="member-list"
            tag="div"
            class="space-y-1"
          >
            <MemberItem
              v-for="member in displayedMembers"
              :key="member.id"
              :member="member"
              :is-owner="channel.createdBy?.id === member.id"
              :size="size"
              @click="handleMemberClick(member)"
              @message="handleMemberMessage(member)"
              @remove="handleMemberRemove(member)"
            />
          </TransitionGroup>

          <!-- Show More Members -->
          <button
            v-if="filteredMembers.length > maxVisibleMembers && !showAllMembers"
            type="button"
            class="w-full text-xs text-olympus-primary hover:text-olympus-primary-hover transition-colors py-2 mt-2"
            @click="showAllMembers = true"
          >
            Show {{ filteredMembers.length - maxVisibleMembers }} more members
          </button>
        </template>
      </CollapsibleSection>

      <!-- Notifications Section -->
      <CollapsibleSection
        title="Notifications"
        :default-open="false"
        :class="sectionClasses"
      >
        <template #content>
          <div class="space-y-3">
            <label class="flex items-center justify-between cursor-pointer">
              <span class="text-sm text-olympus-text">All messages</span>
              <input
                v-model="notificationSetting"
                type="radio"
                value="all"
                class="w-4 h-4 text-olympus-primary bg-olympus-surface border-olympus-border focus:ring-olympus-primary/50"
              >
            </label>
            <label class="flex items-center justify-between cursor-pointer">
              <span class="text-sm text-olympus-text">Mentions only</span>
              <input
                v-model="notificationSetting"
                type="radio"
                value="mentions"
                class="w-4 h-4 text-olympus-primary bg-olympus-surface border-olympus-border focus:ring-olympus-primary/50"
              >
            </label>
            <label class="flex items-center justify-between cursor-pointer">
              <span class="text-sm text-olympus-text">Nothing</span>
              <input
                v-model="notificationSetting"
                type="radio"
                value="none"
                class="w-4 h-4 text-olympus-primary bg-olympus-surface border-olympus-border focus:ring-olympus-primary/50"
              >
            </label>
          </div>
        </template>
      </CollapsibleSection>
    </div>

    <!-- Footer Actions -->
    <div :class="footerClasses">
      <button
        type="button"
        :class="footerActionClasses('danger')"
        @click="handleLeaveChannel"
      >
        <Icon name="ph:sign-out" class="w-4 h-4" />
        Leave channel
      </button>
    </div>
  </aside>
</template>

<script setup lang="ts">
import { h } from 'vue'
import {
  TooltipArrow,
  TooltipContent,
  TooltipPortal,
  TooltipProvider,
  TooltipRoot,
  TooltipTrigger,
  CollapsibleRoot,
  CollapsibleTrigger,
  CollapsibleContent,
  DropdownMenuRoot,
  DropdownMenuTrigger,
  DropdownMenuPortal,
  DropdownMenuContent,
  DropdownMenuItem,
} from 'reka-ui'
import type { Channel, User, Message } from '~/types'

type ChannelInfoSize = 'sm' | 'md' | 'lg'
type ChannelInfoVariant = 'default' | 'compact' | 'floating'
type MemberFilter = 'all' | 'humans' | 'agents' | 'online'
type NotificationSetting = 'all' | 'mentions' | 'none'

interface SharedFile {
  id: string
  name: string
  type: string
  size: number
  uploadedAt: Date
  uploadedBy: User
}

interface PinnedMessage {
  id: string
  content: string
  author: User
  timestamp: Date
}

const props = withDefaults(defineProps<{
  // Core
  channel: Channel
  viewers: User[]

  // Appearance
  size?: ChannelInfoSize
  variant?: ChannelInfoVariant
  width?: string

  // Data
  pinnedMessages?: PinnedMessage[]
  sharedFiles?: SharedFile[]

  // Display options
  showAllSections?: boolean
  showMemberFilters?: boolean

  // Limits
  maxPinnedMessages?: number
  maxSharedFiles?: number
  maxVisibleMembers?: number

  // State
  loading?: boolean
}>(), {
  size: 'md',
  variant: 'default',
  width: 'w-72',
  pinnedMessages: () => [],
  sharedFiles: () => [],
  showAllSections: false,
  showMemberFilters: true,
  maxPinnedMessages: 3,
  maxSharedFiles: 5,
  maxVisibleMembers: 10,
  loading: false,
})

const emit = defineEmits<{
  close: []
  editChannel: [channel: Channel]
  addMember: [channel: Channel]
  memberClick: [member: User]
  memberMessage: [member: User]
  memberRemove: [member: User]
  viewAllPinned: []
  viewAllFiles: []
  viewAllViewers: [viewers: User[]]
  pinnedMessageClick: [message: PinnedMessage]
  fileClick: [file: SharedFile]
  fileDownload: [file: SharedFile]
  leaveChannel: [channel: Channel]
  notificationChange: [setting: NotificationSetting]
}>()

// State
const memberSearch = ref('')
const activeMemberFilter = ref<MemberFilter>('all')
const showAllMembers = ref(false)
const notificationSetting = ref<NotificationSetting>('all')

// Watch notification changes
watch(notificationSetting, (value) => {
  emit('notificationChange', value)
})

// Size configurations
const sizeConfig: Record<ChannelInfoSize, {
  container: string
  header: string
  title: string
  section: string
  padding: string
}> = {
  sm: {
    container: 'w-64',
    header: 'p-3',
    title: 'text-sm',
    section: 'p-3',
    padding: 'p-3',
  },
  md: {
    container: 'w-72',
    header: 'p-4',
    title: 'text-base',
    section: 'p-4',
    padding: 'p-4',
  },
  lg: {
    container: 'w-80',
    header: 'p-5',
    title: 'text-lg',
    section: 'p-5',
    padding: 'p-5',
  },
}

// Channel icon
const channelIcon = computed(() => {
  if (props.channel.type === 'agent') return 'ph:robot'
  if (props.channel.type === 'dm') return 'ph:chat-circle'
  if (props.channel.private) return 'ph:lock-simple'
  return 'ph:hash'
})

// Member filters
const memberFilters: { value: MemberFilter; label: string; icon?: string }[] = [
  { value: 'all', label: 'All' },
  { value: 'humans', label: 'Humans', icon: 'ph:user' },
  { value: 'agents', label: 'Agents', icon: 'ph:robot' },
  { value: 'online', label: 'Online', icon: 'ph:circle-fill' },
]

// Filtered members
const filteredMembers = computed(() => {
  let members = props.channel.members || []

  // Apply search filter
  if (memberSearch.value) {
    const search = memberSearch.value.toLowerCase()
    members = members.filter(m => m.name.toLowerCase().includes(search))
  }

  // Apply type filter
  switch (activeMemberFilter.value) {
    case 'humans':
      members = members.filter(m => m.type === 'human')
      break
    case 'agents':
      members = members.filter(m => m.type === 'agent')
      break
    case 'online':
      members = members.filter(m => m.status === 'online' || m.status === 'working')
      break
  }

  return members
})

// Displayed members (limited)
const displayedMembers = computed(() => {
  if (showAllMembers.value) return filteredMembers.value
  return filteredMembers.value.slice(0, props.maxVisibleMembers)
})

// Get member count by filter
const getMemberCountByFilter = (filter: MemberFilter): number => {
  const members = props.channel.members || []
  switch (filter) {
    case 'humans':
      return members.filter(m => m.type === 'human').length
    case 'agents':
      return members.filter(m => m.type === 'agent').length
    case 'online':
      return members.filter(m => m.status === 'online' || m.status === 'working').length
    default:
      return members.length
  }
}

// Container classes
const containerClasses = computed(() => {
  const classes = [
    'bg-olympus-sidebar border-l border-olympus-border flex flex-col shrink-0',
    props.width || sizeConfig[props.size].container,
  ]

  if (props.variant === 'floating') {
    classes.push('absolute right-0 top-0 h-full shadow-xl z-10')
  }

  if (props.variant === 'compact') {
    classes.push('border-l-0 bg-transparent')
  }

  return classes
})

// Header classes
const headerClasses = computed(() => [
  'flex items-center justify-between border-b border-olympus-border',
  sizeConfig[props.size].header,
])

// Channel icon container classes
const channelIconContainerClasses = computed(() => [
  'flex items-center justify-center w-10 h-10 rounded-xl',
  'bg-olympus-surface',
])

// Channel icon classes
const channelIconClasses = computed(() => [
  'w-5 h-5 text-olympus-text-muted',
])

// Title classes
const titleClasses = computed(() => [
  'font-semibold text-olympus-text truncate',
  sizeConfig[props.size].title,
])

// Header action button classes
const headerActionButtonClasses = computed(() => [
  'p-2 rounded-lg transition-colors duration-150',
  'text-olympus-text-muted hover:text-olympus-text',
  'hover:bg-olympus-surface',
  'focus:outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50',
])

// Section classes
const sectionClasses = computed(() => [
  'border-b border-olympus-border',
  sizeConfig[props.size].section,
])

// Description classes
const descriptionClasses = computed(() => [
  'text-sm text-olympus-text-muted leading-relaxed',
])

// Tooltip classes
const tooltipClasses = computed(() => [
  'z-50 bg-olympus-elevated border border-olympus-border rounded-lg',
  'px-2 py-1 text-xs shadow-lg',
  'animate-in fade-in-0 zoom-in-95 duration-150',
])

// Add member button classes
const addMemberButtonClasses = computed(() => [
  'p-1.5 rounded-md transition-colors duration-150',
  'text-olympus-text-muted hover:text-olympus-primary',
  'hover:bg-olympus-primary/10',
  'focus:outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50',
])

// Member filter classes
const memberFilterClasses = (filter: MemberFilter) => [
  'flex items-center gap-1 px-2 py-1 rounded-md text-xs font-medium',
  'transition-colors duration-150 whitespace-nowrap',
  activeMemberFilter.value === filter
    ? 'bg-olympus-primary/20 text-olympus-primary'
    : 'text-olympus-text-muted hover:text-olympus-text hover:bg-olympus-surface',
]

// Pinned message classes
const pinnedMessageClasses = computed(() => [
  'w-full text-left p-2 rounded-lg transition-colors duration-150',
  'hover:bg-olympus-surface',
  'focus:outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50',
])

// Shared file classes
const sharedFileClasses = computed(() => [
  'w-full flex items-center gap-3 p-2 rounded-lg group',
  'transition-colors duration-150',
  'hover:bg-olympus-surface',
  'focus:outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50',
])

// File icon container classes
const fileIconContainerClasses = (file: SharedFile) => {
  const colorMap: Record<string, string> = {
    image: 'bg-green-500/10 text-green-400',
    video: 'bg-purple-500/10 text-purple-400',
    audio: 'bg-amber-500/10 text-amber-400',
    document: 'bg-blue-500/10 text-blue-400',
    code: 'bg-pink-500/10 text-pink-400',
    archive: 'bg-orange-500/10 text-orange-400',
    default: 'bg-olympus-surface text-olympus-text-muted',
  }

  const category = getFileCategory(file.type)
  return [
    'flex items-center justify-center w-8 h-8 rounded-lg shrink-0',
    colorMap[category] || colorMap.default,
  ]
}

// Footer classes
const footerClasses = computed(() => [
  'border-t border-olympus-border',
  sizeConfig[props.size].padding,
])

// Footer action classes
const footerActionClasses = (variant: 'default' | 'danger') => [
  'w-full flex items-center justify-center gap-2 py-2 px-3 rounded-lg',
  'text-sm font-medium transition-colors duration-150',
  'focus:outline-none focus-visible:ring-2',
  variant === 'danger'
    ? 'text-red-400 hover:bg-red-500/10 focus-visible:ring-red-500/50'
    : 'text-olympus-text-muted hover:bg-olympus-surface focus-visible:ring-olympus-primary/50',
]

// Get file icon
const getFileIcon = (type: string): string => {
  const category = getFileCategory(type)
  const icons: Record<string, string> = {
    image: 'ph:image',
    video: 'ph:video',
    audio: 'ph:music-note',
    document: 'ph:file-text',
    code: 'ph:code',
    archive: 'ph:file-zip',
    default: 'ph:file',
  }
  return icons[category] || icons.default
}

// Get file category
const getFileCategory = (type: string): string => {
  if (type.startsWith('image/')) return 'image'
  if (type.startsWith('video/')) return 'video'
  if (type.startsWith('audio/')) return 'audio'
  if (type.includes('pdf') || type.includes('document') || type.includes('text/')) return 'document'
  if (type.includes('javascript') || type.includes('json') || type.includes('typescript')) return 'code'
  if (type.includes('zip') || type.includes('tar') || type.includes('rar')) return 'archive'
  return 'default'
}

// Format file size
const formatFileSize = (bytes: number): string => {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  if (bytes < 1024 * 1024 * 1024) return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
  return `${(bytes / (1024 * 1024 * 1024)).toFixed(1)} GB`
}

// Format date
const formatDate = (date: Date): string => {
  return new Date(date).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  })
}

// Format relative time
const formatRelativeTime = (date: Date): string => {
  const now = new Date()
  const diff = now.getTime() - new Date(date).getTime()
  const minutes = Math.floor(diff / 60000)
  const hours = Math.floor(minutes / 60)
  const days = Math.floor(hours / 24)

  if (minutes < 1) return 'now'
  if (minutes < 60) return `${minutes}m ago`
  if (hours < 24) return `${hours}h ago`
  if (days < 7) return `${days}d ago`
  return formatDate(date)
}

// Handlers
const handleEditChannel = () => {
  emit('editChannel', props.channel)
}

const handleAddMember = () => {
  emit('addMember', props.channel)
}

const handleMemberClick = (member: User) => {
  emit('memberClick', member)
}

const handleMemberMessage = (member: User) => {
  emit('memberMessage', member)
}

const handleMemberRemove = (member: User) => {
  emit('memberRemove', member)
}

const handleViewAllPinned = () => {
  emit('viewAllPinned')
}

const handleViewAllFiles = () => {
  emit('viewAllFiles')
}

const handleViewersClick = () => {
  emit('viewAllViewers', props.viewers)
}

const handlePinnedMessageClick = (message: PinnedMessage) => {
  emit('pinnedMessageClick', message)
}

const handleFileClick = (file: SharedFile) => {
  emit('fileClick', file)
}

const handleFileDownload = (file: SharedFile) => {
  emit('fileDownload', file)
}

const handleLeaveChannel = () => {
  emit('leaveChannel', props.channel)
}

// Collapsible Section Component
const CollapsibleSection = defineComponent({
  name: 'CollapsibleSection',
  props: {
    title: { type: String, required: true },
    count: { type: Number, default: undefined },
    defaultOpen: { type: Boolean, default: true },
  },
  setup(sectionProps, { slots }) {
    const isOpen = ref(sectionProps.defaultOpen)

    return () => h(CollapsibleRoot, {
      'modelValue': isOpen.value,
      'onUpdate:modelValue': (val: boolean) => { isOpen.value = val },
    }, () => [
      h(CollapsibleTrigger, {
        class: [
          'w-full flex items-center justify-between py-2 group',
          'focus:outline-none',
        ],
      }, () => [
        h('div', { class: 'flex items-center gap-2' }, [
          h('h4', {
            class: 'text-xs font-semibold text-olympus-text-muted uppercase tracking-wider',
          }, sectionProps.title),
          sectionProps.count !== undefined && h('span', {
            class: 'text-xs text-olympus-text-subtle',
          }, `(${sectionProps.count})`),
        ]),
        h('div', { class: 'flex items-center gap-1' }, [
          slots['header-action']?.(),
          h(resolveComponent('Icon'), {
            name: 'ph:caret-down',
            class: [
              'w-4 h-4 text-olympus-text-muted transition-transform duration-200',
              isOpen.value ? 'rotate-180' : '',
            ],
          }),
        ]),
      ]),
      h(CollapsibleContent, {
        class: 'overflow-hidden data-[state=open]:animate-collapsible-down data-[state=closed]:animate-collapsible-up',
      }, () => [
        h('div', { class: 'pt-2' }, slots.content?.()),
      ]),
    ])
  },
})

// Member Item Component
const MemberItem = defineComponent({
  name: 'MemberItem',
  props: {
    member: { type: Object as () => User, required: true },
    isOwner: { type: Boolean, default: false },
    size: { type: String as () => ChannelInfoSize, default: 'md' },
  },
  emits: ['click', 'message', 'remove'],
  setup(memberProps, { emit: memberEmit }) {
    const isHovered = ref(false)
    const menuOpen = ref(false)

    return () => h('div', {
      class: [
        'flex items-center gap-3 p-2 rounded-xl transition-colors cursor-pointer group',
        'hover:bg-olympus-surface',
      ],
      onMouseenter: () => { isHovered.value = true },
      onMouseleave: () => { isHovered.value = false },
      onClick: () => memberEmit('click'),
    }, [
      h(resolveComponent('SharedAgentAvatar'), {
        user: memberProps.member,
        size: memberProps.size === 'sm' ? 'xs' : 'sm',
      }),
      h('div', { class: 'flex-1 min-w-0' }, [
        h('div', { class: 'flex items-center gap-1.5' }, [
          h('p', {
            class: 'text-sm font-medium truncate text-olympus-text',
          }, memberProps.member.name),
          memberProps.isOwner && h(resolveComponent('SharedBadge'), {
            size: 'xs',
            variant: 'secondary',
          }, () => 'Owner'),
        ]),
        memberProps.member.type === 'agent'
          ? h('p', {
              class: 'text-xs text-olympus-text-muted truncate',
            }, memberProps.member.status === 'working' ? memberProps.member.currentTask : 'Ready')
          : h('p', {
              class: 'text-xs text-olympus-text-muted capitalize',
            }, memberProps.member.type),
      ]),
      // Status badge for agents
      memberProps.member.type === 'agent' && memberProps.member.status && h(resolveComponent('SharedStatusBadge'), {
        status: memberProps.member.status,
        size: 'xs',
        showLabel: false,
      }),
      // Actions dropdown
      h(DropdownMenuRoot, {
        'modelValue': menuOpen.value,
        'onUpdate:modelValue': (val: boolean) => { menuOpen.value = val },
      }, () => [
        h(DropdownMenuTrigger, { asChild: true }, () =>
          h('button', {
            type: 'button',
            class: [
              'p-1.5 rounded-md transition-all duration-150',
              'text-olympus-text-muted hover:text-olympus-text',
              'hover:bg-olympus-elevated',
              'opacity-0 group-hover:opacity-100',
              'focus:outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50',
            ],
            onClick: (e: Event) => e.stopPropagation(),
          }, [
            h(resolveComponent('Icon'), { name: 'ph:dots-three', class: 'w-4 h-4' }),
          ]),
        ),
        h(DropdownMenuPortal, () =>
          h(DropdownMenuContent, {
            class: [
              'min-w-40 bg-olympus-elevated border border-olympus-border rounded-xl',
              'shadow-xl p-1 z-50',
              'animate-in fade-in-0 zoom-in-95 duration-150',
            ],
            sideOffset: 5,
          }, () => [
            h(DropdownMenuItem, {
              class: [
                'flex items-center gap-2 px-3 py-2 text-sm rounded-lg cursor-pointer outline-none',
                'text-olympus-text-muted hover:bg-olympus-surface hover:text-olympus-text',
              ],
              onSelect: () => memberEmit('message'),
            }, () => [
              h(resolveComponent('Icon'), { name: 'ph:chat-circle', class: 'w-4 h-4' }),
              h('span', 'Message'),
            ]),
            h(DropdownMenuItem, {
              class: [
                'flex items-center gap-2 px-3 py-2 text-sm rounded-lg cursor-pointer outline-none',
                'text-olympus-text-muted hover:bg-olympus-surface hover:text-olympus-text',
              ],
              onSelect: () => memberEmit('click'),
            }, () => [
              h(resolveComponent('Icon'), { name: 'ph:user', class: 'w-4 h-4' }),
              h('span', 'View profile'),
            ]),
            h(DropdownMenuItem, {
              class: [
                'flex items-center gap-2 px-3 py-2 text-sm rounded-lg cursor-pointer outline-none',
                'text-red-400 hover:bg-red-500/10',
              ],
              onSelect: () => memberEmit('remove'),
            }, () => [
              h(resolveComponent('Icon'), { name: 'ph:user-minus', class: 'w-4 h-4' }),
              h('span', 'Remove'),
            ]),
          ]),
        ),
      ]),
    ])
  },
})
</script>

<style scoped>
/* Scrollbar styling */
.scrollbar-thin::-webkit-scrollbar {
  width: 6px;
}

.scrollbar-thin::-webkit-scrollbar-track {
  background: transparent;
}

.scrollbar-thin::-webkit-scrollbar-thumb {
  background-color: var(--olympus-border);
  border-radius: 3px;
}

.scrollbar-thin::-webkit-scrollbar-thumb:hover {
  background-color: var(--olympus-text-muted);
}

.scrollbar-none::-webkit-scrollbar {
  display: none;
}

.scrollbar-none {
  -ms-overflow-style: none;
  scrollbar-width: none;
}

/* Member list animation */
.member-list-enter-active,
.member-list-leave-active {
  transition: all 0.2s ease;
}

.member-list-enter-from {
  opacity: 0;
  transform: translateX(-10px);
}

.member-list-leave-to {
  opacity: 0;
  transform: translateX(10px);
}

.member-list-move {
  transition: transform 0.2s ease;
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

/* Line clamp */
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* Collapsible animation */
@keyframes collapsible-down {
  from {
    height: 0;
    opacity: 0;
  }
  to {
    height: var(--reka-collapsible-content-height);
    opacity: 1;
  }
}

@keyframes collapsible-up {
  from {
    height: var(--reka-collapsible-content-height);
    opacity: 1;
  }
  to {
    height: 0;
    opacity: 0;
  }
}

.animate-collapsible-down {
  animation: collapsible-down 0.2s ease-out;
}

.animate-collapsible-up {
  animation: collapsible-up 0.2s ease-out;
}
</style>
