<template>
  <div :class="containerClasses">
    <!-- Header -->
    <div :class="headerClasses">
      <div class="flex items-center gap-2">
        <div :class="headerIconClasses">
          <Icon name="ph:pulse-fill" class="w-4 h-4 text-gray-600" />
        </div>
        <div>
          <h2 :class="titleClasses">{{ title }}</h2>
          <p v-if="showCount && activities.length > 0" :class="subtitleClasses">
            {{ activities.length }} recent {{ activities.length === 1 ? 'event' : 'events' }}
          </p>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <!-- Filter Dropdown -->
        <DropdownMenuRoot v-if="showFilter">
          <DropdownMenuTrigger as-child>
            <button type="button" :class="filterButtonClasses">
              <Icon name="ph:funnel" class="w-3.5 h-3.5" />
              <span v-if="size !== 'sm'">{{ activeFilter === 'all' ? 'All' : filterLabels[activeFilter] }}</span>
              <Icon name="ph:caret-down" class="w-3 h-3" />
            </button>
          </DropdownMenuTrigger>
          <DropdownMenuPortal>
            <DropdownMenuContent :class="dropdownClasses" align="end" :side-offset="4">
              <DropdownMenuItem
                v-for="filter in filterOptions"
                :key="filter.value"
                :class="[dropdownItemClasses, activeFilter === filter.value && 'bg-gray-100']"
                @click="activeFilter = filter.value"
              >
                <div :class="['w-2 h-2 rounded-full', filter.color]" />
                <span>{{ filter.label }}</span>
                <span v-if="getFilterCount(filter.value) > 0" class="ml-auto text-xs text-gray-400">
                  {{ getFilterCount(filter.value) }}
                </span>
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenuPortal>
        </DropdownMenuRoot>

        <!-- View All Button -->
        <button
          v-if="showViewAll"
          type="button"
          :class="viewAllButtonClasses"
          @click="emit('viewAll')"
        >
          View all
        </button>

        <!-- Refresh Button -->
        <TooltipProvider v-if="showRefresh" :delay-duration="300">
          <TooltipRoot>
            <TooltipTrigger as-child>
              <button
                type="button"
                :class="refreshButtonClasses"
                :disabled="refreshing"
                @click="emit('refresh')"
              >
                <Icon
                  name="ph:arrows-clockwise"
                  :class="['w-3.5 h-3.5', refreshing && 'animate-spin']"
                />
              </button>
            </TooltipTrigger>
            <TooltipPortal>
              <TooltipContent :class="tooltipClasses" side="bottom">
                Refresh activity
                <TooltipArrow class="fill-white" />
              </TooltipContent>
            </TooltipPortal>
          </TooltipRoot>
        </TooltipProvider>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" :class="contentClasses">
      <ActivityItemSkeleton v-for="i in 5" :key="i" />
    </div>

    <!-- Activity List -->
    <div v-else-if="filteredActivities.length > 0" :class="contentClasses">
      <TransitionGroup :name="animated ? 'activity-list' : ''" tag="div">
        <div
          v-for="(activity, index) in displayedActivities"
          :key="activity.id"
          :class="activityItemClasses"
          @click="handleActivityClick(activity)"
        >
          <!-- Timeline Connector -->
          <div v-if="showTimeline && index < displayedActivities.length - 1" :class="timelineConnectorClasses" />

          <!-- Avatar -->
          <div class="relative shrink-0">
            <AgentAvatar :user="activity.actor" :size="avatarSize" />

            <!-- Activity Type Indicator -->
            <div :class="activityIndicatorClasses(activity.type)">
              <Icon :name="activityIcons[activity.type]" class="w-2.5 h-2.5" />
            </div>
          </div>

          <!-- Content -->
          <div class="flex-1 min-w-0">
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <!-- Actor Name -->
                <span :class="actorNameClasses">{{ activity.actor.name }}</span>

                <!-- Action Description -->
                <span :class="actionTextClasses"> {{ getActionText(activity) }}</span>

                <!-- Target (if any) -->
                <span v-if="activity.target" :class="targetClasses">
                  {{ activity.target.name }}
                </span>
              </div>

              <!-- Timestamp -->
              <TooltipProvider :delay-duration="200">
                <TooltipRoot>
                  <TooltipTrigger as-child>
                    <span :class="timestampClasses">
                      {{ formatRelativeTime(activity.timestamp) }}
                    </span>
                  </TooltipTrigger>
                  <TooltipPortal>
                    <TooltipContent :class="tooltipClasses" side="left">
                      {{ formatFullDate(activity.timestamp) }}
                      <TooltipArrow class="fill-white" />
                    </TooltipContent>
                  </TooltipPortal>
                </TooltipRoot>
              </TooltipProvider>
            </div>

            <!-- Description (if detailed) -->
            <p v-if="activity.description && variant === 'detailed'" :class="descriptionClasses">
              {{ activity.description }}
            </p>

            <!-- Metadata Row -->
            <div v-if="showMetadata && (activity.channel || activity.cost)" :class="metadataClasses">
              <!-- Channel -->
              <span v-if="activity.channel" class="flex items-center gap-1">
                <Icon name="ph:hash" class="w-3 h-3" />
                {{ activity.channel }}
              </span>

              <!-- Cost -->
              <span v-if="activity.cost" class="flex items-center gap-1">
                <Icon name="ph:coins" class="w-3 h-3 text-gray-400" />
                ${{ activity.cost.toFixed(2) }}
              </span>

              <!-- Duration -->
              <span v-if="activity.duration" class="flex items-center gap-1">
                <Icon name="ph:clock" class="w-3 h-3" />
                {{ formatDuration(activity.duration) }}
              </span>
            </div>

            <!-- Expandable Details -->
            <Transition name="expand">
              <div v-if="expandedId === activity.id && activity.details" :class="detailsClasses">
                <div class="flex items-center justify-between text-xs text-gray-500 mb-2">
                  <span>Details</span>
                  <button
                    type="button"
                    class="p-0.5 rounded hover:bg-gray-100"
                    @click.stop="expandedId = null"
                  >
                    <Icon name="ph:x" class="w-3 h-3" />
                  </button>
                </div>
                <pre class="text-xs text-gray-400 whitespace-pre-wrap font-mono">{{ JSON.stringify(activity.details, null, 2) }}</pre>
              </div>
            </Transition>
          </div>

          <!-- Right Side Actions/Indicator -->
          <div class="flex items-center gap-2 shrink-0">
            <!-- Activity Type Badge -->
            <div
              :class="[
                'rounded-lg flex items-center justify-center',
                'transition-colors duration-150',
                activityBgClasses[activity.type],
                size === 'sm' ? 'w-7 h-7' : 'w-8 h-8',
              ]"
            >
              <Icon
                :name="activityIcons[activity.type]"
                :class="[
                  activityIconClasses[activity.type],
                  size === 'sm' ? 'w-3.5 h-3.5' : 'w-4 h-4',
                ]"
              />
            </div>

            <!-- Expand Button (for detailed variant) -->
            <button
              v-if="variant === 'detailed' && activity.details"
              type="button"
              :class="expandButtonClasses"
              @click.stop="toggleExpand(activity.id)"
            >
              <Icon
                :name="expandedId === activity.id ? 'ph:caret-up' : 'ph:caret-down'"
                class="w-3.5 h-3.5"
              />
            </button>
          </div>
        </div>
      </TransitionGroup>

      <!-- Load More Button -->
      <button
        v-if="showLoadMore && hasMore"
        type="button"
        :class="loadMoreButtonClasses"
        :disabled="loadingMore"
        @click="emit('loadMore')"
      >
        <Icon v-if="loadingMore" name="ph:spinner" class="w-4 h-4 animate-spin" />
        <span>{{ loadingMore ? 'Loading...' : `Load ${remainingCount} more` }}</span>
      </button>
    </div>

    <!-- Empty State -->
    <div v-else :class="emptyStateClasses">
      <div :class="emptyIconContainerClasses">
        <Icon name="ph:check-circle" :class="emptyIconClasses" />
      </div>
      <p class="font-medium text-sm text-gray-900">{{ emptyTitle }}</p>
      <p class="text-xs text-gray-500 mt-1">{{ emptyDescription }}</p>

      <!-- Empty State Action -->
      <Button
        v-if="showEmptyAction"
        size="sm"
        variant="secondary"
        class="mt-3"
        @click="emit('emptyAction')"
      >
        <Icon name="ph:arrow-clockwise" class="w-4 h-4" />
        Refresh
      </Button>
    </div>

    <!-- Live Indicator -->
    <div v-if="isLive" :class="liveIndicatorClasses">
      <span class="relative flex h-2 w-2">
        <span class="relative inline-flex rounded-full h-2 w-2 bg-gray-500" />
      </span>
      <span class="text-[10px] text-gray-500 font-medium">Live</span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, h, defineComponent, resolveComponent, watch } from 'vue'
