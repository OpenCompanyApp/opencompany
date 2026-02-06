<template>
  <header :class="headerClasses">
    <!-- Background decoration (subtle) -->
    <div
      v-if="showGradient"
      class="absolute inset-0 bg-neutral-50/50 dark:bg-neutral-800/50 pointer-events-none"
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
                class="text-neutral-500 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white truncate max-w-[120px] px-1 py-0.5 -mx-1 rounded transition-colors duration-150 hover:bg-neutral-100 dark:hover:bg-neutral-800"
                @click="$emit('navigate', crumb)"
              >
                {{ crumb.title }}
              </button>
              <span v-else class="text-neutral-400 dark:text-neutral-500 truncate max-w-[120px]">
                {{ crumb.title }}
              </span>

              <Icon
                v-if="index < breadcrumbs.length - 1"
                name="ph:caret-right"
                class="w-3 h-3 text-neutral-400 dark:text-neutral-500 shrink-0"
              />
            </template>
          </nav>
        </Transition>

        <!-- Title row -->
        <div class="flex items-center gap-3">
          <!-- Document type icon (clickable for color/icon picker) -->
          <div v-if="showIcon" class="relative">
            <button
              type="button"
              :class="[
                'shrink-0 flex items-center justify-center rounded-lg',
                'transition-colors duration-150 hover:ring-2 hover:ring-neutral-300 dark:hover:ring-neutral-600',
                iconContainerClasses,
              ]"
              @click="showIconPicker = !showIconPicker"
            >
              <Icon
                :name="effectiveIcon"
                :class="['transition-colors duration-150', effectiveIconClasses]"
              />
            </button>

            <!-- Icon/Color Picker Popover -->
            <Transition name="picker">
              <div
                v-if="showIconPicker"
                class="absolute top-full left-0 mt-2 z-50 bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-xl shadow-lg p-3 w-[240px]"
              >
                <!-- Color Palette -->
                <div class="mb-3">
                  <p class="text-[10px] font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider mb-2">Color</p>
                  <div class="flex items-center gap-1.5">
                    <button
                      v-for="c in colorOptions"
                      :key="c.name"
                      type="button"
                      :class="[
                        'w-6 h-6 rounded-full transition-transform',
                        c.bg,
                        customColor === c.name ? 'ring-2 ring-offset-2 ring-offset-white dark:ring-offset-neutral-800 ring-neutral-900 dark:ring-white scale-110' : 'hover:scale-110'
                      ]"
                      @click="handleColorSelect(c.name)"
                    />
                  </div>
                </div>

                <!-- Icon Grid -->
                <div>
                  <p class="text-[10px] font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider mb-2">Icon</p>
                  <div class="grid grid-cols-7 gap-1">
                    <button
                      v-for="ic in docIconOptions"
                      :key="ic"
                      type="button"
                      :class="[
                        'p-1.5 rounded-lg transition-colors',
                        customIcon === ic
                          ? 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900'
                          : 'text-neutral-500 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-700'
                      ]"
                      @click="handleIconSelect(ic)"
                    >
                      <Icon :name="ic" class="w-4 h-4" />
                    </button>
                  </div>
                </div>

                <!-- Reset -->
                <button
                  v-if="customColor || customIcon"
                  type="button"
                  class="mt-2 w-full text-xs text-neutral-500 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white py-1.5 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors"
                  @click="handleResetAppearance"
                >
                  Reset to default
                </button>
              </div>
            </Transition>
          </div>

          <!-- Click-outside overlay to close picker -->
          <div
            v-if="showIconPicker"
            class="fixed inset-0 z-40"
            @click="showIconPicker = false"
          />

          <!-- Title with edit mode -->
          <div class="flex-1 min-w-0">
            <div v-if="isEditingTitle" class="flex items-center gap-2">
              <input
                ref="titleInputRef"
                v-model="editedTitle"
                type="text"
                class="flex-1 bg-transparent border-b-2 border-neutral-900 dark:border-white text-2xl font-bold text-neutral-900 dark:text-white outline-none py-1"
                :placeholder="title"
                @keydown.enter="saveTitle"
                @keydown.escape="cancelTitleEdit"
                @blur="saveTitle"
              />
              <div class="flex items-center gap-1">
                <button
                  type="button"
                  class="p-1.5 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors duration-150"
                  aria-label="Save title"
                  @click="saveTitle"
                >
                  <Icon name="ph:check" class="w-4 h-4 text-neutral-600 dark:text-neutral-300" />
                </button>
                <button
                  type="button"
                  class="p-1.5 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors duration-150"
                  aria-label="Cancel edit"
                  @click="cancelTitleEdit"
                >
                  <Icon name="ph:x" class="w-4 h-4 text-neutral-500 dark:text-neutral-400" />
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
                class="ml-2 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-300 border border-neutral-200 dark:border-neutral-700"
              >
                <Icon name="ph:lock-fill" class="w-3 h-3" />
                Locked
              </span>
              <span
                v-if="isTemplate"
                class="ml-2 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-300 border border-neutral-200 dark:border-neutral-700"
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
          <div v-if="author" class="flex items-center">
            <Tooltip :delay-open="300">
              <button
                type="button"
                class="flex items-center gap-2 hover:bg-neutral-100 rounded-lg px-1.5 py-0.5 -ml-1.5 transition-colors duration-150"
                @click="$emit('author-click', author)"
              >
                <AgentAvatar :user="author" size="xs" :show-status="false" />
                <span>{{ author.name }}</span>
              </button>
              <template #content>
                <div class="flex items-center gap-3 p-3">
                  <AgentAvatar :user="author" size="md" :show-status="true" />
                  <div>
                    <p class="font-medium text-neutral-900">{{ author.name }}</p>
                    <p v-if="author.role" class="text-xs text-neutral-500">
                      {{ author.role }}
                    </p>
                  </div>
                </div>
              </template>
            </Tooltip>
          </div>

          <!-- Version indicator -->
          <template v-if="version">
            <span class="text-neutral-300 dark:text-neutral-600">&middot;</span>
            <button
              type="button"
              class="flex items-center gap-1 text-neutral-500 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white px-1.5 py-0.5 -mx-1 rounded-md transition-colors duration-150 hover:bg-neutral-100 dark:hover:bg-neutral-800"
              @click="$emit('version-click')"
            >
              <Icon name="ph:git-branch" class="w-3.5 h-3.5" />
              <span>v{{ version }}</span>
            </button>
          </template>

          <!-- Word count -->
          <template v-if="wordCount !== undefined">
            <span class="text-neutral-300 dark:text-neutral-600">&middot;</span>
            <span class="text-neutral-400 dark:text-neutral-500">
              {{ formatNumber(wordCount) }} words
            </span>
          </template>

          <!-- Read time -->
          <template v-if="readTime">
            <span class="text-neutral-300 dark:text-neutral-600">&middot;</span>
            <span class="text-neutral-400 dark:text-neutral-500">
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
              class="text-xs text-neutral-500 hover:text-neutral-900 px-1.5 py-0.5 rounded-md transition-colors duration-150 hover:bg-neutral-100"
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
            <PresenceRow
              :users="viewers"
              :max-visible="maxVisibleViewers"
              :show-tooltip="true"
            />
            <span class="text-xs text-neutral-500">viewing</span>
          </div>
        </Transition>

        <!-- Editors Indicator -->
        <Transition name="editor">
          <div v-if="editors && editors.length > 0">
            <Tooltip :delay-open="300">
              <div class="flex items-center gap-2 px-3 py-1.5 bg-neutral-100 border border-neutral-200 rounded-full cursor-help">
                <Icon name="ph:pencil-simple" class="w-4 h-4 text-neutral-600" />
                <span class="text-xs text-neutral-700 font-medium">
                  {{ editors.length === 1 ? editors[0].name : `${editors.length} people` }} editing
                </span>
              </div>
              <template #content>
                <div class="p-3">
                  <p class="text-xs text-neutral-500 mb-2">Currently editing:</p>
                  <div class="space-y-2">
                    <div
                      v-for="editor in editors"
                      :key="editor.id"
                      class="flex items-center gap-2 p-1.5 -mx-1.5 rounded-lg transition-colors duration-150 hover:bg-neutral-50"
                    >
                      <AgentAvatar :user="editor" size="xs" :show-status="false" />
                      <span class="text-sm text-neutral-900">{{ editor.name }}</span>
                    </div>
                  </div>
                </div>
              </template>
            </Tooltip>
          </div>
        </Transition>

        <!-- Auto-save indicator -->
        <Transition name="autosave" mode="out-in">
          <div
            v-if="saveStatus"
            :class="[
              'flex items-center gap-1.5 text-xs',
              saveStatus === 'saving' && 'text-neutral-500',
              saveStatus === 'saved' && 'text-neutral-600',
              saveStatus === 'error' && 'text-neutral-700',
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
          <Tooltip :text="isStarred ? 'Unstar' : 'Star'" :delay-open="300">
            <button
              type="button"
              :class="[
                actionButtonClasses,
                isStarred && 'bg-neutral-100',
              ]"
              :aria-label="isStarred ? 'Remove from favorites' : 'Add to favorites'"
              @click="$emit('star')"
            >
              <Icon
                :name="isStarred ? 'ph:star-fill' : 'ph:star'"
                :class="[
                  'w-5 h-5 transition-colors duration-150',
                  isStarred ? 'text-neutral-700' : 'text-neutral-500 hover:text-neutral-700',
                ]"
              />
            </button>
          </Tooltip>

          <!-- Edit button -->
          <Tooltip :text="isLocked ? 'Document is locked' : isEditing ? 'Stop editing' : 'Edit'" :delay-open="300">
            <button
              type="button"
              :class="[actionButtonClasses, isEditing && 'bg-neutral-100']"
              :disabled="isLocked"
              :aria-label="isEditing ? 'Stop editing' : 'Edit document'"
              @click="$emit('edit')"
            >
              <Icon
                :name="isEditing ? 'ph:pencil-simple-fill' : 'ph:pencil-simple'"
                :class="[
                  'w-5 h-5',
                  isEditing ? 'text-neutral-900' : 'text-neutral-500',
                  isLocked && 'opacity-50'
                ]"
              />
            </button>
          </Tooltip>

          <!-- Share button -->
          <Tooltip text="Share" :delay-open="300">
            <button
              type="button"
              :class="actionButtonClasses"
              aria-label="Share document"
              @click="$emit('share')"
            >
              <Icon name="ph:share" class="w-5 h-5 text-neutral-500" />
            </button>
          </Tooltip>

          <!-- Comments toggle -->
          <Tooltip v-if="commentsCount !== undefined" :text="`${commentsCount} comment${commentsCount !== 1 ? 's' : ''}`" :delay-open="300">
            <button
              type="button"
              :class="[actionButtonClasses, showComments && 'bg-neutral-100']"
              aria-label="Toggle comments"
              @click="$emit('toggle-comments')"
            >
              <div class="relative">
                <Icon
                  :name="showComments ? 'ph:chat-circle-fill' : 'ph:chat-circle'"
                  :class="[
                    'w-5 h-5',
                    showComments ? 'text-neutral-900' : 'text-neutral-500'
                  ]"
                />
                <span
                  v-if="commentsCount > 0"
                  class="absolute -top-1 -right-1 min-w-[14px] h-[14px] flex items-center justify-center rounded-full bg-neutral-900 text-[10px] text-white font-medium px-0.5"
                >
                  {{ commentsCount > 99 ? '99+' : commentsCount }}
                </span>
              </div>
            </button>
          </Tooltip>

          <!-- More options menu -->
          <DropdownMenu :items="moreOptionsDropdown">
            <Button
              variant="ghost"
              :class="actionButtonClasses"
              aria-label="More options"
              icon="ph:dots-three"
            />
          </DropdownMenu>
        </div>
      </div>
    </div>

    <!-- Progress bar (for loading) -->
    <Transition name="progress">
      <div
        v-if="loading"
        class="absolute bottom-0 left-0 right-0 h-0.5 bg-neutral-100 overflow-hidden"
      >
        <div class="h-full bg-neutral-900 animate-progress" />
      </div>
    </Transition>
  </header>
