import { describe, expect, it } from 'vitest'
import { canCustomerChange, CUSTOMER_CHANGE_WINDOW_HOURS } from './changeWindow'

describe('canCustomerChange', () => {
  const baseTime = new Date('2026-11-02T08:00:00Z').getTime()

  it('exports CUSTOMER_CHANGE_WINDOW_HOURS = 2', () => {
    expect(CUSTOMER_CHANGE_WINDOW_HOURS).toBe(2)
  })

  it('allows confirmed appointment more than 2 hours away', () => {
    const appointment = {
      status: 'CONFIRMED' as const,
      start: '2026-11-02T10:30:00Z',
    }
    expect(canCustomerChange(appointment, baseTime)).toBe(true)
  })

  it('allows confirmed appointment exactly 2 hours away', () => {
    const appointment = {
      status: 'CONFIRMED' as const,
      start: '2026-11-02T10:00:00Z',
    }
    expect(canCustomerChange(appointment, baseTime)).toBe(true)
  })

  it('disallows confirmed appointment less than 2 hours away', () => {
    const appointment = {
      status: 'CONFIRMED' as const,
      start: '2026-11-02T09:59:00Z',
    }
    expect(canCustomerChange(appointment, baseTime)).toBe(false)
  })

  it('allows held appointment with less than 2 hours remaining', () => {
    const appointment = {
      status: 'HELD' as const,
      start: '2026-11-02T08:30:00Z',
    }
    expect(canCustomerChange(appointment, baseTime)).toBe(true)
  })

  it('disallows non-changeable statuses', () => {
    const statuses = ['CHECKED_IN', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED', 'NO_SHOW', 'EXPIRED'] as const
    for (const status of statuses) {
      const appointment = {
        status,
        start: '2026-11-02T12:00:00Z',
      }
      expect(canCustomerChange(appointment, baseTime)).toBe(false)
    }
  })

  it('returns false for invalid date strings', () => {
    const appointment = {
      status: 'CONFIRMED' as const,
      start: 'invalid-date',
    }
    expect(canCustomerChange(appointment, baseTime)).toBe(false)
  })
})