import {
  TooltipArrow,
  TooltipContent,
  TooltipPortal,
  TooltipProvider,
  TooltipRoot,
  TooltipTrigger,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuPortal,
  DropdownMenuRoot,
  DropdownMenuTrigger,
} from 'reka-ui'
import type { Activity, ActivityType } from '@/types'
import AgentAvatar from '@/Components/shared/AgentAvatar.vue'
import Button from '@/Components/shared/Button.vue'
import Skeleton from '@/Components/shared/Skeleton.vue'
import Icon from '@/Components/shared/Icon.vue'

type ActivityFeedSize = 'sm' | 'md' | 'lg'
type ActivityFeedVariant = 'default' | 'compact' | 'detailed'
type FilterType = 'all' | ActivityType

const props = withDefaults(defineProps<{
  // Core
  activities: Activity[]

  // Appearance
  size?: ActivityFeedSize
  variant?: ActivityFeedVariant

  // Display options
  showFilter?: boolean
  showViewAll?: boolean
  showRefresh?: boolean
  showTimeline?: boolean
  showMetadata?: boolean
  showLoadMore?: boolean
  showCount?: boolean
  showEmptyAction?: boolean

  // Content
  title?: string
  emptyTitle?: string
  emptyDescription?: string

  // State
  loading?: boolean
  loadingMore?: boolean
  refreshing?: boolean
  isLive?: boolean

  // Pagination
  maxItems?: number
  hasMore?: boolean
  remainingCount?: number

  // Behavior
  interactive?: boolean
  animated?: boolean
}>(), {
  size: 'md',
  variant: 'default',
  showFilter: true,
  showViewAll: true,
  showRefresh: false,
  showTimeline: false,
  showMetadata: true,
  showLoadMore: false,
  showCount: false,
  showEmptyAction: true,
  title: 'Recent Activity',
  emptyTitle: 'All caught up!',
  emptyDescription: 'Your recent activity will appear here',
  loading: false,
  loadingMore: false,
  refreshing: false,
  isLive: false,
  maxItems: 10,
  hasMore: false,
  remainingCount: 0,
  interactive: true,
  animated: true,
})

