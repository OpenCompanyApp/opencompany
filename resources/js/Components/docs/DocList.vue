<template>
  <div :class="containerClasses">
    <!-- Header -->
    <div v-if="showHeader" :class="headerClasses">
      <div class="flex items-center gap-3">
        <div v-if="showIcon" :class="headerIconClasses">
          <Icon name="ph:files-fill" :class="headerIconInnerClasses" />
        </div>
        <div>
          <h2 :class="titleClasses">{{ title }}</h2>
          <p v-if="!loading" :class="subtitleClasses">
            {{ totalCount }} {{ totalCount === 1 ? 'document' : 'documents' }}
            <span v-if="selectedCount > 0" class="text-neutral-900 dark:text-white">
              · {{ selectedCount }} selected
            </span>
          </p>
          <SharedSkeleton v-else custom-class="h-3 w-24 mt-1" />
        </div>
      </div>

      <!-- Header Actions -->
      <div class="flex items-center gap-2">
        <!-- Search Toggle -->
        <Tooltip v-if="showSearch" text="Search" :delay-open="300">
          <button
            type="button"
            :class="[headerButtonClasses, searchExpanded && 'bg-neutral-100 dark:bg-neutral-700 text-neutral-900 dark:text-white']"
            @click="searchExpanded = !searchExpanded"
          >
            <Icon name="ph:magnifying-glass" class="w-4 h-4" />
          </button>
        </Tooltip>

        <!-- Filter Dropdown -->
        <DropdownMenu v-if="showFilter" :items="filterDropdownItems">
          <Button variant="ghost" :class="headerButtonClasses">
            <Icon name="ph:funnel" class="w-4 h-4" />
            <span v-if="activeFilter !== 'all'" class="ml-1 text-xs">
              {{ filterOptions.find(f => f.value === activeFilter)?.label }}
            </span>
          </Button>
        </DropdownMenu>

        <!-- Sort Dropdown -->
        <DropdownMenu v-if="showSort" :items="sortDropdownItems">
          <Button variant="ghost" :class="headerButtonClasses">
            <Icon :name="sortDirection === 'asc' ? 'ph:sort-ascending' : 'ph:sort-descending'" class="w-4 h-4" />
          </Button>
        </DropdownMenu>

        <!-- View Toggle -->
        <div v-if="showViewToggle" class="flex items-center gap-0.5 p-0.5 rounded-lg bg-neutral-100 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700">
          <button
            v-for="view in viewOptions"
            :key="view.value"
            type="button"
            :class="[
              'p-1.5 rounded-md',
              'transition-all duration-150 ease-out',
              currentView === view.value
                ? 'bg-white dark:bg-neutral-700 text-neutral-900 dark:text-white shadow-sm'
                : 'text-neutral-500 dark:text-neutral-300 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-50 dark:hover:bg-neutral-700',
            ]"
            @click="currentView = view.value"
          >
            <Icon :name="view.icon" class="w-4 h-4" />
          </button>
        </div>

        <!-- Create Button -->
        <button
          v-if="showCreateButton"
          type="button"
          :class="createButtonClasses"
          @click="emit('create')"
        >
          <Icon name="ph:plus" class="w-4 h-4" />
          <span v-if="size !== 'sm'">New</span>
        </button>
      </div>
    </div>

    <!-- Search Bar -->
    <Transition name="slide-down">
      <div v-if="searchExpanded" :class="searchContainerClasses">
        <SharedSearchInput
          v-model="searchQuery"
          placeholder="Search documents..."
          size="sm"
          :loading="searching"
          clearable
          autofocus
        />
      </div>
    </Transition>

    <!-- Bulk Actions Bar -->
    <Transition name="slide-down">
      <div v-if="selectable && selectedCount > 0" :class="bulkActionsClasses">
        <div class="flex items-center gap-2">
          <button
            type="button"
            class="p-1 rounded transition-all duration-150 ease-out hover:bg-neutral-100 dark:hover:bg-neutral-700"
            @click="emit('clearSelection')"
          >
            <Icon name="ph:x" class="w-4 h-4 text-neutral-500 dark:text-neutral-300" />
          </button>
          <span class="text-sm text-neutral-700 dark:text-neutral-200">
            {{ selectedCount }} selected
          </span>
        </div>
        <div class="flex items-center gap-1">
          <button
            type="button"
            :class="bulkActionButtonClasses"
            @click="emit('bulkMove')"
          >
            <Icon name="ph:folder-simple" class="w-4 h-4" />
            Move
          </button>
          <button
            type="button"
            :class="bulkActionButtonClasses"
            @click="emit('bulkArchive')"
          >
            <Icon name="ph:archive" class="w-4 h-4" />
            Archive
          </button>
          <button
            type="button"
            :class="[bulkActionButtonClasses, 'text-red-400 hover:bg-red-500/10']"
            @click="emit('bulkDelete')"
          >
            <Icon name="ph:trash" class="w-4 h-4" />
            Delete
          </button>
        </div>
      </div>
    </Transition>

    <!-- Loading State -->
    <div v-if="loading" :class="contentClasses">
      <DocListSkeleton :count="5" :view="currentView" />
    </div>

    <!-- Content -->
    <template v-else-if="displayedDocuments.length > 0">
      <!-- Tree View -->
      <div v-if="currentView === 'tree'" :class="contentClasses">
        <DocsDocTreeItem
          v-for="doc in rootDocuments"
          :key="doc.id"
          :item="doc"
          :all-items="displayedDocuments"
          :level="0"
          :selected-id="selected?.id ?? null"
          @select="handleSelect"
        />
      </div>

      <!-- List View -->
      <div v-else-if="currentView === 'list'" :class="contentClasses">
        <TransitionGroup :name="animated ? 'doc-list' : ''" tag="div" class="space-y-1">
          <DocsDocItem
            v-for="doc in displayedDocuments"
            :key="doc.id"
            :document="doc"
            :selected="selected?.id === doc.id"
            :size="size"
            :selectable="selectable"
            :checked="selectedIds.includes(doc.id)"
            :show-checkbox="selectable && selectedCount > 0"
            @click="handleSelect(doc)"
            @check="handleCheck(doc, $event)"
          />
        </TransitionGroup>
      </div>

      <!-- Grid View -->
      <div v-else :class="[contentClasses, 'grid grid-cols-2 lg:grid-cols-3 gap-3']">
        <TransitionGroup :name="animated ? 'doc-grid' : ''">
          <DocsDocItem
            v-for="doc in displayedDocuments"
            :key="doc.id"
            :document="doc"
            :selected="selected?.id === doc.id"
            :size="size"
            variant="card"
            :selectable="selectable"
            :checked="selectedIds.includes(doc.id)"
            :show-checkbox="selectable && selectedCount > 0"
            @click="handleSelect(doc)"
            @check="handleCheck(doc, $event)"
          />
        </TransitionGroup>
      </div>

      <!-- Load More -->
      <button
        v-if="hasMore && !loading"
        type="button"
        :class="loadMoreButtonClasses"
        :disabled="loadingMore"
        @click="emit('loadMore')"
      >
        <Icon v-if="loadingMore" name="ph:spinner" class="w-4 h-4 animate-spin" />
        <span>{{ loadingMore ? 'Loading...' : 'Load more' }}</span>
      </button>
    </template>

    <!-- Search Results Empty -->
    <SharedEmptyState
      v-else-if="searchQuery"
      icon="ph:magnifying-glass"
      title="No results found"
      :description="`No documents match '${searchQuery}'`"
      size="sm"
      :action="{ label: 'Clear search', icon: 'ph:x', onClick: () => searchQuery = '' }"
    />

    <!-- Empty State -->
    <SharedEmptyState
      v-else
      icon="ph:file-dashed"
      :title="emptyTitle"
      :description="emptyDescription"
      :size="size"
      :action="showCreateAction ? {
        label: 'Create document',
        icon: 'ph:plus',
        onClick: () => emit('create')
      } : undefined"
    />

    <!-- Footer Stats -->
    <div v-if="showFooter && !loading && documents.length > 0" :class="footerClasses">
      <div class="flex items-center gap-4 text-xs text-neutral-500 dark:text-neutral-300">
        <span>{{ displayedDocuments.length }} of {{ totalCount }} documents</span>
        <span v-if="activeFilter !== 'all'">
          Filtered by: {{ filterOptions.find(f => f.value === activeFilter)?.label }}
        </span>
      </div>
      <div v-if="lastUpdated" class="text-xs text-neutral-400 dark:text-neutral-400">
        Updated {{ formatTimeAgo(lastUpdated) }}
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { h, defineComponent, resolveComponent, type PropType, ref, computed } from 'vue'
import type { Document } from '@/types'
import Icon from '@/Components/shared/Icon.vue'
import Button from '@/Components/shared/Button.vue'
import Tooltip from '@/Components/shared/Tooltip.vue'
import DropdownMenu from '@/Components/shared/DropdownMenu.vue'
import SharedSkeleton from '@/Components/shared/Skeleton.vue'
import SharedSearchInput from '@/Components/shared/SearchInput.vue'
import SharedEmptyState from '@/Components/shared/EmptyState.vue'
import DocsDocTreeItem from '@/Components/docs/DocTreeItem.vue'
import DocsDocItem from '@/Components/docs/DocItem.vue'

