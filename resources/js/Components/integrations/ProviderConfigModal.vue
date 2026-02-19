<template>
  <Modal
    v-model:open="isOpen"
    :title="`Configure ${providerMeta.name}`"
    :description="providerMeta.description"
    :icon="providerMeta.icon"
    size="md"
  >
    <form class="space-y-5" @submit.prevent="handleSave">
      <!-- API Key -->
      <div v-if="providerMeta.apiFormat !== 'ollama'" class="space-y-2">
        <label class="block text-sm font-medium text-neutral-900 dark:text-white">
          API Key
        </label>
        <div class="relative">
          <input
            v-model="apiKey"
            :type="showApiKey ? 'text' : 'password'"
            :placeholder="`Enter your ${providerMeta.name} API key`"
            class="w-full px-4 py-2.5 pr-10 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white placeholder:text-neutral-400 focus:border-neutral-900 dark:focus:border-white focus:ring-1 focus:ring-neutral-900 dark:focus:ring-white outline-none transition-colors font-mono text-sm"
          />
          <button
            type="button"
            class="absolute right-3 top-1/2 -translate-y-1/2 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300"
            @click="showApiKey = !showApiKey"
          >
            <Icon :name="showApiKey ? 'ph:eye-slash' : 'ph:eye'" class="w-4 h-4" />
          </button>
        </div>
        <p v-if="providerMeta.apiKeyUrl" class="text-xs text-neutral-500 dark:text-neutral-400">
          Get your API key from
          <a :href="providerMeta.apiKeyUrl" target="_blank" class="underline hover:text-neutral-700 dark:hover:text-neutral-300">
            {{ providerMeta.name }}
          </a>
        </p>
      </div>

      <!-- API URL -->
      <div v-if="showUrlField" class="space-y-2">
        <label class="block text-sm font-medium text-neutral-900 dark:text-white">
          API URL
        </label>
        <input
          v-model="apiUrl"
          type="url"
          :placeholder="providerMeta.defaultUrl"
          class="w-full px-4 py-2.5 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white placeholder:text-neutral-400 focus:border-neutral-900 dark:focus:border-white focus:ring-1 focus:ring-neutral-900 dark:focus:ring-white outline-none transition-colors text-sm"
        />
        <p class="text-xs text-neutral-500 dark:text-neutral-400">
          Leave default unless using a custom endpoint or proxy
        </p>
      </div>

      <!-- Default Model -->
      <div class="space-y-2">
        <div class="flex items-center justify-between">
          <label class="block text-sm font-medium text-neutral-900 dark:text-white">
            Default Model
          </label>
          <button
            type="button"
            class="flex items-center gap-1.5 text-xs text-neutral-500 hover:text-neutral-700 dark:text-neutral-400 dark:hover:text-neutral-200 transition-colors"
            :disabled="isFetchingModels || (!apiKey && providerMeta.apiFormat !== 'ollama')"
            @click="refreshModels"
          >
            <Icon name="ph:arrow-clockwise" :class="['w-3.5 h-3.5', isFetchingModels && 'animate-spin']" />
            {{ isFetchingModels ? 'Fetching...' : 'Refresh Models' }}
          </button>
        </div>
        <select
          v-if="availableModels && Object.keys(availableModels).length > 0"
          v-model="defaultModel"
          class="w-full px-4 py-2.5 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white focus:border-neutral-900 dark:focus:border-white focus:ring-1 focus:ring-neutral-900 dark:focus:ring-white outline-none transition-colors text-sm"
        >
          <option v-for="(name, id) in availableModels" :key="id" :value="id">
            {{ name }}
          </option>
        </select>
        <p v-else class="text-xs text-neutral-500 dark:text-neutral-400 italic">
          {{ providerMeta.apiFormat === 'ollama'
            ? 'No models found. Make sure Ollama is running, then click "Refresh Models".'
            : 'No models loaded yet. Save your API key, then click "Refresh Models".' }}
        </p>
        <p v-if="fetchResult" :class="['text-xs', fetchResult.success ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400']">
          {{ fetchResult.message }}
        </p>
      </div>

      <!-- Test Connection -->
      <div class="pt-2">
        <Button
          type="button"
          variant="secondary"
          :loading="isTesting"
          :disabled="!apiKey && providerMeta.apiFormat !== 'ollama'"
          icon-left="ph:plugs-connected"
          @click="testConnection"
        >
          Test Connection
        </Button>

        <div v-if="testResult" class="mt-3">
          <div
            :class="[
              'p-3 rounded-lg text-sm flex items-start gap-2',
              testResult.success
                ? 'bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400'
                : 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400',
            ]"
          >
            <Icon
              :name="testResult.success ? 'ph:check-circle' : 'ph:x-circle'"
              class="w-4 h-4 mt-0.5 shrink-0"
            />
            <span>{{ testResult.message }}</span>
          </div>
        </div>
      </div>

      <!-- Enable/Disable Toggle -->
      <div class="flex items-center justify-between p-4 bg-neutral-50 dark:bg-neutral-800 rounded-lg">
        <div>
          <p class="font-medium text-neutral-900 dark:text-white">Enable Integration</p>
          <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
            Allow agents to use {{ providerMeta.name }} models
          </p>
        </div>
        <button
          type="button"
          :class="[
            'relative w-11 h-6 rounded-full transition-colors',
            enabled ? 'bg-green-500' : 'bg-neutral-300 dark:bg-neutral-600',
          ]"
          @click="enabled = !enabled"
        >
          <span
            :class="[
              'absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition-transform shadow-sm',
              enabled && 'translate-x-5',
            ]"
          />
        </button>
      </div>
    </form>

    <template #footer>
      <div class="flex items-center justify-end gap-3">
        <Button variant="ghost" @click="isOpen = false">
          Cancel
        </Button>
        <Button
          variant="primary"
          :loading="isSaving"
          :disabled="!apiKey && providerMeta.apiFormat !== 'ollama'"
          @click="handleSave"
        >
          Save Configuration
        </Button>
      </div>
    </template>
  </Modal>
