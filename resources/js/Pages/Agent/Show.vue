<template>
  <div class="min-h-screen bg-white p-6">
    <div class="max-w-6xl mx-auto">
      <!-- Back button -->
      <button
        type="button"
        class="flex items-center gap-2 text-sm text-gray-500 hover:text-gray-900 mb-6 transition-colors"
        @click="goBack"
      >
        <Icon name="ph:arrow-left" class="w-4 h-4" />
        Back
      </button>

      <!-- Loading State -->
      <div v-if="loading" class="space-y-6">
        <div class="flex items-center gap-4">
          <SharedSkeleton custom-class="w-20 h-20 rounded-full" />
          <div class="space-y-2">
            <SharedSkeleton custom-class="w-48 h-6 rounded" />
            <SharedSkeleton custom-class="w-32 h-4 rounded" />
          </div>
        </div>
        <SharedSkeleton custom-class="h-40 rounded-xl" />
        <SharedSkeleton custom-class="h-64 rounded-xl" />
      </div>

      <template v-else-if="agentData">
        <!-- Agent Header -->
        <div class="bg-gray-50 rounded-xl p-6 border border-gray-200 mb-6">
          <div class="flex items-start gap-6">
            <!-- Avatar -->
            <div class="relative">
              <div
                :class="[
                  'w-20 h-20 rounded-full flex items-center justify-center text-white text-2xl font-bold',
                  agentColorMap[agentData.agent.agentType || 'default'],
                ]"
              >
                {{ agentData.agent.name.charAt(0) }}
              </div>
              <!-- Status indicator -->
              <span
                :class="[
                  'absolute bottom-1 right-1 w-5 h-5 rounded-full border-4 border-gray-50',
                  statusColorMap[agentData.agent.status || 'offline'],
                ]"
              />
            </div>

            <!-- Info -->
            <div class="flex-1">
              <div class="flex items-center gap-3 mb-2">
                <h1 class="text-2xl font-bold text-gray-900">{{ agentData.agent.name }}</h1>
                <span
                  :class="[
                    'px-2 py-1 rounded-lg text-xs font-medium capitalize',
                    agentBgMap[agentData.agent.agentType || 'default'],
                  ]"
                >
                  {{ agentData.agent.agentType }} Agent
                </span>
                <span
                  v-if="agentData.agent.isTemporary"
                  class="px-2 py-1 rounded-lg text-xs font-medium bg-amber-500/20 text-amber-400"
                >
                  Temporary
                </span>
              </div>
              <p v-if="agentData.agent.currentTask" class="text-sm text-gray-500 mb-3">
                <Icon name="ph:play-circle" class="w-4 h-4 inline mr-1 text-green-400" />
                {{ agentData.agent.currentTask }}
              </p>
              <p v-else class="text-sm text-gray-500 mb-3">
                <Icon name="ph:pause-circle" class="w-4 h-4 inline mr-1 text-amber-400" />
                Idle - No active task
              </p>

              <!-- Resource Usage -->
              <div class="flex items-center gap-6 text-sm">
                <div class="flex items-center gap-2">
                  <Icon name="ph:cpu" class="w-4 h-4 text-gray-500" />
                  <span class="text-gray-500">CPU:</span>
                  <span :class="getResourceColor(agentData.agent.cpuUsage)">{{ agentData.agent.cpuUsage }}%</span>
                </div>
                <div class="flex items-center gap-2">
                  <Icon name="ph:memory" class="w-4 h-4 text-gray-500" />
                  <span class="text-gray-500">RAM:</span>
                  <span :class="getResourceColor(agentData.agent.memoryUsage)">{{ agentData.agent.memoryUsage }}%</span>
                </div>
                <div class="flex items-center gap-2">
                  <Icon name="ph:coins" class="w-4 h-4 text-amber-400" />
                  <span class="text-gray-500">Tokens:</span>
                  <span class="text-gray-900">{{ formatNumber(agentData.agent.tokensUsed) }}</span>
                </div>
              </div>
            </div>

            <!-- Quick Stats -->
            <div class="grid grid-cols-2 gap-4">
              <div class="text-center p-3 bg-white rounded-lg">
                <p class="text-2xl font-bold text-gray-900">{{ agentData.stats.completedTasks }}</p>
                <p class="text-xs text-gray-500">Tasks Done</p>
              </div>
              <div class="text-center p-3 bg-white rounded-lg">
                <p class="text-2xl font-bold text-gray-900">{{ agentData.stats.efficiency }}%</p>
                <p class="text-xs text-gray-500">Efficiency</p>
              </div>
              <div class="text-center p-3 bg-white rounded-lg">
                <p class="text-2xl font-bold text-gray-900">{{ formatCredits(agentData.stats.totalCreditsUsed) }}</p>
                <p class="text-xs text-gray-500">Credits Used</p>
              </div>
              <div class="text-center p-3 bg-white rounded-lg">
                <p class="text-2xl font-bold text-gray-900">{{ formatUptime(agentData.stats.uptimeHours) }}</p>
                <p class="text-xs text-gray-500">Uptime</p>
              </div>
            </div>

            <!-- Controls -->
            <div class="flex flex-col gap-2">
              <Link
                :href="`/messages/${agentData.agent.id}`"
                class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium bg-gray-100 text-gray-900 hover:bg-gray-200 transition-colors"
              >
                <Icon name="ph:chat-circle" class="w-4 h-4" />
                Message
              </Link>
              <button
                type="button"
                :class="[
                  'flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors',
                  agentData.agent.status === 'working'
                    ? 'bg-amber-500/20 text-amber-400 hover:bg-amber-500/30'
                    : 'bg-green-500/20 text-green-400 hover:bg-green-500/30',
                ]"
                @click="togglePause"
              >
                <Icon :name="agentData.agent.status === 'working' ? 'ph:pause' : 'ph:play'" class="w-4 h-4" />
                {{ agentData.agent.status === 'working' ? 'Pause' : 'Resume' }}
              </button>
              <button
                type="button"
                class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium bg-red-500/20 text-red-400 hover:bg-red-500/30 transition-colors"
                @click="cancelAgent"
              >
                <Icon name="ph:stop" class="w-4 h-4" />
                Stop Agent
              </button>
            </div>
          </div>
        </div>

        <!-- Content Tabs -->
        <div class="flex gap-2 mb-6">
          <button
            v-for="tab in tabs"
            :key="tab.id"
            type="button"
            :class="[
              'px-4 py-2 rounded-lg text-sm font-medium transition-colors',
              activeTab === tab.id
                ? 'bg-gray-900 text-white'
                : 'bg-gray-50 text-gray-500 hover:text-gray-900',
            ]"
            @click="activeTab = tab.id"
          >
            <Icon :name="tab.icon" class="w-4 h-4 mr-1.5 inline" />
            {{ tab.label }}
            <span v-if="tab.count" class="ml-1.5 px-1.5 py-0.5 rounded text-xs bg-white/20">
              {{ tab.count }}
            </span>
          </button>
        </div>

        <!-- Activity Log Tab -->
        <div v-if="activeTab === 'activity'" class="bg-gray-50 rounded-xl border border-gray-200 overflow-hidden">
          <div class="p-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Activity Log</h2>
            <p class="text-sm text-gray-500">Real-time activity stream</p>
          </div>
          <div class="max-h-[600px] overflow-y-auto">
            <div
              v-for="(activity, index) in agentData.activityLog"
              :key="activity.id"
              :class="[
                'flex items-start gap-4 p-4 border-b border-gray-200 last:border-b-0',
                activity.type === 'working' && 'bg-blue-500/5',
              ]"
            >
              <!-- Timeline indicator -->
              <div class="relative flex flex-col items-center">
                <div
                  :class="[
                    'w-10 h-10 rounded-full flex items-center justify-center shrink-0',
                    activity.type === 'completed' ? 'bg-green-500/20' : activity.type === 'working' ? 'bg-blue-500/20' : 'bg-gray-500/20',
                  ]"
                >
                  <Icon
                    :name="activity.type === 'completed' ? 'ph:check' : activity.type === 'working' ? 'ph:spinner' : 'ph:clock'"
                    :class="[
                      'w-5 h-5',
                      activity.type === 'completed' ? 'text-green-400' : activity.type === 'working' ? 'text-blue-400 animate-spin' : 'text-gray-400',
                    ]"
                  />
                </div>
                <div
                  v-if="index < agentData.activityLog.length - 1"
                  class="w-0.5 flex-1 bg-gray-200 mt-2"
                />
              </div>

              <!-- Content -->
              <div class="flex-1 min-w-0">
                <p class="text-sm text-gray-900">{{ activity.description }}</p>
                <div class="flex items-center gap-3 mt-1">
                  <p class="text-xs text-gray-500">
                    {{ formatDateTime(activity.timestamp) }}
                  </p>
                  <span
                    v-if="activity.duration"
                    class="text-xs text-gray-400"
                  >
                    {{ formatDuration(activity.duration) }}
                  </span>
                </div>
              </div>

              <!-- Status badge -->
              <span
                :class="[
                  'px-2 py-1 rounded text-xs font-medium capitalize shrink-0',
                  activity.type === 'completed' ? 'bg-green-500/20 text-green-400' : activity.type === 'working' ? 'bg-blue-500/20 text-blue-400' : 'bg-gray-500/20 text-gray-400',
                ]"
              >
                {{ activity.type === 'working' ? 'In Progress' : activity.type }}
              </span>
            </div>
            <div v-if="!agentData.activityLog?.length" class="p-8 text-center text-gray-500">
              No activity recorded yet
            </div>
          </div>
        </div>

        <!-- Tasks Tab -->
        <div v-if="activeTab === 'tasks'" class="space-y-3">
          <div
            v-for="task in agentData.tasks"
            :key="task.id"
            class="flex items-center gap-4 p-4 bg-gray-50 rounded-xl border border-gray-200"
          >
            <div
              :class="[
                'w-10 h-10 rounded-lg flex items-center justify-center shrink-0',
                task.status === 'done' ? 'bg-green-500/20' : task.status === 'in_progress' ? 'bg-blue-500/20' : 'bg-gray-500/20',
              ]"
            >
              <Icon
                :name="task.status === 'done' ? 'ph:check' : task.status === 'in_progress' ? 'ph:spinner' : 'ph:circle-dashed'"
                :class="[
                  'w-5 h-5',
                  task.status === 'done' ? 'text-green-400' : task.status === 'in_progress' ? 'text-blue-400' : 'text-gray-400',
                ]"
              />
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-gray-900">{{ task.title }}</p>
              <p class="text-xs text-gray-500 mt-1 line-clamp-1">{{ task.description }}</p>
            </div>
            <span
              :class="[
                'px-2 py-1 rounded text-xs font-medium capitalize',
                priorityClasses[task.priority],
              ]"
            >
              {{ task.priority }}
            </span>
            <span
              :class="[
                'px-2 py-1 rounded text-xs font-medium capitalize',
                task.status === 'done' ? 'bg-green-500/20 text-green-400' : task.status === 'in_progress' ? 'bg-blue-500/20 text-blue-400' : 'bg-gray-500/20 text-gray-400',
              ]"
            >
              {{ task.status.replace('_', ' ') }}
            </span>
          </div>
          <div v-if="!agentData.tasks?.length" class="text-center py-8 text-gray-500">
            No tasks assigned
          </div>
        </div>

        <!-- Messages Tab -->
        <div v-if="activeTab === 'messages'" class="space-y-3">
          <div
            v-for="message in agentData.recentMessages"
            :key="message.id"
            class="p-4 bg-gray-50 rounded-xl border border-gray-200"
          >
            <div class="flex items-center gap-2 mb-2">
              <span
                v-if="message.channel"
                class="text-xs font-medium text-gray-900"
              >
                #{{ message.channel.name }}
              </span>
              <span class="text-xs text-gray-500">
                {{ formatDateTime(message.timestamp) }}
              </span>
            </div>
            <p class="text-sm text-gray-900 line-clamp-3">{{ message.content }}</p>
          </div>
          <div v-if="!agentData.recentMessages?.length" class="text-center py-8 text-gray-500">
            No messages sent
          </div>
        </div>

        <!-- Credits Tab -->
        <div v-if="activeTab === 'credits'" class="space-y-3">
          <div
            v-for="transaction in agentData.creditTransactions"
            :key="transaction.id"
            class="flex items-center gap-4 p-4 bg-gray-50 rounded-xl border border-gray-200"
          >
            <div
              :class="[
                'w-10 h-10 rounded-lg flex items-center justify-center shrink-0',
                transaction.amount < 0 ? 'bg-red-500/20' : 'bg-green-500/20',
              ]"
            >
              <Icon
                :name="transaction.amount < 0 ? 'ph:arrow-down' : 'ph:arrow-up'"
                :class="['w-5 h-5', transaction.amount < 0 ? 'text-red-400' : 'text-green-400']"
              />
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-gray-900">{{ transaction.description }}</p>
              <p class="text-xs text-gray-500 mt-1">{{ formatDateTime(transaction.createdAt) }}</p>
            </div>
            <span
              :class="[
                'text-sm font-semibold',
                transaction.amount < 0 ? 'text-red-400' : 'text-green-400',
              ]"
            >
              {{ transaction.amount > 0 ? '+' : '' }}{{ formatCredits(transaction.amount) }}
            </span>
          </div>
          <div v-if="!agentData.creditTransactions?.length" class="text-center py-8 text-gray-500">
            No credit transactions
          </div>
        </div>
      </template>

      <!-- Not Found -->
      <div v-else class="text-center py-20">
        <Icon name="ph:robot" class="w-16 h-16 mx-auto text-gray-500 mb-4" />
        <h2 class="text-xl font-semibold text-gray-900 mb-2">Agent not found</h2>
        <p class="text-sm text-gray-500">The agent you're looking for doesn't exist or is not an agent.</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import SharedSkeleton from '@/Components/shared/Skeleton.vue'

