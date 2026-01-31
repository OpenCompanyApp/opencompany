<template>
  <div
    :class="[
      'doc-tree-item',
      {
        'doc-tree-item--dragging': isDragging,
        'doc-tree-item--drag-over': isDragOver,
        'doc-tree-item--drop-above': dropPosition === 'above',
        'doc-tree-item--drop-below': dropPosition === 'below',
        'doc-tree-item--drop-inside': dropPosition === 'inside',
      }
    ]"
    :data-item-id="item.id"
    :data-level="level"
  >
    <!-- Drop indicator line (above) -->
    <Transition name="drop-indicator">
      <div
        v-if="isDragOver && dropPosition === 'above'"
        class="absolute left-0 right-0 top-0 h-0.5 bg-neutral-900 dark:bg-white z-10 rounded-full"
        :style="{ marginLeft: `${8 + level * 20}px` }"
      />
    </Transition>

    <!-- Main row -->
    <DocTreeItemRow
      :title="item.title"
      :is-folder="item.isFolder ?? false"
      :expanded="expanded"
      :selected="selected"
      :level="level"
      :child-count="childCount"
      :updated-at="item.updatedAt"
      :document-type="documentType"
      :is-shared="item.isShared"
      :is-starred="item.isStarred"
      :is-pinned="item.isPinned"
      :is-locked="item.isLocked"
      :tags="item.tags"
      :size="size"
      :selectable="selectable"
      :is-selected="isItemSelected"
      :draggable="draggable"
      :is-dragging="isDragging"
      :highlight-query="highlightQuery"
      :show-path="showPath && !item.isFolder"
      :path="itemPath"
      :loading="itemLoading"
      :disabled="disabled || item.isLocked"
      :show-actions="showActions"
      :show-metadata="showMetadata"
      @click="handleClick"
      @toggle="handleToggle"
      @select="handleSelectToggle"
      @dragstart="handleDragStart"
      @dragend="handleDragEnd"
      @star="$emit('star', item)"
      @pin="$emit('pin', item)"
      @duplicate="$emit('duplicate', item)"
      @delete="$emit('delete', item)"
      @rename="$emit('rename', item)"
      @move="$emit('move', item)"
    />

    <!-- Context menu trigger area -->
    <div
      v-if="showContextMenu"
      class="absolute inset-0 cursor-context-menu"
      @contextmenu.prevent="handleContextMenu"
    />

    <!-- Children (recursive) -->
    <Transition
      enter-active-class="transition-all duration-150 ease-out overflow-hidden"
      enter-from-class="opacity-0 max-h-0"
      enter-to-class="opacity-100 max-h-[2000px]"
      leave-active-class="transition-all duration-150 ease-out overflow-hidden"
      leave-from-class="opacity-100 max-h-[2000px]"
      leave-to-class="opacity-0 max-h-0"
    >
      <div
        v-if="item.isFolder && expanded && !isLoading"
        :class="[
          'doc-tree-children',
          { 'doc-tree-children--with-line': showConnectorLines && children.length > 0 }
        ]"
      >
        <!-- Loading children state -->
        <div
          v-if="childrenLoading"
          class="py-2"
          :style="{ paddingLeft: `${8 + (level + 1) * 20}px` }"
        >
          <div class="flex items-center gap-2 text-neutral-500">
            <Icon name="ph:spinner" class="w-4 h-4 animate-spin" />
            <span class="text-xs">Loading...</span>
          </div>
        </div>

        <!-- Empty folder state -->
        <div
          v-else-if="children.length === 0 && showEmptyState"
          class="py-3"
          :style="{ paddingLeft: `${8 + (level + 1) * 20}px` }"
        >
          <div class="flex items-center gap-2 text-neutral-400">
            <Icon name="ph:folder-dashed" class="w-4 h-4" />
            <span class="text-xs italic">Empty folder</span>
          </div>
        </div>

        <!-- Children items -->
        <TransitionGroup
          v-else
          name="tree-item"
          tag="div"
          @before-leave="onBeforeLeave"
        >
          <DocTreeItem
            v-for="child in sortedChildren"
            :key="child.id"
            :item="child"
            :all-items="allItems"
            :level="level + 1"
            :selected-id="selectedId"
            :selected-ids="selectedIds"
            :size="size"
            :selectable="selectable"
            :draggable="draggable"
            :show-connector-lines="showConnectorLines"
            :show-empty-state="showEmptyState"
            :show-context-menu="showContextMenu"
            :show-actions="showActions"
            :show-metadata="showMetadata"
            :show-path="showPath"
            :highlight-query="highlightQuery"
            :sort-by="sortBy"
            :sort-direction="sortDirection"
            :loading-ids="loadingIds"
            :disabled="disabled"
            :expand-all="expandAll"
            :collapse-all="collapseAll"
            @select="$emit('select', $event)"
            @toggle-select="$emit('toggle-select', $event)"
            @expand="$emit('expand', $event)"
            @collapse="$emit('collapse', $event)"
            @star="$emit('star', $event)"
            @pin="$emit('pin', $event)"
            @duplicate="$emit('duplicate', $event)"
            @delete="$emit('delete', $event)"
            @rename="$emit('rename', $event)"
            @move="$emit('move', $event)"
            @drop="$emit('drop', $event)"
            @context-menu="$emit('context-menu', $event)"
          />
        </TransitionGroup>
      </div>
    </Transition>

    <!-- Drop indicator line (below) -->
    <Transition name="drop-indicator">
      <div
        v-if="isDragOver && dropPosition === 'below'"
        class="absolute left-0 right-0 bottom-0 h-0.5 bg-neutral-900 dark:bg-white z-10 rounded-full"
        :style="{ marginLeft: `${8 + level * 20}px` }"
      />
    </Transition>

    <!-- Drop inside indicator (for folders) -->
    <Transition name="drop-inside">
      <div
        v-if="isDragOver && dropPosition === 'inside' && item.isFolder"
        class="absolute inset-0 rounded-lg border-2 border-dashed border-neutral-900 dark:border-white bg-neutral-50 dark:bg-neutral-800 pointer-events-none z-5"
        :style="{ marginLeft: `${level * 20}px` }"
      />
    </Transition>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, defineAsyncComponent } from 'vue'
