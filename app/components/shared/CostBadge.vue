<template>
  <TooltipProvider v-if="showTooltip" :delay-duration="tooltipDelay">
    <TooltipRoot>
      <TooltipTrigger as-child>
        <component
          :is="interactive ? 'button' : 'span'"
          :type="interactive ? 'button' : undefined"
          :class="badgeClasses"
          :style="badgeStyles"
          @click="handleClick"
          @mouseenter="isHovered = true"
          @mouseleave="isHovered = false"
        >
          <BadgeContent />
        </component>
      </TooltipTrigger>
      <TooltipPortal>
        <TooltipContent
          :side="tooltipSide"
          :side-offset="5"
          class="z-50 bg-olympus-elevated border border-olympus-border rounded-lg px-3 py-2 shadow-xl max-w-xs animate-in fade-in-0 zoom-in-95 duration-150"
        >
          <div class="space-y-2">
            <div class="flex items-center gap-2">
              <Icon :name="getIcon" class="w-4 h-4 text-olympus-text-muted" />
              <p class="font-medium text-olympus-text text-sm">
                {{ variant === 'estimated' ? 'Estimated Cost' : 'Actual Cost' }}
              </p>
            </div>
            <p class="text-olympus-text text-lg font-semibold">
              {{ formattedCost }}
            </p>
            <div v-if="breakdown && breakdown.length > 0" class="pt-2 border-t border-olympus-border space-y-1">
              <p class="text-olympus-text-muted text-xs font-medium uppercase tracking-wider mb-1.5">Breakdown</p>
              <div
                v-for="item in breakdown"
                :key="item.label"
                class="flex items-center justify-between text-xs"
              >
                <span class="text-olympus-text-muted">{{ item.label }}</span>
                <span class="text-olympus-text font-medium">{{ formatCurrency(item.value) }}</span>
              </div>
            </div>
            <div v-if="budget" class="pt-2 border-t border-olympus-border">
              <div class="flex items-center justify-between text-xs mb-1">
                <span class="text-olympus-text-muted">Budget</span>
                <span :class="['font-medium', budgetPercentage > 90 ? 'text-red-400' : budgetPercentage > 70 ? 'text-amber-400' : 'text-green-400']">
                  {{ budgetPercentage.toFixed(0) }}% used
                </span>
              </div>
              <div class="h-1.5 bg-olympus-surface rounded-full overflow-hidden">
                <div
                  :class="['h-full rounded-full transition-all duration-300', budgetBarColor]"
                  :style="{ width: `${Math.min(budgetPercentage, 100)}%` }"
                />
              </div>
            </div>
            <p v-if="timestamp" class="text-olympus-text-muted text-xs pt-1 border-t border-olympus-border">
              {{ timestamp }}
            </p>
          </div>
          <TooltipArrow class="fill-olympus-elevated" />
        </TooltipContent>
      </TooltipPortal>
    </TooltipRoot>
  </TooltipProvider>

  <component
    v-else
    :is="interactive ? 'button' : 'span'"
    :type="interactive ? 'button' : undefined"
    :class="badgeClasses"
    :style="badgeStyles"
    @click="handleClick"
    @mouseenter="isHovered = true"
    @mouseleave="isHovered = false"
  >
    <BadgeContent />
  </component>
</template>

<script setup lang="ts">
import {
  TooltipArrow,
  TooltipContent,
  TooltipPortal,
  TooltipProvider,
  TooltipRoot,
  TooltipTrigger,
} from 'reka-ui'

type CostBadgeSize = 'xs' | 'sm' | 'md' | 'lg' | 'xl'
type CostBadgeVariant = 'actual' | 'estimated' | 'budget' | 'savings'
type CostBadgeStyle = 'default' | 'soft' | 'outline' | 'ghost' | 'filled'
type TooltipSide = 'top' | 'right' | 'bottom' | 'left'
type CurrencyFormat = 'usd' | 'eur' | 'gbp' | 'credits'

interface CostBreakdownItem {
  label: string
  value: number
}

