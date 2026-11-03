import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { apiMock } from '../../test/apiMock'
import { getAvailability, getNextAvailable, reasonTooltip } from './availability'

describe('availability api (WEB-06.1, WEB-06.2)', () => {
  beforeEach(() => {
    apiMock.install()
    apiMock.reset()
  })

  afterEach(() => {
    apiMock.restore()
  })

  it('reasonTooltip returns friendly explanation', () => {
    expect(reasonTooltip('PAST')).toBe('This time has passed')
    expect(reasonTooltip('NO_TECHNICIAN_AVAILABLE')).toBe('No qualified technician free')
    expect(reasonTooltip('NO_BAY_AVAILABLE')).toBe('No bay free')
    expect(reasonTooltip('NO_QUALIFIED_TECHNICIAN')).toBe('No technician for this service')
    expect(reasonTooltip('CUSTOM_REASON')).toBe('CUSTOM_REASON')
    expect(reasonTooltip(null)).toBeUndefined()
  })

  it('getNextAvailable fetches /availability/next and returns nextAvailable slot', async () => {
    apiMock.get(
      new RegExp('/dealerships/dealership-1/availability/next'),
      [
        200,
        {
          data: {
            nextAvailable: {
              start: '2026-11-06T08:00:00+07:00',
              end: '2026-11-06T09:00:00+07:00',
            },
          },
        },
      ],
    )

    const result = await getNextAvailable({
      dealershipId: 'dealership-1',
      serviceTypeId: 'service-1',
      after: '2026-11-02T09:00:00+07:00',
    })

    expect(result.nextAvailable).toEqual({
      start: '2026-11-06T08:00:00+07:00',
      end: '2026-11-06T09:00:00+07:00',
    })
  })

  it('getNextAvailable returns null when no slots available', async () => {
    apiMock.get(
      new RegExp('/dealerships/dealership-1/availability/next'),
      [
        200,
        {
          data: {
            nextAvailable: null,
          },
        },
      ],
    )

    const result = await getNextAvailable({
      dealershipId: 'dealership-1',
      serviceTypeId: 'service-1',
      after: '2026-11-02T09:00:00+07:00',
    })

    expect(result.nextAvailable).toBeNull()
  })

  it('getAvailability fetches /availability for a given date', async () => {
    apiMock.get(
      new RegExp('/dealerships/dealership-1/availability'),
      [
        200,
        {
          data: {
            date: '2026-11-02',
            timezone: 'Asia/Ho_Chi_Minh',
            closed: false,
            opensAt: '08:00',
            closesAt: '17:00',
            stepMinutes: 15,
            durationMinutes: 60,
            freeCount: 1,
            slots: [
              {
                start: '2026-11-02T08:00:00+07:00',
                end: '2026-11-02T09:00:00+07:00',
                available: true,
                reason: null,
              },
            ],
          },
        },
      ],
    )

    const result = await getAvailability({
      dealershipId: 'dealership-1',
      serviceTypeId: 'service-1',
      date: '2026-11-02',
    })

    expect(result.date).toBe('2026-11-02')
    expect(result.freeCount).toBe(1)
    expect(result.slots).toHaveLength(1)
  })
})
