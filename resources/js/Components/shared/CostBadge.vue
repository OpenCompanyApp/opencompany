<template>
  <Popover v-if="showTooltip" mode="hover" :open-delay="tooltipDelay" :close-delay="0">
    <component
      :is="interactive ? 'button' : 'span'"
      :type="interactive ? 'button' : undefined"
      :class="badgeClasses"
      :style="badgeStyles"
      @click="handleClick"
    >
      <BadgeContent />
    </component>

    <template #content>
      <div class="space-y-2.5 p-3.5 max-w-xs">
        <div class="flex items-center gap-2">
          <div class="w-8 h-8 rounded-lg bg-neutral-100 dark:bg-neutral-700 flex items-center justify-center">
            <Icon :name="getIcon" class="w-4 h-4 text-neutral-500 dark:text-neutral-300" />
          </div>
          <div>
            <p class="font-semibold text-neutral-900 dark:text-white text-sm">
              {{ variant === 'estimated' ? 'Estimated Cost' : 'Actual Cost' }}
            </p>
            <p class="text-neutral-900 dark:text-white text-lg font-bold tabular-nums">
              {{ formattedCost }}
            </p>
          </div>
        </div>
        <div v-if="breakdown && breakdown.length > 0" class="pt-2.5 border-t border-neutral-200 dark:border-neutral-700 space-y-1.5">
          <p class="text-neutral-500 dark:text-neutral-300 text-[10px] font-medium uppercase tracking-wider mb-2">Breakdown</p>
          <div
            v-for="(item, index) in breakdown"
            :key="item.label"
            class="flex items-center justify-between text-xs hover:bg-neutral-50 dark:hover:bg-neutral-800 -mx-1 px-1 py-0.5 rounded transition-colors duration-150"
            :style="{ animationDelay: `${index * 50}ms` }"
          >
            <span class="text-neutral-500 dark:text-neutral-300">{{ item.label }}</span>
            <span class="text-neutral-900 dark:text-white font-medium tabular-nums">{{ formatCurrency(item.value) }}</span>
          </div>
        </div>
        <div v-if="budget" class="pt-2.5 border-t border-neutral-200 dark:border-neutral-700">
          <div class="flex items-center justify-between text-xs mb-1.5">
            <span class="text-neutral-500 dark:text-neutral-300">Budget</span>
            <span :class="['font-semibold', budgetPercentage > 90 ? 'text-red-600' : budgetPercentage > 70 ? 'text-amber-600' : 'text-green-600']">
              {{ budgetPercentage.toFixed(0) }}% used
            </span>
          </div>
          <div class="h-2 bg-neutral-100 dark:bg-neutral-700 rounded-full overflow-hidden">
            <div
              :class="['h-full rounded-full transition-all duration-150', budgetBarColor]"
              :style="{ width: `${Math.min(budgetPercentage, 100)}%` }"
            />
          </div>
        </div>
        <p v-if="timestamp" class="text-neutral-400 dark:text-neutral-400 text-[10px] pt-2 border-t border-neutral-200 dark:border-neutral-700 flex items-center gap-1">
          <Icon name="ph:clock" class="w-3 h-3" />
          {{ timestamp }}
        </p>
      </div>
    </template>
  </Popover>

  <component
    v-else
    :is="interactive ? 'button' : 'span'"
    :type="interactive ? 'button' : undefined"
    :class="badgeClasses"
    :style="badgeStyles"
    @click="handleClick"
  >
    <BadgeContent />
  </component>
</template>

