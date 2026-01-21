<template>
  <!-- Preset Layouts -->
  <div v-if="preset" :class="wrapperClasses">
    <!-- Avatar with Text Layout -->
    <template v-if="preset === 'avatar-text'">
      <div class="flex items-center gap-3">
        <SkeletonItem variant="avatar" :size="avatarSize" :animation="animation" />
        <div class="flex-1 space-y-2">
          <SkeletonItem variant="text" width="60%" :animation="animation" :delay="50" />
          <SkeletonItem variant="text" width="40%" :animation="animation" :delay="100" />
        </div>
      </div>
    </template>

    <!-- Card Layout -->
    <template v-else-if="preset === 'card'">
      <div class="space-y-4">
        <SkeletonItem variant="image" :aspectRatio="imageAspect" :animation="animation" />
        <div class="space-y-2 px-1">
          <SkeletonItem variant="heading" :animation="animation" :delay="50" />
          <SkeletonItem variant="text" width="80%" :animation="animation" :delay="100" />
          <SkeletonItem variant="text" width="60%" :animation="animation" :delay="150" />
        </div>
      </div>
    </template>

    <!-- Card Horizontal Layout -->
    <template v-else-if="preset === 'card-horizontal'">
      <div class="flex gap-4">
        <SkeletonItem variant="square" size="lg" :animation="animation" class="shrink-0" />
        <div class="flex-1 space-y-2">
          <SkeletonItem variant="heading" width="70%" :animation="animation" :delay="50" />
          <SkeletonItem variant="text" width="90%" :animation="animation" :delay="100" />
          <SkeletonItem variant="text" width="50%" :animation="animation" :delay="150" />
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
          <SkeletonItem
            v-if="showListIcon"
            variant="circle"
            size="sm"
            :animation="animation"
            :delay="(i - 1) * 50"
          />
          <div class="flex-1 space-y-1.5">
            <SkeletonItem
              variant="text"
              :width="getRandomWidth(60, 90)"
              :animation="animation"
              :delay="(i - 1) * 50 + 25"
            />
            <SkeletonItem
              v-if="showListSubtext"
              variant="text"
              :width="getRandomWidth(40, 60)"
              height="10px"
              :animation="animation"
              :delay="(i - 1) * 50 + 50"
            />
          </div>
        </div>
      </div>
    </template>

    <!-- Table Layout -->
    <template v-else-if="preset === 'table'">
      <div class="space-y-2">
        <!-- Header -->
        <div class="flex gap-4 pb-2 border-b border-gray-200">
          <SkeletonItem
            v-for="col in columns"
            :key="`header-${col}`"
            variant="text"
            height="16px"
            :width="getColumnWidth(col)"
            :animation="animation"
          />
        </div>
        <!-- Rows -->
        <div
          v-for="row in count"
          :key="`row-${row}`"
          class="flex gap-4 py-2"
        >
          <SkeletonItem
            v-for="col in columns"
            :key="`cell-${row}-${col}`"
            variant="text"
            :width="getColumnWidth(col)"
            :animation="animation"
            :delay="(row - 1) * 30"
          />
        </div>
      </div>
    </template>

    <!-- Form Layout -->
    <template v-else-if="preset === 'form'">
      <div class="space-y-4">
        <div v-for="i in count" :key="i" class="space-y-1.5">
          <SkeletonItem
            variant="text"
            width="100px"
            height="14px"
            :animation="animation"
            :delay="(i - 1) * 50"
          />
          <SkeletonItem
            variant="input"
            :animation="animation"
            :delay="(i - 1) * 50 + 25"
          />
        </div>
        <SkeletonItem
          variant="button"
          size="md"
          :animation="animation"
          :delay="count * 50"
          class="mt-2"
        />
      </div>
    </template>

    <!-- Paragraph Layout -->
    <template v-else-if="preset === 'paragraph'">
      <div class="space-y-2">
        <SkeletonItem
          v-for="i in count"
          :key="i"
          variant="text"
          :width="i === count ? getRandomWidth(40, 60) : getRandomWidth(90, 100)"
          :animation="animation"
          :delay="(i - 1) * 30"
        />
      </div>
    </template>

    <!-- Profile Layout -->
    <template v-else-if="preset === 'profile'">
      <div class="flex flex-col items-center gap-4">
        <SkeletonItem variant="avatar" size="xl" :animation="animation" />
        <div class="w-full space-y-2 text-center">
          <SkeletonItem variant="heading" width="60%" :animation="animation" :delay="50" class="mx-auto" />
          <SkeletonItem variant="text" width="40%" :animation="animation" :delay="100" class="mx-auto" />
        </div>
        <div class="w-full flex justify-center gap-2 mt-2">
          <SkeletonItem variant="button" size="sm" :animation="animation" :delay="150" />
          <SkeletonItem variant="button" size="sm" :animation="animation" :delay="200" />
        </div>
      </div>
    </template>

    <!-- Stats Layout -->
    <template v-else-if="preset === 'stats'">
      <div :class="['grid gap-4', gridCols]">
        <div
          v-for="i in count"
          :key="i"
          class="p-4 rounded-xl bg-gray-100 space-y-2"
        >
          <SkeletonItem
            variant="text"
            width="60%"
            height="12px"
            :animation="animation"
            :delay="(i - 1) * 50"
          />
          <SkeletonItem
            variant="heading"
            width="80%"
            :animation="animation"
            :delay="(i - 1) * 50 + 25"
          />
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
          <SkeletonItem
            variant="avatar"
            size="sm"
            :animation="animation"
            :delay="(i - 1) * 100"
            class="shrink-0"
          />
          <div
            :class="[
              'space-y-1.5 max-w-[70%]',
              i % 2 === 0 && 'items-end',
            ]"
          >
            <SkeletonItem
              variant="text"
              :width="getRandomWidth(150, 250)"
              :animation="animation"
              :delay="(i - 1) * 100 + 25"
            />
            <SkeletonItem
              v-if="i % 2 !== 0"
              variant="text"
              :width="getRandomWidth(100, 180)"
              :animation="animation"
              :delay="(i - 1) * 100 + 50"
            />
          </div>
        </div>
      </div>
    </template>
  </div>

  <!-- Single Skeleton Item -->
  <SkeletonItem
    v-else
    :variant="variant"
    :size="size"
    :width="width"
    :height="height"
    :rounded="rounded"
    :animation="animation"
    :delay="delay"
    :aspectRatio="aspectRatio"
    :class="customClass"
  />
