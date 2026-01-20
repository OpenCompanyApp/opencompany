<template>
  <article class="flex-1 overflow-y-auto px-8 py-6">
    <div class="max-w-3xl mx-auto">
      <div class="prose prose-invert prose-olympus max-w-none">
        <template v-for="(block, index) in parsedContent" :key="index">
          <!-- Code Block -->
          <DocsDocViewerCodeBlock
            v-if="block.tag === 'code'"
            :code="block.content"
            :language="block.language"
          />

          <!-- Regular Elements -->
          <component
            :is="block.tag"
            v-else-if="block.tag !== 'text'"
            :class="blockClasses[block.tag]"
          >
            {{ block.content }}
          </component>

          <!-- Plain Text -->
          <span v-else>{{ block.content }}</span>
        </template>
      </div>
    </div>
  </article>
</template>

<script setup lang="ts">
const props = defineProps<{
  content: string
}>()

interface ContentBlock {
  tag: string
  content: string
  level?: number
  language?: string
}

const parsedContent = computed(() => {
  const lines = props.content.split('\n')
  const blocks: ContentBlock[] = []

  let currentParagraph: string[] = []
  let inCodeBlock = false
  let codeBlockContent: string[] = []
  let codeBlockLanguage = ''

  const flushParagraph = () => {
    if (currentParagraph.length > 0) {
      blocks.push({ tag: 'p', content: currentParagraph.join(' ') })
      currentParagraph = []
    }
  }

  for (const line of lines) {
    // Code block start/end
    if (line.startsWith('```')) {
      if (!inCodeBlock) {
        flushParagraph()
        inCodeBlock = true
        codeBlockLanguage = line.slice(3).trim()
        codeBlockContent = []
      } else {
        blocks.push({
          tag: 'code',
          content: codeBlockContent.join('\n'),
          language: codeBlockLanguage,
        })
        inCodeBlock = false
      }
      continue
    }

    if (inCodeBlock) {
      codeBlockContent.push(line)
      continue
    }

    // Headers
    if (line.startsWith('# ')) {
      flushParagraph()
      blocks.push({ tag: 'h1', content: line.slice(2), level: 1 })
    } else if (line.startsWith('## ')) {
      flushParagraph()
      blocks.push({ tag: 'h2', content: line.slice(3), level: 2 })
    } else if (line.startsWith('### ')) {
      flushParagraph()
      blocks.push({ tag: 'h3', content: line.slice(4), level: 3 })
    }
    // List items
    else if (line.startsWith('- ')) {
      flushParagraph()
      blocks.push({ tag: 'li', content: line.slice(2) })
    }
    // Numbered list
    else if (/^\d+\.\s/.test(line)) {
      flushParagraph()
      blocks.push({ tag: 'li', content: line.replace(/^\d+\.\s/, '') })
    }
    // Blockquote
    else if (line.startsWith('> ')) {
      flushParagraph()
      blocks.push({ tag: 'blockquote', content: line.slice(2) })
    }
    // Empty line
    else if (line.trim() === '') {
      flushParagraph()
    }
    // Regular text
    else {
      currentParagraph.push(line)
    }
  }

  flushParagraph()
  return blocks
})

const blockClasses: Record<string, string> = {
  h1: 'text-3xl font-bold mb-6 mt-8 first:mt-0 text-olympus-text',
  h2: 'text-2xl font-semibold mb-4 mt-6 text-olympus-text',
  h3: 'text-xl font-semibold mb-3 mt-5 text-olympus-text',
  p: 'text-olympus-text leading-relaxed mb-4',
  li: 'text-olympus-text leading-relaxed ml-6 mb-2 list-disc',
  blockquote: 'border-l-4 border-olympus-primary/50 pl-4 italic text-olympus-text-muted my-4',
}
</script>
