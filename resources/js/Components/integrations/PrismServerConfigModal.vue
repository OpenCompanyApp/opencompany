<template>
  <Modal
    v-model:open="isOpen"
    title="Prism Server"
    description="Expose your AI models via an OpenAI-compatible API"
    icon="ph:broadcast"
    size="lg"
  >
    <div class="space-y-6">
      <!-- Enable/Disable Toggle -->
      <section>
        <h3 class="text-sm font-medium text-neutral-900 dark:text-white mb-3">Status</h3>
        <div class="bg-neutral-50 dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700 p-4">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div :class="[
                'w-8 h-8 rounded-lg flex items-center justify-center transition-colors',
                config.enabled
                  ? 'bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400'
                  : 'bg-neutral-100 dark:bg-neutral-700 text-neutral-400 dark:text-neutral-500',
              ]">
                <Icon :name="config.enabled ? 'ph:broadcast' : 'ph:broadcast-fill'" class="w-4 h-4" />
              </div>
              <div>
                <p class="text-sm font-medium text-neutral-900 dark:text-white">
                  {{ config.enabled ? 'Server Active' : 'Server Disabled' }}
                </p>
                <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
                  <code class="text-[11px]">/prism/openai/v1</code>
                </p>
              </div>
            </div>
            <button
              type="button"
              role="switch"
              :aria-checked="config.enabled"
              :class="[
                'relative inline-flex h-5 w-9 items-center rounded-full transition-colors',
                config.enabled ? 'bg-green-500' : 'bg-neutral-300 dark:bg-neutral-600',
              ]"
              @click="config.enabled = !config.enabled"
            >
              <span
                :class="[
                  'inline-block h-3.5 w-3.5 rounded-full bg-white transition-transform',
                  config.enabled ? 'translate-x-[18px]' : 'translate-x-[3px]',
                ]"
              />
            </button>
          </div>
        </div>
      </section>

      <!-- Model Selection -->
      <section>
        <div class="flex items-center gap-2 mb-3">
          <h3 class="text-sm font-medium text-neutral-900 dark:text-white">Models</h3>
          <span v-if="config.enabled_models.length > 0" class="text-xs text-neutral-400 dark:text-neutral-500">
            {{ config.enabled_models.length }} selected
          </span>
        </div>

        <div class="bg-neutral-50 dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700 p-4 space-y-2">
          <div v-if="loadingModels" class="flex items-center gap-2 text-sm text-neutral-500 dark:text-neutral-400">
            <Icon name="ph:spinner" class="w-4 h-4 animate-spin" />
            Loading available models...
          </div>

          <div v-else-if="groupedModels.length === 0" class="text-sm text-neutral-500 dark:text-neutral-400">
            <p>No AI models configured.</p>
            <a :href="workspacePath('/integrations')" class="text-neutral-900 dark:text-white underline hover:no-underline mt-1 inline-block">
              Configure integrations
            </a>
          </div>

          <template v-else>
            <div v-for="group in groupedModels" :key="group.provider" class="rounded-lg border border-neutral-200 dark:border-neutral-700 overflow-hidden">
              <!-- Provider Header -->
              <button
                type="button"
                class="w-full px-3 py-2.5 flex items-center gap-2.5 hover:bg-neutral-100 dark:hover:bg-neutral-700/50 transition-colors"
                @click="toggleProvider(group.provider)"
              >
                <div class="w-7 h-7 rounded-md flex items-center justify-center bg-neutral-100 dark:bg-neutral-700 text-neutral-500 dark:text-neutral-400 shrink-0">
                  <Icon :name="group.icon" class="w-3.5 h-3.5" />
                </div>
                <span class="text-sm font-medium text-neutral-900 dark:text-white flex-1 text-left">{{ group.providerName }}</span>
                <span class="text-xs text-neutral-400 dark:text-neutral-500">
                  {{ selectedCountForProvider(group.provider) }}/{{ group.models.length }}
                </span>
                <Icon
                  name="ph:caret-down"
                  :class="[
                    'w-3.5 h-3.5 text-neutral-400 transition-transform duration-200',
                    expandedProviders.has(group.provider) ? 'rotate-180' : '',
                  ]"
                />
              </button>

              <!-- Models List -->
              <div v-if="expandedProviders.has(group.provider)" class="border-t border-neutral-200 dark:border-neutral-700 px-2 py-1.5 space-y-1">
                <button
                  v-for="model in group.models"
                  :key="model.id"
                  type="button"
                  :class="[
                    'w-full px-3 py-2 rounded-md text-left transition-all flex items-center gap-2.5',
                    config.enabled_models.includes(model.id)
                      ? 'bg-neutral-900/5 dark:bg-white/5'
                      : 'hover:bg-neutral-100 dark:hover:bg-neutral-700/50',
                  ]"
                  @click="toggleModel(model.id)"
                >
                  <Icon
                    :name="config.enabled_models.includes(model.id) ? 'ph:check-square-fill' : 'ph:square'"
                    :class="[
                      'w-4 h-4 shrink-0',
                      config.enabled_models.includes(model.id) ? 'text-green-500' : 'text-neutral-400 dark:text-neutral-500',
                    ]"
                  />
                  <span class="flex-1 text-sm text-neutral-700 dark:text-neutral-300">{{ model.name }}</span>
                </button>
              </div>
            </div>
          </template>
        </div>
      </section>

      <!-- API Keys -->
      <section>
        <div class="flex items-center justify-between mb-3">
          <h3 class="text-sm font-medium text-neutral-900 dark:text-white">API Keys</h3>
          <button
            type="button"
            class="flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-md text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors"
            @click="showCreateKey = !showCreateKey"
          >
            <Icon name="ph:plus" class="w-3.5 h-3.5" />
            New Key
          </button>
        </div>

        <div class="bg-neutral-50 dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700 p-4 space-y-3">
          <!-- Create Key Form -->
          <div v-if="showCreateKey" class="p-3 bg-white dark:bg-neutral-900 rounded-lg border border-neutral-200 dark:border-neutral-700 space-y-2">
            <input
              v-model="newKeyName"
              type="text"
              placeholder="Key name (e.g., Production)"
              class="w-full px-3 py-2 text-sm rounded-md border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 text-neutral-900 dark:text-white placeholder-neutral-400 focus:outline-none focus:ring-1 focus:ring-neutral-400"
              @keydown.enter="createKey"
            />
            <div class="flex gap-2">
              <Button
                variant="primary"
                size="sm"
                :loading="isCreatingKey"
                @click="createKey"
              >
                Generate
              </Button>
              <Button
                variant="ghost"
                size="sm"
                @click="showCreateKey = false; newKeyName = ''"
              >
                Cancel
              </Button>
            </div>
          </div>

          <!-- Newly Created Key Alert -->
          <div v-if="newlyCreatedKey" class="p-3 bg-amber-50 dark:bg-amber-900/20 rounded-lg border border-amber-200 dark:border-amber-800">
            <div class="flex items-start gap-2">
              <Icon name="ph:warning" class="w-4 h-4 text-amber-600 dark:text-amber-400 mt-0.5 shrink-0" />
              <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-amber-800 dark:text-amber-300">Copy your API key now</p>
                <p class="text-xs text-amber-700 dark:text-amber-400 mt-0.5">
                  This key won't be shown again.
                </p>
                <div class="mt-2 flex items-center gap-2">
                  <code class="text-xs font-mono bg-white dark:bg-neutral-800 px-2 py-1 rounded border border-amber-200 dark:border-amber-700 text-neutral-900 dark:text-white select-all break-all flex-1">
                    {{ newlyCreatedKey }}
                  </code>
                  <button
                    type="button"
                    class="p-1.5 rounded-md text-amber-600 dark:text-amber-400 hover:bg-amber-100 dark:hover:bg-amber-900/30 transition-colors shrink-0"
                    @click="copyKey(newlyCreatedKey)"
                  >
                    <Icon :name="copiedKey ? 'ph:check' : 'ph:copy'" class="w-4 h-4" />
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Key List -->
          <template v-if="apiKeys.length > 0">
            <div class="rounded-lg border border-neutral-200 dark:border-neutral-700 overflow-hidden divide-y divide-neutral-100 dark:divide-neutral-700">
              <div
                v-for="key in apiKeys"
                :key="key.id"
                class="px-3 py-2.5 flex items-center gap-3 bg-white dark:bg-neutral-900"
              >
                <div class="w-7 h-7 rounded-md flex items-center justify-center bg-neutral-100 dark:bg-neutral-700 text-neutral-400 shrink-0">
                  <Icon name="ph:key" class="w-3.5 h-3.5" />
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium text-neutral-900 dark:text-white">{{ key.name }}</p>
                  <p class="text-xs text-neutral-400 dark:text-neutral-500 font-mono mt-0.5">{{ key.masked_key }}</p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                  <span v-if="key.last_used_at" class="text-[10px] text-neutral-400 dark:text-neutral-500 hidden sm:inline">
                    {{ formatDate(key.last_used_at) }}
                  </span>
                  <button
                    type="button"
                    class="p-1.5 rounded-md text-neutral-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
                    @click="revokeKey(key.id)"
                  >
                    <Icon name="ph:trash" class="w-3.5 h-3.5" />
                  </button>
                </div>
              </div>
            </div>
          </template>

          <div v-else-if="!showCreateKey && !newlyCreatedKey" class="text-center py-4">
            <Icon name="ph:key" class="w-6 h-6 text-neutral-300 dark:text-neutral-600 mx-auto mb-1.5" />
            <p class="text-xs text-neutral-500 dark:text-neutral-400">No API keys yet</p>
          </div>
        </div>
      </section>

      <!-- Endpoint Info -->
      <section>
        <h3 class="text-sm font-medium text-neutral-900 dark:text-white mb-3">Endpoint</h3>
        <div class="bg-neutral-50 dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700 p-4 space-y-3">
          <div class="flex items-center gap-2.5">
            <div class="w-7 h-7 rounded-md flex items-center justify-center bg-neutral-100 dark:bg-neutral-700 text-neutral-500 dark:text-neutral-400 shrink-0">
              <Icon name="ph:link" class="w-3.5 h-3.5" />
            </div>
            <code class="text-sm font-mono text-neutral-900 dark:text-white select-all">{{ baseUrl }}</code>
          </div>
          <div class="rounded-lg overflow-hidden">
            <code class="block text-[11px] font-mono bg-neutral-900 dark:bg-neutral-950 text-green-400 p-3 overflow-x-auto whitespace-pre">curl {{ baseUrl }}/models \
  -H "Authorization: Bearer ps_live_..."</code>
          </div>
        </div>
      </section>

      <!-- Error -->
      <div v-if="errorMessage" class="p-3 rounded-lg text-sm flex items-start gap-2 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400">
        <Icon name="ph:x-circle" class="w-4 h-4 mt-0.5 shrink-0" />
        <span>{{ errorMessage }}</span>
      </div>
    </div>

    <template #footer>
      <div class="flex items-center justify-end gap-2">
        <Button variant="ghost" @click="isOpen = false">
          Close
        </Button>
        <Button
          variant="primary"
          :loading="isSaving"
          @click="save"
        >
          Save
        </Button>
      </div>
    </template>
  </Modal>
