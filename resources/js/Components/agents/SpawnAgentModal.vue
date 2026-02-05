<template>
  <Modal
    v-model:open="isOpen"
    title="Spawn New Agent"
    description="Create a new AI agent to help with your tasks"
    icon="ph:robot"
    size="lg"
  >
    <form class="space-y-6 max-h-[60vh] overflow-y-auto pr-1" @submit.prevent="handleSpawn">
      <!-- Step Indicators -->
      <div class="flex items-center justify-center gap-2 mb-6 sticky top-0 bg-white dark:bg-neutral-900 py-2 -mt-2 z-10">
        <button
          v-for="(stepInfo, index) in steps"
          :key="stepInfo.id"
          type="button"
          :class="[
            'flex items-center gap-2 px-3 py-1.5 rounded-full text-sm transition-colors',
            currentStep === index
              ? 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 font-medium'
              : index < currentStep
                ? 'text-green-600 dark:text-green-400'
                : 'text-neutral-400 dark:text-neutral-500',
          ]"
          @click="goToStep(index)"
        >
          <span
            :class="[
              'w-5 h-5 rounded-full flex items-center justify-center text-xs',
              currentStep === index
                ? 'bg-white/20 dark:bg-neutral-900/20'
                : index < currentStep
                  ? 'bg-green-100 dark:bg-green-900/30'
                  : 'bg-neutral-100 dark:bg-neutral-800',
            ]"
          >
            <Icon v-if="index < currentStep" name="ph:check" class="w-3 h-3" />
            <span v-else>{{ index + 1 }}</span>
          </span>
          {{ stepInfo.label }}
        </button>
      </div>

      <!-- Step 1: Basic Info -->
      <div v-show="currentStep === 0" class="space-y-6">
        <!-- Template Selection -->
        <div class="space-y-3">
          <label class="block text-sm font-medium text-neutral-900 dark:text-white">Template (Optional)</label>
          <p class="text-xs text-neutral-500 dark:text-neutral-400 -mt-1">
            Choose a template to pre-configure your agent, or use General for a flexible assistant
          </p>
          <div class="grid grid-cols-2 gap-2 max-h-[200px] overflow-y-auto pr-1">
            <button
              v-for="template in templates"
              :key="template.id"
              type="button"
              :disabled="template.comingSoon"
              :class="[
                'p-3 rounded-xl border-2 text-left transition-all',
                template.comingSoon
                  ? 'border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 opacity-60 cursor-not-allowed'
                  : selectedTemplate === template.id
                    ? 'border-neutral-900 dark:border-white bg-neutral-900/5 dark:bg-white/5'
                    : 'border-neutral-200 dark:border-neutral-700 hover:border-neutral-400 dark:hover:border-neutral-500 bg-neutral-50 dark:bg-neutral-800',
              ]"
              @click="!template.comingSoon && (selectedTemplate = template.id)"
            >
              <div class="flex items-start gap-2">
                <div
                  :class="[
                    'w-8 h-8 rounded-lg flex items-center justify-center shrink-0',
                    template.comingSoon
                      ? 'bg-neutral-100 dark:bg-neutral-700 text-neutral-400'
                      : selectedTemplate === template.id
                        ? 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900'
                        : 'bg-neutral-100 dark:bg-neutral-700 text-neutral-500',
                  ]"
                >
                  <Icon :name="template.icon" class="w-4 h-4" />
                </div>
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-1.5">
                    <p class="font-medium text-sm text-neutral-900 dark:text-white">{{ template.name }}</p>
                    <span
                      v-if="template.comingSoon"
                      class="px-1.5 py-0.5 text-[10px] font-medium rounded bg-neutral-200 dark:bg-neutral-700 text-neutral-500 dark:text-neutral-400"
                    >
                      Soon
                    </span>
                  </div>
                  <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5 line-clamp-1">{{ template.description }}</p>
                </div>
              </div>
            </button>
          </div>
        </div>

        <!-- Agent Name -->
        <div class="space-y-2">
          <label class="block text-sm font-medium text-neutral-900 dark:text-white">Agent Name</label>
          <input
            v-model="agentName"
            type="text"
            placeholder="Enter a name for this agent"
            class="w-full px-4 py-2.5 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-xl text-neutral-900 dark:text-white placeholder:text-neutral-400 focus:border-neutral-900 dark:focus:border-white focus:ring-1 focus:ring-neutral-900 dark:focus:ring-white outline-none transition-colors"
          />
        </div>

        <!-- Initial Task -->
        <div class="space-y-2">
          <label class="block text-sm font-medium text-neutral-900 dark:text-white">Initial Task (Optional)</label>
          <textarea
            v-model="initialTask"
            rows="3"
            placeholder="Describe the first task for this agent..."
            class="w-full px-4 py-2.5 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-xl text-neutral-900 dark:text-white placeholder:text-neutral-400 focus:border-neutral-900 dark:focus:border-white focus:ring-1 focus:ring-neutral-900 dark:focus:ring-white outline-none transition-colors resize-none"
          />
        </div>

        <!-- Agent Behavior -->
        <div class="space-y-2">
          <label class="block text-sm font-medium text-neutral-900 dark:text-white">Behavior Mode</label>
          <select
            v-model="behavior"
            class="w-full px-4 py-2.5 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-xl text-neutral-900 dark:text-white focus:border-neutral-900 dark:focus:border-white focus:ring-1 focus:ring-neutral-900 dark:focus:ring-white outline-none transition-colors"
          >
            <option value="autonomous">Autonomous (minimal supervision)</option>
            <option value="supervised">Supervised (ask before actions)</option>
            <option value="strict">Strict (require approval for everything)</option>
          </select>
        </div>

        <!-- Ephemeral Agent Toggle -->
        <div class="flex items-center justify-between p-4 bg-neutral-50 dark:bg-neutral-800 rounded-xl">
          <div>
            <p class="font-medium text-neutral-900 dark:text-white">Ephemeral Agent</p>
            <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">Agent forgets everything after completing a task</p>
          </div>
          <button
            type="button"
            :class="[
              'relative w-11 h-6 rounded-full transition-colors',
              isEphemeral ? 'bg-neutral-900 dark:bg-white' : 'bg-neutral-200 dark:bg-neutral-600',
            ]"
            @click="isEphemeral = !isEphemeral"
          >
            <span
              :class="[
                'absolute top-0.5 left-0.5 w-5 h-5 bg-white dark:bg-neutral-900 rounded-full transition-transform shadow-sm',
                isEphemeral && 'translate-x-5',
              ]"
            />
          </button>
        </div>
      </div>

      <!-- Step 2: Brain Selection -->
      <div v-show="currentStep === 1" class="space-y-6">
        <div v-if="availableBrains.length === 0" class="text-center py-8">
          <div class="w-16 h-16 rounded-full bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center mx-auto mb-4">
            <Icon name="ph:warning" class="w-8 h-8 text-amber-600 dark:text-amber-400" />
          </div>
          <h3 class="text-lg font-semibold text-neutral-900 dark:text-white mb-2">No AI Models Configured</h3>
          <p class="text-sm text-neutral-500 dark:text-neutral-400 mb-4">
            You need to configure at least one AI model before creating an agent.
          </p>
          <a
            href="/integrations"
            class="inline-flex items-center gap-2 px-4 py-2 bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 rounded-lg font-medium text-sm hover:bg-neutral-800 dark:hover:bg-neutral-100 transition-colors"
          >
            <Icon name="ph:gear" class="w-4 h-4" />
            Configure Integrations
          </a>
        </div>

        <div v-else class="space-y-4">
          <div class="space-y-2">
            <label class="block text-sm font-medium text-neutral-900 dark:text-white">
              Select Brain <span class="text-red-500">*</span>
            </label>
            <p class="text-xs text-neutral-500 dark:text-neutral-400">
              Choose the AI model that will power this agent's intelligence
            </p>
          </div>

          <div class="space-y-2">
            <button
              v-for="brain in availableBrains"
              :key="brain.id"
              type="button"
              :class="[
                'w-full p-4 rounded-xl border-2 text-left transition-all',
                selectedBrain === brain.id
                  ? 'border-neutral-900 dark:border-white bg-neutral-900/5 dark:bg-white/5'
                  : 'border-neutral-200 dark:border-neutral-700 hover:border-neutral-400 dark:hover:border-neutral-500',
              ]"
              @click="selectedBrain = brain.id"
            >
              <div class="flex items-center gap-3">
                <div
                  :class="[
                    'w-10 h-10 rounded-lg flex items-center justify-center',
                    selectedBrain === brain.id
                      ? 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900'
                      : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-500',
                  ]"
                >
                  <Icon :name="brain.icon" class="w-5 h-5" />
                </div>
                <div class="flex-1">
                  <p class="font-medium text-neutral-900 dark:text-white">{{ brain.name }}</p>
                  <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ brain.providerName }}</p>
                </div>
                <Icon
                  v-if="selectedBrain === brain.id"
                  name="ph:check-circle-fill"
                  class="w-5 h-5 text-green-500"
                />
              </div>
            </button>
          </div>
        </div>
      </div>

      <!-- Step 3: Identity Files -->
      <div v-show="currentStep === 2" class="space-y-4">
        <div class="space-y-2">
          <label class="block text-sm font-medium text-neutral-900 dark:text-white">
            Identity Files
          </label>
          <p class="text-xs text-neutral-500 dark:text-neutral-400">
            Configure the agent's personality, behavior, and context. IDENTITY and SOUL are required.
          </p>
        </div>

        <!-- File Tabs -->
        <div class="flex gap-1 border-b border-neutral-200 dark:border-neutral-700 overflow-x-auto pb-px -mb-px">
          <button
            v-for="file in identityFileTypes"
            :key="file.type"
            type="button"
            @click="activeIdentityFile = file.type"
            :class="[
              'px-3 py-2 text-sm whitespace-nowrap transition-colors border-b-2 -mb-px',
              activeIdentityFile === file.type
                ? 'border-neutral-900 dark:border-white text-neutral-900 dark:text-white font-medium'
                : 'border-transparent text-neutral-500 hover:text-neutral-700 dark:hover:text-neutral-300',
            ]"
          >
            {{ file.type }}
            <span v-if="file.required" class="text-red-500 ml-0.5">*</span>
            <Icon
              v-if="identityContent[file.type]?.trim()"
              name="ph:check"
              class="w-3 h-3 ml-1 text-green-500 inline"
            />
          </button>
        </div>

        <!-- File Editor -->
        <div class="space-y-2">
          <div class="flex items-center justify-between">
            <p class="text-xs text-neutral-500 dark:text-neutral-400">
              {{ getFileDescription(activeIdentityFile) }}
            </p>
            <button
              v-if="!identityContent[activeIdentityFile]?.trim()"
              type="button"
              class="flex items-center gap-1 px-2 py-1 text-xs font-medium text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white bg-neutral-100 dark:bg-neutral-700 hover:bg-neutral-200 dark:hover:bg-neutral-600 rounded-md transition-colors"
              @click="fillWithTemplate(activeIdentityFile)"
            >
              <Icon name="ph:magic-wand" class="w-3 h-3" />
              Use template
            </button>
          </div>
          <textarea
            v-model="identityContent[activeIdentityFile]"
            rows="6"
            class="w-full px-4 py-3 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-xl text-neutral-900 dark:text-white placeholder:text-neutral-400 focus:border-neutral-900 dark:focus:border-white focus:ring-1 focus:ring-neutral-900 dark:focus:ring-white outline-none transition-colors resize-none font-mono text-sm"
            :placeholder="getFilePlaceholder(activeIdentityFile)"
          />
        </div>
      </div>

    </form>

    <template #footer>
      <div class="flex items-center justify-between w-full">
        <Button
          v-if="currentStep > 0"
          variant="ghost"
          @click="currentStep--"
        >
          <Icon name="ph:arrow-left" class="w-4 h-4 mr-1" />
          Back
        </Button>
        <div v-else />

        <div class="flex items-center gap-2">
          <Button variant="ghost" @click="isOpen = false">
            Cancel
          </Button>
          <Button
            v-if="currentStep < steps.length - 1"
            variant="primary"
            :disabled="!canProceed"
            @click="currentStep++"
          >
            Next
            <Icon name="ph:arrow-right" class="w-4 h-4 ml-1" />
          </Button>
          <Button
            v-else
            variant="primary"
            :disabled="!canSpawn"
            :loading="isSpawning"
            @click="handleSpawn"
          >
            <Icon name="ph:sparkle" class="w-4 h-4 mr-1" />
            Spawn Agent
          </Button>
        </div>
      </div>
    </template>
  </Modal>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import Modal from '@/Components/shared/Modal.vue'
