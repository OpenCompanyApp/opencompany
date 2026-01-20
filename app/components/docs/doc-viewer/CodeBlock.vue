<template>
  <div
    :class="containerClasses"
    @mouseenter="isHovered = true"
    @mouseleave="isHovered = false"
  >
    <!-- Header -->
    <div :class="headerClasses">
      <!-- Left side: Language/filename -->
      <div class="flex items-center gap-2 min-w-0">
        <!-- Language icon -->
        <div
          v-if="showLanguageIcon"
          :class="[
            'flex items-center justify-center rounded shrink-0',
            languageIconContainerClasses,
          ]"
        >
          <Icon
            :name="languageIcon"
            :class="languageIconClasses"
          />
        </div>

        <!-- Filename (if provided) -->
        <TooltipProvider v-if="filename" :delay-duration="300">
          <Tooltip>
            <TooltipTrigger as-child>
              <span :class="filenameClasses">
                {{ filename }}
              </span>
            </TooltipTrigger>
            <TooltipContent side="bottom" :side-offset="4">
              <p class="text-xs">{{ fullFilename }}</p>
            </TooltipContent>
          </Tooltip>
        </TooltipProvider>

        <!-- Language label -->
        <span v-else :class="languageLabelClasses">
          {{ displayLanguage }}
        </span>

        <!-- Badges -->
        <div v-if="hasBadges" class="flex items-center gap-1.5 ml-2">
          <span
            v-if="isModified"
            class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-500/10 text-amber-400"
          >
            Modified
          </span>
          <span
            v-if="isReadOnly"
            class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-olympus-surface text-olympus-text-muted"
          >
            Read-only
          </span>
        </div>
      </div>

      <!-- Right side: Actions -->
      <div :class="['flex items-center gap-1', actionsVisibilityClasses]">
        <!-- Line numbers toggle -->
        <TooltipProvider v-if="showLineNumbersToggle" :delay-duration="300">
          <Tooltip>
            <TooltipTrigger as-child>
              <button
                type="button"
                :class="actionButtonClasses"
                :aria-pressed="showLineNumbers"
                @click="toggleLineNumbers"
              >
                <Icon
                  name="ph:list-numbers"
                  :class="[
                    actionIconClasses,
                    showLineNumbers ? 'text-olympus-primary' : 'text-olympus-text-muted'
                  ]"
                />
              </button>
            </TooltipTrigger>
            <TooltipContent side="bottom" :side-offset="4">
              <p class="text-xs">{{ showLineNumbers ? 'Hide' : 'Show' }} line numbers</p>
            </TooltipContent>
          </Tooltip>
        </TooltipProvider>

        <!-- Word wrap toggle -->
        <TooltipProvider v-if="showWordWrapToggle" :delay-duration="300">
          <Tooltip>
            <TooltipTrigger as-child>
              <button
                type="button"
                :class="actionButtonClasses"
                :aria-pressed="wordWrap"
                @click="toggleWordWrap"
              >
                <Icon
                  name="ph:text-align-left"
                  :class="[
                    actionIconClasses,
                    wordWrap ? 'text-olympus-primary' : 'text-olympus-text-muted'
                  ]"
                />
              </button>
            </TooltipTrigger>
            <TooltipContent side="bottom" :side-offset="4">
              <p class="text-xs">{{ wordWrap ? 'Disable' : 'Enable' }} word wrap</p>
            </TooltipContent>
          </Tooltip>
        </TooltipProvider>

        <!-- Expand/collapse (for collapsible blocks) -->
        <TooltipProvider v-if="collapsible" :delay-duration="300">
          <Tooltip>
            <TooltipTrigger as-child>
              <button
                type="button"
                :class="actionButtonClasses"
                :aria-expanded="!isCollapsed"
                @click="toggleCollapse"
              >
                <Icon
                  :name="isCollapsed ? 'ph:caret-down' : 'ph:caret-up'"
                  :class="actionIconClasses"
                />
              </button>
            </TooltipTrigger>
            <TooltipContent side="bottom" :side-offset="4">
              <p class="text-xs">{{ isCollapsed ? 'Expand' : 'Collapse' }}</p>
            </TooltipContent>
          </Tooltip>
        </TooltipProvider>

        <!-- Separator -->
        <div v-if="showLineNumbersToggle || showWordWrapToggle || collapsible" class="w-px h-4 bg-olympus-border mx-1" />

        <!-- Copy button -->
        <TooltipProvider :delay-duration="300">
          <Tooltip>
            <TooltipTrigger as-child>
              <button
                type="button"
                :class="[
                  actionButtonClasses,
                  copied && 'bg-green-500/10',
                ]"
                @click="copyCode"
              >
                <Transition name="icon" mode="out-in">
                  <Icon
                    v-if="copied"
                    key="check"
                    name="ph:check"
                    class="text-green-400"
                    :class="actionIconClasses"
                  />
                  <Icon
                    v-else
                    key="copy"
                    name="ph:copy"
                    :class="['text-olympus-text-muted', actionIconClasses]"
                  />
                </Transition>
              </button>
            </TooltipTrigger>
            <TooltipContent side="bottom" :side-offset="4">
              <p class="text-xs">{{ copied ? 'Copied!' : 'Copy code' }}</p>
            </TooltipContent>
          </Tooltip>
        </TooltipProvider>

        <!-- Download button -->
        <TooltipProvider v-if="showDownload" :delay-duration="300">
          <Tooltip>
            <TooltipTrigger as-child>
              <button
                type="button"
                :class="actionButtonClasses"
                @click="downloadCode"
              >
                <Icon
                  name="ph:download-simple"
                  :class="['text-olympus-text-muted', actionIconClasses]"
                />
              </button>
            </TooltipTrigger>
            <TooltipContent side="bottom" :side-offset="4">
              <p class="text-xs">Download</p>
            </TooltipContent>
          </Tooltip>
        </TooltipProvider>

        <!-- Run button (for runnable code) -->
        <TooltipProvider v-if="runnable" :delay-duration="300">
          <Tooltip>
            <TooltipTrigger as-child>
              <button
                type="button"
                :class="[
                  actionButtonClasses,
                  'bg-green-500/10 hover:bg-green-500/20',
                ]"
                :disabled="isRunning"
                @click="$emit('run', code)"
              >
                <Icon
                  :name="isRunning ? 'ph:spinner' : 'ph:play-fill'"
                  :class="[
                    'text-green-400',
                    actionIconClasses,
                    isRunning && 'animate-spin',
                  ]"
                />
              </button>
            </TooltipTrigger>
            <TooltipContent side="bottom" :side-offset="4">
              <p class="text-xs">{{ isRunning ? 'Running...' : 'Run code' }}</p>
            </TooltipContent>
          </Tooltip>
        </TooltipProvider>
      </div>
    </div>

    <!-- Code content -->
    <CollapsibleRoot v-model:open="isExpanded">
      <CollapsibleContent>
        <div :class="codeContainerClasses">
          <!-- Line numbers gutter -->
          <div
            v-if="showLineNumbers"
            :class="lineNumbersClasses"
            aria-hidden="true"
          >
            <span
              v-for="lineNum in lineCount"
              :key="lineNum"
              :class="[
                'block text-right select-none',
                highlightLines?.includes(lineNum) && 'text-olympus-primary font-medium',
              ]"
            >
              {{ lineNum }}
            </span>
          </div>

          <!-- Code -->
          <pre
            ref="codeRef"
            :class="preClasses"
          ><code :class="codeClasses"><template v-for="(line, index) in codeLines" :key="index"><span
              :class="[
                'block',
                highlightLines?.includes(index + 1) && 'bg-olympus-primary/10 -mx-4 px-4 border-l-2 border-olympus-primary',
                diffAdditions?.includes(index + 1) && 'bg-green-500/10 -mx-4 px-4 border-l-2 border-green-400',
                diffDeletions?.includes(index + 1) && 'bg-red-500/10 -mx-4 px-4 border-l-2 border-red-400 line-through opacity-70',
              ]"
            >{{ line || ' ' }}</span></template></code></pre>
        </div>
      </CollapsibleContent>

      <!-- Collapsed preview -->
      <Transition name="collapsed">
        <div
          v-if="collapsible && isCollapsed"
          class="px-4 py-3 text-olympus-text-muted text-sm flex items-center justify-between"
        >
          <span>
            {{ lineCount }} lines
            <span v-if="language" class="text-olympus-text-subtle">
              &middot; {{ displayLanguage }}
            </span>
          </span>
          <button
            type="button"
            class="text-olympus-primary hover:underline text-sm"
            @click="toggleCollapse"
          >
            Show code
          </button>
        </div>
      </Transition>
    </CollapsibleRoot>

    <!-- Footer (optional) -->
    <Transition name="footer">
      <div
        v-if="showFooter && (characterCount || executionTime !== undefined || output)"
        :class="footerClasses"
      >
        <div class="flex items-center gap-3 text-xs text-olympus-text-subtle">
          <span v-if="characterCount">
            {{ formatNumber(characterCount) }} chars
          </span>
          <span v-if="executionTime !== undefined" class="flex items-center gap-1">
            <Icon name="ph:timer" class="w-3 h-3" />
            {{ executionTime }}ms
          </span>
        </div>

        <!-- Execution output -->
        <Transition name="output">
          <div v-if="output" class="mt-2 pt-2 border-t border-olympus-border">
            <div class="flex items-center gap-1.5 mb-1.5">
              <Icon name="ph:terminal" class="w-3.5 h-3.5 text-olympus-text-muted" />
              <span class="text-xs text-olympus-text-muted font-medium">Output</span>
            </div>
            <pre class="text-sm text-olympus-text font-mono whitespace-pre-wrap">{{ output }}</pre>
          </div>
        </Transition>

        <!-- Error output -->
        <Transition name="output">
          <div v-if="error" class="mt-2 pt-2 border-t border-red-500/30">
            <div class="flex items-center gap-1.5 mb-1.5">
              <Icon name="ph:warning-circle" class="w-3.5 h-3.5 text-red-400" />
              <span class="text-xs text-red-400 font-medium">Error</span>
            </div>
            <pre class="text-sm text-red-400 font-mono whitespace-pre-wrap">{{ error }}</pre>
          </div>
        </Transition>
      </div>
    </Transition>
  </div>
