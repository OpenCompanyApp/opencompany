<template>
  <component
    :is="interactive ? 'button' : href ? Link : 'div'"
    :href="href"
    :type="interactive && !href ? 'button' : undefined"
    :class="cardClasses"
    @click="handleClick"
  >
    <!-- Loading State -->
    <template v-if="loading">
      <div class="absolute inset-0 flex items-center justify-center bg-white/80 dark:bg-neutral-900/80 rounded-lg z-10">
        <Icon name="ph:spinner" class="w-6 h-6 animate-spin text-neutral-400 dark:text-neutral-400" />
      </div>
    </template>

    <!-- Main Content -->
    <div :class="['relative', loading && 'opacity-50']">
      <!-- Header Row -->
      <div class="flex items-start justify-between mb-3">
        <!-- Label & Description -->
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2">
            <span :class="labelClasses">{{ label }}</span>
            <Badge v-if="badge" variant="default" size="xs">{{ badge }}</Badge>
          </div>
          <p v-if="description" class="text-neutral-500 dark:text-neutral-300 text-xs mt-0.5 line-clamp-1">
            {{ description }}
          </p>
        </div>

        <!-- Icon -->
        <div v-if="icon" :class="iconContainerClasses">
          <Icon :name="icon" :class="iconClasses" />
        </div>
      </div>

      <!-- Value Section -->
      <div class="flex items-baseline gap-2 flex-wrap">
        <span :class="valueClasses">
          <span v-if="prefix" class="text-neutral-500 dark:text-neutral-300 mr-0.5">{{ prefix }}</span>
          <Transition
            v-if="animated"
            name="value-change"
            mode="out-in"
          >
            <span :key="formattedValue" class="tabular-nums">{{ formattedValue }}</span>
          </Transition>
          <span v-else class="tabular-nums">{{ formattedValue }}</span>
          <span v-if="suffix" class="text-neutral-500 dark:text-neutral-300 ml-0.5 text-[0.65em]">{{ suffix }}</span>
        </span>
        <span v-if="subValue" class="text-sm text-neutral-500 dark:text-neutral-300">{{ subValue }}</span>
      </div>

      <!-- Trend Indicator -->
      <div v-if="trend !== undefined || trendLabel" :class="trendContainerClasses">
        <div v-if="trend !== undefined" class="flex items-center gap-1">
          <div :class="trendIconContainerClasses">
            <Icon
              :name="trendIcon"
              :class="trendIconClasses"
            />
          </div>
          <span :class="trendValueClasses">
            {{ Math.abs(trend) }}%
          </span>
        </div>
        <span v-if="trendLabel" class="text-neutral-500 dark:text-neutral-300 text-xs">
          {{ trendLabel }}
        </span>
      </div>

      <!-- Sparkline Chart -->
      <div v-if="sparklineData && sparklineData.length > 0" class="mt-3 pt-3 border-t border-neutral-100 dark:border-neutral-800">
        <svg
          class="w-full h-8"
          :viewBox="`0 0 ${sparklineData.length * 10} 32`"
          preserveAspectRatio="none"
        >
          <defs>
            <linearGradient :id="`sparkline-gradient-${uid}`" x1="0%" y1="0%" x2="0%" y2="100%">
              <stop offset="0%" :stop-color="sparklineGradientStart" stop-opacity="0.2" />
              <stop offset="100%" :stop-color="sparklineGradientStart" stop-opacity="0" />
            </linearGradient>
          </defs>
          <!-- Area fill -->
          <path
            :d="sparklineAreaPath"
            :fill="`url(#sparkline-gradient-${uid})`"
          />
          <!-- Line -->
          <path
            :d="sparklineLinePath"
            fill="none"
            :stroke="sparklineLineColor"
            stroke-width="1.5"
            stroke-linecap="round"
            stroke-linejoin="round"
          />
          <!-- End dot -->
          <circle
            :cx="(sparklineData.length - 1) * 10"
            :cy="sparklinePoints[sparklinePoints.length - 1]"
            r="2.5"
            :fill="sparklineLineColor"
          />
        </svg>
      </div>

      <!-- Progress Bar -->
      <div v-if="progress !== undefined" class="mt-3 pt-3 border-t border-neutral-100 dark:border-neutral-800">
        <div class="flex items-center justify-between text-xs mb-1.5">
          <span class="text-neutral-500 dark:text-neutral-300">{{ progressLabel || 'Progress' }}</span>
          <span :class="progressValueClasses">{{ progress }}%</span>
        </div>
        <div class="h-1.5 bg-neutral-100 dark:bg-neutral-700 rounded-full overflow-hidden">
          <div
            :class="progressBarClasses"
            :style="{ width: `${Math.min(Math.max(progress, 0), 100)}%` }"
          />
        </div>
      </div>

      <!-- Footer / Actions -->
      <div v-if="$slots.footer || action" class="mt-3 pt-3 border-t border-neutral-100 flex items-center justify-between">
        <slot name="footer" />
        <button
          v-if="action"
          type="button"
          class="text-xs text-neutral-900 dark:text-white hover:text-neutral-700 dark:hover:text-neutral-200 font-medium transition-colors duration-150 flex items-center gap-1 rounded-md px-2 py-1 -mx-2 hover:bg-neutral-50 dark:hover:bg-neutral-800"
          @click.stop="handleAction"
        >
          {{ action }}
          <Icon name="ph:arrow-right" class="w-3 h-3" />
        </button>
      </div>

      <!-- Comparison -->
      <div v-if="comparison" class="mt-3 pt-3 border-t border-neutral-100 dark:border-neutral-800">
        <div class="flex items-center justify-between text-xs">
          <span class="text-neutral-500 dark:text-neutral-300">{{ comparison.label }}</span>
          <span class="text-neutral-900 dark:text-white font-medium">{{ formatValue(comparison.value) }}</span>
        </div>
      </div>
    </div>
  </component>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import Icon from '@/Components/shared/Icon.vue'
