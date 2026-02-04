<template>
  <div class="h-full overflow-y-auto">
    <div class="max-w-3xl mx-auto p-6">
      <!-- Header -->
      <header class="mb-6">
        <h1 class="text-xl font-semibold text-neutral-900 dark:text-white">Settings</h1>
        <p class="text-sm text-neutral-500 dark:text-neutral-400 mt-1">
          Manage your organization and agent configuration
        </p>
      </header>

      <!-- Settings Sections -->
      <div class="space-y-8">
        <!-- Organization Settings -->
        <SettingsSection title="Organization" icon="ph:buildings">
          <div class="space-y-4">
            <SettingsField label="Organization Name">
              <input
                v-model="orgSettings.name"
                type="text"
                class="settings-input"
                placeholder="Enter organization name"
              />
            </SettingsField>

            <SettingsField label="Organization Email">
              <input
                v-model="orgSettings.email"
                type="email"
                class="settings-input"
                placeholder="org@example.com"
              />
            </SettingsField>

            <SettingsField label="Timezone">
              <select v-model="orgSettings.timezone" class="settings-input">
                <option value="UTC">UTC</option>
                <option value="America/New_York">Eastern Time (ET)</option>
                <option value="America/Los_Angeles">Pacific Time (PT)</option>
                <option value="Europe/London">London (GMT)</option>
                <option value="Europe/Amsterdam">Amsterdam (CET)</option>
                <option value="Asia/Tokyo">Tokyo (JST)</option>
              </select>
            </SettingsField>
          </div>
        </SettingsSection>

        <!-- Agent Defaults -->
        <SettingsSection title="Agent Defaults" icon="ph:robot">
          <div class="space-y-4">
            <SettingsField label="Default Agent Behavior">
              <select v-model="agentSettings.behavior" class="settings-input">
                <option value="autonomous">Autonomous (minimal supervision)</option>
                <option value="supervised">Supervised (ask before actions)</option>
                <option value="strict">Strict (require approval for everything)</option>
              </select>
            </SettingsField>

            <SettingsField label="Auto-spawn Agents">
              <label class="flex items-center gap-3 cursor-pointer">
                <div class="relative">
                  <input
                    v-model="agentSettings.autoSpawn"
                    type="checkbox"
                    class="sr-only"
                  />
                  <div
                    class="w-11 h-6 rounded-full transition-colors"
                    :class="agentSettings.autoSpawn ? 'bg-neutral-900 dark:bg-white' : 'bg-neutral-200 dark:bg-neutral-700'"
                  >
                    <div
                      class="absolute top-0.5 left-0.5 w-5 h-5 bg-white dark:bg-neutral-900 rounded-full transition-transform"
                      :class="{ 'translate-x-5': agentSettings.autoSpawn }"
                    />
                  </div>
                </div>
                <span class="text-sm text-neutral-500 dark:text-neutral-400">
                  Allow manager agents to spawn temporary agents
                </span>
              </label>
            </SettingsField>
          </div>
        </SettingsSection>

        <!-- Action Policies -->
        <SettingsSection title="Action Policies" icon="ph:shield-check">
          <template #actions>
            <button
              type="button"
              class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors duration-150"
              @click="showPolicyModal = true"
            >
              <Icon name="ph:plus" class="w-3.5 h-3.5" />
              Add policy
            </button>
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

        <!-- Notifications -->
        <SettingsSection title="Notifications" icon="ph:bell">
          <div class="space-y-4">
            <SettingsField label="Email Notifications">
              <label class="flex items-center gap-3 cursor-pointer">
                <input
                  v-model="notifications.email"
                  type="checkbox"
                  class="w-4 h-4 rounded text-neutral-900 focus:ring-neutral-900"
                />
                <span class="text-sm text-neutral-500">
                  Receive email notifications for approval requests
                </span>
              </label>
            </SettingsField>

            <SettingsField label="Slack Integration">
              <label class="flex items-center gap-3 cursor-pointer">
                <input
                  v-model="notifications.slack"
                  type="checkbox"
                  class="w-4 h-4 rounded text-neutral-900 focus:ring-neutral-900"
                />
                <span class="text-sm text-neutral-500">
                  Send notifications to Slack channel
                </span>
              </label>
            </SettingsField>

            <SettingsField label="Daily Summary">
              <label class="flex items-center gap-3 cursor-pointer">
                <input
                  v-model="notifications.dailySummary"
                  type="checkbox"
                  class="w-4 h-4 rounded text-neutral-900 focus:ring-neutral-900"
                />
                <span class="text-sm text-neutral-500">
                  Receive a daily summary of agent activities
                </span>
              </label>
            </SettingsField>
          </div>
        </SettingsSection>

        <!-- Danger Zone -->
        <SettingsSection title="Danger Zone" icon="ph:warning">
          <div class="space-y-3">
            <div class="flex items-center justify-between py-2">
              <div>
                <p class="text-sm font-medium text-neutral-900 dark:text-white">Pause All Agents</p>
                <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">Immediately pause all running agent tasks</p>
              </div>
              <button
                type="button"
                class="px-3 py-1.5 text-xs font-medium rounded-md text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors duration-150"
              >
                Pause All
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
              >
                Reset
              </button>
            </div>

            <div class="border-t border-neutral-100 dark:border-neutral-800" />

            <div class="flex items-center justify-between py-2">
              <div>
                <p class="text-sm font-medium text-neutral-900 dark:text-white">Delete Organization</p>
                <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">Permanently delete this organization and all data</p>
              </div>
              <button
                type="button"
                class="px-3 py-1.5 text-xs font-medium rounded-md text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors duration-150"
              >
                Delete
              </button>
            </div>
          </div>
        </SettingsSection>

        <!-- Save Button -->
        <div class="flex justify-end pt-4">
          <SharedButton variant="primary" size="lg" @click="saveSettings">
            <Icon name="ph:floppy-disk" class="w-5 h-5 mr-2" />
            Save Changes
          </SharedButton>
        </div>
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
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed } from 'vue'
import SettingsSection from '@/Components/settings/SettingsSection.vue'
import SettingsField from '@/Components/settings/SettingsField.vue'
import SharedButton from '@/Components/shared/Button.vue'
import Icon from '@/Components/shared/Icon.vue'
import Modal from '@/Components/shared/Modal.vue'

