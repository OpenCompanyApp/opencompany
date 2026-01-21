<template>
  <PopoverRoot v-model:open="isOpen">
    <PopoverTrigger as-child>
      <button
        type="button"
        class="relative p-2 rounded-lg text-gray-500 hover:text-gray-900 hover:bg-gray-100 transition-colors duration-150"
      >
        <Icon name="ph:bell" class="w-5 h-5" />
        <Transition
          enter-active-class="transition-opacity duration-150 ease-out"
          leave-active-class="transition-opacity duration-100 ease-out"
          enter-from-class="opacity-0"
          leave-to-class="opacity-0"
        >
          <span
            v-if="unreadCount > 0"
            class="absolute -top-0.5 -right-0.5 w-5 h-5 flex items-center justify-center text-[10px] font-bold bg-gray-900 text-white rounded-full"
          >
            {{ unreadCount > 9 ? '9+' : unreadCount }}
          </span>
        </Transition>
      </button>
    </PopoverTrigger>

    <PopoverPortal>
      <PopoverContent
        class="z-50 w-96 bg-white border border-gray-200 rounded-lg shadow-lg animate-in fade-in-0 duration-150"
        :side-offset="8"
        align="end"
      >
        <!-- Header -->
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200">
          <h3 class="font-semibold text-gray-900">Notifications</h3>
          <div class="flex items-center gap-2">
            <button
              v-if="unreadCount > 0"
              type="button"
              class="text-xs text-gray-600 hover:text-gray-900 hover:underline transition-colors duration-150"
              @click="markAllAsRead"
            >
              Mark all read
            </button>
            <PopoverClose class="p-1 rounded-md text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors duration-150">
              <Icon name="ph:x" class="w-4 h-4" />
            </PopoverClose>
          </div>
        </div>

        <!-- Notifications List -->
        <div class="max-h-96 overflow-y-auto">
          <div v-if="loading" class="p-4 space-y-3">
            <Skeleton v-for="i in 3" :key="i" custom-class="h-16 rounded-lg" />
          </div>

          <div v-else-if="notifications.length === 0" class="p-8 text-center">
            <div class="w-12 h-12 mx-auto mb-3 rounded-lg bg-gray-100 flex items-center justify-center">
              <Icon name="ph:bell-slash" class="w-6 h-6 text-gray-400" />
            </div>
            <p class="text-sm text-gray-500">No notifications yet</p>
          </div>

          <div v-else class="divide-y divide-gray-100">
            <NotificationItem
              v-for="notification in notifications"
              :key="notification.id"
              :notification="notification"
              @click="handleNotificationClick(notification)"
              @mark-read="markAsRead(notification.id)"
            />
          </div>
        </div>

        <!-- Footer -->
        <div v-if="notifications.length > 0" class="px-4 py-3 border-t border-gray-200">
          <Link
            href="/notifications"
            class="flex items-center justify-center gap-2 text-sm text-gray-600 hover:text-gray-900 transition-colors duration-150"
            @click="isOpen = false"
          >
            <span>View all notifications</span>
            <Icon name="ph:arrow-right" class="w-4 h-4" />
          </Link>
        </div>

        <PopoverArrow class="fill-white" />
      </PopoverContent>
    </PopoverPortal>
  </PopoverRoot>
</template>

<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import Icon from '@/Components/shared/Icon.vue'
import {
  PopoverArrow,
  PopoverClose,
  PopoverContent,
  PopoverPortal,
  PopoverRoot,
  PopoverTrigger,
} from 'reka-ui'
import Skeleton from '@/Components/shared/Skeleton.vue'
import NotificationItem from '@/Components/notifications/NotificationItem.vue'

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

const isOpen = ref(false)
const loading = ref(false)
const notifications = ref<Notification[]>([])
const unreadCount = ref(0)

const fetchNotifications = async () => {
  loading.value = true
  try {
    const response = await fetch('/api/notifications?userId=h1&limit=10')
    const data = await response.json()
    notifications.value = data
  } catch (error) {
    console.error('Failed to fetch notifications:', error)
  } finally {
    loading.value = false
  }
}

const fetchUnreadCount = async () => {
  try {
    const response = await fetch('/api/notifications/count?userId=h1')
    const data = await response.json()
    unreadCount.value = data.unreadCount
  } catch (error) {
    console.error('Failed to fetch unread count:', error)
  }
}

const markAsRead = async (notificationId: string) => {
  try {
    await fetch(`/api/notifications/${notificationId}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ isRead: true }),
    })
    const notification = notifications.value.find(n => n.id === notificationId)
    if (notification && !notification.isRead) {
      notification.isRead = true
      unreadCount.value = Math.max(0, unreadCount.value - 1)
    }
  } catch (error) {
    console.error('Failed to mark notification as read:', error)
  }
}

const markAllAsRead = async () => {
  try {
    await fetch('/api/notifications/read-all', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ userId: 'h1' }),
    })
    notifications.value.forEach(n => n.isRead = true)
    unreadCount.value = 0
  } catch (error) {
    console.error('Failed to mark all as read:', error)
  }
}

const handleNotificationClick = (notification: Notification) => {
  if (!notification.isRead) {
    markAsRead(notification.id)
  }
  if (notification.actionUrl) {
    router.visit(notification.actionUrl)
    isOpen.value = false
  }
}

// Fetch on mount and when popover opens
watch(isOpen, (open) => {
  if (open) {
    fetchNotifications()
  }
})

let interval: ReturnType<typeof setInterval> | null = null

onMounted(() => {
  fetchUnreadCount()
  // Poll for unread count every 30 seconds
  interval = setInterval(fetchUnreadCount, 30000)
})

onUnmounted(() => {
  if (interval) {
    clearInterval(interval)
  }
})
</script>
