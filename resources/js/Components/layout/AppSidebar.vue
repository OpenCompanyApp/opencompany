<template>
  <aside
    :class="[
      'flex flex-col shrink-0 bg-white border-r border-gray-200 transition-all duration-150',
      sizeConfig[size].width,
      collapsed && 'w-16',
      variant === 'floating' && 'my-3 ml-3 rounded-lg border shadow-md',
      variant === 'minimal' && 'border-none bg-transparent',
      className
    ]"
  >
    <!-- Logo / Org Header -->
    <div
      :class="[
        'border-b border-gray-200 transition-all duration-150',
        sizeConfig[size].headerPadding,
        collapsed && 'px-3'
      ]"
    >
      <div class="flex items-center gap-3">
        <!-- Logo -->
        <TooltipProvider :delay-duration="300">
          <TooltipRoot :disabled="!collapsed">
            <TooltipTrigger as-child>
              <button
                :class="[
                  'rounded-lg flex items-center justify-center transition-colors duration-150 group',
                  sizeConfig[size].logo,
                  'bg-gray-900',
                  'hover:bg-gray-800',
                  'focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-gray-400',
                  collapsed && 'mx-auto'
                ]"
                @click="$emit('logoClick')"
              >
                <Icon :name="logoIcon" :class="['text-white', sizeConfig[size].logoIcon]" />

                <!-- Activity indicator dot -->
                <div
                  v-if="hasActivity"
                  class="absolute -top-0.5 -right-0.5 w-2.5 h-2.5 bg-green-500 rounded-full border-2 border-white"
                />
              </button>
            </TooltipTrigger>
            <TooltipPortal>
              <TooltipContent
                :side="tooltipSide"
                :side-offset="8"
                class="bg-white border border-gray-200 px-3 py-1.5 rounded-lg text-sm font-medium shadow-md animate-in fade-in-0 duration-150"
              >
                {{ organizationName }}
                <TooltipArrow class="fill-white" />
              </TooltipContent>
            </TooltipPortal>
          </TooltipRoot>
        </TooltipProvider>

        <!-- Organization info -->
        <Transition
          enter-active-class="transition-all duration-150 ease-out"
          leave-active-class="transition-all duration-100 ease-in"
          enter-from-class="opacity-0"
          leave-to-class="opacity-0"
        >
          <div v-if="!collapsed" class="flex-1 min-w-0">
            <h1 :class="['font-semibold leading-tight truncate text-gray-900', sizeConfig[size].title]">
              {{ organizationName }}
            </h1>
            <p :class="['text-gray-500 truncate', sizeConfig[size].subtitle]">
              {{ workspaceName }}
            </p>
          </div>
        </Transition>

        <!-- Collapse toggle -->
        <TooltipProvider :delay-duration="300">
          <TooltipRoot>
            <TooltipTrigger as-child>
              <button
                v-if="collapsible"
                :class="[
                  'p-2 rounded-lg transition-colors duration-150 outline-none',
                  'hover:bg-gray-100',
                  'focus-visible:ring-1 focus-visible:ring-gray-400',
                  collapsed && 'mx-auto mt-3'
                ]"
                @click="handleCollapse"
              >
                <Icon
                  :name="collapsed ? 'ph:caret-double-right' : 'ph:caret-double-left'"
                  class="w-4 h-4 text-gray-500 transition-transform duration-150"
                />
              </button>
            </TooltipTrigger>
            <TooltipPortal>
              <TooltipContent
                :side="tooltipSide"
                :side-offset="8"
                class="bg-white border border-gray-200 px-3 py-1.5 rounded-lg text-sm shadow-md animate-in fade-in-0 duration-150"
              >
                {{ collapsed ? 'Expand sidebar' : 'Collapse sidebar' }}
                <TooltipArrow class="fill-white" />
              </TooltipContent>
            </TooltipPortal>
          </TooltipRoot>
        </TooltipProvider>

        <!-- Notification bell -->
        <NotificationBell v-if="!collapsed && showNotifications" />

        <!-- Settings button -->
        <TooltipProvider :delay-duration="300">
          <TooltipRoot>
            <TooltipTrigger as-child>
              <button
                v-if="!collapsed && showSettings"
                class="p-2 rounded-lg hover:bg-gray-100 transition-colors duration-150 outline-none focus-visible:ring-1 focus-visible:ring-gray-400 relative group"
                @click="$emit('settingsClick')"
              >
                <Icon name="ph:gear-six" class="w-4 h-4 text-gray-500 group-hover:text-gray-900 transition-colors duration-150" />

                <!-- Notification dot -->
                <span
                  v-if="settingsNotification"
                  class="absolute top-1.5 right-1.5 w-2 h-2 bg-amber-500 rounded-full"
                />
              </button>
            </TooltipTrigger>
            <TooltipPortal>
              <TooltipContent
                :side="tooltipSide"
                :side-offset="8"
                class="bg-white border border-gray-200 px-3 py-1.5 rounded-lg text-sm shadow-md animate-in fade-in-0 duration-150"
              >
                Settings
                <TooltipArrow class="fill-white" />
              </TooltipContent>
            </TooltipPortal>
          </TooltipRoot>
        </TooltipProvider>
      </div>

      <!-- Quick search shortcut -->
      <Transition
        enter-active-class="transition-all duration-150 ease-out"
        leave-active-class="transition-all duration-100 ease-in"
        enter-from-class="opacity-0"
        leave-to-class="opacity-0"
      >
        <button
          v-if="!collapsed && showQuickSearch"
          class="w-full mt-3 flex items-center gap-2 px-3 py-2 rounded-lg bg-gray-50 hover:bg-gray-100 border border-gray-200 transition-colors duration-150 text-sm text-gray-500 group"
          @click="$emit('searchClick')"
        >
          <Icon name="ph:magnifying-glass" class="w-4 h-4" />
          <span class="flex-1 text-left">Search...</span>
          <div class="flex items-center gap-0.5">
            <kbd class="px-1.5 py-0.5 text-[10px] bg-white border border-gray-200 rounded font-mono text-gray-400">Cmd</kbd>
            <kbd class="px-1.5 py-0.5 text-[10px] bg-white border border-gray-200 rounded font-mono text-gray-400">K</kbd>
          </div>
        </button>
      </Transition>
    </div>

    <!-- Navigation slot -->
    <slot name="navigation">
      <SidebarNav :collapsed="collapsed" :size="size" />
    </slot>

    <!-- Credits Display -->
    <div
      v-if="showCredits"
      :class="[
        'border-t border-gray-200 transition-all duration-150',
        sizeConfig[size].creditsPadding,
        collapsed && 'px-2'
      ]"
    >
      <TooltipProvider :delay-duration="300">
        <TooltipRoot :disabled="!collapsed">
          <TooltipTrigger as-child>
            <div
              :class="[
                'cursor-pointer rounded-lg transition-colors duration-150',
                collapsed && 'p-2 hover:bg-gray-100'
              ]"
              @click="$emit('creditsClick')"
            >
              <Transition
                enter-active-class="transition-all duration-150 ease-out"
                leave-active-class="transition-all duration-100 ease-in"
                enter-from-class="opacity-0"
                leave-to-class="opacity-0"
                mode="out-in"
              >
                <!-- Full credits display -->
                <div v-if="!collapsed" key="full">
                  <div class="flex items-center justify-between text-sm mb-2">
                    <div class="flex items-center gap-2">
                      <Icon name="ph:coins" class="w-4 h-4 text-gray-500" />
                      <span class="text-gray-500">Credits</span>
                    </div>
                    <div class="flex items-center gap-2">
                      <span class="font-medium text-gray-900">{{ formatCredits(creditsRemainingValue) }}</span>
                      <span class="text-gray-400">/ {{ formatCredits(creditsTotalValue) }}</span>
                    </div>
                  </div>

                  <!-- Progress bar -->
                  <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                    <div
                      :class="[
                        'h-full rounded-full transition-all duration-300 ease-out',
                        creditsPercentage > 50 && 'bg-gray-600',
                        creditsPercentage <= 50 && creditsPercentage > 20 && 'bg-amber-500',
                        creditsPercentage <= 20 && 'bg-red-500'
                      ]"
                      :style="{ width: `${creditsPercentage}%` }"
                    />
                  </div>

                  <!-- Credits info row -->
                  <div class="mt-2 flex items-center justify-between text-xs">
                    <span
                      :class="[
                        'flex items-center gap-1',
                        creditsPercentage > 50 && 'text-gray-400',
                        creditsPercentage <= 50 && creditsPercentage > 20 && 'text-amber-600',
                        creditsPercentage <= 20 && 'text-red-600'
                      ]"
                    >
                      <Icon
                        v-if="creditsPercentage <= 20"
                        name="ph:warning-circle"
                        class="w-3 h-3"
                      />
                      {{ creditsStatusText }}
                    </span>
                    <button
                      v-if="showUpgradeButton"
                      class="text-gray-900 hover:underline font-medium"
                      @click.stop="$emit('upgradeClick')"
                    >
                      Upgrade
                    </button>
                  </div>
                </div>

                <!-- Collapsed credits display -->
                <div v-else key="collapsed" class="flex flex-col items-center gap-1">
                  <div
                    :class="[
                      'w-8 h-8 rounded-full flex items-center justify-center relative',
                      creditsPercentage > 50 && 'text-gray-600',
                      creditsPercentage <= 50 && creditsPercentage > 20 && 'text-amber-500',
                      creditsPercentage <= 20 && 'text-red-500'
                    ]"
                  >
                    <!-- Circular progress -->
                    <svg class="w-8 h-8 -rotate-90">
                      <circle
                        class="text-gray-200"
                        stroke="currentColor"
                        stroke-width="2"
                        fill="transparent"
                        r="14"
                        cx="16"
                        cy="16"
                      />
                      <circle
                        class="transition-all duration-300"
                        stroke="currentColor"
                        stroke-width="2"
                        fill="transparent"
                        r="14"
                        cx="16"
                        cy="16"
                        :stroke-dasharray="88"
                        :stroke-dashoffset="88 - (88 * creditsPercentage) / 100"
                      />
                    </svg>
                    <Icon name="ph:coins" class="w-3 h-3 absolute" />
                  </div>
                </div>
              </Transition>
            </div>
          </TooltipTrigger>
          <TooltipPortal>
            <TooltipContent
              :side="tooltipSide"
              :side-offset="8"
              class="bg-white border border-gray-200 px-3 py-2 rounded-lg text-sm shadow-md animate-in fade-in-0 duration-150"
            >
              <div class="font-medium text-gray-900">{{ formatCredits(creditsRemainingValue) }} credits remaining</div>
              <div class="text-gray-500 text-xs mt-0.5">{{ creditsStatusText }}</div>
              <TooltipArrow class="fill-white" />
            </TooltipContent>
          </TooltipPortal>
        </TooltipRoot>
      </TooltipProvider>
    </div>

    <!-- User Menu -->
    <slot name="user-menu">
      <UserMenu :collapsed="collapsed" :size="size" />
    </slot>

    <!-- Resize handle -->
    <div
      v-if="resizable && !collapsed && variant !== 'floating'"
      class="absolute top-0 right-0 w-1 h-full cursor-col-resize group"
      @mousedown="startResize"
    >
      <div class="w-full h-full bg-transparent hover:bg-gray-300 group-active:bg-gray-400 transition-colors duration-150" />
    </div>
  </aside>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import {
  TooltipArrow,
  TooltipContent,
  TooltipPortal,
  TooltipProvider,
  TooltipRoot,
  TooltipTrigger,
} from 'reka-ui'
import NotificationBell from '@/Components/notifications/NotificationBell.vue'
import SidebarNav from '@/Components/layout/SidebarNav.vue'
import UserMenu from '@/Components/layout/UserMenu.vue'