const emit = defineEmits<{
  viewAll: []
  refresh: []
  loadMore: []
  activityClick: [activity: Activity]
  emptyAction: []
}>()

// State
const activeFilter = ref<FilterType>('all')
const expandedId = ref<string | null>(null)

// Activity icons
const activityIcons: Record<ActivityType, string> = {
  message: 'ph:chat-circle-fill',
  task_completed: 'ph:check-circle-fill',
  task_started: 'ph:play-circle-fill',
  agent_spawned: 'ph:robot-fill',
  approval_needed: 'ph:warning-circle-fill',
  approval_granted: 'ph:check-circle-fill',
  error: 'ph:x-circle-fill',
}

// Activity background classes - neutral gray for all types
const activityBgClasses: Record<ActivityType, string> = {
  message: 'bg-gray-100',
  task_completed: 'bg-gray-100',
  task_started: 'bg-gray-100',
  agent_spawned: 'bg-gray-100',
  approval_needed: 'bg-gray-100',
  approval_granted: 'bg-gray-100',
  error: 'bg-gray-100',
}

// Activity icon classes - neutral gray for all types
const activityIconClasses: Record<ActivityType, string> = {
  message: 'text-gray-600',
  task_completed: 'text-gray-600',
  task_started: 'text-gray-600',
  agent_spawned: 'text-gray-600',
  approval_needed: 'text-gray-600',
  approval_granted: 'text-gray-600',
  error: 'text-gray-600',
}

