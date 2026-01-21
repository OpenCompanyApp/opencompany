<template>
  <div class="h-full overflow-y-auto">
    <div class="max-w-4xl mx-auto p-8">
      <!-- Header -->
      <header class="mb-8">
        <h1 class="text-3xl font-bold mb-2 text-gray-900">Settings</h1>
        <p class="text-gray-500">
          Manage your organization, credits, and agent configuration.
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

        <!-- Credits & Billing -->
        <SettingsSection title="Credits & Billing" icon="ph:coin">
          <div class="space-y-6">
            <!-- Credits Overview -->
            <div class="bg-gray-50 rounded-xl p-6 border border-gray-200">
              <div class="flex items-center justify-between mb-4">
                <div>
                  <p class="text-sm text-gray-500">Available Credits</p>
                  <p class="text-3xl font-bold text-gray-900">
                    {{ statsData?.creditsRemaining?.toLocaleString() ?? 0 }}
                  </p>
                </div>
                <div class="text-right">
                  <p class="text-sm text-gray-500">Credits Used</p>
                  <p class="text-xl font-semibold text-gray-900">
                    {{ statsData?.creditsUsed?.toLocaleString() ?? 0 }}
                  </p>
                </div>
              </div>

              <!-- Usage Bar -->
              <div class="h-3 bg-white rounded-full overflow-hidden">
                <div
                  class="h-full bg-gradient-to-r from-gray-700 to-gray-500 rounded-full transition-all duration-500"
                  :style="{ width: `${creditUsagePercent}%` }"
                />
              </div>
              <p class="text-xs text-gray-500 mt-2">
                {{ creditUsagePercent.toFixed(1) }}% of total credits used
              </p>
            </div>

            <!-- Credit Packages -->
            <div class="grid grid-cols-3 gap-4">
              <button
                v-for="pkg in creditPackages"
                :key="pkg.credits"
                class="p-4 rounded-xl border border-gray-200 hover:border-gray-900 bg-gray-50 hover:bg-gray-100 transition-all text-left group"
              >
                <p class="text-2xl font-bold text-gray-900 group-hover:scale-105 transition-transform">
                  {{ pkg.credits.toLocaleString() }}
                </p>
                <p class="text-sm text-gray-500">credits</p>
                <p class="mt-2 font-semibold">${{ pkg.price }}</p>
              </button>
            </div>
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

            <SettingsField label="Cost Limit per Task">
              <div class="flex items-center gap-2">
                <input
                  v-model.number="agentSettings.costLimit"
                  type="number"
                  class="settings-input flex-1"
                  placeholder="100"
                  min="0"
                />
                <span class="text-gray-500">credits</span>
              </div>
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
                    :class="agentSettings.autoSpawn ? 'bg-gray-900' : 'bg-gray-50'"
                  >
                    <div
                      class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition-transform"
                      :class="{ 'translate-x-5': agentSettings.autoSpawn }"
                    />
                  </div>
                </div>
                <span class="text-sm text-gray-500">
                  Allow manager agents to spawn temporary agents
                </span>
              </label>
            </SettingsField>
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
                  class="w-4 h-4 rounded text-gray-900 focus:ring-gray-900"
                />
                <span class="text-sm text-gray-500">
                  Receive email notifications for approval requests
                </span>
              </label>
            </SettingsField>

            <SettingsField label="Slack Integration">
              <label class="flex items-center gap-3 cursor-pointer">
                <input
                  v-model="notifications.slack"
                  type="checkbox"
                  class="w-4 h-4 rounded text-gray-900 focus:ring-gray-900"
                />
                <span class="text-sm text-gray-500">
                  Send notifications to Slack channel
                </span>
              </label>
            </SettingsField>

            <SettingsField label="Daily Summary">
              <label class="flex items-center gap-3 cursor-pointer">
                <input
                  v-model="notifications.dailySummary"
                  type="checkbox"
                  class="w-4 h-4 rounded text-gray-900 focus:ring-gray-900"
                />
                <span class="text-sm text-gray-500">
                  Receive a daily summary of agent activities
                </span>
              </label>
            </SettingsField>
          </div>
        </SettingsSection>

        <!-- Danger Zone -->
        <SettingsSection title="Danger Zone" icon="ph:warning" variant="danger">
          <div class="space-y-4">
            <div class="flex items-center justify-between p-4 bg-red-500/10 rounded-xl border border-red-500/30">
              <div>
                <p class="font-semibold text-red-400">Pause All Agents</p>
                <p class="text-sm text-gray-500">Immediately pause all running agent tasks</p>
              </div>
              <SharedButton variant="danger" size="sm">
                Pause All
              </SharedButton>
            </div>

            <div class="flex items-center justify-between p-4 bg-red-500/10 rounded-xl border border-red-500/30">
              <div>
                <p class="font-semibold text-red-400">Reset Agent Memory</p>
                <p class="text-sm text-gray-500">Clear all agent memory and learned behaviors</p>
              </div>
              <SharedButton variant="danger" size="sm">
                Reset Memory
              </SharedButton>
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
    </div>
  </div>
</template>

<script setup lang="ts">
import { reactive, computed } from 'vue'
import SettingsSection from '@/Components/settings/SettingsSection.vue'
import SettingsField from '@/Components/settings/SettingsField.vue'
import SharedButton from '@/Components/shared/Button.vue'
import { useApi } from '@/composables/useApi'

const { fetchStats } = useApi()

const { data: stats } = fetchStats()

const statsData = computed(() => stats.value)

const creditUsagePercent = computed(() => {
  const used = statsData.value?.creditsUsed ?? 0
  const remaining = statsData.value?.creditsRemaining ?? 1
  const total = used + remaining
  return (used / total) * 100
})

const creditPackages = [
  { credits: 1000, price: 10 },
  { credits: 5000, price: 45 },
  { credits: 10000, price: 80 },
]

const orgSettings = reactive({
  name: 'Bloom Agency',
  email: 'team@bloomagency.com',
  timezone: 'America/New_York',
})

const agentSettings = reactive({
  behavior: 'supervised',
  costLimit: 100,
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
  @apply w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:border-gray-900 focus:ring-1 focus:ring-gray-900 outline-none transition-colors;
}
</style>