// Types
type SidebarSize = 'sm' | 'md' | 'lg'
type SidebarVariant = 'default' | 'floating' | 'minimal'

interface SidebarSizeConfig {
  width: string
  headerPadding: string
  creditsPadding: string
  logo: string
  logoIcon: string
  title: string
  subtitle: string
}

// Props
const props = withDefaults(defineProps<{
  size?: SidebarSize
  variant?: SidebarVariant
  organizationName?: string
  workspaceName?: string
  logoIcon?: string
  collapsed?: boolean
  collapsible?: boolean
  resizable?: boolean
  showSettings?: boolean
  settingsNotification?: boolean
  showQuickSearch?: boolean
  showCredits?: boolean
  creditsRemaining?: number
  creditsTotal?: number
  showUpgradeButton?: boolean
  hasActivity?: boolean
  showNotifications?: boolean
  class?: string
}>(), {
  size: 'md',
  variant: 'default',
  organizationName: 'Olympus',
  workspaceName: 'Bloom Agency',
  logoIcon: 'ph:lightning-fill',
  collapsed: false,
  collapsible: true,
  resizable: false,
  showSettings: true,
  settingsNotification: false,
  showQuickSearch: true,
  showCredits: true,
  creditsRemaining: 1850,
  creditsTotal: 3000,
  showUpgradeButton: true,
  hasActivity: false,
  showNotifications: true,
})

