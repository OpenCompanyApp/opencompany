<template>
  <article
    ref="contentRef"
    :class="articleClasses"
    @mouseup="handleTextSelection"
  >
    <!-- Loading skeleton -->
    <Transition name="fade" mode="out-in">
      <div v-if="loading" class="max-w-3xl mx-auto space-y-6">
        <ContentSkeleton />
      </div>

      <!-- Actual content -->
      <div v-else class="max-w-3xl mx-auto">
        <!-- Table of contents (floating) -->
        <Transition name="toc">
          <nav
            v-if="showTableOfContents && tableOfContents.length > 0"
            class="fixed right-8 top-32 w-52 hidden xl:block"
          >
            <div class="bg-white rounded-lg border border-neutral-200 p-4 shadow-sm">
              <h4 class="text-[11px] font-semibold text-neutral-500 uppercase tracking-widest mb-4 flex items-center gap-2">
                <Icon name="ph:list" class="w-3.5 h-3.5" />
                On this page
              </h4>
              <div class="relative">
                <!-- Active indicator line -->
                <div class="absolute left-0 top-0 bottom-0 w-0.5 bg-neutral-100 rounded-full" />
                <ul class="space-y-0.5 relative">
                  <li
                    v-for="item in tableOfContents"
                    :key="item.id"
                    class="relative"
                  >
                    <!-- Active marker -->
                    <Transition name="marker">
                      <div
                        v-if="activeHeadingId === item.id"
                        class="absolute left-0 top-1/2 -translate-y-1/2 w-0.5 h-4 bg-neutral-900 rounded-full"
                      />
                    </Transition>
                    <button
                      type="button"
                      :class="[
                        'text-left w-full text-sm py-1.5 px-3 rounded-md truncate',
                        'transition-colors duration-150',
                        activeHeadingId === item.id
                          ? 'text-neutral-900 font-medium bg-neutral-100'
                          : 'text-neutral-500 hover:text-neutral-900 hover:bg-neutral-50',
                        item.level === 2 && 'pl-5',
                        item.level === 3 && 'pl-7 text-xs',
                      ]"
                      @click="scrollToHeading(item.id)"
                    >
                      {{ item.text }}
                    </button>
                  </li>
                </ul>
              </div>
            </div>
          </nav>
        </Transition>

        <!-- Main content area -->
        <div :class="proseClasses">
          <!-- Selection toolbar -->
          <Transition name="toolbar">
            <div
              v-if="showSelectionToolbar && selectionPosition"
              class="fixed z-50 bg-white rounded-lg border border-neutral-200 shadow-lg p-1.5 flex items-center gap-0.5"
              :style="{
                left: `${selectionPosition.x}px`,
                top: `${selectionPosition.y}px`,
                transform: 'translateX(-50%)',
              }"
            >
              <Tooltip text="Highlight" :delay-open="300">
                <button
                  type="button"
                  class="p-2 rounded-md transition-colors duration-150 hover:bg-neutral-100"
                  @click="handleHighlight"
                >
                  <Icon name="ph:highlighter" class="w-4 h-4 text-neutral-500" />
                </button>
              </Tooltip>

              <Tooltip text="Comment" :delay-open="300">
                <button
                  type="button"
                  class="p-2 rounded-md transition-colors duration-150 hover:bg-neutral-100"
                  @click="handleComment"
                >
                  <Icon name="ph:chat-circle" class="w-4 h-4 text-neutral-500" />
                </button>
              </Tooltip>

              <div class="w-px h-4 bg-neutral-200 mx-1" />

              <Tooltip text="Copy" :delay-open="300">
                <button
                  type="button"
                  class="p-2 rounded-md transition-colors duration-150 hover:bg-neutral-100"
                  @click="handleCopySelection"
                >
                  <Icon name="ph:copy" class="w-4 h-4 text-neutral-500" />
                </button>
              </Tooltip>

              <Tooltip text="Share" :delay-open="300">
                <button
                  type="button"
                  class="p-2 rounded-md transition-colors duration-150 hover:bg-neutral-100"
                  @click="handleShare"
                >
                  <Icon name="ph:share" class="w-4 h-4 text-neutral-500" />
                </button>
              </Tooltip>
            </div>
          </Transition>

          <!-- Parsed content blocks -->
          <TransitionGroup name="content-block" tag="div">
            <template v-for="(block, index) in parsedContent" :key="`${block.tag}-${index}`">
              <!-- Code Block -->
              <CodeBlock
                v-if="block.tag === 'code'"
                :code="block.content"
                :language="block.language"
                :filename="block.filename"
                :show-line-numbers="showLineNumbers"
                :highlight-lines="block.highlightLines"
                :collapsible="block.content.split('\n').length > collapseCodeThreshold"
                :size="size"
                class="my-6"
              />

              <!-- Heading with anchor -->
              <component
                :is="block.tag"
                v-else-if="['h1', 'h2', 'h3', 'h4', 'h5', 'h6'].includes(block.tag)"
                :id="generateHeadingId(block.content)"
                :class="[blockClasses[block.tag], 'group relative scroll-mt-20']"
              >
                {{ block.content }}
                <button
                  type="button"
                  class="absolute -left-6 top-1/2 -translate-y-1/2 opacity-0 group-hover:opacity-100 p-1 rounded-md hover:bg-neutral-100 transition-opacity duration-150"
                  :aria-label="`Copy link to ${block.content}`"
                  @click="copyHeadingLink(block.content)"
                >
                  <Icon name="ph:link" class="w-4 h-4 text-neutral-500" />
                </button>
              </component>

              <!-- Blockquote with styling -->
              <blockquote
                v-else-if="block.tag === 'blockquote'"
                :class="[
                  blockClasses.blockquote,
                  block.variant && blockquoteVariants[block.variant],
                ]"
              >
                <div v-if="block.variant" class="flex items-center gap-2 mb-2">
                  <Icon :name="blockquoteIcons[block.variant]" :class="blockquoteIconClasses[block.variant]" />
                  <span class="font-medium text-sm">{{ blockquoteLabels[block.variant] }}</span>
                </div>
                <p>{{ block.content }}</p>
              </blockquote>

              <!-- Table -->
              <div
                v-else-if="block.tag === 'table'"
                class="my-6 overflow-x-auto rounded-lg border border-neutral-200"
              >
                <table class="w-full text-sm">
                  <thead class="bg-neutral-50">
                    <tr>
                      <th
                        v-for="(header, hIdx) in block.headers"
                        :key="hIdx"
                        class="px-4 py-2 text-left font-medium text-neutral-900 border-b border-neutral-200"
                      >
                        {{ header }}
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="(row, rIdx) in block.rows"
                      :key="rIdx"
                      class="border-b border-neutral-200 last:border-0 hover:bg-neutral-50 transition-colors duration-150"
                    >
                      <td
                        v-for="(cell, cIdx) in row"
                        :key="cIdx"
                        class="px-4 py-2 text-neutral-900"
                      >
                        {{ cell }}
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- Checkbox list item -->
              <div
                v-else-if="block.tag === 'checkbox'"
                class="flex items-start gap-3 my-2 ml-2"
              >
                <button
                  type="button"
                  :class="[
                    'mt-0.5 w-5 h-5 rounded border-2 flex items-center justify-center shrink-0',
                    'transition-colors duration-150',
                    block.checked
                      ? 'bg-neutral-900 border-neutral-900'
                      : 'border-neutral-300 hover:border-neutral-400',
                  ]"
                  :aria-checked="block.checked"
                  role="checkbox"
                  @click="toggleCheckbox(index)"
                >
                  <Transition name="check">
                    <Icon
                      v-if="block.checked"
                      name="ph:check-bold"
                      class="w-3 h-3 text-white"
                    />
                  </Transition>
                </button>
                <span :class="[block.checked && 'line-through text-neutral-500']">
                  {{ block.content }}
                </span>
              </div>

              <!-- Horizontal rule -->
              <hr
                v-else-if="block.tag === 'hr'"
                class="my-8 border-0 h-px bg-neutral-200"
              />

              <!-- Image -->
              <figure
                v-else-if="block.tag === 'image'"
                class="my-6"
              >
                <div class="relative rounded-lg overflow-hidden border border-neutral-200">
                  <img
                    :src="block.src"
                    :alt="block.alt || ''"
                    class="w-full h-auto"
                    loading="lazy"
                  />
                  <button
                    type="button"
                    class="absolute top-2 right-2 p-2 rounded-lg bg-white/80 opacity-0 hover:opacity-100 transition-opacity duration-150 hover:bg-white"
                    @click="$emit('image-click', block.src)"
                  >
                    <Icon name="ph:arrows-out" class="w-4 h-4 text-neutral-500" />
                  </button>
                </div>
                <figcaption
                  v-if="block.caption"
                  class="text-center text-sm text-neutral-500 mt-2 italic"
                >
                  {{ block.caption }}
                </figcaption>
              </figure>

              <!-- Regular Elements -->
              <component
                :is="block.tag"
                v-else-if="block.tag !== 'text'"
                :class="blockClasses[block.tag]"
              >
                <!-- Render inline formatting -->
                <template v-if="block.inlineContent">
                  <template v-for="(part, pIdx) in block.inlineContent" :key="pIdx">
                    <strong v-if="part.bold">{{ part.text }}</strong>
                    <em v-else-if="part.italic">{{ part.text }}</em>
                    <code
                      v-else-if="part.code"
                      class="px-1.5 py-0.5 rounded bg-neutral-100 text-neutral-800 font-mono text-sm"
                    >{{ part.text }}</code>
                    <a
                      v-else-if="part.link"
                      :href="part.link"
                      class="text-neutral-900 underline hover:text-neutral-600"
                      target="_blank"
                      rel="noopener noreferrer"
                    >{{ part.text }}</a>
                    <span v-else>{{ part.text }}</span>
                  </template>
                </template>
                <template v-else>{{ block.content }}</template>
              </component>

              <!-- Plain Text -->
              <span v-else>{{ block.content }}</span>
            </template>
          </TransitionGroup>

          <!-- Read progress indicator -->
          <Transition name="progress">
            <div
              v-if="showReadProgress"
              class="fixed bottom-8 right-8 w-14 h-14 rounded-xl bg-white border border-neutral-200 shadow-md flex items-center justify-center cursor-pointer transition-colors duration-150 hover:bg-neutral-50"
              :title="`${Math.round(readProgress)}% read`"
            >
              <svg class="w-10 h-10 -rotate-90">
                <circle
                  cx="20"
                  cy="20"
                  r="16"
                  stroke="currentColor"
                  stroke-width="2.5"
                  fill="none"
                  class="text-neutral-100"
                />
                <circle
                  cx="20"
                  cy="20"
                  r="16"
                  stroke="currentColor"
                  stroke-width="2.5"
                  fill="none"
                  class="text-neutral-900 transition-all duration-150"
                  :stroke-dasharray="circumference"
                  :stroke-dashoffset="circumference - (readProgress / 100) * circumference"
                  stroke-linecap="round"
                />
              </svg>
              <span class="absolute text-xs font-semibold text-neutral-900 tabular-nums">
                {{ Math.round(readProgress) }}%
              </span>
            </div>
          </Transition>
        </div>
      </div>
    </Transition>
  </article>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, defineComponent, h } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import Tooltip from '@/Components/shared/Tooltip.vue'