</template>

<script setup lang="ts">
import { ref, reactive, watch, computed } from 'vue'
import axios from 'axios'
import Modal from '@/Components/shared/Modal.vue'
import Button from '@/Components/shared/Button.vue'
import Icon from '@/Components/shared/Icon.vue'
import { useWorkspace } from '@/composables/useWorkspace'

const { workspacePath } = useWorkspace()

const isOpen = defineModel<boolean>('open', { default: false })

const emit = defineEmits<{
  saved: [{ enabled: boolean; configured: boolean }]
}>()

interface AvailableModel {
  id: string
  provider: string
  providerName: string
  model: string
  name: string
  icon: string
}

interface ModelGroup {
  provider: string
  providerName: string
  icon: string
  models: AvailableModel[]
}

interface ApiKeyEntry {
  id: string
  name: string
  masked_key: string
  last_used_at: string | null
  expires_at: string | null
  created_at: string
}

// Config state
const config = reactive({
  enabled: false,
  enabled_models: [] as string[],
})

// Available models
const availableModels = ref<AvailableModel[]>([])
const loadingModels = ref(false)
const expandedProviders = ref(new Set<string>())

// API Keys
const apiKeys = ref<ApiKeyEntry[]>([])
const showCreateKey = ref(false)
const newKeyName = ref('')
const isCreatingKey = ref(false)
const newlyCreatedKey = ref('')
const copiedKey = ref(false)

