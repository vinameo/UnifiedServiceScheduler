import type { AxiosResponse, InternalAxiosRequestConfig } from 'axios'
import { AxiosError, AxiosHeaders } from 'axios'
import { api } from '../shared/api/client'

export type MockReply = [status: number, data?: unknown, headers?: Record<string, string>]
export type MockHandler = (config: InternalAxiosRequestConfig) => MockReply | Promise<MockReply>

interface RouteMock {
  method: string
  url: string | RegExp
  handler: MockHandler
}

class ApiMock {
  private routes: RouteMock[] = []
  private originalAdapter = api.defaults.adapter

  install(): this {
    api.defaults.adapter = async (config: InternalAxiosRequestConfig): Promise<AxiosResponse> => {
      const method = (config.method ?? 'get').toUpperCase()
      const url = config.url ?? ''

      const matched = this.routes.find((route) => {
        if (route.method !== method) return false
        if (typeof route.url === 'string') {
          return route.url === url || url.endsWith(route.url)
        }
        return route.url.test(url)
      })

      if (!matched) {
        const error = new AxiosError(
          `Unhandled request: ${method} ${url}`,
          AxiosError.ERR_BAD_REQUEST,
          config,
          null,
          {
            data: { code: 'NOT_FOUND', title: 'Unhandled request in apiMock' },
            status: 404,
            statusText: 'Not Found',
            headers: new AxiosHeaders(),
            config,
          },
        )
        return Promise.reject(error)
      }

      const [status, data = null, headers = {}] = await matched.handler(config)
      const axiosHeaders = new AxiosHeaders(headers)

      const response: AxiosResponse = {
        data,
        status,
        statusText: status >= 200 && status < 300 ? 'OK' : 'Error',
        headers: axiosHeaders,
        config,
      }

      if (status >= 200 && status < 300) {
        return response
      }

      const error = new AxiosError(
        `Request failed with status code ${status}`,
        AxiosError.ERR_BAD_REQUEST,
        config,
        null,
        response,
      )
      return Promise.reject(error)
    }

    return this
  }

  on(method: string, url: string | RegExp, handler: MockHandler): this {
    this.routes.push({ method: method.toUpperCase(), url, handler })
    return this
  }

  get(url: string | RegExp, reply: MockReply | MockHandler): this {
    const handler = typeof reply === 'function' ? reply : () => reply
    return this.on('GET', url, handler)
  }

  post(url: string | RegExp, reply: MockReply | MockHandler): this {
    const handler = typeof reply === 'function' ? reply : () => reply
    return this.on('POST', url, handler)
  }

  put(url: string | RegExp, reply: MockReply | MockHandler): this {
    const handler = typeof reply === 'function' ? reply : () => reply
    return this.on('PUT', url, handler)
  }

  patch(url: string | RegExp, reply: MockReply | MockHandler): this {
    const handler = typeof reply === 'function' ? reply : () => reply
    return this.on('PATCH', url, handler)
  }

  delete(url: string | RegExp, reply: MockReply | MockHandler): this {
    const handler = typeof reply === 'function' ? reply : () => reply
    return this.on('DELETE', url, handler)
  }

  replyProblem(
    status: number,
    code: string,
    title: string,
    extra: Record<string, unknown> = {},
    headers: Record<string, string> = {},
  ): MockReply {
    return [
      status,
      {
        type: `/problems/${code.toLowerCase().replace(/_/g, '-')}`,
        title,
        status,
        code,
        ...extra,
      },
      { 'content-type': 'application/problem+json', ...headers },
    ]
  }

  reset(): void {
    this.routes = []
  }

  restore(): void {
    this.reset()
    api.defaults.adapter = this.originalAdapter
  }
}

export const apiMock = new ApiMock()

