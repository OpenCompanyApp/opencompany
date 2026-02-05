<template>
  <div class="project-tree-item">
    <!-- Main row -->
    <button
      type="button"
      :class="[
        'w-full flex items-center gap-2 px-3 py-2 rounded-lg text-left transition-colors group',
        selected
          ? 'bg-neutral-100 dark:bg-neutral-800 text-neutral-900 dark:text-white'
          : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-50 dark:hover:bg-neutral-800/50'
      ]"
      :style="{ paddingLeft: `${12 + level * 16}px` }"
      @click="handleClick"
    >
      <!-- Expand/Collapse -->
      <button
        v-if="hasChildren"
        type="button"
        class="p-0.5 -ml-1 rounded hover:bg-neutral-200 dark:hover:bg-neutral-700 transition-colors"
        @click.stop="toggleExpanded"
      >
        <Icon
          :name="expanded ? 'ph:caret-down' : 'ph:caret-right'"
          class="w-3 h-3"
        />
      </button>
      <div v-else class="w-4" />

      <!-- Folder Icon -->
      <Icon
        :name="expanded ? 'ph:folder-open' : 'ph:folder'"
        class="w-4 h-4 text-amber-500"
      />

      <!-- Title -->
      <span class="text-sm font-medium truncate flex-1">{{ item.title }}</span>

      <!-- Task Count -->
      <span class="text-xs text-neutral-400 dark:text-neutral-500">{{ taskCount }}</span>

      <!-- Actions (on hover) -->
      <div class="opacity-0 group-hover:opacity-100 flex items-center gap-0.5 transition-opacity">
        <button
          type="button"
          class="p-1 rounded hover:bg-neutral-200 dark:hover:bg-neutral-700 transition-colors"
          title="Rename"
          @click.stop="emit('rename', item)"
        >
          <Icon name="ph:pencil-simple" class="w-3 h-3" />
        </button>
        <button
          type="button"
          class="p-1 rounded hover:bg-red-100 dark:hover:bg-red-900/30 text-neutral-500 hover:text-red-600 dark:hover:text-red-400 transition-colors"
          title="Delete"
          @click.stop="emit('delete', item)"
        >
          <Icon name="ph:trash" class="w-3 h-3" />
        </button>
      </div>
    </button>

    <!-- Children (recursive) -->
    <Transition
      enter-active-class="transition-all duration-150 ease-out overflow-hidden"
      enter-from-class="opacity-0 max-h-0"
      enter-to-class="opacity-100 max-h-[1000px]"
      leave-active-class="transition-all duration-150 ease-out overflow-hidden"
      leave-from-class="opacity-100 max-h-[1000px]"
      leave-to-class="opacity-0 max-h-0"
    >
      <div v-if="expanded && hasChildren">
        <ProjectTreeItem
          v-for="child in children"
          :key="child.id"
          :item="child"
          :all-items="allItems"
          :all-tasks="allTasks"
          :level="level + 1"
          :selected-id="selectedId"
          @select="emit('select', $event)"
          @rename="emit('rename', $event)"
          @delete="emit('delete', $event)"
        />
      </div>
    </Transition>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, defineAsyncComponent } from 'vue'
import type { Task } from '@/types'
import Icon from '@/Components/shared/Icon.vue'

// Self-reference for recursive component
const ProjectTreeItem = defineAsyncComponent(() => import('./ProjectTreeItem.vue'))

const props = defineProps<{
  item: Task
  allItems: Task[]
  allTasks: Task[]
  level: number
  selectedId: string | null
}>()

const emit = defineEmits<{
  select: [task: Task]
  rename: [task: Task]
  delete: [task: Task]
}>()

const expanded = ref(true)

const selected = computed(() => props.selectedId === props.item.id)

const children = computed(() =>
  props.allItems.filter(p => p.parentId === props.item.id)
)

const hasChildren = computed(() => children.value.length > 0)

// Count tasks in this project (direct children only, not folders)
const taskCount = computed(() => {
  const countTasksRecursive = (projectId: string): number => {
    // Count direct task children
    const directTasks = props.allTasks.filter(
      t => t.parentId === projectId && !t.isFolder
    ).length

    // Count tasks in child projects
    const childProjects = props.allItems.filter(p => p.parentId === projectId)
    const childTasks = childProjects.reduce(
      (sum, child) => sum + countTasksRecursive(child.id),
      0
    )

    return directTasks + childTasks
  }

  return countTasksRecursive(props.item.id)
})

const handleClick = () => {
  emit('select', props.item)
}

const toggleExpanded = () => {
  expanded.value = !expanded.value
}
</script>
