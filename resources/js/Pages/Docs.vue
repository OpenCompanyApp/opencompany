<template>
  <div class="h-full flex flex-col md:flex-row">
    <!-- Mobile Toolbar -->
    <div class="md:hidden flex items-center gap-2 p-3 border-b border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 shrink-0">
      <button
        class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-neutral-100 dark:bg-neutral-800 text-sm text-neutral-700 dark:text-neutral-200"
        @click="showMobileDocList = true"
      >
        <Icon name="ph:list" class="w-4 h-4" />
        Docs
      </button>
      <span class="flex-1 text-sm font-medium text-neutral-900 dark:text-white truncate">
        {{ selectedDoc?.title || 'Select a document' }}
      </span>
      <button
        v-if="selectedDoc"
        class="p-2 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-800 text-neutral-500 dark:text-neutral-400 relative"
        @click="showAttachmentsSidebar = true"
      >
        <Icon name="ph:paperclip" class="w-5 h-5" />
        <span v-if="attachmentCount > 0" class="absolute -top-1 -right-1 w-4 h-4 rounded-full bg-neutral-900 text-white text-[10px] flex items-center justify-center">
          {{ attachmentCount }}
        </span>
      </button>
      <button
        v-if="selectedDoc"
        class="p-2 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-800 text-neutral-500 dark:text-neutral-400 relative"
        @click="showCommentsSidebar = true"
      >
        <Icon name="ph:chat-circle" class="w-5 h-5" />
        <span v-if="comments.length > 0" class="absolute -top-1 -right-1 w-4 h-4 rounded-full bg-neutral-900 text-white text-[10px] flex items-center justify-center">
          {{ comments.length }}
        </span>
      </button>
    </div>

    <!-- Document List Sidebar - Desktop always visible, Mobile as overlay -->
    <aside
      :class="[
        'border-r border-neutral-200 dark:border-neutral-700 flex flex-col shrink-0 bg-white dark:bg-neutral-900',
        'fixed inset-0 z-30 md:relative md:w-56',
        showMobileDocList ? 'flex' : 'hidden md:flex'
      ]"
    >
      <!-- Mobile close button -->
      <button
        class="md:hidden absolute top-3 right-3 p-2 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-800 z-10"
        @click="showMobileDocList = false"
      >
        <Icon name="ph:x" class="w-5 h-5 text-neutral-500 dark:text-neutral-300" />
      </button>
      <DocsDocList
        :documents="filteredDocuments"
        :selected="selectedDoc"
        title="Documents"
        @select="handleSelectDocumentMobile"
        @create="handleCreateDocument"
        @create-folder="handleCreateFolder"
        @delete="handleDeleteDocument"
        @rename="handleRenameDocument"
      />
    </aside>

    <!-- Document Viewer -->
    <DocsDocViewer
      :document="selectedDoc"
      :comments="comments"
      :is-editing="isEditing"
      :has-changes="hasChanges"
      :saving="saving"
      class="flex-1"
      @edit="handleStartEdit"
      @cancel="handleCancelEdit"
      @save="handleSaveDocument"
      @content-change="handleContentChange"
      @update:color="handleUpdateColor"
      @update:icon="handleUpdateIcon"
      @version-history="showVersionHistory = true"
    />

    <!-- Version History Panel -->
    <Transition name="slide-left">
      <aside v-if="showVersionHistory" class="fixed inset-0 z-30 md:relative md:inset-auto w-full md:w-80 bg-white dark:bg-neutral-900 md:border-l border-neutral-200 dark:border-neutral-700 flex flex-col shrink-0">
        <div class="p-4 border-b border-neutral-200 dark:border-neutral-700">
          <div class="flex items-center justify-between">
            <h3 class="font-semibold text-neutral-900 dark:text-white">Version History</h3>
            <button
              class="p-1.5 rounded-lg hover:bg-neutral-50 dark:hover:bg-neutral-800 text-neutral-500 dark:text-neutral-300 transition-colors"
              @click="showVersionHistory = false"
            >
              <Icon name="ph:x" class="w-4 h-4" />
            </button>
          </div>
        </div>

        <!-- Compare Button -->
        <div v-if="versions.length >= 1" class="p-3 border-b border-neutral-200 dark:border-neutral-700">
          <button
            class="w-full px-3 py-2 text-sm bg-neutral-900 text-white rounded-lg hover:bg-neutral-800 transition-colors flex items-center justify-center gap-2"
            @click="showDiffViewer = true"
          >
            <Icon name="ph:git-diff" class="w-4 h-4" />
            Compare Versions
          </button>
        </div>

        <div class="flex-1 overflow-y-auto p-3 space-y-2">
          <div
            v-for="version in versions"
            :key="version.id"
            class="p-3 rounded-lg bg-neutral-50 dark:bg-neutral-800 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors border border-neutral-200 dark:border-neutral-700"
          >
            <div class="flex items-center justify-between mb-2">
              <span class="text-sm font-medium text-neutral-700 dark:text-neutral-200">
                Version {{ version.versionNumber }}
              </span>
              <span class="text-xs text-neutral-500 dark:text-neutral-300">
                {{ formatDate(version.createdAt) }}
              </span>
            </div>
            <div v-if="version.author" class="flex items-center gap-2 mb-2">
              <SharedAgentAvatar :user="version.author" size="xs" />
              <span class="text-xs text-neutral-500 dark:text-neutral-300">{{ version.author.name }}</span>
            </div>
            <p v-if="version.changeDescription" class="text-xs text-neutral-500 dark:text-neutral-300 line-clamp-2 mb-2">
              {{ version.changeDescription }}
            </p>
            <div class="flex items-center gap-2">
              <button
                class="text-xs text-neutral-500 dark:text-neutral-300 hover:text-neutral-700 dark:hover:text-neutral-200 transition-colors"
                @click="handleCompareVersion(version)"
              >
                Compare
              </button>
              <span class="text-neutral-300 dark:text-neutral-600">|</span>
              <button
                class="text-xs text-neutral-500 dark:text-neutral-300 hover:text-neutral-700 dark:hover:text-neutral-200 transition-colors"
                @click="handleRestoreVersion(version)"
              >
                Restore
              </button>
            </div>
          </div>

          <div v-if="versions.length === 0" class="text-center py-8 text-neutral-500 dark:text-neutral-300 text-sm">
            No version history yet
          </div>
        </div>
      </aside>
    </Transition>

    <!-- Diff Viewer Modal -->
    <DocsDocumentDiffViewer
      v-if="selectedDoc"
      :open="showDiffViewer"
      :versions="versions"
      :current-document="selectedDoc"
      @update:open="showDiffViewer = $event"
      @restore="handleRestoreFromDiff"
    />

    <!-- Attachments Sidebar -->
    <Transition name="slide-left">
      <aside v-if="showAttachmentsSidebar && selectedDoc" class="fixed inset-0 z-30 md:relative md:inset-auto w-full md:w-80 bg-white dark:bg-neutral-900 md:border-l border-neutral-200 dark:border-neutral-700 flex flex-col shrink-0">
        <DocsDocumentAttachments
          :document-id="selectedDoc.id"
          @close="showAttachmentsSidebar = false"
          @change="loadAttachmentCount"
        />
      </aside>
    </Transition>

    <!-- Comment Sidebar -->
    <Transition name="slide-left">
      <aside v-if="showCommentsSidebar" class="fixed inset-0 z-30 md:relative md:inset-auto w-full md:w-80 bg-white dark:bg-neutral-900 md:border-l border-neutral-200 dark:border-neutral-700 flex flex-col shrink-0">
        <div class="p-4 border-b border-neutral-200 dark:border-neutral-700">
          <div class="flex items-center justify-between">
            <h3 class="font-semibold text-neutral-900 dark:text-white">
              Comments
              <span class="ml-1 text-sm text-neutral-500 dark:text-neutral-300">({{ comments.length }})</span>
            </h3>
            <button
              class="p-1.5 rounded-lg hover:bg-neutral-50 dark:hover:bg-neutral-800 text-neutral-500 dark:text-neutral-300 transition-colors"
              @click="showCommentsSidebar = false"
            >
              <Icon name="ph:x" class="w-4 h-4" />
            </button>
          </div>
        </div>

        <!-- Add Comment Form -->
        <div class="p-4 border-b border-neutral-200 dark:border-neutral-700">
          <textarea
            v-model="newCommentContent"
            placeholder="Add a comment..."
            class="w-full bg-neutral-50 dark:bg-neutral-800 rounded-lg p-3 text-sm resize-none outline-none border border-neutral-200 dark:border-neutral-700 focus:border-neutral-300 dark:text-white dark:placeholder:text-neutral-400"
            rows="3"
          />
          <button
            class="mt-2 w-full btn-primary"
            :disabled="!newCommentContent.trim()"
            @click="handleAddComment"
          >
            Add Comment
          </button>
        </div>

        <!-- Comments List -->
        <div class="flex-1 overflow-y-auto p-3 space-y-3">
          <DocsCommentThread
            v-for="comment in comments"
            :key="comment.id"
            :comment="comment"
            @reply="handleReplyToComment"
            @resolve="handleResolveComment"
            @delete="handleDeleteComment"
          />

          <div v-if="comments.length === 0" class="text-center py-8 text-neutral-500 dark:text-neutral-300 text-sm">
            No comments yet
          </div>
        </div>
      </aside>
    </Transition>

    <!-- Floating Actions (desktop only, mobile uses toolbar) -->
    <div class="hidden md:flex fixed bottom-6 right-6 items-center gap-2">
      <button
        v-if="selectedDoc"
        class="p-3 rounded-full bg-neutral-100 dark:bg-neutral-700 shadow-lg border border-neutral-200 dark:border-neutral-600 hover:bg-neutral-50 dark:hover:bg-neutral-600 transition-colors relative"
        title="Attachments"
        @click="showAttachmentsSidebar = !showAttachmentsSidebar"
      >
        <Icon name="ph:paperclip" class="w-5 h-5 text-neutral-700 dark:text-neutral-200" />
        <span v-if="attachmentCount > 0" class="absolute -top-1 -right-1 w-5 h-5 rounded-full bg-neutral-900 text-white text-xs flex items-center justify-center">
          {{ attachmentCount }}
        </span>
      </button>
      <button
        v-if="selectedDoc"
        class="p-3 rounded-full bg-neutral-100 dark:bg-neutral-700 shadow-lg border border-neutral-200 dark:border-neutral-600 hover:bg-neutral-50 dark:hover:bg-neutral-600 transition-colors relative"
        title="Comments"
        @click="showCommentsSidebar = !showCommentsSidebar"
      >
        <Icon name="ph:chat-circle" class="w-5 h-5 text-neutral-700 dark:text-neutral-200" />
        <span v-if="comments.length > 0" class="absolute -top-1 -right-1 w-5 h-5 rounded-full bg-neutral-900 text-white text-xs flex items-center justify-center">
          {{ comments.length }}
        </span>
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import type { Document, User } from '@/types'
import DocsDocList from '@/Components/docs/DocList.vue'
import DocsDocViewer from '@/Components/docs/DocViewer.vue'
import DocsDocumentDiffViewer from '@/Components/docs/DocumentDiffViewer.vue'
import DocsDocumentAttachments from '@/Components/docs/DocumentAttachments.vue'
import DocsCommentThread from '@/Components/docs/CommentThread.vue'
import SharedAgentAvatar from '@/Components/shared/AgentAvatar.vue'
import Icon from '@/Components/shared/Icon.vue'
import { useApi } from '@/composables/useApi'
import { useRealtime } from '@/composables/useRealtime'

