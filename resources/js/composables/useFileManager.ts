import { ref, computed, reactive } from 'vue'
import { useApi } from './useApi'
import { useToast } from './useToast'
import type { WorkspaceFile, WorkspaceDisk, FolderTreeNode } from '@/types'

export function useFileManager() {
  const { fetchFiles, fetchFolderTree, fetchDisks, createFolder, uploadFile, renameFile, deleteFile } = useApi()
  const { success, error } = useToast()

  // ── Core state ──────────────────────────────────────────────
  const currentFolderId = ref<string | null>(null)
  const files = ref<WorkspaceFile[]>([])
  const filesLoading = ref(false)
  const folderTree = ref<FolderTreeNode[]>([])
  const treeLoading = ref(false)
  const searchQuery = ref('')
  const previewFile = ref<WorkspaceFile | null>(null)

  // ── Disks ─────────────────────────────────────────────────
  const disks = ref<WorkspaceDisk[]>([])
  const activeDiskId = ref<string | null>(null)

  const loadDisks = async () => {
    const { data, promise } = fetchDisks()
    await promise
    disks.value = data.value?.data ?? []
  }

  const switchDisk = (diskId: string | null) => {
    activeDiskId.value = diskId
    currentFolderId.value = null
    historyBack.value = []
    historyForward.value = []
    breadcrumbs.value = []
    clearSelection()
    cancelRename()
    loadFiles()
  }

  // ── View mode (persisted) ───────────────────────────────────
  const viewMode = ref<'grid' | 'list'>(
    (typeof localStorage !== 'undefined' && localStorage.getItem('files-view-mode') as 'grid' | 'list') || 'grid'
  )
  const setViewMode = (mode: 'grid' | 'list') => {
    viewMode.value = mode
    if (typeof localStorage !== 'undefined') localStorage.setItem('files-view-mode', mode)
  }

  // ── Navigation history ──────────────────────────────────────
  const historyBack = ref<(string | null)[]>([])
  const historyForward = ref<(string | null)[]>([])
  const canGoBack = computed(() => historyBack.value.length > 0)
  const canGoForward = computed(() => historyForward.value.length > 0)

  const goBack = () => {
    if (!canGoBack.value) return
    historyForward.value.push(currentFolderId.value)
    currentFolderId.value = historyBack.value.pop()!
    clearSelection()
    cancelRename()
    loadFiles()
    buildBreadcrumbs()
  }

  const goForward = () => {
    if (!canGoForward.value) return
    historyBack.value.push(currentFolderId.value)
    currentFolderId.value = historyForward.value.pop()!
    clearSelection()
    cancelRename()
    loadFiles()
    buildBreadcrumbs()
  }

  // ── Breadcrumbs ─────────────────────────────────────────────
  const breadcrumbs = ref<Array<{ id: string; name: string }>>([])

  const buildBreadcrumbs = () => {
    if (currentFolderId.value === null) {
      breadcrumbs.value = []
    } else {
      breadcrumbs.value = findPathInTree(folderTree.value, currentFolderId.value)
    }
  }

  const findPathInTree = (
    nodes: FolderTreeNode[],
    targetId: string,
    path: Array<{ id: string; name: string }> = [],
  ): Array<{ id: string; name: string }> => {
    for (const node of nodes) {
      const currentPath = [...path, { id: node.id, name: node.name }]
      if (node.id === targetId) return currentPath
      if (node.children.length > 0) {
        const found = findPathInTree(node.children, targetId, currentPath)
        if (found.length > 0) return found
      }
    }
    return []
  }

  // ── Selection ───────────────────────────────────────────────
  const selectedIds = reactive(new Set<string>())
  const lastClickedId = ref<string | null>(null)

  const selectFile = (file: WorkspaceFile, event: MouseEvent) => {
    if (event.metaKey || event.ctrlKey) {
      if (selectedIds.has(file.id)) {
        selectedIds.delete(file.id)
      } else {
        selectedIds.add(file.id)
      }
    } else if (event.shiftKey && lastClickedId.value) {
      const allIds = sortedFiles.value.map(f => f.id)
      const start = allIds.indexOf(lastClickedId.value)
      const end = allIds.indexOf(file.id)
      if (start !== -1 && end !== -1) {
        const [from, to] = start < end ? [start, end] : [end, start]
        selectedIds.clear()
        for (let i = from; i <= to; i++) {
          selectedIds.add(allIds[i])
        }
      }
    } else {
      selectedIds.clear()
      selectedIds.add(file.id)
    }
    lastClickedId.value = file.id
  }

  const clearSelection = () => {
    selectedIds.clear()
    lastClickedId.value = null
  }

  const selectAll = () => {
    sortedFiles.value.forEach(f => selectedIds.add(f.id))
  }

  // ── Sorting ─────────────────────────────────────────────────
  type SortField = 'name' | 'updatedAt' | 'size' | 'mimeType'
  const sortField = ref<SortField>('name')
  const sortDirection = ref<'asc' | 'desc'>('asc')

  const sortedFiles = computed(() => {
    return [...files.value].sort((a, b) => {
      if (a.isFolder && !b.isFolder) return -1
      if (!a.isFolder && b.isFolder) return 1

      let cmp = 0
      switch (sortField.value) {
        case 'name':
          cmp = a.name.localeCompare(b.name, undefined, { sensitivity: 'base' })
          break
        case 'updatedAt':
          cmp = new Date(a.updatedAt).getTime() - new Date(b.updatedAt).getTime()
          break
        case 'size':
          cmp = (a.size || 0) - (b.size || 0)
          break
        case 'mimeType':
          cmp = (a.mimeType || '').localeCompare(b.mimeType || '')
          break
      }
      return sortDirection.value === 'asc' ? cmp : -cmp
    })
  })

  const toggleSort = (field: SortField) => {
    if (sortField.value === field) {
      sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
    } else {
      sortField.value = field
      sortDirection.value = 'asc'
    }
  }

  // ── Inline rename ───────────────────────────────────────────
  const renamingId = ref<string | null>(null)
  const renameValue = ref('')

  const startRename = (file: WorkspaceFile) => {
    renamingId.value = file.id
    renameValue.value = file.name
  }

  const commitRename = async () => {
    if (!renamingId.value || !renameValue.value.trim()) {
      cancelRename()
      return
    }
    const originalFile = files.value.find(f => f.id === renamingId.value)
    if (!originalFile || renameValue.value.trim() === originalFile.name) {
      cancelRename()
      return
    }
    try {
      await renameFile(renamingId.value, renameValue.value.trim())
      success('Renamed')
      cancelRename()
      loadFiles()
      loadTree()
    } catch {
      error('Failed to rename')
    }
  }

  const cancelRename = () => {
    renamingId.value = null
    renameValue.value = ''
  }

  // ── Delete confirmation ─────────────────────────────────────
  const showDeleteDialog = ref(false)
  const fileToDelete = ref<WorkspaceFile | null>(null)

  const confirmDelete = (file: WorkspaceFile) => {
    fileToDelete.value = file
    showDeleteDialog.value = true
  }

  const executeDelete = async () => {
    if (!fileToDelete.value) return
    try {
      await deleteFile(fileToDelete.value.id)
      if (previewFile.value?.id === fileToDelete.value.id) previewFile.value = null
      selectedIds.delete(fileToDelete.value.id)
      success('Deleted')
      loadFiles()
      loadTree()
    } catch {
      error('Failed to delete')
    } finally {
      showDeleteDialog.value = false
      fileToDelete.value = null
    }
  }

  // ── New folder ──────────────────────────────────────────────
  const showNewFolderInput = ref(false)
  const newFolderName = ref('')

  const handleCreateFolder = async () => {
    if (!newFolderName.value.trim()) return
    try {
      await createFolder(newFolderName.value.trim(), currentFolderId.value, activeDiskId.value || undefined)
      newFolderName.value = ''
      showNewFolderInput.value = false
      success('Folder created')
      loadFiles()
      loadTree()
    } catch {
      error('Failed to create folder')
    }
  }

  // ── Data loading ────────────────────────────────────────────
  const loadTree = async () => {
    treeLoading.value = true
    try {
      const { data, promise } = fetchFolderTree()
      await promise
      if (data.value) folderTree.value = data.value
    } finally {
      treeLoading.value = false
    }
  }

  const loadFiles = async () => {
    filesLoading.value = true
    try {
      const { data, promise } = fetchFiles(currentFolderId.value, searchQuery.value || undefined, activeDiskId.value || undefined)
      await promise
      if (data.value) files.value = data.value.data
    } finally {
      filesLoading.value = false
    }
  }

  const navigateToFolder = (folderId: string | null) => {
    if (folderId === currentFolderId.value) return
    historyBack.value.push(currentFolderId.value)
    historyForward.value = []
    currentFolderId.value = folderId
    clearSelection()
    cancelRename()
    loadFiles()
    buildBreadcrumbs()
  }

  const openFile = (file: WorkspaceFile) => {
    if (file.isFolder) {
      navigateToFolder(file.id)
    } else {
      previewFile.value = file
    }
  }

  // ── Upload ──────────────────────────────────────────────────
  const handleUpload = async (fileList: File[], uploadZoneRef?: any) => {
    for (const file of fileList) {
      uploadZoneRef?.addUpload(file.name)
      try {
        await uploadFile(currentFolderId.value, file, activeDiskId.value || undefined)
        success('File uploaded')
      } catch {
        error(`Failed to upload ${file.name}`)
      } finally {
        uploadZoneRef?.removeUpload(file.name)
      }
    }
    loadFiles()
    loadTree()
  }

  // ── Download ────────────────────────────────────────────────
  const downloadFile = (file: WorkspaceFile) => {
    if (file.downloadUrl) {
      const a = document.createElement('a')
      a.href = file.downloadUrl
      a.download = file.name
      a.click()
    }
  }

  // ── Search ──────────────────────────────────────────────────
  let searchTimeout: ReturnType<typeof setTimeout>
  const debouncedSearch = () => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => loadFiles(), 300)
  }

  // ── Helpers ─────────────────────────────────────────────────
  const getKindLabel = (file: WorkspaceFile): string => {
    if (file.isFolder) return 'Folder'
    const mime = file.mimeType || ''
    if (mime === 'image/jpeg') return 'JPEG Image'
    if (mime === 'image/png') return 'PNG Image'
    if (mime === 'image/gif') return 'GIF Image'
    if (mime === 'image/svg+xml') return 'SVG Image'
    if (mime.startsWith('image/')) return 'Image'
    if (mime === 'application/pdf') return 'PDF Document'
    if (mime.startsWith('video/')) return 'Video'
    if (mime.startsWith('audio/')) return 'Audio'
    if (mime.includes('spreadsheet') || mime.includes('excel') || mime === 'text/csv') return 'Spreadsheet'
    if (mime.includes('document') || mime.includes('word')) return 'Document'
    if (mime.includes('presentation') || mime.includes('powerpoint')) return 'Presentation'
    if (mime.includes('zip') || mime.includes('archive') || mime.includes('tar') || mime.includes('gzip')) return 'Archive'
    if (mime === 'application/json') return 'JSON'
    if (mime.startsWith('text/')) return 'Text'
    return 'File'
  }

  const formatSize = (bytes: number): string => {
    if (bytes < 1024) return `${bytes} B`
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
    if (bytes < 1024 * 1024 * 1024) return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
    return `${(bytes / (1024 * 1024 * 1024)).toFixed(1)} GB`
  }

  const formatRelative = (date: string): string => {
    const d = new Date(date)
    const now = new Date()
    const diff = now.getTime() - d.getTime()
    const mins = Math.floor(diff / 60000)
    if (mins < 60) return `${mins}m ago`
    const hrs = Math.floor(mins / 60)
    if (hrs < 24) return `${hrs}h ago`
    const days = Math.floor(hrs / 24)
    if (days < 30) return `${days}d ago`
    return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })
  }

  const itemCountLabel = computed(() => {
    const count = sortedFiles.value.length
    if (count === 0) return 'Empty folder'
    return `${count} item${count !== 1 ? 's' : ''}`
  })

  // ── Context menu items ──────────────────────────────────────
  const getContextMenuItems = (file: WorkspaceFile) => {
    const items: any[] = []

    if (file.isFolder) {
      items.push({ label: 'Open', icon: 'ph:folder-open', click: () => openFile(file) })
    } else {
      items.push({ label: 'Quick Look', icon: 'ph:eye', shortcut: 'Space', click: () => { previewFile.value = file } })
    }

    items.push([
      ...(!file.isFolder && file.downloadUrl
        ? [{ label: 'Download', icon: 'ph:download-simple', click: () => downloadFile(file) }]
        : []),
      { label: 'Rename', icon: 'ph:pencil-simple', shortcut: '↵', click: () => startRename(file) },
    ])

    items.push([
      { label: 'Delete', icon: 'ph:trash', color: 'error' as const, shortcut: '⌫', click: () => confirmDelete(file) },
    ])

    return items
  }

  return {
    // Core state
    currentFolderId,
    files,
    filesLoading,
    folderTree,
    treeLoading,
    searchQuery,
    previewFile,

    // Disks
    disks,
    activeDiskId,
    loadDisks,
    switchDisk,

    // View mode
    viewMode,
    setViewMode,

    // Navigation
    historyBack,
    historyForward,
    canGoBack,
    canGoForward,
    goBack,
    goForward,
    navigateToFolder,
    openFile,
    breadcrumbs,

    // Selection
    selectedIds,
    selectFile,
    clearSelection,
    selectAll,

    // Sorting
    sortField,
    sortDirection,
    sortedFiles,
    toggleSort,

    // Inline rename
    renamingId,
    renameValue,
    startRename,
    commitRename,
    cancelRename,

    // Delete
    showDeleteDialog,
    fileToDelete,
    confirmDelete,
    executeDelete,

    // New folder
    showNewFolderInput,
    newFolderName,
    handleCreateFolder,

    // Operations
    loadTree,
    loadFiles,
    handleUpload,
    downloadFile,
    debouncedSearch,

    // Helpers
    getKindLabel,
    formatSize,
    formatRelative,
    itemCountLabel,
    getContextMenuItems,
  }
}
