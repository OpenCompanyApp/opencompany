<template>
  <div :class="containerClasses">
    <!-- Header -->
    <div v-if="showHeader" :class="headerClasses">
      <div class="flex items-center gap-2">
        <div :class="headerIconClasses">
          <Icon name="ph:lightning-fill" :class="headerIconInnerClasses" />
        </div>
        <div>
          <h2 :class="titleClasses">{{ title }}</h2>
          <p v-if="subtitle" :class="subtitleClasses">{{ subtitle }}</p>
        </div>
      </div>

      <!-- Customize Button -->
      <TooltipProvider v-if="customizable" :delay-duration="300">
        <TooltipRoot>
          <TooltipTrigger as-child>
            <button
              type="button"
              :class="customizeButtonClasses"
              @click="showCustomize = true"
            >
              <Icon name="ph:gear" class="w-3.5 h-3.5" />
            </button>
          </TooltipTrigger>
          <TooltipPortal>
            <TooltipContent class="z-50 bg-white border border-gray-200 rounded-lg px-2.5 py-1.5 text-xs shadow-md" side="bottom">
              Customize actions
              <TooltipArrow class="fill-white" />
            </TooltipContent>
          </TooltipPortal>
        </TooltipRoot>
      </TooltipProvider>
    </div>

    <!-- Loading State -->
    <div v-if="loading" :class="gridClasses">
      <ActionSkeleton v-for="i in 4" :key="i" />
    </div>

    <!-- Actions Grid/List -->
    <div v-else :class="contentClasses">
      <TransitionGroup :name="animated ? 'action-list' : ''" tag="div" :class="gridClasses">
        <button
          v-for="(action, index) in displayedActions"
          :key="action.id || action.label"
          type="button"
          :class="actionButtonClasses(action, index)"
          :disabled="action.disabled || action.loading"
          @click="handleActionClick(action)"
          @mouseenter="hoveredAction = action.id || action.label"
          @mouseleave="hoveredAction = null"
        >
          <!-- Hover indicator line -->
          <div
            :class="[
              'absolute left-0 top-0 bottom-0 w-0.5 rounded-r bg-gray-900 transition-opacity duration-150',
              (hoveredAction === (action.id || action.label))
                ? 'opacity-100'
                : 'opacity-0',
            ]"
          />

          <!-- Icon Container -->
          <div
            :class="[
              iconContainerClasses,
              'bg-gray-100',
            ]"
          >
            <Icon
              v-if="action.loading"
              name="ph:spinner"
              :class="[iconClasses, 'text-gray-600 animate-spin']"
            />
            <Icon
              v-else
              :name="action.icon"
              :class="[iconClasses, 'text-gray-600']"
            />
          </div>

          <!-- Text Content -->
          <div class="flex-1 min-w-0 text-left">
            <div class="flex items-center gap-2">
              <p :class="labelClasses">{{ action.label }}</p>

              <!-- New Badge -->
              <span
                v-if="action.isNew"
                class="px-1.5 py-0.5 text-[9px] font-semibold rounded bg-gray-100 text-gray-600"
              >
                NEW
              </span>

              <!-- Beta Badge -->
              <span
                v-if="action.isBeta"
                class="px-1.5 py-0.5 text-[9px] font-semibold rounded bg-gray-100 text-gray-600"
              >
                BETA
              </span>

              <!-- Keyboard Shortcut -->
              <span
                v-if="action.shortcut && showShortcuts"
                :class="shortcutClasses"
              >
                {{ action.shortcut }}
              </span>
            </div>
            <p :class="descriptionClasses">{{ action.description }}</p>

            <!-- Usage Stats (optional) -->
            <div v-if="showUsageStats && action.usageCount !== undefined" class="mt-1 flex items-center gap-1.5">
              <div class="h-1 flex-1 bg-gray-100 rounded-full overflow-hidden max-w-20">
                <div
                  class="h-full bg-gray-400 rounded-full"
                  :style="{ width: `${Math.min((action.usageCount / maxUsageCount) * 100, 100)}%` }"
                />
              </div>
              <span class="text-[9px] text-gray-400">
                {{ action.usageCount }} uses
              </span>
            </div>
          </div>

          <!-- Right Side -->
          <div class="flex items-center gap-2 shrink-0">
            <!-- Cost indicator -->
            <span
              v-if="action.cost !== undefined"
              class="text-[10px] text-gray-400"
            >
              ~${{ action.cost.toFixed(2) }}
            </span>

            <!-- Arrow Icon -->
            <Icon
              name="ph:caret-right"
              :class="arrowIconClasses"
            />
          </div>

          <!-- Pinned Indicator -->
          <div
            v-if="action.pinned"
            class="absolute top-1 right-1"
          >
            <Icon name="ph:push-pin-fill" class="w-3 h-3 text-gray-400" />
          </div>
        </button>
      </TransitionGroup>

      <!-- Show More Button -->
      <button
        v-if="showMoreButton && hiddenActionsCount > 0"
        type="button"
        :class="showMoreButtonClasses"
        @click="showAll = !showAll"
      >
        <Icon :name="showAll ? 'ph:caret-up' : 'ph:caret-down'" class="w-4 h-4" />
        <span>{{ showAll ? 'Show less' : `Show ${hiddenActionsCount} more` }}</span>
      </button>
    </div>

    <!-- Recent Actions Section -->
    <div v-if="showRecent && recentActions.length > 0" :class="recentSectionClasses">
      <div class="flex items-center justify-between mb-2">
        <span class="text-[10px] uppercase tracking-wide text-gray-400 font-medium">
          Recent
        </span>
        <button
          type="button"
          class="text-[10px] text-gray-400 hover:text-gray-900 transition-colors duration-150"
          @click="emit('clearRecent')"
        >
          Clear
        </button>
      </div>
      <div class="flex flex-wrap gap-1">
        <button
          v-for="action in recentActions.slice(0, 3)"
          :key="`recent-${action.id || action.label}`"
          type="button"
          :class="recentActionClasses"
          @click="handleActionClick(action)"
        >
          <Icon :name="action.icon" class="w-3 h-3" />
          <span>{{ action.label }}</span>
        </button>
      </div>
    </div>

    <!-- Customize Modal -->
    <Teleport to="body">
      <Transition name="modal">
        <div v-if="showCustomize" class="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div class="absolute inset-0 bg-black/50" @click="showCustomize = false" />
          <div :class="modalClasses">
            <div class="flex items-center justify-between p-4 border-b border-gray-200">
              <h3 class="font-semibold text-gray-900">Customize Quick Actions</h3>
              <button
                type="button"
                class="p-1.5 rounded-lg text-gray-500 hover:text-gray-900 hover:bg-gray-100 transition-colors duration-150"
                @click="showCustomize = false"
              >
                <Icon name="ph:x" class="w-4 h-4" />
              </button>
            </div>

            <div class="p-4 max-h-96 overflow-y-auto">
              <p class="text-sm text-gray-500 mb-4">
                Drag to reorder. Toggle visibility with the checkbox.
              </p>

              <div class="space-y-2">
                <div
                  v-for="action in allActions"
                  :key="`customize-${action.id || action.label}`"
                  :class="customizeItemClasses"
                >
                  <Icon name="ph:dots-six-vertical" class="w-4 h-4 text-gray-400 cursor-grab" />
                  <div :class="[iconContainerClasses, 'bg-gray-100 w-8 h-8']">
                    <Icon :name="action.icon" class="w-4 h-4 text-gray-600" />
                  </div>
                  <div class="flex-1">
                    <p class="text-sm font-medium text-gray-900">{{ action.label }}</p>
                    <p class="text-xs text-gray-500">{{ action.description }}</p>
                  </div>
                  <label class="relative inline-flex items-center cursor-pointer">
                    <input
                      type="checkbox"
                      :checked="!action.hidden"
                      class="sr-only peer"
                      @change="toggleActionVisibility(action)"
                    />
                    <div class="w-9 h-5 bg-gray-200 rounded-full peer peer-checked:bg-gray-900 transition-colors duration-150" />
                    <div class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full transition-transform duration-150 peer-checked:translate-x-4" />
                  </label>
                </div>
              </div>
            </div>

            <div class="flex justify-end gap-2 p-4 border-t border-gray-200">
              <Button variant="secondary" size="sm" @click="showCustomize = false">
                Cancel
              </Button>
              <Button size="sm" @click="saveCustomization">
                Save
              </Button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script lang="ts">
