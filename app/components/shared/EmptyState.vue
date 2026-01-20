<template>
  <div :class="containerClasses">
    <!-- Decorative Background -->
    <div v-if="decorative" :class="decorativeClasses" />

    <!-- Illustration / Icon -->
    <div :class="illustrationContainerClasses">
      <!-- Custom illustration slot -->
      <slot name="illustration">
        <!-- Animated Icon Container -->
        <div :class="iconContainerClasses">
          <!-- Background decoration -->
          <div
            v-if="variant !== 'minimal'"
            :class="[
              'absolute inset-0 rounded-full opacity-30',
              animated && 'animate-ping',
              iconBgColorClasses,
            ]"
            :style="{ animationDuration: '3s' }"
          />

          <!-- Icon wrapper -->
          <div :class="iconWrapperClasses">
            <Icon :name="icon" :class="iconClasses" />
          </div>

          <!-- Floating particles (for premium variant) -->
          <template v-if="variant === 'premium'">
            <div
              v-for="i in 3"
              :key="i"
              :class="[
                'absolute w-2 h-2 rounded-full bg-olympus-primary/40',
                `particle-${i}`,
              ]"
            />
          </template>
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
        <SharedButton
          v-if="action"
          :variant="action.variant || 'primary'"
          :size="buttonSize"
          :icon-left="action.icon"
          :loading="action.loading"
          :disabled="action.disabled"
          @click="handleAction"
        >
          {{ action.label }}
        </SharedButton>

        <!-- Secondary Action -->
        <SharedButton
          v-if="secondaryAction"
          :variant="secondaryAction.variant || 'ghost'"
          :size="buttonSize"
          :icon-left="secondaryAction.icon"
          :loading="secondaryAction.loading"
          :disabled="secondaryAction.disabled"
          @click="handleSecondaryAction"
        >
          {{ secondaryAction.label }}
        </SharedButton>
      </slot>
    </div>

    <!-- Help Link -->
    <div v-if="helpLink" class="mt-4">
      <a
        :href="helpLink.url"
        target="_blank"
        rel="noopener noreferrer"
        class="inline-flex items-center gap-1.5 text-sm text-olympus-text-muted hover:text-olympus-primary transition-colors duration-200"
      >
        <Icon name="ph:question" class="w-4 h-4" />
        <span>{{ helpLink.label }}</span>
        <Icon name="ph:arrow-up-right" class="w-3 h-3 opacity-50" />
      </a>
    </div>

    <!-- Retry / Refresh Button -->
    <button
      v-if="showRetry"
      type="button"
      :class="retryButtonClasses"
      :disabled="retrying"
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
    </button>

    <!-- Additional slot for custom footer -->
    <slot name="footer" />
  </div>
</template>

<script setup lang="ts">
type EmptyStateSize = 'xs' | 'sm' | 'md' | 'lg' | 'xl'
type EmptyStateVariant = 'default' | 'minimal' | 'card' | 'bordered' | 'premium'
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

// Color classes
const colorClasses: Record<EmptyStateColor, {
  iconBg: string
  iconText: string
  border: string
}> = {
  default: {
    iconBg: 'bg-olympus-surface',
    iconText: 'text-olympus-text-subtle',
    border: 'border-olympus-border-subtle',
  },
  primary: {
    iconBg: 'bg-olympus-primary/20',
    iconText: 'text-olympus-primary',
    border: 'border-olympus-primary/30',
  },
  success: {
    iconBg: 'bg-green-500/20',
    iconText: 'text-green-400',
    border: 'border-green-500/30',
  },
  warning: {
    iconBg: 'bg-amber-500/20',
    iconText: 'text-amber-400',
    border: 'border-amber-500/30',
  },
  error: {
    iconBg: 'bg-red-500/20',
    iconText: 'text-red-400',
    border: 'border-red-500/30',
  },
  info: {
    iconBg: 'bg-blue-500/20',
    iconText: 'text-blue-400',
    border: 'border-blue-500/30',
  },
}

