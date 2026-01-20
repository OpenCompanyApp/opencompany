<template>
  <div :class="containerClasses">
    <!-- Header -->
    <div :class="headerClasses">
      <div class="flex items-center gap-3">
        <div :class="headerIconClasses">
          <Icon name="ph:robot-fill" :class="headerIconInnerClasses" />
          <!-- Pulse indicator for active agents -->
          <span v-if="activeAgents.length > 0 && !loading" class="absolute -top-0.5 -right-0.5">
            <span class="relative flex h-2.5 w-2.5">
              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75" />
              <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-green-500" />
            </span>
          </span>
        </div>
        <div>
          <h2 :class="titleClasses">
            {{ title }}
            <span v-if="showLiveIndicator && activeAgents.length > 0" class="ml-2 inline-flex items-center gap-1 text-xs font-normal text-green-400">
              <span class="w-1.5 h-1.5 rounded-full bg-green-400 animate-pulse" />
              Live
            </span>
          </h2>
          <p v-if="!loading" :class="subtitleClasses">
            <span v-if="activeAgents.length > 0" class="text-green-400">
              {{ activeAgents.length }} active
            </span>
            <span v-if="activeAgents.length > 0 && pausedAgents.length > 0">, </span>
            <span v-if="pausedAgents.length > 0" class="text-amber-400">
              {{ pausedAgents.length }} paused
            </span>
            <span v-if="activeAgents.length === 0 && pausedAgents.length === 0" class="text-olympus-text-muted">
              No agents working
            </span>
          </p>
          <SharedSkeleton v-else custom-class="h-3 w-20 mt-1" />
        </div>
      </div>

      <!-- Header Actions -->
      <div class="flex items-center gap-2">
        <!-- Filter Dropdown -->
        <DropdownMenuRoot v-if="showFilter && !loading">
          <DropdownMenuTrigger as-child>
            <button type="button" :class="filterButtonClasses">
              <Icon name="ph:funnel" class="w-4 h-4" />
              <span v-if="activeFilter !== 'all'" class="text-xs">
                {{ filterOptions.find(f => f.value === activeFilter)?.label }}
              </span>
            </button>
          </DropdownMenuTrigger>
          <DropdownMenuPortal>
            <DropdownMenuContent :class="dropdownContentClasses" :side-offset="8">
              <DropdownMenuLabel class="px-2 py-1.5 text-xs text-olympus-text-muted">
                Filter by type
              </DropdownMenuLabel>
              <DropdownMenuRadioGroup v-model="activeFilter">
                <DropdownMenuRadioItem
                  v-for="option in filterOptions"
                  :key="option.value"
                  :value="option.value"
                  :class="dropdownItemClasses"
                >
                  <DropdownMenuItemIndicator class="absolute left-2">
                    <Icon name="ph:check" class="w-3.5 h-3.5" />
                  </DropdownMenuItemIndicator>
                  <span class="pl-5">{{ option.label }}</span>
                  <span v-if="getFilterCount(option.value) > 0" class="ml-auto text-xs text-olympus-text-muted">
                    {{ getFilterCount(option.value) }}
                  </span>
                </DropdownMenuRadioItem>
              </DropdownMenuRadioGroup>
            </DropdownMenuContent>
          </DropdownMenuPortal>
        </DropdownMenuRoot>

        <!-- View Toggle -->
        <div v-if="showViewToggle && !loading" class="flex items-center gap-0.5 p-0.5 rounded-lg bg-olympus-bg border border-olympus-border">
          <button
            v-for="view in viewOptions"
            :key="view.value"
            type="button"
            :class="[
              'p-1.5 rounded-md transition-colors duration-150',
              currentView === view.value
                ? 'bg-olympus-primary/20 text-olympus-primary'
                : 'text-olympus-text-muted hover:text-olympus-text-secondary',
            ]"
            @click="currentView = view.value"
          >
            <Icon :name="view.icon" class="w-4 h-4" />
          </button>
        </div>

        <!-- Refresh Button -->
        <TooltipProvider v-if="showRefresh" :delay-duration="300">
          <TooltipRoot>
            <TooltipTrigger as-child>
              <button
                type="button"
                :class="refreshButtonClasses"
                :disabled="refreshing"
                @click="handleRefresh"
              >
                <Icon
                  name="ph:arrows-clockwise"
                  :class="['w-4 h-4', refreshing && 'animate-spin']"
                />
              </button>
            </TooltipTrigger>
            <TooltipPortal>
              <TooltipContent :class="tooltipClasses" side="bottom">
                Refresh
                <TooltipArrow class="fill-olympus-elevated" />
              </TooltipContent>
            </TooltipPortal>
          </TooltipRoot>
        </TooltipProvider>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" :class="contentClasses">
      <AgentCardSkeleton v-for="i in 3" :key="i" :view="currentView" />
    </div>

    <!-- Content -->
    <div v-else-if="filteredAgents.length > 0" :class="contentClasses">
      <TransitionGroup :name="animated ? 'agent-list' : ''" tag="div" :class="gridClasses">
        <div
          v-for="agent in displayedAgents"
          :key="agent.id"
          :class="agentCardClasses(agent)"
          @mouseenter="hoveredAgent = agent.id"
          @mouseleave="hoveredAgent = null"
        >
          <!-- Compact View -->
          <template v-if="currentView === 'compact'">
            <div class="flex items-center gap-3">
              <div class="relative">
                <SharedAgentAvatar :user="agent" :size="avatarSize" />
                <span
                  :class="[
                    'absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full border-2 border-olympus-bg',
                    getStatusColor(agent.status),
                  ]"
                />
              </div>
              <div class="flex-1 min-w-0">
                <p :class="agentNameClasses">{{ agent.name }}</p>
                <p :class="agentTaskClasses">{{ agent.currentTask }}</p>
              </div>
              <AgentProgress v-if="agent.progress !== undefined" :progress="agent.progress" size="sm" />
            </div>
          </template>

          <!-- Default/Detailed View -->
          <template v-else>
            <!-- Agent Header -->
            <div class="flex items-center gap-3 mb-3">
              <div class="relative">
                <SharedAgentAvatar :user="agent" :size="avatarSize" />
                <span
                  :class="[
                    'absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full border-2 border-olympus-bg',
                    getStatusColor(agent.status),
                  ]"
                />
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                  <p :class="agentNameClasses">{{ agent.name }}</p>
                  <SharedStatusBadge :status="agent.status!" size="xs" />
                </div>
                <p :class="agentTypeClasses">
                  {{ agent.agentType }}
                  <span v-if="agent.startedAt" class="text-olympus-text-subtle">
                    · {{ formatElapsed(agent.startedAt) }}
                  </span>
                </p>
              </div>

              <!-- Agent Actions -->
              <div v-if="showActions && hoveredAgent === agent.id" class="flex items-center gap-1">
                <TooltipProvider :delay-duration="200">
                  <TooltipRoot>
                    <TooltipTrigger as-child>
                      <button
                        type="button"
                        :class="agentActionButtonClasses"
                        @click.stop="handlePause(agent)"
                      >
                        <Icon :name="agent.status === 'paused' ? 'ph:play' : 'ph:pause'" class="w-3.5 h-3.5" />
                      </button>
                    </TooltipTrigger>
                    <TooltipPortal>
                      <TooltipContent :class="tooltipClasses" side="top">
                        {{ agent.status === 'paused' ? 'Resume' : 'Pause' }}
                        <TooltipArrow class="fill-olympus-elevated" />
                      </TooltipContent>
                    </TooltipPortal>
                  </TooltipRoot>
                </TooltipProvider>

                <TooltipProvider :delay-duration="200">
                  <TooltipRoot>
                    <TooltipTrigger as-child>
                      <button
                        type="button"
                        :class="[agentActionButtonClasses, 'hover:bg-red-500/20 hover:text-red-400']"
                        @click.stop="handleCancel(agent)"
                      >
                        <Icon name="ph:stop" class="w-3.5 h-3.5" />
                      </button>
                    </TooltipTrigger>
                    <TooltipPortal>
                      <TooltipContent :class="tooltipClasses" side="top">
                        Cancel
                        <TooltipArrow class="fill-olympus-elevated" />
                      </TooltipContent>
                    </TooltipPortal>
                  </TooltipRoot>
                </TooltipProvider>
              </div>
            </div>

            <!-- Current Task -->
            <div :class="taskContainerClasses">
              <Icon name="ph:code" class="w-3.5 h-3.5 text-olympus-text-subtle shrink-0 mt-0.5" />
              <p :class="[agentTaskClasses, 'line-clamp-2']">
                {{ agent.currentTask || 'Idle' }}
              </p>
            </div>

            <!-- Progress Bar -->
            <div v-if="agent.progress !== undefined" class="mt-3">
              <div class="flex items-center justify-between mb-1.5">
                <span class="text-xs text-olympus-text-muted">Progress</span>
                <span class="text-xs font-medium text-olympus-text-secondary">{{ agent.progress }}%</span>
              </div>
              <div class="h-1.5 rounded-full bg-olympus-bg overflow-hidden">
                <div
                  :class="progressBarClasses(agent)"
                  :style="{ width: `${agent.progress}%` }"
                />
              </div>
            </div>

            <!-- Resource Usage (Detailed View) -->
            <div v-if="currentView === 'detailed' && showResources" class="mt-3 pt-3 border-t border-olympus-border-subtle">
              <div class="flex items-center gap-4">
                <div class="flex items-center gap-1.5">
                  <Icon name="ph:cpu" class="w-3.5 h-3.5 text-olympus-text-subtle" />
                  <span class="text-xs text-olympus-text-muted">
                    CPU: <span :class="getResourceColor(agent.cpuUsage || 0)">{{ agent.cpuUsage || 0 }}%</span>
                  </span>
                </div>
                <div class="flex items-center gap-1.5">
                  <Icon name="ph:memory" class="w-3.5 h-3.5 text-olympus-text-subtle" />
                  <span class="text-xs text-olympus-text-muted">
                    RAM: <span :class="getResourceColor(agent.memoryUsage || 0)">{{ agent.memoryUsage || 0 }}%</span>
                  </span>
                </div>
                <div v-if="agent.tokensUsed" class="flex items-center gap-1.5">
                  <Icon name="ph:coins" class="w-3.5 h-3.5 text-amber-400" />
                  <span class="text-xs text-olympus-text-muted">
                    {{ formatTokens(agent.tokensUsed) }} tokens
                  </span>
                </div>
              </div>
            </div>

            <!-- Activity Log -->
            <Transition name="expand">
              <div v-if="showActivityLog && agent.activityLog?.length" class="mt-3">
                <CollapsibleRoot v-model:open="expandedAgents[agent.id]">
                  <CollapsibleTrigger :class="activityToggleClasses">
                    <span class="text-xs">Activity Log</span>
                    <Icon
                      :name="expandedAgents[agent.id] ? 'ph:caret-up' : 'ph:caret-down'"
                      class="w-3 h-3"
                    />
                  </CollapsibleTrigger>
                  <CollapsibleContent class="overflow-hidden data-[state=open]:animate-slideDown data-[state=closed]:animate-slideUp">
                    <div class="pt-2">
                      <SharedActivityLog :steps="agent.activityLog" :max-visible="maxActivitySteps" />
                    </div>
                  </CollapsibleContent>
                </CollapsibleRoot>
              </div>
            </Transition>
          </template>
        </div>
      </TransitionGroup>

      <!-- Load More -->
      <button
        v-if="filteredAgents.length > displayLimit && !expanded"
        type="button"
        :class="loadMoreButtonClasses"
        @click="expanded = true"
      >
        <span>Show {{ filteredAgents.length - displayLimit }} more agents</span>
        <Icon name="ph:caret-down" class="w-4 h-4" />
      </button>
    </div>

    <!-- Empty State -->
    <div v-else :class="emptyStateClasses">
      <div :class="emptyIconClasses">
        <Icon name="ph:moon-stars" class="w-8 h-8 text-olympus-text-subtle" />
      </div>
      <h3 class="text-sm font-medium text-olympus-text-secondary mb-1">
        {{ emptyTitle }}
      </h3>
      <p class="text-xs text-olympus-text-muted text-center max-w-[200px]">
        {{ emptyDescription }}
      </p>
      <button
        v-if="showEmptyAction"
        type="button"
        :class="emptyActionClasses"
        @click="emit('startAgent')"
      >
        <Icon name="ph:plus" class="w-4 h-4" />
        <span>Start an agent</span>
      </button>
    </div>

    <!-- Footer Stats -->
    <div v-if="showStats && !loading && agents.length > 0" :class="footerClasses">
      <div class="flex items-center gap-4">
        <div class="flex items-center gap-1.5">
          <Icon name="ph:timer" class="w-3.5 h-3.5 text-olympus-text-subtle" />
          <span class="text-xs text-olympus-text-muted">
            Avg. runtime: {{ formatDuration(avgRuntime) }}
          </span>
        </div>
        <div v-if="totalTokens > 0" class="flex items-center gap-1.5">
          <Icon name="ph:coins" class="w-3.5 h-3.5 text-amber-400" />
          <span class="text-xs text-olympus-text-muted">
            Total: {{ formatTokens(totalTokens) }} tokens
          </span>
        </div>
      </div>
      <div v-if="lastUpdated" class="text-xs text-olympus-text-subtle">
        Updated {{ formatTimeAgo(lastUpdated) }}
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { h } from 'vue'
import {
  CollapsibleContent,
  CollapsibleRoot,
  CollapsibleTrigger,
  DropdownMenuContent,
  DropdownMenuItemIndicator,
  DropdownMenuLabel,
  DropdownMenuPortal,
  DropdownMenuRadioGroup,
  DropdownMenuRadioItem,
  DropdownMenuRoot,
  DropdownMenuTrigger,
  TooltipArrow,
  TooltipContent,
  TooltipPortal,
  TooltipProvider,
  TooltipRoot,
  TooltipTrigger,
} from 'reka-ui'
import type { User, UserStatus } from '~/types'

