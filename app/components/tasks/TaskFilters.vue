<template>
  <div
    :class="[
      'flex items-center justify-between',
      sizeConfig[size].container
    ]"
  >
    <!-- Left side: Filters -->
    <div :class="['flex items-center', sizeConfig[size].filtersGap]">
      <!-- Search Input -->
      <div class="relative">
        <Icon
          name="ph:magnifying-glass"
          :class="[
            'absolute left-3 top-1/2 -translate-y-1/2 text-olympus-text-muted transition-colors',
            sizeConfig[size].searchIcon,
            searchFocused && 'text-olympus-primary'
          ]"
        />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search tasks..."
          :class="[
            'bg-olympus-surface border border-olympus-border rounded-xl pl-9 pr-3 outline-none transition-all duration-200',
            'placeholder:text-olympus-text-subtle',
            'focus:border-olympus-primary focus:ring-2 focus:ring-olympus-primary/20',
            sizeConfig[size].searchInput
          ]"
          @focus="searchFocused = true"
          @blur="searchFocused = false"
        />
        <!-- Clear button -->
        <Transition
          enter-active-class="transition-all duration-150"
          leave-active-class="transition-all duration-100"
          enter-from-class="opacity-0 scale-75"
          leave-to-class="opacity-0 scale-75"
        >
          <button
            v-if="searchQuery"
            :class="[
              'absolute right-2 top-1/2 -translate-y-1/2 p-1 rounded-full hover:bg-olympus-border transition-colors',
              sizeConfig[size].clearButton
            ]"
            @click="searchQuery = ''"
          >
            <Icon name="ph:x" :class="['text-olympus-text-muted', sizeConfig[size].clearIcon]" />
          </button>
        </Transition>
      </div>

      <!-- Divider -->
      <div :class="['w-px bg-olympus-border', sizeConfig[size].divider]" />

      <!-- Status Filter -->
      <SelectRoot v-model="selectedFilter">
        <SelectTrigger
          :class="[
            'inline-flex items-center bg-olympus-surface border border-olympus-border rounded-xl transition-all duration-200',
            'hover:border-olympus-primary/50 focus:border-olympus-primary focus:ring-2 focus:ring-olympus-primary/20',
            sizeConfig[size].select
          ]"
        >
          <Icon name="ph:funnel" :class="['text-olympus-text-muted', sizeConfig[size].selectIcon]" />
          <SelectValue placeholder="All tasks" />
          <Icon name="ph:caret-down" :class="['text-olympus-text-muted transition-transform', sizeConfig[size].selectIcon]" />
        </SelectTrigger>

        <SelectPortal>
          <SelectContent
            :class="[
              'bg-olympus-elevated border border-olympus-border rounded-xl shadow-xl z-50',
              'animate-in fade-in-0 zoom-in-95 duration-150',
              sizeConfig[size].selectContent
            ]"
            position="popper"
            :side-offset="8"
          >
            <SelectViewport>
              <SelectItem
                v-for="option in filterOptions"
                :key="option.value"
                :value="option.value"
                :class="[
                  'flex items-center rounded-lg cursor-pointer outline-none transition-colors duration-150',
                  'hover:bg-olympus-surface data-[highlighted]:bg-olympus-surface',
                  sizeConfig[size].selectItem
                ]"
              >
                <SelectItemIndicator class="w-4 shrink-0">
                  <Icon name="ph:check" :class="['text-olympus-primary', sizeConfig[size].checkIcon]" />
                </SelectItemIndicator>
                <Icon :name="option.icon" :class="['text-olympus-text-muted mr-2', sizeConfig[size].optionIcon]" />
                <SelectItemText>{{ option.label }}</SelectItemText>
                <span
                  v-if="option.count !== undefined"
                  :class="[
                    'ml-auto text-olympus-text-subtle bg-olympus-surface rounded-full',
                    sizeConfig[size].optionCount
                  ]"
                >
                  {{ option.count }}
                </span>
              </SelectItem>
            </SelectViewport>
          </SelectContent>
        </SelectPortal>
      </SelectRoot>

      <!-- Priority Filter -->
      <SelectRoot v-model="selectedPriority">
        <SelectTrigger
          :class="[
            'inline-flex items-center bg-olympus-surface border border-olympus-border rounded-xl transition-all duration-200',
            'hover:border-olympus-primary/50 focus:border-olympus-primary focus:ring-2 focus:ring-olympus-primary/20',
            sizeConfig[size].select
          ]"
        >
          <Icon name="ph:flag" :class="['text-olympus-text-muted', sizeConfig[size].selectIcon]" />
          <SelectValue placeholder="Any priority" />
          <Icon name="ph:caret-down" :class="['text-olympus-text-muted', sizeConfig[size].selectIcon]" />
        </SelectTrigger>

        <SelectPortal>
          <SelectContent
            :class="[
              'bg-olympus-elevated border border-olympus-border rounded-xl shadow-xl z-50',
              'animate-in fade-in-0 zoom-in-95 duration-150',
              sizeConfig[size].selectContent
            ]"
            position="popper"
            :side-offset="8"
          >
            <SelectViewport>
              <SelectItem
                v-for="option in priorityOptions"
                :key="option.value"
                :value="option.value"
                :class="[
                  'flex items-center rounded-lg cursor-pointer outline-none transition-colors duration-150',
                  'hover:bg-olympus-surface data-[highlighted]:bg-olympus-surface',
                  sizeConfig[size].selectItem
                ]"
              >
                <SelectItemIndicator class="w-4 shrink-0">
                  <Icon name="ph:check" :class="['text-olympus-primary', sizeConfig[size].checkIcon]" />
                </SelectItemIndicator>
                <span
                  v-if="option.color"
                  :class="['w-2 h-2 rounded-full mr-2', option.color]"
                />
                <SelectItemText>{{ option.label }}</SelectItemText>
              </SelectItem>
            </SelectViewport>
          </SelectContent>
        </SelectPortal>
      </SelectRoot>

      <!-- Assignee Filter -->
      <SelectRoot v-model="selectedAssignee">
        <SelectTrigger
          :class="[
            'inline-flex items-center bg-olympus-surface border border-olympus-border rounded-xl transition-all duration-200',
            'hover:border-olympus-primary/50 focus:border-olympus-primary focus:ring-2 focus:ring-olympus-primary/20',
            sizeConfig[size].select
          ]"
        >
          <Icon name="ph:user" :class="['text-olympus-text-muted', sizeConfig[size].selectIcon]" />
          <SelectValue placeholder="Anyone" />
          <Icon name="ph:caret-down" :class="['text-olympus-text-muted', sizeConfig[size].selectIcon]" />
        </SelectTrigger>

        <SelectPortal>
          <SelectContent
            :class="[
              'bg-olympus-elevated border border-olympus-border rounded-xl shadow-xl z-50',
              'animate-in fade-in-0 zoom-in-95 duration-150',
              sizeConfig[size].selectContent
            ]"
            position="popper"
            :side-offset="8"
          >
            <SelectViewport>
              <SelectItem
                v-for="option in assigneeOptions"
                :key="option.value"
                :value="option.value"
                :class="[
                  'flex items-center rounded-lg cursor-pointer outline-none transition-colors duration-150',
                  'hover:bg-olympus-surface data-[highlighted]:bg-olympus-surface',
                  sizeConfig[size].selectItem
                ]"
              >
                <SelectItemIndicator class="w-4 shrink-0">
                  <Icon name="ph:check" :class="['text-olympus-primary', sizeConfig[size].checkIcon]" />
                </SelectItemIndicator>
                <div
                  v-if="option.avatar"
                  :class="[
                    'rounded-full bg-olympus-primary text-white flex items-center justify-center font-medium mr-2',
                    sizeConfig[size].avatar
                  ]"
                >
                  {{ option.avatar }}
                </div>
                <Icon
                  v-else
                  name="ph:users"
                  :class="['text-olympus-text-muted mr-2', sizeConfig[size].optionIcon]"
                />
                <SelectItemText>{{ option.label }}</SelectItemText>
              </SelectItem>
            </SelectViewport>
          </SelectContent>
        </SelectPortal>
      </SelectRoot>

      <!-- Active Filters -->
      <TransitionGroup
        tag="div"
        :class="['flex items-center', sizeConfig[size].activeFilters]"
        enter-active-class="transition-all duration-200"
        leave-active-class="transition-all duration-150"
        enter-from-class="opacity-0 scale-90 -ml-2"
        leave-to-class="opacity-0 scale-90 -ml-2"
      >
        <button
          v-for="filter in activeFilters"
          :key="filter.key"
          :class="[
            'flex items-center bg-olympus-primary/10 text-olympus-primary rounded-lg transition-colors',
            'hover:bg-olympus-primary/20',
            sizeConfig[size].filterChip
          ]"
          @click="clearFilter(filter.key)"
        >
          <span>{{ filter.label }}</span>
          <Icon name="ph:x" :class="['ml-1', sizeConfig[size].filterChipIcon]" />
        </button>

        <!-- Clear all -->
        <button
          v-if="activeFilters.length > 1"
          :class="[
            'text-olympus-text-muted hover:text-olympus-text transition-colors font-medium',
            sizeConfig[size].clearAll
          ]"
          @click="clearAllFilters"
        >
          Clear all
        </button>
      </TransitionGroup>
    </div>

    <!-- Right side: View options + Actions -->
    <div :class="['flex items-center', sizeConfig[size].actionsGap]">
      <!-- Sort Options -->
      <SelectRoot v-model="selectedSort">
        <SelectTrigger
          :class="[
            'inline-flex items-center bg-olympus-surface border border-olympus-border rounded-xl transition-all duration-200',
            'hover:border-olympus-primary/50',
            sizeConfig[size].sortSelect
          ]"
        >
          <Icon name="ph:sort-ascending" :class="['text-olympus-text-muted', sizeConfig[size].selectIcon]" />
          <SelectValue placeholder="Sort by" />
        </SelectTrigger>

        <SelectPortal>
          <SelectContent
            :class="[
              'bg-olympus-elevated border border-olympus-border rounded-xl shadow-xl z-50',
              'animate-in fade-in-0 zoom-in-95 duration-150',
              sizeConfig[size].selectContent
            ]"
            position="popper"
            :side-offset="8"
          >
            <SelectViewport>
              <SelectItem
                v-for="option in sortOptions"
                :key="option.value"
                :value="option.value"
                :class="[
                  'flex items-center rounded-lg cursor-pointer outline-none transition-colors duration-150',
                  'hover:bg-olympus-surface data-[highlighted]:bg-olympus-surface',
                  sizeConfig[size].selectItem
                ]"
              >
                <SelectItemIndicator class="w-4 shrink-0">
                  <Icon name="ph:check" :class="['text-olympus-primary', sizeConfig[size].checkIcon]" />
                </SelectItemIndicator>
                <Icon :name="option.icon" :class="['text-olympus-text-muted mr-2', sizeConfig[size].optionIcon]" />
                <SelectItemText>{{ option.label }}</SelectItemText>
              </SelectItem>
            </SelectViewport>
          </SelectContent>
        </SelectPortal>
      </SelectRoot>

      <!-- View Toggle -->
      <div
        :class="[
          'flex items-center bg-olympus-surface border border-olympus-border rounded-xl p-1',
          sizeConfig[size].viewToggle
        ]"
      >
        <TooltipProvider :delay-duration="300">
          <TooltipRoot v-for="view in viewOptions" :key="view.value">
            <TooltipTrigger as-child>
              <button
                :class="[
                  'rounded-lg transition-all duration-150',
                  sizeConfig[size].viewButton,
                  selectedView === view.value
                    ? 'bg-olympus-primary text-white shadow-sm'
                    : 'text-olympus-text-muted hover:text-olympus-text hover:bg-olympus-border/50'
                ]"
                @click="selectedView = view.value"
              >
                <Icon :name="view.icon" :class="sizeConfig[size].viewIcon" />
              </button>
            </TooltipTrigger>
            <TooltipPortal>
              <TooltipContent
                class="bg-olympus-elevated border border-olympus-border rounded-lg px-2 py-1 text-xs shadow-lg z-50"
                :side-offset="8"
              >
                {{ view.label }}
                <TooltipArrow class="fill-olympus-elevated" />
              </TooltipContent>
            </TooltipPortal>
          </TooltipRoot>
        </TooltipProvider>
      </div>

      <!-- Divider -->
      <div :class="['w-px bg-olympus-border', sizeConfig[size].divider]" />

      <!-- New Task Button -->
      <button
        :class="[
          'flex items-center bg-olympus-primary text-white rounded-xl font-medium transition-all duration-200',
          'hover:bg-olympus-primary-hover hover:shadow-lg hover:shadow-olympus-primary/25',
          'active:scale-[0.98]',
          sizeConfig[size].newTaskButton
        ]"
        @click="$emit('newTask')"
      >
        <Icon name="ph:plus-bold" :class="sizeConfig[size].newTaskIcon" />
        <span v-if="!compact">New Task</span>
      </button>

      <!-- More Options -->
      <DropdownMenuRoot>
        <DropdownMenuTrigger
          :class="[
            'p-2 rounded-xl bg-olympus-surface border border-olympus-border transition-colors',
            'hover:border-olympus-primary/50 hover:bg-olympus-surface/80',
            sizeConfig[size].moreButton
          ]"
        >
          <Icon name="ph:dots-three" :class="['text-olympus-text-muted', sizeConfig[size].moreIcon]" />
        </DropdownMenuTrigger>

        <DropdownMenuPortal>
          <DropdownMenuContent
            :class="[
              'bg-olympus-elevated border border-olympus-border rounded-xl shadow-xl z-50',
              'animate-in fade-in-0 zoom-in-95 duration-150',
              sizeConfig[size].dropdownContent
            ]"
            :side-offset="8"
            align="end"
          >
            <DropdownMenuItem
              v-for="action in moreActions"
              :key="action.id"
              :class="[
                'flex items-center rounded-lg cursor-pointer outline-none transition-colors duration-150',
                'focus:bg-olympus-surface',
                action.variant === 'danger' ? 'text-olympus-error hover:bg-olympus-error/10' : 'hover:bg-olympus-surface',
                sizeConfig[size].dropdownItem
              ]"
              @click="$emit('action', action.id)"
            >
              <Icon :name="action.icon" :class="['mr-2', sizeConfig[size].dropdownIcon]" />
              <span>{{ action.label }}</span>
              <kbd
                v-if="action.shortcut"
                :class="[
                  'ml-auto text-olympus-text-subtle bg-olympus-surface rounded font-mono',
                  sizeConfig[size].shortcut
                ]"
              >
                {{ action.shortcut }}
              </kbd>
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenuPortal>
      </DropdownMenuRoot>
    </div>
  </div>
