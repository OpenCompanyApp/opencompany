import { ref } from 'vue'
import * as ActivityApi from '@/actions/App/Http/Controllers/Api/ActivityController'
import * as AgentApi from '@/actions/App/Http/Controllers/Api/AgentController'
import * as AgentPermissionApi from '@/actions/App/Http/Controllers/Api/AgentPermissionController'
import * as ApprovalApi from '@/actions/App/Http/Controllers/Api/ApprovalController'
import * as AutomationApi from '@/actions/App/Http/Controllers/Api/AutomationController'
import * as AutomationRuleApi from '@/actions/App/Http/Controllers/Api/AutomationRuleController'
import * as CalendarEventApi from '@/actions/App/Http/Controllers/Api/CalendarEventController'
import * as CalendarFeedApi from '@/actions/App/Http/Controllers/Api/CalendarFeedController'
import * as ChannelApi from '@/actions/App/Http/Controllers/Api/ChannelController'
import * as DataTableViewApi from '@/actions/App/Http/Controllers/Api/DataTableViewController'
import * as DirectMessageApi from '@/actions/App/Http/Controllers/Api/DirectMessageController'
import * as DmApi from '@/actions/App/Http/Controllers/Api/DmController'
import * as DocumentApi from '@/actions/App/Http/Controllers/Api/DocumentController'
import * as DocumentAttachmentApi from '@/actions/App/Http/Controllers/Api/DocumentAttachmentController'
import * as DocumentCommentApi from '@/actions/App/Http/Controllers/Api/DocumentCommentController'
import * as DocumentVersionApi from '@/actions/App/Http/Controllers/Api/DocumentVersionController'
import * as FileApi from '@/actions/App/Http/Controllers/Api/FileController'
import * as ListItemApi from '@/actions/App/Http/Controllers/Api/ListItemController'
import * as ListItemCommentApi from '@/actions/App/Http/Controllers/Api/ListItemCommentController'
import * as ListStatusApi from '@/actions/App/Http/Controllers/Api/ListStatusController'
import * as ListTemplateApi from '@/actions/App/Http/Controllers/Api/ListTemplateController'
import * as MessageApi from '@/actions/App/Http/Controllers/Api/MessageController'
import * as NotificationApi from '@/actions/App/Http/Controllers/Api/NotificationController'
import * as SearchApi from '@/actions/App/Http/Controllers/Api/SearchController'
import * as SettingApi from '@/actions/App/Http/Controllers/Api/SettingController'
import * as StatsApi from '@/actions/App/Http/Controllers/Api/StatsController'
import * as TaskApi from '@/actions/App/Http/Controllers/Api/TaskController'
import * as TokenAnalyticsApi from '@/actions/App/Http/Controllers/Api/TokenAnalyticsController'
import * as UserApi from '@/actions/App/Http/Controllers/Api/UserController'
import * as WorkloadApi from '@/actions/App/Http/Controllers/Api/WorkloadController'
import * as WorkspaceDiskApi from '@/actions/App/Http/Controllers/Api/WorkspaceDiskController'
import { wayfinderRequest } from '@/utils/wayfinder'
import type { QueryParams, RouteDefinition } from '@/wayfinder'
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

type WayfinderDefinition = RouteDefinition<any>
type DirectMessageResponse = {
  id?: string
  channel_id?: string
  channelId?: string
  channel?: { id: string }
}

type ActionMessageResponse = {
  message?: string
}

const query = (params: QueryParams): { query: QueryParams } => ({ query: params })

