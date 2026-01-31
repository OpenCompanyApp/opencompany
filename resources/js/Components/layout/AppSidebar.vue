<template>
  <aside
    :class="[
      'flex flex-col shrink-0 bg-neutral-100 dark:bg-neutral-950 transition-all duration-150',
      collapsed ? 'w-16' : 'w-60',
      variant === 'floating' && 'my-3 ml-3 rounded-lg border border-neutral-200 dark:border-neutral-800 shadow-md',
      variant === 'minimal' && 'bg-transparent',
      className
    ]"
  >
    <!-- Header -->
    <div class="flex items-center justify-between p-3">
      <!-- Collapse toggle / New chat button -->
      <Tooltip
        :text="collapsed ? 'Expand sidebar' : 'Toggle sidebar'"
        :delay-open="300"
      >
        <button
          :class="[
            'p-2 rounded-lg transition-colors duration-150 outline-none',
            'hover:bg-neutral-200 dark:hover:bg-neutral-800',
            'focus-visible:ring-1 focus-visible:ring-neutral-400',
          ]"
          @click="handleCollapse"
        >
          <Icon
            name="ph:sidebar-simple"
            class="w-5 h-5 text-neutral-600 dark:text-neutral-300"
          />
        </button>
      </Tooltip>

      <!-- New action button -->
      <Tooltip v-if="!collapsed" text="New chat" :delay-open="300">
        <button
          class="p-2 rounded-lg hover:bg-neutral-200 dark:hover:bg-neutral-800 transition-colors duration-150 outline-none focus-visible:ring-1 focus-visible:ring-neutral-400"
          @click="$emit('searchClick')"
        >
          <Icon name="ph:pencil-simple-line" class="w-5 h-5 text-neutral-600 dark:text-neutral-300" />
        </button>
      </Tooltip>
    </div>

    <!-- Navigation slot -->
    <slot name="navigation">
      <SidebarNav :collapsed="collapsed" size="sm" :show-shortcuts="false" :show-quick-actions="false" />
    </slot>

    <!-- Bottom section -->
    <div class="mt-auto">
      <!-- Credits Display (compact) -->
      <div
        v-if="showCredits && !collapsed"
        class="mx-2 mb-2"
      >
        <button
          class="w-full flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-neutral-200 dark:hover:bg-neutral-800 transition-colors duration-150 text-left group"
          @click="$emit('creditsClick')"
        >
          <div class="relative">
            <Icon name="ph:coins" class="w-4 h-4 text-neutral-500 dark:text-neutral-400" />
            <span
              v-if="creditsPercentage <= 20"
              class="absolute -top-0.5 -right-0.5 w-2 h-2 bg-red-500 rounded-full"
            />
          </div>
          <span class="flex-1 text-sm text-neutral-600 dark:text-neutral-300">
            {{ formatCredits(creditsRemainingValue) }} credits
          </span>
        </button>
      </div>

      <!-- User Menu -->
      <slot name="user-menu">
        <UserMenu :collapsed="collapsed" size="sm" />
      </slot>
    </div>
  </aside>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import SidebarNav from '@/Components/layout/SidebarNav.vue'
import UserMenu from '@/Components/layout/UserMenu.vue'
import Icon from '@/Components/shared/Icon.vue'
import Tooltip from '@/Components/shared/Tooltip.vue'

// Types
type SidebarVariant = 'default' | 'floating' | 'minimal'

// Props
const props = withDefaults(defineProps<{
  variant?: SidebarVariant
  collapsed?: boolean
  showCredits?: boolean
  creditsRemaining?: number
  creditsTotal?: number
  class?: string
}>(), {
  variant: 'default',
  collapsed: false,
  showCredits: true,
  creditsRemaining: 1850,
  creditsTotal: 3000,
})

const className = computed(() => props.class)

// Computed
const creditsRemainingValue = computed(() => props.creditsRemaining ?? 1850)
const creditsTotalValue = computed(() => props.creditsTotal ?? 3000)
const creditsPercentage = computed(() => Math.round((creditsRemainingValue.value / creditsTotalValue.value) * 100))

// Methods
const formatCredits = (value: number): string => {
  if (value >= 1000) {
    return `${(value / 1000).toFixed(1)}k`
  }
  return value.toLocaleString()
}

const emit = defineEmits<{
  searchClick: []
  creditsClick: []
  'update:collapsed': [collapsed: boolean]
}>()

const handleCollapse = () => {
  emit('update:collapsed', !props.collapsed)
}
</script>

<style scoped>
/* Minimal styling */
</style>
