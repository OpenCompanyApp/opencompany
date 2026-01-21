<template>
  <component
    :is="as"
    :class="containerClasses"
    @mouseenter="hovered = true"
    @mouseleave="hovered = false"
    @click="handleClick"
    @contextmenu.prevent="handleContextMenu"
  >
    <!-- Drag Handle (when reorderable) -->
    <div
      v-if="reorderable && hovered"
      :class="dragHandleClasses"
      @mousedown.stop="handleDragStart"
    >
      <Icon name="ph:dots-six-vertical" class="w-4 h-4" />
    </div>

    <!-- Selection Checkbox -->
    <Transition name="fade">
      <div v-if="selectable && (showCheckbox || hovered || checked)" class="shrink-0">
        <label :class="checkboxLabelClasses">
          <input
            type="checkbox"
            :checked="checked"
            :class="checkboxClasses"
            @click.stop
            @change="emit('check', !checked)"
          />
        </label>
      </div>
    </Transition>

    <!-- Document Icon -->
    <div :class="iconContainerClasses">
      <Icon :name="documentIcon" :class="iconClasses" />

      <!-- New Badge -->
      <span v-if="isNew" :class="newBadgeClasses">
        <span class="w-1.5 h-1.5 rounded-full bg-current" />
      </span>

      <!-- Type Indicator -->
      <span v-if="showTypeIndicator && document.type" :class="typeIndicatorClasses">
        <Icon :name="getTypeIcon(document.type)" class="w-2.5 h-2.5" />
      </span>
    </div>

    <!-- Content -->
    <div class="flex-1 min-w-0">
      <!-- Title Row -->
      <div class="flex items-center gap-2">
        <p :class="titleClasses">{{ document.title }}</p>

        <!-- Badges -->
        <div v-if="showBadges" class="flex items-center gap-1 shrink-0">
          <!-- Shared Badge -->
          <TooltipProvider v-if="document.isShared" :delay-duration="300">
            <TooltipRoot>
              <TooltipTrigger as-child>
                <span :class="sharedBadgeClasses">
                  <Icon name="ph:share-network" class="w-3 h-3" />
                </span>
              </TooltipTrigger>
              <TooltipPortal>
                <TooltipContent :class="tooltipClasses" side="top">
                  Shared with team
                  <TooltipArrow class="fill-white" />
                </TooltipContent>
              </TooltipPortal>
            </TooltipRoot>
          </TooltipProvider>

          <!-- Starred Badge -->
          <TooltipProvider v-if="document.isStarred" :delay-duration="300">
            <TooltipRoot>
              <TooltipTrigger as-child>
                <span :class="starredBadgeClasses">
                  <Icon name="ph:star-fill" class="w-3 h-3" />
                </span>
              </TooltipTrigger>
              <TooltipPortal>
                <TooltipContent :class="tooltipClasses" side="top">
                  Starred
                  <TooltipArrow class="fill-white" />
                </TooltipContent>
              </TooltipPortal>
            </TooltipRoot>
          </TooltipProvider>

          <!-- Pinned Badge -->
          <TooltipProvider v-if="document.isPinned" :delay-duration="300">
            <TooltipRoot>
              <TooltipTrigger as-child>
                <span :class="pinnedBadgeClasses">
                  <Icon name="ph:push-pin-fill" class="w-3 h-3" />
                </span>
              </TooltipTrigger>
              <TooltipPortal>
                <TooltipContent :class="tooltipClasses" side="top">
                  Pinned to top
                  <TooltipArrow class="fill-white" />
                </TooltipContent>
              </TooltipPortal>
            </TooltipRoot>
          </TooltipProvider>

          <!-- Locked Badge -->
          <TooltipProvider v-if="document.isLocked" :delay-duration="300">
            <TooltipRoot>
              <TooltipTrigger as-child>
                <span :class="lockedBadgeClasses">
                  <Icon name="ph:lock-fill" class="w-3 h-3" />
                </span>
              </TooltipTrigger>
              <TooltipPortal>
                <TooltipContent :class="tooltipClasses" side="top">
                  Document locked
                  <TooltipArrow class="fill-white" />
                </TooltipContent>
              </TooltipPortal>
            </TooltipRoot>
          </TooltipProvider>
        </div>
      </div>

      <!-- Description (if provided) -->
      <p v-if="showDescription && document.description" :class="descriptionClasses">
        {{ document.description }}
      </p>

      <!-- Meta Row -->
      <div :class="metaRowClasses">
        <!-- Author -->
        <div v-if="showAuthor && document.author" class="flex items-center gap-1.5">
          <SharedAgentAvatar :user="document.author" size="xs" :show-status="false" />
          <span :class="metaTextClasses">{{ document.author.name }}</span>
        </div>

        <!-- Separator -->
        <span v-if="showAuthor && document.author && showDate" :class="separatorClasses">/</span>

        <!-- Date -->
        <span v-if="showDate" :class="metaTextClasses">
          <Icon v-if="variant === 'detailed'" name="ph:clock" class="w-3 h-3 mr-1" />
          {{ formatDate(document.updatedAt) }}
        </span>

        <!-- Word Count -->
        <span v-if="showStats && document.wordCount" :class="metaTextClasses">
          <Icon name="ph:text-aa" class="w-3 h-3 mr-1" />
          {{ formatNumber(document.wordCount) }} words
        </span>

        <!-- Read Time -->
        <span v-if="showStats && document.readTime" :class="metaTextClasses">
          <Icon name="ph:book-open" class="w-3 h-3 mr-1" />
          {{ document.readTime }} min read
        </span>
      </div>

      <!-- Tags -->
      <div v-if="showTags && document.tags?.length" class="flex flex-wrap gap-1 mt-1.5">
        <span
          v-for="tag in visibleTags"
          :key="tag"
          :class="tagClasses"
        >
          {{ tag }}
        </span>
        <span v-if="hiddenTagCount > 0" :class="[tagClasses, 'opacity-60']">
          +{{ hiddenTagCount }}
        </span>
      </div>
    </div>

    <!-- Right Side -->
    <div class="flex items-center gap-2 shrink-0">
      <!-- Viewers Presence -->
      <div v-if="showViewers && document.viewers?.length" class="flex -space-x-1">
        <div
          v-for="viewer in displayedViewers"
          :key="viewer.id"
          :class="viewerAvatarClasses"
        >
          <SharedAgentAvatar :user="viewer" size="xs" :show-status="false" />
        </div>
        <div v-if="hiddenViewerCount > 0" :class="viewerCountClasses">
          +{{ hiddenViewerCount }}
        </div>
      </div>

      <!-- Quick Actions -->
      <Transition name="fade">
        <div v-if="showQuickActions && hovered" class="flex items-center gap-0.5">
          <TooltipProvider :delay-duration="200">
            <TooltipRoot>
              <TooltipTrigger as-child>
                <button
                  type="button"
                  :class="quickActionClasses"
                  @click.stop="handleStar"
                >
                  <Icon :name="document.isStarred ? 'ph:star-fill' : 'ph:star'" :class="['w-4 h-4', document.isStarred && 'text-amber-400']" />
                </button>
              </TooltipTrigger>
              <TooltipPortal>
                <TooltipContent :class="tooltipClasses" side="top">
                  {{ document.isStarred ? 'Unstar' : 'Star' }}
                  <TooltipArrow class="fill-white" />
                </TooltipContent>
              </TooltipPortal>
            </TooltipRoot>
          </TooltipProvider>

          <DropdownMenuRoot>
            <DropdownMenuTrigger as-child>
              <button type="button" :class="quickActionClasses" @click.stop>
                <Icon name="ph:dots-three" class="w-4 h-4" />
              </button>
            </DropdownMenuTrigger>
            <DropdownMenuPortal>
              <DropdownMenuContent :class="dropdownContentClasses" :side-offset="8" align="end">
                <DropdownMenuItem :class="dropdownItemClasses" @select="emit('open')">
                  <Icon name="ph:arrow-square-out" class="w-4 h-4 mr-2" />
                  Open
                </DropdownMenuItem>
                <DropdownMenuItem :class="dropdownItemClasses" @select="emit('edit')">
                  <Icon name="ph:pencil-simple" class="w-4 h-4 mr-2" />
                  Edit
                </DropdownMenuItem>
                <DropdownMenuSeparator class="h-px bg-gray-200 my-1" />
                <DropdownMenuItem :class="dropdownItemClasses" @select="emit('duplicate')">
                  <Icon name="ph:copy" class="w-4 h-4 mr-2" />
                  Duplicate
                </DropdownMenuItem>
                <DropdownMenuItem :class="dropdownItemClasses" @select="emit('move')">
                  <Icon name="ph:folder-simple" class="w-4 h-4 mr-2" />
                  Move to...
                </DropdownMenuItem>
                <DropdownMenuItem :class="dropdownItemClasses" @select="emit('share')">
                  <Icon name="ph:share" class="w-4 h-4 mr-2" />
                  Share
                </DropdownMenuItem>
                <DropdownMenuSeparator class="h-px bg-gray-200 my-1" />
                <DropdownMenuItem :class="dropdownItemClasses" @select="emit('pin')">
                  <Icon :name="document.isPinned ? 'ph:push-pin-slash' : 'ph:push-pin'" class="w-4 h-4 mr-2" />
                  {{ document.isPinned ? 'Unpin' : 'Pin' }}
                </DropdownMenuItem>
                <DropdownMenuItem :class="dropdownItemClasses" @select="emit('archive')">
                  <Icon name="ph:archive" class="w-4 h-4 mr-2" />
                  Archive
                </DropdownMenuItem>
                <DropdownMenuSeparator class="h-px bg-gray-200 my-1" />
                <DropdownMenuItem :class="[dropdownItemClasses, 'text-red-400']" @select="emit('delete')">
                  <Icon name="ph:trash" class="w-4 h-4 mr-2" />
                  Delete
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenuPortal>
          </DropdownMenuRoot>
        </div>
      </Transition>
    </div>
  </component>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import {
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuPortal,
  DropdownMenuRoot,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
  TooltipArrow,
  TooltipContent,
  TooltipPortal,
  TooltipProvider,
  TooltipRoot,
  TooltipTrigger,
} from 'reka-ui'
import type { Document, User } from '@/types'