</template>

<script setup lang="ts">
import {
  SelectContent,
  SelectItem,
  SelectItemIndicator,
  SelectItemText,
  SelectPortal,
  SelectRoot,
  SelectTrigger,
  SelectValue,
  SelectViewport,
} from 'reka-ui'
import {
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuPortal,
  DropdownMenuRoot,
  DropdownMenuTrigger,
} from 'reka-ui'
import {
  TooltipArrow,
  TooltipContent,
  TooltipPortal,
  TooltipProvider,
  TooltipRoot,
  TooltipTrigger,
} from 'reka-ui'

// Types
type FilterSize = 'sm' | 'md' | 'lg'
type ViewType = 'board' | 'list' | 'timeline'

interface FilterOption {
  value: string
  label: string
  icon: string
  count?: number
}

interface PriorityOption {
  value: string
  label: string
  color?: string
}

interface AssigneeOption {
  value: string
  label: string
  avatar?: string
}

interface SortOption {
  value: string
  label: string
  icon: string
}

interface ViewOption {
  value: ViewType
  label: string
  icon: string
}

interface MoreAction {
  id: string
  label: string
  icon: string
  shortcut?: string
  variant?: 'default' | 'danger'
}

interface ActiveFilter {
  key: string
  label: string
}

interface SizeConfig {
  container: string
  filtersGap: string
  searchInput: string
  searchIcon: string
  clearButton: string
  clearIcon: string
  divider: string
  select: string
  selectIcon: string
  selectContent: string
  selectItem: string
  checkIcon: string
  optionIcon: string
  optionCount: string
  avatar: string
  activeFilters: string
  filterChip: string
  filterChipIcon: string
  clearAll: string
  actionsGap: string
  sortSelect: string
  viewToggle: string
  viewButton: string
  viewIcon: string
  newTaskButton: string
  newTaskIcon: string
  moreButton: string
  moreIcon: string
  dropdownContent: string
  dropdownItem: string
  dropdownIcon: string
  shortcut: string
}

