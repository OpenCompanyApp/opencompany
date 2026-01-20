<template>
  <component
    :is="as"
    :type="as === 'button' ? type : undefined"
    :disabled="disabled || loading"
    :class="[
      'inline-flex items-center justify-center gap-2 font-medium transition-all',
      'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50 focus-visible:ring-offset-2 focus-visible:ring-offset-olympus-bg',
      'disabled:opacity-50 disabled:cursor-not-allowed',
      'active:scale-[0.98]',
      sizeClasses[size],
      variantClasses[variant],
      fullWidth && 'w-full',
    ]"
  >
    <Icon
      v-if="loading"
      name="ph:spinner"
      class="animate-spin"
      :class="iconSizeClasses[size]"
    />
    <Icon
      v-else-if="iconLeft"
      :name="iconLeft"
      :class="iconSizeClasses[size]"
    />
    <slot />
    <Icon
      v-if="iconRight && !loading"
      :name="iconRight"
      :class="iconSizeClasses[size]"
    />
  </component>
</template>

<script setup lang="ts">
type ButtonVariant = 'primary' | 'secondary' | 'ghost' | 'danger' | 'success'
type ButtonSize = 'sm' | 'md' | 'lg'

withDefaults(defineProps<{
  variant?: ButtonVariant
  size?: ButtonSize
  type?: 'button' | 'submit' | 'reset'
  disabled?: boolean
  loading?: boolean
  iconLeft?: string
  iconRight?: string
  fullWidth?: boolean
  as?: 'button' | 'a' | 'div'
}>(), {
  variant: 'primary',
  size: 'md',
  type: 'button',
  disabled: false,
  loading: false,
  fullWidth: false,
  as: 'button',
})

const sizeClasses = {
  sm: 'h-8 px-3 text-sm rounded-md',
  md: 'h-10 px-4 text-sm rounded-lg',
  lg: 'h-12 px-6 text-base rounded-lg',
}

const iconSizeClasses = {
  sm: 'w-3.5 h-3.5',
  md: 'w-4 h-4',
  lg: 'w-5 h-5',
}

const variantClasses = {
  primary: [
    'bg-olympus-primary text-white shadow-lg shadow-olympus-primary/25',
    'hover:bg-olympus-primary-hover hover:shadow-olympus-primary/40 hover:shadow-xl',
  ].join(' '),
  secondary: [
    'bg-olympus-surface text-olympus-text border border-olympus-border',
    'hover:bg-olympus-elevated hover:border-olympus-border-subtle',
  ].join(' '),
  ghost: [
    'text-olympus-text-muted',
    'hover:bg-olympus-surface hover:text-olympus-text',
  ].join(' '),
  danger: [
    'bg-red-500/10 text-red-400 border border-red-500/20',
    'hover:bg-red-500/20 hover:border-red-500/30 hover:shadow-lg hover:shadow-red-500/20',
  ].join(' '),
  success: [
    'bg-green-500 text-white shadow-lg shadow-green-500/25',
    'hover:bg-green-600 hover:shadow-green-500/40 hover:shadow-xl',
  ].join(' '),
}
</script>
