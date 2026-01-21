<template>
  <div class="min-h-screen bg-white p-6">
    <div class="max-w-4xl mx-auto">
      <!-- Header -->
      <div class="flex items-center justify-between mb-8">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Approvals</h1>
          <p class="text-sm text-gray-500 mt-1">
            Review and manage pending requests from agents
          </p>
        </div>
        <div class="flex items-center gap-2">
          <button
            v-for="filter in filters"
            :key="filter.value"
            type="button"
            :class="[
              'px-3 py-1.5 rounded-lg text-sm font-medium transition-colors',
              activeFilter === filter.value
                ? 'bg-gray-900 text-white'
                : 'bg-gray-50 text-gray-500 hover:text-gray-900',
            ]"
            @click="activeFilter = filter.value"
          >
            {{ filter.label }}
            <span
              v-if="filter.count > 0"
              :class="[
                'ml-1.5 px-1.5 py-0.5 rounded text-xs',
                activeFilter === filter.value ? 'bg-white/20' : 'bg-white',
              ]"
            >
              {{ filter.count }}
            </span>
          </button>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="space-y-4">
        <SharedSkeleton v-for="i in 3" :key="i" custom-class="h-32 rounded-xl" />
      </div>

      <!-- Empty State -->
      <div
        v-else-if="filteredApprovals.length === 0"
        class="flex flex-col items-center justify-center py-20 text-center"
      >
        <div class="w-16 h-16 rounded-2xl bg-gray-50 flex items-center justify-center mb-4">
          <Icon name="ph:check-circle" class="w-8 h-8 text-gray-500" />
        </div>
        <h2 class="text-lg font-semibold text-gray-900">
          {{ activeFilter === 'pending' ? 'All caught up!' : 'No approvals found' }}
        </h2>
        <p class="text-sm text-gray-500 mt-1">
          {{ activeFilter === 'pending' ? 'There are no pending requests to review.' : 'No approvals match this filter.' }}
        </p>
      </div>

      <!-- Approvals List -->
      <div v-else class="space-y-4">
        <div
          v-for="approval in filteredApprovals"
          :key="approval.id"
          :class="[
            'bg-gray-50 rounded-xl p-5 border transition-all',
            approval.status === 'pending'
              ? 'border-gray-200 shadow-lg shadow-gray-50'
              : 'border-gray-200',
          ]"
        >
          <!-- Header -->
          <div class="flex items-start justify-between mb-4">
            <div class="flex items-center gap-3">
              <div
                :class="[
                  'w-10 h-10 rounded-xl flex items-center justify-center',
                  typeConfig[approval.type]?.bgClass || 'bg-gray-500/20',
                ]"
              >
                <Icon
                  :name="typeConfig[approval.type]?.icon || 'ph:question'"
                  :class="['w-5 h-5', typeConfig[approval.type]?.colorClass || 'text-gray-400']"
                />
              </div>
              <div>
                <h3 class="font-semibold text-gray-900">{{ approval.title }}</h3>
                <p class="text-xs text-gray-500">
                  Requested by
                  <Link
                    v-if="approval.requester"
                    :href="`/profile/${approval.requesterId}`"
                    class="hover:text-gray-900 transition-colors"
                  >
                    {{ approval.requester.name }}
                  </Link>
                  &bull; {{ formatTimeAgo(approval.createdAt) }}
                </p>
              </div>
            </div>
            <span
              :class="[
                'px-2 py-1 rounded-lg text-xs font-medium',
                statusConfig[approval.status]?.class || 'bg-gray-500/20 text-gray-400',
              ]"
            >
              {{ statusConfig[approval.status]?.label || approval.status }}
            </span>
          </div>

          <!-- Description -->
          <p class="text-sm text-gray-500 mb-4">{{ approval.description }}</p>

          <!-- Amount (for budget requests) -->
          <div
            v-if="approval.type === 'budget' && approval.amount"
            class="flex items-center gap-2 mb-4 p-3 rounded-lg bg-white"
          >
            <Icon name="ph:coins" class="w-5 h-5 text-amber-400" />
            <span class="text-sm text-gray-500">Requested amount:</span>
            <span class="font-semibold text-gray-900">{{ formatCurrency(approval.amount) }}</span>
          </div>

          <!-- Responded info (for non-pending) -->
          <div
            v-if="approval.status !== 'pending' && approval.respondedBy"
            class="flex items-center gap-2 mb-4 p-3 rounded-lg bg-white text-sm"
          >
            <span class="text-gray-500">
              {{ approval.status === 'approved' ? 'Approved' : 'Rejected' }} by
            </span>
            <Link :href="`/profile/${approval.respondedById}`" class="font-medium text-gray-900 hover:text-gray-900 transition-colors">
              {{ approval.respondedBy.name }}
            </Link>
            <span class="text-gray-500">
              {{ formatTimeAgo(approval.respondedAt) }}
            </span>
          </div>

          <!-- Actions (for pending) -->
          <div v-if="approval.status === 'pending'" class="flex items-center gap-3 pt-4 border-t border-gray-200">
            <button
              type="button"
              class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-green-500 hover:bg-green-600 text-white font-medium transition-colors"
              :disabled="processing === approval.id"
              @click="handleApproval(approval.id, 'approved')"
            >
              <Icon
                :name="processing === approval.id ? 'ph:spinner' : 'ph:check'"
                :class="['w-5 h-5', processing === approval.id && 'animate-spin']"
              />
              Approve
            </button>
            <button
              type="button"
              class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-red-500/20 hover:bg-red-500/30 text-red-400 font-medium transition-colors"
              :disabled="processing === approval.id"
              @click="handleApproval(approval.id, 'rejected')"
            >
              <Icon name="ph:x" class="w-5 h-5" />
              Reject
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Link } from '@inertiajs/vue3'
import SharedSkeleton from '@/Components/shared/Skeleton.vue'

interface User {
  id: string
  name: string
  type: 'human' | 'agent'
  agentType?: string
}

interface ApprovalRequest {
  id: string
  type: 'budget' | 'action' | 'spawn' | 'access'
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

const typeConfig: Record<string, { icon: string; bgClass: string; colorClass: string }> = {
  budget: { icon: 'ph:coins', bgClass: 'bg-amber-500/20', colorClass: 'text-amber-400' },
  action: { icon: 'ph:play-circle', bgClass: 'bg-blue-500/20', colorClass: 'text-blue-400' },
  spawn: { icon: 'ph:robot', bgClass: 'bg-purple-500/20', colorClass: 'text-purple-400' },
  access: { icon: 'ph:key', bgClass: 'bg-green-500/20', colorClass: 'text-green-400' },
}

const statusConfig: Record<string, { label: string; class: string }> = {
  pending: { label: 'Pending', class: 'bg-amber-500/20 text-amber-400' },
  approved: { label: 'Approved', class: 'bg-green-500/20 text-green-400' },
  rejected: { label: 'Rejected', class: 'bg-red-500/20 text-red-400' },
}

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

const formatTimeAgo = (dateString: string) => {
  const date = new Date(dateString)
  const now = new Date()
  const seconds = Math.floor((now.getTime() - date.getTime()) / 1000)

  if (seconds < 60) return 'Just now'
  if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`
  if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`
  if (seconds < 604800) return `${Math.floor(seconds / 86400)}d ago`
  return date.toLocaleDateString()
}

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
  }).format(amount)
}

onMounted(() => {
  fetchApprovals()
})
</script>
