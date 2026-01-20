<template>
  <div v-if="approvals.length > 0 || loading" :class="containerClasses">
    <!-- Alert Banner -->
    <div :class="bannerClasses">
      <!-- Header -->
      <div :class="headerClasses">
        <div class="flex items-center gap-3">
          <div :class="headerIconClasses">
            <Icon
              :name="urgentCount > 0 ? 'ph:warning-circle-fill' : 'ph:clock-countdown-fill'"
              :class="headerIconInnerClasses"
            />
            <!-- Pulse animation for urgent -->
            <span v-if="urgentCount > 0" class="absolute inset-0 rounded-lg bg-red-500/30 animate-ping" />
          </div>
          <div>
            <h2 :class="titleClasses">{{ title }}</h2>
            <p :class="subtitleClasses">
              <span v-if="urgentCount > 0" class="text-red-400">
                {{ urgentCount }} urgent
                <span v-if="normalCount > 0">, </span>
              </span>
              <span v-if="normalCount > 0">{{ normalCount }} pending</span>
              {{ approvals.length === 1 ? ' approval' : ' approvals' }}
            </p>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <!-- Bulk Actions -->
          <div v-if="showBulkActions && approvals.length > 1" class="flex items-center gap-1">
            <TooltipProvider :delay-duration="300">
              <TooltipRoot>
                <TooltipTrigger as-child>
                  <button
                    type="button"
                    :class="bulkActionButtonClasses"
                    :disabled="approvingAll"
                    @click="handleApproveAll"
                  >
                    <Icon
                      :name="approvingAll ? 'ph:spinner' : 'ph:checks'"
                      :class="['w-4 h-4', approvingAll && 'animate-spin']"
                    />
                  </button>
                </TooltipTrigger>
                <TooltipPortal>
                  <TooltipContent :class="tooltipClasses" side="bottom">
                    Approve all
                    <TooltipArrow class="fill-olympus-elevated" />
                  </TooltipContent>
                </TooltipPortal>
              </TooltipRoot>
            </TooltipProvider>
          </div>

          <!-- Expand/Collapse Toggle -->
          <button
            v-if="approvals.length > 1"
            type="button"
            :class="expandButtonClasses"
            @click="expanded = !expanded"
          >
            <span>{{ expanded ? 'Show less' : 'Show all' }}</span>
            <Icon
              :name="expanded ? 'ph:caret-up' : 'ph:caret-down'"
              class="w-3.5 h-3.5"
            />
          </button>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="divide-y divide-amber-500/10">
        <ApprovalItemSkeleton v-for="i in 2" :key="i" />
      </div>

      <!-- Approval Items -->
      <div v-else class="divide-y divide-amber-500/10">
        <TransitionGroup :name="animated ? 'approval-list' : ''" tag="div">
          <DashboardPendingApprovalItem
            v-for="approval in displayedApprovals"
            :key="approval.id"
            :approval="approval"
            :size="size"
            :show-details="showDetails"
            :show-timestamp="showTimestamp"
            :approving="approvingIds.includes(approval.id)"
            :rejecting="rejectingIds.includes(approval.id)"
            @approve="handleApprove(approval)"
            @reject="handleReject(approval)"
            @view-details="emit('viewDetails', approval)"
          />
        </TransitionGroup>
      </div>

      <!-- Collapsed indicator -->
      <Transition name="fade">
        <button
          v-if="approvals.length > 1 && !expanded && !loading"
          type="button"
          :class="collapsedIndicatorClasses"
          @click="expanded = true"
        >
          <div class="flex items-center gap-2">
            <div class="flex -space-x-2">
              <SharedAgentAvatar
                v-for="approval in hiddenApprovals.slice(0, 3)"
                :key="approval.id"
                :user="approval.requester"
                size="xs"
                class="ring-2 ring-amber-500/20"
              />
            </div>
            <span>+ {{ hiddenApprovals.length }} more {{ hiddenApprovals.length === 1 ? 'request' : 'requests' }}</span>
          </div>
          <Icon name="ph:caret-down" class="w-3.5 h-3.5" />
        </button>
      </Transition>

      <!-- Footer Stats -->
      <div v-if="showStats && !loading" :class="footerStatsClasses">
        <div class="flex items-center gap-4">
          <!-- Total Cost -->
          <div v-if="totalCost > 0" class="flex items-center gap-1.5">
            <Icon name="ph:coins" class="w-3.5 h-3.5 text-amber-400" />
            <span class="text-xs text-amber-300">
              Total: ${{ totalCost.toLocaleString() }}
            </span>
          </div>

          <!-- Avg Response Time -->
          <div v-if="avgResponseTime" class="flex items-center gap-1.5">
            <Icon name="ph:clock" class="w-3.5 h-3.5 text-amber-400/70" />
            <span class="text-xs text-amber-400/70">
              Avg response: {{ formatDuration(avgResponseTime) }}
            </span>
          </div>
        </div>

        <!-- Quick Filter -->
        <div v-if="showFilter" class="flex items-center gap-1">
          <button
            v-for="filter in filterOptions"
            :key="filter.value"
            type="button"
            :class="[filterButtonClasses, activeFilter === filter.value && 'bg-amber-500/20 text-amber-300']"
            @click="activeFilter = filter.value"
          >
            {{ filter.label }}
            <span v-if="getFilterCount(filter.value) > 0" class="ml-1 text-[10px] opacity-70">
              ({{ getFilterCount(filter.value) }})
            </span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { h } from 'vue'
