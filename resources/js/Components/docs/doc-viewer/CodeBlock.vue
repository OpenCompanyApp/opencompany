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
        <Tooltip v-if="filename" :text="fullFilename" :delay-open="300">
          <span :class="filenameClasses">
            {{ filename }}
          </span>
        </Tooltip>

        <!-- Language label -->
        <span v-else :class="languageLabelClasses">
          {{ displayLanguage }}
        </span>

        <!-- Badges -->
        <div v-if="hasBadges" class="flex items-center gap-1.5 ml-2">
          <span
            v-if="isModified"
            class="px-1.5 py-0.5 rounded-md text-[10px] font-medium bg-neutral-100 dark:bg-neutral-700 text-neutral-500 dark:text-neutral-400"
          >
            Modified
          </span>
          <span
            v-if="isReadOnly"
            class="px-1.5 py-0.5 rounded-md text-[10px] font-medium bg-neutral-100 dark:bg-neutral-700 text-neutral-500 dark:text-neutral-400"
          >
            Read-only
          </span>
        </div>
      </div>

      <!-- Right side: Actions -->
      <div :class="['flex items-center gap-1', actionsVisibilityClasses]">
        <!-- Line numbers toggle -->
        <Tooltip v-if="showLineNumbersToggle" :text="showLineNumbers ? 'Hide line numbers' : 'Show line numbers'" :delay-open="300">
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
                showLineNumbers ? 'text-neutral-900' : 'text-neutral-500'
              ]"
            />
          </button>
        </Tooltip>

        <!-- Word wrap toggle -->
        <Tooltip v-if="showWordWrapToggle" :text="wordWrap ? 'Disable word wrap' : 'Enable word wrap'" :delay-open="300">
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
                wordWrap ? 'text-neutral-900' : 'text-neutral-500'
              ]"
            />
          </button>
        </Tooltip>

        <!-- Expand/collapse (for collapsible blocks) -->
        <Tooltip v-if="collapsible" :text="isCollapsed ? 'Expand' : 'Collapse'" :delay-open="300">
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
        </Tooltip>

        <!-- Separator -->
        <div v-if="showLineNumbersToggle || showWordWrapToggle || collapsible" class="w-px h-4 bg-neutral-200 dark:bg-neutral-600 mx-1" />

        <!-- Copy button -->
        <Tooltip :text="copied ? 'Copied!' : 'Copy code'" :delay-open="300">
          <button
            type="button"
            :class="[
              actionButtonClasses,
              copied && 'bg-green-500/20 ring-1 ring-green-500/30',
            ]"
            @click="copyCode"
          >
            <Transition name="icon" mode="out-in">
              <Icon
                v-if="copied"
                key="check"
                name="ph:check-circle-fill"
                class="text-green-600"
                :class="actionIconClasses"
              />
              <Icon
                v-else
                key="copy"
                name="ph:copy"
                :class="['text-neutral-500 hover:text-neutral-700', actionIconClasses]"
              />
            </Transition>
          </button>
        </Tooltip>

        <!-- Download button -->
        <Tooltip v-if="showDownload" text="Download" :delay-open="300">
          <button
            type="button"
            :class="actionButtonClasses"
            @click="downloadCode"
          >
            <Icon
              name="ph:download-simple"
              :class="['text-neutral-500', actionIconClasses]"
            />
          </button>
        </Tooltip>

        <!-- Run button (for runnable code) -->
        <Tooltip v-if="runnable" :text="isRunning ? 'Running...' : 'Run code'" :delay-open="300">
          <button
            type="button"
            :class="[
              actionButtonClasses,
              'bg-neutral-100 hover:bg-neutral-200',
            ]"
            :disabled="isRunning"
            @click="$emit('run', code)"
          >
            <Icon
              :name="isRunning ? 'ph:spinner' : 'ph:play-fill'"
              :class="[
                'text-neutral-600',
                actionIconClasses,
                isRunning && 'animate-spin',
              ]"
            />
          </button>
        </Tooltip>
      </div>
    </div>

    <!-- Code content (expanded) -->
    <Transition
      enter-active-class="transition-all duration-200 ease-out"
      enter-from-class="opacity-0 max-h-0"
      enter-to-class="opacity-100 max-h-[2000px]"
      leave-active-class="transition-all duration-150 ease-in"
      leave-from-class="opacity-100 max-h-[2000px]"
      leave-to-class="opacity-0 max-h-0"
    >
      <div v-if="isExpanded">
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
                highlightLines?.includes(lineNum) && 'text-neutral-900 font-medium',
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
                highlightLines?.includes(index + 1) && 'bg-neutral-100 dark:bg-neutral-800 -mx-4 px-4 border-l-2 border-neutral-400 dark:border-neutral-500',
                diffAdditions?.includes(index + 1) && 'bg-green-50 dark:bg-green-900/30 -mx-4 px-4 border-l-2 border-green-500',
                diffDeletions?.includes(index + 1) && 'bg-red-50 dark:bg-red-900/30 -mx-4 px-4 border-l-2 border-red-500 line-through opacity-70',
              ]"
            >{{ line || ' ' }}</span></template></code></pre>
        </div>
      </div>
    </Transition>

    <!-- Collapsed preview -->
    <Transition name="collapsed">
      <div
        v-if="collapsible && isCollapsed"
        class="px-4 py-3 text-neutral-500 dark:text-neutral-400 text-sm flex items-center justify-between"
      >
        <span>
          {{ lineCount }} lines
          <span v-if="language" class="text-neutral-400 dark:text-neutral-500">
            &middot; {{ displayLanguage }}
          </span>
        </span>
        <button
          type="button"
          class="text-neutral-900 dark:text-white hover:underline text-sm px-2 py-1 -mr-2 rounded-lg transition-colors duration-150 ease-out hover:bg-neutral-100 dark:hover:bg-neutral-800"
          @click="toggleCollapse"
        >
          Show code
        </button>
      </div>
    </Transition>

    <!-- Footer (optional) -->
    <Transition name="footer">
      <div
        v-if="showFooter && (characterCount || executionTime !== undefined || output)"
        :class="footerClasses"
      >
        <div class="flex items-center gap-3 text-xs text-neutral-400">
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
          <div v-if="output" class="mt-2 pt-2 border-t border-neutral-200 dark:border-neutral-700">
            <div class="flex items-center gap-1.5 mb-1.5">
              <Icon name="ph:terminal" class="w-3.5 h-3.5 text-neutral-500 dark:text-neutral-400" />
              <span class="text-xs text-neutral-500 dark:text-neutral-400 font-medium">Output</span>
            </div>
            <pre class="text-sm text-neutral-900 dark:text-neutral-100 font-mono whitespace-pre-wrap">{{ output }}</pre>
          </div>
        </Transition>

        <!-- Error output -->
        <Transition name="output">
          <div v-if="error" class="mt-2 pt-2 border-t border-red-200 dark:border-red-800">
            <div class="flex items-center gap-1.5 mb-1.5">
              <Icon name="ph:warning-circle" class="w-3.5 h-3.5 text-red-600 dark:text-red-400" />
              <span class="text-xs text-red-600 dark:text-red-400 font-medium">Error</span>
            </div>
            <pre class="text-sm text-red-600 dark:text-red-400 font-mono whitespace-pre-wrap">{{ error }}</pre>
          </div>
        </Transition>
      </div>
    </Transition>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onUnmounted } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import Tooltip from '@/Components/shared/Tooltip.vue'

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
  javascript: { icon: 'ph:file-js', color: 'text-neutral-500', label: 'JavaScript' },
  js: { icon: 'ph:file-js', color: 'text-neutral-500', label: 'JavaScript' },
  typescript: { icon: 'ph:file-ts', color: 'text-neutral-500', label: 'TypeScript' },
  ts: { icon: 'ph:file-ts', color: 'text-neutral-500', label: 'TypeScript' },
  python: { icon: 'ph:file-py', color: 'text-neutral-500', label: 'Python' },
  py: { icon: 'ph:file-py', color: 'text-neutral-500', label: 'Python' },
  html: { icon: 'ph:file-html', color: 'text-neutral-500', label: 'HTML' },
  css: { icon: 'ph:file-css', color: 'text-neutral-500', label: 'CSS' },
  scss: { icon: 'ph:file-css', color: 'text-neutral-500', label: 'SCSS' },
  json: { icon: 'ph:brackets-curly', color: 'text-neutral-500', label: 'JSON' },
  yaml: { icon: 'ph:file-text', color: 'text-neutral-500', label: 'YAML' },
  yml: { icon: 'ph:file-text', color: 'text-neutral-500', label: 'YAML' },
  markdown: { icon: 'ph:markdown-logo', color: 'text-neutral-500', label: 'Markdown' },
  md: { icon: 'ph:markdown-logo', color: 'text-neutral-500', label: 'Markdown' },
  shell: { icon: 'ph:terminal', color: 'text-neutral-500', label: 'Shell' },
  bash: { icon: 'ph:terminal', color: 'text-neutral-500', label: 'Bash' },
  zsh: { icon: 'ph:terminal', color: 'text-neutral-500', label: 'Zsh' },
  sql: { icon: 'ph:database', color: 'text-neutral-500', label: 'SQL' },
  rust: { icon: 'ph:gear', color: 'text-neutral-500', label: 'Rust' },
  go: { icon: 'ph:code', color: 'text-neutral-500', label: 'Go' },
  java: { icon: 'ph:coffee', color: 'text-neutral-500', label: 'Java' },
  kotlin: { icon: 'ph:code', color: 'text-neutral-500', label: 'Kotlin' },
  swift: { icon: 'ph:code', color: 'text-neutral-500', label: 'Swift' },
  cpp: { icon: 'ph:code', color: 'text-neutral-500', label: 'C++' },
  c: { icon: 'ph:code', color: 'text-neutral-500', label: 'C' },
  ruby: { icon: 'ph:diamond', color: 'text-neutral-500', label: 'Ruby' },
  php: { icon: 'ph:code', color: 'text-neutral-500', label: 'PHP' },
  vue: { icon: 'ph:file-vue', color: 'text-neutral-500', label: 'Vue' },
  react: { icon: 'ph:atom', color: 'text-neutral-500', label: 'React' },
  jsx: { icon: 'ph:atom', color: 'text-neutral-500', label: 'JSX' },
  tsx: { icon: 'ph:atom', color: 'text-neutral-500', label: 'TSX' },
  graphql: { icon: 'ph:graph', color: 'text-neutral-500', label: 'GraphQL' },
  dockerfile: { icon: 'ph:cube', color: 'text-neutral-500', label: 'Dockerfile' },
  nginx: { icon: 'ph:gear', color: 'text-neutral-500', label: 'Nginx' },
  plaintext: { icon: 'ph:file-text', color: 'text-neutral-500', label: 'Plain Text' },
  text: { icon: 'ph:file-text', color: 'text-neutral-500', label: 'Plain Text' },
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
  'relative group rounded-lg overflow-hidden border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900',
  'transition-all duration-150 ease-out',
  isHovered.value && 'ring-1 ring-neutral-300 dark:ring-neutral-600 border-neutral-300 dark:border-neutral-600',
])

