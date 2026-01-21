<template>
  <div
    :class="[
      'relative',
      sizeConfig[size].container,
      loading && 'pointer-events-none'
    ]"
  >
    <!-- Header -->
    <div
      v-if="showHeader"
      :class="[
        'flex items-center justify-between border-b border-gray-200',
        sizeConfig[size].header
      ]"
    >
      <div class="flex items-center gap-3">
        <h2 :class="['font-semibold text-gray-900', sizeConfig[size].title]">
          {{ title }}
        </h2>
        <span
          v-if="totalTasks > 0"
          :class="[
            'font-medium bg-gray-100 text-gray-500 rounded-full',
            sizeConfig[size].badge
          ]"
        >
          {{ totalTasks }} {{ totalTasks === 1 ? 'task' : 'tasks' }}
        </span>
      </div>

      <!-- Quick stats -->
      <div :class="['flex items-center', sizeConfig[size].statsGap]">
        <div
          v-for="stat in columnStats"
          :key="stat.status"
          :class="['flex items-center', sizeConfig[size].stat]"
        >
          <span :class="['w-2 h-2 rounded-full mr-1.5', stat.color]" />
          <span class="text-gray-500">{{ stat.count }}</span>
        </div>
      </div>
    </div>

    <!-- Board content -->
    <div
      ref="boardRef"
      :class="[
        'flex overflow-x-auto',
        sizeConfig[size].board,
        scrollSnap && 'snap-x snap-mandatory'
      ]"
      @scroll="handleScroll"
    >
      <!-- Scroll shadow left -->
      <Transition
        enter-active-class="transition-opacity duration-200"
        leave-active-class="transition-opacity duration-150"
        enter-from-class="opacity-0"
        leave-to-class="opacity-0"
      >
        <div
          v-if="showLeftShadow"
          class="absolute left-0 top-0 bottom-0 w-8 bg-gradient-to-r from-white to-transparent pointer-events-none z-10"
        />
      </Transition>

      <!-- Columns -->
      <TransitionGroup
        tag="div"
        :class="['flex', sizeConfig[size].columnsGap]"
        enter-active-class="transition-all duration-150 ease-out"
        leave-active-class="transition-all duration-100 ease-out"
        enter-from-class="opacity-0"
        leave-to-class="opacity-0"
        move-class="transition-transform duration-150 ease-out"
      >
        <TaskColumn
          v-for="column in visibleColumns"
          :key="column.status"
          :title="column.title"
          :icon="column.icon"
          :color="column.color"
          :tasks="tasksByStatus[column.status]"
          :status="column.status"
          :size="size"
          :collapsible="collapsibleColumns"
          :collapsed="collapsedColumns.includes(column.status)"
          :max-tasks="maxTasksPerColumn"
          :loading="loading"
          :class="[scrollSnap && 'snap-center']"
          @drop="handleDrop"
          @toggle="toggleColumnCollapse(column.status)"
          @add="$emit('addTask', column.status)"
          @task-click="(task) => $emit('taskClick', task)"
        />

        <!-- Add column button -->
        <div
          v-if="allowAddColumn"
          :class="[
            'flex flex-col items-center justify-center shrink-0 border-2 border-dashed border-gray-200 rounded-lg',
            'hover:border-gray-400 hover:bg-gray-50 transition-colors duration-150 cursor-pointer group',
            sizeConfig[size].addColumn
          ]"
          @click="$emit('addColumn')"
        >
          <Icon
            name="ph:plus"
            :class="[
              'text-gray-400 group-hover:text-gray-600 transition-colors',
              sizeConfig[size].addColumnIcon
            ]"
          />
          <span
            :class="[
              'text-gray-400 group-hover:text-gray-600 transition-colors font-medium mt-2',
              sizeConfig[size].addColumnText
            ]"
          >
            Add column
          </span>
        </div>
      </TransitionGroup>

      <!-- Scroll shadow right -->
      <Transition
        enter-active-class="transition-opacity duration-200"
        leave-active-class="transition-opacity duration-150"
        enter-from-class="opacity-0"
        leave-to-class="opacity-0"
      >
        <div
          v-if="showRightShadow"
          class="absolute right-0 top-0 bottom-0 w-8 bg-gradient-to-l from-white to-transparent pointer-events-none z-10"
        />
      </Transition>
    </div>

    <!-- Loading overlay -->
    <Transition
      enter-active-class="transition-opacity duration-200"
      leave-active-class="transition-opacity duration-150"
      enter-from-class="opacity-0"
      leave-to-class="opacity-0"
    >
      <div
        v-if="loading"
        class="absolute inset-0 bg-white/80 flex items-center justify-center z-20"
      >
        <div class="flex flex-col items-center gap-3">
          <div class="relative">
            <div class="w-10 h-10 border-3 border-gray-200 rounded-full" />
            <div class="absolute inset-0 w-10 h-10 border-3 border-gray-600 border-t-transparent rounded-full animate-spin" />
          </div>
          <span class="text-sm text-gray-500">Loading tasks...</span>
        </div>
      </div>
    </Transition>

    <!-- Empty state -->
    <Transition
      enter-active-class="transition-all duration-150 ease-out"
      leave-active-class="transition-all duration-100 ease-out"
      enter-from-class="opacity-0"
      leave-to-class="opacity-0"
    >
      <div
        v-if="!loading && totalTasks === 0"
        :class="[
          'absolute inset-0 flex flex-col items-center justify-center text-center',
          sizeConfig[size].emptyState
        ]"
      >
        <div
          :class="[
            'relative mb-4 rounded-lg bg-gray-100 flex items-center justify-center',
            sizeConfig[size].emptyIcon
          ]"
        >
          <Icon
            name="ph:kanban"
            :class="['text-gray-400', sizeConfig[size].emptyIconSize]"
          />
        </div>
        <h3 :class="['font-semibold text-gray-900 mb-1', sizeConfig[size].emptyTitle]">
          No tasks yet
        </h3>
        <p :class="['text-gray-500 max-w-sm', sizeConfig[size].emptyDescription]">
          Create your first task to get started with your project board
        </p>
        <button
          :class="[
            'mt-4 flex items-center gap-2 bg-gray-900 text-white rounded-lg font-medium',
            'hover:bg-gray-800 transition-colors',
            sizeConfig[size].emptyButton
          ]"
          @click="$emit('addTask', 'backlog')"
        >
          <Icon name="ph:plus-bold" class="w-4 h-4" />
          <span>Create task</span>
        </button>
      </div>
    </Transition>

    <!-- Keyboard shortcuts hint -->
    <Transition
      enter-active-class="transition-all duration-150 ease-out"
      leave-active-class="transition-all duration-100 ease-out"
      enter-from-class="opacity-0"
      leave-to-class="opacity-0"
    >
      <div
        v-if="showKeyboardHints && !loading"
        :class="[
          'absolute flex items-center gap-4 bg-white border border-gray-200 rounded-lg shadow-sm',
          sizeConfig[size].keyboardHints
        ]"
      >
        <div class="flex items-center gap-1.5">
          <kbd
            :class="[
              'font-mono bg-gray-50 border border-gray-200 rounded',
              sizeConfig[size].kbd
            ]"
          >
            N
          </kbd>
          <span class="text-gray-500">New task</span>
        </div>
        <div class="flex items-center gap-1.5">
          <kbd
            :class="[
              'font-mono bg-gray-50 border border-gray-200 rounded',
              sizeConfig[size].kbd
            ]"
          >
            ←
          </kbd>
          <kbd
            :class="[
              'font-mono bg-gray-50 border border-gray-200 rounded',
              sizeConfig[size].kbd
            ]"
          >
            →
          </kbd>
          <span class="text-gray-500">Move task</span>
        </div>
        <div class="flex items-center gap-1.5">
          <kbd
            :class="[
              'font-mono bg-gray-50 border border-gray-200 rounded',
              sizeConfig[size].kbd
            ]"
          >
            /
          </kbd>
          <span class="text-gray-500">Search</span>
        </div>
      </div>
    </Transition>

    <!-- Drag preview placeholder -->
    <div
      v-if="draggedTask"
      class="fixed pointer-events-none z-50 opacity-80"
      :style="{ left: `${dragPosition.x}px`, top: `${dragPosition.y}px` }"
    >
      <TaskCard
        :task="draggedTask"
        :size="size"
        class="shadow-2xl rotate-3"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import type { Task, TaskStatus } from '@/types'