type DocItemSize = 'sm' | 'md' | 'lg'
type DocItemVariant = 'default' | 'compact' | 'detailed' | 'card'

interface ExtendedDocument extends Document {
  description?: string
  type?: 'doc' | 'spreadsheet' | 'presentation' | 'code' | 'note'
  wordCount?: number
  readTime?: number
  tags?: string[]
  isShared?: boolean
  isStarred?: boolean
  isPinned?: boolean
  isLocked?: boolean
}

const props = withDefaults(defineProps<{
  // Core
  document: ExtendedDocument
  selected?: boolean

  // Appearance
  size?: DocItemSize
  variant?: DocItemVariant
  as?: string

  // Display options
  showAuthor?: boolean
  showDate?: boolean
  showDescription?: boolean
  showViewers?: boolean
  showBadges?: boolean
  showTags?: boolean
  showStats?: boolean
  showQuickActions?: boolean
  showTypeIndicator?: boolean

  // Interaction
  selectable?: boolean
  checked?: boolean
  reorderable?: boolean
  showCheckbox?: boolean
  maxTags?: number
  maxViewers?: number
}>(), {
  selected: false,
  size: 'md',
  variant: 'default',
  as: 'button',
  showAuthor: true,
  showDate: true,
  showDescription: false,
  showViewers: true,
  showBadges: true,
  showTags: false,
  showStats: false,
  showQuickActions: true,
  showTypeIndicator: false,
  selectable: false,
  checked: false,
  reorderable: false,
  showCheckbox: false,
  maxTags: 3,
  maxViewers: 3,
})