</template>

<script setup lang="ts">
import {
  CollapsibleContent,
  CollapsibleRoot,
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from 'reka-ui'

// ============================================================================
// Types
// ============================================================================

type CodeBlockSize = 'sm' | 'md' | 'lg'

// ============================================================================
// Language Configuration
// ============================================================================

const languageConfig: Record<string, {
  icon: string
  color: string
  label: string
}> = {
  javascript: { icon: 'ph:file-js', color: 'text-yellow-400', label: 'JavaScript' },
  js: { icon: 'ph:file-js', color: 'text-yellow-400', label: 'JavaScript' },
  typescript: { icon: 'ph:file-ts', color: 'text-blue-400', label: 'TypeScript' },
  ts: { icon: 'ph:file-ts', color: 'text-blue-400', label: 'TypeScript' },
  python: { icon: 'ph:file-py', color: 'text-green-400', label: 'Python' },
  py: { icon: 'ph:file-py', color: 'text-green-400', label: 'Python' },
  html: { icon: 'ph:file-html', color: 'text-orange-400', label: 'HTML' },
  css: { icon: 'ph:file-css', color: 'text-blue-400', label: 'CSS' },
  scss: { icon: 'ph:file-css', color: 'text-pink-400', label: 'SCSS' },
  json: { icon: 'ph:brackets-curly', color: 'text-amber-400', label: 'JSON' },
  yaml: { icon: 'ph:file-text', color: 'text-red-400', label: 'YAML' },
  yml: { icon: 'ph:file-text', color: 'text-red-400', label: 'YAML' },
  markdown: { icon: 'ph:markdown-logo', color: 'text-olympus-text-muted', label: 'Markdown' },
  md: { icon: 'ph:markdown-logo', color: 'text-olympus-text-muted', label: 'Markdown' },
  shell: { icon: 'ph:terminal', color: 'text-green-400', label: 'Shell' },
  bash: { icon: 'ph:terminal', color: 'text-green-400', label: 'Bash' },
  zsh: { icon: 'ph:terminal', color: 'text-green-400', label: 'Zsh' },
  sql: { icon: 'ph:database', color: 'text-cyan-400', label: 'SQL' },
  rust: { icon: 'ph:gear', color: 'text-orange-400', label: 'Rust' },
  go: { icon: 'ph:code', color: 'text-cyan-400', label: 'Go' },
  java: { icon: 'ph:coffee', color: 'text-red-400', label: 'Java' },
  kotlin: { icon: 'ph:code', color: 'text-purple-400', label: 'Kotlin' },
  swift: { icon: 'ph:code', color: 'text-orange-400', label: 'Swift' },
  cpp: { icon: 'ph:code', color: 'text-blue-400', label: 'C++' },
  c: { icon: 'ph:code', color: 'text-blue-400', label: 'C' },
  ruby: { icon: 'ph:diamond', color: 'text-red-400', label: 'Ruby' },
  php: { icon: 'ph:code', color: 'text-purple-400', label: 'PHP' },
  vue: { icon: 'ph:file-vue', color: 'text-green-400', label: 'Vue' },
  react: { icon: 'ph:atom', color: 'text-cyan-400', label: 'React' },
  jsx: { icon: 'ph:atom', color: 'text-cyan-400', label: 'JSX' },
  tsx: { icon: 'ph:atom', color: 'text-blue-400', label: 'TSX' },
  graphql: { icon: 'ph:graph', color: 'text-pink-400', label: 'GraphQL' },
  dockerfile: { icon: 'ph:cube', color: 'text-blue-400', label: 'Dockerfile' },
  nginx: { icon: 'ph:gear', color: 'text-green-400', label: 'Nginx' },
  plaintext: { icon: 'ph:file-text', color: 'text-olympus-text-muted', label: 'Plain Text' },
  text: { icon: 'ph:file-text', color: 'text-olympus-text-muted', label: 'Plain Text' },
}

const sizeConfig: Record<CodeBlockSize, {
  headerPadding: string
  codePadding: string
  fontSize: string
  lineHeight: string
  iconSize: string
  actionButtonSize: string
}> = {
  sm: {
    headerPadding: 'px-3 py-1.5',
    codePadding: 'p-3',
    fontSize: 'text-xs',
    lineHeight: 'leading-5',
    iconSize: 'w-3 h-3',
    actionButtonSize: 'w-5 h-5',
  },
  md: {
    headerPadding: 'px-4 py-2',
    codePadding: 'p-4',
    fontSize: 'text-sm',
    lineHeight: 'leading-relaxed',
    iconSize: 'w-3.5 h-3.5',
    actionButtonSize: 'w-6 h-6',
  },
  lg: {
    headerPadding: 'px-5 py-2.5',
    codePadding: 'p-5',
    fontSize: 'text-base',
    lineHeight: 'leading-relaxed',
    iconSize: 'w-4 h-4',
    actionButtonSize: 'w-7 h-7',
  },
}

// ============================================================================
// Props & Emits
// ============================================================================

const props = withDefaults(defineProps<{
  code: string
  language?: string
  filename?: string
  size?: CodeBlockSize
  showLineNumbers?: boolean
  showLineNumbersToggle?: boolean
  showWordWrapToggle?: boolean
  showLanguageIcon?: boolean
  showDownload?: boolean
  showFooter?: boolean
  highlightLines?: number[]
  diffAdditions?: number[]
  diffDeletions?: number[]
  collapsible?: boolean
  defaultCollapsed?: boolean
  runnable?: boolean
  isRunning?: boolean
  output?: string
  error?: string
  executionTime?: number
  isModified?: boolean
  isReadOnly?: boolean
}>(), {
  language: 'plaintext',
  size: 'md',
  showLineNumbers: true,
  showLineNumbersToggle: false,
  showWordWrapToggle: false,
  showLanguageIcon: true,
  showDownload: false,
  showFooter: false,
  highlightLines: () => [],
  diffAdditions: () => [],
  diffDeletions: () => [],
  collapsible: false,
  defaultCollapsed: false,
  runnable: false,
  isRunning: false,
  isModified: false,
  isReadOnly: false,
})

const emit = defineEmits<{
  copy: [code: string]
  download: [code: string, filename: string]
  run: [code: string]
  'line-numbers-change': [show: boolean]
  'word-wrap-change': [wrap: boolean]
}>()

// ============================================================================
// State
// ============================================================================

const codeRef = ref<HTMLPreElement | null>(null)
const copied = ref(false)
const isHovered = ref(false)
const isExpanded = ref(!props.defaultCollapsed)
const wordWrap = ref(false)
const lineNumbersVisible = ref(props.showLineNumbers)

let copyTimeout: ReturnType<typeof setTimeout> | null = null

// ============================================================================
// Computed - Configuration
// ============================================================================

const config = computed(() => sizeConfig[props.size])
const langConfig = computed(() => languageConfig[props.language.toLowerCase()] || languageConfig.plaintext)

// ============================================================================
// Computed - Data
// ============================================================================

const codeLines = computed(() => props.code.split('\n'))
const lineCount = computed(() => codeLines.value.length)
const characterCount = computed(() => props.code.length)

const displayLanguage = computed(() => langConfig.value.label)
const languageIcon = computed(() => langConfig.value.icon)
const fullFilename = computed(() => props.filename || '')

const isCollapsed = computed(() => props.collapsible && !isExpanded.value)
const showLineNumbers = computed(() => lineNumbersVisible.value && !isCollapsed.value)

const hasBadges = computed(() => props.isModified || props.isReadOnly)

// ============================================================================
// Computed - Styling
// ============================================================================

const containerClasses = computed(() => [
  'relative group rounded-xl overflow-hidden border border-olympus-border bg-olympus-surface transition-all duration-200',
  isHovered.value && 'ring-1 ring-olympus-border/50',
])

const headerClasses = computed(() => [
  'flex items-center justify-between bg-olympus-elevated border-b border-olympus-border',
  config.value.headerPadding,
])

const languageIconContainerClasses = computed(() => [
  'w-5 h-5 bg-olympus-surface',
])

const languageIconClasses = computed(() => [
  langConfig.value.color,
  config.value.iconSize,
])

const filenameClasses = computed(() => [
  'text-olympus-text font-mono truncate max-w-[200px]',
  config.value.fontSize,
])

const languageLabelClasses = computed(() => [
  'text-olympus-text-muted font-mono',
  config.value.fontSize,
])

const actionsVisibilityClasses = computed(() => [
  'transition-opacity duration-150',
  isHovered.value || copied.value ? 'opacity-100' : 'opacity-0 group-hover:opacity-100',
])

const actionButtonClasses = computed(() => [
  'flex items-center justify-center rounded-md transition-colors',
  'hover:bg-olympus-surface focus-visible:ring-2 focus-visible:ring-olympus-primary/50 outline-none',
  config.value.actionButtonSize,
])

const actionIconClasses = computed(() => config.value.iconSize)

const codeContainerClasses = computed(() => [
  'flex overflow-x-auto',
  config.value.codePadding,
])

const lineNumbersClasses = computed(() => [
  'pr-4 mr-4 border-r border-olympus-border text-olympus-text-subtle font-mono shrink-0',
  config.value.fontSize,
  config.value.lineHeight,
])

const preClasses = computed(() => [
  'flex-1 overflow-x-auto',
  wordWrap.value && 'whitespace-pre-wrap break-words',
])

const codeClasses = computed(() => [
  'font-mono text-olympus-text',
  config.value.fontSize,
  config.value.lineHeight,
])

const footerClasses = computed(() => [
  'border-t border-olympus-border bg-olympus-elevated/50',
  config.value.headerPadding,
])

// ============================================================================
// Methods
// ============================================================================

const copyCode = async () => {
  try {
    await navigator.clipboard.writeText(props.code)
    copied.value = true
    emit('copy', props.code)

    if (copyTimeout) {
      clearTimeout(copyTimeout)
    }

    copyTimeout = setTimeout(() => {
      copied.value = false
    }, 2000)
  } catch {
    // Clipboard API not available
  }
}

const downloadCode = () => {
  const filename = props.filename || `code.${getFileExtension()}`
  const blob = new Blob([props.code], { type: 'text/plain' })
  const url = URL.createObjectURL(blob)

  const a = document.createElement('a')
  a.href = url
  a.download = filename
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  URL.revokeObjectURL(url)

  emit('download', props.code, filename)
}

const getFileExtension = (): string => {
  const extensionMap: Record<string, string> = {
    javascript: 'js',
    typescript: 'ts',
    python: 'py',
    ruby: 'rb',
    shell: 'sh',
    bash: 'sh',
    yaml: 'yml',
    markdown: 'md',
  }

  return extensionMap[props.language.toLowerCase()] || props.language.toLowerCase() || 'txt'
}

const toggleCollapse = () => {
  isExpanded.value = !isExpanded.value
}

const toggleLineNumbers = () => {
  lineNumbersVisible.value = !lineNumbersVisible.value
  emit('line-numbers-change', lineNumbersVisible.value)
}

const toggleWordWrap = () => {
  wordWrap.value = !wordWrap.value
  emit('word-wrap-change', wordWrap.value)
}

const formatNumber = (num: number): string => {
  return new Intl.NumberFormat('en-US').format(num)
}

// ============================================================================
// Lifecycle
// ============================================================================

onUnmounted(() => {
  if (copyTimeout) {
    clearTimeout(copyTimeout)
  }
})
</script>

<style scoped>
/* Icon transition */
.icon-enter-active,
.icon-leave-active {
  transition: all 0.15s ease;
}

.icon-enter-from,
.icon-leave-to {
  opacity: 0;
  transform: scale(0.8);
}

/* Collapsed preview transition */
.collapsed-enter-active,
.collapsed-leave-active {
  transition: all 0.2s ease;
}

.collapsed-enter-from,
.collapsed-leave-to {
  opacity: 0;
  height: 0;
}

/* Footer transition */
.footer-enter-active,
.footer-leave-active {
  transition: all 0.2s ease;
}

.footer-enter-from,
.footer-leave-to {
  opacity: 0;
  transform: translateY(8px);
}

/* Output transition */
.output-enter-active,
.output-leave-active {
  transition: all 0.2s ease;
}

.output-enter-from,
.output-leave-to {
  opacity: 0;
  max-height: 0;
}

/* Custom scrollbar for code blocks */
pre::-webkit-scrollbar {
  height: 6px;
}

pre::-webkit-scrollbar-track {
  background: transparent;
}

pre::-webkit-scrollbar-thumb {
  background: oklch(var(--olympus-border));
  border-radius: 3px;
}

pre::-webkit-scrollbar-thumb:hover {
  background: oklch(var(--olympus-text-subtle));
}

/* Selection styling */
code ::selection {
  background: oklch(var(--olympus-primary) / 0.3);
}

/* Highlight line animation */
@keyframes highlight-flash {
  0%, 100% {
    background-color: oklch(var(--olympus-primary) / 0.1);
  }
  50% {
    background-color: oklch(var(--olympus-primary) / 0.2);
  }
}

.highlight-new {
  animation: highlight-flash 0.5s ease-in-out 2;
}
</style>
