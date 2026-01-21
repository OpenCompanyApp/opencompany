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
    @dragstart="$emit('dragstart', $event)"
    @dragend="$emit('dragend', $event)"
  >
    <!-- Selection Indicator -->
    <div
      v-if="selected"
      class="absolute inset-0 rounded-[inherit] ring-2 ring-gray-900 ring-offset-2 ring-offset-white pointer-events-none z-10"
    />

    <!-- Badge -->
    <div
      v-if="badge !== undefined || dot"
      class="absolute -top-2 -right-2 z-20"
    >
      <span
        v-if="badge !== undefined"
        class="flex items-center justify-center min-w-5 h-5 px-1.5 text-xs font-bold rounded-full text-white bg-gray-600"
      >
        {{ badge > 99 ? '99+' : badge }}
      </span>
      <span
        v-else-if="dot"
        class="block w-3 h-3 rounded-full bg-gray-500"
      />
    </div>

    <!-- Close Button -->
    <button
      v-if="closable && !loading"
      type="button"
      class="absolute top-3 right-3 z-20 p-1.5 rounded-full bg-gray-100 hover:bg-gray-200 text-gray-500 hover:text-gray-700 transition-colors duration-150 outline-none focus-visible:ring-2 focus-visible:ring-gray-900/50"
      aria-label="Close"
      @click.stop="$emit('close')"
    >
      <Icon name="ph:x" class="w-4 h-4" />
    </button>

    <!-- Draggable Handle -->
    <div
      v-if="draggable && showDragHandle"
      class="absolute top-3 left-3 z-20 p-1 cursor-grab active:cursor-grabbing text-gray-400 hover:text-gray-600 transition-colors duration-150"
    >
      <Icon name="ph:dots-six-vertical" class="w-4 h-4" />
    </div>

    <!-- Loading Overlay -->
    <Transition
      enter-active-class="transition-opacity duration-150"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-150"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="loading"
        class="absolute inset-0 z-30 flex items-center justify-center rounded-[inherit] bg-white/90"
      >
        <div class="flex flex-col items-center gap-3">
          <Icon
            :name="loadingIcon"
            class="w-8 h-8 animate-spin text-gray-500"
          />
          <span
            v-if="loadingText"
            class="text-sm font-medium text-gray-500"
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
    </div>

    <!-- Header -->
    <div
      v-if="$slots.header || title || subtitle || collapsible"
      class="flex items-start justify-between gap-4"
      :class="[
        headerDivider && 'pb-4 mb-4 border-b border-gray-200',
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
              class="flex items-center justify-center w-10 h-10 rounded-lg shrink-0 bg-gray-100"
            >
              <Icon
                :name="headerIcon"
                class="w-5 h-5 text-gray-600"
              />
            </div>

            <div class="flex-1 min-w-0">
              <h3
                v-if="title"
                class="font-semibold text-gray-900 truncate"
                :class="titleSizeClass"
              >
                {{ title }}
              </h3>
              <p
                v-if="subtitle"
                class="text-sm text-gray-500 truncate mt-0.5"
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
          class="p-1.5 rounded-lg hover:bg-gray-100 transition-colors duration-150"
          :aria-label="isExpanded ? 'Collapse' : 'Expand'"
          @click.stop="toggleExpanded"
        >
          <Icon
            name="ph:caret-down"
            class="w-5 h-5 text-gray-500 transition-transform duration-150"
            :class="{ 'rotate-180': isExpanded }"
          />
        </button>
      </div>
    </div>

    <!-- Content -->
    <Transition
      v-if="collapsible"
      enter-active-class="transition-all duration-150 overflow-hidden"
      enter-from-class="max-h-0 opacity-0"
      enter-to-class="max-h-[2000px] opacity-100"
      leave-active-class="transition-all duration-150 overflow-hidden"
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
        footerDivider && 'pt-4 mt-4 border-t border-gray-200',
      ]"
    >
      <slot name="footer" />
    </div>

    <!-- Actions Slot (Bottom buttons) -->
    <div
      v-if="$slots.actions"
      :class="[
        'flex items-center gap-2',
        actionsDivider && 'pt-4 mt-4 border-t border-gray-200',
        actionsAlign === 'left' && 'justify-start',
        actionsAlign === 'center' && 'justify-center',
        actionsAlign === 'right' && 'justify-end',
        actionsAlign === 'stretch' && 'justify-stretch [&>*]:flex-1',
      ]"
    >
      <slot name="actions" />
    </div>
  </component>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import Icon from '@/Components/shared/Icon.vue'
