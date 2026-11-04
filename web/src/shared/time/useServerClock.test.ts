import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import type { AxiosResponse, InternalAxiosRequestConfig } from 'axios'
import {
  getServerNow,
  getServerOffsetMs,
  recordServerDate,
  resetServerClock,
  useServerClock,
} from './useServerClock'
import { api } from '../api/client'

interface InterceptorHandler {
  fulfilled?: (res: AxiosResponse) => AxiosResponse | Promise<AxiosResponse>
  rejected?: (err: unknown) => unknown
}

describe('useServerClock (WEB-10.1, D-10.11)', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    resetServerClock()
  })

  afterEach(() => {
    resetServerClock()
    vi.useRealTimers()
  })

  it('TC-10-18: given Response Date 10:00:00, browser 09:58:00; when useServerClock().now(); then 10:00:00', () => {
    // Browser is set to 09:58:00 UTC
    const browserTime = new Date('2026-11-04T09:58:00.000Z')
    vi.setSystemTime(browserTime)

    // Server sends Date header: 10:00:00 GMT (+2 minutes ahead of browser)
    const serverDateHeader = 'Wed, 04 Nov 2026 10:00:00 GMT'
    recordServerDate(serverDateHeader)

    expect(getServerOffsetMs()).toBe(120_000)

    const clock = useServerClock()
    const now = clock.now()

    // Tolerance ±1000ms
    const expectedTime = new Date('2026-11-04T10:00:00.000Z').getTime()
    expect(Math.abs(now.getTime() - expectedTime)).toBeLessThanOrEqual(1000)
    expect(now.toISOString()).toBe('2026-11-04T10:00:00.000Z')

    // Also verify standalone getServerNow()
    expect(getServerNow().toISOString()).toBe('2026-11-04T10:00:00.000Z')
  })

  it('advances in lockstep with the browser clock after offset is set', () => {
    vi.setSystemTime(new Date('2026-11-04T09:58:00.000Z'))
    recordServerDate('Wed, 04 Nov 2026 10:00:00 GMT')

    // Advance browser time by 45 seconds
    vi.advanceTimersByTime(45_000)

    const { now } = useServerClock()
    expect(now().toISOString()).toBe('2026-11-04T10:00:45.000Z')
  })

  it('handles browser ahead of server (negative offset)', () => {
    // Browser at 10:05:00 UTC, Server at 10:00:00 UTC
    vi.setSystemTime(new Date('2026-11-04T10:05:00.000Z'))
    recordServerDate('Wed, 04 Nov 2026 10:00:00 GMT')

    expect(getServerOffsetMs()).toBe(-300_000)

    const { now } = useServerClock()
    expect(now().toISOString()).toBe('2026-11-04T10:00:00.000Z')
  })

  it('defaults to browser time when no server Date header has been recorded', () => {
    vi.setSystemTime(new Date('2026-11-04T08:30:00.000Z'))

    expect(getServerOffsetMs()).toBe(0)

    const { now } = useServerClock()
    expect(now().toISOString()).toBe('2026-11-04T08:30:00.000Z')
  })

  it('ignores invalid Date strings without corrupting current offset', () => {
    vi.setSystemTime(new Date('2026-11-04T09:58:00.000Z'))
    recordServerDate('Wed, 04 Nov 2026 10:00:00 GMT')

    // Invalid header
    recordServerDate('invalid-date-string')

    expect(getServerOffsetMs()).toBe(120_000)
    expect(useServerClock().now().toISOString()).toBe('2026-11-04T10:00:00.000Z')
  })

  it('records Date header through the axios client interceptor on success', async () => {
    vi.setSystemTime(new Date('2026-11-04T09:58:00.000Z'))

    // Trigger an interceptor by calling client handlers
    const mockResponse: AxiosResponse = {
      data: { ok: true },
      status: 200,
      statusText: 'OK',
      headers: { date: 'Wed, 04 Nov 2026 10:00:00 GMT' },
      config: {} as InternalAxiosRequestConfig,
    }

    // Run through the registered interceptor
    const responseManager = api.interceptors.response as unknown as {
      handlers: (InterceptorHandler | null)[]
    }
    for (const h of responseManager.handlers) {
      if (typeof h?.fulfilled === 'function') {
        h.fulfilled(mockResponse)
      }
    }

    expect(getServerOffsetMs()).toBe(120_000)
    expect(useServerClock().now().toISOString()).toBe('2026-11-04T10:00:00.000Z')
  })

  it('records Date header through the axios client interceptor on error responses', async () => {
    vi.setSystemTime(new Date('2026-11-04T09:58:00.000Z'))

    const mockError = {
      isAxiosError: true,
      response: {
        data: { error: 'Bad Request' },
        status: 400,
        statusText: 'Bad Request',
        headers: { date: 'Wed, 04 Nov 2026 10:00:00 GMT' },
        config: { url: '/appointments' },
      },
    }

    const responseManager = api.interceptors.response as unknown as {
      handlers: (InterceptorHandler | null)[]
    }
    for (const h of responseManager.handlers) {
      if (typeof h?.rejected === 'function') {
        try {
          await h.rejected(mockError)
        } catch {
          // Expected to reject
        }
      }
    }

    expect(getServerOffsetMs()).toBe(120_000)
    expect(useServerClock().now().toISOString()).toBe('2026-11-04T10:00:00.000Z')
  })
})
