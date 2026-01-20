<template>
  <aside
    :class="[
      'flex flex-col shrink-0 bg-olympus-sidebar border-r border-olympus-border transition-all duration-300 ease-out',
      sizeConfig[size].width,
      collapsed && 'w-16',
      variant === 'floating' && 'my-3 ml-3 rounded-2xl border shadow-xl shadow-black/10',
      variant === 'minimal' && 'border-none bg-transparent',
      className
    ]"
  >
    <!-- Logo / Org Header -->
    <div
      :class="[
        'border-b border-olympus-border transition-all duration-300',
        sizeConfig[size].headerPadding,
        collapsed && 'px-3'
      ]"
    >
      <div class="flex items-center gap-3">
        <!-- Logo -->
        <TooltipRoot :delay-duration="300" :disabled="!collapsed">
          <TooltipTrigger as-child>
            <button
              :class="[
                'rounded-lg flex items-center justify-center shadow-lg transition-all duration-300 group relative overflow-hidden',
                sizeConfig[size].logo,
                'bg-olympus-primary shadow-olympus-primary/40',
                'hover:shadow-olympus-primary/60 hover:scale-105',
                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary focus-visible:ring-offset-2 focus-visible:ring-offset-olympus-sidebar',
                collapsed && 'mx-auto'
              ]"
              @click="$emit('logoClick')"
            >
              <!-- Animated glow effect -->
              <div class="absolute inset-0 bg-gradient-to-tr from-white/0 via-white/20 to-white/0 translate-x-[-100%] group-hover:translate-x-[100%] transition-transform duration-700" />

              <Icon :name="logoIcon" :class="['text-white relative z-10', sizeConfig[size].logoIcon]" />

              <!-- Pulse ring on activity -->
              <div
                v-if="hasActivity"
                class="absolute inset-0 rounded-lg bg-olympus-primary animate-ping opacity-50"
              />
            </button>
          </TooltipTrigger>
          <TooltipPortal>
            <TooltipContent
              :side="tooltipSide"
              :side-offset="8"
              class="glass px-3 py-1.5 rounded-lg text-sm font-medium shadow-xl animate-in fade-in-0 zoom-in-95 duration-150"
            >
              {{ organizationName }}
              <TooltipArrow class="fill-olympus-elevated" />
            </TooltipContent>
          </TooltipPortal>
        </TooltipRoot>

        <!-- Organization info -->
        <Transition
          enter-active-class="transition-all duration-300 ease-out"
          leave-active-class="transition-all duration-200 ease-in"
          enter-from-class="opacity-0 translate-x-[-8px]"
          leave-to-class="opacity-0 translate-x-[-8px]"
        >
          <div v-if="!collapsed" class="flex-1 min-w-0">
            <h1 :class="['font-semibold leading-tight truncate', sizeConfig[size].title]">
              {{ organizationName }}
            </h1>
            <p :class="['text-olympus-text-muted truncate', sizeConfig[size].subtitle]">
              {{ workspaceName }}
            </p>
          </div>
        </Transition>

        <!-- Collapse toggle -->
        <TooltipRoot :delay-duration="300">
          <TooltipTrigger as-child>
            <button
              v-if="collapsible"
              :class="[
                'p-2 rounded-lg transition-all duration-200 outline-none',
                'hover:bg-olympus-surface',
                'focus-visible:ring-2 focus-visible:ring-olympus-primary/50',
                collapsed && 'mx-auto mt-3'
              ]"
              @click="handleCollapse"
            >
              <Icon
                :name="collapsed ? 'ph:caret-double-right' : 'ph:caret-double-left'"
                class="w-4 h-4 text-olympus-text-muted transition-transform duration-300"
              />
            </button>
          </TooltipTrigger>
          <TooltipPortal>
            <TooltipContent
              :side="tooltipSide"
              :side-offset="8"
              class="glass px-3 py-1.5 rounded-lg text-sm shadow-xl animate-in fade-in-0 zoom-in-95 duration-150"
            >
              {{ collapsed ? 'Expand sidebar' : 'Collapse sidebar' }}
              <TooltipArrow class="fill-olympus-elevated" />
            </TooltipContent>
          </TooltipPortal>
        </TooltipRoot>

        <!-- Settings button -->
        <TooltipRoot :delay-duration="300">
          <TooltipTrigger as-child>
            <button
              v-if="!collapsed && showSettings"
              class="p-2 rounded-lg hover:bg-olympus-surface transition-colors duration-150 outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50 relative group"
              @click="$emit('settingsClick')"
            >
              <Icon name="ph:gear-six" class="w-4 h-4 text-olympus-text-muted group-hover:rotate-90 transition-transform duration-500" />

              <!-- Notification dot -->
              <span
                v-if="settingsNotification"
                class="absolute top-1.5 right-1.5 w-2 h-2 bg-olympus-warning rounded-full animate-pulse"
              />
            </button>
          </TooltipTrigger>
          <TooltipPortal>
            <TooltipContent
              :side="tooltipSide"
              :side-offset="8"
              class="glass px-3 py-1.5 rounded-lg text-sm shadow-xl animate-in fade-in-0 zoom-in-95 duration-150"
            >
              Settings
              <TooltipArrow class="fill-olympus-elevated" />
            </TooltipContent>
          </TooltipPortal>
        </TooltipRoot>
      </div>

      <!-- Quick search shortcut -->
      <Transition
        enter-active-class="transition-all duration-300 ease-out"
        leave-active-class="transition-all duration-200 ease-in"
        enter-from-class="opacity-0 h-0"
        leave-to-class="opacity-0 h-0"
      >
        <button
          v-if="!collapsed && showQuickSearch"
          class="w-full mt-3 flex items-center gap-2 px-3 py-2 rounded-lg bg-olympus-surface/50 hover:bg-olympus-surface border border-olympus-border/50 hover:border-olympus-border transition-all duration-200 text-sm text-olympus-text-muted group"
          @click="$emit('searchClick')"
        >
          <Icon name="ph:magnifying-glass" class="w-4 h-4 group-hover:scale-110 transition-transform" />
          <span class="flex-1 text-left">Search...</span>
          <div class="flex items-center gap-0.5">
            <kbd class="px-1.5 py-0.5 text-[10px] bg-olympus-elevated border border-olympus-border rounded font-mono">⌘</kbd>
            <kbd class="px-1.5 py-0.5 text-[10px] bg-olympus-elevated border border-olympus-border rounded font-mono">K</kbd>
          </div>
        </button>
      </Transition>
    </div>

    <!-- Navigation slot -->
    <slot name="navigation">
      <LayoutSidebarNav :collapsed="collapsed" :size="size" />
    </slot>

    <!-- Credits Display -->
    <div
      v-if="showCredits"
      :class="[
        'border-t border-olympus-border transition-all duration-300',
        sizeConfig[size].creditsPadding,
        collapsed && 'px-2'
      ]"
    >
      <TooltipRoot :delay-duration="300" :disabled="!collapsed">
        <TooltipTrigger as-child>
          <div
            :class="[
              'cursor-pointer rounded-lg transition-colors duration-200',
              collapsed && 'p-2 hover:bg-olympus-surface'
            ]"
            @click="$emit('creditsClick')"
          >
            <Transition
              enter-active-class="transition-all duration-300 ease-out"
              leave-active-class="transition-all duration-200 ease-in"
              enter-from-class="opacity-0"
              leave-to-class="opacity-0"
              mode="out-in"
            >
              <!-- Full credits display -->
              <div v-if="!collapsed" key="full">
                <div class="flex items-center justify-between text-sm mb-2">
                  <div class="flex items-center gap-2">
                    <Icon name="ph:coins" class="w-4 h-4 text-olympus-text-muted" />
                    <span class="text-olympus-text-muted">Credits</span>
                  </div>
                  <div class="flex items-center gap-2">
                    <span class="font-medium">{{ formatCredits(creditsRemaining) }}</span>
                    <span class="text-olympus-text-subtle">/ {{ formatCredits(creditsTotal) }}</span>
                  </div>
                </div>

                <!-- Progress bar -->
                <div class="h-1.5 bg-olympus-bg rounded-full overflow-hidden">
                  <div
                    :class="[
                      'h-full rounded-full transition-all duration-700 ease-out',
                      creditsPercentage > 50 && 'bg-gradient-to-r from-olympus-primary to-olympus-accent shadow-sm shadow-olympus-primary/50',
                      creditsPercentage <= 50 && creditsPercentage > 20 && 'bg-olympus-warning',
                      creditsPercentage <= 20 && 'bg-olympus-error animate-pulse'
                    ]"
                    :style="{ width: `${creditsPercentage}%` }"
                  />
                </div>

                <!-- Credits info row -->
                <div class="mt-2 flex items-center justify-between text-xs">
                  <span
                    :class="[
                      'flex items-center gap-1',
                      creditsPercentage > 50 && 'text-olympus-text-subtle',
                      creditsPercentage <= 50 && creditsPercentage > 20 && 'text-olympus-warning',
                      creditsPercentage <= 20 && 'text-olympus-error'
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
                    class="text-olympus-primary hover:underline font-medium"
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
                    creditsPercentage > 50 && 'text-olympus-primary',
                    creditsPercentage <= 50 && creditsPercentage > 20 && 'text-olympus-warning',
                    creditsPercentage <= 20 && 'text-olympus-error'
                  ]"
                >
                  <!-- Circular progress -->
                  <svg class="w-8 h-8 -rotate-90">
                    <circle
                      class="text-olympus-border"
                      stroke="currentColor"
                      stroke-width="2"
                      fill="transparent"
                      r="14"
                      cx="16"
                      cy="16"
                    />
                    <circle
                      class="transition-all duration-700"
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
            class="glass px-3 py-2 rounded-lg text-sm shadow-xl animate-in fade-in-0 zoom-in-95 duration-150"
          >
            <div class="font-medium">{{ formatCredits(creditsRemaining) }} credits remaining</div>
            <div class="text-olympus-text-muted text-xs mt-0.5">{{ creditsStatusText }}</div>
            <TooltipArrow class="fill-olympus-elevated" />
          </TooltipContent>
        </TooltipPortal>
      </TooltipRoot>
    </div>

    <!-- User Menu -->
    <slot name="user-menu">
      <LayoutUserMenu :collapsed="collapsed" :size="size" />
    </slot>

    <!-- Resize handle -->
    <div
      v-if="resizable && !collapsed && variant !== 'floating'"
      class="absolute top-0 right-0 w-1 h-full cursor-col-resize group"
      @mousedown="startResize"
    >
      <div class="w-full h-full bg-transparent hover:bg-olympus-primary/30 group-active:bg-olympus-primary/50 transition-colors duration-150" />
    </div>
  </aside>
</template>

<script setup lang="ts">
import {
  TooltipArrow,
  TooltipContent,
  TooltipPortal,
  TooltipRoot,
  TooltipTrigger,
} from 'reka-ui'

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

// Mock data
const { stats } = useMockData()

// Computed
const creditsRemaining = computed(() => props.creditsRemaining ?? stats.creditsRemaining)
const creditsTotal = computed(() => props.creditsTotal ?? 3000)
const creditsPercentage = computed(() => Math.round((creditsRemaining.value / creditsTotal.value) * 100))

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
.glow {
  animation: glow 3s ease-in-out infinite;
}

@keyframes glow {
  0%, 100% {
    box-shadow: 0 0 20px oklch(0.75 0.18 250 / 0.4);
  }
  50% {
    box-shadow: 0 0 30px oklch(0.75 0.18 250 / 0.6);
  }
}
</style>