type WorkingAgentsSize = 'sm' | 'md' | 'lg'
type WorkingAgentsView = 'compact' | 'default' | 'detailed'
type AgentFilter = 'all' | 'active' | 'paused' | 'researcher' | 'coder' | 'reviewer'

interface ExtendedAgent extends User {
  startedAt?: Date
  progress?: number
  cpuUsage?: number
  memoryUsage?: number
  tokensUsed?: number
}

const props = withDefaults(defineProps<{
  // Core
  agents: ExtendedAgent[]

  // Appearance
  size?: WorkingAgentsSize

  // Display options
  showFilter?: boolean
  showViewToggle?: boolean
  showRefresh?: boolean
  showActions?: boolean
  showResources?: boolean
  showActivityLog?: boolean
  showStats?: boolean
  showLiveIndicator?: boolean
  showEmptyAction?: boolean

  // Content
  title?: string
  emptyTitle?: string
  emptyDescription?: string

  // State
  loading?: boolean
  refreshing?: boolean

  // Limits
  displayLimit?: number
  maxActivitySteps?: number

  // Behavior
  animated?: boolean
  lastUpdated?: Date
}>(), {
  size: 'md',
  showFilter: true,
  showViewToggle: true,
  showRefresh: true,
  showActions: true,
  showResources: true,
  showActivityLog: true,
  showStats: true,
  showLiveIndicator: true,
  showEmptyAction: true,
  title: 'Working Now',
  emptyTitle: 'All agents idle',
  emptyDescription: 'Agents will appear here when they\'re working on tasks',
  loading: false,
  refreshing: false,
  displayLimit: 5,
  maxActivitySteps: 5,
  animated: true,
  lastUpdated: undefined,
})

