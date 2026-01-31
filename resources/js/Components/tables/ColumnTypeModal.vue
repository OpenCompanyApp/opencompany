<template>
  <Modal :open="true" size="md" @close="$emit('close')">
    <template #title>
      {{ column ? 'Edit Column' : 'Add Column' }}
    </template>

    <form class="space-y-4" @submit.prevent="handleSubmit">
      <!-- Name -->
      <div>
        <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
          Column Name
        </label>
        <Input
          v-model="form.name"
          placeholder="e.g., Status"
          required
        />
      </div>

      <!-- Type -->
      <div>
        <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
          Column Type
        </label>
        <div class="grid grid-cols-2 gap-2">
          <button
            v-for="type in columnTypes"
            :key="type.value"
            type="button"
            :class="[
              'p-3 rounded-lg border text-left transition-colors',
              form.type === type.value
                ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20'
                : 'border-neutral-200 dark:border-neutral-700 hover:border-neutral-300 dark:hover:border-neutral-600'
            ]"
            @click="form.type = type.value"
          >
            <Icon :name="type.icon" class="w-5 h-5 text-neutral-600 dark:text-neutral-300 mb-1" />
            <div class="text-sm font-medium text-neutral-900 dark:text-white">{{ type.label }}</div>
            <div class="text-xs text-neutral-500 dark:text-neutral-400">{{ type.description }}</div>
          </button>
        </div>
      </div>

      <!-- Type change warning -->
      <div
        v-if="column && form.type !== column.type"
        class="p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800"
      >
        <div class="flex items-start gap-2">
          <Icon name="ph:warning-fill" class="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
          <p class="text-sm text-amber-700 dark:text-amber-300">
            Changing the column type may cause data loss for existing values that are incompatible with the new type.
          </p>
        </div>
      </div>

      <!-- Select options -->
      <div v-if="form.type === 'select' || form.type === 'multiselect'">
        <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
          Options
        </label>
        <div class="space-y-2">
          <div
            v-for="(option, index) in form.options.choices"
            :key="index"
            class="flex gap-2"
          >
            <Input
              v-model="form.options.choices[index]"
              placeholder="Option name"
              class="flex-1"
            />
            <button
              type="button"
              class="p-2 text-neutral-400 hover:text-red-500 transition-colors rounded hover:bg-neutral-100 dark:hover:bg-neutral-700"
              @click="removeOption(index)"
            >
              <Icon name="ph:x" class="w-4 h-4" />
            </button>
          </div>
          <button
            type="button"
            class="text-sm text-blue-500 hover:text-blue-600 flex items-center gap-1 transition-colors"
            @click="addOption"
          >
            <Icon name="ph:plus" class="w-4 h-4" />
            Add option
          </button>
        </div>
      </div>

      <!-- Required toggle -->
      <Checkbox
        v-model:checked="form.required"
        label="Required field"
      />
    </form>

    <template #footer>
      <div class="flex gap-2 justify-end">
        <Button variant="secondary" @click="$emit('close')">
          Cancel
        </Button>
        <Button @click="handleSubmit" :disabled="!form.name.trim()">
          {{ column ? 'Save Changes' : 'Add Column' }}
        </Button>
      </div>
    </template>
  </Modal>
</template>

<script setup lang="ts">
import { reactive, watch } from 'vue'
import Modal from '@/Components/shared/Modal.vue'
import Button from '@/Components/shared/Button.vue'
import Input from '@/Components/shared/Input.vue'
import Icon from '@/Components/shared/Icon.vue'
import Checkbox from '@/Components/shared/Checkbox.vue'
import type { DataTableColumn } from '@/types'

const props = defineProps<{
  column?: DataTableColumn
}>()

const emit = defineEmits<{
  close: []
  save: [data: { name: string; type: string; options?: Record<string, unknown>; required?: boolean }]
}>()

const columnTypes = [
  { value: 'text', label: 'Text', icon: 'ph:text-aa', description: 'Plain text content' },
  { value: 'number', label: 'Number', icon: 'ph:hash', description: 'Numeric values' },
  { value: 'date', label: 'Date', icon: 'ph:calendar', description: 'Date picker' },
  { value: 'checkbox', label: 'Checkbox', icon: 'ph:check-square', description: 'True/false toggle' },
  { value: 'select', label: 'Select', icon: 'ph:list', description: 'Single choice from options' },
  { value: 'multiselect', label: 'Multi-select', icon: 'ph:list-checks', description: 'Multiple choices' },
  { value: 'url', label: 'URL', icon: 'ph:link', description: 'Web links' },
  { value: 'email', label: 'Email', icon: 'ph:envelope', description: 'Email addresses' },
]

const form = reactive({
  name: '',
  type: 'text',
  options: {
    choices: [''] as string[],
  },
  required: false,
})

// Initialize form from existing column
watch(
  () => props.column,
  (column) => {
    if (column) {
      form.name = column.name
      form.type = column.type
      form.required = column.required
      if (column.options?.choices) {
        form.options.choices = [...(column.options.choices as string[])]
      }
    }
  },
  { immediate: true }
)

const addOption = () => {
  form.options.choices.push('')
}

const removeOption = (index: number) => {
  form.options.choices.splice(index, 1)
}

const handleSubmit = () => {
  if (!form.name.trim()) return

  const data: { name: string; type: string; options?: Record<string, unknown>; required?: boolean } = {
    name: form.name,
    type: form.type,
    required: form.required,
  }

  if (form.type === 'select' || form.type === 'multiselect') {
    data.options = {
      choices: form.options.choices.filter((c) => c.trim()),
    }
  }

  emit('save', data)
}
</script>
