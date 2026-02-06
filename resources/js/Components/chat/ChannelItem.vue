<template>
  <Tooltip
    v-if="showTooltip"
    :delay-open="tooltipDelay"
    :side="tooltipSide"
    :side-offset="8"
  >
    <component
      :is="componentType"
      :class="containerClasses"
      :type="componentType === 'button' ? 'button' : undefined"
      :href="to"
      @click="handleClick"
      @contextmenu="handleContextMenu"
    >
      <ChannelItemContent />
    </component>
    <template #content>
      <div :class="tooltipClasses">
        <ChannelTooltipContent />
      </div>
    </template>
  </Tooltip>

  <!-- Without tooltip -->
  <component
    v-else
    :is="componentType"
    :class="containerClasses"
    :type="componentType === 'button' ? 'button' : undefined"
    :href="to"
    @click="handleClick"
    @contextmenu="handleContextMenu"
  >
    <ChannelItemContent />
  </component>

  <!-- Context Menu -->
  <ContextMenu v-model:open="contextMenuOpen" :items="contextMenuDropdownItems">
    <div
      ref="contextMenuRef"
      :style="contextMenuStyle"
      class="fixed w-px h-px pointer-events-none"
    />
  </ContextMenu>
</template>

<script setup lang="ts">
import { ref, computed, h, defineComponent } from 'vue'
import { Link } from '@inertiajs/vue3'
import Icon from '@/Components/shared/Icon.vue'
import Badge from '@/Components/shared/Badge.vue'
import Tooltip from '@/Components/shared/Tooltip.vue'
import ContextMenu from '@/Components/shared/ContextMenu.vue'
import type { Channel, User } from '@/types'

type ChannelItemSize = 'sm' | 'md' | 'lg'
type ChannelItemVariant = 'default' | 'compact' | 'prominent'
type TooltipSide = 'top' | 'right' | 'bottom' | 'left'

interface ContextMenuItem {
  label: string
  icon?: string
  shortcut?: string
  variant?: 'default' | 'danger'
  disabled?: boolean
  action?: string
}

const props = withDefaults(defineProps<{
  // Core
  channel: Channel
  selected?: boolean

  // Appearance
  size?: ChannelItemSize
  variant?: ChannelItemVariant

  // Display options
  showIcon?: boolean
  showUnreadBadge?: boolean
  showUnreadDot?: boolean
  showMutedIcon?: boolean
  showPresence?: boolean
  showTypingIndicator?: boolean

  // Status
  muted?: boolean
  pinned?: boolean
  draft?: boolean
  typingUsers?: User[]

  // Tooltip
  showTooltip?: boolean
  tooltipSide?: TooltipSide
  tooltipDelay?: number

  // Navigation
  to?: string

  // Context menu
  contextMenuItems?: ContextMenuItem[]
}>(), {
  selected: false,
  size: 'md',
  variant: 'default',
  showIcon: true,
  showUnreadBadge: true,
  showUnreadDot: true,
  showMutedIcon: true,
  showPresence: false,
  showTypingIndicator: true,
  muted: false,
  pinned: false,
  draft: false,
  showTooltip: true,
  tooltipSide: 'right',
  tooltipDelay: 500,
  contextMenuItems: () => [
    { label: 'Mark as read', icon: 'ph:check', action: 'markRead' },
    { label: 'Mute channel', icon: 'ph:speaker-x', action: 'mute' },
    { label: 'Pin channel', icon: 'ph:push-pin', action: 'pin' },
    { label: 'Copy link', icon: 'ph:link', action: 'copyLink', shortcut: '⌘L' },
    { label: 'Leave channel', icon: 'ph:sign-out', variant: 'danger', action: 'leave' },
  ],
})

const emit = defineEmits<{
  click: [channel: Channel]
  contextAction: [action: string, channel: Channel]
}>()

const contextMenuOpen = ref(false)
const contextMenuPosition = ref({ x: 0, y: 0 })
const contextMenuRef = ref<HTMLElement | null>(null)

