<template>
  <div class="space-y-3">
    <!-- JavaScript (Monaco, read-only, lazy-loaded) -->
    <div>
      <div class="flex items-center justify-between mb-1.5">
        <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">JavaScript</label>
        <div class="flex items-center gap-1">
          <button
            class="p-1 rounded text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors"
            title="Open in fullscreen"
            @click="showFullscreen = true"
          >
            <Icon name="ph:arrows-out" class="w-3.5 h-3.5" />
          </button>
          <button
            class="p-1 rounded text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors"
            title="Open in Code Console"
            @click="openInConsole"
          >
            <Icon name="ph:terminal" class="w-3.5 h-3.5" />
          </button>
        </div>
      </div>
      <div
        class="rounded-md overflow-hidden border border-neutral-200 dark:border-neutral-700"
        :style="{ height: editorHeight + 'px' }"
      >
        <Suspense>
          <MonacoEditor
            :model-value="code"
            language="javascript"
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
            <span v-if="meta?.executionTime != null">{{ meta.executionTime }}ms wall</span>
            <span v-if="meta?.cpuTime != null">{{ meta.cpuTime }}ms CPU</span>
            <span v-if="meta?.peakMemoryUsage">{{ formatBytes(meta.peakMemoryUsage) }} peak</span>
          </div>
        </div>
        <!-- Content -->
        <div class="max-h-64 overflow-y-auto">
          <pre v-if="meta" class="p-3 text-xs leading-5 font-mono whitespace-pre-wrap"><template v-if="meta.error"><span class="text-red-600 dark:text-red-400">{{ formatError(meta.error) }}</span><template v-if="meta.error.suggestion">
<span class="text-amber-600 dark:text-amber-400">Repair: {{ meta.error.suggestion }}</span></template></template><template v-else>{{ meta.output || '(no output)' }}</template><template v-if="meta.result != null">
<span class="text-neutral-400">→ {{ formatReturnValue(meta.result) }}</span></template></pre>
          <pre v-else class="p-3 text-xs leading-5 font-mono whitespace-pre-wrap">{{ humanText }}</pre>
        </div>
      </div>
    </div>

    <!-- Bridge Activity -->
    <div v-if="meta?.bridgeCalls?.length">
      <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider mb-1.5 block">
        Capability Calls
        <span class="text-neutral-400 font-normal">({{ meta.bridgeCalls.length }})</span>
      </label>
      <div class="border border-neutral-200 dark:border-neutral-700 rounded-md divide-y divide-neutral-100 dark:divide-neutral-700/50 overflow-hidden">
        <div
          v-for="(call, i) in meta.bridgeCalls"
          :key="i"
          class="flex items-center gap-2.5 px-3 py-1.5 bg-white dark:bg-neutral-900"
        >
          <Icon
            :name="bridgeCallIcon(call)"
            class="w-3.5 h-3.5 shrink-0 text-neutral-500 dark:text-neutral-400"
          />
          <span class="text-xs text-neutral-700 dark:text-neutral-300 flex-1 truncate">{{ call.name || call.path }}</span>
          <span v-if="call.error" class="text-xs text-red-500 dark:text-red-400 truncate max-w-48" :title="call.error">{{ call.error }}</span>
          <span v-if="call.effect === 'write'" :class="['text-[10px] uppercase', call.effectStatus === 'unknown' ? 'text-amber-500' : 'text-violet-500']">write · {{ call.effectStatus }}</span>
          <Icon
            :name="call.status === 'ok' ? 'ph:check-circle' : 'ph:x-circle'"
            :class="['w-3 h-3 shrink-0', call.status === 'ok' ? 'text-green-500' : 'text-red-500']"
          />
          <span class="text-xs text-neutral-400 tabular-nums font-mono shrink-0">{{ call.durationMs }}ms</span>
        </div>
      </div>
    </div>

    <!-- Fullscreen Modal -->
    <Modal v-model:open="showFullscreen" title="JavaScript / QuickJS" icon="ph:code" size="full">
      <div class="flex flex-col h-full gap-3">
        <!-- Code editor (fills available space) -->
        <div class="flex-1 min-h-0 rounded-md overflow-hidden border border-neutral-200 dark:border-neutral-700">
          <Suspense>
            <MonacoEditor
              :model-value="code"
              language="javascript"
              :readonly="true"
            />
            <template #fallback>
              <div class="h-full w-full bg-neutral-100 dark:bg-neutral-800 animate-pulse" />
            </template>
          </Suspense>
        </div>

        <!-- Output (collapsible) -->
        <div class="shrink-0 rounded-md border border-neutral-200 dark:border-neutral-700 overflow-hidden bg-white dark:bg-[#1f1f1f]">
          <button
            class="w-full flex items-center justify-between px-3 h-7 bg-neutral-50/80 dark:bg-neutral-800/50 hover:bg-neutral-100 dark:hover:bg-neutral-700/50 transition-colors"
            @click="modalResultOpen = !modalResultOpen"
          >
            <div class="flex items-center gap-2">
              <Icon :name="modalResultOpen ? 'ph:caret-down' : 'ph:caret-right'" class="w-3 h-3 text-neutral-400 dark:text-neutral-500" />
              <Icon name="ph:terminal" class="w-3.5 h-3.5 text-neutral-400 dark:text-neutral-500" />
              <span class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Result</span>
            </div>
            <div class="flex items-center gap-3 text-xs text-neutral-400 dark:text-neutral-500 tabular-nums font-mono">
              <span v-if="meta?.executionTime != null">{{ meta.executionTime }}ms wall</span>
              <span v-if="meta?.cpuTime != null">{{ meta.cpuTime }}ms CPU</span>
            </div>
          </button>
          <div v-if="modalResultOpen" class="max-h-48 overflow-y-auto border-t border-neutral-200 dark:border-neutral-700/60">
            <pre v-if="meta" class="p-3 text-xs leading-5 font-mono whitespace-pre-wrap"><template v-if="meta.error"><span class="text-red-600 dark:text-red-400">{{ formatError(meta.error) }}</span></template><template v-else>{{ meta.output || '(no output)' }}</template><template v-if="meta.result != null">