// Props
const props = withDefaults(defineProps<{
  filter?: string
  priority?: string
  assignee?: string
  sort?: string
  view?: ViewType
  search?: string
  compact?: boolean
  size?: FilterSize
}>(), {
  filter: 'all',
  priority: 'any',
  assignee: 'any',
  sort: 'recent',
  view: 'board',
  search: '',
  compact: false,
  size: 'md',
})

// Emits
const emit = defineEmits<{
  'update:filter': [value: string]
  'update:priority': [value: string]
  'update:assignee': [value: string]
  'update:sort': [value: string]
  'update:view': [value: ViewType]
  'update:search': [value: string]
  newTask: []
  action: [actionId: string]
}>()

// Size configuration
const sizeConfig: Record<FilterSize, SizeConfig> = {
  sm: {
    container: 'gap-3 py-2',
    filtersGap: 'gap-2',
    searchInput: 'py-1.5 text-xs w-40',
    searchIcon: 'w-3.5 h-3.5',
    clearButton: 'p-0.5',
    clearIcon: 'w-3 h-3',
    divider: 'h-5 mx-1',
    select: 'gap-1.5 px-2 py-1.5 text-xs',
    selectIcon: 'w-3.5 h-3.5',
    selectContent: 'p-1 min-w-32',
    selectItem: 'gap-1.5 px-2 py-1.5 text-xs',
    checkIcon: 'w-3.5 h-3.5',
    optionIcon: 'w-3.5 h-3.5',
    optionCount: 'text-[10px] px-1.5 py-0.5',
    avatar: 'w-4 h-4 text-[9px]',
    activeFilters: 'gap-1.5 ml-2',
    filterChip: 'gap-1 px-2 py-1 text-xs',
    filterChipIcon: 'w-3 h-3',
    clearAll: 'text-xs ml-1',
    actionsGap: 'gap-2',
    sortSelect: 'gap-1.5 px-2 py-1.5 text-xs',
    viewToggle: '',
    viewButton: 'p-1.5',
    viewIcon: 'w-3.5 h-3.5',
    newTaskButton: 'gap-1.5 px-3 py-1.5 text-xs',
    newTaskIcon: 'w-3.5 h-3.5',
    moreButton: 'p-1.5',
    moreIcon: 'w-3.5 h-3.5',
    dropdownContent: 'p-1 min-w-36',
    dropdownItem: 'gap-2 px-2 py-1.5 text-xs',
    dropdownIcon: 'w-3.5 h-3.5',
    shortcut: 'text-[9px] px-1 py-0.5',
  },
  md: {
    container: 'gap-4 py-3',
    filtersGap: 'gap-3',
    searchInput: 'py-2 text-sm w-48',
    searchIcon: 'w-4 h-4',
    clearButton: 'p-1',
    clearIcon: 'w-3.5 h-3.5',
    divider: 'h-6 mx-2',
    select: 'gap-2 px-3 py-2 text-sm',
    selectIcon: 'w-4 h-4',
    selectContent: 'p-1.5 min-w-40',
    selectItem: 'gap-2 px-3 py-2 text-sm',
    checkIcon: 'w-4 h-4',
    optionIcon: 'w-4 h-4',
    optionCount: 'text-xs px-2 py-0.5',
    avatar: 'w-5 h-5 text-[10px]',
    activeFilters: 'gap-2 ml-3',
    filterChip: 'gap-1 px-2.5 py-1 text-sm',
    filterChipIcon: 'w-3.5 h-3.5',
    clearAll: 'text-sm ml-2',
    actionsGap: 'gap-3',
    sortSelect: 'gap-2 px-3 py-2 text-sm',
    viewToggle: '',
    viewButton: 'p-2',
    viewIcon: 'w-4 h-4',
    newTaskButton: 'gap-2 px-4 py-2 text-sm',
    newTaskIcon: 'w-4 h-4',
    moreButton: 'p-2',
    moreIcon: 'w-4 h-4',
    dropdownContent: 'p-1.5 min-w-44',
    dropdownItem: 'gap-2 px-3 py-2 text-sm',
    dropdownIcon: 'w-4 h-4',
    shortcut: 'text-[10px] px-1.5 py-0.5',
  },
  lg: {
    container: 'gap-5 py-4',
    filtersGap: 'gap-4',
    searchInput: 'py-2.5 text-base w-56',
    searchIcon: 'w-5 h-5',
    clearButton: 'p-1',
    clearIcon: 'w-4 h-4',
    divider: 'h-7 mx-3',
    select: 'gap-2.5 px-4 py-2.5 text-base',
    selectIcon: 'w-5 h-5',
    selectContent: 'p-2 min-w-48',
    selectItem: 'gap-2.5 px-4 py-2.5 text-base',
    checkIcon: 'w-5 h-5',
    optionIcon: 'w-5 h-5',
    optionCount: 'text-sm px-2.5 py-1',
    avatar: 'w-6 h-6 text-xs',
    activeFilters: 'gap-2.5 ml-4',
    filterChip: 'gap-1.5 px-3 py-1.5 text-base',
    filterChipIcon: 'w-4 h-4',
    clearAll: 'text-base ml-2',
    actionsGap: 'gap-4',
    sortSelect: 'gap-2.5 px-4 py-2.5 text-base',
    viewToggle: '',
    viewButton: 'p-2.5',
    viewIcon: 'w-5 h-5',
    newTaskButton: 'gap-2.5 px-5 py-2.5 text-base',
    newTaskIcon: 'w-5 h-5',
    moreButton: 'p-2.5',
    moreIcon: 'w-5 h-5',
    dropdownContent: 'p-2 min-w-52',
    dropdownItem: 'gap-3 px-4 py-2.5 text-base',
    dropdownIcon: 'w-5 h-5',
    shortcut: 'text-xs px-2 py-1',
  },
}

