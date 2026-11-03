import { type AxiosRequestConfig, isAxiosError } from 'axios'
import { api } from '../api/client'
import { type Problem, toProblem } from '../api/problem'

export interface IdempotentPostOptions extends AxiosRequestConfig {
  ifMatch?: string | number
}

export type IdempotentPostResult<T> =
  | { kind: 'ok'; status: number; data: T; headers: Record<string, string> }
  | { kind: 'problem'; status: number; problem: Problem }
  | { kind: 'no-response'; error: unknown }

export async function idempotentPost<T = unknown>(
  url: string,
  body: unknown,
  key: string,
  options: IdempotentPostOptions = {},
): Promise<IdempotentPostResult<T>> {
  const { ifMatch, headers = {}, timeout = 15000, ...rest } = options

  const reqHeaders: Record<string, string> = {
    'Idempotency-Key': key,
    ...(headers as Record<string, string>),
  }

  if (ifMatch !== undefined && ifMatch !== null) {
    const etagStr =
      typeof ifMatch === 'number'
        ? `"${ifMatch}"`
        : String(ifMatch).startsWith('"')
          ? String(ifMatch)
          : `"${ifMatch}"`
    reqHeaders['If-Match'] = etagStr
  }

  try {
    const res = await api.post<T>(url, body, {
      ...rest,
      timeout,
      headers: reqHeaders,
    })

    return {
      kind: 'ok',
      status: res.status,
      data: res.data,
      headers: res.headers as unknown as Record<string, string>,
    }
  } catch (error) {
    if (isAxiosError(error)) {
      if (!error.response) {
        // No response = axios error with no response (timeout, network drop)
        return {
          kind: 'no-response',
          error,
        }
      }

      const problem = toProblem(error) ?? {
        type: 'about:blank',
        title: error.response.statusText || 'Error',
        status: error.response.status,
        code:
          ((error.response.data as Record<string, unknown>)?.code as string) ??
          'UNKNOWN_ERROR',
      }

      return {
        kind: 'problem',
        status: error.response.status,
        problem,
      }
    }

    return {
      kind: 'no-response',
      error,
    }
  }
}