interface DocumentComment {
  id: string
  documentId: string
  authorId: string
  content: string
  parentId: string | null
  resolved: boolean
  resolvedById: string | null
  resolvedAt: Date | null
  createdAt: Date
  updatedAt: Date
  author?: User
  resolvedBy?: User
  replies?: DocumentComment[]
}

interface DocumentVersion {
  id: string
  documentId: string
  title: string
  content: string
  authorId: string
  versionNumber: number
  changeDescription: string | null
  createdAt: Date
  author?: User
}

const {
  fetchDocuments,
  createDocument,
  updateDocument,
  deleteDocument,
  fetchDocumentComments,
  addDocumentComment,
  updateDocumentComment,
  deleteDocumentComment,
  fetchDocumentVersions,
  restoreDocumentVersion,
  fetchDocumentAttachments,
} = useApi()
const { on } = useRealtime()

// Fetch documents from API
const { data: documentsData, refresh: refreshDocuments } = fetchDocuments()

const documents = computed<Document[]>(() => documentsData.value ?? [])

// Select the first actual document (not a folder)
const selectedDoc = ref<Document | null>(null)
const isEditing = ref(false)
const hasChanges = ref(false)
const saving = ref(false)
const editedContent = ref('')
const showVersionHistory = ref(false)
const showCommentsSidebar = ref(false)
const showMobileDocList = ref(false)
const showAttachmentsSidebar = ref(false)
const showDiffViewer = ref(false)
const newCommentContent = ref('')
const attachmentCount = ref(0)