interface ActionPolicy {
  id: string
  name: string
  pattern: string
  level: 'allow' | 'require_approval' | 'block'
  costThreshold?: number
  approvers: string[]
}

// Policy modal state
const showPolicyModal = ref(false)
const editingPolicy = ref<ActionPolicy | null>(null)

const policyForm = reactive({
  name: '',
  pattern: '',
  level: 'require_approval' as 'allow' | 'require_approval' | 'block',
  costThreshold: undefined as number | undefined,
})

// Mock action policies
const actionPolicies = ref<ActionPolicy[]>([
  {
    id: 'policy-1',
    name: 'Document Operations',
    pattern: 'write:documents/*',
    level: 'require_approval',
    costThreshold: 10,
    approvers: ['managers'],
  },
  {
    id: 'policy-2',
    name: 'External API Calls',
    pattern: 'execute:external/*',
    level: 'require_approval',
    approvers: ['admins'],
  },
  {
    id: 'policy-3',
    name: 'Read Operations',
    pattern: 'read:*',
    level: 'allow',
    approvers: [],
  },
])

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
      approvers: [],
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

const orgSettings = reactive({
  name: 'Bloom Agency',
  email: 'team@bloomagency.com',
  timezone: 'America/New_York',
})

const agentSettings = reactive({
  behavior: 'supervised',
  autoSpawn: true,
})

const notifications = reactive({
  email: true,
  slack: false,
  dailySummary: true,
})

const saveSettings = () => {
  console.log('Saving settings...', { orgSettings, agentSettings, notifications })
}
</script>

<style scoped>
@reference "tailwindcss";

.settings-input {
  @apply w-full px-4 py-2.5 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-xl text-neutral-900 dark:text-white focus:border-neutral-400 dark:focus:border-neutral-500 focus:ring-1 focus:ring-neutral-400 dark:focus:ring-neutral-500 outline-none transition-colors;
}
</style>
