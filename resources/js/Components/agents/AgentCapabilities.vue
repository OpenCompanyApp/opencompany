<template>
  <div class="space-y-6">
    <!-- Behavior Mode -->
    <section>
      <h3 class="text-sm font-medium text-neutral-900 dark:text-white mb-1">Behavior Mode</h3>
      <p class="text-xs text-neutral-500 dark:text-neutral-400 mb-3">Controls how much supervision this agent requires</p>
      <div class="bg-neutral-50 dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700 p-4">
        <div class="flex gap-2">
          <button
            v-for="mode in behaviorModes"
            :key="mode.value"
            type="button"
            :class="[
              'flex-1 px-3 py-2 text-sm font-medium rounded-lg transition-colors',
              localBehaviorMode === mode.value
                ? 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900'
                : 'bg-white dark:bg-neutral-700 text-neutral-600 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-600'
            ]"
            @click="handleBehaviorModeChange(mode.value)"
          >
            {{ mode.label }}
          </button>
        </div>
        <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-3">
          {{ behaviorModeDescription }}
        </p>
      </div>
    </section>

    <!-- Approval Behavior -->
    <section>
      <h3 class="text-sm font-medium text-neutral-900 dark:text-white mb-1">Approval Behavior</h3>
      <p class="text-xs text-neutral-500 dark:text-neutral-400 mb-3">Controls whether the agent must wait for approval decisions</p>
      <div class="bg-neutral-50 dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700 p-4">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-neutral-900 dark:text-white">Must wait for approvals</p>
            <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
              {{ localMustWait
                ? 'Agent always pauses when a tool requires approval'
                : 'Agent decides based on context whether to wait or continue' }}
            </p>
          </div>
          <button
            type="button"
            role="switch"
            :aria-checked="localMustWait"
            :class="[
              'relative inline-flex h-5 w-9 items-center rounded-full transition-colors',
              localMustWait
                ? 'bg-amber-500'
                : 'bg-neutral-300 dark:bg-neutral-600'
            ]"
            @click="handleMustWaitChange"
          >
            <span
              :class="[
                'inline-block h-3.5 w-3.5 rounded-full bg-white transition-transform',
                localMustWait ? 'translate-x-[18px]' : 'translate-x-[3px]'
              ]"
            />
          </button>
        </div>
      </div>
    </section>

    <!-- Tool Permissions -->
    <section>
      <h3 class="text-sm font-medium text-neutral-900 dark:text-white mb-1">Tool Permissions</h3>
      <p class="text-xs text-neutral-500 dark:text-neutral-400 mb-3">Control which tools this agent can use</p>
      <div class="bg-neutral-50 dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700 divide-y divide-neutral-200 dark:divide-neutral-700">
        <div
          v-for="tool in localTools"
          :key="tool.id"
          class="px-4 py-3 flex items-center gap-3"
        >
          <div
            :class="[
              'w-8 h-8 rounded-lg flex items-center justify-center shrink-0',
              tool.enabled
                ? 'bg-green-100 dark:bg-green-900/30'
                : 'bg-neutral-100 dark:bg-neutral-700'
            ]"
          >
            <Icon
              :name="tool.icon || 'ph:wrench'"
              :class="[
                'w-4 h-4',
                tool.enabled
                  ? 'text-green-600 dark:text-green-400'
                  : 'text-neutral-400 dark:text-neutral-500'
              ]"
            />
          </div>

          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
              <p class="text-sm font-medium text-neutral-900 dark:text-white">
                {{ tool.name }}
              </p>
              <span
                :class="[
                  'px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wider rounded',
                  tool.type === 'write'
                    ? 'bg-orange-100 dark:bg-orange-900/30 text-orange-600 dark:text-orange-400'
                    : 'bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400'
                ]"
              >
                {{ tool.type }}
              </span>
              <span
                v-if="tool.enabled && tool.requiresApproval"
                class="px-1.5 py-0.5 text-[10px] font-medium rounded bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400"
              >
                Approval required
              </span>
            </div>
            <p v-if="tool.description" class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
              {{ tool.description }}
            </p>
          </div>

          <div class="flex items-center gap-3 shrink-0">
            <!-- Require Approval toggle (only for enabled write tools) -->
            <label
              v-if="tool.enabled && tool.type === 'write'"
              class="flex items-center gap-1.5 cursor-pointer"
              title="Require approval before execution"
            >
              <span class="text-xs text-neutral-500 dark:text-neutral-400">Approval</span>
              <button
                type="button"
                role="switch"
                :aria-checked="tool.requiresApproval"
                :class="[
                  'relative inline-flex h-5 w-9 items-center rounded-full transition-colors',
                  tool.requiresApproval
                    ? 'bg-amber-500'
                    : 'bg-neutral-300 dark:bg-neutral-600'
                ]"
                @click="toggleApproval(tool)"
              >
                <span
                  :class="[
                    'inline-block h-3.5 w-3.5 rounded-full bg-white transition-transform',
                    tool.requiresApproval ? 'translate-x-[18px]' : 'translate-x-[3px]'
                  ]"
                />
              </button>
            </label>

            <!-- Enable/Disable toggle -->
            <button
              type="button"
              role="switch"
              :aria-checked="tool.enabled"
              :class="[
                'relative inline-flex h-5 w-9 items-center rounded-full transition-colors',
                tool.enabled
                  ? 'bg-green-500'
                  : 'bg-neutral-300 dark:bg-neutral-600'
              ]"
              @click="toggleTool(tool)"
            >
              <span
                :class="[
                  'inline-block h-3.5 w-3.5 rounded-full bg-white transition-transform',
                  tool.enabled ? 'translate-x-[18px]' : 'translate-x-[3px]'
                ]"
              />
            </button>
          </div>
        </div>

        <div v-if="localTools.length === 0" class="p-6 text-center">
          <Icon name="ph:wrench" class="w-8 h-8 text-neutral-300 dark:text-neutral-600 mx-auto mb-2" />
          <p class="text-sm text-neutral-500 dark:text-neutral-400">No tools available</p>
        </div>
      </div>

      <!-- Save button for tools -->
      <div v-if="toolsDirty" class="flex justify-end mt-2">
        <button
          type="button"
          class="px-3 py-1.5 text-xs font-medium rounded-md bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 hover:bg-neutral-800 dark:hover:bg-neutral-100 transition-colors"
          @click="saveToolPermissions"
        >
          Save tool permissions
        </button>
      </div>
    </section>

    <!-- Channel Access -->
    <section>
      <div class="flex items-center justify-between mb-1">
        <h3 class="text-sm font-medium text-neutral-900 dark:text-white">Channel Access</h3>
        <button
          type="button"
          class="text-xs text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200"
          @click="channelSectionOpen = !channelSectionOpen"
        >
          {{ channelSectionOpen ? 'Collapse' : 'Configure' }}
        </button>
      </div>
      <p class="text-xs text-neutral-500 dark:text-neutral-400 mb-3">
        {{ localChannelIds.length === 0
          ? 'Unrestricted — agent can send to all channels it belongs to'
          : `Restricted to ${localChannelIds.length} channel(s)` }}
      </p>

      <div v-if="channelSectionOpen" class="bg-neutral-50 dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700 p-4">
        <div class="flex items-center gap-2 mb-3">
          <button
            type="button"
            class="text-xs text-neutral-500 hover:text-neutral-700 dark:text-neutral-400 dark:hover:text-neutral-200"
            @click="selectAllChannels"
          >
            Select all
          </button>
          <span class="text-neutral-300 dark:text-neutral-600">|</span>
          <button
            type="button"
            class="text-xs text-neutral-500 hover:text-neutral-700 dark:text-neutral-400 dark:hover:text-neutral-200"
            @click="clearChannels"
          >
            Clear (unrestricted)
          </button>
        </div>
        <div class="space-y-2 max-h-48 overflow-y-auto">
          <label
            v-for="ch in agentChannels"
            :key="ch.id"
            class="flex items-center gap-2.5 cursor-pointer"
          >
            <input
              type="checkbox"
              :checked="localChannelIds.includes(ch.id)"
              class="rounded border-neutral-300 dark:border-neutral-600 text-neutral-900 dark:text-white"
              @change="toggleChannel(ch.id)"
            />
            <span class="text-sm text-neutral-700 dark:text-neutral-300">
              {{ ch.type === 'dm' ? ch.name : `#${ch.name}` }}
            </span>
          </label>
          <p v-if="agentChannels.length === 0" class="text-xs text-neutral-400 dark:text-neutral-500">
            Agent is not a member of any channels
          </p>
        </div>
        <div v-if="channelsDirty" class="flex justify-end mt-3">
          <button
            type="button"
            class="px-3 py-1.5 text-xs font-medium rounded-md bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 hover:bg-neutral-800 dark:hover:bg-neutral-100 transition-colors"
            @click="saveChannelPermissions"
          >
            Save channel access
          </button>
        </div>
      </div>
    </section>

    <!-- Document Folder Access -->
    <section>
      <div class="flex items-center justify-between mb-1">
        <h3 class="text-sm font-medium text-neutral-900 dark:text-white">Document Folder Access</h3>
        <button
          type="button"
          class="text-xs text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200"
          @click="folderSectionOpen = !folderSectionOpen"
        >
          {{ folderSectionOpen ? 'Collapse' : 'Configure' }}
        </button>
      </div>
      <p class="text-xs text-neutral-500 dark:text-neutral-400 mb-3">
        {{ localFolderIds.length === 0
          ? 'Unrestricted — agent can search all documents'
          : `Restricted to ${localFolderIds.length} folder(s)` }}
      </p>

      <div v-if="folderSectionOpen" class="bg-neutral-50 dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700 p-4">
        <div class="flex items-center gap-2 mb-3">
          <button
            type="button"
            class="text-xs text-neutral-500 hover:text-neutral-700 dark:text-neutral-400 dark:hover:text-neutral-200"
            @click="selectAllFolders"
          >
            Select all
          </button>
          <span class="text-neutral-300 dark:text-neutral-600">|</span>
          <button
            type="button"
            class="text-xs text-neutral-500 hover:text-neutral-700 dark:text-neutral-400 dark:hover:text-neutral-200"
            @click="clearFolders"
          >
            Clear (unrestricted)
          </button>
        </div>
        <div class="space-y-2 max-h-48 overflow-y-auto">
          <label
            v-for="folder in documentFolders"
            :key="folder.id"
            class="flex items-center gap-2.5 cursor-pointer"
          >
            <input
              type="checkbox"
              :checked="localFolderIds.includes(folder.id)"
              class="rounded border-neutral-300 dark:border-neutral-600 text-neutral-900 dark:text-white"
              @change="toggleFolder(folder.id)"
            />
            <Icon name="ph:folder" class="w-4 h-4 text-neutral-400 dark:text-neutral-500" />
            <span class="text-sm text-neutral-700 dark:text-neutral-300">{{ folder.title }}</span>
          </label>
          <p v-if="documentFolders.length === 0" class="text-xs text-neutral-400 dark:text-neutral-500">
            No document folders found
          </p>
        </div>
        <div v-if="foldersDirty" class="flex justify-end mt-3">
          <button
            type="button"
            class="px-3 py-1.5 text-xs font-medium rounded-md bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 hover:bg-neutral-800 dark:hover:bg-neutral-100 transition-colors"
            @click="saveFolderPermissions"
          >
            Save folder access
          </button>
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
import { ref, computed, watch } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import type { AgentCapability, AgentBehaviorMode } from '@/types'

