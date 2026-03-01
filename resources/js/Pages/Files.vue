<template>
  <div class="h-full flex flex-col">
    <!-- Toolbar -->
    <FileToolbar
      :can-go-back="canGoBack"
      :can-go-forward="canGoForward"
      :breadcrumbs="breadcrumbs"
      :search-query="searchQuery"
      :view-mode="viewMode"
      :disks="disks"
      :active-disk-id="activeDiskId"
      @back="goBack"
      @forward="goForward"
      @navigate="navigateToFolder"
      @update:search-query="searchQuery = $event; debouncedSearch()"
      @update:view-mode="setViewMode"
      @search="debouncedSearch"
      @new-folder="showNewFolderInput = true"
      @upload="triggerUpload"
      @mobile-toggle="showMobileTree = true"
      @switch-disk="switchDisk"
    />

    <!-- Body: Sidebar + Content -->
    <div class="flex-1 flex min-h-0">
      <!-- Finder Sidebar -->
      <FinderSidebar
        :tree="folderTree"
        :selected-id="currentFolderId"
        :loading="treeLoading"
        :show-mobile="showMobileTree"
        @navigate="handleSidebarNavigate"
        @close-mobile="showMobileTree = false"
      />

      <!-- Main content area -->
      <FileUploadZone
        ref="uploadZoneRef"
        :parent-id="currentFolderId"
        class="flex-1 flex flex-col min-w-0"
        @upload="handleUploadFiles"
      >
        <!-- New Folder Input -->
        <div v-if="showNewFolderInput" class="px-4 py-2 bg-neutral-50 dark:bg-neutral-800/50 border-b border-neutral-200 dark:border-neutral-700 flex items-center gap-2">
          <Icon name="ph:folder-plus" class="w-4 h-4 text-amber-500 shrink-0" />
          <input
            ref="newFolderInputRef"
            v-model="newFolderName"
            type="text"
            placeholder="Folder name"
            class="flex-1 px-2 py-1 rounded border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-sm text-neutral-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-neutral-400"
            @keydown.enter="handleCreateFolder"
            @keydown.escape="showNewFolderInput = false"
          />
          <button
            class="px-2.5 py-1 rounded-md bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 text-xs font-medium hover:opacity-90 transition-opacity disabled:opacity-40"
            :disabled="!newFolderName.trim()"
            @click="handleCreateFolder"
          >
            Create
          </button>
          <button class="text-xs text-neutral-500 hover:text-neutral-700 dark:hover:text-neutral-300" @click="showNewFolderInput = false">Cancel</button>
        </div>

        <!-- Content area -->
        <div class="flex-1 overflow-y-auto" @click.self="clearSelection">
          <!-- Loading -->
          <div v-if="filesLoading" class="flex justify-center py-12">
            <Icon name="ph:spinner" class="w-6 h-6 animate-spin text-neutral-400" />
          </div>

          <!-- Empty state -->
          <div v-else-if="sortedFiles.length === 0" class="flex flex-col items-center justify-center py-16">
            <Icon :name="searchQuery ? 'ph:magnifying-glass' : 'ph:folder-dashed'" class="w-16 h-16 text-neutral-200 dark:text-neutral-700 mb-4" />
            <p class="text-sm text-neutral-500 dark:text-neutral-400 mb-1">
              {{ searchQuery ? 'No files found' : 'This folder is empty' }}
            </p>
            <p class="text-xs text-neutral-400 dark:text-neutral-500">
              {{ searchQuery ? 'Try a different search term' : 'Drop files here or click + to get started' }}
            </p>
          </div>

          <!-- Grid View -->
          <div v-else-if="viewMode === 'grid'" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-1 p-4">
            <ContextMenu v-for="file in sortedFiles" :key="file.id" :items="getContextMenuItems(file)">
              <button
                :class="[
                  'group flex flex-col items-center gap-1.5 p-4 rounded-lg border transition-colors text-center w-full',
                  selectedIds.has(file.id)
                    ? 'border-blue-300 dark:border-blue-700 bg-blue-50/50 dark:bg-blue-950/20 ring-2 ring-blue-500/30'
                    : 'border-transparent hover:bg-neutral-50 dark:hover:bg-neutral-800/50'
                ]"
                @click="selectFile(file, $event)"
                @dblclick="openFile(file)"
              >
                <!-- Thumbnail for images -->
                <div v-if="file.mimeType?.startsWith('image/') && file.downloadUrl" class="w-12 h-12 rounded-md overflow-hidden bg-neutral-100 dark:bg-neutral-800">
                  <img :src="file.downloadUrl" :alt="file.name" class="w-full h-full object-cover" loading="lazy" />
                </div>
                <FileIcon
                  v-else
                  :mime-type="file.mimeType"
                  :is-folder="file.isFolder"
                  :class="['w-12 h-12', file.isFolder ? 'text-amber-500' : 'text-neutral-400 dark:text-neutral-500']"
                />
                <FileInlineRename
                  v-model="renameValue"
                  :display-name="file.name"
                  :editing="renamingId === file.id"
                  label-class="text-xs text-neutral-700 dark:text-neutral-300 truncate max-w-full block"
                  @commit="commitRename"
                  @cancel="cancelRename"
                />
                <span v-if="!file.isFolder && file.size" class="text-[10px] text-neutral-400">{{ formatSize(file.size) }}</span>
              </button>
            </ContextMenu>
          </div>

          <!-- List View -->
          <div v-else>
            <!-- Column headers -->
            <div class="grid grid-cols-12 gap-2 px-3 py-1.5 border-b border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-900 sticky top-0 z-10">
              <button
                class="col-span-6 flex items-center gap-1 text-[11px] font-medium text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 transition-colors text-left uppercase tracking-wider"
                @click="toggleSort('name')"
              >
                Name
                <Icon v-if="sortField === 'name'" :name="sortDirection === 'asc' ? 'ph:caret-up' : 'ph:caret-down'" class="w-3 h-3" />
              </button>
              <button
                class="col-span-2 hidden sm:flex items-center gap-1 text-[11px] font-medium text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 transition-colors uppercase tracking-wider"
                @click="toggleSort('updatedAt')"
              >
                Modified
                <Icon v-if="sortField === 'updatedAt'" :name="sortDirection === 'asc' ? 'ph:caret-up' : 'ph:caret-down'" class="w-3 h-3" />
              </button>
              <button
                class="col-span-2 hidden sm:flex items-center gap-1 text-[11px] font-medium text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 transition-colors uppercase tracking-wider"
                @click="toggleSort('size')"
              >
                Size
                <Icon v-if="sortField === 'size'" :name="sortDirection === 'asc' ? 'ph:caret-up' : 'ph:caret-down'" class="w-3 h-3" />
              </button>
              <button
                class="col-span-2 hidden sm:flex items-center gap-1 text-[11px] font-medium text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 transition-colors uppercase tracking-wider"
                @click="toggleSort('mimeType')"
              >
                Kind
                <Icon v-if="sortField === 'mimeType'" :name="sortDirection === 'asc' ? 'ph:caret-up' : 'ph:caret-down'" class="w-3 h-3" />
              </button>
            </div>

            <!-- Rows -->
            <div class="divide-y divide-neutral-100 dark:divide-neutral-800">
              <ContextMenu v-for="file in sortedFiles" :key="file.id" :items="getContextMenuItems(file)">
                <button
                  :class="[
                    'w-full grid grid-cols-12 gap-2 px-3 py-1.5 transition-colors items-center text-left',
                    selectedIds.has(file.id)
                      ? 'bg-blue-50 dark:bg-blue-950/30'
                      : 'hover:bg-neutral-50 dark:hover:bg-neutral-800/50'
                  ]"
                  @click="selectFile(file, $event)"
                  @dblclick="openFile(file)"
                >
                  <div class="col-span-6 flex items-center gap-2 min-w-0">
                    <FileIcon
                      :mime-type="file.mimeType"
                      :is-folder="file.isFolder"
                      :class="['w-4 h-4 shrink-0', file.isFolder ? 'text-amber-500' : 'text-neutral-400']"
                    />
                    <FileInlineRename
                      v-model="renameValue"
                      :display-name="file.name"
                      :editing="renamingId === file.id"
                      label-class="text-sm text-neutral-900 dark:text-white truncate"
                      class="min-w-0 flex-1"
                      @commit="commitRename"
                      @cancel="cancelRename"
                    />
                  </div>
                  <div class="col-span-2 text-xs text-neutral-500 hidden sm:block">
                    {{ formatRelative(file.updatedAt) }}
                  </div>
                  <div class="col-span-2 text-xs text-neutral-500 hidden sm:block">
                    {{ file.isFolder ? `${file.childCount ?? 0} items` : formatSize(file.size || 0) }}
                  </div>
                  <div class="col-span-2 text-xs text-neutral-500 hidden sm:block truncate">
                    {{ getKindLabel(file) }}
                  </div>
                </button>
              </ContextMenu>
            </div>
          </div>
        </div>
      </FileUploadZone>
    </div>

    <!-- Status Bar -->
    <FileStatusBar
      :total-items="sortedFiles.length"
      :selected-count="selectedIds.size"
    />

    <!-- Preview Slideover -->
    <FilePreview
      :file="previewFile"
      @close="previewFile = null"
      @delete="confirmDelete"
    />

    <!-- Delete Confirmation -->
    <ConfirmDialog
      :open="showDeleteDialog"
      variant="danger"
      title="Delete File"
      :description="`Are you sure you want to delete '${fileToDelete?.name}'? This action cannot be undone.`"
      confirm-label="Delete"
      @update:open="showDeleteDialog = $event"
      @confirm="executeDelete"
      @cancel="showDeleteDialog = false"
    />

    <!-- Hidden file input -->
    <input
      ref="fileInputRef"
      type="file"
      multiple
      class="hidden"
      @change="handleFileInput"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, watch, nextTick, onMounted, onUnmounted } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import ContextMenu from '@/Components/shared/ContextMenu.vue'
