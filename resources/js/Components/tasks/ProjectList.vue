<template>
  <div class="flex flex-col h-full border-r border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900">
    <!-- Header -->
    <div class="p-4 border-b border-neutral-200 dark:border-neutral-700">
      <div class="flex items-center justify-between mb-3">
        <div>
          <h2 class="text-sm font-semibold text-neutral-900 dark:text-white">Projects</h2>
          <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
            {{ projectCount }} {{ projectCount === 1 ? 'project' : 'projects' }}
          </p>
        </div>
        <button
          type="button"
          class="p-1.5 rounded-lg bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 hover:bg-neutral-800 dark:hover:bg-neutral-100 transition-colors"
          @click="emit('createProject')"
        >
          <Icon name="ph:plus" class="w-4 h-4" />
        </button>
      </div>

      <!-- Search -->
      <div class="relative">
        <Icon
          name="ph:magnifying-glass"
          class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-neutral-400 dark:text-neutral-500 pointer-events-none"
        />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search projects..."
          class="w-full pl-9 pr-3 py-2 text-sm bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg outline-none placeholder:text-neutral-400 dark:placeholder:text-neutral-500 text-neutral-900 dark:text-white focus:border-neutral-300 dark:focus:border-neutral-600 focus:ring-1 focus:ring-neutral-300 dark:focus:ring-neutral-600 transition-all"
        />
        <button
          v-if="searchQuery"
          type="button"
          class="absolute right-2 top-1/2 -translate-y-1/2 p-1 rounded hover:bg-neutral-200 dark:hover:bg-neutral-700 transition-colors"
          @click="searchQuery = ''"
        >
          <Icon name="ph:x" class="w-3 h-3 text-neutral-400" />
        </button>
      </div>
    </div>

    <!-- Project List -->
    <div class="flex-1 overflow-y-auto p-2">
      <!-- All Tasks Option -->
      <button
        type="button"
        :class="[
          'w-full flex items-center gap-2 px-3 py-2 rounded-lg text-left transition-colors mb-1',
          !selectedId
            ? 'bg-neutral-100 dark:bg-neutral-800 text-neutral-900 dark:text-white'
            : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-50 dark:hover:bg-neutral-800/50'
        ]"
        @click="emit('select', null)"
      >
        <Icon name="ph:squares-four" class="w-4 h-4" />
        <span class="text-sm font-medium">All Tasks</span>
        <span class="ml-auto text-xs text-neutral-400 dark:text-neutral-500">{{ totalTaskCount }}</span>
      </button>

      <!-- Divider -->
      <div class="h-px bg-neutral-200 dark:bg-neutral-700 my-2" />

      <!-- Projects Tree -->
      <template v-if="displayedProjects.length > 0">
        <ProjectTreeItem
          v-for="project in rootProjects"
          :key="project.id"
          :item="project"
          :all-items="displayedProjects"
          :all-tasks="tasks"
          :level="0"
          :selected-id="selectedId"
          @select="handleSelect"
          @rename="emit('renameProject', $event)"
          @delete="emit('deleteProject', $event)"
        />
      </template>

      <!-- Empty State -->
      <div v-else-if="!searchQuery" class="flex flex-col items-center justify-center py-8 px-4">
        <div class="w-10 h-10 rounded-full bg-neutral-100 dark:bg-neutral-800 flex items-center justify-center mb-3">
          <Icon name="ph:folder-dashed" class="w-5 h-5 text-neutral-400 dark:text-neutral-500" />
        </div>
        <p class="text-sm font-medium text-neutral-900 dark:text-white mb-1">No projects yet</p>
        <p class="text-xs text-neutral-500 dark:text-neutral-400 text-center mb-3">
          Create a project to organize your tasks
        </p>
        <button
          type="button"
          class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 rounded-lg hover:bg-neutral-800 dark:hover:bg-neutral-100 transition-colors"
          @click="emit('createProject')"
        >
          <Icon name="ph:plus" class="w-3.5 h-3.5" />
          New Project
        </button>
      </div>

      <!-- Search Empty State -->
      <div v-else class="flex flex-col items-center justify-center py-8 px-4">
        <Icon name="ph:magnifying-glass" class="w-8 h-8 text-neutral-300 dark:text-neutral-600 mb-2" />
        <p class="text-sm text-neutral-500 dark:text-neutral-400">No projects match "{{ searchQuery }}"</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import type { Task } from '@/types'
import Icon from '@/Components/shared/Icon.vue'
import ProjectTreeItem from './ProjectTreeItem.vue'

const props = defineProps<{
  tasks: Task[]
  selectedId: string | null
}>()

const emit = defineEmits<{
  select: [id: string | null]
  createProject: []
  renameProject: [task: Task]
  deleteProject: [task: Task]
}>()

const searchQuery = ref('')

// Computed: Only folder tasks (projects)
const projects = computed(() =>
  props.tasks.filter(t => t.isFolder)
)

const projectCount = computed(() => projects.value.length)

const totalTaskCount = computed(() =>
  props.tasks.filter(t => !t.isFolder).length
)

const filteredProjects = computed(() => {
  if (!searchQuery.value) return projects.value
  const query = searchQuery.value.toLowerCase()
  return projects.value.filter(p =>
    p.title.toLowerCase().includes(query)
  )
})

const displayedProjects = computed(() => filteredProjects.value)

const rootProjects = computed(() =>
  displayedProjects.value.filter(p => !p.parentId)
)

const handleSelect = (project: Task) => {
  emit('select', project.id)
}
</script>
