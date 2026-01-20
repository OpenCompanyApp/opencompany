<template>
  <div
    :class="containerClasses"
    @mouseenter="hovered = true"
    @mouseleave="hovered = false"
  >
    <div class="flex items-start gap-3">
      <!-- Requester Avatar -->
      <div class="relative shrink-0">
        <SharedAgentAvatar :user="approval.requester" :size="avatarSize" />
        <!-- Priority indicator -->
        <span
          v-if="approval.urgent"
          :class="urgentIndicatorClasses"
        >
          <Icon name="ph:warning-fill" class="w-2.5 h-2.5" />
        </span>
      </div>

      <!-- Content -->
      <div class="flex-1 min-w-0">
        <!-- Header -->
        <div class="flex items-center gap-2 mb-1 flex-wrap">
          <span :class="titleClasses">{{ approval.title }}</span>

          <!-- Type Badge -->
          <span :class="typeBadgeClasses">
            <Icon :name="getTypeIcon(approval.type)" class="w-3 h-3" />
            {{ approval.type }}
          </span>

          <!-- Priority Badge -->
          <span v-if="approval.urgent" :class="priorityBadgeClasses">
            <Icon name="ph:fire" class="w-3 h-3" />
            Urgent
          </span>

          <!-- New Badge -->
          <span v-if="isNew" :class="newBadgeClasses">
            NEW
          </span>
        </div>

        <!-- Description -->
        <p v-if="approval.description && showDescription" :class="descriptionClasses">
          {{ approval.description }}
        </p>

        <!-- Amount/Cost Display -->
        <div v-if="approval.amount" :class="amountContainerClasses">
          <Icon name="ph:coins" :class="amountIconClasses" />
          <span :class="amountTextClasses">
            ${{ approval.amount.toLocaleString() }}
          </span>
          <span v-if="approval.budget" class="text-olympus-text-subtle">
            / ${{ approval.budget.toLocaleString() }} budget
          </span>
        </div>

        <!-- Expandable Details -->
        <CollapsibleRoot v-if="showExpandableDetails && hasDetails" v-model:open="detailsExpanded">
          <CollapsibleTrigger :class="detailsToggleClasses">
            <Icon
              :name="detailsExpanded ? 'ph:caret-up' : 'ph:caret-down'"
              class="w-3 h-3"
            />
            <span>{{ detailsExpanded ? 'Hide' : 'Show' }} details</span>
          </CollapsibleTrigger>
          <CollapsibleContent class="overflow-hidden data-[state=open]:animate-slideDown data-[state=closed]:animate-slideUp">
            <div :class="detailsContainerClasses">
              <!-- Reason -->
              <div v-if="approval.reason" class="flex items-start gap-2">
                <Icon name="ph:info" class="w-4 h-4 text-olympus-text-subtle mt-0.5 shrink-0" />
                <p class="text-sm text-olympus-text-secondary">{{ approval.reason }}</p>
              </div>

              <!-- Resources -->
              <div v-if="approval.resources?.length" class="flex items-start gap-2">
                <Icon name="ph:folder" class="w-4 h-4 text-olympus-text-subtle mt-0.5 shrink-0" />
                <div class="flex flex-wrap gap-1">
                  <span
                    v-for="resource in approval.resources"
                    :key="resource"
                    class="px-2 py-0.5 text-xs rounded bg-olympus-bg border border-olympus-border"
                  >
                    {{ resource }}
                  </span>
                </div>
              </div>

              <!-- Impact -->
              <div v-if="approval.impact" class="flex items-start gap-2">
                <Icon name="ph:lightning" class="w-4 h-4 text-olympus-text-subtle mt-0.5 shrink-0" />
                <p class="text-sm text-olympus-text-secondary">{{ approval.impact }}</p>
              </div>

              <!-- Related Approvals -->
              <div v-if="approval.relatedApprovals?.length" class="flex items-start gap-2">
                <Icon name="ph:link" class="w-4 h-4 text-olympus-text-subtle mt-0.5 shrink-0" />
                <div class="space-y-1">
                  <p class="text-xs text-olympus-text-muted">Related requests:</p>
                  <div class="flex flex-wrap gap-1">
                    <button
                      v-for="related in approval.relatedApprovals"
                      :key="related.id"
                      type="button"
                      class="text-xs text-olympus-primary hover:text-olympus-primary-hover transition-colors"
                      @click="emit('viewRelated', related)"
                    >
                      {{ related.title }}
                    </button>
                  </div>
                </div>
              </div>

              <!-- Attachments -->
              <div v-if="approval.attachments?.length" class="flex items-start gap-2">
                <Icon name="ph:paperclip" class="w-4 h-4 text-olympus-text-subtle mt-0.5 shrink-0" />
                <div class="flex flex-wrap gap-1">
                  <button
                    v-for="attachment in approval.attachments"
                    :key="attachment.name"
                    type="button"
                    class="flex items-center gap-1 px-2 py-1 text-xs rounded bg-olympus-bg border border-olympus-border hover:border-olympus-border-hover transition-colors"
                    @click="emit('viewAttachment', attachment)"
                  >
                    <Icon :name="getFileIcon(attachment.type)" class="w-3 h-3" />
                    {{ attachment.name }}
                  </button>
                </div>
              </div>
            </div>
          </CollapsibleContent>
        </CollapsibleRoot>

        <!-- Meta Info -->
        <div :class="metaClasses">
          <!-- Requester Info with Tooltip -->
          <TooltipProvider :delay-duration="300">
            <TooltipRoot>
              <TooltipTrigger as-child>
                <button type="button" class="flex items-center gap-1 hover:text-olympus-text-secondary transition-colors">
                  <span>Requested by {{ approval.requester.name }}</span>
                  <Icon v-if="approval.requester.isAgent" name="ph:robot" class="w-3 h-3" />
                </button>
              </TooltipTrigger>
              <TooltipPortal>
                <TooltipContent :class="tooltipClasses" side="bottom">
                  <div class="space-y-1">
                    <div class="flex items-center gap-2">
                      <SharedAgentAvatar :user="approval.requester" size="xs" />
                      <span class="font-medium">{{ approval.requester.name }}</span>
                    </div>
                    <div v-if="approval.requester.agentType" class="text-olympus-text-muted capitalize">
                      {{ approval.requester.agentType }}
                    </div>
                    <div v-if="approval.requester.email" class="text-olympus-text-muted">
                      {{ approval.requester.email }}
                    </div>
                  </div>
                  <TooltipArrow class="fill-olympus-elevated" />
                </TooltipContent>
              </TooltipPortal>
            </TooltipRoot>
          </TooltipProvider>

          <!-- Timestamp -->
          <span v-if="showTimestamp && approval.createdAt" class="flex items-center gap-1">
            <Icon name="ph:clock" class="w-3 h-3" />
            {{ formatTimeAgo(approval.createdAt) }}
          </span>

          <!-- Expiry Warning -->
          <span v-if="approval.expiresAt && isExpiringSoon" :class="expiryWarningClasses">
            <Icon name="ph:timer" class="w-3 h-3" />
            Expires {{ formatTimeRemaining(approval.expiresAt) }}
          </span>
        </div>
      </div>

      <!-- Actions -->
      <div :class="actionsContainerClasses">
        <!-- Quick Actions (visible on hover in compact mode) -->
        <Transition name="fade">
          <div v-if="size === 'sm' && hovered" class="flex items-center gap-1 mr-2">
            <TooltipProvider :delay-duration="200">
              <TooltipRoot>
                <TooltipTrigger as-child>
                  <button
                    type="button"
                    class="p-1.5 rounded-md text-olympus-text-muted hover:text-olympus-text-secondary hover:bg-olympus-elevated transition-colors"
                    @click="emit('viewDetails')"
                  >
                    <Icon name="ph:eye" class="w-4 h-4" />
                  </button>
                </TooltipTrigger>
                <TooltipPortal>
                  <TooltipContent :class="tooltipClasses" side="top">
                    View details
                    <TooltipArrow class="fill-olympus-elevated" />
                  </TooltipContent>
                </TooltipPortal>
              </TooltipRoot>
            </TooltipProvider>
          </div>
        </Transition>

        <!-- Main Action Buttons -->
        <div class="flex items-center gap-2">
          <!-- Approve Button -->
          <TooltipProvider v-if="size === 'sm'" :delay-duration="200">
            <TooltipRoot>
              <TooltipTrigger as-child>
                <button
                  type="button"
                  :class="approveButtonClasses"
                  :disabled="approving || rejecting"
                  @click="handleApprove"
                >
                  <Icon
                    :name="approving ? 'ph:spinner' : 'ph:check'"
                    :class="['w-4 h-4', approving && 'animate-spin']"
                  />
                </button>
              </TooltipTrigger>
              <TooltipPortal>
                <TooltipContent :class="tooltipClasses" side="top">
                  Approve
                  <TooltipArrow class="fill-olympus-elevated" />
                </TooltipContent>
              </TooltipPortal>
            </TooltipRoot>
          </TooltipProvider>

          <SharedButton
            v-else
            variant="success"
            :size="buttonSize"
            icon-left="ph:check"
            :loading="approving"
            :disabled="rejecting"
            @click="handleApprove"
          >
            {{ approveLabel }}
          </SharedButton>

          <!-- Reject Button -->
          <TooltipProvider v-if="size === 'sm'" :delay-duration="200">
            <TooltipRoot>
              <TooltipTrigger as-child>
                <button
                  type="button"
                  :class="rejectButtonClasses"
                  :disabled="approving || rejecting"
                  @click="handleReject"
                >
                  <Icon
                    :name="rejecting ? 'ph:spinner' : 'ph:x'"
                    :class="['w-4 h-4', rejecting && 'animate-spin']"
                  />
                </button>
              </TooltipTrigger>
              <TooltipPortal>
                <TooltipContent :class="tooltipClasses" side="top">
                  Reject
                  <TooltipArrow class="fill-olympus-elevated" />
                </TooltipContent>
              </TooltipPortal>
            </TooltipRoot>
          </TooltipProvider>

          <SharedButton
            v-else
            variant="secondary"
            :size="buttonSize"
            icon-left="ph:x"
            :loading="rejecting"
            :disabled="approving"
            @click="handleReject"
          >
            {{ rejectLabel }}
          </SharedButton>

          <!-- More Options Dropdown -->
          <DropdownMenuRoot v-if="showMoreOptions">
            <DropdownMenuTrigger as-child>
              <button type="button" :class="moreOptionsButtonClasses">
                <Icon name="ph:dots-three-vertical" class="w-4 h-4" />
              </button>
            </DropdownMenuTrigger>
            <DropdownMenuPortal>
              <DropdownMenuContent :class="dropdownContentClasses" :side-offset="8" align="end">
                <DropdownMenuItem :class="dropdownItemClasses" @select="emit('viewDetails')">
                  <Icon name="ph:eye" class="w-4 h-4 mr-2" />
                  View details
                </DropdownMenuItem>
                <DropdownMenuItem v-if="approval.requester" :class="dropdownItemClasses" @select="emit('viewRequester', approval.requester)">
                  <Icon name="ph:user" class="w-4 h-4 mr-2" />
                  View requester
                </DropdownMenuItem>
                <DropdownMenuSeparator class="h-px bg-olympus-border my-1" />
                <DropdownMenuItem :class="dropdownItemClasses" @select="emit('delegate')">
                  <Icon name="ph:share" class="w-4 h-4 mr-2" />
                  Delegate
                </DropdownMenuItem>
                <DropdownMenuItem :class="dropdownItemClasses" @select="emit('snooze')">
                  <Icon name="ph:alarm" class="w-4 h-4 mr-2" />
                  Snooze
                </DropdownMenuItem>
                <DropdownMenuSeparator class="h-px bg-olympus-border my-1" />
                <DropdownMenuItem :class="[dropdownItemClasses, 'text-red-400']" @select="emit('flag')">
                  <Icon name="ph:flag" class="w-4 h-4 mr-2" />
                  Flag for review
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenuPortal>
          </DropdownMenuRoot>
        </div>
      </div>
    </div>

    <!-- Rejection Reason Dialog -->
    <AlertDialogRoot v-model:open="showRejectDialog">
      <AlertDialogPortal>
        <AlertDialogOverlay class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0" />
        <AlertDialogContent :class="dialogContentClasses">
          <AlertDialogTitle class="text-lg font-semibold text-olympus-text-primary mb-2">
            Reject Request
          </AlertDialogTitle>
          <AlertDialogDescription class="text-sm text-olympus-text-muted mb-4">
            Are you sure you want to reject "{{ approval.title }}"? This action cannot be undone.
          </AlertDialogDescription>

          <!-- Reason Input -->
          <div v-if="requireReason" class="mb-4">
            <label class="block text-sm text-olympus-text-secondary mb-1.5">
              Reason for rejection
              <span class="text-red-400">*</span>
            </label>
            <textarea
              v-model="rejectReason"
              :class="reasonInputClasses"
              placeholder="Please provide a reason for rejecting this request..."
              rows="3"
            />
          </div>

          <!-- Quick Reasons -->
          <div v-if="showQuickReasons" class="mb-4">
            <p class="text-xs text-olympus-text-muted mb-2">Quick reasons:</p>
            <div class="flex flex-wrap gap-1">
              <button
                v-for="reason in quickReasons"
                :key="reason"
                type="button"
                :class="[
                  'px-2 py-1 text-xs rounded transition-colors',
                  rejectReason === reason
                    ? 'bg-olympus-primary/20 text-olympus-primary border border-olympus-primary/50'
                    : 'bg-olympus-bg border border-olympus-border hover:border-olympus-border-hover',
                ]"
                @click="rejectReason = reason"
              >
                {{ reason }}
              </button>
            </div>
          </div>

          <div class="flex justify-end gap-2">
            <AlertDialogCancel as-child>
              <SharedButton variant="secondary" size="sm" @click="showRejectDialog = false">
                Cancel
              </SharedButton>
            </AlertDialogCancel>
            <AlertDialogAction as-child>
              <SharedButton
                variant="danger"
                size="sm"
                :disabled="requireReason && !rejectReason.trim()"
                @click="confirmReject"
              >
                Reject Request
              </SharedButton>
            </AlertDialogAction>
          </div>
        </AlertDialogContent>
      </AlertDialogPortal>
    </AlertDialogRoot>
  </div>
