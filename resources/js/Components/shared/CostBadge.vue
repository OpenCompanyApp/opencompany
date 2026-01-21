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
        >
          <BadgeContent />
        </component>
      </TooltipTrigger>
      <TooltipPortal>
        <TooltipContent
          :side="tooltipSide"
          :side-offset="5"
          class="z-50 bg-white border border-gray-200 rounded-lg px-3.5 py-3 shadow-md max-w-xs animate-in fade-in-0 duration-150"
        >
          <div class="space-y-2.5">
            <div class="flex items-center gap-2">
              <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center">
                <Icon :name="getIcon" class="w-4 h-4 text-gray-500" />
              </div>
              <div>
                <p class="font-semibold text-gray-900 text-sm">
                  {{ variant === 'estimated' ? 'Estimated Cost' : 'Actual Cost' }}
                </p>
                <p class="text-gray-900 text-lg font-bold tabular-nums">
                  {{ formattedCost }}
                </p>
              </div>
            </div>
            <div v-if="breakdown && breakdown.length > 0" class="pt-2.5 border-t border-gray-200 space-y-1.5">
              <p class="text-gray-500 text-[10px] font-medium uppercase tracking-wider mb-2">Breakdown</p>
              <div
                v-for="(item, index) in breakdown"
                :key="item.label"
                class="flex items-center justify-between text-xs hover:bg-gray-50 -mx-1 px-1 py-0.5 rounded transition-colors duration-150"
                :style="{ animationDelay: `${index * 50}ms` }"
              >
                <span class="text-gray-500">{{ item.label }}</span>
                <span class="text-gray-900 font-medium tabular-nums">{{ formatCurrency(item.value) }}</span>
              </div>
            </div>
            <div v-if="budget" class="pt-2.5 border-t border-gray-200">
              <div class="flex items-center justify-between text-xs mb-1.5">
                <span class="text-gray-500">Budget</span>
                <span :class="['font-semibold', budgetPercentage > 90 ? 'text-red-600' : budgetPercentage > 70 ? 'text-amber-600' : 'text-green-600']">
                  {{ budgetPercentage.toFixed(0) }}% used
                </span>
              </div>
              <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                <div
                  :class="['h-full rounded-full transition-all duration-150', budgetBarColor]"
                  :style="{ width: `${Math.min(budgetPercentage, 100)}%` }"
                />
              </div>
            </div>
            <p v-if="timestamp" class="text-gray-400 text-[10px] pt-2 border-t border-gray-200 flex items-center gap-1">
              <Icon name="ph:clock" class="w-3 h-3" />
              {{ timestamp }}
            </p>
          </div>
          <TooltipArrow class="fill-white" />
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
  >
    <BadgeContent />
  </component>
</template>

<script setup lang="ts">
import { computed, h, resolveComponent, type VNode } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
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
    actual: 'text-gray-900',
    estimated: 'text-gray-500',
    budget: 'text-amber-600',
    savings: 'text-green-600',
  },
  soft: {
    actual: 'bg-gray-100 text-gray-900',
    estimated: 'bg-gray-50 text-gray-500',
    budget: 'bg-amber-50 text-amber-600',
    savings: 'bg-green-50 text-green-600',
  },
  outline: {
    actual: 'border border-gray-300 text-gray-900',
    estimated: 'border border-gray-200 text-gray-500',
    budget: 'border border-amber-200 text-amber-600',
    savings: 'border border-green-200 text-green-600',
  },
  ghost: {
    actual: 'text-gray-900 hover:bg-gray-50',
    estimated: 'text-gray-500 hover:bg-gray-50',
    budget: 'text-amber-600 hover:bg-amber-50',
    savings: 'text-green-600 hover:bg-green-50',
  },
  filled: {
    actual: 'bg-gray-900 text-white',
    estimated: 'bg-gray-600 text-gray-200',
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
    classes.push('focus:outline-none focus-visible:ring-1 focus-visible:ring-gray-400')
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
        class: [iconSizes[props.size]],
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
