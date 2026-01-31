<template>
  <CollapsibleRoot v-if="collapsible" v-model:open="isOpen" :class="wrapperClasses">
    <!-- Header -->
    <div v-if="showHeader" class="flex items-center justify-between gap-4 mb-3">
      <Button
        variant="ghost"
        color="neutral"
        size="sm"
        class="flex items-center gap-2 text-sm font-medium text-neutral-900 dark:text-white hover:text-neutral-700 dark:hover:text-neutral-200 transition-colors duration-150 group"
        @click="isOpen = !isOpen"
      >
        <Icon
          name="ph:caret-right"
          class="w-4 h-4 transition-transform duration-150 ease-out"
          :class="{ 'rotate-90': isOpen }"
        />
        <span>{{ title }}</span>
        <Badge
          v-if="showCount"
          :label="filteredSteps.length.toString()"
          size="xs"
          variant="subtle"
          color="neutral"
        />
      </Button>

      <!-- Actions -->
      <div class="flex items-center gap-2">
        <!-- Filter dropdown -->
        <DropdownMenu v-if="filterable" :items="filterDropdownItems">
          <Button
            variant="ghost"
            color="neutral"
            size="xs"
            icon="ph:funnel"
            class="p-1.5"
          />
        </DropdownMenu>

        <!-- View mode toggle -->
        <div v-if="showViewToggle" class="flex items-center bg-neutral-100 dark:bg-neutral-700 rounded-md p-0.5">
          <button
            v-for="mode in viewModes"
            :key="mode.value"
            type="button"
            class="p-1 rounded transition-colors duration-150"
            :class="viewMode === mode.value ? 'bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white shadow-sm' : 'text-neutral-500 dark:text-neutral-300 hover:text-neutral-900 dark:hover:text-white'"
            @click="viewMode = mode.value"
          >
            <Icon :name="mode.icon" class="w-4 h-4" />
          </button>
        </div>

        <!-- Expand/Collapse all -->
        <button
          v-if="groupByDate"
          type="button"
          class="p-1.5 rounded-md text-neutral-500 dark:text-neutral-300 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-50 dark:hover:bg-neutral-800 transition-colors duration-150"
          @click="toggleAllGroups"
        >
          <Icon :name="allExpanded ? 'ph:arrows-in' : 'ph:arrows-out'" class="w-4 h-4" />
        </button>

        <!-- Refresh button -->
        <button
          v-if="refreshable"
          type="button"
          class="p-1.5 rounded-md text-neutral-500 dark:text-neutral-300 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-50 dark:hover:bg-neutral-800 transition-colors duration-150"
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
    <div v-show="isOpen" :class="contentClasses">
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
              class="flex items-center gap-2 text-xs font-medium text-neutral-500 dark:text-neutral-300 hover:text-neutral-900 dark:hover:text-white transition-colors duration-150"
              @click="toggleGroup(group.date)"
            >
              <Icon
                name="ph:caret-right"
                class="w-3 h-3 transition-transform duration-150 ease-out"
                :class="{ 'rotate-90': expandedGroups.includes(group.date) }"
              />
              <span>{{ group.label }}</span>
              <span class="text-neutral-400 dark:text-neutral-400">({{ group.steps.length }})</span>
            </button>

            <Transition
              enter-active-class="transition-all duration-150 ease-out"
              enter-from-class="opacity-0 max-h-0"
              enter-to-class="opacity-100 max-h-[2000px]"
              leave-active-class="transition-all duration-150 ease-out"
              leave-from-class="opacity-100 max-h-[2000px]"
              leave-to-class="opacity-0 max-h-0"
            >
              <div v-show="expandedGroups.includes(group.date)" class="pl-5 border-l-2 border-neutral-200 dark:border-neutral-700 space-y-2 overflow-hidden">
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
        <div class="divide-y divide-neutral-200 dark:divide-neutral-700">
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
          <Tooltip
            v-for="step in filteredSteps"
            :key="step.id"
            :delay-duration="200"
            side="top"
            :side-offset="4"
          >
            <template #content>
              <p class="font-medium text-neutral-900 dark:text-white">{{ step.description }}</p>
              <p class="text-neutral-500 dark:text-neutral-300 mt-0.5">{{ formatDuration(step) }}</p>
            </template>
            <button
              type="button"
              class="p-1 rounded transition-colors duration-150"
              :class="stepCompactClasses[step.status]"
              @click="handleStepClick(step)"
            >
              <Icon :name="stepIcons[step.status]" class="w-3 h-3" />
            </button>
          </Tooltip>
        </div>
      </template>

      <!-- Load more -->
      <button
        v-if="hasMore && !loading"
        type="button"
        class="mt-3 w-full py-2 text-xs text-neutral-500 dark:text-neutral-300 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-50 dark:hover:bg-neutral-800 rounded-md transition-colors duration-150"
        @click="$emit('loadMore')"
      >
        Load more activity
      </button>
    </div>
  </CollapsibleRoot>

  <!-- Non-collapsible version -->
  <div v-else :class="wrapperClasses">
    <!-- Header -->
    <div v-if="showHeader" class="flex items-center justify-between gap-4 mb-3">
      <div class="flex items-center gap-2">
        <Icon v-if="icon" :name="icon" class="w-4 h-4 text-neutral-500 dark:text-neutral-300" />
        <span class="text-sm font-medium text-neutral-900 dark:text-white">{{ title }}</span>
        <Badge
          v-if="showCount"
          :label="filteredSteps.length.toString()"
          size="xs"
          variant="subtle"
          color="neutral"
        />
      </div>

      <!-- Actions -->
      <div class="flex items-center gap-2">
        <!-- Filter dropdown -->
        <DropdownMenu v-if="filterable" :items="filterDropdownItems">
          <Button
            variant="ghost"
            color="neutral"
            size="xs"
            icon="ph:funnel"
            class="p-1.5"
          />
        </DropdownMenu>

        <!-- View mode toggle -->
        <div v-if="showViewToggle" class="flex items-center bg-neutral-100 dark:bg-neutral-700 rounded-md p-0.5">
          <button
            v-for="mode in viewModes"
            :key="mode.value"
            type="button"
            class="p-1 rounded transition-colors duration-150"
            :class="viewMode === mode.value ? 'bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white shadow-sm' : 'text-neutral-500 dark:text-neutral-300 hover:text-neutral-900 dark:hover:text-white'"
            @click="viewMode = mode.value"
          >
            <Icon :name="mode.icon" class="w-4 h-4" />
          </button>
        </div>

        <!-- Expand/Collapse all -->
        <button
          v-if="groupByDate"
          type="button"
          class="p-1.5 rounded-md text-neutral-500 dark:text-neutral-300 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-50 dark:hover:bg-neutral-800 transition-colors duration-150"
          @click="toggleAllGroups"
        >
          <Icon :name="allExpanded ? 'ph:arrows-in' : 'ph:arrows-out'" class="w-4 h-4" />
        </button>

        <!-- Refresh button -->
        <button
          v-if="refreshable"
          type="button"
          class="p-1.5 rounded-md text-neutral-500 dark:text-neutral-300 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-50 dark:hover:bg-neutral-800 transition-colors duration-150"
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
              class="flex items-center gap-2 text-xs font-medium text-neutral-500 dark:text-neutral-300 hover:text-neutral-900 dark:hover:text-white transition-colors duration-150"
              @click="toggleGroup(group.date)"
            >
              <Icon
                name="ph:caret-right"
                class="w-3 h-3 transition-transform duration-150 ease-out"
                :class="{ 'rotate-90': expandedGroups.includes(group.date) }"
              />
              <span>{{ group.label }}</span>
              <span class="text-neutral-400 dark:text-neutral-400">({{ group.steps.length }})</span>
            </button>

            <Transition
              enter-active-class="transition-all duration-150 ease-out"
              enter-from-class="opacity-0 max-h-0"
              enter-to-class="opacity-100 max-h-[2000px]"
              leave-active-class="transition-all duration-150 ease-out"
              leave-from-class="opacity-100 max-h-[2000px]"
              leave-to-class="opacity-0 max-h-0"
            >
              <div v-show="expandedGroups.includes(group.date)" class="pl-5 border-l-2 border-neutral-200 dark:border-neutral-700 space-y-2 overflow-hidden">
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
        <div class="divide-y divide-neutral-200 dark:divide-neutral-700">
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
          <Tooltip
            v-for="step in filteredSteps"
            :key="step.id"
            :delay-duration="200"
            side="top"
            :side-offset="4"
          >
            <template #content>
              <p class="font-medium text-neutral-900 dark:text-white">{{ step.description }}</p>
              <p class="text-neutral-500 dark:text-neutral-300 mt-0.5">{{ formatDuration(step) }}</p>
            </template>
            <button
              type="button"
              class="p-1 rounded transition-colors duration-150"
              :class="stepCompactClasses[step.status]"
              @click="handleStepClick(step)"
            >
              <Icon :name="stepIcons[step.status]" class="w-3 h-3" />
            </button>
          </Tooltip>
        </div>
      </template>

      <!-- Load more -->
      <button
        v-if="hasMore && !loading"
        type="button"
        class="mt-3 w-full py-2 text-xs text-neutral-500 dark:text-neutral-300 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-50 dark:hover:bg-neutral-800 rounded-md transition-colors duration-150"
        @click="$emit('loadMore')"
      >
        Load more activity
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, defineComponent, h, type PropType } from 'vue'
import { CollapsibleRoot } from 'reka-ui'
import type { ActivityStep as ActivityStepType } from '@/types'
import Icon from '@/Components/shared/Icon.vue'
import Button from '@/Components/shared/Button.vue'
import Badge from '@/Components/shared/Badge.vue'
import DropdownMenu from '@/Components/shared/DropdownMenu.vue'
import Tooltip from '@/Components/shared/Tooltip.vue'
import SharedSearchInput from '@/Components/shared/SearchInput.vue'
import SharedSkeleton from '@/Components/shared/Skeleton.vue'
import SharedEmptyState from '@/Components/shared/EmptyState.vue'

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
  completed: 'bg-green-50 dark:bg-green-900/30 text-green-600 hover:bg-green-100 dark:hover:bg-green-900/50',
  in_progress: 'bg-neutral-100 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-200 hover:bg-neutral-200 dark:hover:bg-neutral-600',
  pending: 'bg-neutral-50 dark:bg-neutral-800 text-neutral-500 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-700',
  failed: 'bg-red-50 dark:bg-red-900/30 text-red-600 hover:bg-red-100 dark:hover:bg-red-900/50',
  skipped: 'bg-neutral-50 dark:bg-neutral-800 text-neutral-400 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-700',
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
  'pl-5 border-l-2 border-neutral-200 dark:border-neutral-700 space-y-2',
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

