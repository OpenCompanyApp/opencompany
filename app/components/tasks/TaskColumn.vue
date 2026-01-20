<template>
  <div
    :class="[
      'flex flex-col shrink-0 transition-all duration-300',
      sizeConfig[size].container,
      collapsed && sizeConfig[size].collapsedContainer,
      isDragOver && !collapsed && 'scale-[1.02]'
    ]"
    @dragover.prevent="handleDragOver"
    @dragleave="handleDragLeave"
    @drop="handleDrop"
  >
    <!-- Column Header -->
    <div
      :class="[
        'flex items-center justify-between group',
        sizeConfig[size].header,
        collapsible && 'cursor-pointer hover:bg-olympus-surface/50 rounded-xl transition-colors'
      ]"
      @click="collapsible && $emit('toggle')"
    >
      <div class="flex items-center gap-2 min-w-0">
        <!-- Status icon -->
        <div
          :class="[
            'flex items-center justify-center rounded-lg transition-colors',
            sizeConfig[size].iconContainer,
            statusColors[status].bg
          ]"
        >
          <Icon
            :name="icon"
            :class="[
              'transition-colors',
              sizeConfig[size].icon,
              statusColors[status].icon
            ]"
          />
        </div>

        <!-- Title -->
        <h3 :class="['font-semibold truncate', sizeConfig[size].title]">
          {{ title }}
        </h3>

        <!-- Count badge -->
        <Transition
          enter-active-class="transition-all duration-200"
          leave-active-class="transition-all duration-150"
          enter-from-class="opacity-0 scale-75"
          leave-to-class="opacity-0 scale-75"
        >
          <span
            v-if="!collapsed"
            :class="[
              'font-medium bg-olympus-surface text-olympus-text-muted rounded-full shrink-0',
              sizeConfig[size].count
            ]"
          >
            {{ tasks.length }}
          </span>
        </Transition>

        <!-- WIP limit indicator -->
        <Transition
          enter-active-class="transition-all duration-200"
          leave-active-class="transition-all duration-150"
          enter-from-class="opacity-0 scale-75"
          leave-to-class="opacity-0 scale-75"
        >
          <span
            v-if="wipLimit && tasks.length >= wipLimit"
            :class="[
              'font-medium rounded-full shrink-0',
              sizeConfig[size].wipBadge,
              tasks.length > wipLimit
                ? 'bg-olympus-error/20 text-olympus-error'
                : 'bg-olympus-warning/20 text-olympus-warning'
            ]"
          >
            {{ tasks.length > wipLimit ? 'Over limit' : 'At limit' }}
          </span>
        </Transition>
      </div>

      <!-- Header actions -->
      <div
        :class="[
          'flex items-center opacity-0 group-hover:opacity-100 transition-opacity',
          sizeConfig[size].actionsGap
        ]"
      >
        <!-- Collapse chevron -->
        <Icon
          v-if="collapsible"
          name="ph:caret-down"
          :class="[
            'text-olympus-text-subtle transition-transform duration-200',
            sizeConfig[size].chevron,
            collapsed && '-rotate-90'
          ]"
        />

        <!-- Menu -->
        <DropdownMenuRoot>
          <DropdownMenuTrigger as-child>
            <button
              :class="[
                'rounded-lg hover:bg-olympus-border transition-colors',
                sizeConfig[size].menuButton
              ]"
              @click.stop
            >
              <Icon name="ph:dots-three" :class="['text-olympus-text-muted', sizeConfig[size].menuIcon]" />
            </button>
          </DropdownMenuTrigger>

          <DropdownMenuPortal>
            <DropdownMenuContent
              :class="[
                'bg-olympus-elevated border border-olympus-border rounded-xl shadow-xl z-50',
                'animate-in fade-in-0 zoom-in-95 duration-150',
                sizeConfig[size].dropdownContent
              ]"
              :side-offset="5"
              align="end"
            >
              <DropdownMenuItem
                v-for="action in columnActions"
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
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenuPortal>
        </DropdownMenuRoot>
      </div>
    </div>

    <!-- Collapsed state summary -->
    <Transition
      enter-active-class="transition-all duration-200"
      leave-active-class="transition-all duration-150"
      enter-from-class="opacity-0"
      leave-to-class="opacity-0"
    >
      <div
        v-if="collapsed"
        :class="[
          'flex flex-col items-center justify-center flex-1',
          sizeConfig[size].collapsedContent
        ]"
      >
        <span
          :class="[
            'font-bold text-olympus-text-muted',
            sizeConfig[size].collapsedCount
          ]"
        >
          {{ tasks.length }}
        </span>
        <span :class="['text-olympus-text-subtle', sizeConfig[size].collapsedLabel]">
          {{ tasks.length === 1 ? 'task' : 'tasks' }}
        </span>

        <!-- Priority breakdown -->
        <div
          v-if="tasks.length > 0"
          :class="['flex flex-col items-center mt-4', sizeConfig[size].collapsedPriority]"
        >
          <div
            v-for="priority in priorityBreakdown"
            :key="priority.level"
            class="flex items-center gap-1.5"
          >
            <span :class="['w-2 h-2 rounded-full', priority.color]" />
            <span :class="['text-olympus-text-muted', sizeConfig[size].collapsedPriorityText]">
              {{ priority.count }}
            </span>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Cards Container -->
    <Transition
      enter-active-class="transition-all duration-300 ease-out"
      leave-active-class="transition-all duration-200 ease-in"
      enter-from-class="opacity-0 max-h-0"
      leave-to-class="opacity-0 max-h-0"
    >
      <div
        v-if="!collapsed"
        :class="[
          'flex-1 overflow-y-auto rounded-2xl transition-all duration-200',
          sizeConfig[size].cardsContainer,
          isDragOver
            ? 'bg-olympus-primary/10 ring-2 ring-olympus-primary/30 ring-inset'
            : ''
        ]"
      >
        <!-- Loading skeletons -->
        <template v-if="loading">
          <div
            v-for="i in 3"
            :key="`skeleton-${i}`"
            :class="[
              'animate-pulse rounded-xl bg-olympus-surface border border-olympus-border',
              sizeConfig[size].skeleton
            ]"
          >
            <div class="flex items-center gap-2 mb-3">
              <div :class="['rounded bg-olympus-border', sizeConfig[size].skeletonBadge]" />
              <div class="flex-1" />
              <div :class="['rounded bg-olympus-border', sizeConfig[size].skeletonAction]" />
            </div>
            <div :class="['rounded bg-olympus-border mb-2', sizeConfig[size].skeletonTitle]" />
            <div :class="['rounded bg-olympus-border/60', sizeConfig[size].skeletonDesc]" />
            <div class="flex items-center justify-between mt-4 pt-3 border-t border-olympus-border/50">
              <div class="flex -space-x-2">
                <div :class="['rounded-full bg-olympus-border', sizeConfig[size].skeletonAvatar]" />
                <div :class="['rounded-full bg-olympus-border', sizeConfig[size].skeletonAvatar]" />
              </div>
              <div :class="['rounded bg-olympus-border', sizeConfig[size].skeletonMeta]" />
            </div>
          </div>
        </template>

        <!-- Tasks -->
        <TransitionGroup
          v-else
          tag="div"
          :class="['space-y-2', filteredTasks.length === 0 && 'min-h-[100px]']"
          enter-active-class="transition-all duration-200"
          leave-active-class="transition-all duration-150"
          enter-from-class="opacity-0 translate-y-2"
          leave-to-class="opacity-0 translate-y-2"
          move-class="transition-transform duration-200"
        >
          <TasksTaskCard
            v-for="task in displayedTasks"
            :key="task.id"
            :task="task"
            :size="cardSize"
            draggable="true"
            @dragstart="handleDragStart($event, task)"
            @dragend="handleDragEnd"
            @click="$emit('taskClick', task)"
            @edit="$emit('taskEdit', task)"
            @action="(actionId) => $emit('taskAction', task, actionId)"
          />

          <!-- Show more button -->
          <button
            v-if="hasMoreTasks"
            key="show-more"
            :class="[
              'w-full flex items-center justify-center gap-2 bg-olympus-surface/50 hover:bg-olympus-surface',
              'border border-olympus-border hover:border-olympus-primary/50 rounded-xl transition-all duration-200',
              'text-olympus-text-muted hover:text-olympus-text',
              sizeConfig[size].showMore
            ]"
            @click.stop="showAllTasks = true"
          >
            <Icon name="ph:caret-down" :class="sizeConfig[size].showMoreIcon" />
            <span>Show {{ hiddenTasksCount }} more</span>
          </button>
        </TransitionGroup>

        <!-- Drop zone indicator -->
        <Transition
          enter-active-class="transition-all duration-200"
          leave-active-class="transition-all duration-150"
          enter-from-class="opacity-0 scale-95"
          leave-to-class="opacity-0 scale-95"
        >
          <div
            v-if="isDragOver && filteredTasks.length > 0"
            :class="[
              'flex items-center justify-center border-2 border-dashed border-olympus-primary/50 rounded-xl',
              'bg-olympus-primary/5 text-olympus-primary',
              sizeConfig[size].dropIndicator
            ]"
          >
            <Icon name="ph:plus-circle" :class="['mr-2', sizeConfig[size].dropIcon]" />
            <span :class="sizeConfig[size].dropText">Drop here</span>
          </div>
        </Transition>

        <!-- Empty State -->
        <Transition
          enter-active-class="transition-all duration-200"
          leave-active-class="transition-all duration-150"
          enter-from-class="opacity-0 scale-95"
          leave-to-class="opacity-0 scale-95"
        >
          <div
            v-if="!loading && filteredTasks.length === 0 && !isDragOver"
            :class="[
              'flex flex-col items-center justify-center text-center',
              sizeConfig[size].emptyState
            ]"
          >
            <div
              :class="[
                'rounded-xl bg-olympus-surface flex items-center justify-center mb-3',
                sizeConfig[size].emptyIcon
              ]"
            >
              <Icon
                :name="emptyIcon"
                :class="['text-olympus-text-subtle', sizeConfig[size].emptyIconSize]"
              />
            </div>
            <p :class="['text-olympus-text-muted', sizeConfig[size].emptyText]">
              {{ emptyText }}
            </p>
            <p
              v-if="emptySubtext"
              :class="['text-olympus-text-subtle mt-1', sizeConfig[size].emptySubtext]"
            >
              {{ emptySubtext }}
            </p>
          </div>
        </Transition>
      </div>
    </Transition>

    <!-- Add Task Button -->
    <Transition
      enter-active-class="transition-all duration-200"
      leave-active-class="transition-all duration-150"
      enter-from-class="opacity-0 translate-y-2"
      leave-to-class="opacity-0 translate-y-2"
    >
      <button
        v-if="!collapsed && showAddButton"
        :class="[
          'flex items-center justify-center gap-2 w-full border-2 border-dashed border-olympus-border rounded-xl',
          'hover:border-olympus-primary hover:bg-olympus-primary/5 transition-all duration-200',
          'text-olympus-text-muted hover:text-olympus-primary group',
          sizeConfig[size].addButton
        ]"
        @click.stop="$emit('add')"
      >
        <Icon
          name="ph:plus"
          :class="[
            'transition-transform duration-200 group-hover:scale-110',
            sizeConfig[size].addIcon
          ]"
        />
        <span :class="['font-medium', sizeConfig[size].addText]">Add task</span>
      </button>
    </Transition>

    <!-- Quick add input -->
    <Transition
      enter-active-class="transition-all duration-200"
      leave-active-class="transition-all duration-150"
      enter-from-class="opacity-0 translate-y-2"
      leave-to-class="opacity-0 translate-y-2"
    >
      <div
        v-if="showQuickAdd && !collapsed"
        :class="[
          'border border-olympus-border rounded-xl bg-olympus-surface overflow-hidden',
          sizeConfig[size].quickAdd
        ]"
      >
        <input
          ref="quickAddInput"
          v-model="quickAddTitle"
          type="text"
          placeholder="Task title..."
          :class="[
            'w-full bg-transparent outline-none placeholder:text-olympus-text-subtle',
            sizeConfig[size].quickAddInput
          ]"
          @keydown.enter="handleQuickAdd"
          @keydown.escape="cancelQuickAdd"
          @blur="cancelQuickAdd"
        />
        <div :class="['flex items-center justify-end gap-2 border-t border-olympus-border bg-olympus-surface/50', sizeConfig[size].quickAddActions]">
          <button
            :class="[
              'text-olympus-text-muted hover:text-olympus-text transition-colors',
              sizeConfig[size].quickAddCancel
            ]"
            @click="cancelQuickAdd"
          >
            Cancel
          </button>
          <button
            :class="[
              'bg-olympus-primary text-white rounded-lg hover:bg-olympus-primary-hover transition-colors',
              sizeConfig[size].quickAddSubmit
            ]"
            :disabled="!quickAddTitle.trim()"
            @click="handleQuickAdd"
          >
            Add
          </button>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup lang="ts">