import type { Document } from '@/types'
import Icon from '@/Components/shared/Icon.vue'
import DocTreeItemRow from './doc-tree/DocTreeItemRow.vue'

// Self-reference for recursive component
const DocTreeItem = defineAsyncComponent(() => import('./DocTreeItem.vue'))

// ============================================================================
// Types
// ============================================================================

type DocTreeItemSize = 'sm' | 'md' | 'lg'
type SortBy = 'name' | 'updated' | 'created' | 'type'
type SortDirection = 'asc' | 'desc'
type DropPosition = 'above' | 'below' | 'inside' | null

interface ExtendedDocument extends Document {
  isShared?: boolean
  isStarred?: boolean
  isPinned?: boolean
  isLocked?: boolean
  documentType?: string
  tags?: string[]
  path?: string
  createdAt?: Date
}

interface DropEvent {
  item: Document
  target: Document
  position: DropPosition
}

interface ContextMenuEvent {
  item: Document
  event: MouseEvent
  position: { x: number; y: number }
}

// ============================================================================
// Props & Emits
// ============================================================================

const props = withDefaults(defineProps<{
  /** The document/folder item */
  item: ExtendedDocument
  /** All items in the tree (for finding children) */
  allItems: ExtendedDocument[]
  /** Nesting level (0 = root) */
  level: number
  /** Currently selected item ID (single select) */
  selectedId?: string | null
  /** Currently selected item IDs (multi select) */
  selectedIds?: string[]
  /** Component size */
  size?: DocTreeItemSize
  /** Enable checkbox selection */
  selectable?: boolean
  /** Enable drag and drop */
  draggable?: boolean
  /** Show connector lines between items */
  showConnectorLines?: boolean
  /** Show empty folder state */
  showEmptyState?: boolean
  /** Enable context menu */
  showContextMenu?: boolean
  /** Show quick action buttons */
  showActions?: boolean
  /** Show metadata (date, tags) */
  showMetadata?: boolean
  /** Show path breadcrumb */
  showPath?: boolean
  /** Search query to highlight */
  highlightQuery?: string
  /** Sort children by */
  sortBy?: SortBy
  /** Sort direction */
  sortDirection?: SortDirection
  /** IDs of items currently loading */
  loadingIds?: string[]
  /** Disable all interactions */
  disabled?: boolean
  /** Force expand all folders */
  expandAll?: boolean
  /** Force collapse all folders */
  collapseAll?: boolean
}>(), {
  selectedId: null,
  selectedIds: () => [],
  size: 'md',
  selectable: false,
  draggable: false,
  showConnectorLines: true,
  showEmptyState: true,
  showContextMenu: false,
  showActions: false,
  showMetadata: true,
  showPath: false,
  highlightQuery: '',
  sortBy: 'name',
  sortDirection: 'asc',
  loadingIds: () => [],
  disabled: false,
  expandAll: false,
  collapseAll: false,
})

