<template>
  <div class="flex items-center gap-3 px-3 py-2 border-b border-neutral-200 dark:border-neutral-700 bg-neutral-50/80 dark:bg-neutral-900/80 backdrop-blur-sm shrink-0">
    <!-- Mobile sidebar toggle -->
    <button
      class="md:hidden p-1.5 rounded-md text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors"
      @click="$emit('mobileToggle')"
    >
      <Icon name="ph:sidebar" class="w-4 h-4" />
    </button>

    <!-- Back / Forward -->
    <div class="hidden md:flex items-center gap-0.5">
      <button
        :disabled="!canGoBack"
        class="p-1.5 rounded-md text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800 disabled:opacity-30 disabled:cursor-default transition-colors"
        @click="$emit('back')"
      >
        <Icon name="ph:caret-left" class="w-4 h-4" />
      </button>
      <button
        :disabled="!canGoForward"
        class="p-1.5 rounded-md text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800 disabled:opacity-30 disabled:cursor-default transition-colors"
        @click="$emit('forward')"
      >
        <Icon name="ph:caret-right" class="w-4 h-4" />
      </button>
    </div>

    <!-- Disk selector (only visible with multiple disks) -->
    <div v-if="disks.length > 1" class="hidden md:block shrink-0">
      <DropdownMenu :items="diskMenuItems" align="start">
        <button class="flex items-center gap-1.5 px-2 py-1 rounded-md text-xs font-medium text-neutral-600 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 transition-colors">
          <Icon :name="activeDiskIcon" class="w-3.5 h-3.5" />
          {{ activeDiskLabel }}
          <Icon name="ph:caret-down" class="w-3 h-3 text-neutral-400" />
        </button>
      </DropdownMenu>
    </div>

    <!-- Breadcrumbs -->
    <FileBreadcrumb :segments="breadcrumbs" class="flex-1 min-w-0" @navigate="(id) => $emit('navigate', id)" />

    <!-- Right actions -->
    <div class="flex items-center gap-2 shrink-0">
      <!-- Search -->
      <div class="relative hidden sm:block">
        <Icon name="ph:magnifying-glass" class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-neutral-400" />
        <input
          :value="searchQuery"
          type="text"
          placeholder="Search"
          class="pl-8 pr-3 py-1 rounded-md border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-sm text-neutral-900 dark:text-white placeholder-neutral-400 w-36 focus:w-52 focus:outline-none focus:ring-1 focus:ring-neutral-400 transition-all"
          @input="$emit('update:searchQuery', ($event.target as HTMLInputElement).value)"
          @keydown.enter="$emit('search', ($event.target as HTMLInputElement).value)"
        />
      </div>

      <!-- Plus menu -->
      <DropdownMenu :items="plusMenuItems" align="end">
        <button class="p-1.5 rounded-md text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors">
          <Icon name="ph:plus" class="w-4 h-4" />
        </button>
      </DropdownMenu>

      <!-- Segmented view toggle -->
      <div class="flex items-center bg-neutral-100 dark:bg-neutral-800 rounded-lg p-0.5">
        <button
          :class="[
            'px-2 py-1 rounded-md transition-all',
            viewMode === 'grid'
              ? 'bg-white dark:bg-neutral-700 text-neutral-900 dark:text-white shadow-sm'
              : 'text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200'
          ]"
          @click="$emit('update:viewMode', 'grid')"
        >
          <Icon name="ph:squares-four" class="w-3.5 h-3.5" />
        </button>
        <button
          :class="[
            'px-2 py-1 rounded-md transition-all',
            viewMode === 'list'
              ? 'bg-white dark:bg-neutral-700 text-neutral-900 dark:text-white shadow-sm'
              : 'text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200'
          ]"
          @click="$emit('update:viewMode', 'list')"
        >
          <Icon name="ph:list" class="w-3.5 h-3.5" />
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import DropdownMenu from '@/Components/shared/DropdownMenu.vue'
import FileBreadcrumb from './FileBreadcrumb.vue'
import type { WorkspaceDisk } from '@/types'

const props = defineProps<{
  canGoBack: boolean
  canGoForward: boolean
  breadcrumbs: Array<{ id: string; name: string }>
  searchQuery: string
  viewMode: 'grid' | 'list'
  disks: WorkspaceDisk[]
  activeDiskId: string | null
}>()

const emit = defineEmits<{
  back: []
  forward: []
  navigate: [folderId: string | null]
  'update:searchQuery': [value: string]
  'update:viewMode': [value: 'grid' | 'list']
  search: [query: string]
  newFolder: []
  upload: []
  mobileToggle: []
  switchDisk: [diskId: string | null]
}>()

const driverIcon = (driver: string) => {
  switch (driver) {
    case 's3': return 'ph:cloud'
    case 'sftp': return 'ph:plugs-connected'
    default: return 'ph:hard-drive'
  }
}

const activeDisk = computed(() =>
  props.disks.find(d => d.id === props.activeDiskId) ?? null
)

const activeDiskLabel = computed(() =>
  activeDisk.value?.name ?? 'All Disks'
)

const activeDiskIcon = computed(() =>
  activeDisk.value ? driverIcon(activeDisk.value.driver) : 'ph:hard-drives'
)

const diskMenuItems = computed(() => {
  const items: any[] = [
    { label: 'All Disks', icon: 'ph:hard-drives', click: () => emit('switchDisk', null) },
  ]
  if (props.disks.length > 0) {
    items.push(
      props.disks.map(d => ({
        label: d.name + (d.isDefault ? ' (default)' : ''),
        icon: driverIcon(d.driver),
        click: () => emit('switchDisk', d.id),
      }))
    )
  }
  return items
})

const plusMenuItems = computed(() => [
  { label: 'New Folder', icon: 'ph:folder-plus', shortcut: '⇧⌘N', click: () => emit('newFolder') },
  { label: 'Upload Files', icon: 'ph:upload-simple', click: () => emit('upload') },
])
</script>
