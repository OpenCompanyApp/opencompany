<template>
  <Modal
    v-model:open="isOpen"
    :title="isEditing ? 'Configure MCP Server' : 'Add MCP Server'"
    description="Connect a remote MCP server to expose its tools to your agents"
    icon="ph:plugs-connected"
    size="lg"
  >
    <form class="space-y-5" @submit.prevent="handleSave">
      <!-- Name -->
      <div class="space-y-2">
        <label class="block text-sm font-medium text-neutral-900 dark:text-white">Name</label>
        <input
          v-model="form.name"
          type="text"
          placeholder="e.g., Brave Search"
          class="w-full px-4 py-2.5 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white placeholder:text-neutral-400 focus:border-neutral-900 dark:focus:border-white focus:ring-1 focus:ring-neutral-900 dark:focus:ring-white outline-none transition-colors text-sm"
        />
      </div>

      <!-- URL -->
      <div class="space-y-2">
        <label class="block text-sm font-medium text-neutral-900 dark:text-white">Server URL</label>
        <input
          v-model="form.url"
          type="url"
          placeholder="https://mcp.example.com/sse"
          class="w-full px-4 py-2.5 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white placeholder:text-neutral-400 focus:border-neutral-900 dark:focus:border-white focus:ring-1 focus:ring-neutral-900 dark:focus:ring-white outline-none transition-colors text-sm font-mono"
        />
        <p class="text-xs text-neutral-500 dark:text-neutral-400">
          The MCP server endpoint (Streamable HTTP transport)
        </p>
      </div>

      <!-- Auth Type -->
      <div class="space-y-2">
        <label class="block text-sm font-medium text-neutral-900 dark:text-white">Authentication</label>
        <select
          v-model="form.auth_type"
          class="w-full px-4 py-2.5 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white focus:border-neutral-900 dark:focus:border-white focus:ring-1 focus:ring-neutral-900 dark:focus:ring-white outline-none transition-colors text-sm"
        >
          <option value="none">None</option>
          <option value="bearer">Bearer Token</option>
          <option value="header">Custom Header</option>
        </select>
      </div>

      <!-- Bearer Token -->
      <div v-if="form.auth_type === 'bearer'" class="space-y-2">
        <label class="block text-sm font-medium text-neutral-900 dark:text-white">Bearer Token</label>
        <div class="relative">
          <input
            v-model="form.auth_token"
            :type="showToken ? 'text' : 'password'"
            placeholder="Enter token..."
            class="w-full px-4 py-2.5 pr-10 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white placeholder:text-neutral-400 focus:border-neutral-900 dark:focus:border-white focus:ring-1 focus:ring-neutral-900 dark:focus:ring-white outline-none transition-colors font-mono text-sm"
          />
          <button
            type="button"
            class="absolute right-3 top-1/2 -translate-y-1/2 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300"
            @click="showToken = !showToken"
          >
            <Icon :name="showToken ? 'ph:eye-slash' : 'ph:eye'" class="w-4 h-4" />
          </button>
        </div>
      </div>

      <!-- Custom Header -->
      <template v-if="form.auth_type === 'header'">
        <div class="space-y-2">
          <label class="block text-sm font-medium text-neutral-900 dark:text-white">Header Name</label>
          <input
            v-model="form.header_name"
            type="text"
            placeholder="X-API-Key"
            class="w-full px-4 py-2.5 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white placeholder:text-neutral-400 focus:border-neutral-900 dark:focus:border-white focus:ring-1 focus:ring-neutral-900 dark:focus:ring-white outline-none transition-colors text-sm font-mono"
          />
        </div>
        <div class="space-y-2">
          <label class="block text-sm font-medium text-neutral-900 dark:text-white">Header Value</label>
          <div class="relative">
            <input
              v-model="form.header_value"
              :type="showToken ? 'text' : 'password'"
              placeholder="Enter value..."
              class="w-full px-4 py-2.5 pr-10 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white placeholder:text-neutral-400 focus:border-neutral-900 dark:focus:border-white focus:ring-1 focus:ring-neutral-900 dark:focus:ring-white outline-none transition-colors font-mono text-sm"
            />
            <button
              type="button"
              class="absolute right-3 top-1/2 -translate-y-1/2 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300"
              @click="showToken = !showToken"
            >
              <Icon :name="showToken ? 'ph:eye-slash' : 'ph:eye'" class="w-4 h-4" />
            </button>
          </div>
        </div>
      </template>

      <!-- Advanced Section -->
      <details class="group">
        <summary class="flex items-center gap-2 cursor-pointer text-sm font-medium text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white transition-colors">
          <Icon name="ph:caret-right" class="w-3.5 h-3.5 transition-transform group-open:rotate-90" />
          Advanced
        </summary>
        <div class="mt-3 space-y-4 pl-5.5">
          <!-- Timeout -->
          <div class="space-y-2">
            <label class="block text-sm font-medium text-neutral-900 dark:text-white">Timeout (seconds)</label>
            <input
              v-model.number="form.timeout"
              type="number"
              min="5"
              max="300"
              class="w-32 px-4 py-2.5 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white focus:border-neutral-900 dark:focus:border-white focus:ring-1 focus:ring-neutral-900 dark:focus:ring-white outline-none transition-colors text-sm"
            />
          </div>

          <!-- Icon -->
          <div class="space-y-2">
            <label class="block text-sm font-medium text-neutral-900 dark:text-white">Icon</label>
            <div class="flex items-center gap-3">
              <input
                v-model="form.icon"
                type="text"
                placeholder="ph:plug"
                class="flex-1 px-4 py-2.5 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white placeholder:text-neutral-400 focus:border-neutral-900 dark:focus:border-white focus:ring-1 focus:ring-neutral-900 dark:focus:ring-white outline-none transition-colors text-sm font-mono"
              />
              <div class="w-10 h-10 rounded-lg bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center shrink-0">
                <Icon :name="form.icon || 'ph:plug'" class="w-5 h-5 text-purple-600 dark:text-purple-400" />
              </div>
            </div>
            <p class="text-xs text-neutral-500 dark:text-neutral-400">
              Use Phosphor icons with ph: prefix
            </p>
          </div>

          <!-- Description -->
          <div class="space-y-2">
            <label class="block text-sm font-medium text-neutral-900 dark:text-white">Description</label>
            <textarea
              v-model="form.description"
              rows="2"
              placeholder="What does this MCP server do?"
              class="w-full px-4 py-2.5 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white placeholder:text-neutral-400 focus:border-neutral-900 dark:focus:border-white focus:ring-1 focus:ring-neutral-900 dark:focus:ring-white outline-none transition-colors text-sm resize-none"
            />
          </div>
        </div>
      </details>

      <!-- Action Buttons -->
      <div class="flex gap-3 pt-2">
        <Button
          type="button"
          variant="secondary"
          :loading="isTesting"
          :disabled="!form.name || !form.url"
          icon-left="ph:plugs-connected"
          @click="testConnection"
        >
          Test Connection
        </Button>
        <Button
          v-if="isEditing && discoveredTools.length > 0"
          type="button"
          variant="secondary"
          :loading="isRefreshing"
          icon-left="ph:arrows-clockwise"
          @click="refreshTools"
        >
          Refresh Tools
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

      <!-- Discovered Tools -->
      <div v-if="discoveredTools.length > 0" class="space-y-2">
        <div class="flex items-center justify-between">
          <p class="text-sm font-medium text-neutral-900 dark:text-white">
            Discovered Tools
          </p>
          <span class="text-xs text-neutral-500 dark:text-neutral-400">
            {{ discoveredTools.length }} tool{{ discoveredTools.length === 1 ? '' : 's' }}
          </span>
        </div>
        <div class="rounded-lg border border-neutral-200 dark:border-neutral-700 divide-y divide-neutral-100 dark:divide-neutral-800 max-h-48 overflow-y-auto">
          <div
            v-for="tool in discoveredTools"
            :key="tool.name"
            class="px-3 py-2 flex items-start gap-2"
          >
            <Icon name="ph:wrench" class="w-3.5 h-3.5 text-purple-500 mt-0.5 shrink-0" />
            <div class="min-w-0">
              <p class="text-xs font-medium text-neutral-900 dark:text-white font-mono truncate">
                {{ tool.name }}
              </p>
              <p v-if="tool.description" class="text-xs text-neutral-500 dark:text-neutral-400 line-clamp-1">
                {{ tool.description }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Enable Toggle -->
      <div v-if="isEditing" class="flex items-center justify-between p-4 bg-neutral-50 dark:bg-neutral-800 rounded-lg">
        <div>
          <p class="font-medium text-neutral-900 dark:text-white">Enable Server</p>
          <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
            Allow agents to use tools from this server
          </p>
        </div>
        <button
          type="button"
          :class="[
            'relative w-11 h-6 rounded-full transition-colors',
            form.enabled ? 'bg-green-500' : 'bg-neutral-300 dark:bg-neutral-600',
          ]"
          @click="form.enabled = !form.enabled"
        >
          <span
            :class="[
              'absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition-transform shadow-sm',
              form.enabled && 'translate-x-5',
            ]"
          />
        </button>
      </div>
    </form>

    <template #footer>
      <div class="flex items-center justify-between gap-3">
        <div>
          <Button
            v-if="isEditing"
            variant="ghost"
            class="text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300"
            icon-left="ph:trash"
            :loading="isDeleting"
            @click="handleDelete"
          >
            Delete
          </Button>
        </div>
        <div class="flex items-center gap-3">
          <Button variant="ghost" @click="isOpen = false">
            Cancel
          </Button>
          <Button
            variant="primary"
            :loading="isSaving"
            :disabled="!form.name || !form.url"
            @click="handleSave"
          >
            {{ isEditing ? 'Save Changes' : 'Add Server' }}
          </Button>
        </div>
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

