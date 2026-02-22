<template>
  <div class="h-full overflow-hidden flex flex-col">
    <div class="max-w-5xl mx-auto w-full p-4 md:p-6 flex flex-col flex-1 min-h-0">
      <!-- Header -->
      <header class="mb-4 md:mb-6 shrink-0">
        <div class="flex items-center justify-between gap-4">
          <div>
            <div class="flex items-center gap-3">
              <Link
                :href="workspacePath('/integrations')"
                class="text-sm text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 transition-colors"
              >
                <Icon name="ph:arrow-left" class="w-4 h-4" />
              </Link>
              <h1 class="text-xl font-semibold text-neutral-900 dark:text-white">Lua Console</h1>
            </div>
            <p class="text-sm text-neutral-500 dark:text-neutral-400 mt-1 ml-7">
              Execute Lua 5.1 code in a sandboxed environment
            </p>
          </div>

          <!-- Run button -->
          <button
            type="button"
            class="flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 hover:bg-neutral-800 dark:hover:bg-neutral-100 transition-colors disabled:opacity-50"
            :disabled="running || !code.trim()"
            @click="execute"
          >
            <Icon :name="running ? 'ph:spinner' : 'ph:play-fill'" :class="['w-4 h-4', running && 'animate-spin']" />
            {{ running ? 'Running...' : 'Run' }}
          </button>
        </div>
      </header>

      <!-- Editor + Output -->
      <div class="flex-1 flex flex-col gap-4 min-h-0">
        <!-- Code Editor -->
        <div class="flex-1 min-h-0 flex flex-col rounded-lg border border-neutral-200 dark:border-neutral-700 overflow-hidden">
          <div class="flex items-center justify-between px-3 py-1.5 bg-neutral-50 dark:bg-neutral-800 border-b border-neutral-200 dark:border-neutral-700">
            <span class="text-xs text-neutral-500 dark:text-neutral-400 font-mono">lua</span>
            <span class="text-xs text-neutral-400 dark:text-neutral-500">
              <kbd class="px-1 py-0.5 rounded bg-neutral-200 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-300 font-mono text-[10px]">{{ metaKey }}+Enter</kbd>
              to run
            </span>
          </div>
          <textarea
            ref="editorRef"
            v-model="code"
            class="flex-1 w-full p-4 font-mono text-sm bg-white dark:bg-neutral-900 text-neutral-900 dark:text-neutral-100 resize-none focus:outline-none placeholder-neutral-400 dark:placeholder-neutral-600"
            placeholder="-- Write Lua code here&#10;print('Hello, World!')"
            spellcheck="false"
            autocomplete="off"
            autocorrect="off"
            autocapitalize="off"
            @keydown="handleKeydown"
          />
        </div>

        <!-- Output Panel -->
        <div
          v-if="hasOutput"
          class="shrink-0 max-h-64 flex flex-col rounded-lg border border-neutral-200 dark:border-neutral-700 overflow-hidden"
        >
          <div class="flex items-center justify-between px-3 py-1.5 bg-neutral-50 dark:bg-neutral-800 border-b border-neutral-200 dark:border-neutral-700">
            <div class="flex items-center gap-2">
              <Icon name="ph:terminal" class="w-3.5 h-3.5 text-neutral-500 dark:text-neutral-400" />
              <span class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Output</span>
            </div>
            <div class="flex items-center gap-3 text-xs text-neutral-400 dark:text-neutral-500">
              <span v-if="lastResult?.executionTime != null">{{ lastResult.executionTime }}ms</span>
              <span v-if="lastResult?.memoryUsage">{{ formatBytes(lastResult.memoryUsage) }}</span>
              <button
                type="button"
                class="hover:text-neutral-600 dark:hover:text-neutral-300 transition-colors"
                @click="clearOutput"
              >
                <Icon name="ph:x" class="w-3.5 h-3.5" />
              </button>
            </div>
          </div>
          <pre class="flex-1 overflow-auto p-4 text-sm font-mono"><template v-if="lastResult?.error"><span class="text-red-600 dark:text-red-400">{{ lastResult.error }}</span></template><template v-else>{{ lastResult?.output || '(no output)' }}</template></pre>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import axios from 'axios'
import Icon from '@/Components/shared/Icon.vue'
import { useWorkspace } from '@/composables/useWorkspace'

const { workspacePath } = useWorkspace()

const editorRef = ref<HTMLTextAreaElement | null>(null)
const code = ref('')
const running = ref(false)
const lastResult = ref<{
  output?: string
  error?: string
  result?: any
  executionTime?: number
  memoryUsage?: number
} | null>(null)

const metaKey = navigator.platform.includes('Mac') ? '\u2318' : 'Ctrl'

const hasOutput = computed(() => lastResult.value !== null)

async function execute() {
  if (running.value || !code.value.trim()) return

  running.value = true
  try {
    const { data } = await axios.post('/api/lua/execute', { code: code.value })
    lastResult.value = data
  } catch (err: any) {
    lastResult.value = {
      error: err.response?.data?.message || err.message || 'Request failed',
    }
  } finally {
    running.value = false
  }
}

function clearOutput() {
  lastResult.value = null
}

function handleKeydown(e: KeyboardEvent) {
  // Cmd/Ctrl+Enter to run
  if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) {
    e.preventDefault()
    execute()
    return
  }

  // Tab to indent
  if (e.key === 'Tab') {
    e.preventDefault()
    const textarea = editorRef.value
    if (!textarea) return
    const start = textarea.selectionStart
    const end = textarea.selectionEnd
    code.value = code.value.substring(0, start) + '  ' + code.value.substring(end)
    nextTick(() => {
      textarea.selectionStart = textarea.selectionEnd = start + 2
    })
  }
}

function formatBytes(bytes: number): string {
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}

// Need nextTick for tab handling
import { nextTick } from 'vue'
</script>
