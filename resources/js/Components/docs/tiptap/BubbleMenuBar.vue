<template>
  <div class="flex items-center gap-0.5 px-1 py-1 bg-white dark:bg-neutral-800 rounded-lg border border-neutral-200 dark:border-neutral-700 shadow-lg">
    <button
      v-for="action in actions"
      :key="action.id"
      type="button"
      :class="[
        'p-1.5 rounded-md transition-colors duration-100',
        isActive(action)
          ? 'bg-neutral-100 dark:bg-neutral-700 text-neutral-900 dark:text-white'
          : 'text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 hover:bg-neutral-50 dark:hover:bg-neutral-700',
      ]"
      :title="action.label"
      @click="action.command"
    >
      <Icon :name="action.icon" class="w-4 h-4" />
    </button>

    <!-- Separator -->
    <div class="w-px h-4 bg-neutral-200 dark:bg-neutral-600 mx-0.5" />

    <!-- Link button -->
    <button
      type="button"
      :class="[
        'p-1.5 rounded-md transition-colors duration-100',
        editor.isActive('link')
          ? 'bg-neutral-100 dark:bg-neutral-700 text-neutral-900 dark:text-white'
          : 'text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 hover:bg-neutral-50 dark:hover:bg-neutral-700',
      ]"
      title="Link"
      @click="handleLink"
    >
      <Icon name="ph:link" class="w-4 h-4" />
    </button>

    <!-- Separator -->
    <div class="w-px h-4 bg-neutral-200 dark:bg-neutral-600 mx-0.5" />

    <!-- Clear formatting -->
    <button
      type="button"
      class="p-1.5 rounded-md text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 hover:bg-neutral-50 dark:hover:bg-neutral-700 transition-colors duration-100"
      title="Clear formatting"
      @click="editor.chain().focus().unsetAllMarks().run()"
    >
      <Icon name="ph:text-strikethrough" class="w-4 h-4" />
    </button>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { Editor } from '@tiptap/vue-3'
import Icon from '@/Components/shared/Icon.vue'

const props = defineProps<{
  editor: Editor
}>()

interface FormatAction {
  id: string
  icon: string
  label: string
  command: () => void
  activeCheck?: string
}

const actions = computed<FormatAction[]>(() => [
  {
    id: 'bold',
    icon: 'ph:text-b',
    label: 'Bold',
    command: () => props.editor.chain().focus().toggleBold().run(),
    activeCheck: 'bold',
  },
  {
    id: 'italic',
    icon: 'ph:text-italic',
    label: 'Italic',
    command: () => props.editor.chain().focus().toggleItalic().run(),
    activeCheck: 'italic',
  },
  {
    id: 'underline',
    icon: 'ph:text-underline',
    label: 'Underline',
    command: () => props.editor.chain().focus().toggleUnderline().run(),
    activeCheck: 'underline',
  },
  {
    id: 'strike',
    icon: 'ph:text-strikethrough',
    label: 'Strikethrough',
    command: () => props.editor.chain().focus().toggleStrike().run(),
    activeCheck: 'strike',
  },
  {
    id: 'code',
    icon: 'ph:code',
    label: 'Inline code',
    command: () => props.editor.chain().focus().toggleCode().run(),
    activeCheck: 'code',
  },
  {
    id: 'highlight',
    icon: 'ph:highlighter',
    label: 'Highlight',
    command: () => props.editor.chain().focus().toggleHighlight().run(),
    activeCheck: 'highlight',
  },
])

const isActive = (action: FormatAction) => {
  if (!action.activeCheck) return false
  return props.editor.isActive(action.activeCheck)
}

const handleLink = () => {
  const previousUrl = props.editor.getAttributes('link').href
  const url = window.prompt('URL', previousUrl || 'https://')

  if (url === null) return

  if (url === '') {
    props.editor.chain().focus().extendMarkRange('link').unsetLink().run()
    return
  }

  props.editor.chain().focus().extendMarkRange('link').setLink({ href: url }).run()
}
</script>
