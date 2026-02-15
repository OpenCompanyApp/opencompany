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
        <!-- Sleep Status Banner -->
        <div
          v-if="agentSleepingUntil"
          class="mb-4 flex items-center justify-between gap-3 px-4 py-3 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800"
        >
          <div class="flex items-center gap-3 min-w-0">
            <Icon name="ph:moon" class="w-5 h-5 text-amber-600 dark:text-amber-400 shrink-0" />
            <div class="min-w-0">
              <p class="text-sm font-medium text-amber-800 dark:text-amber-200">
                Sleeping until {{ new Date(agentSleepingUntil).toLocaleString() }}
              </p>
              <p v-if="agentSleepingReason" class="text-xs text-amber-600 dark:text-amber-400 truncate">
                {{ agentSleepingReason }}
              </p>
            </div>
          </div>
          <button
            type="button"
            class="shrink-0 px-3 py-1.5 text-sm font-medium rounded-lg bg-amber-600 text-white hover:bg-amber-700 transition-colors"
            @click="handleSleepChange({ sleepingUntil: null, sleepingReason: null })"
          >
            <Icon name="ph:sun" class="w-4 h-4 mr-1 inline" />
            Wake Up
          </button>
        </div>

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
              :href="workspacePath(`/messages/${agent.id}`)"
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

            <!-- Manager & Direct Reports -->
            <section v-if="agentManager || agentDirectReports.length > 0">
              <h3 class="text-sm font-medium text-neutral-900 dark:text-white mb-3">Hierarchy</h3>
              <div class="bg-neutral-50 dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700 divide-y divide-neutral-200 dark:divide-neutral-700">
                <!-- Manager -->
                <div v-if="agentManager" class="px-4 py-3 flex items-center gap-3">
                  <div class="w-8 h-8 rounded-lg bg-neutral-200 dark:bg-neutral-700 flex items-center justify-center text-sm shrink-0">
                    {{ agentManager.type === 'agent' ? '🤖' : '👤' }}
                  </div>
                  <div class="flex-1 min-w-0">
                    <p class="text-xs text-neutral-500 dark:text-neutral-400">Reports to</p>
                    <Link
                      :href="workspacePath(agentManager.type === 'agent' ? `/agent/${agentManager.id}` : `/profile/${agentManager.id}`)"
                      class="text-sm font-medium text-neutral-900 dark:text-white hover:underline"
                    >
                      {{ agentManager.name }}
                    </Link>
                  </div>
                  <Icon name="ph:arrow-up-right" class="w-4 h-4 text-neutral-400 shrink-0" />
                </div>

                <!-- Direct Reports -->
                <div v-if="agentDirectReports.length > 0" class="p-4 space-y-2">
                  <p class="text-xs text-neutral-500 dark:text-neutral-400 mb-2">
                    {{ agentDirectReports.length }} direct report{{ agentDirectReports.length !== 1 ? 's' : '' }}
                  </p>
                  <Link
                    v-for="report in agentDirectReports"
                    :key="report.id"
                    :href="workspacePath(`/agent/${report.id}`)"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors"
                  >
                    <div class="w-7 h-7 rounded-lg bg-neutral-200 dark:bg-neutral-700 flex items-center justify-center text-xs shrink-0">
                      🤖
                    </div>
                    <div class="flex-1 min-w-0">
                      <p class="text-sm font-medium text-neutral-900 dark:text-white truncate">{{ report.name }}</p>
                      <p class="text-xs text-neutral-500 dark:text-neutral-400 capitalize">{{ report.agent_type || 'agent' }}</p>
                    </div>
                    <span
                      :class="[
                        'w-2 h-2 rounded-full shrink-0',
                        report.status === 'working' || report.status === 'idle' ? 'bg-green-500' : 'bg-neutral-400'
                      ]"
                    />
                  </Link>
                </div>
              </div>
            </section>

            <!-- Delegation Queue -->
            <section v-if="agentAwaitingDelegationIds.length > 0">
              <h3 class="text-sm font-medium text-neutral-900 dark:text-white mb-3">
                Awaiting Delegations
                <span class="ml-1.5 px-1.5 py-0.5 text-xs font-medium rounded-full bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400">
                  {{ agentAwaitingDelegationIds.length }}
                </span>
              </h3>
              <div class="bg-neutral-50 dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700 divide-y divide-neutral-200 dark:divide-neutral-700">
                <div
                  v-for="delegationId in agentAwaitingDelegationIds"
                  :key="delegationId"
                  class="px-4 py-3 flex items-center gap-3 cursor-pointer hover:bg-neutral-100 dark:hover:bg-neutral-700/50 transition-colors"
                  @click="router.visit(workspacePath(`/tasks/${delegationId}`))"
                >
                  <Icon name="ph:arrow-bend-up-right" class="w-4 h-4 text-amber-500 shrink-0" />
                  <span class="text-sm text-neutral-600 dark:text-neutral-300 truncate">{{ delegationId }}</span>
                  <Icon name="ph:arrow-right" class="w-3.5 h-3.5 text-neutral-400 shrink-0 ml-auto" />
                </div>
              </div>
            </section>

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

          <!-- Tasks Tab -->
          <div v-if="activeTab === 'tasks'" class="space-y-3">
            <div v-if="agentTasks.length === 0" class="text-center py-12">
              <Icon name="ph:check-square" class="w-12 h-12 mx-auto text-neutral-300 dark:text-neutral-600 mb-3" />
              <p class="text-sm text-neutral-500 dark:text-neutral-400">No tasks assigned to this agent</p>
            </div>
            <div
              v-for="task in agentTasks"
              :key="task.id"
              class="p-4 bg-white dark:bg-neutral-800 rounded-lg border border-neutral-200 dark:border-neutral-700 cursor-pointer hover:border-neutral-300 dark:hover:border-neutral-600 transition-colors"
              @click="openTaskDetail(task)"
            >
              <div class="flex items-start justify-between gap-4">
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2 mb-1">
                    <span
                      :class="[
                        'inline-flex items-center gap-1.5 px-2 py-0.5 text-xs font-medium rounded-full',
                        taskStatusClasses[task.status]
                      ]"
                    >
                      <span :class="['w-1.5 h-1.5 rounded-full', taskStatusDots[task.status]]" />
                      {{ task.status }}
                    </span>
                    <span class="text-xs text-neutral-400 dark:text-neutral-500">
                      {{ task.type }}
                    </span>
                  </div>
                  <h4 class="font-medium text-neutral-900 dark:text-white truncate">
                    {{ task.title }}
                  </h4>
                  <p v-if="task.description" class="text-sm text-neutral-500 dark:text-neutral-400 line-clamp-1 mt-1">
                    {{ task.description }}
                  </p>
                </div>
                <div class="flex flex-col items-end gap-2 shrink-0">
                  <Icon :name="taskTypeIcons[task.type] || 'ph:gear'" class="w-4 h-4 text-neutral-400" />
                  <span v-if="task.steps?.length" class="text-xs text-neutral-400">
                    {{ task.steps.filter(s => s.status === 'completed').length }}/{{ task.steps.length }} steps
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Identity Tab -->
          <div v-if="activeTab === 'identity'">
            <AgentIdentityFiles
              :files="identityFiles"
              :saving="identityFileSaving"
              @save="saveIdentityFile"
            />
          </div>

          <!-- Capabilities Tab -->
          <div v-if="activeTab === 'capabilities'">
            <AgentCapabilities
              :capabilities="agent.capabilities"
              :app-groups="appGroups"
              :enabled-integrations="enabledIntegrations"
              :behavior-mode="behaviorMode"
              :must-wait-for-approval="mustWaitForApproval"
              :channel-permissions="channelPermissions"
              :folder-permissions="folderPermissions"
              :agent-channels="agentChannels"
              :document-folders="documentFolders"
              @update-behavior-mode="handleBehaviorModeChange"
              @update-integrations="handleIntegrations"
              @update-tool-permissions="handleToolPermissions"
              @update-channel-permissions="handleChannelPermissions"
              @update-folder-permissions="handleFolderPermissions"
              @update-must-wait-for-approval="handleMustWaitChange"
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
              :brain="agent.brain"
              :agent-id="agent.id"
              :manager-id="agentManagerId"
              :manager="agentManager"
              :sleeping-until="agentSleepingUntil"
              :sleeping-reason="agentSleepingReason"
              :on-brain-change="updateBrain"
              @update="updateSettings"
              @update-manager="handleManagerChange"
              @update-sleep="handleSleepChange"
              @reset-memory="resetAgentMemory"
              @pause-agent="togglePause"
              @delete-agent="deleteAgentHandler"
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
import { Link, router } from '@inertiajs/vue3'
import Icon from '@/Components/shared/Icon.vue'
import SharedSkeleton from '@/Components/shared/Skeleton.vue'
import AgentIdentityCard from '@/Components/agents/AgentIdentityCard.vue'
import AgentIdentityFiles from '@/Components/agents/AgentIdentityFiles.vue'
import AgentCapabilities from '@/Components/agents/AgentCapabilities.vue'
import AgentSettingsPanel from '@/Components/agents/AgentSettingsPanel.vue'
import { useApi } from '@/composables/useApi'
import { useWorkspace } from '@/composables/useWorkspace'
import type { Agent, AgentType, AgentBehaviorMode, AgentSettings, AgentTask } from '@/types'

