<template>
  <div class="h-full overflow-hidden flex flex-col">
    <div class="max-w-5xl mx-auto w-full p-4 md:p-6 flex flex-col flex-1 min-h-0">
      <!-- Header -->
      <header class="mb-4 md:mb-6 shrink-0">
        <h1 class="text-xl font-semibold text-neutral-900 dark:text-white">Settings</h1>
        <p class="text-sm text-neutral-500 dark:text-neutral-400 mt-1">
          Manage your organization and agent configuration
        </p>
      </header>

      <!-- Mobile Nav -->
      <div class="flex gap-1.5 overflow-x-auto pb-3 -mx-4 px-4 md:hidden shrink-0" style="-ms-overflow-style: none; scrollbar-width: none; -webkit-overflow-scrolling: touch;">
        <button
          v-for="section in sections"
          :key="'mobile-' + section.id"
          type="button"
          :class="[
            'flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium transition-colors whitespace-nowrap shrink-0',
            activeSection === section.id
              ? section.id === 'danger' ? 'bg-red-600 text-white' : 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900'
              : section.id === 'danger' ? 'bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400' : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400',
          ]"
          @click="activeSection = section.id"
        >
          <Icon :name="section.icon" class="w-3.5 h-3.5" />
          {{ section.name }}
        </button>
      </div>

      <!-- Sidebar + Content -->
      <div class="flex flex-col md:flex-row gap-4 md:gap-6 flex-1 min-h-0">
        <!-- Desktop Sidebar -->
        <nav class="hidden md:flex w-52 shrink-0 flex-col gap-1">
          <button
            v-for="section in sections.filter(s => !bottomSections.includes(s.id))"
            :key="section.id"
            type="button"
            :class="[
              'flex items-center gap-2 px-3 py-1.5 rounded-md text-xs font-medium transition-colors text-left w-full',
              activeSection === section.id
                ? 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900'
                : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800',
            ]"
            @click="activeSection = section.id"
          >
            <Icon :name="section.icon" class="w-4 h-4" />
            {{ section.name }}
          </button>

          <!-- Divider -->
          <div class="border-t border-neutral-200 dark:border-neutral-700 my-2" />

          <!-- Debug -->
          <button
            type="button"
            :class="[
              'flex items-center gap-2 px-3 py-1.5 rounded-md text-xs font-medium transition-colors text-left w-full',
              activeSection === 'debug'
                ? 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900'
                : 'text-neutral-500 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800',
            ]"
            @click="activeSection = 'debug'"
          >
            <Icon name="ph:bug" class="w-4 h-4" />
            Debug
          </button>

          <!-- Danger Zone -->
          <button
            type="button"
            :class="[
              'flex items-center gap-2 px-3 py-1.5 rounded-md text-xs font-medium transition-colors text-left w-full',
              activeSection === 'danger'
                ? 'bg-red-600 text-white'
                : 'text-red-500 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20',
            ]"
            @click="activeSection = 'danger'"
          >
            <Icon name="ph:warning" class="w-4 h-4" />
            Danger Zone
          </button>
        </nav>

        <!-- Main Content -->
        <main class="flex-1 min-w-0 overflow-y-auto">
          <!-- Loading -->
          <div v-if="loading" class="flex items-center justify-center py-16">
            <Icon name="ph:spinner" class="w-6 h-6 text-neutral-400 animate-spin" />
          </div>

          <template v-else>
            <OrganizationSettings
              v-if="activeSection === 'organization'"
              :initial-settings="orgSettings"
              :saving-category="saving"
              :saved-category="saved"
              @save="saveCategory"
            />

            <AgentDefaultsSettings
              v-if="activeSection === 'agents'"
              :initial-settings="agentSettings"
              :saving-category="saving"
              :saved-category="saved"
              @save="saveCategory"
            />

            <PoliciesSettings
              v-if="activeSection === 'policies'"
              :initial-policies="actionPolicies"
              :saving-category="saving"
              :saved-category="saved"
              @save="saveCategory"
            />

            <NotificationsSettings
              v-if="activeSection === 'notifications'"
              :initial-settings="notificationSettings"
              :saving-category="saving"
              :saved-category="saved"
              @save="saveCategory"
            />

            <MemorySettings
              v-if="activeSection === 'memory'"
              :initial-memory="memorySettingsData"
              :saving-category="saving"
              :saved-category="saved"
              @save="saveCategory"
            />

            <DebugSettings v-if="activeSection === 'debug'" />

            <DangerZoneSettings v-if="activeSection === 'danger'" />
          </template>
        </main>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import OrganizationSettings from '@/Components/settings/OrganizationSettings.vue'
