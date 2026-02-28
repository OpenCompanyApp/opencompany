<template>
  <div class="h-full flex flex-col overflow-hidden bg-neutral-50 dark:bg-[#181818]">
    <!-- Loading -->
    <template v-if="loading">
      <div class="flex-1 flex items-center justify-center">
        <Icon name="ph:spinner" class="w-6 h-6 text-neutral-400 animate-spin" />
      </div>
    </template>

    <template v-else>
      <!-- Toolbar -->
      <div class="flex items-center justify-between h-10 px-3 shrink-0 border-b border-neutral-200 dark:border-neutral-700/60 bg-white dark:bg-[#1f1f1f]">
        <div class="flex items-center gap-3 min-w-0 flex-1">
          <button
            type="button"
            class="text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 transition-colors shrink-0"
            @click="goBack"
          >
            <Icon name="ph:arrow-left" class="w-4 h-4" />
          </button>

          <input
            v-model="form.name"
            type="text"
            class="text-sm font-semibold text-neutral-900 dark:text-white bg-transparent border-none outline-none placeholder:text-neutral-400 dark:placeholder:text-neutral-500 min-w-0 w-48 focus:ring-0"
            placeholder="Untitled automation"
          />

          <span class="w-px h-4 bg-neutral-200 dark:bg-neutral-700 shrink-0" />

          <!-- Prompt / Script toggle -->
          <div class="flex items-center gap-0.5 bg-neutral-100 dark:bg-neutral-800 rounded-md p-0.5 shrink-0">
            <button
              type="button"
              :class="[
                'px-2 py-0.5 rounded text-xs font-medium transition-colors',
                form.executionType === 'prompt'
                  ? 'bg-white dark:bg-neutral-700 text-neutral-900 dark:text-white shadow-sm'
                  : 'text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-300',
              ]"
              @click="form.executionType = 'prompt'"
            >
              Prompt
            </button>
            <button
              type="button"
              :class="[
                'px-2 py-0.5 rounded text-xs font-medium transition-colors',
                form.executionType === 'script'
                  ? 'bg-white dark:bg-neutral-700 text-neutral-900 dark:text-white shadow-sm'
                  : 'text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-300',
              ]"
              @click="form.executionType = 'script'"
            >
              Script
            </button>
          </div>

          <template v-if="form.executionType === 'script'">
            <span class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-violet-100 dark:bg-violet-900/40 text-violet-600 dark:text-violet-400 leading-none shrink-0">
              Luau
            </span>
            <span class="w-px h-4 bg-neutral-200 dark:bg-neutral-700 shrink-0" />
            <a
              :href="workspacePath('/developer/tools')"
              target="_blank"
              class="flex items-center gap-1.5 text-xs text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 transition-colors shrink-0"
            >
              <Icon name="ph:book-open" class="w-3.5 h-3.5" />
              <span class="hidden sm:inline">API Reference</span>
            </a>
          </template>
        </div>

        <div class="flex items-center gap-1.5 shrink-0">
          <Button size="sm" variant="ghost" icon-left="ph:play-fill" :loading="running" :disabled="running" @click="handleRun">
            Run
          </Button>
          <Button size="sm" variant="primary" icon-left="ph:floppy-disk" :loading="saving" :disabled="!isValid" @click="handleSave">
            Save
          </Button>
        </div>
      </div>

      <!-- Main area: Editor + Sidebar -->
      <div class="flex-1 min-h-0 flex">
        <!-- Editor panel -->
        <div class="flex-1 min-w-0">
          <MonacoEditor
            v-model="content"
            :language="form.executionType === 'script' ? 'lua' : 'markdown'"
            @cursor-change="(line: number, col: number) => { cursorLine = line; cursorColumn = col }"
          />
        </div>

        <!-- Right sidebar -->
        <div class="w-72 shrink-0 border-l border-neutral-200 dark:border-neutral-700/60 bg-white dark:bg-[#1f1f1f] overflow-y-auto">
          <div class="p-4 space-y-5">
            <!-- Agent -->
            <div class="space-y-1.5">
              <label class="block text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">Agent</label>
              <select
                v-model="form.agentId"
                class="w-full bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg px-3 py-2 text-sm text-neutral-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-neutral-400 dark:focus:ring-neutral-500"
              >
                <option value="" disabled>Select agent...</option>
                <option v-for="agent in agents" :key="agent.id" :value="agent.id">
                  {{ agent.name }}
                </option>
              </select>
            </div>

            <!-- Schedule -->
            <div class="space-y-1.5">
              <label class="block text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">Schedule</label>
              <CronBuilder v-model="form.cronExpression" :timezone="form.timezone" />
            </div>

            <!-- Timezone -->
            <div class="space-y-1.5">
              <label class="block text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">Timezone</label>
              <select
                v-model="form.timezone"
                class="w-full bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg px-3 py-2 text-sm text-neutral-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-neutral-400 dark:focus:ring-neutral-500"
              >
                <option v-for="tz in timezones" :key="tz" :value="tz">{{ tz }}</option>
              </select>
            </div>

            <!-- Conversation History -->
            <div class="space-y-1.5">
              <div class="flex items-center justify-between">
                <label class="block text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">History</label>
                <button
                  type="button"
                  :class="[
                    'relative inline-flex h-4 w-7 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors',
                    form.keepHistory ? 'bg-green-500' : 'bg-neutral-300 dark:bg-neutral-600',
                  ]"
                  @click="form.keepHistory = !form.keepHistory"
                >
                  <span
                    :class="[
                      'pointer-events-none inline-block h-3 w-3 transform rounded-full bg-white shadow transition',
                      form.keepHistory ? 'translate-x-3' : 'translate-x-0',
                    ]"
                  />
                </button>
              </div>
              <p class="text-[11px] text-neutral-400 dark:text-neutral-500">
                {{ form.keepHistory ? 'Keeps conversation context between runs' : 'Fresh start each run' }}
              </p>
            </div>

            <!-- Run History -->
            <template v-if="runs.length">
              <div class="border-t border-neutral-200 dark:border-neutral-700/60 -mx-4 px-4 pt-4">
                <label class="block text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider mb-2">Recent Runs</label>
                <div class="space-y-1">
                  <button
                    v-for="run in runs.slice(0, 5)"
                    :key="run.id"
                    type="button"
                    class="w-full flex items-center gap-2 px-2 py-1.5 rounded-md text-left hover:bg-neutral-50 dark:hover:bg-neutral-800/50 transition-colors group"
                    @click="selectedRun = run"
                  >
                    <span
                      :class="[
                        'w-1.5 h-1.5 rounded-full shrink-0',
                        run.status === 'completed' ? 'bg-green-500' :
                        run.status === 'failed' ? 'bg-red-500' :
                        'bg-amber-500',
                      ]"
                    />
                    <span class="text-xs text-neutral-600 dark:text-neutral-300 truncate flex-1">
                      {{ run.status }}
                    </span>
                    <span class="text-[10px] text-neutral-400 dark:text-neutral-500 shrink-0">
                      {{ formatRelativeTime(run.createdAt) }}
                    </span>
                    <Icon name="ph:caret-right" class="w-3 h-3 text-neutral-300 dark:text-neutral-600 shrink-0 opacity-0 group-hover:opacity-100 transition-opacity" />
                  </button>
                </div>
              </div>
            </template>
          </div>
        </div>
      </div>

      <!-- Status bar -->
      <div class="flex items-center h-6 px-3 shrink-0 border-t border-neutral-200 dark:border-neutral-700/60 bg-white dark:bg-[#1f1f1f] text-[11px] text-neutral-400 dark:text-neutral-500 gap-3 select-none">
        <span class="font-medium">{{ form.executionType === 'script' ? 'Luau' : 'Markdown' }}</span>
        <span class="w-px h-3 bg-neutral-200 dark:bg-neutral-700" />
        <span>Ln {{ cursorLine }}, Col {{ cursorColumn }}</span>
      </div>
    </template>

    <!-- Run detail modal -->
    <Modal v-model:open="runModalOpen" title="Run Details" size="md">
      <template v-if="selectedRun">
        <div class="space-y-4">
          <!-- Status + timing row -->
          <div class="flex items-center gap-3">
            <span
              :class="[
                'inline-flex items-center gap-1.5 px-2 py-1 rounded-full text-xs font-medium',
                selectedRun.status === 'completed' ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400' :
                selectedRun.status === 'failed' ? 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400' :
                'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400',
              ]"
            >
              <span
                :class="[
                  'w-1.5 h-1.5 rounded-full',
                  selectedRun.status === 'completed' ? 'bg-green-500' :
                  selectedRun.status === 'failed' ? 'bg-red-500' :
                  'bg-amber-500',
                ]"
              />
              {{ selectedRun.status }}
            </span>
            <span v-if="selectedRun.runNumber" class="text-xs text-neutral-400 font-mono">
              Run #{{ selectedRun.runNumber }}
            </span>
          </div>

          <!-- Metadata -->
          <div class="grid grid-cols-2 gap-3 text-sm">
            <div>
              <span class="text-xs text-neutral-400 dark:text-neutral-500">Created</span>
              <p class="text-neutral-700 dark:text-neutral-200">{{ formatDateTime(selectedRun.createdAt) }}</p>
            </div>
            <div>
              <span class="text-xs text-neutral-400 dark:text-neutral-500">Duration</span>
              <p class="text-neutral-700 dark:text-neutral-200">{{ formatDuration(selectedRun.startedAt, selectedRun.completedAt) }}</p>
            </div>
            <div v-if="selectedRun.agentName">
              <span class="text-xs text-neutral-400 dark:text-neutral-500">Agent</span>
              <p class="text-neutral-700 dark:text-neutral-200">{{ selectedRun.agentName }}</p>
            </div>
          </div>

          <!-- Result -->
          <div v-if="selectedRun.result">
            <span class="text-xs text-neutral-400 dark:text-neutral-500">Result</span>
            <div class="mt-1 rounded-lg bg-neutral-50 dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 p-3 text-sm max-h-48 overflow-y-auto">
              <template v-if="selectedRun.result.error">
                <p class="text-red-600 dark:text-red-400 whitespace-pre-wrap">{{ selectedRun.result.error }}</p>
              </template>
              <template v-else-if="selectedRun.result.output">
                <p class="text-neutral-700 dark:text-neutral-200 whitespace-pre-wrap">{{ selectedRun.result.output }}</p>
              </template>
              <template v-else-if="selectedRun.result.response">
                <p class="text-neutral-700 dark:text-neutral-200 whitespace-pre-wrap">{{ selectedRun.result.response }}</p>
              </template>
              <template v-else>
                <p class="text-neutral-400 italic">No output</p>
              </template>
            </div>
            <div v-if="selectedRun.result.execution_time_ms || selectedRun.result.generation_time_ms" class="mt-1.5 text-[11px] text-neutral-400">
              {{ selectedRun.result.execution_time_ms ?? selectedRun.result.generation_time_ms }}ms
            </div>
          </div>
        </div>
      </template>

      <template #footer>
        <div class="flex justify-end">
          <Button
            v-if="selectedRun"
            size="sm"
            variant="ghost"
            @click="router.visit(workspacePath(`/tasks/${selectedRun.id}`))"
          >
            View task
            <Icon name="ph:arrow-right" class="w-3.5 h-3.5 ml-1" />
          </Button>
        </div>
      </template>
    </Modal>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import type { User } from '@/types'
