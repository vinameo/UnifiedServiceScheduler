import { describe, it, expect } from 'vitest'
import { formatAppointmentSlot, formatInZone } from './time'

describe('time formatting', () => {
  const timeZone = 'Asia/Ho_Chi_Minh'
  const start = '2026-11-03T09:00:00+07:00'
  const end = '2026-11-03T10:00:00+07:00'

  it('formats time pattern', () => {
    expect(formatInZone(start, timeZone, 'time')).toBe('09:00')
    expect(formatInZone(end, timeZone, 'time')).toBe('10:00')
  })

  it('formats dayShort pattern', () => {
    expect(formatInZone(start, timeZone, 'dayShort')).toBe('Tue 3 Nov')
  })

  it('formats dayLong pattern', () => {
    expect(formatInZone(start, timeZone, 'dayLong')).toBe('Tuesday, 3 Nov 2026')
  })

  it('formats range pattern', () => {
    expect(formatInZone(start, timeZone, 'range', end)).toBe('09:00–10:00')
  })

  it('formats appointment slot {dayShort} · {range}', () => {
    expect(formatAppointmentSlot(start, end, timeZone)).toBe('Tue 3 Nov · 09:00–10:00')
  })
})