const props = withDefaults(defineProps<{
  cost: number
  size?: CostBadgeSize
  variant?: CostBadgeVariant
  style?: CostBadgeStyle

  // Currency
  currency?: CurrencyFormat
  showCurrency?: boolean
  precision?: number

  // Icon
  showIcon?: boolean
  customIcon?: string

  // Trend/Change
  change?: number
  showChange?: boolean

  // Budget
  budget?: number
  showBudgetIndicator?: boolean

  // Breakdown (for tooltip)
  breakdown?: CostBreakdownItem[]

  // Interactive
  interactive?: boolean
  disabled?: boolean

  // Tooltip
  showTooltip?: boolean
  tooltipSide?: TooltipSide
  tooltipDelay?: number
  timestamp?: string

  // Visual
  animated?: boolean
  glow?: boolean
  compact?: boolean

  // Loading
  loading?: boolean
}>(), {
  size: 'sm',
  variant: 'actual',
  style: 'default',
  currency: 'usd',
  showCurrency: true,
  precision: 2,
  showIcon: true,
  showChange: true,
  showBudgetIndicator: false,
  interactive: false,
  disabled: false,
  showTooltip: true,
  tooltipSide: 'top',
  tooltipDelay: 300,
  animated: true,
  glow: false,
  compact: false,
  loading: false,
})

const emit = defineEmits<{
  click: [cost: number]
}>()

const isHovered = ref(false)

// Currency symbols
const currencySymbols: Record<CurrencyFormat, string> = {
  usd: '$',
  eur: '€',
  gbp: '£',
  credits: '',
}

// Currency suffixes
const currencySuffixes: Record<CurrencyFormat, string> = {
  usd: '',
  eur: '',
  gbp: '',
  credits: ' credits',
}

// Format currency
const formatCurrency = (value: number): string => {
  const symbol = currencySymbols[props.currency]
  const suffix = currencySuffixes[props.currency]
  const formatted = value.toFixed(props.precision)

  if (props.currency === 'credits') {
    return `${formatted}${suffix}`
  }

  return `${symbol}${formatted}${suffix}`
}

// Formatted cost
const formattedCost = computed(() => {
  const prefix = props.variant === 'estimated' ? '~' : ''
  return `${prefix}${formatCurrency(props.cost)}`
})

// Budget percentage
const budgetPercentage = computed(() => {
  if (!props.budget || props.budget <= 0) return 0
  return (props.cost / props.budget) * 100
})

// Budget bar color
const budgetBarColor = computed(() => {
  if (budgetPercentage.value > 90) return 'bg-red-500'
  if (budgetPercentage.value > 70) return 'bg-amber-500'
  return 'bg-green-500'
})

// Size classes
const sizeClasses: Record<CostBadgeSize, string> = {
  xs: 'text-[10px] gap-0.5',
  sm: 'text-xs gap-1',
  md: 'text-sm gap-1.5',
  lg: 'text-base gap-1.5',
  xl: 'text-lg gap-2',
}

// Icon sizes
const iconSizes: Record<CostBadgeSize, string> = {
  xs: 'w-2.5 h-2.5',
  sm: 'w-3 h-3',
  md: 'w-3.5 h-3.5',
  lg: 'w-4 h-4',
  xl: 'w-5 h-5',
}

// Padding classes (for non-compact)
const paddingClasses: Record<CostBadgeSize, string> = {
  xs: 'px-1 py-0.5',
  sm: 'px-1.5 py-0.5',
  md: 'px-2 py-1',
  lg: 'px-2.5 py-1',
  xl: 'px-3 py-1.5',
}

// Style + Variant combinations
const styleVariantClasses: Record<CostBadgeStyle, Record<CostBadgeVariant, string>> = {
  default: {
    actual: 'text-olympus-text',
    estimated: 'text-olympus-text-muted',
    budget: 'text-amber-400',
    savings: 'text-green-400',
  },
  soft: {
    actual: 'bg-olympus-surface/80 text-olympus-text',
    estimated: 'bg-olympus-surface/60 text-olympus-text-muted',
    budget: 'bg-amber-500/20 text-amber-400',
    savings: 'bg-green-500/20 text-green-400',
  },
  outline: {
    actual: 'border border-olympus-border text-olympus-text',
    estimated: 'border border-olympus-border/50 text-olympus-text-muted',
    budget: 'border border-amber-500/50 text-amber-400',
    savings: 'border border-green-500/50 text-green-400',
  },
  ghost: {
    actual: 'text-olympus-text hover:bg-olympus-surface/50',
    estimated: 'text-olympus-text-muted hover:bg-olympus-surface/50',
    budget: 'text-amber-400 hover:bg-amber-500/10',
    savings: 'text-green-400 hover:bg-green-500/10',
  },
  filled: {
    actual: 'bg-olympus-primary text-white',
    estimated: 'bg-gray-600 text-gray-200',
    budget: 'bg-amber-500 text-white',
    savings: 'bg-green-500 text-white',
  },
}

