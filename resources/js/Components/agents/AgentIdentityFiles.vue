<template>
  <div class="flex gap-4 min-h-[500px]">
    <!-- File List (Left Panel) -->
    <div class="w-56 shrink-0 space-y-1">
      <button
        v-for="file in orderedFiles"
        :key="file.type"
        type="button"
        :class="[
          'w-full flex items-start gap-3 px-3 py-2.5 rounded-lg text-left transition-colors',
          activeFile === file.type
            ? 'bg-neutral-100 dark:bg-neutral-800'
            : 'hover:bg-neutral-50 dark:hover:bg-neutral-800/50'
        ]"
        @click="selectFile(file.type)"
      >
        <Icon
          :name="FILE_META[file.type]?.icon ?? 'ph:file-md'"
          :class="[
            'w-4 h-4 mt-0.5 shrink-0',
            activeFile === file.type
              ? 'text-neutral-900 dark:text-white'
              : 'text-neutral-400 dark:text-neutral-500'
          ]"
        />
        <div class="min-w-0">
          <div :class="[
            'text-sm font-medium truncate',
            activeFile === file.type
              ? 'text-neutral-900 dark:text-white'
              : 'text-neutral-700 dark:text-neutral-300'
          ]">
            {{ FILE_META[file.type]?.label ?? file.type }}
          </div>
          <div class="text-xs text-neutral-400 dark:text-neutral-500 truncate">
            {{ FILE_META[file.type]?.description ?? file.title }}
          </div>
          <div v-if="drafts[file.type] !== undefined && drafts[file.type] !== getBaseContent(file.type)" class="flex items-center gap-1 mt-0.5">
            <span class="w-1.5 h-1.5 rounded-full bg-amber-500" />
            <span class="text-xs text-amber-500">Unsaved</span>
          </div>
        </div>
      </button>
    </div>

    <!-- Editor (Right Panel) -->
    <div class="flex-1 min-w-0">
      <template v-if="activeFile && activeEntry">
        <!-- Editor Header -->
        <div class="flex items-center justify-between mb-3">
          <div>
            <h3 class="text-sm font-medium text-neutral-900 dark:text-white">
              {{ FILE_META[activeFile]?.label ?? activeFile }}.md
            </h3>
            <p class="text-xs text-neutral-400 dark:text-neutral-500 mt-0.5">
              {{ FILE_META[activeFile]?.description }}
              <span v-if="activeEntry.updatedAt"> · Updated {{ formatDate(activeEntry.updatedAt) }}</span>
            </p>
          </div>
          <div class="flex items-center gap-2">
            <span v-if="isDirty" class="text-xs text-amber-500">Unsaved changes</span>
            <button
              v-if="isDirty"
              type="button"
              class="px-3 py-1.5 text-xs font-medium rounded-md text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors"
              @click="resetDraft"
            >
              Reset
            </button>
            <button
              v-if="isDirty"
              type="button"
              class="px-3 py-1.5 text-xs font-medium rounded-md bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 hover:bg-neutral-800 dark:hover:bg-neutral-100 transition-colors"
              :disabled="saving"
              @click="save"
            >
              {{ saving ? 'Saving...' : 'Save' }}
            </button>
          </div>
        </div>

        <!-- Editor Card -->
        <div class="bg-neutral-50 dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700 overflow-hidden">
          <!-- Edit/Preview Tabs -->
          <div class="flex border-b border-neutral-200 dark:border-neutral-700">
            <button
              type="button"
              :class="[
                'px-4 py-2 text-sm font-medium transition-colors',
                !showPreview
                  ? 'text-neutral-900 dark:text-white border-b-2 border-neutral-900 dark:border-white'
                  : 'text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-300'
              ]"
              @click="showPreview = false"
            >
              Edit
            </button>
            <button
              type="button"
              :class="[
                'px-4 py-2 text-sm font-medium transition-colors',
                showPreview
                  ? 'text-neutral-900 dark:text-white border-b-2 border-neutral-900 dark:border-white'
                  : 'text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-300'
              ]"
              @click="showPreview = true"
            >
              Preview
            </button>
          </div>

          <!-- Content -->
          <div class="p-4">
            <textarea
              v-if="!showPreview"
              v-model="currentDraft"
              rows="20"
              class="w-full bg-transparent text-sm text-neutral-900 dark:text-white font-mono focus:outline-none resize-none"
              :placeholder="FILE_META[activeFile]?.placeholder ?? 'Enter content...'"
            />
            <div
              v-else
              class="prose prose-neutral dark:prose-invert prose-sm max-w-none min-h-[30rem]"
              v-html="renderedContent"
            />
          </div>
        </div>
      </template>

      <!-- No File Selected -->
      <div v-else class="flex items-center justify-center h-full text-neutral-400 dark:text-neutral-500">
        <div class="text-center">
          <Icon name="ph:file-md" class="w-10 h-10 mx-auto mb-2 opacity-50" />
          <p class="text-sm">Select a file to edit</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { marked } from 'marked'
import Icon from '@/Components/shared/Icon.vue'