// State
const searchFocused = ref(false)

// Computed v-models
const searchQuery = computed({
  get: () => props.search,
  set: (value) => emit('update:search', value),
})

const selectedFilter = computed({
  get: () => props.filter,
  set: (value) => emit('update:filter', value),
})

const selectedPriority = computed({
  get: () => props.priority,
  set: (value) => emit('update:priority', value),
})

const selectedAssignee = computed({
  get: () => props.assignee,
  set: (value) => emit('update:assignee', value),
})

const selectedSort = computed({
  get: () => props.sort,
  set: (value) => emit('update:sort', value),
})

const selectedView = computed({
  get: () => props.view,
  set: (value) => emit('update:view', value),
})

// Active filters
const activeFilters = computed<ActiveFilter[]>(() => {
  const filters: ActiveFilter[] = []

  if (props.filter !== 'all') {
    const option = filterOptions.find(o => o.value === props.filter)
    if (option) filters.push({ key: 'filter', label: option.label })
  }

  if (props.priority !== 'any') {
    const option = priorityOptions.find(o => o.value === props.priority)
    if (option) filters.push({ key: 'priority', label: option.label })
  }

  if (props.assignee !== 'any') {
    const option = assigneeOptions.find(o => o.value === props.assignee)
    if (option) filters.push({ key: 'assignee', label: option.label })
  }

  return filters
})

