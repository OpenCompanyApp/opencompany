<template>
  <article :class="containerClasses">
    <div class="mx-auto flex w-full max-w-3xl gap-3 px-4 py-5">
      <SharedAgentAvatar
        v-if="!isUser"
        :user="message.author"
        size="md"
        :show-status="message.author?.type === 'agent'"
        class="mt-0.5 shrink-0"
      />
      <div
        v-else
        class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-neutral-900 text-xs font-semibold text-white dark:bg-neutral-100 dark:text-neutral-900"
      >
        {{ userInitials }}
      </div>

      <div class="min-w-0 flex-1">
        <div class="mb-2 flex flex-wrap items-center gap-2">
          <span class="text-sm font-semibold text-neutral-950 dark:text-white">{{ authorName }}</span>
          <span v-if="message.author?.type === 'agent'" class="inline-flex items-center gap-1 rounded-full bg-neutral-100 px-2 py-0.5 text-[11px] font-medium text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300">
            <Icon name="ph:sparkle" class="h-3 w-3" />
            Assistant
          </span>
          <span class="text-xs text-neutral-400">{{ formatTime(message.timestamp) }}</span>
        </div>

        <div
          v-if="message.content"
          class="prose prose-sm max-w-none break-words prose-neutral dark:prose-invert"
          v-html="formattedContent"
        />
        <div v-if="(message as any).streaming" class="mt-1 inline-flex h-5 items-center gap-1 text-neutral-400">
          <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-current" />
          <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-current [animation-delay:120ms]" />
          <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-current [animation-delay:240ms]" />
        </div>

        <div v-if="message.attachments?.length" class="mt-3 grid gap-2 sm:grid-cols-2">
          <a
            v-for="attachment in message.attachments"
            :key="attachment.id"
            :href="attachment.url"
            target="_blank"
            class="group flex min-w-0 items-center gap-3 rounded-xl border border-neutral-200 bg-white p-3 transition-colors hover:border-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:hover:border-neutral-600"
          >
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-neutral-100 text-neutral-500 dark:bg-neutral-800 dark:text-neutral-300">
              <Icon :name="attachmentIcon(attachment.type)" class="h-5 w-5" />
            </div>
            <div class="min-w-0">
              <p class="truncate text-sm font-medium text-neutral-900 dark:text-white">{{ attachment.name }}</p>
              <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ formatFileSize(attachment.size) }}</p>
            </div>
          </a>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-1">
          <button
            type="button"
            class="inline-flex h-8 items-center gap-1.5 rounded-lg px-2 text-xs text-neutral-500 transition-colors hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-white"
            @click="copyMessage"
          >
            <Icon :name="copied ? 'ph:check' : 'ph:copy'" class="h-3.5 w-3.5" />
            {{ copied ? 'Copied' : 'Copy' }}
          </button>
          <button
            v-if="isAgentMessage"
            type="button"
            class="inline-flex h-8 items-center gap-1.5 rounded-lg px-2 text-xs text-neutral-500 transition-colors hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-white"
            @click="$emit('retry', message)"
          >
            <Icon name="ph:arrow-clockwise" class="h-3.5 w-3.5" />
            Retry
          </button>
        </div>
      </div>
    </div>
  </article>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { marked } from 'marked'
import DOMPurify from 'dompurify'
import Icon from '@/Components/shared/Icon.vue'
import SharedAgentAvatar from '@/Components/shared/AgentAvatar.vue'
import type { Message } from '@/types'

const props = defineProps<{
  message: Message
  currentUserId: string
}>()

defineEmits<{
  retry: [message: Message]
}>()

const copied = ref(false)
const isUser = computed(() => props.message.author?.id === props.currentUserId)
const isAgentMessage = computed(() => props.message.author?.type === 'agent')
const authorName = computed(() => isUser.value ? 'You' : props.message.author?.name ?? 'Assistant')
const userInitials = computed(() => authorName.value.split(/\s+/).map(part => part[0]).join('').slice(0, 2).toUpperCase())

const containerClasses = computed(() => [
  isUser.value
    ? 'bg-white dark:bg-neutral-950'
    : 'bg-neutral-50/80 dark:bg-neutral-900/70',
])

const formattedContent = computed(() => {
  const html = marked.parse(props.message.content || '', {
    breaks: true,
    async: false,
  }) as string
  return DOMPurify.sanitize(html)
})

const copyMessage = async () => {
  await navigator.clipboard?.writeText(props.message.content ?? '')
  copied.value = true
  window.setTimeout(() => {
    copied.value = false
  }, 1500)
}

const formatTime = (value: Date | string) => new Date(value).toLocaleTimeString('en-US', {
  hour: 'numeric',
  minute: '2-digit',
})

const attachmentIcon = (type: string) => {
  if (type?.startsWith('image/')) return 'ph:image'
  if (type?.startsWith('audio/')) return 'ph:music-note'
  if (type?.startsWith('video/')) return 'ph:video'
  if (type?.includes('pdf')) return 'ph:file-pdf'
  return 'ph:file'
}

const formatFileSize = (bytes: number) => {
  if (!bytes) return 'File'
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / 1024 / 1024).toFixed(1)} MB`
}
</script>