const emit = defineEmits<{
  refresh: []
  pause: [agent: ExtendedAgent]
  resume: [agent: ExtendedAgent]
  cancel: [agent: ExtendedAgent]
  startAgent: []
}>()

// State
const activeFilter = ref<AgentFilter>('all')
const currentView = ref<WorkingAgentsView>('default')
const expanded = ref(false)
const hoveredAgent = ref<string | null>(null)
const expandedAgents = reactive<Record<string, boolean>>({})

// Filter options
const filterOptions: { value: AgentFilter; label: string }[] = [
  { value: 'all', label: 'All' },
  { value: 'active', label: 'Active' },
  { value: 'paused', label: 'Paused' },
  { value: 'researcher', label: 'Researchers' },
  { value: 'coder', label: 'Coders' },
  { value: 'reviewer', label: 'Reviewers' },
]

// View options
const viewOptions: { value: WorkingAgentsView; icon: string }[] = [
  { value: 'compact', icon: 'ph:rows' },
  { value: 'default', icon: 'ph:square' },
  { value: 'detailed', icon: 'ph:squares-four' },
]

// Size configuration
const sizeConfig: Record<WorkingAgentsSize, {
  padding: string
  headerPadding: string
  contentPadding: string
  avatarSize: 'xs' | 'sm' | 'md'
  titleSize: string
  subtitleSize: string
  gap: string
}> = {
  sm: {
    padding: 'p-3',
    headerPadding: 'px-3 py-2',
    contentPadding: 'px-3 pb-3',
    avatarSize: 'xs',
    titleSize: 'text-sm',
    subtitleSize: 'text-xs',
    gap: 'gap-2',
  },
  md: {
    padding: 'p-4',
    headerPadding: 'px-4 py-3',
    contentPadding: 'px-4 pb-4',
    avatarSize: 'sm',
    titleSize: 'text-base',
    subtitleSize: 'text-sm',
    gap: 'gap-3',
  },
  lg: {
    padding: 'p-5',
    headerPadding: 'px-5 py-4',
    contentPadding: 'px-5 pb-5',
    avatarSize: 'md',
    titleSize: 'text-lg',
    subtitleSize: 'text-sm',
    gap: 'gap-4',
  },
}

