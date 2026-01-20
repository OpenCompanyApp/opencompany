<template>
  <TooltipRoot>
    <TooltipTrigger as-child>
      <div class="flex items-center gap-1 cursor-default">
        <div class="flex -space-x-2">
          <div
            v-for="(user, index) in displayUsers"
            :key="user.id"
            class="relative"
            :style="{ zIndex: displayUsers.length - index }"
          >
            <SharedAgentAvatar :user="user" size="xs" :show-status="false" />
          </div>
        </div>
        <span v-if="remaining > 0" class="text-xs text-olympus-text-muted ml-1">
          +{{ remaining }}
        </span>
        <span v-if="showLabel" class="text-xs text-olympus-text-subtle ml-2">
          {{ label }}
        </span>
      </div>
    </TooltipTrigger>
    <TooltipPortal>
      <TooltipContent
        class="bg-olympus-elevated border border-olympus-border rounded-lg px-3 py-2 text-sm shadow-xl max-w-64 z-50"
        :side-offset="5"
      >
        <p class="text-xs text-olympus-text-muted mb-2 font-medium">{{ tooltipTitle }}</p>
        <ul class="space-y-1">
          <li
            v-for="user in users"
            :key="user.id"
            class="flex items-center gap-2"
          >
            <SharedAgentAvatar :user="user" size="xs" :show-status="false" />
            <span class="text-sm text-olympus-text">{{ user.name }}</span>
          </li>
        </ul>
        <TooltipArrow class="fill-olympus-elevated" />
      </TooltipContent>
    </TooltipPortal>
  </TooltipRoot>
</template>

<script setup lang="ts">
import {
  TooltipArrow,
  TooltipContent,
  TooltipPortal,
  TooltipRoot,
  TooltipTrigger,
} from 'reka-ui'
import type { User } from '~/types'

const props = withDefaults(defineProps<{
  users: User[]
  max?: number
  showLabel?: boolean
  label?: string
  tooltipTitle?: string
}>(), {
  max: 4,
  showLabel: true,
  label: 'viewing',
  tooltipTitle: 'Currently viewing',
})

const displayUsers = computed(() => props.users.slice(0, props.max))
const remaining = computed(() => Math.max(0, props.users.length - props.max))
</script>