const { workspacePath } = useWorkspace()

const { fetchAgentDetail, fetchAgentIdentityFiles, updateAgentIdentityFile, updateAgent, deleteAgent: deleteAgentApi, updateAgentToolPermissions, updateAgentChannelPermissions, updateAgentFolderPermissions, updateAgentIntegrations } = useApi()

type TabId = 'overview' | 'tasks' | 'identity' | 'capabilities' | 'activity' | 'settings'

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
const identityFiles = ref<{ type: string; title: string; content: string; updatedAt: string }[]>([])
const identityFileSaving = ref(false)
const agentTasks = ref<AgentTask[]>([])
const behaviorMode = ref<AgentBehaviorMode>('autonomous')
const mustWaitForApproval = ref(false)
const channelPermissions = ref<string[]>([])
const folderPermissions = ref<string[]>([])
const agentChannels = ref<{ id: string; name: string; type: string }[]>([])
const documentFolders = ref<{ id: string; title: string }[]>([])
const appGroups = ref<{ name: string; description: string; icon: string; logo?: string; isIntegration?: boolean }[]>([])
const enabledIntegrations = ref<string[]>([])
const agentManagerId = ref<string | null>(null)
const agentManager = ref<{ id: string; name: string; type: string; agentType?: string; avatar?: string } | null>(null)
const agentDirectReports = ref<{ id: string; name: string; type: string; agent_type?: string; status: string; avatar?: string }[]>([])
const agentSleepingUntil = ref<string | null>(null)
const agentSleepingReason = ref<string | null>(null)
const agentAwaitingDelegationIds = ref<string[]>([])