const className = computed(() => props.class)

// Size configuration
const sizeConfig: Record<SidebarSize, SidebarSizeConfig> = {
  sm: {
    width: 'w-56',
    headerPadding: 'p-3',
    creditsPadding: 'p-3',
    logo: 'w-8 h-8',
    logoIcon: 'w-4 h-4',
    title: 'text-sm',
    subtitle: 'text-[11px]',
  },
  md: {
    width: 'w-64',
    headerPadding: 'p-4',
    creditsPadding: 'p-4',
    logo: 'w-10 h-10',
    logoIcon: 'w-5 h-5',
    title: 'text-base',
    subtitle: 'text-xs',
  },
  lg: {
    width: 'w-72',
    headerPadding: 'p-5',
    creditsPadding: 'p-5',
    logo: 'w-12 h-12',
    logoIcon: 'w-6 h-6',
    title: 'text-lg',
    subtitle: 'text-sm',
  },
}

// Computed
const creditsRemainingValue = computed(() => props.creditsRemaining ?? 1850)
const creditsTotalValue = computed(() => props.creditsTotal ?? 3000)
const creditsPercentage = computed(() => Math.round((creditsRemainingValue.value / creditsTotalValue.value) * 100))

const creditsStatusText = computed(() => {
  if (creditsPercentage.value > 50) return `${creditsPercentage.value}% remaining`
  if (creditsPercentage.value > 20) return 'Running low on credits'
  return 'Credits critically low!'
})

const tooltipSide = computed(() => props.collapsed ? 'right' : 'top')

// Methods
const formatCredits = (value: number): string => {
  if (value >= 1000) {
    return `${(value / 1000).toFixed(1)}k`
  }
  return value.toLocaleString()
}

const emit = defineEmits<{
  logoClick: []
  settingsClick: []
  searchClick: []
  creditsClick: []
  upgradeClick: []
  'update:collapsed': [collapsed: boolean]
  resize: [width: number]
}>()

const handleCollapse = () => {
  emit('update:collapsed', !props.collapsed)
}

// Resize handling
const startResize = (event: MouseEvent) => {
  event.preventDefault()

  const startX = event.clientX
  const startWidth = 256 // Default md width

  const onMouseMove = (e: MouseEvent) => {
    const diff = e.clientX - startX
    const newWidth = Math.min(Math.max(startWidth + diff, 200), 400)
    emit('resize', newWidth)
  }

  const onMouseUp = () => {
    document.removeEventListener('mousemove', onMouseMove)
    document.removeEventListener('mouseup', onMouseUp)
  }

  document.addEventListener('mousemove', onMouseMove)
  document.addEventListener('mouseup', onMouseUp)
}
</script>

<style scoped>
/* Minimal styling */
</style>
