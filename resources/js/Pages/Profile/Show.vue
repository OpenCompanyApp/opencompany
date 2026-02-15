<template>
  <div class="min-h-screen bg-white dark:bg-neutral-900 p-6">
    <div class="max-w-4xl mx-auto">
      <!-- Back button -->
      <button
        type="button"
        class="flex items-center gap-2 text-sm text-neutral-500 dark:text-neutral-300 hover:text-neutral-900 dark:hover:text-white mb-6 transition-colors"
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
      </div>

      <template v-else-if="user">
        <!-- Profile Header -->
        <div class="bg-neutral-50 dark:bg-neutral-800 rounded-xl p-6 border border-neutral-200 dark:border-neutral-700 mb-6">
          <div class="flex items-start gap-6">
            <!-- Avatar -->
            <div class="relative">
              <div
                v-if="user.avatar"
                class="w-20 h-20 rounded-full overflow-hidden"
              >
                <img :src="user.avatar" :alt="user.name" class="w-full h-full object-cover" />
              </div>
              <div
                v-else
                :class="[
                  'w-20 h-20 rounded-full flex items-center justify-center text-white text-2xl font-bold',
                  user.type === 'human' ? 'bg-blue-500' : agentColorMap[user.agentType || 'default'],
                ]"
              >
                {{ user.name.charAt(0) }}
              </div>
              <!-- Status indicator -->
              <span
                v-if="user.type === 'agent'"
                :class="[
                  'absolute bottom-1 right-1 w-5 h-5 rounded-full border-4 border-neutral-50 dark:border-neutral-800',
                  statusColorMap[user.status || 'offline'],
                ]"
              />
            </div>

            <!-- Info -->
            <div class="flex-1">
              <div class="flex items-center gap-3 mb-2">
                <h1 class="text-2xl font-bold text-neutral-900 dark:text-white">{{ user.name }}</h1>
                <span
                  v-if="user.type === 'agent'"
                  :class="[
                    'px-2 py-1 rounded-lg text-xs font-medium capitalize',
                    agentBgMap[user.agentType || 'default'],
                  ]"
                >
                  {{ user.agentType }} Agent
                </span>
                <span
                  v-if="user.isEphemeral"
                  class="px-2 py-1 rounded-lg text-xs font-medium bg-amber-500/20 text-amber-400"
                >
                  Ephemeral
                </span>
              </div>
              <p v-if="user.email" class="text-sm text-neutral-500 dark:text-neutral-300 mb-2">
                {{ user.email }}
              </p>
              <p v-if="user.currentTask" class="text-sm text-neutral-500 dark:text-neutral-300">
                <Icon name="ph:play-circle" class="w-4 h-4 inline mr-1 text-green-400" />
                {{ user.currentTask }}
              </p>
              <p v-if="user.type === 'human'" class="text-sm text-neutral-500 dark:text-neutral-300">
                <Icon name="ph:user" class="w-4 h-4 inline mr-1" />
                Human Team Member
              </p>
              <div class="flex items-center gap-2 mt-2">
                <Link
                  v-if="user.id !== 'h1'"
                  :href="workspacePath(`/messages/${user.id}`)"
                  class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium bg-neutral-50 dark:bg-neutral-700 border border-neutral-200 dark:border-neutral-600 text-neutral-900 dark:text-white hover:bg-white dark:hover:bg-neutral-600 transition-colors"
                >
                  <Icon name="ph:chat-circle" class="w-4 h-4" />
                  Send Message
                </Link>
                <Link
                  v-if="user.type === 'agent'"
                  :href="workspacePath(`/agent/${user.id}`)"
                  class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium bg-neutral-100 dark:bg-neutral-700 text-neutral-900 dark:text-white hover:bg-neutral-200 dark:hover:bg-neutral-600 transition-colors"
                >
                  <Icon name="ph:gear" class="w-4 h-4" />
                  Manage Agent
                </Link>
              </div>
            </div>

            <!-- Quick Stats -->
            <div class="text-center p-3 bg-white dark:bg-neutral-700 rounded-lg">
              <p class="text-2xl font-bold text-neutral-900 dark:text-white">{{ activityData?.stats?.completedTasks || 0 }}</p>
              <p class="text-xs text-neutral-500 dark:text-neutral-300">Tasks Done</p>
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
                ? 'bg-neutral-900 text-white'
                : 'bg-neutral-50 dark:bg-neutral-800 text-neutral-500 dark:text-neutral-300 hover:text-neutral-900 dark:hover:text-white',
            ]"
            @click="activeTab = tab.id"
          >
            <Icon :name="tab.icon" class="w-4 h-4 mr-1.5 inline" />
            {{ tab.label }}
          </button>
        </div>

        <!-- Activity Steps Tab -->
        <div v-if="activeTab === 'activity'" class="space-y-3">
          <div
            v-for="step in activityData?.steps"
            :key="step.id"
            class="flex items-start gap-4 p-4 bg-neutral-50 dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700"
          >
            <div
              :class="[
                'w-8 h-8 rounded-lg flex items-center justify-center shrink-0',
                step.status === 'completed' ? 'bg-green-500/20' : step.status === 'in_progress' ? 'bg-blue-500/20' : 'bg-neutral-500/20',
              ]"
            >
              <Icon
                :name="step.status === 'completed' ? 'ph:check' : step.status === 'in_progress' ? 'ph:play' : 'ph:clock'"
                :class="[
                  'w-4 h-4',
                  step.status === 'completed' ? 'text-green-400' : step.status === 'in_progress' ? 'text-blue-400' : 'text-neutral-400',
                ]"
              />
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-neutral-900 dark:text-white">{{ step.description }}</p>
              <p class="text-xs text-neutral-500 dark:text-neutral-300 mt-1">
                {{ formatDateTime(step.startedAt) }}
                <span v-if="step.completedAt"> - {{ formatDateTime(step.completedAt) }}</span>
              </p>
            </div>
            <span
              :class="[
                'px-2 py-1 rounded text-xs font-medium capitalize',
                step.status === 'completed' ? 'bg-green-500/20 text-green-400' : step.status === 'in_progress' ? 'bg-blue-500/20 text-blue-400' : 'bg-neutral-500/20 text-neutral-400',
              ]"
            >
              {{ step.status.replace('_', ' ') }}
            </span>
          </div>
          <div v-if="!activityData?.steps?.length" class="text-center py-8 text-neutral-500 dark:text-neutral-300">
            No activity steps recorded
          </div>
        </div>

        <!-- Tasks Tab -->
        <div v-if="activeTab === 'tasks'" class="space-y-3">
          <div
            v-for="task in activityData?.tasks"
            :key="task.id"
            class="flex items-center gap-4 p-4 bg-neutral-50 dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700"
          >
            <div
              :class="[
                'w-8 h-8 rounded-lg flex items-center justify-center shrink-0',
                task.status === 'done' ? 'bg-green-500/20' : task.status === 'in_progress' ? 'bg-blue-500/20' : 'bg-neutral-500/20',
              ]"
            >
              <Icon name="ph:check-square" :class="['w-4 h-4', task.status === 'done' ? 'text-green-400' : 'text-neutral-400']" />
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-neutral-900 dark:text-white">{{ task.title }}</p>
              <p class="text-xs text-neutral-500 dark:text-neutral-300 mt-1 line-clamp-1">{{ task.description }}</p>
            </div>
            <span
              :class="[
                'px-2 py-1 rounded text-xs font-medium capitalize',
                task.status === 'done' ? 'bg-green-500/20 text-green-400' : task.status === 'in_progress' ? 'bg-blue-500/20 text-blue-400' : 'bg-neutral-500/20 text-neutral-400',
              ]"
            >
              {{ task.status.replace('_', ' ') }}
            </span>
          </div>
          <div v-if="!activityData?.tasks?.length" class="text-center py-8 text-neutral-500 dark:text-neutral-300">
            No tasks assigned
          </div>
        </div>

      </template>

      <!-- Not Found -->
      <div v-else class="text-center py-20">
        <Icon name="ph:user-circle" class="w-16 h-16 mx-auto text-neutral-500 mb-4" />
        <h2 class="text-xl font-semibold text-neutral-900 dark:text-white mb-2">User not found</h2>
        <p class="text-sm text-neutral-500 dark:text-neutral-300">The user you're looking for doesn't exist.</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { Link } from '@inertiajs/vue3'
