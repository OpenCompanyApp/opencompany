<template>
  <div v-if="approvals.length > 0" class="rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 overflow-hidden">
    <!-- Header -->
    <div class="px-4 py-3 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between">
      <div class="flex items-center gap-2">
        <span class="w-2 h-2 rounded-full bg-amber-500" />
        <h3 class="text-sm font-medium text-neutral-900 dark:text-white">Action Required</h3>
      </div>
      <span class="text-xs text-neutral-500 dark:text-neutral-400">
        {{ approvals.length }} pending
      </span>
    </div>

    <!-- Approval Items -->
    <div class="divide-y divide-neutral-100 dark:divide-neutral-800">
      <PendingApprovalItem
        v-for="approval in approvals.slice(0, 3)"
        :key="approval.id"
        :approval="approval"
        @approve="emit('approve', approval)"
        @reject="emit('reject', approval)"
      />
    </div>

    <!-- Show more -->
    <Link
      v-if="approvals.length > 3"
      :href="workspacePath('/approvals')"
      class="block px-4 py-2 text-xs text-center text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 border-t border-neutral-100 dark:border-neutral-800"
    >
      View all {{ approvals.length }} approvals
    </Link>
  </div>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import type { ApprovalRequest } from '@/types'
import PendingApprovalItem from '@/Components/dashboard/PendingApprovalItem.vue'
import { useWorkspace } from '@/composables/useWorkspace'

const { workspacePath } = useWorkspace()

defineProps<{
  approvals: ApprovalRequest[]
}>()

const emit = defineEmits<{
  approve: [approval: ApprovalRequest]
  reject: [approval: ApprovalRequest]
}>()
</script>