const emit = defineEmits<{
  click: []
  open: []
  edit: []
  duplicate: []
  move: []
  share: []
  pin: []
  archive: []
  delete: []
  star: []
  check: [checked: boolean]
  contextmenu: [event: MouseEvent]
  dragStart: []
}>()

// State
const hovered = ref(false)

// Size configuration
const sizeConfig: Record<DocItemSize, {
  padding: string
  iconSize: string
  iconContainer: string
  titleSize: string
  metaSize: string
  gap: string
}> = {
  sm: {
    padding: 'p-2',
    iconSize: 'w-4 h-4',
    iconContainer: 'w-8 h-8',
    titleSize: 'text-xs',
    metaSize: 'text-[10px]',
    gap: 'gap-2',
  },
  md: {
    padding: 'p-3',
    iconSize: 'w-5 h-5',
    iconContainer: 'w-10 h-10',
    titleSize: 'text-sm',
    metaSize: 'text-xs',
    gap: 'gap-3',
  },
  lg: {
    padding: 'p-4',
    iconSize: 'w-6 h-6',
    iconContainer: 'w-12 h-12',
    titleSize: 'text-base',
    metaSize: 'text-sm',
    gap: 'gap-4',
  },
}

// Computed values
const isNew = computed(() => {
  if (!props.document.createdAt) return false
  const dayAgo = Date.now() - 24 * 60 * 60 * 1000
  return new Date(props.document.createdAt).getTime() > dayAgo
})