import Icon from '@/Components/shared/Icon.vue'
import Button from '@/Components/shared/Button.vue'
import Modal from '@/Components/shared/Modal.vue'
import CronBuilder from '@/Components/automation/CronBuilder.vue'
import MonacoEditor from '@/Components/developer/MonacoEditor.vue'
import { useApi } from '@/composables/useApi'
import { useWorkspace } from '@/composables/useWorkspace'

const props = defineProps<{
  automationId: string
}>()

const { workspacePath } = useWorkspace()
const { fetchAutomation, updateAutomation, triggerAutomation, fetchAgents, fetchAutomationRuns } = useApi()

const { data: automationData, loading } = fetchAutomation(props.automationId)
const { data: agentsData } = fetchAgents()
const { data: runsData } = fetchAutomationRuns(props.automationId)

const agents = computed<User[]>(() => agentsData.value ?? [])
const runs = computed(() => runsData.value ?? [])

const saving = ref(false)
const running = ref(false)
const cursorLine = ref(1)
const cursorColumn = ref(1)
const selectedRun = ref<Record<string, any> | null>(null)
const runModalOpen = computed({
  get: () => selectedRun.value !== null,
  set: (val: boolean) => { if (!val) selectedRun.value = null },
})

const form = ref({
  name: '',
  executionType: 'prompt' as 'prompt' | 'script',
  agentId: '',
  prompt: '',
  script: '',
  cronExpression: '0 9 * * 1-5',
  timezone: 'UTC',
  keepHistory: true,
})

