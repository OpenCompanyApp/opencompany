<template>
  <div :class="['p-3 rounded-lg border', comment.resolved ? 'bg-neutral-50 border-neutral-100' : 'bg-white border-neutral-200']">
    <!-- Comment Header -->
    <div class="flex items-start gap-2 mb-2">
      <SharedAgentAvatar v-if="comment.author" :user="comment.author" size="sm" />
      <div class="flex-1 min-w-0">
        <div class="flex items-center justify-between">
          <span class="text-sm font-medium text-neutral-700">
            {{ comment.author?.name ?? 'Unknown' }}
          </span>
          <span class="text-xs text-neutral-500">
            {{ formatTimeAgo(comment.createdAt) }}
          </span>
        </div>
      </div>
    </div>

    <!-- Comment Content -->
    <p :class="['text-sm mb-3', comment.resolved ? 'text-neutral-500 line-through' : 'text-neutral-700']">
      {{ comment.content }}
    </p>

    <!-- Resolved Badge -->
    <div v-if="comment.resolved" class="flex items-center gap-1.5 mb-3 text-xs text-green-400">
      <Icon name="ph:check-circle" class="w-4 h-4" />
      <span>Resolved by {{ comment.resolvedBy?.name ?? 'Unknown' }}</span>
    </div>

    <!-- Actions -->
    <div class="flex items-center gap-2">
      <button
        v-if="!comment.resolved"
        class="text-xs text-neutral-500 hover:text-neutral-700 transition-colors"
        @click="showReplyForm = !showReplyForm"
      >
        <Icon name="ph:arrow-bend-up-left" class="w-3.5 h-3.5 inline mr-1" />
        Reply
      </button>
      <button
        class="text-xs text-neutral-500 hover:text-neutral-700 transition-colors"
        @click="emit('resolve', comment.id, !comment.resolved)"
      >
        <Icon :name="comment.resolved ? 'ph:arrow-counter-clockwise' : 'ph:check'" class="w-3.5 h-3.5 inline mr-1" />
        {{ comment.resolved ? 'Unresolve' : 'Resolve' }}
      </button>
      <button
        class="text-xs text-neutral-500 hover:text-red-400 transition-colors"
        @click="emit('delete', comment.id)"
      >
        <Icon name="ph:trash" class="w-3.5 h-3.5 inline mr-1" />
        Delete
      </button>
    </div>

    <!-- Reply Form -->
    <Transition name="slide-down">
      <div v-if="showReplyForm" class="mt-3 pt-3 border-t border-neutral-200">
        <textarea
          v-model="replyContent"
          placeholder="Write a reply..."
          class="w-full bg-white rounded-lg p-2 text-sm resize-none outline-none border border-neutral-200 focus:border-neutral-300"
          rows="2"
        />
        <div class="flex items-center justify-end gap-2 mt-2">
          <button
            class="px-3 py-1.5 text-xs text-neutral-500 hover:text-neutral-700 transition-colors"
            @click="showReplyForm = false; replyContent = ''"
          >
            Cancel
          </button>
          <button
            class="px-3 py-1.5 text-xs bg-neutral-900 text-white rounded-lg hover:bg-neutral-800 transition-colors disabled:opacity-50"
            :disabled="!replyContent.trim()"
            @click="handleReply"
          >
            Reply
          </button>
        </div>
      </div>
    </Transition>

    <!-- Replies -->
    <div v-if="comment.replies?.length" class="mt-3 pl-4 border-l-2 border-neutral-100 space-y-3">
      <div
        v-for="reply in comment.replies"
        :key="reply.id"
        class="p-2 rounded-lg bg-white"
      >
        <div class="flex items-start gap-2 mb-1">
          <SharedAgentAvatar v-if="reply.author" :user="reply.author" size="xs" />
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
              <span class="text-xs font-medium text-neutral-700">
                {{ reply.author?.name ?? 'Unknown' }}
              </span>
              <span class="text-[10px] text-neutral-500">
                {{ formatTimeAgo(reply.createdAt) }}
              </span>
            </div>
          </div>
        </div>
        <p class="text-xs text-neutral-700">{{ reply.content }}</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import type { User } from '@/types'
import Icon from '@/Components/shared/Icon.vue'

interface DocumentComment {
  id: string
  documentId: string
  authorId: string
  content: string
  parentId: string | null
  resolved: boolean
  resolvedById: string | null
  resolvedAt: Date | null
  createdAt: Date
  updatedAt: Date
  author?: User
  resolvedBy?: User
  replies?: DocumentComment[]
}

const props = defineProps<{
  comment: DocumentComment
}>()

const emit = defineEmits<{
  reply: [parentId: string, content: string]
  resolve: [commentId: string, resolved: boolean]
  delete: [commentId: string]
}>()

const showReplyForm = ref(false)
const replyContent = ref('')

const handleReply = () => {
  if (!replyContent.value.trim()) return
  emit('reply', props.comment.id, replyContent.value.trim())
  replyContent.value = ''
  showReplyForm.value = false
}

const formatTimeAgo = (date: Date | string): string => {
  const d = new Date(date)
  const seconds = Math.floor((Date.now() - d.getTime()) / 1000)
  if (seconds < 60) return 'just now'
  const minutes = Math.floor(seconds / 60)
  if (minutes < 60) return `${minutes}m ago`
  const hours = Math.floor(minutes / 60)
  if (hours < 24) return `${hours}h ago`
  const days = Math.floor(hours / 24)
  return `${days}d ago`
}
</script>

<style scoped>
.slide-down-enter-active,
.slide-down-leave-active {
  transition: all 0.2s ease;
}

.slide-down-enter-from,
.slide-down-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}
</style>
