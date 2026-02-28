<template>
  <div :class="containerClasses">
    <!-- Loading State -->
    <template v-if="loading">
      <DocViewerSkeleton />
    </template>

    <!-- Content -->
    <template v-else-if="document">
      <!-- Header -->
      <DocViewerHeader
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
        :custom-color="document.color"
        :custom-icon="document.icon"
        :read-only="readOnly"
        :save-status="saveStatus"
        @share="handleShare"
        @menu="handleMenu"
        @star="handleStar"
        @pin="handlePin"
        @update:color="(color) => emit('update:color', color)"
        @update:icon="(icon) => emit('update:icon', icon)"
        @version-history="emit('versionHistory')"
        @breadcrumb-click="emit('breadcrumbClick', $event)"
      />

      <!-- TipTap Editor (always mounted) -->
      <div class="flex-1 overflow-auto">
        <div class="max-w-3xl mx-auto px-8 py-6">
          <TipTapEditor
            :content="document.content ?? ''"
            :content-format="document.contentFormat ?? 'markdown'"
            :editable="!readOnly && !document.isLocked"
            @update="handleEditorUpdate"
          />
        </div>
      </div>

      <!-- Comments Panel -->
      <Transition name="slide-left">
        <aside v-if="showComments && comments.length > 0" :class="commentsSidebarClasses">
          <div class="sticky top-6">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-sm font-semibold text-neutral-900 dark:text-white">
                Comments
                <span class="ml-1 text-xs text-neutral-500">({{ comments.length }})</span>
              </h3>
              <button
                type="button"
                class="p-1 rounded hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors"
                @click="showComments = false"
              >
                <Icon name="ph:x" class="w-4 h-4 text-neutral-500" />
              </button>
            </div>
            <div class="space-y-4">
              <div
                v-for="comment in comments"
                :key="comment.id"
                :class="commentClasses"
              >
                <div class="flex items-start gap-2 mb-2">
                  <AgentAvatar :user="comment.author" size="xs" />
                  <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-neutral-700 dark:text-neutral-200">{{ comment.author.name }}</p>
                    <p class="text-[10px] text-neutral-400">{{ formatTimeAgo(comment.createdAt) }}</p>
                  </div>
                </div>
                <p class="text-sm text-neutral-700 dark:text-neutral-300">{{ comment.content }}</p>
                <div v-if="comment.replies?.length" class="mt-2 pl-4 border-l border-neutral-100 dark:border-neutral-700 space-y-2">
                  <div v-for="reply in comment.replies" :key="reply.id" class="text-xs">
                    <span class="font-medium text-neutral-700 dark:text-neutral-200">{{ reply.author.name }}</span>
                    <span class="text-neutral-500 dark:text-neutral-400 ml-1">{{ reply.content }}</span>
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
          <span v-if="document.wordCount" class="flex items-center gap-1.5 text-xs text-neutral-500">
            <Icon name="ph:text-aa" class="w-3.5 h-3.5" />
            {{ formatNumber(document.wordCount) }} words
          </span>
          <span v-if="document.characterCount" class="flex items-center gap-1.5 text-xs text-neutral-500">
            <Icon name="ph:text-t" class="w-3.5 h-3.5" />
            {{ formatNumber(document.characterCount) }} characters
          </span>
          <span v-if="document.readTime" class="flex items-center gap-1.5 text-xs text-neutral-500">
            <Icon name="ph:book-open" class="w-3.5 h-3.5" />
            {{ document.readTime }} min read
          </span>
        </div>

        <div class="flex items-center gap-2">
          <button
            v-if="!showComments && comments.length > 0"
            type="button"
            class="flex items-center gap-1.5 px-2 py-1 rounded text-xs text-neutral-500 hover:text-neutral-700 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors"
            @click="showComments = true"
          >
            <Icon name="ph:chat-circle" class="w-3.5 h-3.5" />
            {{ comments.length }} comments
          </button>
        </div>
      </footer>
    </template>

    <!-- Empty State -->
    <div v-else :class="emptyStateClasses">
      <div class="w-16 h-16 rounded-2xl bg-neutral-100 dark:bg-neutral-800 flex items-center justify-center mb-4">
        <Icon name="ph:file-dashed" class="w-8 h-8 text-neutral-400" />
      </div>
      <h3 class="text-base font-medium text-neutral-700 dark:text-neutral-200 mb-1">No document selected</h3>
      <p class="text-sm text-neutral-500 dark:text-neutral-400 text-center max-w-[250px]">
        Select a document from the list to view its contents
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { h, defineComponent, ref, computed } from 'vue'
import type { Document, User } from '@/types'
import Icon from '@/Components/shared/Icon.vue'
import Skeleton from '@/Components/shared/Skeleton.vue'
import AgentAvatar from '@/Components/shared/AgentAvatar.vue'
import DocViewerHeader from './doc-viewer/DocViewerHeader.vue'
import TipTapEditor from './TipTapEditor.vue'

