<template>
  <div :class="containerClasses">
    <!-- Header (when showHeader is true) -->
    <div v-if="showHeader" :class="headerClasses">
      <div class="flex items-center gap-2">
        <div :class="headerIconContainerClasses">
          <Icon name="ph:chart-bar-fill" :class="headerIconClasses" />
        </div>
        <div>
          <h2 :class="headerTitleClasses">{{ title }}</h2>
          <p v-if="subtitle" :class="headerSubtitleClasses">{{ subtitle }}</p>
        </div>
      </div>

      <!-- Time Range Selector -->
      <div v-if="showTimeRange" class="flex items-center gap-1">
        <button
          v-for="range in timeRanges"
          :key="range.value"
          type="button"
          :class="[timeRangeButtonClasses, selectedTimeRange === range.value && 'bg-olympus-surface text-olympus-text']"
          @click="handleTimeRangeChange(range.value)"
        >
          {{ range.label }}
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" :class="gridClasses">
      <StatCardSkeleton v-for="i in 4" :key="i" />
    </div>

    <!-- Stats Grid -->
    <div v-else :class="gridClasses">
      <TransitionGroup :name="animated ? 'stat-card' : ''" tag="div" :class="gridClasses" class="contents">
        <!-- Agents Online -->
        <div
          key="agents"
          :class="[statCardClasses, interactive && 'cursor-pointer']"
          @click="interactive && emit('statClick', 'agents')"
          @mouseenter="hoveredStat = 'agents'"
          @mouseleave="hoveredStat = null"
        >
          <div class="flex items-start justify-between">
            <div :class="[iconContainerClasses, 'bg-green-500/20']">
              <Icon name="ph:robot-fill" :class="[iconClasses, 'text-green-400']" />
            </div>
            <TrendBadge v-if="showTrends" :value="12" :animated="animated" />
          </div>

          <div class="mt-3">
            <p :class="labelClasses">Agents Online</p>
            <div class="flex items-baseline gap-2">
              <AnimatedNumber
                :value="stats.agentsOnline"
                :class="valueClasses"
                :animated="animated"
              />
              <span :class="subValueClasses">of {{ stats.totalAgents }}</span>
            </div>
          </div>

          <!-- Mini Chart -->
          <div v-if="showCharts && hoveredStat === 'agents'" :class="miniChartClasses">
            <MiniSparkline :data="agentChartData" color="green" />
          </div>

          <!-- Progress Bar -->
          <div v-if="showProgress" class="mt-3">
            <div class="h-1.5 bg-olympus-bg rounded-full overflow-hidden">
              <div
                class="h-full bg-gradient-to-r from-green-500 to-green-400 rounded-full transition-all duration-500"
                :style="{ width: `${(stats.agentsOnline / stats.totalAgents) * 100}%` }"
              />
            </div>
          </div>
        </div>

        <!-- Tasks Completed -->
        <div
          key="tasks"
          :class="[statCardClasses, interactive && 'cursor-pointer']"
          @click="interactive && emit('statClick', 'tasks')"
          @mouseenter="hoveredStat = 'tasks'"
          @mouseleave="hoveredStat = null"
        >
          <div class="flex items-start justify-between">
            <div :class="[iconContainerClasses, 'bg-olympus-primary/20']">
              <Icon name="ph:check-circle-fill" :class="[iconClasses, 'text-olympus-primary']" />
            </div>
            <TrendBadge v-if="showTrends" :value="8" :animated="animated" />
          </div>

          <div class="mt-3">
            <p :class="labelClasses">Tasks Completed</p>
            <div class="flex items-baseline gap-2">
              <AnimatedNumber
                :value="stats.tasksCompleted"
                :class="valueClasses"
                :animated="animated"
              />
              <span :class="subValueClasses">+{{ stats.tasksToday }} today</span>
            </div>
          </div>

          <!-- Mini Chart -->
          <div v-if="showCharts && hoveredStat === 'tasks'" :class="miniChartClasses">
            <MiniSparkline :data="taskChartData" color="primary" />
          </div>

          <!-- Task breakdown -->
          <div v-if="showBreakdown" class="mt-3 flex items-center gap-2">
            <span class="text-[10px] px-1.5 py-0.5 rounded bg-green-500/20 text-green-400">
              {{ stats.tasksToday }} done
            </span>
            <span class="text-[10px] px-1.5 py-0.5 rounded bg-blue-500/20 text-blue-400">
              {{ Math.floor(stats.tasksToday * 0.3) }} in progress
            </span>
          </div>
        </div>

        <!-- Messages -->
        <div
          key="messages"
          :class="[statCardClasses, interactive && 'cursor-pointer']"
          @click="interactive && emit('statClick', 'messages')"
          @mouseenter="hoveredStat = 'messages'"
          @mouseleave="hoveredStat = null"
        >
          <div class="flex items-start justify-between">
            <div :class="[iconContainerClasses, 'bg-blue-500/20']">
              <Icon name="ph:chat-circle-fill" :class="[iconClasses, 'text-blue-400']" />
            </div>
            <TrendBadge v-if="showTrends" :value="23" :animated="animated" />
          </div>

          <div class="mt-3">
            <p :class="labelClasses">Messages</p>
            <div class="flex items-baseline gap-2">
              <AnimatedNumber
                :value="stats.messagesTotal"
                :class="valueClasses"
                :animated="animated"
                :format="formatCompactNumber"
              />
              <span :class="subValueClasses">+{{ stats.messagesToday }} today</span>
            </div>
          </div>

          <!-- Mini Chart -->
          <div v-if="showCharts && hoveredStat === 'messages'" :class="miniChartClasses">
            <MiniSparkline :data="messageChartData" color="blue" />
          </div>

          <!-- Message types breakdown -->
          <div v-if="showBreakdown" class="mt-3 flex items-center gap-1">
            <TooltipProvider :delay-duration="200">
              <TooltipRoot>
                <TooltipTrigger as-child>
                  <span class="text-[10px] px-1.5 py-0.5 rounded bg-olympus-surface text-olympus-text-muted">
                    <Icon name="ph:user" class="w-2.5 h-2.5 inline" /> {{ Math.floor(stats.messagesToday * 0.6) }}
                  </span>
                </TooltipTrigger>
                <TooltipPortal>
                  <TooltipContent :class="tooltipClasses" side="bottom">
                    Human messages
                    <TooltipArrow class="fill-olympus-elevated" />
                  </TooltipContent>
                </TooltipPortal>
              </TooltipRoot>
            </TooltipProvider>
            <TooltipProvider :delay-duration="200">
              <TooltipRoot>
                <TooltipTrigger as-child>
                  <span class="text-[10px] px-1.5 py-0.5 rounded bg-olympus-surface text-olympus-text-muted">
                    <Icon name="ph:robot" class="w-2.5 h-2.5 inline" /> {{ Math.floor(stats.messagesToday * 0.4) }}
                  </span>
                </TooltipTrigger>
                <TooltipPortal>
                  <TooltipContent :class="tooltipClasses" side="bottom">
                    AI agent messages
                    <TooltipArrow class="fill-olympus-elevated" />
                  </TooltipContent>
                </TooltipPortal>
              </TooltipRoot>
            </TooltipProvider>
          </div>
        </div>

        <!-- Credits Used -->
        <div
          key="credits"
          :class="[statCardClasses, interactive && 'cursor-pointer']"
          @click="interactive && emit('statClick', 'credits')"
          @mouseenter="hoveredStat = 'credits'"
          @mouseleave="hoveredStat = null"
        >
          <div class="flex items-start justify-between">
            <div :class="[iconContainerClasses, 'bg-amber-500/20']">
              <Icon name="ph:coins-fill" :class="[iconClasses, 'text-amber-400']" />
            </div>
            <CreditWarningBadge v-if="creditWarning" :remaining="stats.creditsRemaining" />
          </div>

          <div class="mt-3">
            <p :class="labelClasses">Credits Used</p>
            <div class="flex items-baseline gap-2">
              <span :class="valueClasses">
                $<AnimatedNumber
                  :value="stats.creditsUsed"
                  :animated="animated"
                />
              </span>
              <span :class="subValueClasses">${{ stats.creditsRemaining.toFixed(0) }} remaining</span>
            </div>
          </div>

          <!-- Mini Chart -->
          <div v-if="showCharts && hoveredStat === 'credits'" :class="miniChartClasses">
            <MiniSparkline :data="creditChartData" color="amber" />
          </div>

          <!-- Credit usage bar -->
          <div v-if="showProgress" class="mt-3">
            <div class="h-1.5 bg-olympus-bg rounded-full overflow-hidden">
              <div
                :class="[
                  'h-full rounded-full transition-all duration-500',
                  creditUsagePercent > 80
                    ? 'bg-gradient-to-r from-red-500 to-red-400'
                    : creditUsagePercent > 50
                      ? 'bg-gradient-to-r from-amber-500 to-amber-400'
                      : 'bg-gradient-to-r from-green-500 to-green-400',
                ]"
                :style="{ width: `${creditUsagePercent}%` }"
              />
            </div>
            <div class="flex justify-between mt-1">
              <span class="text-[9px] text-olympus-text-subtle">$0</span>
              <span class="text-[9px] text-olympus-text-subtle">${{ (stats.creditsUsed + stats.creditsRemaining).toFixed(0) }}</span>
            </div>
          </div>
        </div>
      </TransitionGroup>
    </div>

    <!-- Footer with last updated -->
    <div v-if="showLastUpdated && lastUpdated" :class="footerClasses">
      <div class="flex items-center gap-1.5 text-olympus-text-subtle">
        <Icon name="ph:clock" class="w-3 h-3" />
        <span class="text-[10px]">Updated {{ formatLastUpdated(lastUpdated) }}</span>
      </div>
      <button
        type="button"
        :class="refreshButtonClasses"
        :disabled="refreshing"
        @click="emit('refresh')"
      >
        <Icon
          name="ph:arrows-clockwise"
          :class="['w-3.5 h-3.5 transition-transform', refreshing && 'animate-spin']"
        />
        <span v-if="size !== 'sm'">Refresh</span>
      </button>
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
import type { Stats } from '~/types'

