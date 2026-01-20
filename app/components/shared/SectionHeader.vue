<template>
  <div :class="containerClasses">
    <!-- Left side: Toggle + Title + Count -->
    <component
      :is="collapsible ? 'button' : 'div'"
      :type="collapsible ? 'button' : undefined"
      :class="titleContainerClasses"
      @click="collapsible && handleToggle()"
    >
      <!-- Collapse Toggle -->
      <Transition name="rotate">
        <Icon
          v-if="collapsible"
          :name="collapseIcon"
          :class="[
            'shrink-0 transition-transform duration-200',
            iconSizeClasses[size],
            !collapsed && 'rotate-90',
            iconColorClasses,
          ]"
        />
      </Transition>

      <!-- Custom Icon -->
      <div
        v-if="icon && !collapsible"
        :class="customIconContainerClasses"
      >
        <Icon
          :name="icon"
          :class="[iconSizeClasses[size], iconColor || 'text-olympus-primary']"
        />
      </div>

      <!-- Title -->
      <span :class="titleClasses">
        {{ title }}
      </span>

      <!-- Count Badge -->
      <Transition name="scale">
        <SharedBadge
          v-if="count !== undefined"
          :size="badgeSize"
          :variant="countVariant"
        >
          {{ formattedCount }}
        </SharedBadge>
      </Transition>

      <!-- Beta/New Badge -->
      <SharedBadge
        v-if="badge"
        size="xs"
        :variant="badge === 'new' ? 'primary' : badge === 'beta' ? 'secondary' : 'default'"
        class="ml-1"
      >
        {{ badge.toUpperCase() }}
      </SharedBadge>

      <!-- Loading indicator -->
      <Icon
        v-if="loading"
        name="ph:spinner"
        :class="[iconSizeClasses[size], 'text-olympus-text-muted animate-spin ml-1']"
      />
    </component>

    <!-- Right side: Actions -->
    <div class="flex items-center gap-1">
      <!-- Search toggle (for searchable sections) -->
      <button
        v-if="searchable && !searchExpanded"
        type="button"
        :class="actionButtonClasses"
        :aria-label="searchLabel"
        @click="expandSearch"
      >
        <Icon name="ph:magnifying-glass" :class="actionIconClasses" />
      </button>

      <!-- Expanded Search -->
      <Transition name="expand">
        <div v-if="searchable && searchExpanded" class="flex items-center gap-1">
          <input
            ref="searchInputRef"
            v-model="searchModel"
            type="text"
            :placeholder="searchPlaceholder"
            class="w-32 h-7 px-2 text-xs bg-olympus-surface border border-olympus-border rounded-lg text-olympus-text placeholder:text-olympus-text-subtle focus:outline-none focus:border-olympus-primary transition-all duration-200"
            @keydown.escape="collapseSearch"
            @blur="handleSearchBlur"
          />
          <button
            type="button"
            :class="actionButtonClasses"
            aria-label="Close search"
            @click="collapseSearch"
          >
            <Icon name="ph:x" :class="actionIconClasses" />
          </button>
        </div>
      </Transition>

      <!-- Filter button -->
      <button
        v-if="filterable"
        type="button"
        :class="[actionButtonClasses, hasActiveFilters && 'text-olympus-primary bg-olympus-primary/10']"
        :aria-label="filterLabel"
        @click="$emit('filter')"
      >
        <Icon name="ph:funnel" :class="actionIconClasses" />
        <span
          v-if="hasActiveFilters && filterCount"
          class="absolute -top-1 -right-1 w-4 h-4 bg-olympus-primary text-white text-[10px] font-bold rounded-full flex items-center justify-center"
        >
          {{ filterCount > 9 ? '9+' : filterCount }}
        </span>
      </button>

      <!-- Sort button -->
      <button
        v-if="sortable"
        type="button"
        :class="actionButtonClasses"
        :aria-label="sortLabel"
        @click="$emit('sort')"
      >
        <Icon :name="sortIcon" :class="actionIconClasses" />
      </button>

      <!-- View toggle -->
      <div v-if="viewModes && viewModes.length > 1" class="flex items-center bg-olympus-surface rounded-lg p-0.5">
        <button
          v-for="mode in viewModes"
          :key="mode.value"
          type="button"
          :class="[
            'p-1 rounded-md transition-colors duration-150',
            currentViewMode === mode.value
              ? 'bg-olympus-elevated text-olympus-text'
              : 'text-olympus-text-muted hover:text-olympus-text',
          ]"
          :aria-label="mode.label"
          @click="$emit('update:viewMode', mode.value)"
        >
          <Icon :name="mode.icon" class="w-4 h-4" />
        </button>
      </div>

      <!-- Primary Action -->
      <button
        v-if="action"
        type="button"
        :class="[
          actionButtonClasses,
          action.variant === 'primary' && 'text-olympus-primary hover:bg-olympus-primary/10',
        ]"
        :aria-label="action.label || 'Action'"
        @click="handleAction"
      >
        <Icon :name="action.icon" :class="actionIconClasses" />
        <span v-if="action.showLabel" class="text-xs font-medium ml-1">
          {{ action.label }}
        </span>
      </button>

      <!-- More Actions Menu -->
      <DropdownMenuRoot v-if="moreActions && moreActions.length > 0">
        <DropdownMenuTrigger as-child>
          <button
            type="button"
            :class="actionButtonClasses"
            aria-label="More actions"
          >
            <Icon name="ph:dots-three" :class="actionIconClasses" />
          </button>
        </DropdownMenuTrigger>
        <DropdownMenuPortal>
          <DropdownMenuContent
            class="min-w-40 bg-olympus-elevated border border-olympus-border rounded-xl shadow-xl p-1 z-50 animate-in fade-in-0 zoom-in-95 duration-150"
            :side-offset="5"
          >
            <DropdownMenuItem
              v-for="item in moreActions"
              :key="item.label"
              :class="[
                'flex items-center gap-2 px-3 py-2 text-sm rounded-lg cursor-pointer outline-none transition-colors duration-150',
                item.variant === 'danger'
                  ? 'text-red-400 hover:bg-red-500/10 focus:bg-red-500/10'
                  : 'text-olympus-text-muted hover:bg-olympus-surface focus:bg-olympus-surface hover:text-olympus-text focus:text-olympus-text',
              ]"
              @click="item.onClick"
            >
              <Icon :name="item.icon" class="w-4 h-4" />
              {{ item.label }}
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenuPortal>
      </DropdownMenuRoot>

      <!-- Slot for custom actions -->
      <slot name="actions" />
    </div>
  </div>