// Get icon
const getIcon = computed(() => {
  if (props.customIcon) return props.customIcon

  const icons: Record<CostBadgeVariant, string> = {
    actual: 'ph:coins',
    estimated: 'ph:coins',
    budget: 'ph:wallet',
    savings: 'ph:piggy-bank',
  }

  return icons[props.variant]
})

// Badge classes
const badgeClasses = computed(() => {
  const classes: string[] = [
    'inline-flex items-center font-medium transition-all duration-200',
    sizeClasses[props.size],
    styleVariantClasses[props.style][props.variant],
  ]

  // Padding for non-compact
  if (!props.compact && props.style !== 'default') {
    classes.push(paddingClasses[props.size])
    classes.push('rounded-full')
  }

  // Glow
  if (props.glow) {
    if (props.variant === 'savings') {
      classes.push('shadow-lg shadow-green-500/30')
    } else if (props.variant === 'budget' && budgetPercentage.value > 90) {
      classes.push('shadow-lg shadow-red-500/30')
    }
  }

  // Interactive
  if (props.interactive && !props.disabled) {
    classes.push('cursor-pointer')
    classes.push('focus:outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50')
  }

  // Disabled
  if (props.disabled) {
    classes.push('opacity-50 cursor-not-allowed')
  }

  // Hover
  if (props.interactive && !props.disabled && isHovered.value) {
    classes.push('scale-105')
  }

  return classes
})

// Badge styles
const badgeStyles = computed(() => {
  const styles: Record<string, string> = {}
  return styles
})

// Handle click
const handleClick = () => {
  if (props.interactive && !props.disabled) {
    emit('click', props.cost)
  }
}

// Change indicator color
const changeColor = computed(() => {
  if (!props.change) return ''
  return props.change > 0 ? 'text-red-400' : 'text-green-400'
})

// Change indicator icon
const changeIcon = computed(() => {
  if (!props.change) return ''
  return props.change > 0 ? 'ph:trend-up' : 'ph:trend-down'
})

// Badge Content Component
const BadgeContent = () => {
  const elements: VNode[] = []

  // Loading state
  if (props.loading) {
    elements.push(
      h(resolveComponent('Icon'), {
        name: 'ph:spinner',
        class: ['animate-spin', iconSizes[props.size]],
      })
    )
    elements.push(
      h('span', { class: 'opacity-50' }, '...')
    )
    return elements
  }

  // Icon
  if (props.showIcon) {
    elements.push(
      h(resolveComponent('Icon'), {
        name: getIcon.value,
        class: [iconSizes[props.size], props.animated && props.variant === 'savings' && 'animate-bounce'],
      })
    )
  }

  // Cost value
  elements.push(
    h('span', { class: 'tabular-nums' }, formattedCost.value)
  )

  // Change indicator
  if (props.showChange && props.change !== undefined && props.change !== 0) {
    elements.push(
      h('span', {
        class: ['inline-flex items-center gap-0.5 ml-1', changeColor.value],
      }, [
        h(resolveComponent('Icon'), {
          name: changeIcon.value,
          class: iconSizes[props.size],
        }),
        !props.compact && h('span', { class: 'text-[0.85em]' }, `${Math.abs(props.change)}%`),
      ])
    )
  }

  // Budget indicator (small dot showing status)
  if (props.showBudgetIndicator && props.budget) {
    const indicatorColor = budgetPercentage.value > 90
      ? 'bg-red-400'
      : budgetPercentage.value > 70
        ? 'bg-amber-400'
        : 'bg-green-400'

    elements.push(
      h('span', {
        class: ['w-1.5 h-1.5 rounded-full ml-1', indicatorColor],
      })
    )
  }

  return elements
}
</script>

<style scoped>
/* Bounce animation for savings icon */
@keyframes cost-bounce {
  0%, 100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(-2px);
  }
}

.animate-bounce {
  animation: cost-bounce 1s ease-in-out infinite;
}

/* Pulse for budget warning */
@keyframes budget-pulse {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.6;
  }
}

.budget-warning {
  animation: budget-pulse 2s ease-in-out infinite;
}
</style>
