import { isAxiosError } from 'axios'

// RFC 9457 body returned by every API error. Act on `code`, never on `title`.
export interface Problem {
  type: string
  title: string
  status: number
  code: string
  detail?: string
  correlationId?: string
  errors?: Record<string, string[]>
  [extra: string]: unknown
}

export function toProblem(error: unknown): Problem | null {
  if (!isAxiosError(error)) return null

  const data: unknown = error.response?.data
  if (typeof data === 'object' && data !== null && 'code' in data && 'status' in data) {
    return data as Problem
  }

  return null
}
