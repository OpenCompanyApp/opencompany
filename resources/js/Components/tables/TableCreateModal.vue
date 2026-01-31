<template>
  <Modal :open="true" size="md" @close="$emit('close')">
    <template #title>
      Create New Table
    </template>

    <form class="space-y-4" @submit.prevent="handleSubmit">
      <!-- Name -->
      <div>
        <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
          Name
        </label>
        <Input
          v-model="form.name"
          placeholder="e.g., Customer Database"
          required
        />
      </div>

      <!-- Description -->
      <div>
        <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
          Description
        </label>
        <textarea
          v-model="form.description"
          rows="2"
          class="w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm text-neutral-900 dark:text-white placeholder:text-neutral-400 focus:outline-none focus:ring-2 focus:ring-blue-500"
          placeholder="What is this table for?"
        />
      </div>

      <!-- Icon -->
      <div>
        <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
          Icon
        </label>
        <div class="flex flex-wrap gap-2">
          <button
            v-for="icon in icons"
            :key="icon"
            type="button"
            :class="[
              'w-10 h-10 rounded-lg flex items-center justify-center transition-colors',
              form.icon === icon
                ? 'bg-blue-500 text-white'
                : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-300 hover:bg-neutral-200 dark:hover:bg-neutral-700'
            ]"
            @click="form.icon = icon"
          >
            <Icon :name="icon" class="w-5 h-5" />
          </button>
        </div>
      </div>

      <!-- Quick start templates -->
      <div>
        <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
          Start from template
        </label>
        <div class="grid grid-cols-2 gap-2">
          <button
            v-for="template in templates"
            :key="template.name"
            type="button"
            :class="[
              'p-3 rounded-lg border text-left transition-colors',
              selectedTemplate === template.name
                ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20'
                : 'border-neutral-200 dark:border-neutral-700 hover:border-neutral-300 dark:hover:border-neutral-600'
            ]"
            @click="selectTemplate(template)"
          >
            <Icon :name="template.icon" class="w-5 h-5 text-neutral-600 dark:text-neutral-300 mb-1" />
            <div class="text-sm font-medium text-neutral-900 dark:text-white">{{ template.name }}</div>
            <div class="text-xs text-neutral-500 dark:text-neutral-400">{{ template.description }}</div>
          </button>
        </div>
      </div>
    </form>

    <template #footer>
      <div class="flex gap-2 justify-end">
        <Button variant="secondary" @click="$emit('close')">
          Cancel
        </Button>
        <Button @click="handleSubmit">
          Create Table
        </Button>
      </div>
    </template>
  </Modal>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue'
import Modal from '@/Components/shared/Modal.vue'
import Button from '@/Components/shared/Button.vue'
import Input from '@/Components/shared/Input.vue'
import Icon from '@/Components/shared/Icon.vue'

const emit = defineEmits<{
  close: []
  create: [data: { name: string; description?: string; icon?: string; columns?: { name: string; type: string }[] }]
}>()

const icons = [
  'ph:table',
  'ph:database',
  'ph:spreadsheet',
  'ph:list-bullets',
  'ph:users',
  'ph:briefcase',
  'ph:calendar',
  'ph:chart-bar',
]

const templates = [
  {
    name: 'Blank',
    icon: 'ph:plus',
    description: 'Start from scratch',
    columns: [],
  },
  {
    name: 'Tasks',
    icon: 'ph:check-square',
    description: 'Track work items',
    columns: [
      { name: 'Title', type: 'text' },
      { name: 'Status', type: 'select' },
      { name: 'Due Date', type: 'date' },
      { name: 'Assignee', type: 'user' },
    ],
  },
  {
    name: 'Contacts',
    icon: 'ph:users',
    description: 'Manage contacts',
    columns: [
      { name: 'Name', type: 'text' },
      { name: 'Email', type: 'email' },
      { name: 'Company', type: 'text' },
      { name: 'Phone', type: 'text' },
    ],
  },
  {
    name: 'Inventory',
    icon: 'ph:package',
    description: 'Track inventory',
    columns: [
      { name: 'Item', type: 'text' },
      { name: 'Quantity', type: 'number' },
      { name: 'Price', type: 'number' },
      { name: 'In Stock', type: 'checkbox' },
    ],
  },
]

const form = reactive({
  name: '',
  description: '',
  icon: 'ph:table',
})

const selectedTemplate = ref<string | null>(null)
const selectedColumns = ref<{ name: string; type: string }[]>([])

const selectTemplate = (template: typeof templates[0]) => {
  selectedTemplate.value = template.name
  selectedColumns.value = template.columns
  if (template.name !== 'Blank' && !form.name) {
    form.name = template.name
  }
}

const handleSubmit = () => {
  if (!form.name.trim()) return

  emit('create', {
    name: form.name,
    description: form.description || undefined,
    icon: form.icon || undefined,
    columns: selectedColumns.value.length > 0 ? selectedColumns.value : undefined,
  })
}
</script>