import {
  TooltipArrow,
  TooltipContent,
  TooltipPortal,
  TooltipProvider,
  TooltipRoot,
  TooltipTrigger,
} from 'reka-ui'
import type { ApprovalRequest } from '~/types'

type PendingApprovalsSize = 'sm' | 'md' | 'lg'
type ApprovalFilter = 'all' | 'urgent' | 'cost' | 'access'

const props = withDefaults(defineProps<{
  // Core
  approvals: ApprovalRequest[]

  // Appearance
  size?: PendingApprovalsSize

  // Display options
  showBulkActions?: boolean
  showDetails?: boolean
  showTimestamp?: boolean
  showStats?: boolean
  showFilter?: boolean

  // Content
  title?: string

  // State
  loading?: boolean
  approvingIds?: string[]
  rejectingIds?: string[]
  approvingAll?: boolean

  // Stats
  avgResponseTime?: number

  // Behavior
  animated?: boolean
}>(), {
  size: 'md',
  showBulkActions: true,
  showDetails: true,
  showTimestamp: true,
  showStats: false,
  showFilter: false,
  title: 'Action Required',
  loading: false,
  approvingIds: () => [],
  rejectingIds: () => [],
  approvingAll: false,
  avgResponseTime: undefined,
  animated: true,
})

const emit = defineEmits<{
  approve: [approval: ApprovalRequest]
  reject: [approval: ApprovalRequest]
  approveAll: []
  viewDetails: [approval: ApprovalRequest]
}>()

// State
const expanded = ref(false)
const activeFilter = ref<ApprovalFilter>('all')

// Filter options
const filterOptions: { value: ApprovalFilter; label: string }[] = [
  { value: 'all', label: 'All' },
  { value: 'urgent', label: 'Urgent' },
  { value: 'cost', label: 'Cost' },
  { value: 'access', label: 'Access' },
]

// Size configuration
const sizeConfig: Record<PendingApprovalsSize, {
  headerPadding: string
  iconSize: string
  iconContainer: string
  title: string
  subtitle: string
}> = {
  sm: {
    headerPadding: 'px-3 py-2',
    iconSize: 'w-4 h-4',
    iconContainer: 'w-8 h-8',
    title: 'text-xs',
    subtitle: 'text-[10px]',
  },
  md: {
    headerPadding: 'px-4 py-3',
    iconSize: 'w-5 h-5',
    iconContainer: 'w-9 h-9',
    title: 'text-sm',
    subtitle: 'text-xs',
  },
  lg: {
    headerPadding: 'px-5 py-4',
    iconSize: 'w-6 h-6',
    iconContainer: 'w-10 h-10',
    title: 'text-base',
    subtitle: 'text-sm',
  },
}

// Computed values
const filteredApprovals = computed(() => {
  if (activeFilter.value === 'all') return props.approvals
  if (activeFilter.value === 'urgent') return props.approvals.filter(a => a.urgent)
  if (activeFilter.value === 'cost') return props.approvals.filter(a => a.type === 'cost')
  if (activeFilter.value === 'access') return props.approvals.filter(a => a.type === 'access')
  return props.approvals
})

const displayedApprovals = computed(() =>
  expanded.value ? filteredApprovals.value : filteredApprovals.value.slice(0, 1)
)

const hiddenApprovals = computed(() =>
  filteredApprovals.value.slice(1)
)

const urgentCount = computed(() =>
  props.approvals.filter(a => a.urgent).length
)

const normalCount = computed(() =>
  props.approvals.filter(a => !a.urgent).length
)

const totalCost = computed(() =>
  props.approvals.reduce((sum, a) => sum + (a.amount || 0), 0)
)

const getFilterCount = (filter: ApprovalFilter): number => {
  if (filter === 'all') return props.approvals.length
  if (filter === 'urgent') return urgentCount.value
  if (filter === 'cost') return props.approvals.filter(a => a.type === 'cost').length
  if (filter === 'access') return props.approvals.filter(a => a.type === 'access').length
  return 0
}

// Container classes
const containerClasses = computed(() => [
  'mb-6',
])

// Banner classes
const bannerClasses = computed(() => [
  'rounded-xl overflow-hidden',
  'shadow-lg',
  urgentCount.value > 0
    ? 'bg-red-500/10 border border-red-500/30 shadow-red-500/10'
    : 'bg-amber-500/10 border border-amber-500/30 shadow-amber-500/10',
])

// Header classes
const headerClasses = computed(() => [
  'border-b flex items-center justify-between',
  sizeConfig[props.size].headerPadding,
  urgentCount.value > 0
    ? 'border-red-500/20'
    : 'border-amber-500/20',
])

