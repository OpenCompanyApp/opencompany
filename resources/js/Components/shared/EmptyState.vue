<template>
  <div :class="containerClasses">
    <!-- Decorative Background -->
    <div v-if="decorative" :class="decorativeClasses" />

    <!-- Illustration / Icon -->
    <div :class="illustrationContainerClasses">
      <!-- Custom illustration slot -->
      <slot name="illustration">
        <!-- Icon Container -->
        <div :class="iconContainerClasses">
          <!-- Icon wrapper -->
          <div :class="iconWrapperClasses">
            <Icon :name="icon" :class="iconClasses" />
          </div>
        </div>
      </slot>
    </div>

    <!-- Content -->
    <div :class="contentClasses">
      <!-- Title -->
      <h3 :class="titleClasses">
        {{ title }}
      </h3>

      <!-- Description -->
      <p v-if="description" :class="descriptionClasses">
        {{ description }}
      </p>

      <!-- Custom content slot -->
      <slot name="content" />
    </div>

    <!-- Actions -->
    <div v-if="action || secondaryAction || $slots.actions" :class="actionsClasses">
      <slot name="actions">
        <!-- Primary Action -->
        <Button
          v-if="action"
          :color="getButtonColor(action.variant || 'primary')"
          :variant="getButtonVariant(action.variant || 'primary')"
          :size="buttonSize"
          :icon="action.icon"
          :loading="action.loading"
          :disabled="action.disabled"
          @click="handleAction"
        >
          {{ action.label }}
        </Button>

        <!-- Secondary Action -->
        <Button
          v-if="secondaryAction"
          :color="getButtonColor(secondaryAction.variant || 'ghost')"
          :variant="getButtonVariant(secondaryAction.variant || 'ghost')"
          :size="buttonSize"
          :icon="secondaryAction.icon"
          :loading="secondaryAction.loading"
          :disabled="secondaryAction.disabled"
          @click="handleSecondaryAction"
        >
          {{ secondaryAction.label }}
        </Button>
      </slot>
    </div>

    <!-- Help Link -->
    <div v-if="helpLink" class="mt-4">
      <a
        :href="helpLink.url"
        target="_blank"
        rel="noopener noreferrer"
        class="group/help inline-flex items-center gap-1.5 text-sm text-neutral-500 dark:text-neutral-300 hover:text-neutral-700 dark:hover:text-neutral-200 transition-colors duration-150"
      >
        <Icon name="ph:question" class="w-4 h-4" />
        <span class="underline underline-offset-2">
          {{ helpLink.label }}
        </span>
        <Icon name="ph:arrow-up-right" class="w-3 h-3 opacity-50 group-hover/help:opacity-100" />
      </a>
    </div>

    <!-- Retry / Refresh Button -->
    <Button
      v-if="showRetry"
      color="neutral"
      variant="outline"
      size="sm"
      :disabled="retrying"
      class="mt-4"
      @click="handleRetry"
    >
      <Icon
        name="ph:arrow-clockwise"
        :class="[
          'w-4 h-4',
          retrying && 'animate-spin',
        ]"
      />
      <span>{{ retrying ? 'Retrying...' : 'Retry' }}</span>
    </Button>

    <!-- Additional slot for custom footer -->
    <slot name="footer" />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import Button from '@/Components/shared/Button.vue'

type EmptyStateSize = 'xs' | 'sm' | 'md' | 'lg' | 'xl'
type EmptyStateVariant = 'default' | 'minimal' | 'card' | 'bordered'
type EmptyStateColor = 'default' | 'primary' | 'success' | 'warning' | 'error' | 'info'

interface EmptyStateAction {
  label: string
  icon?: string
  variant?: 'primary' | 'secondary' | 'ghost' | 'danger'
  onClick?: () => void
  loading?: boolean
  disabled?: boolean
}

interface EmptyStateHelpLink {
  label: string
  url: string
}

const props = withDefaults(defineProps<{
  // Core
  icon?: string
  title: string
  description?: string

  // Appearance
  size?: EmptyStateSize
  variant?: EmptyStateVariant
  color?: EmptyStateColor
  centered?: boolean
  fullHeight?: boolean

  // Actions
  action?: EmptyStateAction
  secondaryAction?: EmptyStateAction
  helpLink?: EmptyStateHelpLink

  // Retry
  showRetry?: boolean
  retrying?: boolean

  // Visual
  animated?: boolean
  decorative?: boolean
  compact?: boolean
}>(), {
  icon: 'ph:ghost',
  size: 'md',
  variant: 'default',
  color: 'default',
  centered: true,
  fullHeight: false,
  showRetry: false,
  retrying: false,
  animated: true,
  decorative: false,
  compact: false,
})

const emit = defineEmits<{
  action: []
  secondaryAction: []
  retry: []
}>()

// Size configurations
const sizeConfig: Record<EmptyStateSize, {
  padding: string
  iconContainer: string
  iconWrapper: string
  icon: string
  title: string
  description: string
  maxWidth: string
  gap: string
}> = {
  xs: {
    padding: 'py-4 px-3',
    iconContainer: 'mb-2',
    iconWrapper: 'w-8 h-8',
    icon: 'w-4 h-4',
    title: 'text-sm',
    description: 'text-xs max-w-40',
    maxWidth: 'max-w-48',
    gap: 'gap-2',
  },
  sm: {
    padding: 'py-6 px-4',
    iconContainer: 'mb-3',
    iconWrapper: 'w-10 h-10',
    icon: 'w-5 h-5',
    title: 'text-sm',
    description: 'text-xs max-w-48',
    maxWidth: 'max-w-56',
    gap: 'gap-2',
  },
  md: {
    padding: 'py-10 px-6',
    iconContainer: 'mb-4',
    iconWrapper: 'w-14 h-14',
    icon: 'w-7 h-7',
    title: 'text-base',
    description: 'text-sm max-w-64',
    maxWidth: 'max-w-80',
    gap: 'gap-3',
  },
  lg: {
    padding: 'py-16 px-8',
    iconContainer: 'mb-5',
    iconWrapper: 'w-20 h-20',
    icon: 'w-10 h-10',
    title: 'text-lg',
    description: 'text-base max-w-80',
    maxWidth: 'max-w-96',
    gap: 'gap-4',
  },
  xl: {
    padding: 'py-20 px-10',
    iconContainer: 'mb-6',
    iconWrapper: 'w-24 h-24',
    icon: 'w-12 h-12',
    title: 'text-xl',
    description: 'text-lg max-w-[28rem]',
    maxWidth: 'max-w-[32rem]',
    gap: 'gap-4',
  },
}

