<template>
  <CollapsibleRoot v-if="collapsible" v-model:open="isOpen" :class="wrapperClasses">
    <!-- Header -->
    <div v-if="showHeader" class="flex items-center justify-between gap-4 mb-3">
      <CollapsibleTrigger
        class="flex items-center gap-2 text-sm font-medium text-olympus-text hover:text-olympus-primary transition-colors group outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50 rounded"
      >
        <Icon
          name="ph:caret-right"
          class="w-4 h-4 transition-transform duration-200"
          :class="{ 'rotate-90': isOpen }"
        />
        <span>{{ title }}</span>
        <SharedBadge
          v-if="showCount"
          :label="filteredSteps.length.toString()"
          size="xs"
          variant="default"
        />
      </CollapsibleTrigger>

      <!-- Actions -->
      <div class="flex items-center gap-2">
        <!-- Filter dropdown -->
        <DropdownMenuRoot v-if="filterable">
          <DropdownMenuTrigger
            class="p-1.5 rounded-md text-olympus-text-muted hover:text-olympus-text hover:bg-olympus-surface transition-colors outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50"
          >
            <Icon name="ph:funnel" class="w-4 h-4" />
          </DropdownMenuTrigger>
          <DropdownMenuPortal>
            <DropdownMenuContent
              class="min-w-40 bg-olympus-elevated border border-olympus-border rounded-lg p-1.5 shadow-xl z-50 animate-in fade-in-0 zoom-in-95 duration-150"
              :side-offset="4"
            >
              <DropdownMenuLabel class="px-2 py-1 text-xs text-olympus-text-muted">
                Filter by status
              </DropdownMenuLabel>
              <DropdownMenuSeparator class="h-px bg-olympus-border my-1" />
              <DropdownMenuCheckboxItem
                v-for="status in statusOptions"
                :key="status.value"
                :checked="activeFilters.includes(status.value)"
                class="flex items-center gap-2 px-2 py-1.5 rounded-md text-sm text-olympus-text hover:bg-olympus-surface cursor-pointer outline-none focus:bg-olympus-surface"
                @click="toggleFilter(status.value)"
              >
                <div
                  class="w-3 h-3 rounded border flex items-center justify-center"
                  :class="activeFilters.includes(status.value) ? 'bg-olympus-primary border-olympus-primary' : 'border-olympus-border'"
                >
                  <Icon v-if="activeFilters.includes(status.value)" name="ph:check" class="w-2 h-2 text-white" />
                </div>
                <span>{{ status.label }}</span>
              </DropdownMenuCheckboxItem>
            </DropdownMenuContent>
          </DropdownMenuPortal>
        </DropdownMenuRoot>

        <!-- View mode toggle -->
        <div v-if="showViewToggle" class="flex items-center bg-olympus-surface rounded-md p-0.5">
          <button
            v-for="mode in viewModes"
            :key="mode.value"
            type="button"
            class="p-1 rounded transition-colors"
            :class="viewMode === mode.value ? 'bg-olympus-elevated text-olympus-text' : 'text-olympus-text-muted hover:text-olympus-text'"
            @click="viewMode = mode.value"
          >
            <Icon :name="mode.icon" class="w-4 h-4" />
          </button>
        </div>

        <!-- Expand/Collapse all -->
        <button
          v-if="groupByDate"
          type="button"
          class="p-1.5 rounded-md text-olympus-text-muted hover:text-olympus-text hover:bg-olympus-surface transition-colors"
          @click="toggleAllGroups"
        >
          <Icon :name="allExpanded ? 'ph:arrows-in' : 'ph:arrows-out'" class="w-4 h-4" />
        </button>

        <!-- Refresh button -->
        <button
          v-if="refreshable"
          type="button"
          class="p-1.5 rounded-md text-olympus-text-muted hover:text-olympus-text hover:bg-olympus-surface transition-colors"
          :class="{ 'animate-spin': isRefreshing }"
          @click="handleRefresh"
        >
          <Icon name="ph:arrow-clockwise" class="w-4 h-4" />
        </button>
      </div>
    </div>

    <!-- Search -->
    <div v-if="searchable" class="mb-3">
      <SharedSearchInput
        v-model="searchQuery"
        placeholder="Search activity..."
        size="sm"
        clearable
      />
    </div>

    <!-- Content -->
    <CollapsibleContent :class="contentClasses">
      <!-- Loading state -->
      <div v-if="loading" class="space-y-3">
        <div v-for="i in 3" :key="i" class="flex items-start gap-3">
          <SharedSkeleton variant="circle" size="sm" />
          <div class="flex-1 space-y-2">
            <SharedSkeleton variant="text" width="80%" />
            <SharedSkeleton variant="text" width="40%" height="10px" />
          </div>
        </div>
      </div>

      <!-- Empty state -->
      <SharedEmptyState
        v-else-if="filteredSteps.length === 0"
        :icon="emptyIcon"
        :title="emptyTitle"
        :description="emptyDescription"
        size="sm"
      />

      <!-- Timeline View -->
      <template v-else-if="viewMode === 'timeline'">
        <div v-if="groupByDate" class="space-y-4">
          <div v-for="group in groupedSteps" :key="group.date" class="space-y-2">
            <button
              type="button"
              class="flex items-center gap-2 text-xs font-medium text-olympus-text-muted hover:text-olympus-text transition-colors"
              @click="toggleGroup(group.date)"
            >
              <Icon
                name="ph:caret-right"
                class="w-3 h-3 transition-transform duration-200"
                :class="{ 'rotate-90': expandedGroups.includes(group.date) }"
              />
              <span>{{ group.label }}</span>
              <span class="text-olympus-text-subtle">({{ group.steps.length }})</span>
            </button>

            <Transition
              enter-active-class="transition-all duration-200 ease-out"
              enter-from-class="opacity-0 max-h-0"
              enter-to-class="opacity-100 max-h-[2000px]"
              leave-active-class="transition-all duration-150 ease-in"
              leave-from-class="opacity-100 max-h-[2000px]"
              leave-to-class="opacity-0 max-h-0"
            >
              <div v-show="expandedGroups.includes(group.date)" class="pl-5 border-l-2 border-olympus-border space-y-2 overflow-hidden">
                <ActivityStep
                  v-for="(step, index) in group.steps"
                  :key="step.id"
                  :step="step"
                  :variant="variant"
                  :show-avatar="showAvatar"
                  :show-duration="showDuration"
                  :show-timestamp="showTimestamp"
                  :animate="animate"
                  :clickable="clickable"
                  :style="{ animationDelay: `${index * 50}ms` }"
                  @click="handleStepClick(step)"
                />
              </div>
            </Transition>
          </div>
        </div>

        <div v-else :class="timelineClasses">
          <ActivityStep
            v-for="(step, index) in filteredSteps"
            :key="step.id"
            :step="step"
            :variant="variant"
            :show-avatar="showAvatar"
            :show-duration="showDuration"
            :show-timestamp="showTimestamp"
            :animate="animate"
            :clickable="clickable"
            :style="animate ? { animationDelay: `${index * 50}ms` } : undefined"
            @click="handleStepClick(step)"
          />
        </div>
      </template>

      <!-- List View -->
      <template v-else-if="viewMode === 'list'">
        <div class="divide-y divide-olympus-border">
          <ActivityStep
            v-for="step in filteredSteps"
            :key="step.id"
            :step="step"
            variant="list"
            :show-avatar="showAvatar"
            :show-duration="showDuration"
            :show-timestamp="showTimestamp"
            :clickable="clickable"
            class="py-2 first:pt-0 last:pb-0"
            @click="handleStepClick(step)"
          />
        </div>
      </template>

      <!-- Compact View -->
      <template v-else-if="viewMode === 'compact'">
        <div class="flex flex-wrap gap-1.5">
          <TooltipProvider v-for="step in filteredSteps" :key="step.id" :delay-duration="200">
            <TooltipRoot>
              <TooltipTrigger as-child>
                <button
                  type="button"
                  class="p-1 rounded transition-colors"
                  :class="stepCompactClasses[step.status]"
                  @click="handleStepClick(step)"
                >
                  <Icon :name="stepIcons[step.status]" class="w-3 h-3" />
                </button>
              </TooltipTrigger>
              <TooltipPortal>
                <TooltipContent
                  side="top"
                  :side-offset="4"
                  class="z-50 px-2 py-1.5 text-xs bg-olympus-elevated border border-olympus-border rounded-md shadow-lg max-w-48"
                >
                  <p class="font-medium text-olympus-text">{{ step.description }}</p>
                  <p class="text-olympus-text-muted mt-0.5">{{ formatDuration(step) }}</p>
                  <TooltipArrow class="fill-olympus-elevated" />
                </TooltipContent>
              </TooltipPortal>
            </TooltipRoot>
          </TooltipProvider>
        </div>
      </template>

      <!-- Load more -->
      <button
        v-if="hasMore && !loading"
        type="button"
        class="mt-3 w-full py-2 text-xs text-olympus-text-muted hover:text-olympus-text hover:bg-olympus-surface rounded-md transition-colors"
        @click="$emit('loadMore')"
      >
        Load more activity
      </button>
    </CollapsibleContent>
  </CollapsibleRoot>

  <!-- Non-collapsible version -->
  <div v-else :class="wrapperClasses">
    <!-- Header -->
    <div v-if="showHeader" class="flex items-center justify-between gap-4 mb-3">
      <div class="flex items-center gap-2">
        <Icon v-if="icon" :name="icon" class="w-4 h-4 text-olympus-text-muted" />
        <span class="text-sm font-medium text-olympus-text">{{ title }}</span>
        <SharedBadge
          v-if="showCount"
          :label="filteredSteps.length.toString()"
          size="xs"
          variant="default"
        />
      </div>

      <!-- Actions -->
      <div class="flex items-center gap-2">
        <!-- Filter dropdown -->
        <DropdownMenuRoot v-if="filterable">
          <DropdownMenuTrigger
            class="p-1.5 rounded-md text-olympus-text-muted hover:text-olympus-text hover:bg-olympus-surface transition-colors outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50"
          >
            <Icon name="ph:funnel" class="w-4 h-4" />
          </DropdownMenuTrigger>
          <DropdownMenuPortal>
            <DropdownMenuContent
              class="min-w-40 bg-olympus-elevated border border-olympus-border rounded-lg p-1.5 shadow-xl z-50 animate-in fade-in-0 zoom-in-95 duration-150"
              :side-offset="4"
            >
              <DropdownMenuLabel class="px-2 py-1 text-xs text-olympus-text-muted">
                Filter by status
              </DropdownMenuLabel>
              <DropdownMenuSeparator class="h-px bg-olympus-border my-1" />
              <DropdownMenuCheckboxItem
                v-for="status in statusOptions"
                :key="status.value"
                :checked="activeFilters.includes(status.value)"
                class="flex items-center gap-2 px-2 py-1.5 rounded-md text-sm text-olympus-text hover:bg-olympus-surface cursor-pointer outline-none focus:bg-olympus-surface"
                @click="toggleFilter(status.value)"
              >
                <div
                  class="w-3 h-3 rounded border flex items-center justify-center"
                  :class="activeFilters.includes(status.value) ? 'bg-olympus-primary border-olympus-primary' : 'border-olympus-border'"
                >
                  <Icon v-if="activeFilters.includes(status.value)" name="ph:check" class="w-2 h-2 text-white" />
                </div>
                <span>{{ status.label }}</span>
              </DropdownMenuCheckboxItem>
            </DropdownMenuContent>
          </DropdownMenuPortal>
        </DropdownMenuRoot>

        <!-- View mode toggle -->
        <div v-if="showViewToggle" class="flex items-center bg-olympus-surface rounded-md p-0.5">
          <button
            v-for="mode in viewModes"
            :key="mode.value"
            type="button"
            class="p-1 rounded transition-colors"
            :class="viewMode === mode.value ? 'bg-olympus-elevated text-olympus-text' : 'text-olympus-text-muted hover:text-olympus-text'"
            @click="viewMode = mode.value"
          >
            <Icon :name="mode.icon" class="w-4 h-4" />
          </button>
        </div>

        <!-- Expand/Collapse all -->
        <button
          v-if="groupByDate"
          type="button"
          class="p-1.5 rounded-md text-olympus-text-muted hover:text-olympus-text hover:bg-olympus-surface transition-colors"
          @click="toggleAllGroups"
        >
          <Icon :name="allExpanded ? 'ph:arrows-in' : 'ph:arrows-out'" class="w-4 h-4" />
        </button>

        <!-- Refresh button -->
        <button
          v-if="refreshable"
          type="button"
          class="p-1.5 rounded-md text-olympus-text-muted hover:text-olympus-text hover:bg-olympus-surface transition-colors"
          :class="{ 'animate-spin': isRefreshing }"
          @click="handleRefresh"
        >
          <Icon name="ph:arrow-clockwise" class="w-4 h-4" />
        </button>
      </div>
    </div>

    <!-- Search -->
    <div v-if="searchable" class="mb-3">
      <SharedSearchInput
        v-model="searchQuery"
        placeholder="Search activity..."
        size="sm"
        clearable
      />
    </div>

    <!-- Content -->
    <div :class="contentClasses">
      <!-- Loading state -->
      <div v-if="loading" class="space-y-3">
        <div v-for="i in 3" :key="i" class="flex items-start gap-3">
          <SharedSkeleton variant="circle" size="sm" />
          <div class="flex-1 space-y-2">
            <SharedSkeleton variant="text" width="80%" />
            <SharedSkeleton variant="text" width="40%" height="10px" />
          </div>
        </div>
      </div>

      <!-- Empty state -->
      <SharedEmptyState
        v-else-if="filteredSteps.length === 0"
        :icon="emptyIcon"
        :title="emptyTitle"
        :description="emptyDescription"
        size="sm"
      />

      <!-- Timeline View -->
      <template v-else-if="viewMode === 'timeline'">
        <div v-if="groupByDate" class="space-y-4">
          <div v-for="group in groupedSteps" :key="group.date" class="space-y-2">
            <button
              type="button"
              class="flex items-center gap-2 text-xs font-medium text-olympus-text-muted hover:text-olympus-text transition-colors"
              @click="toggleGroup(group.date)"
            >
              <Icon
                name="ph:caret-right"
                class="w-3 h-3 transition-transform duration-200"
                :class="{ 'rotate-90': expandedGroups.includes(group.date) }"
              />
              <span>{{ group.label }}</span>
              <span class="text-olympus-text-subtle">({{ group.steps.length }})</span>
            </button>

            <Transition
              enter-active-class="transition-all duration-200 ease-out"
              enter-from-class="opacity-0 max-h-0"
              enter-to-class="opacity-100 max-h-[2000px]"
              leave-active-class="transition-all duration-150 ease-in"
              leave-from-class="opacity-100 max-h-[2000px]"
              leave-to-class="opacity-0 max-h-0"
            >
              <div v-show="expandedGroups.includes(group.date)" class="pl-5 border-l-2 border-olympus-border space-y-2 overflow-hidden">
                <ActivityStep
                  v-for="(step, index) in group.steps"
                  :key="step.id"
                  :step="step"
                  :variant="variant"
                  :show-avatar="showAvatar"
                  :show-duration="showDuration"
                  :show-timestamp="showTimestamp"
                  :animate="animate"
                  :clickable="clickable"
                  :style="{ animationDelay: `${index * 50}ms` }"
                  @click="handleStepClick(step)"
                />
              </div>
            </Transition>
          </div>
        </div>

        <div v-else :class="timelineClasses">
          <ActivityStep
            v-for="(step, index) in filteredSteps"
            :key="step.id"
            :step="step"
            :variant="variant"
            :show-avatar="showAvatar"
            :show-duration="showDuration"
            :show-timestamp="showTimestamp"
            :animate="animate"
            :clickable="clickable"
            :style="animate ? { animationDelay: `${index * 50}ms` } : undefined"
            @click="handleStepClick(step)"
          />
        </div>
      </template>

      <!-- List View -->
      <template v-else-if="viewMode === 'list'">
        <div class="divide-y divide-olympus-border">
          <ActivityStep
            v-for="step in filteredSteps"
            :key="step.id"
            :step="step"
            variant="list"
            :show-avatar="showAvatar"
            :show-duration="showDuration"
            :show-timestamp="showTimestamp"
            :clickable="clickable"
            class="py-2 first:pt-0 last:pb-0"
            @click="handleStepClick(step)"
          />
        </div>
      </template>

      <!-- Compact View -->
      <template v-else-if="viewMode === 'compact'">
        <div class="flex flex-wrap gap-1.5">
          <TooltipProvider v-for="step in filteredSteps" :key="step.id" :delay-duration="200">
            <TooltipRoot>
              <TooltipTrigger as-child>
                <button
                  type="button"
                  class="p-1 rounded transition-colors"
                  :class="stepCompactClasses[step.status]"
                  @click="handleStepClick(step)"
                >
                  <Icon :name="stepIcons[step.status]" class="w-3 h-3" />
                </button>
              </TooltipTrigger>
              <TooltipPortal>
                <TooltipContent
                  side="top"
                  :side-offset="4"
                  class="z-50 px-2 py-1.5 text-xs bg-olympus-elevated border border-olympus-border rounded-md shadow-lg max-w-48"
                >
                  <p class="font-medium text-olympus-text">{{ step.description }}</p>
                  <p class="text-olympus-text-muted mt-0.5">{{ formatDuration(step) }}</p>
                  <TooltipArrow class="fill-olympus-elevated" />
                </TooltipContent>
              </TooltipPortal>
            </TooltipRoot>
          </TooltipProvider>
        </div>
      </template>

      <!-- Load more -->
      <button
        v-if="hasMore && !loading"
        type="button"
        class="mt-3 w-full py-2 text-xs text-olympus-text-muted hover:text-olympus-text hover:bg-olympus-surface rounded-md transition-colors"
        @click="$emit('loadMore')"
      >
        Load more activity
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import {
  CollapsibleContent,
  CollapsibleRoot,
  CollapsibleTrigger,
  DropdownMenuCheckboxItem,
  DropdownMenuContent,
  DropdownMenuLabel,
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
import type { ActivityStep as ActivityStepType } from '~/types'

type ViewMode = 'timeline' | 'list' | 'compact'
type StepVariant = 'default' | 'minimal' | 'detailed' | 'list'
type StepStatus = 'completed' | 'in_progress' | 'pending' | 'failed' | 'skipped'

const props = withDefaults(defineProps<{
  // Data
  steps: ActivityStepType[]

  // Header
  title?: string
  icon?: string
  showHeader?: boolean
  showCount?: boolean

  // Features
  collapsible?: boolean
  defaultOpen?: boolean
  searchable?: boolean
  filterable?: boolean
  refreshable?: boolean
  groupByDate?: boolean
  showViewToggle?: boolean
  hasMore?: boolean

  // Display
  variant?: StepVariant
  showAvatar?: boolean
  showDuration?: boolean
  showTimestamp?: boolean
  animate?: boolean
  clickable?: boolean
  maxHeight?: string

  // Loading
  loading?: boolean

  // Empty state
  emptyIcon?: string
  emptyTitle?: string
  emptyDescription?: string
}>(), {
  title: 'Activity log',
  showHeader: true,
  showCount: true,
  collapsible: true,
  defaultOpen: false,
  searchable: false,
  filterable: false,
  refreshable: false,
  groupByDate: false,
  showViewToggle: false,
  hasMore: false,
  variant: 'default',
  showAvatar: false,
  showDuration: true,
  showTimestamp: false,
  animate: true,
  clickable: false,
  loading: false,
  emptyIcon: 'ph:clock',
  emptyTitle: 'No activity',
  emptyDescription: 'Activity will appear here as tasks progress.',
})

const emit = defineEmits<{
  refresh: []
  loadMore: []
  stepClick: [step: ActivityStepType]
}>()

// State
const isOpen = ref(props.defaultOpen)
const viewMode = ref<ViewMode>('timeline')
const searchQuery = ref('')
const activeFilters = ref<StepStatus[]>(['completed', 'in_progress', 'pending', 'failed', 'skipped'])
const expandedGroups = ref<string[]>([])
const isRefreshing = ref(false)

// View modes
const viewModes = [
  { value: 'timeline' as const, icon: 'ph:list-bullets' },
  { value: 'list' as const, icon: 'ph:rows' },
  { value: 'compact' as const, icon: 'ph:squares-four' },
]

// Status options for filter
const statusOptions = [
  { value: 'completed' as const, label: 'Completed' },
  { value: 'in_progress' as const, label: 'In Progress' },
  { value: 'pending' as const, label: 'Pending' },
  { value: 'failed' as const, label: 'Failed' },
  { value: 'skipped' as const, label: 'Skipped' },
]

// Status classes
const stepCompactClasses: Record<string, string> = {
  completed: 'bg-green-500/20 text-green-400 hover:bg-green-500/30',
  in_progress: 'bg-olympus-primary/20 text-olympus-primary hover:bg-olympus-primary/30',
  pending: 'bg-olympus-surface text-olympus-text-muted hover:bg-olympus-elevated',
  failed: 'bg-red-500/20 text-red-400 hover:bg-red-500/30',
  skipped: 'bg-gray-500/20 text-gray-400 hover:bg-gray-500/30',
}

// Status icons
const stepIcons: Record<string, string> = {
  completed: 'ph:check',
  in_progress: 'ph:spinner',
  pending: 'ph:circle',
  failed: 'ph:x',
  skipped: 'ph:minus',
}

// Computed
const wrapperClasses = computed(() => [
  'w-full',
])

const contentClasses = computed(() => [
  props.maxHeight && `max-h-[${props.maxHeight}] overflow-y-auto`,
])

const timelineClasses = computed(() => [
  'pl-5 border-l-2 border-olympus-border space-y-2',
])

// Filtered steps
const filteredSteps = computed(() => {
  let result = props.steps

  // Filter by status
  if (activeFilters.value.length < statusOptions.length) {
    result = result.filter(step => activeFilters.value.includes(step.status as StepStatus))
  }

  // Filter by search
  if (searchQuery.value.trim()) {
    const query = searchQuery.value.toLowerCase()
    result = result.filter(step =>
      step.description.toLowerCase().includes(query)
    )
  }

  return result
})

// Grouped steps by date
const groupedSteps = computed(() => {
  if (!props.groupByDate) return []

  const groups: Record<string, { date: string; label: string; steps: ActivityStepType[] }> = {}

  filteredSteps.value.forEach(step => {
    const date = new Date(step.startedAt)
    const dateKey = date.toISOString().split('T')[0]
    const label = formatDateLabel(date)

    if (!groups[dateKey]) {
      groups[dateKey] = { date: dateKey, label, steps: [] }
    }
    groups[dateKey].steps.push(step)
  })

  return Object.values(groups).sort((a, b) => b.date.localeCompare(a.date))
})

// All groups expanded
const allExpanded = computed(() => {
  return groupedSteps.value.every(g => expandedGroups.value.includes(g.date))
})

// Format date label
const formatDateLabel = (date: Date): string => {
  const today = new Date()
  const yesterday = new Date(today)
  yesterday.setDate(yesterday.getDate() - 1)

  if (date.toDateString() === today.toDateString()) {
    return 'Today'
  }
  if (date.toDateString() === yesterday.toDateString()) {
    return 'Yesterday'
  }
  return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

// Format duration
const formatDuration = (step: ActivityStepType): string => {
  const start = new Date(step.startedAt)
  const end = step.completedAt ? new Date(step.completedAt) : new Date()
  const diffMs = end.getTime() - start.getTime()
  const diffMins = Math.floor(diffMs / 60000)
  const diffSecs = Math.floor((diffMs % 60000) / 1000)

  if (step.status === 'in_progress') {
    return `${diffMins}m ${diffSecs}s elapsed`
  }
  if (diffMins === 0) {
    return `${diffSecs}s`
  }
  return `${diffMins}m ${diffSecs}s`
}

// Toggle filter
const toggleFilter = (status: StepStatus) => {
  const index = activeFilters.value.indexOf(status)
  if (index === -1) {
    activeFilters.value.push(status)
  } else {
    activeFilters.value.splice(index, 1)
  }
}

// Toggle group
const toggleGroup = (date: string) => {
  const index = expandedGroups.value.indexOf(date)
  if (index === -1) {
    expandedGroups.value.push(date)
  } else {
    expandedGroups.value.splice(index, 1)
  }
}

// Toggle all groups
const toggleAllGroups = () => {
  if (allExpanded.value) {
    expandedGroups.value = []
  } else {
    expandedGroups.value = groupedSteps.value.map(g => g.date)
  }
}

// Handle refresh
const handleRefresh = async () => {
  isRefreshing.value = true
  emit('refresh')
  await new Promise(resolve => setTimeout(resolve, 500))
  isRefreshing.value = false
}

// Handle step click
const handleStepClick = (step: ActivityStepType) => {
  if (props.clickable) {
    emit('stepClick', step)
  }
}

// Initialize expanded groups
onMounted(() => {
  if (props.groupByDate && groupedSteps.value.length > 0) {
    expandedGroups.value = [groupedSteps.value[0].date]
  }
})

// Activity Step Component
const ActivityStep = defineComponent({
  name: 'ActivityStep',
  props: {
    step: { type: Object as PropType<ActivityStepType>, required: true },
    variant: { type: String as PropType<StepVariant>, default: 'default' },
    showAvatar: { type: Boolean, default: false },
    showDuration: { type: Boolean, default: true },
    showTimestamp: { type: Boolean, default: false },
    animate: { type: Boolean, default: false },
    clickable: { type: Boolean, default: false },
  },
  emits: ['click'],
  setup(stepProps, { emit: stepEmit }) {
    const statusClasses: Record<string, string> = {
      completed: 'bg-green-500/20 text-green-400',
      in_progress: 'bg-olympus-primary/20 text-olympus-primary',
      pending: 'bg-olympus-surface text-olympus-text-muted',
      failed: 'bg-red-500/20 text-red-400',
      skipped: 'bg-gray-500/20 text-gray-400',
    }

    const formatTime = (date: Date) => {
      return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })
    }

    return () => h('div', {
      class: [
        'flex items-start gap-3 text-xs',
        stepProps.animate && 'animate-in fade-in-0 slide-in-from-left-2 duration-300',
        stepProps.clickable && 'cursor-pointer hover:bg-olympus-surface/50 rounded-md p-1 -m-1 transition-colors',
        stepProps.variant === 'list' && 'items-center',
      ],
      onClick: () => stepEmit('click'),
    }, [
      // Status indicator
      h('div', {
        class: [
          'w-5 h-5 rounded-full flex items-center justify-center shrink-0',
          stepProps.variant === 'list' ? 'mt-0' : 'mt-0.5',
          statusClasses[stepProps.step.status],
        ],
      }, [
        stepProps.step.status === 'completed' && h(resolveComponent('Icon'), {
          name: 'ph:check',
          class: 'w-3 h-3',
        }),
        stepProps.step.status === 'in_progress' && h(resolveComponent('Icon'), {
          name: 'ph:spinner',
          class: 'w-3 h-3 animate-spin',
        }),
        stepProps.step.status === 'failed' && h(resolveComponent('Icon'), {
          name: 'ph:x',
          class: 'w-3 h-3',
        }),
        stepProps.step.status === 'skipped' && h(resolveComponent('Icon'), {
          name: 'ph:minus',
          class: 'w-3 h-3',
        }),
        stepProps.step.status === 'pending' && h('span', {
          class: 'w-1.5 h-1.5 rounded-full bg-current',
        }),
      ]),

      // Content
      h('div', {
        class: 'flex-1 min-w-0',
      }, [
        h('p', {
          class: [
            stepProps.step.status === 'completed' || stepProps.step.status === 'skipped'
              ? 'text-olympus-text-muted'
              : 'text-olympus-text',
            stepProps.step.status === 'failed' && 'text-red-400',
          ],
        }, stepProps.step.description),
        (stepProps.showDuration || stepProps.showTimestamp) && h('p', {
          class: 'text-olympus-text-subtle mt-0.5 flex items-center gap-2',
        }, [
          stepProps.showDuration && formatDuration(stepProps.step),
          stepProps.showTimestamp && stepProps.showDuration && '·',
          stepProps.showTimestamp && formatTime(new Date(stepProps.step.startedAt)),
        ]),
      ]),
    ])
  },
})
</script>
