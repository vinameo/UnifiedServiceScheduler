import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { renderRoute } from '../../test/render'
import { apiMock } from '../../test/apiMock'
import type { Appointment } from '../../shared/api/appointments'

describe('CancelAppointmentModal (TC-07-15, AC-07.12, WEB-07.2)', () => {
  const dealershipId = '0192d111-0000-7000-8000-000000000001'
  const appointmentId = '0192d222-0000-7000-8000-000000000002'

  const mockAdvisorUser = {
    id: 1,
    email: 'advisor@saigonauto.test',
    name: 'Trần Thị Cố Vấn',
    role: 'SERVICE_ADVISOR',
    dealership_id: dealershipId,
    dealership: {
      id: dealershipId,
      code: 'SGA-D7',
      name: 'Saigon Auto',
      branchName: 'District 7',
      timezone: 'Asia/Ho_Chi_Minh',
      phone: '028 3812 3456',
    },
  }

  const baseAppointment: Appointment = {
    id: appointmentId,
    reference: 'APT-20261102-0042',
    status: 'CONFIRMED',
    start: '2026-11-02T09:00:00+07:00',
    end: '2026-11-02T10:00:00+07:00',
    expiresAt: null,
    version: 1,
    dealership: {
      id: dealershipId,
      name: 'Saigon Auto',
      branchName: 'District 7',
      timezone: 'Asia/Ho_Chi_Minh',
    },
    serviceType: {
      id: 'st-1',
      code: 'OIL_CHANGE',
      name: 'Oil change',
      durationMinutes: 60,
    },
    technician: {
      id: 'tech-1',
      name: 'Trần Minh An',
    },
    bay: {
      id: 'bay-1',
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
      year: 2021,
      licencePlate: '51H-123.45',
      vin: 'RL4BT3F3X0M001234',
    },
    reminderOptIn: false,
    source: 'STAFF',
    createdAt: '2026-10-28T16:40:00+07:00',
    createdBy: {
      name: 'Minh Anh',
      role: 'SERVICE_ADVISOR',
    },
  }

  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date('2026-11-02T08:00:00Z'))
    sessionStorage.clear()
    apiMock.install()
    apiMock.reset()
    apiMock.get('/me', [200, mockAdvisorUser])
  })

  afterEach(() => {
    vi.useRealTimers()
    sessionStorage.clear()
    apiMock.restore()
  })

  it('TC-07-15: advisor opens cancel modal, picks reason, confirms with If-Match "1", sees Cancelled badge and closed banner', async () => {
    apiMock.get(`/appointments/${appointmentId}`, [
      200,
      baseAppointment,
      { etag: '"1"' },
    ])
    apiMock.get(`/appointments/${appointmentId}/history`, [200, []])

    let capturedIfMatch: string | null = null
    let capturedIdempotencyKey: string | null = null
    let capturedBody: unknown = null

    const cancelledAppointment: Appointment = {
      ...baseAppointment,
      status: 'CANCELLED',
      version: 2,
      cancellation: {
        reason: 'CUSTOMER_BUSY',
        note: null,
        at: '2026-11-02T08:00:00+07:00',
        byRole: 'SERVICE_ADVISOR',
      },
    }

    apiMock.post(`/appointments/${appointmentId}/cancel`, (config) => {
      capturedIfMatch = (config.headers?.['If-Match'] ?? config.headers?.['if-match']) as string
      capturedIdempotencyKey = (config.headers?.['Idempotency-Key'] ?? config.headers?.['idempotency-key']) as string
      capturedBody = typeof config.data === 'string' ? JSON.parse(config.data) : config.data
      return [200, cancelledAppointment, { etag: '"2"' }]
    })

    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime })
    renderRoute(`/staff/appointments/${appointmentId}`)

    // 1. Initially Confirmed status and Cancel appointment button visible
    await waitFor(() => {
      expect(screen.getByText('Confirmed')).toBeInTheDocument()
    })
    const openModalBtn = screen.getByRole('button', { name: 'Cancel appointment' })
    expect(openModalBtn).toBeInTheDocument()

    // 2. Open modal
    await user.click(openModalBtn)

    // 3. Verify dialog content and released resources message
    await waitFor(() => {
      expect(screen.getByRole('dialog')).toBeInTheDocument()
    })
    const dialog = screen.getByRole('dialog')
    expect(within(dialog).getByText('Cancel this appointment?')).toBeInTheDocument()
    expect(
      within(dialog).getByText(
        'Bay 2 and technician Trần Minh An will be released for 09:00–10:00 so other customers can book it. This cannot be undone.',
      ),
    ).toBeInTheDocument()

    // Confirm button in dialog is disabled until reason is picked
    const confirmBtn = within(dialog).getByRole('button', { name: 'Cancel appointment' })
    expect(confirmBtn).toBeDisabled()

    // 4. Select a reason (e.g. "Customer is busy")
    const reasonInput = within(dialog).getByPlaceholderText('Reason')
    await user.click(reasonInput)
    const option = await screen.findByRole('option', { name: 'Customer is busy', hidden: true })
    await user.click(option)

    expect(confirmBtn).not.toBeDisabled()

    // 5. Confirm cancellation
    await user.click(confirmBtn)

    // 6. Verify request headers
    await waitFor(() => {
      expect(capturedIfMatch).toBe('"1"')
    })
    expect(capturedIdempotencyKey).toBeTruthy()
    expect(capturedBody).toMatchObject({
      reason: 'CUSTOMER_BUSY',
    })

    // 7. Verify page updates: Cancelled badge, closed banner, modal closed, action buttons hidden
    await waitFor(() => {
      expect(screen.getByText('Cancelled')).toBeInTheDocument()
    })
    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    })
    expect(
      screen.getByText(
        'This appointment is closed and can no longer be changed. Its bay and technician have been released for this time slot.',
      ),
    ).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Cancel appointment' })).not.toBeInTheDocument()
  })

  it('TC-07-19: given cancel 412 with modified booking, shows AppointmentModifiedAlert with time diff without automatic resend', async () => {
    apiMock.get(`/appointments/${appointmentId}`, [
      200,
      baseAppointment,
      { etag: '"1"' },
    ])
    apiMock.get(`/appointments/${appointmentId}/history`, [200, []])

    let postCount = 0
    apiMock.post(`/appointments/${appointmentId}/cancel`, () => {
      postCount++
      return [
        412,
        {
          code: 'APPOINTMENT_MODIFIED',
          title: 'This appointment was modified by another request.',
          appointment: {
            ...baseAppointment,
            version: 2,
            start: '2026-11-02T14:00:00+07:00',
            end: '2026-11-02T15:00:00+07:00',
          },
        },
        { etag: '"2"' },
      ]
    })

    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime })
    renderRoute(`/staff/appointments/${appointmentId}`)

    await waitFor(() => {
      expect(screen.getByText('Confirmed')).toBeInTheDocument()
    })
    await user.click(screen.getByRole('button', { name: 'Cancel appointment' }))

    const dialog = await screen.findByRole('dialog')
    const reasonInput = within(dialog).getByPlaceholderText('Reason')
    await user.click(reasonInput)
    const option = await screen.findByRole('option', { name: 'Customer is busy', hidden: true })
    await user.click(option)

    const confirmBtn = within(dialog).getByRole('button', { name: 'Cancel appointment' })
    await user.click(confirmBtn)

    // 412 triggers AppointmentModifiedAlert inside the modal
    await waitFor(() => {
      expect(
        within(dialog).getByText('This appointment was changed by someone else'),
      ).toBeInTheDocument()
    })
    expect(
      within(dialog).getByText(/Time: 09:00 - 10:00 → 14:00 - 15:00/),
    ).toBeInTheDocument()

    // No automatic resend
    expect(postCount).toBe(1)
  })
})