// Save state
const isSaving = ref(false)
const errorMessage = ref('')

// Base URL
const baseUrl = computed(() => {
  return `${window.location.origin}/prism/openai/v1`
})

// Group models by provider
const groupedModels = computed<ModelGroup[]>(() => {
  const groups = new Map<string, ModelGroup>()
  for (const model of availableModels.value) {
    if (!groups.has(model.provider)) {
      groups.set(model.provider, {
        provider: model.provider,
        providerName: model.providerName,
        icon: model.icon,
        models: [],
      })
    }
    groups.get(model.provider)!.models.push(model)
  }
  return Array.from(groups.values())
})

const selectedCountForProvider = (provider: string): number => {
  const group = groupedModels.value.find(g => g.provider === provider)
  if (!group) return 0
  return group.models.filter(m => config.enabled_models.includes(m.id)).length
}

const toggleProvider = (provider: string) => {
  if (expandedProviders.value.has(provider)) {
    expandedProviders.value.delete(provider)
  } else {
    expandedProviders.value.add(provider)
  }
  expandedProviders.value = new Set(expandedProviders.value)
}

const toggleModel = (modelId: string) => {
  const idx = config.enabled_models.indexOf(modelId)
  if (idx >= 0) {
    config.enabled_models.splice(idx, 1)
  } else {
    config.enabled_models.push(modelId)
  }
}

