import type { AgentType, AgentStatus, PresenceStatus } from '@/types'

export type AvatarSize = 'xs' | 'sm' | 'md' | 'lg' | 'xl' | '2xl'
export type AvatarShape = 'circle' | 'square' | 'rounded'
export type AvatarVariant = 'filled' | 'soft' | 'outline'
export type StatusPosition = 'bottom-right' | 'bottom-left' | 'top-right' | 'top-left'
export type BadgePosition = 'bottom-right' | 'bottom-left' | 'top-right' | 'top-left'

// Container sizes
export const containerSizes: Record<AvatarSize, string> = {
  xs: 'w-6 h-6',
  sm: 'w-8 h-8',
  md: 'w-10 h-10',
  lg: 'w-12 h-12',
  xl: 'w-16 h-16',
  '2xl': 'w-20 h-20',
}

// Avatar sizes
export const avatarSizes: Record<AvatarSize, string> = {
  xs: 'w-6 h-6',
  sm: 'w-8 h-8',
  md: 'w-10 h-10',
  lg: 'w-12 h-12',
  xl: 'w-16 h-16',
  '2xl': 'w-20 h-20',
}

// Icon sizes
export const iconSizes: Record<AvatarSize, string> = {
  xs: 'w-3 h-3',
  sm: 'w-4 h-4',
  md: 'w-5 h-5',
  lg: 'w-6 h-6',
  xl: 'w-8 h-8',
  '2xl': 'w-10 h-10',
}

// Text sizes for initials
export const textSizes: Record<AvatarSize, string> = {
  xs: 'text-xs',
  sm: 'text-sm',
  md: 'text-base',
  lg: 'text-lg',
  xl: 'text-xl',
  '2xl': 'text-2xl',
}

// Ring sizes
export const ringSizes: Record<AvatarSize, string> = {
  xs: 'ring-1',
  sm: 'ring-2',
  md: 'ring-2',
  lg: 'ring-2',
  xl: 'ring-[3px]',
  '2xl': 'ring-[3px]',
}

// Status dot sizes
export const dotSizes: Record<AvatarSize, string> = {
  xs: 'w-1.5 h-1.5',
  sm: 'w-2 h-2',
  md: 'w-2.5 h-2.5',
  lg: 'w-3 h-3',
  xl: 'w-4 h-4',
  '2xl': 'w-5 h-5',
}

// Status dot border sizes
export const dotBorderSizes: Record<AvatarSize, string> = {
  xs: 'border',
  sm: 'border-[1.5px]',
  md: 'border-2',
  lg: 'border-2',
  xl: 'border-[3px]',
  '2xl': 'border-[3px]',
}

// Status dot positions
export const dotPositions: Record<StatusPosition, Record<AvatarSize, string>> = {
  'bottom-right': {
    xs: '-bottom-0.5 -right-0.5',
    sm: '-bottom-0.5 -right-0.5',
    md: 'bottom-0 right-0',
    lg: 'bottom-0 right-0',
    xl: 'bottom-0.5 right-0.5',
    '2xl': 'bottom-1 right-1',
  },
  'bottom-left': {
    xs: '-bottom-0.5 -left-0.5',
    sm: '-bottom-0.5 -left-0.5',
    md: 'bottom-0 left-0',
    lg: 'bottom-0 left-0',
    xl: 'bottom-0.5 left-0.5',
    '2xl': 'bottom-1 left-1',
  },
  'top-right': {
    xs: '-top-0.5 -right-0.5',
    sm: '-top-0.5 -right-0.5',
    md: 'top-0 right-0',
    lg: 'top-0 right-0',
    xl: 'top-0.5 right-0.5',
    '2xl': 'top-1 right-1',
  },
  'top-left': {
    xs: '-top-0.5 -left-0.5',
    sm: '-top-0.5 -left-0.5',
    md: 'top-0 left-0',
    lg: 'top-0 left-0',
    xl: 'top-0.5 left-0.5',
    '2xl': 'top-1 left-1',
  },
}

// Badge positions
export const badgePositions: Record<BadgePosition, Record<AvatarSize, string>> = {
  'bottom-right': {
    xs: '-bottom-1 -right-1',
    sm: '-bottom-1 -right-1',
    md: '-bottom-1 -right-1',
    lg: '-bottom-1.5 -right-1.5',
    xl: '-bottom-2 -right-2',
    '2xl': '-bottom-2.5 -right-2.5',
  },
  'bottom-left': {
    xs: '-bottom-1 -left-1',
    sm: '-bottom-1 -left-1',
    md: '-bottom-1 -left-1',
    lg: '-bottom-1.5 -left-1.5',
    xl: '-bottom-2 -left-2',
    '2xl': '-bottom-2.5 -left-2.5',
  },
  'top-right': {
    xs: '-top-1 -right-1',
    sm: '-top-1 -right-1',
    md: '-top-1 -right-1',
    lg: '-top-1.5 -right-1.5',
    xl: '-top-2 -right-2',
    '2xl': '-top-2.5 -right-2.5',
  },
  'top-left': {
    xs: '-top-1 -left-1',
    sm: '-top-1 -left-1',
    md: '-top-1 -left-1',
    lg: '-top-1.5 -left-1.5',
    xl: '-top-2 -left-2',
    '2xl': '-top-2.5 -left-2.5',
  },
}

// Badge sizes
export const badgeSizes: Record<AvatarSize, string> = {
  xs: 'w-3 h-3 text-[8px]',
  sm: 'w-4 h-4 text-[9px]',
  md: 'w-5 h-5 text-[10px]',
  lg: 'w-6 h-6 text-xs',
  xl: 'w-7 h-7 text-xs',
  '2xl': 'w-8 h-8 text-sm',
}