interface ChannelInfo {
  id: string
  name: string
  type: string
}

interface FolderInfo {
  id: string
  title: string
}

const props = defineProps<{
  capabilities: AgentCapability[]
  notes?: string
  behaviorMode: AgentBehaviorMode
  mustWaitForApproval: boolean
  channelPermissions: string[]
  folderPermissions: string[]
  agentChannels: ChannelInfo[]
  documentFolders: FolderInfo[]
}>()

const emit = defineEmits<{
  updateBehaviorMode: [mode: AgentBehaviorMode]
  updateMustWaitForApproval: [value: boolean]
  updateToolPermissions: [tools: { scopeKey: string; permission: string; requiresApproval: boolean }[]]
  updateChannelPermissions: [channels: string[]]
  updateFolderPermissions: [folders: string[]]
  saveNotes: [notes: string]
}>()

// Local state
const localBehaviorMode = ref<AgentBehaviorMode>(props.behaviorMode)
const localMustWait = ref(props.mustWaitForApproval)
const localTools = ref<AgentCapability[]>(props.capabilities.map(c => ({ ...c })))
const localChannelIds = ref<string[]>([...props.channelPermissions])
const localFolderIds = ref<string[]>([...props.folderPermissions])
const editingNotes = ref(false)
const localNotes = ref(props.notes || '')
const channelSectionOpen = ref(false)
const folderSectionOpen = ref(false)

