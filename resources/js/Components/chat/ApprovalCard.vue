<template>
  <div :class="wrapperClasses">
    <!-- Author info -->
    <SharedAgentAvatar :user="author" :size="avatarSize" />

    <div class="flex-1 min-w-0">
      <!-- Author Header -->
      <div class="flex items-center gap-2 mb-2">
        <span class="font-semibold">{{ author.name }}</span>
        <SharedStatusBadge
          v-if="author.type === 'agent' && author.status"
          :status="author.status"
          size="xs"
        />
        <span class="text-xs text-neutral-500 dark:text-neutral-300">
          {{ formatTime(timestamp) }}
        </span>
      </div>

      <!-- Approval Card -->
      <div :class="cardClasses">
        <!-- Header -->
        <div :class="headerClasses">
          <div class="flex items-center gap-3">
            <!-- Icon -->
            <div :class="iconContainerClasses">
              <Icon :name="headerIcon" :class="iconClasses" />
              <!-- Animated ring for pending -->
              <div
                v-if="request.status === 'pending' && animated"
                :class="iconRingClasses"
              />
            </div>

            <!-- Title & Type -->
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2">
                <p class="font-semibold text-sm">{{ headerTitle }}</p>
                <SharedBadge
                  v-if="request.priority"
                  :variant="priorityVariant"
                  size="xs"
                >
                  {{ request.priority }}
                </SharedBadge>
              </div>
              <p class="text-xs text-neutral-500 dark:text-neutral-300 capitalize flex items-center gap-1.5">
                <Icon :name="typeIcon" class="w-3 h-3" />
                {{ request.type }} request
                <span v-if="request.expiresAt" class="flex items-center gap-1">
                  <Icon name="ph:clock" class="w-3 h-3" />
                  {{ formatTimeRemaining }}
                </span>
              </p>
            </div>

            <!-- Status indicator (for resolved) -->
            <div
              v-if="request.status !== 'pending'"
              :class="statusIndicatorClasses"
            >
              <Icon :name="statusIcon" class="w-4 h-4" />
            </div>
          </div>
        </div>

        <!-- Content -->
        <div :class="contentClasses">
          <!-- Title -->
          <h4 :class="titleClasses">{{ request.title }}</h4>

          <!-- Description -->
          <p v-if="request.description" :class="descriptionClasses">
            {{ request.description }}
          </p>

          <!-- Details Section -->
          <div v-if="hasDetails" :class="detailsSectionClasses">
            <!-- Amount -->
            <div v-if="request.amount" :class="detailRowClasses">
              <div class="flex items-center gap-2">
                <Icon name="ph:currency-circle-dollar" class="w-4 h-4 text-neutral-500 dark:text-neutral-300" />
                <span class="text-sm text-neutral-500 dark:text-neutral-300">Amount requested</span>
              </div>
              <span :class="amountClasses">
                {{ formatCurrency(request.amount) }}
              </span>
            </div>

            <!-- Resource -->
            <div v-if="request.resource" :class="detailRowClasses">
              <div class="flex items-center gap-2">
                <Icon name="ph:database" class="w-4 h-4 text-neutral-500 dark:text-neutral-300" />
                <span class="text-sm text-neutral-500 dark:text-neutral-300">Resource</span>
              </div>
              <span class="text-sm font-medium text-neutral-900 dark:text-white">
                {{ request.resource }}
              </span>
            </div>

            <!-- Duration -->
            <div v-if="request.duration" :class="detailRowClasses">
              <div class="flex items-center gap-2">
                <Icon name="ph:timer" class="w-4 h-4 text-neutral-500 dark:text-neutral-300" />
                <span class="text-sm text-neutral-500 dark:text-neutral-300">Duration</span>
              </div>
              <span class="text-sm font-medium text-neutral-900 dark:text-white">
                {{ request.duration }}
              </span>
            </div>

            <!-- Scope -->
            <div v-if="request.scope" :class="detailRowClasses">
              <div class="flex items-center gap-2">
                <Icon name="ph:shield" class="w-4 h-4 text-neutral-500 dark:text-neutral-300" />
                <span class="text-sm text-neutral-500 dark:text-neutral-300">Scope</span>
              </div>
              <SharedBadge variant="secondary" size="xs">
                {{ request.scope }}
              </SharedBadge>
            </div>
          </div>

          <!-- Expandable Details -->
          <div v-if="request.details && showExpandableDetails">
            <button
              type="button"
              :class="expandButtonClasses"
              @click="detailsExpanded = !detailsExpanded"
            >
              <Icon
                name="ph:caret-down"
                :class="[
                  'w-4 h-4 transition-transform duration-150',
                  detailsExpanded && 'rotate-180',
                ]"
              />
              {{ detailsExpanded ? 'Hide details' : 'Show details' }}
            </button>

            <Transition name="expand">
              <div v-if="detailsExpanded" class="mt-2">
                <pre :class="detailsCodeClasses">{{ request.details }}</pre>
              </div>
            </Transition>
          </div>

          <!-- Risk Warning -->
          <div v-if="request.riskLevel && request.riskLevel !== 'low'" :class="riskWarningClasses">
            <Icon :name="riskIcon" class="w-4 h-4 shrink-0" />
            <div class="flex-1 min-w-0">
              <p class="font-medium text-sm">{{ riskTitle }}</p>
              <p v-if="request.riskDescription" class="text-xs opacity-80 mt-0.5">
                {{ request.riskDescription }}
              </p>
            </div>
          </div>

          <!-- Actions (Pending) -->
          <div v-if="request.status === 'pending'" :class="actionsClasses">
            <!-- Approve Button -->
            <button
              :class="approveButtonClasses"
              :disabled="loading"
              @click="handleApprove"
            >
              <Icon
                :name="loading === 'approve' ? 'ph:spinner' : 'ph:check'"
                :class="['w-4 h-4', loading === 'approve' && 'animate-spin']"
              />
              {{ approveLabel }}
            </button>

            <!-- Reject Button -->
            <button
              :class="rejectButtonClasses"
              :disabled="loading"
              @click="handleReject"
            >
              <Icon
                :name="loading === 'reject' ? 'ph:spinner' : 'ph:x'"
                :class="['w-4 h-4', loading === 'reject' && 'animate-spin']"
              />
              {{ rejectLabel }}
            </button>

            <!-- More Actions -->
            <DropdownMenu v-if="showMoreActions" :items="moreActionsItems" :side-offset="5">
              <button :class="moreButtonClasses" :disabled="loading">
                <Icon name="ph:dots-three" class="w-4 h-4" />
              </button>
            </DropdownMenu>
          </div>

          <!-- Status Display (Resolved) -->
          <div v-else :class="resolvedStatusClasses">
            <Icon :name="resolvedIcon" class="w-4 h-4" />
            <span>{{ resolvedText }}</span>
            <span v-if="request.respondedBy" class="text-xs opacity-70">
              by {{ request.respondedBy.name }}
            </span>
            <span v-if="request.respondedAt" class="text-xs opacity-50">
              {{ formatTime(request.respondedAt) }}
            </span>
          </div>

          <!-- Response Note -->
          <div v-if="request.responseNote" :class="responseNoteClasses">
            <Icon name="ph:note" class="w-4 h-4 shrink-0 text-neutral-500 dark:text-neutral-300" />
            <p class="text-sm text-neutral-500 dark:text-neutral-300 italic">
              "{{ request.responseNote }}"
            </p>
          </div>
        </div>

        <!-- Footer (metadata) -->
        <div v-if="showFooter" :class="footerClasses">
          <div class="flex items-center gap-4 text-xs text-neutral-400 dark:text-neutral-400">
            <span v-if="request.requestedAt" class="flex items-center gap-1">
              <Icon name="ph:calendar" class="w-3 h-3" />
              Requested {{ formatRelativeTime(request.requestedAt) }}
            </span>
            <span v-if="request.source" class="flex items-center gap-1">
              <Icon name="ph:code" class="w-3 h-3" />
              {{ request.source }}
            </span>
            <span v-if="request.id" class="flex items-center gap-1 font-mono">
              #{{ request.id.slice(0, 8) }}
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import DropdownMenu from '@/Components/shared/DropdownMenu.vue'
import type { ApprovalRequest, User } from '@/types'