const emit = defineEmits<{
  /** Item selected (click on document) */
  select: [doc: Document]
  /** Item selection toggled (checkbox) */
  'toggle-select': [doc: Document]
  /** Folder expanded */
  expand: [doc: Document]
  /** Folder collapsed */
  collapse: [doc: Document]
  /** Star toggled */
  star: [doc: Document]
  /** Pin toggled */
  pin: [doc: Document]
  /** Duplicate requested */
  duplicate: [doc: Document]
  /** Delete requested */
  delete: [doc: Document]
  /** Rename requested */
  rename: [doc: Document]
  /** Move requested */
  move: [doc: Document]
  /** Item dropped */
  drop: [event: DropEvent]
  /** Context menu opened */
  'context-menu': [event: ContextMenuEvent]
}>()

// ============================================================================
// State
// ============================================================================

const expanded = ref(true)
const isDragging = ref(false)
const isDragOver = ref(false)
const dropPosition = ref<DropPosition>(null)
const childrenLoading = ref(false)

// ============================================================================
// Computed
// ============================================================================

const selected = computed(() => props.selectedId === props.item.id)

const isItemSelected = computed(() =>
  props.selectedIds.includes(props.item.id)
)

const itemLoading = computed(() =>
  props.loadingIds.includes(props.item.id)
)

const isLoading = computed(() => itemLoading.value || childrenLoading.value)

const children = computed(() =>
  props.allItems.filter(doc => doc.parentId === props.item.id)
)

const sortedChildren = computed(() => {
  const sorted = [...children.value]

  // Always put folders first
  sorted.sort((a, b) => {
    if (a.isFolder && !b.isFolder) return -1
    if (!a.isFolder && b.isFolder) return 1

    // Then sort by the specified field
    let comparison = 0
    switch (props.sortBy) {
      case 'name':
        comparison = a.title.localeCompare(b.title)
        break
      case 'updated':
        comparison = new Date(a.updatedAt || 0).getTime() - new Date(b.updatedAt || 0).getTime()
        break
      case 'created':
        comparison = new Date((a as ExtendedDocument).createdAt || 0).getTime() -
                     new Date((b as ExtendedDocument).createdAt || 0).getTime()
        break
      case 'type':
        comparison = ((a as ExtendedDocument).documentType || '').localeCompare(
          (b as ExtendedDocument).documentType || ''
        )
        break
    }

    return props.sortDirection === 'asc' ? comparison : -comparison
  })

  // Pinned items always first within their group
  sorted.sort((a, b) => {
    const aExt = a as ExtendedDocument
    const bExt = b as ExtendedDocument
    if (aExt.isPinned && !bExt.isPinned) return -1
    if (!aExt.isPinned && bExt.isPinned) return 1
    return 0
  })

  return sorted
})

const childCount = computed(() => children.value.length)

const documentType = computed(() => {
  if (props.item.isFolder) return 'folder'
  return (props.item as ExtendedDocument).documentType || 'document'
})

const itemPath = computed(() => {
  if (!props.showPath) return ''

  const pathParts: string[] = []
  let current = props.item

  while (current.parentId) {
    const parent = props.allItems.find(i => i.id === current.parentId)
    if (parent) {
      pathParts.unshift(parent.title)
      current = parent
    } else {
      break
    }
  }

  return pathParts.join(' / ')
})

// ============================================================================
// Watchers
// ============================================================================

watch(() => props.expandAll, (val) => {
  if (val && props.item.isFolder) {
    expanded.value = true
  }
})

watch(() => props.collapseAll, (val) => {
  if (val && props.item.isFolder) {
    expanded.value = false
  }
})

// ============================================================================
// Methods
// ============================================================================

const handleClick = () => {
  if (props.disabled) return

  if (props.item.isFolder) {
    handleToggle()
  } else {
    emit('select', props.item)
  }
}

const handleToggle = () => {
  if (props.disabled) return

  expanded.value = !expanded.value

  if (expanded.value) {
    emit('expand', props.item)
  } else {
    emit('collapse', props.item)
  }
}

const handleSelectToggle = () => {
  if (props.disabled) return
  emit('toggle-select', props.item)
}

const handleContextMenu = (event: MouseEvent) => {
  if (props.disabled) return

  emit('context-menu', {
    item: props.item,
    event,
    position: { x: event.clientX, y: event.clientY }
  })
}

