import { ref, readonly, onUnmounted } from 'vue'
import type { PresenceStatus } from '@/types'
import { useApi } from '@/composables/useApi'
import { useRealtime } from '@/composables/useRealtime'

const AWAY_TIMEOUT = 5 * 60 * 1000 // 5 minutes
const HEARTBEAT_INTERVAL = 30 * 1000 // 30 seconds

export const usePresence = (userId: string, workspaceId?: string) => {
  const { updateUserPresence } = useApi()
  const { presenceChannel, leaveChannel, on } = useRealtime()

  const presenceChannelName = workspaceId ? `workspace.${workspaceId}.online` : 'online'

  const currentPresence = ref<PresenceStatus>('offline')
  const lastActivityTime = ref(Date.now())
  const onlineUsers = ref<Map<string, { id: string; name?: string; presence: PresenceStatus }>>(new Map())

  let awayTimeout: ReturnType<typeof setTimeout> | null = null
  let heartbeatInterval: ReturnType<typeof setInterval> | null = null
  let unsubscribePresence: (() => void) | null = null
  let activityListenersAttached = false

  // Update presence on server
  const setPresence = async (presence: PresenceStatus) => {
    if (currentPresence.value === presence) return
    currentPresence.value = presence
    try {
      await updateUserPresence(userId, presence)
    } catch (error) {
      console.error('Failed to update presence:', error)
    }
  }

  // Reset activity timer
  const resetActivityTimer = () => {
    lastActivityTime.value = Date.now()
    if (currentPresence.value === 'away') {
      setPresence('online')
    }
    if (awayTimeout) {
      clearTimeout(awayTimeout)
    }
    awayTimeout = setTimeout(() => {
      if (currentPresence.value === 'online') {
        setPresence('away')
      }
    }, AWAY_TIMEOUT)
  }

  // Initialize presence tracking
  const initPresence = () => {
    // Set online immediately
    setPresence('online')

    // Listen for user activity (only attach once)
    if (!activityListenersAttached && typeof window !== 'undefined') {
      const activityEvents = ['mousedown', 'mousemove', 'keydown', 'scroll', 'touchstart']
      activityEvents.forEach(event => {
        window.addEventListener(event, resetActivityTimer, { passive: true })
      })

      // Handle page visibility changes
      document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
          setPresence('away')
        } else {
          resetActivityTimer()
        }
      })

      // Handle page close
      window.addEventListener('beforeunload', () => {
        // Use sendBeacon for reliable delivery on page close
        const data = JSON.stringify({ presence: 'offline' })
        navigator.sendBeacon(`/api/users/${userId}/presence`, data)
      })

      activityListenersAttached = true
    }

    // Start heartbeat to keep presence alive
    heartbeatInterval = setInterval(() => {
      if (currentPresence.value === 'online' || currentPresence.value === 'away') {
        // Just touch the last seen timestamp
        updateUserPresence(userId, currentPresence.value).catch(() => {})
      }
    }, HEARTBEAT_INTERVAL)

    // Join presence channel for real-time updates
    const channel = presenceChannel(presenceChannelName)
    if (channel) {
      channel
        .here((users: { id: string; name?: string }[]) => {
          // Initial list of online users
          onlineUsers.value.clear()
          users.forEach(user => {
            onlineUsers.value.set(user.id, { ...user, presence: 'online' })
          })
        })
        .joining((user: { id: string; name?: string }) => {
          // User came online
          onlineUsers.value.set(user.id, { ...user, presence: 'online' })
        })
        .leaving((user: { id: string }) => {
          // User went offline
          onlineUsers.value.delete(user.id)
        })
    }

    // Listen for presence updates from other users
    unsubscribePresence = on('user:presence', (data: { userId: string; presence: PresenceStatus }) => {
      const existing = onlineUsers.value.get(data.userId)
      if (existing) {
        existing.presence = data.presence
      }
    })

    // Start activity timer
    resetActivityTimer()
  }

  // Cleanup on unmount
  const cleanup = () => {
    if (awayTimeout) {
      clearTimeout(awayTimeout)
      awayTimeout = null
    }
    if (heartbeatInterval) {
      clearInterval(heartbeatInterval)
      heartbeatInterval = null
    }
    unsubscribePresence?.()
    leaveChannel(presenceChannelName)
    setPresence('offline')
  }

  // Auto cleanup on unmount
  onUnmounted(() => {
    cleanup()
  })

  return {
    currentPresence: readonly(currentPresence),
    onlineUsers: readonly(onlineUsers),
    setPresence,
    initPresence,
    cleanup,
  }
}
