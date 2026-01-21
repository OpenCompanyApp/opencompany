<template>
  <TooltipProvider :delay-duration="400">
    <component
      :is="draggable ? 'div' : 'button'"
      :class="rowClasses"
      :style="rowStyles"
      :draggable="draggable"
      :disabled="disabled"
      :aria-expanded="isFolder ? expanded : undefined"
      :aria-selected="selected"
      :aria-label="ariaLabel"
      @click="$emit('click')"
      @dragstart="$emit('dragstart', $event)"
      @dragend="$emit('dragend')"
      @keydown="handleKeydown"
    >
      <!-- Selection checkbox -->
      <Transition name="checkbox">
        <div
          v-if="selectable"
          class="shrink-0 mr-1"
          @click.stop
        >
          <button
            type="button"
            role="checkbox"
            :aria-checked="isSelected"
            :class="checkboxClasses"
            @click.stop="$emit('select')"
          >
            <Transition name="check" mode="out-in">
              <Icon
                v-if="isSelected"
                key="checked"
                name="ph:check-bold"
                :class="checkIconClasses"
              />
              <span v-else key="unchecked" />
            </Transition>
          </button>
        </div>
      </Transition>

      <!-- Drag handle -->
      <Transition name="drag-handle">
        <div
          v-if="draggable && !disabled"
          class="shrink-0 mr-1 cursor-grab active:cursor-grabbing"
          @mousedown.stop
        >
          <Icon
            name="ph:dots-six-vertical"
            :class="[
              'transition-opacity duration-150 ease-out',
              isDragging ? 'opacity-100' : 'opacity-0 group-hover:opacity-50 hover:!opacity-100'
            ]"
            :style="iconSizeStyle"
          />
        </div>
      </Transition>

      <!-- Expand/Collapse Toggle (for folders) -->
      <button
        v-if="isFolder"
        type="button"
        :class="toggleButtonClasses"
        :aria-label="expanded ? 'Collapse folder' : 'Expand folder'"
        @click.stop="$emit('toggle')"
      >
        <Icon
          name="ph:caret-right-fill"
          :class="toggleIconClasses"
        />
      </button>
      <div v-else :class="togglePlaceholderClasses" />

      <!-- Loading spinner (replaces icon when loading) -->
      <div v-if="loading" :class="iconContainerClasses">
        <Icon
          name="ph:spinner"
          :class="['animate-spin', iconColorClasses]"
          :style="iconSizeStyle"
        />
      </div>

      <!-- Icon -->
      <div v-else :class="iconContainerClasses">
        <Icon
          :name="iconName"
          :class="documentIconClasses"
        />

        <!-- Status overlay (for shared/locked) -->
        <Transition name="status-badge">
          <div
            v-if="isLocked"
            class="absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full bg-white flex items-center justify-center"
          >
            <Icon name="ph:lock-fill" class="w-2 h-2 text-gray-500" />
          </div>
          <div
            v-else-if="isShared"
            class="absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full bg-white flex items-center justify-center"
          >
            <Icon name="ph:users-fill" class="w-2 h-2 text-gray-500" />
          </div>
        </Transition>
      </div>

      <!-- Content -->
      <div class="flex-1 min-w-0">
        <!-- Title row -->
        <div class="flex items-center gap-1.5">
          <!-- Path (optional) -->
          <span
            v-if="showPath && path"
            class="text-gray-400 shrink-0"
            :class="metaTextClasses"
          >
            {{ path }} /
          </span>

          <!-- Title with highlight -->
          <p :class="titleClasses">
            <template v-if="highlightQuery && highlightedTitle.length > 0">
              <template v-for="(part, index) in highlightedTitle" :key="index">
                <mark
                  v-if="part.highlight"
                  class="bg-yellow-100 text-gray-900 rounded px-0.5"
                >{{ part.text }}</mark>
                <span v-else>{{ part.text }}</span>
              </template>
            </template>
            <template v-else>{{ title }}</template>
          </p>

          <!-- Badges -->
          <div v-if="hasBadges" class="flex items-center gap-1 shrink-0">
            <TooltipRoot v-if="isPinned">
              <TooltipTrigger as-child>
                <span class="flex">
                  <Icon name="ph:push-pin-fill" class="w-3 h-3 text-gray-500" />
                </span>
              </TooltipTrigger>
              <TooltipContent side="top" :side-offset="4">
                <p class="text-xs">Pinned</p>
              </TooltipContent>
            </TooltipRoot>

            <TooltipRoot v-if="isStarred">
              <TooltipTrigger as-child>
                <span class="flex">
                  <Icon name="ph:star-fill" class="w-3 h-3 text-gray-500" />
                </span>
              </TooltipTrigger>
              <TooltipContent side="top" :side-offset="4">
                <p class="text-xs">Starred</p>
              </TooltipContent>
            </TooltipRoot>
          </div>
        </div>

        <!-- Metadata row -->
        <div v-if="showMetadata && hasMetadata" :class="metadataRowClasses">
          <!-- Date/item count -->
          <span
            v-if="!isFolder && updatedAt"
            :class="metaTextClasses"
          >
            Updated {{ formatDate(updatedAt) }}
          </span>
          <span
            v-else-if="isFolder"
            :class="metaTextClasses"
          >
            {{ childCount }} {{ childCount === 1 ? 'item' : 'items' }}
          </span>

          <!-- Tags -->
          <template v-if="tags && tags.length > 0">
            <span class="text-gray-200">&middot;</span>
            <div class="flex items-center gap-1">
              <span
                v-for="tag in displayTags"
                :key="tag"
                class="px-1.5 py-0.5 rounded-md text-[10px] bg-gray-100 text-gray-500"
              >
                {{ tag }}
              </span>
              <span
                v-if="tags.length > maxDisplayTags"
                class="text-gray-400"
                :class="metaTextClasses"
              >
                +{{ tags.length - maxDisplayTags }}
              </span>
            </div>
          </template>
        </div>
      </div>

      <!-- Quick actions -->
      <Transition name="actions">
        <div
          v-if="showActions && !disabled"
          :class="[
            'flex items-center gap-1 shrink-0 ml-2',
            'opacity-0 group-hover:opacity-100 focus-within:opacity-100',
            'transition-opacity duration-150 ease-out',
          ]"
          @click.stop
        >
          <!-- Star button -->
          <TooltipRoot>
            <TooltipTrigger as-child>
              <button
                type="button"
                :class="actionButtonClasses"
                :aria-label="isStarred ? 'Remove from starred' : 'Add to starred'"
                @click.stop="$emit('star')"
              >
                <Icon
                  :name="isStarred ? 'ph:star-fill' : 'ph:star'"
                  :class="[isStarred ? 'text-gray-500' : 'text-gray-400']"
                  :style="actionIconSizeStyle"
                />
              </button>
            </TooltipTrigger>
            <TooltipContent side="top" :side-offset="4">
              <p class="text-xs">{{ isStarred ? 'Unstar' : 'Star' }}</p>
            </TooltipContent>
          </TooltipRoot>

          <!-- More options dropdown -->
          <DropdownMenuRoot>
            <DropdownMenuTrigger as-child>
              <button
                type="button"
                :class="actionButtonClasses"
                aria-label="More options"
              >
                <Icon
                  name="ph:dots-three"
                  class="text-gray-500"
                  :style="actionIconSizeStyle"
                />
              </button>
            </DropdownMenuTrigger>

            <DropdownMenuPortal>
              <DropdownMenuContent
                :side-offset="4"
                align="end"
                class="min-w-[160px] bg-white rounded-lg border border-gray-200 shadow-lg p-1.5 z-50 animate-in fade-in-0 duration-150"
              >
                <DropdownMenuItem
                  class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 rounded-md cursor-pointer outline-none hover:bg-gray-50 focus:bg-gray-50 transition-colors duration-150"
                  @select="$emit('rename')"
                >
                  <Icon name="ph:pencil-simple" class="w-4 h-4 text-gray-500" />
                  <span>Rename</span>
                </DropdownMenuItem>

                <DropdownMenuItem
                  class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 rounded-md cursor-pointer outline-none hover:bg-gray-50 focus:bg-gray-50 transition-colors duration-150"
                  @select="$emit('duplicate')"
                >
                  <Icon name="ph:copy" class="w-4 h-4 text-gray-500" />
                  <span>Duplicate</span>
                </DropdownMenuItem>

                <DropdownMenuItem
                  class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 rounded-md cursor-pointer outline-none hover:bg-gray-50 focus:bg-gray-50 transition-colors duration-150"
                  @select="$emit('pin')"
                >
                  <Icon
                    :name="isPinned ? 'ph:push-pin-slash' : 'ph:push-pin'"
                    class="w-4 h-4 text-gray-500"
                  />
                  <span>{{ isPinned ? 'Unpin' : 'Pin' }}</span>
                </DropdownMenuItem>

                <DropdownMenuItem
                  class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 rounded-md cursor-pointer outline-none hover:bg-gray-50 focus:bg-gray-50 transition-colors duration-150"
                  @select="$emit('move')"
                >
                  <Icon name="ph:folder-simple" class="w-4 h-4 text-gray-500" />
                  <span>Move to...</span>
                </DropdownMenuItem>

                <DropdownMenuSeparator class="h-px bg-gray-200 my-1.5" />

                <DropdownMenuItem
                  class="flex items-center gap-2 px-3 py-2 text-sm text-red-600 rounded-md cursor-pointer outline-none hover:bg-red-50 focus:bg-red-50 transition-colors duration-150"
                  @select="$emit('delete')"
                >
                  <Icon name="ph:trash" class="w-4 h-4" />
                  <span>Delete</span>
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenuPortal>
          </DropdownMenuRoot>
        </div>
      </Transition>
    </component>
  </TooltipProvider>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import {
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuPortal,
  DropdownMenuRoot,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
  TooltipRoot,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from 'reka-ui'

// ============================================================================
// Types
// ============================================================================

type RowSize = 'sm' | 'md' | 'lg'

interface HighlightPart {
  text: string
  highlight: boolean
}

// ============================================================================
// Size Configuration
// ============================================================================

const sizeConfig: Record<RowSize, {
  padding: { x: number; y: number }
  indent: number
  iconContainer: string
  iconSize: string
  toggleSize: string
  titleSize: string
  metaSize: string
  actionSize: string
  actionIconSize: string
  gap: string
}> = {
  sm: {
    padding: { x: 6, y: 4 },
    indent: 16,
    iconContainer: 'w-5 h-5',
    iconSize: 'w-3 h-3',
    toggleSize: 'w-4 h-4',
    titleSize: 'text-xs',
    metaSize: 'text-[10px]',
    actionSize: 'w-5 h-5',
    actionIconSize: 'w-3 h-3',
    gap: 'gap-1.5',
  },
  md: {
    padding: { x: 8, y: 8 },
    indent: 20,
    iconContainer: 'w-7 h-7',
    iconSize: 'w-4 h-4',
    toggleSize: 'w-5 h-5',
    titleSize: 'text-sm',
    metaSize: 'text-xs',
    actionSize: 'w-6 h-6',
    actionIconSize: 'w-3.5 h-3.5',
    gap: 'gap-2',
  },
  lg: {
    padding: { x: 10, y: 10 },
    indent: 24,
    iconContainer: 'w-9 h-9',
    iconSize: 'w-5 h-5',
    toggleSize: 'w-6 h-6',
    titleSize: 'text-base',
    metaSize: 'text-sm',
    actionSize: 'w-8 h-8',
    actionIconSize: 'w-4 h-4',
    gap: 'gap-2.5',
  },
}

const documentTypeIcons: Record<string, string> = {
  folder: 'ph:folder-fill',
  'folder-open': 'ph:folder-open-fill',
  document: 'ph:file-text-fill',
  markdown: 'ph:markdown-logo-fill',
  code: 'ph:file-code-fill',
  image: 'ph:image-fill',
  pdf: 'ph:file-pdf-fill',
  spreadsheet: 'ph:table-fill',
  presentation: 'ph:presentation-chart-fill',
  archive: 'ph:file-archive-fill',
  video: 'ph:video-fill',
  audio: 'ph:music-notes-fill',
}

const documentTypeColors: Record<string, string> = {
  folder: 'text-gray-500',
  'folder-open': 'text-gray-500',
  document: 'text-gray-500',
  markdown: 'text-gray-500',
  code: 'text-gray-500',
  image: 'text-gray-500',
  pdf: 'text-gray-500',
  spreadsheet: 'text-gray-500',
  presentation: 'text-gray-500',
  archive: 'text-gray-500',
  video: 'text-gray-500',
  audio: 'text-gray-500',
}

// ============================================================================
// Props & Emits
// ============================================================================

const props = withDefaults(defineProps<{
  title: string
  isFolder: boolean
  expanded: boolean
  selected: boolean
  level: number
  childCount?: number
  updatedAt?: Date
  documentType?: string
  isShared?: boolean
  isStarred?: boolean
  isPinned?: boolean
  isLocked?: boolean
  tags?: string[]
  size?: RowSize
  selectable?: boolean
  isSelected?: boolean
  draggable?: boolean
  isDragging?: boolean
  highlightQuery?: string
  showPath?: boolean
  path?: string
  loading?: boolean
  disabled?: boolean
  showActions?: boolean
  showMetadata?: boolean
}>(), {
  childCount: 0,
  documentType: 'document',
  isShared: false,
  isStarred: false,
  isPinned: false,
  isLocked: false,
  tags: () => [],
  size: 'md',
  selectable: false,
  isSelected: false,
  draggable: false,
  isDragging: false,
  highlightQuery: '',
  showPath: false,
  path: '',
  loading: false,
  disabled: false,
  showActions: false,
  showMetadata: true,
})

defineEmits<{
  click: []
  toggle: []
  select: []
  star: []
  pin: []
  duplicate: []
  delete: []
  rename: []
  move: []
  dragstart: [event: DragEvent]
  dragend: []
}>()

// ============================================================================
// Constants
// ============================================================================

const maxDisplayTags = 2

// ============================================================================
// Computed - Configuration
// ============================================================================

const config = computed(() => sizeConfig[props.size])

// ============================================================================
// Computed - Styling
// ============================================================================

const rowClasses = computed(() => [
  'w-full flex items-start rounded-lg text-left group outline-none relative overflow-hidden',
  'transition-all duration-150 ease-out',
  'focus-visible:ring-2 focus-visible:ring-gray-900/50 focus-visible:ring-offset-2 focus-visible:ring-offset-white',
  config.value.gap,
  props.selected
    ? 'bg-gray-100 text-gray-900 border-l-2 border-gray-900'
    : 'hover:bg-gray-50 text-gray-900',
  props.disabled && 'opacity-50 cursor-not-allowed',
  props.isDragging && 'opacity-60 shadow-lg ring-2 ring-gray-300',
  props.isLocked && !props.selected && 'bg-gray-50',
])

const rowStyles = computed(() => ({
  paddingLeft: `${config.value.padding.x + props.level * config.value.indent}px`,
  paddingRight: `${config.value.padding.x}px`,
  paddingTop: `${config.value.padding.y}px`,
  paddingBottom: `${config.value.padding.y}px`,
}))

const checkboxClasses = computed(() => [
  'flex items-center justify-center rounded border-2',
  'transition-colors duration-150 ease-out',
  config.value.toggleSize,
  props.isSelected
    ? 'bg-gray-900 border-gray-900'
    : 'border-gray-200 hover:border-gray-400',
])

const checkIconClasses = computed(() => [
  'text-white',
  props.size === 'sm' ? 'w-2.5 h-2.5' : props.size === 'lg' ? 'w-3.5 h-3.5' : 'w-3 h-3',
])

const toggleButtonClasses = computed(() => [
  'flex items-center justify-center shrink-0 -ml-1 rounded',
  'transition-colors duration-150 ease-out',
  'hover:bg-gray-100',
  config.value.toggleSize,
])

const togglePlaceholderClasses = computed(() => [
  'shrink-0',
  config.value.toggleSize,
])

const toggleIconClasses = computed(() => [
  'transition-transform duration-150 ease-out',
  props.expanded ? 'rotate-90' : '',
  props.selected ? 'text-gray-900' : 'text-gray-500',
  props.size === 'sm' ? 'w-2.5 h-2.5' : props.size === 'lg' ? 'w-3.5 h-3.5' : 'w-3 h-3',
])

const iconContainerClasses = computed(() => [
  'relative rounded-lg flex items-center justify-center shrink-0',
  'transition-colors duration-150 ease-out',
  config.value.iconContainer,
  props.selected
    ? 'bg-gray-200'
    : 'bg-gray-100',
])

const iconSizeStyle = computed(() => {
  const sizes = { sm: '12px', md: '16px', lg: '20px' }
  return { width: sizes[props.size], height: sizes[props.size] }
})

const iconColorClasses = computed(() => {
  if (props.selected) return 'text-gray-700'
  return documentTypeColors[effectiveDocType.value] || 'text-gray-500'
})

const documentIconClasses = computed(() => [
  iconColorClasses.value,
  config.value.iconSize,
])

const titleClasses = computed(() => [
  'font-medium truncate',
  config.value.titleSize,
  props.disabled && 'text-gray-500',
])

const metadataRowClasses = computed(() => [
  'flex items-center gap-1.5 mt-0.5',
])

const metaTextClasses = computed(() => [
  'truncate',
  config.value.metaSize,
  props.selected ? 'text-gray-500' : 'text-gray-400',
])

const actionButtonClasses = computed(() => [
  'flex items-center justify-center rounded-md',
  'transition-colors duration-150 ease-out',
  'hover:bg-gray-100',
  'focus-visible:ring-2 focus-visible:ring-gray-900/50 focus-visible:ring-offset-1 focus-visible:ring-offset-white',
  config.value.actionSize,
])

const actionIconSizeStyle = computed(() => {
  const sizes = { sm: '12px', md: '14px', lg: '16px' }
  return { width: sizes[props.size], height: sizes[props.size] }
})

// ============================================================================
// Computed - Data
// ============================================================================

const effectiveDocType = computed(() => {
  if (props.isFolder) {
    return props.expanded ? 'folder-open' : 'folder'
  }
  return props.documentType || 'document'
})

const iconName = computed(() => {
  return documentTypeIcons[effectiveDocType.value] || 'ph:file-text-fill'
})

const hasBadges = computed(() =>
  props.isPinned || props.isStarred
)

const hasMetadata = computed(() =>
  props.updatedAt || props.isFolder || (props.tags && props.tags.length > 0)
)

const displayTags = computed(() =>
  props.tags?.slice(0, maxDisplayTags) || []
)

const highlightedTitle = computed((): HighlightPart[] => {
  if (!props.highlightQuery) return []

  const query = props.highlightQuery.toLowerCase()
  const title = props.title
  const parts: HighlightPart[] = []

  let lastIndex = 0
  let index = title.toLowerCase().indexOf(query)

  while (index !== -1) {
    if (index > lastIndex) {
      parts.push({ text: title.slice(lastIndex, index), highlight: false })
    }
    parts.push({ text: title.slice(index, index + query.length), highlight: true })
    lastIndex = index + query.length
    index = title.toLowerCase().indexOf(query, lastIndex)
  }

  if (lastIndex < title.length) {
    parts.push({ text: title.slice(lastIndex), highlight: false })
  }

  return parts
})

const ariaLabel = computed(() => {
  const parts = [props.title]
  if (props.isFolder) {
    parts.push(`folder with ${props.childCount} items`)
    parts.push(props.expanded ? 'expanded' : 'collapsed')
  }
  if (props.isLocked) parts.push('locked')
  if (props.isShared) parts.push('shared')
  if (props.isPinned) parts.push('pinned')
  if (props.isStarred) parts.push('starred')
  return parts.join(', ')
})

// ============================================================================
// Methods
// ============================================================================

const formatDate = (date: Date) => {
  const d = new Date(date)
  const now = new Date()
  const diff = now.getTime() - d.getTime()
  const seconds = Math.floor(diff / 1000)
  const minutes = Math.floor(seconds / 60)
  const hours = Math.floor(minutes / 60)
  const days = Math.floor(hours / 24)

  if (seconds < 60) return 'just now'
  if (minutes < 60) return `${minutes}m ago`
  if (hours < 24) return `${hours}h ago`
  if (days === 0) return 'today'
  if (days === 1) return 'yesterday'
  if (days < 7) return `${days}d ago`
  if (days < 30) return `${Math.floor(days / 7)}w ago`
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

const handleKeydown = (event: KeyboardEvent) => {
  if (event.key === 'Enter' || event.key === ' ') {
    event.preventDefault()
    // Click is handled by the element's @click
  } else if (event.key === 'ArrowRight' && props.isFolder && !props.expanded) {
    event.preventDefault()
    // Parent should handle expansion
  } else if (event.key === 'ArrowLeft' && props.isFolder && props.expanded) {
    event.preventDefault()
    // Parent should handle collapse
  }
}
</script>

<style scoped>
/* Checkbox animation */
.checkbox-enter-active,
.checkbox-leave-active {
  transition: opacity 0.15s ease-out;
}

.checkbox-enter-from,
.checkbox-leave-to {
  opacity: 0;
}

/* Check mark animation */
.check-enter-active {
  transition: opacity 0.15s ease-out;
}

.check-leave-active {
  transition: opacity 0.1s ease-out;
}

.check-enter-from,
.check-leave-to {
  opacity: 0;
}

/* Drag handle animation */
.drag-handle-enter-active,
.drag-handle-leave-active {
  transition: opacity 0.15s ease-out;
}

.drag-handle-enter-from,
.drag-handle-leave-to {
  opacity: 0;
}

/* Status badge animation */
.status-badge-enter-active {
  transition: opacity 0.15s ease-out;
}

.status-badge-leave-active {
  transition: opacity 0.1s ease-out;
}

.status-badge-enter-from,
.status-badge-leave-to {
  opacity: 0;
}

/* Actions animation */
.actions-enter-active {
  transition: opacity 0.15s ease-out;
}

.actions-leave-active {
  transition: opacity 0.1s ease-out;
}

.actions-enter-from,
.actions-leave-to {
  opacity: 0;
}

/* Focus styles */
button:focus-visible {
  outline: none;
}
</style>
