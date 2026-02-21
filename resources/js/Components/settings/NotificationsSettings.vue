<template>
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
      <SaveButton :saving="savingCategory === 'notifications'" :saved="savedCategory === 'notifications'" @click="$emit('save', 'notifications', { ...notificationSettings })" />
    </template>
  </SettingsSection>
</template>

<script setup lang="ts">
import { reactive } from 'vue'
import SettingsSection from '@/Components/settings/SettingsSection.vue'
import SettingsField from '@/Components/settings/SettingsField.vue'
import SaveButton from '@/Components/settings/SaveButton.vue'

const props = defineProps<{
  initialSettings: { email_notifications: boolean; slack_notifications: boolean; daily_summary: boolean }
  savingCategory: string | null
  savedCategory: string | null
}>()

defineEmits<{
  save: [category: string, settings: Record<string, unknown>]
}>()

const notificationSettings = reactive({ ...props.initialSettings })
</script>