</template>

<script setup lang="ts">
import { ref, computed, nextTick } from 'vue'
import type { User } from '@/types'
import Icon from '@/Components/shared/Icon.vue'
import Button from '@/Components/shared/Button.vue'
import Tooltip from '@/Components/shared/Tooltip.vue'
import DropdownMenu from '@/Components/shared/DropdownMenu.vue'
import AgentAvatar from '@/Components/shared/AgentAvatar.vue'
import PresenceRow from '@/Components/shared/PresenceRow.vue'

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
  document: 'text-neutral-500 dark:text-neutral-400 bg-neutral-100 dark:bg-neutral-800',
  markdown: 'text-neutral-500 dark:text-neutral-400 bg-neutral-100 dark:bg-neutral-800',
  code: 'text-neutral-500 dark:text-neutral-400 bg-neutral-100 dark:bg-neutral-800',
  spreadsheet: 'text-neutral-500 dark:text-neutral-400 bg-neutral-100 dark:bg-neutral-800',
  presentation: 'text-neutral-500 dark:text-neutral-400 bg-neutral-100 dark:bg-neutral-800',
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
  customColor?: string | null
  customIcon?: string | null
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
  customColor: null,
  customIcon: null,
  isStarred: false,
  isLocked: false,
  isTemplate: false,
  isEditing: false,
  showComments: false,
  commentsCount: 0,
  saveStatus: null,
  loading: false,
})