type StatsOverviewSize = 'sm' | 'md' | 'lg'
type StatsOverviewVariant = 'default' | 'compact' | 'detailed'
type TimeRange = 'today' | 'week' | 'month' | 'year'
type StatType = 'agents' | 'tasks' | 'messages' | 'credits'

const props = withDefaults(defineProps<{
  // Core
  stats: Stats

  // Appearance
  size?: StatsOverviewSize
  variant?: StatsOverviewVariant

  // Display options
  showHeader?: boolean
  showTimeRange?: boolean
  showTrends?: boolean
  showCharts?: boolean
  showProgress?: boolean
  showBreakdown?: boolean
  showLastUpdated?: boolean

  // Content
  title?: string
  subtitle?: string

  // State
  loading?: boolean
  refreshing?: boolean
  lastUpdated?: Date | null

  // Behavior
  interactive?: boolean
  animated?: boolean

  // Time range
  selectedTimeRange?: TimeRange
}>(), {
  size: 'md',
  variant: 'default',
  showHeader: false,
  showTimeRange: false,
  showTrends: true,
  showCharts: true,
  showProgress: true,
  showBreakdown: false,
  showLastUpdated: false,
  title: 'Overview',
  subtitle: undefined,
  loading: false,
  refreshing: false,
  lastUpdated: null,
  interactive: true,
  animated: true,
  selectedTimeRange: 'today',
})

