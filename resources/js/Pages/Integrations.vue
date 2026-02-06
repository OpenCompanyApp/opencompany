<template>
  <div class="h-full overflow-y-auto">
    <div class="max-w-4xl mx-auto p-6">
      <!-- Header -->
      <header class="mb-6">
        <h1 class="text-xl font-semibold text-neutral-900 dark:text-white">Integrations</h1>
        <p class="text-sm text-neutral-500 dark:text-neutral-400 mt-1">
          Connect external services and manage API access
        </p>
      </header>

      <!-- Tabs -->
      <div class="flex gap-2 mb-6">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          type="button"
          :class="[
            'px-4 py-2 rounded-lg text-sm font-medium transition-colors',
            activeTab === tab.id
              ? 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900'
              : 'text-neutral-500 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-800',
          ]"
          @click="activeTab = tab.id"
        >
          <Icon :name="tab.icon" class="w-4 h-4 mr-1.5 inline" />
          {{ tab.label }}
          <span
            v-if="tab.id === 'installed' && installedCount > 0"
            class="ml-1.5 px-1.5 py-0.5 text-xs rounded-full bg-white/20 dark:bg-neutral-900/20"
          >
            {{ installedCount }}
          </span>
        </button>
      </div>

      <!-- Installed Tab -->
      <div v-if="activeTab === 'installed'" class="space-y-6">
        <!-- Webhooks Section -->
        <section>
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-medium text-neutral-900 dark:text-white">Webhooks</h2>
            <button
              type="button"
              class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 hover:bg-neutral-800 dark:hover:bg-neutral-100 transition-colors duration-150"
              @click="showWebhookModal = true"
            >
              <Icon name="ph:plus" class="w-3.5 h-3.5" />
              Add webhook
            </button>
          </div>

          <div v-if="webhooks.length > 0" class="rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 divide-y divide-neutral-100 dark:divide-neutral-800">
            <div
              v-for="webhook in webhooks"
              :key="webhook.id"
              class="px-4 py-3"
            >
              <div class="flex items-start gap-3">
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2">
                    <p class="text-sm font-medium text-neutral-900 dark:text-white">{{ webhook.name }}</p>
                    <span
                      :class="[
                        'px-1.5 py-0.5 text-xs rounded',
                        webhook.enabled
                          ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400'
                          : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-500 dark:text-neutral-400'
                      ]"
                    >
                      {{ webhook.enabled ? 'Active' : 'Disabled' }}
                    </span>
                  </div>
                  <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-1 font-mono">
                    POST /api/webhooks/{{ webhook.id }}
                  </p>
                  <p class="text-xs text-neutral-400 dark:text-neutral-500 mt-1">
                    Last triggered: {{ webhook.lastTriggered || 'Never' }}
                    <span v-if="webhook.callCount"> · {{ webhook.callCount }} calls this week</span>
                  </p>
                </div>
                <div class="flex items-center gap-1 shrink-0">
                  <button
                    type="button"
                    class="p-1.5 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 transition-colors"
                    @click="editWebhook(webhook)"
                  >
                    <Icon name="ph:pencil-simple" class="w-4 h-4" />
                  </button>
                  <button
                    type="button"
                    class="p-1.5 text-neutral-400 hover:text-red-500 transition-colors"
                    @click="deleteWebhook(webhook.id)"
                  >
                    <Icon name="ph:trash" class="w-4 h-4" />
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div v-else class="rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 px-4 py-8 text-center">
            <Icon name="ph:webhooks-logo" class="w-8 h-8 text-neutral-300 dark:text-neutral-600 mx-auto mb-2" />
            <p class="text-sm text-neutral-500 dark:text-neutral-400">No webhooks configured</p>
            <p class="text-xs text-neutral-400 dark:text-neutral-500 mt-1">Add a webhook to receive events from external services</p>
          </div>
        </section>

        <!-- API Keys Section -->
        <section>
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-medium text-neutral-900 dark:text-white">API Keys</h2>
            <button
              type="button"
              class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors duration-150"
              @click="generateApiKey"
            >
              <Icon name="ph:key" class="w-3.5 h-3.5" />
              Generate key
            </button>
          </div>

          <div v-if="apiKeys.length > 0" class="rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 divide-y divide-neutral-100 dark:divide-neutral-800">
            <div
              v-for="key in apiKeys"
              :key="key.id"
              class="px-4 py-3"
            >
              <div class="flex items-start gap-3">
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium text-neutral-900 dark:text-white">{{ key.name }}</p>
                  <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-1 font-mono">
                    {{ key.maskedKey }}
                  </p>
                  <p class="text-xs text-neutral-400 dark:text-neutral-500 mt-1">
                    Created {{ key.createdAt }} · Last used {{ key.lastUsed || 'Never' }}
                  </p>
                </div>
                <div class="flex items-center gap-1 shrink-0">
                  <button
                    type="button"
                    class="p-1.5 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 transition-colors"
                    @click="copyApiKey(key)"
                  >
                    <Icon name="ph:copy" class="w-4 h-4" />
                  </button>
                  <button
                    type="button"
                    class="p-1.5 text-neutral-400 hover:text-red-500 transition-colors"
                    @click="revokeApiKey(key.id)"
                  >
                    <Icon name="ph:trash" class="w-4 h-4" />
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div v-else class="rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 px-4 py-8 text-center">
            <Icon name="ph:key" class="w-8 h-8 text-neutral-300 dark:text-neutral-600 mx-auto mb-2" />
            <p class="text-sm text-neutral-500 dark:text-neutral-400">No API keys</p>
            <p class="text-xs text-neutral-400 dark:text-neutral-500 mt-1">Generate a key to access the API programmatically</p>
          </div>
        </section>

        <!-- Connected Services Section -->
        <section>
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-medium text-neutral-900 dark:text-white">Connected Services</h2>
          </div>

          <div v-if="connectedServices.length > 0" class="rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 divide-y divide-neutral-100 dark:divide-neutral-800">
            <div
              v-for="service in connectedServices"
              :key="service.id"
              class="px-4 py-3"
            >
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                  <Icon :name="service.icon" class="w-4 h-4 text-green-600 dark:text-green-400" />
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium text-neutral-900 dark:text-white">{{ service.name }}</p>
                  <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ service.description }}</p>
                </div>
                <div class="flex items-center gap-2">
                  <span class="text-xs text-green-600 dark:text-green-400 flex items-center gap-1">
                    <Icon name="ph:check-circle-fill" class="w-3.5 h-3.5" />
                    Connected
                  </span>
                  <button
                    type="button"
                    class="p-1.5 text-neutral-400 hover:text-red-500 transition-colors"
                    title="Disconnect"
                    @click="disconnectService(service.id)"
                  >
                    <Icon name="ph:plug" class="w-4 h-4" />
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div v-else class="rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 px-4 py-8 text-center">
            <Icon name="ph:plugs-connected" class="w-8 h-8 text-neutral-300 dark:text-neutral-600 mx-auto mb-2" />
            <p class="text-sm text-neutral-500 dark:text-neutral-400">No connected services</p>
            <p class="text-xs text-neutral-400 dark:text-neutral-500 mt-1">
              Browse the
              <button type="button" class="text-neutral-900 dark:text-white underline" @click="activeTab = 'library'">
                library
              </button>
              to connect integrations
            </p>
          </div>
        </section>
      </div>

      <!-- Library Tab -->
      <div v-else-if="activeTab === 'library'" class="space-y-8">
        <!-- Search -->
        <SearchInput
          v-model="librarySearch"
          placeholder="Search integrations..."
          :clearable="true"
        />

        <!-- Empty search state -->
        <div v-if="librarySearch && filteredCategories.length === 0" class="text-center py-12">
          <Icon name="ph:magnifying-glass" class="w-12 h-12 text-neutral-300 dark:text-neutral-600 mx-auto mb-3" />
          <p class="text-sm text-neutral-500 dark:text-neutral-400">No integrations found for "{{ librarySearch }}"</p>
        </div>

        <!-- Categories -->
        <section v-for="category in filteredCategories" :key="category.id">
          <h3 class="text-sm font-medium text-neutral-900 dark:text-white mb-3 flex items-center gap-2">
            <Icon :name="category.icon" class="w-4 h-4 text-neutral-500" />
            {{ category.name }}
          </h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <IntegrationCard
              v-for="integration in category.integrations"
              :key="integration.id"
              :integration="integration"
              @install="handleInstall"
              @uninstall="handleUninstall"
              @configure="handleConfigure"
            />
          </div>
        </section>
      </div>

      <!-- GLM Config Modal -->
      <GlmConfigModal
        v-model:open="showGlmConfigModal"
        :integration-id="activeGlmIntegrationId"
        @saved="handleGlmSaved"
      />

      <!-- Webhook Modal -->
      <Modal v-model:open="showWebhookModal" title="Add Webhook">
        <template #body>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">Name</label>
              <input
                v-model="webhookForm.name"
                type="text"
                class="w-full px-3 py-2 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white text-sm focus:outline-none focus:border-neutral-400"
                placeholder="e.g., GitHub PR Notifications"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">Target</label>
              <select
                v-model="webhookForm.targetType"
                class="w-full px-3 py-2 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white text-sm focus:outline-none focus:border-neutral-400"
              >
                <option value="agent">Send to Agent</option>
                <option value="channel">Send to Channel</option>
                <option value="task">Create Task</option>
              </select>
            </div>
            <div v-if="webhookForm.targetType === 'agent'">
              <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">Agent</label>
              <select
                v-model="webhookForm.targetId"
                class="w-full px-3 py-2 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white text-sm focus:outline-none focus:border-neutral-400"
              >
                <option value="">Select an agent...</option>
                <option value="agent-1">Logic (Coder)</option>
                <option value="agent-2">Scout (Researcher)</option>
              </select>
            </div>
          </div>
        </template>
        <template #footer>
          <div class="flex justify-end gap-2">
            <button
              type="button"
              class="px-3 py-1.5 text-sm rounded-md text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800"
              @click="showWebhookModal = false"
            >
              Cancel
            </button>
            <button
              type="button"
              class="px-3 py-1.5 text-sm font-medium rounded-md bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 hover:bg-neutral-800 dark:hover:bg-neutral-100"
              @click="saveWebhook"
            >
              Create Webhook
            </button>
          </div>
        </template>
      </Modal>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import Modal from '@/Components/shared/Modal.vue'
