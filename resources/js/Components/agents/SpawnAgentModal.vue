<template>
  <Modal
    v-model:open="isOpen"
    title="Spawn New Agent"
    description="Create a new AI agent to help with your tasks"
    icon="ph:robot"
    size="md"
  >
    <form class="space-y-6" @submit.prevent="handleSpawn">
      <!-- Agent Type Selection -->
      <div class="space-y-3">
        <label class="block text-sm font-medium text-gray-900">Agent Type</label>
        <div class="grid grid-cols-2 gap-3">
          <button
            v-for="type in agentTypes"
            :key="type.id"
            type="button"
            :class="[
              'p-4 rounded-xl border-2 text-left transition-all',
              selectedType === type.id
                ? 'border-gray-900 bg-gray-900/10'
                : 'border-gray-200 hover:border-gray-900/50 bg-gray-50',
            ]"
            @click="selectedType = type.id"
          >
            <div class="flex items-start gap-3">
              <div
                :class="[
                  'w-10 h-10 rounded-xl flex items-center justify-center',
                  selectedType === type.id
                    ? 'bg-gray-900 text-white'
                    : 'bg-gray-100 text-gray-900-muted',
                ]"
              >
                <Icon :name="type.icon" class="w-5 h-5" />
              </div>
              <div class="flex-1 min-w-0">
                <p class="font-semibold text-gray-900">{{ type.label }}</p>
                <p class="text-xs text-gray-900-muted mt-0.5">{{ type.description }}</p>
              </div>
            </div>
          </button>
        </div>
      </div>

      <!-- Agent Name -->
      <div class="space-y-2">
        <label class="block text-sm font-medium text-gray-900">Agent Name</label>
        <input
          v-model="agentName"
          type="text"
          placeholder="Enter a name for this agent"
          class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 placeholder:text-gray-900-muted focus:border-gray-900 focus:ring-1 focus:ring-gray-900 outline-none transition-colors"
        />
      </div>

      <!-- Initial Task -->
      <div class="space-y-2">
        <label class="block text-sm font-medium text-gray-900">Initial Task (Optional)</label>
        <textarea
          v-model="initialTask"
          rows="3"
          placeholder="Describe the first task for this agent..."
          class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 placeholder:text-gray-900-muted focus:border-gray-900 focus:ring-1 focus:ring-gray-900 outline-none transition-colors resize-none"
        />
      </div>

      <!-- Agent Behavior -->
      <div class="space-y-2">
        <label class="block text-sm font-medium text-gray-900">Behavior Mode</label>
        <select
          v-model="behavior"
          class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:border-gray-900 focus:ring-1 focus:ring-gray-900 outline-none transition-colors"
        >
          <option value="autonomous">Autonomous (minimal supervision)</option>
          <option value="supervised">Supervised (ask before actions)</option>
          <option value="strict">Strict (require approval for everything)</option>
        </select>
      </div>

      <!-- Temporary Agent Toggle -->
      <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
        <div>
          <p class="font-medium text-gray-900">Temporary Agent</p>
          <p class="text-xs text-gray-900-muted mt-0.5">Agent will be deleted after task completion</p>
        </div>
        <button
          type="button"
          :class="[
            'relative w-11 h-6 rounded-full transition-colors',
            isTemporary ? 'bg-gray-900' : 'bg-gray-100',
          ]"
          @click="isTemporary = !isTemporary"
        >
          <span
            :class="[
              'absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition-transform',
              isTemporary && 'translate-x-5',
            ]"
          />
        </button>
      </div>

      <!-- Estimated Cost -->
      <div class="p-4 bg-gray-900/10 border border-gray-900/20 rounded-xl">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <Icon name="ph:coin" class="w-5 h-5 text-gray-900" />
            <span class="text-sm text-gray-900">Estimated Cost</span>
          </div>
          <span class="font-semibold text-gray-900">{{ estimatedCost }} credits</span>
        </div>
        <p class="text-xs text-gray-900-muted mt-2">
          Cost may vary based on agent activity and task complexity
        </p>
      </div>
    </form>

    <template #footer>
      <Button variant="ghost" @click="isOpen = false">
        Cancel
      </Button>
      <Button
        variant="primary"
        :disabled="!selectedType || !agentName.trim()"
        :loading="isSpawning"
        @click="handleSpawn"
      >
        <Icon name="ph:sparkle" class="w-4 h-4 mr-1" />
        Spawn Agent
      </Button>
    </template>
  </Modal>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import Modal from '@/Components/shared/Modal.vue'
import Button from '@/Components/shared/Button.vue'
import Icon from '@/Components/shared/Icon.vue'

const isOpen = defineModel<boolean>('open', { default: false })

const emit = defineEmits<{
  spawn: [agent: {
    name: string
    type: string
    task: string
    behavior: string
    isTemporary: boolean
  }]
}>()

const selectedType = ref('writer')
const agentName = ref('')
const initialTask = ref('')
const behavior = ref('supervised')
const isTemporary = ref(false)
const isSpawning = ref(false)

const agentTypes = [
  { id: 'writer', label: 'Writer', icon: 'ph:pen-nib', description: 'Creates content, copy, and documentation' },
  { id: 'analyst', label: 'Analyst', icon: 'ph:chart-line-up', description: 'Analyzes data and generates insights' },
  { id: 'researcher', label: 'Researcher', icon: 'ph:magnifying-glass', description: 'Finds and compiles information' },
  { id: 'creative', label: 'Creative', icon: 'ph:paint-brush', description: 'Designs visuals and creative assets' },
  { id: 'coder', label: 'Coder', icon: 'ph:code', description: 'Writes and reviews code' },
  { id: 'coordinator', label: 'Coordinator', icon: 'ph:users-three', description: 'Manages workflows and communication' },
]

const estimatedCost = computed(() => {
  const baseCosts: Record<string, number> = {
    writer: 2,
    analyst: 3,
    researcher: 2,
    creative: 4,
    coder: 5,
    coordinator: 3,
  }
  const base = baseCosts[selectedType.value] || 2
  return isTemporary.value ? base : base * 2
})

const handleSpawn = async () => {
  if (!selectedType.value || !agentName.value.trim()) return

  isSpawning.value = true

  try {
    // Create the agent via API
    await fetch('/api/agents', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        name: agentName.value.trim(),
        agentType: selectedType.value,
        task: initialTask.value.trim() || undefined,
        behavior: behavior.value,
        isTemporary: isTemporary.value,
      }),
    })

    emit('spawn', {
      name: agentName.value.trim(),
      type: selectedType.value,
      task: initialTask.value.trim(),
      behavior: behavior.value,
      isTemporary: isTemporary.value,
    })

    // Reset form
    agentName.value = ''
    initialTask.value = ''
    selectedType.value = 'writer'
    behavior.value = 'supervised'
    isTemporary.value = false
    isOpen.value = false
  } catch (error) {
    console.error('Failed to spawn agent:', error)
  } finally {
    isSpawning.value = false
  }
}
</script>
