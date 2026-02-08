import { ref, readonly, onMounted, onUnmounted } from 'vue'
import Echo from 'laravel-echo'

interface RealtimeEvent {
  event: string
  data: unknown
  timestamp: string
}

type EventHandler = (data: unknown) => void

// Shared state across all instances
const echoInstance = ref<Echo | null>(null)
const isConnected = ref(false)
const handlers = ref<Map<string, Set<EventHandler>>>(new Map())

// Channel subscriptions tracker
const channelSubscriptions = ref<Map<string, ReturnType<typeof echoInstance.value.channel>>>(new Map())

export function useRealtime() {
  const connect = () => {
    if (typeof window === 'undefined') return
    if (echoInstance.value) return

    // Echo instance should be configured in bootstrap.js or app.ts
    // This assumes Echo is already set up with Pusher or WebSocket driver
    echoInstance.value = (window as { Echo?: Echo }).Echo || null

    if (echoInstance.value) {
      console.log('[Realtime] Echo instance found, monitoring connection...')

      // Monitor actual WebSocket connection state via Pusher connector
      const connector = (echoInstance.value as any).connector?.pusher
      if (connector) {
        connector.connection.bind('connected', () => {
          isConnected.value = true
          console.log('[Realtime] WebSocket connected')
        })
        connector.connection.bind('disconnected', () => {
          isConnected.value = false
          console.warn('[Realtime] WebSocket disconnected')
        })
        connector.connection.bind('error', (err: any) => {
          console.error('[Realtime] WebSocket error', err)
        })
        // Check if already connected
        if (connector.connection.state === 'connected') {
          isConnected.value = true
          console.log('[Realtime] WebSocket already connected')
        }
      } else {
        // Fallback: assume connected if Echo exists
        isConnected.value = true
        console.log('[Realtime] Connected via Laravel Echo (no Pusher connector)')
      }
    } else {
      console.warn('[Realtime] Laravel Echo not configured. Real-time features will be disabled.')
    }
  }

  const disconnect = () => {
    if (echoInstance.value) {
      // Leave all channels
      channelSubscriptions.value.forEach((_, channelName) => {
        echoInstance.value?.leave(channelName)
      })
      channelSubscriptions.value.clear()

      echoInstance.value.disconnect()
      echoInstance.value = null
    }
    isConnected.value = false
    handlers.value.clear()
  }

  // Subscribe to a channel
  const channel = (channelName: string) => {
    if (!echoInstance.value) {
      console.warn('[Realtime] Cannot subscribe to channel - Echo not connected')
      return null
    }

    if (!channelSubscriptions.value.has(channelName)) {
      const ch = echoInstance.value.channel(channelName)
      channelSubscriptions.value.set(channelName, ch)
    }

    return channelSubscriptions.value.get(channelName)
  }

  // Subscribe to a private channel
  const privateChannel = (channelName: string) => {
    if (!echoInstance.value) {
      console.warn('[Realtime] Cannot subscribe to private channel - Echo not connected')
      return null
    }

    const fullName = `private-${channelName}`
    if (!channelSubscriptions.value.has(fullName)) {
      const ch = echoInstance.value.private(channelName)
      channelSubscriptions.value.set(fullName, ch)
    }

    return channelSubscriptions.value.get(fullName)
  }

  // Subscribe to a presence channel
  const presenceChannel = (channelName: string) => {
    if (!echoInstance.value) {
      console.warn('[Realtime] Cannot subscribe to presence channel - Echo not connected')
      return null
    }

    const fullName = `presence-${channelName}`
    if (!channelSubscriptions.value.has(fullName)) {
      const ch = echoInstance.value.join(channelName)
      channelSubscriptions.value.set(fullName, ch)
    }

    return channelSubscriptions.value.get(fullName)
  }

  // Leave a channel
  const leaveChannel = (channelName: string) => {
    if (echoInstance.value) {
      echoInstance.value.leave(channelName)
      channelSubscriptions.value.delete(channelName)
      channelSubscriptions.value.delete(`private-${channelName}`)
      channelSubscriptions.value.delete(`presence-${channelName}`)
    }
  }

  // Generic event listener (for backward compatibility)
  const on = (event: string, handler: EventHandler) => {
    if (!handlers.value.has(event)) {
      handlers.value.set(event, new Set())
    }
    handlers.value.get(event)!.add(handler)

    // Return unsubscribe function
    return () => {
      handlers.value.get(event)?.delete(handler)
    }
  }

  const off = (event: string, handler?: EventHandler) => {
    if (handler) {
      handlers.value.get(event)?.delete(handler)
    } else {
      handlers.value.delete(event)
    }
  }

  // Trigger handlers for an event (called from channel listeners)
  const emit = (event: string, data: unknown) => {
    const eventHandlers = handlers.value.get(event)
    if (eventHandlers) {
      for (const handler of eventHandlers) {
        handler(data)
      }
    }

    // Also trigger wildcard handlers
    const wildcardHandlers = handlers.value.get('*')
    if (wildcardHandlers) {
      for (const handler of wildcardHandlers) {
        handler({ event, data })
      }
    }
  }

  // Auto-connect on mount
  onMounted(() => {
    connect()
  })

  return {
    echo: readonly(echoInstance),
    isConnected: readonly(isConnected),
    connect,
    disconnect,
    channel,
    privateChannel,
    presenceChannel,
    leaveChannel,
    on,
    off,
    emit,
  }
}

// Helper composable for specific event subscriptions
export function useRealtimeEvent<T = unknown>(event: string, handler: (data: T) => void) {
  const { on } = useRealtime()

  onMounted(() => {
    const unsubscribe = on(event, handler as EventHandler)
    onUnmounted(() => {
      unsubscribe()
    })
  })
}

// Helper composable for channel-specific subscriptions
export function useChannelListener<T = unknown>(
  channelName: string,
  eventName: string,
  handler: (data: T) => void,
  options: { private?: boolean; presence?: boolean } = {}
) {
  const { channel, privateChannel, presenceChannel, leaveChannel } = useRealtime()

  onMounted(() => {
    let ch
    if (options.presence) {
      ch = presenceChannel(channelName)
    } else if (options.private) {
      ch = privateChannel(channelName)
    } else {
      ch = channel(channelName)
    }

    if (ch) {
      ch.listen(eventName, handler as (data: unknown) => void)
    }
  })

  onUnmounted(() => {
    leaveChannel(channelName)
  })
}
