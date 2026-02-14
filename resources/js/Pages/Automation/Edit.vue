<template>
  <div class="h-full flex flex-col">
    <!-- Header -->
    <header class="shrink-0 px-6 py-4 border-b border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <button
            type="button"
            class="p-2 -ml-2 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-800 text-neutral-500 dark:text-neutral-400 transition-colors"
            @click="goBack"
          >
            <Icon name="ph:arrow-left" class="w-5 h-5" />
          </button>
          <div>
            <h1 class="text-xl font-semibold text-neutral-900 dark:text-white">Edit Automation</h1>
            <p class="text-sm text-neutral-500 dark:text-neutral-400 mt-0.5">
              Update this scheduled automation
            </p>
          </div>
        </div>
        <div class="flex items-center gap-3">
          <Button variant="ghost" @click="goBack">Cancel</Button>
          <Button variant="primary" :loading="saving" :disabled="!isValid" @click="handleSave">
            <Icon name="ph:check" class="w-4 h-4 mr-1" />
            Update
          </Button>
        </div>
      </div>
    </header>

    <!-- Loading -->
    <div v-if="loading" class="flex-1 flex items-center justify-center">
      <Icon name="ph:spinner" class="w-6 h-6 text-neutral-400 animate-spin" />
    </div>

    <!-- Content -->
    <div v-else class="flex-1 overflow-y-auto">
      <div class="max-w-5xl mx-auto px-6 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
          <!-- Left: Form fields -->
          <div class="space-y-6">
            <!-- Name -->
            <div class="space-y-1.5">
              <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-200">Name</label>
              <input
                v-model="form.name"
                type="text"
                class="w-full bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-xl px-4 py-2.5 text-sm text-neutral-900 dark:text-white placeholder:text-neutral-400 focus:outline-none focus:ring-2 focus:ring-neutral-400 dark:focus:ring-neutral-500"
                placeholder="e.g., Morning standup report"
              />
            </div>

            <!-- Agent -->
            <div class="space-y-1.5">
              <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-200">Agent</label>
              <select
                v-model="form.agentId"
                class="w-full bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-xl px-4 py-2.5 text-sm text-neutral-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-neutral-400 dark:focus:ring-neutral-500"
              >
                <option value="" disabled>Select an agent...</option>
                <option v-for="agent in agents" :key="agent.id" :value="agent.id">
                  {{ agent.name }}
                </option>
              </select>
            </div>

            <!-- Prompt -->
            <div class="space-y-1.5">
              <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-200">Prompt</label>
              <PromptEditor v-model="form.prompt" placeholder="What should the agent do on each run?" />
            </div>

            <!-- Timezone -->
            <div class="space-y-1.5">
              <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-200">Timezone</label>
              <select
                v-model="form.timezone"
                class="w-full bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-xl px-4 py-2.5 text-sm text-neutral-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-neutral-400 dark:focus:ring-neutral-500"
              >
                <option v-for="tz in timezones" :key="tz" :value="tz">{{ tz }}</option>
              </select>
            </div>

            <!-- Conversation History -->
            <div class="space-y-1.5">
              <div class="flex items-center justify-between">
                <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-200">
                  Conversation history
                </label>
                <button
                  type="button"
                  :class="[
                    'relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-neutral-400 focus:ring-offset-2 dark:focus:ring-offset-neutral-900',
                    form.keepHistory ? 'bg-green-500' : 'bg-neutral-300 dark:bg-neutral-600',
                  ]"
                  @click="form.keepHistory = !form.keepHistory"
                >
                  <span
                    :class="[
                      'pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                      form.keepHistory ? 'translate-x-4' : 'translate-x-0',
                    ]"
                  />
                </button>
              </div>
              <p class="text-xs text-neutral-400">
                {{ form.keepHistory
                  ? 'Agent sees messages from previous runs as context'
                  : 'Channel is cleared before each run — agent starts fresh'
                }}
              </p>
            </div>
          </div>

          <!-- Right: Schedule builder -->
          <div class="space-y-6">
            <div class="space-y-1.5">
              <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-200">Schedule</label>
              <CronBuilder v-model="form.cronExpression" :timezone="form.timezone" />
            </div>
          </div>
        </div>

        <!-- Run History -->
        <div v-if="runs.length" class="mt-10">
          <h2 class="text-sm font-semibold text-neutral-700 dark:text-neutral-200 mb-3">Run History</h2>
          <div class="border border-neutral-200 dark:border-neutral-700 rounded-xl overflow-hidden">
            <div
              v-for="run in runs"
              :key="run.id"
              class="flex items-center gap-4 px-4 py-3 hover:bg-neutral-50 dark:hover:bg-neutral-800/50 cursor-pointer transition-colors border-b border-neutral-100 dark:border-neutral-800 last:border-b-0"
              @click="router.visit(`/tasks/${run.id}`)"
            >
              <!-- Run number -->
              <span class="text-xs font-mono text-neutral-400 w-8 shrink-0">
                #{{ run.runNumber ?? '—' }}
              </span>

              <!-- Status badge -->
              <Badge
                :variant="run.status === 'completed' ? 'success' : run.status === 'failed' ? 'error' : 'warning'"
                :label="run.status"
                size="xs"
                shape="pill"
              />

              <!-- Result preview -->
              <span class="flex-1 text-sm text-neutral-600 dark:text-neutral-300 truncate">
                <template v-if="run.result?.error">
                  <span class="text-red-500 dark:text-red-400">{{ run.result.error }}</span>
                </template>
                <template v-else-if="run.result?.response">
                  {{ run.result.response }}
                </template>
                <template v-else>
                  {{ run.title }}
                </template>
              </span>

              <!-- Timestamp -->
              <span class="text-xs text-neutral-400 shrink-0">
                {{ formatRelativeTime(run.createdAt) }}
              </span>

              <Icon name="ph:arrow-right" class="w-4 h-4 text-neutral-300 dark:text-neutral-600 shrink-0" />
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import type { User } from '@/types'
import Icon from '@/Components/shared/Icon.vue'
import Button from '@/Components/shared/Button.vue'
import Badge from '@/Components/shared/Badge.vue'
import CronBuilder from '@/Components/automation/CronBuilder.vue'
import PromptEditor from '@/Components/automation/PromptEditor.vue'
import { useApi } from '@/composables/useApi'