type DocViewerSize = 'sm' | 'md' | 'lg'
type SaveStatus = 'idle' | 'saving' | 'saved' | 'error'

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
  document?: ExtendedDocument | null
  size?: DocViewerSize
  showBreadcrumb?: boolean
  showFooter?: boolean
  showVersionHistory?: boolean
  breadcrumb?: BreadcrumbItem[]
  comments?: Comment[]
  loading?: boolean
  readOnly?: boolean
  saveStatus?: SaveStatus
}>(), {
  document: null,
  size: 'md',
  showBreadcrumb: true,
  showFooter: true,
  showVersionHistory: true,
  breadcrumb: () => [],
  comments: () => [],
  loading: false,
  readOnly: false,
  saveStatus: 'idle',
})

const emit = defineEmits<{
  share: []
  menu: []
  star: []
  pin: []
  contentChange: [content: string]
  versionHistory: []
  breadcrumbClick: [item: BreadcrumbItem]
  'update:color': [color: string | null]
  'update:icon': [icon: string | null]
}>()

// State
const showComments = ref(false)

// Handle editor content updates
const handleEditorUpdate = (html: string) => {
  emit('contentChange', html)
}

// Container classes
const containerClasses = computed(() => [
  'flex-1 bg-white dark:bg-neutral-900 flex flex-col h-full overflow-hidden relative',
])

// Comments sidebar classes
const commentsSidebarClasses = computed(() => [
  'absolute right-0 top-0 bottom-0 w-80 p-6',
  'bg-white dark:bg-neutral-900 border-l border-neutral-200 dark:border-neutral-700 overflow-y-auto',
])

const commentClasses = computed(() => [
  'p-3 rounded-lg bg-neutral-50 dark:bg-neutral-800 border border-neutral-100 dark:border-neutral-700',
])

// Footer classes
const footerClasses = computed(() => [
  'sticky bottom-0 flex items-center justify-between px-8 py-2',
  'bg-white dark:bg-neutral-900 border-t border-neutral-200 dark:border-neutral-700',
])

// Empty state classes
const emptyStateClasses = computed(() => [
  'flex-1 flex flex-col items-center justify-center',
])

// Handlers
const handleShare = () => emit('share')
const handleMenu = () => emit('menu')
const handleStar = () => emit('star')
const handlePin = () => emit('pin')

// Helper functions
const formatTimeAgo = (date: Date): string => {
  const seconds = Math.floor((Date.now() - new Date(date).getTime()) / 1000)
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

// Skeleton Component
const DocViewerSkeleton = defineComponent({
  name: 'DocViewerSkeleton',
  setup() {
    return () => h('div', { class: 'flex-1 animate-pulse' }, [
      h('div', { class: 'border-b border-neutral-200 dark:border-neutral-700 px-8 py-4' }, [
        h('div', { class: 'flex items-center justify-between' }, [
          h('div', { class: 'space-y-2' }, [
            h(Skeleton, { customClass: 'h-8 w-64' }),
            h('div', { class: 'flex items-center gap-3' }, [
              h(Skeleton, { variant: 'avatar', customClass: 'w-6 h-6' }),
              h(Skeleton, { customClass: 'h-4 w-24' }),
              h(Skeleton, { customClass: 'h-4 w-32' }),
            ]),
          ]),
          h('div', { class: 'flex items-center gap-2' }, [
            h(Skeleton, { customClass: 'h-10 w-10 rounded-lg' }),
            h(Skeleton, { customClass: 'h-10 w-10 rounded-lg' }),
          ]),
        ]),
      ]),
      h('div', { class: 'px-8 py-6 max-w-3xl mx-auto space-y-6' }, [
        h(Skeleton, { customClass: 'h-10 w-3/4' }),
        h('div', { class: 'space-y-3' }, [
          h(Skeleton, { customClass: 'h-4 w-full' }),
          h(Skeleton, { customClass: 'h-4 w-full' }),
          h(Skeleton, { customClass: 'h-4 w-5/6' }),
        ]),
        h(Skeleton, { customClass: 'h-8 w-1/2' }),
        h('div', { class: 'space-y-3' }, [
          h(Skeleton, { customClass: 'h-4 w-full' }),
          h(Skeleton, { customClass: 'h-4 w-4/5' }),
          h(Skeleton, { customClass: 'h-4 w-full' }),
          h(Skeleton, { customClass: 'h-4 w-3/4' }),
        ]),
        h(Skeleton, { customClass: 'h-40 w-full rounded-xl' }),
      ]),
    ])
  },
})
</script>

<style scoped>
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