// Filter options - neutral gray dots
const filterOptions: { value: FilterType; label: string; color: string }[] = [
  { value: 'all', label: 'All activity', color: 'bg-gray-400' },
  { value: 'message', label: 'Messages', color: 'bg-gray-400' },
  { value: 'task_completed', label: 'Completed', color: 'bg-gray-400' },
  { value: 'task_started', label: 'Started', color: 'bg-gray-400' },
  { value: 'agent_spawned', label: 'Agents', color: 'bg-gray-400' },
  { value: 'approval_needed', label: 'Approvals', color: 'bg-gray-400' },
  { value: 'error', label: 'Errors', color: 'bg-gray-400' },
]

const filterLabels: Record<FilterType, string> = {
  all: 'All',
  message: 'Messages',
  task_completed: 'Completed',
  task_started: 'Started',
  agent_spawned: 'Agents',
  approval_needed: 'Approvals',
  approval_granted: 'Approved',
  error: 'Errors',
}

// Size configuration
const sizeConfig: Record<ActivityFeedSize, {
  padding: string
  avatar: 'xs' | 'sm'
  text: string
  gap: string
}> = {
  sm: {
    padding: 'px-3 py-2',
    avatar: 'xs',
    text: 'text-xs',
    gap: 'gap-2',
  },
  md: {
    padding: 'px-4 py-3',
    avatar: 'sm',
    text: 'text-sm',
    gap: 'gap-3',
  },
  lg: {
    padding: 'px-5 py-4',
    avatar: 'sm',
    text: 'text-sm',
    gap: 'gap-4',
  },
}

// Computed values
const avatarSize = computed(() => sizeConfig[props.size].avatar)

const filteredActivities = computed(() => {
  if (activeFilter.value === 'all') return props.activities
  return props.activities.filter(a => a.type === activeFilter.value)
})

const displayedActivities = computed(() => {
  return filteredActivities.value.slice(0, props.maxItems)
})

const getFilterCount = (filter: FilterType): number => {
  if (filter === 'all') return props.activities.length
  return props.activities.filter(a => a.type === filter).length
}

// Container classes
const containerClasses = computed(() => [
  'bg-white border border-gray-200 rounded-lg overflow-hidden relative',
])

// Header classes
const headerClasses = computed(() => [
  'p-4 border-b border-gray-200 flex items-center justify-between',
])

const headerIconClasses = computed(() => [
  'w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center',
  'transition-colors duration-150',
])

const titleClasses = computed(() => [
  'font-semibold',
  props.size === 'sm' ? 'text-xs' : 'text-sm',
])

const subtitleClasses = computed(() => [
  'text-gray-500',
  props.size === 'sm' ? 'text-[10px]' : 'text-xs',
])

// Filter button classes
const filterButtonClasses = computed(() => [
  'flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs',
  'bg-white border border-gray-200 text-gray-500',
  'hover:text-gray-900 hover:border-gray-300 hover:bg-gray-50',
  'transition-colors duration-150 outline-none',
  'focus-visible:ring-1 focus-visible:ring-gray-400',
])