import Badge from '@/Components/shared/Badge.vue'

type StatCardSize = 'sm' | 'md' | 'lg'
type StatCardVariant = 'default' | 'elevated' | 'outlined'

interface ComparisonData {
  label: string
  value: number | string
}

const props = withDefaults(defineProps<{
  // Core
  label: string
  value: string | number
  subValue?: string
  description?: string

  // Icon
  icon?: string

  // Formatting
  prefix?: string
  suffix?: string
  precision?: number
  compact?: boolean

  // Trend
  trend?: number
  trendLabel?: string
  inverseTrend?: boolean

  // Sparkline
  sparklineData?: number[]

  // Progress
  progress?: number
  progressLabel?: string

  // Comparison
  comparison?: ComparisonData

  // Badge
  badge?: string

  // Appearance
  size?: StatCardSize
  variant?: StatCardVariant

  // Interactive
  interactive?: boolean
  href?: string
  action?: string
  disabled?: boolean

  // Visual
  animated?: boolean

  // Loading
  loading?: boolean
}>(), {
  size: 'md',
  variant: 'default',
  precision: 0,
  compact: false,
  inverseTrend: false,
  interactive: false,
  disabled: false,
  animated: true,
  loading: false,
})

const emit = defineEmits<{
  click: [value: string | number]
  action: []
}>()

// Unique ID for SVG gradients
const uid = Math.random().toString(36).substring(2, 9)

// Format number with compact notation
const formatCompact = (num: number): string => {
  if (num >= 1_000_000_000) return `${(num / 1_000_000_000).toFixed(1)}B`
  if (num >= 1_000_000) return `${(num / 1_000_000).toFixed(1)}M`
  if (num >= 1_000) return `${(num / 1_000).toFixed(1)}K`
  return num.toFixed(props.precision)
}

// Format value
const formatValue = (val: number | string): string => {
  if (typeof val === 'string') return val
  if (props.compact) return formatCompact(val)
  return val.toLocaleString(undefined, {
    minimumFractionDigits: props.precision,
    maximumFractionDigits: props.precision,
  })
}

// Formatted main value
const formattedValue = computed(() => {
  if (typeof props.value === 'number') {
    return formatValue(props.value)
  }
  return props.value
})

// Trend direction
type TrendDirection = 'up' | 'down' | 'neutral'
const trendDirection = computed((): TrendDirection => {
  if (props.trend === undefined || props.trend === 0) return 'neutral'
  return props.trend > 0 ? 'up' : 'down'
})

// Trend is positive (considering inverse)
const trendIsPositive = computed(() => {
  if (props.inverseTrend) {
    return trendDirection.value === 'down'
  }
  return trendDirection.value === 'up'
})

// Trend icon
const trendIcon = computed(() => {
  if (trendDirection.value === 'neutral') return 'ph:minus'
  return trendDirection.value === 'up' ? 'ph:trend-up' : 'ph:trend-down'
})

// Size-based classes
const sizeConfig = {
  sm: {
    padding: 'p-3',
    label: 'text-xs',
    value: 'text-xl',
    icon: 'w-7 h-7',
    iconInner: 'w-4 h-4',
  },
  md: {
    padding: 'p-4',
    label: 'text-sm',
    value: 'text-2xl',
    icon: 'w-9 h-9',
    iconInner: 'w-5 h-5',
  },
  lg: {
    padding: 'p-5',
    label: 'text-sm',
    value: 'text-3xl',
    icon: 'w-11 h-11',
    iconInner: 'w-6 h-6',
  },
}

// Variant classes
const variantClasses: Record<StatCardVariant, string> = {
  default: 'bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700',
  elevated: 'bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 shadow-sm',
  outlined: 'bg-transparent border border-neutral-300 dark:border-neutral-600',
}

