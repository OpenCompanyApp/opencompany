<template>
  <Modal
    v-model:open="isOpen"
    :title="modalTitle"
    :description="modalDescription"
    :icon="modalIcon"
    size="md"
  >
    <form class="space-y-5" @submit.prevent="handleSave">
      <!-- API Key -->
      <div class="space-y-2">
        <label class="block text-sm font-medium text-neutral-900 dark:text-white">
          API Key
        </label>
        <div class="relative">
          <input
            v-model="apiKey"
            :type="showApiKey ? 'text' : 'password'"
            placeholder="Enter your Zhipu AI API key"
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
        <p class="text-xs text-neutral-500 dark:text-neutral-400">
          Get your API key from the <a href="https://open.bigmodel.cn/" target="_blank" class="underline hover:text-neutral-700 dark:hover:text-neutral-300">Zhipu AI Platform</a>
        </p>
      </div>

      <!-- API URL -->
      <div class="space-y-2">
        <label class="block text-sm font-medium text-neutral-900 dark:text-white">
          API URL
        </label>
        <input
          v-model="apiUrl"
          type="url"
          :placeholder="defaultUrl"
          class="w-full px-4 py-2.5 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white placeholder:text-neutral-400 focus:border-neutral-900 dark:focus:border-white focus:ring-1 focus:ring-neutral-900 dark:focus:ring-white outline-none transition-colors text-sm"
        />
        <p class="text-xs text-neutral-500 dark:text-neutral-400">
          {{ urlHint }}
        </p>
      </div>

      <!-- Default Model -->
      <div class="space-y-2">
        <label class="block text-sm font-medium text-neutral-900 dark:text-white">
          Default Model
        </label>
        <select
          v-model="defaultModel"
          class="w-full px-4 py-2.5 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white focus:border-neutral-900 dark:focus:border-white focus:ring-1 focus:ring-neutral-900 dark:focus:ring-white outline-none transition-colors text-sm"
        >
          <option v-for="(name, id) in availableModels" :key="id" :value="id">
            {{ name }}
          </option>
        </select>
      </div>

      <!-- Test Connection -->
      <div class="pt-2">
        <Button
          type="button"
          variant="secondary"
          :loading="isTesting"
          :disabled="!apiKey"
          icon-left="ph:plugs-connected"
          @click="testConnection"
        >
          Test Connection
        </Button>

        <!-- Test Result -->
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
            Allow agents to use {{ integrationId === 'glm-coding' ? 'GLM Coding Plan' : 'GLM' }} models
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
          :disabled="!apiKey"
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
import Modal from '@/Components/shared/Modal.vue'
import Button from '@/Components/shared/Button.vue'
import Icon from '@/Components/shared/Icon.vue'

const props = withDefaults(defineProps<{
  integrationId?: 'glm' | 'glm-coding'
}>(), {
  integrationId: 'glm-coding',
})

const isOpen = defineModel<boolean>('open', { default: false })

const emit = defineEmits<{
  saved: [{ enabled: boolean; configured: boolean }]
}>()

// Integration-specific configuration
const integrationConfig = {
  'glm': {
    title: 'Configure GLM (Zhipu AI)',
    description: 'Set up the general-purpose GLM AI model',
    icon: 'ph:brain',
    defaultUrl: 'https://open.bigmodel.cn/api/paas/v4',
    urlHint: 'Standard Zhipu AI endpoint for general-purpose models',
    models: {
      'glm-4-plus': 'GLM 4 Plus (Most Capable)',
      'glm-4': 'GLM 4',
      'glm-4-air': 'GLM 4 Air (Balanced)',
      'glm-4-flash': 'GLM 4 Flash (Fast)',
    } as Record<string, string>,
    defaultModel: 'glm-4',
  },
  'glm-coding': {
    title: 'Configure GLM Coding Plan',
    description: 'Set up the specialized coding LLM',
    icon: 'ph:code',
    defaultUrl: 'https://api.z.ai/api/coding/paas/v4',
    urlHint: 'Zhipu Coding Plan endpoint for code-optimized models',
    models: {
      'glm-4.7': 'GLM 4.7 (Coding Optimized)',
    } as Record<string, string>,
    defaultModel: 'glm-4.7',
  },
}

// Computed config based on integrationId
const config = computed(() => integrationConfig[props.integrationId])
const modalTitle = computed(() => config.value.title)
const modalDescription = computed(() => config.value.description)
const modalIcon = computed(() => config.value.icon)
const defaultUrl = computed(() => config.value.defaultUrl)
const urlHint = computed(() => config.value.urlHint)

// Models loaded from backend API (falls back to hardcoded defaults)
const serverModels = ref<Record<string, string> | null>(null)
const availableModels = computed(() => serverModels.value ?? config.value.models)

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

// Load existing config when modal opens or integrationId changes
watch([isOpen, () => props.integrationId], async ([open]) => {
  if (open) {
    await loadConfig()
  }
}, { immediate: true })

const loadConfig = async () => {
  // Reset to defaults first
  apiUrl.value = config.value.defaultUrl
  defaultModel.value = config.value.defaultModel
  serverModels.value = null
  testResult.value = null

  try {
    const response = await fetch(`/api/integrations/${props.integrationId}/config`)
    if (response.ok) {
      const data = await response.json()
      apiKey.value = data.config?.apiKey || ''
      apiUrl.value = data.config?.url || config.value.defaultUrl
      defaultModel.value = data.config?.defaultModel || config.value.defaultModel
      enabled.value = data.enabled || false
      if (data.models && Object.keys(data.models).length > 0) {
        serverModels.value = data.models
      }
    }
  } catch (error) {
    console.error('Failed to load config:', error)
  }
}

const testConnection = async () => {
  isTesting.value = true
  testResult.value = null

  try {
    const response = await fetch(`/api/integrations/${props.integrationId}/test`, {
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
        message: `Connection successful! Using model: ${data.model}`,
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
    const response = await fetch(`/api/integrations/${props.integrationId}/config`, {
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
