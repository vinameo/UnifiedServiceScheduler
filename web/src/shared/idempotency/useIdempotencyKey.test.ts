import { act, renderHook } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { shouldKeepKey, useIdempotencyKey } from './useIdempotencyKey'

describe('useIdempotencyKey (TC-04-35, WEB-04.1)', () => {
  it('handles input changes, reset, and settling across different response statuses', () => {
    const inputA = { customerId: 'cust-1', start: '2026-11-20T09:00:00Z' }
    const inputASame = { start: '2026-11-20T09:00:00Z', customerId: 'cust-1' } // key order different, same data
    const inputB = { customerId: 'cust-1', start: '2026-11-20T10:00:00Z' }

    // 1. Initial render with input A
    const { result, rerender } = renderHook(
      ({ input }: { input: unknown }) => useIdempotencyKey(input),
      { initialProps: { input: inputA } },
    )

    const keyA1 = result.current.key
    expect(keyA1).toBeDefined()
    expect(keyA1).toMatch(/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i)

    // 2. Rerender with identical input A (even different key ordering in object) -> same key
    rerender({ input: inputASame })
    expect(result.current.key).toBe(keyA1)

    // 3. Rerender with different input B -> new key
    rerender({ input: inputB })
    const keyB1 = result.current.key
    expect(keyB1).not.toBe(keyA1)
    expect(keyB1).toMatch(/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i)

    // 4. Call reset() -> new key
    act(() => {
      result.current.reset()
    })
    const keyAfterReset = result.current.key
    expect(keyAfterReset).not.toBe(keyB1)

    // 5. settle() with no-response (network error / undefined) -> key kept
    act(() => {
      result.current.settle(undefined)
    })
    expect(result.current.key).toBe(keyAfterReset)

    act(() => {
      result.current.settle(new Error('Network error'))
    })
    expect(result.current.key).toBe(keyAfterReset)

    // 6. settle() with 503 -> key kept
    act(() => {
      result.current.settle(503)
    })
    expect(result.current.key).toBe(keyAfterReset)

    act(() => {
      result.current.settle({ status: 503, title: 'Service Unavailable' })
    })
    expect(result.current.key).toBe(keyAfterReset)

    // 7. settle() with 409 REQUEST_IN_PROGRESS -> key kept
    act(() => {
      result.current.settle({ status: 409, code: 'REQUEST_IN_PROGRESS' })
    })
    expect(result.current.key).toBe(keyAfterReset)

    act(() => {
      result.current.settle({
        response: {
          status: 409,
          data: { code: 'REQUEST_IN_PROGRESS' },
        },
      })
    })
    expect(result.current.key).toBe(keyAfterReset)

    // 8. settle() with 412 (APPOINTMENT_MODIFIED) -> new key
    act(() => {
      result.current.settle({ status: 412, code: 'APPOINTMENT_MODIFIED' })
    })
    const keyAfter412 = result.current.key
    expect(keyAfter412).not.toBe(keyAfterReset)

    // 9. settle() with 409 NO_AVAILABILITY -> new key
    act(() => {
      result.current.settle({ status: 409, code: 'NO_AVAILABILITY' })
    })
    const keyAfter409NoAvail = result.current.key
    expect(keyAfter409NoAvail).not.toBe(keyAfter412)

    // 10. settle() with 201 (CREATED) -> new key
    act(() => {
      result.current.settle(201)
    })
    const keyAfter201 = result.current.key
    expect(keyAfter201).not.toBe(keyAfter409NoAvail)
  })

  it('shouldKeepKey correctly classifies responses', () => {
    // Network / no response
    expect(shouldKeepKey(undefined)).toBe(true)
    expect(shouldKeepKey(null)).toBe(true)
    expect(shouldKeepKey({})).toBe(true)

    // 5xx
    expect(shouldKeepKey(500)).toBe(true)
    expect(shouldKeepKey(502)).toBe(true)
    expect(shouldKeepKey(503)).toBe(true)
    expect(shouldKeepKey({ status: 500 })).toBe(true)

    // 409 REQUEST_IN_PROGRESS
    expect(shouldKeepKey({ status: 409, code: 'REQUEST_IN_PROGRESS' })).toBe(true)

    // Other 4xx
    expect(shouldKeepKey(400)).toBe(false)
    expect(shouldKeepKey(401)).toBe(false)
    expect(shouldKeepKey(404)).toBe(false)
    expect(shouldKeepKey(412)).toBe(false)
    expect(shouldKeepKey(422)).toBe(false)
    expect(shouldKeepKey({ status: 409, code: 'NO_AVAILABILITY' })).toBe(false)

    // 2xx
    expect(shouldKeepKey(200)).toBe(false)
    expect(shouldKeepKey(201)).toBe(false)
    expect(shouldKeepKey(204)).toBe(false)
  })
})