type ApprovalCardSize = 'sm' | 'md' | 'lg'
type ApprovalCardVariant = 'default' | 'compact' | 'detailed'
type RiskLevel = 'low' | 'medium' | 'high' | 'critical'

interface MoreAction {
  label: string
  icon?: string
  variant?: 'default' | 'danger'
  action: string
}

const props = withDefaults(defineProps<{
  // Core
  request: ApprovalRequest
  author: User
  timestamp: Date

  // Appearance
  size?: ApprovalCardSize
  variant?: ApprovalCardVariant

  // Display options
  showExpandableDetails?: boolean
  showMoreActions?: boolean
  showFooter?: boolean

  // Labels
  approveLabel?: string
  rejectLabel?: string

  // State
  loading?: false | 'approve' | 'reject'

  // Visual
  animated?: boolean

  // Actions
  moreActions?: MoreAction[]
}>(), {
  size: 'md',
  variant: 'default',
  showExpandableDetails: true,
  showMoreActions: true,
  showFooter: true,
  approveLabel: 'Approve',
  rejectLabel: 'Reject',
  loading: false,
  animated: true,
  moreActions: () => [
    { label: 'Request more info', icon: 'ph:question', action: 'requestInfo' },
    { label: 'Delegate', icon: 'ph:user-switch', action: 'delegate' },
    { label: 'Snooze', icon: 'ph:alarm', action: 'snooze' },
  ],
})