// Load data when modal opens
watch(isOpen, async (open) => {
  if (open) {
    errorMessage.value = ''
    newlyCreatedKey.value = ''
    await Promise.all([loadConfig(), loadModels(), loadApiKeys()])

    // Auto-expand providers with selected models
    for (const group of groupedModels.value) {
      if (group.models.some(m => config.enabled_models.includes(m.id))) {
        expandedProviders.value.add(group.provider)
      }
    }
    expandedProviders.value = new Set(expandedProviders.value)
  }
})

const loadConfig = async () => {
  try {
    const { data } = await axios.get('/api/prism-server/config')
    config.enabled = data.enabled
    config.enabled_models = data.enabled_models || []
  } catch (error) {
    console.error('Failed to load Prism Server config:', error)
  }
}

const loadModels = async () => {
  loadingModels.value = true
  try {
    const { data } = await axios.get('/api/integrations/models')
    availableModels.value = data
  } catch (error) {
    console.error('Failed to load models:', error)
  } finally {
    loadingModels.value = false
  }
}

const loadApiKeys = async () => {
  try {
    const { data } = await axios.get('/api/prism-server/api-keys')
    apiKeys.value = data
  } catch (error) {
    console.error('Failed to load API keys:', error)
  }
}

const save = async () => {
  isSaving.value = true
  errorMessage.value = ''

  try {
    await axios.put('/api/prism-server/config', {
      enabled: config.enabled,
      enabled_models: config.enabled_models,
    })
    emit('saved', { enabled: config.enabled, configured: true })
  } catch (error: any) {
    errorMessage.value = error.response?.data?.message || 'Failed to save configuration'
  } finally {
    isSaving.value = false
  }
}

const createKey = async () => {
  if (!newKeyName.value.trim()) return

  isCreatingKey.value = true
  errorMessage.value = ''

  try {
    const { data } = await axios.post('/api/prism-server/api-keys', {
      name: newKeyName.value.trim(),
    })
    newlyCreatedKey.value = data.key
    showCreateKey.value = false
    newKeyName.value = ''
    await loadApiKeys()
  } catch (error: any) {
    errorMessage.value = error.response?.data?.message || 'Failed to create API key'
  } finally {
    isCreatingKey.value = false
  }
}

const revokeKey = async (id: string) => {
  try {
    await axios.delete(`/api/prism-server/api-keys/${id}`)
    apiKeys.value = apiKeys.value.filter(k => k.id !== id)
  } catch (error: any) {
    errorMessage.value = error.response?.data?.message || 'Failed to revoke key'
  }
}

const copyKey = async (key: string) => {
  await navigator.clipboard.writeText(key)
  copiedKey.value = true
  setTimeout(() => { copiedKey.value = false }, 2000)
}

const formatDate = (dateStr: string | null) => {
  if (!dateStr) return 'Never'
  try {
    const date = new Date(dateStr)
    const now = new Date()
    const diffMs = now.getTime() - date.getTime()
    const diffMins = Math.floor(diffMs / 60000)

    if (diffMins < 1) return 'just now'
    if (diffMins < 60) return `${diffMins}m ago`
    const diffHours = Math.floor(diffMins / 60)
    if (diffHours < 24) return `${diffHours}h ago`
    const diffDays = Math.floor(diffHours / 24)
    if (diffDays < 30) return `${diffDays}d ago`

    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
  } catch {
    return dateStr
  }
}
</script>
