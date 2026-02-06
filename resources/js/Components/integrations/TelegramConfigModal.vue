<template>
  <Modal
    v-model:open="isOpen"
    title="Configure Telegram Bot"
    description="Set up your Telegram bot for DMs, notifications, and approvals"
    icon="ph:telegram-logo"
    size="md"
  >
    <form class="space-y-5" @submit.prevent="handleSave">
      <!-- Bot Token -->
      <div class="space-y-2">
        <label class="block text-sm font-medium text-neutral-900 dark:text-white">
          Bot Token
        </label>
        <div class="relative">
          <input
            v-model="botToken"
            :type="showToken ? 'text' : 'password'"
            placeholder="Enter your Telegram bot token from @BotFather"
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
        <p class="text-xs text-neutral-500 dark:text-neutral-400">
          Create a bot with <a href="https://t.me/BotFather" target="_blank" class="underline hover:text-neutral-700 dark:hover:text-neutral-300">@BotFather</a> on Telegram to get your token
        </p>
      </div>

      <!-- Default Agent -->
      <div class="space-y-2">
        <label class="block text-sm font-medium text-neutral-900 dark:text-white">
          Default Agent
        </label>
        <select
          v-model="defaultAgentId"
          class="w-full px-4 py-2.5 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white focus:border-neutral-900 dark:focus:border-white focus:ring-1 focus:ring-neutral-900 dark:focus:ring-white outline-none transition-colors text-sm"
        >
          <option value="">Select an agent...</option>
          <option v-for="agent in agents" :key="agent.id" :value="agent.id">
            {{ agent.name }}
          </option>
        </select>
        <p class="text-xs text-neutral-500 dark:text-neutral-400">
          This agent will respond to Telegram DMs
        </p>
      </div>

      <!-- Allowed Telegram Users -->
      <div class="space-y-2">
        <label class="block text-sm font-medium text-neutral-900 dark:text-white">
          Allowed Telegram Users
        </label>
        <div v-if="allowedUsers.length" class="space-y-1.5">
          <div
            v-for="(user, index) in allowedUsers"
            :key="user"
            class="flex items-center gap-2 px-3 py-1.5 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg"
          >
            <Icon name="ph:user" class="w-3.5 h-3.5 text-neutral-400" />
            <span class="font-mono text-sm text-neutral-900 dark:text-white flex-1">{{ user }}</span>
            <button
              type="button"
              class="text-neutral-400 hover:text-red-500 transition-colors"
              @click="allowedUsers.splice(index, 1)"
            >
              <Icon name="ph:x" class="w-3.5 h-3.5" />
            </button>
          </div>
        </div>
        <div class="flex gap-2">
          <input
            v-model="newUserId"
            type="text"
            placeholder="Telegram User ID"
            class="flex-1 px-4 py-2 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white placeholder:text-neutral-400 focus:border-neutral-900 dark:focus:border-white focus:ring-1 focus:ring-neutral-900 dark:focus:ring-white outline-none transition-colors text-sm font-mono"
            @keydown.enter.prevent="addUser"
          />
          <Button
            type="button"
            variant="secondary"
            size="sm"
            :disabled="!newUserId.trim()"
            @click="addUser"
          >
            Add
          </Button>
        </div>
        <p class="text-xs text-neutral-500 dark:text-neutral-400">
          Leave empty to allow all users. Send <code class="px-1 py-0.5 bg-neutral-100 dark:bg-neutral-700 rounded">/start</code> to the bot to find your user ID.
        </p>
      </div>

      <!-- Notifications Chat ID -->
      <div class="space-y-2">
        <label class="block text-sm font-medium text-neutral-900 dark:text-white">
          Notifications Chat ID
        </label>
        <input
          v-model="notifyChatId"
          type="text"
          placeholder="e.g., 123456789"
          class="w-full px-4 py-2.5 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white placeholder:text-neutral-400 focus:border-neutral-900 dark:focus:border-white focus:ring-1 focus:ring-neutral-900 dark:focus:ring-white outline-none transition-colors text-sm"
        />
        <p class="text-xs text-neutral-500 dark:text-neutral-400">
          Your Telegram chat ID for receiving approval requests. Send any message to the bot first, then check the logs or use /start.
        </p>
      </div>

      <!-- Action Buttons -->
      <div class="flex gap-3 pt-2">
        <Button
          type="button"
          variant="secondary"
          :loading="isTesting"
          :disabled="!botToken"
          icon-left="ph:plugs-connected"
          @click="testConnection"
        >
          Test Connection
        </Button>
        <Button
          type="button"
          variant="secondary"
          :loading="isSettingWebhook"
          :disabled="!botToken"
          icon-left="ph:webhooks-logo"
          @click="setupWebhook"
        >
          Set Webhook
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
            Allow agents to send and receive Telegram messages
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
          :disabled="!botToken"
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
const botToken = ref('')
const defaultAgentId = ref('')
const notifyChatId = ref('')
const allowedUsers = ref<string[]>([])
const newUserId = ref('')
const enabled = ref(false)
const showToken = ref(false)