const emit = defineEmits<{
  approve: []
  reject: []
  action: [action: string]
}>()

const detailsExpanded = ref(false)

// Size configurations
const sizeConfig: Record<ApprovalCardSize, {
  wrapper: string
  avatar: 'sm' | 'md' | 'lg'
  card: string
  icon: string
  title: string
}> = {
  sm: {
    wrapper: 'gap-2',
    avatar: 'sm',
    card: 'max-w-sm',
    icon: 'w-3.5 h-3.5',
    title: 'text-sm',
  },
  md: {
    wrapper: 'gap-3',
    avatar: 'md',
    card: 'max-w-md',
    icon: 'w-4 h-4',
    title: 'text-base',
  },
  lg: {
    wrapper: 'gap-4',
    avatar: 'lg',
    card: 'max-w-lg',
    icon: 'w-5 h-5',
    title: 'text-lg',
  },
}

const avatarSize = computed(() => sizeConfig[props.size].avatar)

// Has details to show
const hasDetails = computed(() =>
  props.request.amount ||
  props.request.resource ||
  props.request.duration ||
  props.request.scope
)

// Type icon
const typeIcon = computed(() => {
  const icons: Record<string, string> = {
    budget: 'ph:wallet',
    access: 'ph:key',
    deployment: 'ph:rocket',
    resource: 'ph:database',
    permission: 'ph:shield-check',
    action: 'ph:lightning',
  }
  return icons[props.request.type] || 'ph:question'
})

// Header icon
const headerIcon = computed(() => {
  if (props.request.status === 'approved') return 'ph:check-circle-fill'
  if (props.request.status === 'rejected') return 'ph:x-circle-fill'
  return 'ph:warning-circle-fill'
})

// Header title
const headerTitle = computed(() => {
  if (props.request.status === 'approved') return 'Approved'
  if (props.request.status === 'rejected') return 'Rejected'
  return 'Approval Required'
})

// Status icon
const statusIcon = computed(() => {
  return props.request.status === 'approved' ? 'ph:check-circle-fill' : 'ph:x-circle-fill'
})

// Resolved icon & text
const resolvedIcon = computed(() => {
  return props.request.status === 'approved' ? 'ph:check-circle-fill' : 'ph:x-circle-fill'
})

const resolvedText = computed(() => {
  return props.request.status === 'approved' ? 'Approved' : 'Rejected'
})

// Priority variant
const priorityVariant = computed(() => {
  const variants: Record<string, 'default' | 'primary' | 'success' | 'warning' | 'error'> = {
    low: 'default',
    medium: 'warning',
    high: 'error',
    critical: 'error',
  }
  return variants[props.request.priority || 'low'] || 'default'
})

// Risk level
const riskIcon = computed(() => {
  const icons: Record<RiskLevel, string> = {
    low: 'ph:info',
    medium: 'ph:warning',
    high: 'ph:warning-circle',
    critical: 'ph:warning-octagon',
  }
  return icons[props.request.riskLevel as RiskLevel] || 'ph:warning'
})

