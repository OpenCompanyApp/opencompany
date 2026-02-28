<template>
  <div class="h-full flex flex-col overflow-hidden bg-neutral-50 dark:bg-[#181818]">
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

      <div class="flex items-center gap-2 shrink-0">
        <Button size="sm" variant="primary" :loading="saving" :disabled="!isValid" @click="handleSave">
          Create
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
        </div>
      </div>
    </div>

    <!-- Status bar -->
    <div class="flex items-center h-6 px-3 shrink-0 border-t border-neutral-200 dark:border-neutral-700/60 bg-white dark:bg-[#1f1f1f] text-[11px] text-neutral-400 dark:text-neutral-500 gap-3 select-none">
      <span class="font-medium">{{ form.executionType === 'script' ? 'Luau' : 'Markdown' }}</span>
      <span class="w-px h-3 bg-neutral-200 dark:bg-neutral-700" />
      <span>Ln {{ cursorLine }}, Col {{ cursorColumn }}</span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import type { User } from '@/types'
import Icon from '@/Components/shared/Icon.vue'
import Button from '@/Components/shared/Button.vue'
import CronBuilder from '@/Components/automation/CronBuilder.vue'
import MonacoEditor from '@/Components/developer/MonacoEditor.vue'
import { useApi } from '@/composables/useApi'
import { useWorkspace } from '@/composables/useWorkspace'

const { workspacePath } = useWorkspace()
const { createAutomation, fetchAgents } = useApi()
const { data: agentsData } = fetchAgents()
const agents = computed<User[]>(() => agentsData.value ?? [])

const saving = ref(false)
const cursorLine = ref(1)
const cursorColumn = ref(1)

const form = ref({
  name: '',
  executionType: 'prompt' as 'prompt' | 'script',
  agentId: '',
  prompt: '',
  script: '',
  cronExpression: '0 9 * * 1-5',
  timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC',
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

const isValid = computed(() => {
  if (!form.value.name.trim() || !form.value.agentId || !form.value.cronExpression) return false
  if (form.value.executionType === 'script') return !!form.value.script.trim()
  return !!form.value.prompt.trim()
})

function goBack() {
  router.visit(workspacePath('/automation'))
}

async function handleSave() {
  if (!isValid.value || saving.value) return
  saving.value = true
  try {
    await createAutomation({
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