// Dirty tracking
const toolsDirty = ref(false)
const channelsDirty = ref(false)
const foldersDirty = ref(false)

// Watch for prop changes
watch(() => props.capabilities, (newCaps) => {
  localTools.value = newCaps.map(c => ({ ...c }))
  toolsDirty.value = false
}, { deep: true })

watch(() => props.channelPermissions, (newIds) => {
  localChannelIds.value = [...newIds]
  channelsDirty.value = false
})

watch(() => props.folderPermissions, (newIds) => {
  localFolderIds.value = [...newIds]
  foldersDirty.value = false
})

watch(() => props.behaviorMode, (newMode) => {
  localBehaviorMode.value = newMode
})

watch(() => props.mustWaitForApproval, (newVal) => {
  localMustWait.value = newVal
})

watch(() => props.notes, (newNotes) => {
  if (newNotes !== undefined) localNotes.value = newNotes
})

// Behavior mode
const behaviorModes: { value: AgentBehaviorMode; label: string }[] = [
  { value: 'autonomous', label: 'Autonomous' },
  { value: 'supervised', label: 'Supervised' },
  { value: 'strict', label: 'Strict' },
]

const behaviorModeDescription = computed(() => {
  switch (localBehaviorMode.value) {
    case 'autonomous':
      return 'Agent works independently. Only tools explicitly marked "require approval" will need human approval.'
    case 'supervised':
      return 'All write tools require approval before execution. Read tools execute freely.'
    case 'strict':
      return 'All tools require human approval before execution.'
    default:
      return ''
  }
})

