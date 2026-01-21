export type AgentStatus = 'idle' | 'working' | 'offline' | 'paused' | 'online' | 'busy'
export type UserStatus = 'online' | 'away' | 'busy' | 'dnd' | 'offline'
export type PresenceStatus = 'online' | 'away' | 'busy' | 'offline'
export type TaskStatus = 'backlog' | 'in_progress' | 'done'
export type EntityType = 'human' | 'agent'
export type AgentType = 'manager' | 'writer' | 'analyst' | 'creative' | 'researcher' | 'coder' | 'coordinator'
export type ChannelType = 'public' | 'private' | 'agent' | 'dm'
export type Priority = 'low' | 'medium' | 'high' | 'urgent'
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
  isTemporary?: boolean
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

export interface Task {
  id: string
  title: string
  description: string
  status: TaskStatus
  assignee: User
  collaborators?: User[]
  priority: Priority
  cost?: number
  estimatedCost?: number
  createdAt: Date
  completedAt?: Date
  channelId?: string
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
  creditsUsed: number
  creditsRemaining: number
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