const emit = defineEmits<{
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
  'update:color': [color: string | null]
  'update:icon': [icon: string | null]
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
const showIconPicker = ref(false)

// Color and icon options
const colorOptions = [
  { name: 'neutral', bg: 'bg-neutral-400' },
  { name: 'blue', bg: 'bg-blue-500' },
  { name: 'green', bg: 'bg-green-500' },
  { name: 'yellow', bg: 'bg-yellow-500' },
  { name: 'orange', bg: 'bg-orange-500' },
  { name: 'red', bg: 'bg-red-500' },
  { name: 'purple', bg: 'bg-purple-500' },
  { name: 'pink', bg: 'bg-pink-500' },
]

const docIconOptions = [
  'ph:file-text', 'ph:notebook', 'ph:book-open', 'ph:article',
  'ph:clipboard-text', 'ph:note', 'ph:scroll',
  'ph:lightning', 'ph:star', 'ph:heart', 'ph:flag',
  'ph:rocket', 'ph:gear', 'ph:code',
]

const colorIconMap: Record<string, string> = {
  neutral: 'text-neutral-500 dark:text-neutral-400',
  blue: 'text-blue-500 dark:text-blue-400',
  green: 'text-green-500 dark:text-green-400',
  yellow: 'text-yellow-600 dark:text-yellow-400',
  orange: 'text-orange-500 dark:text-orange-400',
  red: 'text-red-500 dark:text-red-400',
  purple: 'text-purple-500 dark:text-purple-400',
  pink: 'text-pink-500 dark:text-pink-400',
}

const colorBgMap: Record<string, string> = {
  neutral: 'text-neutral-500 dark:text-neutral-400 bg-neutral-100 dark:bg-neutral-800',
  blue: 'text-blue-500 dark:text-blue-400 bg-blue-50 dark:bg-blue-950',
  green: 'text-green-500 dark:text-green-400 bg-green-50 dark:bg-green-950',
  yellow: 'text-yellow-600 dark:text-yellow-400 bg-yellow-50 dark:bg-yellow-950',
  orange: 'text-orange-500 dark:text-orange-400 bg-orange-50 dark:bg-orange-950',
  red: 'text-red-500 dark:text-red-400 bg-red-50 dark:bg-red-950',
  purple: 'text-purple-500 dark:text-purple-400 bg-purple-50 dark:bg-purple-950',
  pink: 'text-pink-500 dark:text-pink-400 bg-pink-50 dark:bg-pink-950',
}

// ============================================================================
// Computed - Configuration
// ============================================================================

const config = computed(() => sizeConfig[props.size])

// ============================================================================
// Computed - Styling
// ============================================================================

const headerClasses = computed(() => [
  'sticky top-0 bg-white dark:bg-neutral-900 border-b border-neutral-200 dark:border-neutral-700 shrink-0 z-10 relative overflow-hidden',
  config.value.padding,
])

const titleClasses = computed(() => [
  'font-bold truncate text-neutral-900 dark:text-white flex items-center',
  config.value.titleSize,
])

const metadataClasses = computed(() => [
  'flex items-center mt-2 text-neutral-500 dark:text-neutral-400',
  config.value.metaSize,
  config.value.gap,
])

const iconContainerClasses = computed(() => [
  config.value.iconContainer,
  props.customColor && colorBgMap[props.customColor]
    ? colorBgMap[props.customColor]
    : documentTypeColors[props.documentType],
])

const iconClasses = computed(() => [
  config.value.iconSize,
])

const effectiveIcon = computed(() => {
  if (props.customIcon) return props.customIcon
  return documentTypeIcons[props.documentType] || 'ph:file-text-fill'
})

const effectiveIconClasses = computed(() => {
  if (props.customColor && colorIconMap[props.customColor]) {
    return [config.value.iconSize, colorIconMap[props.customColor]]
  }
  return [config.value.iconSize]
})

const tagClasses = computed(() => [
  'px-2 py-0.5 rounded-md text-xs bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-300',
  'hover:bg-neutral-200 dark:hover:bg-neutral-700 hover:text-neutral-900 dark:hover:text-white',
  'transition-colors duration-150',
])

const actionButtonClasses = computed(() => [
  'p-2 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-800 outline-none',
  'transition-colors duration-150',
  'focus-visible:ring-2 focus-visible:ring-neutral-900/20 dark:focus-visible:ring-neutral-500/30',
])

// ============================================================================
// Computed - Data
// ============================================================================

const documentIcon = computed(() =>
  documentTypeIcons[props.documentType] || 'ph:file-text-fill'
)

// No glow effect for clean minimal design

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

const moreOptionsDropdown = computed(() => [
  [
    { label: 'Duplicate', icon: 'ph:copy', click: () => emit('duplicate') },
    { label: 'Move to...', icon: 'ph:folder-simple', click: () => emit('move') },
    { label: 'Export', icon: 'ph:export', click: () => emit('export') },
    { label: 'Print', icon: 'ph:printer', click: () => emit('print') },
  ],
  [
    { label: 'Version history', icon: 'ph:clock-counter-clockwise', click: () => emit('history') },
    { label: 'Document info', icon: 'ph:info', click: () => emit('info') },
  ],
  [
    { label: 'Delete', icon: 'ph:trash', color: 'error' as const, disabled: props.isLocked, click: () => emit('delete') },
  ],
])

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

const handleColorSelect = (color: string) => {
  emit('update:color', color === props.customColor ? null : color)
}

const handleIconSelect = (icon: string) => {
  emit('update:icon', icon === props.customIcon ? null : icon)
}

const handleResetAppearance = () => {
  emit('update:color', null)
  emit('update:icon', null)
  showIconPicker.value = false
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
.breadcrumb-enter-active {
  transition: opacity 0.15s ease-out;
}

.breadcrumb-leave-active {
  transition: opacity 0.1s ease-out;
}

.breadcrumb-enter-from,
.breadcrumb-leave-to {
  opacity: 0;
}

/* Tags transition */
.tags-enter-active {
  transition: opacity 0.15s ease-out;
}

.tags-leave-active {
  transition: opacity 0.1s ease-out;
}

.tags-enter-from,
.tags-leave-to {
  opacity: 0;
}

/* Individual tag transition */
.tag-enter-active {
  transition: opacity 0.15s ease-out;
}

.tag-leave-active {
  transition: opacity 0.1s ease-out;
}

.tag-enter-from,
.tag-leave-to {
  opacity: 0;
}

.tag-move {
  transition: transform 0.15s ease-out;
}

/* Presence transition */
.presence-enter-active {
  transition: opacity 0.15s ease-out;
}

.presence-leave-active {
  transition: opacity 0.1s ease-out;
}

.presence-enter-from,
.presence-leave-to {
  opacity: 0;
}

/* Editor indicator transition */
.editor-enter-active {
  transition: opacity 0.15s ease-out;
}

.editor-leave-active {
  transition: opacity 0.1s ease-out;
}

.editor-enter-from,
.editor-leave-to {
  opacity: 0;
}

/* Autosave transition */
.autosave-enter-active {
  transition: opacity 0.15s ease-out;
}

.autosave-leave-active {
  transition: opacity 0.1s ease-out;
}

.autosave-enter-from,
.autosave-leave-to {
  opacity: 0;
}

/* Progress bar transition */
.progress-enter-active {
  transition: opacity 0.15s ease-out;
}

.progress-leave-active {
  transition: opacity 0.1s ease-out;
}

.progress-enter-from,
.progress-leave-to {
  opacity: 0;
}

/* Picker popover */
.picker-enter-active {
  transition: all 0.15s ease-out;
}

.picker-leave-active {
  transition: all 0.1s ease-in;
}

.picker-enter-from,
.picker-leave-to {
  opacity: 0;
  transform: translateY(-4px) scale(0.95);
}
</style>