const emit = defineEmits<{
  statClick: [stat: StatType]
  timeRangeChange: [range: TimeRange]
  refresh: []
}>()

// State
const hoveredStat = ref<StatType | null>(null)

// Time ranges
const timeRanges: { value: TimeRange; label: string }[] = [
  { value: 'today', label: 'Today' },
  { value: 'week', label: '7D' },
  { value: 'month', label: '30D' },
  { value: 'year', label: '1Y' },
]

// Size configuration
const sizeConfig: Record<StatsOverviewSize, {
  grid: string
  card: string
  icon: string
  iconContainer: string
  label: string
  value: string
  subValue: string
  gap: string
}> = {
  sm: {
    grid: 'grid-cols-2 lg:grid-cols-4 gap-3',
    card: 'p-3',
    icon: 'w-4 h-4',
    iconContainer: 'w-8 h-8',
    label: 'text-[10px]',
    value: 'text-lg',
    subValue: 'text-[9px]',
    gap: 'gap-3',
  },
  md: {
    grid: 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4',
    card: 'p-4',
    icon: 'w-5 h-5',
    iconContainer: 'w-10 h-10',
    label: 'text-xs',
    value: 'text-2xl',
    subValue: 'text-xs',
    gap: 'gap-4',
  },
  lg: {
    grid: 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5',
    card: 'p-5',
    icon: 'w-6 h-6',
    iconContainer: 'w-12 h-12',
    label: 'text-sm',
    value: 'text-3xl',
    subValue: 'text-sm',
    gap: 'gap-5',
  },
}

// Mock chart data
const agentChartData = [3, 5, 4, 7, 6, 8, 7, 9, 8, 10]
const taskChartData = [12, 15, 18, 14, 22, 19, 25, 28, 24, 30]
const messageChartData = [45, 52, 48, 61, 55, 70, 68, 75, 82, 90]
const creditChartData = [10, 18, 25, 32, 38, 45, 52, 58, 65, 70]

// Credit warning
const creditWarning = computed(() => {
  const total = props.stats.creditsUsed + props.stats.creditsRemaining
  return (props.stats.creditsUsed / total) > 0.8
})