// Comments and versions for selected document
const comments = ref<DocumentComment[]>([])
const versions = ref<DocumentVersion[]>([])

// Initialize selected document
watch(documents, (docs) => {
  if (!selectedDoc.value && docs.length > 0) {
    const firstDocument = docs.find(doc => !doc.isFolder)
    selectedDoc.value = firstDocument ?? docs[0]
  }
}, { immediate: true })

// Fetch comments, versions, and attachment count when document changes
watch(selectedDoc, async (doc) => {
  if (doc) {
    await Promise.all([
      loadComments(doc.id),
      loadVersions(doc.id),
      loadAttachmentCount(doc.id),
    ])
  } else {
    comments.value = []
    versions.value = []
    attachmentCount.value = 0
  }
}, { immediate: true })

const loadComments = async (documentId: string) => {
  try {
    const { data } = await fetchDocumentComments(documentId)
    comments.value = data.value ?? []
  } catch {
    comments.value = []
  }
}

const loadVersions = async (documentId: string) => {
  try {
    const { data } = await fetchDocumentVersions(documentId)
    versions.value = data.value ?? []
  } catch {
    versions.value = []
  }
}

const loadAttachmentCount = async (documentId?: string) => {
  const docId = documentId || selectedDoc.value?.id
  if (!docId) {
    attachmentCount.value = 0
    return
  }
  try {
    const { data } = await fetchDocumentAttachments(docId)
    attachmentCount.value = (data.value ?? []).length
  } catch {
    attachmentCount.value = 0
  }
}

