const getCsrfToken = () =>
  document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''

// Small fetch wrapper for API calls made outside Inertia forms. It applies the
// headers Laravel expects and converts non-2xx responses into thrown errors.
export async function apiFetch(url: string, options: RequestInit = {}): Promise<Response> {
  const headers = new Headers(options.headers)
  if (!headers.has('X-CSRF-TOKEN')) {
    headers.set('X-CSRF-TOKEN', getCsrfToken())
  }
  if (!headers.has('X-Requested-With')) {
    headers.set('X-Requested-With', 'XMLHttpRequest')
  }
  const response = await fetch(url, { ...options, headers })
  if (!response.ok) {
    throw new Error(`Request failed: ${response.status} ${response.statusText}`)
  }
  return response
}
