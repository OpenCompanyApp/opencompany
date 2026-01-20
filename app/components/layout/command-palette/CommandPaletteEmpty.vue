<template>
  <div
    :class="[
      'flex flex-col items-center justify-center text-center',
      sizeConfig[size].container
    ]"
  >
    <!-- Animated icon -->
    <div
      :class="[
        'relative mb-4 rounded-2xl bg-olympus-surface flex items-center justify-center',
        sizeConfig[size].iconContainer
      ]"
    >
      <Icon
        :name="icon"
        :class="[
          'text-olympus-text-subtle animate-pulse',
          sizeConfig[size].icon
        ]"
      />

      <!-- Decorative rings -->
      <div class="absolute inset-0 rounded-2xl border border-olympus-border/50 animate-ping opacity-20" />
      <div class="absolute -inset-2 rounded-3xl border border-dashed border-olympus-border/30" />
    </div>

    <!-- Title -->
    <h3 :class="['font-semibold text-olympus-text mb-1', sizeConfig[size].title]">
      {{ title }}
    </h3>

    <!-- Description -->
    <p :class="['text-olympus-text-muted max-w-xs mx-auto', sizeConfig[size].description]">
      <template v-if="query">
        No results for "<span class="text-olympus-text font-medium">{{ query }}</span>"
      </template>
      <template v-else>
        {{ description }}
      </template>
    </p>

    <!-- Suggestions -->
    <div v-if="suggestions && suggestions.length > 0" class="mt-4">
      <p :class="['text-olympus-text-subtle mb-2', sizeConfig[size].suggestionsLabel]">
        Try these:
      </p>
      <div class="flex flex-wrap justify-center gap-2">
        <button
          v-for="(suggestion, index) in suggestions"
          :key="index"
          :class="[
            'flex items-center gap-1.5 bg-olympus-surface hover:bg-olympus-border rounded-lg transition-colors duration-150 group',
            sizeConfig[size].suggestion
          ]"
          @click="$emit('suggestion', suggestion)"
        >
          <Icon
            name="ph:arrow-bend-up-right"
            :class="[
              'text-olympus-text-subtle group-hover:text-olympus-primary transition-colors',
              sizeConfig[size].suggestionIcon
            ]"
          />
          <span class="text-olympus-text-muted group-hover:text-olympus-text transition-colors">
            {{ suggestion }}
          </span>
        </button>
      </div>
    </div>

    <!-- Tips section -->
    <div v-if="showTips" class="mt-6 pt-4 border-t border-olympus-border w-full">
      <p :class="['text-olympus-text-subtle mb-3', sizeConfig[size].tipsLabel]">
        Search tips
      </p>
      <div class="flex flex-wrap justify-center gap-3">
        <div
          v-for="tip in tips"
          :key="tip.prefix"
          class="flex items-center gap-2 text-sm"
        >
          <kbd
            :class="[
              'font-mono bg-olympus-surface border border-olympus-border rounded',
              sizeConfig[size].tipKbd
            ]"
          >
            {{ tip.prefix }}
          </kbd>
          <span class="text-olympus-text-muted">{{ tip.description }}</span>
        </div>
      </div>
    </div>

    <!-- Action button -->
    <slot name="action">
      <button
        v-if="actionLabel"
        :class="[
          'mt-4 flex items-center gap-2 bg-olympus-primary text-white rounded-lg hover:bg-olympus-primary/90 transition-colors duration-150 font-medium',
          sizeConfig[size].action
        ]"
        @click="$emit('action')"
      >
        <Icon v-if="actionIcon" :name="actionIcon" class="w-4 h-4" />
        <span>{{ actionLabel }}</span>
      </button>
    </slot>
  </div>
</template>

<script setup lang="ts">
// Types
type EmptySize = 'sm' | 'md' | 'lg'

interface Tip {
  prefix: string
  description: string
}

interface SizeConfig {
  container: string
  iconContainer: string
  icon: string
  title: string
  description: string
  suggestionsLabel: string
  suggestion: string
  suggestionIcon: string
  tipsLabel: string
  tipKbd: string
  action: string
}

// Props
withDefaults(defineProps<{
  icon?: string
  title?: string
  description?: string
  query?: string
  suggestions?: string[]
  showTips?: boolean
  actionLabel?: string
  actionIcon?: string
  size?: EmptySize
}>(), {
  icon: 'ph:magnifying-glass',
  title: 'No results found',
  description: 'Try searching with different keywords',
  showTips: true,
  size: 'md',
})

// Emits
defineEmits<{
  suggestion: [value: string]
  action: []
}>()

// Size configuration
const sizeConfig: Record<EmptySize, SizeConfig> = {
  sm: {
    container: 'py-6 px-4',
    iconContainer: 'w-12 h-12',
    icon: 'w-5 h-5',
    title: 'text-sm',
    description: 'text-xs',
    suggestionsLabel: 'text-[10px] uppercase tracking-wider',
    suggestion: 'px-2 py-1 text-xs',
    suggestionIcon: 'w-3 h-3',
    tipsLabel: 'text-[10px] uppercase tracking-wider',
    tipKbd: 'px-1.5 py-0.5 text-[10px]',
    action: 'px-3 py-1.5 text-xs',
  },
  md: {
    container: 'py-8 px-6',
    iconContainer: 'w-16 h-16',
    icon: 'w-7 h-7',
    title: 'text-base',
    description: 'text-sm',
    suggestionsLabel: 'text-xs uppercase tracking-wider',
    suggestion: 'px-3 py-1.5 text-sm',
    suggestionIcon: 'w-3.5 h-3.5',
    tipsLabel: 'text-xs uppercase tracking-wider',
    tipKbd: 'px-2 py-1 text-xs',
    action: 'px-4 py-2 text-sm',
  },
  lg: {
    container: 'py-10 px-8',
    iconContainer: 'w-20 h-20',
    icon: 'w-8 h-8',
    title: 'text-lg',
    description: 'text-base',
    suggestionsLabel: 'text-sm uppercase tracking-wider',
    suggestion: 'px-4 py-2 text-base',
    suggestionIcon: 'w-4 h-4',
    tipsLabel: 'text-sm uppercase tracking-wider',
    tipKbd: 'px-2.5 py-1 text-sm',
    action: 'px-5 py-2.5 text-base',
  },
}

// Search tips
const tips: Tip[] = [
  { prefix: '#', description: 'Search channels' },
  { prefix: '@', description: 'Search agents' },
  { prefix: '>', description: 'Run commands' },
  { prefix: '/', description: 'Quick actions' },
]
</script>
