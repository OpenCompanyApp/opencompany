<template>
  <TooltipRoot v-if="tooltip" :delay-duration="300">
    <TooltipTrigger as-child>
      <component
        :is="computedElement"
        ref="buttonRef"
        :type="computedElement === 'button' ? type : undefined"
        :href="href"
        :to="to"
        :disabled="isDisabled"
        :aria-disabled="isDisabled"
        :aria-busy="loading"
        :aria-label="ariaLabel || (iconOnly ? String(tooltip) : undefined)"
        :class="buttonClasses"
        @click="handleClick"
        @mousedown="handleMouseDown"
        @mouseup="handleMouseUp"
        @mouseleave="handleMouseLeave"
        @keydown="handleKeydown"
      >
        <!-- Animated gradient border -->
        <span v-if="gradient && !isDisabled" class="absolute inset-0 rounded-[inherit] p-px overflow-hidden">
          <span class="absolute inset-0 rounded-[inherit] bg-gradient-to-r from-olympus-primary via-purple-500 to-pink-500 animate-gradient-x" />
        </span>

        <!-- Glow effect -->
        <span
          v-if="glow && !isDisabled"
          class="absolute inset-0 rounded-[inherit] blur-md opacity-50 transition-opacity duration-300"
          :class="glowClasses"
        />

        <!-- Ripple effect container -->
        <span
          v-if="ripple && rippleStyle"
          class="absolute rounded-full bg-white/30 animate-ripple pointer-events-none"
          :style="rippleStyle"
        />

        <!-- Button content wrapper -->
        <span
          class="relative z-10 inline-flex items-center justify-center gap-2"
          :class="[
            gradient && 'bg-olympus-elevated rounded-[inherit]',
            gradient && sizeClasses[size],
          ]"
        >
          <!-- Leading icon / spinner -->
          <Transition
            enter-active-class="transition-all duration-200"
            enter-from-class="opacity-0 scale-75 -rotate-180"
            enter-to-class="opacity-100 scale-100 rotate-0"
            leave-active-class="transition-all duration-200"
            leave-from-class="opacity-100 scale-100 rotate-0"
            leave-to-class="opacity-0 scale-75 rotate-180"
            mode="out-in"
          >
            <Icon
              v-if="loading"
              key="spinner"
              :name="loadingIcon"
              class="animate-spin"
              :class="iconSizeClasses[size]"
            />
            <Icon
              v-else-if="iconLeft"
              key="icon-left"
              :name="iconLeft"
              :class="[iconSizeClasses[size], iconOnly && 'mx-0']"
            />
          </Transition>

          <!-- Button text -->
          <span
            v-if="!iconOnly"
            class="truncate"
            :class="[loading && loadingText && 'ml-0']"
          >
            <slot>{{ loading && loadingText ? loadingText : undefined }}</slot>
          </span>

          <!-- Trailing icon -->
          <Icon
            v-if="iconRight && !loading"
            :name="iconRight"
            :class="iconSizeClasses[size]"
          />

          <!-- Keyboard shortcut badge -->
          <kbd
            v-if="shortcut && !loading && !iconOnly"
            class="ml-1 px-1.5 py-0.5 text-[10px] font-mono rounded border opacity-60"
            :class="shortcutClasses"
          >
            {{ shortcut }}
          </kbd>

          <!-- Notification badge -->
          <span
            v-if="badge !== undefined"
            class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 flex items-center justify-center text-[10px] font-bold rounded-full"
            :class="badgeClasses"
          >
            {{ badge > 99 ? '99+' : badge }}
          </span>

          <!-- Dot indicator -->
          <span
            v-if="dot"
            class="absolute -top-0.5 -right-0.5 w-2.5 h-2.5 rounded-full border-2 border-olympus-bg"
            :class="dotClasses"
          />
        </span>

        <!-- Pressed overlay -->
        <span
          v-if="isPressed && !isDisabled"
          class="absolute inset-0 rounded-[inherit] bg-black/10 dark:bg-black/20"
        />
      </component>
    </TooltipTrigger>
    <TooltipPortal>
      <TooltipContent
        class="z-50 px-3 py-1.5 text-xs font-medium bg-olympus-elevated border border-olympus-border rounded-lg shadow-xl animate-in fade-in-0 zoom-in-95 duration-150"
        :side="tooltipSide"
        :side-offset="5"
      >
        {{ tooltip }}
        <span v-if="shortcut" class="ml-2 opacity-60 font-mono">{{ shortcut }}</span>
        <TooltipArrow class="fill-olympus-elevated" />
      </TooltipContent>
    </TooltipPortal>
  </TooltipRoot>

  <!-- Button without tooltip -->
  <component
    v-else
    :is="computedElement"
    ref="buttonRef"
    :type="computedElement === 'button' ? type : undefined"
    :href="href"
    :to="to"
    :disabled="isDisabled"
    :aria-disabled="isDisabled"
    :aria-busy="loading"
    :aria-label="ariaLabel"
    :class="buttonClasses"
    @click="handleClick"
    @mousedown="handleMouseDown"
    @mouseup="handleMouseUp"
    @mouseleave="handleMouseLeave"
    @keydown="handleKeydown"
  >
    <!-- Animated gradient border -->
    <span v-if="gradient && !isDisabled" class="absolute inset-0 rounded-[inherit] p-px overflow-hidden">
      <span class="absolute inset-0 rounded-[inherit] bg-gradient-to-r from-olympus-primary via-purple-500 to-pink-500 animate-gradient-x" />
    </span>

    <!-- Glow effect -->
    <span
      v-if="glow && !isDisabled"
      class="absolute inset-0 rounded-[inherit] blur-md opacity-50 transition-opacity duration-300"
      :class="glowClasses"
    />

    <!-- Ripple effect container -->
    <span
      v-if="ripple && rippleStyle"
      class="absolute rounded-full bg-white/30 animate-ripple pointer-events-none"
      :style="rippleStyle"
    />

    <!-- Button content wrapper -->
    <span
      class="relative z-10 inline-flex items-center justify-center gap-2"
      :class="[
        gradient && 'bg-olympus-elevated rounded-[inherit]',
        gradient && sizeClasses[size],
      ]"
    >
      <!-- Leading icon / spinner -->
      <Transition
        enter-active-class="transition-all duration-200"
        enter-from-class="opacity-0 scale-75 -rotate-180"
        enter-to-class="opacity-100 scale-100 rotate-0"
        leave-active-class="transition-all duration-200"
        leave-from-class="opacity-100 scale-100 rotate-0"
        leave-to-class="opacity-0 scale-75 rotate-180"
        mode="out-in"
      >
        <Icon
          v-if="loading"
          key="spinner"
          :name="loadingIcon"
          class="animate-spin"
          :class="iconSizeClasses[size]"
        />
        <Icon
          v-else-if="iconLeft"
          key="icon-left"
          :name="iconLeft"
          :class="[iconSizeClasses[size], iconOnly && 'mx-0']"
        />
      </Transition>

      <!-- Button text -->
      <span
        v-if="!iconOnly"
        class="truncate"
        :class="[loading && loadingText && 'ml-0']"
      >
        <slot>{{ loading && loadingText ? loadingText : undefined }}</slot>
      </span>

      <!-- Trailing icon -->
      <Icon
        v-if="iconRight && !loading"
        :name="iconRight"
        :class="iconSizeClasses[size]"
      />

      <!-- Keyboard shortcut badge -->
      <kbd
        v-if="shortcut && !loading && !iconOnly"
        class="ml-1 px-1.5 py-0.5 text-[10px] font-mono rounded border opacity-60"
        :class="shortcutClasses"
      >
        {{ shortcut }}
      </kbd>

      <!-- Notification badge -->
      <span
        v-if="badge !== undefined"
        class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 flex items-center justify-center text-[10px] font-bold rounded-full"
        :class="badgeClasses"
      >
        {{ badge > 99 ? '99+' : badge }}
      </span>

      <!-- Dot indicator -->
      <span
        v-if="dot"
        class="absolute -top-0.5 -right-0.5 w-2.5 h-2.5 rounded-full border-2 border-olympus-bg"
        :class="dotClasses"
      />
    </span>

    <!-- Pressed overlay -->
    <span
      v-if="isPressed && !isDisabled"
      class="absolute inset-0 rounded-[inherit] bg-black/10 dark:bg-black/20"
    />
  </component>
