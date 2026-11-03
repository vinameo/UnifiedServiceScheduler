import { describe, it, expect } from 'vitest'
import { formatInZone } from './time'
import { localSlotsForDay } from './slots'
import { maskPhone } from './phone'

describe('TC-02-29: Web unit formatting and slot helpers', () => {
  it('formats dayLong in Asia/Ho_Chi_Minh for 2026-11-01T23:30:00Z', () => {
    const formatted = formatInZone('2026-11-01T23:30:00Z', 'Asia/Ho_Chi_Minh', 'dayLong')
    expect(formatted).toBe('Monday, 2 Nov 2026')
  })

  it('generates localSlotsForDay Saturday (08–12), 60 min duration -> 13 times from 08:00 to 11:00', () => {
    const slots = localSlotsForDay(
      '2026-11-07',
      { opensAt: '08:00', closesAt: '12:00' },
      60,
      15,
      'Asia/Ho_Chi_Minh',
    )

    expect(slots).toHaveLength(13)
    expect(slots[0].time).toBe('08:00')
    expect(slots[0].iso).toBe('2026-11-07T08:00:00+07:00')
    expect(slots[slots.length - 1].time).toBe('11:00')
    expect(slots[slots.length - 1].iso).toBe('2026-11-07T11:00:00+07:00')

    const times = slots.map((s) => s.time)
    expect(times).toEqual([
      '08:00',
      '08:15',
      '08:30',
      '08:45',
      '09:00',
      '09:15',
      '09:30',
      '09:45',
      '10:00',
      '10:15',
      '10:30',
      '10:45',
      '11:00',
    ])
  })

  it('masks phone number correctly', () => {
    expect(maskPhone('0903123218')).toBe('0903 *** 218')
    expect(maskPhone('')).toBe('')
    expect(maskPhone(null)).toBe('')
    expect(maskPhone('12345')).toBe('12345')
  })
})