type DocListSize = 'sm' | 'md' | 'lg'
type DocListView = 'list' | 'grid' | 'tree'
type DocListFilter = 'all' | 'recent' | 'starred' | 'shared' | 'archived'
type DocListSort = 'name' | 'updated' | 'created' | 'author'

const props = withDefaults(defineProps<{
  // Core
  documents: Document[]
  selected?: Document | null

  // Appearance
  size?: DocListSize

  // Display options
  showHeader?: boolean
  showIcon?: boolean
  showSearch?: boolean
  showFilter?: boolean
  showSort?: boolean
  showViewToggle?: boolean
  showCreateButton?: boolean
  showCreateAction?: boolean
  showFooter?: boolean

  // Content
  title?: string
  emptyTitle?: string
  emptyDescription?: string

  // State
  loading?: boolean
  loadingMore?: boolean
  searching?: boolean
  hasMore?: boolean
  lastUpdated?: Date

  // Selection
  selectable?: boolean
  selectedIds?: string[]

  // Behavior
  animated?: boolean
}>(), {
  selected: null,
  size: 'md',
  showHeader: true,
  showIcon: true,
  showSearch: true,
  showFilter: true,
  showSort: true,
  showViewToggle: true,
  showCreateButton: true,
  showCreateAction: true,
  showFooter: true,
  title: 'Documents',
  emptyTitle: 'No documents yet',
  emptyDescription: 'Create your first document to get started',
  loading: false,
  loadingMore: false,
  searching: false,
  hasMore: false,
  lastUpdated: undefined,
  selectable: false,
  selectedIds: () => [],
  animated: true,
})

