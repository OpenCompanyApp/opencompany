<template>
  <div class="group py-1 px-2 -mx-2 rounded-lg hover:bg-olympus-surface/50 transition-colors duration-150">
    <!-- Reply context -->
    <div
      v-if="message.replyTo"
      class="flex items-center gap-2 ml-12 mb-1 text-xs text-olympus-text-muted"
    >
      <div class="w-4 h-4 border-l-2 border-t-2 border-olympus-border rounded-tl" />
      <SharedAgentAvatar :user="message.replyTo.author" size="xs" :show-status="false" />
      <span class="font-medium">{{ message.replyTo.author.name }}</span>
      <span class="truncate max-w-xs">{{ message.replyTo.content }}</span>
    </div>

    <!-- Approval Card -->
    <ChatApprovalCard
      v-if="message.isApprovalRequest && message.approvalRequest"
      :request="message.approvalRequest"
      :author="message.author"
      :timestamp="message.timestamp"
    />

    <!-- Regular Message -->
    <div v-else class="flex gap-3">
      <SharedAgentAvatar :user="message.author" size="md" />

      <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2 mb-0.5">
          <span class="font-semibold text-sm">{{ message.author.name }}</span>
          <SharedStatusBadge
            v-if="message.author.type === 'agent' && message.author.status"
            :status="message.author.status"
            size="xs"
          />
          <span class="text-xs text-olympus-text-subtle">
            {{ formatTime(message.timestamp) }}
          </span>
        </div>

        <p class="text-sm text-olympus-text leading-relaxed">{{ message.content }}</p>

        <!-- Agent activity log -->
        <div
          v-if="message.author.type === 'agent' && message.author.status === 'working' && message.author.activityLog?.length"
          class="mt-2"
        >
          <SharedActivityLog :steps="message.author.activityLog" />
        </div>

        <!-- Message actions (shown on hover) -->
        <div class="flex items-center gap-0.5 mt-1.5 opacity-0 group-hover:opacity-100 transition-opacity duration-150">
          <button class="p-1.5 rounded-md hover:bg-olympus-surface transition-colors duration-150 outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50">
            <Icon name="ph:smiley" class="w-4 h-4 text-olympus-text-muted" />
          </button>
          <button class="p-1.5 rounded-md hover:bg-olympus-surface transition-colors duration-150 outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50">
            <Icon name="ph:arrow-bend-up-left" class="w-4 h-4 text-olympus-text-muted" />
          </button>
          <button class="p-1.5 rounded-md hover:bg-olympus-surface transition-colors duration-150 outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50">
            <Icon name="ph:dots-three" class="w-4 h-4 text-olympus-text-muted" />
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { Message } from '~/types'

defineProps<{
  message: Message
}>()

const formatTime = (date: Date) => {
  return new Date(date).toLocaleTimeString('en-US', {
    hour: 'numeric',
    minute: '2-digit',
  })
}
</script>