const handleMustWaitChange = () => {
  localMustWait.value = !localMustWait.value
  emit('updateMustWaitForApproval', localMustWait.value)
}

const handleBehaviorModeChange = (mode: AgentBehaviorMode) => {
  localBehaviorMode.value = mode
  emit('updateBehaviorMode', mode)
}

// Tool toggles
const toggleTool = (tool: AgentCapability) => {
  tool.enabled = !tool.enabled
  if (!tool.enabled) tool.requiresApproval = false
  toolsDirty.value = true
}

const toggleApproval = (tool: AgentCapability) => {
  tool.requiresApproval = !tool.requiresApproval
  toolsDirty.value = true
}

const saveToolPermissions = () => {
  const tools = localTools.value.map(t => ({
    scopeKey: t.id,
    permission: t.enabled ? 'allow' : 'deny',
    requiresApproval: t.requiresApproval,
  }))
  emit('updateToolPermissions', tools)
  toolsDirty.value = false
}

// Channel toggles
const toggleChannel = (channelId: string) => {
  const idx = localChannelIds.value.indexOf(channelId)
  if (idx >= 0) {
    localChannelIds.value.splice(idx, 1)
  } else {
    localChannelIds.value.push(channelId)
  }
  channelsDirty.value = true
}

const selectAllChannels = () => {
  localChannelIds.value = props.agentChannels.map(ch => ch.id)
  channelsDirty.value = true
}

const clearChannels = () => {
  localChannelIds.value = []
  channelsDirty.value = true
}

const saveChannelPermissions = () => {
  emit('updateChannelPermissions', [...localChannelIds.value])
  channelsDirty.value = false
}

// Folder toggles
const toggleFolder = (folderId: string) => {
  const idx = localFolderIds.value.indexOf(folderId)
  if (idx >= 0) {
    localFolderIds.value.splice(idx, 1)
  } else {
    localFolderIds.value.push(folderId)
  }
  foldersDirty.value = true
}

const selectAllFolders = () => {
  localFolderIds.value = props.documentFolders.map(f => f.id)
  foldersDirty.value = true
}

const clearFolders = () => {
  localFolderIds.value = []
  foldersDirty.value = true
}

const saveFolderPermissions = () => {
  emit('updateFolderPermissions', [...localFolderIds.value])
  foldersDirty.value = false
}

// Tool notes
const cancelNotes = () => {
  localNotes.value = props.notes || ''
  editingNotes.value = false
}

const saveNotes = () => {
  emit('saveNotes', localNotes.value)
  editingNotes.value = false
}
</script>
