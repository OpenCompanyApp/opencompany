<template>
  <component
    :is="computedComponent"
    ref="cardRef"
    :type="computedType"
    :href="href"
    :to="to"
    :disabled="disabled || loading"
    :aria-disabled="disabled || loading"
    :aria-busy="loading"
    :aria-expanded="collapsible ? isExpanded : undefined"
    :aria-label="ariaLabel"
    :draggable="draggable"
    :class="cardClasses"
    @click="handleClick"
    @mouseenter="handleMouseEnter"
    @mouseleave="handleMouseLeave"
    @dragstart="$emit('dragstart', $event)"
    @dragend="$emit('dragend', $event)"
  >
    <!-- Gradient Border Overlay -->
    <div
      v-if="gradientBorder"
      class="absolute inset-0 rounded-[inherit] p-px bg-gradient-to-br from-olympus-primary via-purple-500 to-pink-500 -z-10 animate-gradient-x"
      :class="{ 'opacity-50': disabled }"
    />

    <!-- Glow Effect -->
    <div
      v-if="glow && !disabled"
      class="absolute inset-0 rounded-[inherit] blur-xl opacity-0 transition-opacity duration-300 -z-20"
      :class="[glowColorClass, isHovered && 'opacity-30']"
    />

    <!-- Selection Indicator -->
    <div
      v-if="selected"
      class="absolute inset-0 rounded-[inherit] ring-2 ring-olympus-primary ring-offset-2 ring-offset-olympus-bg pointer-events-none z-10"
    />

    <!-- Corner Decoration -->
    <div
      v-if="cornerDecoration"
      class="absolute top-0 right-0 w-16 h-16 overflow-hidden rounded-tr-[inherit]"
    >
      <div
        class="absolute -top-8 -right-8 w-16 h-16 rotate-45"
        :class="cornerDecorationColorClass"
      />
      <Icon
        v-if="cornerIcon"
        :name="cornerIcon"
        class="absolute top-2 right-2 w-4 h-4 text-white"
      />
    </div>

    <!-- Badge -->
    <div
      v-if="badge !== undefined || dot"
      class="absolute -top-2 -right-2 z-20"
    >
      <span
        v-if="badge !== undefined"
        class="flex items-center justify-center min-w-5 h-5 px-1.5 text-xs font-bold rounded-full text-white shadow-lg"
        :class="badgeColorClass"
      >
        {{ badge > 99 ? '99+' : badge }}
      </span>
      <span
        v-else-if="dot"
        class="block w-3 h-3 rounded-full shadow-lg animate-pulse"
        :class="dotColorClass"
      />
    </div>

    <!-- Close Button -->
    <button
      v-if="closable && !loading"
      type="button"
      class="absolute top-3 right-3 z-20 p-1 rounded-full bg-olympus-surface/80 hover:bg-olympus-surface text-olympus-text-muted hover:text-olympus-text transition-colors duration-150 outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50"
      aria-label="Close"
      @click.stop="$emit('close')"
    >
      <Icon name="ph:x" class="w-4 h-4" />
    </button>

    <!-- Draggable Handle -->
    <div
      v-if="draggable && showDragHandle"
      class="absolute top-3 left-3 z-20 p-1 cursor-grab active:cursor-grabbing text-olympus-text-muted"
    >
      <Icon name="ph:dots-six-vertical" class="w-4 h-4" />
    </div>

    <!-- Loading Overlay -->
    <Transition
      enter-active-class="transition-opacity duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-200"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="loading"
        class="absolute inset-0 z-30 flex items-center justify-center rounded-[inherit]"
        :class="loadingOverlayClass"
      >
        <div class="flex flex-col items-center gap-3">
          <Icon
            :name="loadingIcon"
            class="w-8 h-8 animate-spin"
            :class="loadingIconColorClass"
          />
          <span
            v-if="loadingText"
            class="text-sm font-medium"
            :class="loadingTextColorClass"
          >
            {{ loadingText }}
          </span>
        </div>
      </div>
    </Transition>

    <!-- Media Slot (Image/Video at top) -->
    <div
      v-if="$slots.media"
      class="relative overflow-hidden"
      :class="[
        mediaAspectClass,
        { '-mx-4 -mt-4 mb-4': padding === 'md' && !noPadding },
        { '-mx-3 -mt-3 mb-3': padding === 'sm' && !noPadding },
        { '-mx-5 -mt-5 mb-5': padding === 'lg' && !noPadding },
        { '-mx-6 -mt-6 mb-6': padding === 'xl' && !noPadding },
      ]"
    >
      <slot name="media" />
      <div
        v-if="mediaOverlay"
        class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent"
      />
    </div>

    <!-- Header -->
    <div
      v-if="$slots.header || title || subtitle || collapsible"
      class="flex items-start justify-between gap-4"
      :class="[
        headerDivider && 'pb-4 mb-4 border-b border-olympus-border',
        { 'cursor-pointer': collapsible },
      ]"
      @click="collapsible ? toggleExpanded() : undefined"
    >
      <div class="flex-1 min-w-0">
        <slot name="header">
          <div class="flex items-center gap-3">
            <!-- Header Icon -->
            <div
              v-if="headerIcon"
              class="flex items-center justify-center w-10 h-10 rounded-lg shrink-0"
              :class="headerIconBgClass"
            >
              <Icon
                :name="headerIcon"
                class="w-5 h-5"
                :class="headerIconColorClass"
              />
            </div>

            <div class="flex-1 min-w-0">
              <h3
                v-if="title"
                class="font-semibold truncate"
                :class="titleSizeClass"
              >
                {{ title }}
              </h3>
              <p
                v-if="subtitle"
                class="text-sm text-olympus-text-muted truncate mt-0.5"
              >
                {{ subtitle }}
              </p>
            </div>
          </div>
        </slot>
      </div>

      <!-- Header Actions -->
      <div v-if="$slots.headerActions || collapsible" class="flex items-center gap-2 shrink-0">
        <slot name="headerActions" />
        <button
          v-if="collapsible"
          type="button"
          class="p-1 rounded-md hover:bg-olympus-surface transition-colors duration-150"
          :aria-label="isExpanded ? 'Collapse' : 'Expand'"
          @click.stop="toggleExpanded"
        >
          <Icon
            name="ph:caret-down"
            class="w-5 h-5 text-olympus-text-muted transition-transform duration-200"
            :class="{ 'rotate-180': isExpanded }"
          />
        </button>
      </div>
    </div>

    <!-- Content -->
    <Transition
      v-if="collapsible"
      enter-active-class="transition-all duration-300 ease-out overflow-hidden"
      enter-from-class="max-h-0 opacity-0"
      enter-to-class="max-h-[2000px] opacity-100"
      leave-active-class="transition-all duration-200 ease-in overflow-hidden"
      leave-from-class="max-h-[2000px] opacity-100"
      leave-to-class="max-h-0 opacity-0"
    >
      <div v-show="isExpanded">
        <slot />
      </div>
    </Transition>
    <div v-else :class="{ 'opacity-50 pointer-events-none': loading }">
      <slot />
    </div>

    <!-- Footer -->
    <div
      v-if="$slots.footer"
      :class="[
        'flex items-center justify-between gap-4',
        footerDivider && 'pt-4 mt-4 border-t border-olympus-border',
      ]"
    >
      <slot name="footer" />
    </div>

    <!-- Actions Slot (Bottom buttons) -->
    <div
      v-if="$slots.actions"
      :class="[
        'flex items-center gap-2',
        actionsDivider && 'pt-4 mt-4 border-t border-olympus-border',
        actionsAlign === 'left' && 'justify-start',
        actionsAlign === 'center' && 'justify-center',
        actionsAlign === 'right' && 'justify-end',
        actionsAlign === 'stretch' && 'justify-stretch [&>*]:flex-1',
      ]"
    >
      <slot name="actions" />
    </div>

    <!-- Ripple Effect -->
    <span
      v-for="ripple in ripples"
      :key="ripple.id"
      class="absolute rounded-full bg-white/30 animate-ripple pointer-events-none"
      :style="{
        left: `${ripple.x}px`,
        top: `${ripple.y}px`,
        width: `${ripple.size}px`,
        height: `${ripple.size}px`,
      }"
    />
  </component>