import TaskColumn from '@/Components/tasks/TaskColumn.vue'
import TaskCard from '@/Components/tasks/TaskCard.vue'

// Types
type BoardSize = 'sm' | 'md' | 'lg'

interface Column {
  status: TaskStatus
  title: string
  icon: string
  color: string
}

interface ColumnStat {
  status: TaskStatus
  count: number
  color: string
}

interface SizeConfig {
  container: string
  header: string
  title: string
  badge: string
  statsGap: string
  stat: string
  board: string
  columnsGap: string
  addColumn: string
  addColumnIcon: string
  addColumnText: string
  emptyState: string
  emptyIcon: string
  emptyIconSize: string
  emptyTitle: string
  emptyDescription: string
  emptyButton: string
  keyboardHints: string
  kbd: string
}

// Props
const props = withDefaults(defineProps<{
  tasks: Task[]
  title?: string
  showHeader?: boolean
  size?: BoardSize
  loading?: boolean
  collapsibleColumns?: boolean
  allowAddColumn?: boolean
  maxTasksPerColumn?: number
  scrollSnap?: boolean
  showKeyboardHints?: boolean
}>(), {
  title: 'Task Board',
  showHeader: true,
  size: 'md',
  loading: false,
  collapsibleColumns: true,
  allowAddColumn: false,
  scrollSnap: false,
  showKeyboardHints: true,
})

