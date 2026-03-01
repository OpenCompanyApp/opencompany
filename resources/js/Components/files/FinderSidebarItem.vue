<template>
  <div>
    <button
      :class="[
        'w-full flex items-center gap-1.5 rounded-md text-sm transition-colors py-1 pr-2',
        selectedId === node.id
          ? 'bg-neutral-200 dark:bg-neutral-700 text-neutral-900 dark:text-white font-medium'
          : 'text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800'
      ]"
      :style="{ paddingLeft: `${(depth * 16) + 8}px` }"
      @click="$emit('select', node.id)"
    >
      <button
        v-if="node.children.length > 0"
        class="p-0.5 shrink-0"
        @click.stop="expanded = !expanded"
      >
        <Icon
          name="ph:caret-right"
          :class="['w-3 h-3 text-neutral-400 transition-transform', expanded && 'rotate-90']"
        />
      </button>
      <span v-else class="w-4 shrink-0" />

      <Icon
        :name="expanded ? 'ph:folder-open-fill' : 'ph:folder-fill'"
        class="w-4 h-4 shrink-0 text-amber-500"
      />
      <span class="truncate">{{ node.name }}</span>
    </button>

    <div v-if="expanded && node.children.length > 0">
      <FinderSidebarItem
        v-for="child in node.children"
        :key="child.id"
        :node="child"
        :selected-id="selectedId"
        :depth="depth + 1"
        @select="(id: string) => $emit('select', id)"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import type { FolderTreeNode } from '@/types'

const props = defineProps<{
  node: FolderTreeNode
  selectedId: string | null
  depth: number
}>()

defineEmits<{
  select: [folderId: string]
}>()

const expanded = ref(props.depth < 1)
</script>
