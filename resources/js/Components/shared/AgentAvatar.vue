<template>
  <Tooltip
    v-if="showTooltip && tooltipContent"
    :side="tooltipSide"
    :side-offset="tooltipOffset"
    :delay-duration="tooltipDelay"
  >
    <template #content>
      <div class="max-w-64">
        <div class="flex items-center gap-2">
          <span
            v-if="user.type === 'agent' && user.status"
            :class="[
              'w-2.5 h-2.5 rounded-full shrink-0 ring-2 ring-white dark:ring-neutral-900',
              statusColors[user.status],
            ]"
          />
          <span
            v-else-if="user.presence"
            :class="[
              'w-2.5 h-2.5 rounded-full shrink-0 ring-2 ring-white dark:ring-neutral-900',
              presenceColors[user.presence],
            ]"
          />
          <p class="font-semibold text-neutral-900 dark:text-white text-sm">{{ user.name }}</p>
        </div>
        <p v-if="user.type === 'agent' && user.agentType" class="text-neutral-500 dark:text-neutral-300 text-xs mt-1 capitalize flex items-center gap-1.5">
          <Icon :name="agentIcons[user.agentType || 'manager']" class="w-3 h-3" />
          {{ user.agentType }} Agent
        </p>
        <p v-if="user.type === 'agent' && user.currentTask" class="text-neutral-400 dark:text-neutral-400 text-xs mt-2 line-clamp-2 italic">
          "{{ user.currentTask }}"
        </p>
        <p v-if="user.type === 'human' && user.presence" class="text-neutral-500 dark:text-neutral-300 text-xs mt-1 capitalize">
          {{ user.presence }}
        </p>
        <p v-if="user.type === 'human' && customTooltip" class="text-neutral-500 dark:text-neutral-300 text-xs mt-1">
          {{ customTooltip }}
        </p>
      </div>
    </template>

    <component
      :is="interactive ? 'button' : 'div'"
      :type="interactive ? 'button' : undefined"
      class="relative outline-none transition-colors duration-150 group"
      :class="[
        containerSizes[size],
        interactive && 'cursor-pointer focus-visible:ring-1 focus-visible:ring-neutral-400 rounded-full',
      ]"
      @click="handleClick"
    >
      <AvatarContent />
    </component>
  </Tooltip>

  <component
    v-else
    :is="interactive ? 'button' : 'div'"
    :type="interactive ? 'button' : undefined"
    class="relative outline-none transition-colors duration-150 group"
    :class="[
      containerSizes[size],
      interactive && 'cursor-pointer focus-visible:ring-1 focus-visible:ring-neutral-400 rounded-full',
    ]"
    @click="handleClick"
  >
    <AvatarContent />
  </component>
</template>

<script setup lang="ts">
import { ref, computed, h } from 'vue'
import type { User } from '@/types'
import Icon from '@/Components/shared/Icon.vue'
import Tooltip from '@/Components/shared/Tooltip.vue'
import {
  containerSizes,
  avatarSizes,
  iconSizes,
  textSizes,
  ringSizes,
  dotSizes,
  dotBorderSizes,
  dotPositions,
  badgePositions,
  badgeSizes,
  shapeClasses,
  agentIcons,
  agentBgColors,
  agentSoftBgColors,
  agentSoftTextColors,
  agentBorderColors,
  statusColors,
  presenceColors,
  humanColors,
  humanSoftColors,
  humanTextColors,
  getColorIndex,
  getInitials,
  presenceIndicators,
  type AvatarSize,
  type AvatarShape,
  type AvatarVariant,
  type StatusPosition,
  type BadgePosition,
} from './agent-avatar.config'

type TooltipSide = 'top' | 'right' | 'bottom' | 'left'
type PresenceType = 'typing' | 'editing' | 'viewing'

