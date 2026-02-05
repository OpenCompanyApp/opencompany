<template>
  <div class="space-y-6">
    <!-- Brain / AI Model -->
    <section>
      <h3 class="text-sm font-medium text-neutral-900 dark:text-white mb-3">AI Model</h3>
      <div class="bg-neutral-50 dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700 p-4 space-y-3">
        <div v-if="loadingBrains" class="flex items-center gap-2 text-sm text-neutral-500 dark:text-neutral-400">
          <Icon name="ph:spinner" class="w-4 h-4 animate-spin" />
          Loading available models...
        </div>

        <div v-else-if="availableBrains.length === 0" class="text-sm text-neutral-500 dark:text-neutral-400">
          <p>No AI models configured.</p>
          <a href="/integrations" class="text-neutral-900 dark:text-white underline hover:no-underline mt-1 inline-block">
            Configure integrations
          </a>
        </div>

        <template v-else>
          <div class="space-y-2">
            <button
              v-for="brain in availableBrains"
              :key="brain.id"
              type="button"
              :class="[
                'w-full p-3 rounded-lg border-2 text-left transition-all flex items-center gap-3',
                selectedBrain === brain.id
                  ? 'border-neutral-900 dark:border-white bg-neutral-900/5 dark:bg-white/5'
                  : 'border-neutral-200 dark:border-neutral-700 hover:border-neutral-400 dark:hover:border-neutral-500',
              ]"
              @click="changeBrain(brain.id)"
            >
              <div
                :class="[
                  'w-8 h-8 rounded-lg flex items-center justify-center shrink-0',
                  selectedBrain === brain.id
                    ? 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900'
                    : 'bg-neutral-100 dark:bg-neutral-700 text-neutral-500',
                ]"
              >
                <Icon :name="brain.icon" class="w-4 h-4" />
              </div>
              <div class="flex-1 min-w-0">
                <p class="font-medium text-sm text-neutral-900 dark:text-white">{{ brain.name }}</p>
                <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ brain.providerName }}</p>
              </div>
              <Icon
                v-if="selectedBrain === brain.id"
                name="ph:check-circle-fill"
                class="w-5 h-5 text-green-500 shrink-0"
              />
            </button>
          </div>
        </template>
      </div>
    </section>

    <!-- Behavior Mode -->
    <section>
      <h3 class="text-sm font-medium text-neutral-900 dark:text-white mb-3">Behavior Mode</h3>
      <div class="bg-neutral-50 dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700 p-4">
        <div class="flex gap-2">
          <button
            v-for="mode in behaviorModes"
            :key="mode.value"
            type="button"
            :class="[
              'flex-1 px-3 py-2 text-sm font-medium rounded-lg transition-colors',
              localSettings.behaviorMode === mode.value
                ? 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900'
                : 'bg-white dark:bg-neutral-700 text-neutral-600 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-600'
            ]"
            @click="updateBehaviorMode(mode.value)"
          >
            {{ mode.label }}
          </button>
        </div>
        <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-3">
          {{ behaviorModeDescription }}
        </p>
      </div>
    </section>

    <!-- Session Reset -->
    <section>
      <h3 class="text-sm font-medium text-neutral-900 dark:text-white mb-3">Session Reset</h3>
      <div class="bg-neutral-50 dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700 p-4 space-y-4">
        <div>
          <label class="text-sm text-neutral-600 dark:text-neutral-300 mb-2 block">Reset mode:</label>
          <select
            v-model="localSettings.resetPolicy.mode"
            class="w-full px-3 py-2 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-900 dark:text-white text-sm focus:outline-none focus:border-neutral-400"
            @change="emitUpdate"
          >
            <option value="daily">Daily at specific hour</option>
            <option value="idle">After idle period</option>
            <option value="manual">Manual only</option>
          </select>
        </div>

        <div v-if="localSettings.resetPolicy.mode === 'daily'" class="flex items-center gap-3">
          <label class="text-sm text-neutral-600 dark:text-neutral-300">Reset at:</label>
          <select
            v-model.number="localSettings.resetPolicy.dailyHour"
            class="px-3 py-1.5 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-900 dark:text-white text-sm focus:outline-none focus:border-neutral-400"
            @change="emitUpdate"
          >
            <option v-for="hour in 24" :key="hour - 1" :value="hour - 1">
              {{ formatHour(hour - 1) }}
            </option>
          </select>
        </div>

        <div v-if="localSettings.resetPolicy.mode === 'idle'" class="flex items-center gap-3">
          <label class="text-sm text-neutral-600 dark:text-neutral-300">Idle for:</label>
          <input
            v-model.number="localSettings.resetPolicy.idleMinutes"
            type="number"
            min="5"
            step="5"
            class="w-20 px-3 py-1.5 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-900 dark:text-white text-sm focus:outline-none focus:border-neutral-400"
            @change="emitUpdate"
          />
          <span class="text-sm text-neutral-500 dark:text-neutral-400">minutes</span>
        </div>
      </div>
    </section>

    <!-- Danger Zone -->
    <section>
      <h3 class="text-sm font-medium text-red-600 dark:text-red-400 mb-3">Danger Zone</h3>
      <div class="bg-neutral-50 dark:bg-neutral-800 rounded-xl border border-red-200 dark:border-red-900/50 divide-y divide-neutral-200 dark:divide-neutral-700">
        <div class="flex items-center justify-between p-4">
          <div>
            <p class="text-sm font-medium text-neutral-900 dark:text-white">Reset Memory</p>
            <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
              Clear all persistent memories and start fresh
            </p>
          </div>
          <button
            type="button"
            class="px-3 py-1.5 text-xs font-medium rounded-md text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/20 transition-colors"
            @click="$emit('resetMemory')"
          >
            Reset
          </button>
        </div>

        <div class="flex items-center justify-between p-4">
          <div>
            <p class="text-sm font-medium text-neutral-900 dark:text-white">Pause Agent</p>
            <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
              Stop all current tasks and prevent new ones
            </p>
          </div>
          <button
            type="button"
            class="px-3 py-1.5 text-xs font-medium rounded-md text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/20 transition-colors"
            @click="$emit('pauseAgent')"
          >
            Pause
          </button>
        </div>

        <div class="flex items-center justify-between p-4">
          <div>
            <p class="text-sm font-medium text-neutral-900 dark:text-white">Delete Agent</p>
            <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
              Permanently remove this agent and all data
            </p>
          </div>
          <button
            type="button"
            class="px-3 py-1.5 text-xs font-medium rounded-md text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
            @click="$emit('deleteAgent')"
          >
            Delete
          </button>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import type { AgentSettings, AgentBehaviorMode } from '@/types'

