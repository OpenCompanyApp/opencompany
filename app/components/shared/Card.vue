<template>
  <component
    :is="as"
    :type="as === 'button' ? 'button' : undefined"
    :class="[
      'bg-olympus-surface rounded-xl overflow-hidden transition-all duration-200',
      paddingClasses[padding],
      gradient ? 'card-gradient' : 'border border-olympus-border',
      hoverable && 'card-interactive cursor-pointer',
      interactive && 'active:scale-[0.99]',
      elevated && 'shadow-lg',
      glow && 'glow',
      as === 'button' && 'outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50 focus-visible:ring-offset-2 focus-visible:ring-offset-olympus-bg text-left',
    ]"
  >
    <slot />
  </component>
</template>

<script setup lang="ts">
type CardPadding = 'none' | 'sm' | 'md' | 'lg'

withDefaults(defineProps<{
  padding?: CardPadding
  hoverable?: boolean
  interactive?: boolean
  elevated?: boolean
  gradient?: boolean
  glow?: boolean
  as?: 'div' | 'article' | 'section' | 'button'
}>(), {
  padding: 'md',
  hoverable: false,
  interactive: false,
  elevated: false,
  gradient: false,
  glow: false,
  as: 'div',
})

const paddingClasses = {
  none: '',
  sm: 'p-3',
  md: 'p-4',
  lg: 'p-6',
}
</script>