const props = withDefaults(defineProps<{
  // Required
  user: User

  // Size & Shape
  size?: AvatarSize
  shape?: AvatarShape
  variant?: AvatarVariant

  // Status indicator
  showStatus?: boolean
  statusPosition?: StatusPosition

  // Visual effects
  ring?: boolean
  ringColor?: string
  glow?: boolean
  animate?: boolean
  pulse?: boolean

  // Badge
  badge?: number | string
  badgePosition?: BadgePosition
  badgeColor?: 'primary' | 'success' | 'warning' | 'danger' | 'info'

  // Presence indicator
  presence?: PresenceType

  // Image
  src?: string
  fallbackSrc?: string

  // Interactive
  interactive?: boolean
  disabled?: boolean

  // Tooltip
  showTooltip?: boolean
  tooltipSide?: TooltipSide
  tooltipOffset?: number
  tooltipDelay?: number
  customTooltip?: string

  // Loading
  loading?: boolean

  // Stacked (for avatar groups)
  stacked?: boolean
  stackIndex?: number
}>(), {
  size: 'md',
  shape: 'circle',
  variant: 'filled',
  showStatus: true,
  statusPosition: 'bottom-right',
  ring: false,
  glow: false,
  animate: false,
  pulse: false,
  badgePosition: 'top-right',
  badgeColor: 'primary',
  interactive: false,
  disabled: false,
  showTooltip: true,
  tooltipSide: 'top',
  tooltipOffset: 5,
  tooltipDelay: 300,
  loading: false,
  stacked: false,
  stackIndex: 0,
})

const emit = defineEmits<{
  click: [user: User]
}>()

// Color index for human users
const colorIndex = computed(() => getColorIndex(props.user?.name || ''))

// Initials for human users
const initials = computed(() => getInitials(props.user?.name || ''))

// Agent type helper
const agentType = computed(() => props.user.agentType || 'manager')

// Tooltip content
const tooltipContent = computed(() => {
  if (props.customTooltip) return true
  if (props.user.type === 'agent') return true
  return props.showTooltip
})

// Avatar background classes
const avatarBgClasses = computed(() => {
  const type = props.user.type
  const aType = agentType.value

  if (type === 'agent') {
    switch (props.variant) {
      case 'soft':
        return agentSoftBgColors[aType]
      case 'outline':
        return `bg-transparent border-2 ${agentBorderColors[aType]}`
      case 'filled':
      default:
        return agentBgColors[aType]
    }
  } else {
    // Human user
    switch (props.variant) {
      case 'soft':
        return humanSoftColors[colorIndex.value]
      case 'outline':
        return 'bg-transparent border-2 border-neutral-400'
      case 'filled':
      default:
        return humanColors[colorIndex.value]
    }
  }
})

// Avatar text/icon color classes
const avatarContentClasses = computed(() => {
  const type = props.user.type
  const aType = agentType.value

  if (type === 'agent') {
    if (props.variant === 'soft') {
      return agentSoftTextColors[aType]
    }
    if (props.variant === 'outline') {
      return agentSoftTextColors[aType]
    }
    return 'text-white'
  } else {
    if (props.variant === 'soft' || props.variant === 'outline') {
      return humanTextColors[colorIndex.value]
    }
    return 'text-white'
  }
})

// Ring classes
const ringClasses = computed(() => {
  if (!props.ring) return ''
  return `${ringSizes[props.size]} ${props.ringColor || 'ring-white'}`
})

// Stacked styles
const stackedStyles = computed(() => {
  if (!props.stacked) return {}
  return {
    marginLeft: props.stackIndex > 0 ? `-${props.size === 'xs' ? 8 : props.size === 'sm' ? 10 : 12}px` : '0',
    zIndex: 10 - props.stackIndex,
  }
})

// Badge color classes
const badgeColorClasses = computed(() => {
  const colors: Record<string, string> = {
    primary: 'bg-neutral-600',
    success: 'bg-green-600',
    warning: 'bg-amber-600',
    danger: 'bg-red-600',
    info: 'bg-blue-600',
  }
  return colors[props.badgeColor]
})

// Handle click
const handleClick = () => {
  if (props.interactive && !props.disabled) {
    emit('click', props.user)
  }
}

