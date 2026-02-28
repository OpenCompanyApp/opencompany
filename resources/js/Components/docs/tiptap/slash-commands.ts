import { Extension } from '@tiptap/core'
import { type Editor } from '@tiptap/vue-3'
import Suggestion, { type SuggestionOptions } from '@tiptap/suggestion'
import { VueRenderer } from '@tiptap/vue-3'
import tippy, { type Instance as TippyInstance } from 'tippy.js'
import SlashCommandList from './SlashCommandList.vue'

export interface SlashCommandItem {
  id: string
  label: string
  description: string
  icon: string
  command: (editor: Editor) => void
  aliases?: string[]
}

export const slashCommandItems: SlashCommandItem[] = [
  {
    id: 'heading1',
    label: 'Heading 1',
    description: 'Large section heading',
    icon: 'ph:text-h-one',
    aliases: ['h1'],
    command: (editor) => editor.chain().focus().toggleHeading({ level: 1 }).run(),
  },
  {
    id: 'heading2',
    label: 'Heading 2',
    description: 'Medium section heading',
    icon: 'ph:text-h-two',
    aliases: ['h2'],
    command: (editor) => editor.chain().focus().toggleHeading({ level: 2 }).run(),
  },
  {
    id: 'heading3',
    label: 'Heading 3',
    description: 'Small section heading',
    icon: 'ph:text-h-three',
    aliases: ['h3'],
    command: (editor) => editor.chain().focus().toggleHeading({ level: 3 }).run(),
  },
  {
    id: 'bulletList',
    label: 'Bullet List',
    description: 'Unordered list with bullet points',
    icon: 'ph:list-bullets',
    aliases: ['ul', 'bullets'],
    command: (editor) => editor.chain().focus().toggleBulletList().run(),
  },
  {
    id: 'numberedList',
    label: 'Numbered List',
    description: 'Ordered list with numbers',
    icon: 'ph:list-numbers',
    aliases: ['ol', 'ordered'],
    command: (editor) => editor.chain().focus().toggleOrderedList().run(),
  },
  {
    id: 'taskList',
    label: 'Task List',
    description: 'List with checkboxes',
    icon: 'ph:check-square',
    aliases: ['todo', 'checklist'],
    command: (editor) => editor.chain().focus().toggleTaskList().run(),
  },
  {
    id: 'blockquote',
    label: 'Quote',
    description: 'Block quotation',
    icon: 'ph:quotes',
    aliases: ['quote', 'blockquote'],
    command: (editor) => editor.chain().focus().toggleBlockquote().run(),
  },
  {
    id: 'codeBlock',
    label: 'Code Block',
    description: 'Code snippet with syntax highlighting',
    icon: 'ph:code-block',
    aliases: ['code', 'pre'],
    command: (editor) => editor.chain().focus().toggleCodeBlock().run(),
  },
  {
    id: 'image',
    label: 'Image',
    description: 'Insert an image from URL',
    icon: 'ph:image',
    aliases: ['img', 'picture'],
    command: (editor) => {
      const url = window.prompt('Image URL')
      if (url) {
        editor.chain().focus().setImage({ src: url }).run()
      }
    },
  },
  {
    id: 'table',
    label: 'Table',
    description: 'Insert a 3x3 table',
    icon: 'ph:table',
    aliases: ['grid'],
    command: (editor) => editor.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run(),
  },
  {
    id: 'divider',
    label: 'Divider',
    description: 'Horizontal line separator',
    icon: 'ph:minus',
    aliases: ['hr', 'separator', 'line'],
    command: (editor) => editor.chain().focus().setHorizontalRule().run(),
  },
]

function filterItems(query: string): SlashCommandItem[] {
  const q = query.toLowerCase()
  return slashCommandItems.filter((item) => {
    return (
      item.label.toLowerCase().includes(q) ||
      item.description.toLowerCase().includes(q) ||
      item.aliases?.some((alias) => alias.toLowerCase().includes(q))
    )
  })
}

export const SlashCommands = Extension.create({
  name: 'slashCommands',

  addOptions() {
    return {
      suggestion: {
        char: '/',
        command: ({ editor, range, props }: { editor: Editor; range: any; props: SlashCommandItem }) => {
          // Delete the slash command text
          editor.chain().focus().deleteRange(range).run()
          // Execute the command
          props.command(editor)
        },
        items: ({ query }: { query: string }) => filterItems(query),
        render: () => {
          let component: VueRenderer
          let popup: TippyInstance[]

          return {
            onStart: (props: any) => {
              component = new VueRenderer(SlashCommandList, {
                props,
                editor: props.editor,
              })

              if (!props.clientRect) return

              popup = tippy('body', {
                getReferenceClientRect: props.clientRect,
                appendTo: () => document.body,
                content: component.element,
                showOnCreate: true,
                interactive: true,
                trigger: 'manual',
                placement: 'bottom-start',
                maxWidth: 'none',
              })
            },
            onUpdate: (props: any) => {
              component.updateProps(props)

              if (!props.clientRect) return

              popup[0].setProps({
                getReferenceClientRect: props.clientRect,
              })
            },
            onKeyDown: (props: any) => {
              if (props.event.key === 'Escape') {
                popup[0].hide()
                return true
              }
              return (component.ref as any)?.onKeyDown?.(props.event) ?? false
            },
            onExit: () => {
              popup?.[0]?.destroy()
              component?.destroy()
            },
          }
        },
      } as Partial<SuggestionOptions>,
    }
  },

  addProseMirrorPlugins() {
    return [
      Suggestion({
        editor: this.editor,
        ...this.options.suggestion,
      }),
    ]
  },
})