</template>

<script setup lang="ts">
import { computed, defineComponent, h, type PropType } from 'vue'

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

// Skeleton Item Component
const SkeletonItem = defineComponent({
  name: 'SkeletonItem',
  props: {
    variant: { type: String as PropType<SkeletonVariant>, default: 'line' },
    size: { type: String as PropType<SkeletonSize>, default: 'md' },
    rounded: { type: String as PropType<SkeletonRounded>, default: 'md' },
    animation: { type: String as PropType<SkeletonAnimation>, default: 'pulse' },
    width: { type: [String, Number], default: undefined },
    height: { type: [String, Number], default: undefined },
    aspectRatio: { type: String as PropType<AspectRatio>, default: 'auto' },
    delay: { type: Number, default: 0 },
  },
  setup(itemProps) {
    // Size mappings
    const sizeMap: Record<SkeletonSize, { avatar: string; button: string; icon: string }> = {
      xs: { avatar: 'h-6 w-6', button: 'h-6 w-16', icon: 'h-3 w-3' },
      sm: { avatar: 'h-8 w-8', button: 'h-8 w-20', icon: 'h-4 w-4' },
      md: { avatar: 'h-10 w-10', button: 'h-10 w-24', icon: 'h-5 w-5' },
      lg: { avatar: 'h-12 w-12', button: 'h-12 w-32', icon: 'h-6 w-6' },
      xl: { avatar: 'h-16 w-16', button: 'h-14 w-40', icon: 'h-8 w-8' },
      '2xl': { avatar: 'h-20 w-20', button: 'h-16 w-48', icon: 'h-10 w-10' },
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

    // Animation classes
    const animationMap: Record<SkeletonAnimation, string> = {
      pulse: 'animate-pulse',
      wave: 'skeleton-wave',
      shimmer: 'skeleton-shimmer',
      none: '',
    }

    // Aspect ratio classes
    const aspectMap: Record<AspectRatio, string> = {
      auto: '',
      square: 'aspect-square',
      video: 'aspect-video',
      wide: 'aspect-[21/9]',
      portrait: 'aspect-[3/4]',
    }

    // Variant classes
    const variantClasses = computed(() => {
      const size = itemProps.size as SkeletonSize
      const variants: Record<SkeletonVariant, string> = {
        line: 'h-4 w-full',
        text: 'h-3 w-3/4',
        heading: 'h-6 w-1/2',
        circle: `${sizeMap[size].avatar} rounded-full`,
        square: sizeMap[size].avatar,
        avatar: `${sizeMap[size].avatar} rounded-full`,
        button: `${sizeMap[size].button} rounded-lg`,
        input: 'h-10 w-full rounded-lg',
        image: 'w-full rounded-lg',
        badge: 'h-5 w-16 rounded-full',
        icon: sizeMap[size].icon,
        custom: '',
      }
      return variants[itemProps.variant as SkeletonVariant]
    })

    // Computed styles
    const computedStyles = computed(() => {
      const styles: Record<string, string> = {}

      if (itemProps.width) {
        styles.width = typeof itemProps.width === 'number'
          ? `${itemProps.width}px`
          : itemProps.width
      }

      if (itemProps.height) {
        styles.height = typeof itemProps.height === 'number'
          ? `${itemProps.height}px`
          : itemProps.height
      }

      if (itemProps.delay > 0) {
        styles.animationDelay = `${itemProps.delay}ms`
      }

      return styles
    })

    // Main classes
    const classes = computed(() => [
      'bg-gray-200',
      variantClasses.value,
      roundedMap[itemProps.rounded as SkeletonRounded],
      animationMap[itemProps.animation as SkeletonAnimation],
      aspectMap[itemProps.aspectRatio as AspectRatio],
    ])

    return () => h('div', {
      class: classes.value,
      style: computedStyles.value,
    })
  },
})
</script>

<style scoped>
/* Wave animation - smooth flowing effect */
@keyframes skeleton-wave {
  0% {
    background-position: -200% 0;
    opacity: 0.7;
  }
  50% {
    opacity: 0.9;
  }
  100% {
    background-position: 200% 0;
    opacity: 0.7;
  }
}

.skeleton-wave {
  background: linear-gradient(
    90deg,
    rgb(229 231 235 / 0.6) 0%,
    rgb(243 244 246 / 0.95) 25%,
    rgb(209 213 219 / 0.3) 50%,
    rgb(243 244 246 / 0.95) 75%,
    rgb(229 231 235 / 0.6) 100%
  );
  background-size: 200% 100%;
  animation: skeleton-wave 2s cubic-bezier(0.4, 0, 0.2, 1) infinite;
}

/* Shimmer animation - elegant highlight sweep */
@keyframes skeleton-shimmer {
  0% {
    background-position: -100% 0;
    transform: translateX(0);
  }
  100% {
    background-position: 200% 0;
    transform: translateX(0);
  }
}

.skeleton-shimmer {
  position: relative;
  overflow: hidden;
  background: linear-gradient(
    110deg,
    rgb(229 231 235 / 0.7) 0%,
    rgb(229 231 235 / 0.7) 40%,
    rgb(243 244 246 / 1) 50%,
    rgb(229 231 235 / 0.7) 60%,
    rgb(229 231 235 / 0.7) 100%
  );
  background-size: 200% 100%;
  animation: skeleton-shimmer 1.5s cubic-bezier(0.4, 0, 0.2, 1) infinite;
}

/* Enhanced pulse with subtle scale */
@keyframes skeleton-pulse {
  0%, 100% {
    opacity: 0.6;
    transform: scale(1);
  }
  50% {
    opacity: 0.9;
    transform: scale(1.002);
  }
}

.animate-pulse {
  animation: skeleton-pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

/* Stagger animation delays for children */
.skeleton-wave,
.skeleton-shimmer,
.animate-pulse {
  animation-fill-mode: both;
  transition: opacity 0.3s ease-out;
}

/* Hover state for interactive skeletons */
.skeleton-wave:hover,
.skeleton-shimmer:hover {
  opacity: 0.95;
}
</style>
