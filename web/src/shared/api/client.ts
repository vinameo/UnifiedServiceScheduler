import axios from 'axios'
import { recordServerDate } from '../time/useServerClock'

// Sanctum SPA mode: same-site cookies, and the XSRF-TOKEN cookie sent back as a header.
export const api = axios.create({
  baseURL: '/api/v1',
  withCredentials: true,
  withXSRFToken: true,
  headers: { Accept: 'application/json' },
})

// Call before sign-in and sign-up so Laravel sets the XSRF-TOKEN cookie.
export function ensureCsrfCookie() {
  return api.get('/sanctum/csrf-cookie', { baseURL: '' })
}

export type UnauthenticatedHandler = (targetUrl: string) => void

let onUnauthenticated: UnauthenticatedHandler = (targetUrl: string) => {
  if (typeof window !== 'undefined' && window.location) {
    window.location.assign(targetUrl)
  }
}

export function setUnauthenticatedHandler(handler: UnauthenticatedHandler) {
  onUnauthenticated = handler
}

let queryCacheClearer: (() => void) | null = null

export function setQueryCacheClearer(clearer: () => void) {
  queryCacheClearer = clearer
}

function extractDateHeader(headers: unknown): string | null {
  if (!headers || typeof headers !== 'object') {
    return null
  }
  const h = headers as Record<string, unknown> & { get?: (name: string) => unknown }
  const val = (typeof h.get === 'function' ? h.get('date') : undefined) ?? h['date'] ?? h['Date']
  return typeof val === 'string' && val.length > 0 ? val : null
}

api.interceptors.response.use(
  (response) => {
    const dateHeader = extractDateHeader(response.headers)
    if (dateHeader) {
      recordServerDate(dateHeader)
    }
    return response
  },
  (error) => {
    if (axios.isAxiosError(error) && error.response) {
      const dateHeader = extractDateHeader(error.response.headers)
      if (dateHeader) {
        recordServerDate(dateHeader)
      }

      if (error.response.status === 401) {
        const url = error.config?.url ?? ''
        // A 401 UNAUTHENTICATED from any route other than /me and /auth/* (WEB-00.3)
        const isMeOrAuth = url.includes('/me') || url.includes('/auth/')
        if (!isMeOrAuth) {
          queryCacheClearer?.()

          const pathname = typeof window !== 'undefined' ? window.location.pathname : '/'
          const search = typeof window !== 'undefined' ? window.location.search : ''
          const currentPath = pathname + search
          const isStaffArea = pathname.startsWith('/staff')
          const signInPath = isStaffArea ? '/staff/sign-in' : '/sign-in'
          const redirectUrl = `${signInPath}?returnTo=${encodeURIComponent(currentPath)}`

          onUnauthenticated(redirectUrl)
        }
      }
    }
    return Promise.reject(error)
  },
)
