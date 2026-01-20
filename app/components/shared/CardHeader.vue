<template>
  <div class="flex items-center justify-between">
    <div class="flex items-center gap-3">
      <!-- Icon Container -->
      <div
        v-if="icon"
        :class="[
          'w-9 h-9 rounded-lg flex items-center justify-center',
          iconBg || 'bg-olympus-primary/20'
        ]"
      >
        <Icon
          :name="icon"
          :class="['w-5 h-5', iconColor || 'text-olympus-primary']"
        />
      </div>

      <!-- Title & Subtitle -->
      <div>
        <h2
          :class="[
            'font-semibold',
            gradient ? 'text-gradient' : 'text-olympus-text',
            subtitle ? 'text-sm' : 'text-base'
          ]"
        >
          {{ title }}
        </h2>
        <p v-if="subtitle" class="text-xs text-olympus-text-muted">
          {{ subtitle }}
        </p>
      </div>
    </div>

    <!-- Action -->
    <button
      v-if="action"
      class="text-sm text-olympus-primary hover:text-olympus-primary-hover transition-colors duration-150 outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50 rounded px-2 py-1 -mr-2"
      @click="action.onClick"
    >
      <Icon v-if="action.icon" :name="action.icon" class="w-4 h-4 inline mr-1" />
      {{ action.label }}
    </button>

    <!-- Slot for custom actions -->
    <slot name="actions" />
  </div>
</template>

<script setup lang="ts">
interface CardHeaderAction {
  label: string
  icon?: string
  onClick: () => void
}

withDefaults(defineProps<{
  title: string
  subtitle?: string
  icon?: string
  iconColor?: string
  iconBg?: string
  gradient?: boolean
  action?: CardHeaderAction
}>(), {
  gradient: false,
})
</script>