// View all button classes
const viewAllButtonClasses = computed(() => [
  'text-sm text-gray-600 font-medium hover:text-gray-900',
  'transition-colors duration-150 outline-none',
  'focus-visible:ring-1 focus-visible:ring-gray-400',
  'rounded-lg px-2 py-1 -mr-2 hover:bg-gray-100',
])

// Refresh button classes
const refreshButtonClasses = computed(() => [
  'p-1.5 rounded-lg outline-none',
  'transition-colors duration-150',
  'text-gray-500 hover:text-gray-900 hover:bg-gray-100',
  'focus-visible:ring-1 focus-visible:ring-gray-400',
  'disabled:opacity-50 disabled:cursor-not-allowed',
])

// Dropdown classes
const dropdownClasses = computed(() => [
  'min-w-40 bg-white border border-gray-200 rounded-lg',
  'shadow-md p-1.5 z-50',
  'animate-in fade-in-0 duration-150',
])

const dropdownItemClasses = computed(() => [
  'flex items-center gap-2 px-3 py-2 text-sm rounded-md cursor-pointer outline-none',
  'text-gray-500 hover:bg-gray-50 hover:text-gray-900',
  'focus:bg-gray-50 focus:text-gray-900 transition-colors duration-150',
])

// Tooltip classes
const tooltipClasses = computed(() => [
  'z-50 bg-white border border-gray-200 rounded-lg',
  'px-2.5 py-1.5 text-xs shadow-md',
  'animate-in fade-in-0 duration-150',
])

// Content classes
const contentClasses = computed(() => [
  'divide-y divide-gray-200',
])

// Activity item classes
const activityItemClasses = computed(() => [
  'relative flex items-start group/activity',
  sizeConfig[props.size].padding,
  sizeConfig[props.size].gap,
  'hover:bg-gray-50 transition-colors duration-150',
  props.interactive && 'cursor-pointer',
])

// Timeline connector classes
const timelineConnectorClasses = computed(() => [
  'absolute left-6 top-10 w-0.5 h-full -bottom-3 bg-gray-200',
  props.size === 'sm' && 'left-5',
])

// Activity indicator classes
const activityIndicatorClasses = (type: ActivityType) => [
  'absolute -bottom-0.5 -right-0.5 w-4 h-4 rounded-full border-2 border-white',
  'flex items-center justify-center',
  activityBgClasses[type],
]

// Text classes
const actorNameClasses = computed(() => [
  'font-medium text-gray-900',
  sizeConfig[props.size].text,
])

const actionTextClasses = computed(() => [
  'text-gray-500',
  sizeConfig[props.size].text,
])

const targetClasses = computed(() => [
  'font-medium text-gray-900',
  sizeConfig[props.size].text,
])

const timestampClasses = computed(() => [
  'text-gray-400 whitespace-nowrap',
  props.size === 'sm' ? 'text-[10px]' : 'text-xs',
])

const descriptionClasses = computed(() => [
  'mt-1 text-gray-500 line-clamp-2',
  props.size === 'sm' ? 'text-[10px]' : 'text-xs',
])

const metadataClasses = computed(() => [
  'mt-1.5 flex items-center gap-3 text-gray-400',
  props.size === 'sm' ? 'text-[10px]' : 'text-xs',
])

const detailsClasses = computed(() => [
  'mt-3 p-3 rounded-lg bg-gray-50 border border-gray-200',
])

const expandButtonClasses = computed(() => [
  'p-1.5 rounded-lg outline-none',
  'transition-colors duration-150',
  'text-gray-400 hover:text-gray-900 hover:bg-gray-100',
  'focus-visible:ring-1 focus-visible:ring-gray-400',
])

// Load more button classes
const loadMoreButtonClasses = computed(() => [
  'w-full py-3 text-sm text-gray-500 font-medium',
  'hover:text-gray-900 hover:bg-gray-50',
  'transition-colors duration-150 flex items-center justify-center gap-2',
  'disabled:opacity-50 disabled:cursor-not-allowed',
  'focus-visible:ring-1 focus-visible:ring-inset focus-visible:ring-gray-400',
])