// Image error handling
const imageError = ref(false)
const handleImageError = () => {
  imageError.value = true
}

// Effective src — use explicit prop, fall back to user.avatar
const effectiveSrc = computed(() => props.src || props.user.avatar || undefined)

// Should show image
const showImage = computed(() => {
  return effectiveSrc.value && !imageError.value && !props.loading
})

// Icon class mapping for iconify
const agentIconClasses: Record<string, string> = {
  manager: 'i-ph:user-circle-gear',
  writer: 'i-ph:pencil-simple',
  analyst: 'i-ph:chart-line-up',
  creative: 'i-ph:paint-brush',
  researcher: 'i-ph:magnifying-glass',
  coder: 'i-ph:code',
  coordinator: 'i-ph:users-three',
}

const presenceIconClasses: Record<string, string> = {
  typing: 'i-ph:cursor-text',
  editing: 'i-ph:pencil-simple',
  viewing: 'i-ph:eye',
}

// Avatar Content Component
const AvatarContent = () => {
  return h('div', {
    class: [
      'relative',
      containerSizes[props.size],
    ],
    style: stackedStyles.value,
  }, [
    // Main avatar
    h('div', {
      class: [
        'flex items-center justify-center overflow-hidden',
        avatarSizes[props.size],
        shapeClasses[props.shape],
        avatarBgClasses.value,
        ringClasses.value,
        props.stacked && 'ring-2 ring-white',
        props.disabled && 'opacity-50',
      ],
    }, [
      // Loading state
      props.loading && h('span', {
        class: ['i-ph:spinner animate-spin', iconSizes[props.size], 'text-neutral-400 dark:text-neutral-400'],
      }),

      // Image
      showImage.value && h('img', {
        src: effectiveSrc.value,
        alt: props.user.name,
        class: 'w-full h-full object-cover',
        onError: handleImageError,
      }),

      // Fallback image
      !showImage.value && !props.loading && imageError.value && props.fallbackSrc && h('img', {
        src: props.fallbackSrc,
        alt: props.user.name,
        class: 'w-full h-full object-cover',
      }),

      // Agent icon
      !showImage.value && !props.loading && props.user.type === 'agent' && h('span', {
        class: [agentIconClasses[agentType.value] || 'i-ph:user-circle-gear', iconSizes[props.size], avatarContentClasses.value],
      }),

      // Human initials
      !showImage.value && !props.loading && props.user.type === 'human' && h('span', {
        class: ['font-semibold', textSizes[props.size], avatarContentClasses.value],
      }, initials.value),
    ]),

    // Status indicator - shows presence for all users, or agent status for agents
    props.showStatus && h('div', {
      class: [
        'absolute rounded-full border-white',
        dotSizes[props.size],
        dotBorderSizes[props.size],
        dotPositions[props.statusPosition][props.size],
        // For agents, show agent status (working/idle); for humans, show presence
        props.user.type === 'agent' && props.user.status
          ? statusColors[props.user.status]
          : props.user.presence
            ? presenceColors[props.user.presence]
            : 'bg-neutral-300 dark:bg-neutral-600', // Default to offline/unknown
      ],
    }),

    // Badge
    props.badge !== undefined && h('div', {
      class: [
        'absolute flex items-center justify-center rounded-full font-bold text-white',
        badgeSizes[props.size],
        badgePositions[props.badgePosition][props.size],
        badgeColorClasses.value,
      ],
    }, typeof props.badge === 'number' && props.badge > 99 ? '99+' : props.badge),

    // Presence indicator
    props.presence && h('div', {
      class: [
        'absolute flex items-center justify-center rounded-full',
        badgeSizes[props.size],
        badgePositions['bottom-left'][props.size],
        presenceIndicators[props.presence].bgColor,
        presenceIndicators[props.presence].animation,
      ],
    }, [
      h('span', {
        class: [presenceIconClasses[props.presence] || 'i-ph:cursor-text', 'w-2.5 h-2.5', presenceIndicators[props.presence].iconColor],
      }),
    ]),
  ])
}
</script>
