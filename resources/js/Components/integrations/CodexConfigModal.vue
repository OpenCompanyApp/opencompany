<template>
  <Modal
    v-model:open="isOpen"
    title="Configure OpenAI Codex"
    description="Use your ChatGPT Pro/Plus subscription at $0 token cost"
    icon="ph:open-ai-logo"
    size="md"
  >
    <div class="space-y-5">
      <!-- Authenticated State -->
      <template v-if="authStatus.authenticated && !authStatus.is_expired">
        <!-- Status -->
        <div class="p-4 bg-green-50 dark:bg-green-900/20 rounded-lg space-y-2">
          <div class="flex items-center gap-2 text-green-700 dark:text-green-400">
            <Icon name="ph:check-circle-fill" class="w-4 h-4" />
            <span class="text-sm font-medium">Connected</span>
          </div>
          <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs">
            <span class="text-neutral-500 dark:text-neutral-400">Email</span>
            <span class="text-neutral-900 dark:text-white font-mono">{{ authStatus.email || 'N/A' }}</span>
            <span class="text-neutral-500 dark:text-neutral-400">Account</span>
            <span class="text-neutral-900 dark:text-white font-mono truncate" :title="authStatus.account_id">{{ truncateId(authStatus.account_id) }}</span>
            <span class="text-neutral-500 dark:text-neutral-400">Token Expires</span>
            <span class="text-neutral-900 dark:text-white">{{ formatDate(authStatus.expires_at) }}</span>
          </div>
        </div>

        <!-- Default Model -->
        <div class="space-y-2">
          <label class="block text-sm font-medium text-neutral-900 dark:text-white">
            Default Model
          </label>
          <select
            v-model="selectedModel"
            class="w-full px-4 py-2.5 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white focus:border-neutral-900 dark:focus:border-white focus:ring-1 focus:ring-neutral-900 dark:focus:ring-white outline-none transition-colors text-sm"
          >
            <option v-for="(name, id) in authStatus.models" :key="id" :value="id">
              {{ name }}
            </option>
          </select>
        </div>

        <!-- Test Connection -->
        <div>
          <Button
            type="button"
            variant="secondary"
            :loading="isTesting"
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

        <!-- Disconnect -->
        <div class="flex items-center justify-between p-4 bg-red-50 dark:bg-red-900/10 rounded-lg">
          <div>
            <p class="font-medium text-neutral-900 dark:text-white text-sm">Disconnect</p>
            <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
              Remove stored OAuth tokens
            </p>
          </div>
          <Button
            type="button"
            variant="danger"
            size="sm"
            :loading="isDisconnecting"
            @click="disconnect"
          >
            Disconnect
          </Button>
        </div>
      </template>

      <!-- Not Authenticated State -->
      <template v-else>
        <div class="p-4 bg-neutral-50 dark:bg-neutral-800 rounded-lg">
          <p class="text-sm text-neutral-600 dark:text-neutral-300">
            Connect your ChatGPT Pro or Plus subscription to use OpenAI Codex models at no additional token cost. Click below to get a login code, then enter it at OpenAI to connect your account.
          </p>
        </div>

        <!-- Device Code Auth -->
        <div class="space-y-3">
          <template v-if="!deviceFlow.active">
            <Button
              type="button"
              variant="primary"
              :loading="isStartingDevice"
              icon-left="ph:sign-in"
              @click="startDeviceFlow"
            >
              Connect Account
            </Button>
          </template>

          <template v-else>
            <!-- Device Code Display -->
            <div class="p-4 bg-neutral-100 dark:bg-neutral-800 rounded-lg text-center space-y-3">
              <p class="text-xs text-neutral-500 dark:text-neutral-400">Enter this code at:</p>
              <a
                :href="deviceFlow.verificationUrl"
                target="_blank"
                class="text-sm font-medium text-blue-600 dark:text-blue-400 underline hover:text-blue-700 dark:hover:text-blue-300"
              >
                {{ deviceFlow.verificationUrl }}
              </a>
              <div class="py-2 flex items-center justify-center gap-2">
                <code class="text-2xl font-mono font-bold tracking-widest text-neutral-900 dark:text-white select-all">
                  {{ deviceFlow.userCode }}
                </code>
                <button
                  type="button"
                  class="p-1.5 rounded-md text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 hover:bg-neutral-200 dark:hover:bg-neutral-700 transition-colors"
                  @click="copyCode"
                >
                  <Icon :name="copied ? 'ph:check' : 'ph:copy'" class="w-4 h-4" />
                </button>
              </div>
              <div class="flex items-center justify-center gap-2 text-xs text-neutral-500 dark:text-neutral-400">
                <Icon v-if="deviceFlow.polling" name="ph:spinner" class="w-3.5 h-3.5 animate-spin" />
                <span>{{ deviceFlow.polling ? 'Waiting for authorization...' : 'Enter the code above' }}</span>
              </div>
            </div>

            <Button
              type="button"
              variant="ghost"
              size="sm"
              @click="cancelDeviceFlow"
            >
              Cancel
            </Button>
          </template>
        </div>

        <!-- Error Display -->
        <div v-if="errorMessage" class="p-3 rounded-lg text-sm flex items-start gap-2 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400">
          <Icon name="ph:x-circle" class="w-4 h-4 mt-0.5 shrink-0" />
          <span>{{ errorMessage }}</span>
        </div>
      </template>
    </div>

    <template #footer>
      <div class="flex items-center justify-end">
        <Button variant="ghost" @click="isOpen = false">
          Close
        </Button>
      </div>
    </template>
  </Modal>
