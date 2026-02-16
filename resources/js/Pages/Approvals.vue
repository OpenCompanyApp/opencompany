<template>
  <div class="h-full flex flex-col bg-white dark:bg-neutral-900">
    <!-- Header -->
    <header class="h-14 px-4 md:px-6 border-b border-neutral-200 dark:border-neutral-700 flex items-center gap-4 bg-white dark:bg-neutral-900 shrink-0">
      <span class="text-lg font-semibold text-neutral-900 dark:text-white">Approvals</span>

      <!-- Status tabs -->
      <div v-if="!loading" class="hidden md:flex items-center gap-0.5 bg-neutral-100 dark:bg-neutral-800 rounded-lg p-0.5">
        <button
          v-for="s in statusTabs"
          :key="s.value"
          :class="[
            'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium transition-colors',
            statusFilter === s.value
              ? 'bg-white dark:bg-neutral-700 text-neutral-900 dark:text-white shadow-sm'
              : 'text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-300',
          ]"
          @click="statusFilter = s.value"
        >
          <span v-if="s.dot" :class="['w-1.5 h-1.5 rounded-full', s.dot]" />
          {{ s.label }}
          <span v-if="s.count > 0" class="text-[10px] opacity-60">{{ s.count }}</span>
        </button>
      </div>

      <div class="ml-auto flex items-center gap-2">
        <SearchInput
          v-model="searchQuery"
          placeholder="Search..."
          variant="ghost"
          size="sm"
          :clearable="true"
          :debounce="300"
          class="w-36 lg:w-48 shrink-0"
        />

        <!-- Type filter -->
        <DropdownMenu side="bottom" align="end">
          <button class="hidden md:inline-flex items-center gap-1.5 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg px-2.5 py-1.5 text-sm text-neutral-700 dark:text-neutral-300 hover:border-neutral-300 dark:hover:border-neutral-600 transition-colors shrink-0">
            <Icon v-if="typeFilter !== 'all'" :name="activeTypeIcon" class="w-3.5 h-3.5" />
            <span>{{ activeTypeLabel }}</span>
            <Icon name="ph:caret-down" class="w-3 h-3 text-neutral-400" />
          </button>
          <template #content>
            <button
              v-for="t in typeFilters"
              :key="t.value"
              class="flex items-center gap-2 w-full px-2 py-1.5 text-sm rounded cursor-pointer outline-none select-none hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors"
              :class="typeFilter === t.value ? 'text-neutral-900 dark:text-white font-medium' : 'text-neutral-600 dark:text-neutral-300'"
              @click="typeFilter = t.value"
            >
              <Icon :name="t.icon" class="w-3.5 h-3.5 shrink-0" />
              <span>{{ t.label }}</span>
            </button>
          </template>
        </DropdownMenu>
      </div>
    </header>

    <!-- Content -->
    <div class="flex-1 overflow-y-auto">
      <div class="max-w-4xl mx-auto p-4 md:p-6">
        <!-- Loading -->
        <div v-if="loading" class="space-y-3">
          <div v-for="i in 4" :key="i" class="h-20 rounded-lg bg-neutral-100 dark:bg-neutral-800 animate-pulse" />
        </div>

        <!-- Empty -->
        <div v-else-if="filteredApprovals.length === 0" class="py-16 text-center">
          <Icon name="ph:check-circle" class="w-10 h-10 text-neutral-300 dark:text-neutral-600 mx-auto mb-3" />
          <p class="text-sm font-medium text-neutral-900 dark:text-white mb-1">
            {{ statusFilter === 'pending' ? 'All caught up' : 'No approvals found' }}
          </p>
          <p class="text-xs text-neutral-500 dark:text-neutral-400">
            {{ statusFilter === 'pending' ? 'No pending requests right now.' : 'Try adjusting your filters.' }}
          </p>
        </div>

        <!-- Approvals list -->
        <div v-else class="rounded-lg border border-neutral-200 dark:border-neutral-800 divide-y divide-neutral-100 dark:divide-neutral-800 bg-white dark:bg-neutral-900">
          <Link
            v-for="approval in filteredApprovals"
            :key="approval.id"
            :href="workspacePath('/approvals/' + approval.id)"
            class="block px-4 py-3.5 hover:bg-neutral-50 dark:hover:bg-neutral-800/50 transition-colors"
          >
            <div class="flex items-start gap-3">
              <!-- Type icon -->
              <div :class="['w-8 h-8 rounded-lg flex items-center justify-center shrink-0', typeBg(approval.type)]">
                <Icon :name="typeIcon(approval.type)" :class="['w-4 h-4', typeColor(approval.type)]" />
              </div>

              <!-- Content -->
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                  <p class="text-sm font-medium text-neutral-900 dark:text-white truncate">{{ approval.title }}</p>
                  <span :class="['px-1.5 py-0.5 text-[10px] font-medium rounded capitalize shrink-0', typeBadge(approval.type)]">
                    {{ approval.type }}
                  </span>
                </div>
                <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
                  {{ approval.requester?.name || 'Unknown' }}
                  <span v-if="formatTimeAgo(approval.createdAt)"> · {{ formatTimeAgo(approval.createdAt) }}</span>
                </p>
                <p v-if="approval.description" class="text-sm text-neutral-500 dark:text-neutral-400 mt-1.5 line-clamp-2">
                  {{ approval.description }}
                </p>
                <p v-if="approval.amount" class="text-sm font-medium text-neutral-700 dark:text-neutral-300 mt-1.5">
                  {{ formatCurrency(approval.amount) }}
                </p>
                <p v-if="approval.status !== 'pending' && approval.respondedBy" class="text-xs text-neutral-400 dark:text-neutral-500 mt-1.5">
                  {{ approval.status === 'approved' ? 'Approved' : 'Rejected' }} by {{ approval.respondedBy.name }}
                </p>
              </div>

              <!-- Actions -->
              <div class="shrink-0">
                <div v-if="approval.status === 'pending'" class="flex items-center gap-2">
                  <button
                    type="button"
                    class="px-3 py-1.5 text-xs font-medium rounded-md bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 hover:bg-neutral-800 dark:hover:bg-neutral-100 transition-colors duration-150 disabled:opacity-50"
                    :disabled="processing === approval.id"
                    @click.prevent="handleApproval(approval.id, 'approved')"
                  >
                    Approve
                  </button>
                  <button
                    type="button"
                    class="px-3 py-1.5 text-xs font-medium rounded-md text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors duration-150 disabled:opacity-50"
                    :disabled="processing === approval.id"
                    @click.prevent="handleApproval(approval.id, 'rejected')"
                  >
                    Reject
                  </button>
                </div>
                <span
                  v-else
                  :class="[
                    'inline-flex items-center gap-1 text-xs',
                    approval.status === 'approved' ? 'text-green-600 dark:text-green-400' : 'text-neutral-400 dark:text-neutral-500',
                  ]"
                >
                  <Icon :name="approval.status === 'approved' ? 'ph:check-circle' : 'ph:x-circle'" class="w-3.5 h-3.5" />
                  {{ approval.status === 'approved' ? 'Approved' : 'Rejected' }}
                </span>
              </div>
            </div>
          </Link>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import Icon from '@/Components/shared/Icon.vue'
