import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { screen, waitFor } from '@testing-library/react'
import { renderRoute } from '../../test/render'
import { apiMock } from '../../test/apiMock'
import type { Appointment } from '../../shared/api/appointments'

describe('BookingDetailsPage', () => {
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
    vi.setSystemTime(new Date('2026-11-02T08:00:00Z'))
    sessionStorage.clear()
    apiMock.install()
    apiMock.reset()
    apiMock.get('/me', [200, mockCustomerUser])
  })

  afterEach(() => {
    vi.useRealTimers()
    sessionStorage.clear()
    apiMock.restore()
  })

  it('renders booking details, service with duration, vehicle, booked by, reminder, location with phone link, and new booking button', async () => {
    apiMock.get(`/appointments/${appointmentId}`, [
      200,
      baseAppointment,
      { etag: '"1"' },
    ])

    renderRoute(`/my-bookings/${appointmentId}`)

    // Title and reference
    await waitFor(() => {
      expect(screen.getByRole('heading', { level: 3, name: 'Appointment details' })).toBeInTheDocument()
      expect(screen.getByText('APT-20261102-0042')).toBeInTheDocument()
    })

    // Back link
    const backLink = screen.getByLabelText('Back')
    expect(backLink).toHaveAttribute('href', '/my-bookings')

    // Time slot and status
    expect(screen.getByText('Mon 2 Nov · 10:00–11:00')).toBeInTheDocument()
    expect(screen.getByText('Confirmed')).toBeInTheDocument()

    // Service with duration
    expect(screen.getByText('Oil change · about 60 min')).toBeInTheDocument()

    // Vehicle details
    expect(screen.getByText('Vehicle')).toBeInTheDocument()
    expect(screen.getByText('Toyota Vios · 51H-123.45')).toBeInTheDocument()

    // Booked by
    expect(screen.getByText('Booked by')).toBeInTheDocument()
    expect(screen.getByText('Nguyễn Văn Hùng')).toBeInTheDocument()

    // Reminder (reminderOptIn is true)
    expect(screen.getByText('Reminder')).toBeInTheDocument()
    expect(screen.getByText('Reminder SMS 1 day before')).toBeInTheDocument()

    // Location & phone link
    expect(screen.getByText('Location')).toBeInTheDocument()
    expect(screen.getByText('Saigon Auto · District 7')).toBeInTheDocument()
    const phoneLink = screen.getByRole('link', { name: '028 3812 3456' })
    expect(phoneLink).toHaveAttribute('href', 'tel:02838123456')

    // Actions area placeholder and New booking button
    expect(screen.getByTestId('actions-placeholder')).toBeInTheDocument()
    const newBookingButton = screen.getByRole('link', { name: 'New booking' })
    expect(newBookingButton).toHaveAttribute('href', '/book')
  })

  it('omits reminder when reminderOptIn is false', async () => {
    apiMock.get(`/appointments/${appointmentId}`, [
      200,
      {
        ...baseAppointment,
        reminderOptIn: false,
      },
      { etag: '"1"' },
    ])

    renderRoute(`/my-bookings/${appointmentId}`)

    await waitFor(() => {
      expect(screen.getByRole('heading', { level: 3, name: 'Appointment details' })).toBeInTheDocument()
    })

    expect(screen.queryByText('Reminder SMS 1 day before')).not.toBeInTheDocument()
  })

  it('renders 404 error state when appointment is not found or inaccessible', async () => {
    apiMock.get(`/appointments/${appointmentId}`, [
      404,
      { code: 'APPOINTMENT_NOT_FOUND', title: "We couldn't find this appointment." },
    ])

    renderRoute(`/my-bookings/${appointmentId}`)

    await waitFor(() => {
      expect(screen.getByRole('heading', { level: 3, name: "We couldn't find this appointment." })).toBeInTheDocument()
    })

    const backButton = screen.getByRole('link', { name: 'Back to my bookings' })
    expect(backButton).toHaveAttribute('href', '/my-bookings')
  })
})