interface AgentData {
  agent: {
    id: string
    name: string
    type: 'agent'
    agentType?: string
    status?: string
    currentTask?: string
    isTemporary?: boolean
    cpuUsage: number
    memoryUsage: number
    tokensUsed: number
  }
  activityLog: Array<{
    id: string
    type: 'completed' | 'working' | 'pending'
    description: string
    timestamp: string
    completedAt?: string
    duration?: number
  }>
  tasks: Array<{
    id: string
    title: string
    description: string
    status: string
    priority: string
  }>
  recentMessages: Array<{
    id: string
    content: string
    timestamp: string
    channel?: { id: string; name: string }
  }>
  creditTransactions: Array<{
    id: string
    type: string
    amount: number
    description: string
    createdAt: string
  }>
  stats: {
    totalCreditsUsed: number
    completedTasks: number
    inProgressTasks: number
    totalTasks: number
    uptimeHours: number
    efficiency: number
    messagesCount: number
  }
}

const props = defineProps<{
  id: string
}>()

const loading = ref(true)
const agentData = ref<AgentData | null>(null)
const activeTab = ref<'activity' | 'tasks' | 'messages' | 'credits'>('activity')

const tabs = computed(() => [
  { id: 'activity' as const, label: 'Activity', icon: 'ph:activity', count: agentData.value?.activityLog?.length },
  { id: 'tasks' as const, label: 'Tasks', icon: 'ph:check-square', count: agentData.value?.tasks?.length },
  { id: 'messages' as const, label: 'Messages', icon: 'ph:chat-circle', count: agentData.value?.recentMessages?.length },
  { id: 'credits' as const, label: 'Credits', icon: 'ph:coins' },
])

