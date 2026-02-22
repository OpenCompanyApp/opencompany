<template>
  <div ref="container" class="h-full w-full" />
</template>

<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, watch, shallowRef } from 'vue'
import { setupMonaco, monaco } from '@/composables/useMonaco'
import { useColorMode } from '@/composables/useColorMode'

const props = withDefaults(defineProps<{
  modelValue?: string
  language?: string
  readonly?: boolean
}>(), {
  modelValue: '',
  language: 'lua',
  readonly: false,
})

const emit = defineEmits<{
  'update:modelValue': [value: string]
  'ready': [editor: monaco.editor.IStandaloneCodeEditor]
  'cursor-change': [line: number, column: number]
}>()

const { isDark } = useColorMode()
const container = ref<HTMLDivElement>()
const editor = shallowRef<monaco.editor.IStandaloneCodeEditor>()

function getTheme() {
  return isDark.value ? 'olympus-dark' : 'olympus-light'
}

onMounted(() => {
  if (!container.value) return

  setupMonaco()

  editor.value = monaco.editor.create(container.value, {
    value: props.modelValue,
    language: props.language,
    theme: getTheme(),
    readOnly: props.readonly,
    minimap: { enabled: false },
    fontSize: 13,
    lineHeight: 20,
    tabSize: 2,
    insertSpaces: true,
    scrollBeyondLastLine: false,
    bracketPairColorization: { enabled: true },
    fixedOverflowWidgets: true,
    cursorSmoothCaretAnimation: 'on',
    cursorBlinking: 'smooth',
    smoothScrolling: true,
    padding: { top: 12, bottom: 12 },
    renderLineHighlight: 'line',
    overviewRulerLanes: 0,
    hideCursorInOverviewRuler: true,
    overviewRulerBorder: false,
    scrollbar: {
      verticalScrollbarSize: 8,
      horizontalScrollbarSize: 8,
      useShadows: false,
    },
    wordWrap: 'off',
  })

  // Sync content changes back to parent
  editor.value.onDidChangeModelContent(() => {
    emit('update:modelValue', editor.value!.getValue())
  })

  // Track cursor position
  editor.value.onDidChangeCursorPosition((e) => {
    emit('cursor-change', e.position.lineNumber, e.position.column)
  })

  // ResizeObserver for proper layout when splitter is dragged
  const observer = new ResizeObserver(() => {
    editor.value?.layout()
  })
  observer.observe(container.value)

  onBeforeUnmount(() => {
    observer.disconnect()
    editor.value?.dispose()
  })

  emit('ready', editor.value)
})

// Sync external modelValue changes into the editor
watch(() => props.modelValue, (val) => {
  if (editor.value && val !== editor.value.getValue()) {
    editor.value.setValue(val)
  }
})

// Toggle theme with dark mode
watch(isDark, () => {
  monaco.editor.setTheme(getTheme())
})

defineExpose({
  getEditor: () => editor.value,
})
</script>
