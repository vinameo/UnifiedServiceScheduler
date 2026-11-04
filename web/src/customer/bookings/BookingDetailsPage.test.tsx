import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
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
    // Default system time: 07:00 UTC+7 (00:00 UTC), which is 3 hours before 10:00 UTC+7
    vi.setSystemTime(new Date('2026-11-02T00:00:00Z'))
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

  it('renders booking details, service with duration, vehicle, booked by, reminder, location with phone link, and cancel button when >= 2h before', async () => {
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
    const phoneLinks = screen.getAllByRole('link', { name: '028 3812 3456' })
    expect(phoneLinks[0]).toHaveAttribute('href', 'tel:02838123456')

    // Change time and Cancel appointment buttons are available when >= 2 hours before
    const changeTimeButton = screen.getByRole('link', { name: 'Change time' })
    expect(changeTimeButton).toHaveAttribute('href', `/my-bookings/${appointmentId}/change`)
    expect(screen.getByRole('button', { name: 'Cancel appointment' })).toBeInTheDocument()

    const newBookingButton = screen.getByRole('link', { name: 'New booking' })
    expect(newBookingButton).toHaveAttribute('href', '/book')
  })

  it('TC-07-16 (AC-07.13, WEB-07.4): inside 2 hours shows no Cancel button and displays call dealership notice', async () => {
    // Fake clock 08:00 (UTC+7 -> 01:00 UTC); booking 09:00 (UTC+7) -> 1 hour before start
    vi.setSystemTime(new Date('2026-11-02T01:00:00Z'))

    apiMock.get(`/appointments/${appointmentId}`, [
      200,
      {
        ...baseAppointment,
        start: '2026-11-02T09:00:00+07:00',
        end: '2026-11-02T10:00:00+07:00',
      },
      { etag: '"1"' },
    ])

    renderRoute(`/my-bookings/${appointmentId}`)

    await waitFor(() => {
      expect(screen.getByRole('heading', { level: 3, name: 'Appointment details' })).toBeInTheDocument()
    })

    // No Change time or Cancel button
    expect(screen.queryByRole('link', { name: 'Change time' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Cancel appointment' })).not.toBeInTheDocument()

    // "It's less than 2 hours before your appointment. Please call 028 3812 3456 to cancel."
    expect(
      screen.getByText(/It's less than 2 hours before your appointment\. Please call/i),
    ).toBeInTheDocument()
    const callLinks = screen.getAllByRole('link', { name: '028 3812 3456' })
    expect(callLinks.length).toBeGreaterThanOrEqual(1)
    expect(callLinks[callLinks.length - 1]).toHaveAttribute('href', 'tel:02838123456')
  })

  it('customer cancels appointment via bottom sheet with optional reason', async () => {
    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime })

    apiMock.get(`/appointments/${appointmentId}`, [
      200,
      baseAppointment,
      { etag: '"1"' },
    ])

    const cancelledAppointment: Appointment = {
      ...baseAppointment,
      status: 'CANCELLED',
      version: 2,
      cancellation: {
        reason: 'SOMETHING_CAME_UP',
        note: null,
        at: '2026-11-02T07:05:00+07:00',
        byRole: 'CUSTOMER',
      },
    }

    apiMock.post(`/appointments/${appointmentId}/cancel`, [
      200,
      cancelledAppointment,
      { etag: '"2"' },
    ])

    renderRoute(`/my-bookings/${appointmentId}`)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Cancel appointment' })).toBeInTheDocument()
    })

    // Click Cancel appointment to open sheet
    await user.click(screen.getByRole('button', { name: 'Cancel appointment' }))

    // Sheet displays
    await waitFor(() => {
      expect(
        screen.getByRole('heading', {
          level: 2,
          name: 'Cancel your 10:00 appointment on Mon 2 Nov?',
        }),
      ).toBeInTheDocument()
    })

    // Select reason
    const radio = screen.getByRole('radio', { name: 'Something came up' })
    await user.click(radio)

    // Submit cancellation inside the sheet dialog
    const dialog = screen.getByRole('dialog')
    const confirmButton = within(dialog).getByRole('button', { name: 'Cancel appointment' })
    await user.click(confirmButton)

    // Sheet closes and page displays "You cancelled this appointment" banner
    await waitFor(() => {
      expect(screen.getByText('You cancelled this appointment')).toBeInTheDocument()
      expect(
        screen.getByText("The time slot has been released. We'll send you a cancellation SMS."),
      ).toBeInTheDocument()
    })

    // Sheet dialog is closed and Cancel button is hidden
    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    })
    expect(screen.queryByRole('button', { name: 'Cancel appointment' })).not.toBeInTheDocument()
  })

  it('displays dealership cancelled banner when cancelled by service advisor', async () => {
    apiMock.get(`/appointments/${appointmentId}`, [
      200,
      {
        ...baseAppointment,
        status: 'CANCELLED',
        cancellation: {
          reason: 'CUSTOMER_REQUEST',
          note: 'Customer requested by phone',
          at: '2026-11-02T07:10:00+07:00',
          byRole: 'SERVICE_ADVISOR',
        },
      },
      { etag: '"2"' },
    ])

    renderRoute(`/my-bookings/${appointmentId}`)

    await waitFor(() => {
      expect(
        screen.getByText(/This appointment was cancelled by the dealership\. Call/i),
      ).toBeInTheDocument()
    })

    const phoneLinks = screen.getAllByRole('link', { name: '028 3812 3456' })
    expect(phoneLinks.length).toBeGreaterThanOrEqual(1)
    expect(phoneLinks[0]).toHaveAttribute('href', 'tel:02838123456')
    expect(screen.queryByRole('button', { name: 'Cancel appointment' })).not.toBeInTheDocument()
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