import CodeBlock from './CodeBlock.vue'

// ============================================================================
// Types
// ============================================================================

type ContentSize = 'sm' | 'md' | 'lg'
type BlockquoteVariant = 'note' | 'tip' | 'warning' | 'danger' | 'info'

interface InlinePart {
  text: string
  bold?: boolean
  italic?: boolean
  code?: boolean
  link?: string
}

interface ContentBlock {
  tag: string
  content: string
  level?: number
  language?: string
  filename?: string
  highlightLines?: number[]
  variant?: BlockquoteVariant
  checked?: boolean
  src?: string
  alt?: string
  caption?: string
  headers?: string[]
  rows?: string[][]
  inlineContent?: InlinePart[]
}

interface TableOfContentsItem {
  id: string
  text: string
  level: number
}

interface SelectionPosition {
  x: number
  y: number
}

// ============================================================================
// Content Skeleton Component
// ============================================================================

const ContentSkeleton = defineComponent({
  name: 'ContentSkeleton',
  setup() {
    return () => h('div', { class: 'space-y-6 animate-pulse' }, [
      // Title skeleton
      h('div', { class: 'h-8 bg-neutral-100 rounded-lg w-3/4' }),
      // Paragraphs
      h('div', { class: 'space-y-3' }, [
        h('div', { class: 'h-4 bg-neutral-100 rounded w-full' }),
        h('div', { class: 'h-4 bg-neutral-100 rounded w-5/6' }),
        h('div', { class: 'h-4 bg-neutral-100 rounded w-4/5' }),
      ]),
      // Subheading
      h('div', { class: 'h-6 bg-neutral-100 rounded-lg w-1/2 mt-8' }),
      // More paragraphs
      h('div', { class: 'space-y-3' }, [
        h('div', { class: 'h-4 bg-neutral-100 rounded w-full' }),
        h('div', { class: 'h-4 bg-neutral-100 rounded w-3/4' }),
      ]),
      // Code block skeleton
      h('div', { class: 'h-32 bg-neutral-100 rounded-lg mt-6' }),
      // More content
      h('div', { class: 'space-y-3 mt-6' }, [
        h('div', { class: 'h-4 bg-neutral-100 rounded w-full' }),
        h('div', { class: 'h-4 bg-neutral-100 rounded w-2/3' }),
      ]),
    ])
  },
})

