<template>
  <div
    class="relative"
    @dragenter.prevent="dragOver = true"
    @dragover.prevent="dragOver = true"
    @dragleave.prevent="dragOver = false"
    @drop.prevent="handleDrop"
  >
    <slot />

    <!-- Drag overlay -->
    <Transition name="fade">
      <div
        v-if="dragOver"
        class="absolute inset-0 z-20 flex items-center justify-center bg-white/90 dark:bg-neutral-900/90 backdrop-blur-sm border-2 border-dashed border-neutral-400 dark:border-neutral-500 rounded-lg"
      >
        <div class="text-center">
          <Icon name="ph:upload-simple" class="w-10 h-10 text-neutral-400 dark:text-neutral-500 mx-auto mb-2" />
          <p class="text-sm font-medium text-neutral-600 dark:text-neutral-300">Drop files to upload</p>
        </div>
      </div>
    </Transition>

    <!-- Upload progress -->
    <div v-if="uploads.length > 0" class="absolute bottom-4 right-4 z-30 space-y-2 w-64">
      <div
        v-for="upload in uploads"
        :key="upload.name"
        class="bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg px-3 py-2 shadow-lg flex items-center gap-2"
      >
        <Icon name="ph:spinner" class="w-4 h-4 animate-spin text-neutral-400" />
        <span class="text-xs text-neutral-600 dark:text-neutral-300 truncate flex-1">{{ upload.name }}</span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import Icon from '@/Components/shared/Icon.vue'

const props = defineProps<{
  parentId: string | null
}>()

const emit = defineEmits<{
  upload: [files: File[]]
}>()

const dragOver = ref(false)
const uploads = ref<Array<{ name: string }>>([])

const handleDrop = (e: DragEvent) => {
  dragOver.value = false
  const files = Array.from(e.dataTransfer?.files || [])
  if (files.length > 0) {
    emit('upload', files)
  }
}

defineExpose({
  addUpload(name: string) {
    uploads.value.push({ name })
  },
  removeUpload(name: string) {
    uploads.value = uploads.value.filter(u => u.name !== name)
  },
})
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.15s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
