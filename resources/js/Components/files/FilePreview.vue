<template>
  <Slideover :open="!!file" @close="$emit('close')">
    <template #header>
      <div class="flex items-center gap-3 min-w-0">
        <FileIcon :mime-type="file?.mimeType" :is-folder="file?.isFolder" class="w-5 h-5 shrink-0" />
        <span class="font-medium text-neutral-900 dark:text-white truncate">{{ file?.name }}</span>
      </div>
    </template>

    <div v-if="file" class="space-y-6">
      <!-- Preview -->
      <div v-if="file.mimeType?.startsWith('image/')" class="rounded-lg overflow-hidden bg-neutral-100 dark:bg-neutral-800">
        <img :src="file.downloadUrl" :alt="file.name" class="max-w-full max-h-80 mx-auto object-contain" />
      </div>

      <div v-else-if="file.mimeType === 'application/pdf'" class="rounded-lg overflow-hidden bg-neutral-100 dark:bg-neutral-800 h-96">
        <iframe :src="file.downloadUrl" class="w-full h-full border-0" />
      </div>

      <div v-else-if="file.mimeType?.startsWith('text/')" class="rounded-lg bg-neutral-50 dark:bg-neutral-800 p-4 max-h-96 overflow-auto">
        <pre class="text-xs text-neutral-700 dark:text-neutral-300 whitespace-pre-wrap font-mono">{{ textContent }}</pre>
      </div>

      <div v-else class="flex flex-col items-center gap-3 py-8">
        <FileIcon :mime-type="file.mimeType" class="w-16 h-16 text-neutral-300 dark:text-neutral-600" />
        <p class="text-sm text-neutral-500 dark:text-neutral-400">Preview not available</p>
      </div>

      <!-- Actions -->
      <div class="flex gap-2">
        <a
          v-if="file.downloadUrl"
          :href="file.downloadUrl"
          download
          class="flex-1 flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 text-sm font-medium hover:opacity-90 transition-opacity"
        >
          <Icon name="ph:download-simple" class="w-4 h-4" />
          Download
        </a>
        <button
          class="px-4 py-2 rounded-lg border border-red-200 dark:border-red-800 text-red-600 dark:text-red-400 text-sm hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
          @click="$emit('delete', file)"
        >
          <Icon name="ph:trash" class="w-4 h-4" />
        </button>
      </div>

      <!-- General Info -->
      <div>
        <span class="text-[11px] font-semibold uppercase tracking-wider text-neutral-400 dark:text-neutral-500">General</span>
        <div class="mt-2 space-y-2.5 text-sm">
          <div class="flex justify-between">
            <span class="text-neutral-500 dark:text-neutral-400">Kind</span>
            <span class="text-neutral-900 dark:text-white">{{ file.mimeType || 'Unknown' }}</span>
          </div>
          <div v-if="file.size" class="flex justify-between">
            <span class="text-neutral-500 dark:text-neutral-400">Size</span>
            <span class="text-neutral-900 dark:text-white">{{ formatSize(file.size) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-neutral-500 dark:text-neutral-400">Where</span>
            <span class="text-neutral-900 dark:text-white font-mono text-xs">{{ file.virtualPath }}</span>
          </div>
        </div>
      </div>

      <!-- Dates -->
      <div>
        <span class="text-[11px] font-semibold uppercase tracking-wider text-neutral-400 dark:text-neutral-500">More Info</span>
        <div class="mt-2 space-y-2.5 text-sm">
          <div class="flex justify-between">
            <span class="text-neutral-500 dark:text-neutral-400">Created</span>
            <span class="text-neutral-900 dark:text-white">{{ formatDate(file.createdAt) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-neutral-500 dark:text-neutral-400">Modified</span>
            <span class="text-neutral-900 dark:text-white">{{ formatDate(file.updatedAt) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-neutral-500 dark:text-neutral-400">Owner</span>
            <span class="text-neutral-900 dark:text-white">{{ file.owner?.name || 'Unknown' }}</span>
          </div>
        </div>
      </div>
    </div>
  </Slideover>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import Slideover from '@/Components/shared/Slideover.vue'
import FileIcon from './FileIcon.vue'
import Icon from '@/Components/shared/Icon.vue'
import type { WorkspaceFile } from '@/types'

const props = defineProps<{
  file: WorkspaceFile | null
}>()

defineEmits<{
  close: []
  delete: [file: WorkspaceFile]
}>()

const textContent = ref('')

const formatSize = (bytes: number): string => {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  if (bytes < 1024 * 1024 * 1024) return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
  return `${(bytes / (1024 * 1024 * 1024)).toFixed(1)} GB`
}

const formatDate = (date: string): string => {
  return new Date(date).toLocaleDateString(undefined, {
    year: 'numeric', month: 'short', day: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}

// Load text content for text files
watch(() => props.file, async (file) => {
  textContent.value = ''
  if (file && file.mimeType?.startsWith('text/') && file.downloadUrl) {
    try {
      const res = await fetch(file.downloadUrl)
      textContent.value = await res.text()
    } catch {
      textContent.value = 'Failed to load file content.'
    }
  }
})
</script>
