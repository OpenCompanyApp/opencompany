<template>
  <div class="space-y-6">
    <!-- Current Session -->
    <section>
      <h3 class="text-sm font-medium text-neutral-900 dark:text-white mb-3">Current Session</h3>
      <div class="bg-neutral-50 dark:bg-neutral-800 rounded-xl p-4 border border-neutral-200 dark:border-neutral-700">
        <div v-if="session" class="space-y-3">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-neutral-900 dark:text-white">
                Started {{ formatTimeAgo(session.startedAt) }}
              </p>
              <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
                {{ session.messageCount }} messages
              </p>
            </div>
            <div class="flex items-center gap-2">
              <button
                type="button"
                class="px-3 py-1.5 text-xs font-medium rounded-md text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors"
                @click="$emit('viewHistory')"
              >
                View History
              </button>
              <button
                type="button"
                class="px-3 py-1.5 text-xs font-medium rounded-md bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 hover:bg-neutral-800 dark:hover:bg-neutral-100 transition-colors"
                @click="$emit('newSession')"
              >
                New Session
              </button>
            </div>
          </div>

          <!-- Context Usage -->
          <div>
            <div class="flex items-center justify-between text-xs mb-1">
              <span class="text-neutral-500 dark:text-neutral-400">Context Usage</span>
              <span class="text-neutral-600 dark:text-neutral-300">
                {{ formatTokens(session.tokenCount) }} / {{ formatTokens(session.maxTokens) }}
              </span>
            </div>
            <div class="h-2 bg-neutral-200 dark:bg-neutral-700 rounded-full overflow-hidden">
              <div
                class="h-full bg-neutral-700 dark:bg-neutral-300 rounded-full transition-all"
                :style="{ width: `${contextPercentage}%` }"
              />
            </div>
            <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-1">
              {{ contextPercentage }}% used
              <span v-if="contextPercentage > 80" class="text-amber-500"> - context summary may occur soon</span>
            </p>
          </div>
        </div>

        <div v-else class="text-center py-4">
          <p class="text-sm text-neutral-500 dark:text-neutral-400">No active session</p>
          <button
            type="button"
            class="mt-2 px-3 py-1.5 text-xs font-medium rounded-md bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 hover:bg-neutral-800 dark:hover:bg-neutral-100 transition-colors"
            @click="$emit('newSession')"
          >
            Start Session
          </button>
        </div>
      </div>
    </section>

    <!-- Persistent Memory -->
    <section>
      <div class="flex items-center justify-between mb-3">
        <h3 class="text-sm font-medium text-neutral-900 dark:text-white">Persistent Memory</h3>
        <button
          type="button"
          class="px-2 py-1 text-xs text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-300 transition-colors"
          @click="showAddMemory = true"
        >
          + Add
        </button>
      </div>

      <div class="bg-neutral-50 dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700 divide-y divide-neutral-200 dark:divide-neutral-700">
        <div
          v-for="entry in memoryEntries"
          :key="entry.id"
          class="p-3 group"
        >
          <div class="flex items-start gap-3">
            <div class="flex-1 min-w-0">
              <p class="text-sm text-neutral-900 dark:text-white">{{ entry.content }}</p>
              <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-1">
                {{ formatDate(entry.createdAt) }}
                <span v-if="entry.source"> · {{ entry.source }}</span>
              </p>
            </div>
            <button
              type="button"
              class="p-1 text-neutral-400 hover:text-red-500 opacity-0 group-hover:opacity-100 transition-all"
              @click="$emit('deleteMemory', entry.id)"
            >
              <Icon name="ph:trash" class="w-4 h-4" />
            </button>
          </div>
        </div>

        <div v-if="memoryEntries.length === 0" class="p-6 text-center">
          <Icon name="ph:brain" class="w-8 h-8 text-neutral-300 dark:text-neutral-600 mx-auto mb-2" />
          <p class="text-sm text-neutral-500 dark:text-neutral-400">No persistent memories</p>
          <p class="text-xs text-neutral-400 dark:text-neutral-500 mt-1">
            Add notes that persist across sessions
          </p>
        </div>
      </div>
    </section>

    <!-- Add Memory Modal -->
    <Modal v-model:open="showAddMemory" title="Add Memory">
      <template #body>
        <div class="space-y-3">
          <div>
            <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
              Memory Note
            </label>
            <textarea
              v-model="newMemoryContent"
              rows="3"
              class="w-full px-3 py-2 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white text-sm focus:outline-none focus:border-neutral-400 resize-none"
              placeholder="Enter a note to remember..."
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
              Category
            </label>
            <select
              v-model="newMemoryCategory"
              class="w-full px-3 py-2 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white text-sm focus:outline-none focus:border-neutral-400"
            >
              <option value="note">Note</option>
              <option value="fact">Fact</option>
              <option value="preference">Preference</option>
              <option value="context">Context</option>
            </select>
          </div>
        </div>
      </template>
      <template #footer>
        <div class="flex justify-end gap-2">
          <button
            type="button"
            class="px-3 py-1.5 text-sm rounded-md text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800"
            @click="showAddMemory = false"
          >
            Cancel
          </button>
          <button
            type="button"
            class="px-3 py-1.5 text-sm font-medium rounded-md bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 hover:bg-neutral-800 dark:hover:bg-neutral-100"
            @click="addMemory"
          >
            Add Memory
          </button>
        </div>
      </template>
    </Modal>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import Modal from '@/Components/shared/Modal.vue'
import type { AgentSession, AgentMemoryEntry } from '@/types'

const props = defineProps<{
  session?: AgentSession
  memoryEntries: AgentMemoryEntry[]
}>()

const emit = defineEmits<{
  newSession: []
  viewHistory: []
  addMemory: [entry: { content: string; category: string }]
  deleteMemory: [id: string]
}>()

const showAddMemory = ref(false)
const newMemoryContent = ref('')
const newMemoryCategory = ref<'note' | 'fact' | 'preference' | 'context'>('note')

const contextPercentage = computed(() => {
  if (!props.session) return 0
  return Math.round((props.session.tokenCount / props.session.maxTokens) * 100)
})

const formatTimeAgo = (date: Date | string): string => {
  const d = date instanceof Date ? date : new Date(date)
  if (isNaN(d.getTime())) return ''
  const now = new Date()
  const diffMs = now.getTime() - d.getTime()
  const diffMins = Math.floor(diffMs / 60000)
  const diffHours = Math.floor(diffMs / 3600000)
  const diffDays = Math.floor(diffMs / 86400000)

  if (diffMins < 1) return 'just now'
  if (diffMins < 60) return `${diffMins}m ago`
  if (diffHours < 24) return `${diffHours}h ago`
  return `${diffDays}d ago`
}

const formatDate = (date: Date | string): string => {
  const d = date instanceof Date ? date : new Date(date)
  if (isNaN(d.getTime())) return ''
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

const formatTokens = (tokens: number): string => {
  if (tokens >= 1000) {
    return `${(tokens / 1000).toFixed(0)}k`
  }
  return tokens.toString()
}

const addMemory = () => {
  if (newMemoryContent.value.trim()) {
    emit('addMemory', {
      content: newMemoryContent.value.trim(),
      category: newMemoryCategory.value
    })
    newMemoryContent.value = ''
    newMemoryCategory.value = 'note'
    showAddMemory.value = false
  }
}
</script>