import type { Task, TaskStatus, Priority } from '~/types'
import {
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuPortal,
  DropdownMenuRoot,
  DropdownMenuTrigger,
} from 'reka-ui'

// Types
type ColumnSize = 'sm' | 'md' | 'lg'

interface ColumnAction {
  id: string
  label: string
  icon: string
  variant?: 'default' | 'danger'
}

interface PriorityBreakdownItem {
  level: Priority
  count: number
  color: string
}

interface SizeConfig {
  container: string
  collapsedContainer: string
  header: string
  iconContainer: string
  icon: string
  title: string
  count: string
  wipBadge: string
  actionsGap: string
  chevron: string
  menuButton: string
  menuIcon: string
  dropdownContent: string
  dropdownItem: string
  dropdownIcon: string
  collapsedContent: string
  collapsedCount: string
  collapsedLabel: string
  collapsedPriority: string
  collapsedPriorityText: string
  cardsContainer: string
  skeleton: string
  skeletonBadge: string
  skeletonAction: string
  skeletonTitle: string
  skeletonDesc: string
  skeletonAvatar: string
  skeletonMeta: string
  showMore: string
  showMoreIcon: string
  dropIndicator: string
  dropIcon: string
  dropText: string
  emptyState: string
  emptyIcon: string
  emptyIconSize: string
  emptyText: string
  emptySubtext: string
  addButton: string
  addIcon: string
  addText: string
  quickAdd: string
  quickAddInput: string
  quickAddActions: string
  quickAddCancel: string
  quickAddSubmit: string
}

