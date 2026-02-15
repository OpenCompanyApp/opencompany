<template>
  <div class="space-y-6">
    <!-- Brain / AI Model -->
    <section>
      <div class="flex items-center gap-2 mb-3">
        <h3 class="text-sm font-medium text-neutral-900 dark:text-white">AI Model</h3>
        <Transition
          enter-active-class="transition-opacity duration-200"
          leave-active-class="transition-opacity duration-300"
          enter-from-class="opacity-0"
          leave-to-class="opacity-0"
        >
          <span v-if="savingBrain" class="text-xs text-neutral-400 flex items-center gap-1">
            <Icon name="ph:spinner" class="w-3 h-3 animate-spin" /> Saving...
          </span>
          <span v-else-if="brainSaved" class="text-xs text-green-500 flex items-center gap-1">
            <Icon name="ph:check" class="w-3 h-3" /> Saved
          </span>
          <span v-else-if="brainError" class="text-xs text-red-500 flex items-center gap-1">
            <Icon name="ph:x" class="w-3 h-3" /> Failed to save
          </span>
        </Transition>
      </div>
      <div class="bg-neutral-50 dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700 p-4 space-y-2">
        <div v-if="loadingBrains" class="flex items-center gap-2 text-sm text-neutral-500 dark:text-neutral-400">
          <Icon name="ph:spinner" class="w-4 h-4 animate-spin" />
          Loading available models...
        </div>

        <div v-else-if="groupedBrains.length === 0" class="text-sm text-neutral-500 dark:text-neutral-400">
          <p>No AI models configured.</p>
          <Link :href="workspacePath('/integrations')" class="text-neutral-900 dark:text-white underline hover:no-underline mt-1 inline-block">
            Configure integrations
          </Link>
        </div>

        <template v-else>
          <div v-for="group in groupedBrains" :key="group.provider" class="rounded-lg border border-neutral-200 dark:border-neutral-700 overflow-hidden">
            <!-- Provider Header -->
            <button
              type="button"
              class="w-full px-3 py-2.5 flex items-center gap-2.5 hover:bg-neutral-100 dark:hover:bg-neutral-700/50 transition-colors"
              @click="toggleProvider(group.provider)"
            >
              <div class="w-7 h-7 rounded-md flex items-center justify-center bg-neutral-100 dark:bg-neutral-700 text-neutral-500 dark:text-neutral-400 shrink-0">
                <Icon :name="group.icon" class="w-3.5 h-3.5" />
              </div>
              <span class="text-sm font-medium text-neutral-900 dark:text-white flex-1 text-left">{{ group.providerName }}</span>
              <span class="text-xs text-neutral-400 dark:text-neutral-500">{{ group.models.length }}</span>
              <Icon
                name="ph:caret-down"
                :class="[
                  'w-3.5 h-3.5 text-neutral-400 transition-transform duration-200',
                  expandedProviders.has(group.provider) ? 'rotate-180' : '',
                ]"
              />
            </button>

            <!-- Models List -->
            <div v-if="expandedProviders.has(group.provider)" class="border-t border-neutral-200 dark:border-neutral-700 px-2 py-1.5 space-y-1">
              <button
                v-for="brain in group.models"
                :key="brain.id"
                type="button"
                :class="[
                  'w-full px-3 py-2 rounded-md text-left transition-all flex items-center gap-2.5',
                  selectedBrain === brain.id
                    ? 'bg-neutral-900/5 dark:bg-white/5'
                    : 'hover:bg-neutral-100 dark:hover:bg-neutral-700/50',
                ]"
                @click="changeBrain(brain.id)"
              >
                <span class="flex-1 text-sm text-neutral-700 dark:text-neutral-300">{{ brain.name }}</span>
                <Icon
                  v-if="selectedBrain === brain.id"
                  name="ph:check-circle-fill"
                  class="w-4 h-4 text-green-500 shrink-0"
                />
              </button>
            </div>
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

    <!-- Manager Assignment -->
    <section>
      <div class="flex items-center gap-2 mb-3">
        <h3 class="text-sm font-medium text-neutral-900 dark:text-white">Manager</h3>
        <Transition
          enter-active-class="transition-opacity duration-200"
          leave-active-class="transition-opacity duration-300"
          enter-from-class="opacity-0"
          leave-to-class="opacity-0"
        >
          <span v-if="managerSaved" class="text-xs text-green-500 flex items-center gap-1">
            <Icon name="ph:check" class="w-3 h-3" /> Saved
          </span>
        </Transition>
      </div>
      <div class="bg-neutral-50 dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700 p-4 space-y-3">
        <div v-if="manager" class="flex items-center gap-3 mb-2">
          <div class="w-8 h-8 rounded-lg bg-neutral-200 dark:bg-neutral-700 flex items-center justify-center text-sm">
            {{ manager.type === 'agent' ? '🤖' : '👤' }}
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-neutral-900 dark:text-white truncate">{{ manager.name }}</p>
            <p class="text-xs text-neutral-500 dark:text-neutral-400 capitalize">{{ manager.type === 'agent' ? manager.agentType || 'agent' : 'Human' }}</p>
          </div>
          <Link
            :href="workspacePath(manager.type === 'agent' ? `/agent/${manager.id}` : `/profile/${manager.id}`)"
            class="text-xs text-neutral-500 hover:text-neutral-700 dark:hover:text-neutral-300"
          >
            View
          </Link>
        </div>

        <select
          :value="selectedManagerId"
          class="w-full px-3 py-2 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-900 dark:text-white text-sm focus:outline-none focus:border-neutral-400"
          @change="changeManager(($event.target as HTMLSelectElement).value)"
        >
          <option value="">No manager</option>
          <option
            v-for="user in availableManagers"
            :key="user.id"
            :value="user.id"
          >
            {{ user.name }} {{ user.type === 'agent' ? '(Agent)' : '' }}
          </option>
        </select>
        <p class="text-xs text-neutral-500 dark:text-neutral-400">
          The manager this agent reports to in the org hierarchy
        </p>
      </div>
    </section>

    <!-- Sleep Controls -->
    <section>
      <h3 class="text-sm font-medium text-neutral-900 dark:text-white mb-3">Sleep</h3>
      <div class="bg-neutral-50 dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700 p-4 space-y-3">
        <div v-if="isSleeping" class="space-y-3">
          <div class="flex items-center gap-2 text-amber-600 dark:text-amber-400">
            <Icon name="ph:moon" class="w-4 h-4" />
            <span class="text-sm font-medium">Agent is sleeping</span>
          </div>
          <div v-if="sleepingUntil" class="text-xs text-neutral-500 dark:text-neutral-400">
            Until: {{ new Date(sleepingUntil).toLocaleString() }}
          </div>
          <div v-if="sleepingReason" class="text-xs text-neutral-500 dark:text-neutral-400">
            Reason: {{ sleepingReason }}
          </div>
          <button
            type="button"
            class="px-3 py-1.5 text-sm font-medium rounded-lg bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 hover:bg-neutral-800 dark:hover:bg-neutral-100 transition-colors"
            :disabled="savingSleep"
            @click="wakeUp"
          >
            <Icon name="ph:sun" class="w-4 h-4 mr-1 inline" />
            Wake Up
          </button>
        </div>

        <div v-else class="space-y-3">
          <p class="text-xs text-neutral-500 dark:text-neutral-400">
            Put the agent to sleep. Delegated tasks will be queued until it wakes.
          </p>
          <div class="space-y-2">
            <input
              v-model="sleepReason"
              type="text"
              placeholder="Reason (optional)"
              class="w-full px-3 py-2 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-900 dark:text-white text-sm focus:outline-none focus:border-neutral-400 placeholder:text-neutral-400"
            />
            <input
              v-model="sleepUntil"
              type="datetime-local"
              class="w-full px-3 py-2 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-900 dark:text-white text-sm focus:outline-none focus:border-neutral-400"
            />
          </div>
          <button
            type="button"
            class="px-3 py-1.5 text-sm font-medium rounded-lg text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/20 transition-colors"
            :disabled="savingSleep"
            @click="putToSleep"
          >
            <Icon name="ph:moon" class="w-4 h-4 mr-1 inline" />
            Put to Sleep
          </button>
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
import { Link } from '@inertiajs/vue3'
import Icon from '@/Components/shared/Icon.vue'
import { useWorkspace } from '@/composables/useWorkspace'
import type { AgentSettings, AgentBehaviorMode } from '@/types'

