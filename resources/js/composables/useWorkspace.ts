import { computed } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import {
  activity,
  approvals,
  automation,
  calendar,
  chat,
  dashboard,
  docs,
  files,
  integrations,
  lists,
  org,
  settings,
  tables,
  tasks,
} from '@/routes'
import { show as showAgentRoute } from '@/routes/agent'
import { show as showApprovalRoute } from '@/routes/approvals'
import { edit as editAutomationRoute } from '@/routes/automation'
import { codeConsole as developerCodeConsoleRoute, tools as developerToolsRoute } from '@/routes/developer'
import { index as messagesRoute, show as showMessageRoute } from '@/routes/messages'
import { edit as editProfileRoute, show as showProfileRoute } from '@/routes/profile'
import { show as showTableRoute } from '@/routes/tables'
import { show as showTaskRoute } from '@/routes/tasks'
import type { RouteDefinition, RouteQueryOptions } from '@/wayfinder'

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

type WorkspaceMemberLinkTarget = {
  id: string | number
  type?: string | null
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

  const workspaceRouteParams = <T extends Record<string, unknown> = Record<string, never>>(
    params?: T
  ): T & { workspace_slug: string } => {
    const slug = workspace.value?.slug ?? 'default'

    return {
      ...(params ?? {} as T),
      workspace_slug: slug,
    }
  }

  const workspaceRoute = <TArgs extends Record<string, unknown>>(
    definition: (args: TArgs & { workspace_slug: string }, options?: RouteQueryOptions) => RouteDefinition<any>,
    params?: TArgs,
    options?: RouteQueryOptions
  ) => definition(workspaceRouteParams(params), options)

  const workspaceRouteUrl = <TArgs extends Record<string, unknown>>(
    route: { url: (args: TArgs & { workspace_slug: string }, options?: RouteQueryOptions) => string },
    params?: TArgs,
    options?: RouteQueryOptions
  ) => route.url(workspaceRouteParams(params), options)

  const visitWorkspaceRoute = <TArgs extends Record<string, unknown>>(
    route: (args: TArgs & { workspace_slug: string }, options?: RouteQueryOptions) => RouteDefinition<any>,
    params?: TArgs,
    routeOptions?: RouteQueryOptions,
    visitOptions?: Record<string, unknown>
  ) => {
    router.visit(workspaceRoute(route, params, routeOptions).url, visitOptions)
  }

  const dashboardUrl = (options?: RouteQueryOptions) => dashboard.url(workspaceRouteParams(), options)
  const chatUrl = (options?: RouteQueryOptions) => chat.url(workspaceRouteParams(), options)
  const tasksUrl = (options?: RouteQueryOptions) => tasks.url(workspaceRouteParams(), options)
  const listsUrl = (options?: RouteQueryOptions) => lists.url(workspaceRouteParams(), options)
  const docsUrl = (options?: RouteQueryOptions) => docs.url(workspaceRouteParams(), options)
  const filesUrl = (options?: RouteQueryOptions) => files.url(workspaceRouteParams(), options)
  const activityUrl = (options?: RouteQueryOptions) => activity.url(workspaceRouteParams(), options)
  const approvalsUrl = (options?: RouteQueryOptions) => approvals.url(workspaceRouteParams(), options)
  const approvalUrl = (id: string | number, options?: RouteQueryOptions) => showApprovalRoute.url(workspaceRouteParams({ id }), options)
  const automationUrl = (options?: RouteQueryOptions) => automation.url(workspaceRouteParams(), options)
  const orgUrl = (options?: RouteQueryOptions) => org.url(workspaceRouteParams(), options)
  const settingsUrl = (options?: RouteQueryOptions) => settings.url(workspaceRouteParams(), options)
  const integrationsUrl = (options?: RouteQueryOptions) => integrations.url(workspaceRouteParams(), options)
  const calendarUrl = (options?: RouteQueryOptions) => calendar.url(workspaceRouteParams(), options)
  const tablesUrl = (options?: RouteQueryOptions) => tables.url(workspaceRouteParams(), options)
  const agentUrl = (id: string | number, options?: RouteQueryOptions) => showAgentRoute.url(workspaceRouteParams({ id }), options)
  const profileUrl = (id: string | number, options?: RouteQueryOptions) => showProfileRoute.url(workspaceRouteParams({ id }), options)
  const profileEditUrl = (options?: RouteQueryOptions) => editProfileRoute.url(workspaceRouteParams(), options)
  const memberUrl = (member: WorkspaceMemberLinkTarget, options?: RouteQueryOptions) => (
    member.type === 'agent' ? agentUrl(member.id, options) : profileUrl(member.id, options)
  )
  const taskUrl = (id: string | number, options?: RouteQueryOptions) => showTaskRoute.url(workspaceRouteParams({ id }), options)
  const tableUrl = (id: string | number, options?: RouteQueryOptions) => showTableRoute.url(workspaceRouteParams({ id }), options)
  const automationEditUrl = (id: string | number, options?: RouteQueryOptions) => editAutomationRoute.url(workspaceRouteParams({ id }), options)
  const messagesUrl = (options?: RouteQueryOptions) => messagesRoute.url(workspaceRouteParams(), options)
  const messageUrl = (id: string | number, options?: RouteQueryOptions) => showMessageRoute.url(workspaceRouteParams({ id }), options)
  const developerToolsUrl = (options?: RouteQueryOptions) => developerToolsRoute.url(workspaceRouteParams(), options)
  const developerCodeConsoleUrl = (options?: RouteQueryOptions) => developerCodeConsoleRoute.url(workspaceRouteParams(), options)

  /**
   * Switch to a different workspace.
   */
  const switchWorkspace = (slug: string) => {
    router.visit(dashboard.url(slug))
  }

  return {
    workspace,
    role,
    workspaces,
    isAdmin,
    isMember,
    workspaceRouteParams,
    workspaceRoute,
    workspaceRouteUrl,
    visitWorkspaceRoute,
    dashboardUrl,
    chatUrl,
    tasksUrl,
    listsUrl,
    docsUrl,
    filesUrl,
    activityUrl,
    approvalsUrl,
    approvalUrl,
    automationUrl,
    orgUrl,
    settingsUrl,
    integrationsUrl,
    calendarUrl,
    tablesUrl,
    agentUrl,
    profileUrl,
    profileEditUrl,
    memberUrl,
    taskUrl,
    tableUrl,
    automationEditUrl,
    messagesUrl,
    messageUrl,
    developerToolsUrl,
    developerCodeConsoleUrl,
    switchWorkspace,
  }
}