// Context menu items in UContextMenu format
const contextMenuDropdownItems = computed(() => [
  props.contextMenuItems.map(item => ({
    label: item.label,
    icon: item.icon,
    shortcut: item.shortcut,
    color: item.variant === 'danger' ? 'error' as const : undefined,
    disabled: item.disabled,
    click: () => handleContextAction(item),
  })),
])

// Component type based on navigation
const componentType = computed(() => {
  if (props.to) return Link
  return 'button'
})

// Size configurations
const sizeConfig: Record<ChannelItemSize, {
  container: string
  icon: string
  text: string
  badge: string
  gap: string
}> = {
  sm: {
    container: 'px-2 py-1.5',
    icon: 'w-3.5 h-3.5',
    text: 'text-xs',
    badge: 'min-w-4 h-4 text-[9px] px-1',
    gap: 'gap-2',
  },
  md: {
    container: 'px-3 py-2',
    icon: 'w-4 h-4',
    text: 'text-sm',
    badge: 'min-w-5 h-5 text-[10px] px-1.5',
    gap: 'gap-2.5',
  },
  lg: {
    container: 'px-4 py-2.5',
    icon: 'w-5 h-5',
    text: 'text-base',
    badge: 'min-w-6 h-6 text-xs px-2',
    gap: 'gap-3',
  },
}

// Channel icon based on type
const channelIcon = computed(() => {
  if (props.channel.type === 'dm') return 'ph:chat-circle'
  if (props.channel.type === 'external') {
    // Provider-specific icons for external channels
    const providerIcons: Record<string, string> = {
      telegram: 'ph:telegram-logo',
      slack: 'ph:slack-logo',
      discord: 'ph:discord-logo',
      google_chat: 'ph:google-logo',
      whatsapp: 'ph:whatsapp-logo',
    }
    // Handle both camelCase and snake_case from API
    const provider = props.channel.externalProvider || (props.channel as any).external_provider || ''
    return providerIcons[provider] || 'ph:plug'
  }
  if (props.channel.private) return 'ph:lock-simple'
  return 'ph:hash'
})

// Icon color based on channel type and state
const iconColorClass = computed(() => {
  if (props.selected) return 'text-neutral-900 dark:text-white'
  if (props.muted) return 'text-neutral-400 dark:text-neutral-400'
  return 'text-neutral-500 dark:text-neutral-300 group-hover:text-neutral-900 dark:group-hover:text-white'
})

// Has unread messages
const hasUnread = computed(() => (props.channel.unreadCount || 0) > 0)

// Show typing
const isTyping = computed(() => (props.typingUsers?.length || 0) > 0)

// Container classes
const containerClasses = computed(() => {
  const classes = [
    'w-full flex items-center rounded-lg transition-colors duration-150 text-left group outline-none',
    'focus-visible:ring-1 focus-visible:ring-neutral-400 dark:focus-visible:ring-neutral-500',
    sizeConfig[props.size].container,
    sizeConfig[props.size].gap,
  ]

  // Selected state
  if (props.selected) {
    classes.push(
      'bg-neutral-100 dark:bg-neutral-700 text-neutral-900 dark:text-white',
      'border-l-2 border-neutral-900 dark:border-white pl-[10px]',
    )
  } else {
    classes.push(
      'hover:bg-neutral-50 dark:hover:bg-neutral-800 text-neutral-500 dark:text-neutral-300 hover:text-neutral-900 dark:hover:text-white',
    )
  }

  // Muted state
  if (props.muted && !props.selected) {
    classes.push('opacity-60 hover:opacity-80')
  }

  // Variant
  if (props.variant === 'compact') {
    classes.push('py-1')
  } else if (props.variant === 'prominent') {
    classes.push('py-3 bg-neutral-50 dark:bg-neutral-800 hover:bg-neutral-100 dark:hover:bg-neutral-700')
  }

  return classes
})

