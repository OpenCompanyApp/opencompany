<template>
  <div class="h-full overflow-y-auto">
    <div class="max-w-4xl mx-auto p-6">
      <!-- Back button -->
      <button
        type="button"
        class="flex items-center gap-2 text-sm text-neutral-500 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white mb-6 transition-colors"
        @click="goBack"
      >
        <Icon name="ph:arrow-left" class="w-4 h-4" />
        Back
      </button>

      <!-- Loading State -->
      <div v-if="loading" class="space-y-6">
        <SharedSkeleton custom-class="h-32 rounded-xl" />
        <div class="flex gap-2">
          <SharedSkeleton v-for="i in 7" :key="i" custom-class="w-24 h-9 rounded-lg" />
        </div>
        <SharedSkeleton custom-class="h-96 rounded-xl" />
      </div>

      <template v-else-if="agent">
        <!-- Simplified Header -->
        <div class="flex items-center justify-between mb-6">
          <div class="flex items-center gap-4">
            <div class="relative">
              <div
                :class="[
                  'w-14 h-14 rounded-xl flex items-center justify-center text-2xl',
                  agentBgColor
                ]"
              >
                {{ agent.identity?.emoji || '🤖' }}
              </div>
              <div
                :class="[
                  'absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 rounded-full border-2 border-white dark:border-neutral-900',
                  statusColor
                ]"
              />
            </div>
            <div>
              <div class="flex items-center gap-2">
                <h1 class="text-xl font-semibold text-neutral-900 dark:text-white">
                  {{ agent.identity?.name || agent.name }}
                </h1>
                <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400 capitalize">
                  {{ agent.identity?.type || agent.agentType }}
                </span>
              </div>
              <p class="text-sm text-neutral-500 dark:text-neutral-400 mt-0.5">
                {{ statusLabel }}
              </p>
            </div>
          </div>

          <div class="flex items-center gap-2">
            <Link
              :href="`/messages/${agent.id}`"
              class="flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-md bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 hover:bg-neutral-800 dark:hover:bg-neutral-100 transition-colors"
            >
              <Icon name="ph:chat-circle" class="w-4 h-4" />
              Message
            </Link>
            <button
              type="button"
              :class="[
                'flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-md transition-colors',
                agent.status === 'working'
                  ? 'text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/20'
                  : 'text-green-600 dark:text-green-400 hover:bg-green-50 dark:hover:bg-green-900/20'
              ]"
              @click="togglePause"
            >
              <Icon :name="agent.status === 'working' ? 'ph:pause' : 'ph:play'" class="w-4 h-4" />
              {{ agent.status === 'working' ? 'Pause' : 'Resume' }}
            </button>
          </div>
        </div>

        <!-- Tabs -->
        <div class="flex gap-1 mb-6 overflow-x-auto pb-1">
          <button
            v-for="tab in tabs"
            :key="tab.id"
            type="button"
            :class="[
              'px-3 py-1.5 rounded-md text-sm font-medium transition-colors whitespace-nowrap',
              activeTab === tab.id
                ? 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900'
                : 'text-neutral-500 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-800'
            ]"
            @click="activeTab = tab.id"
          >
            {{ tab.label }}
          </button>
        </div>

        <!-- Tab Content -->
        <div class="min-h-[500px]">
          <!-- Overview Tab -->
          <div v-if="activeTab === 'overview'" class="space-y-6">
            <AgentIdentityCard :agent="agent" />

            <!-- Current Task -->
            <section v-if="agent.currentTask">
              <h3 class="text-sm font-medium text-neutral-900 dark:text-white mb-3">Current Task</h3>
              <div class="bg-neutral-50 dark:bg-neutral-800 rounded-xl p-4 border border-neutral-200 dark:border-neutral-700">
                <p class="text-sm text-neutral-900 dark:text-white">{{ agent.currentTask }}</p>
              </div>
            </section>

            <!-- Recent Activity -->
            <section>
              <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-medium text-neutral-900 dark:text-white">Recent Activity</h3>
                <button
                  type="button"
                  class="text-xs text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-300"
                  @click="activeTab = 'activity'"
                >
                  View all
                </button>
              </div>
              <div class="bg-neutral-50 dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700 divide-y divide-neutral-200 dark:divide-neutral-700">
                <div
                  v-for="activity in recentActivity"
                  :key="activity.id"
                  class="px-4 py-3 flex items-center gap-3"
                >
                  <div
                    :class="[
                      'w-8 h-8 rounded-lg flex items-center justify-center shrink-0',
                      activity.type === 'completed' ? 'bg-green-100 dark:bg-green-900/30' : 'bg-neutral-100 dark:bg-neutral-700'
                    ]"
                  >
                    <Icon
                      :name="activity.type === 'completed' ? 'ph:check' : 'ph:clock'"
                      :class="[
                        'w-4 h-4',
                        activity.type === 'completed' ? 'text-green-600 dark:text-green-400' : 'text-neutral-500 dark:text-neutral-400'
                      ]"
                    />
                  </div>
                  <div class="flex-1 min-w-0">
                    <p class="text-sm text-neutral-900 dark:text-white truncate">{{ activity.description }}</p>
                    <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ formatTimeAgo(activity.timestamp) }}</p>
                  </div>
                </div>
                <div v-if="recentActivity.length === 0" class="px-4 py-6 text-center">
                  <p class="text-sm text-neutral-500 dark:text-neutral-400">No recent activity</p>
                </div>
              </div>
            </section>
          </div>

          <!-- Personality Tab -->
          <div v-if="activeTab === 'personality'">
            <AgentPersonalityEditor
              :personality="agent.personality"
              @save="savePersonality"
            />
          </div>

          <!-- Instructions Tab -->
          <div v-if="activeTab === 'instructions'">
            <AgentInstructionsEditor
              :instructions="agent.instructions"
              @save="saveInstructions"
            />
          </div>

          <!-- Capabilities Tab -->
          <div v-if="activeTab === 'capabilities'">
            <AgentCapabilities
              :capabilities="agent.capabilities"
              :notes="capabilityNotes"
              @save-notes="saveCapabilityNotes"
            />
          </div>

          <!-- Memory Tab -->
          <div v-if="activeTab === 'memory'">
            <AgentMemoryView
              :session="agent.currentSession"
              :memory-entries="agent.memoryEntries || []"
              @new-session="startNewSession"
              @view-history="viewSessionHistory"
              @add-memory="addMemoryEntry"
              @delete-memory="deleteMemoryEntry"
            />
          </div>

          <!-- Activity Tab -->
          <div v-if="activeTab === 'activity'" class="bg-neutral-50 dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700">
            <div class="p-4 border-b border-neutral-200 dark:border-neutral-700">
              <h2 class="text-sm font-medium text-neutral-900 dark:text-white">Activity Log</h2>
            </div>
            <div class="max-h-[600px] overflow-y-auto divide-y divide-neutral-200 dark:divide-neutral-700">
              <div
                v-for="activity in activityLog"
                :key="activity.id"
                class="px-4 py-3 flex items-center gap-3"
              >
                <div
                  :class="[
                    'w-8 h-8 rounded-lg flex items-center justify-center shrink-0',
                    getActivityBg(activity.type)
                  ]"
                >
                  <Icon
                    :name="getActivityIcon(activity.type)"
                    :class="['w-4 h-4', getActivityColor(activity.type)]"
                  />
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-sm text-neutral-900 dark:text-white">{{ activity.description }}</p>
                  <p class="text-xs text-neutral-500 dark:text-neutral-400">
                    {{ formatDateTime(activity.timestamp) }}
                    <span v-if="activity.duration"> · {{ formatDuration(activity.duration) }}</span>
                  </p>
                </div>
                <span
                  :class="[
                    'px-2 py-0.5 rounded text-xs font-medium capitalize shrink-0',
                    getActivityBadge(activity.type)
                  ]"
                >
                  {{ activity.type === 'working' ? 'In Progress' : activity.type }}
                </span>
              </div>
              <div v-if="activityLog.length === 0" class="p-8 text-center">
                <Icon name="ph:activity" class="w-8 h-8 text-neutral-300 dark:text-neutral-600 mx-auto mb-2" />
                <p class="text-sm text-neutral-500 dark:text-neutral-400">No activity recorded</p>
              </div>
            </div>
          </div>

          <!-- Settings Tab -->
          <div v-if="activeTab === 'settings'">
            <AgentSettingsPanel
              :settings="agent.settings"
              @update="updateSettings"
              @reset-memory="resetAgentMemory"
              @pause-agent="togglePause"
              @delete-agent="deleteAgent"
            />
          </div>
        </div>
      </template>

      <!-- Not Found -->
      <div v-else class="text-center py-20">
        <Icon name="ph:robot" class="w-12 h-12 mx-auto text-neutral-300 dark:text-neutral-600 mb-3" />
        <h2 class="text-lg font-semibold text-neutral-900 dark:text-white mb-1">Agent not found</h2>
        <p class="text-sm text-neutral-500 dark:text-neutral-400">The agent you're looking for doesn't exist.</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { Link } from '@inertiajs/vue3'