<span class="text-neutral-400">→ {{ formatReturnValue(meta.result) }}</span></template></pre>
            <pre v-else class="p-3 text-xs leading-5 font-mono whitespace-pre-wrap">{{ humanText }}</pre>
          </div>
        </div>

        <!-- Bridge calls in modal (collapsible) -->
        <div v-if="meta?.bridgeCalls?.length" class="shrink-0 rounded-md border border-neutral-200 dark:border-neutral-700 overflow-hidden">
          <button
            class="w-full flex items-center justify-between px-3 h-7 bg-neutral-50/80 dark:bg-neutral-800/50 hover:bg-neutral-100 dark:hover:bg-neutral-700/50 transition-colors"
            @click="modalBridgeOpen = !modalBridgeOpen"
          >
            <div class="flex items-center gap-2">
              <Icon :name="modalBridgeOpen ? 'ph:caret-down' : 'ph:caret-right'" class="w-3 h-3 text-neutral-400 dark:text-neutral-500" />
              <span class="text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">Capability Calls</span>
              <span class="text-xs text-neutral-400 font-normal">({{ meta.bridgeCalls.length }})</span>
            </div>
          </button>
          <div v-if="modalBridgeOpen" class="divide-y divide-neutral-100 dark:divide-neutral-700/50 max-h-32 overflow-y-auto border-t border-neutral-200 dark:border-neutral-700/60">
            <div
              v-for="(call, i) in meta.bridgeCalls"
              :key="i"
              class="flex items-center gap-2.5 px-3 py-1.5 bg-white dark:bg-neutral-900"
            >
              <Icon
                :name="bridgeCallIcon(call)"
                class="w-3.5 h-3.5 shrink-0 text-neutral-500 dark:text-neutral-400"
              />
              <span class="text-xs text-neutral-700 dark:text-neutral-300 flex-1 truncate">{{ call.name || call.path }}</span>
              <span v-if="call.error" class="text-xs text-red-500 dark:text-red-400 truncate max-w-48" :title="call.error">{{ call.error }}</span>
              <Icon
                :name="call.status === 'ok' ? 'ph:check-circle' : 'ph:x-circle'"
                :class="['w-3 h-3 shrink-0', call.status === 'ok' ? 'text-green-500' : 'text-red-500']"
              />
              <span class="text-xs text-neutral-400 tabular-nums font-mono shrink-0">{{ call.durationMs }}ms</span>
            </div>
          </div>
        </div>
      </div>
    </Modal>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, defineAsyncComponent } from 'vue'
