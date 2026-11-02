import axios from 'axios'

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