// Props
const props = withDefaults(defineProps<{
  title: string
  icon: string
  color?: string
  tasks: Task[]
  status: TaskStatus
  size?: ColumnSize
  collapsible?: boolean
  collapsed?: boolean
  loading?: boolean
  maxTasks?: number
  wipLimit?: number
  showAddButton?: boolean
  emptyText?: string
  emptySubtext?: string
  emptyIcon?: string
}>(), {
  size: 'md',
  collapsible: true,
  collapsed: false,
  loading: false,
  showAddButton: true,
  emptyText: 'No tasks',
  emptyIcon: 'ph:tray',
})

// Emits
const emit = defineEmits<{
  drop: [taskId: string, newStatus: TaskStatus]
  toggle: []
  add: []
  quickAdd: [title: string]
  action: [actionId: string]
  taskClick: [task: Task]
  taskEdit: [task: Task]
  taskAction: [task: Task, actionId: string]
}>()

// Size configuration
const sizeConfig: Record<ColumnSize, SizeConfig> = {
  sm: {
    container: 'w-64',
    collapsedContainer: 'w-12',
    header: 'gap-2 mb-2 px-2 py-1.5',
    iconContainer: 'w-5 h-5',
    icon: 'w-3 h-3',
    title: 'text-xs',
    count: 'text-[10px] px-1.5 py-0.5',
    wipBadge: 'text-[9px] px-1.5 py-0.5',
    actionsGap: 'gap-0.5',
    chevron: 'w-3 h-3',
    menuButton: 'p-1',
    menuIcon: 'w-3 h-3',
    dropdownContent: 'p-1 min-w-32',
    dropdownItem: 'gap-1.5 px-2 py-1.5 text-xs',
    dropdownIcon: 'w-3 h-3',
    collapsedContent: 'py-4',
    collapsedCount: 'text-lg',
    collapsedLabel: 'text-[10px]',
    collapsedPriority: 'gap-1',
    collapsedPriorityText: 'text-[10px]',
    cardsContainer: 'space-y-2 p-1 -m-1',
    skeleton: 'p-3',
    skeletonBadge: 'w-12 h-4',
    skeletonAction: 'w-4 h-4',
    skeletonTitle: 'w-3/4 h-3',
    skeletonDesc: 'w-full h-2',
    skeletonAvatar: 'w-5 h-5',
    skeletonMeta: 'w-8 h-3',
    showMore: 'py-2 text-xs',
    showMoreIcon: 'w-3 h-3',
    dropIndicator: 'py-6 mt-2',
    dropIcon: 'w-4 h-4',
    dropText: 'text-xs font-medium',
    emptyState: 'py-8',
    emptyIcon: 'w-8 h-8',
    emptyIconSize: 'w-4 h-4',
    emptyText: 'text-xs',
    emptySubtext: 'text-[10px]',
    addButton: 'mt-2 py-2',
    addIcon: 'w-3 h-3',
    addText: 'text-xs',
    quickAdd: 'mt-2',
    quickAddInput: 'px-3 py-2 text-xs',
    quickAddActions: 'px-2 py-1.5',
    quickAddCancel: 'text-xs px-2 py-1',
    quickAddSubmit: 'text-xs px-2 py-1',
  },
  md: {
    container: 'w-80',
    collapsedContainer: 'w-14',
    header: 'gap-2 mb-4 px-2 py-2',
    iconContainer: 'w-6 h-6',
    icon: 'w-4 h-4',
    title: 'text-sm',
    count: 'text-xs px-2 py-0.5',
    wipBadge: 'text-[10px] px-2 py-0.5',
    actionsGap: 'gap-1',
    chevron: 'w-4 h-4',
    menuButton: 'p-1.5',
    menuIcon: 'w-4 h-4',
    dropdownContent: 'p-1.5 min-w-40',
    dropdownItem: 'gap-2 px-3 py-2 text-sm',
    dropdownIcon: 'w-4 h-4',
    collapsedContent: 'py-6',
    collapsedCount: 'text-2xl',
    collapsedLabel: 'text-xs',
    collapsedPriority: 'gap-1.5',
    collapsedPriorityText: 'text-xs',
    cardsContainer: 'space-y-3 p-1 -m-1',
    skeleton: 'p-4',
    skeletonBadge: 'w-16 h-5',
    skeletonAction: 'w-5 h-5',
    skeletonTitle: 'w-3/4 h-4',
    skeletonDesc: 'w-full h-3',
    skeletonAvatar: 'w-6 h-6',
    skeletonMeta: 'w-10 h-4',
    showMore: 'py-2.5 text-sm',
    showMoreIcon: 'w-4 h-4',
    dropIndicator: 'py-8 mt-3',
    dropIcon: 'w-5 h-5',
    dropText: 'text-sm font-medium',
    emptyState: 'py-12',
    emptyIcon: 'w-10 h-10',
    emptyIconSize: 'w-5 h-5',
    emptyText: 'text-sm',
    emptySubtext: 'text-xs',
    addButton: 'mt-4 py-3',
    addIcon: 'w-4 h-4',
    addText: 'text-sm',
    quickAdd: 'mt-4',
    quickAddInput: 'px-4 py-3 text-sm',
    quickAddActions: 'px-3 py-2',
    quickAddCancel: 'text-sm px-3 py-1.5',
    quickAddSubmit: 'text-sm px-3 py-1.5',
  },
  lg: {
    container: 'w-96',
    collapsedContainer: 'w-16',
    header: 'gap-3 mb-5 px-3 py-2.5',
    iconContainer: 'w-8 h-8',
    icon: 'w-5 h-5',
    title: 'text-base',
    count: 'text-sm px-2.5 py-1',
    wipBadge: 'text-xs px-2.5 py-1',
    actionsGap: 'gap-1.5',
    chevron: 'w-5 h-5',
    menuButton: 'p-2',
    menuIcon: 'w-5 h-5',
    dropdownContent: 'p-2 min-w-48',
    dropdownItem: 'gap-2.5 px-4 py-2.5 text-base',
    dropdownIcon: 'w-5 h-5',
    collapsedContent: 'py-8',
    collapsedCount: 'text-3xl',
    collapsedLabel: 'text-sm',
    collapsedPriority: 'gap-2',
    collapsedPriorityText: 'text-sm',
    cardsContainer: 'space-y-4 p-1 -m-1',
    skeleton: 'p-5',
    skeletonBadge: 'w-20 h-6',
    skeletonAction: 'w-6 h-6',
    skeletonTitle: 'w-3/4 h-5',
    skeletonDesc: 'w-full h-4',
    skeletonAvatar: 'w-8 h-8',
    skeletonMeta: 'w-12 h-5',
    showMore: 'py-3 text-base',
    showMoreIcon: 'w-5 h-5',
    dropIndicator: 'py-10 mt-4',
    dropIcon: 'w-6 h-6',
    dropText: 'text-base font-medium',
    emptyState: 'py-16',
    emptyIcon: 'w-12 h-12',
    emptyIconSize: 'w-6 h-6',
    emptyText: 'text-base',
    emptySubtext: 'text-sm',
    addButton: 'mt-5 py-4',
    addIcon: 'w-5 h-5',
    addText: 'text-base',
    quickAdd: 'mt-5',
    quickAddInput: 'px-5 py-4 text-base',
    quickAddActions: 'px-4 py-2.5',
    quickAddCancel: 'text-base px-4 py-2',
    quickAddSubmit: 'text-base px-4 py-2',
  },
}