const activeTab = ref<TabId>('overview')

const tabs: { id: TabId; label: string }[] = [
  { id: 'overview', label: 'Overview' },
  { id: 'tasks', label: 'Tasks' },
  { id: 'identity', label: 'Identity' },
  { id: 'capabilities', label: 'Capabilities' },
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
    case 'awaiting_approval':
      return 'bg-amber-500'
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
    case 'awaiting_approval':
      return 'Waiting for approval'
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
    // Fetch agent detail (includes identity files, tasks, capabilities)
    const agentFetch = fetchAgentDetail(props.id)
    await agentFetch.promise

    const raw = agentFetch.data.value as Record<string, unknown> | null
    if (!raw) {
      agent.value = null
      return
    }

    // Map API response to Agent interface
    agent.value = {
      id: raw.id as string,
      name: raw.name as string,
      type: raw.type as 'agent',
      agentType: (raw.agentType as AgentType) || 'coder',
      status: (raw.status as string) || 'idle',
      currentTask: raw.currentTask as string | undefined,
      brain: raw.brain as string | undefined,
      identity: raw.identity as Agent['identity'],
      capabilities: (raw.capabilities as Agent['capabilities']) || [],
      settings: {
        behaviorMode: 'supervised',
        costLimit: 100,
        resetPolicy: { mode: 'daily', dailyHour: 4 },
      },
      stats: raw.stats as Agent['stats'],
    } as Agent

    behaviorMode.value = (raw.behaviorMode as AgentBehaviorMode) || 'autonomous'
    mustWaitForApproval.value = (raw.mustWaitForApproval as boolean) || false
    channelPermissions.value = (raw.channelPermissions as string[]) || []
    folderPermissions.value = (raw.folderPermissions as string[]) || []
    agentChannels.value = (raw.agentChannels as { id: string; name: string; type: string }[]) || []
    documentFolders.value = (raw.documentFolders as { id: string; title: string }[]) || []
    appGroups.value = (raw.appGroups as typeof appGroups.value) || []
    enabledIntegrations.value = (raw.enabledIntegrations as string[]) || []
    agentManagerId.value = (raw.managerId as string) || null
    agentManager.value = (raw.manager as typeof agentManager.value) || null
    agentDirectReports.value = (raw.directReports as typeof agentDirectReports.value) || []
    agentSleepingUntil.value = (raw.sleepingUntil as string) || null
    agentSleepingReason.value = (raw.sleepingReason as string) || null
    agentAwaitingDelegationIds.value = (raw.awaitingDelegationIds as string[]) || []

    // Map tasks from the detail response
    const rawTasks = (raw.tasks as AgentTask[]) || []
    agentTasks.value = rawTasks

    // Fetch identity files
    try {
      const identityFetch = fetchAgentIdentityFiles(props.id)
      await identityFetch.promise
      identityFiles.value = (identityFetch.data.value as typeof identityFiles.value) || []
    } catch (e) {
      console.error('Failed to fetch identity files:', e)
    }

    // Build activity log from completed tasks
    activityLog.value = rawTasks
      .filter((t: AgentTask) => t.status === 'completed' || t.status === 'active')
      .map((t: AgentTask) => ({
        id: t.id,
        type: t.status === 'completed' ? 'completed' as const : 'working' as const,
        description: t.title,
        timestamp: (t.completedAt || t.startedAt || t.createdAt)?.toString() || new Date().toISOString(),
      }))
  } catch (error) {
    console.error('Failed to fetch agent data:', error)
    agent.value = null
  } finally {
    loading.value = false
  }
}

