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
      <!-- Logo or collapse toggle -->
      <Link v-if="!collapsed" href="/" class="flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-neutral-900 dark:bg-white flex items-center justify-center shrink-0">
          <span class="text-white dark:text-neutral-900 font-bold text-sm">O</span>
        </div>
        <span class="font-semibold text-neutral-900 dark:text-white tracking-tight">OpenCompany</span>
      </Link>

      <!-- Collapsed: show icon only -->
      <Link v-else href="/" class="p-2">
        <div class="w-8 h-8 rounded-lg bg-neutral-900 dark:bg-white flex items-center justify-center">
          <span class="text-white dark:text-neutral-900 font-bold text-sm">O</span>
        </div>
      </Link>

      <!-- Collapse toggle button -->
      <Tooltip
        v-if="!collapsed"
        text="Hide sidebar"
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
    </div>

    <!-- Expand button when collapsed -->
    <div v-if="collapsed" class="px-2 mb-2">
      <Tooltip text="Expand sidebar" side="right" :delay-open="300">
        <button
          class="w-full p-2 rounded-lg hover:bg-neutral-200 dark:hover:bg-neutral-800 transition-colors duration-150 outline-none focus-visible:ring-1 focus-visible:ring-neutral-400 flex items-center justify-center"
          @click="handleCollapse"
        >
          <Icon
            name="ph:sidebar-simple"
            class="w-5 h-5 text-neutral-600 dark:text-neutral-300"
          />
        </button>
      </Tooltip>
    </div>

    <!-- Navigation slot -->
    <slot name="navigation">
      <SidebarNav :collapsed="collapsed" size="sm" :show-shortcuts="false" :show-quick-actions="false" />
    </slot>

    <!-- Bottom section -->
    <div class="mt-auto">
      <!-- Config: Automation, Integrations, Settings -->
      <div :class="['space-y-0.5', collapsed ? 'px-2' : 'px-2']">
        <Link
          href="/automation"
          :class="[
            'group flex items-center rounded-lg transition-colors duration-150 outline-none',
            collapsed ? 'justify-center p-2' : 'gap-2.5 px-3 py-2',
            isActive('/automation')
              ? 'bg-neutral-200 dark:bg-neutral-800 text-neutral-900 dark:text-white'
              : 'hover:bg-neutral-200 dark:hover:bg-neutral-800 text-neutral-600 dark:text-neutral-300',
            'focus-visible:ring-1 focus-visible:ring-neutral-400',
          ]"
        >
          <Icon
            :name="isActive('/automation') ? 'ph:lightning-fill' : 'ph:lightning'"
            class="w-[18px] h-[18px] shrink-0"
          />
          <span v-if="!collapsed" class="text-sm truncate">Automation</span>
        </Link>
        <Link
          href="/integrations"
          :class="[
            'group flex items-center rounded-lg transition-colors duration-150 outline-none',
            collapsed ? 'justify-center p-2' : 'gap-2.5 px-3 py-2',
            isActive('/integrations')
              ? 'bg-neutral-200 dark:bg-neutral-800 text-neutral-900 dark:text-white'
              : 'hover:bg-neutral-200 dark:hover:bg-neutral-800 text-neutral-600 dark:text-neutral-300',
            'focus-visible:ring-1 focus-visible:ring-neutral-400',
          ]"
        >
          <Icon
            :name="isActive('/integrations') ? 'ph:plugs-connected-fill' : 'ph:plugs-connected'"
            class="w-[18px] h-[18px] shrink-0"
          />
          <span v-if="!collapsed" class="text-sm truncate">Integrations</span>
        </Link>
        <Link
          href="/settings"
          :class="[
            'group flex items-center rounded-lg transition-colors duration-150 outline-none',
            collapsed ? 'justify-center p-2' : 'gap-2.5 px-3 py-2',
            isActive('/settings')
              ? 'bg-neutral-200 dark:bg-neutral-800 text-neutral-900 dark:text-white'
              : 'hover:bg-neutral-200 dark:hover:bg-neutral-800 text-neutral-600 dark:text-neutral-300',
            'focus-visible:ring-1 focus-visible:ring-neutral-400',
          ]"
        >
          <Icon
            :name="isActive('/settings') ? 'ph:gear-fill' : 'ph:gear'"
            class="w-[18px] h-[18px] shrink-0"
          />
          <span v-if="!collapsed" class="text-sm truncate">Settings</span>
        </Link>
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
import { Link, usePage } from '@inertiajs/vue3'
import SidebarNav from '@/Components/layout/SidebarNav.vue'
import UserMenu from '@/Components/layout/UserMenu.vue'
import Icon from '@/Components/shared/Icon.vue'
import Tooltip from '@/Components/shared/Tooltip.vue'

// Types
type SidebarVariant = 'default' | 'floating' | 'minimal'

// Props
const props = withDefaults(defineProps<{
  variant?: SidebarVariant
  class?: string
}>(), {
  variant: 'default',
})

const collapsed = defineModel<boolean>('collapsed', { default: false })

const className = computed(() => props.class)
const page = usePage()

const isActive = (path: string): boolean => {
  return page.url.startsWith(path)
}

const emit = defineEmits<{
  searchClick: []
}>()

const handleCollapse = () => {
  collapsed.value = !collapsed.value
}
</script>

<style scoped>
/* Minimal styling */
</style>