// Types and defaults must be in regular script block for defineProps() hoisting
export type QuickActionsSize = 'sm' | 'md' | 'lg'
export type QuickActionsVariant = 'default' | 'compact' | 'grid'

export interface QuickAction {
  id?: string
  label: string
  description: string
  icon: string
  bgClass?: string
  iconClass?: string
  shortcut?: string
  action?: () => void | Promise<void>
  isNew?: boolean
  isBeta?: boolean
  disabled?: boolean
  loading?: boolean
  hidden?: boolean
  pinned?: boolean
  usageCount?: number
  cost?: number
}

export const defaultActions: QuickAction[] = [
  {
    id: 'new-channel',
    label: 'New Channel',
    description: 'Create a collaboration space',
    icon: 'ph:chats-circle',
    shortcut: '\u2318N',
  },
  {
    id: 'spawn-agent',
    label: 'Spawn Agent',
    description: 'Deploy a new AI worker',
    icon: 'ph:robot-fill',
    shortcut: '\u2318A',
  },
  {
    id: 'create-task',
    label: 'Create Task',
    description: 'Assign work to your team',
    icon: 'ph:check-square-fill',
    shortcut: '\u2318T',
  },
  {
    id: 'new-document',
    label: 'New Document',
    description: 'Write a new document',
    icon: 'ph:file-plus-fill',
    shortcut: '\u2318D',
  },
]
</script>

