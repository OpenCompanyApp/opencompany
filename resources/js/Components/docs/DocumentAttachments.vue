<template>
  <div class="flex flex-col h-full">
    <!-- Header -->
    <div class="p-4 border-b border-neutral-200">
      <div class="flex items-center justify-between">
        <h3 class="font-semibold text-neutral-900">
          Attachments
          <span class="ml-1 text-sm text-neutral-500">({{ attachments.length }})</span>
        </h3>
        <button
          class="p-1.5 rounded-lg hover:bg-neutral-50 text-neutral-500 transition-colors"
          @click="$emit('close')"
        >
          <Icon name="ph:x" class="w-4 h-4" />
        </button>
      </div>
    </div>

    <!-- Upload Area -->
    <div class="p-4 border-b border-neutral-200">
      <div
        ref="dropZoneRef"
        :class="[
          'relative border-2 border-dashed rounded-lg p-4 text-center transition-colors',
          isDragging
            ? 'border-neutral-400 bg-neutral-50'
            : 'border-neutral-200 hover:border-neutral-300'
        ]"
        @dragover.prevent="isDragging = true"
        @dragleave.prevent="isDragging = false"
        @drop.prevent="handleDrop"
      >
        <input
          ref="fileInputRef"
          type="file"
          multiple
          class="hidden"
          @change="handleFileSelect"
        />
        <Icon name="ph:cloud-arrow-up" class="w-8 h-8 mx-auto text-neutral-400 mb-2" />
        <p class="text-sm text-neutral-500">
          Drag & drop files or
          <button
            type="button"
            class="text-neutral-900 font-medium hover:underline"
            @click="fileInputRef?.click()"
          >
            browse
          </button>
        </p>
        <p class="text-xs text-neutral-400 mt-1">Max 10MB per file</p>

        <!-- Upload progress -->
        <div v-if="uploading" class="absolute inset-0 bg-white/80 flex items-center justify-center rounded-lg">
          <div class="flex flex-col items-center gap-2">
            <div class="w-8 h-8 border-2 border-neutral-300 border-t-neutral-600 rounded-full animate-spin" />
            <span class="text-sm text-neutral-600">Uploading...</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Attachments List -->
    <div class="flex-1 overflow-y-auto p-3 space-y-2">
      <TransitionGroup
        name="attachment"
        tag="div"
        class="space-y-2"
      >
        <div
          v-for="attachment in attachments"
          :key="attachment.id"
          class="group flex items-center gap-3 p-3 rounded-lg bg-neutral-50 hover:bg-neutral-100 transition-colors"
        >
          <!-- File icon -->
          <div
            :class="[
              'w-10 h-10 rounded-lg flex items-center justify-center shrink-0',
              getFileTypeColor(attachment.mimeType)
            ]"
          >
            <Icon :name="getFileTypeIcon(attachment.mimeType)" class="w-5 h-5" />
          </div>

          <!-- File info -->
          <div class="flex-1 min-w-0">
            <a
              :href="attachment.url"
              target="_blank"
              class="text-sm font-medium text-neutral-900 hover:text-neutral-600 truncate block"
            >
              {{ attachment.originalName }}
            </a>
            <div class="flex items-center gap-2 text-xs text-neutral-500">
              <span>{{ formatFileSize(attachment.size) }}</span>
              <span class="text-neutral-300">|</span>
              <span>{{ formatDate(attachment.createdAt) }}</span>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
            <a
              :href="attachment.url"
              download
              class="p-1.5 rounded-lg hover:bg-neutral-200 text-neutral-500 transition-colors"
              title="Download"
            >
              <Icon name="ph:download-simple" class="w-4 h-4" />
            </a>
            <button
              type="button"
              class="p-1.5 rounded-lg hover:bg-red-100 text-neutral-500 hover:text-red-600 transition-colors"
              title="Delete"
              @click="handleDelete(attachment)"
            >
              <Icon name="ph:trash" class="w-4 h-4" />
            </button>
          </div>
        </div>
      </TransitionGroup>

      <!-- Empty state -->
      <div v-if="attachments.length === 0 && !loading" class="text-center py-8 text-neutral-500 text-sm">
        No attachments yet
      </div>

      <!-- Loading state -->
      <div v-if="loading" class="flex items-center justify-center py-8">
        <div class="w-6 h-6 border-2 border-neutral-300 border-t-neutral-600 rounded-full animate-spin" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import type { User } from '@/types'
import Icon from '@/Components/shared/Icon.vue'

interface Attachment {
  id: string
  documentId: string
  filename: string
  originalName: string
  mimeType: string
  size: number
  url: string
  uploadedById: string
  createdAt: Date
  uploadedBy?: User
}

const props = defineProps<{
  documentId: string
}>()

const emit = defineEmits<{
  close: []
  change: []
}>()

