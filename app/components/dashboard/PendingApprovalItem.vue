<template>
  <div class="p-4 hover:bg-amber-500/5 transition-colors duration-150">
    <div class="flex items-start gap-3">
      <!-- Requester Avatar -->
      <SharedAgentAvatar :user="approval.requester" size="sm" />

      <!-- Content -->
      <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2 mb-1">
          <span class="font-medium text-sm">{{ approval.title }}</span>
          <span class="px-2 py-0.5 text-xs rounded-md bg-olympus-bg text-olympus-text-muted capitalize border border-olympus-border">
            {{ approval.type }}
          </span>
        </div>
        <p v-if="approval.description" class="text-sm text-olympus-text-muted line-clamp-1 mb-2">
          {{ approval.description }}
        </p>

        <!-- Amount if present -->
        <div v-if="approval.amount" class="inline-flex items-center gap-1 px-2 py-1 bg-olympus-bg rounded-md border border-olympus-border mb-2">
          <Icon name="ph:coins" class="w-3.5 h-3.5 text-amber-400" />
          <span class="text-sm font-semibold">${{ approval.amount.toLocaleString() }}</span>
        </div>

        <p class="text-xs text-olympus-text-subtle">
          Requested by {{ approval.requester.name }}
        </p>
      </div>

      <!-- Actions -->
      <div class="flex items-center gap-2 shrink-0">
        <SharedButton
          variant="success"
          size="sm"
          icon-left="ph:check"
          :loading="approving"
          :disabled="rejecting"
          @click="$emit('approve')"
        >
          Approve
        </SharedButton>
        <SharedButton
          variant="secondary"
          size="sm"
          icon-left="ph:x"
          :loading="rejecting"
          :disabled="approving"
          @click="$emit('reject')"
        >
          Reject
        </SharedButton>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { ApprovalRequest } from '~/types'

withDefaults(defineProps<{
  approval: ApprovalRequest
  approving?: boolean
  rejecting?: boolean
}>(), {
  approving: false,
  rejecting: false,
})

defineEmits<{
  approve: []
  reject: []
}>()
</script>
