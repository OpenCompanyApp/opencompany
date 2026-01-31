<template>
  <div class="min-h-[28px] flex items-center">
    <!-- Text -->
    <input
      v-if="column.type === 'text'"
      type="text"
      :value="String(value || '')"
      class="w-full bg-transparent border-0 p-0 text-sm text-neutral-900 dark:text-white placeholder:text-neutral-400 focus:ring-0 focus:outline-none"
      placeholder="Enter text..."
      @blur="handleUpdate($event.target as HTMLInputElement)"
      @keydown.enter="($event.target as HTMLInputElement).blur()"
    >

    <!-- Number -->
    <input
      v-else-if="column.type === 'number'"
      type="number"
      :value="value ?? ''"
      class="w-full bg-transparent border-0 p-0 text-sm text-neutral-900 dark:text-white placeholder:text-neutral-400 focus:ring-0 focus:outline-none"
      placeholder="0"
      @blur="handleNumberUpdate($event.target as HTMLInputElement)"
      @keydown.enter="($event.target as HTMLInputElement).blur()"
    >

    <!-- Date -->
    <input
      v-else-if="column.type === 'date'"
      type="date"
      :value="formatDate(value)"
      class="w-full bg-transparent border-0 p-0 text-sm text-neutral-900 dark:text-white focus:ring-0 focus:outline-none cursor-pointer"
      @change="handleDateUpdate($event.target as HTMLInputElement)"
    >

    <!-- Checkbox -->
    <div v-else-if="column.type === 'checkbox'" class="flex items-center">
      <Checkbox
        :checked="Boolean(value)"
        @update:checked="$emit('update', $event)"
      />
    </div>

    <!-- Select -->
    <div v-else-if="column.type === 'select'" class="w-full">
      <select
        :value="String(value || '')"
        class="w-full bg-transparent border-0 p-0 text-sm text-neutral-900 dark:text-white focus:ring-0 focus:outline-none cursor-pointer appearance-none pr-6"
        @change="$emit('update', ($event.target as HTMLSelectElement).value)"
      >
        <option value="" class="dark:bg-neutral-800">Select...</option>
        <option
          v-for="option in (column.options?.choices as string[]) || []"
          :key="option"
          :value="option"
          class="dark:bg-neutral-800"
        >
          {{ option }}
        </option>
      </select>
    </div>

    <!-- Multi-select -->
    <div v-else-if="column.type === 'multiselect'" class="flex flex-wrap items-center gap-1 w-full">
      <Badge
        v-for="item in selectedMultiselectItems"
        :key="item"
        :label="item"
        size="xs"
        variant="default"
        style="soft"
        removable
        @remove="handleRemoveMultiselectItem(item)"
      />
      <DropdownMenu
        v-if="availableMultiselectOptions.length > 0"
        :items="multiselectMenuItems"
        side="bottom"
        align="start"
      >
        <button
          type="button"
          class="inline-flex items-center gap-0.5 px-1.5 py-0.5 text-xs text-neutral-500 hover:text-neutral-700 dark:hover:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-700 rounded transition-colors"
        >
          <Icon name="ph:plus" class="w-3 h-3" />
          <span>Add</span>
        </button>
      </DropdownMenu>
      <span
        v-else-if="selectedMultiselectItems.length === 0"
        class="text-sm text-neutral-400"
      >
        Select...
      </span>
    </div>

    <!-- URL -->
    <div v-else-if="column.type === 'url'" class="flex items-center gap-2 w-full group/url">
      <template v-if="!isEditingUrl">
        <a
          v-if="value"
          :href="String(value)"
          target="_blank"
          class="text-sm text-blue-500 hover:underline truncate flex-1"
          @click.stop
        >
          {{ formatUrl(String(value)) }}
        </a>
        <span v-else class="text-sm text-neutral-400 flex-1">-</span>
        <button
          type="button"
          class="p-0.5 opacity-0 group-hover/url:opacity-100 rounded hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-all shrink-0"
          @click="startEditingUrl"
        >
          <Icon name="ph:pencil" class="w-3.5 h-3.5 text-neutral-400" />
        </button>
      </template>
      <input
        v-else
        ref="urlInputRef"
        type="url"
        :value="String(value || '')"
        placeholder="Enter URL..."
        class="w-full bg-transparent border-0 p-0 text-sm text-neutral-900 dark:text-white placeholder:text-neutral-400 focus:ring-0 focus:outline-none"
        @blur="handleUrlUpdate"
        @keydown.enter="($event.target as HTMLInputElement).blur()"
        @keydown.escape="isEditingUrl = false"
      >
    </div>

    <!-- Email -->
    <div v-else-if="column.type === 'email'" class="flex items-center gap-2 w-full group/email">
      <template v-if="!isEditingEmail">
        <a
          v-if="value"
          :href="`mailto:${String(value)}`"
          class="text-sm text-blue-500 hover:underline truncate flex-1"
          @click.stop
        >
          {{ value }}
        </a>
        <span v-else class="text-sm text-neutral-400 flex-1">-</span>
        <button
          type="button"
          class="p-0.5 opacity-0 group-hover/email:opacity-100 rounded hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-all shrink-0"
          @click="startEditingEmail"
        >
          <Icon name="ph:pencil" class="w-3.5 h-3.5 text-neutral-400" />
        </button>
      </template>
      <input
        v-else
        ref="emailInputRef"
        type="email"
        :value="String(value || '')"
        placeholder="Enter email..."
        class="w-full bg-transparent border-0 p-0 text-sm text-neutral-900 dark:text-white placeholder:text-neutral-400 focus:ring-0 focus:outline-none"
        @blur="handleEmailUpdate"
        @keydown.enter="($event.target as HTMLInputElement).blur()"
        @keydown.escape="isEditingEmail = false"
      >
    </div>

    <!-- Default text display -->
    <span v-else class="text-sm text-neutral-900 dark:text-white">
      {{ value ?? '-' }}
    </span>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, nextTick } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import Checkbox from '@/Components/shared/Checkbox.vue'
