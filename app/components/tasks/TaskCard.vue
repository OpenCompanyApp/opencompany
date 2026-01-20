<template>
  <div
    :class="[
      'relative group overflow-hidden transition-all duration-200',
      sizeConfig[size].container,
      variantClasses[variant],
      selected && 'ring-2 ring-olympus-primary ring-offset-2 ring-offset-olympus-elevated',
      task.status === 'done' && 'opacity-75',
      dragging && 'opacity-50 scale-[0.98] rotate-1',
      !disabled && 'cursor-grab active:cursor-grabbing',
      disabled && 'opacity-50 cursor-not-allowed'
    ]"
    :draggable="!disabled"
    @dragstart="handleDragStart"
    @dragend="handleDragEnd"
    @click="!disabled && $emit('click')"
    @dblclick="!disabled && $emit('open')"
  >
    <!-- Selection checkbox -->
    <Transition
      enter-active-class="transition-all duration-150"
      leave-active-class="transition-all duration-100"
      enter-from-class="opacity-0 scale-75"
      leave-to-class="opacity-0 scale-75"
    >
      <div
        v-if="selectable"
        :class="[
          'absolute z-10 opacity-0 group-hover:opacity-100 transition-opacity',
          sizeConfig[size].checkbox
        ]"
      >
        <button
          :class="[
            'flex items-center justify-center rounded-md border-2 transition-all duration-150',
            selected
              ? 'bg-olympus-primary border-olympus-primary'
              : 'bg-olympus-surface border-olympus-border hover:border-olympus-primary'
          ]"
          @click.stop="$emit('select')"
        >
          <Icon
            v-if="selected"
            name="ph:check-bold"
            :class="['text-white', sizeConfig[size].checkIcon]"
          />
        </button>
      </div>
    </Transition>

    <!-- Priority indicator bar -->
    <div
      :class="[
        'absolute top-0 left-0 right-0 h-1 transition-colors',
        priorityColorMap[task.priority]
      ]"
    />

    <!-- Header -->
    <div :class="['flex items-start justify-between', sizeConfig[size].header]">
      <!-- Priority badge -->
      <div class="flex items-center gap-2">
        <span
          :class="[
            'font-semibold rounded-lg flex items-center gap-1',
            sizeConfig[size].priorityBadge,
            priorityClasses[task.priority]
          ]"
        >
          <span
            :class="[
              'w-1.5 h-1.5 rounded-full',
              priorityDotClasses[task.priority]
            ]"
          />
          {{ priorityLabels[task.priority] }}
        </span>

        <!-- Type badge -->
        <span
          v-if="task.type"
          :class="[
            'rounded-md bg-olympus-surface text-olympus-text-muted flex items-center gap-1',
            sizeConfig[size].typeBadge
          ]"
        >
          <Icon :name="typeIcons[task.type]" :class="sizeConfig[size].typeIcon" />
          {{ task.type }}
        </span>
      </div>

      <!-- Actions -->
      <div
        :class="[
          'flex items-center opacity-0 group-hover:opacity-100 transition-opacity',
          sizeConfig[size].actionsGap
        ]"
      >
        <TooltipProvider :delay-duration="300">
          <TooltipRoot>
            <TooltipTrigger as-child>
              <button
                :class="[
                  'rounded hover:bg-olympus-border transition-colors',
                  sizeConfig[size].actionButton
                ]"
                @click.stop="$emit('edit')"
              >
                <Icon name="ph:pencil-simple" :class="['text-olympus-text-muted', sizeConfig[size].actionIcon]" />
              </button>
            </TooltipTrigger>
            <TooltipPortal>
              <TooltipContent
                class="bg-olympus-elevated border border-olympus-border rounded-lg px-2 py-1 text-xs shadow-lg z-50"
                :side-offset="5"
              >
                Edit task
                <TooltipArrow class="fill-olympus-elevated" />
              </TooltipContent>
            </TooltipPortal>
          </TooltipRoot>
        </TooltipProvider>

        <DropdownMenuRoot>
          <DropdownMenuTrigger as-child>
            <button
              :class="[
                'rounded hover:bg-olympus-border transition-colors',
                sizeConfig[size].actionButton
              ]"
              @click.stop
            >
              <Icon name="ph:dots-three" :class="['text-olympus-text-muted', sizeConfig[size].actionIcon]" />
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
                v-for="action in taskActions"
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

    <!-- ID badge -->
    <span
      v-if="showId && task.id"
      :class="[
        'text-olympus-text-subtle font-mono bg-olympus-surface rounded',
        sizeConfig[size].idBadge
      ]"
    >
      #{{ task.id.slice(0, 6) }}
    </span>

    <!-- Title -->
    <h4
      :class="[
        'font-medium leading-snug',
        sizeConfig[size].title,
        task.status === 'done' && 'line-through text-olympus-text-muted'
      ]"
    >
      {{ task.title }}
    </h4>

    <!-- Description -->
    <p
      v-if="task.description"
      :class="[
        'text-olympus-text-muted line-clamp-2',
        sizeConfig[size].description
      ]"
    >
      {{ task.description }}
    </p>

    <!-- Labels -->
    <div
      v-if="task.labels && task.labels.length > 0"
      :class="['flex flex-wrap', sizeConfig[size].labelsGap]"
    >
      <span
        v-for="label in task.labels.slice(0, maxLabels)"
        :key="label.id"
        :class="[
          'rounded-full font-medium',
          sizeConfig[size].label
        ]"
        :style="{ backgroundColor: `${label.color}20`, color: label.color }"
      >
        {{ label.name }}
      </span>
      <span
        v-if="task.labels.length > maxLabels"
        :class="[
          'rounded-full bg-olympus-surface text-olympus-text-muted',
          sizeConfig[size].label
        ]"
      >
        +{{ task.labels.length - maxLabels }}
      </span>
    </div>

    <!-- Progress bar -->
    <div v-if="task.progress !== undefined" :class="sizeConfig[size].progressContainer">
      <div class="flex items-center justify-between mb-1">
        <span :class="['text-olympus-text-muted', sizeConfig[size].progressLabel]">
          Progress
        </span>
        <span :class="['font-medium', sizeConfig[size].progressValue]">
          {{ task.progress }}%
        </span>
      </div>
      <div :class="['bg-olympus-border rounded-full overflow-hidden', sizeConfig[size].progressBar]">
        <div
          class="h-full bg-olympus-primary rounded-full transition-all duration-500"
          :style="{ width: `${task.progress}%` }"
        />
      </div>
    </div>

    <!-- Subtasks -->
    <div
      v-if="task.subtasks && task.subtasks.length > 0"
      :class="['flex items-center', sizeConfig[size].subtasks]"
    >
      <Icon name="ph:list-checks" :class="['text-olympus-text-muted mr-1.5', sizeConfig[size].subtaskIcon]" />
      <span :class="['text-olympus-text-muted', sizeConfig[size].subtaskText]">
        {{ completedSubtasks }}/{{ task.subtasks.length }} subtasks
      </span>
      <div :class="['flex-1 bg-olympus-border rounded-full overflow-hidden ml-2', sizeConfig[size].subtaskBar]">
        <div
          class="h-full bg-olympus-success rounded-full transition-all duration-300"
          :style="{ width: `${subtaskProgress}%` }"
        />
      </div>
    </div>

    <!-- Footer -->
    <div :class="['flex items-center justify-between', sizeConfig[size].footer]">
      <!-- Assignees -->
      <div class="flex items-center">
        <div :class="['flex', sizeConfig[size].avatarStack]">
          <SharedAgentAvatar
            :user="task.assignee"
            :size="avatarSize"
            class="ring-2 ring-olympus-surface"
          />
          <SharedAgentAvatar
            v-for="collab in (task.collaborators || []).slice(0, maxCollaborators)"
            :key="collab.id"
            :user="collab"
            :size="avatarSize"
            class="ring-2 ring-olympus-surface"
          />
        </div>
        <span
          v-if="task.collaborators && task.collaborators.length > maxCollaborators"
          :class="['text-olympus-text-muted ml-1', sizeConfig[size].moreCount]"
        >
          +{{ task.collaborators.length - maxCollaborators }}
        </span>
      </div>

      <!-- Meta info -->
      <div :class="['flex items-center', sizeConfig[size].metaGap]">
        <!-- Due date -->
        <div
          v-if="task.dueDate"
          :class="[
            'flex items-center',
            sizeConfig[size].metaItem,
            isOverdue ? 'text-olympus-error' : isPastDue ? 'text-olympus-warning' : 'text-olympus-text-muted'
          ]"
        >
          <Icon name="ph:calendar" :class="['mr-1', sizeConfig[size].metaIcon]" />
          <span>{{ formatDueDate(task.dueDate) }}</span>
        </div>

        <!-- Comments count -->
        <div
          v-if="task.commentsCount"
          :class="['flex items-center text-olympus-text-muted', sizeConfig[size].metaItem]"
        >
          <Icon name="ph:chat-circle" :class="['mr-1', sizeConfig[size].metaIcon]" />
          <span>{{ task.commentsCount }}</span>
        </div>

        <!-- Attachments count -->
        <div
          v-if="task.attachmentsCount"
          :class="['flex items-center text-olympus-text-muted', sizeConfig[size].metaItem]"
        >
          <Icon name="ph:paperclip" :class="['mr-1', sizeConfig[size].metaIcon]" />
          <span>{{ task.attachmentsCount }}</span>
        </div>

        <!-- Cost -->
        <SharedCostBadge
          v-if="task.cost || task.estimatedCost"
          :cost="task.cost || task.estimatedCost!"
          :variant="task.cost ? 'actual' : 'estimated'"
          :size="costBadgeSize"
        />
      </div>
    </div>

    <!-- Completion indicator -->
    <Transition
      enter-active-class="transition-all duration-200"
      leave-active-class="transition-all duration-150"
      enter-from-class="opacity-0 -translate-y-2"
      leave-to-class="opacity-0 -translate-y-2"
    >
      <div
        v-if="task.status === 'done' && task.completedAt"
        :class="[
          'flex items-center border-t border-olympus-border',
          sizeConfig[size].completionBar
        ]"
      >
        <Icon name="ph:check-circle-fill" :class="['text-olympus-success mr-2', sizeConfig[size].completionIcon]" />
        <span :class="['text-olympus-text-muted', sizeConfig[size].completionText]">
          Completed {{ formatDate(task.completedAt) }}
        </span>
        <span
          v-if="task.completedBy"
          :class="['text-olympus-text-subtle ml-1', sizeConfig[size].completionText]"
        >
          by {{ task.completedBy.name }}
        </span>
      </div>
    </Transition>

    <!-- AI working indicator -->
    <Transition
      enter-active-class="transition-all duration-300"
      leave-active-class="transition-all duration-200"
      enter-from-class="opacity-0"
      leave-to-class="opacity-0"
    >
      <div
        v-if="task.isAIWorking"
        :class="[
          'absolute inset-0 bg-gradient-to-r from-olympus-primary/5 to-olympus-accent/5 pointer-events-none',
          'animate-pulse'
        ]"
      />
    </Transition>

    <!-- Working indicator dot -->
    <div
      v-if="task.isAIWorking"
      :class="[
        'absolute flex items-center gap-1.5 text-olympus-primary',
        sizeConfig[size].workingIndicator
      ]"
    >
      <span class="relative flex h-2 w-2">
        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-olympus-primary opacity-75" />
        <span class="relative inline-flex rounded-full h-2 w-2 bg-olympus-primary" />
      </span>
      <span :class="['font-medium', sizeConfig[size].workingText]">AI Working</span>
    </div>

    <!-- Hover gradient effect -->
    <div
      class="absolute inset-0 bg-gradient-to-br from-olympus-primary/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none"
    />
  </div>
