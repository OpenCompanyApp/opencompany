<template>
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
      <SaveButton :saving="savingCategory === 'organization'" :saved="savedCategory === 'organization'" @click="$emit('save', 'organization', { ...orgSettings })" />
    </template>
  </SettingsSection>
</template>

<script setup lang="ts">
import { reactive } from 'vue'
import SettingsSection from '@/Components/settings/SettingsSection.vue'
import SettingsField from '@/Components/settings/SettingsField.vue'
import SaveButton from '@/Components/settings/SaveButton.vue'

const props = defineProps<{
  initialSettings: { org_name: string; org_email: string; org_timezone: string }
  savingCategory: string | null
  savedCategory: string | null
}>()

defineEmits<{
  save: [category: string, settings: Record<string, unknown>]
}>()

const orgSettings = reactive({ ...props.initialSettings })
</script>

<style scoped>
@reference "tailwindcss";

.settings-input {
  @apply w-full px-4 py-2.5 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-xl text-neutral-900 dark:text-white focus:border-neutral-400 dark:focus:border-neutral-500 focus:ring-1 focus:ring-neutral-400 dark:focus:ring-neutral-500 outline-none transition-colors;
}
</style>
