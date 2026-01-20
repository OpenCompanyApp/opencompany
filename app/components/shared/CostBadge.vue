<template>
  <span
    :class="[
      'inline-flex items-center gap-1 font-medium',
      sizeClasses[size],
      variant === 'actual' ? 'text-olympus-text' : 'text-olympus-text-muted'
    ]"
  >
    <Icon name="ph:coins" :class="iconSizes[size]" />
    <span>{{ variant === 'estimated' ? '~' : '' }}${{ cost.toFixed(2) }}</span>
  </span>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  cost: number
  size?: 'xs' | 'sm' | 'md'
  variant?: 'actual' | 'estimated'
}>(), {
  size: 'sm',
  variant: 'actual',
})

const sizeClasses = {
  xs: 'text-[10px]',
  sm: 'text-xs',
  md: 'text-sm',
}

const iconSizes = {
  xs: 'w-3 h-3',
  sm: 'w-3.5 h-3.5',
  md: 'w-4 h-4',
}
</script>
