<template>
  <component
    :is="interactive ? 'button' : href ? resolveComponent('NuxtLink') : 'div'"
    :to="href"
    :type="interactive && !href ? 'button' : undefined"
    :class="cardClasses"
    :style="cardStyles"
    @click="handleClick"
    @mouseenter="isHovered = true"
    @mouseleave="isHovered = false"
  >
    <!-- Loading State -->
    <template v-if="loading">
      <div class="absolute inset-0 flex items-center justify-center bg-olympus-surface/80 rounded-xl backdrop-blur-sm z-10">
        <Icon name="ph:spinner" class="w-6 h-6 animate-spin text-olympus-text-muted" />
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
            <Badge v-if="badge" :variant="badgeVariant" size="xs">{{ badge }}</Badge>
          </div>
          <p v-if="description" class="text-olympus-text-muted text-xs mt-0.5 line-clamp-1">
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
          <span v-if="prefix" class="text-olympus-text-muted mr-0.5">{{ prefix }}</span>
          <Transition
            v-if="animated"
            name="value-change"
            mode="out-in"
          >
            <span :key="formattedValue" class="tabular-nums">{{ formattedValue }}</span>
          </Transition>
          <span v-else class="tabular-nums">{{ formattedValue }}</span>
          <span v-if="suffix" class="text-olympus-text-muted ml-0.5 text-[0.65em]">{{ suffix }}</span>
        </span>
        <span v-if="subValue" class="text-sm text-olympus-text-muted">{{ subValue }}</span>
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
        <span v-if="trendLabel" class="text-olympus-text-muted text-xs">
          {{ trendLabel }}
        </span>
      </div>

      <!-- Sparkline Chart -->
      <div v-if="sparklineData && sparklineData.length > 0" class="mt-3 pt-3 border-t border-olympus-border/50">
        <svg
          class="w-full h-8"
          :viewBox="`0 0 ${sparklineData.length * 10} 32`"
          preserveAspectRatio="none"
        >
          <defs>
            <linearGradient :id="`sparkline-gradient-${uid}`" x1="0%" y1="0%" x2="0%" y2="100%">
              <stop offset="0%" :stop-color="sparklineGradientStart" stop-opacity="0.3" />
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
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
          />
          <!-- End dot -->
          <circle
            :cx="(sparklineData.length - 1) * 10"
            :cy="sparklinePoints[sparklinePoints.length - 1]"
            r="3"
            :fill="sparklineLineColor"
          />
        </svg>
      </div>

      <!-- Progress Bar -->
      <div v-if="progress !== undefined" class="mt-3 pt-3 border-t border-olympus-border/50">
        <div class="flex items-center justify-between text-xs mb-1.5">
          <span class="text-olympus-text-muted">{{ progressLabel || 'Progress' }}</span>
          <span :class="progressValueClasses">{{ progress }}%</span>
        </div>
        <div class="h-1.5 bg-olympus-elevated rounded-full overflow-hidden">
          <div
            :class="progressBarClasses"
            :style="{ width: `${Math.min(Math.max(progress, 0), 100)}%` }"
          />
        </div>
      </div>

      <!-- Footer / Actions -->
      <div v-if="$slots.footer || action" class="mt-3 pt-3 border-t border-olympus-border/50 flex items-center justify-between">
        <slot name="footer" />
        <button
          v-if="action"
          type="button"
          class="text-xs text-olympus-primary hover:text-olympus-primary/80 font-medium transition-colors flex items-center gap-1 group"
          @click.stop="handleAction"
        >
          {{ action }}
          <Icon name="ph:arrow-right" class="w-3 h-3 transition-transform group-hover:translate-x-0.5" />
        </button>
      </div>

      <!-- Comparison -->
      <div v-if="comparison" class="mt-3 pt-3 border-t border-olympus-border/50">
        <div class="flex items-center justify-between text-xs">
          <span class="text-olympus-text-muted">{{ comparison.label }}</span>
          <span class="text-olympus-text font-medium">{{ formatValue(comparison.value) }}</span>
        </div>
      </div>
    </div>

    <!-- Decorative Elements -->
    <div v-if="decorative" :class="decorativeClasses" />

    <!-- Hover Indicator -->
    <div
      v-if="interactive || href"
      :class="[
        'absolute top-3 right-3 opacity-0 transition-opacity duration-200',
        (isHovered || focused) && 'opacity-100',
      ]"
    >
      <Icon name="ph:arrow-up-right" class="w-4 h-4 text-olympus-text-muted" />
    </div>
  </component>
