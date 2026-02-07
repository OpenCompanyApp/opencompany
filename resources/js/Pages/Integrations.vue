<template>
  <div class="h-full overflow-hidden flex flex-col">
    <div class="max-w-6xl mx-auto w-full p-4 md:p-6 flex flex-col flex-1 min-h-0">
      <!-- Header -->
      <header class="mb-4 md:mb-6 shrink-0">
        <h1 class="text-xl font-semibold text-neutral-900 dark:text-white">Integrations</h1>
        <p class="text-sm text-neutral-500 dark:text-neutral-400 mt-1">
          Connect external services and manage API access
        </p>
      </header>

      <!-- Sidebar + Content -->
      <div class="flex flex-col md:flex-row gap-4 md:gap-6 flex-1 min-h-0">
        <!-- Mobile Nav -->
        <div class="flex flex-col gap-3 md:hidden shrink-0">
          <!-- Mobile Search -->
          <div class="relative">
            <Icon name="ph:magnifying-glass" class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-neutral-400" />
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search integrations..."
              class="w-full pl-8 pr-3 py-2 text-sm rounded-md border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white placeholder-neutral-400 focus:outline-none focus:ring-1 focus:ring-neutral-400 dark:focus:ring-neutral-500"
            />
            <button
              v-if="searchQuery"
              type="button"
              class="absolute right-2 top-1/2 -translate-y-1/2 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300"
              @click="searchQuery = ''"
            >
              <Icon name="ph:x" class="w-3.5 h-3.5" />
            </button>
          </div>
          <!-- Mobile Category Pills -->
          <div class="flex gap-1.5 overflow-x-auto pb-1 -mx-4 px-4" style="-ms-overflow-style: none; scrollbar-width: none; -webkit-overflow-scrolling: touch;">
            <button
              type="button"
              :class="[
                'flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium transition-colors whitespace-nowrap shrink-0',
                activeCategory === 'all' && !searchQuery
                  ? 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900'
                  : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400',
              ]"
              @click="activeCategory = 'all'; searchQuery = ''"
            >
              All
            </button>
            <button
              type="button"
              :class="[
                'flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium transition-colors whitespace-nowrap shrink-0',
                activeCategory === 'installed' && !searchQuery
                  ? 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900'
                  : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400',
              ]"
              @click="activeCategory = 'installed'; searchQuery = ''"
            >
              <Icon name="ph:check-circle" class="w-3.5 h-3.5" />
              Installed
              <span
                v-if="installedCount > 0"
                class="text-[10px] px-1 rounded-full bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400"
              >
                {{ installedCount }}
              </span>
            </button>
            <button
              v-for="category in integrationCategories"
              :key="'mobile-' + category.id"
              type="button"
              :class="[
                'flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium transition-colors whitespace-nowrap shrink-0',
                activeCategory === category.id && !searchQuery
                  ? 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900'
                  : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400',
              ]"
              @click="activeCategory = category.id; searchQuery = ''"
            >
              <Icon :name="category.icon" class="w-3.5 h-3.5" />
              {{ category.name }}
            </button>
          </div>
        </div>

        <!-- Desktop Sidebar -->
        <nav class="hidden md:flex w-52 shrink-0 flex-col gap-1 overflow-y-auto">
          <!-- Search -->
          <div class="relative mb-3">
            <Icon name="ph:magnifying-glass" class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-neutral-400" />
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search..."
              class="w-full pl-8 pr-3 py-1.5 text-xs rounded-md border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white placeholder-neutral-400 focus:outline-none focus:ring-1 focus:ring-neutral-400 dark:focus:ring-neutral-500"
            />
            <button
              v-if="searchQuery"
              type="button"
              class="absolute right-2 top-1/2 -translate-y-1/2 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300"
              @click="searchQuery = ''"
            >
              <Icon name="ph:x" class="w-3 h-3" />
            </button>
          </div>

          <!-- All -->
          <button
            type="button"
            :class="[
              'flex items-center gap-2 px-3 py-1.5 rounded-md text-xs font-medium transition-colors text-left w-full',
              activeCategory === 'all' && !searchQuery
                ? 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900'
                : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800',
            ]"
            @click="activeCategory = 'all'; searchQuery = ''"
          >
            <Icon name="ph:squares-four" class="w-4 h-4" />
            All
            <span class="ml-auto text-[10px] opacity-60">{{ totalIntegrationCount }}</span>
          </button>

          <!-- Installed -->
          <button
            type="button"
            :class="[
              'flex items-center gap-2 px-3 py-1.5 rounded-md text-xs font-medium transition-colors text-left w-full',
              activeCategory === 'installed' && !searchQuery
                ? 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900'
                : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800',
            ]"
            @click="activeCategory = 'installed'; searchQuery = ''"
          >
            <Icon name="ph:check-circle" class="w-4 h-4" />
            Installed
            <span
              v-if="installedCount > 0"
              class="ml-auto text-[10px] px-1.5 py-0.5 rounded-full bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400"
            >
              {{ installedCount }}
            </span>
          </button>

          <!-- Divider -->
          <div class="border-t border-neutral-200 dark:border-neutral-700 my-2" />

          <!-- Categories -->
          <button
            v-for="category in integrationCategories"
            :key="category.id"
            type="button"
            :class="[
              'flex items-center gap-2 px-3 py-1.5 rounded-md text-xs font-medium transition-colors text-left w-full',
              activeCategory === category.id && !searchQuery
                ? 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900'
                : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800',
            ]"
            @click="activeCategory = category.id; searchQuery = ''"
          >
            <Icon :name="category.icon" class="w-4 h-4" />
            {{ category.name }}
            <span class="ml-auto text-[10px] opacity-60">{{ category.integrations.length }}</span>
          </button>
        </nav>

        <!-- Main Content -->
        <main class="flex-1 min-w-0 overflow-y-auto">
          <!-- Search Results -->
          <template v-if="searchQuery">
            <div class="mb-4">
              <h2 class="text-sm font-medium text-neutral-900 dark:text-white">
                Search results for "{{ searchQuery }}"
              </h2>
              <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
                {{ searchResults.length }} integration{{ searchResults.length === 1 ? '' : 's' }} found
              </p>
            </div>

            <div v-if="searchResults.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
              <IntegrationCard
                v-for="integration in searchResults"
                :key="integration.id"
                :integration="integration"
                @install="handleInstall"
                @uninstall="handleUninstall"
                @configure="handleConfigure"
              />
            </div>

            <div v-else class="text-center py-16">
              <Icon name="ph:magnifying-glass" class="w-10 h-10 text-neutral-300 dark:text-neutral-600 mx-auto mb-3" />
              <p class="text-sm text-neutral-500 dark:text-neutral-400">No integrations match your search</p>
            </div>
          </template>

          <!-- Installed View -->
          <template v-else-if="activeCategory === 'installed'">
            <!-- Connected Services -->
            <section class="mb-8">
              <h2 class="text-sm font-medium text-neutral-900 dark:text-white mb-3">Connected Services</h2>

              <div v-if="connectedServices.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 mb-6">
                <IntegrationCard
                  v-for="service in installedIntegrations"
                  :key="service.id"
                  :integration="service"
                  @install="handleInstall"
                  @uninstall="handleUninstall"
                  @configure="handleConfigure"
                />
              </div>

              <div v-else class="rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 px-4 py-8 text-center mb-6">
                <Icon name="ph:plugs-connected" class="w-8 h-8 text-neutral-300 dark:text-neutral-600 mx-auto mb-2" />
                <p class="text-sm text-neutral-500 dark:text-neutral-400">No connected services</p>
                <p class="text-xs text-neutral-400 dark:text-neutral-500 mt-1">
                  Browse the
                  <button type="button" class="text-neutral-900 dark:text-white underline" @click="activeCategory = 'all'">
                    library
                  </button>
                  to connect integrations
                </p>
              </div>
            </section>

            <!-- Webhooks Section -->
            <section class="mb-8">
              <div class="flex items-center justify-between mb-3">
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
              <div class="flex items-center justify-between mb-3">
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
          </template>

          <!-- All Integrations View -->
          <template v-else-if="activeCategory === 'all'">
            <section v-for="category in integrationCategories" :key="category.id" class="mb-8 last:mb-0">
              <h3 class="text-sm font-medium text-neutral-900 dark:text-white mb-3 flex items-center gap-2">
                <Icon :name="category.icon" class="w-4 h-4 text-neutral-500" />
                {{ category.name }}
              </h3>
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
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
          </template>

          <!-- Single Category View -->
          <template v-else>
            <div v-if="selectedCategory" class="mb-4">
              <h2 class="text-sm font-medium text-neutral-900 dark:text-white flex items-center gap-2">
                <Icon :name="selectedCategory.icon" class="w-4 h-4 text-neutral-500" />
                {{ selectedCategory.name }}
              </h2>
            </div>
            <div v-if="selectedCategory" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
              <IntegrationCard
                v-for="integration in selectedCategory.integrations"
                :key="integration.id"
                :integration="integration"
                @install="handleInstall"
                @uninstall="handleUninstall"
                @configure="handleConfigure"
              />
            </div>
          </template>
        </main>
      </div>
    </div>

    <!-- GLM Config Modal -->
    <GlmConfigModal
      v-model:open="showGlmConfigModal"
      :integration-id="activeGlmIntegrationId"
      @saved="handleGlmSaved"
    />

    <!-- Telegram Config Modal -->
    <TelegramConfigModal
      v-model:open="showTelegramConfigModal"
      @saved="handleTelegramSaved"
    />

    <!-- Plausible Config Modal -->
    <PlausibleConfigModal
      v-model:open="showPlausibleConfigModal"
      @saved="handlePlausibleSaved"
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
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import Modal from '@/Components/shared/Modal.vue'
import IntegrationCard from '@/Components/integrations/IntegrationCard.vue'
import GlmConfigModal from '@/Components/integrations/GlmConfigModal.vue'
import TelegramConfigModal from '@/Components/integrations/TelegramConfigModal.vue'
import PlausibleConfigModal from '@/Components/integrations/PlausibleConfigModal.vue'
import type { Integration } from '@/Components/integrations/IntegrationCard.vue'

