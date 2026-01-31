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

      <div class="space-y-6">
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

        <!-- Connected Services Section (future) -->
        <section>
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-medium text-neutral-900 dark:text-white">Connected Services</h2>
          </div>

          <div class="rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 divide-y divide-neutral-100 dark:divide-neutral-800">
            <div
              v-for="service in availableServices"
              :key="service.id"
              class="px-4 py-3"
            >
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-neutral-100 dark:bg-neutral-800 flex items-center justify-center">
                  <Icon :name="service.icon" class="w-4 h-4 text-neutral-600 dark:text-neutral-400" />
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium text-neutral-900 dark:text-white">{{ service.name }}</p>
                  <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ service.description }}</p>
                </div>
                <button
                  v-if="!service.connected"
                  type="button"
                  class="px-3 py-1.5 text-xs font-medium rounded-md text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors duration-150"
                >
                  Connect
                </button>
                <span v-else class="text-xs text-green-600 dark:text-green-400">Connected</span>
              </div>
            </div>
          </div>
        </section>
      </div>

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
import { ref, reactive } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import Modal from '@/Components/shared/Modal.vue'

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

const showWebhookModal = ref(false)

const webhookForm = reactive({
  name: '',
  targetType: 'agent' as 'agent' | 'channel' | 'task',
  targetId: '',
})

// Mock data
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

const apiKeys = ref<ApiKey[]>([
  {
    id: 'key-1',
    name: 'Production API Key',
    maskedKey: 'sk_live_••••••••••••••••4f2a',
    createdAt: 'Jan 15, 2025',
    lastUsed: '1h ago',
  },
])

const availableServices = ref<Service[]>([
  { id: 'slack', name: 'Slack', icon: 'ph:slack-logo', description: 'Send notifications to Slack channels', connected: false },
  { id: 'github', name: 'GitHub', icon: 'ph:github-logo', description: 'Sync issues and pull requests', connected: true },
  { id: 'linear', name: 'Linear', icon: 'ph:square-split-horizontal', description: 'Sync tasks with Linear projects', connected: false },
])

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

const generateApiKey = () => {
  const newKey: ApiKey = {
    id: `key-${Date.now()}`,
    name: 'New API Key',
    maskedKey: 'sk_live_••••••••••••••••' + Math.random().toString(36).substring(2, 6),
    createdAt: new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }),
  }
  apiKeys.value.push(newKey)
}

const copyApiKey = (key: ApiKey) => {
  // In real implementation, would copy actual key to clipboard
  console.log('Copying key:', key.id)
}

const revokeApiKey = (id: string) => {
  apiKeys.value = apiKeys.value.filter(k => k.id !== id)
}
</script>
