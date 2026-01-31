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
            'absolute left-3 top-1/2 -translate-y-1/2 text-neutral-400 transition-colors',
            sizeConfig[size].searchIcon,
            searchFocused && 'text-neutral-600'
          ]"
        />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search tasks..."
          :class="[
            'bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg pl-9 pr-3 outline-none',
            'transition-colors duration-150',
            'placeholder:text-neutral-400 dark:placeholder:text-neutral-500 text-neutral-900 dark:text-white',
            'focus:border-neutral-400 dark:focus:border-neutral-500 focus:ring-1 focus:ring-neutral-200 dark:focus:ring-neutral-700',
            sizeConfig[size].searchInput
          ]"
          @focus="searchFocused = true"
          @blur="searchFocused = false"
        />
        <!-- Clear button -->
        <Transition
          enter-active-class="transition-all duration-150 ease-out"
          leave-active-class="transition-all duration-100 ease-out"
          enter-from-class="opacity-0"
          leave-to-class="opacity-0"
        >
          <button
            v-if="searchQuery"
            :class="[
              'absolute right-2 top-1/2 -translate-y-1/2 p-1 rounded-full',
              'transition-colors duration-150',
              'hover:bg-neutral-100 dark:hover:bg-neutral-700',
              sizeConfig[size].clearButton
            ]"
            @click="searchQuery = ''"
          >
            <Icon name="ph:x" :class="['text-neutral-500 dark:text-neutral-300', sizeConfig[size].clearIcon]" />
          </button>
        </Transition>
      </div>

      <!-- Divider -->
      <div :class="['w-px bg-neutral-200 dark:bg-neutral-700', sizeConfig[size].divider]" />

      <!-- Status Filter -->
      <Select
        v-model="selectedFilter"
        :items="filterOptionsForSelect"
        value-key="value"
        placeholder="All tasks"
        icon="ph:funnel"
        :size="size"
      />

      <!-- Priority Filter -->
      <Select
        v-model="selectedPriority"
        :items="priorityOptionsForSelect"
        value-key="value"
        placeholder="Any priority"
        icon="ph:flag"
        :size="size"
      />

      <!-- Assignee Filter -->
      <Select
        v-model="selectedAssignee"
        :items="assigneeOptionsForSelect"
        value-key="value"
        placeholder="Anyone"
        icon="ph:user"
        :size="size"
      />

      <!-- Active Filters -->
      <TransitionGroup
        tag="div"
        :class="['flex items-center', sizeConfig[size].activeFilters]"
        enter-active-class="transition-all duration-150 ease-out"
        leave-active-class="transition-all duration-100 ease-out"
        enter-from-class="opacity-0"
        leave-to-class="opacity-0"
      >
        <button
          v-for="filter in activeFilters"
          :key="filter.key"
          :class="[
            'flex items-center bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-200 rounded-lg',
            'transition-colors duration-150',
            'hover:bg-neutral-200 dark:hover:bg-neutral-700',
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
            'text-neutral-500 dark:text-neutral-300 font-medium',
            'transition-colors duration-150',
            'hover:text-neutral-900 dark:hover:text-white',
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
      <Select
        v-model="selectedSort"
        :items="sortOptionsForSelect"
        value-key="value"
        placeholder="Sort by"
        icon="ph:sort-ascending"
        :size="size"
      />

      <!-- View Toggle -->
      <div
        :class="[
          'flex items-center bg-neutral-100 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg p-1',
          sizeConfig[size].viewToggle
        ]"
      >
        <Tooltip
          v-for="view in viewOptions"
          :key="view.value"
          :text="view.label"
          :delay-open="300"
        >
          <button
            :class="[
              'rounded-md transition-colors duration-150',
              sizeConfig[size].viewButton,
              selectedView === view.value
                ? 'bg-white dark:bg-neutral-700 text-neutral-900 dark:text-white shadow-sm'
                : 'text-neutral-500 dark:text-neutral-300 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-50 dark:hover:bg-neutral-700'
            ]"
            @click="selectedView = view.value"
          >
            <Icon :name="view.icon" :class="sizeConfig[size].viewIcon" />
          </button>
        </Tooltip>
      </div>

      <!-- Divider -->
      <div :class="['w-px bg-neutral-200 dark:bg-neutral-700', sizeConfig[size].divider]" />

      <!-- New Task Button -->
      <Button :size="size" @click="$emit('newTask')">
        <Icon name="ph:plus-bold" :class="sizeConfig[size].newTaskIcon" />
        <span v-if="!compact">New Task</span>
      </Button>

      <!-- More Options -->
      <DropdownMenu :items="moreActionsDropdown" :size="size">
        <Button variant="outline" :size="size" icon="ph:dots-three" />
      </DropdownMenu>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import Button from '@/Components/shared/Button.vue'
import Tooltip from '@/Components/shared/Tooltip.vue'
import DropdownMenu from '@/Components/shared/DropdownMenu.vue'
import Select from '@/Components/shared/Select.vue'

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
  { value: 'low', label: 'Low', color: 'bg-neutral-400' },
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

// Computed options for USelectMenu
const filterOptionsForSelect = computed(() =>
  filterOptions.map(opt => ({ ...opt, icon: opt.icon }))
)

const priorityOptionsForSelect = computed(() =>
  priorityOptions.map(opt => ({ ...opt, chip: opt.color ? { color: opt.color } : undefined }))
)

const assigneeOptionsForSelect = computed(() =>
  assigneeOptions.map(opt => ({ ...opt }))
)

const sortOptionsForSelect = computed(() =>
  sortOptions.map(opt => ({ ...opt, icon: opt.icon }))
)

// Computed dropdown items for UDropdownMenu
const moreActionsDropdown = computed(() => [
  moreActions.map(action => ({
    label: action.label,
    icon: action.icon,
    kbds: action.shortcut ? [action.shortcut] : undefined,
    color: action.variant === 'danger' ? 'error' as const : undefined,
    click: () => emit('action', action.id),
  })),
])
</script>