// Note: useApi would need to be implemented or imported from a composable
// For this migration, we'll define placeholder functions
const fetchDocumentAttachments = async (documentId: string) => {
  // Placeholder - implement actual API call
  return { data: { value: [] as Attachment[] } }
}
const uploadDocumentAttachment = async (documentId: string, file: File) => {
  // Placeholder - implement actual API call
}
const deleteDocumentAttachment = async (documentId: string, attachmentId: string) => {
  // Placeholder - implement actual API call
}

const attachments = ref<Attachment[]>([])
const loading = ref(true)
const uploading = ref(false)
const isDragging = ref(false)
const fileInputRef = ref<HTMLInputElement | null>(null)
const dropZoneRef = ref<HTMLElement | null>(null)

const loadAttachments = async () => {
  loading.value = true
  try {
    const { data } = await fetchDocumentAttachments(props.documentId)
    attachments.value = data.value ?? []
  } catch (e) {
    console.error('Failed to load attachments:', e)
    attachments.value = []
  } finally {
    loading.value = false
  }
}

const handleFileSelect = async (event: Event) => {
  const target = event.target as HTMLInputElement
  if (target.files) {
    await uploadFiles(Array.from(target.files))
    target.value = ''
  }
}

const handleDrop = async (event: DragEvent) => {
  isDragging.value = false
  if (event.dataTransfer?.files) {
    await uploadFiles(Array.from(event.dataTransfer.files))
  }
}

const uploadFiles = async (files: File[]) => {
  const MAX_SIZE = 10 * 1024 * 1024 // 10MB

  for (const file of files) {
    if (file.size > MAX_SIZE) {
      alert(`File "${file.name}" exceeds the 10MB limit`)
      continue
    }

    uploading.value = true
    try {
      await uploadDocumentAttachment(props.documentId, file)
    } catch (e) {
      console.error('Failed to upload file:', e)
      alert(`Failed to upload "${file.name}"`)
    }
  }

  uploading.value = false
  await loadAttachments()
  emit('change')
}

const handleDelete = async (attachment: Attachment) => {
  if (!confirm(`Delete "${attachment.originalName}"?`)) return

  try {
    await deleteDocumentAttachment(props.documentId, attachment.id)
    await loadAttachments()
    emit('change')
  } catch (e) {
    console.error('Failed to delete attachment:', e)
    alert('Failed to delete attachment')
  }
}

const getFileTypeIcon = (mimeType: string): string => {
  if (mimeType.startsWith('image/')) return 'ph:image'
  if (mimeType.startsWith('video/')) return 'ph:video'
  if (mimeType.startsWith('audio/')) return 'ph:music-note'
  if (mimeType.includes('pdf')) return 'ph:file-pdf'
  if (mimeType.includes('word') || mimeType.includes('document')) return 'ph:file-doc'
  if (mimeType.includes('excel') || mimeType.includes('spreadsheet')) return 'ph:file-xls'
  if (mimeType.includes('powerpoint') || mimeType.includes('presentation')) return 'ph:file-ppt'
  if (mimeType.includes('zip') || mimeType.includes('archive') || mimeType.includes('compressed')) return 'ph:file-zip'
  if (mimeType.includes('text') || mimeType.includes('json') || mimeType.includes('javascript')) return 'ph:file-code'
  return 'ph:file'
}

const getFileTypeColor = (mimeType: string): string => {
  if (mimeType.startsWith('image/')) return 'bg-purple-100 text-purple-600'
  if (mimeType.startsWith('video/')) return 'bg-pink-100 text-pink-600'
  if (mimeType.startsWith('audio/')) return 'bg-orange-100 text-orange-600'
  if (mimeType.includes('pdf')) return 'bg-red-100 text-red-600'
  if (mimeType.includes('word') || mimeType.includes('document')) return 'bg-blue-100 text-blue-600'
  if (mimeType.includes('excel') || mimeType.includes('spreadsheet')) return 'bg-green-100 text-green-600'
  if (mimeType.includes('powerpoint') || mimeType.includes('presentation')) return 'bg-amber-100 text-amber-600'
  if (mimeType.includes('zip') || mimeType.includes('archive')) return 'bg-neutral-200 text-neutral-600'
  return 'bg-neutral-100 text-neutral-600'
}

const formatFileSize = (bytes: number): string => {
  if (bytes === 0) return '0 B'
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return `${parseFloat((bytes / Math.pow(k, i)).toFixed(1))} ${sizes[i]}`
}

const formatDate = (date: Date | string): string => {
  const d = new Date(date)
  return d.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
  })
}

// Load attachments when document changes
watch(() => props.documentId, () => {
  loadAttachments()
}, { immediate: true })
</script>

<style scoped>
.attachment-enter-active,
.attachment-leave-active {
  transition: all 0.2s ease;
}

.attachment-enter-from,
.attachment-leave-to {
  opacity: 0;
  transform: translateX(-10px);
}
</style>