</template>

<script setup lang="ts">
import type { RouteLocationRaw } from 'vue-router'

type CardVariant = 'default' | 'elevated' | 'outlined' | 'glass' | 'gradient' | 'premium' | 'danger' | 'success' | 'warning' | 'info' | 'ghost'
type CardPadding = 'none' | 'xs' | 'sm' | 'md' | 'lg' | 'xl'
type CardRadius = 'none' | 'sm' | 'md' | 'lg' | 'xl' | '2xl' | '3xl' | 'full'
type CardShadow = 'none' | 'sm' | 'md' | 'lg' | 'xl' | '2xl'
type MediaAspect = 'auto' | 'square' | 'video' | 'wide' | 'portrait'
type ActionsAlign = 'left' | 'center' | 'right' | 'stretch'

const props = withDefaults(defineProps<{
  // Basic props
  variant?: CardVariant
  padding?: CardPadding
  radius?: CardRadius
  shadow?: CardShadow
  noPadding?: boolean

  // Header props
  title?: string
  subtitle?: string
  headerIcon?: string
  headerIconColor?: 'primary' | 'success' | 'warning' | 'danger' | 'info' | 'muted'
  headerDivider?: boolean

  // Footer props
  footerDivider?: boolean

  // Actions props
  actionsDivider?: boolean
  actionsAlign?: ActionsAlign

  // Media props
  mediaAspect?: MediaAspect
  mediaOverlay?: boolean

  // Interaction props
  hoverable?: boolean
  interactive?: boolean
  clickable?: boolean
  selected?: boolean
  disabled?: boolean
  ripple?: boolean

  // Navigation props
  as?: 'div' | 'article' | 'section' | 'aside' | 'button' | 'a'
  href?: string
  to?: RouteLocationRaw

  // Visual effects
  elevated?: boolean
  glow?: boolean
  glowColor?: 'primary' | 'success' | 'warning' | 'danger' | 'info'
  gradientBorder?: boolean

  // Collapsible
  collapsible?: boolean
  defaultExpanded?: boolean

  // Loading state
  loading?: boolean
  loadingText?: string
  loadingIcon?: string

  // Decorations
  badge?: number
  dot?: boolean
  dotColor?: 'primary' | 'success' | 'warning' | 'danger' | 'info'
  cornerDecoration?: boolean
  cornerColor?: 'primary' | 'success' | 'warning' | 'danger' | 'info'
  cornerIcon?: string

  // Close button
  closable?: boolean

  // Draggable
  draggable?: boolean
  showDragHandle?: boolean

  // Accessibility
  ariaLabel?: string
}>(), {
  variant: 'default',
  padding: 'md',
  radius: 'xl',
  shadow: 'none',
  noPadding: false,
  headerIconColor: 'primary',
  headerDivider: false,
  footerDivider: false,
  actionsDivider: false,
  actionsAlign: 'right',
  mediaAspect: 'auto',
  mediaOverlay: false,
  hoverable: false,
  interactive: false,
  clickable: false,
  selected: false,
  disabled: false,
  ripple: false,
  elevated: false,
  glow: false,
  glowColor: 'primary',
  gradientBorder: false,
  collapsible: false,
  defaultExpanded: true,
  loading: false,
  loadingIcon: 'ph:spinner',
  dotColor: 'primary',
  cornerColor: 'primary',
  cornerDecoration: false,
  closable: false,
  draggable: false,
  showDragHandle: true,
})

