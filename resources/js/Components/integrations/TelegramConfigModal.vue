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
        <!-- Bot info line -->
        <p v-if="botUsername" class="text-xs text-green-600 dark:text-green-400 flex items-center gap-1">
          <Icon name="ph:check-circle" class="w-3.5 h-3.5" />
          Connected as @{{ botUsername }}
        </p>
        <p v-else class="text-xs text-neutral-500 dark:text-neutral-400">
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

      <!-- User Mappings (always visible if there are external identities or allowed users) -->
      <div v-if="mappableUserIds.length" class="space-y-2">
        <label class="block text-sm font-medium text-neutral-900 dark:text-white">
          User Mappings
        </label>
        <p class="text-xs text-neutral-500 dark:text-neutral-400">
          Link Telegram users to their system accounts so messages appear from the correct person.
        </p>
        <div class="space-y-1.5">
          <div
            v-for="telegramId in mappableUserIds"
            :key="'map-' + telegramId"
            class="flex items-center gap-2 px-3 py-2 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg"
          >
            <Icon name="ph:telegram-logo" class="w-3.5 h-3.5 text-neutral-400 shrink-0" />
            <span class="font-mono text-sm text-neutral-500 dark:text-neutral-400 shrink-0">{{ telegramId }}</span>
            <Icon name="ph:arrow-right" class="w-3.5 h-3.5 text-neutral-300 dark:text-neutral-600 shrink-0" />
            <select
              :value="userMappings[telegramId] || ''"
              class="flex-1 px-2 py-1 bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 rounded text-sm text-neutral-900 dark:text-white focus:border-neutral-900 dark:focus:border-white outline-none transition-colors"
              @change="linkUser(telegramId, ($event.target as HTMLSelectElement).value)"
            >
              <option value="">Unlinked (shadow user)</option>
              <option v-for="u in humanUsers" :key="u.id" :value="u.id">
                {{ u.name }}
              </option>
            </select>
          </div>
        </div>
      </div>

      <!-- Approvals Chat ID -->
      <div class="space-y-2">
        <label class="block text-sm font-medium text-neutral-900 dark:text-white">
          Approvals Chat ID
        </label>
        <input
          v-model="notifyChatId"
          type="text"
          placeholder="e.g., 123456789"
          class="w-full px-4 py-2.5 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white placeholder:text-neutral-400 focus:border-neutral-900 dark:focus:border-white focus:ring-1 focus:ring-neutral-900 dark:focus:ring-white outline-none transition-colors text-sm"
        />
        <p class="text-xs text-neutral-500 dark:text-neutral-400">
          Telegram chat where approval requests (budget, tool access) are sent. Use a group chat ID for team approvals.
        </p>
      </div>

      <!-- Action Buttons -->
      <div class="flex items-center gap-3 pt-2">
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
        <!-- Webhook status badge -->
        <span
          v-if="webhookActive"
          class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-full bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400"
        >
          <span class="w-1.5 h-1.5 rounded-full bg-green-500" />
          Webhook active
        </span>
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

      <!-- Connected Channels -->
      <div class="space-y-2">
        <label class="block text-sm font-medium text-neutral-900 dark:text-white">
          Connected Channels
        </label>
        <div v-if="telegramChannels.length > 0" class="space-y-1.5">
          <div
            v-for="channel in telegramChannels"
            :key="channel.id"
            class="flex items-center gap-3 px-3 py-2.5 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg"
          >
            <div class="w-8 h-8 rounded-full bg-violet-100 dark:bg-violet-900/30 flex items-center justify-center shrink-0">
              <Icon name="ph:telegram-logo" class="w-4 h-4 text-violet-600 dark:text-violet-400" />
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-neutral-900 dark:text-white truncate">{{ channel.name }}</p>
              <p class="text-xs text-neutral-500 dark:text-neutral-400">
                Chat ID: {{ channel.external_id }}
                <span v-if="channel.last_message_at"> · Last active {{ formatRelativeDate(channel.last_message_at) }}</span>
              </p>
            </div>
            <span
              v-if="channelLinkedUser(channel)"
              class="text-xs text-neutral-500 dark:text-neutral-400 shrink-0"
            >
              {{ channelLinkedUser(channel) }}
            </span>
            <span
              v-else
              class="text-xs text-neutral-400 dark:text-neutral-500 italic shrink-0"
            >
              Shadow user
            </span>
          </div>
        </div>
        <div v-else class="px-3 py-6 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-center">
          <Icon name="ph:chat-circle-dots" class="w-8 h-8 text-neutral-300 dark:text-neutral-600 mx-auto mb-2" />
          <p class="text-xs text-neutral-500 dark:text-neutral-400">
            No Telegram conversations yet — messages from allowed users will appear here
          </p>
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
import { ref, computed, watch } from 'vue'
import { apiFetch } from '@/utils/apiFetch'
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

// Agents & human users lists
const agents = ref<{ id: string; name: string }[]>([])
const humanUsers = ref<{ id: string; name: string }[]>([])
const userMappings = ref<Record<string, string>>({}) // telegramId -> userId