// Methods
const clearFilter = (key: string) => {
  switch (key) {
    case 'filter':
      emit('update:filter', 'all')
      break
    case 'priority':
      emit('update:priority', 'any')
      break
    case 'assignee':
      emit('update:assignee', 'any')
      break
  }
}

const clearAllFilters = () => {
  emit('update:filter', 'all')
  emit('update:priority', 'any')
  emit('update:assignee', 'any')
  emit('update:search', '')
}

// Options
const filterOptions: FilterOption[] = [
  { value: 'all', label: 'All tasks', icon: 'ph:list-bullets', count: 24 },
  { value: 'agents', label: 'Agent tasks', icon: 'ph:robot', count: 18 },
  { value: 'humans', label: 'Human tasks', icon: 'ph:user', count: 6 },
  { value: 'my', label: 'My tasks', icon: 'ph:user-circle', count: 4 },
]

const priorityOptions: PriorityOption[] = [
  { value: 'any', label: 'Any priority' },
  { value: 'urgent', label: 'Urgent', color: 'bg-red-500' },
  { value: 'high', label: 'High', color: 'bg-amber-500' },
  { value: 'medium', label: 'Medium', color: 'bg-blue-500' },
  { value: 'low', label: 'Low', color: 'bg-gray-400' },
]

const assigneeOptions: AssigneeOption[] = [
  { value: 'any', label: 'Anyone' },
  { value: 'me', label: 'Assigned to me', avatar: 'ME' },
  { value: 'unassigned', label: 'Unassigned' },
]

const sortOptions: SortOption[] = [
  { value: 'recent', label: 'Most recent', icon: 'ph:clock' },
  { value: 'priority', label: 'Priority', icon: 'ph:flag' },
  { value: 'due', label: 'Due date', icon: 'ph:calendar' },
  { value: 'alpha', label: 'Alphabetical', icon: 'ph:sort-ascending' },
]

const viewOptions: ViewOption[] = [
  { value: 'board', label: 'Board view', icon: 'ph:kanban' },
  { value: 'list', label: 'List view', icon: 'ph:list' },
  { value: 'timeline', label: 'Timeline', icon: 'ph:chart-line' },
]

const moreActions: MoreAction[] = [
  { id: 'import', label: 'Import tasks', icon: 'ph:upload-simple', shortcut: '⌘I' },
  { id: 'export', label: 'Export tasks', icon: 'ph:download-simple', shortcut: '⌘E' },
  { id: 'archive', label: 'View archived', icon: 'ph:archive' },
  { id: 'settings', label: 'Board settings', icon: 'ph:gear' },
]
</script>