</template>

<script setup lang="ts">
import { ref, reactive, watch } from 'vue'
import axios from 'axios'
import Modal from '@/Components/shared/Modal.vue'
import Button from '@/Components/shared/Button.vue'
import Icon from '@/Components/shared/Icon.vue'

const isOpen = defineModel<boolean>('open', { default: false })

const emit = defineEmits<{
  saved: [{ enabled: boolean; configured: boolean }]
}>()

// Auth status from backend
const authStatus = reactive<{
  authenticated: boolean
  email: string | null
  account_id: string | null
  expires_at: string | null
  is_expired: boolean
  models: Record<string, string>
}>({
  authenticated: false,
  email: null,
  account_id: null,
  expires_at: null,
  is_expired: true,
  models: {},
})

const selectedModel = ref('gpt-5.3-codex')

// UI state
const copied = ref(false)
const isStartingDevice = ref(false)
const isDisconnecting = ref(false)
const isTesting = ref(false)
const errorMessage = ref('')
const testResult = ref<{ success: boolean; message: string } | null>(null)

// Device flow state
const deviceFlow = reactive({
  active: false,
  userCode: '',
  verificationUrl: '',
  deviceAuthId: '',
  polling: false,
  pollInterval: 8,
  pollTimer: null as ReturnType<typeof setInterval> | null,
})

// Load status when modal opens
watch(isOpen, async (open) => {
  if (open) {
    errorMessage.value = ''
    testResult.value = null
    await loadStatus()
  } else {
    cancelDeviceFlow()
  }
})

const loadStatus = async () => {
  try {
    const { data } = await axios.get('/api/integrations/codex/auth/status')
    Object.assign(authStatus, data)
  } catch (error) {
    console.error('Failed to load Codex status:', error)
  }
}

// Device Code Auth
const startDeviceFlow = async () => {
  isStartingDevice.value = true
  errorMessage.value = ''

  try {
    const { data } = await axios.post('/api/integrations/codex/auth/device')
    deviceFlow.active = true
    deviceFlow.userCode = data.user_code
    deviceFlow.verificationUrl = data.verification_url
    deviceFlow.deviceAuthId = data.device_auth_id
    deviceFlow.pollInterval = data.interval || 8

    // Start polling
    startDevicePolling()
  } catch (error: any) {
    errorMessage.value = error.response?.data?.error || 'Failed to start device authorization'
  } finally {
    isStartingDevice.value = false
  }
}

const startDevicePolling = () => {
  deviceFlow.polling = true
  deviceFlow.pollTimer = setInterval(async () => {
    try {
      const { data } = await axios.post('/api/integrations/codex/auth/device/poll', {
        device_auth_id: deviceFlow.deviceAuthId,
        user_code: deviceFlow.userCode,
      })

      if (data.status === 'complete') {
        cancelDeviceFlow()
        await loadStatus()
        emit('saved', { enabled: true, configured: true })
      }
      // 'pending' — keep polling
    } catch (error: any) {
      if (error.response?.data?.status === 'error') {
        cancelDeviceFlow()
        errorMessage.value = error.response.data.error || 'Device authorization failed'
      }
    }
  }, deviceFlow.pollInterval * 1000)
}

const cancelDeviceFlow = () => {
  if (deviceFlow.pollTimer) {
    clearInterval(deviceFlow.pollTimer)
    deviceFlow.pollTimer = null
  }
  deviceFlow.active = false
  deviceFlow.polling = false
  deviceFlow.userCode = ''
  deviceFlow.verificationUrl = ''
  deviceFlow.deviceAuthId = ''
}

// Test Connection
const testConnection = async () => {
  isTesting.value = true
  testResult.value = null

  try {
    const { data } = await axios.post('/api/integrations/codex/test')
    testResult.value = {
      success: data.success,
      message: data.success
        ? `Connection successful! Model: ${data.model}`
        : (data.error || 'Connection failed'),
    }
  } catch (error: any) {
    testResult.value = {
      success: false,
      message: error.response?.data?.error || 'Failed to test connection',
    }
  } finally {
    isTesting.value = false
  }
}

// Disconnect
const disconnect = async () => {
  isDisconnecting.value = true

  try {
    await axios.post('/api/integrations/codex/auth/logout')
    await loadStatus()
    emit('saved', { enabled: false, configured: false })
  } catch (error: any) {
    errorMessage.value = error.response?.data?.error || 'Failed to disconnect'
  } finally {
    isDisconnecting.value = false
  }
}

const copyCode = async () => {
  await navigator.clipboard.writeText(deviceFlow.userCode)
  copied.value = true
  setTimeout(() => { copied.value = false }, 2000)
}

// Helpers
const truncateId = (id: string | null) => {
  if (!id) return 'N/A'
  if (id.length <= 16) return id
  return id.slice(0, 8) + '...' + id.slice(-4)
}

const formatDate = (dateStr: string | null) => {
  if (!dateStr) return 'N/A'
  try {
    return new Date(dateStr).toLocaleString()
  } catch {
    return dateStr
  }
}
</script>
