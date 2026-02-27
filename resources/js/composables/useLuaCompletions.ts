import { onBeforeUnmount } from 'vue'
import axios from 'axios'
import { monaco } from '@/composables/useMonaco'

interface CatalogParam {
  name: string
  type: string
  required: boolean
  description?: string
}

interface CatalogTool {
  slug: string
  name: string
  description: string
  luaFunction?: string
  parameters: CatalogParam[]
}

interface CatalogGroup {
  name: string
  luaNamespace: string
  description?: string
  tools: CatalogTool[]
  isIntegration: boolean
  enabled?: boolean
}

interface CatalogResponse {
  groups: CatalogGroup[]
}

// Module-level cache so we only fetch once per page load
let catalogPromise: Promise<CatalogResponse> | null = null
let disposable: monaco.IDisposable | null = null
let registrationCount = 0

interface NamespaceNode {
  children: Map<string, NamespaceNode>
  tools: CatalogTool[]
  description?: string
}

function buildTree(groups: CatalogGroup[]): NamespaceNode {
  const root: NamespaceNode = { children: new Map(), tools: [] }

  for (const group of groups) {
    // luaNamespace is like "app.chat" or "app.integrations.google_calendar"
    const parts = group.luaNamespace.split('.')
    // Skip the "app" prefix — it's implicit in the root
    let node = root
    for (let i = 1; i < parts.length; i++) {
      if (!node.children.has(parts[i])) {
        node.children.set(parts[i], { children: new Map(), tools: [], description: undefined })
      }
      node = node.children.get(parts[i])!
    }
    node.tools = group.tools.filter(t => t.luaFunction)
    node.description = group.description
  }

  return root
}

function resolveNode(root: NamespaceNode, segments: string[]): NamespaceNode | null {
  let node = root
  for (const seg of segments) {
    const child = node.children.get(seg)
    if (!child) return null
    node = child
  }
  return node
}

function buildParamSnippet(params: CatalogParam[]): string {
  if (params.length === 0) return '()'

  const lines: string[] = []
  let tabStop = 1
  for (const p of params) {
    const placeholder = p.type === 'string' ? `"\${${tabStop}}"` : `\${${tabStop}}`
    lines.push(`  ${p.name} = ${placeholder}`)
    tabStop++
  }
  return '({\n' + lines.join(',\n') + '\n})'
}

function buildParamDocs(params: CatalogParam[]): string {
  if (params.length === 0) return ''

  const lines = params.map(p => {
    const req = p.required ? '**required**' : 'optional'
    const desc = p.description ? ` — ${p.description}` : ''
    return `- \`${p.name}\` (${p.type}, ${req})${desc}`
  })

  return '\n\n**Parameters:**\n' + lines.join('\n')
}

function registerProvider(catalog: CatalogResponse): monaco.IDisposable {
  const tree = buildTree(catalog.groups)

  return monaco.languages.registerCompletionItemProvider('lua', {
    triggerCharacters: ['.'],

    provideCompletionItems(model, position) {
      const line = model.getValueInRange({
        startLineNumber: position.lineNumber,
        startColumn: 1,
        endLineNumber: position.lineNumber,
        endColumn: position.column,
      })

      // Match "app.something.something." pattern
      const match = line.match(/\bapp\.([\w.]*?)\.?$/)
      if (!match) {
        return { suggestions: [] }
      }

      const typed = match[1]
      // segments is what's been typed after "app."
      // e.g. "app.chat." → segments = ["chat"], trailing dot means we want children of chat
      // e.g. "app.ch" → segments = [], partial = "ch" (filtering top-level)
      const hasDot = line.endsWith('.')
      const allSegments = typed ? typed.split('.') : []

      let pathSegments: string[]
      let partial: string

      if (hasDot) {
        // User typed "app.chat." — resolve "chat" node, suggest its children
        pathSegments = allSegments
        partial = ''
      } else if (allSegments.length > 0) {
        // User typed "app.ch" — resolve parent (root), filter by partial "ch"
        pathSegments = allSegments.slice(0, -1)
        partial = allSegments[allSegments.length - 1]
      } else {
        // Just "app." — show root children
        pathSegments = []
        partial = ''
      }

      const node = resolveNode(tree, pathSegments)
      if (!node) {
        return { suggestions: [] }
      }

      const word = model.getWordUntilPosition(position)
      const range = {
        startLineNumber: position.lineNumber,
        endLineNumber: position.lineNumber,
        startColumn: word.startColumn,
        endColumn: position.column,
      }

      const suggestions: monaco.languages.CompletionItem[] = []

      // Suggest child namespaces
      for (const [name, child] of node.children) {
        if (partial && !name.startsWith(partial)) continue
        suggestions.push({
          label: name,
          kind: monaco.languages.CompletionItemKind.Module,
          detail: child.description || 'namespace',
          insertText: name,
          range,
          sortText: '0_' + name,
        })
      }

      // Suggest functions at this level
      for (const tool of node.tools) {
        if (!tool.luaFunction) continue
        if (partial && !tool.luaFunction.startsWith(partial)) continue

        const sig = tool.parameters.length > 0
          ? '(' + tool.parameters.map(p => p.name).join(', ') + ')'
          : '()'

        suggestions.push({
          label: { label: tool.luaFunction, detail: ' ' + sig },
          kind: monaco.languages.CompletionItemKind.Function,
          detail: sig,
          documentation: {
            value: tool.description + buildParamDocs(tool.parameters),
          },
          insertText: tool.luaFunction + buildParamSnippet(tool.parameters),
          insertTextRules: monaco.languages.CompletionItemInsertTextRule.InsertAsSnippet,
          range,
          sortText: '1_' + tool.luaFunction,
        })
      }

      return { suggestions }
    },
  })
}

/**
 * Register Lua autocomplete for the `app.*` bridge API.
 * Fetches the tool catalog once and provides dot-triggered completions.
 * Call in LuaConsole setup — auto-disposes on unmount.
 */
export function useLuaCompletions() {
  registrationCount++

  // Lazily fetch catalog
  if (!catalogPromise) {
    catalogPromise = axios.get<CatalogResponse>('/api/tools/catalog')
      .then(res => res.data)
      .catch(() => ({ groups: [] }))
  }

  // Register provider (once globally)
  if (!disposable) {
    catalogPromise.then(catalog => {
      if (!disposable && catalog.groups.length > 0) {
        disposable = registerProvider(catalog)
      }
    })
  }

  onBeforeUnmount(() => {
    registrationCount--
    if (registrationCount <= 0 && disposable) {
      disposable.dispose()
      disposable = null
      registrationCount = 0
    }
  })
}
