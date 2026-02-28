import { useEditor } from '@tiptap/vue-3'
import type { Editor } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Underline from '@tiptap/extension-underline'
import Link from '@tiptap/extension-link'
import Image from '@tiptap/extension-image'
import TaskList from '@tiptap/extension-task-list'
import TaskItem from '@tiptap/extension-task-item'
import { Table, TableRow, TableHeader, TableCell } from '@tiptap/extension-table'
import CodeBlockLowlight from '@tiptap/extension-code-block-lowlight'
import Placeholder from '@tiptap/extension-placeholder'
import Typography from '@tiptap/extension-typography'
import TextAlign from '@tiptap/extension-text-align'
import { Color } from '@tiptap/extension-color'
import { TextStyle } from '@tiptap/extension-text-style'
import Highlight from '@tiptap/extension-highlight'
import { SlashCommands } from '@/Components/docs/tiptap/slash-commands'
import { common, createLowlight } from 'lowlight'
import { marked } from 'marked'
import type { Ref, ShallowRef } from 'vue'

const lowlight = createLowlight(common)

function convertMarkdownToHtml(markdown: string): string {
  if (!markdown) return ''
  try {
    return marked.parse(markdown, { async: false }) as string
  } catch {
    return markdown
  }
}

export interface TipTapEditorOptions {
  content: string
  contentFormat?: 'markdown' | 'html'
  editable?: boolean
  placeholder?: string
  onUpdate?: (html: string) => void
}

export function useTipTapEditor(options: TipTapEditorOptions): {
  editor: ShallowRef<Editor | undefined>
} {
  const resolvedContent = options.contentFormat === 'markdown' && options.content
    ? convertMarkdownToHtml(options.content)
    : options.content || ''

  const editor = useEditor({
    content: resolvedContent,
    editable: options.editable ?? true,
    extensions: [
      StarterKit.configure({
        codeBlock: false,
      }),
      Underline,
      Link.configure({
        openOnClick: false,
        HTMLAttributes: {
          class: 'text-blue-600 dark:text-blue-400 underline cursor-pointer',
        },
      }),
      Image.configure({
        HTMLAttributes: {
          class: 'rounded-lg max-w-full',
        },
      }),
      TaskList,
      TaskItem.configure({ nested: true }),
      Table.configure({ resizable: true }),
      TableRow,
      TableHeader,
      TableCell,
      CodeBlockLowlight.configure({ lowlight }),
      Placeholder.configure({
        placeholder: options.placeholder ?? 'Start writing, or press / for commands...',
      }),
      Typography,
      TextAlign.configure({
        types: ['heading', 'paragraph'],
      }),
      TextStyle,
      Color,
      Highlight.configure({ multicolor: true }),
      SlashCommands,
    ],
    onUpdate: ({ editor }) => {
      options.onUpdate?.(editor.getHTML())
    },
  })

  return { editor }
}