// Computed values
const avatarSize = computed(() => sizeConfig[props.size].avatarSize)

const activeAgents = computed(() =>
  props.agents.filter(a => a.status === 'online' || a.status === 'busy')
)

const pausedAgents = computed(() =>
  props.agents.filter(a => a.status === 'paused')
)

const filteredAgents = computed(() => {
  if (activeFilter.value === 'all') return props.agents
  if (activeFilter.value === 'active') return activeAgents.value
  if (activeFilter.value === 'paused') return pausedAgents.value
  return props.agents.filter(a => a.agentType?.toLowerCase() === activeFilter.value)
})

const displayedAgents = computed(() =>
  expanded.value ? filteredAgents.value : filteredAgents.value.slice(0, props.displayLimit)
)

const avgRuntime = computed(() => {
  const agentsWithTime = props.agents.filter(a => a.startedAt)
  if (agentsWithTime.length === 0) return 0
  const totalSeconds = agentsWithTime.reduce((sum, a) => {
    return sum + Math.floor((Date.now() - a.startedAt!.getTime()) / 1000)
  }, 0)
  return Math.floor(totalSeconds / agentsWithTime.length)
})

const totalTokens = computed(() =>
  props.agents.reduce((sum, a) => sum + (a.tokensUsed || 0), 0)
)