const agentColorMap: Record<string, string> = {
  manager: 'bg-purple-500',
  writer: 'bg-green-500',
  analyst: 'bg-cyan-500',
  creative: 'bg-pink-500',
  researcher: 'bg-amber-500',
  coder: 'bg-indigo-500',
  coordinator: 'bg-teal-500',
  default: 'bg-gray-500',
}

const agentBgMap: Record<string, string> = {
  manager: 'bg-purple-500/20 text-purple-400',
  writer: 'bg-green-500/20 text-green-400',
  analyst: 'bg-cyan-500/20 text-cyan-400',
  creative: 'bg-pink-500/20 text-pink-400',
  researcher: 'bg-amber-500/20 text-amber-400',
  coder: 'bg-indigo-500/20 text-indigo-400',
  coordinator: 'bg-teal-500/20 text-teal-400',
  default: 'bg-gray-500/20 text-gray-400',
}

const statusColorMap: Record<string, string> = {
  working: 'bg-green-400',
  idle: 'bg-amber-400',
  offline: 'bg-gray-400',
}

const priorityClasses: Record<string, string> = {
  low: 'bg-gray-500/20 text-gray-400',
  medium: 'bg-blue-500/20 text-blue-400',
  high: 'bg-amber-500/20 text-amber-400',
  urgent: 'bg-red-500/20 text-red-400',
}

