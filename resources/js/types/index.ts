export type AgentStatus = 'idle' | 'working' | 'offline' | 'paused' | 'online' | 'busy' | 'awaiting_approval' | 'sleeping'
export type UserStatus = 'online' | 'away' | 'busy' | 'dnd' | 'offline'
export type PresenceStatus = 'online' | 'away' | 'busy' | 'offline'
export type ListItemStatus = 'backlog' | 'in_progress' | 'done'
export type TaskStatus = 'pending' | 'active' | 'paused' | 'completed' | 'failed' | 'cancelled'
export type TaskType = 'ticket' | 'request' | 'analysis' | 'content' | 'research' | 'custom'
export type TaskStepType = 'action' | 'decision' | 'approval' | 'sub_task' | 'message'
export type TaskStepStatus = 'pending' | 'in_progress' | 'completed' | 'skipped'
export type EntityType = 'human' | 'agent'
export type AgentType = 'manager' | 'writer' | 'analyst' | 'creative' | 'researcher' | 'coder' | 'coordinator'
export type ChannelType = 'public' | 'private' | 'dm' | 'external'
export type ExternalChannelProvider = 'telegram' | 'slack'
export type Priority = 'low' | 'medium' | 'high' | 'urgent' | 'normal'
export type ActivityType = 'message' | 'task_completed' | 'task_started' | 'agent_spawned' | 'approval_needed' | 'approval_granted' | 'error'

export interface User {
  id: string
  name: string
  avatar?: string
  email?: string
  type: EntityType
  agentType?: AgentType
  status?: AgentStatus
  presence?: PresenceStatus
  lastSeenAt?: Date
  currentTask?: string
  activityLog?: ActivityStep[]
  isAgent?: boolean
  awaitingApprovalId?: string
  mustWaitForApproval?: boolean
  brain?: string
  role?: 'admin' | 'editor' | 'viewer' | 'owner'
}

export interface ActivityStep {
  id: string
  description: string
  status: 'completed' | 'in_progress' | 'pending'
  startedAt: Date
  completedAt?: Date
}

export interface Channel {
  id: string
  name: string
  type: ChannelType
  description?: string
  unreadCount: number
  members: User[]
  isEphemeral?: boolean
  private?: boolean
  createdAt?: Date
  createdBy?: User
  lastMessage?: Message
  lastMessageAt?: Date
  onlineCount?: number
  muted?: boolean
  pinned?: boolean
  starred?: boolean
  archived?: boolean
  // External channel fields
  externalProvider?: ExternalChannelProvider
  externalId?: string
  externalConfig?: Record<string, unknown>
}

export interface Attachment {
  id: string
  name: string
  type: string
  size: number
  url: string
}

export interface CodeBlock {
  id: string
  language: string
  code: string
  filename?: string
}

export interface Reaction {
  emoji: string
  count: number
  users: User[]
  reacted?: boolean
}

export interface Message {
  id: string
  content: string
  author: User
  timestamp: Date
  channelId: string
  replyTo?: Message
  isApprovalRequest?: boolean
  approvalRequest?: ApprovalRequest
  editedAt?: Date
  isPinned?: boolean
  pinnedBy?: User
  attachments?: Attachment[]
  codeBlocks?: CodeBlock[]
  reactions?: Reaction[]
  threadCount?: number
  threadParticipants?: User[]
  lastThreadReplyAt?: Date
  source?: string | null
}

export interface ApprovalRequest {
  id: string
  type: 'budget' | 'action' | 'spawn' | 'access' | 'cost'
  title: string
  description: string
  requester: User
  amount?: number
  status: 'pending' | 'approved' | 'rejected'
  respondedBy?: User
  respondedAt?: Date
  priority?: Priority
  expiresAt?: Date
  resource?: string
  duration?: string
  scope?: string
  details?: string[]
  riskLevel?: 'low' | 'medium' | 'high'
  riskDescription?: string
  responseNote?: string
  requestedAt?: Date
  source?: string
  urgent?: boolean
  createdAt?: Date
}

/**
 * ListItem - Items in a kanban board (formerly Task)
 * For discrete agent work items, see the Task interface instead.
 */
export interface ListItem {
  id: string
  parentId?: string | null
  isFolder?: boolean
  title: string
  description: string
  status: ListItemStatus
  assignee: User
  collaborators?: User[]
  priority: Priority
  cost?: number
  estimatedCost?: number
  createdAt: Date
  completedAt?: Date
  channelId?: string
}

// Legacy alias for backwards compatibility
export type Task = ListItem

/**
 * AgentTask - Discrete work items that agents work on (like cases)
 *
 * Examples: support tickets, content requests, research tasks, analysis jobs
 */
export interface AgentTask {
  id: string
  title: string
  description?: string
  type: TaskType
  status: TaskStatus
  priority: Priority
  agent?: User
  agentId?: string
  requester?: User
  requesterId: string
  channel?: Channel
  channelId?: string
  project?: { id: string; name: string }
  projectId?: string
  listItem?: ListItem
  listItemId?: string
  parentTask?: AgentTask
  parentTaskId?: string
  subtasks?: AgentTask[]
  steps?: TaskStep[]
  context?: Record<string, unknown>
  result?: Record<string, unknown>
  startedAt?: Date
  completedAt?: Date
  dueAt?: Date
  createdAt: Date
  updatedAt: Date
}

