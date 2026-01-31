<template>
  <div class="h-full overflow-y-auto">
    <div class="max-w-3xl mx-auto p-6">
      <!-- Header -->
      <header class="mb-6">
        <h1 class="text-xl font-semibold text-neutral-900 dark:text-white">Approvals</h1>
        <p class="text-sm text-neutral-500 dark:text-neutral-400 mt-1">
          Review and manage pending requests
        </p>
      </header>

      <!-- Filters -->
      <div class="flex items-center gap-1 mb-6">
        <button
          v-for="filter in filters"
          :key="filter.value"
          type="button"
          :class="[
            'px-3 py-1.5 rounded-lg text-sm transition-colors duration-150',
            activeFilter === filter.value
              ? 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900'
              : 'text-neutral-500 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-800',
          ]"
          @click="activeFilter = filter.value"
        >
          {{ filter.label }}
          <span v-if="filter.count > 0" class="ml-1 opacity-60">{{ filter.count }}</span>
        </button>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="space-y-3">
        <div v-for="i in 3" :key="i" class="h-20 rounded-lg bg-neutral-100 dark:bg-neutral-800 animate-pulse" />
      </div>

      <!-- Empty State -->
      <div v-else-if="filteredApprovals.length === 0" class="py-16 text-center">
        <Icon name="ph:check-circle" class="w-10 h-10 text-neutral-300 dark:text-neutral-600 mx-auto mb-3" />
        <p class="text-sm text-neutral-500 dark:text-neutral-400">
          {{ activeFilter === 'pending' ? 'All caught up' : 'No approvals found' }}
        </p>
      </div>

      <!-- Approvals List -->
      <div v-else class="rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 divide-y divide-neutral-100 dark:divide-neutral-800">
        <div
          v-for="approval in filteredApprovals"
          :key="approval.id"
          class="px-4 py-4"
        >
          <div class="flex items-start gap-3">
            <!-- Content -->
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-neutral-900 dark:text-white">{{ approval.title }}</p>
              <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-1">
                {{ approval.requester?.name || 'Unknown' }}
                <span v-if="formatTimeAgo(approval.createdAt)"> · {{ formatTimeAgo(approval.createdAt) }}</span>
              </p>
              <p v-if="approval.description" class="text-sm text-neutral-500 dark:text-neutral-400 mt-2 line-clamp-2">
                {{ approval.description }}
              </p>
              <p v-if="approval.amount" class="text-sm font-medium text-neutral-700 dark:text-neutral-300 mt-2">
                {{ formatCurrency(approval.amount) }}
              </p>

              <!-- Responded info -->
              <p v-if="approval.status !== 'pending' && approval.respondedBy" class="text-xs text-neutral-400 dark:text-neutral-500 mt-2">
                {{ approval.status === 'approved' ? 'Approved' : 'Rejected' }} by {{ approval.respondedBy.name }}
              </p>
            </div>

            <!-- Status or Actions -->
            <div class="shrink-0">
              <div v-if="approval.status === 'pending'" class="flex items-center gap-2">
                <button
                  type="button"
                  class="px-3 py-1.5 text-xs font-medium rounded-md bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 hover:bg-neutral-800 dark:hover:bg-neutral-100 transition-colors duration-150 disabled:opacity-50"
                  :disabled="processing === approval.id"
                  @click="handleApproval(approval.id, 'approved')"
                >
                  Approve
                </button>
                <button
                  type="button"
                  class="px-3 py-1.5 text-xs font-medium rounded-md text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors duration-150 disabled:opacity-50"
                  :disabled="processing === approval.id"
                  @click="handleApproval(approval.id, 'rejected')"
                >
                  Reject
                </button>
              </div>
              <span
                v-else
                :class="[
                  'text-xs',
                  approval.status === 'approved' ? 'text-green-600 dark:text-green-400' : 'text-neutral-400 dark:text-neutral-500',
                ]"
              >
                {{ approval.status === 'approved' ? 'Approved' : 'Rejected' }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import Icon from '@/Components/shared/Icon.vue'

interface User {
  id: string
  name: string
}

interface ApprovalRequest {
  id: string
  type: string
  title: string
  description: string
  requesterId: string
  amount?: number
  status: 'pending' | 'approved' | 'rejected'
  respondedById?: string
  respondedAt?: string
  createdAt: string
  requester?: User
  respondedBy?: User
}

const activeFilter = ref<'all' | 'pending' | 'approved' | 'rejected'>('pending')
const loading = ref(true)
const processing = ref<string | null>(null)
const approvals = ref<ApprovalRequest[]>([])

const filters = computed(() => [
  { value: 'all' as const, label: 'All', count: approvals.value.length },
  { value: 'pending' as const, label: 'Pending', count: approvals.value.filter((a) => a.status === 'pending').length },
  { value: 'approved' as const, label: 'Approved', count: approvals.value.filter((a) => a.status === 'approved').length },
  { value: 'rejected' as const, label: 'Rejected', count: approvals.value.filter((a) => a.status === 'rejected').length },
])

const filteredApprovals = computed(() => {
  if (activeFilter.value === 'all') return approvals.value
  return approvals.value.filter((a) => a.status === activeFilter.value)
})

const fetchApprovals = async () => {
  loading.value = true
  try {
    const response = await fetch('/api/approvals')
    const data = await response.json()
    approvals.value = data
  } catch (error) {
    console.error('Failed to fetch approvals:', error)
  } finally {
    loading.value = false
  }
}

const handleApproval = async (id: string, status: 'approved' | 'rejected') => {
  processing.value = id
  try {
    await fetch(`/api/approvals/${id}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ status, respondedById: 'h1' }),
    })
    await fetchApprovals()
  } catch (error) {
    console.error('Failed to update approval:', error)
  } finally {
    processing.value = null
  }
}

const formatTimeAgo = (dateString: string): string => {
  const date = new Date(dateString)
  if (isNaN(date.getTime())) return ''
  const seconds = Math.floor((Date.now() - date.getTime()) / 1000)
  if (seconds < 60) return 'just now'
  if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`
  if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`
  if (seconds < 604800) return `${Math.floor(seconds / 86400)}d ago`
  return date.toLocaleDateString()
}

const formatCurrency = (amount: number): string => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
  }).format(amount)
}

onMounted(() => {
  fetchApprovals()
})
</script>