import Icon from '@/Components/shared/Icon.vue'
import SharedSkeleton from '@/Components/shared/Skeleton.vue'
import { useWorkspace } from '@/composables/useWorkspace'

const { workspacePath } = useWorkspace()

interface User {
  id: string
  name: string
  avatar?: string
  type: 'human' | 'agent'
  agentType?: string
  status?: string
  currentTask?: string
  email?: string
  isEphemeral?: boolean
}

interface ActivityData {
  steps: Array<{
    id: string
    description: string
    status: string
    startedAt: string
    completedAt?: string
  }>
  activities: Array<{
    id: string
    type: string
    description: string
    timestamp: string
  }>
  tasks: Array<{
    id: string
    title: string
    description: string
    status: string
  }>
  stats: {
    completedTasks: number
    inProgressTasks: number
    totalTasks: number
  }
}

const props = defineProps<{
  id: string
}>()

const loading = ref(true)
const user = ref<User | null>(null)
const activityData = ref<ActivityData | null>(null)
const activeTab = ref<'activity' | 'tasks'>('activity')

const tabs = [
  { id: 'activity' as const, label: 'Activity', icon: 'ph:activity' },
  { id: 'tasks' as const, label: 'Tasks', icon: 'ph:check-square' },
]

const agentColorMap: Record<string, string> = {
  manager: 'bg-purple-500',
  writer: 'bg-green-500',
  analyst: 'bg-cyan-500',
  creative: 'bg-pink-500',
  researcher: 'bg-amber-500',
  coder: 'bg-indigo-500',
  coordinator: 'bg-teal-500',
  default: 'bg-neutral-500',
}

const agentBgMap: Record<string, string> = {
  manager: 'bg-purple-500/20 text-purple-400',
  writer: 'bg-green-500/20 text-green-400',
  analyst: 'bg-cyan-500/20 text-cyan-400',
  creative: 'bg-pink-500/20 text-pink-400',
  researcher: 'bg-amber-500/20 text-amber-400',
  coder: 'bg-indigo-500/20 text-indigo-400',
  coordinator: 'bg-teal-500/20 text-teal-400',
  default: 'bg-neutral-500/20 text-neutral-400',
}

const statusColorMap: Record<string, string> = {
  working: 'bg-green-400',
  idle: 'bg-amber-400',
  offline: 'bg-neutral-400',
}

const goBack = () => {
  window.history.back()
}

const fetchData = async () => {
  loading.value = true
  try {
    const [userResponse, activityResponse] = await Promise.all([
      fetch(`/api/users/${props.id}`),
      fetch(`/api/users/${props.id}/activity`),
    ])
    const userData = await userResponse.json()
    const activity = await activityResponse.json()
    user.value = userData
    activityData.value = activity
  } catch (error) {
    console.error('Failed to fetch user data:', error)
    user.value = null
  } finally {
    loading.value = false
  }
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

onMounted(() => {
  fetchData()
})

watch(() => props.id, () => {
  fetchData()
})
</script>
