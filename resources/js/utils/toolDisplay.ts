const TOOL_LABELS: Record<string, string> = {
  CreateTask: 'Create Task',
  CreateTaskStep: 'Create Task Step',
  CodeExec: 'Execute Ruby',
  CodeReadDoc: 'Read Code Mode API Doc',
  CodeSearchDocs: 'Search Code Mode API Docs',
  SetTaskStatus: 'Set Task Status',
  UpdateTask: 'Update Task',
}

export const humanizeToolName = (value: unknown, fallback = 'Tool'): string => {
  if (typeof value !== 'string' || value.length === 0) return fallback

  return TOOL_LABELS[value]
    ?? value
      .replace(/([a-z0-9])([A-Z])/g, '$1 $2')
      .replace(/[_-]+/g, ' ')
      .replace(/\s+/g, ' ')
      .trim()
      .replace(/\b\w/g, letter => letter.toUpperCase())
}

export const displayToolName = (metadata: Record<string, unknown> | null | undefined, fallback = 'Tool'): string => {
  const explicit = metadata?.tool_name
  if (typeof explicit === 'string' && explicit.length > 0) return explicit

  return humanizeToolName(metadata?.tool, fallback)
}