const riskTitle = computed(() => {
  const titles: Record<RiskLevel, string> = {
    low: 'Low risk operation',
    medium: 'Medium risk - review carefully',
    high: 'High risk - requires attention',
    critical: 'Critical - may cause irreversible changes',
  }
  return titles[props.request.riskLevel as RiskLevel] || 'Review required'
})

// Time remaining
const formatTimeRemaining = computed(() => {
  if (!props.request.expiresAt) return ''
  const now = new Date()
  const expires = new Date(props.request.expiresAt)
  const diff = expires.getTime() - now.getTime()

  if (diff <= 0) return 'Expired'

  const hours = Math.floor(diff / (1000 * 60 * 60))
  const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60))

  if (hours > 0) return `${hours}h ${minutes}m left`
  return `${minutes}m left`
})

// Wrapper classes
const wrapperClasses = computed(() => [
  'flex',
  sizeConfig[props.size].wrapper,
])

// Card classes
const cardClasses = computed(() => [
  'group/card bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 rounded-lg overflow-hidden',
  'transition-colors duration-150',
  'hover:border-neutral-300 dark:hover:border-neutral-600',
  sizeConfig[props.size].card,
])

// Header classes
const headerClasses = computed(() => {
  return [
    'px-4 py-3 border-b border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800',
  ]
})

// Icon container classes
const iconContainerClasses = computed(() => {
  return [
    'relative w-8 h-8 rounded-lg flex items-center justify-center shrink-0',
    'bg-neutral-100 dark:bg-neutral-700',
  ]
})

// Icon classes
const iconClasses = computed(() => {
  return [
    sizeConfig[props.size].icon,
    'text-neutral-600 dark:text-neutral-200',
  ]
})

// Icon ring classes
const iconRingClasses = computed(() => [
  'absolute inset-0 rounded-lg ring-2 ring-neutral-300 dark:ring-neutral-600',
])

// Status indicator classes
const statusIndicatorClasses = computed(() => {
  return [
    'w-8 h-8 rounded-lg flex items-center justify-center',
    'bg-neutral-100 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-200',
  ]
})

// Content classes
const contentClasses = computed(() => [
  'p-4',
  props.variant === 'compact' && 'p-3',
])

// Title classes
const titleClasses = computed(() => [
  'font-medium mb-2 text-neutral-900 dark:text-white',
  sizeConfig[props.size].title,
])

// Description classes
const descriptionClasses = computed(() => [
  'text-sm text-neutral-500 dark:text-neutral-300 mb-4 leading-relaxed',
])

// Details section classes
const detailsSectionClasses = computed(() => [
  'space-y-2 p-3 bg-neutral-50 dark:bg-neutral-800 rounded-lg mb-4',
])

// Detail row classes
const detailRowClasses = computed(() => [
  'flex items-center justify-between',
])

// Amount classes
const amountClasses = computed(() => [
  'font-bold text-lg text-neutral-900 dark:text-white',
])

// Expand button classes
const expandButtonClasses = computed(() => [
  'flex items-center gap-1.5 text-xs text-neutral-500 dark:text-neutral-300',
  'transition-colors duration-150',
  'hover:text-neutral-900 dark:hover:text-white',
])

// Details code classes
const detailsCodeClasses = computed(() => [
  'text-xs font-mono p-3 bg-neutral-50 dark:bg-neutral-800 rounded-lg',
  'text-neutral-500 dark:text-neutral-300 overflow-x-auto',
  'border border-neutral-200 dark:border-neutral-700',
])

// Risk warning classes
const riskWarningClasses = computed(() => {
  return [
    'flex items-start gap-2 p-3 rounded-lg border mb-4',
    'bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-600 dark:text-neutral-200',
  ]
})

// Actions classes
const actionsClasses = computed(() => [
  'flex items-center gap-2',
])

// Approve button classes
const approveButtonClasses = computed(() => [
  'flex-1 flex items-center justify-center gap-2 px-4 py-2.5',
  'bg-neutral-900 dark:bg-neutral-100 hover:bg-neutral-800 dark:hover:bg-neutral-200 text-white dark:text-neutral-900 font-medium rounded-lg',
  'transition-colors duration-150',
  'focus:outline-none focus-visible:ring-1 focus-visible:ring-neutral-400 dark:focus-visible:ring-neutral-500',
  'disabled:opacity-50 disabled:cursor-not-allowed',
])

