import { useCallback, useState } from 'react'

function generateUuid(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0
    const v = c === 'x' ? r : (r & 0x3) | 0x8
    return v.toString(16)
  })
}

function stableStringify(value: unknown): string {
  if (value === null || typeof value !== 'object') {
    return JSON.stringify(value) ?? 'undefined'
  }
  if (Array.isArray(value)) {
    return '[' + value.map(stableStringify).join(',') + ']'
  }
  const obj = value as Record<string, unknown>
  const keys = Object.keys(obj).sort()
  return '{' + keys.map((k) => JSON.stringify(k) + ':' + stableStringify(obj[k])).join(',') + '}'
}

export function shouldKeepKey(result?: unknown): boolean {
  if (result === undefined || result === null) {
    // No response / network error
    return true
  }

  let status: number | undefined
  let code: string | undefined

  if (typeof result === 'number') {
    status = result
  } else if (typeof result === 'object') {
    const res = result as Record<string, unknown>
    // AxiosError-like structure
    if ('response' in res && res.response && typeof res.response === 'object') {
      const resp = res.response as Record<string, unknown>
      if (typeof resp.status === 'number') {
        status = resp.status
      }
      if (resp.data && typeof resp.data === 'object') {
        const data = resp.data as Record<string, unknown>
        if (typeof data.code === 'string') {
          code = data.code
        }
      }
    }

    if (status === undefined && typeof res.status === 'number') {
      status = res.status
    }
    if (code === undefined && typeof res.code === 'string') {
      code = res.code
    }
    if (res.problem && typeof res.problem === 'object') {
      const prob = res.problem as Record<string, unknown>
      if (typeof prob.status === 'number') status = prob.status
      if (typeof prob.code === 'string') code = prob.code
    }
  }

  // If no status is present, it's treated as network error/no response
  if (status === undefined) {
    return true
  }

  // 5xx: keep key
  if (status >= 500) {
    return true
  }

  // 409 REQUEST_IN_PROGRESS: keep key
  if (status === 409 && code === 'REQUEST_IN_PROGRESS') {
    return true
  }

  // All other responses (2xx, 412, 409 NO_AVAILABILITY, 422, etc.) reset key
  return false
}

export interface UseIdempotencyKeyReturn {
  key: string
  reset: () => void
  settle: (result?: unknown) => void
}

interface KeyState {
  input: string
  key: string
}

/**
 * Keeps one idempotency key per attempt of an action (WEB-04.1, README D-G.15).
 * Reuses the key only to resend after no response, a 5xx, or 409 REQUEST_IN_PROGRESS.
 * Any other answer resets the key so trying again never replays a saved answer.
 */
export function useIdempotencyKey(actionInput?: unknown): UseIdempotencyKeyReturn {
  const currentSerialized = stableStringify(actionInput)
  const [state, setState] = useState<KeyState>(() => ({
    input: currentSerialized,
    key: generateUuid(),
  }))

  let currentKey = state.key
  if (state.input !== currentSerialized) {
    const newKey = generateUuid()
    currentKey = newKey
    setState({
      input: currentSerialized,
      key: newKey,
    })
  }

  const reset = useCallback(() => {
    setState((prev) => ({
      ...prev,
      key: generateUuid(),
    }))
  }, [])

  const settle = useCallback((result?: unknown) => {
    if (!shouldKeepKey(result)) {
      setState((prev) => ({
        ...prev,
        key: generateUuid(),
      }))
    }
  }, [])

  return {
    key: currentKey,
    reset,
    settle,
  }
}
