<template>
  <div class="flex flex-col h-full bg-white dark:bg-neutral-900">
    <!-- Header -->
    <div class="p-4 border-b border-neutral-200 dark:border-neutral-700">
      <!-- Title Row -->
      <div class="flex items-center justify-between mb-3">
        <div>
          <h2 class="text-sm font-semibold text-neutral-900 dark:text-white">{{ title }}</h2>
          <p v-if="!loading" class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
            {{ documentCount }} {{ documentCount === 1 ? 'document' : 'documents' }}
            <span v-if="folderCount > 0" class="text-neutral-400 dark:text-neutral-500">
              · {{ folderCount }} {{ folderCount === 1 ? 'folder' : 'folders' }}
            </span>
          </p>
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-1">
          <DropdownMenu :items="createDropdownItems">
            <button
              type="button"
              class="p-1.5 rounded-lg bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 hover:bg-neutral-800 dark:hover:bg-neutral-100 transition-colors"
            >
              <Icon name="ph:plus" class="w-4 h-4" />
            </button>
          </DropdownMenu>
        </div>
      </div>

      <!-- Search -->
      <div class="relative">
        <Icon
          name="ph:magnifying-glass"
          class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-neutral-400 dark:text-neutral-500 pointer-events-none"
        />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search documents..."
          class="w-full pl-9 pr-3 py-2 text-sm bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg outline-none placeholder:text-neutral-400 dark:placeholder:text-neutral-500 text-neutral-900 dark:text-white focus:border-neutral-300 dark:focus:border-neutral-600 focus:ring-1 focus:ring-neutral-300 dark:focus:ring-neutral-600 transition-all"
        />
        <button
          v-if="searchQuery"
          type="button"
          class="absolute right-2 top-1/2 -translate-y-1/2 p-1 rounded hover:bg-neutral-200 dark:hover:bg-neutral-700 transition-colors"
          @click="searchQuery = ''"
        >
          <Icon name="ph:x" class="w-3 h-3 text-neutral-400" />
        </button>
      </div>
    </div>

    <!-- Tree Content -->
    <div class="flex-1 overflow-y-auto p-2">
      <!-- Loading State -->
      <div v-if="loading" class="space-y-1 p-2">
        <div v-for="i in 5" :key="i" class="flex items-center gap-2 p-2 animate-pulse">
          <div class="w-5 h-5 rounded bg-neutral-200 dark:bg-neutral-700" />
          <div class="flex-1 h-4 rounded bg-neutral-200 dark:bg-neutral-700" />
        </div>
      </div>

      <!-- Tree View -->
      <template v-else-if="displayedDocuments.length > 0">
        <DocsDocTreeItem
          v-for="doc in rootDocuments"
          :key="doc.id"
          :item="doc"
          :all-items="displayedDocuments"
          :level="0"
          :selected-id="selected?.id ?? null"
          :highlight-query="searchQuery"
          @select="handleSelect"
        />
      </template>

      <!-- Search Empty State -->
      <div v-else-if="searchQuery" class="flex flex-col items-center justify-center py-12 px-4">
        <div class="w-10 h-10 rounded-full bg-neutral-100 dark:bg-neutral-800 flex items-center justify-center mb-3">
          <Icon name="ph:magnifying-glass" class="w-5 h-5 text-neutral-400 dark:text-neutral-500" />
        </div>
        <p class="text-sm font-medium text-neutral-900 dark:text-white mb-1">No results</p>
        <p class="text-xs text-neutral-500 dark:text-neutral-400 text-center">
          No documents match "{{ searchQuery }}"
        </p>
        <button
          type="button"
          class="mt-3 text-xs text-neutral-500 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white transition-colors"
          @click="searchQuery = ''"
        >
          Clear search
        </button>
      </div>

      <!-- Empty State -->
      <div v-else class="flex flex-col items-center justify-center py-12 px-4">
        <div class="w-12 h-12 rounded-xl bg-neutral-100 dark:bg-neutral-800 flex items-center justify-center mb-3">
          <Icon name="ph:folder-dashed" class="w-6 h-6 text-neutral-400 dark:text-neutral-500" />
        </div>
        <p class="text-sm font-medium text-neutral-900 dark:text-white mb-1">{{ emptyTitle }}</p>
        <p class="text-xs text-neutral-500 dark:text-neutral-400 text-center mb-4">
          {{ emptyDescription }}
        </p>
        <button
          type="button"
          class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 rounded-lg hover:bg-neutral-800 dark:hover:bg-neutral-100 transition-colors"
          @click="emit('create')"
        >
          <Icon name="ph:plus" class="w-3.5 h-3.5" />
          Create document
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import type { Document } from '@/types'
import Icon from '@/Components/shared/Icon.vue'
import DropdownMenu from '@/Components/shared/DropdownMenu.vue'
import DocsDocTreeItem from '@/Components/docs/DocTreeItem.vue'

const props = withDefaults(defineProps<{
  documents: Document[]
  selected?: Document | null
  title?: string
  emptyTitle?: string
  emptyDescription?: string
  loading?: boolean
}>(), {
  selected: null,
  title: 'Documents',
  emptyTitle: 'No documents yet',
  emptyDescription: 'Create your first document to get started',
  loading: false,
})

const emit = defineEmits<{
  select: [doc: Document]
  create: []
  createFolder: []
}>()

// State
const searchQuery = ref('')

// Computed
const documentCount = computed(() =>
  props.documents.filter(d => !d.isFolder).length
)

const folderCount = computed(() =>
  props.documents.filter(d => d.isFolder).length
)

const filteredDocuments = computed(() => {
  if (!searchQuery.value) return props.documents

  const query = searchQuery.value.toLowerCase()
  return props.documents.filter(doc =>
    doc.title.toLowerCase().includes(query) ||
    doc.author?.name?.toLowerCase().includes(query)
  )
})

const displayedDocuments = computed(() => filteredDocuments.value)

const rootDocuments = computed(() =>
  displayedDocuments.value.filter(doc => !doc.parentId)
)

// Create dropdown items
const createDropdownItems = computed(() => [
  [
    {
      label: 'New Document',
      icon: 'ph:file-text',
      click: () => emit('create')
    },
    {
      label: 'New Folder',
      icon: 'ph:folder-simple-plus',
      click: () => emit('createFolder')
    },
  ],
])

// Handlers
const handleSelect = (doc: Document) => {
  emit('select', doc)
}
</script>