// Status colors
const statusColors: Record<TaskStatus, { bg: string; icon: string }> = {
  backlog: {
    bg: 'bg-gray-500/10',
    icon: 'text-gray-400',
  },
  in_progress: {
    bg: 'bg-olympus-primary/10',
    icon: 'text-olympus-primary',
  },
  done: {
    bg: 'bg-olympus-success/10',
    icon: 'text-olympus-success',
  },
}

// Priority colors
const priorityColors: Record<Priority, string> = {
  urgent: 'bg-red-500',
  high: 'bg-amber-500',
  medium: 'bg-blue-500',
  low: 'bg-gray-400',
}

// Column actions
const columnActions: ColumnAction[] = [
  { id: 'sort', label: 'Sort by...', icon: 'ph:sort-ascending' },
  { id: 'filter', label: 'Filter', icon: 'ph:funnel' },
  { id: 'setLimit', label: 'Set WIP limit', icon: 'ph:warning-circle' },
  { id: 'clearDone', label: 'Clear completed', icon: 'ph:broom' },
  { id: 'archive', label: 'Archive all', icon: 'ph:archive', variant: 'danger' },
]

// State
const isDragOver = ref(false)
const draggedTaskId = ref<string | null>(null)
const showAllTasks = ref(false)
const showQuickAdd = ref(false)
const quickAddTitle = ref('')
const quickAddInput = ref<HTMLInputElement | null>(null)