</template>

<script setup lang="ts">
import type { Task, Priority } from '~/types'
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
type CardSize = 'sm' | 'md' | 'lg'
type CardVariant = 'default' | 'outlined' | 'elevated'

interface TaskAction {
  id: string
  label: string
  icon: string
  variant?: 'default' | 'danger'
}

interface SizeConfig {
  container: string
  checkbox: string
  checkIcon: string
  header: string
  priorityBadge: string
  typeBadge: string
  typeIcon: string
  actionsGap: string
  actionButton: string
  actionIcon: string
  dropdownContent: string
  dropdownItem: string
  dropdownIcon: string
  idBadge: string
  title: string
  description: string
  labelsGap: string
  label: string
  progressContainer: string
  progressLabel: string
  progressValue: string
  progressBar: string
  subtasks: string
  subtaskIcon: string
  subtaskText: string
  subtaskBar: string
  footer: string
  avatarStack: string
  moreCount: string
  metaGap: string
  metaItem: string
  metaIcon: string
  completionBar: string
  completionIcon: string
  completionText: string
  workingIndicator: string
  workingText: string
}

// Props
const props = withDefaults(defineProps<{
  task: Task
  size?: CardSize
  variant?: CardVariant
  selectable?: boolean
  selected?: boolean
  disabled?: boolean
  showId?: boolean
  maxLabels?: number
  maxCollaborators?: number
}>(), {
  size: 'md',
  variant: 'default',
  selectable: false,
  selected: false,
  disabled: false,
  showId: false,
  maxLabels: 3,
  maxCollaborators: 2,
})

