<template>
  <div
    :class="[
      'animate-pulse bg-olympus-surface',
      roundedClasses[rounded],
      variantClasses[variant],
      customClass,
    ]"
  />
</template>

<script setup lang="ts">
type SkeletonRounded = 'sm' | 'md' | 'lg' | 'xl' | 'full'
type SkeletonVariant = 'line' | 'text' | 'circle' | 'square' | 'card' | 'avatar' | 'button' | 'custom'

withDefaults(defineProps<{
  rounded?: SkeletonRounded
  variant?: SkeletonVariant
  customClass?: string
}>(), {
  rounded: 'md',
  variant: 'line',
})

const roundedClasses: Record<SkeletonRounded, string> = {
  sm: 'rounded-sm',
  md: 'rounded-md',
  lg: 'rounded-lg',
  xl: 'rounded-xl',
  full: 'rounded-full',
}

const variantClasses: Record<SkeletonVariant, string> = {
  line: 'h-4 w-full',
  text: 'h-3 w-3/4',
  circle: 'h-10 w-10 rounded-full',
  square: 'h-10 w-10',
  card: 'h-24 w-full rounded-xl',
  avatar: 'h-8 w-8 rounded-full',
  button: 'h-9 w-24 rounded-lg',
  custom: '',
}
</script>