const visibleTags = computed(() =>
  props.document.tags?.slice(0, props.maxTags) || []
)

const hiddenTagCount = computed(() =>
  Math.max(0, (props.document.tags?.length || 0) - props.maxTags)
)

const displayedViewers = computed(() =>
  props.document.viewers?.slice(0, props.maxViewers) || []
)

const hiddenViewerCount = computed(() =>
  Math.max(0, (props.document.viewers?.length || 0) - props.maxViewers)
)

// Icon based on document type
const documentIcon = computed(() => {
  const typeIcons: Record<string, string> = {
    doc: 'ph:file-text-fill',
    spreadsheet: 'ph:file-xls-fill',
    presentation: 'ph:presentation-fill',
    code: 'ph:file-code-fill',
    note: 'ph:note-fill',
  }
  return typeIcons[props.document.type || 'doc'] || 'ph:file-text-fill'
})

const getTypeIcon = (type: string): string => {
  const icons: Record<string, string> = {
    doc: 'ph:file-text',
    spreadsheet: 'ph:table',
    presentation: 'ph:presentation',
    code: 'ph:code',
    note: 'ph:note',
  }
  return icons[type] || 'ph:file-text'
}

// Container classes
const containerClasses = computed(() => {
  const base = [
    'w-full flex items-start text-left group transition-all duration-150 ease-out outline-none',
    'focus-visible:ring-2 focus-visible:ring-gray-900/50 focus-visible:ring-offset-2 focus-visible:ring-offset-white',
    sizeConfig[props.size].padding,
    sizeConfig[props.size].gap,
  ]

  if (props.variant === 'card') {
    base.push('rounded-lg border')
    base.push(
      props.selected
        ? 'bg-gray-900 text-white shadow-md border-gray-900'
        : 'bg-white border-gray-100 hover:border-gray-200 hover:bg-gray-50'
    )
  } else {
    base.push('rounded-lg')
    base.push(
      props.selected
        ? 'bg-gray-900 text-white shadow-md'
        : 'hover:bg-gray-50 text-gray-900'
    )
  }

  return base
})

// Icon classes
const iconContainerClasses = computed(() => [
  'relative rounded-lg flex items-center justify-center shrink-0 transition-all duration-150 ease-out',
  sizeConfig[props.size].iconContainer,
  props.selected ? 'bg-white/20' : 'bg-gray-100',
])

const iconClasses = computed(() => [
  sizeConfig[props.size].iconSize,
  props.selected ? 'text-white' : 'text-gray-500',
])

// Title classes
const titleClasses = computed(() => [
  'font-medium truncate',
  sizeConfig[props.size].titleSize,
  props.selected ? 'text-white' : 'text-gray-900',
])

// Description classes
const descriptionClasses = computed(() => [
  'line-clamp-2 mt-0.5',
  props.size === 'sm' ? 'text-[10px]' : 'text-xs',
  props.selected ? 'text-white/70' : 'text-gray-500',
])

// Meta row classes
const metaRowClasses = computed(() => [
  'flex items-center gap-2 mt-1',
  sizeConfig[props.size].metaSize,
])

const metaTextClasses = computed(() => [
  'flex items-center truncate',
  props.selected ? 'text-white/70' : 'text-gray-500',
])

const separatorClasses = computed(() => [
  props.selected ? 'text-white/40' : 'text-gray-200',
])

