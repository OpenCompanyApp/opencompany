<template>
  <!-- Preset Layouts -->
  <div v-if="preset" :class="wrapperClasses">
    <!-- Avatar with Text Layout -->
    <template v-if="preset === 'avatar-text'">
      <div class="flex items-center gap-3">
        <div :class="[baseSkeletonClasses, avatarSizeClasses[avatarSize], 'rounded-full']" />
        <div class="flex-1 space-y-2">
          <div :class="[baseSkeletonClasses, 'h-3 w-[60%]']" />
          <div :class="[baseSkeletonClasses, 'h-3 w-[40%]']" />
        </div>
      </div>
    </template>

    <!-- Card Layout -->
    <template v-else-if="preset === 'card'">
      <div class="space-y-4">
        <div :class="[baseSkeletonClasses, aspectClasses[imageAspect], 'w-full rounded-lg']" />
        <div class="space-y-2 px-1">
          <div :class="[baseSkeletonClasses, 'h-6 w-1/2']" />
          <div :class="[baseSkeletonClasses, 'h-3 w-[80%]']" />
          <div :class="[baseSkeletonClasses, 'h-3 w-[60%]']" />
        </div>
      </div>
    </template>

    <!-- Card Horizontal Layout -->
    <template v-else-if="preset === 'card-horizontal'">
      <div class="flex gap-4">
        <div :class="[baseSkeletonClasses, 'h-12 w-12 shrink-0']" />
        <div class="flex-1 space-y-2">
          <div :class="[baseSkeletonClasses, 'h-6 w-[70%]']" />
          <div :class="[baseSkeletonClasses, 'h-3 w-[90%]']" />
          <div :class="[baseSkeletonClasses, 'h-3 w-[50%]']" />
        </div>
      </div>
    </template>

    <!-- List Layout -->
    <template v-else-if="preset === 'list'">
      <div class="space-y-3">
        <div
          v-for="i in count"
          :key="i"
          class="flex items-center gap-3"
        >
          <div
            v-if="showListIcon"
            :class="[baseSkeletonClasses, 'h-8 w-8 rounded-full shrink-0']"
          />
          <div class="flex-1 space-y-1.5">
            <div :class="[baseSkeletonClasses, 'h-3']" :style="{ width: getRandomWidth(60, 90) }" />
            <div
              v-if="showListSubtext"
              :class="[baseSkeletonClasses, 'h-2.5']"
              :style="{ width: getRandomWidth(40, 60) }"
            />
          </div>
        </div>
      </div>
    </template>

    <!-- Table Layout -->
    <template v-else-if="preset === 'table'">
      <div class="space-y-2">
        <!-- Header -->
        <div class="flex gap-4 pb-2 border-b border-neutral-200 dark:border-neutral-700">
          <div
            v-for="col in columns"
            :key="`header-${col}`"
            :class="[baseSkeletonClasses, 'h-4']"
            :style="{ width: getColumnWidth(col) }"
          />
        </div>
        <!-- Rows -->
        <div
          v-for="row in count"
          :key="`row-${row}`"
          class="flex gap-4 py-2"
        >
          <div
            v-for="col in columns"
            :key="`cell-${row}-${col}`"
            :class="[baseSkeletonClasses, 'h-3']"
            :style="{ width: getColumnWidth(col) }"
          />
        </div>
      </div>
    </template>

    <!-- Form Layout -->
    <template v-else-if="preset === 'form'">
      <div class="space-y-4">
        <div v-for="i in count" :key="i" class="space-y-1.5">
          <div :class="[baseSkeletonClasses, 'h-3.5 w-24']" />
          <div :class="[baseSkeletonClasses, 'h-10 w-full rounded-lg']" />
        </div>
        <div :class="[baseSkeletonClasses, 'h-10 w-24 mt-2 rounded-lg']" />
      </div>
    </template>

    <!-- Paragraph Layout -->
    <template v-else-if="preset === 'paragraph'">
      <div class="space-y-2">
        <div
          v-for="i in count"
          :key="i"
          :class="[baseSkeletonClasses, 'h-3']"
          :style="{ width: i === count ? getRandomWidth(40, 60) : getRandomWidth(90, 100) }"
        />
      </div>
    </template>

    <!-- Profile Layout -->
    <template v-else-if="preset === 'profile'">
      <div class="flex flex-col items-center gap-4">
        <div :class="[baseSkeletonClasses, 'h-16 w-16 rounded-full']" />
        <div class="w-full space-y-2 text-center">
          <div :class="[baseSkeletonClasses, 'h-6 w-[60%] mx-auto']" />
          <div :class="[baseSkeletonClasses, 'h-3 w-[40%] mx-auto']" />
        </div>
        <div class="w-full flex justify-center gap-2 mt-2">
          <div :class="[baseSkeletonClasses, 'h-8 w-20 rounded-lg']" />
          <div :class="[baseSkeletonClasses, 'h-8 w-20 rounded-lg']" />
        </div>
      </div>
    </template>

    <!-- Stats Layout -->
    <template v-else-if="preset === 'stats'">
      <div :class="['grid gap-4', gridCols]">
        <div
          v-for="i in count"
          :key="i"
          class="p-4 rounded-xl bg-neutral-100 dark:bg-neutral-700 space-y-2"
        >
          <div :class="[baseSkeletonClasses, 'h-3 w-[60%]']" />
          <div :class="[baseSkeletonClasses, 'h-6 w-[80%]']" />
        </div>
      </div>
    </template>

    <!-- Message Layout (Chat) -->
    <template v-else-if="preset === 'message'">
      <div class="space-y-4">
        <div
          v-for="i in count"
          :key="i"
          :class="['flex gap-3', i % 2 === 0 && 'flex-row-reverse']"
        >
          <div :class="[baseSkeletonClasses, 'h-8 w-8 rounded-full shrink-0']" />
          <div
            :class="[
              'space-y-1.5 max-w-[70%]',
              i % 2 === 0 && 'items-end',
            ]"
          >
            <div :class="[baseSkeletonClasses, 'h-3']" :style="{ width: getRandomWidth(150, 250) }" />
            <div
              v-if="i % 2 !== 0"
              :class="[baseSkeletonClasses, 'h-3']"
              :style="{ width: getRandomWidth(100, 180) }"
            />
          </div>
        </div>
      </div>
    </template>
  </div>

  <!-- Single Skeleton Item -->
  <div
    v-else
    :class="[baseSkeletonClasses, variantClasses, roundedClasses, customClass]"
    :style="computedStyles"
  />
