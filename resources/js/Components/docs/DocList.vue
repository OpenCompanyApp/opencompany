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
            <span v-if="selectedCount > 0" class="text-gray-900">
              · {{ selectedCount }} selected
            </span>
          </p>
          <SharedSkeleton v-else custom-class="h-3 w-24 mt-1" />
        </div>
      </div>

      <!-- Header Actions -->
      <div class="flex items-center gap-2">
        <!-- Search Toggle -->
        <TooltipProvider v-if="showSearch" :delay-duration="300">
          <TooltipRoot>
            <TooltipTrigger as-child>
              <button
                type="button"
                :class="[headerButtonClasses, searchExpanded && 'bg-gray-100 text-gray-900']"
                @click="searchExpanded = !searchExpanded"
              >
                <Icon name="ph:magnifying-glass" class="w-4 h-4" />
              </button>
            </TooltipTrigger>
            <TooltipPortal>
              <TooltipContent :class="tooltipClasses" side="bottom">
                Search
                <TooltipArrow class="fill-white" />
              </TooltipContent>
            </TooltipPortal>
          </TooltipRoot>
        </TooltipProvider>

        <!-- Filter Dropdown -->
        <DropdownMenuRoot v-if="showFilter">
          <DropdownMenuTrigger as-child>
            <button type="button" :class="headerButtonClasses">
              <Icon name="ph:funnel" class="w-4 h-4" />
              <span v-if="activeFilter !== 'all'" class="ml-1 text-xs">
                {{ filterOptions.find(f => f.value === activeFilter)?.label }}
              </span>
            </button>
          </DropdownMenuTrigger>
          <DropdownMenuPortal>
            <DropdownMenuContent :class="dropdownContentClasses" :side-offset="8">
              <DropdownMenuLabel class="px-2 py-1.5 text-xs text-gray-500">
                Filter by
              </DropdownMenuLabel>
              <DropdownMenuRadioGroup v-model="activeFilter">
                <DropdownMenuRadioItem
                  v-for="option in filterOptions"
                  :key="option.value"
                  :value="option.value"
                  :class="dropdownItemClasses"
                >
                  <DropdownMenuItemIndicator class="absolute left-2">
                    <Icon name="ph:check" class="w-3.5 h-3.5" />
                  </DropdownMenuItemIndicator>
                  <Icon v-if="option.icon" :name="option.icon" class="w-4 h-4 mr-2 ml-5" />
                  <span :class="!option.icon && 'pl-5'">{{ option.label }}</span>
                </DropdownMenuRadioItem>
              </DropdownMenuRadioGroup>
            </DropdownMenuContent>
          </DropdownMenuPortal>
        </DropdownMenuRoot>

        <!-- Sort Dropdown -->
        <DropdownMenuRoot v-if="showSort">
          <DropdownMenuTrigger as-child>
            <button type="button" :class="headerButtonClasses">
              <Icon :name="sortDirection === 'asc' ? 'ph:sort-ascending' : 'ph:sort-descending'" class="w-4 h-4" />
            </button>
          </DropdownMenuTrigger>
          <DropdownMenuPortal>
            <DropdownMenuContent :class="dropdownContentClasses" :side-offset="8">
              <DropdownMenuLabel class="px-2 py-1.5 text-xs text-gray-500">
                Sort by
              </DropdownMenuLabel>
              <DropdownMenuRadioGroup v-model="activeSort">
                <DropdownMenuRadioItem
                  v-for="option in sortOptions"
                  :key="option.value"
                  :value="option.value"
                  :class="dropdownItemClasses"
                >
                  <DropdownMenuItemIndicator class="absolute left-2">
                    <Icon name="ph:check" class="w-3.5 h-3.5" />
                  </DropdownMenuItemIndicator>
                  <span class="pl-5">{{ option.label }}</span>
                </DropdownMenuRadioItem>
              </DropdownMenuRadioGroup>
              <DropdownMenuSeparator class="h-px bg-gray-200 my-1" />
              <DropdownMenuItem
                :class="dropdownItemClasses"
                @select="sortDirection = sortDirection === 'asc' ? 'desc' : 'asc'"
              >
                <Icon :name="sortDirection === 'asc' ? 'ph:arrow-up' : 'ph:arrow-down'" class="w-4 h-4 mr-2" />
                {{ sortDirection === 'asc' ? 'Ascending' : 'Descending' }}
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenuPortal>
        </DropdownMenuRoot>

        <!-- View Toggle -->
        <div v-if="showViewToggle" class="flex items-center gap-0.5 p-0.5 rounded-lg bg-gray-100 border border-gray-200">
          <button
            v-for="view in viewOptions"
            :key="view.value"
            type="button"
            :class="[
              'p-1.5 rounded-md',
              'transition-all duration-150 ease-out',
              currentView === view.value
                ? 'bg-white text-gray-900 shadow-sm'
                : 'text-gray-500 hover:text-gray-900 hover:bg-gray-50',
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
            class="p-1 rounded transition-all duration-150 ease-out hover:bg-gray-100"
            @click="emit('clearSelection')"
          >
            <Icon name="ph:x" class="w-4 h-4 text-gray-500" />
          </button>
          <span class="text-sm text-gray-700">
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
      <div class="flex items-center gap-4 text-xs text-gray-500">
        <span>{{ displayedDocuments.length }} of {{ totalCount }} documents</span>
        <span v-if="activeFilter !== 'all'">
          Filtered by: {{ filterOptions.find(f => f.value === activeFilter)?.label }}
        </span>
      </div>
      <div v-if="lastUpdated" class="text-xs text-gray-400">
        Updated {{ formatTimeAgo(lastUpdated) }}
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { h, defineComponent, resolveComponent, type PropType, ref, computed } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import {
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuItemIndicator,
  DropdownMenuLabel,
  DropdownMenuPortal,
  DropdownMenuRadioGroup,
  DropdownMenuRadioItem,
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
import type { Document } from '@/types'

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

// Container classes
const containerClasses = computed(() => [
  'flex flex-col h-full',
])

// Header classes
const headerClasses = computed(() => [
  'flex items-center justify-between border-b border-gray-200 sticky top-0 bg-white z-10',
  sizeConfig[props.size].headerPadding,
])

const headerIconClasses = computed(() => [
  'rounded-lg flex items-center justify-center bg-gray-100',
  sizeConfig[props.size].iconContainer,
  'transition-all duration-150 ease-out',
])

const headerIconInnerClasses = computed(() => [
  'text-gray-600',
  sizeConfig[props.size].iconSize,
])

const titleClasses = computed(() => [
  'font-semibold text-gray-900',
  sizeConfig[props.size].titleSize,
])

const subtitleClasses = computed(() => [
  'text-gray-500',
  sizeConfig[props.size].subtitleSize,
])

const headerButtonClasses = computed(() => [
  'flex items-center gap-1 px-2.5 py-1.5 rounded-lg',
  'transition-all duration-150 ease-out',
  'text-gray-500 hover:text-gray-900',
  'hover:bg-gray-100',
  'focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-300',
])

const createButtonClasses = computed(() => [
  'flex items-center gap-1.5 px-3 py-1.5 rounded-lg group',
  'bg-gray-900 text-white hover:bg-gray-800',
  'transition-all duration-150 ease-out',
  'focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-400',
])

// Search container classes
const searchContainerClasses = computed(() => [
  'px-4 py-2 border-b border-gray-200',
])

// Bulk actions classes
const bulkActionsClasses = computed(() => [
  'flex items-center justify-between px-4 py-2',
  'bg-gray-50 border-b border-gray-200',
])

const bulkActionButtonClasses = computed(() => [
  'flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs',
  'text-gray-500 hover:text-gray-900',
  'hover:bg-gray-100',
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
  'text-sm text-gray-500 hover:text-gray-900',
  'rounded-lg hover:bg-gray-50',
  'transition-all duration-150 ease-out',
  'disabled:opacity-50 disabled:cursor-not-allowed',
])

// Footer classes
const footerClasses = computed(() => [
  'flex items-center justify-between px-4 py-2 border-t border-gray-200',
])

// Dropdown classes
const dropdownContentClasses = computed(() => [
  'z-50 min-w-[180px] bg-white border border-gray-200 rounded-lg',
  'p-1.5 shadow-lg',
  'animate-in fade-in-0 duration-150',
])

const dropdownItemClasses = computed(() => [
  'relative flex items-center px-3 py-2 text-sm rounded-md cursor-pointer',
  'text-gray-700 hover:bg-gray-50',
  'transition-colors duration-150 ease-out outline-none',
  'data-[highlighted]:bg-gray-50',
])

// Tooltip classes
const tooltipClasses = computed(() => [
  'z-50 bg-white border border-gray-200 rounded-lg',
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
            h('div', { key: i, class: 'p-4 rounded-xl border border-gray-100 animate-pulse' }, [
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