</template>

<script setup lang="ts">
import {
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuPortal,
  DropdownMenuRoot,
  DropdownMenuTrigger,
} from 'reka-ui'

type SectionHeaderSize = 'sm' | 'md' | 'lg'
type CountVariant = 'default' | 'primary' | 'success' | 'warning' | 'error'
type SortDirection = 'asc' | 'desc' | 'none'

interface SectionHeaderAction {
  icon: string
  label?: string
  showLabel?: boolean
  variant?: 'default' | 'primary'
  onClick?: () => void
}

interface MoreAction {
  icon: string
  label: string
  variant?: 'default' | 'danger'
  onClick: () => void
}

interface ViewMode {
  value: string
  label: string
  icon: string
}

const props = withDefaults(defineProps<{
  // Core
  title: string
  count?: number
  countVariant?: CountVariant
  badge?: 'new' | 'beta' | string

  // Icon
  icon?: string
  iconColor?: string
  collapseIcon?: string

  // Appearance
  size?: SectionHeaderSize
  uppercase?: boolean
  sticky?: boolean
  bordered?: boolean

  // Collapsible
  collapsible?: boolean
  collapsed?: boolean

  // Search
  searchable?: boolean
  searchValue?: string
  searchPlaceholder?: string
  searchLabel?: string

  // Filter
  filterable?: boolean
  filterLabel?: string
  hasActiveFilters?: boolean
  filterCount?: number

  // Sort
  sortable?: boolean
  sortLabel?: string
  sortDirection?: SortDirection

  // View modes
  viewModes?: ViewMode[]
  viewMode?: string

  // Actions
  action?: SectionHeaderAction
  moreActions?: MoreAction[]

  // State
  loading?: boolean
}>(), {
  countVariant: 'default',
  collapseIcon: 'ph:caret-right-fill',
  size: 'md',
  uppercase: true,
  sticky: false,
  bordered: false,
  collapsible: false,
  collapsed: false,
  searchable: false,
  searchPlaceholder: 'Search...',
  searchLabel: 'Toggle search',
  filterable: false,
  filterLabel: 'Filter',
  hasActiveFilters: false,
  sortable: false,
  sortLabel: 'Sort',
  sortDirection: 'none',
  loading: false,
})