import Icon from '@/Components/shared/Icon.vue'
import SharedSkeleton from '@/Components/shared/Skeleton.vue'
import AgentIdentityCard from '@/Components/agents/AgentIdentityCard.vue'
import AgentPersonalityEditor from '@/Components/agents/AgentPersonalityEditor.vue'
import AgentInstructionsEditor from '@/Components/agents/AgentInstructionsEditor.vue'
import AgentCapabilities from '@/Components/agents/AgentCapabilities.vue'
import AgentMemoryView from '@/Components/agents/AgentMemoryView.vue'
import AgentSettingsPanel from '@/Components/agents/AgentSettingsPanel.vue'
import type { Agent, AgentType, AgentSettings } from '@/types'

type TabId = 'overview' | 'personality' | 'instructions' | 'capabilities' | 'memory' | 'activity' | 'settings'

interface ActivityItem {
  id: string
  type: 'completed' | 'working' | 'pending'
  description: string
  timestamp: string
  duration?: number
}

const props = defineProps<{
  id: string
}>()

const loading = ref(true)
const agent = ref<Agent | null>(null)
const activityLog = ref<ActivityItem[]>([])
const capabilityNotes = ref('')

const activeTab = ref<TabId>('overview')

const tabs: { id: TabId; label: string }[] = [
  { id: 'overview', label: 'Overview' },
  { id: 'personality', label: 'Personality' },
  { id: 'instructions', label: 'Instructions' },
  { id: 'capabilities', label: 'Capabilities' },
  { id: 'memory', label: 'Memory' },
  { id: 'activity', label: 'Activity' },
  { id: 'settings', label: 'Settings' },
]