import type { RouteLocationRaw } from 'vue-router'

type CardVariant = 'default' | 'elevated' | 'outlined' | 'ghost'
type CardPadding = 'none' | 'xs' | 'sm' | 'md' | 'lg' | 'xl'
type CardRadius = 'none' | 'sm' | 'md' | 'lg' | 'xl' | '2xl'
type CardShadow = 'none' | 'sm' | 'md' | 'lg'
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
  headerDivider?: boolean

  // Footer props
  footerDivider?: boolean

  // Actions props
  actionsDivider?: boolean
  actionsAlign?: ActionsAlign

  // Media props
  mediaAspect?: MediaAspect

  // Interaction props
  hoverable?: boolean
  interactive?: boolean
  clickable?: boolean
  selected?: boolean
  disabled?: boolean

  // Navigation props
  as?: 'div' | 'article' | 'section' | 'aside' | 'button' | 'a'
  href?: string
  to?: RouteLocationRaw

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
  radius: 'lg',
  shadow: 'none',
  noPadding: false,
  as: 'div',
  headerDivider: false,
  footerDivider: false,
  actionsDivider: false,
  actionsAlign: 'right',
  mediaAspect: 'auto',
  hoverable: false,
  interactive: false,
  clickable: false,
  selected: false,
  disabled: false,
  collapsible: false,
  defaultExpanded: true,
  loading: false,
  loadingIcon: 'ph:spinner',
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
const isExpanded = ref(props.defaultExpanded)

// Computed component
const computedComponent = computed(() => {
  if (props.to) {
    return Link
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
}

// Shadow classes
const shadowClasses: Record<CardShadow, string> = {
  none: '',
  sm: 'shadow-sm',
  md: 'shadow-md',
  lg: 'shadow-lg',
}

// Variant classes - clean, minimal
const variantClasses = computed(() => {
  const variants: Record<CardVariant, string> = {
    default: 'bg-white border border-gray-200',
    elevated: 'bg-white border border-gray-200 shadow-sm',
    outlined: 'bg-transparent border border-gray-300',
    ghost: 'bg-transparent border-0',
  }
  return variants[props.variant]
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

// Main card classes
const cardClasses = computed(() => [
  // Base
  'relative overflow-hidden transition-colors duration-150',

  // Padding
  props.noPadding ? '' : paddingClasses[props.padding],

  // Radius
  radiusClasses[props.radius],

  // Shadow
  shadowClasses[props.shadow],

  // Variant
  variantClasses.value,

  // Hoverable
  props.hoverable && !props.disabled && 'hover:border-gray-300 hover:shadow-sm',

  // Clickable (cursor)
  (props.clickable || props.as === 'button' || props.href || props.to) && !props.disabled && 'cursor-pointer',

  // Selected
  props.selected && 'ring-2 ring-gray-900 ring-offset-2 ring-offset-white',

  // Disabled
  props.disabled && 'opacity-50 cursor-not-allowed pointer-events-none',

  // Focus ring for interactive elements
  (props.as === 'button' || props.href || props.to) && 'outline-none focus-visible:ring-2 focus-visible:ring-gray-900/50 focus-visible:ring-offset-2 focus-visible:ring-offset-white',

  // Text alignment for button/link
  (props.as === 'button' || props.href || props.to) && 'text-left',
])

// Event handlers
const handleClick = (event: MouseEvent) => {
  if (props.disabled || props.loading) return
  emit('click', event)
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