// Emits
defineEmits<{
  click: []
  open: []
  edit: []
  select: []
  action: [actionId: string]
  dragStart: [task: Task]
  dragEnd: []
}>()

// Size configuration
const sizeConfig: Record<CardSize, SizeConfig> = {
  sm: {
    container: 'bg-olympus-surface rounded-xl p-3 border border-olympus-border',
    checkbox: 'top-2 left-2 w-4 h-4',
    checkIcon: 'w-2.5 h-2.5',
    header: 'gap-2 mb-2',
    priorityBadge: 'px-1.5 py-0.5 text-[10px]',
    typeBadge: 'px-1.5 py-0.5 text-[10px]',
    typeIcon: 'w-2.5 h-2.5',
    actionsGap: 'gap-0.5',
    actionButton: 'p-0.5',
    actionIcon: 'w-3 h-3',
    dropdownContent: 'p-1 min-w-32',
    dropdownItem: 'gap-1.5 px-2 py-1.5 text-xs',
    dropdownIcon: 'w-3 h-3',
    idBadge: 'text-[9px] px-1 py-0.5 mb-1 inline-block',
    title: 'text-xs mb-1.5',
    description: 'text-[10px] mb-2',
    labelsGap: 'gap-1 mb-2',
    label: 'px-1.5 py-0.5 text-[9px]',
    progressContainer: 'mb-2',
    progressLabel: 'text-[10px]',
    progressValue: 'text-[10px]',
    progressBar: 'h-1',
    subtasks: 'gap-1 mb-2',
    subtaskIcon: 'w-3 h-3',
    subtaskText: 'text-[10px]',
    subtaskBar: 'h-1',
    footer: 'mt-2 pt-2 border-t border-olympus-border/50',
    avatarStack: '-space-x-1.5',
    moreCount: 'text-[10px]',
    metaGap: 'gap-2',
    metaItem: 'text-[10px]',
    metaIcon: 'w-3 h-3',
    completionBar: 'mt-2 pt-2 text-[10px]',
    completionIcon: 'w-3 h-3',
    completionText: 'text-[10px]',
    workingIndicator: 'top-2 right-2',
    workingText: 'text-[10px]',
  },
  md: {
    container: 'bg-olympus-surface rounded-2xl p-4 border border-olympus-border',
    checkbox: 'top-3 left-3 w-5 h-5',
    checkIcon: 'w-3 h-3',
    header: 'gap-2 mb-3',
    priorityBadge: 'px-2 py-0.5 text-xs',
    typeBadge: 'px-2 py-0.5 text-xs',
    typeIcon: 'w-3 h-3',
    actionsGap: 'gap-1',
    actionButton: 'p-1',
    actionIcon: 'w-3.5 h-3.5',
    dropdownContent: 'p-1.5 min-w-40',
    dropdownItem: 'gap-2 px-3 py-2 text-sm',
    dropdownIcon: 'w-4 h-4',
    idBadge: 'text-[10px] px-1.5 py-0.5 mb-1.5 inline-block',
    title: 'text-sm mb-2',
    description: 'text-xs mb-3',
    labelsGap: 'gap-1.5 mb-3',
    label: 'px-2 py-0.5 text-[10px]',
    progressContainer: 'mb-3',
    progressLabel: 'text-xs',
    progressValue: 'text-xs',
    progressBar: 'h-1.5',
    subtasks: 'gap-1.5 mb-3',
    subtaskIcon: 'w-3.5 h-3.5',
    subtaskText: 'text-xs',
    subtaskBar: 'h-1',
    footer: 'mt-3 pt-3 border-t border-olympus-border/50',
    avatarStack: '-space-x-2',
    moreCount: 'text-xs',
    metaGap: 'gap-3',
    metaItem: 'text-xs',
    metaIcon: 'w-3.5 h-3.5',
    completionBar: 'mt-3 pt-3 text-xs',
    completionIcon: 'w-4 h-4',
    completionText: 'text-xs',
    workingIndicator: 'top-3 right-3',
    workingText: 'text-xs',
  },
  lg: {
    container: 'bg-olympus-surface rounded-2xl p-5 border border-olympus-border',
    checkbox: 'top-4 left-4 w-6 h-6',
    checkIcon: 'w-4 h-4',
    header: 'gap-3 mb-4',
    priorityBadge: 'px-2.5 py-1 text-sm',
    typeBadge: 'px-2.5 py-1 text-sm',
    typeIcon: 'w-4 h-4',
    actionsGap: 'gap-1.5',
    actionButton: 'p-1.5',
    actionIcon: 'w-4 h-4',
    dropdownContent: 'p-2 min-w-48',
    dropdownItem: 'gap-2.5 px-4 py-2.5 text-base',
    dropdownIcon: 'w-5 h-5',
    idBadge: 'text-xs px-2 py-1 mb-2 inline-block',
    title: 'text-base mb-2.5',
    description: 'text-sm mb-4',
    labelsGap: 'gap-2 mb-4',
    label: 'px-2.5 py-1 text-xs',
    progressContainer: 'mb-4',
    progressLabel: 'text-sm',
    progressValue: 'text-sm',
    progressBar: 'h-2',
    subtasks: 'gap-2 mb-4',
    subtaskIcon: 'w-4 h-4',
    subtaskText: 'text-sm',
    subtaskBar: 'h-1.5',
    footer: 'mt-4 pt-4 border-t border-olympus-border/50',
    avatarStack: '-space-x-2.5',
    moreCount: 'text-sm',
    metaGap: 'gap-4',
    metaItem: 'text-sm',
    metaIcon: 'w-4 h-4',
    completionBar: 'mt-4 pt-4 text-sm',
    completionIcon: 'w-5 h-5',
    completionText: 'text-sm',
    workingIndicator: 'top-4 right-4',
    workingText: 'text-sm',
  },
}

