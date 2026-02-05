<template>
  <div
    :class="[
      'flex flex-col shrink-0',
      'transition-all duration-150',
      sizeConfig[size].container,
      collapsed && sizeConfig[size].collapsedContainer,
      isDragOver && !collapsed && 'ring-2 ring-neutral-300'
    ]"
    @dragover.prevent="handleDragOver"
    @dragleave="handleDragLeave"
    @drop="handleDrop"
  >
    <!-- Column Header -->
    <div
      :class="[
        'flex items-center justify-between group/header',
        sizeConfig[size].header,
        collapsible && 'cursor-pointer hover:bg-neutral-50 dark:hover:bg-neutral-800 rounded-lg transition-colors duration-150'
      ]"
      @click="collapsible && $emit('toggle')"
    >
      <div class="flex items-center gap-2 min-w-0">
        <!-- Status icon -->
        <div
          :class="[
            'flex items-center justify-center rounded-lg',
            'transition-colors duration-150',
            sizeConfig[size].iconContainer,
            statusColors[status].bg
          ]"
        >
          <Icon
            :name="icon"
            :class="[
              'transition-colors duration-150',
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
          enter-active-class="transition-all duration-150 ease-out"
          leave-active-class="transition-all duration-100 ease-out"
          enter-from-class="opacity-0"
          leave-to-class="opacity-0"
        >
          <span
            v-if="!collapsed"
            :class="[
              'font-medium bg-neutral-100 dark:bg-neutral-800 text-neutral-500 dark:text-neutral-300 rounded-full shrink-0',
              'transition-colors duration-150',
              'group-hover/header:bg-neutral-200 dark:group-hover/header:bg-neutral-700',
              sizeConfig[size].count
            ]"
          >
            {{ tasks.length }}
          </span>
        </Transition>

        <!-- WIP limit indicator -->
        <Transition
          enter-active-class="transition-all duration-150 ease-out"
          leave-active-class="transition-all duration-100 ease-out"
          enter-from-class="opacity-0"
          leave-to-class="opacity-0"
        >
          <span
            v-if="wipLimit && tasks.length >= wipLimit"
            :class="[
              'font-medium rounded-full shrink-0',
              sizeConfig[size].wipBadge,
              tasks.length > wipLimit
                ? 'bg-red-100 text-red-600'
                : 'bg-amber-100 text-amber-600'
            ]"
          >
            {{ tasks.length > wipLimit ? 'Over limit' : 'At limit' }}
          </span>
        </Transition>
      </div>

      <!-- Header actions -->
      <div
        :class="[
          'flex items-center opacity-0 group-hover/header:opacity-100',
          'transition-opacity duration-150',
          sizeConfig[size].actionsGap
        ]"
      >
        <!-- Collapse chevron -->
        <Icon
          v-if="collapsible"
          name="ph:caret-down"
          :class="[
            'text-neutral-400',
            'transition-transform duration-150',
            sizeConfig[size].chevron,
            collapsed && '-rotate-90'
          ]"
        />

        <!-- Menu -->
        <DropdownMenu :items="columnDropdownItems">
          <button
            :class="[
              'rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-700',
              'transition-colors duration-150',
              sizeConfig[size].menuButton
            ]"
            @click.stop
          >
            <Icon name="ph:dots-three" :class="['text-neutral-500 dark:text-neutral-300', sizeConfig[size].menuIcon]" />
          </button>
        </DropdownMenu>
      </div>
    </div>

    <!-- Collapsed state summary -->
    <Transition
      enter-active-class="transition-all duration-150 ease-out"
      leave-active-class="transition-all duration-100 ease-out"
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
            'font-bold text-neutral-500 dark:text-neutral-300',
            sizeConfig[size].collapsedCount
          ]"
        >
          {{ tasks.length }}
        </span>
        <span :class="['text-neutral-400 dark:text-neutral-400', sizeConfig[size].collapsedLabel]">
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
            <span :class="['text-neutral-500', sizeConfig[size].collapsedPriorityText]">
              {{ priority.count }}
            </span>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Cards Container -->
    <Transition
      enter-active-class="transition-all duration-150 ease-out"
      leave-active-class="transition-all duration-100 ease-out"
      enter-from-class="opacity-0"
      leave-to-class="opacity-0"
    >
      <div
        v-if="!collapsed"
        :class="[
          'flex-1 overflow-y-auto rounded-lg',
          'transition-colors duration-150',
          sizeConfig[size].cardsContainer,
          isDragOver
            ? 'bg-neutral-50 dark:bg-neutral-800 ring-2 ring-neutral-300 dark:ring-neutral-600 ring-inset'
            : ''
        ]"
      >
        <!-- Loading skeletons -->
        <template v-if="loading">
          <div
            v-for="i in 3"
            :key="`skeleton-${i}`"
            :class="[
              'animate-pulse rounded-lg bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700',
              sizeConfig[size].skeleton
            ]"
          >
            <div class="flex items-center gap-2 mb-3">
              <div :class="['rounded bg-neutral-200 dark:bg-neutral-700', sizeConfig[size].skeletonBadge]" />
              <div class="flex-1" />
              <div :class="['rounded bg-neutral-200 dark:bg-neutral-700', sizeConfig[size].skeletonAction]" />
            </div>
            <div :class="['rounded bg-neutral-200 dark:bg-neutral-700 mb-2', sizeConfig[size].skeletonTitle]" />
            <div :class="['rounded bg-neutral-100 dark:bg-neutral-700', sizeConfig[size].skeletonDesc]" />
            <div class="flex items-center justify-between mt-4 pt-3 border-t border-neutral-100 dark:border-neutral-700">
              <div class="flex -space-x-2">
                <div :class="['rounded-full bg-neutral-200 dark:bg-neutral-700', sizeConfig[size].skeletonAvatar]" />
                <div :class="['rounded-full bg-neutral-200 dark:bg-neutral-700', sizeConfig[size].skeletonAvatar]" />
              </div>
              <div :class="['rounded bg-neutral-200 dark:bg-neutral-700', sizeConfig[size].skeletonMeta]" />
            </div>
          </div>
        </template>

        <!-- Tasks -->
        <TransitionGroup
          v-else
          tag="div"
          :class="['space-y-2', filteredTasks.length === 0 && 'min-h-[100px]']"
          enter-active-class="transition-all duration-150 ease-out"
          leave-active-class="transition-all duration-100 ease-out"
          enter-from-class="opacity-0"
          leave-to-class="opacity-0"
          move-class="transition-transform duration-150 ease-out"
        >
          <!-- Drop indicator at top -->
          <div
            v-if="isDragOver && dropTargetIndex === 0"
            key="drop-indicator-top"
            class="h-1 bg-neutral-400 rounded-full mx-2"
          />

          <template v-for="(task, index) in displayedTasks" :key="task.id">
            <div
              @dragover="handleCardDragOver($event, index)"
            >
              <TaskCard
                :task="task"
                :size="cardSize"
                draggable="true"
                @dragstart="handleDragStart($event, task)"
                @dragend="handleDragEnd"
                @click="$emit('taskClick', task)"
                @edit="$emit('taskEdit', task)"
                @action="(actionId) => $emit('taskAction', task, actionId)"
              />
            </div>

            <!-- Drop indicator after each card -->
            <div
              v-if="isDragOver && dropTargetIndex === index + 1"
              :key="`drop-indicator-${index}`"
              class="h-1 bg-neutral-400 rounded-full mx-2"
            />
          </template>

          <!-- Show more button -->
          <button
            v-if="hasMoreTasks"
            key="show-more"
            :class="[
              'w-full flex items-center justify-center gap-2 bg-neutral-50 dark:bg-neutral-800 hover:bg-neutral-100 dark:hover:bg-neutral-700',
              'border border-neutral-200 dark:border-neutral-700 hover:border-neutral-300 dark:hover:border-neutral-600 rounded-lg',
              'transition-colors duration-150',
              'text-neutral-500 dark:text-neutral-300 hover:text-neutral-900 dark:hover:text-white',
              sizeConfig[size].showMore
            ]"
            @click.stop="showAllTasks = true"
          >
            <Icon
              name="ph:caret-down"
              :class="[sizeConfig[size].showMoreIcon, 'transition-transform duration-300 group-hover:translate-y-0.5']"
            />
            <span>Show {{ hiddenTasksCount }} more</span>
          </button>
        </TransitionGroup>

        <!-- Drop zone indicator -->
        <Transition
          enter-active-class="transition-all duration-150 ease-out"
          leave-active-class="transition-all duration-100 ease-out"
          enter-from-class="opacity-0"
          leave-to-class="opacity-0"
        >
          <div
            v-if="isDragOver && filteredTasks.length > 0"
            :class="[
              'flex items-center justify-center border-2 border-dashed border-neutral-400 dark:border-neutral-500 rounded-lg',
              'bg-neutral-50 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-200',
              sizeConfig[size].dropIndicator
            ]"
          >
            <Icon name="ph:plus-circle" :class="['mr-2', sizeConfig[size].dropIcon]" />
            <span :class="sizeConfig[size].dropText">Drop here</span>
          </div>
        </Transition>

        <!-- Empty State -->
        <Transition
          enter-active-class="transition-all duration-150 ease-out"
          leave-active-class="transition-all duration-100 ease-out"
          enter-from-class="opacity-0"
          leave-to-class="opacity-0"
        >
          <div
            v-if="!loading && filteredTasks.length === 0 && !isDragOver"
            :class="[
              'flex flex-col items-center justify-center text-center group/empty',
              sizeConfig[size].emptyState
            ]"
          >
            <div
              :class="[
                'rounded-lg bg-neutral-100 dark:bg-neutral-800 flex items-center justify-center mb-3',
                'transition-colors duration-150',
                'group-hover/empty:bg-neutral-200 dark:group-hover/empty:bg-neutral-700',
                sizeConfig[size].emptyIcon
              ]"
            >
              <Icon
                :name="emptyIcon"
                :class="[
                  'text-neutral-400 dark:text-neutral-400',
                  'transition-colors duration-150',
                  'group-hover/empty:text-neutral-600 dark:group-hover/empty:text-neutral-300',
                  sizeConfig[size].emptyIconSize
                ]"
              />
            </div>
            <p :class="['text-neutral-500 dark:text-neutral-300 transition-colors duration-150', sizeConfig[size].emptyText]">
              {{ emptyText }}
            </p>
            <p
              v-if="emptySubtext"
              :class="['text-neutral-400 dark:text-neutral-400 mt-1', sizeConfig[size].emptySubtext]"
            >
              {{ emptySubtext }}
            </p>
          </div>
        </Transition>
      </div>
    </Transition>

    <!-- Add Task Button -->
    <Transition
      enter-active-class="transition-all duration-150 ease-out"
      leave-active-class="transition-all duration-100 ease-out"
      enter-from-class="opacity-0"
      leave-to-class="opacity-0"
    >
      <button
        v-if="!collapsed && showAddButton"
        :class="[
          'flex items-center justify-center gap-2 w-full border-2 border-dashed border-neutral-200 dark:border-neutral-700 rounded-lg',
          'hover:border-neutral-400 dark:hover:border-neutral-500 hover:bg-neutral-50 dark:hover:bg-neutral-800',
          'transition-colors duration-150',
          'text-neutral-500 dark:text-neutral-300 hover:text-neutral-900 dark:hover:text-white group/add',
          sizeConfig[size].addButton
        ]"
        @click.stop="$emit('add')"
      >
        <Icon
          name="ph:plus"
          :class="[
            'transition-colors duration-150',
            sizeConfig[size].addIcon
          ]"
        />
        <span :class="['font-medium', sizeConfig[size].addText]">Add task</span>
      </button>
    </Transition>

    <!-- Quick add input -->
    <Transition
      enter-active-class="transition-all duration-150 ease-out"
      leave-active-class="transition-all duration-100 ease-out"
      enter-from-class="opacity-0"
      leave-to-class="opacity-0"
    >
      <div
        v-if="showQuickAdd && !collapsed"
        :class="[
          'border border-neutral-200 dark:border-neutral-700 rounded-lg bg-white dark:bg-neutral-800 overflow-hidden',
          'shadow-md',
          sizeConfig[size].quickAdd
        ]"
      >
        <input
          ref="quickAddInput"
          v-model="quickAddTitle"
          type="text"
          placeholder="Task title..."
          :class="[
            'w-full bg-transparent outline-none placeholder:text-neutral-400 dark:placeholder:text-neutral-500 text-neutral-900 dark:text-white',
            'transition-colors duration-150 focus:placeholder:text-neutral-500 dark:focus:placeholder:text-neutral-400',
            sizeConfig[size].quickAddInput
          ]"
          @keydown.enter="handleQuickAdd"
          @keydown.escape="cancelQuickAdd"
          @blur="cancelQuickAdd"
        />
        <div :class="['flex items-center justify-end gap-2 border-t border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-900', sizeConfig[size].quickAddActions]">
          <button
            :class="[
              'text-neutral-500 dark:text-neutral-300 hover:text-neutral-900 dark:hover:text-white',
              'transition-colors duration-150',
              sizeConfig[size].quickAddCancel
            ]"
            @click="cancelQuickAdd"
          >
            Cancel
          </button>
          <button
            :class="[
              'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 rounded-lg hover:bg-neutral-800 dark:hover:bg-neutral-100',
              'transition-colors duration-150',
              'disabled:opacity-50 disabled:cursor-not-allowed',
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
import { ref, computed, watch, nextTick } from 'vue'
import type { ListItem, ListItemStatus, Priority } from '@/types'
import TaskCard from '@/Components/lists/TaskCard.vue'
import Icon from '@/Components/shared/Icon.vue'
import DropdownMenu from '@/Components/shared/DropdownMenu.vue'

