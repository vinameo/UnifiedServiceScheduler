import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { act, screen, waitFor } from '@testing-library/react'
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

  describe('TC-10-16 (AC-10.12): expired hold screen behavior', () => {
    const expiresAt = '2026-11-02T08:05:00+00:00'
    const serverDateHeader = 'Mon, 02 Nov 2026 08:00:00 GMT'

    const mockHoldAppointment = {
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
    }

    it('shows expired state when countdown reaches 0 and tapping "Pick a time again" returns to /book/time keeping entered vehicle', async () => {
      vi.setSystemTime(new Date('2026-11-02T08:00:00Z'))

      apiMock.get(`/appointments/${holdId}`, [
        200,
        mockHoldAppointment,
        {
          Date: serverDateHeader,
          ETag: '"1"',
        },
      ])

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
      renderRoute(`/book/hold/${holdId}`)

      await waitFor(() => {
        expect(screen.getByText('Step 3 of 3 · Contact details')).toBeInTheDocument()
        expect(screen.getByText('Slot held for you')).toBeInTheDocument()
      })

      // Select "Add a new vehicle" and type vehicle details
      const addVehicleRadio = screen.getByRole('radio', { name: /Add a new vehicle/i })
      await user.click(addVehicleRadio)

      const plateInput = screen.getByLabelText(/Licence plate/i)
      const makeInput = screen.getByLabelText(/Make/i)
      const modelInput = screen.getByLabelText(/Model/i)

      await user.type(plateInput, '51H-999.99')
      await user.type(makeInput, 'Honda')
      await user.type(modelInput, 'Civic')

      // Advance fake timers by 301 seconds (past the 5 minute hold expiry)
      await act(async () => {
        vi.advanceTimersByTime(301_000)
      })

      // Verify expired state with exact copy
      await waitFor(() => {
        expect(screen.getByRole('heading', { name: 'Your hold has expired' })).toBeInTheDocument()
        expect(
          screen.getByText(
            'Someone may have booked the 10:00 slot. Pick a time again to continue; the details you entered are kept.',
          ),
        ).toBeInTheDocument()
      })

      // Inputs and confirm button are disabled
      expect(screen.getByRole('button', { name: 'Confirm appointment' })).toBeDisabled()
      expect(plateInput).toBeDisabled()
      expect(makeInput).toBeDisabled()
      expect(modelInput).toBeDisabled()

      // Tapping "Pick a time again"
      const pickAgainBtn = screen.getByRole('button', { name: 'Pick a time again' })
      await user.click(pickAgainBtn)

      // Navigates back to /book/time
      await waitFor(() => {
        expect(screen.getByText('Step 2 of 3 · Choose date and time')).toBeInTheDocument()
      })

      // Vehicle details kept in draft, start is null
      const draft = JSON.parse(sessionStorage.getItem('customer_booking_draft') || '{}')
      expect(draft.start).toBeNull()
      expect(draft.dealershipId).toBe(dealershipId)
      expect(draft.serviceTypeId).toBe(serviceId)
      expect(draft.date).toBe('2026-11-02')
      expect(draft.newVehicle).toEqual({
        licencePlate: '51H-999.99',
        make: 'Honda',
        model: 'Civic',
        year: null,
      })
    })

    it('shows expired state when confirm returns 410 HOLD_EXPIRED and preserves vehicle in draft', async () => {
      vi.setSystemTime(new Date('2026-11-02T08:00:00Z'))

      apiMock.get(`/appointments/${holdId}`, [
        200,
        mockHoldAppointment,
        {
          Date: serverDateHeader,
          ETag: '"1"',
        },
      ])

      // Confirm endpoint returns 410 HOLD_EXPIRED
      apiMock.post(`/appointments/${holdId}/confirm`, [
        410,
        {
          type: '/problems/hold-expired',
          title: 'Hold has expired.',
          status: 410,
          code: 'HOLD_EXPIRED',
        },
      ])

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
      renderRoute(`/book/hold/${holdId}`)

      await waitFor(() => {
        expect(screen.getByText('Step 3 of 3 · Contact details')).toBeInTheDocument()
      })

      // Select existing vehicle
      const existingVehicleRadio = await screen.findByRole('radio', { name: /Toyota Vios · 51H-123.45/i })
      await user.click(existingVehicleRadio)

      // Tap Confirm appointment
      const confirmBtn = screen.getByRole('button', { name: 'Confirm appointment' })
      await user.click(confirmBtn)

      // Expired state shown with exact copy
      await waitFor(() => {
        expect(screen.getByRole('heading', { name: 'Your hold has expired' })).toBeInTheDocument()
        expect(
          screen.getByText(
            'Someone may have booked the 10:00 slot. Pick a time again to continue; the details you entered are kept.',
          ),
        ).toBeInTheDocument()
      })

      // Tapping "Pick a time again"
      const pickAgainBtn = screen.getByRole('button', { name: 'Pick a time again' })
      await user.click(pickAgainBtn)

      // Navigates to /book/time
      await waitFor(() => {
        expect(screen.getByText('Step 2 of 3 · Choose date and time')).toBeInTheDocument()
      })

      // Selected vehicle preserved in draft
      const draft = JSON.parse(sessionStorage.getItem('customer_booking_draft') || '{}')
      expect(draft.start).toBeNull()
      expect(draft.vehicleId).toBe('0192d555-0000-7000-8000-000000000005')
    })

    it('shows expired state when confirm returns 412 with appointment status EXPIRED and preserves vehicle in draft', async () => {
      vi.setSystemTime(new Date('2026-11-02T08:00:00Z'))

      apiMock.get(`/appointments/${holdId}`, [
        200,
        mockHoldAppointment,
        {
          Date: serverDateHeader,
          ETag: '"1"',
        },
      ])

      // Confirm endpoint returns 412 APPOINTMENT_MODIFIED with status EXPIRED
      apiMock.post(`/appointments/${holdId}/confirm`, [
        412,
        {
          type: '/problems/appointment-modified',
          title: 'This appointment was modified by another request.',
          status: 412,
          code: 'APPOINTMENT_MODIFIED',
          appointment: {
            id: holdId,
            status: 'EXPIRED',
            version: 2,
          },
        },
      ])

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
      renderRoute(`/book/hold/${holdId}`)

      await waitFor(() => {
        expect(screen.getByText('Step 3 of 3 · Contact details')).toBeInTheDocument()
      })

      // Select existing vehicle
      const existingVehicleRadio = await screen.findByRole('radio', { name: /Toyota Vios · 51H-123.45/i })
      await user.click(existingVehicleRadio)

      // Tap Confirm appointment
      const confirmBtn = screen.getByRole('button', { name: 'Confirm appointment' })
      await user.click(confirmBtn)

      // Expired state shown with exact copy
      await waitFor(() => {
        expect(screen.getByRole('heading', { name: 'Your hold has expired' })).toBeInTheDocument()
        expect(
          screen.getByText(
            'Someone may have booked the 10:00 slot. Pick a time again to continue; the details you entered are kept.',
          ),
        ).toBeInTheDocument()
      })

      // Tapping "Pick a time again"
      const pickAgainBtn = screen.getByRole('button', { name: 'Pick a time again' })
      await user.click(pickAgainBtn)

      // Navigates to /book/time
      await waitFor(() => {
        expect(screen.getByText('Step 2 of 3 · Choose date and time')).toBeInTheDocument()
      })

      const draft = JSON.parse(sessionStorage.getItem('customer_booking_draft') || '{}')
      expect(draft.start).toBeNull()
      expect(draft.vehicleId).toBe('0192d555-0000-7000-8000-000000000005')
    })
  })
})