const emit = defineEmits<{
  click: [event: MouseEvent]
  close: []
  expand: []
  collapse: []
  dragstart: [event: DragEvent]
  dragend: [event: DragEvent]
}>()

const cardRef = ref<HTMLElement | null>(null)
const isHovered = ref(false)
const isExpanded = ref(props.defaultExpanded)
const ripples = ref<Array<{ id: number; x: number; y: number; size: number }>>([])
let rippleId = 0

// Computed component
const computedComponent = computed(() => {
  if (props.to) {
    return resolveComponent('NuxtLink')
  }
  if (props.href) {
    return 'a'
  }
  return props.as
})

const computedType = computed(() => {
  if (props.as === 'button' && !props.to && !props.href) {
    return 'button'
  }
  return undefined
})

// Padding classes
const paddingClasses: Record<CardPadding, string> = {
  none: '',
  xs: 'p-2',
  sm: 'p-3',
  md: 'p-4',
  lg: 'p-5',
  xl: 'p-6',
}

// Radius classes
const radiusClasses: Record<CardRadius, string> = {
  none: 'rounded-none',
  sm: 'rounded-sm',
  md: 'rounded-md',
  lg: 'rounded-lg',
  xl: 'rounded-xl',
  '2xl': 'rounded-2xl',
  '3xl': 'rounded-3xl',
  full: 'rounded-full',
}

