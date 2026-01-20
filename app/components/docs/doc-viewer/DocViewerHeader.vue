<template>
  <header :class="headerClasses">
    <!-- Background gradient decoration -->
    <div
      v-if="showGradient"
      class="absolute inset-0 bg-gradient-to-r from-olympus-primary/5 via-transparent to-transparent pointer-events-none"
    />

    <div class="relative flex items-start justify-between gap-4">
      <!-- Left side: Title and metadata -->
      <div class="flex-1 min-w-0">
        <!-- Breadcrumb navigation -->
        <Transition name="breadcrumb">
          <nav
            v-if="breadcrumbs && breadcrumbs.length > 0"
            class="flex items-center gap-1 mb-2 text-sm"
            aria-label="Breadcrumb"
          >
            <template v-for="(crumb, index) in breadcrumbs" :key="crumb.id || index">
              <button
                v-if="index < breadcrumbs.length - 1"
                type="button"
                class="text-olympus-text-muted hover:text-olympus-text transition-colors truncate max-w-[120px]"
                @click="$emit('navigate', crumb)"
              >
                {{ crumb.title }}
              </button>
              <span v-else class="text-olympus-text-subtle truncate max-w-[120px]">
                {{ crumb.title }}
              </span>

              <Icon
                v-if="index < breadcrumbs.length - 1"
                name="ph:caret-right"
                class="w-3 h-3 text-olympus-text-subtle shrink-0"
              />
            </template>
          </nav>
        </Transition>

        <!-- Title row -->
        <div class="flex items-center gap-3">
          <!-- Document type icon -->
          <div
            v-if="showIcon"
            :class="[
              'shrink-0 flex items-center justify-center rounded-lg transition-transform duration-150',
              iconContainerClasses,
              'hover:scale-105'
            ]"
          >
            <Icon
              :name="documentIcon"
              :class="['transition-colors', iconClasses]"
            />
          </div>

          <!-- Title with edit mode -->
          <div class="flex-1 min-w-0">
            <div v-if="isEditingTitle" class="flex items-center gap-2">
              <input
                ref="titleInputRef"
                v-model="editedTitle"
                type="text"
                class="flex-1 bg-transparent border-b-2 border-olympus-primary text-2xl font-bold text-olympus-text outline-none py-1"
                :placeholder="title"
                @keydown.enter="saveTitle"
                @keydown.escape="cancelTitleEdit"
                @blur="saveTitle"
              />
              <div class="flex items-center gap-1">
                <button
                  type="button"
                  class="p-1 rounded hover:bg-olympus-surface transition-colors"
                  aria-label="Save title"
                  @click="saveTitle"
                >
                  <Icon name="ph:check" class="w-4 h-4 text-green-400" />
                </button>
                <button
                  type="button"
                  class="p-1 rounded hover:bg-olympus-surface transition-colors"
                  aria-label="Cancel edit"
                  @click="cancelTitleEdit"
                >
                  <Icon name="ph:x" class="w-4 h-4 text-red-400" />
                </button>
              </div>
            </div>

            <h1
              v-else
              :class="titleClasses"
              @dblclick="startTitleEdit"
            >
              {{ title }}

              <!-- Title badges -->
              <span
                v-if="isLocked"
                class="ml-2 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs bg-amber-500/10 text-amber-400"
              >
                <Icon name="ph:lock-fill" class="w-3 h-3" />
                Locked
              </span>
              <span
                v-if="isTemplate"
                class="ml-2 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs bg-purple-500/10 text-purple-400"
              >
                <Icon name="ph:file-dashed" class="w-3 h-3" />
                Template
              </span>
            </h1>
          </div>
        </div>

        <!-- Metadata row -->
        <div :class="metadataClasses">
          <!-- Author -->
          <TooltipProvider v-if="author" :delay-duration="300">
            <Tooltip>
              <TooltipTrigger as-child>
                <button
                  type="button"
                  class="flex items-center gap-2 hover:bg-olympus-surface rounded-md px-1.5 py-0.5 -ml-1.5 transition-colors"
                  @click="$emit('author-click', author)"
                >
                  <SharedAgentAvatar :user="author" size="xs" :show-status="false" />
                  <span>{{ author.name }}</span>
                </button>
              </TooltipTrigger>
              <TooltipContent
                side="bottom"
                :side-offset="4"
                class="bg-olympus-elevated border border-olympus-border rounded-lg p-3 shadow-xl max-w-xs"
              >
                <div class="flex items-center gap-3">
                  <SharedAgentAvatar :user="author" size="md" :show-status="true" />
                  <div>
                    <p class="font-medium text-olympus-text">{{ author.name }}</p>
                    <p v-if="author.role" class="text-xs text-olympus-text-muted">
                      {{ author.role }}
                    </p>
                  </div>
                </div>
              </TooltipContent>
            </Tooltip>
          </TooltipProvider>

          <span v-if="author && (updatedAt || createdAt)" class="text-olympus-border">/</span>

          <!-- Dates -->
          <TooltipProvider v-if="updatedAt" :delay-duration="300">
            <Tooltip>
              <TooltipTrigger as-child>
                <span class="cursor-help">{{ formatRelativeDate(updatedAt) }}</span>
              </TooltipTrigger>
              <TooltipContent side="bottom" :side-offset="4">
                <div class="text-xs">
                  <p>Last updated: {{ formatFullDate(updatedAt) }}</p>
                  <p v-if="createdAt" class="text-olympus-text-muted">
                    Created: {{ formatFullDate(createdAt) }}
                  </p>
                </div>
              </TooltipContent>
            </Tooltip>
          </TooltipProvider>

          <!-- Version indicator -->
          <template v-if="version">
            <span class="text-olympus-border">&middot;</span>
            <button
              type="button"
              class="flex items-center gap-1 text-olympus-text-muted hover:text-olympus-text transition-colors"
              @click="$emit('version-click')"
            >
              <Icon name="ph:git-branch" class="w-3.5 h-3.5" />
              <span>v{{ version }}</span>
            </button>
          </template>

          <!-- Word count -->
          <template v-if="wordCount !== undefined">
            <span class="text-olympus-border">&middot;</span>
            <span class="text-olympus-text-subtle">
              {{ formatNumber(wordCount) }} words
            </span>
          </template>

          <!-- Read time -->
          <template v-if="readTime">
            <span class="text-olympus-border">&middot;</span>
            <span class="text-olympus-text-subtle">
              {{ readTime }} min read
            </span>
          </template>
        </div>

        <!-- Tags -->
        <Transition name="tags">
          <div v-if="tags && tags.length > 0" class="flex items-center gap-2 mt-2">
            <TransitionGroup name="tag" tag="div" class="flex items-center gap-1.5 flex-wrap">
              <button
                v-for="tag in displayTags"
                :key="tag"
                type="button"
                :class="tagClasses"
                @click="$emit('tag-click', tag)"
              >
                {{ tag }}
              </button>
            </TransitionGroup>
            <button
              v-if="tags.length > maxDisplayTags"
              type="button"
              class="text-xs text-olympus-text-muted hover:text-olympus-text transition-colors"
              @click="$emit('tags-expand')"
            >
              +{{ tags.length - maxDisplayTags }} more
            </button>
          </div>
        </Transition>
      </div>

      <!-- Right side: Viewers, editors, and actions -->
      <div class="flex items-center gap-4 shrink-0">
        <!-- Viewers Presence -->
        <Transition name="presence">
          <div v-if="viewers && viewers.length > 0" class="flex items-center gap-2">
            <SharedPresenceRow
              :users="viewers"
              :max-visible="maxVisibleViewers"
              :show-tooltip="true"
            />
            <span class="text-xs text-olympus-text-muted">viewing</span>
          </div>
        </Transition>

        <!-- Editors Indicator -->
        <Transition name="editor">
          <TooltipProvider v-if="editors && editors.length > 0" :delay-duration="300">
            <Tooltip>
              <TooltipTrigger as-child>
                <div class="flex items-center gap-2 px-3 py-1.5 bg-olympus-primary/20 rounded-full cursor-help">
                  <Icon name="ph:pencil-simple" class="w-4 h-4 text-olympus-primary animate-pulse" />
                  <span class="text-xs text-olympus-primary font-medium">
                    {{ editors.length === 1 ? editors[0].name : `${editors.length} people` }} editing
                  </span>
                </div>
              </TooltipTrigger>
              <TooltipContent
                side="bottom"
                :side-offset="4"
                class="bg-olympus-elevated border border-olympus-border rounded-lg p-3 shadow-xl"
              >
                <p class="text-xs text-olympus-text-muted mb-2">Currently editing:</p>
                <div class="space-y-2">
                  <div
                    v-for="editor in editors"
                    :key="editor.id"
                    class="flex items-center gap-2"
                  >
                    <SharedAgentAvatar :user="editor" size="xs" :show-status="false" />
                    <span class="text-sm text-olympus-text">{{ editor.name }}</span>
                  </div>
                </div>
              </TooltipContent>
            </Tooltip>
          </TooltipProvider>
        </Transition>

        <!-- Auto-save indicator -->
        <Transition name="autosave" mode="out-in">
          <div
            v-if="saveStatus"
            :class="[
              'flex items-center gap-1.5 text-xs',
              saveStatus === 'saving' && 'text-olympus-text-muted',
              saveStatus === 'saved' && 'text-green-400',
              saveStatus === 'error' && 'text-red-400',
            ]"
          >
            <Icon
              :name="saveStatusIcon"
              :class="['w-3.5 h-3.5', saveStatus === 'saving' && 'animate-spin']"
            />
            <span>{{ saveStatusText }}</span>
          </div>
        </Transition>

        <!-- Actions -->
        <div class="flex items-center gap-1">
          <!-- Favorite/Star -->
          <TooltipProvider :delay-duration="300">
            <Tooltip>
              <TooltipTrigger as-child>
                <button
                  type="button"
                  :class="actionButtonClasses"
                  :aria-label="isStarred ? 'Remove from favorites' : 'Add to favorites'"
                  @click="$emit('star')"
                >
                  <Icon
                    :name="isStarred ? 'ph:star-fill' : 'ph:star'"
                    :class="['w-5 h-5', isStarred ? 'text-amber-400' : 'text-olympus-text-muted']"
                  />
                </button>
              </TooltipTrigger>
              <TooltipContent side="bottom" :side-offset="4">
                <p class="text-xs">{{ isStarred ? 'Unstar' : 'Star' }}</p>
              </TooltipContent>
            </Tooltip>
          </TooltipProvider>

          <!-- Edit button -->
          <TooltipProvider :delay-duration="300">
            <Tooltip>
              <TooltipTrigger as-child>
                <button
                  type="button"
                  :class="[actionButtonClasses, isEditing && 'bg-olympus-primary/20']"
                  :disabled="isLocked"
                  :aria-label="isEditing ? 'Stop editing' : 'Edit document'"
                  @click="$emit('edit')"
                >
                  <Icon
                    :name="isEditing ? 'ph:pencil-simple-fill' : 'ph:pencil-simple'"
                    :class="[
                      'w-5 h-5',
                      isEditing ? 'text-olympus-primary' : 'text-olympus-text-muted',
                      isLocked && 'opacity-50'
                    ]"
                  />
                </button>
              </TooltipTrigger>
              <TooltipContent side="bottom" :side-offset="4">
                <p class="text-xs">
                  {{ isLocked ? 'Document is locked' : isEditing ? 'Stop editing' : 'Edit' }}
                </p>
              </TooltipContent>
            </Tooltip>
          </TooltipProvider>

          <!-- Share button -->
          <TooltipProvider :delay-duration="300">
            <Tooltip>
              <TooltipTrigger as-child>
                <button
                  type="button"
                  :class="actionButtonClasses"
                  aria-label="Share document"
                  @click="$emit('share')"
                >
                  <Icon name="ph:share" class="w-5 h-5 text-olympus-text-muted" />
                </button>
              </TooltipTrigger>
              <TooltipContent side="bottom" :side-offset="4">
                <p class="text-xs">Share</p>
              </TooltipContent>
            </Tooltip>
          </TooltipProvider>

          <!-- Comments toggle -->
          <TooltipProvider v-if="commentsCount !== undefined" :delay-duration="300">
            <Tooltip>
              <TooltipTrigger as-child>
                <button
                  type="button"
                  :class="[actionButtonClasses, showComments && 'bg-olympus-surface']"
                  aria-label="Toggle comments"
                  @click="$emit('toggle-comments')"
                >
                  <div class="relative">
                    <Icon
                      :name="showComments ? 'ph:chat-circle-fill' : 'ph:chat-circle'"
                      :class="[
                        'w-5 h-5',
                        showComments ? 'text-olympus-primary' : 'text-olympus-text-muted'
                      ]"
                    />
                    <span
                      v-if="commentsCount > 0"
                      class="absolute -top-1 -right-1 min-w-[14px] h-[14px] flex items-center justify-center rounded-full bg-olympus-primary text-[10px] text-white font-medium px-0.5"
                    >
                      {{ commentsCount > 99 ? '99+' : commentsCount }}
                    </span>
                  </div>
                </button>
              </TooltipTrigger>
              <TooltipContent side="bottom" :side-offset="4">
                <p class="text-xs">
                  {{ commentsCount }} comment{{ commentsCount !== 1 ? 's' : '' }}
                </p>
              </TooltipContent>
            </Tooltip>
          </TooltipProvider>

          <!-- More options menu -->
          <DropdownMenuRoot>
            <DropdownMenuTrigger as-child>
              <button
                type="button"
                :class="actionButtonClasses"
                aria-label="More options"
              >
                <Icon name="ph:dots-three" class="w-5 h-5 text-olympus-text-muted" />
              </button>
            </DropdownMenuTrigger>

            <DropdownMenuPortal>
              <DropdownMenuContent
                :side-offset="4"
                align="end"
                class="min-w-[180px] bg-olympus-elevated rounded-lg border border-olympus-border shadow-xl p-1 z-50 animate-in fade-in-0 zoom-in-95"
              >
                <DropdownMenuItem
                  class="flex items-center gap-2 px-3 py-2 text-sm text-olympus-text rounded-md cursor-pointer outline-none hover:bg-olympus-surface focus:bg-olympus-surface transition-colors"
                  @select="$emit('duplicate')"
                >
                  <Icon name="ph:copy" class="w-4 h-4 text-olympus-text-muted" />
                  <span>Duplicate</span>
                </DropdownMenuItem>

                <DropdownMenuItem
                  class="flex items-center gap-2 px-3 py-2 text-sm text-olympus-text rounded-md cursor-pointer outline-none hover:bg-olympus-surface focus:bg-olympus-surface transition-colors"
                  @select="$emit('move')"
                >
                  <Icon name="ph:folder-simple" class="w-4 h-4 text-olympus-text-muted" />
                  <span>Move to...</span>
                </DropdownMenuItem>

                <DropdownMenuItem
                  class="flex items-center gap-2 px-3 py-2 text-sm text-olympus-text rounded-md cursor-pointer outline-none hover:bg-olympus-surface focus:bg-olympus-surface transition-colors"
                  @select="$emit('export')"
                >
                  <Icon name="ph:export" class="w-4 h-4 text-olympus-text-muted" />
                  <span>Export</span>
                </DropdownMenuItem>

                <DropdownMenuItem
                  class="flex items-center gap-2 px-3 py-2 text-sm text-olympus-text rounded-md cursor-pointer outline-none hover:bg-olympus-surface focus:bg-olympus-surface transition-colors"
                  @select="$emit('print')"
                >
                  <Icon name="ph:printer" class="w-4 h-4 text-olympus-text-muted" />
                  <span>Print</span>
                </DropdownMenuItem>

                <DropdownMenuSeparator class="h-px bg-olympus-border my-1" />

                <DropdownMenuItem
                  class="flex items-center gap-2 px-3 py-2 text-sm text-olympus-text rounded-md cursor-pointer outline-none hover:bg-olympus-surface focus:bg-olympus-surface transition-colors"
                  @select="$emit('history')"
                >
                  <Icon name="ph:clock-counter-clockwise" class="w-4 h-4 text-olympus-text-muted" />
                  <span>Version history</span>
                </DropdownMenuItem>

                <DropdownMenuItem
                  class="flex items-center gap-2 px-3 py-2 text-sm text-olympus-text rounded-md cursor-pointer outline-none hover:bg-olympus-surface focus:bg-olympus-surface transition-colors"
                  @select="$emit('info')"
                >
                  <Icon name="ph:info" class="w-4 h-4 text-olympus-text-muted" />
                  <span>Document info</span>
                </DropdownMenuItem>

                <DropdownMenuSeparator class="h-px bg-olympus-border my-1" />

                <DropdownMenuItem
                  :disabled="isLocked"
                  class="flex items-center gap-2 px-3 py-2 text-sm text-red-400 rounded-md cursor-pointer outline-none hover:bg-red-500/10 focus:bg-red-500/10 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                  @select="$emit('delete')"
                >
                  <Icon name="ph:trash" class="w-4 h-4" />
                  <span>Delete</span>
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenuPortal>
          </DropdownMenuRoot>
        </div>
      </div>
    </div>

    <!-- Progress bar (for loading) -->
    <Transition name="progress">
      <div
        v-if="loading"
        class="absolute bottom-0 left-0 right-0 h-0.5 bg-olympus-surface overflow-hidden"
      >
        <div class="h-full bg-olympus-primary animate-progress" />
      </div>
    </Transition>
  </header>