import AgentDefaultsSettings from '@/Components/settings/AgentDefaultsSettings.vue'
import PoliciesSettings from '@/Components/settings/PoliciesSettings.vue'
import NotificationsSettings from '@/Components/settings/NotificationsSettings.vue'
import MemorySettings from '@/Components/settings/MemorySettings.vue'
import DebugSettings from '@/Components/settings/DebugSettings.vue'
import DangerZoneSettings from '@/Components/settings/DangerZoneSettings.vue'
import { useApi } from '@/composables/useApi'
import type { ActionPolicy, MemorySettingsData } from '@/Components/settings/types'

// --- Sidebar sections ---
const sections = [
  { id: 'organization', name: 'Organization', icon: 'ph:buildings' },
  { id: 'agents', name: 'Agent Defaults', icon: 'ph:robot' },
  { id: 'policies', name: 'Action Policies', icon: 'ph:shield-check' },
  { id: 'notifications', name: 'Notifications', icon: 'ph:bell' },
  { id: 'memory', name: 'Memory', icon: 'ph:brain' },
  { id: 'debug', name: 'Debug', icon: 'ph:bug' },
  { id: 'danger', name: 'Danger Zone', icon: 'ph:warning' },
]

const bottomSections = ['debug', 'danger']
const activeSection = ref('organization')

// --- API ---
const { fetchSettings, updateSettings } = useApi()
const loading = ref(true)
const saving = ref<string | null>(null)
const saved = ref<string | null>(null)

// --- Settings state (hydrated on mount, passed as initialX props) ---
const orgSettings = reactive({
  org_name: '',
  org_email: '',
  org_timezone: 'UTC',
})

const agentSettings = reactive({
  default_behavior: 'supervised',
  auto_spawn: false,
  budget_approval_threshold: 0,
})

const notificationSettings = reactive({
  email_notifications: true,
  slack_notifications: false,
  daily_summary: true,
})

const actionPolicies = ref<ActionPolicy[]>([])

const memorySettingsData = reactive<MemorySettingsData>({
  memory_embedding_model: 'openai:text-embedding-3-small',
  memory_summary_model: 'anthropic:claude-sonnet-4-5-20250929',
  memory_compaction_enabled: true,
  memory_reranking_enabled: true,
  memory_reranking_model: 'ollama:dengcao/Qwen3-Reranker-0.6B:Q8_0',
  model_context_windows: {},
})

// --- Load settings ---
onMounted(async () => {
  try {
    const { data, promise } = fetchSettings()
    await promise
    if (data.value) {
      const s = data.value
      // Organization
      if (s.organization) {
        orgSettings.org_name = (s.organization.org_name as string) ?? ''
        orgSettings.org_email = (s.organization.org_email as string) ?? ''
        orgSettings.org_timezone = (s.organization.org_timezone as string) ?? 'UTC'
      }
      // Agents
      if (s.agents) {
        agentSettings.default_behavior = (s.agents.default_behavior as string) ?? 'supervised'
        agentSettings.auto_spawn = !!s.agents.auto_spawn
        agentSettings.budget_approval_threshold = Number(s.agents.budget_approval_threshold) || 0
      }
      // Notifications
      if (s.notifications) {
        notificationSettings.email_notifications = s.notifications.email_notifications !== false
        notificationSettings.slack_notifications = !!s.notifications.slack_notifications
        notificationSettings.daily_summary = s.notifications.daily_summary !== false
      }
      // Policies
      if (s.policies?.action_policies) {
        actionPolicies.value = s.policies.action_policies as ActionPolicy[]
      }
      // Memory
      if (s.memory) {
        memorySettingsData.memory_embedding_model = (s.memory.memory_embedding_model as string) ?? 'openai:text-embedding-3-small'
        memorySettingsData.memory_summary_model = (s.memory.memory_summary_model as string) ?? 'anthropic:claude-sonnet-4-5-20250929'
        memorySettingsData.memory_compaction_enabled = s.memory.memory_compaction_enabled !== false
        memorySettingsData.memory_reranking_enabled = s.memory.memory_reranking_enabled !== false
        memorySettingsData.memory_reranking_model = (s.memory.memory_reranking_model as string) ?? 'ollama:dengcao/Qwen3-Reranker-0.6B:Q8_0'
        memorySettingsData.model_context_windows = (s.memory.model_context_windows as Record<string, number>) ?? {}
      }
    }
  } finally {
    loading.value = false
  }
})

// --- Save ---
async function saveCategory(category: string, settings: Record<string, unknown>) {
  saving.value = category
  saved.value = null
  try {
    await updateSettings(category, { ...settings })
    saved.value = category
    setTimeout(() => { if (saved.value === category) saved.value = null }, 2000)
  } catch (e) {
    console.error('Failed to save settings:', e)
  } finally {
    saving.value = null
  }
}
</script>
