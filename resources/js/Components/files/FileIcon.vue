<template>
  <Icon :name="iconName" :class="props.class" />
</template>

<script setup lang="ts">
import { computed } from 'vue'
import Icon from '@/Components/shared/Icon.vue'

const props = defineProps<{
  mimeType?: string
  isFolder?: boolean
  class?: string
}>()

const iconName = computed(() => {
  if (props.isFolder) return 'ph:folder-fill'

  const mime = props.mimeType || ''
  if (mime.startsWith('image/')) return 'ph:file-image'
  if (mime === 'application/pdf') return 'ph:file-pdf'
  if (mime.startsWith('video/')) return 'ph:file-video'
  if (mime.startsWith('audio/')) return 'ph:file-audio'
  if (mime === 'text/csv' || mime.includes('spreadsheet') || mime.includes('excel')) return 'ph:file-csv'
  if (mime.includes('zip') || mime.includes('archive') || mime.includes('compressed')) return 'ph:file-zip'
  if (mime.startsWith('text/') || mime === 'application/json' || mime === 'application/xml' || mime === 'application/javascript') return 'ph:file-code'
  if (mime.includes('document') || mime.includes('word')) return 'ph:file-doc'
  if (mime.includes('presentation') || mime.includes('powerpoint')) return 'ph:file-ppt'
  return 'ph:file'
})
</script>