interface McpTool {
  name: string
  description?: string
}

const props = defineProps<{
  serverId?: string
}>()

const isOpen = defineModel<boolean>('open', { default: false })

const emit = defineEmits<{
  saved: []
  deleted: []
}>()

const isEditing = computed(() => !!props.serverId)

// Form state
const form = reactive({
  name: '',
  url: '',
  auth_type: 'none' as 'none' | 'bearer' | 'header',
  auth_token: '',
  header_name: '',
  header_value: '',
  timeout: 30,
  icon: 'ph:plug',
  description: '',
  enabled: true,
})

const showToken = ref(false)
const discoveredTools = ref<McpTool[]>([])

// UI state
const isTesting = ref(false)
const isRefreshing = ref(false)
const isSaving = ref(false)
const isDeleting = ref(false)
const resultMessage = ref<{ success: boolean; message: string } | null>(null)

const resetForm = () => {
  form.name = ''
  form.url = ''
  form.auth_type = 'none'
  form.auth_token = ''
  form.header_name = ''
  form.header_value = ''
  form.timeout = 30
  form.icon = 'ph:plug'
  form.description = ''
  form.enabled = true
  discoveredTools.value = []
  resultMessage.value = null
  showToken.value = false
}

// Load server data when editing
watch(isOpen, async (open) => {
  if (open) {
    resultMessage.value = null
    if (props.serverId) {
      await loadServer()
    } else {
      resetForm()
    }
  }
}, { immediate: true })

