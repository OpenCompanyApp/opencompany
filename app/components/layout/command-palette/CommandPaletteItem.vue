<template>
  <button
    class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl transition-colors text-left group"
    :class="[
      selected
        ? 'bg-olympus-primary text-white'
        : 'hover:bg-olympus-surface text-olympus-text'
    ]"
    @click="$emit('select')"
    @mouseenter="$emit('hover')"
  >
    <div
      :class="[
        'w-8 h-8 rounded-lg flex items-center justify-center transition-colors',
        selected ? 'bg-white/20' : 'bg-olympus-surface group-hover:bg-olympus-border'
      ]"
    >
      <Icon
        :name="icon"
        :class="[
          'w-4 h-4',
          selected ? 'text-white' : 'text-olympus-text-muted'
        ]"
      />
    </div>
    <div class="flex-1 min-w-0">
      <p class="font-medium">{{ label }}</p>
      <p
        v-if="description"
        :class="[
          'text-xs truncate',
          selected ? 'text-white/70' : 'text-olympus-text-muted'
        ]"
      >
        {{ description }}
      </p>
    </div>
    <span
      v-if="shortcut"
      :class="[
        'text-xs font-mono',
        selected ? 'text-white/70' : 'text-olympus-text-subtle'
      ]"
    >
      {{ shortcut }}
    </span>
  </button>
</template>

<script setup lang="ts">
defineProps<{
  icon: string
  label: string
  description?: string
  shortcut?: string
  selected?: boolean
}>()

defineEmits<{
  select: []
  hover: []
}>()
</script>
