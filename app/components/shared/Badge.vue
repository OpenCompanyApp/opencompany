<template>
  <component
    :is="interactive ? 'button' : 'span'"
    :type="interactive ? 'button' : undefined"
    :class="[
      'inline-flex items-center gap-1.5 font-medium transition-all duration-150',
      sizeClasses[size],
      variantClasses[variant],
      pill ? 'rounded-full' : 'rounded-md',
      interactive && [
        'cursor-pointer outline-none',
        'focus-visible:ring-2 focus-visible:ring-olympus-primary/50',
        interactiveHoverClasses[variant],
      ],
    ]"
  >
    <Icon v-if="icon" :name="icon" :class="iconSizeClasses[size]" />
    <slot />
  </component>
</template>

<script setup lang="ts">
type BadgeVariant = 'default' | 'primary' | 'success' | 'warning' | 'error' | 'info'
type BadgeSize = 'sm' | 'md'

withDefaults(defineProps<{
  variant?: BadgeVariant
  size?: BadgeSize
  icon?: string
  pill?: boolean
  interactive?: boolean
}>(), {
  variant: 'default',
  size: 'sm',
  pill: false,
  interactive: false,
})

const sizeClasses = {
  sm: 'px-2 py-0.5 text-xs',
  md: 'px-2.5 py-1 text-sm',
}

const iconSizeClasses = {
  sm: 'w-3 h-3',
  md: 'w-3.5 h-3.5',
}

const variantClasses: Record<BadgeVariant, string> = {
  default: 'bg-olympus-surface text-olympus-text-muted border border-olympus-border',
  primary: 'bg-olympus-primary/15 text-olympus-primary border border-olympus-primary/20',
  success: 'bg-green-500/15 text-green-400 border border-green-500/20',
  warning: 'bg-amber-500/15 text-amber-400 border border-amber-500/20',
  error: 'bg-red-500/15 text-red-400 border border-red-500/20',
  info: 'bg-blue-500/15 text-blue-400 border border-blue-500/20',
}

const interactiveHoverClasses: Record<BadgeVariant, string> = {
  default: 'hover:bg-olympus-elevated hover:border-olympus-border-subtle',
  primary: 'hover:bg-olympus-primary/25 hover:border-olympus-primary/30',
  success: 'hover:bg-green-500/25 hover:border-green-500/30',
  warning: 'hover:bg-amber-500/25 hover:border-amber-500/30',
  error: 'hover:bg-red-500/25 hover:border-red-500/30',
  info: 'hover:bg-blue-500/25 hover:border-blue-500/30',
}
</script>