const getFilterCount = (filter: AgentFilter): number => {
  if (filter === 'all') return props.agents.length
  if (filter === 'active') return activeAgents.value.length
  if (filter === 'paused') return pausedAgents.value.length
  return props.agents.filter(a => a.agentType?.toLowerCase() === filter).length
}

// Status color mapping
const getStatusColor = (status?: UserStatus): string => {
  const colors: Record<UserStatus, string> = {
    online: 'bg-green-500',
    busy: 'bg-amber-500',
    offline: 'bg-gray-500',
    away: 'bg-yellow-500',
    paused: 'bg-blue-500',
  }
  return colors[status || 'offline']
}

// Resource color
const getResourceColor = (value: number): string => {
  if (value < 50) return 'text-green-400'
  if (value < 80) return 'text-amber-400'
  return 'text-red-400'
}

// Container classes
const containerClasses = computed(() => [
  'card-gradient bg-olympus-surface rounded-2xl overflow-hidden',
  'border border-olympus-border-subtle',
])

// Header classes
const headerClasses = computed(() => [
  'flex items-center justify-between border-b border-olympus-border-subtle',
  sizeConfig[props.size].headerPadding,
])

const headerIconClasses = computed(() => [
  'relative rounded-xl flex items-center justify-center',
  'bg-olympus-primary/20',
  props.size === 'sm' ? 'w-8 h-8' : props.size === 'lg' ? 'w-12 h-12' : 'w-10 h-10',
])