// Emits
const emit = defineEmits<{
  update: [taskId: string, newStatus: TaskStatus, newIndex: number]
  addTask: [status: TaskStatus]
  addColumn: []
  taskClick: [task: Task]
}>()

// Size configuration
const sizeConfig: Record<BoardSize, SizeConfig> = {
  sm: {
    container: 'min-h-[300px]',
    header: 'px-4 py-2 mb-3',
    title: 'text-sm',
    badge: 'text-[10px] px-1.5 py-0.5',
    statsGap: 'gap-3',
    stat: 'text-[10px]',
    board: 'gap-4 p-4 min-h-[250px]',
    columnsGap: 'gap-4',
    addColumn: 'w-60 min-h-[200px]',
    addColumnIcon: 'w-6 h-6',
    addColumnText: 'text-xs',
    emptyState: 'p-4',
    emptyIcon: 'w-12 h-12',
    emptyIconSize: 'w-6 h-6',
    emptyTitle: 'text-sm',
    emptyDescription: 'text-xs',
    emptyButton: 'px-3 py-1.5 text-xs',
    keyboardHints: 'bottom-3 right-3 px-3 py-2 text-[10px]',
    kbd: 'px-1 py-0.5 text-[9px]',
  },
  md: {
    container: 'min-h-[400px]',
    header: 'px-6 py-3 mb-4',
    title: 'text-base',
    badge: 'text-xs px-2 py-0.5',
    statsGap: 'gap-4',
    stat: 'text-xs',
    board: 'gap-6 p-6 min-h-[350px]',
    columnsGap: 'gap-6',
    addColumn: 'w-72 min-h-[300px]',
    addColumnIcon: 'w-8 h-8',
    addColumnText: 'text-sm',
    emptyState: 'p-6',
    emptyIcon: 'w-16 h-16',
    emptyIconSize: 'w-8 h-8',
    emptyTitle: 'text-base',
    emptyDescription: 'text-sm',
    emptyButton: 'px-4 py-2 text-sm',
    keyboardHints: 'bottom-4 right-4 px-4 py-2.5 text-xs',
    kbd: 'px-1.5 py-0.5 text-[10px]',
  },
  lg: {
    container: 'min-h-[500px]',
    header: 'px-8 py-4 mb-5',
    title: 'text-lg',
    badge: 'text-sm px-2.5 py-1',
    statsGap: 'gap-5',
    stat: 'text-sm',
    board: 'gap-8 p-8 min-h-[450px]',
    columnsGap: 'gap-8',
    addColumn: 'w-80 min-h-[400px]',
    addColumnIcon: 'w-10 h-10',
    addColumnText: 'text-base',
    emptyState: 'p-8',
    emptyIcon: 'w-20 h-20',
    emptyIconSize: 'w-10 h-10',
    emptyTitle: 'text-lg',
    emptyDescription: 'text-base',
    emptyButton: 'px-5 py-2.5 text-base',
    keyboardHints: 'bottom-5 right-5 px-5 py-3 text-sm',
    kbd: 'px-2 py-1 text-xs',
  },
}