</template>

<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { apiFetch } from '@/utils/apiFetch'
import Modal from '@/Components/shared/Modal.vue'
import Button from '@/Components/shared/Button.vue'
import Icon from '@/Components/shared/Icon.vue'

const props = defineProps<{
  integrationId: string
}>()

const isOpen = defineModel<boolean>('open', { default: false })

const emit = defineEmits<{
  saved: [{ enabled: boolean; configured: boolean }]
}>()

// Provider metadata loaded from backend
const providerMeta = ref<{
  name: string
  description: string
  icon: string
  defaultUrl: string
  apiFormat: string | null
  apiKeyUrl: string | null
}>({
  name: '',
  description: '',
  icon: 'ph:gear',
  defaultUrl: '',
  apiFormat: null,
  apiKeyUrl: null,
})

// Show URL field for providers that support custom URLs
const showUrlField = computed(() => {
  const format = providerMeta.value.apiFormat
  return format === 'openai_compat' || format === 'ollama'
})

// Models loaded from backend API
const availableModels = ref<Record<string, string> | null>(null)
const isFetchingModels = ref(false)
const fetchResult = ref<{ success: boolean; message: string } | null>(null)

// Form state
const apiKey = ref('')
const apiUrl = ref('')
const defaultModel = ref('')
const enabled = ref(false)
const showApiKey = ref(false)

// UI state
const isTesting = ref(false)
const isSaving = ref(false)
const testResult = ref<{ success: boolean; message: string } | null>(null)

// Load config when modal opens or integrationId changes
watch([isOpen, () => props.integrationId], async ([open]) => {
  if (open) {
    await loadConfig()
  }
}, { immediate: true })

const loadConfig = async () => {
  // Reset
  apiKey.value = ''
  apiUrl.value = ''
  defaultModel.value = ''
  availableModels.value = null
  testResult.value = null
  fetchResult.value = null
  enabled.value = false

  try {
    const response = await apiFetch(`/api/integrations/${props.integrationId}/config`)
    if (response.ok) {
      const data = await response.json()

      // Set metadata
      providerMeta.value = {
        name: data.name || props.integrationId,
        description: data.description || '',
        icon: data.icon || 'ph:gear',
        defaultUrl: data.defaultUrl || '',
        apiFormat: data.apiFormat || null,
        apiKeyUrl: data.apiKeyUrl || null,
      }

      // Set form values
      apiKey.value = data.config?.apiKey || ''
      apiUrl.value = data.config?.url || data.defaultUrl || ''
      enabled.value = data.enabled || false

      if (data.models && Object.keys(data.models).length > 0) {
        availableModels.value = data.models
      }
      defaultModel.value = data.config?.defaultModel || Object.keys(data.models || {})[0] || ''
    }
  } catch (error) {
    console.error('Failed to load config:', error)
  }
}

const refreshModels = async () => {
  isFetchingModels.value = true
  fetchResult.value = null

  try {
    const response = await apiFetch(`/api/integrations/${props.integrationId}/fetch-models`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
    })
    const data = await response.json()

    if (data.success) {
      availableModels.value = data.models
      if (!defaultModel.value && data.models) {
        defaultModel.value = Object.keys(data.models)[0] || ''
      }
      fetchResult.value = { success: true, message: `Found ${data.count} model(s)` }
    } else {
      fetchResult.value = { success: false, message: data.error || 'Failed to fetch models' }
    }
  } catch (error) {
    fetchResult.value = { success: false, message: 'Failed to fetch models. Check your network.' }
  } finally {
    isFetchingModels.value = false
  }
}

const testConnection = async () => {
  isTesting.value = true
  testResult.value = null

  try {
    const response = await apiFetch(`/api/integrations/${props.integrationId}/test`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        apiKey: apiKey.value,
        url: apiUrl.value,
        defaultModel: defaultModel.value,
      }),
    })

    const data = await response.json()

    if (data.success) {
      testResult.value = {
        success: true,
        message: `Connection successful${data.model ? ` — model: ${data.model}` : ''}`,
      }
    } else {
      testResult.value = {
        success: false,
        message: data.error || 'Connection failed',
      }
    }
  } catch (error) {
    testResult.value = {
      success: false,
      message: 'Failed to test connection. Please check your network.',
    }
  } finally {
    isTesting.value = false
  }
}

const handleSave = async () => {
  isSaving.value = true

  try {
    const response = await apiFetch(`/api/integrations/${props.integrationId}/config`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        apiKey: apiKey.value,
        url: apiUrl.value,
        defaultModel: defaultModel.value,
        enabled: enabled.value,
      }),
    })

    if (response.ok) {
      const data = await response.json()
      emit('saved', { enabled: data.enabled, configured: data.configured })
      isOpen.value = false
    } else {
      const error = await response.json()
      testResult.value = {
        success: false,
        message: error.error || 'Failed to save configuration',
      }
    }
  } catch (error) {
    testResult.value = {
      success: false,
      message: 'Failed to save configuration. Please try again.',
    }
  } finally {
    isSaving.value = false
  }
}
</script>
