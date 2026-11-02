<template>
  <div class="rounded-xl border border-neutral-200 dark:border-neutral-700 overflow-hidden">
    <!-- Tabs -->
    <div class="flex items-center gap-1 px-3 py-2 bg-neutral-50 dark:bg-neutral-800 border-b border-neutral-200 dark:border-neutral-700">
      <button
        type="button"
        :class="[
          'px-3 py-1 text-xs font-medium rounded-md transition-all',
          mode === 'write'
            ? 'bg-white dark:bg-neutral-700 text-neutral-900 dark:text-white shadow-sm'
            : 'text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-300',
        ]"
        @click="mode = 'write'"
      >
        <Icon name="ph:pencil-simple" class="w-3.5 h-3.5 inline mr-1" />
        Write
      </button>
      <button
        type="button"
        :class="[
          'px-3 py-1 text-xs font-medium rounded-md transition-all',
          mode === 'preview'
            ? 'bg-white dark:bg-neutral-700 text-neutral-900 dark:text-white shadow-sm'
            : 'text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-300',
        ]"
        @click="mode = 'preview'"
      >
        <Icon name="ph:eye" class="w-3.5 h-3.5 inline mr-1" />
        Preview
      </button>
      <button
        type="button"
        class="ml-auto p-1 rounded-md text-neutral-400 dark:text-neutral-500 hover:text-neutral-600 dark:hover:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors"
        title="Expand editor"
        @click="isFullscreen = true"
      >
        <Icon name="ph:arrows-out" class="w-3.5 h-3.5" />
      </button>
    </div>

    <!-- Write mode -->
    <textarea
      v-if="mode === 'write'"
      :value="modelValue"
      rows="6"
      class="w-full bg-white dark:bg-neutral-900 px-4 py-3 text-sm text-neutral-900 dark:text-white placeholder:text-neutral-400 font-mono leading-relaxed resize-none outline-none"
      :placeholder="placeholder"
      @input="$emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
    />

    <!-- Preview mode -->
    <div
      v-else
      class="px-4 py-3 min-h-[168px] bg-white dark:bg-neutral-900"
    >
      <div
        v-if="modelValue?.trim()"
        class="prose prose-sm prose-neutral dark:prose-invert max-w-none"
        v-html="rendered"
      />
      <p v-else class="text-sm text-neutral-400 dark:text-neutral-500 italic">
        Nothing to preview
      </p>
    </div>
  </div>

  <!-- Fullscreen Modal -->
  <Modal v-model:open="isFullscreen" title="Edit Prompt" description="Markdown supported" size="full">
    <div class="flex flex-col h-full">
      <!-- Modal tabs -->
      <div class="flex items-center gap-1 mb-4">
        <button
          type="button"
          :class="[
            'px-3 py-1.5 text-sm font-medium rounded-md transition-all',
            modalMode === 'write'
              ? 'bg-neutral-100 dark:bg-neutral-700 text-neutral-900 dark:text-white shadow-sm'
              : 'text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-300',
          ]"
          @click="modalMode = 'write'"
        >
          <Icon name="ph:pencil-simple" class="w-4 h-4 inline mr-1" />
          Write
        </button>
        <button
          type="button"
          :class="[
            'px-3 py-1.5 text-sm font-medium rounded-md transition-all',
            modalMode === 'preview'
              ? 'bg-neutral-100 dark:bg-neutral-700 text-neutral-900 dark:text-white shadow-sm'
              : 'text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-300',
          ]"
          @click="modalMode = 'preview'"
        >
          <Icon name="ph:eye" class="w-4 h-4 inline mr-1" />
          Preview
        </button>
      </div>

      <!-- Modal write mode -->
      <textarea
        v-if="modalMode === 'write'"
        :value="modelValue"
        class="flex-1 min-h-[60vh] w-full bg-neutral-50 dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 rounded-xl px-4 py-3 text-sm text-neutral-900 dark:text-white placeholder:text-neutral-400 font-mono leading-relaxed resize-none outline-none focus:ring-2 focus:ring-neutral-400 dark:focus:ring-neutral-500"
        :placeholder="placeholder"
        @input="$emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
      />

      <!-- Modal preview mode -->
      <div
        v-else
        class="flex-1 min-h-[60vh] bg-neutral-50 dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 rounded-xl px-6 py-4 overflow-y-auto"
      >
        <div
          v-if="modelValue?.trim()"
          class="prose prose-neutral dark:prose-invert max-w-none"
          v-html="rendered"
        />
        <p v-else class="text-sm text-neutral-400 dark:text-neutral-500 italic">
          Nothing to preview
        </p>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end">
        <Button variant="primary" @click="isFullscreen = false">
          Done
        </Button>
      </div>
    </template>
  </Modal>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import Button from '@/Components/shared/Button.vue'
import Modal from '@/Components/shared/Modal.vue'
import { useMarkdown } from '@/composables/useMarkdown'

const props = defineProps<{
  modelValue: string
  placeholder?: string
}>()

defineEmits<{
  'update:modelValue': [value: string]
}>()

const { renderMarkdown } = useMarkdown()
const mode = ref<'write' | 'preview'>('write')
const modalMode = ref<'write' | 'preview'>('write')
const isFullscreen = ref(false)

const rendered = computed(() => renderMarkdown(props.modelValue))
</script>