// Columns configuration
const columns: Column[] = [
  { status: 'backlog', title: 'Backlog', icon: 'ph:circle-dashed', color: 'bg-gray-400' },
  { status: 'in_progress', title: 'In Progress', icon: 'ph:circle-half', color: 'bg-gray-600' },
  { status: 'done', title: 'Done', icon: 'ph:check-circle', color: 'bg-gray-800' },
]

// State
const boardRef = ref<HTMLElement | null>(null)
const collapsedColumns = ref<TaskStatus[]>([])
const showLeftShadow = ref(false)
const showRightShadow = ref(false)
const draggedTask = ref<Task | null>(null)
const dragPosition = ref({ x: 0, y: 0 })

// Computed
const visibleColumns = computed(() => columns)

const tasksByStatus = computed(() => {
  const grouped: Record<TaskStatus, Task[]> = {
    backlog: [],
    in_progress: [],
    done: [],
  }

  props.tasks.forEach(task => {
    if (grouped[task.status]) {
      grouped[task.status].push(task)
    }
  })

  return grouped
})

const totalTasks = computed(() => props.tasks.length)

const columnStats = computed<ColumnStat[]>(() => {
  return columns.map(column => ({
    status: column.status,
    count: tasksByStatus.value[column.status].length,
    color: column.color,
  }))
})

// Methods
const handleDrop = (taskId: string, newStatus: TaskStatus, newIndex: number) => {
  emit('update', taskId, newStatus, newIndex)
}

const toggleColumnCollapse = (status: TaskStatus) => {
  const index = collapsedColumns.value.indexOf(status)
  if (index === -1) {
    collapsedColumns.value.push(status)
  } else {
    collapsedColumns.value.splice(index, 1)
  }
}

const handleScroll = () => {
  if (!boardRef.value) return

  const { scrollLeft, scrollWidth, clientWidth } = boardRef.value
  showLeftShadow.value = scrollLeft > 0
  showRightShadow.value = scrollLeft + clientWidth < scrollWidth - 10
}

// Keyboard shortcuts
const handleKeydown = (event: KeyboardEvent) => {
  if (event.target instanceof HTMLInputElement || event.target instanceof HTMLTextAreaElement) {
    return
  }

  switch (event.key.toLowerCase()) {
    case 'n':
      event.preventDefault()
      emit('addTask', 'backlog')
      break
  }
}

// Lifecycle
onMounted(() => {
  window.addEventListener('keydown', handleKeydown)
  // Check initial scroll state
  nextTick(() => {
    handleScroll()
  })
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown)
})

// Watch for task changes to update scroll shadows
watch(() => props.tasks, () => {
  nextTick(() => {
    handleScroll()
  })
})
</script>

<style scoped>
.snap-x {
  scroll-snap-type: x mandatory;
}

.snap-center {
  scroll-snap-align: center;
}

.snap-mandatory {
  scroll-snap-type: x mandatory;
}
</style>