// Badge classes
const sharedBadgeClasses = computed(() => [
  'p-1 rounded',
  props.selected ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-500',
])

const starredBadgeClasses = computed(() => [
  'p-1 rounded',
  props.selected ? 'bg-white/20 text-amber-300' : 'bg-gray-100 text-amber-500',
])

const pinnedBadgeClasses = computed(() => [
  'p-1 rounded',
  props.selected ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-500',
])

const lockedBadgeClasses = computed(() => [
  'p-1 rounded',
  props.selected ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-500',
])

const newBadgeClasses = computed(() => [
  'absolute -top-1 -right-1 p-0.5 rounded-full',
  props.selected ? 'text-gray-300' : 'text-gray-400',
])

const typeIndicatorClasses = computed(() => [
  'absolute -bottom-0.5 -right-0.5 p-0.5 rounded bg-white',
  props.selected ? 'text-white/70' : 'text-gray-400',
])

// Tag classes
const tagClasses = computed(() => [
  'px-1.5 py-0.5 rounded text-[10px]',
  props.selected
    ? 'bg-white/20 text-white'
    : 'bg-gray-100 text-gray-500',
])

// Viewer classes
const viewerAvatarClasses = computed(() => [
  'w-5 h-5 rounded-full border-2 overflow-hidden',
  props.selected ? 'border-gray-900' : 'border-gray-50',
])

const viewerCountClasses = computed(() => [
  'w-5 h-5 rounded-full border-2 flex items-center justify-center text-[9px] font-medium',
  props.selected
    ? 'border-gray-900 bg-white/20 text-white'
    : 'border-gray-50 bg-gray-100 text-gray-500',
])

// Checkbox classes
const checkboxLabelClasses = computed(() => [
  'flex items-center cursor-pointer',
])

const checkboxClasses = computed(() => [
  'w-4 h-4 rounded border-2 cursor-pointer transition-colors',
  'focus:ring-2 focus:ring-gray-900/50 focus:ring-offset-2 focus:ring-offset-white',
  props.checked
    ? 'bg-gray-900 border-gray-900'
    : 'bg-transparent border-gray-200 hover:border-gray-300',
])

// Drag handle classes
const dragHandleClasses = computed(() => [
  'absolute left-0 top-1/2 -translate-y-1/2 -translate-x-2 p-1 cursor-grab',
  'text-gray-400 hover:text-gray-500',
  'opacity-0 group-hover:opacity-100 transition-opacity',
])

// Quick action classes
const quickActionClasses = computed(() => [
  'p-1.5 rounded-md transition-all duration-150 ease-out',
  props.selected
    ? 'text-white/70 hover:text-white hover:bg-white/20'
    : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100',
])

// Dropdown classes
const dropdownContentClasses = computed(() => [
  'z-50 min-w-[180px] bg-white border border-gray-200 rounded-lg',
  'p-1.5 shadow-lg',
  'animate-in fade-in-0 duration-150',
])

const dropdownItemClasses = computed(() => [
  'flex items-center px-2 py-1.5 text-sm rounded-md cursor-pointer',
  'text-gray-700 hover:bg-gray-50',
  'transition-colors duration-150 ease-out outline-none',
  'data-[highlighted]:bg-gray-50',
])

// Tooltip classes
const tooltipClasses = computed(() => [
  'z-50 bg-white border border-gray-200 rounded-lg',
  'px-2 py-1 text-xs shadow-md',
  'animate-in fade-in-0 duration-150',
])

// Helper functions
const formatDate = (date: Date) => {
  const d = new Date(date)
  const now = new Date()
  const diff = now.getTime() - d.getTime()
  const days = Math.floor(diff / (1000 * 60 * 60 * 24))

  if (days === 0) return 'today'
  if (days === 1) return 'yesterday'
  if (days < 7) return `${days} days ago`
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

const formatNumber = (num: number): string => {
  if (num < 1000) return num.toString()
  return `${(num / 1000).toFixed(1)}K`
}

// Handlers
const handleClick = () => {
  emit('click')
}

const handleContextMenu = (event: MouseEvent) => {
  emit('contextmenu', event)
}

const handleStar = () => {
  emit('star')
}

const handleDragStart = () => {
  emit('dragStart')
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