</template>

<script setup lang="ts">
import {
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuPortal,
  DropdownMenuRoot,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from 'reka-ui'
import type { User } from '~/types'

// ============================================================================
// Types
// ============================================================================

type HeaderSize = 'sm' | 'md' | 'lg'
type SaveStatus = 'saving' | 'saved' | 'error' | null
type DocumentType = 'document' | 'markdown' | 'code' | 'spreadsheet' | 'presentation'

interface Breadcrumb {
  id: string
  title: string
}

// ============================================================================
// Size Configuration
// ============================================================================

const sizeConfig: Record<HeaderSize, {
  padding: string
  titleSize: string
  metaSize: string
  iconContainer: string
  iconSize: string
  gap: string
}> = {
  sm: {
    padding: 'px-4 py-3',
    titleSize: 'text-lg',
    metaSize: 'text-xs',
    iconContainer: 'w-8 h-8',
    iconSize: 'w-4 h-4',
    gap: 'gap-2',
  },
  md: {
    padding: 'px-8 py-4',
    titleSize: 'text-2xl',
    metaSize: 'text-sm',
    iconContainer: 'w-10 h-10',
    iconSize: 'w-5 h-5',
    gap: 'gap-3',
  },
  lg: {
    padding: 'px-10 py-6',
    titleSize: 'text-3xl',
    metaSize: 'text-base',
    iconContainer: 'w-12 h-12',
    iconSize: 'w-6 h-6',
    gap: 'gap-4',
  },
}

const documentTypeIcons: Record<DocumentType, string> = {
  document: 'ph:file-text-fill',
  markdown: 'ph:markdown-logo-fill',
  code: 'ph:file-code-fill',
  spreadsheet: 'ph:table-fill',
  presentation: 'ph:presentation-chart-fill',
}

const documentTypeColors: Record<DocumentType, string> = {
  document: 'text-olympus-text-muted bg-olympus-surface',
  markdown: 'text-blue-400 bg-blue-500/10',
  code: 'text-green-400 bg-green-500/10',
  spreadsheet: 'text-emerald-400 bg-emerald-500/10',
  presentation: 'text-orange-400 bg-orange-500/10',
}

// ============================================================================
// Props & Emits
// ============================================================================

const props = withDefaults(defineProps<{
  title: string
  author?: User
  updatedAt?: Date
  createdAt?: Date
  viewers?: User[]
  editors?: User[]
  size?: HeaderSize
  showIcon?: boolean
  showGradient?: boolean
  documentType?: DocumentType
  breadcrumbs?: Breadcrumb[]
  version?: string
  wordCount?: number
  readTime?: number
  tags?: string[]
  isStarred?: boolean
  isLocked?: boolean
  isTemplate?: boolean
  isEditing?: boolean
  showComments?: boolean
  commentsCount?: number
  saveStatus?: SaveStatus
  loading?: boolean
}>(), {
  size: 'md',
  showIcon: true,
  showGradient: false,
  documentType: 'document',
  breadcrumbs: () => [],
  tags: () => [],
  isStarred: false,
  isLocked: false,
  isTemplate: false,
  isEditing: false,
  showComments: false,
  commentsCount: 0,
  saveStatus: null,
  loading: false,
})

defineEmits<{
  edit: []
  share: []
  menu: []
  star: []
  duplicate: []
  move: []
  export: []
  print: []
  history: []
  info: []
  delete: []
  'toggle-comments': []
  'version-click': []
  'author-click': [author: User]
  'tag-click': [tag: string]
  'tags-expand': []
  navigate: [crumb: Breadcrumb]
  'title-change': [title: string]
}>()

// ============================================================================
// Constants
// ============================================================================

const maxDisplayTags = 3
const maxVisibleViewers = 3

// ============================================================================
// State
// ============================================================================

const isEditingTitle = ref(false)
const editedTitle = ref('')
const titleInputRef = ref<HTMLInputElement | null>(null)

// ============================================================================
// Computed - Configuration
// ============================================================================

const config = computed(() => sizeConfig[props.size])

// ============================================================================
// Computed - Styling
// ============================================================================

const headerClasses = computed(() => [
  'sticky top-0 bg-olympus-bg/95 backdrop-blur-sm border-b border-olympus-border shrink-0 z-10 relative overflow-hidden',
  config.value.padding,
])

const titleClasses = computed(() => [
  'font-bold truncate text-olympus-text flex items-center',
  config.value.titleSize,
])

const metadataClasses = computed(() => [
  'flex items-center mt-2 text-olympus-text-muted',
  config.value.metaSize,
  config.value.gap,
])

const iconContainerClasses = computed(() => [
  config.value.iconContainer,
  documentTypeColors[props.documentType],
])

const iconClasses = computed(() => [
  config.value.iconSize,
])

const tagClasses = computed(() => [
  'px-2 py-0.5 rounded-md text-xs bg-olympus-surface text-olympus-text-muted',
  'hover:bg-olympus-elevated hover:text-olympus-text transition-colors',
])

const actionButtonClasses = computed(() => [
  'p-2 rounded-lg hover:bg-olympus-surface transition-colors outline-none',
  'focus-visible:ring-2 focus-visible:ring-olympus-primary/50',
])

// ============================================================================
// Computed - Data
// ============================================================================

const documentIcon = computed(() =>
  documentTypeIcons[props.documentType] || 'ph:file-text-fill'
)

const displayTags = computed(() =>
  props.tags?.slice(0, maxDisplayTags) || []
)

const saveStatusIcon = computed(() => {
  switch (props.saveStatus) {
    case 'saving': return 'ph:spinner'
    case 'saved': return 'ph:check-circle'
    case 'error': return 'ph:warning-circle'
    default: return ''
  }
})

const saveStatusText = computed(() => {
  switch (props.saveStatus) {
    case 'saving': return 'Saving...'
    case 'saved': return 'Saved'
    case 'error': return 'Error saving'
    default: return ''
  }
})

// ============================================================================
// Methods
// ============================================================================

const formatRelativeDate = (date: Date) => {
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
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

const formatFullDate = (date: Date) => {
  return new Date(date).toLocaleDateString('en-US', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const formatNumber = (num: number) => {
  return new Intl.NumberFormat('en-US').format(num)
}

const startTitleEdit = () => {
  if (props.isLocked) return
  isEditingTitle.value = true
  editedTitle.value = props.title
  nextTick(() => {
    titleInputRef.value?.focus()
    titleInputRef.value?.select()
  })
}

const saveTitle = () => {
  if (editedTitle.value.trim() && editedTitle.value !== props.title) {
    // Emit would be handled by parent
  }
  isEditingTitle.value = false
}

const cancelTitleEdit = () => {
  isEditingTitle.value = false
  editedTitle.value = ''
}
</script>

<style scoped>
/* Progress bar animation */
@keyframes progress {
  0% {
    transform: translateX(-100%);
  }
  50% {
    transform: translateX(0%);
  }
  100% {
    transform: translateX(100%);
  }
}

.animate-progress {
  animation: progress 1.5s ease-in-out infinite;
}

/* Breadcrumb transition */
.breadcrumb-enter-active,
.breadcrumb-leave-active {
  transition: all 0.2s ease;
}

.breadcrumb-enter-from,
.breadcrumb-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}

/* Tags transition */
.tags-enter-active,
.tags-leave-active {
  transition: all 0.2s ease;
}

.tags-enter-from,
.tags-leave-to {
  opacity: 0;
  transform: translateY(4px);
}

/* Individual tag transition */
.tag-enter-active,
.tag-leave-active {
  transition: all 0.15s ease;
}

.tag-enter-from,
.tag-leave-to {
  opacity: 0;
  transform: scale(0.9);
}

.tag-move {
  transition: transform 0.2s ease;
}

/* Presence transition */
.presence-enter-active,
.presence-leave-active {
  transition: all 0.2s ease;
}

.presence-enter-from,
.presence-leave-to {
  opacity: 0;
  transform: translateX(8px);
}

/* Editor indicator transition */
.editor-enter-active,
.editor-leave-active {
  transition: all 0.2s ease;
}

.editor-enter-from,
.editor-leave-to {
  opacity: 0;
  transform: scale(0.9);
}

/* Autosave transition */
.autosave-enter-active,
.autosave-leave-active {
  transition: all 0.15s ease;
}

.autosave-enter-from,
.autosave-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}

/* Progress bar transition */
.progress-enter-active,
.progress-leave-active {
  transition: all 0.2s ease;
}

.progress-enter-from,
.progress-leave-to {
  opacity: 0;
}
</style>