// Card classes
const cardClasses = computed(() => {
  const classes: string[] = [
    'relative rounded-lg transition-colors duration-150 overflow-hidden',
    sizeConfig[props.size].padding,
    variantClasses[props.variant],
  ]

  // Interactive styles
  if ((props.interactive || props.href) && !props.disabled) {
    classes.push(
      'cursor-pointer',
      'hover:border-neutral-300 dark:hover:border-neutral-600 hover:shadow-sm',
      'focus:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900/50 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-neutral-900'
    )
  }

  // Disabled
  if (props.disabled) {
    classes.push('opacity-50 cursor-not-allowed')
  }

  return classes
})

// Label classes
const labelClasses = computed(() => [
  'text-neutral-500 dark:text-neutral-300 font-medium',
  sizeConfig[props.size].label,
])

// Value classes
const valueClasses = computed(() => [
  'font-bold tracking-tight text-neutral-900 dark:text-white',
  sizeConfig[props.size].value,
])

// Icon container classes
const iconContainerClasses = computed(() => [
  'rounded-lg flex items-center justify-center bg-neutral-100 dark:bg-neutral-700',
  sizeConfig[props.size].icon,
])

// Icon classes
const iconClasses = computed(() => [
  sizeConfig[props.size].iconInner,
  'text-neutral-600 dark:text-neutral-200',
])

// Trend container classes
const trendContainerClasses = computed(() => [
  'mt-2 flex items-center gap-2 flex-wrap',
])

// Trend icon container classes
const trendIconContainerClasses = computed(() => {
  const baseClasses = 'w-5 h-5 rounded flex items-center justify-center'

  if (trendIsPositive.value) {
    return `${baseClasses} bg-green-100`
  }
  if (trendDirection.value === 'down') {
    return `${baseClasses} bg-red-100`
  }
  return `${baseClasses} bg-neutral-100 dark:bg-neutral-700`
})

// Trend icon classes
const trendIconClasses = computed(() => {
  const baseClasses = 'w-3.5 h-3.5'

  if (trendIsPositive.value) {
    return `${baseClasses} text-green-600`
  }
  if (trendDirection.value === 'down') {
    return `${baseClasses} text-red-600`
  }
  return `${baseClasses} text-neutral-500 dark:text-neutral-300`
})

// Trend value classes
const trendValueClasses = computed(() => {
  const baseClasses = 'text-xs font-medium'

  if (trendIsPositive.value) {
    return `${baseClasses} text-green-600`
  }
  if (trendDirection.value === 'down') {
    return `${baseClasses} text-red-600`
  }
  return `${baseClasses} text-neutral-500 dark:text-neutral-300`
})

// Progress value classes
const progressValueClasses = computed(() => {
  const progress = props.progress || 0
  if (progress >= 100) return 'text-green-600 font-medium'
  if (progress >= 75) return 'text-neutral-900 dark:text-white font-medium'
  return 'text-neutral-500 dark:text-neutral-300'
})

// Progress bar classes
const progressBarClasses = computed(() => {
  return 'h-full rounded-full transition-all duration-300 bg-neutral-600'
})

// Sparkline calculations
const sparklinePoints = computed(() => {
  if (!props.sparklineData || props.sparklineData.length === 0) return []

  const min = Math.min(...props.sparklineData)
  const max = Math.max(...props.sparklineData)
  const range = max - min || 1

  return props.sparklineData.map(val => {
    const normalized = (val - min) / range
    return 28 - (normalized * 24) // 28 is bottom, 4 is top (with padding)
  })
})

const sparklineLinePath = computed(() => {
  if (sparklinePoints.value.length === 0) return ''

  return sparklinePoints.value
    .map((y, i) => `${i === 0 ? 'M' : 'L'} ${i * 10} ${y}`)
    .join(' ')
})

const sparklineAreaPath = computed(() => {
  if (sparklinePoints.value.length === 0) return ''

  const linePath = sparklinePoints.value
    .map((y, i) => `${i === 0 ? 'M' : 'L'} ${i * 10} ${y}`)
    .join(' ')

  const lastX = (sparklinePoints.value.length - 1) * 10
  return `${linePath} L ${lastX} 32 L 0 32 Z`
})

const sparklineLineColor = computed(() => {
  // Color based on trend or last vs first value
  if (props.sparklineData && props.sparklineData.length >= 2) {
    const first = props.sparklineData[0]
    const last = props.sparklineData[props.sparklineData.length - 1]
    return last >= first ? '#16a34a' : '#dc2626' // green-600 or red-600
  }
  return '#6b7280' // neutral-500 default
})

const sparklineGradientStart = computed(() => sparklineLineColor.value)

// Handlers
const handleClick = () => {
  if ((props.interactive || props.href) && !props.disabled) {
    emit('click', props.value)
  }
}

const handleAction = () => {
  emit('action')
}
</script>

<style scoped>
/* Value change transition */
.value-change-enter-active {
  transition: all 0.15s ease-out;
}

.value-change-leave-active {
  transition: all 0.1s ease-out;
}

.value-change-enter-from {
  opacity: 0;
  transform: translateY(-4px);
}

.value-change-leave-to {
  opacity: 0;
  transform: translateY(4px);
}
</style>