</template>

<script setup lang="ts">
type StatCardSize = 'sm' | 'md' | 'lg'
type StatCardVariant = 'default' | 'elevated' | 'outlined' | 'glass' | 'gradient' | 'colored'
type TrendDirection = 'up' | 'down' | 'neutral'
type IconColor = 'primary' | 'success' | 'warning' | 'error' | 'info' | 'purple' | 'pink' | 'cyan'

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
  iconColor?: IconColor

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
  progressColor?: IconColor

  // Comparison
  comparison?: ComparisonData

  // Badge
  badge?: string
  badgeVariant?: 'default' | 'primary' | 'success' | 'warning' | 'error'

  // Appearance
  size?: StatCardSize
  variant?: StatCardVariant
  colorAccent?: IconColor

  // Interactive
  interactive?: boolean
  href?: string
  action?: string
  disabled?: boolean

  // Visual
  decorative?: boolean
  animated?: boolean
  glow?: boolean

  // Loading
  loading?: boolean
}>(), {
  size: 'md',
  variant: 'default',
  iconColor: 'primary',
  precision: 0,
  compact: false,
  inverseTrend: false,
  badgeVariant: 'default',
  interactive: false,
  disabled: false,
  decorative: false,
  animated: true,
  glow: false,
  loading: false,
})

const emit = defineEmits<{
  click: [value: string | number]
  action: []
}>()

const isHovered = ref(false)
const focused = ref(false)

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

// Icon colors
const iconColorClasses: Record<IconColor, { bg: string; text: string; glow: string }> = {
  primary: { bg: 'bg-olympus-primary/20', text: 'text-olympus-primary', glow: 'shadow-olympus-primary/30' },
  success: { bg: 'bg-green-500/20', text: 'text-green-400', glow: 'shadow-green-500/30' },
  warning: { bg: 'bg-amber-500/20', text: 'text-amber-400', glow: 'shadow-amber-500/30' },
  error: { bg: 'bg-red-500/20', text: 'text-red-400', glow: 'shadow-red-500/30' },
  info: { bg: 'bg-blue-500/20', text: 'text-blue-400', glow: 'shadow-blue-500/30' },
  purple: { bg: 'bg-purple-500/20', text: 'text-purple-400', glow: 'shadow-purple-500/30' },
  pink: { bg: 'bg-pink-500/20', text: 'text-pink-400', glow: 'shadow-pink-500/30' },
  cyan: { bg: 'bg-cyan-500/20', text: 'text-cyan-400', glow: 'shadow-cyan-500/30' },
}

// Variant classes
const variantClasses: Record<StatCardVariant, string> = {
  default: 'bg-olympus-surface',
  elevated: 'bg-olympus-elevated shadow-lg',
  outlined: 'bg-transparent border border-olympus-border',
  glass: 'bg-olympus-surface/60 backdrop-blur-xl border border-white/10',
  gradient: 'bg-gradient-to-br from-olympus-surface to-olympus-elevated',
  colored: 'bg-gradient-to-br',
}

// Card classes
const cardClasses = computed(() => {
  const classes: string[] = [
    'relative rounded-xl transition-all duration-200 group overflow-hidden',
    sizeConfig[props.size].padding,
    variantClasses[props.variant],
  ]

  // Colored variant background
  if (props.variant === 'colored' && props.colorAccent) {
    const colorGradients: Record<IconColor, string> = {
      primary: 'from-olympus-primary/10 to-olympus-primary/5',
      success: 'from-green-500/10 to-green-500/5',
      warning: 'from-amber-500/10 to-amber-500/5',
      error: 'from-red-500/10 to-red-500/5',
      info: 'from-blue-500/10 to-blue-500/5',
      purple: 'from-purple-500/10 to-purple-500/5',
      pink: 'from-pink-500/10 to-pink-500/5',
      cyan: 'from-cyan-500/10 to-cyan-500/5',
    }
    classes.push(colorGradients[props.colorAccent])
  }

  // Interactive styles
  if ((props.interactive || props.href) && !props.disabled) {
    classes.push(
      'cursor-pointer',
      'hover:shadow-lg hover:scale-[1.02]',
      'focus:outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50 focus-visible:ring-offset-2 focus-visible:ring-offset-olympus-bg'
    )
  }

  // Disabled
  if (props.disabled) {
    classes.push('opacity-50 cursor-not-allowed')
  }

  // Glow
  if (props.glow && props.iconColor) {
    classes.push('shadow-lg', iconColorClasses[props.iconColor].glow)
  }

  return classes
})