const emit = defineEmits<{
  select: [doc: Document]
  create: []
  loadMore: []
  clearSelection: []
  bulkMove: []
  bulkArchive: []
  bulkDelete: []
  check: [doc: Document, checked: boolean]
}>()

// State
const searchQuery = ref('')
const searchExpanded = ref(false)
const activeFilter = ref<DocListFilter>('all')
const activeSort = ref<DocListSort>('updated')
const sortDirection = ref<'asc' | 'desc'>('desc')
const currentView = ref<DocListView>('tree')

// Filter options
const filterOptions: { value: DocListFilter; label: string; icon?: string }[] = [
  { value: 'all', label: 'All documents' },
  { value: 'recent', label: 'Recent', icon: 'ph:clock' },
  { value: 'starred', label: 'Starred', icon: 'ph:star' },
  { value: 'shared', label: 'Shared', icon: 'ph:share-network' },
  { value: 'archived', label: 'Archived', icon: 'ph:archive' },
]

// Sort options
const sortOptions: { value: DocListSort; label: string }[] = [
  { value: 'updated', label: 'Last updated' },
  { value: 'created', label: 'Date created' },
  { value: 'name', label: 'Name' },
  { value: 'author', label: 'Author' },
]

// View options
const viewOptions: { value: DocListView; icon: string }[] = [
  { value: 'tree', icon: 'ph:tree-structure' },
  { value: 'list', icon: 'ph:list' },
  { value: 'grid', icon: 'ph:squares-four' },
]

