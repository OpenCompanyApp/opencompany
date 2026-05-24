<template>
  <div class="mx-auto w-full max-w-3xl px-4 pb-4 md:pb-6">
    <div class="rounded-2xl border border-neutral-200 bg-white shadow-sm transition-colors dark:border-neutral-700 dark:bg-neutral-900">
      <div v-if="attachments.length" class="flex gap-2 overflow-x-auto border-b border-neutral-100 px-3 py-3 dark:border-neutral-800">
        <div
          v-for="attachment in attachments"
          :key="attachment.id"
          class="group flex max-w-48 items-center gap-2 rounded-xl border border-neutral-200 bg-neutral-50 px-3 py-2 text-left dark:border-neutral-700 dark:bg-neutral-800"
        >
          <Icon :name="attachmentIcon(attachment.type)" class="h-4 w-4 shrink-0 text-neutral-500 dark:text-neutral-300" />
          <div class="min-w-0">
            <p class="truncate text-xs font-medium text-neutral-900 dark:text-white">{{ attachment.name }}</p>
            <p class="text-[11px] text-neutral-500 dark:text-neutral-400">{{ formatFileSize(attachment.size) }}</p>
          </div>
          <button
            type="button"
            class="ml-1 rounded-md p-1 text-neutral-400 transition-colors hover:bg-neutral-200 hover:text-neutral-700 dark:hover:bg-neutral-700 dark:hover:text-white"
            @click="removeAttachment(attachment.id)"
          >
            <Icon name="ph:x" class="h-3.5 w-3.5" />
          </button>
        </div>
      </div>

      <textarea
        ref="textarea"
        v-model="prompt"
        :disabled="disabled"
        rows="1"
        class="max-h-52 min-h-20 w-full resize-none bg-transparent px-4 pt-4 text-[15px] leading-6 text-neutral-950 outline-none placeholder:text-neutral-400 disabled:opacity-60 dark:text-white dark:placeholder:text-neutral-500"
        :placeholder="placeholder"
        @input="handleInput"
        @keydown="handleKeydown"
      />

      <div class="flex flex-wrap items-center justify-between gap-2 px-3 pb-3">
        <div class="flex min-w-0 items-center gap-1.5">
          <input ref="fileInput" type="file" class="hidden" multiple @change="handleFileSelect">
          <button
            type="button"
            class="inline-flex h-9 w-9 items-center justify-center rounded-xl text-neutral-500 transition-colors hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:hover:text-white"
            title="Attach files"
            @click="fileInput?.click()"
          >
            <Icon name="ph:paperclip" class="h-5 w-5" />
          </button>
          <button
            type="button"
            class="inline-flex h-9 items-center gap-1.5 rounded-xl px-3 text-sm text-neutral-600 transition-colors hover:bg-neutral-100 hover:text-neutral-950 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:hover:text-white"
            title="Run memory compaction"
            @click="$emit('compact')"
          >
            <Icon name="ph:arrows-in-simple" class="h-4 w-4" />
            <span class="hidden sm:inline">Compact</span>
          </button>
          <button
            type="button"
            class="inline-flex h-9 items-center gap-1.5 rounded-xl px-3 text-sm text-neutral-600 transition-colors hover:bg-neutral-100 hover:text-neutral-950 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:hover:text-white"
            title="Show workspace status"
            @click="$emit('status')"
          >
            <Icon name="ph:pulse" class="h-4 w-4" />
            <span class="hidden sm:inline">Status</span>
          </button>
        </div>

        <div class="flex min-w-0 items-center gap-2">
          <select
            v-if="agents.length > 0"
            :value="selectedAgentId"
            class="h-9 max-w-44 rounded-xl border border-neutral-200 bg-white px-3 text-sm text-neutral-800 outline-none transition-colors hover:border-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100"
            @change="$emit('update:selectedAgentId', ($event.target as HTMLSelectElement).value)"
          >
            <option v-for="agent in agents" :key="agent.id" :value="agent.id">{{ agent.name }}</option>
          </select>

          <button
            v-if="running"
            type="button"
            class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-neutral-900 text-white transition-colors hover:bg-neutral-800 dark:bg-neutral-100 dark:text-neutral-900 dark:hover:bg-neutral-200"
            title="Stop response"
            @click="$emit('stop')"
          >
            <Icon name="ph:stop-fill" class="h-4 w-4" />
          </button>
          <button
            v-else
            type="button"
            class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-neutral-900 text-white transition-colors disabled:cursor-not-allowed disabled:bg-neutral-200 disabled:text-neutral-400 dark:bg-neutral-100 dark:text-neutral-900 dark:disabled:bg-neutral-800 dark:disabled:text-neutral-500"
            :disabled="!canSend"
            title="Send message"
            @click="send"
          >
            <Icon name="ph:arrow-up" class="h-4 w-4" />
          </button>
        </div>
      </div>
    </div>

    <p class="mt-2 px-2 text-center text-xs text-neutral-400 dark:text-neutral-500">
      OpenCompany can use tools and may ask for approval before making changes.
    </p>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, ref } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import type { User } from '@/types'

export interface ComposerAttachment {
  id: string
  file: File
  name: string
  type: string
  size: number
}

const props = withDefaults(defineProps<{
  agents: User[]
  selectedAgentId?: string
  disabled?: boolean
  running?: boolean
  placeholder?: string
}>(), {
  disabled: false,
  running: false,
  placeholder: 'Ask OpenCompany anything...',
})

const emit = defineEmits<{
  send: [content: string, attachments: ComposerAttachment[]]
  stop: []
  compact: []
  status: []
  'update:selectedAgentId': [agentId: string]
}>()

const prompt = ref('')
const attachments = ref<ComposerAttachment[]>([])
const textarea = ref<HTMLTextAreaElement | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)

const canSend = computed(() => (prompt.value.trim().length > 0 || attachments.value.length > 0) && !props.disabled)

const resize = () => {
  if (!textarea.value) return
  textarea.value.style.height = 'auto'
  textarea.value.style.height = `${Math.min(textarea.value.scrollHeight, 208)}px`
}

const handleInput = () => resize()

const handleKeydown = (event: KeyboardEvent) => {
  if (event.key === 'Enter' && !event.shiftKey) {
    event.preventDefault()
    send()
  }
}

const send = () => {
  if (!canSend.value) return
  emit('send', prompt.value.trim(), attachments.value)
  prompt.value = ''
  attachments.value = []
  if (fileInput.value) fileInput.value.value = ''
  nextTick(resize)
}

const handleFileSelect = (event: Event) => {
  const files = Array.from((event.target as HTMLInputElement).files ?? [])
  attachments.value = [
    ...attachments.value,
    ...files.map(file => ({
      id: `${file.name}-${file.lastModified}-${Math.random().toString(36).slice(2)}`,
      file,
      name: file.name,
      type: file.type || 'application/octet-stream',
      size: file.size,
    })),
  ]
}

const removeAttachment = (id: string) => {
  attachments.value = attachments.value.filter(attachment => attachment.id !== id)
}

const attachmentIcon = (type: string) => {
  if (type.startsWith('image/')) return 'ph:image'
  if (type.startsWith('audio/')) return 'ph:music-note'
  if (type.startsWith('video/')) return 'ph:video'
  if (type.includes('pdf')) return 'ph:file-pdf'
  return 'ph:file'
}

const formatFileSize = (bytes: number) => {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / 1024 / 1024).toFixed(1)} MB`
}
</script>