const content = computed({
  get: () => form.value.executionType === 'script' ? form.value.script : form.value.prompt,
  set: (val: string) => {
    if (form.value.executionType === 'script') {
      form.value.script = val
    } else {
      form.value.prompt = val
    }
  },
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
      executionType: automation.executionType || 'prompt',
      agentId: automation.agentId,
      prompt: automation.prompt || '',
      script: automation.script || '',
      cronExpression: automation.cronExpression,
      timezone: automation.timezone,
      keepHistory: automation.keepHistory ?? true,
    }
  }
}, { immediate: true })

const isValid = computed(() => {
  if (!form.value.name.trim() || !form.value.agentId || !form.value.cronExpression) return false
  if (form.value.executionType === 'script') return !!form.value.script.trim()
  return !!form.value.prompt.trim()
})

function goBack() {
  router.visit(workspacePath('/automation'))
}

function formatDateTime(dateStr: string): string {
  if (!dateStr) return '—'
  const d = new Date(dateStr)
  return d.toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function formatDuration(startStr: string | null, endStr: string | null): string {
  if (!startStr || !endStr) return '—'
  const ms = new Date(endStr).getTime() - new Date(startStr).getTime()
  if (ms < 1000) return `${ms}ms`
  if (ms < 60000) return `${(ms / 1000).toFixed(1)}s`
  return `${Math.floor(ms / 60000)}m ${Math.round((ms % 60000) / 1000)}s`
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

async function handleRun() {
  if (running.value) return
  running.value = true
  try {
    await triggerAutomation(props.automationId)
  } finally {
    running.value = false
  }
}

async function handleSave() {
  if (!isValid.value || saving.value) return
  saving.value = true
  try {
    await updateAutomation(props.automationId, {
      name: form.value.name.trim(),
      executionType: form.value.executionType,
      agentId: form.value.agentId,
      prompt: form.value.executionType === 'prompt' ? form.value.prompt.trim() : undefined,
      script: form.value.executionType === 'script' ? form.value.script.trim() : undefined,
      cronExpression: form.value.cronExpression,
      timezone: form.value.timezone,
      keepHistory: form.value.keepHistory,
    })
    router.visit(workspacePath('/automation'))
  } finally {
    saving.value = false
  }
}
</script>
