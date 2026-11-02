<template>
  <Modal
    v-model:open="isOpen"
    :title="'Configure ' + (meta?.name || integrationId)"
    :description="meta?.description || ''"
    :icon="meta?.icon || 'ph:gear'"
    size="md"
  >
    <form class="space-y-5" data-test="dynamic-config-form" @submit.prevent="handleSave">
      <div class="space-y-2">
        <div class="flex items-center justify-between gap-3">
          <div>
            <label class="block text-sm font-medium text-neutral-900 dark:text-white">
              Integration Account
            </label>
            <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
              Add named accounts when one integration needs more than one inbox, API key, or external workspace.
            </p>
          </div>
          <span
            v-if="selectedAccountInfo?.is_default"
            class="text-[11px] px-1.5 py-0.5 rounded bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400"
          >
            Default
          </span>
        </div>
        <div class="flex gap-2">
          <select
            v-model="selectedAccount"
            data-test="integration-account-select"
            class="flex-1 min-w-0 px-3 py-2 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white focus:border-neutral-900 dark:focus:border-white focus:ring-1 focus:ring-neutral-900 dark:focus:ring-white outline-none transition-colors text-sm"
            @change="loadConfig"
          >
            <option value="">Default account</option>
            <option v-for="account in namedAccounts" :key="account.alias" :value="account.alias">
              {{ account.alias }}{{ account.is_default ? ' (default)' : '' }}
            </option>
          </select>
          <Button
            v-if="selectedAccount"
            type="button"
            variant="secondary"
            size="sm"
            @click="setDefaultAccount"
          >
            Make Default
          </Button>
        </div>
        <div class="flex gap-2">
          <input
            v-model="newAccountAlias"
            data-test="integration-new-account"
            type="text"
            placeholder="Account name"
            class="flex-1 min-w-0 px-3 py-2 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white placeholder:text-neutral-400 focus:border-neutral-900 dark:focus:border-white focus:ring-1 focus:ring-neutral-900 dark:focus:ring-white outline-none transition-colors text-sm"
            @keydown.enter.prevent="createAccount"
          />
          <Button
            type="button"
            variant="secondary"
            size="sm"
            :disabled="!newAccountAlias.trim()"
            @click="createAccount"
          >
            Add
          </Button>
        </div>
        <p v-if="!newAccountAlias.trim()" class="text-[11px] text-neutral-400 dark:text-neutral-500">
          Enter a short label to add another account; the existing default account remains unchanged.
        </p>
      </div>

      <!-- Static config fields (config/integrations.php, config/chat_integrations.php) -->
      <div v-for="field in schema" :key="field.key" class="space-y-2">
        <label class="block text-sm font-medium text-neutral-900 dark:text-white">
          {{ field.label }}
        </label>

        <!-- Secret field -->
        <div v-if="field.type === 'secret'" class="relative">
          <input
            v-model="formValues[field.key]"
            :data-test="`integration-field-${field.key}`"
            :name="field.key"
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
          :data-test="`integration-field-${field.key}`"
          :name="field.key"
          type="text"
          :placeholder="field.placeholder || ''"
          class="w-full px-4 py-2.5 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white placeholder:text-neutral-400 focus:border-neutral-900 dark:focus:border-white focus:ring-1 focus:ring-neutral-900 dark:focus:ring-white outline-none transition-colors text-sm"
        />

        <!-- Text field -->
        <input
          v-else-if="field.type === 'text'"
          v-model="formValues[field.key]"
          :data-test="`integration-field-${field.key}`"
          :name="field.key"
          type="text"
          :placeholder="field.placeholder || ''"
          class="w-full px-4 py-2.5 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white placeholder:text-neutral-400 focus:border-neutral-900 dark:focus:border-white focus:ring-1 focus:ring-neutral-900 dark:focus:ring-white outline-none transition-colors text-sm"
        />

        <!-- String list field -->
        <div v-else-if="field.type === 'string_list'">
          <div v-if="(formValues[field.key] || []).length" class="space-y-1.5">
            <div
              v-for="(item, index) in formValues[field.key]"
              :key="item"
              class="flex items-center gap-2 px-3 py-1.5 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg"
            >
              <Icon name="ph:dot" class="w-3.5 h-3.5 text-neutral-400" />
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
              :data-test="`integration-field-${field.key}`"
              :name="field.key"
              type="text"
              :placeholder="field.placeholder || 'Add item...'"
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

    </form>

    <template #footer>
      <div class="flex items-center justify-end gap-3">
        <Button variant="ghost" @click="isOpen = false">
          Cancel
        </Button>
        <Button
          variant="primary"
          data-test="save-integration-config"
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
/**
 * Generic config form for app-owned static integrations (chat adapters such as
 * Telegram/Slack/Discord, web providers) whose `config_fields` live in
 * config/integrations.php and config/chat_integrations.php.
 *
 * Owns: per-account config load/save/test through the IntegrationController
 * config, test and accounts endpoints. Does not own AI providers (ProviderConfigModal),
 * Codex OAuth (CodexConfigModal) or MCP servers (McpConfigModal).
 *
 * Breadcrumb: package-schema features (oauth_connect, select options,
 * visible_when, docs links) were removed with the integration package system
 * (2026-11). External OAuth accounts return as Connections
 * (docs/planning/connections-events-routines.md), not through this modal.
 */
