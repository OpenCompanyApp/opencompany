<template>
  <div class="h-full flex flex-col bg-white dark:bg-neutral-900">
    <!-- Header -->
    <header class="h-14 px-4 md:px-6 border-b border-neutral-200 dark:border-neutral-700 flex items-center gap-3 bg-white dark:bg-neutral-900 shrink-0">
      <button
        class="p-1.5 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors"
        @click="goBack"
      >
        <Icon name="ph:arrow-left" class="w-4.5 h-4.5 text-neutral-600 dark:text-neutral-300" />
      </button>
      <span class="text-lg font-semibold text-neutral-900 dark:text-white">Approval</span>
    </header>

    <!-- Content -->
    <div class="flex-1 overflow-y-auto">
      <!-- Loading -->
      <div v-if="loading" class="max-w-3xl mx-auto p-6 space-y-6">
        <div class="h-8 w-48 rounded-lg bg-neutral-100 dark:bg-neutral-800 animate-pulse" />
        <div class="h-6 w-96 rounded-lg bg-neutral-100 dark:bg-neutral-800 animate-pulse" />
        <div class="grid grid-cols-2 gap-4">
          <div v-for="i in 4" :key="i" class="h-16 rounded-lg bg-neutral-100 dark:bg-neutral-800 animate-pulse" />
        </div>
      </div>

      <!-- Error -->
      <div v-else-if="error" class="max-w-3xl mx-auto p-6">
        <div class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 p-4 text-center">
          <Icon name="ph:warning" class="w-8 h-8 text-red-400 mx-auto mb-2" />
          <p class="text-sm text-red-600 dark:text-red-400">Approval not found or failed to load.</p>
          <Link :href="workspacePath('/approvals')" class="text-sm text-red-500 hover:underline mt-2 inline-block">
            Back to approvals
          </Link>
        </div>
      </div>

      <!-- Approval detail -->
      <div v-else-if="approval" class="max-w-3xl mx-auto p-6 space-y-6">
        <!-- Badges -->
        <div class="flex items-center gap-2 flex-wrap">
          <span :class="['inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium', statusBadge]">
            <span :class="['w-1.5 h-1.5 rounded-full', statusDot]" />
            {{ statusLabel }}
          </span>
          <span :class="['inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium', typeBadge(approval.type)]">
            <Icon :name="typeIcon(approval.type)" class="w-3.5 h-3.5" />
            {{ approval.type }}
          </span>
          <span v-if="approval.urgent" class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400">
            <Icon name="ph:warning" class="w-3.5 h-3.5" />
            Urgent
          </span>
        </div>

        <!-- Title -->
        <h1 class="text-xl font-semibold text-neutral-900 dark:text-white">{{ approval.title }}</h1>

        <!-- Metadata grid -->
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
          <!-- Requester -->
          <div class="space-y-1">
            <span class="text-[11px] font-medium text-neutral-400 dark:text-neutral-500 uppercase tracking-wider">Requester</span>
            <p class="text-sm text-neutral-900 dark:text-white">{{ approval.requester?.name || 'Unknown' }}</p>
          </div>

          <!-- Type -->
          <div class="space-y-1">
            <span class="text-[11px] font-medium text-neutral-400 dark:text-neutral-500 uppercase tracking-wider">Type</span>
            <p class="text-sm text-neutral-900 dark:text-white capitalize flex items-center gap-1.5">
              <Icon :name="typeIcon(approval.type)" :class="['w-4 h-4', typeColor(approval.type)]" />
              {{ approval.type }}
            </p>
          </div>

          <!-- Created -->
          <div class="space-y-1">
            <span class="text-[11px] font-medium text-neutral-400 dark:text-neutral-500 uppercase tracking-wider">Created</span>
            <p class="text-sm text-neutral-900 dark:text-white">{{ formatTime(approval.createdAt) }}</p>
          </div>

          <!-- Amount -->
          <div v-if="approval.amount" class="space-y-1">
            <span class="text-[11px] font-medium text-neutral-400 dark:text-neutral-500 uppercase tracking-wider">Amount</span>
            <p class="text-sm font-medium text-neutral-900 dark:text-white">{{ formatCurrency(approval.amount) }}</p>
          </div>

          <!-- Resource -->
          <div v-if="approval.resource" class="space-y-1">
            <span class="text-[11px] font-medium text-neutral-400 dark:text-neutral-500 uppercase tracking-wider">Resource</span>
            <p class="text-sm text-neutral-900 dark:text-white">{{ approval.resource }}</p>
          </div>

          <!-- Duration -->
          <div v-if="approval.duration" class="space-y-1">
            <span class="text-[11px] font-medium text-neutral-400 dark:text-neutral-500 uppercase tracking-wider">Duration</span>
            <p class="text-sm text-neutral-900 dark:text-white">{{ approval.duration }}</p>
          </div>

          <!-- Scope -->
          <div v-if="approval.scope" class="space-y-1">
            <span class="text-[11px] font-medium text-neutral-400 dark:text-neutral-500 uppercase tracking-wider">Scope</span>
            <p class="text-sm text-neutral-900 dark:text-white">{{ approval.scope }}</p>
          </div>

          <!-- Source -->
          <div v-if="approval.source" class="space-y-1">
            <span class="text-[11px] font-medium text-neutral-400 dark:text-neutral-500 uppercase tracking-wider">Source</span>
            <p class="text-sm text-neutral-900 dark:text-white capitalize">{{ approval.source }}</p>
          </div>

          <!-- Responded by -->
          <div v-if="approval.respondedBy" class="space-y-1">
            <span class="text-[11px] font-medium text-neutral-400 dark:text-neutral-500 uppercase tracking-wider">{{ approval.status === 'approved' ? 'Approved by' : 'Rejected by' }}</span>
            <p class="text-sm text-neutral-900 dark:text-white">{{ approval.respondedBy.name }}</p>
          </div>

          <!-- Responded at -->
          <div v-if="approval.respondedAt" class="space-y-1">
            <span class="text-[11px] font-medium text-neutral-400 dark:text-neutral-500 uppercase tracking-wider">{{ approval.status === 'approved' ? 'Approved at' : 'Rejected at' }}</span>
            <p class="text-sm text-neutral-900 dark:text-white">{{ formatTime(approval.respondedAt) }}</p>
          </div>
        </div>

        <!-- Description -->
        <div v-if="approval.description" class="space-y-2">
          <span class="text-[11px] font-medium text-neutral-400 dark:text-neutral-500 uppercase tracking-wider">Description</span>
          <div class="rounded-lg bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 p-4">
            <p class="text-sm text-neutral-700 dark:text-neutral-300 whitespace-pre-wrap">{{ approval.description }}</p>
          </div>
        </div>

        <!-- Risk -->
        <div v-if="approval.riskLevel" class="space-y-2">
          <span class="text-[11px] font-medium text-neutral-400 dark:text-neutral-500 uppercase tracking-wider">Risk Assessment</span>
          <div :class="['rounded-lg border p-4', riskClasses]">
            <div class="flex items-center gap-2 mb-1">
              <Icon name="ph:warning" :class="['w-4 h-4', riskIconColor]" />
              <span :class="['text-sm font-medium capitalize', riskIconColor]">{{ approval.riskLevel }} risk</span>
            </div>
            <p v-if="approval.riskDescription" class="text-sm text-neutral-600 dark:text-neutral-400">
              {{ approval.riskDescription }}
            </p>
          </div>
        </div>

        <!-- Details list -->
        <div v-if="approval.details && approval.details.length > 0" class="space-y-2">
          <span class="text-[11px] font-medium text-neutral-400 dark:text-neutral-500 uppercase tracking-wider">Details</span>
          <ul class="rounded-lg bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 divide-y divide-neutral-200 dark:divide-neutral-700">
            <li v-for="(detail, i) in approval.details" :key="i" class="px-4 py-2.5 text-sm text-neutral-700 dark:text-neutral-300">
              {{ detail }}
            </li>
          </ul>
        </div>

        <!-- Response note -->
        <div v-if="approval.responseNote" class="space-y-2">
          <span class="text-[11px] font-medium text-neutral-400 dark:text-neutral-500 uppercase tracking-wider">Response Note</span>
          <div class="rounded-lg bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 p-4">
            <p class="text-sm text-neutral-700 dark:text-neutral-300">{{ approval.responseNote }}</p>
          </div>
        </div>

        <!-- Action bar -->
        <div v-if="approval.status === 'pending'" class="flex items-center gap-3 pt-2">
          <button
            type="button"
            class="px-4 py-2 text-sm font-medium rounded-lg bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 hover:bg-neutral-800 dark:hover:bg-neutral-100 transition-colors disabled:opacity-50"
            :disabled="processing !== null"
            @click="handleAction('approved')"
          >
            Approve
          </button>
          <button
            type="button"
            class="px-4 py-2 text-sm font-medium rounded-lg text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors disabled:opacity-50"
            :disabled="processing !== null"
            @click="handleAction('rejected')"
          >
            Reject
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import Icon from '@/Components/shared/Icon.vue'
import { useApi } from '@/composables/useApi'
import { useWorkspace } from '@/composables/useWorkspace'
import type { ApprovalRequest } from '@/types'