import Badge from '@/Components/shared/Badge.vue'
import DropdownMenu from '@/Components/shared/DropdownMenu.vue'
import type { DataTableColumn } from '@/types'

const props = defineProps<{
  column: DataTableColumn
  value: unknown
}>()

const emit = defineEmits<{
  update: [value: unknown]
}>()

// Edit states
const isEditingUrl = ref(false)
const isEditingEmail = ref(false)
const urlInputRef = ref<HTMLInputElement | null>(null)
const emailInputRef = ref<HTMLInputElement | null>(null)

// Multiselect computed
const selectedMultiselectItems = computed(() => {
  if (!Array.isArray(props.value)) return []
  return props.value as string[]
})

const availableMultiselectOptions = computed(() => {
  const choices = (props.column.options?.choices as string[]) || []
  return choices.filter(c => !selectedMultiselectItems.value.includes(c))
})

const multiselectMenuItems = computed(() => {
  return availableMultiselectOptions.value.map(option => ({
    label: option,
    click: () => handleAddMultiselectItem(option),
  }))
})

// Handlers
const handleUpdate = (input: HTMLInputElement) => {
  if (input.value !== props.value) {
    emit('update', input.value)
  }
}

const handleNumberUpdate = (input: HTMLInputElement) => {
  const num = parseFloat(input.value)
  if (!isNaN(num) && num !== props.value) {
    emit('update', num)
  } else if (input.value === '' && props.value !== 0) {
    emit('update', 0)
  }
}

const handleDateUpdate = (input: HTMLInputElement) => {
  emit('update', input.value)
}

// Multiselect handlers
const handleAddMultiselectItem = (item: string) => {
  const current = Array.isArray(props.value) ? [...props.value] : []
  current.push(item)
  emit('update', current)
}

const handleRemoveMultiselectItem = (item: string) => {
  const current = Array.isArray(props.value) ? props.value.filter(v => v !== item) : []
  emit('update', current)
}

// URL handlers
const startEditingUrl = () => {
  isEditingUrl.value = true
  nextTick(() => urlInputRef.value?.focus())
}

const handleUrlUpdate = (event: FocusEvent) => {
  const input = event.target as HTMLInputElement
  if (input.value !== props.value) {
    emit('update', input.value)
  }
  isEditingUrl.value = false
}

// Email handlers
const startEditingEmail = () => {
  isEditingEmail.value = true
  nextTick(() => emailInputRef.value?.focus())
}

const handleEmailUpdate = (event: FocusEvent) => {
  const input = event.target as HTMLInputElement
  if (input.value !== props.value) {
    emit('update', input.value)
  }
  isEditingEmail.value = false
}

// Formatters
const formatDate = (value: unknown): string => {
  if (!value) return ''
  try {
    const date = new Date(String(value))
    return date.toISOString().split('T')[0]
  } catch {
    return ''
  }
}

const formatUrl = (url: string): string => {
  try {
    const parsed = new URL(url)
    return parsed.hostname + (parsed.pathname !== '/' ? parsed.pathname : '')
  } catch {
    return url
  }
}
</script>