export interface TaskStep {
  id: string
  taskId: string
  description: string
  status: TaskStepStatus
  stepType: TaskStepType
  metadata?: Record<string, unknown>
  startedAt?: Date
  completedAt?: Date
  createdAt: Date
  updatedAt: Date
}

export interface Document {
  id: string
  title: string
  content: string
  updatedAt: Date
  createdAt: Date
  author: User
  viewers?: User[]
  editors?: User[]
  parentId?: string | null
  isFolder?: boolean
}

export interface Activity {
  id: string
  type: ActivityType
  description: string
  actor: User
  timestamp: Date
  metadata?: Record<string, unknown>
  target?: User | string
  channel?: string
  cost?: number
  duration?: string
  details?: string
}

export interface Stats {
  agentsOnline: number
  totalAgents: number
  tasksCompleted: number
  tasksToday: number
  messagesTotal: number
  messagesToday: number
}

export interface CommandItem {
  id: string
  label: string
  description?: string
  icon: string
  shortcut?: string
  category: 'navigation' | 'channels' | 'actions' | 'settings'
  action: () => void
}

// Agent Identity & Configuration (OpenClaw-inspired)
export interface AgentIdentity {
  name: string
  emoji?: string
  type: AgentType
  avatar?: string
  description?: string
  theme?: string
  creature?: string
  vibe?: string
}

export interface AgentPersonality {
  content: string  // Markdown content for behavior guidelines
  updatedAt: Date
}

export interface AgentInstructions {
  content: string  // Markdown content for operating manual
  updatedAt: Date
}

export type ToolType = 'read' | 'write'
export type ToolKind = 'read' | 'write' | 'execute' | 'other'

export interface Capability {
  id: string
  name: string
  displayName: string
  description?: string
  icon?: string
  category?: string
  kind: ToolKind
  defaultEnabled: boolean
  defaultRequiresApproval: boolean
  sortOrder: number
}

export interface AgentCapability {
  id: string
  name: string
  description?: string
  type: ToolType
  enabled: boolean
  requiresApproval: boolean
  notes?: string
  icon?: string
  app?: string
}

export interface AgentConfiguration {
  id: string
  userId: string
  personality?: string
  instructions?: string
  identity?: AgentIdentity
  toolNotes?: string
  createdAt: string
  updatedAt: string
}

export interface AgentSession {
  id: string
  startedAt: Date
  messageCount: number
  tokenCount: number
  maxTokens: number
  lastActivityAt?: Date
}

export interface AgentMemoryEntry {
  id: string
  content: string
  createdAt: Date
  source?: string
  category?: 'fact' | 'preference' | 'context' | 'note'
}

export type AgentBehaviorMode = 'autonomous' | 'supervised' | 'strict'
export type SecurityMode = 'deny' | 'allowlist' | 'full'
export type AskMode = 'off' | 'on-miss' | 'always'
export type SessionResetMode = 'daily' | 'idle' | 'manual'

export interface AgentSettings {
  id?: string
  agentConfigId?: string
  behaviorMode: AgentBehaviorMode
  securityMode?: SecurityMode
  askMode?: AskMode
  costLimitDaily?: number | null
  costLimit?: number // legacy alias
  maxTokensPerRequest?: number
  reserveTokens?: number
  reserveTokensFloor?: number
  keepRecentTokens?: number
  softThresholdTokens?: number
  pruningTtlMinutes?: number
  autoAllowSkills?: boolean
  resetPolicy: {
    mode: SessionResetMode
    dailyHour?: number
    idleMinutes?: number
  }
}

export interface Agent extends User {
  identity: AgentIdentity
  personality?: AgentPersonality
  instructions?: AgentInstructions
  capabilities: AgentCapability[]
  currentSession?: AgentSession
  memoryEntries?: AgentMemoryEntry[]
  settings: AgentSettings
  stats?: {
    tasksCompleted: number
    efficiency: number
    totalSessions: number
  }
}

// Calendar Types
export type CalendarAttendeeStatus = 'pending' | 'accepted' | 'declined' | 'tentative'

export interface CalendarEventAttendee {
  id: string
  eventId: string
  userId: string
  status: CalendarAttendeeStatus
  user: User
}

export interface CalendarEvent {
  id: string
  title: string
  description?: string
  startAt: string
  endAt?: string
  allDay: boolean
  location?: string
  color?: string
  recurrenceRule?: string
  createdBy: string
  creator?: User
  attendees: CalendarEventAttendee[]
}

// Data Tables Types
export type DataTableColumnType = 'text' | 'number' | 'date' | 'select' | 'multiselect' | 'checkbox' | 'url' | 'email' | 'user' | 'attachment'
export type DataTableViewType = 'grid' | 'kanban' | 'gallery' | 'calendar'

export interface DataTableColumn {
  id: string
  tableId: string
  name: string
  type: DataTableColumnType
  options?: Record<string, unknown>
  order: number
  required: boolean
}

export interface DataTableRow {
  id: string
  tableId: string
  data: Record<string, unknown>
  createdBy: string
  creator?: User
  createdAt?: string
  updatedAt?: string
}

export interface DataTableView {
  id: string
  tableId: string
  name: string
  type: DataTableViewType
  filters?: Record<string, unknown>[]
  sorts?: { columnId: string; direction: 'asc' | 'desc' }[]
  hiddenColumns?: string[]
}

export interface DataTable {
  id: string
  name: string
  description?: string
  icon?: string
  createdBy: string
  creator?: User
  columns: DataTableColumn[]
  views?: DataTableView[]
  rowsCount?: number
  createdAt?: string
  updatedAt?: string
}