// Shape classes
export const shapeClasses: Record<AvatarShape, string> = {
  circle: 'rounded-full',
  square: 'rounded-none',
  rounded: 'rounded-lg',
}

// Agent icons
export const agentIcons: Record<AgentType, string> = {
  manager: 'ph:crown-fill',
  writer: 'ph:pencil-fill',
  analyst: 'ph:chart-line-fill',
  creative: 'ph:palette-fill',
  researcher: 'ph:magnifying-glass-fill',
  coder: 'ph:terminal-fill',
  coordinator: 'ph:calendar-fill',
}

// Agent background colors - all neutral gray
export const agentBgColors: Record<AgentType, string> = {
  manager: 'bg-neutral-600',
  writer: 'bg-neutral-600',
  analyst: 'bg-neutral-600',
  creative: 'bg-neutral-600',
  researcher: 'bg-neutral-600',
  coder: 'bg-neutral-600',
  coordinator: 'bg-neutral-600',
}

// Agent background colors for soft variant - all neutral
export const agentSoftBgColors: Record<AgentType, string> = {
  manager: 'bg-neutral-100',
  writer: 'bg-neutral-100',
  analyst: 'bg-neutral-100',
  creative: 'bg-neutral-100',
  researcher: 'bg-neutral-100',
  coder: 'bg-neutral-100',
  coordinator: 'bg-neutral-100',
}

// Agent text colors for soft variant - all neutral
export const agentSoftTextColors: Record<AgentType, string> = {
  manager: 'text-neutral-600',
  writer: 'text-neutral-600',
  analyst: 'text-neutral-600',
  creative: 'text-neutral-600',
  researcher: 'text-neutral-600',
  coder: 'text-neutral-600',
  coordinator: 'text-neutral-600',
}

// Agent border colors for outline variant - all neutral
export const agentBorderColors: Record<AgentType, string> = {
  manager: 'border-neutral-400',
  writer: 'border-neutral-400',
  analyst: 'border-neutral-400',
  creative: 'border-neutral-400',
  researcher: 'border-neutral-400',
  coder: 'border-neutral-400',
  coordinator: 'border-neutral-400',
}

// Status colors
export const statusColors: Record<AgentStatus, string> = {
  idle: 'bg-neutral-400',
  working: 'bg-green-500',
  offline: 'bg-neutral-300',
  sleeping: 'bg-indigo-400',
  awaiting_approval: 'bg-amber-500',
  busy: 'bg-amber-500',
  paused: 'bg-neutral-400',
  online: 'bg-green-500',
}

// Presence colors - for all user types
export const presenceColors: Record<PresenceStatus, string> = {
  online: 'bg-green-500',
  away: 'bg-amber-400',
  busy: 'bg-red-500',
  offline: 'bg-neutral-300',
}

// Status glow colors - removed
export const statusGlowColors: Record<AgentStatus, string> = {
  idle: '',
  working: '',
  offline: '',
  sleeping: '',
  awaiting_approval: '',
  busy: '',
  paused: '',
  online: '',
}

// Human avatar colors - all neutral gray
export const humanColors: string[] = [
  'bg-neutral-500',
  'bg-neutral-600',
  'bg-neutral-500',
  'bg-neutral-600',
  'bg-neutral-500',
  'bg-neutral-600',
  'bg-neutral-500',
  'bg-neutral-600',
  'bg-neutral-500',
  'bg-neutral-600',
  'bg-neutral-500',
  'bg-neutral-600',
]

// Human soft colors - all neutral
export const humanSoftColors: string[] = [
  'bg-neutral-100',
  'bg-neutral-100',
  'bg-neutral-100',
  'bg-neutral-100',
  'bg-neutral-100',
  'bg-neutral-100',
  'bg-neutral-100',
  'bg-neutral-100',
  'bg-neutral-100',
  'bg-neutral-100',
  'bg-neutral-100',
  'bg-neutral-100',
]

// Human text colors for soft variant - all neutral
export const humanTextColors: string[] = [
  'text-neutral-600',
  'text-neutral-600',
  'text-neutral-600',
  'text-neutral-600',
  'text-neutral-600',
  'text-neutral-600',
  'text-neutral-600',
  'text-neutral-600',
  'text-neutral-600',
  'text-neutral-600',
  'text-neutral-600',
  'text-neutral-600',
]

// Get color index from name
export const getColorIndex = (name: string): number => {
  if (!name) return 0
  let hash = 0
  for (let i = 0; i < name.length; i++) {
    hash = name.charCodeAt(i) + ((hash << 5) - hash)
  }
  return Math.abs(hash) % humanColors.length
}

// Get initials from name
export const getInitials = (name: string, maxChars: number = 2): string => {
  if (!name) return '?'
  const parts = name.trim().split(/\s+/)
  if (parts.length === 1) {
    return parts[0].substring(0, maxChars).toUpperCase()
  }
  return parts
    .slice(0, maxChars)
    .map(part => part.charAt(0))
    .join('')
    .toUpperCase()
}

// Presence indicators - neutral colors
export const presenceIndicators = {
  typing: {
    icon: 'ph:chat-dots',
    animation: '',
    bgColor: 'bg-neutral-100',
    iconColor: 'text-neutral-600',
  },
  editing: {
    icon: 'ph:pencil-simple',
    animation: '',
    bgColor: 'bg-neutral-100',
    iconColor: 'text-neutral-600',
  },
  viewing: {
    icon: 'ph:eye',
    animation: '',
    bgColor: 'bg-neutral-100',
    iconColor: 'text-neutral-600',
  },
}