const headerIconClasses = computed(() => [
  'relative rounded-lg flex items-center justify-center',
  sizeConfig[props.size].iconContainer,
  urgentCount.value > 0
    ? 'bg-red-500/20'
    : 'bg-amber-500/20',
])

const headerIconInnerClasses = computed(() => [
  sizeConfig[props.size].iconSize,
  urgentCount.value > 0
    ? 'text-red-400'
    : 'text-amber-400',
])

const titleClasses = computed(() => [
  'font-semibold',
  sizeConfig[props.size].title,
  urgentCount.value > 0
    ? 'text-red-200'
    : 'text-amber-200',
])

const subtitleClasses = computed(() => [
  sizeConfig[props.size].subtitle,
  urgentCount.value > 0
    ? 'text-red-400/70'
    : 'text-amber-400/70',
])

// Bulk action button classes
const bulkActionButtonClasses = computed(() => [
  'p-2 rounded-lg transition-colors duration-150 outline-none',
  'hover:bg-green-500/20 text-green-400',
  'focus-visible:ring-2 focus-visible:ring-green-400/50',
  'disabled:opacity-50 disabled:cursor-not-allowed',
])

// Expand button classes
const expandButtonClasses = computed(() => [
  'flex items-center gap-1.5 text-sm transition-colors duration-150 outline-none',
  'rounded px-2 py-1 -mr-2',
  'focus-visible:ring-2',
  urgentCount.value > 0
    ? 'text-red-400 hover:text-red-300 focus-visible:ring-red-400/50'
    : 'text-amber-400 hover:text-amber-300 focus-visible:ring-amber-400/50',
])

// Tooltip classes
const tooltipClasses = computed(() => [
  'z-50 bg-olympus-elevated border border-olympus-border rounded-lg',
  'px-2 py-1 text-xs shadow-lg',
  'animate-in fade-in-0 zoom-in-95 duration-100',
])

// Collapsed indicator classes
const collapsedIndicatorClasses = computed(() => [
  'w-full py-2.5 text-xs flex items-center justify-between px-4',
  'transition-colors duration-150 outline-none',
  urgentCount.value > 0
    ? 'text-red-400/70 hover:text-red-400 hover:bg-red-500/5 focus-visible:bg-red-500/10'
    : 'text-amber-400/70 hover:text-amber-400 hover:bg-amber-500/5 focus-visible:bg-amber-500/10',
])

// Footer stats classes
const footerStatsClasses = computed(() => [
  'flex items-center justify-between px-4 py-2 border-t',
  urgentCount.value > 0
    ? 'border-red-500/20 bg-red-500/5'
    : 'border-amber-500/20 bg-amber-500/5',
])

// Filter button classes
const filterButtonClasses = computed(() => [
  'px-2 py-0.5 text-[10px] rounded transition-colors duration-150',
  urgentCount.value > 0
    ? 'text-red-400/70 hover:text-red-400 hover:bg-red-500/10'
    : 'text-amber-400/70 hover:text-amber-400 hover:bg-amber-500/10',
])

// Helper functions
const formatDuration = (seconds: number): string => {
  if (seconds < 60) return `${seconds}s`
  const minutes = Math.floor(seconds / 60)
  if (minutes < 60) return `${minutes}m`
  const hours = Math.floor(minutes / 60)
  return `${hours}h ${minutes % 60}m`
}

// Handlers
const handleApprove = (approval: ApprovalRequest) => {
  emit('approve', approval)
}

const handleReject = (approval: ApprovalRequest) => {
  emit('reject', approval)
}

const handleApproveAll = () => {
  emit('approveAll')
}

// Approval Item Skeleton
const ApprovalItemSkeleton = defineComponent({
  name: 'ApprovalItemSkeleton',
  setup() {
    return () => h('div', {
      class: 'p-4 flex items-start gap-3 animate-pulse',
    }, [
      h(resolveComponent('SharedSkeleton'), { variant: 'avatar' }),
      h('div', { class: 'flex-1 space-y-2' }, [
        h('div', { class: 'flex items-center gap-2' }, [
          h(resolveComponent('SharedSkeleton'), { customClass: 'h-4 w-32' }),
          h(resolveComponent('SharedSkeleton'), { customClass: 'h-4 w-16 rounded' }),
        ]),
        h(resolveComponent('SharedSkeleton'), { customClass: 'h-3 w-48' }),
        h(resolveComponent('SharedSkeleton'), { customClass: 'h-3 w-24' }),
      ]),
      h('div', { class: 'flex gap-2' }, [
        h(resolveComponent('SharedSkeleton'), { customClass: 'h-8 w-20 rounded-lg' }),
        h(resolveComponent('SharedSkeleton'), { customClass: 'h-8 w-16 rounded-lg' }),
      ]),
    ])
  },
})
</script>

<style scoped>
/* Approval list transitions */
.approval-list-enter-active,
.approval-list-leave-active {
  transition: all 0.3s ease;
}

.approval-list-enter-from {
  opacity: 0;
  transform: translateX(-20px);
}

.approval-list-leave-to {
  opacity: 0;
  transform: translateX(20px);
  max-height: 0;
}

.approval-list-move {
  transition: transform 0.3s ease;
}

/* Fade transition */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