// Context menu style
const contextMenuStyle = computed(() => ({
  position: 'fixed' as const,
  top: `${contextMenuPosition.value.y}px`,
  left: `${contextMenuPosition.value.x}px`,
}))

// Tooltip classes
const tooltipClasses = computed(() => [
  'z-50 bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 rounded-lg',
  'px-3 py-2.5 text-sm shadow-md max-w-64',
  'animate-in fade-in-0 duration-150',
])

// Channel Item Content component
const ChannelItemContent = defineComponent({
  name: 'ChannelItemContent',
  setup() {
    return () => h('div', { class: 'flex items-center gap-2 flex-1 min-w-0' }, [
      // Icon container
      props.showIcon && h('div', {
        class: [
          'flex items-center justify-center shrink-0 transition-colors duration-150',
          props.variant === 'prominent' && 'w-8 h-8 rounded-lg bg-neutral-50 dark:bg-neutral-800 group-hover:bg-neutral-100 dark:group-hover:bg-neutral-700',
        ],
      }, [
        h(Icon, {
          name: channelIcon.value,
          class: [sizeConfig[props.size].icon, iconColorClass.value, 'transition-colors duration-150'],
        }),
      ]),

      // Name and status
      h('div', { class: 'flex-1 min-w-0' }, [
        h('div', { class: 'flex items-center gap-1.5' }, [
          // Channel name
          h('span', {
            class: [
              'truncate font-medium transition-colors duration-150',
              sizeConfig[props.size].text,
              hasUnread.value && !props.selected && 'font-semibold text-neutral-900 dark:text-white',
            ],
          }, props.channel.name),

          // Pinned icon
          props.pinned && h(Icon, {
            name: 'ph:push-pin-fill',
            class: 'w-3 h-3 text-neutral-400 dark:text-neutral-400 shrink-0',
          }),

          // Muted icon
          props.muted && props.showMutedIcon && h(Icon, {
            name: 'ph:speaker-x-fill',
            class: 'w-3 h-3 text-neutral-400 dark:text-neutral-400 shrink-0',
          }),
        ]),

        // Typing indicator or last message
        isTyping.value && props.showTypingIndicator
          ? h('span', {
              class: 'text-xs text-neutral-500 dark:text-neutral-300 truncate flex items-center gap-1',
            }, [
              h('span', { class: 'flex gap-0.5' }, [
                h('span', { class: 'w-1 h-1 bg-neutral-500 dark:bg-neutral-400 rounded-full animate-bounce', style: 'animation-delay: 0ms' }),
                h('span', { class: 'w-1 h-1 bg-neutral-500 dark:bg-neutral-400 rounded-full animate-bounce', style: 'animation-delay: 150ms' }),
                h('span', { class: 'w-1 h-1 bg-neutral-500 dark:bg-neutral-400 rounded-full animate-bounce', style: 'animation-delay: 300ms' }),
              ]),
              props.typingUsers![0].name.split(' ')[0] + ' is typing...',
            ])
          : props.draft
            ? h('span', { class: 'text-xs text-neutral-600 dark:text-neutral-200 truncate flex items-center gap-1' }, [
                h(Icon, { name: 'ph:pencil-simple', class: 'w-3 h-3' }),
                'Draft',
              ])
            : props.channel.lastMessage && h('span', {
                class: 'text-xs text-neutral-400 dark:text-neutral-400 truncate transition-colors duration-150 group-hover:text-neutral-500 dark:group-hover:text-neutral-400',
              }, props.channel.lastMessage),
      ]),

      // Right side indicators
      h('div', { class: 'flex items-center gap-1.5 shrink-0' }, [
        // Presence indicator
        props.showPresence && props.channel.onlineCount && h('span', {
          class: 'text-xs text-neutral-400 dark:text-neutral-400 flex items-center gap-1',
        }, [
          h('span', { class: 'w-1.5 h-1.5 rounded-full bg-green-600' }),
          `${props.channel.onlineCount}`,
        ]),

        // Unread badge or dot
        hasUnread.value && !props.selected && (
          props.showUnreadBadge
            ? h('span', {
                class: [
                  'rounded-full font-semibold text-center transition-colors duration-150',
                  'bg-neutral-100 dark:bg-neutral-700 text-neutral-900 dark:text-white',
                  sizeConfig[props.size].badge,
                ],
              }, (props.channel.unreadCount || 0) > 99 ? '99+' : props.channel.unreadCount)
            : props.showUnreadDot && h('span', {
                class: 'w-2 h-2 rounded-full bg-neutral-900 dark:bg-white shrink-0',
              })
        ),

        // Timestamp
        props.channel.lastMessageAt && !hasUnread.value && h('span', {
          class: 'text-xs text-neutral-400 dark:text-neutral-400 transition-colors duration-150 group-hover:text-neutral-500 dark:group-hover:text-neutral-400',
        }, formatRelativeTime(props.channel.lastMessageAt)),
      ]),
    ])
  },
})

