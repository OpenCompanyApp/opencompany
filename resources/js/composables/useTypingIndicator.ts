import { ref, computed, watch, type Ref, onUnmounted } from 'vue'
import { useApi } from '@/composables/useApi'
import { useRealtime } from '@/composables/useRealtime'

interface TypingUser {
  userId: string
  userName: string
  timestamp: number
}

const TYPING_TIMEOUT = 3000 // Stop showing after 3 seconds of no typing

export const useTypingIndicator = (channelId: Ref<string | null>, currentUserId: string = 'h1', currentUserName: string = 'User') => {
  const { sendTypingIndicator } = useApi()
  const { on, privateChannel, leaveChannel } = useRealtime()

  // Users currently typing in the channel
  const typingUsers = ref<Map<string, TypingUser>>(new Map())

  // Debounce timer for sending typing events
  let typingTimeout: ReturnType<typeof setTimeout> | null = null
  let isCurrentlyTyping = false

  // Cleanup timer for removing stale typing users
  let cleanupInterval: ReturnType<typeof setInterval> | null = null

  // Real-time listener unsubscribe function
  let unsubscribe: (() => void) | null = null

  // Computed list of typing users (excluding current user)
  const typingUsersList = computed(() => {
    const users: TypingUser[] = []
    typingUsers.value.forEach((user) => {
      if (user.userId !== currentUserId) {
        users.push(user)
      }
    })
    return users
  })

  // Format typing indicator text
  const typingText = computed(() => {
    const users = typingUsersList.value
    if (users.length === 0) return null
    if (users.length === 1) return `${users[0].userName} is typing...`
    if (users.length === 2) return `${users[0].userName} and ${users[1].userName} are typing...`
    return `${users[0].userName} and ${users.length - 1} others are typing...`
  })

  // Send typing indicator
  const startTyping = async () => {
    if (!channelId.value) return

    if (!isCurrentlyTyping) {
      isCurrentlyTyping = true
      try {
        await sendTypingIndicator(channelId.value, currentUserId, currentUserName, true)
      } catch (error) {
        console.error('Failed to send typing indicator:', error)
      }
    }

    // Reset the timeout
    if (typingTimeout) clearTimeout(typingTimeout)
    typingTimeout = setTimeout(stopTyping, TYPING_TIMEOUT)
  }

  // Stop typing indicator
  const stopTyping = async () => {
    if (!channelId.value || !isCurrentlyTyping) return

    isCurrentlyTyping = false
    if (typingTimeout) {
      clearTimeout(typingTimeout)
      typingTimeout = null
    }

    try {
      await sendTypingIndicator(channelId.value, currentUserId, currentUserName, false)
    } catch (error) {
      console.error('Failed to send stop typing indicator:', error)
    }
  }

  // Handle incoming typing events
  const handleTypingEvent = (data: { channelId: string; userId: string; userName: string; isTyping: boolean; timestamp: string }) => {
    if (data.channelId !== channelId.value) return
    if (data.userId === currentUserId) return // Ignore own typing events

    if (data.isTyping) {
      typingUsers.value.set(data.userId, {
        userId: data.userId,
        userName: data.userName,
        timestamp: Date.now(),
      })
    } else {
      typingUsers.value.delete(data.userId)
    }
  }

  // Cleanup stale typing users
  const cleanupStaleTypers = () => {
    const now = Date.now()
    const staleIds: string[] = []

    typingUsers.value.forEach((user, id) => {
      if (now - user.timestamp > TYPING_TIMEOUT + 1000) {
        staleIds.push(id)
      }
    })

    staleIds.forEach(id => typingUsers.value.delete(id))
  }

  // Initialize
  const init = () => {
    // Subscribe to typing events via generic handler
    unsubscribe = on('user:typing', handleTypingEvent)

    // Also subscribe to channel-specific typing events via Echo
    if (channelId.value) {
      const channel = privateChannel(`chat.${channelId.value}`)
      if (channel) {
        channel.listen('.typing', handleTypingEvent)
      }
    }

    // Start cleanup interval
    cleanupInterval = setInterval(cleanupStaleTypers, 1000)
  }

  // Cleanup
  const cleanup = () => {
    // Stop typing if currently typing
    if (isCurrentlyTyping) {
      stopTyping()
    }

    // Clear timeouts
    if (typingTimeout) clearTimeout(typingTimeout)
    if (cleanupInterval) clearInterval(cleanupInterval)

    // Unsubscribe from events
    if (unsubscribe) unsubscribe()

    // Leave channel
    if (channelId.value) {
      leaveChannel(`chat.${channelId.value}`)
    }

    // Clear typing users
    typingUsers.value.clear()
  }

  // Watch for channel changes
  watch(channelId, (newChannel, oldChannel) => {
    // Stop typing in old channel
    if (oldChannel && isCurrentlyTyping) {
      isCurrentlyTyping = false
      sendTypingIndicator(oldChannel, currentUserId, currentUserName, false).catch(console.error)
      leaveChannel(`chat.${oldChannel}`)
    }

    // Subscribe to new channel
    if (newChannel) {
      const channel = privateChannel(`chat.${newChannel}`)
      if (channel) {
        channel.listen('.typing', handleTypingEvent)
      }
    }

    // Clear typing users when channel changes
    typingUsers.value.clear()
  })

  // Auto cleanup on unmount
  onUnmounted(() => {
    cleanup()
  })

  return {
    typingUsers: typingUsersList,
    typingText,
    startTyping,
    stopTyping,
    init,
    cleanup,
  }
}