const loadServer = async () => {
  if (!props.serverId) return

  try {
    const { data } = await axios.get(`/api/mcp-servers/${props.serverId}`)
    form.name = data.name || ''
    form.url = data.url || ''
    form.auth_type = data.auth_type || 'none'
    form.timeout = data.timeout || 30
    form.icon = data.icon || 'ph:plug'
    form.description = data.description || ''
    form.enabled = data.enabled ?? true

    // Auth config (masked)
    if (data.auth_type === 'bearer') {
      form.auth_token = data.maskedAuth || ''
    } else if (data.auth_type === 'header') {
      form.header_name = data.auth_config?.header_name || ''
      form.header_value = data.maskedAuth || ''
    }

    // Discovered tools
    discoveredTools.value = (data.tools || data.discovered_tools || []).map((t: any) => ({
      name: t.name,
      description: t.description,
    }))
  } catch (error) {
    console.error('Failed to load MCP server:', error)
  }
}

const buildAuthConfig = () => {
  if (form.auth_type === 'bearer') {
    return { token: form.auth_token }
  } else if (form.auth_type === 'header') {
    return { header_name: form.header_name, header_value: form.header_value }
  }
  return null
}

const testConnection = async () => {
  isTesting.value = true
  resultMessage.value = null

  try {
    const endpoint = props.serverId
      ? `/api/mcp-servers/${props.serverId}/test`
      : '/api/mcp-servers/test-new'

    const payload: Record<string, any> = {
      url: form.url,
      auth_type: form.auth_type,
      auth_config: buildAuthConfig(),
      timeout: form.timeout,
    }

    // For existing servers, only send URL/auth if changed
    if (props.serverId) {
      payload.url_override = form.url
      payload.auth_type_override = form.auth_type
      payload.auth_config_override = buildAuthConfig()
    }

    const { data } = await axios.post(endpoint, payload)
    resultMessage.value = {
      success: data.success ?? true,
      message: data.success ? (data.message || 'Connection successful') : (data.error || data.message || 'Connection failed'),
    }
  } catch (error: any) {
    const errData = error.response?.data
    resultMessage.value = {
      success: false,
      message: errData?.error || errData?.message || 'Failed to test connection. Please check your network.',
    }
  } finally {
    isTesting.value = false
  }
}

