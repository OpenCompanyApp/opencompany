<template>
  <div class="flex items-center justify-between px-2 mb-2">
    <button
      v-if="collapsible"
      class="flex items-center gap-1.5 text-xs font-semibold text-olympus-text-muted uppercase tracking-wider hover:text-olympus-text transition-colors duration-150 outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50 rounded"
      @click="handleToggle"
    >
      <Icon
        name="ph:caret-right-fill"
        :class="[
          'w-3 h-3 transition-transform duration-200',
          !collapsed && 'rotate-90'
        ]"
      />
      <span>{{ title }}</span>
      <SharedBadge v-if="count !== undefined" size="sm" variant="default">
        {{ count }}
      </SharedBadge>
    </button>

    <span
      v-else
      class="flex items-center gap-1.5 text-xs font-semibold text-olympus-text-muted uppercase tracking-wider"
    >
      {{ title }}
      <SharedBadge v-if="count !== undefined" size="sm" variant="default">
        {{ count }}
      </SharedBadge>
    </span>

    <button
      v-if="action"
      class="p-1 rounded-md text-olympus-text-muted hover:text-olympus-text hover:bg-olympus-surface transition-colors duration-150 outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50"
      :aria-label="action.label || 'Action'"
      @click="action.onClick"
    >
      <Icon :name="action.icon" class="w-4 h-4" />
    </button>
  </div>
</template>

<script setup lang="ts">
interface SectionHeaderAction {
  icon: string
  label?: string
  onClick: () => void
}

const props = withDefaults(defineProps<{
  title: string
  count?: number
  collapsible?: boolean
  collapsed?: boolean
  action?: SectionHeaderAction
}>(), {
  collapsible: false,
  collapsed: false,
})

const emit = defineEmits<{
  'update:collapsed': [value: boolean]
}>()

const handleToggle = () => {
  emit('update:collapsed', !props.collapsed)
}
</script>