const agentColors: Record<AgentType, string> = {
  manager: 'bg-purple-100 dark:bg-purple-900/30',
  writer: 'bg-green-100 dark:bg-green-900/30',
  analyst: 'bg-cyan-100 dark:bg-cyan-900/30',
  creative: 'bg-pink-100 dark:bg-pink-900/30',
  researcher: 'bg-amber-100 dark:bg-amber-900/30',
  coder: 'bg-indigo-100 dark:bg-indigo-900/30',
  coordinator: 'bg-teal-100 dark:bg-teal-900/30',
}

const agentBgColor = computed(() => {
  if (!agent.value) return 'bg-neutral-100 dark:bg-neutral-700'
  const type = agent.value.identity?.type || agent.value.agentType || 'coder'
  return agentColors[type] || 'bg-neutral-100 dark:bg-neutral-700'
})

const statusColor = computed(() => {
  if (!agent.value) return 'bg-neutral-400'
  switch (agent.value.status) {
    case 'working':
    case 'online':
      return 'bg-green-500'
    case 'busy':
      return 'bg-amber-500'
    case 'paused':
      return 'bg-yellow-500'
    default:
      return 'bg-neutral-400'
  }
})

const statusLabel = computed(() => {
  if (!agent.value) return 'Unknown'
  if (agent.value.status === 'working' && agent.value.currentTask) {
    return `Working on ${agent.value.currentTask}`
  }
  switch (agent.value.status) {
    case 'working':
      return 'Working'
    case 'online':
    case 'idle':
      return 'Available'
    case 'paused':
      return 'Paused'
    default:
      return 'Offline'
  }
})

