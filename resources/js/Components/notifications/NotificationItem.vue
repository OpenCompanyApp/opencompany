<template>
  <div
    :class="[
      'px-4 py-3 cursor-pointer',
      'transition-colors duration-150',
      'hover:bg-gray-50',
      !notification.isRead && 'bg-gray-50 border-l-2 border-gray-900',
      notification.isRead && 'border-l-2 border-transparent',
    ]"
    @click="$emit('click')"
  >
    <div class="flex items-start gap-3">
      <!-- Icon or Avatar -->
      <div v-if="notification.actor" class="shrink-0">
        <img
          v-if="notification.actor.avatar"
          :src="notification.actor.avatar"
          :alt="notification.actor.name"
          class="w-10 h-10 rounded-full"
        />
        <div
          v-else
          class="w-10 h-10 rounded-full flex items-center justify-center text-white font-semibold bg-gray-500"
        >
          {{ notification.actor.name.charAt(0) }}
        </div>
      </div>
      <div
        v-else
        class="w-10 h-10 rounded-lg shrink-0 flex items-center justify-center bg-gray-100"
      >
        <Icon :name="iconName" class="w-5 h-5 text-gray-500" />
      </div>

      <!-- Content -->
      <div class="flex-1 min-w-0">
        <div class="flex items-start justify-between gap-2">
          <p class="text-sm font-medium text-gray-900 line-clamp-1">
            {{ notification.title }}
          </p>
          <div class="flex items-center gap-2 shrink-0">
            <Transition
              enter-active-class="transition-opacity duration-150 ease-out"
              leave-active-class="transition-opacity duration-100 ease-out"
              enter-from-class="opacity-0"
              leave-to-class="opacity-0"
            >
              <span
                v-if="!notification.isRead"
                class="w-2 h-2 rounded-full bg-gray-900"
              />
            </Transition>
            <Transition
              enter-active-class="transition-opacity duration-150 ease-out"
              leave-active-class="transition-opacity duration-100 ease-out"
              enter-from-class="opacity-0"
              leave-to-class="opacity-0"
            >
              <button
                v-if="!notification.isRead"
                type="button"
                class="p-1.5 rounded-md text-gray-400 transition-colors duration-150 hover:text-gray-600 hover:bg-gray-100"
                @click.stop="$emit('mark-read')"
              >
                <Icon name="ph:check" class="w-4 h-4" />
              </button>
            </Transition>
          </div>
        </div>
        <p class="text-xs text-gray-500 line-clamp-2 mt-0.5">
          {{ notification.message }}
        </p>
        <p class="text-[10px] text-gray-400 mt-1.5 flex items-center gap-1">
          <Icon name="ph:clock" class="w-3 h-3" />
          {{ formatTimeAgo(notification.createdAt) }}
        </p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Icon } from '@iconify/vue'

interface Notification {
  id: string
  type: string
  title: string
  message: string
  isRead: boolean
  actionUrl?: string
  actor?: {
    id: string
    name: string
    avatar?: string
  }
  createdAt: string
}

const props = defineProps<{
  notification: Notification
}>()

defineEmits<{
  click: []
  'mark-read': []
}>()

const typeIcons: Record<string, string> = {
  approval: 'ph:check-circle',
  task: 'ph:check-square',
  message: 'ph:chat-circle',
  agent: 'ph:robot',
  system: 'ph:info',
  mention: 'ph:at',
}

const iconName = computed(() => typeIcons[props.notification.type] || typeIcons.system)

const formatTimeAgo = (dateString: string) => {
  const date = new Date(dateString)
  const now = new Date()
  const seconds = Math.floor((now.getTime() - date.getTime()) / 1000)

  if (seconds < 60) return 'Just now'
  if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`
  if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`
  if (seconds < 604800) return `${Math.floor(seconds / 86400)}d ago`
  return date.toLocaleDateString()
}
</script>