</template>

<script setup lang="ts">
import { computed } from 'vue'

type SkeletonAnimation = 'pulse' | 'wave' | 'shimmer' | 'none'
type SkeletonVariant = 'line' | 'text' | 'heading' | 'circle' | 'square' | 'avatar' | 'button' | 'input' | 'image' | 'badge' | 'icon' | 'custom'
type SkeletonSize = 'xs' | 'sm' | 'md' | 'lg' | 'xl' | '2xl'
type SkeletonRounded = 'none' | 'sm' | 'md' | 'lg' | 'xl' | '2xl' | 'full'
type SkeletonPreset = 'avatar-text' | 'card' | 'card-horizontal' | 'list' | 'table' | 'form' | 'paragraph' | 'profile' | 'stats' | 'message'
type AspectRatio = 'auto' | 'square' | 'video' | 'wide' | 'portrait'

const props = withDefaults(defineProps<{
  // Basic props
  variant?: SkeletonVariant
  size?: SkeletonSize
  rounded?: SkeletonRounded
  animation?: SkeletonAnimation

  // Dimensions
  width?: string | number
  height?: string | number
  aspectRatio?: AspectRatio

  // Timing
  delay?: number

  // Custom class
  customClass?: string

  // Preset layouts
  preset?: SkeletonPreset

  // Preset options
  count?: number
  columns?: number
  showListIcon?: boolean
  showListSubtext?: boolean
  avatarSize?: SkeletonSize
  imageAspect?: AspectRatio
}>(), {
  variant: 'line',
  size: 'md',
  rounded: 'md',
  animation: 'pulse',
  delay: 0,
  count: 3,
  columns: 4,
  showListIcon: true,
  showListSubtext: true,
  avatarSize: 'md',
  imageAspect: 'video',
})

