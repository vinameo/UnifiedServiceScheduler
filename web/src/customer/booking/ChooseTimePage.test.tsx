import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { renderRoute } from '../../test/render'
import { apiMock } from '../../test/apiMock'

describe('ChooseTimePage (TC-06-15)', () => {
  const dealershipId = '0192d111-0000-7000-8000-000000000001'
  const serviceId = '0192d333-0000-7000-8000-000000000003'
  const customerId = '0192d222-0000-7000-8000-000000000002'

  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date('2026-11-02T08:00:00Z'))
    sessionStorage.clear()
    apiMock.install()
    apiMock.reset()

    apiMock.get('/me', [
      200,
      {
        id: 10,
        email: 'hung@example.test',
        name: 'Nguyễn Văn Hùng',
        role: 'CUSTOMER',
        dealership_id: null,
        customer: {
          id: customerId,
          fullName: 'Nguyễn Văn Hùng',
          phone: '0903123218',
        },
      },
    ])

    apiMock.get('/dealerships', [
      200,
      {
        data: [
          {
            id: dealershipId,
            code: 'SGA-D7',
            name: 'Saigon Auto',
            branchName: 'District 7',
            timezone: 'Asia/Ho_Chi_Minh',
            phone: '028 3812 3456',
            slotStepMinutes: 15,
            openingHours: [
              { weekday: 1, opensAt: '08:00', closesAt: '17:00' },
              { weekday: 2, opensAt: '08:00', closesAt: '17:00' },
              { weekday: 3, opensAt: '08:00', closesAt: '17:00' },
              { weekday: 4, opensAt: '08:00', closesAt: '17:00' },
              { weekday: 5, opensAt: '08:00', closesAt: '17:00' },
              { weekday: 6, opensAt: '08:00', closesAt: '12:00' },
            ],
          },
        ],
      },
    ])

    apiMock.get(`/dealerships/${dealershipId}`, [
      200,
      {
        data: {
          id: dealershipId,
          code: 'SGA-D7',
          name: 'Saigon Auto',
          branchName: 'District 7',
          timezone: 'Asia/Ho_Chi_Minh',
          phone: '028 3812 3456',
          slotStepMinutes: 15,
          openingHours: [
            { weekday: 1, opensAt: '08:00', closesAt: '17:00' },
            { weekday: 2, opensAt: '08:00', closesAt: '17:00' },
            { weekday: 3, opensAt: '08:00', closesAt: '17:00' },
            { weekday: 4, opensAt: '08:00', closesAt: '17:00' },
            { weekday: 5, opensAt: '08:00', closesAt: '17:00' },
            { weekday: 6, opensAt: '08:00', closesAt: '12:00' },
          ],
        },
      },
    ])

    apiMock.get(`/dealerships/${dealershipId}/service-types`, [
      200,
      {
        data: [
          {
            id: serviceId,
            code: 'OIL_CHANGE',
            name: 'Oil change',
            description: 'Quick engine oil change',
            durationMinutes: 60,
            requiredLevel: 1,
            requiredBayType: 'QUICK_SERVICE',
            active: true,
          },
        ],
      },
    ])

    // Pre-populate draft with chosen service and initial date
    sessionStorage.setItem(
      'customer_booking_draft',
      JSON.stringify({
        dealershipId,
        serviceTypeId: serviceId,
        date: '2026-11-02',
        start: null,
      }),
    )
  })

  afterEach(() => {
    vi.useRealTimers()
    sessionStorage.clear()
    apiMock.restore()
  })

  it('TC-06-15 (AC-06.14, WEB-06.5): given fake API freeCount: 0, next: Fri 6 Nov 08:00; when render step 2, tap the chip; then "This day is fully booked", "Next available slot for Oil change:", "Fri 6 Nov · 08:00", chosen day becomes 6 Nov, time 08:00', async () => {
    // 2026-11-02 is fully booked (freeCount: 0)
    apiMock.get(new RegExp(`/dealerships/${dealershipId}/availability\\?.*date=2026-11-02`), [
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
          freeCount: 0,
          slots: [
            {
              start: '2026-11-02T08:00:00+07:00',
              end: '2026-11-02T09:00:00+07:00',
              available: false,
              reason: 'NO_BAY_AVAILABLE',
            },
            {
              start: '2026-11-02T08:15:00+07:00',
              end: '2026-11-02T09:15:00+07:00',
              available: false,
              reason: 'NO_TECHNICIAN_AVAILABLE',
            },
          ],
        },
      },
    ])

    // Next available slot endpoint returns Friday 6 Nov 08:00
    apiMock.get(new RegExp(`/dealerships/${dealershipId}/availability/next`), [
      200,
      {
        data: {
          nextAvailable: {
            start: '2026-11-06T08:00:00+07:00',
            end: '2026-11-06T09:00:00+07:00',
          },
        },
      },
    ])

    // When switched to 2026-11-06, availability has 08:00 free
    apiMock.get(new RegExp(`/dealerships/${dealershipId}/availability\\?.*date=2026-11-06`), [
      200,
      {
        data: {
          date: '2026-11-06',
          timezone: 'Asia/Ho_Chi_Minh',
          closed: false,
          opensAt: '08:00',
          closesAt: '17:00',
          stepMinutes: 15,
          durationMinutes: 60,
          freeCount: 1,
          slots: [
            {
              start: '2026-11-06T08:00:00+07:00',
              end: '2026-11-06T09:00:00+07:00',
              available: true,
              reason: null,
            },
          ],
        },
      },
    ])

    const user = userEvent.setup()
    renderRoute('/book/time')

    // Assert step 2 header
    await waitFor(() => {
      expect(screen.getByText('Step 2 of 3 · Choose date and time')).toBeInTheDocument()
    })

    // "This day is fully booked"
    await waitFor(() => {
      expect(screen.getByText('This day is fully booked')).toBeInTheDocument()
    })

    // "Next available slot for Oil change:"
    await waitFor(() => {
      expect(screen.getByText('Next available slot for Oil change:')).toBeInTheDocument()
    })

    // Chip formatted as "Fri 6 Nov · 08:00"
    const nextChip = await screen.findByRole('button', { name: 'Fri 6 Nov · 08:00' })
    expect(nextChip).toBeInTheDocument()

    // Tap the chip
    await user.click(nextChip)

    // The chosen day becomes 6 Nov, time 08:00
    await waitFor(() => {
      expect(screen.getByText('Friday, 6 Nov 2026')).toBeInTheDocument()
      expect(screen.getByText('Your choice Fri 6 Nov, 08:00')).toBeInTheDocument()
    })

    // Continue button is now enabled
    const continueBtn = screen.getByRole('button', { name: 'Continue' })
    expect(continueBtn).not.toBeDisabled()

    // Verify draft stored in sessionStorage
    const savedDraft = JSON.parse(sessionStorage.getItem('customer_booking_draft') ?? '{}')
    expect(savedDraft.date).toBe('2026-11-06')
    expect(savedDraft.start).toBe('2026-11-06T08:00:00+07:00')
  })

  it('renders "No free time in the next 7 days. Call {phone}." when no next available slot exists', async () => {
    apiMock.get(new RegExp(`/dealerships/${dealershipId}/availability\\?.*date=2026-11-02`), [
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
          freeCount: 0,
          slots: [
            {
              start: '2026-11-02T08:00:00+07:00',
              end: '2026-11-02T09:00:00+07:00',
              available: false,
              reason: 'NO_BAY_AVAILABLE',
            },
          ],
        },
      },
    ])

    apiMock.get(new RegExp(`/dealerships/${dealershipId}/availability/next`), [
      200,
      {
        data: {
          nextAvailable: null,
        },
      },
    ])

    renderRoute('/book/time')

    await waitFor(() => {
      expect(screen.getByText('This day is fully booked')).toBeInTheDocument()
    })

    await waitFor(() => {
      expect(
        screen.getByText('No free time in the next 7 days. Call 028 3812 3456.'),
      ).toBeInTheDocument()
    })
  })

  it('renders slot grid with counts and notice when day has free slots', async () => {
    apiMock.get(new RegExp(`/dealerships/${dealershipId}/availability\\?.*date=2026-11-02`), [
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
          freeCount: 2,
          slots: [
            {
              start: '2026-11-02T08:00:00+07:00',
              end: '2026-11-02T09:00:00+07:00',
              available: true,
              reason: null,
            },
            {
              start: '2026-11-02T08:15:00+07:00',
              end: '2026-11-02T09:15:00+07:00',
              available: false,
              reason: 'NO_BAY_AVAILABLE',
            },
            {
              start: '2026-11-02T13:00:00+07:00',
              end: '2026-11-02T14:00:00+07:00',
              available: true,
              reason: null,
            },
          ],
        },
      },
    ])

    const user = userEvent.setup()
    renderRoute('/book/time')

    await waitFor(() => {
      expect(screen.getByText(/Morning\s+1 free slots/)).toBeInTheDocument()
      expect(screen.getByText(/Afternoon\s+1 free slots/)).toBeInTheDocument()
    })

    // Struck-through slot is disabled
    const struckSlot = screen.getByRole('button', { name: '08:15' })
    expect(struckSlot).toBeDisabled()
    expect(struckSlot).toHaveAttribute('aria-disabled', 'true')

    // Notice under the grid
    expect(
      screen.getByText('Times can be taken by someone else until you confirm.'),
    ).toBeInTheDocument()

    // Select available slot 08:00
    const slot0800 = screen.getByRole('button', { name: '08:00' })
    await user.click(slot0800)

    expect(screen.getByText('Your choice Mon 2 Nov, 08:00')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Continue' })).not.toBeDisabled()
  })

  it('drops selected time if it is no longer available on date/service refresh', async () => {
    // Seed draft with 08:00 already selected
    sessionStorage.setItem(
      'customer_booking_draft',
      JSON.stringify({
        dealershipId,
        serviceTypeId: serviceId,
        date: '2026-11-02',
        start: '2026-11-02T08:00:00+07:00',
      }),
    )

    // But availability returns 08:00 as no longer available
    apiMock.get(new RegExp(`/dealerships/${dealershipId}/availability\\?.*date=2026-11-02`), [
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
              available: false,
              reason: 'NO_BAY_AVAILABLE',
            },
            {
              start: '2026-11-02T08:30:00+07:00',
              end: '2026-11-02T09:30:00+07:00',
              available: true,
              reason: null,
            },
          ],
        },
      },
    ])

    renderRoute('/book/time')

    await waitFor(() => {
      // Continue button should be disabled because 08:00 was dropped
      expect(screen.getByRole('button', { name: 'Continue' })).toBeDisabled()
      expect(screen.getByText('Choose a time to continue')).toBeInTheDocument()
    })
  })
})
