<template>
  <Modal
    v-model:open="isOpen"
    title="Configure Plausible Analytics"
    description="Connect your Plausible instance for website analytics"
    icon="ph:chart-line-up"
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
            :type="showKey ? 'text' : 'password'"
            placeholder="Enter your Plausible API key"
            class="w-full px-4 py-2.5 pr-10 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white placeholder:text-neutral-400 focus:border-neutral-900 dark:focus:border-white focus:ring-1 focus:ring-neutral-900 dark:focus:ring-white outline-none transition-colors font-mono text-sm"
          />
          <button
            type="button"
            class="absolute right-3 top-1/2 -translate-y-1/2 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300"
            @click="showKey = !showKey"
          >
            <Icon :name="showKey ? 'ph:eye-slash' : 'ph:eye'" class="w-4 h-4" />
          </button>
        </div>
        <p class="text-xs text-neutral-500 dark:text-neutral-400">
          Generate an API key in your Plausible account settings under "API Keys"
        </p>
      </div>

      <!-- Instance URL -->
      <div class="space-y-2">
        <label class="block text-sm font-medium text-neutral-900 dark:text-white">
          Instance URL
        </label>
        <input
          v-model="instanceUrl"
          type="text"
          placeholder="https://plausible.io"
          class="w-full px-4 py-2.5 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white placeholder:text-neutral-400 focus:border-neutral-900 dark:focus:border-white focus:ring-1 focus:ring-neutral-900 dark:focus:ring-white outline-none transition-colors text-sm"
        />
        <p class="text-xs text-neutral-500 dark:text-neutral-400">
          Use <code class="px-1 py-0.5 bg-neutral-100 dark:bg-neutral-700 rounded">https://plausible.io</code> for cloud, or your self-hosted URL
        </p>
      </div>

      <!-- Tracked Sites -->
      <div class="space-y-2">
        <label class="block text-sm font-medium text-neutral-900 dark:text-white">
          Tracked Sites
        </label>
        <div v-if="sites.length" class="space-y-1.5">
          <div
            v-for="(site, index) in sites"
            :key="site"
            class="flex items-center gap-2 px-3 py-1.5 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg"
          >
            <Icon name="ph:globe" class="w-3.5 h-3.5 text-neutral-400" />
            <span class="font-mono text-sm text-neutral-900 dark:text-white flex-1">{{ site }}</span>
            <button
              type="button"
              class="text-neutral-400 hover:text-red-500 transition-colors"
              @click="sites.splice(index, 1)"
            >
              <Icon name="ph:x" class="w-3.5 h-3.5" />
            </button>
          </div>
        </div>
        <div class="flex gap-2">
          <input
            v-model="newSite"
            type="text"
            placeholder="example.com"
            class="flex-1 px-4 py-2 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white placeholder:text-neutral-400 focus:border-neutral-900 dark:focus:border-white focus:ring-1 focus:ring-neutral-900 dark:focus:ring-white outline-none transition-colors text-sm font-mono"
            @keydown.enter.prevent="addSite"
          />
          <Button
            type="button"
            variant="secondary"
            size="sm"
            :disabled="!newSite.trim()"
            @click="addSite"
          >
            Add
          </Button>
        </div>
        <p class="text-xs text-neutral-500 dark:text-neutral-400">
          Add the domains you track in Plausible (e.g., <code class="px-1 py-0.5 bg-neutral-100 dark:bg-neutral-700 rounded">example.com</code>). Agents use these to query analytics.
        </p>
      </div>

      <!-- Action Buttons -->
      <div class="flex gap-3 pt-2">
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
      </div>

      <!-- Result Message -->
      <div v-if="resultMessage" class="mt-3">
        <div
          :class="[
            'p-3 rounded-lg text-sm flex items-start gap-2',
            resultMessage.success
              ? 'bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400'
              : 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400',
          ]"
        >
          <Icon
            :name="resultMessage.success ? 'ph:check-circle' : 'ph:x-circle'"
            class="w-4 h-4 mt-0.5 shrink-0"
          />
          <span>{{ resultMessage.message }}</span>
        </div>
      </div>

      <!-- Enable/Disable Toggle -->
      <div class="flex items-center justify-between p-4 bg-neutral-50 dark:bg-neutral-800 rounded-lg">
        <div>
          <p class="font-medium text-neutral-900 dark:text-white">Enable Integration</p>
          <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
            Allow agents to query Plausible Analytics data
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
import { ref, watch } from 'vue'
import Modal from '@/Components/shared/Modal.vue'
import Button from '@/Components/shared/Button.vue'
import Icon from '@/Components/shared/Icon.vue'

const isOpen = defineModel<boolean>('open', { default: false })

const emit = defineEmits<{
  saved: [{ enabled: boolean; configured: boolean }]
}>()

// Form state
const apiKey = ref('')
const instanceUrl = ref('https://plausible.io')
const sites = ref<string[]>([])
const newSite = ref('')
const enabled = ref(false)
const showKey = ref(false)

// UI state
const isTesting = ref(false)
const isSaving = ref(false)
const resultMessage = ref<{ success: boolean; message: string } | null>(null)

// Load config when modal opens
watch(isOpen, async (open) => {
  if (open) {
    resultMessage.value = null
    await loadConfig()
  }
}, { immediate: true })

const addSite = () => {
  const domain = newSite.value.trim().toLowerCase()
  if (domain && !sites.value.includes(domain)) {
    sites.value.push(domain)
    newSite.value = ''
  }
}

const loadConfig = async () => {
  try {
    const response = await fetch('/api/integrations/plausible/config')
    if (response.ok) {
      const data = await response.json()
      apiKey.value = data.config?.apiKey || ''
      instanceUrl.value = data.config?.url || 'https://plausible.io'
      sites.value = data.config?.sites || []
      enabled.value = data.enabled || false
    }
  } catch (error) {
    console.error('Failed to load Plausible config:', error)
  }
}

const testConnection = async () => {
  isTesting.value = true
  resultMessage.value = null

  try {
    const response = await fetch('/api/integrations/plausible/test', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        apiKey: apiKey.value,
        url: instanceUrl.value,
      }),
    })

    const data = await response.json()
    resultMessage.value = {
      success: data.success,
      message: data.success
        ? data.message
        : data.error || 'Connection failed',
    }
  } catch (error) {
    resultMessage.value = {
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
    const response = await fetch('/api/integrations/plausible/config', {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        apiKey: apiKey.value,
        url: instanceUrl.value,
        sites: sites.value,
        enabled: enabled.value,
      }),
    })

    if (response.ok) {
      const data = await response.json()
      emit('saved', { enabled: data.enabled, configured: data.configured })
      isOpen.value = false
    } else {
      const error = await response.json()
      resultMessage.value = {
        success: false,
        message: error.error || 'Failed to save configuration',
      }
    }
  } catch (error) {
    resultMessage.value = {
      success: false,
      message: 'Failed to save configuration. Please try again.',
    }
  } finally {
    isSaving.value = false
  }
}
</script>