// ============================================================================
// Size Configuration
// ============================================================================

const sizeConfig: Record<ContentSize, {
  padding: string
  fontSize: string
  lineHeight: string
}> = {
  sm: {
    padding: 'px-4 py-4',
    fontSize: 'text-sm',
    lineHeight: 'leading-relaxed',
  },
  md: {
    padding: 'px-8 py-6',
    fontSize: 'text-base',
    lineHeight: 'leading-relaxed',
  },
  lg: {
    padding: 'px-12 py-8',
    fontSize: 'text-lg',
    lineHeight: 'leading-loose',
  },
}

// ============================================================================
// Props & Emits
// ============================================================================

const props = withDefaults(defineProps<{
  content: string
  size?: ContentSize
  loading?: boolean
  showTableOfContents?: boolean
  showSelectionToolbar?: boolean
  showReadProgress?: boolean
  showLineNumbers?: boolean
  collapseCodeThreshold?: number
  editable?: boolean
}>(), {
  size: 'md',
  loading: false,
  showTableOfContents: true,
  showSelectionToolbar: true,
  showReadProgress: false,
  showLineNumbers: true,
  collapseCodeThreshold: 20,
  editable: false,
})

const emit = defineEmits<{
  highlight: [text: string]
  comment: [text: string, position: SelectionPosition]
  'checkbox-toggle': [index: number, checked: boolean]
  'image-click': [src: string]
  'link-copy': [url: string]
}>()