// Button size mapping
const buttonSize = computed(() => {
  if (props.size === 'xs' || props.size === 'sm') return 'sm'
  if (props.size === 'lg' || props.size === 'xl') return 'lg'
  return 'md'
})

// Icon bg color classes
const iconBgColorClasses = computed(() => {
  return colorClasses[props.color].iconBg.replace('/20', '/10')
})

// Container classes
const containerClasses = computed(() => {
  const classes = [
    'relative flex flex-col',
    props.centered && 'items-center justify-center text-center',
    props.fullHeight && 'min-h-[300px]',
    !props.compact && sizeConfig[props.size].padding,
  ]

  // Variant-specific styling
  switch (props.variant) {
    case 'card':
      classes.push(
        'bg-olympus-surface rounded-2xl border border-olympus-border'
      )
      break
    case 'bordered':
      classes.push(
        'rounded-2xl border-2 border-dashed',
        colorClasses[props.color].border
      )
      break
    case 'premium':
      classes.push(
        'bg-gradient-to-br from-olympus-surface to-olympus-elevated rounded-2xl border border-olympus-border shadow-xl'
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
  'absolute inset-0 pointer-events-none overflow-hidden rounded-2xl',
  'before:absolute before:top-1/2 before:left-1/2 before:-translate-x-1/2 before:-translate-y-1/2',
  'before:w-[200%] before:h-[200%] before:bg-gradient-radial before:from-olympus-primary/5 before:to-transparent',
  'before:opacity-50',
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
  'relative z-10 rounded-2xl flex items-center justify-center transition-all duration-300',
  sizeConfig[props.size].iconWrapper,
  colorClasses[props.color].iconBg,
  'border',
  colorClasses[props.color].border,
  props.animated && 'group-hover:scale-105',
])

// Icon classes
const iconClasses = computed(() => [
  sizeConfig[props.size].icon,
  colorClasses[props.color].iconText,
  props.animated && 'transition-transform duration-300 group-hover:scale-110',
])

// Content classes
const contentClasses = computed(() => [
  sizeConfig[props.size].maxWidth,
])

// Title classes
const titleClasses = computed(() => [
  'font-medium text-olympus-text',
  sizeConfig[props.size].title,
])

// Description classes
const descriptionClasses = computed(() => [
  'text-olympus-text-muted mt-1.5 leading-relaxed',
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
  'inline-flex items-center gap-2 mt-4 px-3 py-1.5 rounded-lg text-sm',
  'text-olympus-text-muted hover:text-olympus-text',
  'bg-transparent hover:bg-olympus-surface',
  'border border-transparent hover:border-olympus-border',
  'transition-all duration-200',
  'focus:outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50',
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
</script>

<style scoped>
/* Floating particle animations for premium variant */
.particle-1 {
  top: 0;
  left: 50%;
  animation: float-particle-1 4s ease-in-out infinite;
}

.particle-2 {
  bottom: 20%;
  right: 0;
  animation: float-particle-2 5s ease-in-out infinite;
}

.particle-3 {
  bottom: 0;
  left: 20%;
  animation: float-particle-3 6s ease-in-out infinite;
}

@keyframes float-particle-1 {
  0%, 100% {
    transform: translate(-50%, 0) scale(1);
    opacity: 0.4;
  }
  50% {
    transform: translate(-50%, -20px) scale(1.2);
    opacity: 0.8;
  }
}

@keyframes float-particle-2 {
  0%, 100% {
    transform: translate(0, 0) scale(1);
    opacity: 0.3;
  }
  50% {
    transform: translate(-15px, -10px) scale(1.3);
    opacity: 0.7;
  }
}

@keyframes float-particle-3 {
  0%, 100% {
    transform: translate(0, 0) scale(1);
    opacity: 0.5;
  }
  50% {
    transform: translate(10px, -15px) scale(1.1);
    opacity: 0.9;
  }
}

/* Radial gradient */
.bg-gradient-radial {
  background: radial-gradient(circle, var(--tw-gradient-from), var(--tw-gradient-to));
}
</style>