const goBack = () => {
  window.history.back()
}

const fetchData = async () => {
  loading.value = true
  try {
    const response = await fetch(`/api/agents/${props.id}`)
    const data = await response.json()
    agentData.value = data
  } catch (error) {
    console.error('Failed to fetch agent data:', error)
    agentData.value = null
  } finally {
    loading.value = false
  }
}

const togglePause = () => {
  // In a real app, this would call an API to pause/resume the agent
  console.log('Toggle pause for agent:', props.id)
}

const cancelAgent = () => {
  // In a real app, this would call an API to stop the agent
  console.log('Cancel agent:', props.id)
}

const getResourceColor = (value: number): string => {
  if (value < 50) return 'text-green-400'
  if (value < 80) return 'text-amber-400'
  return 'text-red-400'
}

const formatNumber = (value: number) => {
  if (value < 1000) return value.toString()
  if (value < 1000000) return `${(value / 1000).toFixed(1)}K`
  return `${(value / 1000000).toFixed(2)}M`
}

const formatCredits = (value: number) => {
  return new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  }).format(value)
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
  if (seconds < 3600) return `${Math.floor(seconds / 60)}m ${seconds % 60}s`
  return `${Math.floor(seconds / 3600)}h ${Math.floor((seconds % 3600) / 60)}m`
}

const formatUptime = (hours: number) => {
  if (hours < 24) return `${hours}h`
  const days = Math.floor(hours / 24)
  return `${days}d ${hours % 24}h`
}

onMounted(() => {
  fetchData()
})

watch(() => props.id, () => {
  fetchData()
})
</script>