// Filter dropdown items for DropdownMenu
const filterDropdownItems = computed(() => [
  [{
    label: 'Filter by status',
    slot: 'header',
    disabled: true,
  }],
  statusOptions.map(status => ({
    label: status.label,
    icon: activeFilters.value.includes(status.value) ? 'ph:check-square' : 'ph:square',
    click: () => toggleFilter(status.value),
  })),
])

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

// Icon class mapping for iconify in render functions
const stepIconClasses: Record<string, string> = {
  completed: 'i-ph:check',
  in_progress: 'i-ph:spinner',
  failed: 'i-ph:x',
  skipped: 'i-ph:minus',
}

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
      completed: 'bg-green-50 dark:bg-green-900/30 text-green-600',
      in_progress: 'bg-neutral-100 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-200',
      pending: 'bg-neutral-50 dark:bg-neutral-800 text-neutral-500 dark:text-neutral-300',
      failed: 'bg-red-50 dark:bg-red-900/30 text-red-600',
      skipped: 'bg-neutral-50 dark:bg-neutral-800 text-neutral-400 dark:text-neutral-400',
    }

    const formatTime = (date: Date) => {
      return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })
    }

    return () => h('div', {
      class: [
        'flex items-start gap-3 text-xs',
        stepProps.animate && 'animate-in fade-in-0 slide-in-from-left-2 duration-150',
        stepProps.clickable && 'cursor-pointer hover:bg-neutral-50 dark:hover:bg-neutral-800 rounded-md p-1 -m-1 transition-colors duration-150',
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
        stepProps.step.status === 'completed' && h('span', {
          class: ['w-3 h-3', stepIconClasses.completed],
        }),
        stepProps.step.status === 'in_progress' && h('span', {
          class: ['w-3 h-3 animate-spin', stepIconClasses.in_progress],
        }),
        stepProps.step.status === 'failed' && h('span', {
          class: ['w-3 h-3', stepIconClasses.failed],
        }),
        stepProps.step.status === 'skipped' && h('span', {
          class: ['w-3 h-3', stepIconClasses.skipped],
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
              ? 'text-neutral-500 dark:text-neutral-300'
              : 'text-neutral-900 dark:text-white',
            stepProps.step.status === 'failed' && 'text-red-600',
          ],
        }, stepProps.step.description),
        (stepProps.showDuration || stepProps.showTimestamp) && h('p', {
          class: 'text-neutral-400 dark:text-neutral-400 mt-0.5 flex items-center gap-2',
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
