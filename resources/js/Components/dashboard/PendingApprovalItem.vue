<template>
  <div class="px-4 py-3 hover:bg-neutral-50 dark:hover:bg-neutral-800/50 transition-colors duration-150">
    <div class="flex items-start gap-3">
      <!-- Content -->
      <div class="flex-1 min-w-0">
        <p class="text-sm font-medium text-neutral-900 dark:text-white">{{ approval.title }}</p>
        <p v-if="approval.description" class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5 line-clamp-2">
          {{ approval.description }}
        </p>
        <div class="flex items-center gap-3 mt-2 text-xs text-neutral-400 dark:text-neutral-500">
          <span v-if="approval.amount" class="text-neutral-600 dark:text-neutral-300 font-medium">
            ${{ approval.amount.toLocaleString() }}
          </span>
          <span>{{ approval.requester.name }}</span>
          <span v-if="approval.createdAt">{{ formatTimeAgo(approval.createdAt) }}</span>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex items-center gap-2 shrink-0">
        <button
          type="button"
          class="px-3 py-1.5 text-xs font-medium rounded-md bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 hover:bg-neutral-800 dark:hover:bg-neutral-100 transition-colors duration-150"
          @click="emit('approve')"
        >
          Approve
        </button>
        <button
          type="button"
          class="px-3 py-1.5 text-xs font-medium rounded-md text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors duration-150"
          @click="emit('reject')"
        >
          Reject
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { ApprovalRequest } from '@/types'

defineProps<{
  approval: ApprovalRequest
}>()

const emit = defineEmits<{
  approve: []
  reject: []
}>()

const formatTimeAgo = (date: Date | string): string => {
  const d = date instanceof Date ? date : new Date(date)
  if (isNaN(d.getTime())) return ''
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