const creditUsagePercent = computed(() => {
  const total = props.stats.creditsUsed + props.stats.creditsRemaining
  return Math.min((props.stats.creditsUsed / total) * 100, 100)
})

// Container classes
const containerClasses = computed(() => [
  props.variant === 'detailed' && 'bg-olympus-surface rounded-2xl p-4',
])

// Header classes
const headerClasses = computed(() => [
  'flex items-center justify-between mb-4',
])

const headerIconContainerClasses = computed(() => [
  'rounded-lg flex items-center justify-center',
  'bg-olympus-primary/20',
  sizeConfig[props.size].iconContainer,
])

const headerIconClasses = computed(() => [
  'text-olympus-primary',
  sizeConfig[props.size].icon,
])

const headerTitleClasses = computed(() => [
  'font-semibold',
  props.size === 'sm' ? 'text-sm' : props.size === 'lg' ? 'text-lg' : 'text-base',
])

const headerSubtitleClasses = computed(() => [
  'text-olympus-text-muted',
  sizeConfig[props.size].label,
])

// Time range button classes
const timeRangeButtonClasses = computed(() => [
  'px-2.5 py-1 text-xs rounded-lg transition-colors duration-150',
  'text-olympus-text-muted hover:text-olympus-text hover:bg-olympus-surface/50',
  'outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50',
])

// Grid classes
const gridClasses = computed(() => [
  'grid',
  sizeConfig[props.size].grid,
])

// Stat card classes
const statCardClasses = computed(() => [
  'rounded-xl bg-olympus-surface border border-olympus-border-subtle',
  'transition-all duration-200',
  sizeConfig[props.size].card,
  props.interactive && 'hover:border-olympus-border hover:shadow-lg hover:shadow-olympus-primary/5',
  props.interactive && 'hover:-translate-y-0.5',
])

// Icon classes
const iconContainerClasses = computed(() => [
  'rounded-lg flex items-center justify-center',
  sizeConfig[props.size].iconContainer,
])

const iconClasses = computed(() => [
  sizeConfig[props.size].icon,
])

// Text classes
const labelClasses = computed(() => [
  'text-olympus-text-muted font-medium',
  sizeConfig[props.size].label,
])

const valueClasses = computed(() => [
  'font-bold text-olympus-text',
  sizeConfig[props.size].value,
])

const subValueClasses = computed(() => [
  'text-olympus-text-subtle',
  sizeConfig[props.size].subValue,
])

// Mini chart classes
const miniChartClasses = computed(() => [
  'mt-3 h-8 animate-in fade-in-0 duration-200',
])

// Footer classes
const footerClasses = computed(() => [
  'flex items-center justify-between mt-4 pt-4 border-t border-olympus-border',
])

// Refresh button classes
const refreshButtonClasses = computed(() => [
  'flex items-center gap-1 px-2 py-1 text-xs rounded-lg',
  'text-olympus-text-muted hover:text-olympus-text hover:bg-olympus-surface',
  'transition-colors duration-150',
  'outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50',
  'disabled:opacity-50 disabled:cursor-not-allowed',
])

// Tooltip classes
const tooltipClasses = computed(() => [
  'z-50 bg-olympus-elevated border border-olympus-border rounded-lg',
  'px-2 py-1 text-xs shadow-lg',
  'animate-in fade-in-0 zoom-in-95 duration-100',
])

// Handlers
const handleTimeRangeChange = (range: TimeRange) => {
  emit('timeRangeChange', range)
}

// Format functions
const formatCompactNumber = (value: number): string => {
  if (value >= 1000000) return `${(value / 1000000).toFixed(1)}M`
  if (value >= 1000) return `${(value / 1000).toFixed(1)}K`
  return value.toString()
}

const formatLastUpdated = (date: Date): string => {
  const now = new Date()
  const diff = now.getTime() - new Date(date).getTime()
  const seconds = Math.floor(diff / 1000)
  const minutes = Math.floor(seconds / 60)

  if (seconds < 60) return 'just now'
  if (minutes < 60) return `${minutes}m ago`
  return new Date(date).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })
}

// Animated Number component
const AnimatedNumber = defineComponent({
  name: 'AnimatedNumber',
  props: {
    value: { type: Number, required: true },
    animated: { type: Boolean, default: true },
    format: { type: Function, default: (v: number) => v.toLocaleString() },
  },
  setup(props) {
    const displayValue = ref(props.animated ? 0 : props.value)

    watch(() => props.value, (newVal) => {
      if (!props.animated) {
        displayValue.value = newVal
        return
      }

      const start = displayValue.value
      const diff = newVal - start
      const duration = 800
      const startTime = performance.now()

      const animate = (currentTime: number) => {
        const elapsed = currentTime - startTime
        const progress = Math.min(elapsed / duration, 1)
        const easeOut = 1 - Math.pow(1 - progress, 3)
        displayValue.value = Math.round(start + diff * easeOut)

        if (progress < 1) {
          requestAnimationFrame(animate)
        }
      }

      requestAnimationFrame(animate)
    }, { immediate: true })

    return () => h('span', {}, props.format(displayValue.value))
  },
})

