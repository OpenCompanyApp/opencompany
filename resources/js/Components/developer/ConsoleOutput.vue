<template>
  <div class="h-full flex flex-col bg-white dark:bg-[#1f1f1f] min-h-0">
    <!-- Header bar -->
    <div class="flex items-center justify-between px-3 h-8 shrink-0 border-b border-neutral-200 dark:border-neutral-700/60 bg-neutral-50/80 dark:bg-neutral-800/50">
      <div class="flex items-center gap-2">
        <Icon name="ph:terminal" class="w-3.5 h-3.5 text-neutral-400 dark:text-neutral-500" />
        <span class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Output</span>
      </div>
      <div class="flex items-center gap-3 text-xs text-neutral-400 dark:text-neutral-500">
        <span v-if="result?.executionTime != null">{{ result.executionTime }}ms wall</span>
        <span v-if="result?.cpuTime != null">{{ result.cpuTime }}ms CPU</span>
        <span v-if="result?.peakMemoryUsage">{{ formatBytes(result.peakMemoryUsage) }} peak</span>
        <button
          v-if="result"
          type="button"
          class="hover:text-neutral-600 dark:hover:text-neutral-300 transition-colors"
          @click="emit('clear')"
        >
          <Icon name="ph:x" class="w-3.5 h-3.5" />
        </button>
      </div>
    </div>

    <!-- Content -->
    <div class="flex-1 overflow-auto min-h-0">
      <div v-if="result" class="p-3 text-[13px] leading-5 font-mono whitespace-pre-wrap space-y-3">
        <div v-if="result.validatedOnly && !result.error" class="text-emerald-600 dark:text-emerald-400">Validation passed. Nothing was executed.</div>
        <div v-if="result.error" class="text-red-600 dark:text-red-400">
          <template v-if="typeof result.error === 'string'">{{ result.error }}</template>
          <template v-else>
            <div>{{ formatError(result.error) }}</div>
            <div v-if="result.error.suggestion" class="mt-2 text-amber-600 dark:text-amber-400">Repair: {{ result.error.suggestion }}</div>
            <div class="mt-1 text-neutral-500 dark:text-neutral-400">Retryable: {{ result.error.retryable ? 'yes' : 'no' }} · writes: {{ result.error.effectStatus || 'none' }}</div>
          </template>
        </div>
        <div v-if="result.output">{{ result.output }}</div>
        <div v-if="result.result != null" class="text-neutral-700 dark:text-neutral-200">Return: {{ formatValue(result.result) }}</div>
        <div v-if="!result.error && !result.output && result.result == null && !result.validatedOnly" class="text-neutral-400">(no output)</div>
        <div v-if="result.executionId" class="text-[11px] text-neutral-400">Execution {{ result.executionId }}</div>
      </div>
      <div v-else class="p-3 text-[13px] text-neutral-400 dark:text-neutral-500 font-mono italic">
        Run code to see output here...
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import Icon from '@/Components/shared/Icon.vue'

interface ConsoleError {
  type: string
  message: string
  line?: number | null
  column?: number | null
  suggestion?: string
  retryable?: boolean
  effectStatus?: string
}

defineProps<{
  result?: {
    output?: string
    executionId?: string
    validatedOnly?: boolean
    error?: string | ConsoleError
    result?: any
    executionTime?: number
    cpuTime?: number
    memoryUsage?: number
    peakMemoryUsage?: number
  } | null
}>()

const emit = defineEmits<{
  clear: []
}>()

function formatBytes(bytes: number): string {
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}

function formatError(error: ConsoleError): string {
  const location = error.line ? ` at ${error.line}${error.column ? `:${error.column}` : ''}` : ''
  return `[${error.type}]${location}: ${error.message}`
}

function formatValue(value: unknown): string {
  if (typeof value === 'string') return value
  try {
    return JSON.stringify(value, null, 2)
  } catch {
    return String(value)
  }
}
</script>
