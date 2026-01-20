<template>
  <div class="flex gap-3">
    <SharedAgentAvatar :user="author" />

    <div class="flex-1 min-w-0">
      <div class="flex items-center gap-2 mb-2">
        <span class="font-semibold">{{ author.name }}</span>
        <SharedStatusBadge
          v-if="author.type === 'agent' && author.status"
          :status="author.status"
          size="xs"
        />
        <span class="text-xs text-olympus-text-muted">
          {{ formatTime(timestamp) }}
        </span>
      </div>

      <!-- Approval Card -->
      <div class="bg-olympus-surface border border-olympus-border rounded-2xl overflow-hidden max-w-md">
        <!-- Header -->
        <div class="px-4 py-3 border-b border-olympus-border bg-amber-500/10">
          <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-lg bg-amber-500/20 flex items-center justify-center">
              <Icon name="ph:warning-circle-fill" class="w-4 h-4 text-amber-400" />
            </div>
            <div>
              <p class="font-semibold text-sm">Approval Required</p>
              <p class="text-xs text-olympus-text-muted capitalize">{{ request.type }} request</p>
            </div>
          </div>
        </div>

        <!-- Content -->
        <div class="p-4">
          <h4 class="font-medium mb-2">{{ request.title }}</h4>
          <p class="text-sm text-olympus-text-muted mb-4">{{ request.description }}</p>

          <!-- Amount if present -->
          <div v-if="request.amount" class="flex items-center justify-between p-3 bg-olympus-bg rounded-xl mb-4">
            <span class="text-sm text-olympus-text-muted">Amount requested</span>
            <span class="font-bold text-lg">${{ request.amount.toLocaleString() }}</span>
          </div>

          <!-- Actions -->
          <div v-if="request.status === 'pending'" class="flex items-center gap-2">
            <button
              class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 bg-green-500 hover:bg-green-600 text-white font-medium rounded-xl transition-colors"
              @click="handleApprove"
            >
              <Icon name="ph:check" class="w-4 h-4" />
              Approve
            </button>
            <button
              class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 bg-olympus-elevated hover:bg-olympus-border text-olympus-text font-medium rounded-xl border border-olympus-border transition-colors"
              @click="handleReject"
            >
              <Icon name="ph:x" class="w-4 h-4" />
              Reject
            </button>
          </div>

          <!-- Status if already responded -->
          <div
            v-else
            :class="[
              'flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl font-medium',
              request.status === 'approved' ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'
            ]"
          >
            <Icon
              :name="request.status === 'approved' ? 'ph:check-circle-fill' : 'ph:x-circle-fill'"
              class="w-4 h-4"
            />
            {{ request.status === 'approved' ? 'Approved' : 'Rejected' }}
            <span v-if="request.respondedBy" class="text-xs opacity-70">
              by {{ request.respondedBy.name }}
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { ApprovalRequest, User } from '~/types'

const props = defineProps<{
  request: ApprovalRequest
  author: User
  timestamp: Date
}>()

const emit = defineEmits<{
  approve: []
  reject: []
}>()

const formatTime = (date: Date) => {
  return new Date(date).toLocaleTimeString('en-US', {
    hour: 'numeric',
    minute: '2-digit',
  })
}

const handleApprove = () => {
  emit('approve')
}

const handleReject = () => {
  emit('reject')
}
</script>
