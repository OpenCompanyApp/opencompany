import { marked } from 'marked'
import { useHighlight } from './useHighlight'

const { highlight } = useHighlight()

// Configure marked with highlight.js for code blocks
marked.setOptions({
  gfm: true,
  breaks: true,
})

// Custom renderer for code blocks with syntax highlighting
const renderer = new marked.Renderer()

renderer.code = ({ text, lang }: { text: string; lang?: string }) => {
  const highlighted = lang ? highlight(text, lang) : highlight(text)
  const langLabel = lang ? `<span class="absolute top-2 right-2 text-xs text-neutral-500 font-mono select-none">${lang}</span>` : ''
  return `<pre class="relative bg-neutral-900 rounded-md p-3 overflow-x-auto border border-neutral-700 my-2">${langLabel}<code class="hljs text-sm">${highlighted}</code></pre>`
}

renderer.codespan = ({ text }: { text: string }) => {
  return `<code class="px-1.5 py-0.5 bg-neutral-100 dark:bg-neutral-800 rounded text-sm font-mono text-pink-600 dark:text-pink-400">${text}</code>`
}

marked.use({ renderer })

export function useMarkdown() {
  const renderMarkdown = (content: string): string => {
    if (!content) return ''
    try {
      return marked.parse(content) as string
    } catch {
      return content
    }
  }

  return { renderMarkdown }
}