// Variant classes
const variantClasses: Record<CardVariant, string> = {
  default: 'hover:border-olympus-primary/50 hover:shadow-lg hover:shadow-olympus-primary/5',
  outlined: 'border-2 hover:border-olympus-primary',
  elevated: 'shadow-lg hover:shadow-xl hover:shadow-olympus-primary/10 border-transparent',
}

// Priority styling
const priorityClasses: Record<Priority, string> = {
  low: 'bg-gray-500/20 text-gray-400',
  medium: 'bg-blue-500/20 text-blue-400',
  high: 'bg-amber-500/20 text-amber-400',
  urgent: 'bg-red-500/20 text-red-400',
}

const priorityColorMap: Record<Priority, string> = {
  low: 'bg-gray-400',
  medium: 'bg-blue-400',
  high: 'bg-amber-400',
  urgent: 'bg-red-400',
}

const priorityDotClasses: Record<Priority, string> = {
  low: 'bg-gray-400',
  medium: 'bg-blue-400',
  high: 'bg-amber-400',
  urgent: 'bg-red-400 animate-pulse',
}

const priorityLabels: Record<Priority, string> = {
  low: 'Low',
  medium: 'Medium',
  high: 'High',
  urgent: 'Urgent',
}

// Type icons
const typeIcons: Record<string, string> = {
  bug: 'ph:bug',
  feature: 'ph:star',
  improvement: 'ph:arrow-up',
  task: 'ph:check-square',
  research: 'ph:magnifying-glass',
}