</template>

<script setup lang="ts">
import {
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogOverlay,
  AlertDialogPortal,
  AlertDialogRoot,
  AlertDialogTitle,
  CollapsibleContent,
  CollapsibleRoot,
  CollapsibleTrigger,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuPortal,
  DropdownMenuRoot,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
  TooltipArrow,
  TooltipContent,
  TooltipPortal,
  TooltipProvider,
  TooltipRoot,
  TooltipTrigger,
} from 'reka-ui'
import type { ApprovalRequest, User } from '~/types'

type ApprovalItemSize = 'sm' | 'md' | 'lg'

interface Attachment {
  name: string
  type: string
  url?: string
}

interface RelatedApproval {
  id: string
  title: string
}

interface ExtendedApprovalRequest extends ApprovalRequest {
  reason?: string
  resources?: string[]
  impact?: string
  budget?: number
  expiresAt?: Date
  relatedApprovals?: RelatedApproval[]
  attachments?: Attachment[]
}

const props = withDefaults(defineProps<{
  // Core
  approval: ExtendedApprovalRequest

  // Appearance
  size?: ApprovalItemSize
  urgent?: boolean

  // Display options
  showDescription?: boolean
  showTimestamp?: boolean
  showExpandableDetails?: boolean
  showMoreOptions?: boolean

  // Labels
  approveLabel?: string
  rejectLabel?: string

  // State
  approving?: boolean
  rejecting?: boolean

  // Behavior
  requireConfirmation?: boolean
  requireReason?: boolean
  showQuickReasons?: boolean
}>(), {
  size: 'md',
  urgent: false,
  showDescription: true,
  showTimestamp: true,
  showExpandableDetails: true,
  showMoreOptions: true,
  approveLabel: 'Approve',
  rejectLabel: 'Reject',
  approving: false,
  rejecting: false,
  requireConfirmation: true,
  requireReason: false,
  showQuickReasons: true,
})