const headerClasses = computed(() => [
  'flex items-center justify-between bg-neutral-50 dark:bg-neutral-800 border-b border-neutral-200 dark:border-neutral-700',
  config.value.headerPadding,
])

const languageIconContainerClasses = computed(() => [
  'w-5 h-5 bg-neutral-100 dark:bg-neutral-700 transition-colors duration-150 ease-out',
])

const languageIconClasses = computed(() => [
  langConfig.value.color,
  config.value.iconSize,
])

const filenameClasses = computed(() => [
  'text-neutral-900 dark:text-neutral-100 font-mono truncate max-w-[200px]',
  config.value.fontSize,
])

const languageLabelClasses = computed(() => [
  'text-neutral-500 dark:text-neutral-400 font-mono',
  config.value.fontSize,
])

const actionsVisibilityClasses = computed(() => [
  'transition-opacity duration-150 ease-out',
  isHovered.value || copied.value ? 'opacity-100' : 'opacity-0 group-hover:opacity-100',
])

const actionButtonClasses = computed(() => [
  'flex items-center justify-center rounded-md outline-none',
  'transition-colors duration-150 ease-out',
  'hover:bg-neutral-100 dark:hover:bg-neutral-700',
  'focus-visible:ring-2 focus-visible:ring-neutral-900/50 dark:focus-visible:ring-neutral-500/50',
  config.value.actionButtonSize,
])

