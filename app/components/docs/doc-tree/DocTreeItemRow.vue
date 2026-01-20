<template>
  <button
    :class="[
      'w-full flex items-start gap-2 py-2 px-2 rounded-lg transition-all duration-150 text-left group outline-none',
      'focus-visible:ring-2 focus-visible:ring-olympus-primary/50 focus-visible:ring-offset-2 focus-visible:ring-offset-olympus-bg',
      selected
        ? 'bg-olympus-primary-muted text-olympus-text border-l-2 border-olympus-primary'
        : 'hover:bg-olympus-surface text-olympus-text'
    ]"
    :style="{ paddingLeft: `${8 + level * 20}px` }"
    @click="$emit('click')"
  >
    <!-- Expand/Collapse Toggle (for folders) -->
    <button
      v-if="isFolder"
      class="w-5 h-5 flex items-center justify-center shrink-0 -ml-1 rounded hover:bg-olympus-elevated transition-colors duration-150"
      @click.stop="$emit('toggle')"
    >
      <Icon
        name="ph:caret-right-fill"
        :class="[
          'w-3 h-3 transition-transform duration-150',
          expanded ? 'rotate-90' : '',
          selected ? 'text-olympus-primary' : 'text-olympus-text-muted'
        ]"
      />
    </button>
    <div v-else class="w-5 h-5 shrink-0" />

    <!-- Icon -->
    <div
      :class="[
        'w-7 h-7 rounded-md flex items-center justify-center shrink-0 transition-transform duration-150 group-hover:scale-105',
        selected ? 'bg-olympus-primary/20' : 'bg-olympus-surface'
      ]"
    >
      <Icon
        :name="iconName"
        :class="[
          'w-4 h-4',
          selected ? 'text-olympus-primary' : isFolder ? 'text-amber-400' : 'text-olympus-text-muted'
        ]"
      />
    </div>

    <!-- Content -->
    <div class="flex-1 min-w-0">
      <p class="font-medium text-sm truncate">{{ title }}</p>
      <p
        v-if="!isFolder && updatedAt"
        :class="[
          'text-xs truncate',
          selected ? 'text-olympus-text-muted' : 'text-olympus-text-subtle'
        ]"
      >
        Updated {{ formatDate(updatedAt) }}
      </p>
      <p
        v-else-if="isFolder"
        :class="[
          'text-xs',
          selected ? 'text-olympus-text-muted' : 'text-olympus-text-subtle'
        ]"
      >
        {{ childCount }} {{ childCount === 1 ? 'item' : 'items' }}
      </p>
    </div>
  </button>
</template>

<script setup lang="ts">
const props = defineProps<{
  title: string
  isFolder: boolean
  expanded: boolean
  selected: boolean
  level: number
  childCount?: number
  updatedAt?: Date
}>()

defineEmits<{
  click: []
  toggle: []
}>()

const iconName = computed(() => {
  if (props.isFolder) {
    return props.expanded ? 'ph:folder-open-fill' : 'ph:folder-fill'
  }
  return 'ph:file-text-fill'
})

const formatDate = (date: Date) => {
  const d = new Date(date)
  const now = new Date()
  const diff = now.getTime() - d.getTime()
  const days = Math.floor(diff / (1000 * 60 * 60 * 24))

  if (days === 0) return 'today'
  if (days === 1) return 'yesterday'
  if (days < 7) return `${days} days ago`
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}
</script>
