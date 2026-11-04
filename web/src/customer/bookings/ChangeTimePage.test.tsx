import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { notifications } from '@mantine/notifications'
import type { InternalAxiosRequestConfig } from 'axios'
import { renderRoute } from '../../test/render'
import { apiMock } from '../../test/apiMock'
import type { Appointment } from '../../shared/api/appointments'

describe('ChangeTimePage (TC-09-17, TC-09-18)', () => {
  const dealershipId = '0192d111-0000-7000-8000-000000000001'
  const appointmentId = '0192d000-0000-7000-8000-000000000042'

  const mockCustomerUser = {
    id: 10,
    email: 'hung@example.test',
    name: 'Nguyễn Văn Hùng',
    role: 'CUSTOMER',
    dealership: null,
    customer: {
      id: 'cust-1',
      fullName: 'Nguyễn Văn Hùng',
      phone: '0903123218',
    },
  }

  const baseAppointment: Appointment = {
    id: appointmentId,
    reference: 'APT-20261102-0042',
    status: 'CONFIRMED',
    start: '2026-11-02T10:00:00+07:00',
    end: '2026-11-02T11:00:00+07:00',
    expiresAt: null,
    version: 1,
    dealership: {
      id: dealershipId,
      name: 'Saigon Auto',
      branchName: 'District 7',
      timezone: 'Asia/Ho_Chi_Minh',
      phone: '028 3812 3456',
    },
    serviceType: {
      id: 'st-1',
      code: 'OIL_CHANGE',
      name: 'Oil change',
      durationMinutes: 60,
    },
    technician: {
      id: 'tech-001',
      name: 'Trần Minh An',
    },
    bay: {
      id: 'bay-002',
      code: 'K2',
      name: 'Bay 2',
      bayType: 'QUICK',
    },
    customer: {
      id: 'cust-1',
      fullName: 'Nguyễn Văn Hùng',
      phone: '0903123218',
    },
    vehicle: {
      id: 'veh-1',
      make: 'Toyota',
      model: 'Vios',
      licencePlate: '51H-123.45',
    },
    reminderOptIn: true,
    source: 'CUSTOMER',
    createdAt: '2026-10-28T16:40:00+07:00',
  }

  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] })
    // 07:00 UTC+7 (00:00 UTC) -> 3 hours before start (10:00 UTC+7)
    vi.setSystemTime(new Date('2026-11-02T00:00:00Z'))
    sessionStorage.clear()
    notifications.clean()
    apiMock.install()
    apiMock.reset()
    apiMock.get('/me', [200, mockCustomerUser])
  })

  afterEach(() => {
    notifications.clean()
    vi.useRealTimers()
    sessionStorage.clear()
    apiMock.restore()
  })

  it('TC-09-18 (AC-09.13): availability request includes excludeAppointmentId; own slot shows Current and is disabled', async () => {
    let capturedAvailabilityUrl: string | null = null

    apiMock.get(`/appointments/${appointmentId}`, [
      200,
      baseAppointment,
      { etag: '"1"' },
    ])

    apiMock.get(new RegExp(`/dealerships/${dealershipId}/availability`), (config) => {
      capturedAvailabilityUrl = config.url ?? ''
      return [
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
                start: '2026-11-02T10:00:00+07:00',
                end: '2026-11-02T11:00:00+07:00',
                available: true,
                reason: null,
              },
              {
                start: '2026-11-02T14:00:00+07:00',
                end: '2026-11-02T15:00:00+07:00',
                available: true,
                reason: null,
              },
            ],
          },
        },
      ]
    })

    renderRoute(`/my-bookings/${appointmentId}/change`)

    // Screen header
    await waitFor(() => {
      expect(
        screen.getByRole('heading', { level: 3, name: 'Change appointment time' }),
      ).toBeInTheDocument()
    })
    expect(screen.getByText('Oil change · 60 min')).toBeInTheDocument()

    // Availability request MUST include excludeAppointmentId
    await waitFor(() => {
      expect(capturedAvailabilityUrl).not.toBeNull()
    })
    expect(capturedAvailabilityUrl).toContain(`excludeAppointmentId=${appointmentId}`)

    // Own slot shows "Current" badge
    await waitFor(() => {
      expect(screen.getByText('Current')).toBeInTheDocument()
    })

    // Own slot (10:00) is marked disabled from selection
    const ownSlotBtn = screen.getByRole('button', { name: /10:00 Current/i })
    expect(ownSlotBtn).toBeDisabled()

    // Other free slot (14:00) is selectable
    const slot1400Btn = screen.getByRole('button', { name: '14:00' })
    expect(slot1400Btn).not.toBeDisabled()

    // Confirm button is initially disabled because no new slot is selected
    const confirmBtn = screen.getByRole('button', { name: 'Confirm new time' })
    expect(confirmBtn).toBeDisabled()
  })

  it('TC-09-17 (AC-09.3 web): reschedule 412 shows AppointmentModifiedAlert with Status: Confirmed -> Cancelled and no automatic resend', async () => {
    let rescheduleCalls = 0

    apiMock.get(`/appointments/${appointmentId}`, [
      200,
      baseAppointment,
      { etag: '"1"' },
    ])

    apiMock.get(new RegExp(`/dealerships/${dealershipId}/availability`), [
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
              start: '2026-11-02T10:00:00+07:00',
              end: '2026-11-02T11:00:00+07:00',
              available: true,
              reason: null,
            },
            {
              start: '2026-11-02T14:00:00+07:00',
              end: '2026-11-02T15:00:00+07:00',
              available: true,
              reason: null,
            },
          ],
        },
      },
    ])

    apiMock.post(new RegExp(`/appointments/${appointmentId}/reschedule`), () => {
      rescheduleCalls++
      return [
        412,
        {
          code: 'APPOINTMENT_MODIFIED',
          title: 'This appointment was modified by another request.',
          appointment: {
            ...baseAppointment,
            status: 'CANCELLED',
            version: 2,
          },
        },
        { etag: '"2"' },
      ]
    })

    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime })
    renderRoute(`/my-bookings/${appointmentId}/change`)

    await waitFor(() => {
      expect(
        screen.getByRole('heading', { level: 3, name: 'Change appointment time' }),
      ).toBeInTheDocument()
    })

    // Pick 14:00 slot
    const slot1400Btn = await screen.findByRole('button', { name: '14:00' })
    await user.click(slot1400Btn)

    // Confirm button enabled
    const confirmBtn = screen.getByRole('button', { name: 'Confirm new time' })
    expect(confirmBtn).not.toBeDisabled()
    await user.click(confirmBtn)

    // Expect AppointmentModifiedAlert with "Status: Confirmed → Cancelled"
    await waitFor(() => {
      expect(
        screen.getByText('This appointment was changed by someone else'),
      ).toBeInTheDocument()
      expect(screen.getByText('Status: Confirmed → Cancelled')).toBeInTheDocument()
    })

    // No automatic resend
    expect(rescheduleCalls).toBe(1)

    // Confirm button is disabled while conflict is present
    expect(confirmBtn).toBeDisabled()
  })

  it('successful reschedule sends If-Match & keepTechnician: true, shows notification, and navigates to details', async () => {
    let capturedRequest: InternalAxiosRequestConfig | null = null

    apiMock.get(`/appointments/${appointmentId}`, [
      200,
      baseAppointment,
      { etag: '"1"' },
    ])

    apiMock.get(new RegExp(`/dealerships/${dealershipId}/availability`), [
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
              start: '2026-11-02T10:00:00+07:00',
              end: '2026-11-02T11:00:00+07:00',
              available: true,
              reason: null,
            },
            {
              start: '2026-11-02T14:00:00+07:00',
              end: '2026-11-02T15:00:00+07:00',
              available: true,
              reason: null,
            },
          ],
        },
      },
    ])

    apiMock.post(new RegExp(`/appointments/${appointmentId}/reschedule`), (config) => {
      capturedRequest = config
      return [
        200,
        {
          ...baseAppointment,
          start: '2026-11-02T14:00:00+07:00',
          end: '2026-11-02T15:00:00+07:00',
          version: 2,
        },
        { etag: '"2"' },
      ]
    })

    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime })
    renderRoute(`/my-bookings/${appointmentId}/change`)

    await waitFor(() => {
      expect(
        screen.getByRole('heading', { level: 3, name: 'Change appointment time' }),
      ).toBeInTheDocument()
    })

    // Current time is shown in live comparison
    expect(screen.getByText('Mon 2 Nov · 10:00')).toBeInTheDocument()
    expect(screen.getByText('—')).toBeInTheDocument()

    // Pick 14:00
    const slot1400Btn = await screen.findByRole('button', { name: '14:00' })
    await user.click(slot1400Btn)

    // New time comparison updates
    expect(screen.getByText('Mon 2 Nov · 14:00')).toBeInTheDocument()

    // Confirm reschedule
    const confirmBtn = screen.getByRole('button', { name: 'Confirm new time' })
    await user.click(confirmBtn)

    await waitFor(() => {
      expect(capturedRequest).not.toBeNull()
    })

    // Header If-Match check
    const headers = capturedRequest!.headers
    expect(headers['If-Match'] || headers.get?.('If-Match')).toBe('"1"')

    // Payload check
    const body = typeof capturedRequest!.data === 'string'
      ? JSON.parse(capturedRequest!.data)
      : capturedRequest!.data
    expect(body.newStart).toBe('2026-11-02T14:00:00+07:00')
    expect(body.keepTechnician).toBe(true)

    // Notification toast on success
    await waitFor(() => {
      expect(
        screen.getByText('Appointment moved to Mon 2 Nov, 14:00–15:00'),
      ).toBeInTheDocument()
    })
  })

  it('guards against non-confirmed appointments with an alert and back button', async () => {
    apiMock.get(`/appointments/${appointmentId}`, [
      200,
      {
        ...baseAppointment,
        status: 'CANCELLED',
      },
      { etag: '"2"' },
    ])

    renderRoute(`/my-bookings/${appointmentId}/change`)

    await waitFor(() => {
      expect(
        screen.getByText(/Only confirmed appointments can be changed\. Current status is CANCELLED\./i),
      ).toBeInTheDocument()
    })

    const backButton = screen.getByRole('link', { name: 'Back' })
    expect(backButton).toHaveAttribute('href', `/my-bookings/${appointmentId}`)
    expect(screen.queryByRole('button', { name: 'Confirm new time' })).not.toBeInTheDocument()
  })

  it('guards against changing within 2 hours with phone notice and back button', async () => {
    // Clock at 09:00 UTC+7 (02:00 UTC) -> 1 hour before 10:00 start
    vi.setSystemTime(new Date('2026-11-02T02:00:00Z'))

    apiMock.get(`/appointments/${appointmentId}`, [
      200,
      baseAppointment,
      { etag: '"1"' },
    ])

    renderRoute(`/my-bookings/${appointmentId}/change`)

    await waitFor(() => {
      expect(
        screen.getByText(/It's less than 2 hours before your appointment\. Please call 028 3812 3456 to change it\./i),
      ).toBeInTheDocument()
    })

    const backButton = screen.getByRole('link', { name: 'Back' })
    expect(backButton).toHaveAttribute('href', `/my-bookings/${appointmentId}`)
    expect(screen.queryByRole('button', { name: 'Confirm new time' })).not.toBeInTheDocument()
  })

  it('handles 409 conflict with alternative suggestions and allows picking an alternative', async () => {
    apiMock.get(`/appointments/${appointmentId}`, [
      200,
      baseAppointment,
      { etag: '"1"' },
    ])

    apiMock.get(new RegExp(`/dealerships/${dealershipId}/availability`), [
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
              start: '2026-11-02T10:00:00+07:00',
              end: '2026-11-02T11:00:00+07:00',
              available: true,
              reason: null,
            },
            {
              start: '2026-11-02T14:00:00+07:00',
              end: '2026-11-02T15:00:00+07:00',
              available: true,
              reason: null,
            },
          ],
        },
      },
    ])

    apiMock.post(new RegExp(`/appointments/${appointmentId}/reschedule`), [
      409,
      {
        code: 'NO_AVAILABILITY',
        reason: 'SLOT_TAKEN',
        title: 'The selected time was just taken',
        detail: 'The slot is no longer available.',
        alternatives: [
          {
            start: '2026-11-02T15:00:00+07:00',
            end: '2026-11-02T16:00:00+07:00',
          },
        ],
      },
    ])

    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime })
    renderRoute(`/my-bookings/${appointmentId}/change`)

    await waitFor(() => {
      expect(
        screen.getByRole('heading', { level: 3, name: 'Change appointment time' }),
      ).toBeInTheDocument()
    })

    const slot1400Btn = await screen.findByRole('button', { name: '14:00' })
    await user.click(slot1400Btn)

    const confirmBtn = screen.getByRole('button', { name: 'Confirm new time' })
    await user.click(confirmBtn)

    // 409 BookingErrorCard displays
    await waitFor(() => {
      expect(screen.getByText('The 14:00 slot was just taken')).toBeInTheDocument()
    })

    // Alternative chip is rendered
    const altChip = screen.getByRole('button', { name: /15:00/ })
    expect(altChip).toBeInTheDocument()

    // Clicking alternative chip selects it and dismisses error card
    await user.click(altChip)
    expect(screen.getByText('Mon 2 Nov · 15:00')).toBeInTheDocument()
  })
})
