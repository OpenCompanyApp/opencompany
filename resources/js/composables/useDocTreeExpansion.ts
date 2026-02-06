const STORAGE_KEY = 'doc-tree-expanded'

let expandedIds: Set<string> | null = null

function getExpandedIds(): Set<string> {
  if (!expandedIds) {
    try {
      const stored = localStorage.getItem(STORAGE_KEY)
      expandedIds = stored ? new Set(JSON.parse(stored)) : new Set()
    } catch {
      expandedIds = new Set()
    }
  }
  return expandedIds
}

function save() {
  localStorage.setItem(STORAGE_KEY, JSON.stringify([...getExpandedIds()]))
}

export function useDocTreeExpansion() {
  return {
    isExpanded: (id: string) => getExpandedIds().has(id),
    setExpanded: (id: string, value: boolean) => {
      if (value) getExpandedIds().add(id)
      else getExpandedIds().delete(id)
      save()
    },
  }
}