</template>

<script setup lang="ts">
import {
  TooltipArrow,
  TooltipContent,
  TooltipPortal,
  TooltipRoot,
  TooltipTrigger,
} from 'reka-ui'
import type { RouteLocationRaw } from 'vue-router'

// Types
type ButtonVariant = 'primary' | 'secondary' | 'ghost' | 'danger' | 'success' | 'warning' | 'info' | 'link' | 'outline' | 'premium'
type ButtonSize = 'xs' | 'sm' | 'md' | 'lg' | 'xl'
type TooltipSide = 'top' | 'right' | 'bottom' | 'left'

// Props
const props = withDefaults(defineProps<{
  // Core props
  variant?: ButtonVariant
  size?: ButtonSize
  type?: 'button' | 'submit' | 'reset'
  disabled?: boolean
  loading?: boolean
  loadingText?: string
  loadingIcon?: string

  // Icons
  iconLeft?: string
  iconRight?: string
  iconOnly?: boolean

  // Layout
  fullWidth?: boolean
  rounded?: boolean
  square?: boolean

  // Element type
  as?: 'button' | 'a' | 'div' | 'span'
  href?: string
  to?: RouteLocationRaw

  // Visual effects
  gradient?: boolean
  glow?: boolean
  ripple?: boolean
  elevated?: boolean

  // Additional features
  tooltip?: string
  tooltipSide?: TooltipSide
  shortcut?: string
  badge?: number
  dot?: boolean
  dotColor?: 'primary' | 'success' | 'warning' | 'danger' | 'info'

  // Accessibility
  ariaLabel?: string
}>(), {
  variant: 'primary',
  size: 'md',
  type: 'button',
  disabled: false,
  loading: false,
  loadingIcon: 'ph:spinner',
  iconOnly: false,
  fullWidth: false,
  rounded: false,
  square: false,
  as: 'button',
  gradient: false,
  glow: false,
  ripple: true,
  elevated: false,
  tooltipSide: 'top',
  dotColor: 'primary',
})