import SearchInput from '@/Components/shared/SearchInput.vue'
import DropdownMenu from '@/Components/shared/DropdownMenu.vue'
import { useApi } from '@/composables/useApi'
import { useRealtime } from '@/composables/useRealtime'
import { useWorkspace } from '@/composables/useWorkspace'
import type { ApprovalRequest } from '@/types'

const page = usePage()
const { fetchApprovals: apiFetchApprovals, respondToApproval } = useApi()
const { workspacePath } = useWorkspace()
const { on } = useRealtime()

const currentUserId = computed(() => (page.props.auth as any)?.user?.id || '')

// Filters
const statusFilter = ref<'all' | 'pending' | 'approved' | 'rejected'>('pending')
const typeFilter = ref<'all' | 'action' | 'spawn' | 'access'>('all')
const searchQuery = ref('')
const processing = ref<string | null>(null)

// Data
const { data: approvals, loading, refresh } = apiFetchApprovals()

// Status tabs
const statusTabs = computed(() => {
  const all = approvals.value ?? []
  return [
    { label: 'All', value: 'all' as const, count: all.length },
    { label: 'Pending', value: 'pending' as const, dot: 'bg-amber-500', count: all.filter(a => a.status === 'pending').length },
    { label: 'Approved', value: 'approved' as const, dot: 'bg-green-500', count: all.filter(a => a.status === 'approved').length },
    { label: 'Rejected', value: 'rejected' as const, dot: 'bg-neutral-400', count: all.filter(a => a.status === 'rejected').length },
  ]
})

