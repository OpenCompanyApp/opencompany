<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <div>
        <h3 class="text-sm font-medium text-neutral-900 dark:text-white">Instructions</h3>
        <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
          Operating manual, task guidelines, and domain knowledge
        </p>
      </div>
      <div class="flex items-center gap-2">
        <span v-if="hasChanges" class="text-xs text-amber-500">Unsaved changes</span>
        <button
          v-if="hasChanges"
          type="button"
          class="px-3 py-1.5 text-xs font-medium rounded-md bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 hover:bg-neutral-800 dark:hover:bg-neutral-100 transition-colors"
          :disabled="saving"
          @click="save"
        >
          {{ saving ? 'Saving...' : 'Save' }}
        </button>
      </div>
    </div>

    <div class="bg-neutral-50 dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700 overflow-hidden">
      <!-- Tabs -->
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
          v-model="localContent"
          rows="16"
          class="w-full bg-transparent text-sm text-neutral-900 dark:text-white font-mono focus:outline-none resize-none"
          placeholder="## Primary Responsibilities
- Write clean, tested code
- Review pull requests from other agents
- Document technical decisions

## Workflow
1. Understand the task fully before starting
2. Break complex tasks into subtasks
3. Test changes locally before committing
4. Request review when complete

## Domain Knowledge
- Project uses TypeScript with strict mode
- API follows RESTful conventions
- Database migrations use sequential versioning"
        />
        <div
          v-else
          class="prose prose-neutral dark:prose-invert prose-sm max-w-none min-h-[24rem]"
          v-html="renderedContent"
        />
      </div>
    </div>

    <p v-if="instructions?.updatedAt" class="text-xs text-neutral-400 dark:text-neutral-500">
      Last updated {{ formatDate(instructions.updatedAt) }}
    </p>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { marked } from 'marked'
import type { AgentInstructions } from '@/types'

const props = defineProps<{
  instructions?: AgentInstructions
}>()

const emit = defineEmits<{
  save: [content: string]
}>()

const localContent = ref(props.instructions?.content || '')
const showPreview = ref(false)
const saving = ref(false)

watch(() => props.instructions?.content, (newContent) => {
  if (newContent !== undefined) {
    localContent.value = newContent
  }
})

const hasChanges = computed(() => {
  return localContent.value !== (props.instructions?.content || '')
})

const renderedContent = computed(() => {
  if (!localContent.value) {
    return '<p class="text-neutral-400">No instructions defined</p>'
  }
  return marked(localContent.value)
})

const formatDate = (date: Date | string): string => {
  const d = date instanceof Date ? date : new Date(date)
  if (isNaN(d.getTime())) return ''
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

const save = async () => {
  saving.value = true
  emit('save', localContent.value)
  await new Promise(resolve => setTimeout(resolve, 500))
  saving.value = false
}
</script>