// Card styles
const cardStyles = computed(() => {
  const styles: Record<string, string> = {}
  return styles
})

// Label classes
const labelClasses = computed(() => [
  'text-olympus-text-muted font-medium',
  sizeConfig[props.size].label,
])

// Value classes
const valueClasses = computed(() => [
  'font-bold tracking-tight text-olympus-text',
  sizeConfig[props.size].value,
])

// Icon container classes
const iconContainerClasses = computed(() => {
  const classes = [
    'rounded-lg flex items-center justify-center transition-all duration-200',
    sizeConfig[props.size].icon,
    iconColorClasses[props.iconColor].bg,
  ]

  if (isHovered.value && (props.interactive || props.href)) {
    classes.push('scale-110')
  }

  return classes
})

// Icon classes
const iconClasses = computed(() => [
  sizeConfig[props.size].iconInner,
  iconColorClasses[props.iconColor].text,
])

// Trend container classes
const trendContainerClasses = computed(() => [
  'mt-2 flex items-center gap-2 flex-wrap',
])

// Trend icon container classes
const trendIconContainerClasses = computed(() => {
  const baseClasses = 'w-5 h-5 rounded flex items-center justify-center'

  if (trendIsPositive.value) {
    return `${baseClasses} bg-green-500/20`
  }
  if (trendDirection.value === 'down') {
    return `${baseClasses} bg-red-500/20`
  }
  return `${baseClasses} bg-gray-500/20`
})

// Trend icon classes
const trendIconClasses = computed(() => {
  const baseClasses = 'w-3.5 h-3.5'

  if (trendIsPositive.value) {
    return `${baseClasses} text-green-400`
  }
  if (trendDirection.value === 'down') {
    return `${baseClasses} text-red-400`
  }
  return `${baseClasses} text-gray-400`
})

// Trend value classes
const trendValueClasses = computed(() => {
  const baseClasses = 'text-xs font-medium'

  if (trendIsPositive.value) {
    return `${baseClasses} text-green-400`
  }
  if (trendDirection.value === 'down') {
    return `${baseClasses} text-red-400`
  }
  return `${baseClasses} text-gray-400`
})

// Progress value classes
const progressValueClasses = computed(() => {
  const progress = props.progress || 0
  if (progress >= 100) return 'text-green-400 font-medium'
  if (progress >= 75) return 'text-olympus-text font-medium'
  return 'text-olympus-text-muted'
})

// Progress bar classes
const progressBarClasses = computed(() => {
  const color = props.progressColor || props.iconColor || 'primary'
  const colorMap: Record<IconColor, string> = {
    primary: 'bg-olympus-primary',
    success: 'bg-green-500',
    warning: 'bg-amber-500',
    error: 'bg-red-500',
    info: 'bg-blue-500',
    purple: 'bg-purple-500',
    pink: 'bg-pink-500',
    cyan: 'bg-cyan-500',
  }

  return `h-full rounded-full transition-all duration-500 ${colorMap[color]}`
})

// Decorative classes
const decorativeClasses = computed(() => {
  const color = props.iconColor || 'primary'
  const colorMap: Record<IconColor, string> = {
    primary: 'from-olympus-primary/20',
    success: 'from-green-500/20',
    warning: 'from-amber-500/20',
    error: 'from-red-500/20',
    info: 'from-blue-500/20',
    purple: 'from-purple-500/20',
    pink: 'from-pink-500/20',
    cyan: 'from-cyan-500/20',
  }

  return `absolute -top-12 -right-12 w-32 h-32 rounded-full bg-gradient-to-br ${colorMap[color]} to-transparent blur-2xl opacity-50 pointer-events-none`
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
    return last >= first ? '#22c55e' : '#ef4444' // green-500 or red-500
  }
  return '#6366f1' // indigo-500 default
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
.value-change-enter-active,
.value-change-leave-active {
  transition: all 0.3s ease;
}

.value-change-enter-from {
  opacity: 0;
  transform: translateY(-10px);
}

.value-change-leave-to {
  opacity: 0;
  transform: translateY(10px);
}

/* Card gradient overlay on hover */
.card-gradient::before {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(
    135deg,
    transparent 0%,
    oklch(var(--color-olympus-primary) / 0.03) 100%
  );
  opacity: 0;
  transition: opacity 0.3s ease;
  pointer-events: none;
  border-radius: inherit;
}

.group:hover.card-gradient::before {
  opacity: 1;
}
</style>