import { ref, reactive, watch, computed } from 'vue'
import {
  createAccount as createIntegrationAccount,
  listAccounts,
  setDefaultAccount as setDefaultIntegrationAccount,
  showConfig,
  testConnection as testIntegrationConnection,
  updateConfig,
} from '@/actions/App/Http/Controllers/Api/IntegrationController'
import Modal from '@/Components/shared/Modal.vue'
import Button from '@/Components/shared/Button.vue'
import Icon from '@/Components/shared/Icon.vue'
import { wayfinderFetch } from '@/utils/wayfinder'

interface ConfigField {
  key: string
  type: 'secret' | 'url' | 'text' | 'string_list'
  label: string
  placeholder?: string
  hint?: string
  required?: boolean
}

interface IntegrationMeta {
  name: string
  description: string
  icon: string
}

interface Account {
  alias: string
  is_default: boolean
  enabled: boolean
  configured: boolean
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
const accounts = ref<Account[]>([])
const selectedAccount = ref('')
const newAccountAlias = ref('')

// UI state
const isTesting = ref(false)
const isSaving = ref(false)
const resultMessage = ref<{ success: boolean; message: string } | null>(null)

const namedAccounts = computed(() => accounts.value.filter(account => account.alias))
const selectedAccountInfo = computed(() => {
  return accounts.value.find(account => account.alias === selectedAccount.value)
    || accounts.value.find(account => account.is_default)
    || null
})

const accountRouteOptions = () => {
  return selectedAccount.value ? { query: { account: selectedAccount.value } } : undefined
}

// Check if required fields are filled
const hasRequiredFields = computed(() => {
  for (const field of props.schema) {
    if (field.required && !formValues[field.key]) {
      return false
    }
  }
  return true
})

// Initialize form values from schema defaults
const initForm = () => {
  for (const key of Object.keys(formValues)) {
    delete formValues[key]
  }
  for (const key of Object.keys(showSecrets)) {
    delete showSecrets[key]
  }
  for (const key of Object.keys(listInputs)) {
    delete listInputs[key]
  }

  for (const field of props.schema) {
    formValues[field.key] = field.type === 'string_list' ? [] : ''
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
    await loadAccounts()
    await loadConfig()
  }
}, { immediate: true })

const loadAccounts = async () => {
  try {
    const response = await wayfinderFetch(listAccounts(props.integrationId))
    if (!response.ok) return

    const data = await response.json()
    accounts.value = data.accounts || []
    const defaultAccount = accounts.value.find(account => account.is_default)
    selectedAccount.value = defaultAccount?.alias || ''
  } catch (error) {
    console.error('Failed to load accounts:', error)
  }
}

const loadConfig = async () => {
  try {
    const response = await wayfinderFetch(showConfig(props.integrationId, accountRouteOptions()))
    if (response.ok) {
      const data = await response.json()
      for (const field of props.schema) {
        if (data.config?.[field.key] !== undefined) {
          formValues[field.key] = data.config[field.key]
        }
      }
    }
  } catch (error) {
    console.error('Failed to load config:', error)
  }
}

const createAccount = async () => {
  const alias = newAccountAlias.value.trim().toLowerCase()
  if (!alias) return

  try {
    const response = await wayfinderFetch(createIntegrationAccount(props.integrationId), {
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ alias, config: {} }),
    })

    const data = await response.json()
    if (!response.ok) {
      resultMessage.value = { success: false, message: data.error || 'Failed to create account' }
      return
    }

    newAccountAlias.value = ''
    selectedAccount.value = data.alias || alias
    await loadAccounts()
    selectedAccount.value = data.alias || alias
    await loadConfig()
  } catch (error) {
    resultMessage.value = { success: false, message: 'Failed to create account.' }
  }
}

const setDefaultAccount = async () => {
  if (!selectedAccount.value) return

  try {
    const response = await wayfinderFetch(setDefaultIntegrationAccount({
      id: props.integrationId,
      alias: selectedAccount.value,
    }))

    if (response.ok) {
      await loadAccounts()
      selectedAccount.value = selectedAccountInfo.value?.alias || selectedAccount.value
    }
  } catch (error) {
    resultMessage.value = { success: false, message: 'Failed to update default account.' }
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
      payload[field.key] = formValues[field.key]
    }

    const response = await wayfinderFetch(testIntegrationConnection(props.integrationId), {
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ ...payload, account: selectedAccount.value || null }),
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
    const payload: Record<string, any> = { enabled: true }
    for (const field of props.schema) {
      payload[field.key] = formValues[field.key]
    }

    const response = await wayfinderFetch(updateConfig(props.integrationId), {
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ ...payload, account: selectedAccount.value || null }),
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