const togglePause = async () => {
  if (!agent.value) return
  const newStatus = agent.value.status === 'working' ? 'idle' : 'working'
  try {
    await updateAgent(props.id, { status: newStatus })
    agent.value = { ...agent.value, status: newStatus } as Agent
  } catch (e) {
    console.error('Failed to toggle pause:', e)
  }
}

const saveIdentityFile = async (fileType: string, content: string) => {
  identityFileSaving.value = true
  try {
    await updateAgentIdentityFile(props.id, fileType, content)
    // Update the local identity files array
    const idx = identityFiles.value.findIndex(f => f.type === fileType)
    if (idx >= 0) {
      identityFiles.value[idx] = { ...identityFiles.value[idx], content, updatedAt: new Date().toISOString() }
    }
  } catch (e) {
    console.error('Failed to save identity file:', e)
  } finally {
    identityFileSaving.value = false
  }
}

const handleMustWaitChange = async (value: boolean) => {
  try {
    await updateAgent(props.id, { mustWaitForApproval: value })
    mustWaitForApproval.value = value
  } catch (e) {
    console.error('Failed to update must-wait setting:', e)
  }
}

const handleBehaviorModeChange = async (mode: AgentBehaviorMode) => {
  try {
    await updateAgent(props.id, { behaviorMode: mode })
    behaviorMode.value = mode
  } catch (e) {
    console.error('Failed to update behavior mode:', e)
  }
}