// ============================================================================
// State
// ============================================================================

const contentRef = ref<HTMLElement | null>(null)
const activeHeadingId = ref<string | null>(null)
const readProgress = ref(0)
const selectedText = ref('')
const selectionPosition = ref<SelectionPosition | null>(null)

// ============================================================================
// Constants
// ============================================================================

const circumference = 2 * Math.PI * 16

const blockquoteVariants: Record<BlockquoteVariant, string> = {
  note: 'border-neutral-400 bg-neutral-50',
  tip: 'border-neutral-400 bg-neutral-50',
  warning: 'border-neutral-400 bg-neutral-50',
  danger: 'border-neutral-400 bg-neutral-50',
  info: 'border-neutral-400 bg-neutral-50',
}

const blockquoteIcons: Record<BlockquoteVariant, string> = {
  note: 'ph:note',
  tip: 'ph:lightbulb',
  warning: 'ph:warning',
  danger: 'ph:warning-octagon',
  info: 'ph:info',
}

const blockquoteIconClasses: Record<BlockquoteVariant, string> = {
  note: 'w-4 h-4 text-neutral-500',
  tip: 'w-4 h-4 text-neutral-500',
  warning: 'w-4 h-4 text-neutral-500',
  danger: 'w-4 h-4 text-neutral-500',
  info: 'w-4 h-4 text-neutral-500',
}