// Type filter config
const typeFilters = [
  { value: 'all' as const, label: 'All types', icon: 'ph:funnel' },
  { value: 'action' as const, label: 'Actions', icon: 'ph:lightning' },
  { value: 'spawn' as const, label: 'Spawns', icon: 'ph:robot' },
  { value: 'access' as const, label: 'Access', icon: 'ph:lock-key' },
]

const activeTypeLabel = computed(() =>
  typeFilters.find(t => t.value === typeFilter.value)?.label ?? 'All types'
)

const activeTypeIcon = computed(() =>
  typeFilters.find(t => t.value === typeFilter.value)?.icon ?? 'ph:funnel'
)

// Filtered list
const filteredApprovals = computed(() => {
  let result = approvals.value ?? []
  if (statusFilter.value !== 'all') {
    result = result.filter(a => a.status === statusFilter.value)
  }
  if (typeFilter.value !== 'all') {
    result = result.filter(a => a.type === typeFilter.value)
  }
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase()
    result = result.filter(a =>
      a.title.toLowerCase().includes(q) ||
      a.description?.toLowerCase().includes(q) ||
      a.requester?.name?.toLowerCase().includes(q)
    )
  }
  return result
})

// Type helpers
const typeIcon = (type: string): string => {
  switch (type) {
    case 'action': return 'ph:lightning'
    case 'spawn': return 'ph:robot'
    case 'access': return 'ph:lock-key'
    case 'budget': return 'ph:currency-circle-dollar'
    default: return 'ph:question'
  }
}

const typeBg = (type: string): string => {
  switch (type) {
    case 'action': return 'bg-blue-100 dark:bg-blue-900/30'
    case 'spawn': return 'bg-purple-100 dark:bg-purple-900/30'
    case 'access': return 'bg-amber-100 dark:bg-amber-900/30'
    case 'budget': return 'bg-green-100 dark:bg-green-900/30'
    default: return 'bg-neutral-100 dark:bg-neutral-700'
  }
}

const typeColor = (type: string): string => {
  switch (type) {
    case 'action': return 'text-blue-600 dark:text-blue-400'
    case 'spawn': return 'text-purple-600 dark:text-purple-400'
    case 'access': return 'text-amber-600 dark:text-amber-400'
    case 'budget': return 'text-green-600 dark:text-green-400'
    default: return 'text-neutral-500 dark:text-neutral-400'
  }
}

const typeBadge = (type: string): string => {
  switch (type) {
    case 'action': return 'bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400'
    case 'spawn': return 'bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400'
    case 'access': return 'bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400'
    case 'budget': return 'bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400'
    default: return 'bg-neutral-100 dark:bg-neutral-700 text-neutral-500 dark:text-neutral-400'
  }
}

// Actions
const handleApproval = async (id: string, status: 'approved' | 'rejected') => {
  processing.value = id
  try {
    await respondToApproval(id, status, currentUserId.value)
    await refresh()
  } catch (error) {
    console.error('Failed to update approval:', error)
  } finally {
    processing.value = null
  }
}

// Formatters
const formatTimeAgo = (dateString?: string | Date): string => {
  if (!dateString) return ''
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

// Real-time updates
const unsubApproval = on('approval:created', () => {
  refresh()
})

onUnmounted(() => {
  unsubApproval()
})
</script>