import Button from '@/Components/shared/Button.vue'
import Icon from '@/Components/shared/Icon.vue'

const isOpen = defineModel<boolean>('open', { default: false })

const emit = defineEmits<{
  spawn: [agent: { id: string; name: string; [key: string]: unknown }]
}>()

// Steps
const steps = [
  { id: 'basic', label: 'Basic Info' },
  { id: 'brain', label: 'Brain' },
  { id: 'identity', label: 'Identity' },
]

const currentStep = ref(0)

// Basic Info
const selectedTemplate = ref('general')
const agentName = ref('')
const initialTask = ref('')
const behavior = ref('supervised')
const isEphemeral = ref(false)

// Brain Selection
const selectedBrain = ref('')
const availableBrains = ref<Array<{
  id: string
  provider: string
  providerName: string
  model: string
  name: string
  icon: string
}>>([])

// Identity Files
const identityFileTypes = [
  { type: 'IDENTITY', required: true },
  { type: 'SOUL', required: true },
  { type: 'USER', required: false },
  { type: 'AGENTS', required: false },
  { type: 'TOOLS', required: false },
  { type: 'HEARTBEAT', required: false },
  { type: 'BOOTSTRAP', required: false },
  { type: 'MEMORY', required: false },
]

const activeIdentityFile = ref('IDENTITY')
const identityContent = ref<Record<string, string>>({
  IDENTITY: '',
  SOUL: '',
  USER: '',
  AGENTS: '',
  TOOLS: '',
  HEARTBEAT: '',
  BOOTSTRAP: '',
  MEMORY: '',
})