const blockquoteLabels: Record<BlockquoteVariant, string> = {
  note: 'Note',
  tip: 'Tip',
  warning: 'Warning',
  danger: 'Danger',
  info: 'Info',
}

// ============================================================================
// Computed - Configuration
// ============================================================================

const config = computed(() => sizeConfig[props.size])

// ============================================================================
// Computed - Styling
// ============================================================================

const articleClasses = computed(() => [
  'flex-1 overflow-y-auto relative',
  config.value.padding,
])

const proseClasses = computed(() => [
  'prose prose-gray max-w-none',
  config.value.fontSize,
  config.value.lineHeight,
])

const blockClasses: Record<string, string> = {
  h1: 'text-3xl font-bold mb-6 mt-8 first:mt-0 text-neutral-900',
  h2: 'text-2xl font-semibold mb-4 mt-8 text-neutral-900',
  h3: 'text-xl font-semibold mb-3 mt-6 text-neutral-900',
  h4: 'text-lg font-medium mb-2 mt-4 text-neutral-900',
  h5: 'text-base font-medium mb-2 mt-4 text-neutral-900',
  h6: 'text-sm font-medium mb-2 mt-4 text-neutral-500 uppercase tracking-wide',
  p: 'text-neutral-700 leading-relaxed mb-4',
  li: 'text-neutral-700 leading-relaxed ml-6 mb-2 list-disc',
  ol: 'text-neutral-700 leading-relaxed ml-6 mb-4',
  ul: 'text-neutral-700 leading-relaxed ml-6 mb-4',
  blockquote: 'border-l-4 border-neutral-300 pl-4 py-2 my-4 rounded-r-lg',
}

// ============================================================================
// Computed - Content Parsing
// ============================================================================

