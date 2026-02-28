<template>
  <div class="tiptap-wrapper relative">
    <!-- Bubble Menu (appears on text selection) -->
    <BubbleMenu
      v-if="editor"
      :editor="editor"
      :tippy-options="{ duration: 150, maxWidth: 'none' }"
    >
      <BubbleMenuBar :editor="editor" />
    </BubbleMenu>

    <!-- Editor Content -->
    <EditorContent
      :editor="editor"
      class="tiptap-editor prose prose-neutral dark:prose-invert max-w-none"
    />
  </div>
</template>

<script setup lang="ts">
import { watch, onBeforeUnmount } from 'vue'
import { EditorContent } from '@tiptap/vue-3'
import { BubbleMenu } from '@tiptap/extension-bubble-menu'
import { useTipTapEditor } from '@/composables/useTipTapEditor'
import { marked } from 'marked'
import BubbleMenuBar from './tiptap/BubbleMenuBar.vue'

const props = withDefaults(defineProps<{
  content: string
  contentFormat?: 'markdown' | 'html'
  editable?: boolean
  placeholder?: string
}>(), {
  contentFormat: 'markdown',
  editable: true,
  placeholder: 'Start writing, or press / for commands...',
})

const emit = defineEmits<{
  update: [html: string]
}>()

const { editor } = useTipTapEditor({
  content: props.content,
  contentFormat: props.contentFormat,
  editable: props.editable,
  placeholder: props.placeholder,
  onUpdate: (html: string) => {
    emit('update', html)
  },
})

// When document changes (different doc selected), update editor content
watch(() => props.content, (newContent) => {
  if (!editor.value) return

  // Convert markdown to HTML if needed
  let html = newContent || ''
  if (props.contentFormat === 'markdown' && html) {
    try {
      html = marked.parse(html, { async: false }) as string
    } catch {
      // keep raw
    }
  }

  // Only update if content actually differs (prevents cursor jump)
  const currentHtml = editor.value.getHTML()
  if (currentHtml !== html) {
    editor.value.commands.setContent(html, false)
  }
})

watch(() => props.editable, (editable) => {
  editor.value?.setEditable(editable)
})

onBeforeUnmount(() => {
  editor.value?.destroy()
})

defineExpose({ editor })
</script>
