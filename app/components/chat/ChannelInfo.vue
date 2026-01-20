<template>
  <aside class="w-72 bg-olympus-sidebar border-l border-olympus-border flex flex-col shrink-0">
    <!-- Header -->
    <div class="p-4 border-b border-olympus-border">
      <h3 class="font-semibold">Channel Info</h3>
    </div>

    <!-- Content -->
    <div class="flex-1 overflow-y-auto">
      <!-- Presence -->
      <div v-if="viewers.length > 0" class="p-4 border-b border-olympus-border">
        <SharedPresenceRow :users="viewers" />
      </div>

      <!-- About -->
      <div class="p-4 border-b border-olympus-border">
        <h4 class="text-xs font-semibold text-olympus-text-muted uppercase tracking-wider mb-2">
          About
        </h4>
        <p class="text-sm text-olympus-text-muted">
          {{ channel.description || 'No description' }}
        </p>
      </div>

      <!-- Members -->
      <div class="p-4">
        <div class="flex items-center justify-between mb-3">
          <h4 class="text-xs font-semibold text-olympus-text-muted uppercase tracking-wider">
            Members
          </h4>
          <span class="text-xs text-olympus-text-subtle">{{ channel.members.length }}</span>
        </div>

        <div class="space-y-2">
          <div
            v-for="member in channel.members"
            :key="member.id"
            class="flex items-center gap-3 p-2 rounded-xl hover:bg-olympus-surface transition-colors cursor-pointer"
          >
            <SharedAgentAvatar :user="member" size="sm" />
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium truncate">{{ member.name }}</p>
              <p v-if="member.type === 'agent'" class="text-xs text-olympus-text-muted truncate">
                {{ member.status === 'working' ? member.currentTask : 'Ready' }}
              </p>
              <p v-else class="text-xs text-olympus-text-muted capitalize">
                {{ member.type }}
              </p>
            </div>
            <SharedStatusBadge
              v-if="member.type === 'agent' && member.status"
              :status="member.status"
              size="xs"
              :show-label="false"
            />
          </div>
        </div>
      </div>
    </div>
  </aside>
</template>

<script setup lang="ts">
import type { Channel, User } from '~/types'

defineProps<{
  channel: Channel
  viewers: User[]
}>()
</script>