// Size configuration
const sizeConfig: Record<DocListSize, {
  headerPadding: string
  contentPadding: string
  titleSize: string
  subtitleSize: string
  iconContainer: string
  iconSize: string
}> = {
  sm: {
    headerPadding: 'px-3 py-2',
    contentPadding: 'px-3 pb-3',
    titleSize: 'text-sm',
    subtitleSize: 'text-xs',
    iconContainer: 'w-8 h-8',
    iconSize: 'w-4 h-4',
  },
  md: {
    headerPadding: 'px-4 py-3',
    contentPadding: 'px-4 pb-4',
    titleSize: 'text-base',
    subtitleSize: 'text-sm',
    iconContainer: 'w-10 h-10',
    iconSize: 'w-5 h-5',
  },
  lg: {
    headerPadding: 'px-5 py-4',
    contentPadding: 'px-5 pb-5',
    titleSize: 'text-lg',
    subtitleSize: 'text-sm',
    iconContainer: 'w-12 h-12',
    iconSize: 'w-6 h-6',
  },
}

// Computed values
const totalCount = computed(() => props.documents.length)

const selectedCount = computed(() => props.selectedIds.length)

const filteredDocuments = computed(() => {
  let docs = [...props.documents]

  // Apply filter
  if (activeFilter.value !== 'all') {
    docs = docs.filter(doc => {
      if (activeFilter.value === 'recent') {
        const weekAgo = Date.now() - 7 * 24 * 60 * 60 * 1000
        return new Date(doc.updatedAt).getTime() > weekAgo
      }
      if (activeFilter.value === 'starred') return (doc as any).isStarred
      if (activeFilter.value === 'shared') return (doc as any).isShared
      if (activeFilter.value === 'archived') return (doc as any).isArchived
      return true
    })
  }

  // Apply search
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    docs = docs.filter(doc =>
      doc.title.toLowerCase().includes(query) ||
      doc.author?.name.toLowerCase().includes(query)
    )
  }

  // Apply sort
  docs.sort((a, b) => {
    let comparison = 0
    switch (activeSort.value) {
      case 'name':
        comparison = a.title.localeCompare(b.title)
        break
      case 'updated':
        comparison = new Date(b.updatedAt).getTime() - new Date(a.updatedAt).getTime()
        break
      case 'created':
        comparison = new Date(b.createdAt || 0).getTime() - new Date(a.createdAt || 0).getTime()
        break
      case 'author':
        comparison = (a.author?.name || '').localeCompare(b.author?.name || '')
        break
    }
    return sortDirection.value === 'asc' ? comparison : -comparison
  })

  return docs
})

const displayedDocuments = computed(() => filteredDocuments.value)

const rootDocuments = computed(() =>
  displayedDocuments.value.filter(doc => !doc.parentId)
)

// Dropdown items
const filterDropdownItems = computed(() => [
  filterOptions.map(option => ({
    label: option.label,
    icon: option.icon || (activeFilter.value === option.value ? 'ph:check' : undefined),
    click: () => { activeFilter.value = option.value },
  })),
])

