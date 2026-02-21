<template>
  <SettingsSection title="Action Policies" icon="ph:shield-check">
    <template #actions>
      <div class="flex items-center gap-2">
        <SaveButton :saving="savingCategory === 'policies'" :saved="savedCategory === 'policies'" @click="savePolicies" />
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
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue'
import type { ActionPolicy } from '@/Components/settings/types'
import SettingsSection from '@/Components/settings/SettingsSection.vue'
import SaveButton from '@/Components/settings/SaveButton.vue'
import Icon from '@/Components/shared/Icon.vue'
import Modal from '@/Components/shared/Modal.vue'

const props = defineProps<{
  initialPolicies: ActionPolicy[]
  savingCategory: string | null
  savedCategory: string | null
}>()

const emit = defineEmits<{
  save: [category: string, settings: Record<string, unknown>]
}>()

const actionPolicies = ref<ActionPolicy[]>([...props.initialPolicies])

const showPolicyModal = ref(false)
const editingPolicy = ref<ActionPolicy | null>(null)
const policyForm = reactive({
  name: '',
  pattern: '',
  level: 'require_approval' as 'allow' | 'require_approval' | 'block',
  costThreshold: undefined as number | undefined,
})

function getPolicyLevelText(policy: ActionPolicy): string {
  if (policy.level === 'allow') return 'Allowed without approval'
  if (policy.level === 'block') return 'Blocked'
  if (policy.costThreshold) return `Require approval above $${policy.costThreshold}`
  return 'Require approval'
}

function editPolicy(policy: ActionPolicy) {
  editingPolicy.value = policy
  policyForm.name = policy.name
  policyForm.pattern = policy.pattern
  policyForm.level = policy.level
  policyForm.costThreshold = policy.costThreshold
  showPolicyModal.value = true
}

function deletePolicy(id: string) {
  actionPolicies.value = actionPolicies.value.filter(p => p.id !== id)
}

function savePolicy() {
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

function closePolicyModal() {
  showPolicyModal.value = false
  editingPolicy.value = null
  policyForm.name = ''
  policyForm.pattern = ''
  policyForm.level = 'require_approval'
  policyForm.costThreshold = undefined
}

function savePolicies() {
  emit('save', 'policies', { action_policies: actionPolicies.value })
}
</script>
