import axios from 'axios'
import { ref } from 'vue'
import type {
  User,
  Channel,
  Message,
  Task,
  Document,
  Activity,
  Stats,
  ApprovalRequest
} from '@/types'

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

  // Tasks
  const fetchTasks = () => useFetch<Task[]>('/tasks')
  const fetchTask = (id: string) => useFetch<Task>(`/tasks/${id}`)
  const createTask = (data: { title: string; description?: string; assigneeId?: string; priority?: string; status?: string; channelId?: string | null; estimatedCost?: number | null; collaboratorIds?: string[]; parentId?: string | null; isFolder?: boolean }) =>
    api.post('/tasks', data)
  const updateTask = (id: string, data: Partial<Task>) =>
    api.patch(`/tasks/${id}`, data)
  const deleteTask = (id: string) =>
    api.delete(`/tasks/${id}`)
  const reorderTasks = (taskOrders: { id: string; position: number; status?: string }[]) =>
    api.post('/tasks/reorder', { taskOrders })

  // Task Comments
  const fetchTaskComments = (taskId: string) =>
    useFetch(`/tasks/${taskId}/comments`)
  const addTaskComment = (taskId: string, data: { content: string; parentId?: string; authorId?: string }) =>
    api.post(`/tasks/${taskId}/comments`, data)
  const deleteTaskComment = (taskId: string, commentId: string) =>
    api.delete(`/tasks/${taskId}/comments/${commentId}`)

  // Documents
  const fetchDocuments = () => useFetch<Document[]>('/documents')
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
  const createApproval = (data: { type: string; title: string; description?: string; requesterId: string; amount?: number; channelId?: string }) =>
    api.post('/approvals', data)
  const respondToApproval = (id: string, status: 'approved' | 'rejected', respondedById: string) =>
    api.patch(`/approvals/${id}`, { status, respondedById })

  // Activities
  const fetchActivities = (limit?: number) => {
    const params = limit ? `?limit=${limit}` : ''
    return useFetch<Activity[]>(`/activities${params}`)
  }

  // Stats
  const fetchStats = () => useFetch<Stats>('/stats')
  const updateStats = (data: Partial<Stats>) =>
    api.patch('/stats', data)

  // Task Templates
  const fetchTaskTemplates = (activeOnly = true) =>
    useFetch(`/task-templates?activeOnly=${activeOnly}`)
  const createTaskTemplate = (data: {
    name: string
    defaultTitle: string
    description?: string
    defaultDescription?: string
    defaultPriority?: string
    defaultAssigneeId?: string
    estimatedCost?: number
    tags?: string[]
    createdById?: string
  }) => api.post('/task-templates', data)
  const updateTaskTemplate = (id: string, data: Record<string, unknown>) =>
    api.patch(`/task-templates/${id}`, data)
  const deleteTaskTemplate = (id: string) =>
    api.delete(`/task-templates/${id}`)
  const createTaskFromTemplate = (templateId: string, overrides?: {
    title?: string
    description?: string
    assigneeId?: string
    priority?: string
    channelId?: string
    collaboratorIds?: string[]
    estimatedCost?: number
  }) => api.post(`/task-templates/${templateId}/create-task`, overrides || {})

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
    // Tasks
    fetchTasks,
    fetchTask,
    createTask,
    updateTask,
    deleteTask,
    reorderTasks,
    // Task Comments
    fetchTaskComments,
    addTaskComment,
    deleteTaskComment,
    // Documents
    fetchDocuments,
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
    createApproval,
    respondToApproval,
    // Activities
    fetchActivities,
    // Stats
    fetchStats,
    updateStats,
    // Task Templates
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
    // Search
    search,
    // Direct Messages
    fetchDirectMessages,
    createDirectMessage,
    markDirectMessageRead,
    getUnreadDMCount,
    // Notifications
    fetchNotifications,
    markNotificationRead,
    markAllNotificationsRead,
    getUnreadNotificationCount,
  }
}