import ConfirmDialog from '@/Components/shared/ConfirmDialog.vue'
import FileToolbar from '@/Components/files/FileToolbar.vue'
import FileStatusBar from '@/Components/files/FileStatusBar.vue'
import FinderSidebar from '@/Components/files/FinderSidebar.vue'
import FileIcon from '@/Components/files/FileIcon.vue'
import FileInlineRename from '@/Components/files/FileInlineRename.vue'
import FileUploadZone from '@/Components/files/FileUploadZone.vue'
import FilePreview from '@/Components/files/FilePreview.vue'
import { useFileManager } from '@/composables/useFileManager'

const {
  // Core state
  currentFolderId, filesLoading, folderTree, treeLoading, searchQuery, previewFile,
  // Disks
  disks, activeDiskId, loadDisks, switchDisk,
  // View
  viewMode, setViewMode,
  // Navigation
  canGoBack, canGoForward, goBack, goForward, navigateToFolder, openFile, breadcrumbs,
  // Selection
  selectedIds, selectFile, clearSelection, selectAll,
  // Sorting
  sortField, sortDirection, sortedFiles, toggleSort,
  // Inline rename
  renamingId, renameValue, startRename, commitRename, cancelRename,
  // Delete
  showDeleteDialog, fileToDelete, confirmDelete, executeDelete,
  // New folder
  showNewFolderInput, newFolderName, handleCreateFolder,
  // Operations
  loadTree, loadFiles, handleUpload, debouncedSearch,
  // Helpers
  getKindLabel, formatSize, formatRelative, getContextMenuItems,
} = useFileManager()