interface BrainOption {
  id: string
  provider: string
  providerName: string
  model: string
  name: string
  icon: string
}

const props = defineProps<{
  settings: AgentSettings
  brain?: string
}>()

const emit = defineEmits<{
  update: [settings: AgentSettings]
  updateBrain: [brain: string]
  resetMemory: []
  pauseAgent: []
  deleteAgent: []
}>()

const localSettings = ref<AgentSettings>({ ...props.settings })
const availableBrains = ref<BrainOption[]>([])
const loadingBrains = ref(false)
const selectedBrain = ref(props.brain || '')

watch(() => props.settings, (newSettings) => {
  localSettings.value = { ...newSettings }
}, { deep: true })

watch(() => props.brain, (newBrain) => {
  if (newBrain) selectedBrain.value = newBrain
})

onMounted(async () => {
  await loadAvailableBrains()
})

const loadAvailableBrains = async () => {
  loadingBrains.value = true
  try {
    const response = await fetch('/api/integrations/models')
    if (response.ok) {
      availableBrains.value = await response.json()
    }
  } catch (error) {
    console.error('Failed to load available brains:', error)
  } finally {
    loadingBrains.value = false
  }
}

const changeBrain = (brainId: string) => {
  if (brainId === selectedBrain.value) return
  selectedBrain.value = brainId
  emit('updateBrain', brainId)
}

const behaviorModes: { value: AgentBehaviorMode; label: string }[] = [
  { value: 'autonomous', label: 'Autonomous' },
  { value: 'supervised', label: 'Supervised' },
  { value: 'strict', label: 'Strict' },
]

const behaviorModeDescription = computed(() => {
  switch (localSettings.value.behaviorMode) {
    case 'autonomous':
      return 'Agent works independently, only requesting approval for high-risk actions.'
    case 'supervised':
      return 'Agent requests approval for significant decisions and cost thresholds.'
    case 'strict':
      return 'Agent requires approval for all actions beyond basic operations.'
    default:
      return ''
  }
})

const formatHour = (hour: number): string => {
  const period = hour >= 12 ? 'PM' : 'AM'
  const displayHour = hour === 0 ? 12 : hour > 12 ? hour - 12 : hour
  return `${displayHour}:00 ${period}`
}

const updateBehaviorMode = (mode: AgentBehaviorMode) => {
  localSettings.value.behaviorMode = mode
  emitUpdate()
}

const emitUpdate = () => {
  emit('update', { ...localSettings.value })
}
</script>
