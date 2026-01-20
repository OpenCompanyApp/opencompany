<template>
  <div
    :class="[
      'flex flex-col items-center justify-center text-center',
      paddingClasses[size],
    ]"
  >
    <!-- Icon -->
    <div
      :class="[
        'rounded-2xl flex items-center justify-center mb-4',
        'bg-olympus-surface border border-olympus-border-subtle',
        iconContainerClasses[size],
      ]"
    >
      <Icon
        :name="icon"
        :class="['text-olympus-text-subtle', iconClasses[size]]"
      />
    </div>

    <!-- Title -->
    <h3
      :class="[
        'font-medium text-olympus-text',
        titleClasses[size],
      ]"
    >
      {{ title }}
    </h3>

    <!-- Description -->
    <p
      v-if="description"
      :class="[
        'text-olympus-text-muted mt-1.5',
        descriptionClasses[size],
      ]"
    >
      {{ description }}
    </p>

    <!-- Action Button -->
    <SharedButton
      v-if="action"
      :variant="action.variant || 'primary'"
      :size="size === 'sm' ? 'sm' : 'md'"
      :icon-left="action.icon"
      class="mt-4"
      @click="action.onClick"
    >
      {{ action.label }}
    </SharedButton>

    <!-- Slot for custom content -->
    <slot />
  </div>
</template>

<script setup lang="ts">
type EmptyStateSize = 'sm' | 'md' | 'lg'

interface EmptyStateAction {
  label: string
  icon?: string
  variant?: 'primary' | 'secondary' | 'ghost'
  onClick: () => void
}

withDefaults(defineProps<{
  icon?: string
  title: string
  description?: string
  size?: EmptyStateSize
  action?: EmptyStateAction
}>(), {
  icon: 'ph:ghost',
  size: 'md',
})

const paddingClasses: Record<EmptyStateSize, string> = {
  sm: 'py-6 px-4',
  md: 'py-10 px-6',
  lg: 'py-16 px-8',
}

const iconContainerClasses: Record<EmptyStateSize, string> = {
  sm: 'w-10 h-10',
  md: 'w-14 h-14',
  lg: 'w-20 h-20',
}

const iconClasses: Record<EmptyStateSize, string> = {
  sm: 'w-5 h-5',
  md: 'w-7 h-7',
  lg: 'w-10 h-10',
}

const titleClasses: Record<EmptyStateSize, string> = {
  sm: 'text-sm',
  md: 'text-base',
  lg: 'text-lg',
}

const descriptionClasses: Record<EmptyStateSize, string> = {
  sm: 'text-xs max-w-48',
  md: 'text-sm max-w-64',
  lg: 'text-base max-w-80',
}
</script>
