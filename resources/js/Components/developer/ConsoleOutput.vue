<template>
  <div class="h-full flex flex-col bg-white dark:bg-[#1f1f1f] min-h-0">
    <!-- Header bar -->
    <div class="flex items-center justify-between px-3 h-8 shrink-0 border-b border-neutral-200 dark:border-neutral-700/60 bg-neutral-50/80 dark:bg-neutral-800/50">
      <div class="flex items-center gap-2">
        <Icon name="ph:terminal" class="w-3.5 h-3.5 text-neutral-400 dark:text-neutral-500" />
        <span class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Output</span>
      </div>
      <div class="flex items-center gap-3 text-xs text-neutral-400 dark:text-neutral-500">
        <span v-if="result?.executionTime != null">{{ result.executionTime }}ms</span>
        <span v-if="result?.memoryUsage">{{ formatBytes(result.memoryUsage) }}</span>
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
      <pre v-if="result" class="p-3 text-[13px] leading-5 font-mono whitespace-pre-wrap"><template v-if="result.error"><span class="text-red-600 dark:text-red-400">{{ result.error }}</span></template><template v-else>{{ result.output || '(no output)' }}</template></pre>
      <div v-else class="p-3 text-[13px] text-neutral-400 dark:text-neutral-500 font-mono italic">
        Run code to see output here...
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import Icon from '@/Components/shared/Icon.vue'

defineProps<{
  result?: {
    output?: string
    error?: string
    result?: any
    executionTime?: number
    memoryUsage?: number
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
</script>