const { workspacePath } = useWorkspace()

interface BrainOption {
  id: string
  provider: string
  providerName: string
  model: string
  name: string
  icon: string
}

interface BrainGroup {
  provider: string
  providerName: string
  icon: string
  models: BrainOption[]
}

const props = defineProps<{
  settings: AgentSettings
  brain?: string
  agentId?: string
  managerId?: string | null
  manager?: { id: string; name: string; type: string; agentType?: string; avatar?: string } | null
  sleepingUntil?: string | null
  sleepingReason?: string | null
  onBrainChange?: (brain: string) => Promise<void>
}>()

const emit = defineEmits<{
  update: [settings: AgentSettings]
  updateManager: [managerId: string | null]
  updateSleep: [data: { sleepingUntil: string | null; sleepingReason: string | null }]
  resetMemory: []
  pauseAgent: []
  deleteAgent: []
}>()

const localSettings = ref<AgentSettings>({ ...props.settings })
const availableBrains = ref<BrainOption[]>([])
const loadingBrains = ref(false)
const selectedBrain = ref(props.brain || '')
const expandedProviders = ref(new Set<string>())

// Manager
const availableManagers = ref<Array<{ id: string; name: string; type: string }>>([])
const selectedManagerId = ref(props.managerId || '')
const savingManager = ref(false)
const managerSaved = ref(false)

