<template>
  <CollapsibleRoot v-if="collapsible" v-model:open="isOpen" :class="wrapperClasses">
    <!-- Header -->
    <div v-if="showHeader" class="flex items-center justify-between gap-4 mb-3">
      <CollapsibleTrigger
        class="flex items-center gap-2 text-sm font-medium text-gray-900 hover:text-gray-700 transition-colors duration-150 group outline-none focus-visible:ring-1 focus-visible:ring-gray-400 rounded"
      >
        <Icon
          name="ph:caret-right"
          class="w-4 h-4 transition-transform duration-150 ease-out"
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
            class="p-1.5 rounded-md text-gray-500 hover:text-gray-900 hover:bg-gray-50 transition-colors duration-150 outline-none focus-visible:ring-1 focus-visible:ring-gray-400"
          >
            <Icon name="ph:funnel" class="w-4 h-4" />
          </DropdownMenuTrigger>
          <DropdownMenuPortal>
            <DropdownMenuContent
              class="min-w-40 bg-white border border-gray-200 rounded-lg p-1.5 shadow-md z-50 animate-in fade-in-0 duration-150"
              :side-offset="4"
            >
              <DropdownMenuLabel class="px-2 py-1 text-xs text-gray-500">
                Filter by status
              </DropdownMenuLabel>
              <DropdownMenuSeparator class="h-px bg-gray-200 my-1" />
              <DropdownMenuCheckboxItem
                v-for="status in statusOptions"
                :key="status.value"
                :checked="activeFilters.includes(status.value)"
                class="flex items-center gap-2 px-2 py-1.5 rounded-md text-sm text-gray-900 hover:bg-gray-50 cursor-pointer outline-none focus:bg-gray-50"
                @click="toggleFilter(status.value)"
              >
                <div
                  class="w-3 h-3 rounded border flex items-center justify-center"
                  :class="activeFilters.includes(status.value) ? 'bg-gray-900 border-gray-900' : 'border-gray-300'"
                >
                  <Icon v-if="activeFilters.includes(status.value)" name="ph:check" class="w-2 h-2 text-white" />
                </div>
                <span>{{ status.label }}</span>
              </DropdownMenuCheckboxItem>
            </DropdownMenuContent>
          </DropdownMenuPortal>
        </DropdownMenuRoot>

        <!-- View mode toggle -->
        <div v-if="showViewToggle" class="flex items-center bg-gray-100 rounded-md p-0.5">
          <button
            v-for="mode in viewModes"
            :key="mode.value"
            type="button"
            class="p-1 rounded transition-colors duration-150"
            :class="viewMode === mode.value ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-900'"
            @click="viewMode = mode.value"
          >
            <Icon :name="mode.icon" class="w-4 h-4" />
          </button>
        </div>

        <!-- Expand/Collapse all -->
        <button
          v-if="groupByDate"
          type="button"
          class="p-1.5 rounded-md text-gray-500 hover:text-gray-900 hover:bg-gray-50 transition-colors duration-150"
          @click="toggleAllGroups"
        >
          <Icon :name="allExpanded ? 'ph:arrows-in' : 'ph:arrows-out'" class="w-4 h-4" />
        </button>

        <!-- Refresh button -->
        <button
          v-if="refreshable"
          type="button"
          class="p-1.5 rounded-md text-gray-500 hover:text-gray-900 hover:bg-gray-50 transition-colors duration-150"
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
              class="flex items-center gap-2 text-xs font-medium text-gray-500 hover:text-gray-900 transition-colors duration-150"
              @click="toggleGroup(group.date)"
            >
              <Icon
                name="ph:caret-right"
                class="w-3 h-3 transition-transform duration-150 ease-out"
                :class="{ 'rotate-90': expandedGroups.includes(group.date) }"
              />
              <span>{{ group.label }}</span>
              <span class="text-gray-400">({{ group.steps.length }})</span>
            </button>

            <Transition
              enter-active-class="transition-all duration-150 ease-out"
              enter-from-class="opacity-0 max-h-0"
              enter-to-class="opacity-100 max-h-[2000px]"
              leave-active-class="transition-all duration-150 ease-out"
              leave-from-class="opacity-100 max-h-[2000px]"
              leave-to-class="opacity-0 max-h-0"
            >
              <div v-show="expandedGroups.includes(group.date)" class="pl-5 border-l-2 border-gray-200 space-y-2 overflow-hidden">
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
            :style="animate ? { animationDelay: `${index * 30}ms` } : undefined"
            @click="handleStepClick(step)"
          />
        </div>
      </template>

      <!-- List View -->
      <template v-else-if="viewMode === 'list'">
        <div class="divide-y divide-gray-200">
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
                  class="p-1 rounded transition-colors duration-150"
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
                  class="z-50 px-2 py-1.5 text-xs bg-white border border-gray-200 rounded-md shadow-md max-w-48 animate-in fade-in-0 duration-150"
                >
                  <p class="font-medium text-gray-900">{{ step.description }}</p>
                  <p class="text-gray-500 mt-0.5">{{ formatDuration(step) }}</p>
                  <TooltipArrow class="fill-white" />
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
        class="mt-3 w-full py-2 text-xs text-gray-500 hover:text-gray-900 hover:bg-gray-50 rounded-md transition-colors duration-150"
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
        <Icon v-if="icon" :name="icon" class="w-4 h-4 text-gray-500" />
        <span class="text-sm font-medium text-gray-900">{{ title }}</span>
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
            class="p-1.5 rounded-md text-gray-500 hover:text-gray-900 hover:bg-gray-50 transition-colors duration-150 outline-none focus-visible:ring-1 focus-visible:ring-gray-400"
          >
            <Icon name="ph:funnel" class="w-4 h-4" />
          </DropdownMenuTrigger>
          <DropdownMenuPortal>
            <DropdownMenuContent
              class="min-w-40 bg-white border border-gray-200 rounded-lg p-1.5 shadow-md z-50 animate-in fade-in-0 duration-150"
              :side-offset="4"
            >
              <DropdownMenuLabel class="px-2 py-1 text-xs text-gray-500">
                Filter by status
              </DropdownMenuLabel>
              <DropdownMenuSeparator class="h-px bg-gray-200 my-1" />
              <DropdownMenuCheckboxItem
                v-for="status in statusOptions"
                :key="status.value"
                :checked="activeFilters.includes(status.value)"
                class="flex items-center gap-2 px-2 py-1.5 rounded-md text-sm text-gray-900 hover:bg-gray-50 cursor-pointer outline-none focus:bg-gray-50"
                @click="toggleFilter(status.value)"
              >
                <div
                  class="w-3 h-3 rounded border flex items-center justify-center"
                  :class="activeFilters.includes(status.value) ? 'bg-gray-900 border-gray-900' : 'border-gray-300'"
                >
                  <Icon v-if="activeFilters.includes(status.value)" name="ph:check" class="w-2 h-2 text-white" />
                </div>
                <span>{{ status.label }}</span>
              </DropdownMenuCheckboxItem>
            </DropdownMenuContent>
          </DropdownMenuPortal>
        </DropdownMenuRoot>

        <!-- View mode toggle -->
        <div v-if="showViewToggle" class="flex items-center bg-gray-100 rounded-md p-0.5">
          <button
            v-for="mode in viewModes"
            :key="mode.value"
            type="button"
            class="p-1 rounded transition-colors duration-150"
            :class="viewMode === mode.value ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-900'"
            @click="viewMode = mode.value"
          >
            <Icon :name="mode.icon" class="w-4 h-4" />
          </button>
        </div>

        <!-- Expand/Collapse all -->
        <button
          v-if="groupByDate"
          type="button"
          class="p-1.5 rounded-md text-gray-500 hover:text-gray-900 hover:bg-gray-50 transition-colors duration-150"
          @click="toggleAllGroups"
        >
          <Icon :name="allExpanded ? 'ph:arrows-in' : 'ph:arrows-out'" class="w-4 h-4" />
        </button>

        <!-- Refresh button -->
        <button
          v-if="refreshable"
          type="button"
          class="p-1.5 rounded-md text-gray-500 hover:text-gray-900 hover:bg-gray-50 transition-colors duration-150"
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
              class="flex items-center gap-2 text-xs font-medium text-gray-500 hover:text-gray-900 transition-colors duration-150"
              @click="toggleGroup(group.date)"
            >
              <Icon
                name="ph:caret-right"
                class="w-3 h-3 transition-transform duration-150 ease-out"
                :class="{ 'rotate-90': expandedGroups.includes(group.date) }"
              />
              <span>{{ group.label }}</span>
              <span class="text-gray-400">({{ group.steps.length }})</span>
            </button>

            <Transition
              enter-active-class="transition-all duration-150 ease-out"
              enter-from-class="opacity-0 max-h-0"
              enter-to-class="opacity-100 max-h-[2000px]"
              leave-active-class="transition-all duration-150 ease-out"
              leave-from-class="opacity-100 max-h-[2000px]"
              leave-to-class="opacity-0 max-h-0"
            >
              <div v-show="expandedGroups.includes(group.date)" class="pl-5 border-l-2 border-gray-200 space-y-2 overflow-hidden">
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
            :style="animate ? { animationDelay: `${index * 30}ms` } : undefined"
            @click="handleStepClick(step)"
          />
        </div>
      </template>

      <!-- List View -->
      <template v-else-if="viewMode === 'list'">
        <div class="divide-y divide-gray-200">
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
                  class="p-1 rounded transition-colors duration-150"
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
                  class="z-50 px-2 py-1.5 text-xs bg-white border border-gray-200 rounded-md shadow-md max-w-48 animate-in fade-in-0 duration-150"
                >
                  <p class="font-medium text-gray-900">{{ step.description }}</p>
                  <p class="text-gray-500 mt-0.5">{{ formatDuration(step) }}</p>
                  <TooltipArrow class="fill-white" />
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
        class="mt-3 w-full py-2 text-xs text-gray-500 hover:text-gray-900 hover:bg-gray-50 rounded-md transition-colors duration-150"
        @click="$emit('loadMore')"
      >
        Load more activity
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, defineComponent, h, resolveComponent, type PropType } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
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
import type { ActivityStep as ActivityStepType } from '@/types'

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
  completed: 'bg-green-50 text-green-600 hover:bg-green-100',
  in_progress: 'bg-gray-100 text-gray-600 hover:bg-gray-200',
  pending: 'bg-gray-50 text-gray-500 hover:bg-gray-100',
  failed: 'bg-red-50 text-red-600 hover:bg-red-100',
  skipped: 'bg-gray-50 text-gray-400 hover:bg-gray-100',
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
  'pl-5 border-l-2 border-gray-200 space-y-2',
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
      completed: 'bg-green-50 text-green-600',
      in_progress: 'bg-gray-100 text-gray-600',
      pending: 'bg-gray-50 text-gray-500',
      failed: 'bg-red-50 text-red-600',
      skipped: 'bg-gray-50 text-gray-400',
    }

    const formatTime = (date: Date) => {
      return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })
    }

    return () => h('div', {
      class: [
        'flex items-start gap-3 text-xs',
        stepProps.animate && 'animate-in fade-in-0 slide-in-from-left-2 duration-150',
        stepProps.clickable && 'cursor-pointer hover:bg-gray-50 rounded-md p-1 -m-1 transition-colors duration-150',
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
              ? 'text-gray-500'
              : 'text-gray-900',
            stepProps.step.status === 'failed' && 'text-red-600',
          ],
        }, stepProps.step.description),
        (stepProps.showDuration || stepProps.showTimestamp) && h('p', {
          class: 'text-gray-400 mt-0.5 flex items-center gap-2',
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