const sortDropdownItems = computed(() => [
  sortOptions.map(option => ({
    label: option.label,
    icon: activeSort.value === option.value ? 'ph:check' : undefined,
    click: () => { activeSort.value = option.value },
  })),
  [
    {
      label: sortDirection.value === 'asc' ? 'Ascending' : 'Descending',
      icon: sortDirection.value === 'asc' ? 'ph:arrow-up' : 'ph:arrow-down',
      click: () => { sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc' },
    },
  ],
])

// Container classes
const containerClasses = computed(() => [
  'flex flex-col h-full',
])

// Header classes
const headerClasses = computed(() => [
  'flex items-center justify-between border-b border-neutral-200 dark:border-neutral-700 sticky top-0 bg-white dark:bg-neutral-900 z-10',
  sizeConfig[props.size].headerPadding,
])

const headerIconClasses = computed(() => [
  'rounded-lg flex items-center justify-center bg-neutral-100 dark:bg-neutral-800',
  sizeConfig[props.size].iconContainer,
  'transition-all duration-150 ease-out',
])

const headerIconInnerClasses = computed(() => [
  'text-neutral-600 dark:text-neutral-200',
  sizeConfig[props.size].iconSize,
])

const titleClasses = computed(() => [
  'font-semibold text-neutral-900 dark:text-white',
  sizeConfig[props.size].titleSize,
])

const subtitleClasses = computed(() => [
  'text-neutral-500 dark:text-neutral-300',
  sizeConfig[props.size].subtitleSize,
])

const headerButtonClasses = computed(() => [
  'flex items-center gap-1 px-2.5 py-1.5 rounded-lg',
  'transition-all duration-150 ease-out',
  'text-neutral-500 dark:text-neutral-300 hover:text-neutral-900 dark:hover:text-white',
  'hover:bg-neutral-100 dark:hover:bg-neutral-800',
  'focus:outline-none focus-visible:ring-2 focus-visible:ring-neutral-300 dark:focus-visible:ring-neutral-600',
])

const createButtonClasses = computed(() => [
  'flex items-center gap-1.5 px-3 py-1.5 rounded-lg group',
  'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 hover:bg-neutral-800 dark:hover:bg-neutral-100',
  'transition-all duration-150 ease-out',
  'focus:outline-none focus-visible:ring-2 focus-visible:ring-neutral-400 dark:focus-visible:ring-neutral-500',
])

// Search container classes
const searchContainerClasses = computed(() => [
  'px-4 py-2 border-b border-neutral-200 dark:border-neutral-700',
])

// Bulk actions classes
const bulkActionsClasses = computed(() => [
  'flex items-center justify-between px-4 py-2',
  'bg-neutral-50 dark:bg-neutral-800 border-b border-neutral-200 dark:border-neutral-700',
])

const bulkActionButtonClasses = computed(() => [
  'flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs',
  'text-neutral-500 dark:text-neutral-300 hover:text-neutral-900 dark:hover:text-white',
  'hover:bg-neutral-100 dark:hover:bg-neutral-700',
  'transition-all duration-150 ease-out',
])

// Content classes
const contentClasses = computed(() => [
  'flex-1 overflow-y-auto scrollbar-thin',
  sizeConfig[props.size].contentPadding,
  'pt-4',
])

// Load more button classes
const loadMoreButtonClasses = computed(() => [
  'w-full flex items-center justify-center gap-2 py-3.5 mt-2 group',
  'text-sm text-neutral-500 dark:text-neutral-300 hover:text-neutral-900 dark:hover:text-white',
  'rounded-lg hover:bg-neutral-50 dark:hover:bg-neutral-800',
  'transition-all duration-150 ease-out',
  'disabled:opacity-50 disabled:cursor-not-allowed',
])

// Footer classes
const footerClasses = computed(() => [
  'flex items-center justify-between px-4 py-2 border-t border-neutral-200 dark:border-neutral-700',
])

// Dropdown classes
const dropdownContentClasses = computed(() => [
  'z-50 min-w-[180px] bg-white border border-neutral-200 rounded-lg',
  'p-1.5 shadow-lg',
  'animate-in fade-in-0 duration-150',
])

const dropdownItemClasses = computed(() => [
  'relative flex items-center px-3 py-2 text-sm rounded-md cursor-pointer',
  'text-neutral-700 hover:bg-neutral-50',
  'transition-colors duration-150 ease-out outline-none',
  'data-[highlighted]:bg-neutral-50',
])

// Tooltip classes
const tooltipClasses = computed(() => [
  'z-50 bg-white border border-neutral-200 rounded-lg',
  'px-3 py-1.5 text-xs shadow-md',
  'animate-in fade-in-0 duration-150',
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

// Handlers
const handleSelect = (doc: Document) => {
  emit('select', doc)
}

const handleCheck = (doc: Document, checked: boolean) => {
  emit('check', doc, checked)
}

// Skeleton Component
const DocListSkeleton = defineComponent({
  name: 'DocListSkeleton',
  props: {
    count: {
      type: Number,
      default: 5,
    },
    view: {
      type: String as PropType<DocListView>,
      default: 'list',
    },
  },
  setup(props) {
    return () => {
      if (props.view === 'grid') {
        return h('div', { class: 'grid grid-cols-2 lg:grid-cols-3 gap-3' },
          Array.from({ length: props.count }).map((_, i) =>
            h('div', { key: i, class: 'p-4 rounded-xl border border-neutral-100 animate-pulse' }, [
              h('div', { class: 'flex items-center gap-3 mb-3' }, [
                h(resolveComponent('SharedSkeleton'), { customClass: 'w-10 h-10 rounded-xl' }),
                h('div', { class: 'flex-1 space-y-2' }, [
                  h(resolveComponent('SharedSkeleton'), { customClass: 'h-4 w-3/4' }),
                  h(resolveComponent('SharedSkeleton'), { customClass: 'h-3 w-1/2' }),
                ]),
              ]),
              h(resolveComponent('SharedSkeleton'), { customClass: 'h-3 w-full' }),
            ])
          )
        )
      }

      return h('div', { class: 'space-y-1' },
        Array.from({ length: props.count }).map((_, i) =>
          h('div', { key: i, class: 'flex items-center gap-3 p-3 rounded-xl animate-pulse' }, [
            h(resolveComponent('SharedSkeleton'), { customClass: 'w-10 h-10 rounded-xl' }),
            h('div', { class: 'flex-1 space-y-2' }, [
              h(resolveComponent('SharedSkeleton'), { customClass: 'h-4 w-48' }),
              h('div', { class: 'flex items-center gap-2' }, [
                h(resolveComponent('SharedSkeleton'), { variant: 'avatar', customClass: 'w-4 h-4' }),
                h(resolveComponent('SharedSkeleton'), { customClass: 'h-3 w-20' }),
              ]),
            ]),
          ])
        )
      )
    }
  },
})
</script>

<style scoped>
/* Custom scrollbar */
.scrollbar-thin::-webkit-scrollbar {
  width: 6px;
}

.scrollbar-thin::-webkit-scrollbar-track {
  background: transparent;
}

.scrollbar-thin::-webkit-scrollbar-thumb {
  background-color: #e5e7eb;
  border-radius: 3px;
}

.scrollbar-thin::-webkit-scrollbar-thumb:hover {
  background-color: #9ca3af;
}

/* Slide down transition */
.slide-down-enter-active {
  transition: all 0.15s ease-out;
}

.slide-down-leave-active {
  transition: all 0.1s ease-out;
}

.slide-down-enter-from {
  opacity: 0;
  transform: translateY(-10px);
}

.slide-down-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}

/* Document list transitions */
.doc-list-enter-active {
  transition: all 0.15s ease-out;
}

.doc-list-leave-active {
  transition: all 0.1s ease-out;
}

.doc-list-enter-from {
  opacity: 0;
}

.doc-list-leave-to {
  opacity: 0;
}

.doc-list-move {
  transition: transform 0.15s ease-out;
}

/* Document grid transitions */
.doc-grid-enter-active {
  transition: all 0.15s ease-out;
}

.doc-grid-leave-active {
  transition: all 0.1s ease-out;
}

.doc-grid-enter-from {
  opacity: 0;
}

.doc-grid-leave-to {
  opacity: 0;
}

.doc-grid-move {
  transition: transform 0.15s ease-out;
}
</style>
