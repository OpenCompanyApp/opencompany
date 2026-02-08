<template>
  <div>
    <h3 class="text-sm font-medium text-neutral-900 dark:text-white mb-3 flex items-center gap-2">
      <Icon name="ph:tree-structure" class="w-4 h-4" />
      Execution Trace
      <span class="text-xs text-neutral-400 font-normal">({{ steps.length }} steps)</span>
    </h3>

    <div v-if="steps.length > 0" class="border border-neutral-200 dark:border-neutral-700 rounded-lg divide-y divide-neutral-200 dark:divide-neutral-700 overflow-hidden">
      <div v-for="step in steps" :key="step.id">
        <!-- Step Row -->
        <button
          class="w-full flex items-center gap-3 px-4 py-2.5 text-left hover:bg-neutral-50 dark:hover:bg-neutral-800/50 transition-colors"
          :class="{ 'cursor-default': !hasExpandableContent(step) }"
          @click="hasExpandableContent(step) && toggleStep(step.id)"
        >
          <!-- Status Icon -->
          <div
            :class="[
              'w-6 h-6 rounded-full flex items-center justify-center shrink-0',
              stepStatusClasses(step),
            ]"
          >
            <Icon :name="stepStatusIcon(step)" :class="['w-3.5 h-3.5', step.status === 'in_progress' && 'animate-spin']" />
          </div>

          <!-- Step Type Icon -->
          <Icon :name="stepTypeIcon(step)" class="w-4 h-4 text-neutral-400 shrink-0" />

          <!-- Description -->
          <span class="flex-1 text-sm text-neutral-900 dark:text-white truncate">
            {{ step.description }}
          </span>

          <!-- Duration badge -->
          <span v-if="stepDuration(step)" class="text-xs text-neutral-400 tabular-nums shrink-0 font-mono">
            {{ stepDuration(step) }}
          </span>

          <!-- Expand chevron -->
          <Icon
            v-if="hasExpandableContent(step)"
            :name="expandedSteps.has(step.id) ? 'ph:caret-up' : 'ph:caret-down'"
            class="w-4 h-4 text-neutral-400 shrink-0"
          />
        </button>

        <!-- Expandable Detail Panel -->
        <div
          v-if="expandedSteps.has(step.id) && hasExpandableContent(step)"
          class="px-4 py-3 bg-neutral-50 dark:bg-neutral-800/30 border-t border-neutral-100 dark:border-neutral-700/50 space-y-3"
        >
          <!-- Tool Arguments -->
          <div v-if="step.metadata?.arguments">
            <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider mb-1.5 block">Arguments</label>
            <pre class="text-xs bg-neutral-900 rounded-md p-3 overflow-x-auto border border-neutral-700 max-h-48 overflow-y-auto"><code class="hljs" v-html="highlightJson(step.metadata.arguments)" /></pre>
          </div>

          <!-- Tool Result -->
          <div v-if="step.metadata?.result !== undefined && step.metadata?.result !== null">
            <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider mb-1.5 block">Result</label>
            <!-- JSON result -->
            <pre v-if="isJsonResult(step.metadata.result)" class="text-xs bg-neutral-900 rounded-md p-3 overflow-x-auto border border-neutral-700 max-h-64 overflow-y-auto"><code class="hljs whitespace-pre-wrap break-words" v-html="highlightResult(step.metadata.result)" /></pre>
            <!-- Markdown/text result with raw/preview toggle -->
            <template v-else>
              <div class="flex justify-end mb-1.5">
                <div class="flex items-center gap-1 bg-neutral-100 dark:bg-neutral-800 rounded-md p-0.5">
                  <button
                    class="px-2 py-0.5 text-xs font-medium rounded transition-colors"
                    :class="resultViewModes[step.id] !== 'preview' ? 'bg-white dark:bg-neutral-700 text-neutral-900 dark:text-white shadow-sm' : 'text-neutral-500 dark:text-neutral-400'"
                    @click="resultViewModes[step.id] = 'raw'"
                  >Raw</button>
                  <button
                    class="px-2 py-0.5 text-xs font-medium rounded transition-colors"
                    :class="resultViewModes[step.id] === 'preview' ? 'bg-white dark:bg-neutral-700 text-neutral-900 dark:text-white shadow-sm' : 'text-neutral-500 dark:text-neutral-400'"
                    @click="resultViewModes[step.id] = 'preview'"
                  >Preview</button>
                </div>
              </div>
              <pre v-if="resultViewModes[step.id] !== 'preview'" class="text-xs bg-neutral-900 rounded-md p-3 overflow-x-auto border border-neutral-700 max-h-64 overflow-y-auto"><code class="hljs whitespace-pre-wrap break-words" v-html="highlight(String(step.metadata.result), 'markdown')" /></pre>
              <div v-else class="text-xs bg-white dark:bg-neutral-900 rounded-md p-3 overflow-x-auto border border-neutral-200 dark:border-neutral-700 max-h-64 overflow-y-auto prose prose-sm prose-neutral dark:prose-invert max-w-none" v-html="renderMarkdown(String(step.metadata.result))" />
            </template>
          </div>
        </div>
      </div>
    </div>

    <div v-else class="text-center py-8 text-sm text-neutral-400">
      No execution steps recorded
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue'
import type { TaskStep } from '@/types'
import Icon from '@/Components/shared/Icon.vue'
import { useHighlight } from '@/composables/useHighlight'
import { useMarkdown } from '@/composables/useMarkdown'