import SearchInput from '@/Components/shared/SearchInput.vue'
import IntegrationCard from '@/Components/integrations/IntegrationCard.vue'
import GlmConfigModal from '@/Components/integrations/GlmConfigModal.vue'
import type { Integration } from '@/Components/integrations/IntegrationCard.vue'

// Tab state
type TabId = 'installed' | 'library'

const tabs = [
  { id: 'installed' as const, label: 'Installed', icon: 'ph:check-circle' },
  { id: 'library' as const, label: 'Library', icon: 'ph:squares-four' },
]

const activeTab = ref<TabId>('installed')

// Interfaces
interface Webhook {
  id: string
  name: string
  enabled: boolean
  targetType: 'agent' | 'channel' | 'task'
  targetId: string
  lastTriggered?: string
  callCount?: number
}

interface ApiKey {
  id: string
  name: string
  maskedKey: string
  createdAt: string
  lastUsed?: string
}

interface Service {
  id: string
  name: string
  icon: string
  description: string
  connected: boolean
}

interface IntegrationCategory {
  id: string
  name: string
  icon: string
  integrations: Integration[]
}

// Webhook state
const showWebhookModal = ref(false)
const webhookForm = reactive({
  name: '',
  targetType: 'agent' as 'agent' | 'channel' | 'task',
  targetId: '',
})