<script setup lang="ts">
import { computed, h, type VNode } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import Popover from '@/Components/shared/Popover.vue'

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
const formatCurrency = (value: number | string | null | undefined): string => {
  const symbol = currencySymbols[props.currency]
  const suffix = currencySuffixes[props.currency]

  // Convert to number, defaulting to 0 if invalid
  const numValue = typeof value === 'number' ? value : parseFloat(String(value)) || 0
  const formatted = numValue.toFixed(props.precision)

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

// Get numeric cost value
const numericCost = computed(() => {
  return typeof props.cost === 'number' ? props.cost : parseFloat(String(props.cost)) || 0
})

// Budget percentage
const budgetPercentage = computed(() => {
  if (!props.budget || props.budget <= 0) return 0
  return (numericCost.value / props.budget) * 100
})

// Budget bar color
const budgetBarColor = computed(() => {
  if (budgetPercentage.value > 90) return 'bg-red-600'
  if (budgetPercentage.value > 70) return 'bg-amber-600'
  return 'bg-green-600'
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
    actual: 'text-neutral-900 dark:text-white',
    estimated: 'text-neutral-500 dark:text-neutral-300',
    budget: 'text-amber-600',
    savings: 'text-green-600',
  },
  soft: {
    actual: 'bg-neutral-100 dark:bg-neutral-700 text-neutral-900 dark:text-white',
    estimated: 'bg-neutral-50 dark:bg-neutral-800 text-neutral-500 dark:text-neutral-300',
    budget: 'bg-amber-50 dark:bg-amber-900/30 text-amber-600',
    savings: 'bg-green-50 dark:bg-green-900/30 text-green-600',
  },
  outline: {
    actual: 'border border-neutral-300 dark:border-neutral-600 text-neutral-900 dark:text-white',
    estimated: 'border border-neutral-200 dark:border-neutral-700 text-neutral-500 dark:text-neutral-300',
    budget: 'border border-amber-200 dark:border-amber-800 text-amber-600',
    savings: 'border border-green-200 dark:border-green-800 text-green-600',
  },
  ghost: {
    actual: 'text-neutral-900 dark:text-white hover:bg-neutral-50 dark:hover:bg-neutral-800',
    estimated: 'text-neutral-500 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-800',
    budget: 'text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/30',
    savings: 'text-green-600 hover:bg-green-50 dark:hover:bg-green-900/30',
  },
  filled: {
    actual: 'bg-neutral-900 dark:bg-neutral-100 text-white dark:text-neutral-900',
    estimated: 'bg-neutral-600 text-neutral-200',
    budget: 'bg-amber-600 text-white',
    savings: 'bg-green-600 text-white',
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
    'inline-flex items-center font-medium transition-colors duration-150',
    sizeClasses[props.size],
    styleVariantClasses[props.style][props.variant],
  ]

  // Padding for non-compact
  if (!props.compact && props.style !== 'default') {
    classes.push(paddingClasses[props.size])
    classes.push('rounded-full')
  }

  // Interactive
  if (props.interactive && !props.disabled) {
    classes.push('cursor-pointer')
    classes.push('focus:outline-none focus-visible:ring-1 focus-visible:ring-neutral-400')
  }

  // Disabled
  if (props.disabled) {
    classes.push('opacity-50 cursor-not-allowed')
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
  return props.change > 0 ? 'text-red-600' : 'text-green-600'
})

// Change indicator icon
const changeIcon = computed(() => {
  if (!props.change) return ''
  return props.change > 0 ? 'ph:trend-up' : 'ph:trend-down'
})

// Get icon class (for render function)
const getIconClass = computed(() => {
  const iconName = getIcon.value.replace(':', '-')
  return `i-${iconName}`
})

// Change icon class (for render function)
const changeIconClass = computed(() => {
  if (!props.change) return ''
  const iconName = changeIcon.value.replace(':', '-')
  return `i-${iconName}`
})

// Badge Content Component
const BadgeContent = () => {
  const elements: VNode[] = []

  // Loading state
  if (props.loading) {
    elements.push(
      h('span', {
        class: ['i-ph-spinner animate-spin', iconSizes[props.size]],
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
      h('span', {
        class: [getIconClass.value, iconSizes[props.size]],
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
        h('span', {
          class: [changeIconClass.value, iconSizes[props.size]],
        }),
        !props.compact && h('span', { class: 'text-[0.85em]' }, `${Math.abs(props.change)}%`),
      ])
    )
  }

  // Budget indicator (small dot showing status)
  if (props.showBudgetIndicator && props.budget) {
    const indicatorColor = budgetPercentage.value > 90
      ? 'bg-red-600'
      : budgetPercentage.value > 70
        ? 'bg-amber-600'
        : 'bg-green-600'

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
/* Minimal styling */
</style>
