<template>
  <div
    :class="[
      'flex flex-col items-center justify-center text-center animate-in fade-in-0 slide-in-from-bottom-2 duration-300',
      sizeConfig[size].container
    ]"
  >
    <!-- Animated icon -->
    <div
      :class="[
        'group/icon relative mb-4 rounded-2xl bg-gray-100 flex items-center justify-center transition-all duration-300 ease-[cubic-bezier(0.34,1.56,0.64,1)] hover:scale-105 hover:bg-gray-200 cursor-default',
        sizeConfig[size].iconContainer
      ]"
    >
      <Icon
        :name="icon"
        :class="[
          'text-gray-400 transition-all duration-300 group-hover/icon:scale-110 group-hover/icon:text-gray-500',
          sizeConfig[size].icon
        ]"
      />

      <!-- Decorative rings -->
      <div class="absolute inset-0 rounded-2xl border border-gray-200/50 animate-ping opacity-20" />
      <div class="absolute -inset-2 rounded-3xl border border-dashed border-gray-200/30 transition-all duration-300 group-hover/icon:border-gray-200/50 group-hover/icon:scale-105" />
    </div>

    <!-- Title -->
    <h3 :class="['font-semibold text-gray-900 mb-1', sizeConfig[size].title]">
      {{ title }}
    </h3>

    <!-- Description -->
    <p :class="['text-gray-500 max-w-xs mx-auto', sizeConfig[size].description]">
      <template v-if="query">
        No results for "<span class="text-gray-900 font-medium">{{ query }}</span>"
      </template>
      <template v-else>
        {{ description }}
      </template>
    </p>

    <!-- Suggestions -->
    <div v-if="suggestions && suggestions.length > 0" class="mt-4">
      <p :class="['text-gray-400 mb-2', sizeConfig[size].suggestionsLabel]">
        Try these:
      </p>
      <div class="flex flex-wrap justify-center gap-2">
        <button
          v-for="(suggestion, index) in suggestions"
          :key="index"
          :class="[
            'group/suggestion flex items-center gap-1.5 bg-gray-100 hover:bg-gray-200 rounded-lg transition-all duration-300 ease-[cubic-bezier(0.34,1.56,0.64,1)] hover:scale-[1.02] hover:-translate-y-0.5 hover:shadow-sm active:scale-[0.98]',
            sizeConfig[size].suggestion
          ]"
          :style="{ animationDelay: `${index * 50}ms` }"
          @click="$emit('suggestion', suggestion)"
        >
          <Icon
            name="ph:arrow-bend-up-right"
            :class="[
              'text-gray-400 group-hover/suggestion:text-gray-900 transition-all duration-300 group-hover/suggestion:translate-x-0.5 group-hover/suggestion:-translate-y-0.5',
              sizeConfig[size].suggestionIcon
            ]"
          />
          <span class="text-gray-500 group-hover/suggestion:text-gray-900 transition-colors duration-300">
            {{ suggestion }}
          </span>
        </button>
      </div>
    </div>

    <!-- Tips section -->
    <div v-if="showTips" class="mt-6 pt-4 border-t border-gray-200 w-full">
      <p :class="['text-gray-400 mb-3', sizeConfig[size].tipsLabel]">
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
              'font-mono bg-gray-100 border border-gray-200 rounded',
              sizeConfig[size].tipKbd
            ]"
          >
            {{ tip.prefix }}
          </kbd>
          <span class="text-gray-500">{{ tip.description }}</span>
        </div>
      </div>
    </div>

    <!-- Action button -->
    <slot name="action">
      <button
        v-if="actionLabel"
        :class="[
          'group/action mt-4 flex items-center gap-2 bg-gray-900 text-white rounded-xl hover:bg-gray-900/90 transition-all duration-300 ease-[cubic-bezier(0.34,1.56,0.64,1)] font-medium hover:scale-105 hover:-translate-y-0.5 hover:shadow-lg hover:shadow-gray-900/30 active:scale-[0.98] active:translate-y-0',
          sizeConfig[size].action
        ]"
        @click="$emit('action')"
      >
        <Icon v-if="actionIcon" :name="actionIcon" class="w-4 h-4 transition-transform duration-300 group-hover/action:scale-110" />
        <span>{{ actionLabel }}</span>
      </button>
    </slot>
  </div>
</template>

<script setup lang="ts">
import { Icon } from '@iconify/vue'

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
