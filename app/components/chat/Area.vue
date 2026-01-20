<template>
  <div class="flex flex-col bg-olympus-bg h-full">
    <!-- Header -->
    <header class="h-14 px-4 border-b border-olympus-border flex items-center justify-between shrink-0">
      <div class="flex items-center gap-3">
        <div
          :class="[
            'w-8 h-8 rounded-lg flex items-center justify-center',
            channel.type === 'agent' ? 'bg-blue-500/20' : 'bg-olympus-surface'
          ]"
        >
          <Icon
            :name="channel.type === 'agent' ? 'ph:robot-fill' : 'ph:hash'"
            :class="[
              'w-4 h-4',
              channel.type === 'agent' ? 'text-blue-400' : 'text-olympus-text-muted'
            ]"
          />
        </div>
        <div>
          <h2 class="font-semibold text-sm">{{ channel.name }}</h2>
          <p v-if="channel.description" class="text-xs text-olympus-text-muted line-clamp-1">
            {{ channel.description }}
          </p>
        </div>
      </div>

      <div class="flex items-center gap-1">
        <button class="p-2 rounded-lg hover:bg-olympus-surface transition-colors duration-150 outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50">
          <Icon name="ph:magnifying-glass" class="w-4 h-4 text-olympus-text-muted" />
        </button>
        <button class="p-2 rounded-lg hover:bg-olympus-surface transition-colors duration-150 outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50">
          <Icon name="ph:push-pin" class="w-4 h-4 text-olympus-text-muted" />
        </button>
        <button class="p-2 rounded-lg hover:bg-olympus-surface transition-colors duration-150 outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50">
          <Icon name="ph:sidebar-simple" class="w-4 h-4 text-olympus-text-muted" />
        </button>
      </div>
    </header>

    <!-- Messages -->
    <div class="flex-1 overflow-y-auto p-4 space-y-4">
      <!-- Loading State -->
      <template v-if="loading">
        <div v-for="i in 5" :key="i" class="flex items-start gap-3">
          <SharedSkeleton variant="avatar" />
          <div class="flex-1 space-y-2">
            <div class="flex items-center gap-2">
              <SharedSkeleton custom-class="h-3 w-24" />
              <SharedSkeleton custom-class="h-2 w-12" />
            </div>
            <SharedSkeleton custom-class="h-4 w-full" />
            <SharedSkeleton custom-class="h-4 w-3/4" />
          </div>
        </div>
      </template>

      <!-- Empty State -->
      <SharedEmptyState
        v-else-if="messages.length === 0"
        icon="ph:chat-circle-dots"
        title="No messages yet"
        :description="`Start the conversation in #${channel.name}`"
        class="my-auto"
      />

      <!-- Messages Content -->
      <template v-else>
        <ChatMessage
          v-for="message in messages"
          :key="message.id"
          :message="message"
        />
      </template>

      <ChatTypingIndicator v-if="typingUsers.length > 0" :users="typingUsers" />
    </div>

    <!-- Input -->
    <ChatMessageInput :channel="channel" :sending="sending" @send="handleSend" />
  </div>
</template>

<script setup lang="ts">
import type { Channel, Message, User } from '~/types'

const props = withDefaults(defineProps<{
  channel: Channel
  messages: Message[]
  loading?: boolean
  sending?: boolean
}>(), {
  loading: false,
  sending: false,
})

const emit = defineEmits<{
  send: [message: string]
}>()

const handleSend = (message: string) => {
  emit('send', message)
}

// Mock typing users
const typingUsers = computed(() => {
  return [] as User[]
})
</script>
