<template>
  <Popover v-model:open="isOpen">
    <button
      type="button"
      class="relative p-2 rounded-lg text-neutral-500 dark:text-neutral-300 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors duration-150"
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
          class="absolute -top-0.5 -right-0.5 w-5 h-5 flex items-center justify-center text-[10px] font-bold bg-neutral-900 text-white rounded-full"
        >
          {{ unreadCount > 9 ? '9+' : unreadCount }}
        </span>
      </Transition>
    </button>

    <template #content>
      <div class="w-96">
        <!-- Header -->
        <div class="flex items-center justify-between px-4 py-3 border-b border-neutral-200 dark:border-neutral-700">
          <h3 class="font-semibold text-neutral-900 dark:text-white">Notifications</h3>
          <div class="flex items-center gap-2">
            <button
              v-if="unreadCount > 0"
              type="button"
              class="text-xs text-neutral-600 dark:text-neutral-200 hover:text-neutral-900 dark:hover:text-white hover:underline transition-colors duration-150"
              @click="markAllAsRead"
            >
              Mark all read
            </button>
            <button
              type="button"
              class="p-1 rounded-md text-neutral-400 dark:text-neutral-400 hover:text-neutral-600 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors duration-150"
              @click="isOpen = false"
            >
              <Icon name="ph:x" class="w-4 h-4" />
            </button>
          </div>
        </div>

        <!-- Notifications List -->
        <div class="max-h-96 overflow-y-auto">
          <div v-if="loading" class="p-4 space-y-3">
            <Skeleton v-for="i in 3" :key="i" custom-class="h-16 rounded-lg" />
          </div>

          <div v-else-if="notifications.length === 0" class="p-8 text-center">
            <div class="w-12 h-12 mx-auto mb-3 rounded-lg bg-neutral-100 dark:bg-neutral-700 flex items-center justify-center">
              <Icon name="ph:bell-slash" class="w-6 h-6 text-neutral-400 dark:text-neutral-400" />
            </div>
            <p class="text-sm text-neutral-500 dark:text-neutral-300">No notifications yet</p>
          </div>

          <div v-else class="divide-y divide-neutral-100 dark:divide-neutral-800">
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
        <div v-if="notifications.length > 0" class="px-4 py-3 border-t border-neutral-200 dark:border-neutral-700">
          <Link
            href="/notifications"
            class="flex items-center justify-center gap-2 text-sm text-neutral-600 dark:text-neutral-200 hover:text-neutral-900 dark:hover:text-white transition-colors duration-150"
            @click="isOpen = false"
          >
            <span>View all notifications</span>
            <Icon name="ph:arrow-right" class="w-4 h-4" />
          </Link>
        </div>
      </div>
    </template>
  </Popover>
</template>

<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { apiFetch } from '@/utils/apiFetch'
import Icon from '@/Components/shared/Icon.vue'
import Popover from '@/Components/shared/Popover.vue'
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
    const response = await apiFetch('/api/notifications?userId=h1&limit=10')
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
    const response = await apiFetch('/api/notifications/count?userId=h1')
    const data = await response.json()
    unreadCount.value = data.unreadCount
  } catch (error) {
    console.error('Failed to fetch unread count:', error)
  }
}

const markAsRead = async (notificationId: string) => {
  try {
    await apiFetch(`/api/notifications/${notificationId}`, {
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
    await apiFetch('/api/notifications/read-all', {
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