const emit = defineEmits<{
  'update:collapsed': [value: boolean]
  'update:searchValue': [value: string]
  'update:viewMode': [value: string]
  'search': [value: string]
  'filter': []
  'sort': []
  'action': []
}>()

const searchInputRef = ref<HTMLInputElement | null>(null)
const searchExpanded = ref(false)

// Search model
const searchModel = computed({
  get: () => props.searchValue || '',
  set: (value) => emit('update:searchValue', value),
})

// Current view mode
const currentViewMode = computed(() => props.viewMode)

// Size classes
const paddingClasses: Record<SectionHeaderSize, string> = {
  sm: 'px-2 py-1.5',
  md: 'px-2 py-2',
  lg: 'px-3 py-2.5',
}

const iconSizeClasses: Record<SectionHeaderSize, string> = {
  sm: 'w-3 h-3',
  md: 'w-3.5 h-3.5',
  lg: 'w-4 h-4',
}

const titleSizeClasses: Record<SectionHeaderSize, string> = {
  sm: 'text-[10px]',
  md: 'text-xs',
  lg: 'text-sm',
}

const badgeSize = computed(() => {
  if (props.size === 'lg') return 'sm'
  return 'xs'
})

// Sort icon
const sortIcon = computed(() => {
  if (props.sortDirection === 'asc') return 'ph:sort-ascending'
  if (props.sortDirection === 'desc') return 'ph:sort-descending'
  return 'ph:arrows-down-up'
})

// Formatted count
const formattedCount = computed(() => {
  if (props.count === undefined) return ''
  if (props.count >= 1000) {
    return `${(props.count / 1000).toFixed(1)}k`
  }
  return props.count.toString()
})

// Icon color classes
const iconColorClasses = computed(() => {
  if (props.collapsed) return 'text-olympus-text-muted'
  return 'text-olympus-text-subtle'
})

// Container classes
const containerClasses = computed(() => [
  'flex items-center justify-between',
  paddingClasses[props.size],
  props.sticky && 'sticky top-0 z-10 bg-olympus-bg/80 backdrop-blur-sm',
  props.bordered && 'border-b border-olympus-border mb-2',
])

// Title container classes
const titleContainerClasses = computed(() => [
  'flex items-center gap-1.5',
  props.collapsible && 'cursor-pointer group',
  props.collapsible && 'outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50 rounded',
  'transition-colors duration-150',
  props.collapsible && 'hover:text-olympus-text',
])

// Custom icon container classes
const customIconContainerClasses = computed(() => [
  'w-5 h-5 rounded flex items-center justify-center',
  'bg-olympus-surface',
])

// Title classes
const titleClasses = computed(() => [
  'font-semibold text-olympus-text-muted tracking-wider',
  titleSizeClasses[props.size],
  props.uppercase && 'uppercase',
  props.collapsible && 'group-hover:text-olympus-text transition-colors duration-150',
])

// Action button classes
const actionButtonClasses = computed(() => [
  'relative p-1.5 rounded-md transition-colors duration-150',
  'text-olympus-text-muted hover:text-olympus-text hover:bg-olympus-surface',
  'outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50',
])

// Action icon classes
const actionIconClasses = computed(() => [
  iconSizeClasses[props.size],
])

// Handlers
const handleToggle = () => {
  emit('update:collapsed', !props.collapsed)
}

const handleAction = () => {
  if (props.action?.onClick) {
    props.action.onClick()
  }
  emit('action')
}

const expandSearch = () => {
  searchExpanded.value = true
  nextTick(() => {
    searchInputRef.value?.focus()
  })
}

const collapseSearch = () => {
  searchExpanded.value = false
  searchModel.value = ''
}

const handleSearchBlur = () => {
  if (!searchModel.value) {
    searchExpanded.value = false
  }
}

// Watch search value changes
watch(searchModel, (value) => {
  emit('search', value)
})
</script>

<style scoped>
/* Rotate transition */
.rotate-enter-active,
.rotate-leave-active {
  transition: transform 0.2s ease;
}

/* Scale transition */
.scale-enter-active,
.scale-leave-active {
  transition: all 0.2s ease;
}

.scale-enter-from,
.scale-leave-to {
  opacity: 0;
  transform: scale(0.8);
}

/* Expand transition */
.expand-enter-active,
.expand-leave-active {
  transition: all 0.2s ease;
}

.expand-enter-from,
.expand-leave-to {
  opacity: 0;
  width: 0;
  transform: scaleX(0);
}
</style>