// Shadow classes
const shadowClasses: Record<CardShadow, string> = {
  none: '',
  sm: 'shadow-sm',
  md: 'shadow-md',
  lg: 'shadow-lg',
  xl: 'shadow-xl',
  '2xl': 'shadow-2xl',
}

// Variant classes
const variantClasses = computed(() => {
  const variants: Record<CardVariant, string> = {
    default: 'bg-olympus-surface border border-olympus-border',
    elevated: 'bg-olympus-elevated border border-olympus-border shadow-lg',
    outlined: 'bg-transparent border-2 border-olympus-border',
    glass: 'glass border border-olympus-border/50 backdrop-blur-xl',
    gradient: 'bg-gradient-to-br from-olympus-surface via-olympus-elevated to-olympus-surface border border-olympus-border',
    premium: 'bg-gradient-to-br from-olympus-primary/20 via-purple-500/10 to-pink-500/20 border border-olympus-primary/30',
    danger: 'bg-red-500/10 border border-red-500/30',
    success: 'bg-green-500/10 border border-green-500/30',
    warning: 'bg-yellow-500/10 border border-yellow-500/30',
    info: 'bg-blue-500/10 border border-blue-500/30',
    ghost: 'bg-transparent border-0',
  }
  return variants[props.variant]
})

// Glow color classes
const glowColorClass = computed(() => {
  const colors: Record<string, string> = {
    primary: 'bg-olympus-primary',
    success: 'bg-green-500',
    warning: 'bg-yellow-500',
    danger: 'bg-red-500',
    info: 'bg-blue-500',
  }
  return colors[props.glowColor]
})

// Badge color class
const badgeColorClass = computed(() => {
  const colors: Record<string, string> = {
    primary: 'bg-olympus-primary',
    success: 'bg-green-500',
    warning: 'bg-yellow-500',
    danger: 'bg-red-500',
    info: 'bg-blue-500',
  }
  return colors[props.dotColor]
})

// Dot color class
const dotColorClass = computed(() => {
  const colors: Record<string, string> = {
    primary: 'bg-olympus-primary',
    success: 'bg-green-500',
    warning: 'bg-yellow-500',
    danger: 'bg-red-500',
    info: 'bg-blue-500',
  }
  return colors[props.dotColor]
})

// Corner decoration color
const cornerDecorationColorClass = computed(() => {
  const colors: Record<string, string> = {
    primary: 'bg-olympus-primary',
    success: 'bg-green-500',
    warning: 'bg-yellow-500',
    danger: 'bg-red-500',
    info: 'bg-blue-500',
  }
  return colors[props.cornerColor]
})

// Header icon classes
const headerIconBgClass = computed(() => {
  const colors: Record<string, string> = {
    primary: 'bg-olympus-primary/20',
    success: 'bg-green-500/20',
    warning: 'bg-yellow-500/20',
    danger: 'bg-red-500/20',
    info: 'bg-blue-500/20',
    muted: 'bg-olympus-surface',
  }
  return colors[props.headerIconColor]
})

const headerIconColorClass = computed(() => {
  const colors: Record<string, string> = {
    primary: 'text-olympus-primary',
    success: 'text-green-500',
    warning: 'text-yellow-500',
    danger: 'text-red-500',
    info: 'text-blue-500',
    muted: 'text-olympus-text-muted',
  }
  return colors[props.headerIconColor]
})

// Title size class based on padding
const titleSizeClass = computed(() => {
  const sizes: Record<CardPadding, string> = {
    none: 'text-base',
    xs: 'text-sm',
    sm: 'text-base',
    md: 'text-lg',
    lg: 'text-xl',
    xl: 'text-2xl',
  }
  return sizes[props.padding]
})

