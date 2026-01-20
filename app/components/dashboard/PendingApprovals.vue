<template>
  <div v-if="approvals.length > 0" class="mb-6">
    <!-- Alert Banner -->
    <div class="bg-amber-500/10 border border-amber-500/30 rounded-xl overflow-hidden shadow-lg shadow-amber-500/10">
      <!-- Header -->
      <div class="px-4 py-3 border-b border-amber-500/20 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-lg bg-amber-500/20 flex items-center justify-center">
            <Icon name="ph:warning-circle-fill" class="w-5 h-5 text-amber-400" />
          </div>
          <div>
            <h2 class="font-semibold text-sm text-amber-200">Action Required</h2>
            <p class="text-xs text-amber-400/70">
              {{ approvals.length }} pending {{ approvals.length === 1 ? 'approval' : 'approvals' }}
            </p>
          </div>
        </div>
        <button
          v-if="approvals.length > 1"
          class="text-sm text-amber-400 hover:text-amber-300 transition-colors duration-150 outline-none focus-visible:ring-2 focus-visible:ring-amber-400/50 rounded px-2 py-1"
          @click="expanded = !expanded"
        >
          {{ expanded ? 'Show less' : 'Show all' }}
        </button>
      </div>

      <!-- Approval Items -->
      <div class="divide-y divide-amber-500/10">
        <DashboardPendingApprovalItem
          v-for="approval in displayedApprovals"
          :key="approval.id"
          :approval="approval"
          @approve="$emit('approve', approval)"
          @reject="$emit('reject', approval)"
        />
      </div>

      <!-- Collapsed indicator -->
      <button
        v-if="approvals.length > 1 && !expanded"
        class="w-full py-2.5 text-xs text-amber-400/70 hover:text-amber-400 hover:bg-amber-500/5 transition-colors duration-150 outline-none focus-visible:bg-amber-500/10"
        @click="expanded = true"
      >
        + {{ approvals.length - 1 }} more {{ approvals.length - 1 === 1 ? 'request' : 'requests' }}
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { ApprovalRequest } from '~/types'

const props = defineProps<{
  approvals: ApprovalRequest[]
}>()

defineEmits<{
  approve: [approval: ApprovalRequest]
  reject: [approval: ApprovalRequest]
}>()

const expanded = ref(false)

const displayedApprovals = computed(() =>
  expanded.value ? props.approvals : props.approvals.slice(0, 1)
)
</script>