const headerIconInnerClasses = computed(() => [
  'text-olympus-primary',
  props.size === 'sm' ? 'w-4 h-4' : props.size === 'lg' ? 'w-6 h-6' : 'w-5 h-5',
])

const titleClasses = computed(() => [
  'font-semibold text-olympus-text-primary',
  sizeConfig[props.size].titleSize,
])

const subtitleClasses = computed(() => [
  'text-olympus-text-muted',
  sizeConfig[props.size].subtitleSize,
])

// Content classes
const contentClasses = computed(() => [
  sizeConfig[props.size].contentPadding,
  'pt-0 mt-4',
])

const gridClasses = computed(() => [
  currentView.value === 'detailed' ? 'grid grid-cols-1 lg:grid-cols-2' : 'flex flex-col',
  sizeConfig[props.size].gap,
])

// Agent card classes
const agentCardClasses = (agent: ExtendedAgent) => [
  'rounded-xl bg-olympus-bg border transition-all duration-200',
  currentView.value === 'compact' ? 'p-3' : 'p-4',
  hoveredAgent.value === agent.id
    ? 'border-olympus-primary/50 shadow-lg shadow-olympus-primary/10'
    : 'border-olympus-border-subtle hover:border-olympus-border',
  agent.status === 'paused' && 'opacity-75',
]

const agentNameClasses = computed(() => [
  'font-medium text-olympus-text-primary truncate',
  props.size === 'sm' ? 'text-xs' : 'text-sm',
])

const agentTypeClasses = computed(() => [
  'text-olympus-text-muted capitalize',
  props.size === 'sm' ? 'text-[10px]' : 'text-xs',
])

const agentTaskClasses = computed(() => [
  'text-olympus-text-secondary',
  props.size === 'sm' ? 'text-xs' : 'text-sm',
])

const taskContainerClasses = computed(() => [
  'flex items-start gap-2 p-2 rounded-lg bg-olympus-elevated/50',
])

// Progress bar classes
const progressBarClasses = (agent: ExtendedAgent) => [
  'h-full rounded-full transition-all duration-500',
  agent.progress! >= 80 ? 'bg-green-500' : agent.progress! >= 50 ? 'bg-olympus-primary' : 'bg-amber-500',
]

// Button classes
const filterButtonClasses = computed(() => [
  'flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg transition-colors duration-150',
  'text-olympus-text-muted hover:text-olympus-text-secondary',
  'bg-olympus-bg border border-olympus-border hover:border-olympus-border-hover',
  'focus:outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50',
])

const refreshButtonClasses = computed(() => [
  'p-1.5 rounded-lg transition-colors duration-150',
  'text-olympus-text-muted hover:text-olympus-text-secondary',
  'hover:bg-olympus-elevated',
  'focus:outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50',
  'disabled:opacity-50 disabled:cursor-not-allowed',
])