// Helper to create reactive fetch
function useFetch<T>(route: WayfinderDefinition) {
  const data = ref<T | null>(null)
  const error = ref<Error | null>(null)
  const loading = ref(true)

  const execute = async (): Promise<T | null> => {
    loading.value = true
    error.value = null
    try {
      const response = await wayfinderRequest<T>(route)
      data.value = response.data
      return response.data
    } catch (e) {
      error.value = e as Error
      return null
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
  const fetchUsers = () => useFetch<User[]>(UserApi.index())
  const fetchUser = (id: string) => useFetch<User>(UserApi.show(id))
  const fetchAgents = () => useFetch<User[]>(UserApi.agents())
  const updateUser = (id: string, data: Partial<User>) =>
    wayfinderRequest(UserApi.update(id), { data })
  const updateUserPresence = (id: string, presence: 'online' | 'away' | 'busy' | 'offline') =>
    wayfinderRequest(UserApi.updatePresence(id), { data: { presence } })

  // Channels
  const fetchChannels = () => useFetch<Channel[]>(ChannelApi.index())
  const fetchChannel = (id: string) => useFetch<Channel>(ChannelApi.show(id))
  const createChannel = (data: { name: string; type?: string; description?: string; creatorId?: string; memberIds?: string[] }) =>
    wayfinderRequest<Channel>(ChannelApi.store(), { data })
  const addChannelMember = (channelId: string, userId: string) =>
    wayfinderRequest(ChannelApi.addMember(channelId), { data: { userId } })
  const removeChannelMember = (channelId: string, userId: string) =>
    wayfinderRequest(ChannelApi.removeMember([channelId, userId]))
  const markChannelRead = (channelId: string, userId?: string) =>
    wayfinderRequest(ChannelApi.markRead(channelId), { data: { userId } })
  const sendTypingIndicator = (channelId: string, userId: string, userName: string, isTyping: boolean) =>
    wayfinderRequest(ChannelApi.typing(channelId), { data: { userId, userName, isTyping } })

  // Messages
  const fetchMessages = (channelId?: string, limit?: number) => {
    return useFetch<Message[]>(MessageApi.index(query({ channelId, limit })))
  }
  const sendMessage = (data: { content: string; channelId: string; authorId: string; replyToId?: string; attachmentIds?: string[] }) =>
    wayfinderRequest(MessageApi.store(), { data })
  const uploadMessageAttachment = async (file: File, channelId: string, uploaderId?: string) => {
    const formData = new FormData()
    formData.append('file', file)
    formData.append('channelId', channelId)
    if (uploaderId) formData.append('uploaderId', uploaderId)
    const response = await wayfinderRequest<{ id: string; name: string; type: string; size: number; url: string }>(MessageApi.uploadAttachment(), {
      data: formData,
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    return response.data
  }
  const compactChannel = (channelId: string) =>
    wayfinderRequest(MessageApi.compact(channelId))
  const deleteMessage = (id: string) =>
    wayfinderRequest(MessageApi.destroy(id))
  const addMessageReaction = (messageId: string, data: { emoji: string; userId?: string }) =>
    wayfinderRequest(MessageApi.addReaction(messageId), { data })
  const removeMessageReaction = (messageId: string, reactionId: string) =>
    wayfinderRequest(MessageApi.removeReaction([messageId, reactionId]))
  const fetchMessageThread = (messageId: string) =>
    useFetch<{ parentMessage: Message; replies: Message[] }>(MessageApi.thread(messageId))
  const pinMessage = (messageId: string, userId?: string) =>
    wayfinderRequest(MessageApi.pin(messageId), { data: { userId } })
  const fetchPinnedMessages = (channelId: string) =>
    useFetch<Message[]>(ChannelApi.pinned(channelId))

  // List Statuses
  const fetchListStatuses = () => useFetch<ListStatus[]>(ListStatusApi.index())
  const createListStatus = (data: { name: string; color: string; icon: string; isDone?: boolean }) =>
    wayfinderRequest(ListStatusApi.store(), { data })
  const updateListStatus = (id: string, data: Partial<{ name: string; color: string; icon: string; isDone: boolean; isDefault: boolean }>) =>
    wayfinderRequest(ListStatusApi.update(id), { data })
  const deleteListStatus = (id: string, replacementSlug?: string) =>
    wayfinderRequest(ListStatusApi.destroy(id), { data: { replacementSlug } })
  const reorderListStatuses = (orders: { id: string; position: number }[]) =>
    wayfinderRequest(ListStatusApi.reorder(), { data: { orders } })

  // List Items (kanban board items - formerly Tasks)
  const fetchListItems = () => useFetch<ListItem[]>(ListItemApi.index())
  const fetchListItem = (id: string) => useFetch<ListItem>(ListItemApi.show(id))
  const createListItem = (data: { title: string; description?: string; assigneeId?: string; priority?: string; status?: string; channelId?: string | null; dueDate?: string | null; collaboratorIds?: string[]; parentId?: string | null; isFolder?: boolean }) =>
    wayfinderRequest(ListItemApi.store(), { data })
  const updateListItem = (id: string, data: Partial<ListItem>) =>
    wayfinderRequest(ListItemApi.update(id), { data })
  const deleteListItem = (id: string) =>
    wayfinderRequest(ListItemApi.destroy(id))
  const reorderListItems = (itemOrders: { id: string; position: number; status?: string }[]) =>
    wayfinderRequest(ListItemApi.reorder(), { data: { itemOrders } })

  // Legacy aliases for backwards compatibility
  const fetchTasks = fetchListItems
  const fetchTask = fetchListItem
  const createTask = createListItem
  const updateTask = updateListItem
  const deleteTask = deleteListItem
  const reorderTasks = (taskOrders: { id: string; position: number; status?: string }[]) =>
    wayfinderRequest(ListItemApi.reorder(), { data: { taskOrders } })

  // List Item Comments
  const fetchListItemComments = (listItemId: string) =>
    useFetch(ListItemCommentApi.index(listItemId))
  const addListItemComment = (listItemId: string, data: { content: string; parentId?: string; authorId?: string }) =>
    wayfinderRequest(ListItemCommentApi.store(listItemId), { data })
  const deleteListItemComment = (listItemId: string, commentId: string) =>
    wayfinderRequest(ListItemCommentApi.destroy([listItemId, commentId]))

  // Legacy aliases
  const fetchTaskComments = fetchListItemComments
  const addTaskComment = addListItemComment
  const deleteTaskComment = deleteListItemComment

  // Agent Tasks (cases - discrete work items)
  const fetchAgentTasks = (filters?: { status?: string | string[]; agentId?: string; requesterId?: string; channelId?: string; type?: string; priority?: string; source?: string; search?: string; page?: number; perPage?: number }) => {
    return useFetch<PaginatedResponse<AgentTask>>(TaskApi.index(query({
      status: filters?.status,
      agentId: filters?.agentId,
      requesterId: filters?.requesterId,
      channelId: filters?.channelId,
      type: filters?.type,
      priority: filters?.priority,
      source: filters?.source,
      search: filters?.search,
      page: filters?.page,
      perPage: filters?.perPage,
    })))
  }
  const fetchAgentTask = (id: string) => useFetch<AgentTask>(TaskApi.show(id))
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
  }) => wayfinderRequest(TaskApi.store(), { data })
  const updateAgentTask = (id: string, data: Partial<AgentTask>) =>
    wayfinderRequest(TaskApi.update(id), { data })
  const deleteAgentTask = (id: string) =>
    wayfinderRequest(TaskApi.destroy(id))

  // Agent Task Lifecycle
  const startAgentTask = (id: string) =>
    wayfinderRequest(TaskApi.start(id))
  const pauseAgentTask = (id: string) =>
    wayfinderRequest(TaskApi.pause(id))
  const resumeAgentTask = (id: string) =>
    wayfinderRequest(TaskApi.resume(id))
  const completeAgentTask = (id: string, result?: Record<string, unknown>) =>
    wayfinderRequest(TaskApi.complete(id), { data: { result } })
  const failAgentTask = (id: string, reason?: string) =>
    wayfinderRequest(TaskApi.fail(id), { data: { reason } })
  const cancelAgentTask = (id: string) =>
    wayfinderRequest(TaskApi.cancel(id))

  // Task Steps
  const fetchTaskSteps = (taskId: string) =>
    useFetch<TaskStep[]>(TaskApi.steps(taskId))
  const addTaskStep = (taskId: string, data: { description: string; type?: string; metadata?: Record<string, unknown> }) =>
    wayfinderRequest(TaskApi.addStep(taskId), { data })
  const updateTaskStep = (taskId: string, stepId: string, data: Partial<TaskStep>) =>
    wayfinderRequest(TaskApi.updateStep([taskId, stepId]), { data })
  const completeTaskStep = (taskId: string, stepId: string) =>
    wayfinderRequest(TaskApi.completeStep([taskId, stepId]))

  // Documents
  const fetchDocuments = () => useFetch<Document[]>(DocumentApi.index())
  const searchDocuments = (term: string) => wayfinderRequest<Document[]>(DocumentApi.search(query({ q: term })))
  const fetchDocument = (id: string) => useFetch<Document>(DocumentApi.show(id))
  const createDocument = (data: { title: string; content?: string; authorId: string; parentId?: string; isFolder?: boolean; viewerIds?: string[]; editorIds?: string[] }) =>
    wayfinderRequest<Document>(DocumentApi.store(), { data }).then(response => response.data)
  const updateDocument = (id: string, data: Partial<Document> & { saveVersion?: boolean; changeDescription?: string }) =>
    wayfinderRequest<Document>(DocumentApi.update(id), { data }).then(response => response.data)
  const deleteDocument = (id: string) =>
    wayfinderRequest(DocumentApi.destroy(id))

  // Document Comments
  const fetchDocumentComments = (documentId: string) =>
    useFetch<any[]>(DocumentCommentApi.index(documentId))
  const addDocumentComment = (documentId: string, data: { content: string; parentId?: string; authorId?: string }) =>
    wayfinderRequest(DocumentCommentApi.store(documentId), { data })
  const updateDocumentComment = (documentId: string, commentId: string, data: { content?: string; resolved?: boolean; resolvedById?: string }) =>
    wayfinderRequest(DocumentCommentApi.update([documentId, commentId]), { data })
  const deleteDocumentComment = (documentId: string, commentId: string) =>
    wayfinderRequest(DocumentCommentApi.destroy([documentId, commentId]))

  // Document Versions
  const fetchDocumentVersions = (documentId: string) =>
    useFetch<any[]>(DocumentVersionApi.index(documentId))
  const restoreDocumentVersion = (documentId: string, versionId: string, authorId?: string) =>
    wayfinderRequest(DocumentVersionApi.restore([documentId, versionId]), { data: { authorId } })

  // Document Attachments
  const fetchDocumentAttachments = (documentId: string) =>
    useFetch<any[]>(DocumentAttachmentApi.index(documentId))
  const uploadDocumentAttachment = async (documentId: string, file: File, uploaderId?: string) => {
    const formData = new FormData()
    formData.append('file', file)
    if (uploaderId) formData.append('uploaderId', uploaderId)
    const response = await wayfinderRequest(DocumentAttachmentApi.store(documentId), {
      data: formData,
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    return response.data
  }
  const deleteDocumentAttachment = (documentId: string, attachmentId: string) =>
    wayfinderRequest(DocumentAttachmentApi.destroy([documentId, attachmentId]))

  // Approvals
  const fetchApprovals = (status?: string) => {
    return useFetch<ApprovalRequest[]>(ApprovalApi.index(query({ status })))
  }
  const fetchApproval = (id: string) => useFetch<ApprovalRequest>(ApprovalApi.show(id))
  const createApproval = (data: { type: string; title: string; description?: string; requesterId: string; amount?: number; channelId?: string }) =>
    wayfinderRequest(ApprovalApi.store(), { data })
  const respondToApproval = (id: string, status: 'approved' | 'rejected') =>
    wayfinderRequest(ApprovalApi.update(id), { data: { status } })

  // Activities
  const fetchActivities = (filters?: { limit?: number; offset?: number; type?: string; userId?: string; since?: string }) => {
    return useFetch<{ data: Activity[]; total: number; hasMore: boolean }>(ActivityApi.index(query({
      limit: filters?.limit,
      offset: filters?.offset,
      type: filters?.type,
      userId: filters?.userId,
      since: filters?.since,
    })))
  }

  // Workload
  const fetchWorkload = () => useFetch<{
    agents: Array<{
      agent: { id: string; name: string; avatar: string | null; agentType: string | null; status: string }
      metrics: { currentTasks: number; pendingTasks: number; completedToday: number; completedThisWeek: number; failedThisWeek: number; avgDurationSeconds: number | null }
      currentTaskTitle: string | null
    }>
    summary: { totalAgents: number; activeAgents: number; totalActiveTasks: number; totalPendingTasks: number; completedToday: number; completedThisWeek: number; failedThisWeek: number }
  }>(WorkloadApi.index())

  // Token Analytics
  const fetchTokenAnalytics = (period = '30') =>
    useFetch<TokenAnalyticsResponse>(TokenAnalyticsApi.index(query({ period })))

  // Stats
  const fetchStats = () => useFetch<Stats>(StatsApi.index())
  const fetchWorkspaceStatus = (params?: { channelId?: string; agentId?: string }) =>
    wayfinderRequest(StatsApi.status(query(params ?? {})))
  const updateStats = (data: Partial<Stats>) =>
    wayfinderRequest(StatsApi.update(), { data })

  // List Templates (formerly Task Templates)
  const fetchListTemplates = (activeOnly = true) =>
    useFetch(ListTemplateApi.index(query({ activeOnly })))
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
  }) => wayfinderRequest(ListTemplateApi.store(), { data })
  const updateListTemplate = (id: string, data: Record<string, unknown>) =>
    wayfinderRequest(ListTemplateApi.update(id), { data })
  const deleteListTemplate = (id: string) =>
    wayfinderRequest(ListTemplateApi.destroy(id))
  const createListItemFromTemplate = (templateId: string, overrides?: {
    title?: string
    description?: string
    assigneeId?: string
    priority?: string
    channelId?: string
    collaboratorIds?: string[]
    estimatedCost?: number
  }) => wayfinderRequest(ListTemplateApi.createListItem(templateId), { data: overrides || {} })

  // Legacy aliases
  const fetchTaskTemplates = fetchListTemplates
  const createTaskTemplate = createListTemplate
  const updateTaskTemplate = updateListTemplate
  const deleteTaskTemplate = deleteListTemplate
  const createTaskFromTemplate = createListItemFromTemplate

  // Automation Rules
  const fetchAutomationRules = (activeOnly = true) =>
    useFetch(AutomationRuleApi.index(query({ activeOnly })))
  const createAutomationRule = (data: {
    name: string
    triggerType: string
    actionType: string
    description?: string
    triggerConditions?: Record<string, unknown>
    actionConfig?: Record<string, unknown>
    templateId?: string
    createdById?: string
  }) => wayfinderRequest(AutomationRuleApi.store(), { data })
  const updateAutomationRule = (id: string, data: Record<string, unknown>) =>
    wayfinderRequest(AutomationRuleApi.update(id), { data })
  const deleteAutomationRule = (id: string) =>
    wayfinderRequest(AutomationRuleApi.destroy(id))

  // Automations
  const fetchAutomations = () =>
    useFetch<import('@/types').Automation[]>(AutomationApi.index())
  const fetchAutomation = (id: string) =>
    useFetch<import('@/types').Automation>(AutomationApi.show(id))
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
  }) => wayfinderRequest(AutomationApi.store(), { data })
  const updateAutomation = (id: string, data: Record<string, unknown>) =>
    wayfinderRequest(AutomationApi.update(id), { data })
  const deleteAutomation = (id: string) =>
    wayfinderRequest(AutomationApi.destroy(id))
  const triggerAutomation = (id: string) =>
    wayfinderRequest(AutomationApi.triggerRun(id))
  const bulkDeleteAutomations = (ids: string[]) =>
    wayfinderRequest(AutomationApi.bulkDestroy(), { data: { ids } })
  const bulkTriggerAutomations = (ids: string[]) =>
    wayfinderRequest(AutomationApi.bulkTriggerRun(), { data: { ids } })
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
    }>>(AutomationApi.runs(id))
  const previewSchedule = (cronExpression: string, timezone = 'UTC') =>
    wayfinderRequest(AutomationApi.previewSchedule(query({ cronExpression, timezone })))

  // Agents
  const fetchAgentDetail = (id: string) => useFetch<Record<string, unknown>>(AgentApi.show(id))
  const fetchAgentIdentityFiles = (id: string) => useFetch<Record<string, unknown>[]>(AgentApi.identityFiles(id))
  const updateAgentIdentityFile = (id: string, fileType: string, content: string) =>
    wayfinderRequest(AgentApi.updateIdentityFile([id, fileType]), { data: { content } })
  const updateAgent = (id: string, data: Record<string, unknown>) =>
    wayfinderRequest(AgentApi.update(id), { data })
  const deleteAgent = (id: string) =>
    wayfinderRequest(AgentApi.destroy(id))

  // Agent Permissions
  const fetchAgentPermissions = (id: string) =>
    useFetch<{ tools: unknown[]; channelIds: string[]; folderIds: string[]; behaviorMode: string }>(AgentPermissionApi.index(id))
  const updateAgentToolPermissions = (id: string, tools: { scopeKey: string; permission: string; requiresApproval: boolean }[]) =>
    wayfinderRequest(AgentPermissionApi.updateTools(id), { data: { tools } })
  const updateAgentChannelPermissions = (id: string, channels: string[]) =>
    wayfinderRequest(AgentPermissionApi.updateChannels(id), { data: { channels } })
  const updateAgentFolderPermissions = (id: string, folders: string[]) =>
    wayfinderRequest(AgentPermissionApi.updateFolders(id), { data: { folders } })
  const updateAgentFileFolderPermissions = (id: string, folders: string[]) =>
    wayfinderRequest(AgentPermissionApi.updateFileFolders(id), { data: { folders } })
  const updateAgentIntegrations = (id: string, integrations: string[]) =>
    wayfinderRequest(AgentPermissionApi.updateIntegrations(id), { data: { integrations } })

  // Search
  const search = (term: string, type?: string) =>
    wayfinderRequest(SearchApi.index(query({ q: term, type })))

  // Direct Messages
  const fetchDirectMessages = (userId: string) =>
    useFetch(DirectMessageApi.index(query({ userId })))
  const createDirectMessage = (user1Id: string, user2Id: string) =>
    wayfinderRequest<DirectMessageResponse>(DirectMessageApi.store(), { data: { user1Id, user2Id } })
  const markDirectMessageRead = (id: string, userId: string) =>
    wayfinderRequest(DirectMessageApi.markRead(id), { data: { userId } })
  const getUnreadDMCount = (userId: string) =>
    wayfinderRequest(DirectMessageApi.unreadCount(query({ userId })))
  const fetchDm = (userId: string) =>
    wayfinderRequest(DmApi.show(userId))

  // Data Table Views
  const updateTableView = (tableId: string, viewId: string, data: Record<string, unknown>) =>
    wayfinderRequest(DataTableViewApi.update([tableId, viewId]), { data })

  // Calendar Events
  const fetchCalendarEvents = (filters?: { start?: string; end?: string; userId?: string }) => {
    return useFetch<CalendarEvent[]>(CalendarEventApi.index(query({
      start: filters?.start,
      end: filters?.end,
      userId: filters?.userId,
    })))
  }
  const createCalendarEvent = (data: { title: string; startAt: string; endAt?: string; allDay?: boolean; description?: string; location?: string; color?: string; recurrenceRule?: string; recurrenceEnd?: string; attendeeIds?: string[] }) =>
    wayfinderRequest<CalendarEvent>(CalendarEventApi.store(), { data })
  const updateCalendarEvent = (id: string, data: Partial<CalendarEvent>) =>
    wayfinderRequest<CalendarEvent>(CalendarEventApi.update(id), { data })
  const deleteCalendarEvent = (id: string) =>
    wayfinderRequest(CalendarEventApi.destroy(id))
  const importCalendarEvents = async (file: File) => {
    const formData = new FormData()
    formData.append('file', file)
    const response = await wayfinderRequest<{ imported: number; events: CalendarEvent[] }>(CalendarEventApi.importMethod(), {
      data: formData,
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    return response.data
  }
  const importCalendarEventsFromUrl = (url: string) =>
    wayfinderRequest<{ imported: number; events: CalendarEvent[] }>(CalendarEventApi.importFromUrl(), { data: { url } })

  // Calendar Feeds
  const fetchCalendarFeeds = () => useFetch<CalendarFeed[]>(CalendarFeedApi.index())
  const createCalendarFeed = (data: { name?: string }) =>
    wayfinderRequest<CalendarFeed>(CalendarFeedApi.store(), { data })
  const deleteCalendarFeed = (id: string) =>
    wayfinderRequest(CalendarFeedApi.destroy(id))

  // Settings
  const fetchSettings = () => useFetch<Record<string, Record<string, unknown>>>(SettingApi.index())
  const updateSettings = (category: string, settings: Record<string, unknown>) =>
    wayfinderRequest(SettingApi.update(), { data: { category, settings } })
  const dangerAction = (action: string) =>
    wayfinderRequest<ActionMessageResponse>(SettingApi.dangerAction(), { data: { action } })
  const fetchDebugInfo = () => useFetch<Record<string, unknown>>(SettingApi.debug())

  // Storage Disks
  const fetchDisks = () => useFetch<{ data: WorkspaceDisk[] }>(WorkspaceDiskApi.index())
  const createDisk = (data: { name: string; driver: string; config?: Record<string, string> }) =>
    wayfinderRequest<WorkspaceDisk>(WorkspaceDiskApi.store(), { data })
  const updateDisk = (id: string, data: { name?: string; config?: Record<string, string>; enabled?: boolean }) =>
    wayfinderRequest<WorkspaceDisk>(WorkspaceDiskApi.update(id), { data })
  const deleteDisk = (id: string) => wayfinderRequest(WorkspaceDiskApi.destroy(id))
  const testDisk = (id: string) => wayfinderRequest<{ success: boolean; message: string }>(WorkspaceDiskApi.test(id))
  const setDefaultDisk = (id: string) => wayfinderRequest(WorkspaceDiskApi.setDefault(id))

  // Files
  const fetchFiles = (parentId?: string | null, search?: string, diskId?: string) => {
    return useFetch<{ data: WorkspaceFile[]; parentId: string | null }>(FileApi.index(query({
      parent_id: parentId,
      search,
      disk_id: diskId,
    })))
  }
  const fetchFolderTree = () => useFetch<FolderTreeNode[]>(FileApi.tree())
  const searchFiles = (term: string, mimeType?: string) =>
    useFetch<{ data: WorkspaceFile[] }>(FileApi.search(query({ q: term, mime_type: mimeType })))
  const uploadFile = async (parentId: string | null, file: File, diskId?: string) => {
    const formData = new FormData()
    formData.append('file', file)
    if (parentId) formData.append('parent_id', parentId)
    if (diskId) formData.append('disk_id', diskId)
    return wayfinderRequest<WorkspaceFile>(FileApi.store(), {
      data: formData,
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  }
  const createFolder = (name: string, parentId?: string | null, diskId?: string) =>
    wayfinderRequest<WorkspaceFile>(FileApi.createFolder(), { data: { name, parent_id: parentId, disk_id: diskId } })
  const fetchFileDetails = (id: string) => useFetch<WorkspaceFile>(FileApi.show(id))
  const fetchFolderChildren = (id: string) =>
    useFetch<{ data: WorkspaceFile[]; parentId: string; parentName: string; parentPath: string }>(FileApi.children(id))
  const renameFile = (id: string, name: string) =>
    wayfinderRequest<WorkspaceFile>(FileApi.update(id), { data: { name } })
  const moveFile = (id: string, parentId: string) =>
    wayfinderRequest<WorkspaceFile>(FileApi.update(id), { data: { parent_id: parentId } })
  const deleteFile = (id: string) => wayfinderRequest(FileApi.destroy(id))
  const copyFile = (id: string, parentId: string, name?: string) =>
    wayfinderRequest<WorkspaceFile>(FileApi.copy(id), { data: { parent_id: parentId, name } })

  // Notifications
  const fetchNotifications = (userId?: string, unreadOnly?: boolean) => {
    return useFetch(NotificationApi.index(query({ userId, unreadOnly })))
  }
  const markNotificationRead = (id: string) =>
    wayfinderRequest(NotificationApi.update(id), { data: { is_read: true } })
  const markAllNotificationsRead = (userId?: string) =>
    wayfinderRequest(NotificationApi.markAllRead(), { data: { userId } })
  const getUnreadNotificationCount = (userId?: string) => {
    return wayfinderRequest(NotificationApi.count(query({ userId })))
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
    updateAgentFileFolderPermissions,
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