const handleIntegrations = async (integrations: string[]) => {
  try {
    await updateAgentIntegrations(props.id, integrations)
    enabledIntegrations.value = integrations
  } catch (e) {
    console.error('Failed to update integrations:', e)
  }
}

const handleToolPermissions = async (tools: { scopeKey: string; permission: string; requiresApproval: boolean }[]) => {
  try {
    await updateAgentToolPermissions(props.id, tools)
    // Refresh agent data to get updated capabilities
    await fetchData()
  } catch (e) {
    console.error('Failed to update tool permissions:', e)
  }
}

const handleChannelPermissions = async (channels: string[]) => {
  try {
    await updateAgentChannelPermissions(props.id, channels)
    channelPermissions.value = channels
  } catch (e) {
    console.error('Failed to update channel permissions:', e)
  }
}

const handleFolderPermissions = async (folders: string[]) => {
  try {
    await updateAgentFolderPermissions(props.id, folders)
    folderPermissions.value = folders
  } catch (e) {
    console.error('Failed to update folder permissions:', e)
  }
}

const handleManagerChange = async (managerId: string | null) => {
  try {
    await updateAgent(props.id, { managerId })
    agentManagerId.value = managerId
    // Refresh to get updated manager details
    await fetchData()
  } catch (e) {
    console.error('Failed to update manager:', e)
  }
}

const handleSleepChange = async (data: { sleepingUntil: string | null; sleepingReason: string | null }) => {
  try {
    await updateAgent(props.id, {
      sleepingUntil: data.sleepingUntil,
      sleepingReason: data.sleepingReason,
    })
    agentSleepingUntil.value = data.sleepingUntil
    agentSleepingReason.value = data.sleepingReason
  } catch (e) {
    console.error('Failed to update sleep status:', e)
  }
}

const updateSettings = async (settings: AgentSettings) => {
  if (!agent.value) return
  try {
    await updateAgent(props.id, {
      behaviorMode: settings.behaviorMode,
    })
    agent.value = { ...agent.value, settings } as Agent
  } catch (e) {
    console.error('Failed to update settings:', e)
  }
}

const updateBrain = async (brain: string) => {
  if (!agent.value) throw new Error('No agent loaded')
  await updateAgent(props.id, { brain })
  agent.value = { ...agent.value, brain } as Agent
}

const resetAgentMemory = async () => {
  try {
    await updateAgentIdentityFile(props.id, 'MEMORY', '# Long-term Memory\n\n(Cleared)')
  } catch (e) {
    console.error('Failed to reset memory:', e)
  }
}

const deleteAgentHandler = async () => {
  if (!confirm('Are you sure you want to delete this agent?')) return
  try {
    await deleteAgentApi(props.id)
    window.location.href = '/'
  } catch (e) {
    console.error('Failed to delete agent:', e)
  }
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

// Task helpers
const taskStatusClasses: Record<string, string> = {
  pending: 'bg-neutral-100 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-400',
  active: 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-600 dark:text-yellow-400',
  paused: 'bg-orange-100 dark:bg-orange-900/30 text-orange-600 dark:text-orange-400',
  completed: 'bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400',
  failed: 'bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400',
  cancelled: 'bg-neutral-100 dark:bg-neutral-700 text-neutral-500 dark:text-neutral-500',
}

const taskStatusDots: Record<string, string> = {
  pending: 'bg-neutral-400',
  active: 'bg-yellow-500',
  paused: 'bg-orange-500',
  completed: 'bg-green-500',
  failed: 'bg-red-500',
  cancelled: 'bg-neutral-400',
}

const taskTypeIcons: Record<string, string> = {
  ticket: 'ph:ticket',
  request: 'ph:envelope-simple',
  analysis: 'ph:chart-line',
  content: 'ph:article',
  research: 'ph:magnifying-glass',
  custom: 'ph:gear',
}

const openTaskDetail = (task: AgentTask) => {
  router.visit(workspacePath(`/tasks/${task.id}`))
}

onMounted(() => {
  fetchData()
})

watch(() => props.id, () => {
  fetchData()
})
</script>
