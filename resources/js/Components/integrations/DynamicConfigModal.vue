<template>
  <Modal
    v-model:open="isOpen"
    :title="'Configure ' + (meta?.name || integrationId)"
    :description="meta?.description || ''"
    :icon="meta?.icon || 'ph:gear'"
    size="md"
  >
    <form class="space-y-5" @submit.prevent="handleSave">
      <!-- Dynamic Fields -->
      <div v-for="field in schema" :key="field.key" v-show="isFieldVisible(field)" class="space-y-2">
        <label class="block text-sm font-medium text-neutral-900 dark:text-white">
          {{ field.label }}
        </label>

        <!-- Secret field -->
        <div v-if="field.type === 'secret'" class="relative">
          <input
            v-model="formValues[field.key]"
            :type="showSecrets[field.key] ? 'text' : 'password'"
            :placeholder="field.placeholder || ''"
            class="w-full px-4 py-2.5 pr-10 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white placeholder:text-neutral-400 focus:border-neutral-900 dark:focus:border-white focus:ring-1 focus:ring-neutral-900 dark:focus:ring-white outline-none transition-colors font-mono text-sm"
          />
          <button
            type="button"
            class="absolute right-3 top-1/2 -translate-y-1/2 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300"
            @click="showSecrets[field.key] = !showSecrets[field.key]"
          >
            <Icon :name="showSecrets[field.key] ? 'ph:eye-slash' : 'ph:eye'" class="w-4 h-4" />
          </button>
        </div>

        <!-- URL field -->
        <input
          v-else-if="field.type === 'url'"
          v-model="formValues[field.key]"
          type="text"
          :placeholder="field.placeholder || ''"
          class="w-full px-4 py-2.5 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white placeholder:text-neutral-400 focus:border-neutral-900 dark:focus:border-white focus:ring-1 focus:ring-neutral-900 dark:focus:ring-white outline-none transition-colors text-sm"
        />

        <!-- Text field -->
        <input
          v-else-if="field.type === 'text'"
          v-model="formValues[field.key]"
          type="text"
          :placeholder="field.placeholder || ''"
          class="w-full px-4 py-2.5 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white placeholder:text-neutral-400 focus:border-neutral-900 dark:focus:border-white focus:ring-1 focus:ring-neutral-900 dark:focus:ring-white outline-none transition-colors text-sm"
        />

        <!-- Select field -->
        <select
          v-else-if="field.type === 'select'"
          v-model="formValues[field.key]"
          class="w-full px-4 py-2.5 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white focus:border-neutral-900 dark:focus:border-white focus:ring-1 focus:ring-neutral-900 dark:focus:ring-white outline-none transition-colors text-sm"
        >
          <option v-for="(optLabel, optValue) in field.options" :key="optValue" :value="optValue">
            {{ optLabel }}
          </option>
        </select>

        <!-- String list field -->
        <div v-else-if="field.type === 'string_list'">
          <div v-if="(formValues[field.key] || []).length" class="space-y-1.5">
            <div
              v-for="(item, index) in formValues[field.key]"
              :key="item"
              class="flex items-center gap-2 px-3 py-1.5 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg"
            >
              <Icon :name="field.item_icon || 'ph:dot'" class="w-3.5 h-3.5 text-neutral-400" />
              <span class="font-mono text-sm text-neutral-900 dark:text-white flex-1">{{ item }}</span>
              <button
                type="button"
                class="text-neutral-400 hover:text-red-500 transition-colors"
                @click="removeListItem(field.key, index)"
              >
                <Icon name="ph:x" class="w-3.5 h-3.5" />
              </button>
            </div>
          </div>
          <div class="flex gap-2 mt-1.5">
            <input
              v-model="listInputs[field.key]"
              type="text"
              :placeholder="field.item_placeholder || 'Add item...'"
              class="flex-1 px-4 py-2 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white placeholder:text-neutral-400 focus:border-neutral-900 dark:focus:border-white focus:ring-1 focus:ring-neutral-900 dark:focus:ring-white outline-none transition-colors text-sm font-mono"
              @keydown.enter.prevent="addListItem(field.key)"
            />
            <Button
              type="button"
              variant="secondary"
              size="sm"
              :disabled="!(listInputs[field.key] || '').trim()"
              @click="addListItem(field.key)"
            >
              Add
            </Button>
          </div>
        </div>

        <!-- OAuth connect field -->
        <div v-else-if="field.type === 'oauth_connect'">
          <!-- Connected state -->
          <div v-if="formValues[field.key]" class="flex items-center justify-between p-3 bg-green-50 dark:bg-green-900/20 rounded-lg">
            <div class="flex items-center gap-2">
              <Icon name="ph:check-circle-fill" class="w-4 h-4 text-green-500" />
              <span class="text-sm font-medium text-green-700 dark:text-green-400">Connected</span>
            </div>
            <Button type="button" variant="ghost" size="sm" @click="disconnectOAuth(field)">
              Disconnect
            </Button>
          </div>
          <!-- Not connected state -->
          <div v-else class="space-y-2">
            <Button
              type="button"
              variant="secondary"
              icon-left="ph:plugs-connected"
              :disabled="!hasRequiredFields"
              @click="connectOAuth(field)"
            >
              Connect with {{ meta?.name || 'service' }}
            </Button>
            <div v-if="field.redirect_uri" class="p-3 bg-neutral-50 dark:bg-neutral-800 rounded-lg space-y-1.5">
              <p class="text-xs font-medium text-neutral-700 dark:text-neutral-300">
                In your
                <a v-if="meta?.docs_url" :href="meta.docs_url" target="_blank" rel="noopener" class="underline">{{ meta?.name }} developer settings</a>
                <span v-else>{{ meta?.name }} developer settings</span>, set:
              </p>
              <div class="space-y-1">
                <p class="text-xs text-neutral-500 dark:text-neutral-400">
                  <strong>OAuth redirect URL:</strong>
                  <code class="ml-1 px-1 py-0.5 bg-neutral-100 dark:bg-neutral-700 rounded text-[11px]">{{ fullRedirectUri(field) }}</code>
                </p>
                <p class="text-xs text-neutral-500 dark:text-neutral-400">
                  <strong>App Service URL:</strong>
                  <code class="ml-1 px-1 py-0.5 bg-neutral-100 dark:bg-neutral-700 rounded text-[11px]">{{ origin }}</code>
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Hint text -->
        <p v-if="field.hint" class="text-xs text-neutral-500 dark:text-neutral-400" v-html="field.hint" />
      </div>

      <!-- Action Buttons -->
      <div class="flex gap-3 pt-2">
        <Button
          type="button"
          variant="secondary"
          :loading="isTesting"
          :disabled="!hasRequiredFields"
          icon-left="ph:plugs-connected"
          @click="testConnection"
        >
          Test Connection
        </Button>
        <a
          v-if="meta?.docs_url"
          :href="meta.docs_url"
          target="_blank"
          rel="noopener"
          class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-300 transition-colors"
        >
          <Icon name="ph:arrow-square-out" class="w-3.5 h-3.5" />
          Docs
        </a>
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
            Allow agents to use {{ meta?.name || 'this integration' }}
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
          :disabled="!hasRequiredFields"
          @click="handleSave"
        >
          Save Configuration
        </Button>
      </div>
    </template>
  </Modal>