const templates = [
  { id: 'general', name: 'General', icon: 'ph:robot', description: 'A flexible AI assistant for any task' },
  { id: 'writer', name: 'Writer', icon: 'ph:pen-nib', description: 'Creates content, copy, and documentation' },
  { id: 'analyst', name: 'Analyst', icon: 'ph:chart-line-up', description: 'Analyzes data and generates insights' },
  { id: 'researcher', name: 'Researcher', icon: 'ph:magnifying-glass', description: 'Finds and compiles information' },
  { id: 'creative', name: 'Creative', icon: 'ph:paint-brush', description: 'Designs visuals and creative assets' },
  { id: 'coder', name: 'Coder', icon: 'ph:code', description: 'Writes and reviews code' },
  { id: 'coordinator', name: 'Coordinator', icon: 'ph:users-three', description: 'Manages workflows and communication' },
  { id: 'workspace-manager', name: 'Workspace Manager', icon: 'ph:buildings', description: 'Manages workspace settings and configuration', comingSoon: true },
]

const isSpawning = ref(false)

// Load available brains from backend
onMounted(async () => {
  await loadAvailableBrains()
})

// Reset form when modal closes
watch(isOpen, (open) => {
  if (!open) {
    currentStep.value = 0
    agentName.value = ''
    initialTask.value = ''
    selectedTemplate.value = 'general'
    selectedBrain.value = ''
    behavior.value = 'supervised'
    isEphemeral.value = false
    activeIdentityFile.value = 'IDENTITY'
    identityContent.value = {
      IDENTITY: '',
      SOUL: '',
      USER: '',
      AGENTS: '',
      TOOLS: '',
      HEARTBEAT: '',
      BOOTSTRAP: '',
      MEMORY: '',
    }
  } else {
    loadAvailableBrains()
  }
})