<script setup lang="ts">
import { ref, computed, h, defineComponent, onMounted, onUnmounted } from 'vue'
import {
  TooltipArrow,
  TooltipContent,
  TooltipPortal,
  TooltipProvider,
  TooltipRoot,
  TooltipTrigger,
} from 'reka-ui'
import Button from '@/Components/shared/Button.vue'
import Skeleton from '@/Components/shared/Skeleton.vue'
import Icon from '@/Components/shared/Icon.vue'

const props = withDefaults(defineProps<{
  // Core
  actions?: QuickAction[]

  // Appearance
  size?: QuickActionsSize
  variant?: QuickActionsVariant

  // Display options
  showHeader?: boolean
  showShortcuts?: boolean
  showRecent?: boolean
  showUsageStats?: boolean
  showMoreButton?: boolean

  // Content
  title?: string
  subtitle?: string

  // Behavior
  customizable?: boolean
  maxVisible?: number
  animated?: boolean

  // State
  loading?: boolean

  // Recent
  recentActions?: QuickAction[]
}>(), {
  actions: () => defaultActions,
  size: 'md',
  variant: 'default',
  showHeader: true,
  showShortcuts: true,
  showRecent: false,
  showUsageStats: false,
  showMoreButton: false,
  title: 'Quick Actions',
  subtitle: undefined,
  customizable: false,
  maxVisible: 4,
  animated: true,
  loading: false,
  recentActions: () => [],
})

const emit = defineEmits<{
  actionClick: [action: QuickAction]
  clearRecent: []
  customizeChange: [actions: QuickAction[]]
}>()

// State
const hoveredAction = ref<string | null>(null)
const showAll = ref(false)
const showCustomize = ref(false)
const allActions = ref<QuickAction[]>([...props.actions])


