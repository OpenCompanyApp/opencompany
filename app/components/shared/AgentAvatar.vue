<template>
  <div class="relative" :class="containerSizes[size]">
    <div
      :class="[
        'rounded-full flex items-center justify-center overflow-hidden ring-2 ring-transparent transition-all duration-150',
        avatarSizes[size],
        user.type === 'agent' ? agentBgColors[user.agentType || 'manager'] : 'bg-olympus-primary'
      ]"
    >
      <Icon
        v-if="user.type === 'agent'"
        :name="agentIcons[user.agentType || 'manager']"
        :class="['text-white', iconSizes[size]]"
      />
      <span
        v-else
        :class="['font-semibold text-white', textSizes[size]]"
      >
        {{ user.name.charAt(0) }}
      </span>
    </div>

    <!-- Status indicator for agents -->
    <div
      v-if="user.type === 'agent' && showStatus && user.status"
      :class="[
        'absolute rounded-full border-2 border-olympus-sidebar shadow-sm',
        statusColors[user.status],
        dotSizes[size],
        dotPositions[size],
        user.status === 'working' && 'animate-pulse'
      ]"
    />

    <!-- Tooltip for working agents -->
    <TooltipRoot v-if="user.type === 'agent' && user.status === 'working' && user.currentTask">
      <TooltipTrigger as-child>
        <div class="absolute inset-0 cursor-help" />
      </TooltipTrigger>
      <TooltipPortal>
        <TooltipContent
          class="bg-olympus-elevated border border-olympus-border rounded-lg px-3 py-2 text-sm shadow-xl max-w-64 z-50"
          :side-offset="5"
        >
          <p class="font-medium text-olympus-text">{{ user.name }}</p>
          <p class="text-olympus-text-muted text-xs mt-1">{{ user.currentTask }}</p>
          <TooltipArrow class="fill-olympus-elevated" />
        </TooltipContent>
      </TooltipPortal>
    </TooltipRoot>
  </div>
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
import {
  containerSizes,
  avatarSizes,
  iconSizes,
  textSizes,
  dotSizes,
  dotPositions,
  agentIcons,
  agentBgColors,
  statusColors,
  type AvatarSize,
} from './agent-avatar.config'

withDefaults(defineProps<{
  user: User
  size?: AvatarSize
  showStatus?: boolean
}>(), {
  size: 'md',
  showStatus: true,
})
</script>