const loadAvailableBrains = async () => {
  try {
    const response = await fetch('/api/integrations/models')
    if (response.ok) {
      availableBrains.value = await response.json()
      // Auto-select first brain if none selected
      if (availableBrains.value.length > 0 && !selectedBrain.value) {
        selectedBrain.value = availableBrains.value[0].id
      }
    }
  } catch (error) {
    console.error('Failed to load available brains:', error)
  }
}

// Can proceed to next step
const canProceed = computed(() => {
  if (currentStep.value === 0) {
    return selectedTemplate.value && agentName.value.trim()
  }
  if (currentStep.value === 1) {
    return selectedBrain.value && availableBrains.value.length > 0
  }
  return true
})

// Can spawn the agent
const canSpawn = computed(() => {
  return (
    selectedTemplate.value &&
    agentName.value.trim() &&
    selectedBrain.value &&
    identityContent.value.IDENTITY?.trim() &&
    identityContent.value.SOUL?.trim()
  )
})

const goToStep = (index: number) => {
  // Only allow going to completed steps or the next step
  if (index <= currentStep.value || (index === currentStep.value + 1 && canProceed.value)) {
    currentStep.value = index
  }
}

const fillWithTemplate = (type: string) => {
  identityContent.value[type] = getFilePlaceholder(type)
}

