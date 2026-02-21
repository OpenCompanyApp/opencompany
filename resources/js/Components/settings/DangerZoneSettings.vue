<template>
  <SettingsSection title="Danger Zone" icon="ph:warning" variant="danger">
    <div class="space-y-3">
      <DangerRow
        v-for="action in actions"
        :key="action.key"
        :title="action.title"
        :description="action.description"
        :button-label="action.buttonLabel"
        :loading="dangerLoading === action.key"
        :loading-label="action.loadingLabel"
        :disabled="dangerLoading !== null"
        @click="confirmDangerAction(action.key, action.title, action.confirmMessage)"
      />
    </div>

    <!-- Action Result Banner -->
    <div
      v-if="actionResult"
      class="mt-4 p-2.5 rounded-lg text-xs flex items-center gap-2 border"
      :class="actionResult.error
        ? 'bg-red-50 dark:bg-red-900/10 border-red-200 dark:border-red-800/30 text-red-700 dark:text-red-400'
        : 'bg-green-50 dark:bg-green-900/10 border-green-200 dark:border-green-800/30 text-green-700 dark:text-green-400'"
    >
      <Icon :name="actionResult.error ? 'ph:warning' : 'ph:check-circle'" class="w-3.5 h-3.5 shrink-0" />
      <span class="flex-1">{{ actionResult.message }}</span>
      <button type="button" class="shrink-0 opacity-60 hover:opacity-100" @click="actionResult = null">
        <Icon name="ph:x" class="w-3 h-3" />
      </button>
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
import { ref, h } from 'vue'
import SettingsSection from '@/Components/settings/SettingsSection.vue'
import Icon from '@/Components/shared/Icon.vue'
import Modal from '@/Components/shared/Modal.vue'
import { useApi } from '@/composables/useApi'

// --- Inline DangerRow component ---
const DangerRow = (props: {
  title: string
  description: string
  buttonLabel: string
  loading?: boolean
  loadingLabel?: string
  disabled?: boolean
}, { emit }: any) => {
  return h('div', {}, [
    h('div', { class: 'flex items-center justify-between py-2' }, [
      h('div', {}, [
        h('p', { class: 'text-sm font-medium text-neutral-900 dark:text-white' }, props.title),
        h('p', { class: 'text-xs text-neutral-500 dark:text-neutral-400 mt-0.5' }, props.description),
      ]),
      h('button', {
        type: 'button',
        class: 'px-3 py-1.5 text-xs font-medium rounded-md text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors duration-150',
        disabled: props.disabled,
        onClick: () => emit('click'),
      }, props.loading
        ? [
            h('span', { class: 'flex items-center gap-1' }, [
              h(Icon, { name: 'ph:spinner', class: 'w-3.5 h-3.5 animate-spin' }),
              props.loadingLabel || 'Running...',
            ]),
          ]
        : props.buttonLabel),
    ]),
    h('div', { class: 'border-t border-neutral-100 dark:border-neutral-800' }),
  ])
}
DangerRow.props = ['title', 'description', 'buttonLabel', 'loading', 'loadingLabel', 'disabled']
DangerRow.emits = ['click']

const { dangerAction } = useApi()

const actions = [
  {
    key: 'pause_agents',
    title: 'Pause All Agents',
    description: 'Immediately pause all running agent tasks.',
    buttonLabel: 'Pause All',
    loadingLabel: 'Pausing...',
    confirmMessage: 'This will immediately pause all running agents. They can be resumed from the System Health panel above.',
  },
  {
    key: 'reset_memory',
    title: 'Reset Agent Memory',
    description: 'Clear all agent memory and learned behaviors.',
    buttonLabel: 'Reset',
    loadingLabel: 'Resetting...',
    confirmMessage: 'This will clear all agent memory files. This action cannot be undone.',
  },
  {
    key: 'reset_embeddings',
    title: 'Reset Embeddings',
    description: 'Clear all vector embeddings for this workspace. Documents will need to be re-indexed.',
    buttonLabel: 'Reset',
    loadingLabel: 'Resetting...',
    confirmMessage: 'This will delete all document chunks and embedding data for this workspace. You will need to re-index documents for semantic search to work again.',
  },
  {
    key: 'clear_embedding_cache',
    title: 'Clear Embedding Cache',
    description: 'Clear cached embedding vectors. New embeddings will be generated on next use.',
    buttonLabel: 'Clear',
    loadingLabel: 'Clearing...',
    confirmMessage: 'This will clear the embedding cache. New embeddings will be regenerated automatically on next use, which may take some time.',
  },
  {
    key: 'clear_conversation_summaries',
    title: 'Clear Conversation Summaries',
    description: 'Remove all conversation compaction summaries. Conversations will be re-compacted naturally.',
    buttonLabel: 'Clear',
    loadingLabel: 'Clearing...',
    confirmMessage: 'This will remove all conversation summaries. Agents will lose their compacted conversation context. New summaries will be generated during future conversations.',
  },
]

const showDangerModal = ref(false)
const dangerModalTitle = ref('')
const dangerModalMessage = ref('')
const pendingDangerAction = ref('')
const dangerLoading = ref<string | null>(null)
const actionResult = ref<{ message: string, error?: boolean } | null>(null)

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
  actionResult.value = null

  try {
    const response = await dangerAction(action)
    actionResult.value = { message: response.data?.message || 'Action completed.' }
  } catch (e: any) {
    const msg = e?.response?.data?.message || e?.message || 'Action failed.'
    actionResult.value = { message: msg, error: true }
  } finally {
    dangerLoading.value = null
  }
}
</script>