// Emits
const emit = defineEmits<{
  click: [event: MouseEvent]
}>()

// Refs
const buttonRef = ref<HTMLElement | null>(null)
const isPressed = ref(false)
const rippleStyle = ref<{ left: string; top: string; width: string; height: string } | null>(null)

// Computed
const isDisabled = computed(() => props.disabled || props.loading)

const computedElement = computed(() => {
  if (props.to) return resolveComponent('NuxtLink')
  if (props.href) return 'a'
  return props.as
})

const buttonClasses = computed(() => [
  // Base styles
  'relative inline-flex items-center justify-center font-medium transition-all duration-200',
  'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-offset-olympus-bg',

  // Disabled state
  isDisabled.value && 'opacity-50 cursor-not-allowed pointer-events-none',

  // Active state
  !isDisabled.value && 'active:scale-[0.97]',

  // Size classes
  !props.gradient && sizeClasses[props.size],

  // Icon only adjustments
  props.iconOnly && iconOnlySizeClasses[props.size],

  // Variant classes
  variantClasses[props.variant],

  // Shape modifiers
  props.rounded && '!rounded-full',
  props.square && '!rounded-none',
  props.fullWidth && 'w-full',

  // Elevated shadow
  props.elevated && !isDisabled.value && 'shadow-lg hover:shadow-xl',

  // Gradient requires relative positioning
  props.gradient && 'p-px',

  // Overflow for ripple
  props.ripple && 'overflow-hidden',
])

const glowClasses = computed(() => {
  const glowColors: Record<ButtonVariant, string> = {
    primary: 'bg-olympus-primary',
    secondary: 'bg-olympus-text-muted',
    ghost: 'bg-olympus-text-muted',
    danger: 'bg-red-500',
    success: 'bg-green-500',
    warning: 'bg-amber-500',
    info: 'bg-blue-500',
    link: 'bg-olympus-primary',
    outline: 'bg-olympus-primary',
    premium: 'bg-gradient-to-r from-amber-500 to-orange-500',
  }
  return glowColors[props.variant]
})

const shortcutClasses = computed(() => {
  if (props.variant === 'primary' || props.variant === 'success' || props.variant === 'premium') {
    return 'bg-white/10 border-white/20 text-white/80'
  }
  return 'bg-olympus-surface border-olympus-border text-olympus-text-muted'
})

const badgeClasses = computed(() => {
  if (props.variant === 'primary' || props.variant === 'success') {
    return 'bg-white text-olympus-primary'
  }
  return 'bg-red-500 text-white'
})

const dotClasses = computed(() => {
  const colors: Record<string, string> = {
    primary: 'bg-olympus-primary',
    success: 'bg-green-500',
    warning: 'bg-amber-500',
    danger: 'bg-red-500',
    info: 'bg-blue-500',
  }
  return colors[props.dotColor]
})

// Size classes
const sizeClasses: Record<ButtonSize, string> = {
  xs: 'h-6 px-2 text-xs rounded',
  sm: 'h-8 px-3 text-sm rounded-md',
  md: 'h-10 px-4 text-sm rounded-lg',
  lg: 'h-12 px-6 text-base rounded-lg',
  xl: 'h-14 px-8 text-lg rounded-xl',
}

const iconOnlySizeClasses: Record<ButtonSize, string> = {
  xs: 'h-6 w-6 p-0 rounded',
  sm: 'h-8 w-8 p-0 rounded-md',
  md: 'h-10 w-10 p-0 rounded-lg',
  lg: 'h-12 w-12 p-0 rounded-lg',
  xl: 'h-14 w-14 p-0 rounded-xl',
}