// Base skeleton classes (animation and background)
const baseSkeletonClasses = computed(() => [
  'bg-neutral-200 dark:bg-neutral-700',
  props.animation === 'pulse' && 'animate-pulse',
  props.animation === 'none' && '',
])

// Wrapper classes for presets
const wrapperClasses = computed(() => [
  'w-full',
])

// Grid columns for stats preset
const gridCols = computed(() => {
  if (props.count <= 2) return 'grid-cols-2'
  if (props.count <= 3) return 'grid-cols-3'
  return 'grid-cols-4'
})

// Helper to get random width for realistic effect
const getRandomWidth = (min: number, max: number): string => {
  const value = Math.floor(Math.random() * (max - min + 1)) + min
  return `${value}%`
}

// Get column width for table
const getColumnWidth = (col: number): string => {
  const widths = ['15%', '25%', '20%', '20%', '10%', '10%']
  return widths[col % widths.length] || '20%'
}

// Size mappings
const avatarSizeClasses: Record<SkeletonSize, string> = {
  xs: 'h-6 w-6',
  sm: 'h-8 w-8',
  md: 'h-10 w-10',
  lg: 'h-12 w-12',
  xl: 'h-16 w-16',
  '2xl': 'h-20 w-20',
}

const buttonSizeClasses: Record<SkeletonSize, string> = {
  xs: 'h-6 w-16',
  sm: 'h-8 w-20',
  md: 'h-10 w-24',
  lg: 'h-12 w-32',
  xl: 'h-14 w-40',
  '2xl': 'h-16 w-48',
}

const iconSizeClasses: Record<SkeletonSize, string> = {
  xs: 'h-3 w-3',
  sm: 'h-4 w-4',
  md: 'h-5 w-5',
  lg: 'h-6 w-6',
  xl: 'h-8 w-8',
  '2xl': 'h-10 w-10',
}

// Aspect ratio classes
const aspectClasses: Record<AspectRatio, string> = {
  auto: '',
  square: 'aspect-square',
  video: 'aspect-video',
  wide: 'aspect-[21/9]',
  portrait: 'aspect-[3/4]',
}

// Rounded mappings
const roundedMap: Record<SkeletonRounded, string> = {
  none: 'rounded-none',
  sm: 'rounded-sm',
  md: 'rounded-md',
  lg: 'rounded-lg',
  xl: 'rounded-xl',
  '2xl': 'rounded-2xl',
  full: 'rounded-full',
}

// Variant classes for single skeleton
const variantClasses = computed(() => {
  const variants: Record<SkeletonVariant, string> = {
    line: 'h-4 w-full',
    text: 'h-3 w-3/4',
    heading: 'h-6 w-1/2',
    circle: `${avatarSizeClasses[props.size]} rounded-full`,
    square: avatarSizeClasses[props.size],
    avatar: `${avatarSizeClasses[props.size]} rounded-full`,
    button: `${buttonSizeClasses[props.size]} rounded-lg`,
    input: 'h-10 w-full rounded-lg',
    image: `w-full rounded-lg ${aspectClasses[props.aspectRatio]}`,
    badge: 'h-5 w-16 rounded-full',
    icon: iconSizeClasses[props.size],
    custom: '',
  }
  return variants[props.variant]
})

// Rounded classes for single skeleton
const roundedClasses = computed(() => {
  // Skip if variant already has rounded class
  if (['circle', 'avatar', 'button', 'input', 'image', 'badge'].includes(props.variant)) {
    return ''
  }
  return roundedMap[props.rounded]
})

// Computed styles for single skeleton
const computedStyles = computed(() => {
  const styles: Record<string, string> = {}

  if (props.width) {
    styles.width = typeof props.width === 'number'
      ? `${props.width}px`
      : props.width
  }

  if (props.height) {
    styles.height = typeof props.height === 'number'
      ? `${props.height}px`
      : props.height
  }

  if (props.delay > 0) {
    styles.animationDelay = `${props.delay}ms`
  }

  return styles
})
</script>