const props = defineProps<{
  approvalId: string
}>()

const page = usePage()
const { fetchApproval, respondToApproval } = useApi()
const { workspacePath } = useWorkspace()

const currentUserId = computed(() => (page.props.auth as any)?.user?.id || '')
const processing = ref<string | null>(null)

const { data: approval, loading, error, refresh } = fetchApproval(props.approvalId)

// Status styling
const statusBadge = computed(() => {
  switch (approval.value?.status) {
    case 'pending': return 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400'
    case 'approved': return 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400'
    case 'rejected': return 'bg-neutral-100 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-400'
    default: return 'bg-neutral-100 dark:bg-neutral-700 text-neutral-500'
  }
})

const statusDot = computed(() => {
  switch (approval.value?.status) {
    case 'pending': return 'bg-amber-500'
    case 'approved': return 'bg-green-500'
    case 'rejected': return 'bg-neutral-400'
    default: return 'bg-neutral-400'
  }
})

const statusLabel = computed(() => {
  switch (approval.value?.status) {
    case 'pending': return 'Pending'
    case 'approved': return 'Approved'
    case 'rejected': return 'Rejected'
    default: return 'Unknown'
  }
})

// Type helpers
const typeIcon = (type: string): string => {
  switch (type) {
    case 'action': return 'ph:lightning'
    case 'spawn': return 'ph:robot'
    case 'access': return 'ph:lock-key'
    case 'budget': return 'ph:currency-circle-dollar'
    case 'cost': return 'ph:currency-circle-dollar'
    default: return 'ph:question'
  }
}

