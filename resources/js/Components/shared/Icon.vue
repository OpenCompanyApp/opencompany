<script setup lang="ts">
import { computed } from 'vue'
import { Icon as IconifyIcon } from '@iconify/vue'

interface Props {
  name?: string
  icon?: string
  class?: string | string[] | (string | false | undefined)[]
}

const props = defineProps<Props>()

// Support both 'name' and 'icon' props for backwards compatibility
const iconName = computed(() => props.icon || props.name || '')

// Normalize class to string
const normalizedClass = computed(() => {
  if (!props.class) return ''
  if (typeof props.class === 'string') return props.class
  return props.class.filter(Boolean).join(' ')
})
</script>

<template>
  <IconifyIcon :icon="iconName" :class="normalizedClass" />
</template>