// Tooltip content component
const ChannelTooltipContent = defineComponent({
  name: 'ChannelTooltipContent',
  setup() {
    return () => h('div', { class: 'space-y-2' }, [
      // Channel name and type
      h('div', { class: 'flex items-center gap-2' }, [
        h(Icon, {
          name: channelIcon.value,
          class: 'w-4 h-4 text-neutral-500 dark:text-neutral-300',
        }),
        h('span', { class: 'font-semibold text-neutral-900 dark:text-white' }, props.channel.name),
        props.channel.private && h(Badge, {
          size: 'xs',
          variant: 'secondary',
        }, () => 'Private'),
      ]),

      // Description
      props.channel.description && h('p', {
        class: 'text-xs text-neutral-500 dark:text-neutral-300 leading-relaxed',
      }, props.channel.description),

      // Stats
      h('div', { class: 'flex items-center gap-3 text-xs text-neutral-400 dark:text-neutral-400 pt-2 mt-2 border-t border-neutral-200 dark:border-neutral-700' }, [
        props.channel.members?.length && h('span', {
          class: 'flex items-center gap-1.5',
        }, [
          h(Icon, { name: 'ph:users', class: 'w-3.5 h-3.5' }),
          `${props.channel.members.length} members`,
        ]),
        props.channel.onlineCount && h('span', {
          class: 'flex items-center gap-1.5',
        }, [
          h('span', { class: 'w-2 h-2 rounded-full bg-green-600' }),
          `${props.channel.onlineCount} online`,
        ]),
      ]),
    ])
  },
})

// Format relative time
const formatRelativeTime = (date: Date): string => {
  const now = new Date()
  const diff = now.getTime() - new Date(date).getTime()
  const minutes = Math.floor(diff / 60000)
  const hours = Math.floor(minutes / 60)
  const days = Math.floor(hours / 24)

  if (minutes < 1) return 'now'
  if (minutes < 60) return `${minutes}m`
  if (hours < 24) return `${hours}h`
  if (days < 7) return `${days}d`
  return new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

// Handlers
const handleClick = () => {
  emit('click', props.channel)
}

const handleContextMenu = (event: MouseEvent) => {
  if (!props.contextMenuItems?.length) return

  event.preventDefault()
  contextMenuPosition.value = { x: event.clientX, y: event.clientY }
  contextMenuOpen.value = true
}

const handleContextAction = (item: ContextMenuItem) => {
  if (item.disabled) return
  if (item.action) {
    emit('contextAction', item.action, props.channel)
  }
  contextMenuOpen.value = false
}
</script>

<style scoped>
/* Bounce animation for typing dots */
@keyframes typing-bounce {
  0%, 60%, 100% {
    transform: translateY(0);
  }
  30% {
    transform: translateY(-3px);
  }
}

.animate-bounce {
  animation: typing-bounce 1s ease-in-out infinite;
}
</style>