// Local UI state
const showMobileTree = ref(false)
const fileInputRef = ref<HTMLInputElement>()
const newFolderInputRef = ref<HTMLInputElement>()
const uploadZoneRef = ref<InstanceType<typeof FileUploadZone>>()

const handleSidebarNavigate = (folderId: string | null) => {
  navigateToFolder(folderId)
  showMobileTree.value = false
}

const triggerUpload = () => fileInputRef.value?.click()

const handleFileInput = async (e: Event) => {
  const input = e.target as HTMLInputElement
  const fileList = Array.from(input.files || [])
  if (fileList.length > 0) {
    await handleUploadFiles(fileList)
  }
  input.value = ''
}

const handleUploadFiles = (fileList: File[]) => {
  handleUpload(fileList, uploadZoneRef.value)
}

// Auto-focus new folder input
watch(showNewFolderInput, (val) => {
  if (val) nextTick(() => newFolderInputRef.value?.focus())
})

// Keyboard shortcuts
const handleKeyDown = (e: KeyboardEvent) => {
  // Don't fire when typing in inputs
  const target = e.target as HTMLElement
  if (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.isContentEditable) return

  if ((e.key === 'Backspace' || e.key === 'Delete') && selectedIds.size > 0) {
    e.preventDefault()
    const file = sortedFiles.value.find(f => selectedIds.has(f.id))
    if (file) confirmDelete(file)
  } else if (e.key === 'Enter' && selectedIds.size === 1) {
    e.preventDefault()
    const file = sortedFiles.value.find(f => selectedIds.has(f.id))
    if (file) startRename(file)
  } else if (e.key === ' ' && selectedIds.size === 1) {
    e.preventDefault()
    const file = sortedFiles.value.find(f => selectedIds.has(f.id))
    if (file && !file.isFolder) previewFile.value = file
  } else if ((e.metaKey || e.ctrlKey) && e.key === 'a') {
    e.preventDefault()
    selectAll()
  } else if (e.key === 'Escape') {
    clearSelection()
    cancelRename()
  } else if ((e.metaKey || e.ctrlKey) && e.shiftKey && e.key === 'n') {
    e.preventDefault()
    showNewFolderInput.value = true
  }
}

onMounted(() => {
  loadDisks()
  loadTree()
  loadFiles()
  window.addEventListener('keydown', handleKeyDown)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeyDown)
})
</script>
