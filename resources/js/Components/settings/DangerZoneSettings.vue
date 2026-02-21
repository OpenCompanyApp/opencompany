<template>
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
</template>

<script setup lang="ts">
import { ref } from 'vue'
import SettingsSection from '@/Components/settings/SettingsSection.vue'
import Icon from '@/Components/shared/Icon.vue'
import Modal from '@/Components/shared/Modal.vue'
import { useApi } from '@/composables/useApi'

const { dangerAction } = useApi()

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
</script>