const refreshTools = async () => {
  if (!props.serverId) return
  isRefreshing.value = true
  resultMessage.value = null

  try {
    const { data } = await axios.post(`/api/mcp-servers/${props.serverId}/discover`)
    discoveredTools.value = (data.tools || data.discovered_tools || []).map((t: any) => ({
      name: t.name,
      description: t.description,
    }))
    resultMessage.value = {
      success: true,
      message: `Discovered ${discoveredTools.value.length} tool${discoveredTools.value.length === 1 ? '' : 's'}`,
    }
  } catch (error: any) {
    const errData = error.response?.data
    resultMessage.value = {
      success: false,
      message: errData?.error || errData?.message || 'Failed to refresh tools. Please try again.',
    }
  } finally {
    isRefreshing.value = false
  }
}

const handleSave = async () => {
  isSaving.value = true

  try {
    const payload: Record<string, any> = {
      name: form.name,
      url: form.url,
      auth_type: form.auth_type,
      auth_config: buildAuthConfig(),
      timeout: form.timeout,
      icon: form.icon,
      description: form.description,
      enabled: form.enabled,
    }

    const url = props.serverId
      ? `/api/mcp-servers/${props.serverId}`
      : '/api/mcp-servers'

    if (props.serverId) {
      await axios.patch(url, payload)
    } else {
      await axios.post(url, payload)
    }

    emit('saved')
    isOpen.value = false
  } catch (error: any) {
    const errData = error.response?.data
    resultMessage.value = {
      success: false,
      message: errData?.error || errData?.message || 'Failed to save server. Please try again.',
    }
  } finally {
    isSaving.value = false
  }
}

const handleDelete = async () => {
  if (!props.serverId) return
  if (!confirm('Delete this MCP server? This will remove all associated permissions.')) return

  isDeleting.value = true

  try {
    await axios.delete(`/api/mcp-servers/${props.serverId}`)
    emit('deleted')
    isOpen.value = false
  } catch (error: any) {
    const errData = error.response?.data
    resultMessage.value = {
      success: false,
      message: errData?.error || errData?.message || 'Failed to delete server. Please try again.',
    }
  } finally {
    isDeleting.value = false
  }
}
</script>
