import type { AgentType, AgentStatus } from '@/types'

// --- Node colors (hex values for PixiJS) ---
export const NODE_COLORS: Record<string, number> = {
  manager:     0x3b82f6,
  coder:       0xa855f7,
  writer:      0x22c55e,
  analyst:     0xf97316,
  creative:    0xec4899,
  researcher:  0xeab308,
  coordinator: 0x06b6d4,
  human:       0xf59e0b,
  default:     0x6b7280,
}

// --- Node radii ---
export const NODE_RADIUS = {
  human: 38,
  manager: 28,
  default: 22,
} as const

// --- Status opacity multipliers ---
export const STATUS_OPACITY: Record<AgentStatus, number> = {
  working:             1.0,
  idle:                0.7,
  online:              0.8,
  busy:                0.85,
  awaiting_approval:   0.75,
  awaiting_delegation: 0.75,
  sleeping:            0.4,
  paused:              0.5,
  offline:             0.25,
}

// --- Status pulse (whether node should pulse) ---
export const STATUS_PULSE: Record<AgentStatus, boolean> = {
  working: true,
  idle: false,
  online: false,
  busy: false,
  awaiting_approval: true,
  awaiting_delegation: true,
  sleeping: false,
  paused: false,
  offline: false,
}

// --- Glow config ---
export const GLOW = {
  layers: 4,
  maxAlpha: 0.25,
  radiusMultiplier: 2.2,
  workingBoost: 1.5,
} as const

// --- Theme-dependent values ---
export interface ConstellationTheme {
  bg: number
  edgeColor: number
  edgeGlowColor: number
  edgeAlpha: number
  starColor: number
  starMinAlpha: number
  starMaxAlpha: number
  nameColor: string
  roleColor: string
  modelBgColor: number
  modelTextColor: string
  modelBgAlpha: number
  ringColor: number
  ringAlpha: number
}

export const THEME_DARK: ConstellationTheme = {
  bg: 0x09090b,
  edgeColor: 0x334155,
  edgeGlowColor: 0x475569,
  edgeAlpha: 0.35,
  starColor: 0x94a3b8,
  starMinAlpha: 0.15,
  starMaxAlpha: 0.5,
  nameColor: '#e2e8f0',
  roleColor: '#94a3b8',
  modelBgColor: 0x1e293b,
  modelTextColor: '#cbd5e1',
  modelBgAlpha: 0.8,
  ringColor: 0xffffff,
  ringAlpha: 0.3,
}

export const THEME_LIGHT: ConstellationTheme = {
  bg: 0xf5f5f5,
  edgeColor: 0xd4d4d8,
  edgeGlowColor: 0xa1a1aa,
  edgeAlpha: 0.4,
  starColor: 0xd4d4d8,
  starMinAlpha: 0.2,
  starMaxAlpha: 0.5,
  nameColor: '#18181b',
  roleColor: '#71717a',
  modelBgColor: 0xe4e4e7,
  modelTextColor: '#52525b',
  modelBgAlpha: 0.9,
  ringColor: 0xffffff,
  ringAlpha: 0.5,
}

// --- Edge config ---
export const EDGE = {
  alpha: 0.35,
  width: 1.5,
  glowWidth: 4,
  glowAlpha: 0.08,
} as const

// --- Particle config ---
export const PARTICLE = {
  count: 3,
  radius: 1.8,
  speed: 0.4,
  alpha: 0.8,
} as const

// --- Starfield ---
export const STARFIELD = {
  count: 200,
  minRadius: 0.3,
  maxRadius: 1.2,
} as const

// --- Label config ---
export const LABEL = {
  fontFamily: 'Lexend Variable, system-ui, sans-serif',
  nameSize: 12,
  modelSize: 9,
  yOffset: 8,
} as const

// --- d3-force simulation params ---
export const FORCE = {
  chargeStrength: -600,
  linkDistance: 140,
  linkStrength: 0.7,
  centerStrength: 0.05,
  collisionRadius: 60,
  alphaDecay: 0.02,
  velocityDecay: 0.35,
} as const

// --- Get theme by mode ---
export function getTheme(dark: boolean): ConstellationTheme {
  return dark ? THEME_DARK : THEME_LIGHT
}

// --- Brain label shortener ---
export function shortenBrain(brain: string | undefined | null): string {
  if (!brain) return ''
  const parts = brain.split(':')
  const modelId = parts.length > 1 ? parts[1] : parts[0]

  const shortcuts: [RegExp, string][] = [
    [/claude-opus-4/i,             'Opus 4'],
    [/claude-sonnet-4-5/i,         'Sonnet 4.5'],
    [/claude-sonnet-4/i,           'Sonnet 4'],
    [/claude-haiku-3-5/i,          'Haiku 3.5'],
    [/claude-3-5-sonnet/i,         'Sonnet 3.5'],
    [/claude-3-opus/i,             'Opus 3'],
    [/gpt-4o/i,                    'GPT-4o'],
    [/gpt-4-turbo/i,               'GPT-4 Turbo'],
    [/gpt-4/i,                     'GPT-4'],
    [/gpt-3\.5/i,                  'GPT-3.5'],
    [/glm-4\.7/i,                  'GLM 4.7'],
    [/glm-4/i,                     'GLM 4'],
    [/gemini-2/i,                  'Gemini 2'],
    [/gemini-1\.5-pro/i,           'Gemini 1.5 Pro'],
    [/gemini-1\.5-flash/i,         'Gemini 1.5 Flash'],
    [/deepseek-r1/i,               'DeepSeek R1'],
    [/deepseek-v3/i,               'DeepSeek V3'],
    [/llama-3/i,                   'Llama 3'],
    [/qwen/i,                      'Qwen'],
    [/mistral/i,                   'Mistral'],
  ]

  for (const [pattern, label] of shortcuts) {
    if (pattern.test(modelId)) return label
  }

  return modelId
    .replace(/-\d{8,}$/, '')
    .replace(/-/g, ' ')
    .replace(/\b\w/g, c => c.toUpperCase())
    .substring(0, 20)
}

// --- Unicode icons for canvas rendering ---
export const AGENT_TYPE_ICONS: Record<string, string> = {
  manager:     '\u2655',
  writer:      '\u270E',
  analyst:     '\u2191',
  creative:    '\u25C6',
  researcher:  '\u2315',
  coder:       '\u276F',
  coordinator: '\u25A3',
  human:       '\u2726',
}
