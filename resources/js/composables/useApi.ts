import axios from 'axios'
import { ref } from 'vue'
import type {
  User,
  Channel,
  Message,
  Task,
  ListItem,
  AgentTask,
  TaskStep,
  Document,
  Activity,
  Stats,
  ApprovalRequest,
  CalendarEvent,
  CalendarFeed,
  ListStatus,
  TokenAnalyticsResponse,
  WorkspaceDisk,
  WorkspaceFile,
  FolderTreeNode,
} from '@/types'

export interface PaginatedResponse<T> {
  data: T[]
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
  counts?: { total: number; pending: number; active: number; completed: number }
}

const api = axios.create({
  baseURL: '/api',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
})

// Helper to create reactive fetch
function useFetch<T>(url: string) {
  const data = ref<T | null>(null)
  const error = ref<Error | null>(null)
  const loading = ref(true)

  const execute = async () => {
    loading.value = true
    error.value = null
    try {
      const response = await api.get(url)
      data.value = response.data
    } catch (e) {
      error.value = e as Error
    } finally {
      loading.value = false
    }
  }

  // Start fetching and return promise along with reactive refs
  const promise = execute()

  return { data, error, loading, refresh: execute, promise }
}

export const useApi = () => {
  // Users
  const fetchUsers = () => useFetch<User[]>('/users')
  const fetchUser = (id: string) => useFetch<User>(`/users/${id}`)
  const fetchAgents = () => useFetch<User[]>('/users/agents')
  const updateUser = (id: string, data: Partial<User>) =>
    api.patch(`/users/${id}`, data)
  const updateUserPresence = (id: string, presence: 'online' | 'away' | 'busy' | 'offline') =>
    api.patch(`/users/${id}/presence`, { presence })

  // Channels
  const fetchChannels = () => useFetch<Channel[]>('/channels')
  const fetchChannel = (id: string) => useFetch<Channel>(`/channels/${id}`)
  const createChannel = (data: { name: string; type?: string; description?: string; creatorId?: string; memberIds?: string[] }) =>
    api.post('/channels', data)
  const addChannelMember = (channelId: string, userId: string) =>
    api.post(`/channels/${channelId}/members`, { userId })
  const removeChannelMember = (channelId: string, userId: string) =>
    api.delete(`/channels/${channelId}/members/${userId}`)
  const markChannelRead = (channelId: string, userId?: string) =>
    api.post(`/channels/${channelId}/read`, { userId })
  const sendTypingIndicator = (channelId: string, userId: string, userName: string, isTyping: boolean) =>
    api.post(`/channels/${channelId}/typing`, { userId, userName, isTyping })

  // Messages
  const fetchMessages = (channelId?: string, limit?: number) => {
    const params = new URLSearchParams()
    if (channelId) params.append('channelId', channelId)
    if (limit) params.append('limit', limit.toString())
    return useFetch<Message[]>(`/messages?${params.toString()}`)
  }
  const sendMessage = (data: { content: string; channelId: string; authorId: string; replyToId?: string; attachmentIds?: string[] }) =>
    api.post('/messages', data)
  const uploadMessageAttachment = async (file: File, channelId: string, uploaderId?: string) => {
    const formData = new FormData()
    formData.append('file', file)
    formData.append('channelId', channelId)
    if (uploaderId) formData.append('uploaderId', uploaderId)
    const response = await api.post<{ id: string; name: string; type: string; size: number; url: string }>('/messages/attachments', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    return response.data
  }
  const compactChannel = (channelId: string) =>
    api.post(`/channels/${channelId}/compact`)
  const deleteMessage = (id: string) =>
    api.delete(`/messages/${id}`)
  const addMessageReaction = (messageId: string, data: { emoji: string; userId?: string }) =>
    api.post(`/messages/${messageId}/reactions`, data)
  const removeMessageReaction = (messageId: string, reactionId: string) =>
    api.delete(`/messages/${messageId}/reactions/${reactionId}`)
  const fetchMessageThread = (messageId: string) =>
    useFetch(`/messages/${messageId}/thread`)
  const pinMessage = (messageId: string, userId?: string) =>
    api.post(`/messages/${messageId}/pin`, { userId })
  const fetchPinnedMessages = (channelId: string) =>
    useFetch<Message[]>(`/channels/${channelId}/pinned`)

  // List Statuses
  const fetchListStatuses = () => useFetch<ListStatus[]>('/list-statuses')
  const createListStatus = (data: { name: string; color: string; icon: string; isDone?: boolean }) =>
    api.post('/list-statuses', data)
  const updateListStatus = (id: string, data: Partial<{ name: string; color: string; icon: string; isDone: boolean; isDefault: boolean }>) =>
    api.patch(`/list-statuses/${id}`, data)
  const deleteListStatus = (id: string, replacementSlug?: string) =>
    api.delete(`/list-statuses/${id}`, { data: { replacementSlug } })
  const reorderListStatuses = (orders: { id: string; position: number }[]) =>
    api.post('/list-statuses/reorder', { orders })

  // List Items (kanban board items - formerly Tasks)
  const fetchListItems = () => useFetch<ListItem[]>('/list-items')
  const fetchListItem = (id: string) => useFetch<ListItem>(`/list-items/${id}`)
  const createListItem = (data: { title: string; description?: string; assigneeId?: string; priority?: string; status?: string; channelId?: string | null; dueDate?: string | null; collaboratorIds?: string[]; parentId?: string | null; isFolder?: boolean }) =>
    api.post('/list-items', data)
  const updateListItem = (id: string, data: Partial<ListItem>) =>
    api.patch(`/list-items/${id}`, data)
  const deleteListItem = (id: string) =>
    api.delete(`/list-items/${id}`)
  const reorderListItems = (itemOrders: { id: string; position: number; status?: string }[]) =>
    api.post('/list-items/reorder', { itemOrders })

  // Legacy aliases for backwards compatibility
  const fetchTasks = fetchListItems
  const fetchTask = fetchListItem
  const createTask = createListItem
  const updateTask = updateListItem
  const deleteTask = deleteListItem
  const reorderTasks = (taskOrders: { id: string; position: number; status?: string }[]) =>
    api.post('/list-items/reorder', { taskOrders })

  // List Item Comments
  const fetchListItemComments = (listItemId: string) =>
    useFetch(`/list-items/${listItemId}/comments`)
  const addListItemComment = (listItemId: string, data: { content: string; parentId?: string; authorId?: string }) =>
    api.post(`/list-items/${listItemId}/comments`, data)
  const deleteListItemComment = (listItemId: string, commentId: string) =>
    api.delete(`/list-items/${listItemId}/comments/${commentId}`)

  // Legacy aliases
  const fetchTaskComments = fetchListItemComments
  const addTaskComment = addListItemComment
  const deleteTaskComment = deleteListItemComment

  // Agent Tasks (cases - discrete work items)
  const fetchAgentTasks = (filters?: { status?: string | string[]; agentId?: string; requesterId?: string; type?: string; priority?: string; source?: string; search?: string; page?: number; perPage?: number }) => {
    const params = new URLSearchParams()
    if (filters?.status) {
      if (Array.isArray(filters.status)) {
        filters.status.forEach(s => params.append('status[]', s))
      } else {
        params.append('status', filters.status)
      }
    }
    if (filters?.agentId) params.append('agentId', filters.agentId)
    if (filters?.requesterId) params.append('requesterId', filters.requesterId)
    if (filters?.type) params.append('type', filters.type)
    if (filters?.priority) params.append('priority', filters.priority)
    if (filters?.source) params.append('source', filters.source)
    if (filters?.search) params.append('search', filters.search)
    if (filters?.page) params.append('page', String(filters.page))
    if (filters?.perPage) params.append('perPage', String(filters.perPage))
    return useFetch<PaginatedResponse<AgentTask>>(`/tasks?${params.toString()}`)
  }
  const fetchAgentTask = (id: string) => useFetch<AgentTask>(`/tasks/${id}`)
  const createAgentTask = (data: {
    title: string
    description?: string
    type?: string
    priority?: string
    agentId?: string
    requesterId: string
    channelId?: string
    projectId?: string
    listItemId?: string
    parentTaskId?: string
    context?: Record<string, unknown>
    dueAt?: string
  }) => api.post('/tasks', data)
  const updateAgentTask = (id: string, data: Partial<AgentTask>) =>
    api.patch(`/tasks/${id}`, data)
  const deleteAgentTask = (id: string) =>
    api.delete(`/tasks/${id}`)

  // Agent Task Lifecycle
  const startAgentTask = (id: string) =>
    api.post(`/tasks/${id}/start`)
  const pauseAgentTask = (id: string) =>
    api.post(`/tasks/${id}/pause`)
  const resumeAgentTask = (id: string) =>
    api.post(`/tasks/${id}/resume`)
  const completeAgentTask = (id: string, result?: Record<string, unknown>) =>
    api.post(`/tasks/${id}/complete`, { result })
  const failAgentTask = (id: string, reason?: string) =>
    api.post(`/tasks/${id}/fail`, { reason })
  const cancelAgentTask = (id: string) =>
    api.post(`/tasks/${id}/cancel`)

  // Task Steps
  const fetchTaskSteps = (taskId: string) =>
    useFetch<TaskStep[]>(`/tasks/${taskId}/steps`)
  const addTaskStep = (taskId: string, data: { description: string; type?: string; metadata?: Record<string, unknown> }) =>
    api.post(`/tasks/${taskId}/steps`, data)
  const updateTaskStep = (taskId: string, stepId: string, data: Partial<TaskStep>) =>
    api.patch(`/tasks/${taskId}/steps/${stepId}`, data)
  const completeTaskStep = (taskId: string, stepId: string) =>
    api.post(`/tasks/${taskId}/steps/${stepId}/complete`)

  // Documents
  const fetchDocuments = () => useFetch<Document[]>('/documents')
  const searchDocuments = (query: string) => api.get<Document[]>(`/documents/search?q=${encodeURIComponent(query)}`)
  const fetchDocument = (id: string) => useFetch<Document>(`/documents/${id}`)
  const createDocument = (data: { title: string; content?: string; authorId: string; parentId?: string; isFolder?: boolean; viewerIds?: string[]; editorIds?: string[] }) =>
    api.post('/documents', data)
  const updateDocument = (id: string, data: Partial<Document> & { saveVersion?: boolean; changeDescription?: string }) =>
    api.patch(`/documents/${id}`, data)
  const deleteDocument = (id: string) =>
    api.delete(`/documents/${id}`)

  // Document Comments
  const fetchDocumentComments = (documentId: string) =>
    useFetch(`/documents/${documentId}/comments`)
  const addDocumentComment = (documentId: string, data: { content: string; parentId?: string; authorId?: string }) =>
    api.post(`/documents/${documentId}/comments`, data)
  const updateDocumentComment = (documentId: string, commentId: string, data: { content?: string; resolved?: boolean; resolvedById?: string }) =>
    api.patch(`/documents/${documentId}/comments/${commentId}`, data)
  const deleteDocumentComment = (documentId: string, commentId: string) =>
    api.delete(`/documents/${documentId}/comments/${commentId}`)

  // Document Versions
  const fetchDocumentVersions = (documentId: string) =>
    useFetch(`/documents/${documentId}/versions`)
  const restoreDocumentVersion = (documentId: string, versionId: string, authorId?: string) =>
    api.post(`/documents/${documentId}/versions/${versionId}/restore`, { authorId })

  // Document Attachments
  const fetchDocumentAttachments = (documentId: string) =>
    useFetch(`/documents/${documentId}/attachments`)
  const uploadDocumentAttachment = async (documentId: string, file: File, uploaderId?: string) => {
    const formData = new FormData()
    formData.append('file', file)
    if (uploaderId) formData.append('uploaderId', uploaderId)
    const response = await api.post(`/documents/${documentId}/attachments`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    return response.data
  }
  const deleteDocumentAttachment = (documentId: string, attachmentId: string) =>
    api.delete(`/documents/${documentId}/attachments/${attachmentId}`)

  // Approvals
  const fetchApprovals = (status?: string) => {
    const params = status ? `?status=${status}` : ''
    return useFetch<ApprovalRequest[]>(`/approvals${params}`)
  }
  const fetchApproval = (id: string) => useFetch<ApprovalRequest>(`/approvals/${id}`)
  const createApproval = (data: { type: string; title: string; description?: string; requesterId: string; amount?: number; channelId?: string }) =>
    api.post('/approvals', data)
  const respondToApproval = (id: string, status: 'approved' | 'rejected') =>
    api.patch(`/approvals/${id}`, { status })

  // Activities
  const fetchActivities = (filters?: { limit?: number; offset?: number; type?: string; userId?: string; since?: string }) => {
    const params = new URLSearchParams()
    if (filters?.limit) params.append('limit', String(filters.limit))
    if (filters?.offset) params.append('offset', String(filters.offset))
    if (filters?.type) params.append('type', filters.type)
    if (filters?.userId) params.append('userId', filters.userId)
    if (filters?.since) params.append('since', filters.since)
    return useFetch<{ data: Activity[]; total: number; hasMore: boolean }>(`/activities?${params.toString()}`)
  }

  // Workload
  const fetchWorkload = () => useFetch<{
    agents: Array<{
      agent: { id: string; name: string; avatar: string | null; agentType: string | null; status: string }
      metrics: { currentTasks: number; pendingTasks: number; completedToday: number; completedThisWeek: number; failedThisWeek: number; avgDurationSeconds: number | null }
      currentTaskTitle: string | null
    }>
    summary: { totalAgents: number; activeAgents: number; totalActiveTasks: number; totalPendingTasks: number; completedToday: number; completedThisWeek: number; failedThisWeek: number }
  }>('/workload')

  // Token Analytics
  const fetchTokenAnalytics = (period = '30') =>
    useFetch<TokenAnalyticsResponse>(`/tasks/analytics/tokens?period=${period}`)

  // Stats
  const fetchStats = () => useFetch<Stats>('/stats')
  const fetchWorkspaceStatus = () => api.get('/stats/status')
  const updateStats = (data: Partial<Stats>) =>
    api.patch('/stats', data)

  // List Templates (formerly Task Templates)
  const fetchListTemplates = (activeOnly = true) =>
    useFetch(`/list-templates?activeOnly=${activeOnly}`)
  const createListTemplate = (data: {
    name: string
    defaultTitle: string
    description?: string
    defaultDescription?: string
    defaultPriority?: string
    defaultAssigneeId?: string
    estimatedCost?: number
    tags?: string[]
    createdById?: string
  }) => api.post('/list-templates', data)
  const updateListTemplate = (id: string, data: Record<string, unknown>) =>
    api.patch(`/list-templates/${id}`, data)
  const deleteListTemplate = (id: string) =>
    api.delete(`/list-templates/${id}`)
  const createListItemFromTemplate = (templateId: string, overrides?: {
    title?: string
    description?: string
    assigneeId?: string
    priority?: string
    channelId?: string
    collaboratorIds?: string[]
    estimatedCost?: number
  }) => api.post(`/list-templates/${templateId}/create-item`, overrides || {})

  // Legacy aliases
  const fetchTaskTemplates = fetchListTemplates
  const createTaskTemplate = createListTemplate
  const updateTaskTemplate = updateListTemplate
  const deleteTaskTemplate = deleteListTemplate
  const createTaskFromTemplate = createListItemFromTemplate

  // Automation Rules
  const fetchAutomationRules = (activeOnly = true) =>
    useFetch(`/automation-rules?activeOnly=${activeOnly}`)
  const createAutomationRule = (data: {
    name: string
    triggerType: string
    actionType: string
    description?: string
    triggerConditions?: Record<string, unknown>
    actionConfig?: Record<string, unknown>
    templateId?: string
    createdById?: string
  }) => api.post('/automation-rules', data)
  const updateAutomationRule = (id: string, data: Record<string, unknown>) =>
    api.patch(`/automation-rules/${id}`, data)
  const deleteAutomationRule = (id: string) =>
    api.delete(`/automation-rules/${id}`)

  // Automations
  const fetchAutomations = () =>
    useFetch<import('@/types').Automation[]>('/automations')
  const fetchAutomation = (id: string) =>
    useFetch<import('@/types').Automation>(`/automations/${id}`)
  const createAutomation = (data: {
    name: string
    agentId: string
    executionType?: 'prompt' | 'script'
    prompt?: string
    script?: string
    cronExpression: string
    timezone?: string
    description?: string
    channelId?: string
    keepHistory?: boolean
    createdById?: string
  }) => api.post('/automations', data)
  const updateAutomation = (id: string, data: Record<string, unknown>) =>
    api.patch(`/automations/${id}`, data)
  const deleteAutomation = (id: string) =>
    api.delete(`/automations/${id}`)
  const triggerAutomation = (id: string) =>
    api.post(`/automations/${id}/run`)
  const bulkDeleteAutomations = (ids: string[]) =>
    api.post('/automations/bulk-delete', { ids })
  const bulkTriggerAutomations = (ids: string[]) =>
    api.post('/automations/bulk-run', { ids })
  const fetchAutomationRuns = (id: string) =>
    useFetch<Array<{
      id: string
      title: string
      status: string
      runNumber: number | null
      result: Record<string, unknown> | null
      agentName: string | null
      startedAt: string | null
      completedAt: string | null
      createdAt: string
    }>>(`/automations/${id}/runs`)
  const previewSchedule = (cronExpression: string, timezone = 'UTC') =>
    api.get('/automations/preview-schedule', {
      params: { cronExpression, timezone },
    })

  // Agents
  const fetchAgentDetail = (id: string) => useFetch<Record<string, unknown>>(`/agents/${id}`)
  const fetchAgentIdentityFiles = (id: string) => useFetch<Record<string, unknown>[]>(`/agents/${id}/identity`)
  const updateAgentIdentityFile = (id: string, fileType: string, content: string) =>
    api.put(`/agents/${id}/identity/${fileType}`, { content })
  const updateAgent = (id: string, data: Record<string, unknown>) =>
    api.patch(`/agents/${id}`, data)
  const deleteAgent = (id: string) =>
    api.delete(`/agents/${id}`)

  // Agent Permissions
  const fetchAgentPermissions = (id: string) =>
    useFetch<{ tools: unknown[]; channelIds: string[]; folderIds: string[]; behaviorMode: string }>(`/agents/${id}/permissions`)
  const updateAgentToolPermissions = (id: string, tools: { scopeKey: string; permission: string; requiresApproval: boolean }[]) =>
    api.put(`/agents/${id}/permissions/tools`, { tools })
  const updateAgentChannelPermissions = (id: string, channels: string[]) =>
    api.put(`/agents/${id}/permissions/channels`, { channels })
  const updateAgentFolderPermissions = (id: string, folders: string[]) =>
    api.put(`/agents/${id}/permissions/folders`, { folders })
  const updateAgentIntegrations = (id: string, integrations: string[]) =>
    api.put(`/agents/${id}/permissions/integrations`, { integrations })

  // Search
  const search = (query: string, type?: string) => {
    const params = new URLSearchParams({ q: query })
    if (type) params.append('type', type)
    return api.get(`/search?${params.toString()}`)
  }

  // Direct Messages
  const fetchDirectMessages = (userId: string) =>
    useFetch(`/direct-messages?userId=${userId}`)
  const createDirectMessage = (user1Id: string, user2Id: string) =>
    api.post('/direct-messages', { user1Id, user2Id })
  const markDirectMessageRead = (id: string, userId: string) =>
    api.post(`/direct-messages/${id}/read`, { userId })
  const getUnreadDMCount = (userId: string) =>
    api.get(`/direct-messages/unread-count?userId=${userId}`)
  const fetchDm = (userId: string) =>
    api.get(`/dm/${userId}`)

  // Data Table Views
  const updateTableView = (tableId: string, viewId: string, data: Record<string, unknown>) =>
    api.patch(`/tables/${tableId}/views/${viewId}`, data)

  // Calendar Events
  const fetchCalendarEvents = (filters?: { start?: string; end?: string; userId?: string }) => {
    const params = new URLSearchParams()
    if (filters?.start) params.append('start', filters.start)
    if (filters?.end) params.append('end', filters.end)
    if (filters?.userId) params.append('userId', filters.userId)
    return useFetch<CalendarEvent[]>(`/calendar/events?${params.toString()}`)
  }
  const createCalendarEvent = (data: { title: string; startAt: string; endAt?: string; allDay?: boolean; description?: string; location?: string; color?: string; recurrenceRule?: string; recurrenceEnd?: string; attendeeIds?: string[] }) =>
    api.post<CalendarEvent>('/calendar/events', data)
  const updateCalendarEvent = (id: string, data: Partial<CalendarEvent>) =>
    api.patch<CalendarEvent>(`/calendar/events/${id}`, data)
  const deleteCalendarEvent = (id: string) =>
    api.delete(`/calendar/events/${id}`)
  const importCalendarEvents = async (file: File) => {
    const formData = new FormData()
    formData.append('file', file)
    const response = await api.post<{ imported: number; events: CalendarEvent[] }>('/calendar/events/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    return response.data
  }
  const importCalendarEventsFromUrl = (url: string) =>
    api.post<{ imported: number; events: CalendarEvent[] }>('/calendar/events/import-url', { url })

  // Calendar Feeds
  const fetchCalendarFeeds = () => useFetch<CalendarFeed[]>('/calendar/feeds')
  const createCalendarFeed = (data: { name?: string }) =>
    api.post<CalendarFeed>('/calendar/feeds', data)
  const deleteCalendarFeed = (id: string) =>
    api.delete(`/calendar/feeds/${id}`)

  // Settings
  const fetchSettings = () => useFetch<Record<string, Record<string, unknown>>>('/settings')
  const updateSettings = (category: string, settings: Record<string, unknown>) =>
    api.patch('/settings', { category, settings })
  const dangerAction = (action: string) =>
    api.post('/settings/danger-action', { action })
  const fetchDebugInfo = () => useFetch<Record<string, unknown>>('/settings/debug')

  // Storage Disks
  const fetchDisks = () => useFetch<{ data: WorkspaceDisk[] }>('/disks')
  const createDisk = (data: { name: string; driver: string; config?: Record<string, string> }) =>
    api.post<WorkspaceDisk>('/disks', data)
  const updateDisk = (id: string, data: { name?: string; config?: Record<string, string>; enabled?: boolean }) =>
    api.patch<WorkspaceDisk>(`/disks/${id}`, data)
  const deleteDisk = (id: string) => api.delete(`/disks/${id}`)
  const testDisk = (id: string) => api.post<{ success: boolean; message: string }>(`/disks/${id}/test`)
  const setDefaultDisk = (id: string) => api.post(`/disks/${id}/default`)

  // Files
  const fetchFiles = (parentId?: string | null, search?: string, diskId?: string) => {
    const params = new URLSearchParams()
    if (parentId) params.append('parent_id', parentId)
    if (search) params.append('search', search)
    if (diskId) params.append('disk_id', diskId)
    return useFetch<{ data: WorkspaceFile[]; parentId: string | null }>(`/files?${params.toString()}`)
  }
  const fetchFolderTree = () => useFetch<FolderTreeNode[]>('/files/tree')
  const searchFiles = (query: string, mimeType?: string) => {
    const params = new URLSearchParams({ q: query })
    if (mimeType) params.append('mime_type', mimeType)
    return useFetch<{ data: WorkspaceFile[] }>(`/files/search?${params.toString()}`)
  }
  const uploadFile = async (parentId: string | null, file: File, diskId?: string) => {
    const formData = new FormData()
    formData.append('file', file)
    if (parentId) formData.append('parent_id', parentId)
    if (diskId) formData.append('disk_id', diskId)
    return api.post<WorkspaceFile>('/files', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  }
  const createFolder = (name: string, parentId?: string | null, diskId?: string) =>
    api.post<WorkspaceFile>('/files/folder', { name, parent_id: parentId, disk_id: diskId })
  const fetchFileDetails = (id: string) => useFetch<WorkspaceFile>(`/files/${id}`)
  const fetchFolderChildren = (id: string) =>
    useFetch<{ data: WorkspaceFile[]; parentId: string; parentName: string; parentPath: string }>(`/files/${id}/children`)
  const renameFile = (id: string, name: string) =>
    api.patch<WorkspaceFile>(`/files/${id}`, { name })
  const moveFile = (id: string, parentId: string) =>
    api.patch<WorkspaceFile>(`/files/${id}`, { parent_id: parentId })
  const deleteFile = (id: string) => api.delete(`/files/${id}`)
  const copyFile = (id: string, parentId: string, name?: string) =>
    api.post<WorkspaceFile>(`/files/${id}/copy`, { parent_id: parentId, name })

  // Notifications
  const fetchNotifications = (userId?: string, unreadOnly?: boolean) => {
    const params = new URLSearchParams()
    if (userId) params.append('userId', userId)
    if (unreadOnly) params.append('unreadOnly', 'true')
    return useFetch(`/notifications?${params.toString()}`)
  }
  const markNotificationRead = (id: string) =>
    api.patch(`/notifications/${id}`, { is_read: true })
  const markAllNotificationsRead = (userId?: string) =>
    api.post('/notifications/mark-all-read', { userId })
  const getUnreadNotificationCount = (userId?: string) => {
    const params = userId ? `?userId=${userId}` : ''
    return api.get(`/notifications/count${params}`)
  }

  return {
    // Users
    fetchUsers,
    fetchUser,
    fetchAgents,
    updateUser,
    updateUserPresence,
    // Channels
    fetchChannels,
    fetchChannel,
    createChannel,
    addChannelMember,
    removeChannelMember,
    markChannelRead,
    sendTypingIndicator,
    // Messages
    fetchMessages,
    sendMessage,
    deleteMessage,
    addMessageReaction,
    removeMessageReaction,
    fetchMessageThread,
    pinMessage,
    fetchPinnedMessages,
    uploadMessageAttachment,
    compactChannel,
    // List Statuses
    fetchListStatuses,
    createListStatus,
    updateListStatus,
    deleteListStatus,
    reorderListStatuses,
    // List Items (kanban board)
    fetchListItems,
    fetchListItem,
    createListItem,
    updateListItem,
    deleteListItem,
    reorderListItems,
    // List Item Comments
    fetchListItemComments,
    addListItemComment,
    deleteListItemComment,
    // Legacy aliases for Tasks (kanban)
    fetchTasks,
    fetchTask,
    createTask,
    updateTask,
    deleteTask,
    reorderTasks,
    fetchTaskComments,
    addTaskComment,
    deleteTaskComment,
    // Agent Tasks (cases)
    fetchAgentTasks,
    fetchAgentTask,
    createAgentTask,
    updateAgentTask,
    deleteAgentTask,
    startAgentTask,
    pauseAgentTask,
    resumeAgentTask,
    completeAgentTask,
    failAgentTask,
    cancelAgentTask,
    // Task Steps
    fetchTaskSteps,
    addTaskStep,
    updateTaskStep,
    completeTaskStep,
    // Documents
    fetchDocuments,
    searchDocuments,
    fetchDocument,
    createDocument,
    updateDocument,
    deleteDocument,
    // Document Comments
    fetchDocumentComments,
    addDocumentComment,
    updateDocumentComment,
    deleteDocumentComment,
    // Document Versions
    fetchDocumentVersions,
    restoreDocumentVersion,
    // Document Attachments
    fetchDocumentAttachments,
    uploadDocumentAttachment,
    deleteDocumentAttachment,
    // Approvals
    fetchApprovals,
    fetchApproval,
    createApproval,
    respondToApproval,
    // Activities
    fetchActivities,
    // Workload
    fetchWorkload,
    // Token Analytics
    fetchTokenAnalytics,
    // Stats
    fetchStats,
    fetchWorkspaceStatus,
    updateStats,
    // List Templates
    fetchListTemplates,
    createListTemplate,
    updateListTemplate,
    deleteListTemplate,
    createListItemFromTemplate,
    // Legacy aliases
    fetchTaskTemplates,
    createTaskTemplate,
    updateTaskTemplate,
    deleteTaskTemplate,
    createTaskFromTemplate,
    // Automation Rules
    fetchAutomationRules,
    createAutomationRule,
    updateAutomationRule,
    deleteAutomationRule,
    // Automations
    fetchAutomations,
    fetchAutomation,
    createAutomation,
    updateAutomation,
    deleteAutomation,
    triggerAutomation,
    bulkDeleteAutomations,
    bulkTriggerAutomations,
    fetchAutomationRuns,
    previewSchedule,
    // Agents
    fetchAgentDetail,
    fetchAgentIdentityFiles,
    updateAgentIdentityFile,
    updateAgent,
    deleteAgent,
    // Agent Permissions
    fetchAgentPermissions,
    updateAgentToolPermissions,
    updateAgentChannelPermissions,
    updateAgentFolderPermissions,
    updateAgentIntegrations,
    // Calendar Events
    fetchCalendarEvents,
    createCalendarEvent,
    updateCalendarEvent,
    deleteCalendarEvent,
    importCalendarEvents,
    importCalendarEventsFromUrl,
    // Calendar Feeds
    fetchCalendarFeeds,
    createCalendarFeed,
    deleteCalendarFeed,
    // Search
    search,
    // Direct Messages
    fetchDirectMessages,
    createDirectMessage,
    markDirectMessageRead,
    getUnreadDMCount,
    fetchDm,
    // Data Table Views
    updateTableView,
    // Settings
    fetchSettings,
    updateSettings,
    dangerAction,
    fetchDebugInfo,
    // Files
    fetchDisks,
    createDisk,
    updateDisk,
    deleteDisk,
    testDisk,
    setDefaultDisk,
    fetchFiles,
    fetchFolderTree,
    searchFiles,
    uploadFile,
    createFolder,
    fetchFileDetails,
    fetchFolderChildren,
    renameFile,
    moveFile,
    deleteFile,
    copyFile,
    // Notifications
    fetchNotifications,
    markNotificationRead,
    markAllNotificationsRead,
    getUnreadNotificationCount,
  }
}
