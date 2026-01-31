<template>
  <component
    :is="as"
    :class="[
      // Base styles
      'relative overflow-hidden bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700',
      // Radius
      radiusClasses[radius],
      // Variant styles
      variantClasses[variant],
      // Shadow
      shadowClasses[shadow],
      // Interactive states
      hoverable && 'hover:border-neutral-300 dark:hover:border-neutral-600 transition-all',
      selected && 'ring-2 ring-neutral-900 dark:ring-white',
      disabled && 'opacity-50 cursor-not-allowed pointer-events-none',
      (clickable || interactive) && !disabled && 'cursor-pointer',
    ]"
    @click="handleClick"
  >
    <!-- Badge -->
    <div v-if="badge !== undefined" class="absolute -top-2 -right-2 z-20">
      <span class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 text-xs font-medium bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 rounded-full">
        {{ badge > 99 ? '99+' : badge }}
      </span>
    </div>

    <!-- Dot indicator -->
    <div v-if="dot && badge === undefined" class="absolute top-2 right-2 z-20">
      <span class="w-2 h-2 bg-neutral-900 dark:bg-white rounded-full" />
    </div>

    <!-- Close Button -->
    <button
      v-if="closable && !loading"
      type="button"
      class="absolute top-3 right-3 z-20 p-1 rounded text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors"
      @click.stop="$emit('close')"
    >
      <Icon name="ph:x" class="w-4 h-4" />
    </button>

    <!-- Draggable Handle -->
    <div
      v-if="draggable && showDragHandle"
      class="absolute top-3 left-3 z-20 p-1 cursor-grab active:cursor-grabbing text-neutral-400 dark:text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 transition-colors"
    >
      <Icon name="ph:dots-six-vertical" class="w-4 h-4" />
    </div>

    <!-- Loading Overlay -->
    <Transition
      enter-active-class="transition-opacity duration-150"
      enter-from-class="opacity-0"
      leave-active-class="transition-opacity duration-150"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="loading"
        class="absolute inset-0 z-30 flex items-center justify-center rounded-[inherit] bg-white/90 dark:bg-neutral-900/90"
      >
        <div class="flex flex-col items-center gap-3">
          <Icon
            :name="loadingIcon"
            class="w-8 h-8 animate-spin text-neutral-500 dark:text-neutral-300"
          />
          <span v-if="loadingText" class="text-sm font-medium text-neutral-500 dark:text-neutral-300">
            {{ loadingText }}
          </span>
        </div>
      </div>
    </Transition>

    <!-- Media Slot -->
    <div v-if="$slots.media" class="relative overflow-hidden" :class="mediaAspectClass">
      <slot name="media" />
    </div>

    <!-- Header -->
    <div
      v-if="$slots.header || title || subtitle || collapsible"
      :class="[
        'flex items-start justify-between gap-4',
        paddingClasses[padding],
        headerDivider && 'border-b border-neutral-200 dark:border-neutral-700',
        collapsible && 'cursor-pointer select-none',
      ]"
      @click="collapsible ? toggleExpanded() : undefined"
    >
      <div class="flex-1 min-w-0">
        <slot name="header">
          <div class="flex items-center gap-3">
            <!-- Header Icon -->
            <div
              v-if="headerIcon"
              class="flex items-center justify-center w-10 h-10 rounded-lg shrink-0 bg-neutral-100 dark:bg-neutral-700"
            >
              <Icon :name="headerIcon" class="w-5 h-5 text-neutral-600 dark:text-neutral-200" />
            </div>

            <div class="flex-1 min-w-0">
              <h3 v-if="title" class="font-semibold text-neutral-900 dark:text-white truncate" :class="titleSizeClass">
                {{ title }}
              </h3>
              <p v-if="subtitle" class="text-sm text-neutral-500 dark:text-neutral-300 truncate mt-0.5">
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
          class="p-1 rounded text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-all"
          @click.stop="toggleExpanded"
        >
          <Icon name="ph:caret-down" class="w-4 h-4 transition-transform" :class="{ 'rotate-180': isExpanded }" />
        </button>
      </div>
    </div>

    <!-- Content -->
    <div :class="[paddingClasses[padding], loading && 'opacity-50 pointer-events-none']">
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
      <div v-else>
        <slot />
      </div>
    </div>

    <!-- Footer -->
    <div
      v-if="$slots.footer"
      :class="[
        paddingClasses[padding],
        footerDivider && 'border-t border-neutral-200 dark:border-neutral-700',
      ]"
    >
      <slot name="footer" />
    </div>
  </component>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import type { RouteLocationRaw } from 'vue-router'
import Icon from './Icon.vue'

type CardVariant = 'default' | 'elevated' | 'outlined' | 'ghost'
type CardPadding = 'none' | 'xs' | 'sm' | 'md' | 'lg' | 'xl'
type CardRadius = 'none' | 'sm' | 'md' | 'lg' | 'xl' | '2xl'
type CardShadow = 'none' | 'sm' | 'md' | 'lg'
type MediaAspect = 'auto' | 'square' | 'video' | 'wide' | 'portrait'

const props = withDefaults(defineProps<{
  variant?: CardVariant
  padding?: CardPadding
  radius?: CardRadius
  shadow?: CardShadow
  noPadding?: boolean
  title?: string
  subtitle?: string
  headerIcon?: string
  headerDivider?: boolean
  footerDivider?: boolean
  mediaAspect?: MediaAspect
  hoverable?: boolean
  interactive?: boolean
  clickable?: boolean
  selected?: boolean
  disabled?: boolean
  as?: 'div' | 'article' | 'section' | 'aside' | 'button' | 'a'
  href?: string
  to?: RouteLocationRaw
  collapsible?: boolean
  defaultExpanded?: boolean
  loading?: boolean
  loadingText?: string
  loadingIcon?: string
  badge?: number
  dot?: boolean
  closable?: boolean
  draggable?: boolean
  showDragHandle?: boolean
}>(), {
  variant: 'default',
  padding: 'md',
  radius: 'lg',
  shadow: 'none',
  noPadding: false,
  as: 'div',
  headerDivider: false,
  footerDivider: false,
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
}>()

const isExpanded = ref(props.defaultExpanded)

const radiusClasses: Record<CardRadius, string> = {
  none: 'rounded-none',
  sm: 'rounded-sm',
  md: 'rounded-md',
  lg: 'rounded-lg',
  xl: 'rounded-xl',
  '2xl': 'rounded-2xl',
}

const variantClasses: Record<CardVariant, string> = {
  default: '',
  elevated: 'shadow-md border-transparent',
  outlined: '',
  ghost: 'bg-transparent border-transparent shadow-none',
}

const shadowClasses: Record<CardShadow, string> = {
  none: '',
  sm: 'shadow-sm',
  md: 'shadow-md',
  lg: 'shadow-lg',
}

const paddingClasses: Record<CardPadding, string> = {
  none: 'p-0',
  xs: 'p-2',
  sm: 'p-3',
  md: 'p-4',
  lg: 'p-5',
  xl: 'p-6',
}

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

const handleClick = (event: MouseEvent) => {
  if (props.disabled || props.loading) return
  emit('click', event)
}

const toggleExpanded = () => {
  isExpanded.value = !isExpanded.value
  emit(isExpanded.value ? 'expand' : 'collapse')
}

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
