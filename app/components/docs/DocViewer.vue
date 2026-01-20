<template>
  <div class="flex-1 bg-olympus-bg flex flex-col h-full overflow-hidden">
    <DocsDocViewerHeader
      :title="document.title"
      :author="document.author"
      :updated-at="document.updatedAt"
      :viewers="document.viewers"
      :editors="document.editors"
      @edit="$emit('edit')"
      @share="$emit('share')"
      @menu="$emit('menu')"
    />

    <DocsDocViewerContent :content="document.content" />
  </div>
</template>

<script setup lang="ts">
import type { Document } from '~/types'

defineProps<{
  document: Document
}>()

defineEmits<{
  edit: []
  share: []
  menu: []
}>()
</script>
