<template>
  <div class="h-full overflow-hidden flex flex-col">
    <div class="max-w-5xl mx-auto w-full p-4 md:p-6 flex flex-col flex-1 min-h-0">
      <!-- Header -->
      <header class="mb-4 md:mb-6 shrink-0">
        <h1 class="text-xl font-semibold text-neutral-900 dark:text-white">Settings</h1>
        <p class="text-sm text-neutral-500 dark:text-neutral-400 mt-1">
          Manage your organization and agent configuration
        </p>
      </header>

      <!-- Mobile Nav -->
      <div class="flex gap-1.5 overflow-x-auto pb-3 -mx-4 px-4 md:hidden shrink-0" style="-ms-overflow-style: none; scrollbar-width: none; -webkit-overflow-scrolling: touch;">
        <button
          v-for="section in sections"
          :key="'mobile-' + section.id"
          type="button"
          :class="[
            'flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium transition-colors whitespace-nowrap shrink-0',
            activeSection === section.id
              ? section.id === 'danger' ? 'bg-red-600 text-white' : 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900'
              : section.id === 'danger' ? 'bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400' : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400',
          ]"
          @click="activeSection = section.id"
        >
          <Icon :name="section.icon" class="w-3.5 h-3.5" />
          {{ section.name }}
        </button>
      </div>

      <!-- Sidebar + Content -->
      <div class="flex flex-col md:flex-row gap-4 md:gap-6 flex-1 min-h-0">
        <!-- Desktop Sidebar -->
        <nav class="hidden md:flex w-52 shrink-0 flex-col gap-1">
          <button
            v-for="section in sections.filter(s => s.id !== 'danger')"
            :key="section.id"
            type="button"
            :class="[
              'flex items-center gap-2 px-3 py-1.5 rounded-md text-xs font-medium transition-colors text-left w-full',
              activeSection === section.id
                ? 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900'
                : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800',
            ]"
            @click="activeSection = section.id"
          >
            <Icon :name="section.icon" class="w-4 h-4" />
            {{ section.name }}
          </button>

          <!-- Divider -->
          <div class="border-t border-neutral-200 dark:border-neutral-700 my-2" />

          <!-- Danger Zone -->
          <button
            type="button"
            :class="[
              'flex items-center gap-2 px-3 py-1.5 rounded-md text-xs font-medium transition-colors text-left w-full',
              activeSection === 'danger'
                ? 'bg-red-600 text-white'
                : 'text-red-500 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20',
            ]"
            @click="activeSection = 'danger'"
          >
            <Icon name="ph:warning" class="w-4 h-4" />
            Danger Zone
          </button>
        </nav>

        <!-- Main Content -->
        <main class="flex-1 min-w-0 overflow-y-auto">
          <!-- Loading -->
          <div v-if="loading" class="flex items-center justify-center py-16">
            <Icon name="ph:spinner" class="w-6 h-6 text-neutral-400 animate-spin" />
          </div>

          <template v-else>
            <!-- Organization -->
            <template v-if="activeSection === 'organization'">
              <SettingsSection title="Organization" icon="ph:buildings">
                <div class="space-y-4">
                  <SettingsField label="Organization Name">
                    <input
                      v-model="orgSettings.org_name"
                      type="text"
                      class="settings-input"
                      placeholder="Enter organization name"
                    />
                  </SettingsField>

                  <SettingsField label="Organization Email">
                    <input
                      v-model="orgSettings.org_email"
                      type="email"
                      class="settings-input"
                      placeholder="org@example.com"
                    />
                  </SettingsField>

                  <SettingsField label="Timezone">
                    <select v-model="orgSettings.org_timezone" class="settings-input">
                      <option value="UTC">UTC</option>
                      <option value="America/New_York">Eastern Time (ET)</option>
                      <option value="America/Chicago">Central Time (CT)</option>
                      <option value="America/Denver">Mountain Time (MT)</option>
                      <option value="America/Los_Angeles">Pacific Time (PT)</option>
                      <option value="Europe/London">London (GMT)</option>
                      <option value="Europe/Amsterdam">Amsterdam (CET)</option>
                      <option value="Europe/Berlin">Berlin (CET)</option>
                      <option value="Asia/Tokyo">Tokyo (JST)</option>
                      <option value="Asia/Shanghai">Shanghai (CST)</option>
                    </select>
                  </SettingsField>
                </div>

                <template #actions>
                  <SaveButton :saving="saving === 'organization'" :saved="saved === 'organization'" @click="saveCategory('organization', orgSettings)" />
                </template>
              </SettingsSection>
            </template>

            <!-- Agent Defaults -->
            <template v-if="activeSection === 'agents'">
              <SettingsSection title="Agent Defaults" icon="ph:robot">
                <div class="space-y-4">
                  <SettingsField label="Default Agent Behavior" description="Controls how newly created agents behave by default">
                    <select v-model="agentSettings.default_behavior" class="settings-input">
                      <option value="autonomous">Autonomous (minimal supervision)</option>
                      <option value="supervised">Supervised (ask before actions)</option>
                      <option value="strict">Strict (require approval for everything)</option>
                    </select>
                  </SettingsField>

                  <SettingsField label="Auto-spawn Agents">
                    <label class="flex items-center gap-3 cursor-pointer">
                      <div class="relative">
                        <input
                          v-model="agentSettings.auto_spawn"
                          type="checkbox"
                          class="sr-only"
                        />
                        <div
                          class="w-11 h-6 rounded-full transition-colors"
                          :class="agentSettings.auto_spawn ? 'bg-neutral-900 dark:bg-white' : 'bg-neutral-200 dark:bg-neutral-700'"
                        >
                          <div
                            class="absolute top-0.5 left-0.5 w-5 h-5 bg-white dark:bg-neutral-900 rounded-full transition-transform"
                            :class="{ 'translate-x-5': agentSettings.auto_spawn }"
                          />
                        </div>
                      </div>
                      <span class="text-sm text-neutral-500 dark:text-neutral-400">
                        Allow manager agents to spawn temporary agents
                      </span>
                    </label>
                  </SettingsField>
                </div>

                <template #actions>
                  <SaveButton :saving="saving === 'agents'" :saved="saved === 'agents'" @click="saveCategory('agents', agentSettings)" />
                </template>
              </SettingsSection>
            </template>

            <!-- Action Policies -->
            <template v-if="activeSection === 'policies'">
              <SettingsSection title="Action Policies" icon="ph:shield-check">
                <template #actions>
                  <div class="flex items-center gap-2">
                    <SaveButton :saving="saving === 'policies'" :saved="saved === 'policies'" @click="savePolicies" />
                    <button
                      type="button"
                      class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors duration-150"
                      @click="showPolicyModal = true"
                    >
                      <Icon name="ph:plus" class="w-3.5 h-3.5" />
                      Add policy
                    </button>
                  </div>
                </template>

                <div v-if="actionPolicies.length > 0" class="space-y-3">
                  <div
                    v-for="policy in actionPolicies"
                    :key="policy.id"
                    class="flex items-start gap-3 p-3 rounded-lg bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700"
                  >
                    <div class="flex-1 min-w-0">
                      <p class="text-sm font-medium text-neutral-900 dark:text-white">{{ policy.name }}</p>
                      <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5 font-mono">{{ policy.pattern }}</p>
                      <p class="text-xs text-neutral-400 dark:text-neutral-500 mt-1">
                        {{ getPolicyLevelText(policy) }}
                      </p>
                    </div>
                    <div class="flex items-center gap-1 shrink-0">
                      <button
                        type="button"
                        class="p-1.5 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 transition-colors"
                        @click="editPolicy(policy)"
                      >
                        <Icon name="ph:pencil-simple" class="w-4 h-4" />
                      </button>
                      <button
                        type="button"
                        class="p-1.5 text-neutral-400 hover:text-red-500 transition-colors"
                        @click="deletePolicy(policy.id)"
                      >
                        <Icon name="ph:trash" class="w-4 h-4" />
                      </button>
                    </div>
                  </div>
                </div>

                <div v-else class="py-6 text-center">
                  <Icon name="ph:shield" class="w-8 h-8 text-neutral-300 dark:text-neutral-600 mx-auto mb-2" />
                  <p class="text-sm text-neutral-500 dark:text-neutral-400">No action policies configured</p>
                  <p class="text-xs text-neutral-400 dark:text-neutral-500 mt-1">Add policies to control which agent actions require approval</p>
                </div>
              </SettingsSection>
            </template>

            <!-- Notifications -->
            <template v-if="activeSection === 'notifications'">
              <SettingsSection title="Notifications" icon="ph:bell">
                <div class="space-y-4">
                  <SettingsField label="Email Notifications">
                    <label class="flex items-center gap-3 cursor-pointer">
                      <input
                        v-model="notificationSettings.email_notifications"
                        type="checkbox"
                        class="w-4 h-4 rounded text-neutral-900 focus:ring-neutral-900"
                      />
                      <span class="text-sm text-neutral-500 dark:text-neutral-400">
                        Receive email notifications for approval requests
                      </span>
                    </label>
                  </SettingsField>

                  <SettingsField label="Slack Integration">
                    <label class="flex items-center gap-3 cursor-pointer">
                      <input
                        v-model="notificationSettings.slack_notifications"
                        type="checkbox"
                        class="w-4 h-4 rounded text-neutral-900 focus:ring-neutral-900"
                      />
                      <span class="text-sm text-neutral-500 dark:text-neutral-400">
                        Send notifications to Slack channel
                      </span>
                    </label>
                  </SettingsField>

                  <SettingsField label="Daily Summary">
                    <label class="flex items-center gap-3 cursor-pointer">
                      <input
                        v-model="notificationSettings.daily_summary"
                        type="checkbox"
                        class="w-4 h-4 rounded text-neutral-900 focus:ring-neutral-900"
                      />
                      <span class="text-sm text-neutral-500 dark:text-neutral-400">
                        Receive a daily summary of agent activities
                      </span>
                    </label>
                  </SettingsField>
                </div>

                <template #actions>
                  <SaveButton :saving="saving === 'notifications'" :saved="saved === 'notifications'" @click="saveCategory('notifications', notificationSettings)" />
                </template>
              </SettingsSection>
            </template>

            <!-- Danger Zone -->
            <template v-if="activeSection === 'danger'">
              <SettingsSection title="Danger Zone" icon="ph:warning" variant="danger">
                <div class="space-y-3">
                  <div class="flex items-center justify-between py-2">
                    <div>
                      <p class="text-sm font-medium text-neutral-900 dark:text-white">Pause All Agents</p>
                      <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">Immediately pause all running agent tasks</p>
                    </div>
                    <button
                      type="button"
                      class="px-3 py-1.5 text-xs font-medium rounded-md text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors duration-150"
                      :disabled="dangerLoading !== null"
                      @click="confirmDangerAction('pause_agents', 'Pause All Agents', 'This will immediately pause all running agents. They can be resumed individually.')"
                    >
                      <span v-if="dangerLoading === 'pause_agents'" class="flex items-center gap-1">
                        <Icon name="ph:spinner" class="w-3.5 h-3.5 animate-spin" />
                        Pausing...
                      </span>
                      <span v-else>Pause All</span>
                    </button>
                  </div>

                  <div class="border-t border-neutral-100 dark:border-neutral-800" />

                  <div class="flex items-center justify-between py-2">
                    <div>
                      <p class="text-sm font-medium text-neutral-900 dark:text-white">Reset Agent Memory</p>
                      <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">Clear all agent memory and learned behaviors</p>
                    </div>
                    <button
                      type="button"
                      class="px-3 py-1.5 text-xs font-medium rounded-md text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors duration-150"
                      :disabled="dangerLoading !== null"
                      @click="confirmDangerAction('reset_memory', 'Reset Agent Memory', 'This will clear all agent memory files. This action cannot be undone.')"
                    >
                      <span v-if="dangerLoading === 'reset_memory'" class="flex items-center gap-1">
                        <Icon name="ph:spinner" class="w-3.5 h-3.5 animate-spin" />
                        Resetting...
                      </span>
                      <span v-else>Reset</span>
                    </button>
                  </div>
                </div>
              </SettingsSection>
            </template>
          </template>
        </main>
      </div>

      <!-- Policy Modal -->
      <Modal v-model:open="showPolicyModal" :title="editingPolicy ? 'Edit Policy' : 'Add Policy'">
        <template #body>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">Name</label>
              <input
                v-model="policyForm.name"
                type="text"
                class="w-full px-3 py-2 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white text-sm focus:outline-none focus:border-neutral-400"
                placeholder="e.g., Document Operations"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">Pattern</label>
              <input
                v-model="policyForm.pattern"
                type="text"
                class="w-full px-3 py-2 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white text-sm font-mono focus:outline-none focus:border-neutral-400"
                placeholder="e.g., write:documents/*"
              />
              <p class="text-xs text-neutral-400 dark:text-neutral-500 mt-1">Use * as wildcard. Examples: read:*, execute:external/*</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">Policy Level</label>
              <div class="space-y-2">
                <label class="flex items-center gap-2 cursor-pointer">
                  <input v-model="policyForm.level" type="radio" value="allow" class="text-neutral-900" />
                  <span class="text-sm text-neutral-700 dark:text-neutral-300">Allow without approval</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                  <input v-model="policyForm.level" type="radio" value="require_approval" class="text-neutral-900" />
                  <span class="text-sm text-neutral-700 dark:text-neutral-300">Require approval</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                  <input v-model="policyForm.level" type="radio" value="block" class="text-neutral-900" />
                  <span class="text-sm text-neutral-700 dark:text-neutral-300">Block entirely</span>
                </label>
              </div>
            </div>
            <div v-if="policyForm.level === 'require_approval'">
              <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">Cost Threshold (optional)</label>
              <div class="flex items-center gap-2">
                <span class="text-neutral-500">$</span>
                <input
                  v-model.number="policyForm.costThreshold"
                  type="number"
                  class="flex-1 px-3 py-2 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white text-sm focus:outline-none focus:border-neutral-400"
                  placeholder="0"
                  min="0"
                />
              </div>
              <p class="text-xs text-neutral-400 dark:text-neutral-500 mt-1">Only require approval when cost exceeds this amount</p>
            </div>
          </div>
        </template>
        <template #footer>
          <div class="flex justify-end gap-2">
            <button
              type="button"
              class="px-3 py-1.5 text-sm rounded-md text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800"
              @click="closePolicyModal"
            >
              Cancel
            </button>
            <button
              type="button"
              class="px-3 py-1.5 text-sm font-medium rounded-md bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 hover:bg-neutral-800 dark:hover:bg-neutral-100"
              @click="savePolicy"
            >
              {{ editingPolicy ? 'Save Changes' : 'Create Policy' }}
            </button>
          </div>
        </template>
      </Modal>

      <!-- Danger Confirmation Modal -->
      <Modal v-model:open="showDangerModal" :title="dangerModalTitle">
        <template #body>
          <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ dangerModalMessage }}</p>
        </template>
        <template #footer>
          <div class="flex justify-end gap-2">
            <button
              type="button"
              class="px-3 py-1.5 text-sm rounded-md text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800"
              @click="showDangerModal = false"
            >
              Cancel
            </button>
            <button
              type="button"
              class="px-3 py-1.5 text-sm font-medium rounded-md bg-red-600 text-white hover:bg-red-700"
              @click="executeDangerAction"
            >
              Confirm
            </button>
          </div>
        </template>
      </Modal>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import SettingsSection from '@/Components/settings/SettingsSection.vue'
import SettingsField from '@/Components/settings/SettingsField.vue'
import Icon from '@/Components/shared/Icon.vue'
import Modal from '@/Components/shared/Modal.vue'
import { useApi } from '@/composables/useApi'

// --- Sidebar sections ---
const sections = [
  { id: 'organization', name: 'Organization', icon: 'ph:buildings' },
  { id: 'agents', name: 'Agent Defaults', icon: 'ph:robot' },
  { id: 'policies', name: 'Action Policies', icon: 'ph:shield-check' },
  { id: 'notifications', name: 'Notifications', icon: 'ph:bell' },
  { id: 'danger', name: 'Danger Zone', icon: 'ph:warning' },
]

const activeSection = ref('organization')

// --- API ---
const { fetchSettings, updateSettings, dangerAction } = useApi()
const loading = ref(true)
const saving = ref<string | null>(null)
const saved = ref<string | null>(null)

// --- Settings state ---
const orgSettings = reactive({
  org_name: '',
  org_email: '',
  org_timezone: 'UTC',
})

const agentSettings = reactive({
  default_behavior: 'supervised',
  auto_spawn: false,
})

const notificationSettings = reactive({
  email_notifications: true,
  slack_notifications: false,
  daily_summary: true,
})

interface ActionPolicy {
  id: string
  name: string
  pattern: string
  level: 'allow' | 'require_approval' | 'block'
  costThreshold?: number
}

const actionPolicies = ref<ActionPolicy[]>([])

// --- Load settings ---
onMounted(async () => {
  try {
    const { data, promise } = fetchSettings()
    await promise
    if (data.value) {
      const s = data.value
      // Organization
      if (s.organization) {
        orgSettings.org_name = (s.organization.org_name as string) ?? ''
        orgSettings.org_email = (s.organization.org_email as string) ?? ''
        orgSettings.org_timezone = (s.organization.org_timezone as string) ?? 'UTC'
      }
      // Agents
      if (s.agents) {
        agentSettings.default_behavior = (s.agents.default_behavior as string) ?? 'supervised'
        agentSettings.auto_spawn = !!s.agents.auto_spawn
      }
      // Notifications
      if (s.notifications) {
        notificationSettings.email_notifications = s.notifications.email_notifications !== false
        notificationSettings.slack_notifications = !!s.notifications.slack_notifications
        notificationSettings.daily_summary = s.notifications.daily_summary !== false
      }
      // Policies
      if (s.policies?.action_policies) {
        actionPolicies.value = s.policies.action_policies as ActionPolicy[]
      }
    }
  } finally {
    loading.value = false
  }
})

// --- Save ---
async function saveCategory(category: string, settings: Record<string, unknown>) {
  saving.value = category
  saved.value = null
  try {
    await updateSettings(category, { ...settings })
    saved.value = category
    setTimeout(() => { if (saved.value === category) saved.value = null }, 2000)
  } catch (e) {
    console.error('Failed to save settings:', e)
  } finally {
    saving.value = null
  }
}

async function savePolicies() {
  await saveCategory('policies', { action_policies: actionPolicies.value })
}

// --- Policy CRUD ---
const showPolicyModal = ref(false)
const editingPolicy = ref<ActionPolicy | null>(null)
const policyForm = reactive({
  name: '',
  pattern: '',
  level: 'require_approval' as 'allow' | 'require_approval' | 'block',
  costThreshold: undefined as number | undefined,
})

const getPolicyLevelText = (policy: ActionPolicy): string => {
  if (policy.level === 'allow') return 'Allowed without approval'
  if (policy.level === 'block') return 'Blocked'
  if (policy.costThreshold) return `Require approval above $${policy.costThreshold}`
  return 'Require approval'
}

const editPolicy = (policy: ActionPolicy) => {
  editingPolicy.value = policy
  policyForm.name = policy.name
  policyForm.pattern = policy.pattern
  policyForm.level = policy.level
  policyForm.costThreshold = policy.costThreshold
  showPolicyModal.value = true
}

const deletePolicy = (id: string) => {
  actionPolicies.value = actionPolicies.value.filter(p => p.id !== id)
}

const savePolicy = () => {
  if (editingPolicy.value) {
    const index = actionPolicies.value.findIndex(p => p.id === editingPolicy.value!.id)
    if (index !== -1) {
      actionPolicies.value[index] = {
        ...actionPolicies.value[index],
        name: policyForm.name,
        pattern: policyForm.pattern,
        level: policyForm.level,
        costThreshold: policyForm.costThreshold,
      }
    }
  } else {
    actionPolicies.value.push({
      id: `policy-${Date.now()}`,
      name: policyForm.name,
      pattern: policyForm.pattern,
      level: policyForm.level,
      costThreshold: policyForm.costThreshold,
    })
  }
  closePolicyModal()
}

const closePolicyModal = () => {
  showPolicyModal.value = false
  editingPolicy.value = null
  policyForm.name = ''
  policyForm.pattern = ''
  policyForm.level = 'require_approval'
  policyForm.costThreshold = undefined
}

// --- Danger Zone ---
const showDangerModal = ref(false)
const dangerModalTitle = ref('')
const dangerModalMessage = ref('')
const pendingDangerAction = ref('')
const dangerLoading = ref<string | null>(null)

function confirmDangerAction(action: string, title: string, message: string) {
  pendingDangerAction.value = action
  dangerModalTitle.value = title
  dangerModalMessage.value = message
  showDangerModal.value = true
}

async function executeDangerAction() {
  showDangerModal.value = false
  const action = pendingDangerAction.value
  dangerLoading.value = action
  try {
    await dangerAction(action)
  } catch (e) {
    console.error('Danger action failed:', e)
  } finally {
    dangerLoading.value = null
  }
}

// --- SaveButton component (inline) ---
const SaveButton = {
  props: {
    saving: Boolean,
    saved: Boolean,
  },
  emits: ['click'],
  template: `
    <button
      type="button"
      class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md transition-colors duration-150"
      :class="saved
        ? 'text-green-600 dark:text-green-400'
        : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800'"
      :disabled="saving"
      @click="$emit('click')"
    >
      <svg v-if="saving" class="w-3.5 h-3.5 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-linecap="round" class="opacity-25" /><path d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="3" stroke-linecap="round" /></svg>
      <svg v-else-if="saved" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5" /></svg>
      <svg v-else class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z" /><polyline points="17,21 17,13 7,13 7,21" /><polyline points="7,3 7,8 15,8" /></svg>
      {{ saving ? 'Saving...' : saved ? 'Saved' : 'Save' }}
    </button>
  `,
}
</script>

<style scoped>
@reference "tailwindcss";

.settings-input {
  @apply w-full px-4 py-2.5 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-xl text-neutral-900 dark:text-white focus:border-neutral-400 dark:focus:border-neutral-500 focus:ring-1 focus:ring-neutral-400 dark:focus:ring-neutral-500 outline-none transition-colors;
}
</style>