// Library search
const librarySearch = ref('')

// GLM Config modal
const showGlmConfigModal = ref(false)
const activeGlmIntegrationId = ref<'glm' | 'glm-coding'>('glm-coding')

// Load integration status from backend
onMounted(async () => {
  await loadIntegrationStatus()
})

const loadIntegrationStatus = async () => {
  try {
    const response = await fetch('/api/integrations')
    if (response.ok) {
      const integrations = await response.json()
      // Update the AI Models category with real status
      for (const integration of integrations) {
        for (const category of integrationCategories.value) {
          const found = category.integrations.find(i => i.id === integration.id)
          if (found) {
            found.installed = integration.enabled
          }
        }
      }
    }
  } catch (error) {
    console.error('Failed to load integration status:', error)
  }
}

// Mock data - Webhooks
const webhooks = ref<Webhook[]>([
  {
    id: 'wh-1',
    name: 'GitHub PR Notifications',
    enabled: true,
    targetType: 'agent',
    targetId: 'agent-1',
    lastTriggered: '2h ago',
    callCount: 47,
  },
  {
    id: 'wh-2',
    name: 'Stripe Payment Events',
    enabled: false,
    targetType: 'channel',
    targetId: 'channel-1',
    lastTriggered: '3d ago',
    callCount: 12,
  },
])