const props = defineProps<{
  automationId: string
}>()

const { fetchScheduledAutomation, updateScheduledAutomation, fetchAgents, fetchAutomationRuns } = useApi()

const { data: automationData, loading } = fetchScheduledAutomation(props.automationId)
const { data: agentsData } = fetchAgents()
const { data: runsData } = fetchAutomationRuns(props.automationId)

const agents = computed<User[]>(() => agentsData.value ?? [])
const runs = computed(() => runsData.value ?? [])

const saving = ref(false)

const form = ref({
  name: '',
  agentId: '',
  prompt: '',
  cronExpression: '0 9 * * 1-5',
  timezone: 'UTC',
  keepHistory: true,
})

const timezones = [
  'UTC',
  'America/New_York',
  'America/Chicago',
  'America/Denver',
  'America/Los_Angeles',
  'America/Sao_Paulo',
  'Europe/London',
  'Europe/Paris',
  'Europe/Berlin',
  'Europe/Amsterdam',
  'Europe/Moscow',
  'Asia/Dubai',
  'Asia/Kolkata',
  'Asia/Singapore',
  'Asia/Tokyo',
  'Asia/Shanghai',
  'Australia/Sydney',
  'Pacific/Auckland',
]

// Populate form when automation data loads
watch(automationData, (automation) => {
  if (automation) {
    form.value = {
      name: automation.name,
      agentId: automation.agentId,
      prompt: automation.prompt,
      cronExpression: automation.cronExpression,
      timezone: automation.timezone,
      keepHistory: automation.keepHistory ?? true,
    }
  }
}, { immediate: true })

const isValid = computed(() =>
  form.value.name.trim() &&
  form.value.agentId &&
  form.value.prompt.trim() &&
  form.value.cronExpression,
)

function goBack() {
  router.visit('/automation')
}

function formatRelativeTime(dateStr: string): string {
  const date = new Date(dateStr)
  const now = new Date()
  const diffMs = now.getTime() - date.getTime()
  const diffMins = Math.floor(diffMs / 60000)
  if (diffMins < 1) return 'just now'
  if (diffMins < 60) return `${diffMins}m ago`
  const diffHours = Math.floor(diffMins / 60)
  if (diffHours < 24) return `${diffHours}h ago`
  const diffDays = Math.floor(diffHours / 24)
  if (diffDays < 7) return `${diffDays}d ago`
  return date.toLocaleDateString()
}

async function handleSave() {
  if (!isValid.value || saving.value) return
  saving.value = true
  try {
    await updateScheduledAutomation(props.automationId, {
      name: form.value.name.trim(),
      agentId: form.value.agentId,
      prompt: form.value.prompt.trim(),
      cronExpression: form.value.cronExpression,
      timezone: form.value.timezone,
      keepHistory: form.value.keepHistory,
    })
    router.visit('/automation')
  } finally {
    saving.value = false
  }
}
</script>