</template>

<script setup lang="ts">
import { ref, reactive, watch, computed } from 'vue'
import Modal from '@/Components/shared/Modal.vue'
import Button from '@/Components/shared/Button.vue'
import Icon from '@/Components/shared/Icon.vue'

interface ConfigField {
  key: string
  type: 'secret' | 'url' | 'text' | 'select' | 'string_list' | 'oauth_connect'
  label: string
  placeholder?: string
  hint?: string
  required?: boolean
  default?: any
  options?: Record<string, string>
  item_icon?: string
  item_placeholder?: string
  authorize_url?: string
  redirect_uri?: string
  visible_when?: {
    field: string
    value: string | string[]
  }
}

interface IntegrationMeta {
  name: string
  description: string
  icon: string
  logo?: string
  category?: string
  badge?: string
  docs_url?: string
}

const props = defineProps<{
  integrationId: string
  schema: ConfigField[]
  meta: IntegrationMeta
}>()

const isOpen = defineModel<boolean>('open', { default: false })

const emit = defineEmits<{
  saved: [{ enabled: boolean; configured: boolean }]
}>()

// Form state
const formValues = reactive<Record<string, any>>({})
const listInputs = reactive<Record<string, string>>({})
const showSecrets = reactive<Record<string, boolean>>({})
const enabled = ref(false)