// Agents list
const agents = ref<{ id: string; name: string }[]>([])

// UI state
const isTesting = ref(false)
const isSettingWebhook = ref(false)
const isSaving = ref(false)
const resultMessage = ref<{ success: boolean; message: string } | null>(null)

// Load config when modal opens
watch(isOpen, async (open) => {
  if (open) {
    resultMessage.value = null
    await Promise.all([loadConfig(), loadAgents()])
  }
}, { immediate: true })

const addUser = () => {
  const id = newUserId.value.trim()
  if (id && !allowedUsers.value.includes(id)) {
    allowedUsers.value.push(id)
    newUserId.value = ''
  }
}

const loadConfig = async () => {
  try {
    const response = await fetch('/api/integrations/telegram/config')
    if (response.ok) {
      const data = await response.json()
      botToken.value = data.config?.apiKey || ''
      defaultAgentId.value = data.config?.defaultAgentId || ''
      notifyChatId.value = data.config?.notifyChatId || ''
      allowedUsers.value = data.config?.allowedTelegramUsers || []
      enabled.value = data.enabled || false
    }
  } catch (error) {
    console.error('Failed to load Telegram config:', error)
  }
}

const loadAgents = async () => {
  try {
    const response = await fetch('/api/users/agents')
    if (response.ok) {
      agents.value = await response.json()
    }
  } catch (error) {
    console.error('Failed to load agents:', error)
  }
}

const testConnection = async () => {
  isTesting.value = true
  resultMessage.value = null

  try {
    const response = await fetch('/api/integrations/telegram/test', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ apiKey: botToken.value }),
    })

    const data = await response.json()
    resultMessage.value = {
      success: data.success,
      message: data.success
        ? `Connected to @${data.username} (${data.botName})`
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

const setupWebhook = async () => {
  isSettingWebhook.value = true
  resultMessage.value = null

  try {
    const response = await fetch('/api/integrations/telegram/setup-webhook', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ apiKey: botToken.value }),
    })

    const data = await response.json()
    resultMessage.value = {
      success: data.success,
      message: data.success
        ? `Webhook set to ${data.webhookUrl}`
        : data.error || 'Failed to set webhook',
    }
  } catch (error) {
    resultMessage.value = {
      success: false,
      message: 'Failed to set webhook. Please try again.',
    }
  } finally {
    isSettingWebhook.value = false
  }
}

const handleSave = async () => {
  isSaving.value = true

  try {
    const response = await fetch('/api/integrations/telegram/config', {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        apiKey: botToken.value,
        defaultAgentId: defaultAgentId.value,
        notifyChatId: notifyChatId.value,
        allowedTelegramUsers: allowedUsers.value,
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
