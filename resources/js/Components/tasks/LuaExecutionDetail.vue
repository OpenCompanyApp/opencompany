<template>
  <div class="space-y-3">
    <!-- Lua Code (Monaco, read-only, lazy-loaded) -->
    <div>
      <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider mb-1.5 block">Lua Code</label>
      <div
        class="rounded-md overflow-hidden border border-neutral-200 dark:border-neutral-700"
        :style="{ height: editorHeight + 'px' }"
      >
        <Suspense>
          <MonacoEditor
            :model-value="code"
            language="lua"
            :readonly="true"
          />
          <template #fallback>
            <div class="h-full w-full bg-neutral-100 dark:bg-neutral-800 animate-pulse" />
          </template>
        </Suspense>
      </div>
    </div>

    <!-- Output -->
    <div>
      <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider mb-1.5 block">Output</label>
      <div class="rounded-md border border-neutral-200 dark:border-neutral-700 overflow-hidden bg-white dark:bg-[#1f1f1f]">
        <!-- Header bar -->
        <div class="flex items-center justify-between px-3 h-7 border-b border-neutral-200 dark:border-neutral-700/60 bg-neutral-50/80 dark:bg-neutral-800/50">
          <div class="flex items-center gap-2">
            <Icon name="ph:terminal" class="w-3.5 h-3.5 text-neutral-400 dark:text-neutral-500" />
            <span class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Result</span>
          </div>
          <div class="flex items-center gap-3 text-xs text-neutral-400 dark:text-neutral-500 tabular-nums font-mono">
            <span v-if="meta?.executionTime != null">{{ meta.executionTime }}ms</span>
            <span v-if="meta?.memoryUsage">{{ formatBytes(meta.memoryUsage) }}</span>
          </div>
        </div>
        <!-- Content -->
        <div class="max-h-64 overflow-y-auto">
          <pre v-if="meta" class="p-3 text-xs leading-5 font-mono whitespace-pre-wrap"><template v-if="meta.error"><span class="text-red-600 dark:text-red-400">{{ meta.error }}</span></template><template v-else>{{ meta.output || '(no output)' }}</template><template v-if="meta.returnValue != null">
<span class="text-neutral-400">→ {{ formatReturnValue(meta.returnValue) }}</span></template></pre>
          <pre v-else class="p-3 text-xs leading-5 font-mono whitespace-pre-wrap">{{ humanText }}</pre>
        </div>
      </div>
    </div>

    <!-- Bridge Activity -->
    <div v-if="meta?.bridgeCalls?.length">
      <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider mb-1.5 block">
        Bridge Calls
        <span class="text-neutral-400 font-normal">({{ meta.bridgeCalls.length }})</span>
      </label>
      <div class="border border-neutral-200 dark:border-neutral-700 rounded-md divide-y divide-neutral-100 dark:divide-neutral-700/50 overflow-hidden">
        <div
          v-for="(call, i) in meta.bridgeCalls"
          :key="i"
          class="flex items-center gap-2.5 px-3 py-1.5 bg-white dark:bg-neutral-900"
        >
          <Icon
            :name="call.status === 'ok' ? 'ph:check-circle' : 'ph:x-circle'"
            :class="['w-3.5 h-3.5 shrink-0', call.status === 'ok' ? 'text-green-500' : 'text-red-500']"
          />
          <code class="text-xs font-mono text-neutral-700 dark:text-neutral-300 flex-1 truncate">app.{{ call.path }}</code>
          <span v-if="call.error" class="text-xs text-red-500 dark:text-red-400 truncate max-w-48" :title="call.error">{{ call.error }}</span>
          <span class="text-xs text-neutral-400 tabular-nums font-mono shrink-0">{{ call.durationMs }}ms</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, defineAsyncComponent } from 'vue'
import Icon from '@/Components/shared/Icon.vue'

const MonacoEditor = defineAsyncComponent(() =>
  import('@/Components/developer/MonacoEditor.vue')
)

interface BridgeCall {
  path: string
  durationMs: number
  status: 'ok' | 'error'
  error?: string
}

interface LuaMeta {
  output: string
  error: string | null
  returnValue: unknown
  executionTime: number
  memoryUsage: number | null
  bridgeCalls: BridgeCall[]
}

const props = defineProps<{
  code: string
  result: string
  luaMeta?: LuaMeta | null
}>()

const LUA_META_RE = /<!--__LUA_META__(.*?)__LUA_META__-->/s

const parsed = computed(() => {
  // Prefer structured metadata from backend (survives truncation)
  if (props.luaMeta) {
    return { meta: props.luaMeta as LuaMeta, humanText: props.result }
  }
  // Legacy fallback: parse from result string
  const match = props.result.match(LUA_META_RE)
  if (match) {
    try {
      const meta: LuaMeta = JSON.parse(match[1])
      const humanText = props.result.replace(LUA_META_RE, '').trim()
      return { meta, humanText }
    } catch {
      // Corrupted metadata — fall back
    }
  }
  return { meta: null as LuaMeta | null, humanText: props.result }
})

const meta = computed(() => parsed.value.meta)
const humanText = computed(() => parsed.value.humanText)

const editorHeight = computed(() => {
  const lineCount = props.code.split('\n').length
  const height = lineCount * 20 + 24 // lineHeight 20px + padding 24px
  return Math.min(Math.max(height, 80), 400)
})

function formatBytes(bytes: number): string {
  if (bytes >= 1024 * 1024) return (bytes / 1024 / 1024).toFixed(1) + ' MB'
  if (bytes >= 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return bytes + ' B'
}

function formatReturnValue(value: unknown): string {
  if (value === null || value === undefined) return 'nil'
  if (typeof value === 'object') {
    try {
      return JSON.stringify(value, null, 2)
    } catch {
      return String(value)
    }
  }
  return String(value)
}
</script>