// Empty state classes
const emptyStateClasses = computed(() => [
  'p-8 text-center',
])

const emptyIconContainerClasses = computed(() => [
  'w-12 h-12 rounded-lg bg-gray-100 flex items-center justify-center mx-auto mb-3',
])

const emptyIconClasses = computed(() => [
  'w-6 h-6 text-gray-400',
])

// Live indicator classes
const liveIndicatorClasses = computed(() => [
  'absolute top-4 right-4 flex items-center gap-1.5 px-2 py-1 rounded-full',
  'bg-gray-100 border border-gray-200',
  'transition-colors duration-150',
])

// Helper functions
const getActionText = (activity: Activity): string => {
  const actionMap: Record<ActivityType, string> = {
    message: 'sent a message in',
    task_completed: 'completed task',
    task_started: 'started working on',
    agent_spawned: 'spawned agent',
    approval_needed: 'requested approval for',
    approval_granted: 'approved',
    error: 'encountered an error in',
  }
  return actionMap[activity.type] || 'performed action'
}

const formatRelativeTime = (date: Date): string => {
  const now = new Date()
  const diff = now.getTime() - new Date(date).getTime()
  const seconds = Math.floor(diff / 1000)
  const minutes = Math.floor(seconds / 60)
  const hours = Math.floor(minutes / 60)
  const days = Math.floor(hours / 24)

  if (seconds < 60) return 'Just now'
  if (minutes < 60) return `${minutes}m ago`
  if (hours < 24) return `${hours}h ago`
  if (days < 7) return `${days}d ago`
  return new Date(date).toLocaleDateString()
}

const formatFullDate = (date: Date): string => {
  return new Date(date).toLocaleString('en-US', {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  })
}

const formatDuration = (seconds: number): string => {
  if (seconds < 60) return `${seconds}s`
  const minutes = Math.floor(seconds / 60)
  if (minutes < 60) return `${minutes}m`
  const hours = Math.floor(minutes / 60)
  return `${hours}h ${minutes % 60}m`
}

// Actions
const toggleExpand = (id: string) => {
  expandedId.value = expandedId.value === id ? null : id
}

const handleActivityClick = (activity: Activity) => {
  if (props.interactive) {
    emit('activityClick', activity)
  }
}

// Activity Item Skeleton component
const ActivityItemSkeleton = defineComponent({
  name: 'ActivityItemSkeleton',
  setup() {
    return () => h('div', {
      class: 'px-4 py-3 flex items-start gap-3 animate-pulse',
    }, [
      h(Skeleton, { variant: 'avatar' }),
      h('div', { class: 'flex-1 space-y-2' }, [
        h('div', { class: 'flex items-center gap-2' }, [
          h(Skeleton, { customClass: 'h-3 w-20' }),
          h(Skeleton, { customClass: 'h-3 w-32' }),
        ]),
        h(Skeleton, { customClass: 'h-2 w-16' }),
      ]),
      h(Skeleton, { customClass: 'w-8 h-8 rounded-lg' }),
    ])
  },
})
</script>

<style scoped>
/* Activity list transitions - simple fade */
.activity-list-enter-active {
  transition: opacity 0.15s ease-out;
}

.activity-list-leave-active {
  transition: opacity 0.1s ease-out;
}

.activity-list-enter-from,
.activity-list-leave-to {
  opacity: 0;
}

.activity-list-move {
  transition: transform 0.15s ease-out;
}

/* Expand transition */
.expand-enter-active {
  transition: all 0.15s ease-out;
  overflow: hidden;
}

.expand-leave-active {
  transition: all 0.1s ease-out;
  overflow: hidden;
}

.expand-enter-from,
.expand-leave-to {
  opacity: 0;
  max-height: 0;
}

.expand-enter-to,
.expand-leave-from {
  max-height: 200px;
}
</style>
