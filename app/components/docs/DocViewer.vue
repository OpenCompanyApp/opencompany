<template>
  <div :class="containerClasses">
    <!-- Loading State -->
    <template v-if="loading">
      <DocViewerSkeleton />
    </template>

    <!-- Content -->
    <template v-else-if="document">
      <!-- Header -->
      <DocsDocViewerHeader
        :title="document.title"
        :author="document.author"
        :updated-at="document.updatedAt"
        :viewers="document.viewers"
        :editors="document.editors"
        :size="size"
        :show-breadcrumb="showBreadcrumb"
        :breadcrumb="breadcrumb"
        :show-version-history="showVersionHistory"
        :version-count="document.versionCount"
        :is-starred="document.isStarred"
        :is-pinned="document.isPinned"
        :is-locked="document.isLocked"
        :read-only="readOnly"
        @edit="handleEdit"
        @share="handleShare"
        @menu="handleMenu"
        @star="handleStar"
        @pin="handlePin"
        @version-history="emit('versionHistory')"
        @breadcrumb-click="emit('breadcrumbClick', $event)"
      />

      <!-- Toolbar (when editing) -->
      <Transition name="slide-down">
        <div v-if="isEditing && showToolbar" :class="toolbarClasses">
          <div class="flex items-center gap-1">
            <!-- Text Formatting -->
            <div class="flex items-center gap-0.5 pr-2 border-r border-olympus-border">
              <TooltipProvider v-for="action in textFormattingActions" :key="action.id" :delay-duration="200">
                <TooltipRoot>
                  <TooltipTrigger as-child>
                    <button
                      type="button"
                      :class="toolbarButtonClasses"
                      @click="emit('format', action.id)"
                    >
                      <Icon :name="action.icon" class="w-4 h-4" />
                    </button>
                  </TooltipTrigger>
                  <TooltipPortal>
                    <TooltipContent :class="tooltipClasses" side="bottom">
                      {{ action.label }}
                      <span v-if="action.shortcut" class="ml-1 text-olympus-text-subtle">{{ action.shortcut }}</span>
                      <TooltipArrow class="fill-olympus-elevated" />
                    </TooltipContent>
                  </TooltipPortal>
                </TooltipRoot>
              </TooltipProvider>
            </div>

            <!-- Heading Dropdown -->
            <DropdownMenuRoot>
              <DropdownMenuTrigger as-child>
                <button type="button" :class="toolbarButtonClasses">
                  <span class="text-xs">Heading</span>
                  <Icon name="ph:caret-down" class="w-3 h-3 ml-1" />
                </button>
              </DropdownMenuTrigger>
              <DropdownMenuPortal>
                <DropdownMenuContent :class="dropdownContentClasses" :side-offset="4">
                  <DropdownMenuItem
                    v-for="heading in headingOptions"
                    :key="heading.value"
                    :class="dropdownItemClasses"
                    @select="emit('format', heading.value)"
                  >
                    <component :is="heading.value" class="text-olympus-text-secondary">
                      {{ heading.label }}
                    </component>
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenuPortal>
            </DropdownMenuRoot>

            <!-- Insert Actions -->
            <div class="flex items-center gap-0.5 pl-2 border-l border-olympus-border">
              <TooltipProvider v-for="action in insertActions" :key="action.id" :delay-duration="200">
                <TooltipRoot>
                  <TooltipTrigger as-child>
                    <button
                      type="button"
                      :class="toolbarButtonClasses"
                      @click="emit('insert', action.id)"
                    >
                      <Icon :name="action.icon" class="w-4 h-4" />
                    </button>
                  </TooltipTrigger>
                  <TooltipPortal>
                    <TooltipContent :class="tooltipClasses" side="bottom">
                      {{ action.label }}
                      <TooltipArrow class="fill-olympus-elevated" />
                    </TooltipContent>
                  </TooltipPortal>
                </TooltipRoot>
              </TooltipProvider>
            </div>
          </div>

          <!-- Right Side Actions -->
          <div class="flex items-center gap-2">
            <span v-if="autoSaving" class="flex items-center gap-1.5 text-xs text-olympus-text-muted">
              <Icon name="ph:spinner" class="w-3.5 h-3.5 animate-spin" />
              Saving...
            </span>
            <span v-else-if="lastSaved" class="text-xs text-olympus-text-subtle">
              Saved {{ formatTimeAgo(lastSaved) }}
            </span>

            <button
              type="button"
              :class="[toolbarButtonClasses, 'px-3']"
              @click="handleCancelEdit"
            >
              Cancel
            </button>
            <button
              type="button"
              :class="[saveButtonClasses]"
              :disabled="!hasChanges || saving"
              @click="handleSave"
            >
              <Icon v-if="saving" name="ph:spinner" class="w-4 h-4 animate-spin mr-1.5" />
              Save
            </button>
          </div>
        </div>
      </Transition>

      <!-- Table of Contents Sidebar -->
      <Transition name="slide-right">
        <aside v-if="showTableOfContents && tableOfContents.length > 0" :class="tocSidebarClasses">
          <div class="sticky top-6">
            <h3 class="text-xs font-semibold text-olympus-text-muted uppercase tracking-wider mb-3">
              On this page
            </h3>
            <nav class="space-y-1">
              <button
                v-for="item in tableOfContents"
                :key="item.id"
                type="button"
                :class="[
                  tocItemClasses,
                  activeSection === item.id && 'text-olympus-primary border-olympus-primary',
                ]"
                :style="{ paddingLeft: `${8 + item.level * 12}px` }"
                @click="scrollToSection(item.id)"
              >
                {{ item.title }}
              </button>
            </nav>
          </div>
        </aside>
      </Transition>

      <!-- Document Content -->
      <DocsDocViewerContent
        :content="document.content"
        :size="size"
        :editable="isEditing"
        :show-line-numbers="showLineNumbers"
        :highlight-syntax="highlightSyntax"
        @content-change="handleContentChange"
        @section-visible="activeSection = $event"
      />

      <!-- Comments Panel -->
      <Transition name="slide-left">
        <aside v-if="showComments && comments.length > 0" :class="commentsSidebarClasses">
          <div class="sticky top-6">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-sm font-semibold text-olympus-text-primary">
                Comments
                <span class="ml-1 text-xs text-olympus-text-muted">({{ comments.length }})</span>
              </h3>
              <button
                type="button"
                class="p-1 rounded hover:bg-olympus-elevated transition-colors"
                @click="showComments = false"
              >
                <Icon name="ph:x" class="w-4 h-4 text-olympus-text-muted" />
              </button>
            </div>
            <div class="space-y-4">
              <div
                v-for="comment in comments"
                :key="comment.id"
                :class="commentClasses"
              >
                <div class="flex items-start gap-2 mb-2">
                  <SharedAgentAvatar :user="comment.author" size="xs" />
                  <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-olympus-text-secondary">{{ comment.author.name }}</p>
                    <p class="text-[10px] text-olympus-text-subtle">{{ formatTimeAgo(comment.createdAt) }}</p>
                  </div>
                </div>
                <p class="text-sm text-olympus-text-secondary">{{ comment.content }}</p>
                <div v-if="comment.replies?.length" class="mt-2 pl-4 border-l border-olympus-border-subtle space-y-2">
                  <div v-for="reply in comment.replies" :key="reply.id" class="text-xs">
                    <span class="font-medium text-olympus-text-secondary">{{ reply.author.name }}</span>
                    <span class="text-olympus-text-muted ml-1">{{ reply.content }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </aside>
      </Transition>

      <!-- Footer Stats -->
      <footer v-if="showFooter" :class="footerClasses">
        <div class="flex items-center gap-4">
          <span v-if="document.wordCount" class="flex items-center gap-1.5 text-xs text-olympus-text-muted">
            <Icon name="ph:text-aa" class="w-3.5 h-3.5" />
            {{ formatNumber(document.wordCount) }} words
          </span>
          <span v-if="document.characterCount" class="flex items-center gap-1.5 text-xs text-olympus-text-muted">
            <Icon name="ph:text-t" class="w-3.5 h-3.5" />
            {{ formatNumber(document.characterCount) }} characters
          </span>
          <span v-if="document.readTime" class="flex items-center gap-1.5 text-xs text-olympus-text-muted">
            <Icon name="ph:book-open" class="w-3.5 h-3.5" />
            {{ document.readTime }} min read
          </span>
        </div>

        <div class="flex items-center gap-2">
          <button
            v-if="!showComments && comments.length > 0"
            type="button"
            class="flex items-center gap-1.5 px-2 py-1 rounded text-xs text-olympus-text-muted hover:text-olympus-text-secondary hover:bg-olympus-elevated transition-colors"
            @click="showComments = true"
          >
            <Icon name="ph:chat-circle" class="w-3.5 h-3.5" />
            {{ comments.length }} comments
          </button>

          <button
            v-if="!showTableOfContents && tableOfContents.length > 0"
            type="button"
            class="flex items-center gap-1.5 px-2 py-1 rounded text-xs text-olympus-text-muted hover:text-olympus-text-secondary hover:bg-olympus-elevated transition-colors"
            @click="showTableOfContents = true"
          >
            <Icon name="ph:list" class="w-3.5 h-3.5" />
            Contents
          </button>
        </div>
      </footer>
    </template>

    <!-- Empty State -->
    <div v-else :class="emptyStateClasses">
      <div class="w-16 h-16 rounded-2xl bg-olympus-elevated/50 flex items-center justify-center mb-4">
        <Icon name="ph:file-dashed" class="w-8 h-8 text-olympus-text-subtle" />
      </div>
      <h3 class="text-base font-medium text-olympus-text-secondary mb-1">No document selected</h3>
      <p class="text-sm text-olympus-text-muted text-center max-w-[250px]">
        Select a document from the list to view its contents
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { h } from 'vue'
import {
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuPortal,
  DropdownMenuRoot,
  DropdownMenuTrigger,
  TooltipArrow,
  TooltipContent,
  TooltipPortal,
  TooltipProvider,
  TooltipRoot,
  TooltipTrigger,
} from 'reka-ui'
import type { Document, User } from '~/types'

type DocViewerSize = 'sm' | 'md' | 'lg'

interface TableOfContentsItem {
  id: string
  title: string
  level: number
}

interface Comment {
  id: string
  author: User
  content: string
  createdAt: Date
  replies?: {
    id: string
    author: User
    content: string
  }[]
}

interface BreadcrumbItem {
  id: string
  title: string
}

interface ExtendedDocument extends Document {
  wordCount?: number
  characterCount?: number
  readTime?: number
  versionCount?: number
  isStarred?: boolean
  isPinned?: boolean
  isLocked?: boolean
}

const props = withDefaults(defineProps<{
  // Core
  document?: ExtendedDocument | null

  // Appearance
  size?: DocViewerSize

  // Display options
  showBreadcrumb?: boolean
  showToolbar?: boolean
  showTableOfContents?: boolean
  showFooter?: boolean
  showVersionHistory?: boolean
  showLineNumbers?: boolean
  highlightSyntax?: boolean

  // Data
  breadcrumb?: BreadcrumbItem[]
  comments?: Comment[]
  tableOfContents?: TableOfContentsItem[]

  // State
  loading?: boolean
  isEditing?: boolean
  hasChanges?: boolean
  saving?: boolean
  autoSaving?: boolean
  lastSaved?: Date
  readOnly?: boolean
}>(), {
  document: null,
  size: 'md',
  showBreadcrumb: true,
  showToolbar: true,
  showTableOfContents: false,
  showFooter: true,
  showVersionHistory: true,
  showLineNumbers: false,
  highlightSyntax: true,
  breadcrumb: () => [],
  comments: () => [],
  tableOfContents: () => [],
  loading: false,
  isEditing: false,
  hasChanges: false,
  saving: false,
  autoSaving: false,
  lastSaved: undefined,
  readOnly: false,
})

const emit = defineEmits<{
  edit: []
  share: []
  menu: []
  star: []
  pin: []
  save: [content: string]
  cancel: []
  format: [action: string]
  insert: [type: string]
  versionHistory: []
  breadcrumbClick: [item: BreadcrumbItem]
  contentChange: [content: string]
}>()

// State
const showComments = ref(false)
const showTableOfContents = ref(props.showTableOfContents)
const activeSection = ref<string | null>(null)

// Watch for prop changes
watch(() => props.showTableOfContents, (val) => {
  showTableOfContents.value = val
})

// Text formatting actions
const textFormattingActions = [
  { id: 'bold', icon: 'ph:text-b', label: 'Bold', shortcut: '⌘B' },
  { id: 'italic', icon: 'ph:text-italic', label: 'Italic', shortcut: '⌘I' },
  { id: 'underline', icon: 'ph:text-underline', label: 'Underline', shortcut: '⌘U' },
  { id: 'strikethrough', icon: 'ph:text-strikethrough', label: 'Strikethrough' },
  { id: 'code', icon: 'ph:code', label: 'Code', shortcut: '⌘E' },
]

// Heading options
const headingOptions = [
  { value: 'h1', label: 'Heading 1' },
  { value: 'h2', label: 'Heading 2' },
  { value: 'h3', label: 'Heading 3' },
  { value: 'p', label: 'Paragraph' },
]

// Insert actions
const insertActions = [
  { id: 'link', icon: 'ph:link', label: 'Insert link' },
  { id: 'image', icon: 'ph:image', label: 'Insert image' },
  { id: 'table', icon: 'ph:table', label: 'Insert table' },
  { id: 'codeblock', icon: 'ph:brackets-curly', label: 'Code block' },
  { id: 'quote', icon: 'ph:quotes', label: 'Blockquote' },
  { id: 'divider', icon: 'ph:minus', label: 'Divider' },
]

// Container classes
const containerClasses = computed(() => [
  'flex-1 bg-olympus-bg flex flex-col h-full overflow-hidden relative',
])

// Toolbar classes
const toolbarClasses = computed(() => [
  'sticky top-0 z-20 flex items-center justify-between px-8 py-2',
  'bg-olympus-surface/95 backdrop-blur-sm border-b border-olympus-border',
])

const toolbarButtonClasses = computed(() => [
  'p-2 rounded-lg text-olympus-text-muted hover:text-olympus-text-secondary',
  'hover:bg-olympus-elevated transition-colors duration-150',
  'outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50',
])

const saveButtonClasses = computed(() => [
  'px-4 py-2 rounded-lg font-medium text-sm',
  'bg-olympus-primary text-white hover:bg-olympus-primary-hover',
  'transition-colors duration-150',
  'disabled:opacity-50 disabled:cursor-not-allowed',
])

// TOC sidebar classes
const tocSidebarClasses = computed(() => [
  'absolute right-0 top-0 bottom-0 w-64 p-6',
  'bg-olympus-bg border-l border-olympus-border overflow-y-auto',
])

const tocItemClasses = computed(() => [
  'block w-full text-left text-sm py-1 pr-2 border-l-2 border-transparent',
  'text-olympus-text-muted hover:text-olympus-text-secondary',
  'transition-colors duration-150',
])

// Comments sidebar classes
const commentsSidebarClasses = computed(() => [
  'absolute right-0 top-0 bottom-0 w-80 p-6',
  'bg-olympus-surface border-l border-olympus-border overflow-y-auto',
])

const commentClasses = computed(() => [
  'p-3 rounded-lg bg-olympus-bg border border-olympus-border-subtle',
])

// Footer classes
const footerClasses = computed(() => [
  'sticky bottom-0 flex items-center justify-between px-8 py-2',
  'bg-olympus-bg/95 backdrop-blur-sm border-t border-olympus-border',
])

// Empty state classes
const emptyStateClasses = computed(() => [
  'flex-1 flex flex-col items-center justify-center',
])

// Dropdown classes
const dropdownContentClasses = computed(() => [
  'z-50 min-w-[160px] bg-olympus-elevated border border-olympus-border rounded-xl',
  'p-1 shadow-xl shadow-black/20',
  'animate-in fade-in-0 zoom-in-95 duration-100',
])

const dropdownItemClasses = computed(() => [
  'flex items-center px-2 py-1.5 text-sm rounded-lg cursor-pointer',
  'text-olympus-text-secondary hover:bg-olympus-hover',
  'transition-colors duration-100 outline-none',
  'data-[highlighted]:bg-olympus-hover',
])

// Tooltip classes
const tooltipClasses = computed(() => [
  'z-50 bg-olympus-elevated border border-olympus-border rounded-lg',
  'px-2 py-1 text-xs shadow-lg',
  'animate-in fade-in-0 zoom-in-95 duration-100',
])

// Helper functions
const formatTimeAgo = (date: Date): string => {
  const seconds = Math.floor((Date.now() - date.getTime()) / 1000)
  if (seconds < 60) return 'just now'
  const minutes = Math.floor(seconds / 60)
  if (minutes < 60) return `${minutes}m ago`
  const hours = Math.floor(minutes / 60)
  if (hours < 24) return `${hours}h ago`
  const days = Math.floor(hours / 24)
  return `${days}d ago`
}

const formatNumber = (num: number): string => {
  if (num < 1000) return num.toString()
  return `${(num / 1000).toFixed(1)}K`
}

const scrollToSection = (id: string) => {
  const element = document.getElementById(id)
  if (element) {
    element.scrollIntoView({ behavior: 'smooth', block: 'start' })
  }
}

// Handlers
const handleEdit = () => {
  emit('edit')
}

const handleShare = () => {
  emit('share')
}

const handleMenu = () => {
  emit('menu')
}

const handleStar = () => {
  emit('star')
}

const handlePin = () => {
  emit('pin')
}

const handleSave = () => {
  emit('save', props.document?.content || '')
}

const handleCancelEdit = () => {
  emit('cancel')
}

const handleContentChange = (content: string) => {
  emit('contentChange', content)
}

// Skeleton Component
const DocViewerSkeleton = defineComponent({
  name: 'DocViewerSkeleton',
  setup() {
    return () => h('div', { class: 'flex-1 animate-pulse' }, [
      // Header skeleton
      h('div', { class: 'border-b border-olympus-border px-8 py-4' }, [
        h('div', { class: 'flex items-center justify-between' }, [
          h('div', { class: 'space-y-2' }, [
            h(resolveComponent('SharedSkeleton'), { customClass: 'h-8 w-64' }),
            h('div', { class: 'flex items-center gap-3' }, [
              h(resolveComponent('SharedSkeleton'), { variant: 'avatar', customClass: 'w-6 h-6' }),
              h(resolveComponent('SharedSkeleton'), { customClass: 'h-4 w-24' }),
              h(resolveComponent('SharedSkeleton'), { customClass: 'h-4 w-32' }),
            ]),
          ]),
          h('div', { class: 'flex items-center gap-2' }, [
            h(resolveComponent('SharedSkeleton'), { customClass: 'h-10 w-10 rounded-lg' }),
            h(resolveComponent('SharedSkeleton'), { customClass: 'h-10 w-10 rounded-lg' }),
            h(resolveComponent('SharedSkeleton'), { customClass: 'h-10 w-10 rounded-lg' }),
          ]),
        ]),
      ]),
      // Content skeleton
      h('div', { class: 'px-8 py-6 max-w-3xl mx-auto space-y-6' }, [
        h(resolveComponent('SharedSkeleton'), { customClass: 'h-10 w-3/4' }),
        h('div', { class: 'space-y-3' }, [
          h(resolveComponent('SharedSkeleton'), { customClass: 'h-4 w-full' }),
          h(resolveComponent('SharedSkeleton'), { customClass: 'h-4 w-full' }),
          h(resolveComponent('SharedSkeleton'), { customClass: 'h-4 w-5/6' }),
        ]),
        h(resolveComponent('SharedSkeleton'), { customClass: 'h-8 w-1/2' }),
        h('div', { class: 'space-y-3' }, [
          h(resolveComponent('SharedSkeleton'), { customClass: 'h-4 w-full' }),
          h(resolveComponent('SharedSkeleton'), { customClass: 'h-4 w-4/5' }),
          h(resolveComponent('SharedSkeleton'), { customClass: 'h-4 w-full' }),
          h(resolveComponent('SharedSkeleton'), { customClass: 'h-4 w-3/4' }),
        ]),
        h(resolveComponent('SharedSkeleton'), { customClass: 'h-40 w-full rounded-xl' }),
      ]),
    ])
  },
})
</script>

<style scoped>
/* Slide transitions */
.slide-down-enter-active,
.slide-down-leave-active {
  transition: all 0.2s ease;
}

.slide-down-enter-from,
.slide-down-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}

.slide-right-enter-active,
.slide-right-leave-active {
  transition: all 0.3s ease;
}

.slide-right-enter-from,
.slide-right-leave-to {
  opacity: 0;
  transform: translateX(20px);
}

.slide-left-enter-active,
.slide-left-leave-active {
  transition: all 0.3s ease;
}

.slide-left-enter-from,
.slide-left-leave-to {
  opacity: 0;
  transform: translateX(20px);
}
</style>