const emit = defineEmits<{
  approve: []
  reject: [reason?: string]
  viewDetails: []
  viewRequester: [user: User]
  viewRelated: [approval: RelatedApproval]
  viewAttachment: [attachment: Attachment]
  delegate: []
  snooze: []
  flag: []
}>()

// State
const hovered = ref(false)
const detailsExpanded = ref(false)
const showRejectDialog = ref(false)
const rejectReason = ref('')

// Quick rejection reasons
const quickReasons = [
  'Exceeds budget',
  'Insufficient justification',
  'Not a priority',
  'Already completed',
  'Duplicate request',
]

// Size configuration
const sizeConfig: Record<ApprovalItemSize, {
  padding: string
  avatarSize: 'xs' | 'sm' | 'md'
  buttonSize: 'xs' | 'sm' | 'md'
  titleSize: string
  descriptionSize: string
  metaSize: string
}> = {
  sm: {
    padding: 'p-3',
    avatarSize: 'xs',
    buttonSize: 'xs',
    titleSize: 'text-sm',
    descriptionSize: 'text-xs',
    metaSize: 'text-[10px]',
  },
  md: {
    padding: 'p-4',
    avatarSize: 'sm',
    buttonSize: 'sm',
    titleSize: 'text-sm',
    descriptionSize: 'text-sm',
    metaSize: 'text-xs',
  },
  lg: {
    padding: 'p-5',
    avatarSize: 'md',
    buttonSize: 'md',
    titleSize: 'text-base',
    descriptionSize: 'text-sm',
    metaSize: 'text-xs',
  },
}