const filteredDocuments = computed(() => documents.value)

const handleSelectDocument = async (doc: Document) => {
  if (isEditing.value && hasChanges.value) {
    if (!confirm('You have unsaved changes. Discard them?')) {
      return
    }
  }
  selectedDoc.value = doc
  isEditing.value = false
  hasChanges.value = false
}

const handleSelectDocumentMobile = async (doc: Document) => {
  await handleSelectDocument(doc)
  showMobileDocList.value = false
}

// Determine current folder context for creating new items
const currentFolderId = computed(() => {
  if (!selectedDoc.value) return undefined
  if (selectedDoc.value.isFolder) return selectedDoc.value.id
  return selectedDoc.value.parentId ?? undefined
})

const handleCreateDocument = async () => {
  const newDoc = await createDocument({
    title: 'Untitled Document',
    content: '',
    authorId: 'h1',
    parentId: currentFolderId.value,
  })
  await refreshDocuments()
  if (newDoc) {
    selectedDoc.value = newDoc as Document
  }
}

const handleCreateFolder = async () => {
  await createDocument({
    title: 'New Folder',
    content: '',
    authorId: 'h1',
    isFolder: true,
    parentId: currentFolderId.value,
  })
  await refreshDocuments()
}

const handleDeleteDocument = async (doc: Document) => {
  const msg = doc.isFolder
    ? `Delete folder "${doc.title}" and all its contents?`
    : `Delete "${doc.title}"?`
  if (!confirm(msg)) return

  try {
    await deleteDocument(doc.id)
    if (selectedDoc.value?.id === doc.id) {
      selectedDoc.value = null
    }
    await refreshDocuments()
  } catch (error) {
    console.error('Failed to delete document:', error)
  }
}

const handleRenameDocument = async (doc: Document) => {
  const newTitle = prompt('Rename:', doc.title)
  if (!newTitle || newTitle === doc.title) return

  try {
    await updateDocument(doc.id, { title: newTitle })
    await refreshDocuments()
    if (selectedDoc.value?.id === doc.id) {
      const updated = documents.value.find(d => d.id === doc.id)
      if (updated) selectedDoc.value = updated
    }
  } catch (error) {
    console.error('Failed to rename document:', error)
  }
}

const handleStartEdit = () => {
  isEditing.value = true
  editedContent.value = selectedDoc.value?.content ?? ''
}

const handleContentChange = (content: string) => {
  editedContent.value = content
  hasChanges.value = content !== selectedDoc.value?.content
}

const handleSaveDocument = async () => {
  if (!selectedDoc.value || !hasChanges.value) return

  saving.value = true
  try {
    await updateDocument(selectedDoc.value.id, {
      content: editedContent.value,
      changeDescription: 'Content updated',
    })
    await refreshDocuments()
    await loadVersions(selectedDoc.value.id)

    // Update selected doc with new content
    const updatedDoc = documents.value.find(d => d.id === selectedDoc.value?.id)
    if (updatedDoc) {
      selectedDoc.value = updatedDoc
    }

    hasChanges.value = false
    isEditing.value = false
  } finally {
    saving.value = false
  }
}

const handleCancelEdit = () => {
  isEditing.value = false
  hasChanges.value = false
  editedContent.value = selectedDoc.value?.content ?? ''
}

