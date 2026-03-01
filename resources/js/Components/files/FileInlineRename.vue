<template>
  <input
    v-if="editing"
    ref="inputRef"
    :value="modelValue"
    class="w-full bg-white dark:bg-neutral-800 border border-blue-400 dark:border-blue-500 rounded px-1 py-0.5 text-sm text-neutral-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-blue-400"
    @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
    @keydown.enter.prevent="$emit('commit')"
    @keydown.escape.prevent="$emit('cancel')"
    @blur="$emit('commit')"
    @click.stop
  />
  <span v-else :class="labelClass" :title="displayName">
    {{ displayName }}
  </span>
</template>

<script setup lang="ts">
import { ref, watch, nextTick } from 'vue'

const props = defineProps<{
  modelValue: string
  displayName: string
  editing: boolean
  labelClass?: string
}>()

defineEmits<{
  'update:modelValue': [value: string]
  commit: []
  cancel: []
}>()

const inputRef = ref<HTMLInputElement>()

watch(() => props.editing, (val) => {
  if (val) {
    nextTick(() => {
      const el = inputRef.value
      if (el) {
        el.focus()
        const dotIndex = props.modelValue.lastIndexOf('.')
        if (dotIndex > 0) {
          el.setSelectionRange(0, dotIndex)
        } else {
          el.select()
        }
      }
    })
  }
})
</script>
