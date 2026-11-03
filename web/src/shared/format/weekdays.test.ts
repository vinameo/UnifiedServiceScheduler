import { describe, it, expect } from 'vitest'
import {
  groupWeekdays,
  formatWeekdays,
  formatTimezoneOffset,
} from './weekdays'

describe('groupWeekdays (TC-01-14)', () => {
  it('TC-01-14: groups [1..6 08–17] into "Mon–Sat"', () => {
    const rows = [
      { weekday: 1, opensAt: '08:00', closesAt: '17:00' },
      { weekday: 2, opensAt: '08:00', closesAt: '17:00' },
      { weekday: 3, opensAt: '08:00', closesAt: '17:00' },
      { weekday: 4, opensAt: '08:00', closesAt: '17:00' },
      { weekday: 5, opensAt: '08:00', closesAt: '17:00' },
      { weekday: 6, opensAt: '08:00', closesAt: '17:00' },
    ]

    const grouped = groupWeekdays(rows)

    expect(grouped).toHaveLength(1)
    expect(grouped[0].days).toBe('Mon–Sat')
    expect(grouped[0].label).toBe('Mon–Sat')
    expect(grouped[0].hours).toBe('08:00–17:00')
    expect(grouped[0].weekdays).toEqual([1, 2, 3, 4, 5, 6])
  })

  it('TC-01-14: groups [1,2,4] with same hours into "Mon–Tue, Thu"', () => {
    const rows = [
      { weekday: 1, opensAt: '08:00', closesAt: '17:00' },
      { weekday: 2, opensAt: '08:00', closesAt: '17:00' },
      { weekday: 4, opensAt: '08:00', closesAt: '17:00' },
    ]

    const grouped = groupWeekdays(rows)

    expect(grouped).toHaveLength(1)
    expect(grouped[0].days).toBe('Mon–Tue, Thu')
    expect(grouped[0].label).toBe('Mon–Tue, Thu')
    expect(grouped[0].hours).toBe('08:00–17:00')
    expect(grouped[0].weekdays).toEqual([1, 2, 4])
  })

  it('TC-01-14: splits different hours into separate rows', () => {
    const rows = [
      { weekday: 1, opensAt: '08:00', closesAt: '17:00' },
      { weekday: 2, opensAt: '08:00', closesAt: '17:00' },
      { weekday: 3, opensAt: '08:00', closesAt: '17:00' },
      { weekday: 4, opensAt: '08:00', closesAt: '17:00' },
      { weekday: 5, opensAt: '08:00', closesAt: '17:00' },
      { weekday: 6, opensAt: '08:00', closesAt: '12:00' },
    ]

    const grouped = groupWeekdays(rows)

    expect(grouped).toHaveLength(2)
    expect(grouped[0].days).toBe('Mon–Fri')
    expect(grouped[0].hours).toBe('08:00–17:00')
    expect(grouped[0].weekdays).toEqual([1, 2, 3, 4, 5])

    expect(grouped[1].days).toBe('Sat')
    expect(grouped[1].hours).toBe('08:00–12:00')
    expect(grouped[1].weekdays).toEqual([6])
  })

  it('merges long names and closed days with includeClosed: true', () => {
    const rows = [
      { weekday: 1, opensAt: '08:00', closesAt: '17:00' },
      { weekday: 2, opensAt: '08:00', closesAt: '17:00' },
      { weekday: 3, opensAt: '08:00', closesAt: '17:00' },
      { weekday: 4, opensAt: '08:00', closesAt: '17:00' },
      { weekday: 5, opensAt: '08:00', closesAt: '17:00' },
      { weekday: 6, opensAt: '08:00', closesAt: '12:00' },
    ]

    const grouped = groupWeekdays(rows, { format: 'long', includeClosed: true })

    expect(grouped).toHaveLength(3)
    expect(grouped[0].days).toBe('Monday to Friday')
    expect(grouped[0].hours).toBe('08:00–17:00')
    expect(grouped[0].isClosed).toBe(false)

    expect(grouped[1].days).toBe('Saturday')
    expect(grouped[1].hours).toBe('08:00–12:00')
    expect(grouped[1].isClosed).toBe(false)

    expect(grouped[2].days).toBe('Sunday')
    expect(grouped[2].hours).toBe('Closed')
    expect(grouped[2].isClosed).toBe(true)
  })

  it('normalizes 2-digit hour strings like "08" and "17"', () => {
    const rows = [
      { weekday: 1, opensAt: '08', closesAt: '17' },
      { weekday: 2, opensAt: '08', closesAt: '17' },
    ]

    const grouped = groupWeekdays(rows)
    expect(grouped[0].hours).toBe('08:00–17:00')
  })
})

describe('formatWeekdays', () => {
  it('formats single days', () => {
    expect(formatWeekdays([6], 'short')).toBe('Sat')
    expect(formatWeekdays([7], 'short')).toBe('Sun')
    expect(formatWeekdays([6], 'long')).toBe('Saturday')
    expect(formatWeekdays([7], 'long')).toBe('Sunday')
  })

  it('formats non-consecutive days', () => {
    expect(formatWeekdays([1, 3, 5], 'short')).toBe('Mon, Wed, Fri')
    expect(formatWeekdays([1, 3, 5], 'long')).toBe('Monday, Wednesday, Friday')
  })
})

describe('formatTimezoneOffset', () => {
  it('formats Asia/Ho_Chi_Minh to UTC+7', () => {
    const offset = formatTimezoneOffset('Asia/Ho_Chi_Minh')
    expect(offset).toBe('UTC+7')
  })
})