// Task actions
const taskActions: TaskAction[] = [
  { id: 'view', label: 'View details', icon: 'ph:eye' },
  { id: 'edit', label: 'Edit task', icon: 'ph:pencil-simple' },
  { id: 'duplicate', label: 'Duplicate', icon: 'ph:copy' },
  { id: 'move', label: 'Move to...', icon: 'ph:arrow-right' },
  { id: 'archive', label: 'Archive', icon: 'ph:archive' },
  { id: 'delete', label: 'Delete', icon: 'ph:trash', variant: 'danger' },
]

// Computed
const avatarSize = computed(() => {
  return props.size === 'sm' ? 'xs' : props.size === 'lg' ? 'md' : 'sm'
})

const costBadgeSize = computed(() => {
  return props.size === 'sm' ? 'xs' : props.size === 'lg' ? 'sm' : 'xs'
})

const completedSubtasks = computed(() => {
  if (!props.task.subtasks) return 0
  return props.task.subtasks.filter(s => s.completed).length
})

const subtaskProgress = computed(() => {
  if (!props.task.subtasks || props.task.subtasks.length === 0) return 0
  return Math.round((completedSubtasks.value / props.task.subtasks.length) * 100)
})

const isOverdue = computed(() => {
  if (!props.task.dueDate) return false
  return new Date(props.task.dueDate) < new Date()
})

