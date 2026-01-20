<template>
  <TooltipProvider v-if="showTooltip && tooltipContent" :delay-duration="tooltipDelay">
    <TooltipRoot>
      <TooltipTrigger as-child>
        <component
          :is="interactive ? 'button' : 'div'"
          :type="interactive ? 'button' : undefined"
          class="relative outline-none"
          :class="[
            containerSizes[size],
            interactive && 'cursor-pointer focus-visible:ring-2 focus-visible:ring-olympus-primary/50 focus-visible:ring-offset-2 focus-visible:ring-offset-olympus-bg rounded-full',
          ]"
          @click="handleClick"
        >
          <AvatarContent />
        </component>
      </TooltipTrigger>
      <TooltipPortal>
        <TooltipContent
          :side="tooltipSide"
          :side-offset="tooltipOffset"
          class="z-50 bg-olympus-elevated border border-olympus-border rounded-lg px-3 py-2 shadow-xl max-w-64 animate-in fade-in-0 zoom-in-95 duration-150"
        >
          <div class="flex items-center gap-2">
            <span
              v-if="user.type === 'agent' && user.status"
              :class="[
                'w-2 h-2 rounded-full shrink-0',
                statusColors[user.status],
              ]"
            />
            <p class="font-medium text-olympus-text text-sm">{{ user.name }}</p>
          </div>
          <p v-if="user.type === 'agent' && user.agentType" class="text-olympus-text-muted text-xs mt-0.5 capitalize">
            {{ user.agentType }} Agent
          </p>
          <p v-if="user.type === 'agent' && user.currentTask" class="text-olympus-text-muted text-xs mt-1 line-clamp-2">
            {{ user.currentTask }}
          </p>
          <p v-if="user.type === 'human' && customTooltip" class="text-olympus-text-muted text-xs mt-0.5">
            {{ customTooltip }}
          </p>
          <TooltipArrow class="fill-olympus-elevated" />
        </TooltipContent>
      </TooltipPortal>
    </TooltipRoot>
  </TooltipProvider>

  <component
    v-else
    :is="interactive ? 'button' : 'div'"
    :type="interactive ? 'button' : undefined"
    class="relative outline-none"
    :class="[
      containerSizes[size],
      interactive && 'cursor-pointer focus-visible:ring-2 focus-visible:ring-olympus-primary/50 focus-visible:ring-offset-2 focus-visible:ring-offset-olympus-bg rounded-full',
    ]"
    @click="handleClick"
  >
    <AvatarContent />
  </component>
</template>

<script setup lang="ts">
import {
  TooltipArrow,
  TooltipContent,
  TooltipPortal,
  TooltipProvider,
  TooltipRoot,
  TooltipTrigger,
} from 'reka-ui'
import type { User } from '~/types'
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
  agentGradients,
  agentGlowColors,
  statusColors,
  statusGlowColors,
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
const colorIndex = computed(() => getColorIndex(props.user.name))

// Initials for human users
const initials = computed(() => getInitials(props.user.name))

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
      case 'gradient':
        return agentGradients[aType]
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
        return 'bg-transparent border-2 border-olympus-primary'
      case 'gradient':
        return 'bg-gradient-to-br from-olympus-primary to-purple-600'
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
  return `${ringSizes[props.size]} ${props.ringColor || 'ring-olympus-bg'}`
})

// Glow classes
const glowClasses = computed(() => {
  if (!props.glow) return ''
  if (props.user.type === 'agent') {
    return `shadow-lg ${agentGlowColors[agentType.value]}`
  }
  return 'shadow-lg shadow-olympus-primary/30'
})

// Animation classes
const animationClasses = computed(() => {
  const classes: string[] = []
  if (props.animate && props.user.type === 'agent' && props.user.status === 'working') {
    classes.push('animate-pulse')
  }
  if (props.pulse) {
    classes.push('animate-pulse')
  }
  return classes.join(' ')
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
    primary: 'bg-olympus-primary',
    success: 'bg-green-500',
    warning: 'bg-amber-500',
    danger: 'bg-red-500',
    info: 'bg-blue-500',
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

// Should show image
const showImage = computed(() => {
  return props.src && !imageError.value && !props.loading
})

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
        'flex items-center justify-center overflow-hidden transition-all duration-200',
        avatarSizes[props.size],
        shapeClasses[props.shape],
        avatarBgClasses.value,
        ringClasses.value,
        glowClasses.value,
        animationClasses.value,
        props.stacked && 'ring-2 ring-olympus-bg',
        props.disabled && 'opacity-50',
      ],
    }, [
      // Loading state
      props.loading && h(resolveComponent('Icon'), {
        name: 'ph:spinner',
        class: ['animate-spin', iconSizes[props.size], 'text-olympus-text-muted'],
      }),

      // Image
      showImage.value && h('img', {
        src: props.src,
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
      !showImage.value && !props.loading && props.user.type === 'agent' && h(resolveComponent('Icon'), {
        name: agentIcons[agentType.value],
        class: [iconSizes[props.size], avatarContentClasses.value],
      }),

      // Human initials
      !showImage.value && !props.loading && props.user.type === 'human' && h('span', {
        class: ['font-semibold', textSizes[props.size], avatarContentClasses.value],
      }, initials.value),
    ]),

    // Status indicator
    props.showStatus && props.user.type === 'agent' && props.user.status && h('div', {
      class: [
        'absolute rounded-full border-olympus-bg',
        dotSizes[props.size],
        dotBorderSizes[props.size],
        dotPositions[props.statusPosition][props.size],
        statusColors[props.user.status],
        statusGlowColors[props.user.status],
        props.user.status === 'working' && 'animate-pulse',
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
      h(resolveComponent('Icon'), {
        name: presenceIndicators[props.presence].icon,
        class: ['w-2.5 h-2.5', presenceIndicators[props.presence].iconColor],
      }),
    ]),
  ])
}
</script>
