import type { AgentType, AgentStatus } from '~/types'

export type AvatarSize = 'xs' | 'sm' | 'md' | 'lg' | 'xl' | '2xl'
export type AvatarShape = 'circle' | 'square' | 'rounded'
export type AvatarVariant = 'filled' | 'soft' | 'outline' | 'gradient'
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

// Agent background colors for filled variant
export const agentBgColors: Record<AgentType, string> = {
  manager: 'bg-amber-600',
  writer: 'bg-blue-600',
  analyst: 'bg-indigo-600',
  creative: 'bg-pink-600',
  researcher: 'bg-emerald-600',
  coder: 'bg-cyan-600',
  coordinator: 'bg-orange-600',
}

// Agent background colors for soft variant
export const agentSoftBgColors: Record<AgentType, string> = {
  manager: 'bg-amber-500/20',
  writer: 'bg-blue-500/20',
  analyst: 'bg-indigo-500/20',
  creative: 'bg-pink-500/20',
  researcher: 'bg-emerald-500/20',
  coder: 'bg-cyan-500/20',
  coordinator: 'bg-orange-500/20',
}

// Agent text colors for soft variant
export const agentSoftTextColors: Record<AgentType, string> = {
  manager: 'text-amber-400',
  writer: 'text-blue-400',
  analyst: 'text-indigo-400',
  creative: 'text-pink-400',
  researcher: 'text-emerald-400',
  coder: 'text-cyan-400',
  coordinator: 'text-orange-400',
}

// Agent border colors for outline variant
export const agentBorderColors: Record<AgentType, string> = {
  manager: 'border-amber-500',
  writer: 'border-blue-500',
  analyst: 'border-indigo-500',
  creative: 'border-pink-500',
  researcher: 'border-emerald-500',
  coder: 'border-cyan-500',
  coordinator: 'border-orange-500',
}

// Agent gradient backgrounds
export const agentGradients: Record<AgentType, string> = {
  manager: 'bg-gradient-to-br from-amber-500 to-orange-600',
  writer: 'bg-gradient-to-br from-blue-500 to-indigo-600',
  analyst: 'bg-gradient-to-br from-indigo-500 to-purple-600',
  creative: 'bg-gradient-to-br from-pink-500 to-rose-600',
  researcher: 'bg-gradient-to-br from-emerald-500 to-teal-600',
  coder: 'bg-gradient-to-br from-cyan-500 to-blue-600',
  coordinator: 'bg-gradient-to-br from-orange-500 to-red-600',
}

// Agent glow colors
export const agentGlowColors: Record<AgentType, string> = {
  manager: 'shadow-amber-500/40',
  writer: 'shadow-blue-500/40',
  analyst: 'shadow-indigo-500/40',
  creative: 'shadow-pink-500/40',
  researcher: 'shadow-emerald-500/40',
  coder: 'shadow-cyan-500/40',
  coordinator: 'shadow-orange-500/40',
}

// Status colors
export const statusColors: Record<AgentStatus, string> = {
  idle: 'bg-gray-400',
  working: 'bg-green-500',
  offline: 'bg-gray-600',
}

// Status glow colors (for working status)
export const statusGlowColors: Record<AgentStatus, string> = {
  idle: '',
  working: 'shadow-sm shadow-green-500/50',
  offline: '',
}

// Human avatar colors (based on name hash)
export const humanColors: string[] = [
  'bg-olympus-primary',
  'bg-purple-600',
  'bg-pink-600',
  'bg-rose-600',
  'bg-indigo-600',
  'bg-blue-600',
  'bg-cyan-600',
  'bg-teal-600',
  'bg-emerald-600',
  'bg-green-600',
  'bg-amber-600',
  'bg-orange-600',
]

// Human soft colors
export const humanSoftColors: string[] = [
  'bg-olympus-primary/20',
  'bg-purple-500/20',
  'bg-pink-500/20',
  'bg-rose-500/20',
  'bg-indigo-500/20',
  'bg-blue-500/20',
  'bg-cyan-500/20',
  'bg-teal-500/20',
  'bg-emerald-500/20',
  'bg-green-500/20',
  'bg-amber-500/20',
  'bg-orange-500/20',
]

// Human text colors for soft variant
export const humanTextColors: string[] = [
  'text-olympus-primary',
  'text-purple-400',
  'text-pink-400',
  'text-rose-400',
  'text-indigo-400',
  'text-blue-400',
  'text-cyan-400',
  'text-teal-400',
  'text-emerald-400',
  'text-green-400',
  'text-amber-400',
  'text-orange-400',
]

// Get color index from name
export const getColorIndex = (name: string): number => {
  let hash = 0
  for (let i = 0; i < name.length; i++) {
    hash = name.charCodeAt(i) + ((hash << 5) - hash)
  }
  return Math.abs(hash) % humanColors.length
}

// Get initials from name
export const getInitials = (name: string, maxChars: number = 2): string => {
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

// Presence indicators for "is typing" etc.
export const presenceIndicators = {
  typing: {
    icon: 'ph:chat-dots',
    animation: 'animate-pulse',
    bgColor: 'bg-olympus-primary/20',
    iconColor: 'text-olympus-primary',
  },
  editing: {
    icon: 'ph:pencil-simple',
    animation: 'animate-pulse',
    bgColor: 'bg-amber-500/20',
    iconColor: 'text-amber-400',
  },
  viewing: {
    icon: 'ph:eye',
    animation: '',
    bgColor: 'bg-blue-500/20',
    iconColor: 'text-blue-400',
  },
}