interface IdentityFile {
  type: string
  title: string
  content: string
  updatedAt: string
}

const props = defineProps<{
  files: IdentityFile[]
  saving?: boolean
}>()

const emit = defineEmits<{
  save: [fileType: string, content: string]
}>()

const FILE_META: Record<string, { label: string; description: string; icon: string; placeholder?: string }> = {
  IDENTITY: {
    label: 'Identity',
    description: 'Name, type, emoji, and appearance',
    icon: 'ph:identification-card',
    placeholder: '# Agent Identity\n\n- **Name**: ...\n- **Type**: ...\n- **Emoji**: ...\n- **Vibe**: ...',
  },
  SOUL: {
    label: 'Personality',
    description: 'Values, behavior guidelines, communication style',
    icon: 'ph:heart',
    placeholder: '# Core Values\n\n- Be helpful and accurate\n- Communicate clearly\n\n# Communication Style\n\n- Use clear, professional language',
  },
  USER: {
    label: 'User Context',
    description: 'Preferences and working style of the user',
    icon: 'ph:user',
    placeholder: '# User Context\n\n## Preferences\n(Document user preferences)\n\n## Working Style\n(Note how users prefer to interact)',
  },
  AGENTS: {
    label: 'Instructions',
    description: 'Operating manual, task guidelines, domain knowledge',
    icon: 'ph:book-open-text',
    placeholder: '# Instructions\n\n## Primary Responsibilities\n- ...\n\n## Workflow\n1. Understand the task\n2. Execute carefully\n3. Report results',
  },
  TOOLS: {
    label: 'Tool Notes',
    description: 'Configuration and usage notes for tools',
    icon: 'ph:wrench',
    placeholder: '# Available Tools\n\n## Usage Guidelines\n(Best practices for tool usage)',
  },
  MEMORY: {
    label: 'Long-term Memory',
    description: 'Persistent learnings and context',
    icon: 'ph:brain',
    placeholder: '# Long-term Memory\n\n## Key Learnings\n(Auto-updated based on interactions)\n\n## Important Context\n(Critical information to remember)',
  },
  HEARTBEAT: {
    label: 'Heartbeat',
    description: 'Periodic check-in and status update rules',
    icon: 'ph:heartbeat',
    placeholder: '# Heartbeat Configuration\n\n## Status Checks\n- Verify pending tasks\n- Check for new messages\n- Update availability status',
  },
  BOOTSTRAP: {
    label: 'Bootstrap',
    description: 'Initialization sequence and startup tasks',
    icon: 'ph:rocket-launch',
    placeholder: '# Bootstrap Sequence\n\n## Startup Tasks\n1. Load identity configuration\n2. Review pending tasks\n3. Update status to online',
  },
}

const FILE_ORDER = ['IDENTITY', 'SOUL', 'USER', 'AGENTS', 'TOOLS', 'MEMORY', 'HEARTBEAT', 'BOOTSTRAP']

const activeFile = ref<string | null>(null)
const showPreview = ref(false)
const drafts = ref<Record<string, string>>({})

// Initialize drafts from files
watch(() => props.files, (files) => {
  for (const file of files) {
    if (!(file.type in drafts.value)) {
      drafts.value[file.type] = file.content ?? ''
    }
  }
  // Auto-select first file if nothing selected
  if (!activeFile.value && files.length > 0) {
    const firstOrdered = FILE_ORDER.find(type => files.some(f => f.type === type))
    activeFile.value = firstOrdered ?? files[0].type
  }
}, { immediate: true })

const orderedFiles = computed(() => {
  return FILE_ORDER
    .map(type => props.files.find(f => f.type === type))
    .filter((f): f is IdentityFile => !!f)
})

const activeEntry = computed(() => {
  if (!activeFile.value) return null
  return props.files.find(f => f.type === activeFile.value) ?? null
})

const getBaseContent = (type: string) => {
  return props.files.find(f => f.type === type)?.content ?? ''
}

const currentDraft = computed({
  get: () => {
    if (!activeFile.value) return ''
    return drafts.value[activeFile.value] ?? getBaseContent(activeFile.value)
  },
  set: (value: string) => {
    if (!activeFile.value) return
    drafts.value[activeFile.value] = value
  },
})

const isDirty = computed(() => {
  if (!activeFile.value) return false
  return currentDraft.value !== getBaseContent(activeFile.value)
})

const renderedContent = computed(() => {
  if (!currentDraft.value) {
    return '<p class="text-neutral-400">No content</p>'
  }
  return marked(currentDraft.value)
})

const selectFile = (type: string) => {
  activeFile.value = type
  showPreview.value = false
}

const resetDraft = () => {
  if (!activeFile.value) return
  drafts.value[activeFile.value] = getBaseContent(activeFile.value)
}

const save = () => {
  if (!activeFile.value || !isDirty.value) return
  emit('save', activeFile.value, currentDraft.value)
  // Optimistically update the draft base (will be confirmed by parent re-fetching)
}

const formatDate = (dateStr: string): string => {
  const d = new Date(dateStr)
  if (isNaN(d.getTime())) return ''
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}
</script>