// Mock data - API Keys
const apiKeys = ref<ApiKey[]>([
  {
    id: 'key-1',
    name: 'Production API Key',
    maskedKey: 'sk_live_••••••••••••••••4f2a',
    createdAt: 'Jan 15, 2025',
    lastUsed: '1h ago',
  },
])

// Integration categories for Library
const integrationCategories = ref<IntegrationCategory[]>([
  {
    id: 'ai-models',
    name: 'AI Models',
    icon: 'ph:brain',
    integrations: [
      { id: 'glm', name: 'GLM (Zhipu AI)', icon: 'ph:brain', description: 'General-purpose Chinese LLM', installed: false, popular: true },
      { id: 'glm-coding', name: 'GLM Coding Plan', icon: 'ph:code', description: 'Specialized coding LLM', installed: false, popular: true },
    ],
  },
  {
    id: 'communication',
    name: 'Communication',
    icon: 'ph:chat-circle',
    integrations: [
      { id: 'slack', name: 'Slack', icon: 'ph:slack-logo', description: 'Team messaging and notifications', installed: false, popular: true },
      { id: 'discord', name: 'Discord', icon: 'ph:discord-logo', description: 'Community chat and voice', installed: false, popular: true },
      { id: 'teams', name: 'Microsoft Teams', icon: 'ph:microsoft-teams-logo', description: 'Enterprise collaboration', installed: false },
      { id: 'telegram', name: 'Telegram', icon: 'ph:telegram-logo', description: 'Secure messaging', installed: false },
      { id: 'matrix', name: 'Matrix', icon: 'ph:chat-centered-dots', description: 'Decentralized chat (self-hosted)', installed: false },
    ],
  },
  {
    id: 'developer',
    name: 'Developer Tools',
    icon: 'ph:code',
    integrations: [
      { id: 'github', name: 'GitHub', icon: 'ph:github-logo', description: 'Repos, issues, PRs, actions', installed: true, popular: true },
      { id: 'gitlab', name: 'GitLab', icon: 'ph:gitlab-logo', description: 'Git hosting and CI/CD', installed: false },
      { id: 'linear', name: 'Linear', icon: 'ph:square-split-horizontal', description: 'Issue tracking', installed: false, popular: true },
      { id: 'jira', name: 'Jira', icon: 'ph:kanban', description: 'Project management', installed: false },
    ],
  },
  {
    id: 'productivity',
    name: 'Productivity',
    icon: 'ph:briefcase',
    integrations: [
      { id: 'notion', name: 'Notion', icon: 'ph:notebook', description: 'Docs and knowledge base', installed: false, popular: true },
      { id: 'trello', name: 'Trello', icon: 'ph:trello-logo', description: 'Kanban boards', installed: false },
      { id: 'google-calendar', name: 'Google Calendar', icon: 'ph:calendar', description: 'Calendar sync', installed: false },
      { id: 'obsidian', name: 'Obsidian', icon: 'ph:vault', description: 'Knowledge management', installed: false },
      { id: 'google-drive', name: 'Google Drive', icon: 'ph:google-drive-logo', description: 'File storage and sharing', installed: false },
    ],
  },
  {
    id: 'data',
    name: 'Data & APIs',
    icon: 'ph:database',
    integrations: [
      { id: 'webhooks', name: 'Webhooks', icon: 'ph:webhooks-logo', description: 'Custom HTTP webhooks', installed: true },
      { id: 'email', name: 'Email (SMTP)', icon: 'ph:envelope', description: 'Send and receive emails', installed: false },
      { id: 'rest-api', name: 'REST API', icon: 'ph:plug', description: 'Generic API connector', installed: false },
      { id: 'zapier', name: 'Zapier', icon: 'ph:lightning', description: 'Connect to 5,000+ apps', installed: false, popular: true },
    ],
  },
])

