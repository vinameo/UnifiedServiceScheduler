import { describe, expect, it } from 'vitest'
import { diffAppointment, formatStatus } from './diffAppointment'

describe('diffAppointment (WEB-04.6, TC-04-38)', () => {
  it('TC-04-38: returns Status: Confirmed → Cancelled when only status changed', () => {
    const before = {
      status: 'CONFIRMED',
      start: '09:00',
      technician: 'An',
    }
    const latest = {
      status: 'CANCELLED',
      start: '09:00',
      technician: 'An',
      version: 4,
    }

    const diffs = diffAppointment(before, latest)

    expect(diffs).toHaveLength(1)
    expect(diffs[0]).toEqual({
      field: 'status',
      label: 'Status',
      from: 'Confirmed',
      to: 'Cancelled',
      text: 'Status: Confirmed → Cancelled',
    })
  })

  it('preserves field ordering: status, time, technician, bay, vehicle', () => {
    const before = {
      status: 'CONFIRMED',
      start: '09:00',
      end: '10:00',
      technician: { name: 'An' },
      bay: { code: 'K1' },
      vehicle: { licencePlate: '51A-123.45' },
    }
    const latest = {
      status: 'RESCHEDULED',
      start: '10:30',
      end: '11:30',
      technician: { name: 'Dũng' },
      bay: { code: 'K2' },
      vehicle: { licencePlate: '51B-678.90' },
    }

    const diffs = diffAppointment(before, latest)

    expect(diffs.map((d) => d.field)).toEqual([
      'status',
      'time',
      'technician',
      'bay',
      'vehicle',
    ])
    expect(diffs[0].text).toBe('Status: Confirmed → Rescheduled')
    expect(diffs[1].text).toBe('Time: 09:00 - 10:00 → 10:30 - 11:30')
    expect(diffs[2].text).toBe('Technician: An → Dũng')
    expect(diffs[3].text).toBe('Bay: K1 → K2')
    expect(diffs[4].text).toBe('Vehicle: 51A-123.45 → 51B-678.90')
  })

  it('returns empty array when appointments are identical', () => {
    const appt = {
      status: 'CONFIRMED',
      start: '09:00',
      technician: 'An',
      bay: 'K1',
    }
    expect(diffAppointment(appt, appt)).toEqual([])
  })

  it('formatStatus handles all common appointment statuses', () => {
    expect(formatStatus('CONFIRMED')).toBe('Confirmed')
    expect(formatStatus('CANCELLED')).toBe('Cancelled')
    expect(formatStatus('HELD')).toBe('Held')
    expect(formatStatus('COMPLETED')).toBe('Completed')
    expect(formatStatus('IN_SERVICE')).toBe('In service')
    expect(formatStatus('NO_SHOW')).toBe('No show')
  })
})