// UI state
const isTesting = ref(false)
const isSaving = ref(false)
const resultMessage = ref<{ success: boolean; message: string } | null>(null)

// OAuth helpers
const origin = window.location.origin

const fullRedirectUri = (field: ConfigField): string => {
  return origin + (field.redirect_uri || '')
}

const connectOAuth = async (field: ConfigField) => {
  isSaving.value = true
  try {
    const payload: Record<string, any> = { enabled: enabled.value }
    for (const f of props.schema) {
      if (f.type === 'oauth_connect') continue
      payload[f.key] = formValues[f.key]
    }
    const response = await fetch(`/api/integrations/${props.integrationId}/config`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    })
    if (response.ok) {
      window.location.href = field.authorize_url || ''
    } else {
      const error = await response.json()
      resultMessage.value = { success: false, message: error.error || 'Failed to save before connecting' }
    }
  } catch (error) {
    resultMessage.value = { success: false, message: 'Failed to save configuration.' }
  } finally {
    isSaving.value = false
  }
}

const disconnectOAuth = async (field: ConfigField) => {
  try {
    const response = await fetch(`/api/integrations/${props.integrationId}/disconnect`, { method: 'POST' })
    if (response.ok) {
      formValues[field.key] = ''
      enabled.value = false
    }
  } catch (error) {
    console.error('Failed to disconnect:', error)
  }
}

// Check if a field should be visible based on its visible_when condition
const isFieldVisible = (field: ConfigField): boolean => {
  if (!field.visible_when) return true
  const depValue = formValues[field.visible_when.field]
  if (Array.isArray(field.visible_when.value)) {
    return field.visible_when.value.includes(depValue)
  }
  return depValue === field.visible_when.value
}

// Check if required fields are filled (only visible ones)
const hasRequiredFields = computed(() => {
  for (const field of props.schema) {
    if (field.required && isFieldVisible(field) && !formValues[field.key]) {
      return false
    }
  }
  return true
})

// Initialize form values from schema defaults
const initForm = () => {
  for (const field of props.schema) {
    if (!(field.key in formValues)) {
      formValues[field.key] = field.default ?? (field.type === 'string_list' ? [] : '')
    }
    if (field.type === 'secret') {
      showSecrets[field.key] = false
    }
    if (field.type === 'string_list') {
      listInputs[field.key] = ''
    }
  }
}

// Load config when modal opens
watch(isOpen, async (open) => {
  if (open) {
    resultMessage.value = null
    initForm()
    await loadConfig()
  }
}, { immediate: true })

const loadConfig = async () => {
  try {
    const response = await fetch(`/api/integrations/${props.integrationId}/config`)
    if (response.ok) {
      const data = await response.json()
      for (const field of props.schema) {
        if (data.config?.[field.key] !== undefined) {
          formValues[field.key] = data.config[field.key]
        }
      }
      enabled.value = data.enabled || false
    }
  } catch (error) {
    console.error('Failed to load config:', error)
  }
}

// String list helpers
const addListItem = (key: string) => {
  const value = (listInputs[key] || '').trim().toLowerCase()
  if (value && !formValues[key]?.includes(value)) {
    if (!formValues[key]) {
      formValues[key] = []
    }
    formValues[key].push(value)
    listInputs[key] = ''
  }
}

const removeListItem = (key: string, index: number) => {
  formValues[key]?.splice(index, 1)
}

const testConnection = async () => {
  isTesting.value = true
  resultMessage.value = null

  try {
    const payload: Record<string, any> = {}
    for (const field of props.schema) {
      if (field.type === 'oauth_connect') continue
      payload[field.key] = formValues[field.key]
    }

    const response = await fetch(`/api/integrations/${props.integrationId}/test`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    })

    const data = await response.json()
    resultMessage.value = {
      success: data.success,
      message: data.success ? data.message : data.error || 'Connection failed',
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
    const payload: Record<string, any> = { enabled: enabled.value }
    for (const field of props.schema) {
      if (field.type === 'oauth_connect') continue
      payload[field.key] = formValues[field.key]
    }

    const response = await fetch(`/api/integrations/${props.integrationId}/config`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
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