const isPastDue = computed(() => {
  if (!props.task.dueDate) return false
  const dueDate = new Date(props.task.dueDate)
  const now = new Date()
  const diffTime = dueDate.getTime() - now.getTime()
  const diffDays = diffTime / (1000 * 60 * 60 * 24)
  return diffDays <= 1 && diffDays > 0
})

// State
const dragging = ref(false)

// Methods
const formatDate = (date: Date) => {
  const d = new Date(date)
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

const formatDueDate = (date: Date) => {
  const d = new Date(date)
  const now = new Date()
  const diffTime = d.getTime() - now.getTime()
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))

  if (diffDays < 0) return `${Math.abs(diffDays)}d overdue`
  if (diffDays === 0) return 'Today'
  if (diffDays === 1) return 'Tomorrow'
  if (diffDays < 7) return `${diffDays}d`
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

const handleDragStart = (event: DragEvent) => {
  if (props.disabled) return
  dragging.value = true
  if (event.dataTransfer) {
    event.dataTransfer.setData('taskId', props.task.id)
    event.dataTransfer.effectAllowed = 'move'
  }
}

const handleDragEnd = () => {
  dragging.value = false
}
</script>

<style scoped>
@keyframes shimmer {
  0% {
    background-position: -200% 0;
  }
  100% {
    background-position: 200% 0;
  }
}

.animate-shimmer {
  background: linear-gradient(
    90deg,
    transparent,
    oklch(var(--color-olympus-primary) / 0.1),
    transparent
  );
  background-size: 200% 100%;
  animation: shimmer 2s infinite;
}
</style>