// Media aspect ratio classes
const mediaAspectClass = computed(() => {
  const aspects: Record<MediaAspect, string> = {
    auto: '',
    square: 'aspect-square',
    video: 'aspect-video',
    wide: 'aspect-[21/9]',
    portrait: 'aspect-[3/4]',
  }
  return aspects[props.mediaAspect]
})

// Loading overlay class
const loadingOverlayClass = computed(() => {
  if (props.variant === 'glass') {
    return 'bg-olympus-bg/70 backdrop-blur-sm'
  }
  return 'bg-olympus-surface/90'
})

// Loading icon color
const loadingIconColorClass = computed(() => {
  if (props.variant === 'danger') return 'text-red-500'
  if (props.variant === 'success') return 'text-green-500'
  if (props.variant === 'warning') return 'text-yellow-500'
  if (props.variant === 'info') return 'text-blue-500'
  return 'text-olympus-primary'
})

// Loading text color
const loadingTextColorClass = computed(() => {
  return 'text-olympus-text-muted'
})

// Main card classes
const cardClasses = computed(() => [
  // Base
  'relative overflow-hidden transition-all duration-200',

  // Padding
  props.noPadding ? '' : paddingClasses[props.padding],

  // Radius
  radiusClasses[props.radius],

  // Shadow
  shadowClasses[props.shadow],

  // Variant
  props.gradientBorder ? 'bg-olympus-surface' : variantClasses.value,

  // Elevated
  props.elevated && 'shadow-lg',

  // Hoverable
  props.hoverable && !props.disabled && 'hover:bg-olympus-elevated hover:border-olympus-border-hover',

  // Interactive (scale on click)
  props.interactive && !props.disabled && 'active:scale-[0.98]',

  // Clickable (cursor)
  (props.clickable || props.as === 'button' || props.href || props.to) && !props.disabled && 'cursor-pointer',

  // Selected
  props.selected && 'ring-2 ring-olympus-primary ring-offset-2 ring-offset-olympus-bg',

  // Disabled
  props.disabled && 'opacity-50 cursor-not-allowed pointer-events-none',

  // Focus ring for interactive elements
  (props.as === 'button' || props.href || props.to) && 'outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50 focus-visible:ring-offset-2 focus-visible:ring-offset-olympus-bg',

  // Text alignment for button/link
  (props.as === 'button' || props.href || props.to) && 'text-left',

  // Glow effect
  props.glow && 'card-glow',
])

// Event handlers
const handleClick = (event: MouseEvent) => {
  if (props.disabled || props.loading) return

  // Add ripple effect
  if (props.ripple && cardRef.value) {
    const rect = cardRef.value.getBoundingClientRect()
    const size = Math.max(rect.width, rect.height) * 2
    const x = event.clientX - rect.left - size / 2
    const y = event.clientY - rect.top - size / 2

    const id = ++rippleId
    ripples.value.push({ id, x, y, size })

    setTimeout(() => {
      ripples.value = ripples.value.filter(r => r.id !== id)
    }, 600)
  }

  emit('click', event)
}

const handleMouseEnter = () => {
  isHovered.value = true
}

const handleMouseLeave = () => {
  isHovered.value = false
}

const toggleExpanded = () => {
  isExpanded.value = !isExpanded.value
  emit(isExpanded.value ? 'expand' : 'collapse')
}

// Expose methods
defineExpose({
  expand: () => {
    isExpanded.value = true
    emit('expand')
  },
  collapse: () => {
    isExpanded.value = false
    emit('collapse')
  },
  toggle: toggleExpanded,
  isExpanded: computed(() => isExpanded.value),
})
</script>

<style scoped>
.glass {
  background: linear-gradient(
    135deg,
    oklch(var(--color-olympus-surface) / 0.8) 0%,
    oklch(var(--color-olympus-elevated) / 0.6) 100%
  );
}

.card-glow {
  box-shadow: 0 0 20px -5px oklch(var(--color-olympus-primary) / 0.3);
}

.card-glow:hover {
  box-shadow: 0 0 30px -5px oklch(var(--color-olympus-primary) / 0.5);
}

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
  background-size: 200% 200%;
  animation: gradient-x 3s ease infinite;
}
</style>