// Color classes - neutral palette
const colorClasses: Record<EmptyStateColor, {
  iconBg: string
  iconText: string
  border: string
}> = {
  default: {
    iconBg: 'bg-neutral-100 dark:bg-neutral-700',
    iconText: 'text-neutral-400 dark:text-neutral-400',
    border: 'border-neutral-200 dark:border-neutral-700',
  },
  primary: {
    iconBg: 'bg-neutral-100 dark:bg-neutral-700',
    iconText: 'text-neutral-500 dark:text-neutral-300',
    border: 'border-neutral-300 dark:border-neutral-600',
  },
  success: {
    iconBg: 'bg-green-50',
    iconText: 'text-green-600',
    border: 'border-green-200',
  },
  warning: {
    iconBg: 'bg-amber-50',
    iconText: 'text-amber-600',
    border: 'border-amber-200',
  },
  error: {
    iconBg: 'bg-red-50',
    iconText: 'text-red-600',
    border: 'border-red-200',
  },
  info: {
    iconBg: 'bg-blue-50',
    iconText: 'text-blue-600',
    border: 'border-blue-200',
  },
}

// Button size mapping
const buttonSize = computed(() => {
  if (props.size === 'xs' || props.size === 'sm') return 'sm'
  if (props.size === 'lg' || props.size === 'xl') return 'lg'
  return 'md'
})

// Container classes
const containerClasses = computed(() => {
  const classes = [
    'relative flex flex-col group/empty',
    props.centered && 'items-center justify-center text-center',
    props.fullHeight && 'min-h-[300px]',
    !props.compact && sizeConfig[props.size].padding,
  ]

  // Variant-specific styling
  switch (props.variant) {
    case 'card':
      classes.push(
        'bg-white dark:bg-neutral-900 rounded-lg border border-neutral-200 dark:border-neutral-700'
      )
      break
    case 'bordered':
      classes.push(
        'rounded-lg border-2 border-dashed',
        colorClasses[props.color].border
      )
      break
    case 'minimal':
      // No additional styling
      break
    default:
      // Default styling
      break
  }

  return classes
})

// Decorative background classes
const decorativeClasses = computed(() => [
  'absolute inset-0 pointer-events-none overflow-hidden rounded-lg',
])

// Illustration container classes
const illustrationContainerClasses = computed(() => [
  'relative',
  sizeConfig[props.size].iconContainer,
])

// Icon container classes
const iconContainerClasses = computed(() => [
  'relative flex items-center justify-center',
])

// Icon wrapper classes
const iconWrapperClasses = computed(() => [
  'relative z-10 rounded-lg flex items-center justify-center',
  sizeConfig[props.size].iconWrapper,
  colorClasses[props.color].iconBg,
])

// Icon classes
const iconClasses = computed(() => [
  sizeConfig[props.size].icon,
  colorClasses[props.color].iconText,
])

// Content classes
const contentClasses = computed(() => [
  sizeConfig[props.size].maxWidth,
])

// Title classes
const titleClasses = computed(() => [
  'font-medium text-neutral-900 dark:text-white',
  sizeConfig[props.size].title,
])

// Description classes
const descriptionClasses = computed(() => [
  'text-neutral-500 dark:text-neutral-300 mt-1.5 leading-relaxed',
  sizeConfig[props.size].description,
])

// Actions classes
const actionsClasses = computed(() => [
  'flex flex-wrap mt-5',
  props.centered && 'justify-center',
  sizeConfig[props.size].gap,
])

// Retry button classes
const retryButtonClasses = computed(() => [
  'group/retry inline-flex items-center gap-2 mt-4 px-3 py-1.5 rounded-md text-sm',
  'text-neutral-500 dark:text-neutral-300 hover:text-neutral-700 dark:hover:text-neutral-200',
  'bg-transparent hover:bg-neutral-100 dark:hover:bg-neutral-700',
  'border border-neutral-200 dark:border-neutral-700',
  'transition-colors duration-150',
  'focus:outline-none focus-visible:ring-1 focus-visible:ring-neutral-400',
  'disabled:opacity-50 disabled:cursor-not-allowed',
])

// Event handlers
const handleAction = () => {
  if (props.action?.onClick) {
    props.action.onClick()
  }
  emit('action')
}

const handleSecondaryAction = () => {
  if (props.secondaryAction?.onClick) {
    props.secondaryAction.onClick()
  }
  emit('secondaryAction')
}

const handleRetry = () => {
  emit('retry')
}

// Button variant mapping
const getButtonColor = (variant: string) => {
  if (variant === 'danger') return 'error'
  if (variant === 'primary') return 'primary'
  return 'neutral'
}

const getButtonVariant = (variant: string) => {
  if (variant === 'ghost') return 'ghost'
  if (variant === 'secondary') return 'outline'
  return 'solid'
}
</script>