const recentActivity = computed(() => {
  return activityLog.value.slice(0, 5)
})

const goBack = () => {
  window.history.back()
}

const fetchData = async () => {
  loading.value = true
  try {
    // Simulate API call with mock data
    await new Promise(resolve => setTimeout(resolve, 300))

    // Mock agent data
    agent.value = {
      id: props.id,
      name: 'Logic',
      type: 'agent',
      agentType: 'coder',
      status: 'working',
      currentTask: 'Implementing user authentication',
      identity: {
        name: 'Logic',
        emoji: '🤖',
        type: 'coder',
        description: 'Senior software engineer specializing in backend development',
      },
      personality: {
        content: `## Communication Style
- Be direct and concise
- Use technical terms when appropriate
- Ask clarifying questions before making assumptions

## Boundaries
- Never deploy to production without explicit approval
- Always explain reasoning for significant decisions`,
        updatedAt: new Date(),
      },
      instructions: {
        content: `## Primary Responsibilities
- Write clean, tested code
- Review pull requests
- Document technical decisions

## Workflow
1. Understand the task fully before starting
2. Break complex tasks into subtasks
3. Test changes locally before committing`,
        updatedAt: new Date(),
      },
      capabilities: [
        { id: '1', name: 'Code execution', description: 'Run Node.js and Python code', enabled: true, requiresApproval: false, icon: 'ph:code' },
        { id: '2', name: 'File operations', description: 'Read, write, and edit files', enabled: true, requiresApproval: false, icon: 'ph:file' },
        { id: '3', name: 'Git operations', description: 'Commit, push, and manage branches', enabled: true, requiresApproval: false, icon: 'ph:git-branch' },
        { id: '4', name: 'API requests', description: 'Make HTTP requests to external services', enabled: true, requiresApproval: false, icon: 'ph:globe' },
        { id: '5', name: 'Database access', description: 'Query and modify database', enabled: true, requiresApproval: true, icon: 'ph:database' },
        { id: '6', name: 'Production deployment', description: 'Deploy to production servers', enabled: false, requiresApproval: true, icon: 'ph:rocket-launch' },
      ],
      currentSession: {
        id: 'session-1',
        startedAt: new Date(Date.now() - 2 * 60 * 60 * 1000),
        messageCount: 24,
        tokenCount: 45000,
        maxTokens: 128000,
      },
      memoryEntries: [
        { id: '1', content: 'API credentials stored in vault', createdAt: new Date('2025-01-30'), category: 'fact' },
        { id: '2', content: 'User prefers TypeScript over JavaScript', createdAt: new Date('2025-01-28'), category: 'preference' },
        { id: '3', content: 'Project uses pnpm as package manager', createdAt: new Date('2025-01-25'), category: 'fact' },
      ],
      settings: {
        behaviorMode: 'supervised',
        costLimit: 100,
        resetPolicy: {
          mode: 'daily',
          dailyHour: 4,
        },
      },
      stats: {
        tasksCompleted: 127,
        efficiency: 94,
        creditsUsed: 45,
        totalSessions: 89,
      },
    } as Agent

    activityLog.value = [
      { id: '1', type: 'working', description: 'Implementing user authentication module', timestamp: new Date().toISOString() },
      { id: '2', type: 'completed', description: 'Fixed database connection timeout issue', timestamp: new Date(Date.now() - 30 * 60000).toISOString(), duration: 1200 },
      { id: '3', type: 'completed', description: 'Reviewed PR #234 for API refactoring', timestamp: new Date(Date.now() - 2 * 3600000).toISOString(), duration: 900 },
      { id: '4', type: 'completed', description: 'Created unit tests for payment service', timestamp: new Date(Date.now() - 4 * 3600000).toISOString(), duration: 2400 },
      { id: '5', type: 'completed', description: 'Updated documentation for deployment process', timestamp: new Date(Date.now() - 6 * 3600000).toISOString(), duration: 600 },
    ]

    capabilityNotes.value = 'Preferred test framework: Jest\nCode style: ESLint + Prettier\nDatabase: PostgreSQL with Prisma ORM'
  } catch (error) {
    console.error('Failed to fetch agent data:', error)
    agent.value = null
  } finally {
    loading.value = false
  }
}