defineProps<{
  steps: TaskStep[]
}>()

const { highlight } = useHighlight()
const { renderMarkdown } = useMarkdown()

const expandedSteps = ref(new Set<string>())
const resultViewModes = reactive<Record<string, 'raw' | 'preview'>>({})

const toggleStep = (id: string) => {
  if (expandedSteps.value.has(id)) {
    expandedSteps.value.delete(id)
  } else {
    expandedSteps.value.add(id)
  }
}

const hasExpandableContent = (step: TaskStep): boolean => {
  return !!(step.metadata?.arguments || step.metadata?.result !== undefined && step.metadata?.result !== null)
}

const toolIconMap: Record<string, string> = {
  send_channel_message: 'ph:paper-plane-tilt',
  read_channel: 'ph:chat-circle',
  list_channels: 'ph:list',
  search_documents: 'ph:magnifying-glass',
  manage_document: 'ph:file-text',
  web_search: 'ph:globe',
  web_fetch: 'ph:download',
  render_vegalite: 'ph:chart-bar',
  render_svg: 'ph:file-svg',
  query_table: 'ph:table',
  manage_table_rows: 'ph:rows',
  get_tool_info: 'ph:info',
  update_current_task: 'ph:pencil-simple',
  manage_list_item: 'ph:check-square',
  request_approval: 'ph:shield-check',
  manage_calendar: 'ph:calendar',
  set_sleep_timer: 'ph:alarm',
  send_telegram: 'ph:telegram-logo',
}

const stepTypeIcon = (step: TaskStep): string => {
  if (step.metadata?.tool) {
    return toolIconMap[step.metadata.tool as string] ?? 'ph:wrench'
  }
  const icons: Record<string, string> = {
    action: 'ph:lightning',
    decision: 'ph:git-branch',
    approval: 'ph:shield-check',
    sub_task: 'ph:git-fork',
    message: 'ph:chat-circle',
  }
  return icons[step.stepType] || 'ph:circle'
}

const stepStatusIcon = (step: TaskStep): string => {
  switch (step.status) {
    case 'completed': return 'ph:check-bold'
    case 'in_progress': return 'ph:circle-notch'
    case 'skipped': return 'ph:minus'
    default: return 'ph:clock'
  }
}

const stepStatusClasses = (step: TaskStep): string => {
  switch (step.status) {
    case 'completed': return 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400'
    case 'in_progress': return 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400'
    case 'skipped': return 'bg-neutral-100 dark:bg-neutral-700 text-neutral-400'
    default: return 'bg-neutral-100 dark:bg-neutral-700 text-neutral-500'
  }
}

const stepDuration = (step: TaskStep): string | null => {
  if (!step.startedAt || !step.completedAt) return null
  const ms = new Date(step.completedAt).getTime() - new Date(step.startedAt).getTime()
  if (ms < 1000) return `${ms}ms`
  if (ms < 60000) return `${(ms / 1000).toFixed(1)}s`
  return `${Math.floor(ms / 60000)}m ${Math.floor((ms % 60000) / 1000)}s`
}

const formatJson = (obj: unknown): string => {
  try {
    return JSON.stringify(obj, null, 2)
  } catch {
    return String(obj)
  }
}

const highlightJson = (obj: unknown): string => {
  const json = formatJson(obj)
  return highlight(json, 'json')
}

const isJsonResult = (result: unknown): boolean => {
  if (typeof result !== 'string') return true // objects/arrays are always JSON
  const trimmed = result.trim()
  if ((trimmed.startsWith('{') && trimmed.endsWith('}')) || (trimmed.startsWith('[') && trimmed.endsWith(']'))) {
    try {
      JSON.parse(trimmed)
      return true
    } catch {
      return false
    }
  }
  return false
}

const highlightResult = (result: unknown): string => {
  if (typeof result === 'string') {
    // Try to detect if it's JSON
    const trimmed = result.trim()
    if ((trimmed.startsWith('{') && trimmed.endsWith('}')) || (trimmed.startsWith('[') && trimmed.endsWith(']'))) {
      try {
        const parsed = JSON.parse(trimmed)
        return highlight(JSON.stringify(parsed, null, 2), 'json')
      } catch {
        // Not valid JSON, fall through
      }
    }
    // Plain text — shouldn't reach here since isJsonResult gates the template, but fallback
    return result
  }
  return highlight(formatJson(result), 'json')
}
</script>
