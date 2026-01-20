<template>
  <div class="flex items-center gap-3 text-sm text-olympus-text-muted">
    <div class="flex -space-x-2">
      <SharedAgentAvatar
        v-for="user in users.slice(0, 3)"
        :key="user.id"
        :user="user"
        size="xs"
        :show-status="false"
      />
    </div>
    <span>
      <span class="font-medium">{{ displayNames }}</span>
      {{ users.length === 1 ? 'is' : 'are' }} typing
    </span>
    <span class="flex gap-0.5">
      <span class="w-1.5 h-1.5 bg-olympus-text-muted rounded-full animate-bounce" style="animation-delay: 0ms" />
      <span class="w-1.5 h-1.5 bg-olympus-text-muted rounded-full animate-bounce" style="animation-delay: 150ms" />
      <span class="w-1.5 h-1.5 bg-olympus-text-muted rounded-full animate-bounce" style="animation-delay: 300ms" />
    </span>
  </div>
</template>

<script setup lang="ts">
import type { User } from '~/types'

const props = defineProps<{
  users: User[]
}>()

const displayNames = computed(() => {
  if (props.users.length === 1) {
    return props.users[0].name
  }
  if (props.users.length === 2) {
    return `${props.users[0].name} and ${props.users[1].name}`
  }
  return `${props.users[0].name} and ${props.users.length - 1} others`
})
</script>
