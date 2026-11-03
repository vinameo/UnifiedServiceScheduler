import { describe, it, expect } from 'vitest'
import { safeReturnTo } from './returnTo'

describe('safeReturnTo', () => {
  it('accepts safe relative paths', () => {
    expect(safeReturnTo('/staff/appointments')).toBe('/staff/appointments')
    expect(safeReturnTo('/staff/workshop?date=2026-11-02')).toBe('/staff/workshop?date=2026-11-02')
  })

  it('rejects open redirects and falls back', () => {
    expect(safeReturnTo('//evil.com')).toBe('/staff')
    expect(safeReturnTo('/\\evil.com')).toBe('/staff')
    expect(safeReturnTo('https://evil.com')).toBe('/staff')
    expect(safeReturnTo(null)).toBe('/staff')
    expect(safeReturnTo(undefined, '/custom-fallback')).toBe('/custom-fallback')
  })
})

