<template>
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

      <SettingsField label="Budget Approval Threshold" description="Auto-approve budget actions below this amount. Set to 0 to always require approval.">
        <div class="flex items-center gap-2">
          <span class="text-sm text-neutral-500 dark:text-neutral-400">$</span>
          <input
            v-model.number="agentSettings.budget_approval_threshold"
            type="number"
            class="settings-input"
            placeholder="0"
            min="0"
            step="1"
          />
        </div>
      </SettingsField>
    </div>

    <template #actions>
      <SaveButton :saving="savingCategory === 'agents'" :saved="savedCategory === 'agents'" @click="$emit('save', 'agents', { ...agentSettings })" />
    </template>
  </SettingsSection>
</template>

<script setup lang="ts">
import { reactive } from 'vue'
import SettingsSection from '@/Components/settings/SettingsSection.vue'
import SettingsField from '@/Components/settings/SettingsField.vue'
import SaveButton from '@/Components/settings/SaveButton.vue'

const props = defineProps<{
  initialSettings: { default_behavior: string; auto_spawn: boolean; budget_approval_threshold: number }
  savingCategory: string | null
  savedCategory: string | null
}>()

defineEmits<{
  save: [category: string, settings: Record<string, unknown>]
}>()

const agentSettings = reactive({ ...props.initialSettings })
</script>

<style scoped>
@reference "tailwindcss";

.settings-input {
  @apply w-full px-4 py-2.5 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-xl text-neutral-900 dark:text-white focus:border-neutral-400 dark:focus:border-neutral-500 focus:ring-1 focus:ring-neutral-400 dark:focus:ring-neutral-500 outline-none transition-colors;
}
</style>