// Reject button classes
const rejectButtonClasses = computed(() => [
  'flex-1 flex items-center justify-center gap-2 px-4 py-2.5',
  'bg-white dark:bg-neutral-900 hover:bg-neutral-50 dark:hover:bg-neutral-800 text-neutral-900 dark:text-white font-medium rounded-lg',
  'border border-neutral-200 dark:border-neutral-700 hover:border-neutral-300 dark:hover:border-neutral-600',
  'transition-colors duration-150',
  'focus:outline-none focus-visible:ring-1 focus-visible:ring-neutral-400 dark:focus-visible:ring-neutral-500',
  'disabled:opacity-50 disabled:cursor-not-allowed',
])

// More button classes
const moreButtonClasses = computed(() => [
  'p-2.5 rounded-lg',
  'bg-white dark:bg-neutral-900 hover:bg-neutral-50 dark:hover:bg-neutral-800 text-neutral-500 dark:text-neutral-300 hover:text-neutral-900 dark:hover:text-white',
  'border border-neutral-200 dark:border-neutral-700 hover:border-neutral-300 dark:hover:border-neutral-600',
  'transition-colors duration-150',
  'focus:outline-none focus-visible:ring-1 focus-visible:ring-neutral-400 dark:focus-visible:ring-neutral-500',
  'disabled:opacity-50 disabled:cursor-not-allowed',
])

// Menu content classes
const menuContentClasses = computed(() => [
  'min-w-40 bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 rounded-lg',
  'shadow-lg p-1 z-50',
  'animate-in fade-in-0 duration-150',
])

// Menu item classes
const menuItemClasses = (action: MoreAction) => [
  'flex items-center gap-2 px-3 py-2 text-sm rounded-lg cursor-pointer outline-none',
  'transition-colors duration-150',
  action.variant === 'danger'
    ? 'text-red-600 hover:bg-red-50 dark:hover:bg-red-950 focus:bg-red-50 dark:focus:bg-red-950'
    : 'text-neutral-500 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-800 focus:bg-neutral-50 dark:focus:bg-neutral-800 hover:text-neutral-900 dark:hover:text-white focus:text-neutral-900 dark:focus:text-white',
]

// Resolved status classes
const resolvedStatusClasses = computed(() => {
  return [
    'flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg font-medium',
    'bg-neutral-100 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-200',
  ]
})

// Response note classes
const responseNoteClasses = computed(() => [
  'flex items-start gap-2 mt-3 p-3 bg-neutral-50 dark:bg-neutral-800 rounded-lg border border-neutral-200 dark:border-neutral-700',
])

// Footer classes
const footerClasses = computed(() => [
  'px-4 py-2 border-t border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800',
])

// Format time
const formatTime = (date: Date) => {
  return new Date(date).toLocaleTimeString('en-US', {
    hour: 'numeric',
    minute: '2-digit',
  })
}

// Format relative time
const formatRelativeTime = (date: Date): string => {
  const now = new Date()
  const diff = now.getTime() - new Date(date).getTime()
  const minutes = Math.floor(diff / 60000)
  const hours = Math.floor(minutes / 60)
  const days = Math.floor(hours / 24)

  if (minutes < 1) return 'just now'
  if (minutes < 60) return `${minutes}m ago`
  if (hours < 24) return `${hours}h ago`
  if (days === 1) return 'yesterday'
  if (days < 7) return `${days} days ago`
  return new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

// Format currency
const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  }).format(amount)
}

// More actions items for UDropdownMenu
const moreActionsItems = computed(() => [
  props.moreActions.map(action => ({
    label: action.label,
    icon: action.icon,
    color: action.variant === 'danger' ? 'error' as const : undefined,
    click: () => handleMoreAction(action),
  })),
])

// Handlers
const handleApprove = () => {
  emit('approve')
}

const handleReject = () => {
  emit('reject')
}

const handleMoreAction = (action: MoreAction) => {
  emit('action', action.action)
}
</script>

<style scoped>
/* Expand transition */
.expand-enter-active,
.expand-leave-active {
  transition: opacity 0.15s ease-out;
  overflow: hidden;
}

.expand-enter-from,
.expand-leave-to {
  opacity: 0;
}

.expand-enter-to,
.expand-leave-from {
  opacity: 1;
}
</style>
