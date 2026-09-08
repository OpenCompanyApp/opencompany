import { onBeforeUnmount, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { index as toolCatalogIndex } from '@/actions/App/Http/Controllers/Api/ToolCatalogController'
import { monaco } from '@/composables/useMonaco'
import { wayfinderRequest } from '@/utils/wayfinder'

interface CatalogParam {
  name: string
  type: string | string[]
  required: boolean
  description?: string
  enum?: Array<string | number | boolean>
}

interface CatalogTool {
  slug: string
  name: string
  description: string
  type: 'read' | 'write'
  codeFunction?: string
  parameters: CatalogParam[]
  returns?: Record<string, unknown>
}

interface CatalogGroup {
  name: string
  codeNamespace: string
  description?: string
  tools: CatalogTool[]
  isIntegration: boolean
  enabled?: boolean
}

interface CatalogResponse {
  groups: CatalogGroup[]
}

interface NamespaceNode {
  children: Map<string, NamespaceNode>
  tools: CatalogTool[]
  description?: string
}

function buildTree(groups: CatalogGroup[]): NamespaceNode {
  const root: NamespaceNode = { children: new Map(), tools: [] }

  for (const group of groups) {
    // codeNamespace is like "app.chat" or "app.integrations.google_calendar"
    const parts = group.codeNamespace.split('.')
    // Skip the "app" prefix — it's implicit in the root
    let node = root
    for (let i = 1; i < parts.length; i++) {
      if (!node.children.has(parts[i])) {
        node.children.set(parts[i], { children: new Map(), tools: [], description: undefined })
      }
      node = node.children.get(parts[i])!
    }
    node.tools = group.tools.filter(t => t.codeFunction)
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

function snippetValue(param: CatalogParam, tabStop: number): string {
  const escapeDefault = (value: string) => value.replace(/([\\$}])/g, '\\$1')
  const firstEnumValue = param.enum?.[0]
  if (typeof firstEnumValue === 'string') {
    // Escape Ruby syntax before Monaco snippet syntax. Catalog text is data:
    // quotes, backslashes and interpolation markers must never become code.
    const rubyValue = JSON.stringify(firstEnumValue).slice(1, -1).replace(/#(?=[{@$])/g, '\\#')
    return `"\${${tabStop}:${escapeDefault(rubyValue)}}"`
  }
  if (typeof firstEnumValue === 'number' || typeof firstEnumValue === 'boolean') {
    return `\${${tabStop}:${String(firstEnumValue)}}`
  }

  const type = Array.isArray(param.type) ? param.type[0] : param.type
  switch (type) {
    case 'string':
      return `"\${${tabStop}:value}"`
    case 'integer':
    case 'number':
      return `\${${tabStop}:0}`
    case 'boolean':
      return `\${${tabStop}:false}`
    case 'array':
      return `[\${${tabStop}}]`
    case 'object':
      return `{\${${tabStop}}}`
    default:
      return `\${${tabStop}:nil}`
  }
}

function buildParamSnippet(params: CatalogParam[]): string {
  // Required-only snippets keep the first runnable draft small. Optional
  // parameters remain discoverable in completion documentation and the catalog.
  const required = params.filter(param => param.required)
  if (required.length === 0) return '()'

  const lines: string[] = []
  let tabStop = 1
  for (const param of required) {
    lines.push(`  ${param.name}: ${snippetValue(param, tabStop)}`)
    tabStop++
  }
  return '(\n' + lines.join(',\n') + '\n)'
}

function buildContractDocs(tool: CatalogTool): string {
  const effect = `\n\n**Effect:** ${tool.type}`
  const returns = tool.returns && Object.keys(tool.returns).length > 0
    ? `\n\n**Returns:** \`${JSON.stringify(tool.returns)}\``
    : ''

  return effect + returns
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
  const functions = new Map<string, CatalogTool>(catalog.groups.flatMap(group => group.tools
    .filter(tool => tool.codeFunction)
    .map(tool => [`${group.codeNamespace}.${tool.codeFunction}`, tool] as const)))

  const completion = monaco.languages.registerCompletionItemProvider('ruby', {
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
        if (!tool.codeFunction) continue
        if (partial && !tool.codeFunction.startsWith(partial)) continue

        const required = tool.parameters.filter(param => param.required)
        const optionalCount = tool.parameters.length - required.length
        const sig = required.length > 0
          ? '(' + required.map(param => `${param.name}:`).join(', ') + ')'
          : '()'
        const detail = optionalCount > 0 ? `${sig} · ${optionalCount} optional` : sig

        suggestions.push({
          label: { label: tool.codeFunction, detail: ' ' + detail },
          kind: monaco.languages.CompletionItemKind.Function,
          detail,
          documentation: {
            value: tool.description + buildContractDocs(tool) + buildParamDocs(tool.parameters),
            isTrusted: false,
            supportHtml: false,
          },
          insertText: tool.codeFunction + buildParamSnippet(tool.parameters),
          insertTextRules: monaco.languages.CompletionItemInsertTextRule.InsertAsSnippet,
          range,
          sortText: '1_' + tool.codeFunction,
        })
      }

      return { suggestions }
    },
  })
  const signature = monaco.languages.registerSignatureHelpProvider('ruby', {
    signatureHelpTriggerCharacters: ['(', ','],
    provideSignatureHelp(model, position) {
      const prefix = model.getValueInRange({ startLineNumber: Math.max(1, position.lineNumber - 12),
        startColumn: 1, endLineNumber: position.lineNumber, endColumn: position.column })
      const match = prefix.match(/\b(app\.[\w.]+)\(([^()]*)$/)
      if (!match) return null
      const tool = functions.get(match[1])
      if (!tool) return null
      const activeKeyword = match[2].match(/(?:^|,)\s*(\w+)\s*:\s*[^,]*$/)?.[1]
      const activeParameter = Math.max(0, tool.parameters.findIndex(p => p.name === activeKeyword))
      const parameters = tool.parameters.map(p => ({ label: `${p.name}: ${p.type}${p.required ? '' : ' (optional)'}`,
        documentation: p.description || '' }))
      return { value: { activeSignature: 0, activeParameter, signatures: [{
        label: `${match[1]}(${parameters.map(p => p.label).join(', ')})`, parameters,
        documentation: { value: tool.description + buildContractDocs(tool), isTrusted: false, supportHtml: false },
      }] }, dispose() {} }
    },
  })
  return { dispose() { completion.dispose(); signature.dispose() } }
}

/**
 * Register Ruby autocomplete for the permission-scoped `app.*` API.
 * Fetches the tool catalog once and provides dot-triggered completions.
 * Call in CodeConsole setup — auto-disposes on unmount.
 */
export function useCodeCompletions() {
  const page = usePage()
  let disposable: monaco.IDisposable | null = null
  let generation = 0
  // Inertia can switch workspaces without a page reload. Never reuse another
  // workspace's capability metadata or accept a late response from that scope.
  watch(() => (page.props.workspace as { id?: string } | undefined)?.id, async () => {
    const requestGeneration = ++generation
    disposable?.dispose()
    disposable = null
    try {
      const { data } = await wayfinderRequest<CatalogResponse>(toolCatalogIndex())
      if (requestGeneration === generation && data.groups.length > 0) {
        disposable = registerProvider(data)
      }
    } catch {
      // Discovery failures leave completions empty; execution still authorizes
      // every call independently in the PHP host.
    }
  }, { immediate: true })
  onBeforeUnmount(() => {
    generation++
    disposable?.dispose()
  })
}
