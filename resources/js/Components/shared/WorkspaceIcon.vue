<template>
  <div
    :class="[
      'flex items-center justify-center shrink-0 rounded-lg',
      sizeClasses[size],
      colorBgMap[color] ?? colorBgMap.neutral,
    ]"
  >
    <Icon :name="icon || 'ph:buildings'" :class="iconSizeClasses[size]" class="text-white" />
  </div>
</template>

<script setup lang="ts">
import Icon from './Icon.vue'

withDefaults(defineProps<{
  icon?: string
  color?: string
  size?: 'xs' | 'sm' | 'md' | 'lg'
}>(), {
  icon: 'ph:buildings',
  color: 'neutral',
  size: 'md',
})

const sizeClasses: Record<string, string> = {
  xs: 'w-5 h-5',
  sm: 'w-6 h-6',
  md: 'w-8 h-8',
  lg: 'w-12 h-12',
}

const iconSizeClasses: Record<string, string> = {
  xs: 'w-3 h-3',
  sm: 'w-3.5 h-3.5',
  md: 'w-4.5 h-4.5',
  lg: 'w-6 h-6',
}

const colorBgMap: Record<string, string> = {
  neutral: 'bg-neutral-500',
  blue: 'bg-blue-500',
  green: 'bg-green-500',
  yellow: 'bg-yellow-500',
  orange: 'bg-orange-500',
  red: 'bg-red-500',
  purple: 'bg-purple-500',
  pink: 'bg-pink-500',
}
</script>