// Trend Badge component
const TrendBadge = defineComponent({
  name: 'TrendBadge',
  props: {
    value: { type: Number, required: true },
    animated: { type: Boolean, default: true },
  },
  setup(props) {
    const isPositive = props.value >= 0
    return () => h('div', {
      class: [
        'flex items-center gap-0.5 px-1.5 py-0.5 rounded-md text-[10px] font-medium',
        isPositive ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400',
        props.animated && 'animate-in fade-in-0 slide-in-from-right-2 duration-300',
      ],
    }, [
      h(resolveComponent('Icon'), {
        name: isPositive ? 'ph:trend-up' : 'ph:trend-down',
        class: 'w-3 h-3',
      }),
      `${isPositive ? '+' : ''}${props.value}%`,
    ])
  },
})

// Credit Warning Badge component
const CreditWarningBadge = defineComponent({
  name: 'CreditWarningBadge',
  props: {
    remaining: { type: Number, required: true },
  },
  setup(props) {
    return () => h('div', {
      class: [
        'flex items-center gap-1 px-1.5 py-0.5 rounded-md text-[10px] font-medium',
        'bg-red-500/20 text-red-400 animate-pulse',
      ],
    }, [
      h(resolveComponent('Icon'), {
        name: 'ph:warning',
        class: 'w-3 h-3',
      }),
      'Low',
    ])
  },
})

// Mini Sparkline component
const MiniSparkline = defineComponent({
  name: 'MiniSparkline',
  props: {
    data: { type: Array as () => number[], required: true },
    color: { type: String, default: 'primary' },
  },
  setup(props) {
    const max = Math.max(...props.data)
    const min = Math.min(...props.data)
    const range = max - min || 1

    const colorMap: Record<string, string> = {
      primary: 'stroke-olympus-primary',
      green: 'stroke-green-400',
      blue: 'stroke-blue-400',
      amber: 'stroke-amber-400',
    }

    const points = props.data.map((value, index) => {
      const x = (index / (props.data.length - 1)) * 100
      const y = 100 - ((value - min) / range) * 100
      return `${x},${y}`
    }).join(' ')

    return () => h('svg', {
      viewBox: '0 0 100 100',
      preserveAspectRatio: 'none',
      class: 'w-full h-full',
    }, [
      h('polyline', {
        points,
        fill: 'none',
        class: [colorMap[props.color] || colorMap.primary, 'opacity-50'],
        'stroke-width': '3',
        'stroke-linecap': 'round',
        'stroke-linejoin': 'round',
      }),
    ])
  },
})

// Stat Card Skeleton component
const StatCardSkeleton = defineComponent({
  name: 'StatCardSkeleton',
  setup() {
    return () => h('div', {
      class: [
        'rounded-xl bg-olympus-surface border border-olympus-border-subtle p-4',
        'animate-pulse',
      ],
    }, [
      h('div', { class: 'flex items-start justify-between' }, [
        h(resolveComponent('SharedSkeleton'), { customClass: 'w-10 h-10 rounded-lg' }),
        h(resolveComponent('SharedSkeleton'), { customClass: 'w-12 h-5 rounded-md' }),
      ]),
      h('div', { class: 'mt-3 space-y-2' }, [
        h(resolveComponent('SharedSkeleton'), { customClass: 'h-3 w-20' }),
        h(resolveComponent('SharedSkeleton'), { customClass: 'h-6 w-16' }),
        h(resolveComponent('SharedSkeleton'), { customClass: 'h-2 w-24' }),
      ]),
      h('div', { class: 'mt-3' }, [
        h(resolveComponent('SharedSkeleton'), { customClass: 'h-1.5 w-full rounded-full' }),
      ]),
    ])
  },
})
</script>

<style scoped>
/* Stat card transitions */
.stat-card-enter-active,
.stat-card-leave-active {
  transition: all 0.3s ease;
}

.stat-card-enter-from {
  opacity: 0;
  transform: translateY(10px);
}

.stat-card-leave-to {
  opacity: 0;
  transform: scale(0.95);
}

.stat-card-move {
  transition: transform 0.3s ease;
}
</style>