// Computed - Connected services (installed integrations)
const connectedServices = computed<Service[]>(() => {
  const installed: Service[] = []
  for (const category of integrationCategories.value) {
    for (const integration of category.integrations) {
      if (integration.installed) {
        installed.push({
          id: integration.id,
          name: integration.name,
          icon: integration.icon,
          description: integration.description,
          connected: true,
        })
      }
    }
  }
  return installed
})

// Computed - Installed count for tab badge
const installedCount = computed(() => {
  return webhooks.value.length + apiKeys.value.length + connectedServices.value.length
})

// Computed - Filtered categories for search
const filteredCategories = computed(() => {
  if (!librarySearch.value) {
    return integrationCategories.value
  }

  const search = librarySearch.value.toLowerCase()
  return integrationCategories.value
    .map(category => ({
      ...category,
      integrations: category.integrations.filter(
        i => i.name.toLowerCase().includes(search) || i.description.toLowerCase().includes(search)
      ),
    }))
    .filter(category => category.integrations.length > 0)
})

// Webhook handlers
const editWebhook = (webhook: Webhook) => {
  webhookForm.name = webhook.name
  webhookForm.targetType = webhook.targetType
  webhookForm.targetId = webhook.targetId
  showWebhookModal.value = true
}

const deleteWebhook = (id: string) => {
  webhooks.value = webhooks.value.filter(w => w.id !== id)
}

const saveWebhook = () => {
  const newWebhook: Webhook = {
    id: `wh-${Date.now()}`,
    name: webhookForm.name,
    enabled: true,
    targetType: webhookForm.targetType,
    targetId: webhookForm.targetId,
  }
  webhooks.value.push(newWebhook)
  showWebhookModal.value = false
  webhookForm.name = ''
  webhookForm.targetType = 'agent'
  webhookForm.targetId = ''
}

// API Key handlers
const generateApiKey = () => {
  const newKey: ApiKey = {
    id: `key-${Date.now()}`,
    name: 'New API Key',
    maskedKey: 'sk_live_••••••••••••••••' + Math.random().toString(36).substring(2, 6),
    createdAt: new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }),
  }
  apiKeys.value.push(newKey)
}

const copyApiKey = async (key: ApiKey) => {
  try {
    await navigator.clipboard.writeText(key.maskedKey)
  } catch {
    // Fallback for non-HTTPS contexts
    const textarea = document.createElement('textarea')
    textarea.value = key.maskedKey
    document.body.appendChild(textarea)
    textarea.select()
    document.execCommand('copy')
    document.body.removeChild(textarea)
  }
}

const revokeApiKey = (id: string) => {
  apiKeys.value = apiKeys.value.filter(k => k.id !== id)
}

// Integration handlers
const handleInstall = (integration: Integration) => {
  // For AI model integrations, open config modal
  if (integration.id === 'glm' || integration.id === 'glm-coding') {
    activeGlmIntegrationId.value = integration.id as 'glm' | 'glm-coding'
    showGlmConfigModal.value = true
    return
  }

  // Find and update the integration
  for (const category of integrationCategories.value) {
    const found = category.integrations.find(i => i.id === integration.id)
    if (found) {
      found.installed = true
      break
    }
  }
}

// Handle GLM config saved
const handleGlmSaved = (result: { enabled: boolean; configured: boolean }) => {
  // Update the specific GLM integration status
  for (const category of integrationCategories.value) {
    const found = category.integrations.find(i => i.id === activeGlmIntegrationId.value)
    if (found) {
      found.installed = result.enabled
      break
    }
  }
}

// Handle configure integration
const handleConfigure = (integration: Integration) => {
  if (integration.id === 'glm' || integration.id === 'glm-coding') {
    activeGlmIntegrationId.value = integration.id as 'glm' | 'glm-coding'
    showGlmConfigModal.value = true
  }
}

const handleUninstall = (integration: Integration) => {
  // Find and update the integration
  for (const category of integrationCategories.value) {
    const found = category.integrations.find(i => i.id === integration.id)
    if (found) {
      found.installed = false
      break
    }
  }
}

const disconnectService = (id: string) => {
  // Find and disconnect the service
  for (const category of integrationCategories.value) {
    const found = category.integrations.find(i => i.id === id)
    if (found) {
      found.installed = false
      break
    }
  }
}
</script>