// Bot & webhook status
const botUsername = ref('')
const webhookActive = ref(false)

// Connected Telegram channels
const telegramChannels = ref<any[]>([])

// UI state
const isTesting = ref(false)
const isSettingWebhook = ref(false)
const isSaving = ref(false)
const resultMessage = ref<{ success: boolean; message: string } | null>(null)

// Combine allowed users + external identity user IDs for mapping section
const mappableUserIds = computed(() => {
  const ids = new Set<string>([...allowedUsers.value])
  // Add any external identity IDs not already in the allowed list
  for (const telegramId of Object.keys(userMappings.value)) {
    ids.add(telegramId)
  }
  return Array.from(ids)
})

// Load config when modal opens
watch(isOpen, async (open) => {
  if (open) {
    resultMessage.value = null
    await Promise.all([
      loadConfig(),
      loadAgents(),
      loadHumanUsers(),
      loadExternalIdentities(),
      loadTelegramChannels(),
    ])
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
    const response = await apiFetch('/api/integrations/telegram/config')
    if (response.ok) {
      const data = await response.json()
      botToken.value = data.config?.apiKey || ''
      defaultAgentId.value = data.config?.defaultAgentId || ''
      notifyChatId.value = data.config?.notifyChatId || ''
      allowedUsers.value = data.config?.allowedTelegramUsers || []
      enabled.value = data.enabled || false
      botUsername.value = data.config?.botUsername || ''
      webhookActive.value = data.config?.webhookActive || false
    }
  } catch (error) {
    console.error('Failed to load Telegram config:', error)
  }
}

const loadAgents = async () => {
  try {
    const response = await apiFetch('/api/users/agents')
    if (response.ok) {
      agents.value = await response.json()
    }
  } catch (error) {
    console.error('Failed to load agents:', error)
  }
}

const loadHumanUsers = async () => {
  try {
    const response = await apiFetch('/api/users')
    if (response.ok) {
      const allUsers = await response.json()
      humanUsers.value = allUsers.filter((u: any) => u.type === 'human' && !u.isEphemeral)
    }
  } catch (error) {
    console.error('Failed to load users:', error)
  }
}

const loadExternalIdentities = async () => {
  try {
    const response = await apiFetch('/api/integrations/external-identities?provider=telegram')
    if (response.ok) {
      const identities = await response.json()
      const mappings: Record<string, string> = {}
      for (const identity of identities) {
        mappings[identity.external_id] = identity.user_id
      }
      userMappings.value = mappings
    }
  } catch (error) {
    console.error('Failed to load external identities:', error)
  }
}

const loadTelegramChannels = async () => {
  try {
    const response = await apiFetch('/api/channels?type=external&provider=telegram')
    if (response.ok) {
      telegramChannels.value = await response.json()
    }
  } catch (error) {
    console.error('Failed to load Telegram channels:', error)
  }
}

const channelLinkedUser = (channel: any): string | null => {
  // Check if any member is a linked (non-ephemeral) human
  const members = channel.members || []
  const human = members.find((m: any) => m.type === 'human' && !m.is_ephemeral)
  return human?.name || null
}

const formatRelativeDate = (dateStr: string): string => {
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
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
  } catch {
    return dateStr
  }
}

const linkUser = async (telegramId: string, userId: string) => {
  if (!userId) {
    // Unlink: find the identity and delete it
    try {
      const response = await apiFetch('/api/integrations/external-identities?provider=telegram')
      if (response.ok) {
        const identities = await response.json()
        const identity = identities.find((i: any) => i.external_id === telegramId)
        if (identity) {
          await apiFetch(`/api/integrations/link-user/${identity.id}`, { method: 'DELETE' })
        }
      }
      const newMappings = { ...userMappings.value }
      delete newMappings[telegramId]
      userMappings.value = newMappings
    } catch (error) {
      console.error('Failed to unlink user:', error)
    }
    return
  }

  try {
    const response = await apiFetch('/api/integrations/link-user', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        userId,
        provider: 'telegram',
        externalId: telegramId,
      }),
    })

    if (response.ok) {
      userMappings.value = { ...userMappings.value, [telegramId]: userId }
    } else {
      const data = await response.json()
      resultMessage.value = { success: false, message: data.error || 'Failed to link user' }
    }
  } catch (error) {
    console.error('Failed to link user:', error)
  }
}

const testConnection = async () => {
  isTesting.value = true
  resultMessage.value = null

  try {
    const response = await apiFetch('/api/integrations/telegram/test', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ apiKey: botToken.value }),
    })

    const data = await response.json()
    if (data.success) {
      botUsername.value = data.username || ''
    }
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
    const response = await apiFetch('/api/integrations/telegram/setup-webhook', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ apiKey: botToken.value }),
    })

    const data = await response.json()
    if (data.success) {
      webhookActive.value = true
    }
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
    const response = await apiFetch('/api/integrations/telegram/config', {
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
