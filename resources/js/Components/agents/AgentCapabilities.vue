<template>
  <div class="space-y-6">
    <!-- Capabilities List -->
    <section>
      <h3 class="text-sm font-medium text-neutral-900 dark:text-white mb-3">Available Tools</h3>
      <div class="bg-neutral-50 dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700 divide-y divide-neutral-200 dark:divide-neutral-700">
        <div
          v-for="capability in capabilities"
          :key="capability.id"
          class="px-4 py-3 flex items-center gap-3"
        >
          <div
            :class="[
              'w-8 h-8 rounded-lg flex items-center justify-center shrink-0',
              capability.enabled
                ? 'bg-green-100 dark:bg-green-900/30'
                : 'bg-neutral-100 dark:bg-neutral-700'
            ]"
          >
            <Icon
              :name="capability.icon || getDefaultIcon(capability.name)"
              :class="[
                'w-4 h-4',
                capability.enabled
                  ? 'text-green-600 dark:text-green-400'
                  : 'text-neutral-400 dark:text-neutral-500'
              ]"
            />
          </div>

          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
              <p class="text-sm font-medium text-neutral-900 dark:text-white">
                {{ capability.name }}
              </p>
              <span
                v-if="capability.requiresApproval"
                class="px-1.5 py-0.5 text-xs rounded bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400"
              >
                Approval required
              </span>
            </div>
            <p v-if="capability.description" class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
              {{ capability.description }}
            </p>
          </div>

          <div class="flex items-center gap-2 shrink-0">
            <Icon
              :name="getStatusIcon(capability)"
              :class="[
                'w-4 h-4',
                capability.enabled
                  ? 'text-green-500'
                  : 'text-neutral-400'
              ]"
            />
          </div>
        </div>

        <div v-if="capabilities.length === 0" class="p-6 text-center">
          <Icon name="ph:wrench" class="w-8 h-8 text-neutral-300 dark:text-neutral-600 mx-auto mb-2" />
          <p class="text-sm text-neutral-500 dark:text-neutral-400">No capabilities configured</p>
        </div>
      </div>
    </section>

    <!-- Tool Notes -->
    <section>
      <div class="flex items-center justify-between mb-3">
        <h3 class="text-sm font-medium text-neutral-900 dark:text-white">Tool Notes</h3>
        <button
          type="button"
          class="px-2 py-1 text-xs text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-300 transition-colors"
          @click="editingNotes = true"
        >
          {{ notes ? 'Edit' : 'Add notes' }}
        </button>
      </div>

      <div class="bg-neutral-50 dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700 p-4">
        <div v-if="editingNotes">
          <textarea
            v-model="localNotes"
            rows="4"
            class="w-full bg-transparent text-sm text-neutral-900 dark:text-white focus:outline-none resize-none"
            placeholder="Add notes about tool usage, preferences, or configuration..."
          />
          <div class="flex justify-end gap-2 mt-3">
            <button
              type="button"
              class="px-3 py-1.5 text-xs rounded-md text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-700"
              @click="cancelNotes"
            >
              Cancel
            </button>
            <button
              type="button"
              class="px-3 py-1.5 text-xs font-medium rounded-md bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 hover:bg-neutral-800 dark:hover:bg-neutral-100"
              @click="saveNotes"
            >
              Save
            </button>
          </div>
        </div>

        <div v-else-if="notes" class="text-sm text-neutral-700 dark:text-neutral-300 whitespace-pre-wrap">
          {{ notes }}
        </div>

        <div v-else class="text-center py-2">
          <p class="text-sm text-neutral-500 dark:text-neutral-400">No tool notes</p>
          <p class="text-xs text-neutral-400 dark:text-neutral-500 mt-1">
            Document preferences and configurations
          </p>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import type { AgentCapability } from '@/types'

const props = defineProps<{
  capabilities: AgentCapability[]
  notes?: string
}>()

const emit = defineEmits<{
  saveNotes: [notes: string]
}>()

const editingNotes = ref(false)
const localNotes = ref(props.notes || '')

watch(() => props.notes, (newNotes) => {
  if (newNotes !== undefined) {
    localNotes.value = newNotes
  }
})

const getDefaultIcon = (name: string): string => {
  const lower = name.toLowerCase()
  if (lower.includes('code') || lower.includes('execute')) return 'ph:code'
  if (lower.includes('file')) return 'ph:file'
  if (lower.includes('git')) return 'ph:git-branch'
  if (lower.includes('api') || lower.includes('request')) return 'ph:globe'
  if (lower.includes('database') || lower.includes('db')) return 'ph:database'
  if (lower.includes('deploy')) return 'ph:rocket-launch'
  if (lower.includes('test')) return 'ph:check-circle'
  if (lower.includes('search')) return 'ph:magnifying-glass'
  return 'ph:wrench'
}

const getStatusIcon = (capability: AgentCapability): string => {
  if (capability.enabled) {
    return capability.requiresApproval ? 'ph:check-circle' : 'ph:check-circle-fill'
  }
  return 'ph:x-circle'
}

const cancelNotes = () => {
  localNotes.value = props.notes || ''
  editingNotes.value = false
}

const saveNotes = () => {
  emit('saveNotes', localNotes.value)
  editingNotes.value = false
}
</script>