// Computed values
const avatarSize = computed(() => sizeConfig[props.size].avatarSize)
const buttonSize = computed(() => sizeConfig[props.size].buttonSize)

const isNew = computed(() => {
  if (!props.approval.createdAt) return false
  const hourAgo = Date.now() - 60 * 60 * 1000
  return props.approval.createdAt.getTime() > hourAgo
})

const isExpiringSoon = computed(() => {
  if (!props.approval.expiresAt) return false
  const hourFromNow = Date.now() + 60 * 60 * 1000
  return props.approval.expiresAt.getTime() < hourFromNow
})

const hasDetails = computed(() =>
  props.approval.reason ||
  props.approval.resources?.length ||
  props.approval.impact ||
  props.approval.relatedApprovals?.length ||
  props.approval.attachments?.length
)

const isUrgent = computed(() => props.urgent || props.approval.urgent)

// Type icon mapping
const getTypeIcon = (type: string): string => {
  const icons: Record<string, string> = {
    cost: 'ph:credit-card',
    access: 'ph:key',
    action: 'ph:lightning',
    resource: 'ph:database',
    permission: 'ph:shield-check',
  }
  return icons[type] || 'ph:question'
}

// File icon mapping
const getFileIcon = (type: string): string => {
  const icons: Record<string, string> = {
    pdf: 'ph:file-pdf',
    image: 'ph:image',
    document: 'ph:file-doc',
    spreadsheet: 'ph:file-xls',
    code: 'ph:file-code',
  }
  return icons[type] || 'ph:file'
}