// Sidebar state
const activeCategory = ref<string>('all')
const searchQuery = ref('')

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

// GLM Config modal
const showGlmConfigModal = ref(false)
const activeGlmIntegrationId = ref<'glm' | 'glm-coding'>('glm-coding')

// Telegram Config modal
const showTelegramConfigModal = ref(false)

// Plausible Config modal
const showPlausibleConfigModal = ref(false)

// Load integration status from backend
onMounted(async () => {
  await loadIntegrationStatus()
})

const loadIntegrationStatus = async () => {
  try {
    const response = await fetch('/api/integrations')
    if (response.ok) {
      const integrations = await response.json()
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

// Integration categories
const integrationCategories = ref<IntegrationCategory[]>([
  {
    id: 'ai-models',
    name: 'AI Models',
    icon: 'ph:brain',
    integrations: [
      { id: 'glm', name: 'GLM (Zhipu AI)', icon: 'ph:brain', description: 'General-purpose Chinese LLM', installed: false, badge: 'verified' },
      { id: 'glm-coding', name: 'GLM Coding Plan', icon: 'ph:code', description: 'Specialized coding LLM', installed: false, badge: 'verified' },
    ],
  },
  {
    id: 'analytics',
    name: 'Analytics',
    icon: 'ph:chart-line-up',
    integrations: [
      { id: 'plausible', name: 'Plausible Analytics', icon: 'ph:chart-line-up', description: 'Privacy-friendly website analytics', installed: false, badge: 'verified' },
      { id: 'google-analytics', name: 'Google Analytics', icon: 'ph:google-logo', description: 'Website traffic analytics', installed: false },
    ],
  },
  {
    id: 'communication',
    name: 'Communication',
    icon: 'ph:chat-circle',
    integrations: [
      { id: 'slack', name: 'Slack', icon: 'ph:slack-logo', description: 'Team messaging and notifications', installed: false },
      { id: 'discord', name: 'Discord', icon: 'ph:discord-logo', description: 'Community chat and voice', installed: false },
      { id: 'teams', name: 'Microsoft Teams', icon: 'ph:microsoft-teams-logo', description: 'Enterprise collaboration', installed: false },
      { id: 'telegram', name: 'Telegram', icon: 'ph:telegram-logo', description: 'Secure messaging', installed: false, badge: 'verified' },
      { id: 'matrix', name: 'Matrix', icon: 'ph:chat-centered-dots', description: 'Decentralized chat (self-hosted)', installed: false },
    ],
  },
  {
    id: 'developer',
    name: 'Developer Tools',
    icon: 'ph:code',
    integrations: [
      { id: 'github', name: 'GitHub', icon: 'ph:github-logo', description: 'Repos, issues, PRs, actions', installed: true },
      { id: 'gitlab', name: 'GitLab', icon: 'ph:gitlab-logo', description: 'Git hosting and CI/CD', installed: false },
      { id: 'linear', name: 'Linear', icon: 'ph:square-split-horizontal', description: 'Issue tracking', installed: false },
      { id: 'jira', name: 'Jira', icon: 'ph:kanban', description: 'Project management', installed: false },
    ],
  },
  {
    id: 'productivity',
    name: 'Productivity',
    icon: 'ph:briefcase',
    integrations: [
      { id: 'notion', name: 'Notion', icon: 'ph:notebook', description: 'Docs and knowledge base', installed: false },
      { id: 'trello', name: 'Trello', icon: 'ph:trello-logo', description: 'Kanban boards', installed: false },
      { id: 'google-calendar', name: 'Google Calendar', icon: 'ph:calendar', description: 'Calendar sync', installed: false },
      { id: 'obsidian', name: 'Obsidian', icon: 'ph:vault', description: 'Knowledge management', installed: false },
      { id: 'google-drive', name: 'Google Drive', icon: 'ph:google-drive-logo', description: 'File storage and sharing', installed: false },
    ],
  },
  {
    id: 'automation',
    name: 'Automation',
    icon: 'ph:flow-arrow',
    integrations: [
      { id: 'n8n', name: 'n8n', icon: 'ph:flow-arrow', description: 'Open-source workflow automation', installed: false },
      { id: 'zapier', name: 'Zapier', icon: 'ph:lightning', description: 'Connect to 5,000+ apps', installed: false },
      { id: 'make', name: 'Make (Integromat)', icon: 'ph:circles-three-plus', description: 'Visual automation platform', installed: false },
    ],
  },
  {
    id: 'data',
    name: 'Data & APIs',
    icon: 'ph:database',
    integrations: [
      { id: 'webhooks', name: 'Webhooks', icon: 'ph:webhooks-logo', description: 'Custom HTTP webhooks', installed: true, badge: 'built-in' },
      { id: 'email', name: 'Email (SMTP)', icon: 'ph:envelope', description: 'Send and receive emails', installed: false, badge: 'built-in' },
      { id: 'rest-api', name: 'REST API', icon: 'ph:plug', description: 'Generic API connector', installed: false, badge: 'built-in' },
    ],
  },
])

// Computed - Selected category
const selectedCategory = computed(() => {
  return integrationCategories.value.find(c => c.id === activeCategory.value)
})

// Computed - Total integration count
const totalIntegrationCount = computed(() => {
  return integrationCategories.value.reduce((sum, cat) => sum + cat.integrations.length, 0)
})

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

// Computed - Installed integrations as Integration objects (for cards)
const installedIntegrations = computed(() => {
  const installed: Integration[] = []
  for (const category of integrationCategories.value) {
    for (const integration of category.integrations) {
      if (integration.installed) {
        installed.push(integration)
      }
    }
  }
  return installed
})

// Computed - Installed count
const installedCount = computed(() => {
  return connectedServices.value.length
})

// Computed - Search results
const searchResults = computed(() => {
  if (!searchQuery.value) return []
  const query = searchQuery.value.toLowerCase()
  const results: Integration[] = []
  for (const category of integrationCategories.value) {
    for (const integration of category.integrations) {
      if (
        integration.name.toLowerCase().includes(query) ||
        integration.description.toLowerCase().includes(query)
      ) {
        results.push(integration)
      }
    }
  }
  return results
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
  if (integration.id === 'glm' || integration.id === 'glm-coding') {
    activeGlmIntegrationId.value = integration.id as 'glm' | 'glm-coding'
    showGlmConfigModal.value = true
    return
  }

  if (integration.id === 'telegram') {
    showTelegramConfigModal.value = true
    return
  }

  if (integration.id === 'plausible') {
    showPlausibleConfigModal.value = true
    return
  }

  for (const category of integrationCategories.value) {
    const found = category.integrations.find(i => i.id === integration.id)
    if (found) {
      found.installed = true
      break
    }
  }
}

const handleGlmSaved = (result: { enabled: boolean; configured: boolean }) => {
  for (const category of integrationCategories.value) {
    const found = category.integrations.find(i => i.id === activeGlmIntegrationId.value)
    if (found) {
      found.installed = result.enabled
      break
    }
  }
}

const handleConfigure = (integration: Integration) => {
  if (integration.id === 'glm' || integration.id === 'glm-coding') {
    activeGlmIntegrationId.value = integration.id as 'glm' | 'glm-coding'
    showGlmConfigModal.value = true
  } else if (integration.id === 'telegram') {
    showTelegramConfigModal.value = true
  } else if (integration.id === 'plausible') {
    showPlausibleConfigModal.value = true
  }
}

const handlePlausibleSaved = (result: { enabled: boolean; configured: boolean }) => {
  for (const category of integrationCategories.value) {
    const found = category.integrations.find(i => i.id === 'plausible')
    if (found) {
      found.installed = result.enabled
      break
    }
  }
}

const handleTelegramSaved = (result: { enabled: boolean; configured: boolean }) => {
  for (const category of integrationCategories.value) {
    const found = category.integrations.find(i => i.id === 'telegram')
    if (found) {
      found.installed = result.enabled
      break
    }
  }
}

const handleUninstall = (integration: Integration) => {
  for (const category of integrationCategories.value) {
    const found = category.integrations.find(i => i.id === integration.id)
    if (found) {
      found.installed = false
      break
    }
  }
}
</script>