import { router } from '@inertiajs/vue3'
import { useWorkspace } from '@/composables/useWorkspace'
import Icon from '@/Components/shared/Icon.vue'
import Modal from '@/Components/shared/Modal.vue'

const MonacoEditor = defineAsyncComponent(() =>
  import('@/Components/developer/MonacoEditor.vue')
)

const { developerCodeConsoleUrl } = useWorkspace()

interface BridgeCall {
  path: string
  durationMs: number
  status: 'ok' | 'error'
  error?: string
  icon?: string
  name?: string
  group?: string
  effect?: 'read' | 'write'
  effectStatus?: 'none' | 'succeeded' | 'unknown'
  retryable?: boolean
}

const GROUP_ICONS: Record<string, string> = {
  docs: 'ph:file-text', tables: 'ph:table', chat: 'ph:chat-circle',
  agents: 'ph:users-three', lists: 'ph:kanban', calendar: 'ph:calendar',
  memory: 'ph:brain', tasks: 'ph:list-checks', automations: 'ph:lightning',
  svg: 'ph:file-svg', system: 'ph:gear', workspace: 'ph:gear-six',
}

function bridgeCallIcon(call: BridgeCall): string {
  if (call.icon) return call.icon
  const parts = call.path?.split('.') ?? []
  const group = call.group || (parts[0] === 'integrations' && parts[1] ? parts[1] : parts[0]) || ''
  return GROUP_ICONS[group] ?? 'ph:wrench'
}

interface CodeMeta {
  executionId: string
  profile: string
  output: string
  error: {
    type: string
    message: string
    line?: number | null
    column?: number | null
    suggestion?: string
    retryable?: boolean
    effectStatus?: string
  } | null
  result: unknown
  executionTime: number
  cpuTime: number
  memoryUsage: number | null
  peakMemoryUsage: number | null
  bridgeCalls: BridgeCall[]
}

const props = defineProps<{
  code: string
  result: string
  codeMeta?: CodeMeta | null
}>()

const showFullscreen = ref(false)
const modalResultOpen = ref(false)
const modalBridgeOpen = ref(false)

const parsed = computed(() => {
  return { meta: props.codeMeta ?? null, humanText: props.result }
})

const meta = computed(() => parsed.value.meta)
const humanText = computed(() => parsed.value.humanText)

const editorHeight = computed(() => {
  const lineCount = props.code.split('\n').length
  const height = lineCount * 20 + 24 // lineHeight 20px + padding 24px
  return Math.min(Math.max(height, 80), 400)
})

function openInConsole() {
  sessionStorage.setItem('code-console-code', props.code)
  router.visit(developerCodeConsoleUrl())
}

function formatBytes(bytes: number): string {
  if (bytes >= 1024 * 1024) return (bytes / 1024 / 1024).toFixed(1) + ' MB'
  if (bytes >= 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return bytes + ' B'
}

function formatReturnValue(value: unknown): string {
  if (value === null || value === undefined) return 'null'
  if (typeof value === 'object') {
    try {
      return JSON.stringify(value, null, 2)
    } catch {
      return String(value)
    }
  }
  return String(value)
}

function formatError(error: NonNullable<CodeMeta['error']>): string {
  const location = error.line ? ` at ${error.line}${error.column ? `:${error.column}` : ''}` : ''
  return `[${error.type}]${location}: ${error.message}`
}
</script>