// Computed
const cardSize = computed(() => {
  return props.size === 'lg' ? 'md' : props.size
})

const filteredTasks = computed(() => {
  return props.tasks
})

const displayedTasks = computed(() => {
  if (!props.maxTasks || showAllTasks.value) {
    return filteredTasks.value
  }
  return filteredTasks.value.slice(0, props.maxTasks)
})

const hasMoreTasks = computed(() => {
  if (!props.maxTasks || showAllTasks.value) return false
  return filteredTasks.value.length > props.maxTasks
})

const hiddenTasksCount = computed(() => {
  if (!props.maxTasks) return 0
  return filteredTasks.value.length - props.maxTasks
})

const priorityBreakdown = computed<PriorityBreakdownItem[]>(() => {
  const counts: Record<Priority, number> = {
    urgent: 0,
    high: 0,
    medium: 0,
    low: 0,
  }

  props.tasks.forEach(task => {
    if (counts[task.priority] !== undefined) {
      counts[task.priority]++
    }
  })

  return (Object.entries(counts) as [Priority, number][])
    .filter(([_, count]) => count > 0)
    .map(([level, count]) => ({
      level,
      count,
      color: priorityColors[level],
    }))
})

// Methods
const handleDragStart = (event: DragEvent, task: Task) => {
  if (event.dataTransfer) {
    event.dataTransfer.setData('taskId', task.id)
    event.dataTransfer.effectAllowed = 'move'
  }
  draggedTaskId.value = task.id
}

const handleDragEnd = () => {
  draggedTaskId.value = null
  isDragOver.value = false
}

const handleDragOver = (event: DragEvent) => {
  if (props.collapsed) return
  isDragOver.value = true
  event.dataTransfer!.dropEffect = 'move'
}

const handleDragLeave = () => {
  isDragOver.value = false
}

const handleDrop = (event: DragEvent) => {
  isDragOver.value = false
  const taskId = event.dataTransfer?.getData('taskId')
  if (taskId) {
    emit('drop', taskId, props.status)
  }
}

const handleQuickAdd = () => {
  if (quickAddTitle.value.trim()) {
    emit('quickAdd', quickAddTitle.value.trim())
    quickAddTitle.value = ''
    showQuickAdd.value = false
  }
}

const cancelQuickAdd = () => {
  quickAddTitle.value = ''
  showQuickAdd.value = false
}

// Watch for quick add to focus input
watch(showQuickAdd, (show) => {
  if (show) {
    nextTick(() => {
      quickAddInput.value?.focus()
    })
  }
})
</script>