const parsedContent = computed(() => {
  const lines = props.content.split('\n')
  const blocks: ContentBlock[] = []

  let currentParagraph: string[] = []
  let inCodeBlock = false
  let codeBlockContent: string[] = []
  let codeBlockLanguage = ''
  let codeBlockFilename = ''
  let inTable = false
  let tableHeaders: string[] = []
  let tableRows: string[][] = []

  const flushParagraph = () => {
    if (currentParagraph.length > 0) {
      const content = currentParagraph.join(' ')
      blocks.push({
        tag: 'p',
        content,
        inlineContent: parseInlineFormatting(content),
      })
      currentParagraph = []
    }
  }

  const flushTable = () => {
    if (tableHeaders.length > 0) {
      blocks.push({
        tag: 'table',
        content: '',
        headers: tableHeaders,
        rows: tableRows,
      })
      tableHeaders = []
      tableRows = []
      inTable = false
    }
  }

  for (const line of lines) {
    // Code block start/end
    if (line.startsWith('```')) {
      if (!inCodeBlock) {
        flushParagraph()
        inCodeBlock = true
        const meta = line.slice(3).trim()
        const parts = meta.split(':')
        codeBlockLanguage = parts[0] || ''
        codeBlockFilename = parts[1] || ''
        codeBlockContent = []
      } else {
        blocks.push({
          tag: 'code',
          content: codeBlockContent.join('\n'),
          language: codeBlockLanguage,
          filename: codeBlockFilename,
        })
        inCodeBlock = false
      }
      continue
    }

    if (inCodeBlock) {
      codeBlockContent.push(line)
      continue
    }

    // Table handling
    if (line.includes('|') && line.trim().startsWith('|')) {
      const cells = line.split('|').filter(c => c.trim()).map(c => c.trim())

      if (!inTable) {
        tableHeaders = cells
        inTable = true
      } else if (line.includes('---')) {
        // Separator row, skip
      } else {
        tableRows.push(cells)
      }
      continue
    } else if (inTable) {
      flushTable()
    }

    // Horizontal rule
    if (/^(-{3,}|\*{3,}|_{3,})$/.test(line.trim())) {
      flushParagraph()
      blocks.push({ tag: 'hr', content: '' })
      continue
    }

    // Headers
    const headerMatch = line.match(/^(#{1,6})\s+(.+)$/)
    if (headerMatch) {
      flushParagraph()
      const level = headerMatch[1].length
      blocks.push({ tag: `h${level}`, content: headerMatch[2], level })
      continue
    }

    // Checkbox list items
    const checkboxMatch = line.match(/^[-*]\s+\[([ xX])\]\s+(.+)$/)
    if (checkboxMatch) {
      flushParagraph()
      blocks.push({
        tag: 'checkbox',
        content: checkboxMatch[2],
        checked: checkboxMatch[1].toLowerCase() === 'x',
      })
      continue
    }

    // List items
    if (line.match(/^[-*]\s+/)) {
      flushParagraph()
      const content = line.replace(/^[-*]\s+/, '')
      blocks.push({
        tag: 'li',
        content,
        inlineContent: parseInlineFormatting(content),
      })
      continue
    }

    // Numbered list
    if (/^\d+\.\s/.test(line)) {
      flushParagraph()
      const content = line.replace(/^\d+\.\s/, '')
      blocks.push({
        tag: 'li',
        content,
        inlineContent: parseInlineFormatting(content),
      })
      continue
    }

    // Blockquote with optional variant
    const blockquoteMatch = line.match(/^>\s*(\[(\w+)\])?\s*(.+)$/)
    if (blockquoteMatch) {
      flushParagraph()
      const variant = blockquoteMatch[2]?.toLowerCase() as BlockquoteVariant | undefined
      blocks.push({
        tag: 'blockquote',
        content: blockquoteMatch[3],
        variant: variant && blockquoteVariants[variant] ? variant : undefined,
      })
      continue
    }

    // Image
    const imageMatch = line.match(/^!\[(.*?)\]\((.*?)(?:\s+"(.*?)")?\)$/)
    if (imageMatch) {
      flushParagraph()
      blocks.push({
        tag: 'image',
        content: '',
        alt: imageMatch[1],
        src: imageMatch[2],
        caption: imageMatch[3],
      })
      continue
    }

    // Empty line
    if (line.trim() === '') {
      flushParagraph()
      continue
    }

    // Regular text
    currentParagraph.push(line)
  }

  flushParagraph()
  flushTable()

  return blocks
})

// ============================================================================
// Computed - Table of Contents
// ============================================================================

const tableOfContents = computed((): TableOfContentsItem[] => {
  return parsedContent.value
    .filter(block => ['h1', 'h2', 'h3'].includes(block.tag))
    .map(block => ({
      id: generateHeadingId(block.content),
      text: block.content,
      level: parseInt(block.tag.slice(1)),
    }))
})

// ============================================================================
// Methods - Parsing
// ============================================================================

const parseInlineFormatting = (text: string): InlinePart[] => {
  const parts: InlinePart[] = []
  let remaining = text

  // Simple regex-based parsing for bold, italic, code, links
  const patterns = [
    { regex: /\*\*(.+?)\*\*/g, type: 'bold' },
    { regex: /\*(.+?)\*/g, type: 'italic' },
    { regex: /`(.+?)`/g, type: 'code' },
    { regex: /\[(.+?)\]\((.+?)\)/g, type: 'link' },
  ]

  let lastIndex = 0

  // Find all matches and sort by position
  const matches: Array<{
    index: number
    length: number
    text: string
    type: string
    link?: string
  }> = []

  for (const { regex, type } of patterns) {
    let match
    while ((match = regex.exec(text)) !== null) {
      matches.push({
        index: match.index,
        length: match[0].length,
        text: type === 'link' ? match[1] : match[1],
        type,
        link: type === 'link' ? match[2] : undefined,
      })
    }
  }

  // Sort matches by position
  matches.sort((a, b) => a.index - b.index)

  // Build parts array
  for (const match of matches) {
    if (match.index > lastIndex) {
      parts.push({ text: text.slice(lastIndex, match.index) })
    }

    const part: InlinePart = { text: match.text }
    if (match.type === 'bold') part.bold = true
    else if (match.type === 'italic') part.italic = true
    else if (match.type === 'code') part.code = true
    else if (match.type === 'link') part.link = match.link

    parts.push(part)
    lastIndex = match.index + match.length
  }

  if (lastIndex < text.length) {
    parts.push({ text: text.slice(lastIndex) })
  }

  return parts.length > 0 ? parts : [{ text }]
}

const generateHeadingId = (text: string): string => {
  return text
    .toLowerCase()
    .replace(/[^a-z0-9\s-]/g, '')
    .replace(/\s+/g, '-')
    .replace(/-+/g, '-')
    .trim()
}

// ============================================================================
// Methods - Interactions
// ============================================================================

const scrollToHeading = (id: string) => {
  const element = document.getElementById(id)
  if (element) {
    element.scrollIntoView({ behavior: 'smooth', block: 'start' })
    activeHeadingId.value = id
  }
}

const copyHeadingLink = async (text: string) => {
  const id = generateHeadingId(text)
  const url = `${window.location.href.split('#')[0]}#${id}`

  try {
    await navigator.clipboard.writeText(url)
    emit('link-copy', url)
  } catch {
    // Fallback
  }
}

const handleTextSelection = () => {
  if (!props.showSelectionToolbar) return

  const selection = window.getSelection()
  if (!selection || selection.isCollapsed) {
    selectionPosition.value = null
    selectedText.value = ''
    return
  }

  const text = selection.toString().trim()
  if (!text) {
    selectionPosition.value = null
    selectedText.value = ''
    return
  }

  selectedText.value = text

  const range = selection.getRangeAt(0)
  const rect = range.getBoundingClientRect()

  selectionPosition.value = {
    x: rect.left + rect.width / 2,
    y: rect.top - 48,
  }
}

const handleHighlight = () => {
  if (selectedText.value) {
    emit('highlight', selectedText.value)
    window.getSelection()?.removeAllRanges()
    selectionPosition.value = null
  }
}

const handleComment = () => {
  if (selectedText.value && selectionPosition.value) {
    emit('comment', selectedText.value, selectionPosition.value)
    window.getSelection()?.removeAllRanges()
    selectionPosition.value = null
  }
}

const handleCopySelection = async () => {
  if (selectedText.value) {
    try {
      await navigator.clipboard.writeText(selectedText.value)
    } catch {
      // Fallback
    }
    window.getSelection()?.removeAllRanges()
    selectionPosition.value = null
  }
}

const handleShare = () => {
  // Would open share dialog with selected text
  window.getSelection()?.removeAllRanges()
  selectionPosition.value = null
}

const toggleCheckbox = (index: number) => {
  const block = parsedContent.value[index]
  if (block && block.tag === 'checkbox') {
    emit('checkbox-toggle', index, !block.checked)
  }
}

// ============================================================================
// Scroll tracking
// ============================================================================

const updateReadProgress = () => {
  if (!contentRef.value) return

  const scrollTop = contentRef.value.scrollTop
  const scrollHeight = contentRef.value.scrollHeight - contentRef.value.clientHeight

  if (scrollHeight > 0) {
    readProgress.value = (scrollTop / scrollHeight) * 100
  }
}

const updateActiveHeading = () => {
  if (!props.showTableOfContents) return

  const headings = tableOfContents.value
  let currentHeading: string | null = null

  for (const heading of headings) {
    const element = document.getElementById(heading.id)
    if (element) {
      const rect = element.getBoundingClientRect()
      if (rect.top <= 100) {
        currentHeading = heading.id
      }
    }
  }

  activeHeadingId.value = currentHeading
}

onMounted(() => {
  if (contentRef.value) {
    contentRef.value.addEventListener('scroll', () => {
      updateReadProgress()
      updateActiveHeading()
    })
  }

  // Close selection toolbar on outside click
  document.addEventListener('mousedown', (e) => {
    if (selectionPosition.value) {
      const target = e.target as HTMLElement
      if (!target.closest('.selection-toolbar')) {
        // Let the mouseup handler deal with new selections
      }
    }
  })
})
</script>

<style scoped>
@reference "tailwindcss";

/* Fade transition */
.fade-enter-active {
  transition: opacity 0.15s ease-out;
}

.fade-leave-active {
  transition: opacity 0.1s ease-out;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

/* TOC transition */
.toc-enter-active {
  transition: opacity 0.15s ease-out;
}

.toc-leave-active {
  transition: opacity 0.1s ease-out;
}

.toc-enter-from,
.toc-leave-to {
  opacity: 0;
}

/* TOC marker transition */
.marker-enter-active,
.marker-leave-active {
  transition: opacity 0.15s ease-out;
}

.marker-enter-from,
.marker-leave-to {
  opacity: 0;
}

/* Toolbar transition */
.toolbar-enter-active {
  transition: opacity 0.15s ease-out;
}

.toolbar-leave-active {
  transition: opacity 0.1s ease-out;
}

.toolbar-enter-from,
.toolbar-leave-to {
  opacity: 0;
}

/* Content block transition */
.content-block-enter-active {
  transition: opacity 0.15s ease-out;
}

.content-block-leave-active {
  transition: opacity 0.1s ease-out;
}

.content-block-enter-from,
.content-block-leave-to {
  opacity: 0;
}

/* Checkbox animation */
.check-enter-active {
  transition: opacity 0.15s ease-out;
}

.check-leave-active {
  transition: opacity 0.1s ease-out;
}

.check-enter-from,
.check-leave-to {
  opacity: 0;
}

/* Progress indicator transition */
.progress-enter-active {
  transition: opacity 0.15s ease-out;
}

.progress-leave-active {
  transition: opacity 0.1s ease-out;
}

.progress-enter-from,
.progress-leave-to {
  opacity: 0;
}

/* Prose customizations */
.prose :deep(a) {
  @apply text-neutral-900 underline hover:text-neutral-600 transition-colors;
}

.prose :deep(strong) {
  @apply text-neutral-900 font-semibold;
}

.prose :deep(em) {
  @apply text-neutral-500;
}

/* Highlight animation */
@keyframes highlight-pulse {
  0%, 100% {
    background-color: rgba(156, 163, 175, 0.2);
  }
  50% {
    background-color: rgba(156, 163, 175, 0.3);
  }
}

.highlight-new {
  animation: highlight-pulse 1s ease-in-out 2;
}
</style>
