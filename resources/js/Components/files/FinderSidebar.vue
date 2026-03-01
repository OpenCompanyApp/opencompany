<template>
  <aside
    :class="[
      'border-r border-neutral-200 dark:border-neutral-700 flex flex-col shrink-0 bg-neutral-50/50 dark:bg-neutral-900/50',
      'fixed inset-0 z-30 md:relative md:w-52',
      showMobile ? 'flex' : 'hidden md:flex'
    ]"
  >
    <!-- Mobile close -->
    <div class="flex items-center justify-between px-3 py-3 border-b border-neutral-200 dark:border-neutral-700 md:hidden">
      <span class="text-sm font-medium text-neutral-900 dark:text-white">Files</span>
      <button
        class="p-1.5 rounded-md hover:bg-neutral-100 dark:hover:bg-neutral-800"
        @click="$emit('closeMobile')"
      >
        <Icon name="ph:x" class="w-4 h-4 text-neutral-500" />
      </button>
    </div>

    <!-- Scrollable content -->
    <div class="flex-1 overflow-y-auto py-2">
      <!-- FAVORITES -->
      <div class="px-4 pt-2 pb-1">
        <span class="text-[11px] font-semibold uppercase tracking-wider text-neutral-400 dark:text-neutral-500">
          Favorites
        </span>
      </div>
      <div class="space-y-0.5 px-2">
        <button
          :class="[
            'w-full flex items-center gap-2 px-2 py-1 rounded-md text-sm transition-colors',
            selectedId === null
              ? 'bg-neutral-200 dark:bg-neutral-700 text-neutral-900 dark:text-white font-medium'
              : 'text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800'
          ]"
          @click="$emit('navigate', null)"
        >
          <Icon name="ph:files" class="w-4 h-4 text-blue-500 shrink-0" />
          <span class="truncate">All Files</span>
        </button>
      </div>

      <!-- FOLDERS -->
      <div class="px-4 pt-4 pb-1">
        <span class="text-[11px] font-semibold uppercase tracking-wider text-neutral-400 dark:text-neutral-500">
          Folders
        </span>
      </div>
      <div v-if="loading" class="flex justify-center py-4">
        <Icon name="ph:spinner" class="w-4 h-4 animate-spin text-neutral-400" />
      </div>
      <div v-else class="space-y-0.5 px-2">
        <FinderSidebarItem
          v-for="node in tree"
          :key="node.id"
          :node="node"
          :selected-id="selectedId"
          :depth="0"
          @select="(id: string) => $emit('navigate', id)"
        />
        <p v-if="tree.length === 0" class="px-2 py-2 text-xs text-neutral-400 dark:text-neutral-500 italic">
          No folders yet
        </p>
      </div>
    </div>
  </aside>
</template>

<script setup lang="ts">
import Icon from '@/Components/shared/Icon.vue'
import FinderSidebarItem from './FinderSidebarItem.vue'
import type { FolderTreeNode } from '@/types'

defineProps<{
  tree: FolderTreeNode[]
  selectedId: string | null
  loading: boolean
  showMobile: boolean
}>()

defineEmits<{
  navigate: [folderId: string | null]
  closeMobile: []
}>()
</script>