// Sleep
const isSleeping = computed(() => !!props.sleepingUntil)
const sleepReason = ref('')
const sleepUntil = ref('')
const savingSleep = ref(false)

// Save feedback
const savingBrain = ref(false)
const brainSaved = ref(false)
const brainError = ref(false)

watch(() => props.settings, (newSettings) => {
  localSettings.value = { ...newSettings }
}, { deep: true })

watch(() => props.brain, (newBrain) => {
  if (newBrain) selectedBrain.value = newBrain
})

watch(() => props.managerId, (newId) => {
  selectedManagerId.value = newId || ''
})

// Group brains by provider
const groupedBrains = computed<BrainGroup[]>(() => {
  const groups = new Map<string, BrainGroup>()
  for (const brain of availableBrains.value) {
    if (!groups.has(brain.provider)) {
      groups.set(brain.provider, {
        provider: brain.provider,
        providerName: brain.providerName,
        icon: brain.icon,
        models: [],
      })
    }
    groups.get(brain.provider)!.models.push(brain)
  }
  return Array.from(groups.values())
})

onMounted(async () => {
  await Promise.all([loadAvailableBrains(), loadAvailableManagers()])
  // Auto-expand the provider of the currently selected brain
  if (selectedBrain.value) {
    const [provider] = selectedBrain.value.split(':')
    if (provider) expandedProviders.value.add(provider)
  }
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

const toggleProvider = (provider: string) => {
  if (expandedProviders.value.has(provider)) {
    expandedProviders.value.delete(provider)
  } else {
    expandedProviders.value.add(provider)
  }
  // Trigger reactivity
  expandedProviders.value = new Set(expandedProviders.value)
}

const changeBrain = async (brainId: string) => {
  if (brainId === selectedBrain.value) return
  const previousBrain = selectedBrain.value
  selectedBrain.value = brainId
  savingBrain.value = true
  brainSaved.value = false
  brainError.value = false

  try {
    if (props.onBrainChange) {
      await props.onBrainChange(brainId)
    }
    savingBrain.value = false
    brainSaved.value = true
    setTimeout(() => { brainSaved.value = false }, 2000)
  } catch (e) {
    console.error('Failed to save brain:', e)
    savingBrain.value = false
    brainError.value = true
    selectedBrain.value = previousBrain
    setTimeout(() => { brainError.value = false }, 3000)
  }
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

const loadAvailableManagers = async () => {
  try {
    const response = await fetch('/api/users')
    if (response.ok) {
      const users = await response.json()
      // Exclude the current agent from the manager list
      availableManagers.value = users
        .filter((u: any) => u.id !== props.agentId)
        .map((u: any) => ({ id: u.id, name: u.name, type: u.type }))
    }
  } catch (error) {
    console.error('Failed to load available managers:', error)
  }
}

const changeManager = (newManagerId: string) => {
  savingManager.value = true
  managerSaved.value = false
  emit('updateManager', newManagerId || null)
  selectedManagerId.value = newManagerId
  savingManager.value = false
  managerSaved.value = true
  setTimeout(() => { managerSaved.value = false }, 2000)
}

const putToSleep = () => {
  savingSleep.value = true
  emit('updateSleep', {
    sleepingUntil: sleepUntil.value || null,
    sleepingReason: sleepReason.value || null,
  })
  sleepReason.value = ''
  sleepUntil.value = ''
  savingSleep.value = false
}

const wakeUp = () => {
  savingSleep.value = true
  emit('updateSleep', { sleepingUntil: null, sleepingReason: null })
  savingSleep.value = false
}

const emitUpdate = () => {
  emit('update', { ...localSettings.value })
}
</script>