const iconSizeClasses: Record<ButtonSize, string> = {
  xs: 'w-3 h-3',
  sm: 'w-3.5 h-3.5',
  md: 'w-4 h-4',
  lg: 'w-5 h-5',
  xl: 'w-6 h-6',
}

// Variant classes with all states
const variantClasses: Record<ButtonVariant, string> = {
  primary: [
    'bg-olympus-primary text-white shadow-lg shadow-olympus-primary/25',
    'hover:bg-olympus-primary-hover hover:shadow-olympus-primary/40',
    'focus-visible:ring-olympus-primary/50',
  ].join(' '),

  secondary: [
    'bg-olympus-surface text-olympus-text border border-olympus-border',
    'hover:bg-olympus-elevated hover:border-olympus-border-subtle',
    'focus-visible:ring-olympus-primary/50',
  ].join(' '),

  ghost: [
    'text-olympus-text-muted bg-transparent',
    'hover:bg-olympus-surface hover:text-olympus-text',
    'focus-visible:ring-olympus-primary/50',
  ].join(' '),

  danger: [
    'bg-red-500/10 text-red-400 border border-red-500/20',
    'hover:bg-red-500/20 hover:border-red-500/30',
    'focus-visible:ring-red-500/50',
  ].join(' '),

  success: [
    'bg-green-500 text-white shadow-lg shadow-green-500/25',
    'hover:bg-green-600 hover:shadow-green-500/40',
    'focus-visible:ring-green-500/50',
  ].join(' '),

  warning: [
    'bg-amber-500/10 text-amber-400 border border-amber-500/20',
    'hover:bg-amber-500/20 hover:border-amber-500/30',
    'focus-visible:ring-amber-500/50',
  ].join(' '),

  info: [
    'bg-blue-500/10 text-blue-400 border border-blue-500/20',
    'hover:bg-blue-500/20 hover:border-blue-500/30',
    'focus-visible:ring-blue-500/50',
  ].join(' '),

  link: [
    'text-olympus-primary bg-transparent underline-offset-4',
    'hover:underline hover:text-olympus-primary-hover',
    'focus-visible:ring-olympus-primary/50',
    'h-auto px-0 py-0',
  ].join(' '),

  outline: [
    'bg-transparent text-olympus-primary border-2 border-olympus-primary',
    'hover:bg-olympus-primary hover:text-white',
    'focus-visible:ring-olympus-primary/50',
  ].join(' '),

  premium: [
    'bg-gradient-to-r from-amber-500 via-orange-500 to-amber-500 text-white shadow-lg shadow-amber-500/25',
    'hover:from-amber-600 hover:via-orange-600 hover:to-amber-600 hover:shadow-amber-500/40',
    'focus-visible:ring-amber-500/50',
    'bg-[length:200%_100%] animate-gradient-x',
  ].join(' '),
}

// Methods
const handleClick = (event: MouseEvent) => {
  if (isDisabled.value) {
    event.preventDefault()
    event.stopPropagation()
    return
  }
  emit('click', event)
}

const handleMouseDown = (event: MouseEvent) => {
  if (isDisabled.value) return
  isPressed.value = true

  if (props.ripple && buttonRef.value) {
    const rect = buttonRef.value.getBoundingClientRect()
    const size = Math.max(rect.width, rect.height) * 2
    const x = event.clientX - rect.left - size / 2
    const y = event.clientY - rect.top - size / 2

    rippleStyle.value = {
      left: `${x}px`,
      top: `${y}px`,
      width: `${size}px`,
      height: `${size}px`,
    }

    // Clear ripple after animation
    setTimeout(() => {
      rippleStyle.value = null
    }, 600)
  }
}

const handleMouseUp = () => {
  isPressed.value = false
}

const handleMouseLeave = () => {
  isPressed.value = false
}

const handleKeydown = (event: KeyboardEvent) => {
  if (event.key === 'Enter' || event.key === ' ') {
    isPressed.value = true
    setTimeout(() => {
      isPressed.value = false
    }, 150)
  }
}

// Expose for parent components
defineExpose({
  focus: () => buttonRef.value?.focus(),
  blur: () => buttonRef.value?.blur(),
  el: buttonRef,
})
</script>

<style>
@keyframes ripple {
  0% {
    transform: scale(0);
    opacity: 1;
  }
  100% {
    transform: scale(1);
    opacity: 0;
  }
}

.animate-ripple {
  animation: ripple 0.6s ease-out forwards;
}

@keyframes gradient-x {
  0%, 100% {
    background-position: 0% 50%;
  }
  50% {
    background-position: 100% 50%;
  }
}

.animate-gradient-x {
  animation: gradient-x 3s ease infinite;
}
</style>
