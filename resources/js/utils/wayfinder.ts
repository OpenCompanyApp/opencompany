import axios, { type AxiosRequestConfig } from 'axios'
import { apiFetch } from '@/utils/apiFetch'
import type { RouteDefinition } from '@/wayfinder'

type WayfinderDefinition = RouteDefinition<any>

type WayfinderAxiosConfig = Omit<AxiosRequestConfig, 'method' | 'url'>

function routeMethod(route: WayfinderDefinition): string | undefined {
  if ('method' in route) {
    return route.method
  }

  return route.methods[0]
}

/**
 * Send an Axios request using a generated Wayfinder route/action definition.
 *
 * The generated object is the authority for URL and HTTP method. Callers only
 * provide request data, query params, headers, and response handling options.
 */
export function wayfinderRequest<TResponse = unknown>(
  route: WayfinderDefinition,
  config: WayfinderAxiosConfig = {}
) {
  return axios.request<TResponse>({
    ...config,
    method: routeMethod(route),
    url: route.url,
  })
}

export function wayfinderUrl(route: WayfinderDefinition): string {
  return route.url
}

export function wayfinderFetch(
  route: WayfinderDefinition,
  init: RequestInit = {}
): Promise<Response> {
  return apiFetch(route.url, {
    ...init,
    method: init.method ?? routeMethod(route)?.toUpperCase(),
  })
}