// Container classes
const containerClasses = computed(() => [
  'transition-colors duration-150',
  sizeConfig[props.size].padding,
  isUrgent.value
    ? 'hover:bg-red-500/5 border-l-2 border-l-red-500'
    : 'hover:bg-amber-500/5',
])

// Urgent indicator classes
const urgentIndicatorClasses = computed(() => [
  'absolute -top-1 -right-1 w-4 h-4 rounded-full flex items-center justify-center',
  'bg-red-500 text-white',
  'animate-pulse',
])

// Title classes
const titleClasses = computed(() => [
  'font-medium',
  sizeConfig[props.size].titleSize,
  isUrgent.value ? 'text-red-200' : 'text-olympus-text-primary',
])

// Type badge classes
const typeBadgeClasses = computed(() => [
  'inline-flex items-center gap-1 px-2 py-0.5 text-xs rounded-md capitalize',
  'bg-olympus-bg text-olympus-text-muted border border-olympus-border',
])

// Priority badge classes
const priorityBadgeClasses = computed(() => [
  'inline-flex items-center gap-1 px-2 py-0.5 text-xs rounded-md',
  'bg-red-500/20 text-red-400 border border-red-500/30',
])

// New badge classes
const newBadgeClasses = computed(() => [
  'px-1.5 py-0.5 text-[10px] font-semibold rounded',
  'bg-olympus-primary/20 text-olympus-primary',
  'animate-pulse',
])

// Description classes
const descriptionClasses = computed(() => [
  'text-olympus-text-muted line-clamp-2 mb-2',
  sizeConfig[props.size].descriptionSize,
])

// Amount container classes
const amountContainerClasses = computed(() => [
  'inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg mb-2',
  'bg-olympus-bg border border-olympus-border',
])

const amountIconClasses = computed(() => [
  'text-amber-400',
  props.size === 'sm' ? 'w-3 h-3' : 'w-4 h-4',
])

const amountTextClasses = computed(() => [
  'font-semibold',
  props.size === 'sm' ? 'text-xs' : 'text-sm',
])

// Details toggle classes
const detailsToggleClasses = computed(() => [
  'flex items-center gap-1 text-xs text-olympus-text-muted hover:text-olympus-text-secondary',
  'transition-colors duration-150 outline-none mt-2',
])

// Details container classes
const detailsContainerClasses = computed(() => [
  'mt-3 pt-3 border-t border-olympus-border-subtle space-y-3',
])

// Meta classes
const metaClasses = computed(() => [
  'flex flex-wrap items-center gap-x-3 gap-y-1 text-olympus-text-subtle',
  sizeConfig[props.size].metaSize,
])

// Expiry warning classes
const expiryWarningClasses = computed(() => [
  'flex items-center gap-1 text-red-400',
])

// Actions container classes
const actionsContainerClasses = computed(() => [
  'flex items-center shrink-0',
])