// Size configuration
const sizeConfig: Record<QuickActionsSize, {
  container: string
  iconContainer: string
  icon: string
  label: string
  description: string
  gap: string
  padding: string
}> = {
  sm: {
    container: 'p-3',
    iconContainer: 'w-8 h-8',
    icon: 'w-4 h-4',
    label: 'text-xs',
    description: 'text-[10px]',
    gap: 'gap-2',
    padding: 'p-2.5',
  },
  md: {
    container: 'p-4',
    iconContainer: 'w-10 h-10',
    icon: 'w-5 h-5',
    label: 'text-sm',
    description: 'text-xs',
    gap: 'gap-3',
    padding: 'p-3',
  },
  lg: {
    container: 'p-5',
    iconContainer: 'w-12 h-12',
    icon: 'w-6 h-6',
    label: 'text-base',
    description: 'text-sm',
    gap: 'gap-4',
    padding: 'p-4',
  },
}

// Computed values
const visibleActions = computed(() => {
  return props.actions.filter(a => !a.hidden)
})

const displayedActions = computed(() => {
  if (showAll.value || !props.showMoreButton) return visibleActions.value
  return visibleActions.value.slice(0, props.maxVisible)
})

const hiddenActionsCount = computed(() => {
  return Math.max(0, visibleActions.value.length - props.maxVisible)
})

const maxUsageCount = computed(() => {
  return Math.max(...props.actions.map(a => a.usageCount || 0), 1)
})

// Container classes
const containerClasses = computed(() => [
  'bg-white border border-gray-200 rounded-lg',
  sizeConfig[props.size].container,
])

// Header classes
const headerClasses = computed(() => [
  'flex items-center justify-between mb-4',
])

const headerIconClasses = computed(() => [
  'rounded-lg flex items-center justify-center bg-gray-100',
  'transition-colors duration-150',
  sizeConfig[props.size].iconContainer,
])

const headerIconInnerClasses = computed(() => [
  'text-gray-600',
  sizeConfig[props.size].icon,
])

const titleClasses = computed(() => [
  'font-semibold text-gray-900',
  sizeConfig[props.size].label,
])

const subtitleClasses = computed(() => [
  'text-gray-500',
  sizeConfig[props.size].description,
])

const customizeButtonClasses = computed(() => [
  'p-1.5 rounded-lg outline-none',
  'transition-colors duration-150',
  'text-gray-500 hover:text-gray-900 hover:bg-gray-100',
  'focus-visible:ring-1 focus-visible:ring-gray-400',
])

// Content classes
const contentClasses = computed(() => [
  'space-y-1.5',
])

// Grid classes
const gridClasses = computed(() => {
  if (props.variant === 'grid') {
    return 'grid grid-cols-2 gap-2'
  }
  return 'space-y-1.5'
})

// Icon container classes
const iconContainerClasses = computed(() => [
  'rounded-lg flex items-center justify-center',
  'transition-colors duration-150',
  sizeConfig[props.size].iconContainer,
])

const iconClasses = computed(() => [
  sizeConfig[props.size].icon,
])

// Action button classes
const actionButtonClasses = (action: QuickAction, _index: number) => [
  'relative w-full flex items-center rounded-lg overflow-hidden',
  'bg-gray-50 hover:bg-gray-100',
  'border border-gray-200 hover:border-gray-300',
  'transition-colors duration-150 text-left group outline-none',
  'focus-visible:ring-1 focus-visible:ring-gray-400',
  sizeConfig[props.size].gap,
  sizeConfig[props.size].padding,
  action.disabled && 'opacity-50 cursor-not-allowed pointer-events-none',
  action.pinned && 'ring-1 ring-gray-300',
]

// Text classes
const labelClasses = computed(() => [
  'font-medium text-gray-900',
  sizeConfig[props.size].label,
])

const descriptionClasses = computed(() => [
  'text-gray-500',
  sizeConfig[props.size].description,
])

const shortcutClasses = computed(() => [
  'text-[10px] text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded',
  'border border-gray-200 font-mono',
  'opacity-0 group-hover:opacity-100',
  'transition-opacity duration-150',
])

