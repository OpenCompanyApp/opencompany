import editorWorkerUrl from 'monaco-editor/esm/vs/editor/editor.worker?worker&url'

let editorWorkerBlobUrl: string | null = null

const monacoWorkerUrl = () => {
  if (editorWorkerBlobUrl) return editorWorkerBlobUrl

  // Valet serves the app from opencompany.test while Vite serves modules from
  // its dev origin. Wrapping the worker import in a same-origin blob avoids the
  // browser rejecting Monaco's worker constructor during local development.
  const workerSource = `import ${JSON.stringify(editorWorkerUrl)};`
  editorWorkerBlobUrl = URL.createObjectURL(new Blob([workerSource], { type: 'text/javascript' }))

  return editorWorkerBlobUrl
}

// Must be set BEFORE monaco-editor is imported — it checks this on load
self.MonacoEnvironment = {
  getWorker() {
    // Production assets are served from the same origin, so the emitted
    // worker URL is both simpler and more reliable there. Keep the blob
    // indirection exclusively for Valet/Vite development, where the page and
    // module server intentionally have different origins.
    const workerUrl = import.meta.env.DEV ? monacoWorkerUrl() : editorWorkerUrl

    return new Worker(workerUrl, { type: 'module', name: 'monaco-editor-worker' })
  },
}

// Import the editor surface and contributions explicitly. The umbrella
// `monaco-editor` entry registers every bundled language and makes Code Mode
// users download dozens of irrelevant tokenizers.
import * as monaco from 'monaco-editor/esm/vs/editor/editor.api.js'
import 'monaco-editor/esm/vs/editor/editor.all.js'
import 'monaco-editor/esm/vs/basic-languages/ruby/ruby.contribution.js'

let initialized = false

export function setupMonaco() {
  if (initialized) return
  initialized = true

  // Olympus Light — warm cream palette matching the app
  monaco.editor.defineTheme('olympus-light', {
    base: 'vs',
    inherit: true,
    rules: [
      { token: 'comment', foreground: '9a9488', fontStyle: 'italic' },
      { token: 'keyword', foreground: '7c5cbf' },
      { token: 'string', foreground: '2a7e4f' },
      { token: 'number', foreground: 'b35c00' },
      { token: 'type', foreground: '2563eb' },
      { token: 'identifier', foreground: '1a1a1a' },
      { token: 'delimiter', foreground: '6b6b6b' },
      { token: 'operator', foreground: '6b6b6b' },
    ],
    colors: {
      'editor.background': '#FEFDFB',
      'editor.foreground': '#1a1a1a',
      'editor.lineHighlightBackground': '#f5f2ed',
      'editor.selectionBackground': '#d4cfca80',
      'editor.inactiveSelectionBackground': '#d4cfca40',
      'editorLineNumber.foreground': '#b5b0a8',
      'editorLineNumber.activeForeground': '#6b6b6b',
      'editorCursor.foreground': '#1a1a1a',
      'editorIndentGuide.background': '#e5e2dd',
      'editorIndentGuide.activeBackground': '#d4cfca',
      'editorBracketMatch.background': '#d4cfca60',
      'editorBracketMatch.border': '#b5b0a8',
      'editor.findMatchBackground': '#f5c04280',
      'editor.findMatchHighlightBackground': '#f5c04240',
      'editorWidget.background': '#faf8f5',
      'editorWidget.border': '#e5e2dd',
      'input.background': '#FEFDFB',
      'input.border': '#e5e2dd',
      'scrollbarSlider.background': '#d4cfca40',
      'scrollbarSlider.hoverBackground': '#d4cfca80',
      'scrollbarSlider.activeBackground': '#b5b0a8',
    },
  })

  // Olympus Dark — deep dark with warm accent hints
  monaco.editor.defineTheme('olympus-dark', {
    base: 'vs-dark',
    inherit: true,
    rules: [
      { token: 'comment', foreground: '6b6b6b', fontStyle: 'italic' },
      { token: 'keyword', foreground: 'b49ee8' },
      { token: 'string', foreground: '6ec99a' },
      { token: 'number', foreground: 'e5a04a' },
      { token: 'type', foreground: '60a5fa' },
      { token: 'identifier', foreground: 'e5e5e5' },
      { token: 'delimiter', foreground: '8b8b8b' },
      { token: 'operator', foreground: '8b8b8b' },
    ],
    colors: {
      'editor.background': '#1f1f1f',
      'editor.foreground': '#e5e5e5',
      'editor.lineHighlightBackground': '#292929',
      'editor.selectionBackground': '#3a3a3a80',
      'editor.inactiveSelectionBackground': '#3a3a3a40',
      'editorLineNumber.foreground': '#4a4a4a',
      'editorLineNumber.activeForeground': '#8b8b8b',
      'editorCursor.foreground': '#e5e5e5',
      'editorIndentGuide.background': '#2a2a2a',
      'editorIndentGuide.activeBackground': '#3a3a3a',
      'editorBracketMatch.background': '#3a3a3a60',
      'editorBracketMatch.border': '#5a5a5a',
      'editor.findMatchBackground': '#b3860080',
      'editor.findMatchHighlightBackground': '#b3860040',
      'editorWidget.background': '#252525',
      'editorWidget.border': '#3a3a3a',
      'input.background': '#1f1f1f',
      'input.border': '#3a3a3a',
      'scrollbarSlider.background': '#3a3a3a40',
      'scrollbarSlider.hoverBackground': '#3a3a3a80',
      'scrollbarSlider.activeBackground': '#5a5a5a',
    },
  })
}

// Expose for debugging/testing
if (import.meta.env.DEV) {
  ;(window as any).__monaco = monaco
}

export { monaco }