// Button classes
const approveButtonClasses = computed(() => [
  'p-2 rounded-lg transition-colors duration-150',
  'bg-green-500/20 text-green-400 hover:bg-green-500/30',
  'focus:outline-none focus-visible:ring-2 focus-visible:ring-green-400/50',
  'disabled:opacity-50 disabled:cursor-not-allowed',
])

const rejectButtonClasses = computed(() => [
  'p-2 rounded-lg transition-colors duration-150',
  'bg-olympus-elevated text-olympus-text-muted hover:text-olympus-text-secondary',
  'focus:outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50',
  'disabled:opacity-50 disabled:cursor-not-allowed',
])

const moreOptionsButtonClasses = computed(() => [
  'p-2 rounded-lg transition-colors duration-150',
  'text-olympus-text-muted hover:text-olympus-text-secondary hover:bg-olympus-elevated',
  'focus:outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50',
])

// Dropdown classes
const dropdownContentClasses = computed(() => [
  'z-50 min-w-[180px] bg-olympus-elevated border border-olympus-border rounded-xl',
  'p-1 shadow-xl shadow-black/20',
  'animate-in fade-in-0 zoom-in-95 duration-100',
])

const dropdownItemClasses = computed(() => [
  'flex items-center px-2 py-1.5 text-sm rounded-lg cursor-pointer',
  'text-olympus-text-secondary hover:bg-olympus-hover',
  'transition-colors duration-100 outline-none',
  'data-[highlighted]:bg-olympus-hover',
])

// Tooltip classes
const tooltipClasses = computed(() => [
  'z-50 bg-olympus-elevated border border-olympus-border rounded-lg',
  'px-3 py-2 text-xs shadow-lg max-w-[250px]',
  'animate-in fade-in-0 zoom-in-95 duration-100',
])

// Dialog classes
const dialogContentClasses = computed(() => [
  'fixed left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 z-50',
  'w-full max-w-md p-6 rounded-2xl',
  'bg-olympus-surface border border-olympus-border',
  'shadow-xl shadow-black/30',
  'data-[state=open]:animate-in data-[state=closed]:animate-out',
  'data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0',
  'data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95',
])

// Reason input classes
const reasonInputClasses = computed(() => [
  'w-full px-3 py-2 rounded-lg',
  'bg-olympus-bg border border-olympus-border',
  'text-olympus-text-primary placeholder:text-olympus-text-subtle',
  'focus:outline-none focus:border-olympus-primary focus:ring-2 focus:ring-olympus-primary/20',
  'resize-none',
])

// Helper functions
const formatTimeAgo = (date: Date): string => {
  const seconds = Math.floor((Date.now() - date.getTime()) / 1000)
  if (seconds < 60) return 'just now'
  const minutes = Math.floor(seconds / 60)
  if (minutes < 60) return `${minutes}m ago`
  const hours = Math.floor(minutes / 60)
  if (hours < 24) return `${hours}h ago`
  const days = Math.floor(hours / 24)
  return `${days}d ago`
}

const formatTimeRemaining = (date: Date): string => {
  const seconds = Math.floor((date.getTime() - Date.now()) / 1000)
  if (seconds <= 0) return 'now'
  if (seconds < 60) return `in ${seconds}s`
  const minutes = Math.floor(seconds / 60)
  if (minutes < 60) return `in ${minutes}m`
  const hours = Math.floor(minutes / 60)
  return `in ${hours}h`
}

// Handlers
const handleApprove = () => {
  emit('approve')
}

const handleReject = () => {
  if (props.requireConfirmation) {
    showRejectDialog.value = true
  } else {
    emit('reject')
  }
}

const confirmReject = () => {
  emit('reject', rejectReason.value.trim() || undefined)
  showRejectDialog.value = false
  rejectReason.value = ''
}
</script>

<style scoped>
/* Fade transition */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

/* Collapsible animations */
@keyframes slideDown {
  from {
    height: 0;
    opacity: 0;
  }
  to {
    height: var(--reka-collapsible-content-height);
    opacity: 1;
  }
}

@keyframes slideUp {
  from {
    height: var(--reka-collapsible-content-height);
    opacity: 1;
  }
  to {
    height: 0;
    opacity: 0;
  }
}

.data-\[state\=open\]\:animate-slideDown[data-state='open'] {
  animation: slideDown 0.2s ease-out;
}

.data-\[state\=closed\]\:animate-slideUp[data-state='closed'] {
  animation: slideUp 0.2s ease-out;
}
</style>