const actionIconClasses = computed(() => config.value.iconSize)

const codeContainerClasses = computed(() => [
  'flex overflow-x-auto',
  config.value.codePadding,
])

const lineNumbersClasses = computed(() => [
  'pr-4 mr-4 border-r border-neutral-200 dark:border-neutral-700 text-neutral-400 dark:text-neutral-500 font-mono shrink-0',
  config.value.fontSize,
  config.value.lineHeight,
])

const preClasses = computed(() => [
  'flex-1 overflow-x-auto',
  wordWrap.value && 'whitespace-pre-wrap break-words',
])

const codeClasses = computed(() => [
  'font-mono text-neutral-900 dark:text-neutral-100',
  config.value.fontSize,
  config.value.lineHeight,
])

const footerClasses = computed(() => [
  'border-t border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800',
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
.icon-enter-active {
  transition: opacity 0.15s ease-out;
}

.icon-leave-active {
  transition: opacity 0.1s ease-out;
}

.icon-enter-from,
.icon-leave-to {
  opacity: 0;
}

/* Collapsed preview transition */
.collapsed-enter-active {
  transition: opacity 0.15s ease-out;
}

.collapsed-leave-active {
  transition: opacity 0.1s ease-out;
}

.collapsed-enter-from,
.collapsed-leave-to {
  opacity: 0;
}

/* Footer transition */
.footer-enter-active {
  transition: opacity 0.15s ease-out;
}

.footer-leave-active {
  transition: opacity 0.1s ease-out;
}

.footer-enter-from,
.footer-leave-to {
  opacity: 0;
}

/* Output transition */
.output-enter-active {
  transition: opacity 0.15s ease-out;
}

.output-leave-active {
  transition: opacity 0.1s ease-out;
}

.output-enter-from,
.output-leave-to {
  opacity: 0;
}

/* Custom scrollbar for code blocks */
pre::-webkit-scrollbar {
  height: 6px;
}

pre::-webkit-scrollbar-track {
  background: transparent;
}

pre::-webkit-scrollbar-thumb {
  background: rgb(209, 213, 219);
  border-radius: 3px;
}

pre::-webkit-scrollbar-thumb:hover {
  background: rgb(156, 163, 175);
}

/* Selection styling */
code ::selection {
  background: rgba(17, 24, 39, 0.15);
}
</style>