// Type alias for backwards compatibility
type Task = ListItem
type TaskStatus = ListItemStatus

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
  drop: [taskId: string, newStatus: TaskStatus, newIndex: number]
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
    bg: 'bg-neutral-100 dark:bg-neutral-800',
    icon: 'text-neutral-500 dark:text-neutral-300',
  },
  in_progress: {
    bg: 'bg-neutral-100 dark:bg-neutral-800',
    icon: 'text-neutral-600 dark:text-neutral-200',
  },
  done: {
    bg: 'bg-neutral-100 dark:bg-neutral-800',
    icon: 'text-neutral-700 dark:text-neutral-200',
  },
}

// Priority colors
const priorityColors: Record<Priority, string> = {
  urgent: 'bg-neutral-700',
  high: 'bg-neutral-600',
  medium: 'bg-neutral-500',
  low: 'bg-neutral-400',
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
const dropTargetIndex = ref<number | null>(null)

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

const columnDropdownItems = computed(() => [
  columnActions.map(action => ({
    label: action.label,
    icon: action.icon,
    color: action.variant === 'danger' ? 'error' as const : undefined,
    click: () => emit('action', action.id),
  })),
])

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

const handleDragLeave = (event: DragEvent) => {
  // Only reset if we're leaving the column entirely
  const relatedTarget = event.relatedTarget as HTMLElement | null
  if (!relatedTarget || !event.currentTarget || !(event.currentTarget as HTMLElement).contains(relatedTarget)) {
    isDragOver.value = false
    dropTargetIndex.value = null
  }
}

const handleDrop = (event: DragEvent) => {
  isDragOver.value = false
  const taskId = event.dataTransfer?.getData('taskId')
  const targetIndex = dropTargetIndex.value ?? filteredTasks.value.length
  dropTargetIndex.value = null
  if (taskId) {
    emit('drop', taskId, props.status, targetIndex)
  }
}

const handleCardDragOver = (event: DragEvent, index: number) => {
  event.preventDefault()
  event.stopPropagation()
  isDragOver.value = true
  event.dataTransfer!.dropEffect = 'move'

  // Determine if we should drop before or after this card
  const target = event.currentTarget as HTMLElement
  const rect = target.getBoundingClientRect()
  const midY = rect.top + rect.height / 2

  if (event.clientY < midY) {
    dropTargetIndex.value = index
  } else {
    dropTargetIndex.value = index + 1
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
