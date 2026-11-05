import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router'
import { AppProviders } from '../../AppProviders'
import { apiMock } from '../../test/apiMock'
import type { Appointment } from '../../shared/api/appointments'
import { AppointmentActions, type AppointmentActionsProps } from './AppointmentActions'

describe('AppointmentActions (TC-12-25, WEB-12.6)', () => {
  const dealershipId = '0192d111-0000-7000-8000-000000000001'
  const appointmentId = '0192d222-0000-7000-8000-000000000002'

  const inProgressAppointment: Appointment = {
    id: appointmentId,
    reference: 'APT-20261102-0042',
    status: 'IN_PROGRESS',
    start: '2026-11-02T09:00:00+07:00',
    end: '2026-11-02T10:00:00+07:00',
    expiresAt: null,
    version: 3,
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
  }

  beforeEach(() => {
    sessionStorage.clear()
    apiMock.install()
    apiMock.reset()
  })

  afterEach(() => {
    sessionStorage.clear()
    apiMock.restore()
  })

  function renderAppointmentActions(props: AppointmentActionsProps) {
    return render(
      <AppProviders>
        <MemoryRouter>
          <AppointmentActions {...props} />
        </MemoryRouter>
      </AppProviders>,
    )
  }

  it('renders "Mark completed" and "Extend time" for foreman, none for advisor; extend menu sends {minutes: 30} with If-Match', async () => {
    // 1. Given Booking IN_PROGRESS and advisor -> actions: none
    const { unmount } = renderAppointmentActions({
      appointment: inProgressAppointment,
      etag: '"3"',
      role: 'SERVICE_ADVISOR',
    })

    expect(screen.queryByRole('button', { name: 'Mark completed' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Extend time' })).not.toBeInTheDocument()

    unmount()

    // 2. Given Booking IN_PROGRESS and foreman -> actions: "Mark completed", "Extend time"
    let capturedIfMatch: string | null = null
    let capturedIdempotencyKey: string | null = null
    let capturedBody: unknown = null

    apiMock.post(`/appointments/${appointmentId}/extend`, (config) => {
      capturedIfMatch = (config.headers?.['If-Match'] ?? config.headers?.['if-match']) as string
      capturedIdempotencyKey = (config.headers?.['Idempotency-Key'] ??
        config.headers?.['idempotency-key']) as string
      capturedBody = typeof config.data === 'string' ? JSON.parse(config.data) : config.data

      return [
        200,
        {
          appointment: {
            ...inProgressAppointment,
            end: '2026-11-02T10:30:00+07:00',
            version: 4,
          },
          affected: [],
        },
        { etag: '"4"' },
      ]
    })

    const user = userEvent.setup()

    renderAppointmentActions({
      appointment: inProgressAppointment,
      etag: '"3"',
      role: 'WORKSHOP_FOREMAN',
    })

    const completeBtn = screen.getByRole('button', { name: 'Mark completed' })
    const extendBtn = screen.getByRole('button', { name: 'Extend time' })

    expect(completeBtn).toBeInTheDocument()
    expect(extendBtn).toBeInTheDocument()

    // 3. Extend menu sends {minutes: 30} with If-Match
    await user.click(extendBtn)

    const extend30Option = await screen.findByRole('menuitem', { name: '30 min' })
    expect(extend30Option).toBeInTheDocument()

    await user.click(extend30Option)

    await waitFor(() => {
      expect(capturedBody).toEqual({ minutes: 30 })
      expect(capturedIfMatch).toBe('"3"')
      expect(capturedIdempotencyKey).toBeTruthy()
    })
  })

  it('a 412 on an action shows who changed what and offers a reload', async () => {
    apiMock.post(`/appointments/${appointmentId}/complete`, [
      412,
      {
        code: 'APPOINTMENT_MODIFIED',
        status: 412,
        title: 'Modified',
        appointment: { ...inProgressAppointment, status: 'COMPLETED', version: 4 },
      },
      { etag: '"4"' },
    ])

    const user = userEvent.setup()
    renderAppointmentActions({
      appointment: inProgressAppointment,
      etag: '"3"',
      role: 'WORKSHOP_FOREMAN',
    })

    await user.click(screen.getByRole('button', { name: 'Mark completed' }))

    expect(await screen.findByText('This appointment was changed by someone else')).toBeInTheDocument()
    expect(screen.getByText('Status: In progress → Completed')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Review and retry' })).toBeInTheDocument()
  })
})