const agentActionButtonClasses = computed(() => [
  'p-1.5 rounded-md transition-colors duration-150',
  'text-olympus-text-muted hover:text-olympus-text-secondary',
  'hover:bg-olympus-elevated',
  'focus:outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50',
])

const activityToggleClasses = computed(() => [
  'w-full flex items-center justify-between py-1.5',
  'text-olympus-text-muted hover:text-olympus-text-secondary',
  'transition-colors duration-150 outline-none',
])

const loadMoreButtonClasses = computed(() => [
  'w-full flex items-center justify-center gap-2 py-2.5 mt-2',
  'text-sm text-olympus-text-muted hover:text-olympus-text-secondary',
  'rounded-lg hover:bg-olympus-elevated transition-colors duration-150',
  'focus:outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50',
])

// Dropdown classes
const dropdownContentClasses = computed(() => [
  'z-50 min-w-[160px] bg-olympus-elevated border border-olympus-border rounded-xl',
  'p-1 shadow-xl shadow-black/20',
  'animate-in fade-in-0 zoom-in-95 duration-100',
])

const dropdownItemClasses = computed(() => [
  'relative flex items-center px-2 py-1.5 text-sm rounded-lg cursor-pointer',
  'text-olympus-text-secondary hover:bg-olympus-hover',
  'transition-colors duration-100 outline-none',
  'data-[highlighted]:bg-olympus-hover',
])

// Tooltip classes
const tooltipClasses = computed(() => [
  'z-50 bg-olympus-elevated border border-olympus-border rounded-lg',
  'px-2 py-1 text-xs shadow-lg',
  'animate-in fade-in-0 zoom-in-95 duration-100',
])

// Empty state classes
const emptyStateClasses = computed(() => [
  'flex flex-col items-center justify-center py-8',
  sizeConfig[props.size].contentPadding,
])

const emptyIconClasses = computed(() => [
  'w-16 h-16 rounded-2xl bg-olympus-elevated/50 flex items-center justify-center mb-4',
])

const emptyActionClasses = computed(() => [
  'flex items-center gap-2 mt-4 px-4 py-2 rounded-lg',
  'bg-olympus-primary/10 text-olympus-primary',
  'hover:bg-olympus-primary/20 transition-colors duration-150',
  'focus:outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50',
])

// Footer classes
const footerClasses = computed(() => [
  'flex items-center justify-between px-4 py-2.5 border-t border-olympus-border-subtle',
  'bg-olympus-bg/50',
])

// Helper functions
const formatElapsed = (startedAt: Date): string => {
  const seconds = Math.floor((Date.now() - startedAt.getTime()) / 1000)
  if (seconds < 60) return `${seconds}s`
  const minutes = Math.floor(seconds / 60)
  if (minutes < 60) return `${minutes}m`
  const hours = Math.floor(minutes / 60)
  return `${hours}h ${minutes % 60}m`
}

const formatDuration = (seconds: number): string => {
  if (seconds < 60) return `${seconds}s`
  const minutes = Math.floor(seconds / 60)
  if (minutes < 60) return `${minutes}m`
  const hours = Math.floor(minutes / 60)
  return `${hours}h ${minutes % 60}m`
}

const formatTokens = (tokens: number): string => {
  if (tokens < 1000) return tokens.toString()
  if (tokens < 1000000) return `${(tokens / 1000).toFixed(1)}K`
  return `${(tokens / 1000000).toFixed(2)}M`
}

const formatTimeAgo = (date: Date): string => {
  const seconds = Math.floor((Date.now() - date.getTime()) / 1000)
  if (seconds < 60) return 'just now'
  const minutes = Math.floor(seconds / 60)
  if (minutes < 60) return `${minutes}m ago`
  const hours = Math.floor(minutes / 60)
  return `${hours}h ago`
}

// Handlers
const handleRefresh = () => {
  emit('refresh')
}

const handlePause = (agent: ExtendedAgent) => {
  if (agent.status === 'paused') {
    emit('resume', agent)
  } else {
    emit('pause', agent)
  }
}