const arrowIconClasses = computed(() => [
  'w-4 h-4 text-gray-400',
  'opacity-0 group-hover:opacity-100',
  'transition-opacity duration-150',
])

// Show more button classes
const showMoreButtonClasses = computed(() => [
  'w-full flex items-center justify-center gap-2 py-2 mt-2',
  'text-xs text-gray-500 hover:text-gray-900',
  'transition-colors duration-150',
  'rounded-lg hover:bg-gray-100',
])

// Recent section classes
const recentSectionClasses = computed(() => [
  'mt-4 pt-4 border-t border-gray-200',
])

const recentActionClasses = computed(() => [
  'flex items-center gap-1.5 px-2 py-1 rounded-lg',
  'bg-gray-50 hover:bg-gray-100',
  'border border-gray-200 hover:border-gray-300',
  'text-xs text-gray-500 hover:text-gray-900',
  'transition-colors duration-150',
])

// Modal classes
const modalClasses = computed(() => [
  'relative bg-white border border-gray-200 rounded-lg',
  'w-full max-w-md shadow-lg',
])

// Customize item classes
const customizeItemClasses = computed(() => [
  'flex items-center gap-3 p-3 rounded-lg bg-gray-50 border border-gray-200',
  'transition-colors duration-150',
  'hover:border-gray-300',
])

// Handlers
const handleActionClick = async (action: QuickAction) => {
  if (action.disabled || action.loading) return

  emit('actionClick', action)

  if (action.action) {
    await action.action()
  }
}

const toggleActionVisibility = (action: QuickAction) => {
  const index = allActions.value.findIndex(a => (a.id || a.label) === (action.id || action.label))
  if (index !== -1) {
    allActions.value[index] = {
      ...allActions.value[index],
      hidden: !allActions.value[index].hidden,
    }
  }
}

const saveCustomization = () => {
  emit('customizeChange', allActions.value)
  showCustomize.value = false
}

// Action Skeleton component
const ActionSkeleton = defineComponent({
  name: 'ActionSkeleton',
  setup() {
    return () => h('div', {
      class: 'flex items-center gap-3 p-3 rounded-lg bg-gray-50 border border-gray-200',
    }, [
      h(Skeleton, { customClass: 'w-10 h-10 rounded-lg' }),
      h('div', { class: 'flex-1 space-y-2' }, [
        h(Skeleton, { customClass: 'h-4 w-24' }),
        h(Skeleton, { customClass: 'h-3 w-32' }),
      ]),
      h(Skeleton, { customClass: 'w-4 h-4' }),
    ])
  },
})

// Keyboard shortcut handler
onMounted(() => {
  const handleKeydown = (e: KeyboardEvent) => {
    if (!props.showShortcuts) return

    for (const action of props.actions) {
      if (action.shortcut && action.action) {
        const parts = action.shortcut.toLowerCase().split('')
        const needsMeta = parts.includes('\u2318') || parts.includes('cmd')
        const needsShift = parts.includes('\u21e7') || parts.includes('shift')
        const key = parts[parts.length - 1]

        if (
          (needsMeta ? e.metaKey || e.ctrlKey : true) &&
          (needsShift ? e.shiftKey : true) &&
          e.key.toLowerCase() === key
        ) {
          e.preventDefault()
          handleActionClick(action)
          break
        }
      }
    }
  }

  window.addEventListener('keydown', handleKeydown)
  onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown)
  })
})
</script>

<style scoped>
/* Action list transitions */
.action-list-enter-active {
  transition: opacity 0.15s ease-out;
}

.action-list-leave-active {
  transition: opacity 0.1s ease-out;
}

.action-list-enter-from,
.action-list-leave-to {
  opacity: 0;
}

.action-list-move {
  transition: transform 0.15s ease-out;
}

/* Modal transitions */
.modal-enter-active {
  transition: opacity 0.15s ease-out;
}

.modal-leave-active {
  transition: opacity 0.1s ease-out;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}
</style>
