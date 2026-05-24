import axios, { type AxiosRequestConfig } from 'axios'
import type { RouteDefinition } from '@/wayfinder'

type WayfinderDefinition = RouteDefinition<any>

type WayfinderAxiosConfig = Omit<AxiosRequestConfig, 'method' | 'url'>

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
    method: 'method' in route ? route.method : undefined,
    url: route.url,
  })
}

export function wayfinderUrl(route: WayfinderDefinition): string {
  return route.url
}
