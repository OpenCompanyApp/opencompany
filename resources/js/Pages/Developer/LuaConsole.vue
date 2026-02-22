<template>
  <div class="h-full flex flex-col overflow-hidden bg-neutral-50 dark:bg-[#181818]">
    <!-- Toolbar -->
    <div class="flex items-center justify-between h-10 px-3 shrink-0 border-b border-neutral-200 dark:border-neutral-700/60 bg-white dark:bg-[#1f1f1f]">
      <div class="flex items-center gap-3">
        <Link
          :href="workspacePath('/developer')"
          class="text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 transition-colors"
        >
          <Icon name="ph:arrow-left" class="w-4 h-4" />
        </Link>
        <h1 class="text-sm font-semibold text-neutral-900 dark:text-white">Lua Console</h1>
        <span class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-violet-100 dark:bg-violet-900/40 text-violet-600 dark:text-violet-400 leading-none">
          Luau
        </span>
      </div>

      <div class="flex items-center gap-2">
        <span class="text-xs text-neutral-400 dark:text-neutral-500 hidden sm:block">
          <kbd class="px-1 py-0.5 rounded bg-neutral-100 dark:bg-neutral-700 text-neutral-500 dark:text-neutral-400 font-mono text-[10px]">{{ metaKey }}+Enter</kbd>
        </span>
        <Button
          size="sm"
          :icon-left="running ? 'ph:spinner' : 'ph:play-fill'"
          :loading="running"
          :disabled="!code.trim()"
          @click="execute"
        >
          Run
        </Button>
      </div>
    </div>

    <!-- Editor + Output splitter -->
    <SplitterGroup direction="vertical" auto-save-id="lua-console" class="flex-1 min-h-0">
      <!-- Editor panel -->
      <SplitterPanel :default-size="70" :min-size="25">
        <MonacoEditor
          v-model="code"
          language="lua"
          @ready="onEditorReady"
          @cursor-change="onCursorChange"
        />
      </SplitterPanel>

      <!-- Resize handle -->
      <SplitterResizeHandle class="group relative h-[3px] shrink-0 bg-neutral-200 dark:bg-neutral-700/60 hover:bg-blue-400 dark:hover:bg-blue-500 transition-colors data-[state=drag]:bg-blue-500 dark:data-[state=drag]:bg-blue-400" />

      <!-- Output panel -->
      <SplitterPanel :default-size="30" :min-size="10">
        <ConsoleOutput :result="lastResult" @clear="lastResult = null" />
      </SplitterPanel>
    </SplitterGroup>

    <!-- Status bar -->
    <div class="flex items-center h-6 px-3 shrink-0 border-t border-neutral-200 dark:border-neutral-700/60 bg-white dark:bg-[#1f1f1f] text-[11px] text-neutral-400 dark:text-neutral-500 gap-3 select-none">
      <span class="font-medium">Luau</span>
      <span class="w-px h-3 bg-neutral-200 dark:bg-neutral-700" />
      <span>Ln {{ cursorLine }}, Col {{ cursorColumn }}</span>
      <span class="w-px h-3 bg-neutral-200 dark:bg-neutral-700" />
      <span>Spaces: 2</span>
      <div class="flex-1" />
      <template v-if="lastResult?.executionTime != null">
        <span>{{ lastResult.executionTime }}ms</span>
        <span class="w-px h-3 bg-neutral-200 dark:bg-neutral-700" />
      </template>
      <span :class="running ? 'text-amber-500' : lastResult?.error ? 'text-red-400' : 'text-emerald-500'">
        {{ running ? 'Running...' : lastResult?.error ? 'Error' : 'Ready' }}
      </span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import axios from 'axios'
import { SplitterGroup, SplitterPanel, SplitterResizeHandle } from 'reka-ui'
import Icon from '@/Components/shared/Icon.vue'
import Button from '@/Components/shared/Button.vue'
import MonacoEditor from '@/Components/developer/MonacoEditor.vue'
import ConsoleOutput from '@/Components/developer/ConsoleOutput.vue'
import { useWorkspace } from '@/composables/useWorkspace'
import type { editor as MonacoEditorType } from 'monaco-editor'

const { workspacePath } = useWorkspace()

const code = ref('')
const running = ref(false)
const cursorLine = ref(1)
const cursorColumn = ref(1)
const lastResult = ref<{
  output?: string
  error?: string
  result?: any
  executionTime?: number
  memoryUsage?: number
} | null>(null)

const metaKey = navigator.platform.includes('Mac') ? '\u2318' : 'Ctrl'

let editorInstance: MonacoEditorType.IStandaloneCodeEditor | null = null

function onEditorReady(editor: MonacoEditorType.IStandaloneCodeEditor) {
  editorInstance = editor

  // Register Cmd/Ctrl+Enter to run
  editor.addAction({
    id: 'lua-execute',
    label: 'Run Lua Code',
    keybindings: [
      // Monaco KeyMod.CtrlCmd | Monaco KeyCode.Enter
      2048 | 3,
    ],
    run: () => execute(),
  })
}

function onCursorChange(line: number, column: number) {
  cursorLine.value = line
  cursorColumn.value = column
}

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
    editorInstance?.focus()
  }
}
</script>