const typeColor = (type: string): string => {
  switch (type) {
    case 'action': return 'text-blue-600 dark:text-blue-400'
    case 'spawn': return 'text-purple-600 dark:text-purple-400'
    case 'access': return 'text-amber-600 dark:text-amber-400'
    case 'budget': case 'cost': return 'text-green-600 dark:text-green-400'
    default: return 'text-neutral-500 dark:text-neutral-400'
  }
}

const typeBadge = (type: string): string => {
  switch (type) {
    case 'action': return 'bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400'
    case 'spawn': return 'bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400'
    case 'access': return 'bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400'
    case 'budget': case 'cost': return 'bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400'
    default: return 'bg-neutral-100 dark:bg-neutral-700 text-neutral-500 dark:text-neutral-400'
  }
}

// Risk styling
const riskClasses = computed(() => {
  switch (approval.value?.riskLevel) {
    case 'high': return 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800'
    case 'medium': return 'bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-800'
    default: return 'bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700'
  }
})

const riskIconColor = computed(() => {
  switch (approval.value?.riskLevel) {
    case 'high': return 'text-red-500 dark:text-red-400'
    case 'medium': return 'text-amber-500 dark:text-amber-400'
    default: return 'text-neutral-500 dark:text-neutral-400'
  }
})

// Actions
const handleAction = async (status: 'approved' | 'rejected') => {
  processing.value = status
  try {
    await respondToApproval(props.approvalId, status, currentUserId.value)
    await refresh()
  } catch (err) {
    console.error('Failed to update approval:', err)
  } finally {
    processing.value = null
  }
}

const goBack = () => {
  window.history.back()
}

// Formatters
const formatTime = (dateString?: string | Date): string => {
  if (!dateString) return '-'
  const date = new Date(dateString)
  if (isNaN(date.getTime())) return '-'
  const seconds = Math.floor((Date.now() - date.getTime()) / 1000)
  if (seconds < 60) return 'just now'
  if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`
  if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`
  if (seconds < 604800) return `${Math.floor(seconds / 86400)}d ago`
  return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

const formatCurrency = (amount: number): string => {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(amount)
}
</script>