// Drag and drop
const handleDragStart = (event: DragEvent) => {
  if (!props.draggable || props.disabled) return

  isDragging.value = true

  if (event.dataTransfer) {
    event.dataTransfer.effectAllowed = 'move'
    event.dataTransfer.setData('text/plain', props.item.id)
    event.dataTransfer.setData('application/json', JSON.stringify(props.item))
  }
}

const handleDragEnd = () => {
  isDragging.value = false
  isDragOver.value = false
  dropPosition.value = null
}

const handleDragOver = (event: DragEvent) => {
  if (!props.draggable || props.disabled) return

  event.preventDefault()
  isDragOver.value = true

  const rect = (event.currentTarget as HTMLElement).getBoundingClientRect()
  const y = event.clientY - rect.top
  const height = rect.height

  // Determine drop position based on cursor location
  if (props.item.isFolder) {
    if (y < height * 0.25) {
      dropPosition.value = 'above'
    } else if (y > height * 0.75) {
      dropPosition.value = 'below'
    } else {
      dropPosition.value = 'inside'
    }
  } else {
    dropPosition.value = y < height / 2 ? 'above' : 'below'
  }
}

const handleDragLeave = () => {
  isDragOver.value = false
  dropPosition.value = null
}

const handleDrop = (event: DragEvent) => {
  if (!props.draggable || props.disabled) return

  event.preventDefault()

  const data = event.dataTransfer?.getData('application/json')
  if (data) {
    try {
      const droppedItem = JSON.parse(data) as Document

      // Don't drop on self
      if (droppedItem.id === props.item.id) return

      // Don't drop parent into child
      if (isDescendant(props.item, droppedItem)) return

      emit('drop', {
        item: droppedItem,
        target: props.item,
        position: dropPosition.value
      })
    } catch {
      // Invalid JSON
    }
  }

  isDragOver.value = false
  dropPosition.value = null
}

const isDescendant = (potentialChild: Document, potentialParent: Document): boolean => {
  let current = potentialChild
  while (current.parentId) {
    if (current.parentId === potentialParent.id) return true
    const parent = props.allItems.find(i => i.id === current.parentId)
    if (parent) {
      current = parent
    } else {
      break
    }
  }
  return false
}

// Animation helpers
const onBeforeLeave = (el: Element) => {
  const htmlEl = el as HTMLElement
  htmlEl.style.position = 'absolute'
  htmlEl.style.width = '100%'
}

// ============================================================================
// Expose
// ============================================================================

defineExpose({
  expand: () => { expanded.value = true },
  collapse: () => { expanded.value = false },
  toggle: () => { expanded.value = !expanded.value },
  isExpanded: () => expanded.value,
})
</script>

<style scoped>
.doc-tree-item {
  position: relative;
}

.doc-tree-item--dragging {
  opacity: 0.5;
}

.doc-tree-item--drag-over {
  /* Highlight state handled by drop indicators */
}

/* Connector lines */
.doc-tree-children--with-line {
  position: relative;
}

.doc-tree-children--with-line::before {
  content: '';
  position: absolute;
  left: calc(18px + var(--level, 0) * 20px);
  top: 0;
  bottom: 12px;
  width: 1px;
  background: linear-gradient(
    to bottom,
    rgb(229 231 235) 0%,
    rgb(229 231 235) 50%,
    transparent 100%
  );
  opacity: 0.5;
}

/* Tree item transitions */
.tree-item-enter-active {
  transition: all 0.2s ease-out;
}

.tree-item-leave-active {
  transition: all 0.15s ease-in;
}

.tree-item-enter-from {
  opacity: 0;
  transform: translateX(-8px);
}

.tree-item-leave-to {
  opacity: 0;
  transform: translateX(8px);
}

.tree-item-move {
  transition: transform 0.2s ease;
}

/* Drop indicator transitions */
.drop-indicator-enter-active,
.drop-indicator-leave-active {
  transition: all 0.15s ease;
}

.drop-indicator-enter-from,
.drop-indicator-leave-to {
  opacity: 0;
  transform: scaleX(0.8);
}

.drop-inside-enter-active,
.drop-inside-leave-active {
  transition: all 0.15s ease;
}

.drop-inside-enter-from,
.drop-inside-leave-to {
  opacity: 0;
}

/* Keyboard navigation focus */
.doc-tree-item:focus-within > :first-child {
  outline: none;
}

/* Selection highlight animation */
@keyframes selectPulse {
  0%, 100% {
    box-shadow: 0 0 0 0 transparent;
  }
  50% {
    box-shadow: 0 0 0 4px rgb(229 231 235 / 0.5);
  }
}

.doc-tree-item--selected > :first-child {
  animation: selectPulse 0.3s ease-out;
}
</style>