const getFileDescription = (type: string): string => {
  const descriptions: Record<string, string> = {
    IDENTITY: 'Basic info about the agent: name, emoji, theme, and vibe',
    SOUL: 'Core persona, values, behavior guidelines, and communication style',
    USER: 'Information about users this agent works with and their preferences',
    AGENTS: 'Knowledge of other agents in the system and how to collaborate',
    TOOLS: 'Available tools, APIs, and how to use them effectively',
    HEARTBEAT: 'Periodic check-in and status update instructions',
    BOOTSTRAP: 'Initialization sequence and startup tasks',
    MEMORY: 'Long-term memory, learnings, and context across conversations',
  }
  return descriptions[type] || ''
}

const getFilePlaceholder = (type: string): string => {
  const placeholders: Record<string, string> = {
    IDENTITY: `# Agent Identity

- **Name**: ${agentName.value || 'Agent Name'}
- **Template**: ${selectedTemplate.value}
- **Emoji**: 🤖
- **Theme**: Professional assistant
- **Vibe**: Helpful and efficient`,
    SOUL: `# Core Values

- Be helpful and accurate
- Communicate clearly
- Respect user privacy
- Admit uncertainty when unsure

# Behavior Guidelines

- Respond promptly and thoroughly
- Ask clarifying questions when needed
- Stay focused on the task at hand`,
    USER: `# User Context

## Preferences
(Document user preferences as they are discovered)

## Working Style
(Note how users prefer to interact)`,
    AGENTS: `# Agent Network

## Known Agents
(Document other agents and their specialties)

## Collaboration Notes
(How to work effectively with other agents)`,
    TOOLS: `# Available Tools

## Internal Tools
(Company systems and APIs)

## External Integrations
(Third-party services)`,
    HEARTBEAT: `# Heartbeat Configuration

## Check Interval
Every 15 minutes when active

## Status Checks
- Verify pending tasks
- Check for new messages
- Update availability status`,
    BOOTSTRAP: `# Bootstrap Sequence

## Startup Tasks
1. Load identity configuration
2. Review pending tasks
3. Check message queue
4. Update status to online`,
    MEMORY: `# Long-term Memory

## Key Learnings
(Auto-updated based on interactions)

## User Preferences
(Discovered preferences and working styles)`,
  }
  return placeholders[type] || ''
}

const handleSpawn = async () => {
  if (!canSpawn.value) return

  isSpawning.value = true

  try {
    // Create the agent via API
    const response = await fetch('/api/agents', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        name: agentName.value.trim(),
        agentType: selectedTemplate.value,
        brain: selectedBrain.value,
        task: initialTask.value.trim() || undefined,
        behavior: behavior.value,
        isEphemeral: isEphemeral.value,
        identity: identityContent.value,
      }),
    })

    if (!response.ok) {
      const error = await response.json()
      console.error('Failed to create agent:', error)
      return
    }

    const result = await response.json()

    emit('spawn', result)

    isOpen.value = false
  } catch (error) {
    console.error('Failed to spawn agent:', error)
  } finally {
    isSpawning.value = false
  }
}
</script>