const togglePause = () => {
  console.log('Toggle pause for agent:', props.id)
}

const savePersonality = (content: string) => {
  console.log('Save personality:', content)
}

const saveInstructions = (content: string) => {
  console.log('Save instructions:', content)
}

const saveCapabilityNotes = (notes: string) => {
  capabilityNotes.value = notes
  console.log('Save capability notes:', notes)
}

const startNewSession = () => {
  console.log('Start new session')
}

const viewSessionHistory = () => {
  console.log('View session history')
}

const addMemoryEntry = (entry: { content: string; category: string }) => {
  console.log('Add memory entry:', entry)
}

const deleteMemoryEntry = (id: string) => {
  console.log('Delete memory entry:', id)
}

const updateSettings = (settings: AgentSettings) => {
  console.log('Update settings:', settings)
}

const resetAgentMemory = () => {
  console.log('Reset agent memory')
}

const deleteAgent = () => {
  console.log('Delete agent')
}

const formatTimeAgo = (dateString: string): string => {
  const date = new Date(dateString)
  if (isNaN(date.getTime())) return ''
  const now = new Date()
  const diffMs = now.getTime() - date.getTime()
  const diffMins = Math.floor(diffMs / 60000)
  const diffHours = Math.floor(diffMs / 3600000)
  const diffDays = Math.floor(diffMs / 86400000)

  if (diffMins < 1) return 'just now'
  if (diffMins < 60) return `${diffMins}m ago`
  if (diffHours < 24) return `${diffHours}h ago`
  return `${diffDays}d ago`
}

const formatDateTime = (dateString: string) => {
  const date = new Date(dateString)
  return date.toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  })
}

const formatDuration = (seconds: number) => {
  if (seconds < 60) return `${seconds}s`
  if (seconds < 3600) return `${Math.floor(seconds / 60)}m`
  return `${Math.floor(seconds / 3600)}h ${Math.floor((seconds % 3600) / 60)}m`
}

const getActivityIcon = (type: string) => {
  switch (type) {
    case 'completed': return 'ph:check'
    case 'working': return 'ph:spinner'
    default: return 'ph:clock'
  }
}

const getActivityBg = (type: string) => {
  switch (type) {
    case 'completed': return 'bg-green-100 dark:bg-green-900/30'
    case 'working': return 'bg-blue-100 dark:bg-blue-900/30'
    default: return 'bg-neutral-100 dark:bg-neutral-700'
  }
}

const getActivityColor = (type: string) => {
  switch (type) {
    case 'completed': return 'text-green-600 dark:text-green-400'
    case 'working': return 'text-blue-600 dark:text-blue-400 animate-spin'
    default: return 'text-neutral-500 dark:text-neutral-400'
  }
}

const getActivityBadge = (type: string) => {
  switch (type) {
    case 'completed': return 'bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400'
    case 'working': return 'bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400'
    default: return 'bg-neutral-100 dark:bg-neutral-700 text-neutral-500 dark:text-neutral-400'
  }
}

onMounted(() => {
  fetchData()
})

watch(() => props.id, () => {
  fetchData()
})
</script>
