import { computed } from 'vue'
import { usePage, router } from '@inertiajs/vue3'

interface Workspace {
  id: string
  name: string
  slug: string
  icon: string
  color: string
  owner_id: string | null
}

interface WorkspaceListItem {
  id: string
  name: string
  slug: string
  icon: string
  color: string
}

export const useWorkspace = () => {
  const page = usePage()

  const workspace = computed<Workspace | null>(
    () => (page.props.workspace as Workspace) ?? null
  )

  const role = computed<string | null>(
    () => (page.props.workspaceRole as string) ?? null
  )

  const workspaces = computed<WorkspaceListItem[]>(
    () => (page.props.workspaces as WorkspaceListItem[]) ?? []
  )

  const isAdmin = computed(() => role.value === 'admin')
  const isMember = computed(() => role.value === 'member')

  /**
   * Build a workspace-prefixed path.
   * workspacePath('/chat') → '/w/my-workspace/chat'
   */
  const workspacePath = (path: string): string => {
    const slug = workspace.value?.slug ?? 'default'
    const cleanPath = path.startsWith('/') ? path : `/${path}`
    return `/w/${slug}${cleanPath}`
  }

  /**
   * Navigate to a workspace-prefixed path using Inertia.
   */
  const visitWorkspacePath = (path: string, options?: Record<string, unknown>) => {
    router.visit(workspacePath(path), options)
  }

  /**
   * Switch to a different workspace.
   */
  const switchWorkspace = (slug: string) => {
    router.visit(`/w/${slug}`)
  }

  return {
    workspace,
    role,
    workspaces,
    isAdmin,
    isMember,
    workspacePath,
    visitWorkspacePath,
    switchWorkspace,
  }
}
