const STORAGE_KEY = 'doc-tree-expanded'

// Module-level cache keeps expansion state shared between multiple tree
// components without introducing a full store.
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
  // Persist IDs as an array because Set is not JSON-serializable.
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
