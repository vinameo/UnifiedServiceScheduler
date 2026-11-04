import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { renderRoute } from '../../test/render'
import { apiMock } from '../../test/apiMock'
import { resetServerClock } from '../../shared/time/useServerClock'

describe('HoldPage (TC-10-14, AC-10.1 web)', () => {
  const dealershipId = '0192d111-0000-7000-8000-000000000001'
  const customerId = '0192d222-0000-7000-8000-000000000002'
  const serviceId = '0192d333-0000-7000-8000-000000000003'
  const holdId = '0192d444-0000-7000-8000-000000000004'

  beforeEach(() => {
    sessionStorage.clear()
    resetServerClock()
    apiMock.install()
    apiMock.reset()

    // Browser clock is set to 08:02:00 (2 minutes ahead of server clock 08:00:00)
    vi.useFakeTimers({ toFake: ['Date', 'setInterval', 'clearInterval'] })
    vi.setSystemTime(new Date('2026-11-02T08:02:00Z'))

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

    apiMock.get('/me/vehicles', [
      200,
      {
        data: [
          {
            id: '0192d555-0000-7000-8000-000000000005',
            licencePlate: '51H-123.45',
            make: 'Toyota',
            model: 'Vios',
            year: 2021,
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
          openingHours: [{ weekday: 1, opensAt: '08:00', closesAt: '17:00' }],
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

    apiMock.get(new RegExp(`/dealerships/${dealershipId}/availability`), [
      200,
      {
        data: {
          date: '2026-11-02',
          timezone: 'Asia/Ho_Chi_Minh',
          closed: false,
          freeCount: 5,
          slots: [
            {
              start: '2026-11-02T10:00:00+07:00',
              end: '2026-11-02T11:00:00+07:00',
              available: true,
              reason: null,
            },
          ],
        },
      },
    ])
  })

  afterEach(() => {
    vi.useRealTimers()
    resetServerClock()
  })

  it('TC-10-14 (AC-10.1 web): tap "Hold and continue" navigates to hold page and shows 05:00 countdown using server clock', async () => {
    // Server time is 08:00:00 GMT (Date header)
    // Hold expires 5 minutes later: 08:05:00 GMT
    const serverDateHeader = 'Mon, 02 Nov 2026 08:00:00 GMT'
    const expiresAt = '2026-11-02T08:05:00+00:00'

    apiMock.post('/appointments', [
      201,
      {
        id: holdId,
        reference: 'APT-20261102-0001',
        status: 'HELD',
        start: '2026-11-02T10:00:00+07:00',
        end: '2026-11-02T11:00:00+07:00',
        expiresAt,
        version: 1,
        dealership: {
          id: dealershipId,
          name: 'Saigon Auto',
          branchName: 'District 7',
          timezone: 'Asia/Ho_Chi_Minh',
        },
        serviceType: {
          id: serviceId,
          code: 'OIL_CHANGE',
          name: 'Oil change',
          durationMinutes: 60,
        },
        technician: {
          id: 'tech-1',
          name: 'An',
        },
        bay: {
          id: 'bay-1',
          name: 'Bay 1',
        },
        customer: {
          id: customerId,
          fullName: 'Nguyễn Văn Hùng',
          phone: '0903123218',
        },
        vehicle: null,
        reminderOptIn: true,
        source: 'CUSTOMER',
        createdAt: '2026-11-02T08:00:00+07:00',
      },
      {
        Date: serverDateHeader,
        ETag: '"1"',
      },
    ])

    // Pre-populate draft with chosen dealership, service, date and start time
    sessionStorage.setItem(
      'customer_booking_draft',
      JSON.stringify({
        dealershipId,
        serviceTypeId: serviceId,
        date: '2026-11-02',
        start: '2026-11-02T10:00:00+07:00',
      }),
    )

    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime })
    renderRoute('/book/time')

    // Wait for the slot to be rendered
    await waitFor(() => {
      expect(screen.getByText('Step 2 of 3 · Choose date and time')).toBeInTheDocument()
    })

    // Find "Hold and continue" button
    const holdBtn = await screen.findByRole('button', { name: /Hold and continue/i })
    expect(holdBtn).not.toBeDisabled()

    // Tap "Hold and continue"
    await user.click(holdBtn)

    // Navigates to the hold page: /book/hold/:id
    await waitFor(() => {
      expect(screen.getByText('Step 3 of 3 · Contact details')).toBeInTheDocument()
      expect(screen.getByText('Slot held for you')).toBeInTheDocument()
    })

    // Countdown shows 05:00 (calculated against server time), not 03:00 (which would be browser clock)
    expect(screen.getByText('05:00')).toBeInTheDocument()
    expect(screen.queryByText('03:00')).not.toBeInTheDocument()
  })
})