const handleCancel = (agent: ExtendedAgent) => {
  emit('cancel', agent)
}

// Agent Card Skeleton
const AgentCardSkeleton = defineComponent({
  name: 'AgentCardSkeleton',
  props: {
    view: {
      type: String as PropType<WorkingAgentsView>,
      default: 'default',
    },
  },
  setup(props) {
    const isCompact = computed(() => props.view === 'compact')

    return () => h('div', {
      class: [
        'rounded-xl bg-olympus-bg border border-olympus-border-subtle animate-pulse',
        isCompact.value ? 'p-3' : 'p-4',
      ],
    }, [
      h('div', { class: 'flex items-center gap-3' }, [
        h(resolveComponent('SharedSkeleton'), { variant: 'avatar' }),
        h('div', { class: 'flex-1 space-y-2' }, [
          h(resolveComponent('SharedSkeleton'), { customClass: 'h-4 w-24' }),
          h(resolveComponent('SharedSkeleton'), { customClass: 'h-3 w-16' }),
        ]),
        !isCompact.value && h(resolveComponent('SharedSkeleton'), { customClass: 'h-5 w-16 rounded' }),
      ]),
      !isCompact.value && h('div', { class: 'mt-3' }, [
        h(resolveComponent('SharedSkeleton'), { customClass: 'h-10 w-full rounded-lg' }),
      ]),
      !isCompact.value && h('div', { class: 'mt-3' }, [
        h('div', { class: 'flex justify-between mb-1.5' }, [
          h(resolveComponent('SharedSkeleton'), { customClass: 'h-3 w-12' }),
          h(resolveComponent('SharedSkeleton'), { customClass: 'h-3 w-8' }),
        ]),
        h(resolveComponent('SharedSkeleton'), { customClass: 'h-1.5 w-full rounded-full' }),
      ]),
    ])
  },
})

// Agent Progress Component
const AgentProgress = defineComponent({
  name: 'AgentProgress',
  props: {
    progress: {
      type: Number,
      required: true,
    },
    size: {
      type: String as PropType<'sm' | 'md'>,
      default: 'md',
    },
  },
  setup(props) {
    const progressColor = computed(() => {
      if (props.progress >= 80) return 'text-green-400'
      if (props.progress >= 50) return 'text-olympus-primary'
      return 'text-amber-400'
    })

    return () => h('div', {
      class: [
        'flex items-center gap-1.5',
        props.size === 'sm' ? 'text-xs' : 'text-sm',
      ],
    }, [
      h('div', {
        class: [
          'rounded-full bg-olympus-elevated overflow-hidden',
          props.size === 'sm' ? 'w-12 h-1' : 'w-16 h-1.5',
        ],
      }, [
        h('div', {
          class: [
            'h-full rounded-full transition-all duration-500',
            props.progress >= 80 ? 'bg-green-500' : props.progress >= 50 ? 'bg-olympus-primary' : 'bg-amber-500',
          ],
          style: { width: `${props.progress}%` },
        }),
      ]),
      h('span', { class: ['font-medium', progressColor.value] }, `${props.progress}%`),
    ])
  },
})
</script>

<style scoped>
/* Agent list transitions */
.agent-list-enter-active,
.agent-list-leave-active {
  transition: all 0.3s ease;
}

.agent-list-enter-from {
  opacity: 0;
  transform: translateY(-10px);
}

.agent-list-leave-to {
  opacity: 0;
  transform: translateY(10px);
}

.agent-list-move {
  transition: transform 0.3s ease;
}

/* Expand transition */
.expand-enter-active,
.expand-leave-active {
  transition: all 0.2s ease;
}

.expand-enter-from,
.expand-leave-to {
  opacity: 0;
  transform: translateY(-5px);
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

.animate-slideDown {
  animation: slideDown 0.2s ease-out;
}

.animate-slideUp {
  animation: slideUp 0.2s ease-out;
}
</style>