const handleUpdateColor = async (color: string | null) => {
  if (!selectedDoc.value) return
  try {
    await updateDocument(selectedDoc.value.id, { color })
    await refreshDocuments()
    const updatedDoc = documents.value.find(d => d.id === selectedDoc.value?.id)
    if (updatedDoc) selectedDoc.value = updatedDoc
  } catch (error) {
    console.error('Failed to update color:', error)
  }
}

const handleUpdateIcon = async (icon: string | null) => {
  if (!selectedDoc.value) return
  try {
    await updateDocument(selectedDoc.value.id, { icon })
    await refreshDocuments()
    const updatedDoc = documents.value.find(d => d.id === selectedDoc.value?.id)
    if (updatedDoc) selectedDoc.value = updatedDoc
  } catch (error) {
    console.error('Failed to update icon:', error)
  }
}

const handleRestoreVersion = async (version: DocumentVersion) => {
  if (!selectedDoc.value) return
  if (!confirm(`Restore to version ${version.versionNumber}? This will create a snapshot of the current state.`)) {
    return
  }

  try {
    await restoreDocumentVersion(selectedDoc.value.id, version.id, 'h1')
    await refreshDocuments()
    await loadVersions(selectedDoc.value.id)

    // Update selected doc
    const updatedDoc = documents.value.find(d => d.id === selectedDoc.value?.id)
    if (updatedDoc) {
      selectedDoc.value = updatedDoc
    }
  } catch (error) {
    console.error('Failed to restore version:', error)
  }
}

const handleCompareVersion = (version: DocumentVersion) => {
  showDiffViewer.value = true
}

const handleRestoreFromDiff = async (versionId: string) => {
  if (!selectedDoc.value) return

  const version = versions.value.find(v => v.id === versionId)
  if (!version) return

  if (!confirm(`Restore to version ${version.versionNumber}? This will create a snapshot of the current state.`)) {
    return
  }

  try {
    await restoreDocumentVersion(selectedDoc.value.id, versionId, 'h1')
    await refreshDocuments()
    await loadVersions(selectedDoc.value.id)

    // Update selected doc
    const updatedDoc = documents.value.find(d => d.id === selectedDoc.value?.id)
    if (updatedDoc) {
      selectedDoc.value = updatedDoc
    }

    showDiffViewer.value = false
  } catch (error) {
    console.error('Failed to restore version:', error)
  }
}

// Comment handlers
const handleAddComment = async () => {
  if (!selectedDoc.value || !newCommentContent.value.trim()) return

  try {
    await addDocumentComment(selectedDoc.value.id, {
      content: newCommentContent.value.trim(),
      authorId: 'h1',
    })
    newCommentContent.value = ''
    await loadComments(selectedDoc.value.id)
  } catch (error) {
    console.error('Failed to add comment:', error)
  }
}

const handleReplyToComment = async (parentId: string, content: string) => {
  if (!selectedDoc.value) return

  try {
    await addDocumentComment(selectedDoc.value.id, {
      content,
      parentId,
      authorId: 'h1',
    })
    await loadComments(selectedDoc.value.id)
  } catch (error) {
    console.error('Failed to reply to comment:', error)
  }
}

const handleResolveComment = async (commentId: string, resolved: boolean) => {
  if (!selectedDoc.value) return

  try {
    await updateDocumentComment(selectedDoc.value.id, commentId, {
      resolved,
      resolvedById: resolved ? 'h1' : undefined,
    })
    await loadComments(selectedDoc.value.id)
  } catch (error) {
    console.error('Failed to resolve comment:', error)
  }
}

const handleDeleteComment = async (commentId: string) => {
  if (!selectedDoc.value) return
  if (!confirm('Delete this comment?')) return

  try {
    await deleteDocumentComment(selectedDoc.value.id, commentId)
    await loadComments(selectedDoc.value.id)
  } catch (error) {
    console.error('Failed to delete comment:', error)
  }
}

const formatDate = (date: Date | string) => {
  const d = new Date(date)
  return d.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  })
}

// Real-time updates
let unsubscribeComment: (() => void) | null = null
let unsubscribeUpdate: (() => void) | null = null

onMounted(() => {
  unsubscribeComment = on('document:comment:new', (data: { documentId: string }) => {
    if (data.documentId === selectedDoc.value?.id) {
      loadComments(data.documentId)
    }
  })

  unsubscribeUpdate = on('document:updated', (data: { documentId: string }) => {
    if (data.documentId === selectedDoc.value?.id) {
      refreshDocuments()
      loadVersions(data.documentId)
    }
  })
})

onUnmounted(() => {
  unsubscribeComment?.()
  unsubscribeUpdate?.()
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
