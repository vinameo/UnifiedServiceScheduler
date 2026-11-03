import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { renderRoute } from '../../test/render'
import { apiMock } from '../../test/apiMock'
import type { Appointment, AppointmentEvent } from '../../shared/api/appointments'

describe('AppointmentDetailsPage', () => {
  const dealershipId = '0192d111-0000-7000-8000-000000000001'
  const appointmentId = '0192d222-0000-7000-8000-000000000002'

  const mockStaffUser = {
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
    apiMock.get('/me', [200, mockStaffUser])
  })

  afterEach(() => {
    vi.useRealTimers()
    sessionStorage.clear()
    apiMock.restore()
  })

  it('renders appointment details, info card, and masked phone with reveal toggle', async () => {
    apiMock.get(`/appointments/${appointmentId}`, [
      200,
      baseAppointment,
      { etag: '"1"' },
    ])
    apiMock.get(`/appointments/${appointmentId}/history`, [200, []])

    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime })
    renderRoute(`/staff/appointments/${appointmentId}`)

    // Reference in breadcrumb
    await waitFor(() => {
      expect(screen.getByText('APT-20261102-0042')).toBeInTheDocument()
    })

    // Header info
    expect(screen.getByRole('heading', { level: 2, name: /Nguyễn Văn Hùng · Toyota Vios/i })).toBeInTheDocument()
    expect(screen.getByText('Confirmed')).toBeInTheDocument()
    expect(screen.getByText(/Monday, 2 Nov 2026, 09:00–10:00 · Oil change/i)).toBeInTheDocument()

    // Appointment info fields
    expect(screen.getByText('09:00–10:00, Monday, 2 Nov 2026')).toBeInTheDocument()
    expect(screen.getByText('Oil change · 60 min')).toBeInTheDocument()
    expect(screen.getByText('Trần Minh An')).toBeInTheDocument()
    expect(screen.getByText('Bay 2 · Quick service')).toBeInTheDocument()
    expect(screen.getAllByText(/Saigon Auto · District 7/i)).toHaveLength(2)
    expect(screen.getByText(/Minh Anh, 28\/10\/2026 16:40/i)).toBeInTheDocument()

    // Customer & vehicle info
    expect(screen.getByText('Toyota Vios 2021 · 51H-123.45')).toBeInTheDocument()
    expect(screen.getByText('RL4BT3F3X0M001234')).toBeInTheDocument()

    // Phone is masked initially
    expect(screen.getByText('0903 *** 218')).toBeInTheDocument()
    const showButton = screen.getByRole('button', { name: /Show number/i })
    expect(showButton).toBeInTheDocument()

    // Clicking "Show number" reveals full phone number as tel link
    await user.click(showButton)
    const phoneLink = screen.getByRole('link', { name: '0903123218' })
    expect(phoneLink).toHaveAttribute('href', 'tel:0903123218')
  })

  it('renders closed banner when appointment status is closed', async () => {
    const closedAppointment: Appointment = {
      ...baseAppointment,
      status: 'CANCELLED',
    }

    apiMock.get(`/appointments/${appointmentId}`, [
      200,
      closedAppointment,
      { etag: '"2"' },
    ])
    apiMock.get(`/appointments/${appointmentId}/history`, [200, []])

    renderRoute(`/staff/appointments/${appointmentId}`)

    await waitFor(() => {
      expect(screen.getByText('Cancelled')).toBeInTheDocument()
    })

    expect(
      screen.getByText(
        'This appointment is closed and can no longer be changed. Its bay and technician have been released for this time slot.',
      ),
    ).toBeInTheDocument()
  })

  it('shows not found error view when appointment does not exist or user lacks permission', async () => {
    apiMock.get(`/appointments/${appointmentId}`, [
      404,
      { code: 'APPOINTMENT_NOT_FOUND', title: "We couldn't find this appointment." },
    ])

    renderRoute(`/staff/appointments/${appointmentId}`)

    await waitFor(() => {
      expect(screen.getByText("We couldn't find this appointment.")).toBeInTheDocument()
    })
    expect(screen.getByRole('button', { name: /Back to appointments/i })).toBeInTheDocument()
  })

  it('TC-05-15: renders closed banner, history timeline, and reveals full phone number upon clicking Show number', async () => {
    const cancelledAppointment: Appointment = {
      ...baseAppointment,
      status: 'CANCELLED',
    }

    const mockHistory: AppointmentEvent[] = [
      {
        id: 'evt-1',
        type: 'CREATED',
        fromStatus: null,
        toStatus: 'CONFIRMED',
        version: 1,
        actor: { name: 'Minh Anh', role: 'SERVICE_ADVISOR' },
        data: {},
        occurredAt: '2026-10-28T16:40:00+07:00',
      },
      {
        id: 'evt-2',
        type: 'CANCELLED',
        fromStatus: 'CONFIRMED',
        toStatus: 'CANCELLED',
        version: 2,
        actor: { name: 'Minh Anh', role: 'SERVICE_ADVISOR' },
        data: { reason: 'CUSTOMER_BUSY' },
        occurredAt: '2026-11-01T15:10:00+07:00',
      },
    ]

    apiMock.get(`/appointments/${appointmentId}`, [
      200,
      cancelledAppointment,
      { etag: '"2"' },
    ])
    apiMock.get(`/appointments/${appointmentId}/history`, [200, mockHistory])

    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime })
    renderRoute(`/staff/appointments/${appointmentId}`)

    // 1. Closed banner is visible
    await waitFor(() => {
      expect(
        screen.getByText(
          'This appointment is closed and can no longer be changed. Its bay and technician have been released for this time slot.',
        ),
      ).toBeInTheDocument()
    })

    // 2. History contains "Cancelled · Minh Anh"
    await waitFor(() => {
      expect(screen.getByText(/Cancelled · Minh Anh/i)).toBeInTheDocument()
    })

    // 3. Masked phone shows initially
    expect(screen.getByText('0903 *** 218')).toBeInTheDocument()
    const showButton = screen.getByRole('button', { name: /Show number/i })

    // 4. Click "Show number" -> full number shows
    await user.click(showButton)
    const phoneLink = screen.getByRole('link', { name: '0903123218' })
    expect(phoneLink).toHaveAttribute('href', 'tel:0903123218')
  })
})
