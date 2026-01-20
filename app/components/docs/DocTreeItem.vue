<template>
  <div>
    <DocsDocTreeItemRow
      :title="item.title"
      :is-folder="item.isFolder ?? false"
      :expanded="expanded"
      :selected="selected"
      :level="level"
      :child-count="childCount"
      :updated-at="item.updatedAt"
      @click="handleClick"
      @toggle="expanded = !expanded"
    />

    <!-- Children (recursive) -->
    <Transition
      enter-active-class="transition-all duration-200 ease-out"
      enter-from-class="opacity-0 -translate-y-1"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition-all duration-150 ease-in"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0 -translate-y-1"
    >
      <div v-if="item.isFolder && expanded && children.length > 0">
        <DocsDocTreeItem
          v-for="child in children"
          :key="child.id"
          :item="child"
          :all-items="allItems"
          :level="level + 1"
          :selected-id="selectedId"
          @select="$emit('select', $event)"
        />
      </div>
    </Transition>
  </div>
</template>

<script setup lang="ts">
import type { Document } from '~/types'

const props = defineProps<{
  item: Document
  allItems: Document[]
  level: number
  selectedId: string | null
}>()

const emit = defineEmits<{
  select: [doc: Document]
}>()

const expanded = ref(true)

const selected = computed(() => props.selectedId === props.item.id)

const children = computed(() =>
  props.allItems.filter(doc => doc.parentId === props.item.id)
)

const childCount = computed(() => children.value.length)

const handleClick = () => {
  if (props.item.isFolder) {
    expanded.value = !expanded.value
  } else {
    emit('select', props.item)
  }
}
</script>
