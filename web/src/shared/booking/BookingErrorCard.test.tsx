import { describe, it, expect, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MantineProvider } from '@mantine/core'
import { BookingErrorCard } from './BookingErrorCard'

function renderCard(ui: React.ReactElement) {
  return render(<MantineProvider>{ui}</MantineProvider>)
}

describe('BookingErrorCard (TC-02-28)', () => {
  it('TC-02-28: renders NO_AVAILABILITY with NO_BAY_AVAILABLE heading and body', () => {
    const error = {
      status: 409,
      code: 'NO_AVAILABILITY',
      reason: 'NO_BAY_AVAILABLE',
    }

    renderCard(
      <BookingErrorCard
        error={error}
        context={{
          time: '09:00',
          start: '09:00',
          end: '10:00',
          bayTypeLabel: 'Quick service',
        }}
      />,
    )

    expect(screen.getByRole('alert')).toBeInTheDocument()
    expect(screen.getByText('The 09:00 slot was just taken')).toBeInTheDocument()
    expect(
      screen.getByText('No Quick service bay is free from 09:00 to 10:00. Your appointment was not created.'),
    ).toBeInTheDocument()
  })

  it('TC-02-28: VEHICLE_NOT_OWNED_BY_CUSTOMER returns null (card is not shown, handled field-level)', () => {
    const error = {
      status: 422,
      code: 'VEHICLE_NOT_OWNED_BY_CUSTOMER',
      title: 'This vehicle is not registered to the selected customer.',
    }

    renderCard(<BookingErrorCard error={error} />)
    expect(screen.queryByRole('alert')).toBeNull()
  })

  it('renders OUTSIDE_OPENING_HOURS with latest start and duration', () => {
    const error = {
      status: 422,
      code: 'OUTSIDE_OPENING_HOURS',
      closesAt: '17:00:00',
      latestStart: '15:00',
    }

    renderCard(
      <BookingErrorCard
        error={error}
        context={{
          serviceName: '20,000 km service',
          durationMinutes: 120,
          start: '16:00',
          end: '18:00',
        }}
      />,
    )

    expect(screen.getByText('The service would end after closing time')).toBeInTheDocument()
    expect(screen.getByText(/The latest start that day is 15:00/)).toBeInTheDocument()
  })

  it('renders VEHICLE_ALREADY_BOOKED message', () => {
    const error = {
      status: 409,
      code: 'VEHICLE_ALREADY_BOOKED',
    }

    renderCard(
      <BookingErrorCard
        error={error}
        context={{
          licencePlate: '51H-123.45',
          start: '09:00',
        }}
      />,
    )

    expect(
      screen.getByText('Vehicle 51H-123.45 already has a booking at 09:00 that day'),
    ).toBeInTheDocument()
    expect(
      screen.getByText(
        'A vehicle cannot be in two bays at once. You can reschedule the existing booking instead of creating a new one.',
      ),
    ).toBeInTheDocument()
  })

  it('renders START_IN_PAST message', () => {
    const error = {
      status: 422,
      code: 'START_IN_PAST',
    }

    renderCard(<BookingErrorCard error={error} />)
    expect(screen.getByText('This time has already passed')).toBeInTheDocument()
    expect(screen.getByText('This time has already passed. Pick another time.')).toBeInTheDocument()
  })

  it('renders START_TOO_FAR_AHEAD message with maxDate', () => {
    const error = {
      status: 422,
      code: 'START_TOO_FAR_AHEAD',
      maxDate: '2027-01-31',
    }

    renderCard(<BookingErrorCard error={error} />)
    expect(screen.getByText('Date is too far ahead')).toBeInTheDocument()
    expect(screen.getByText(/You can book up to 90 days ahead/)).toBeInTheDocument()
    expect(screen.getByText(/2027-01-31/)).toBeInTheDocument()
  })

  it('TC-05-17: VEHICLE_ALREADY_BOOKED with existingAppointment links to /staff/appointments/{id} for staff', () => {
    const error = {
      status: 409,
      code: 'VEHICLE_ALREADY_BOOKED',
      existingAppointment: {
        id: 'apt-staff-existing-99',
        reference: 'APT-20261102-0099',
      },
    }

    renderCard(<BookingErrorCard error={error} variant="staff" />)
    const link = screen.getByRole('link', { name: 'View existing booking' })
    expect(link).toBeInTheDocument()
    expect(link).toHaveAttribute('href', '/staff/appointments/apt-staff-existing-99')
  })

  it('TC-05-17: VEHICLE_ALREADY_BOOKED with existingAppointment defaults to staff appointment link when unspecified', () => {
    const error = {
      status: 409,
      code: 'VEHICLE_ALREADY_BOOKED',
      existingAppointment: {
        id: 'apt-staff-existing-88',
      },
    }

    renderCard(<BookingErrorCard error={error} />)
    const link = screen.getByRole('link', { name: 'View existing booking' })
    expect(link).toBeInTheDocument()
    expect(link).toHaveAttribute('href', '/staff/appointments/apt-staff-existing-88')
  })

  it('TC-05-17: VEHICLE_ALREADY_BOOKED with existingAppointment links to /my-bookings/{id} for customer', () => {
    const error = {
      status: 409,
      code: 'VEHICLE_ALREADY_BOOKED',
      existingAppointment: {
        id: 'apt-cust-existing-77',
      },
    }

    renderCard(<BookingErrorCard error={error} variant="customer" />)
    const link = screen.getByRole('link', { name: 'View existing booking' })
    expect(link).toBeInTheDocument()
    expect(link).toHaveAttribute('href', '/my-bookings/apt-cust-existing-77')
  })

  it('TC-05-17: VEHICLE_ALREADY_BOOKED without existingAppointment does not render View existing booking', () => {
    const error = {
      status: 409,
      code: 'VEHICLE_ALREADY_BOOKED',
    }

    renderCard(<BookingErrorCard error={error} />)
    expect(screen.queryByRole('link', { name: 'View existing booking' })).not.toBeInTheDocument()
  })

  it('renders AlternativeChips when error has alternatives and triggers onPickAlternative', async () => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date('2026-11-02T08:00:00Z'))

    const onPickAlternative = vi.fn()
    const error = {
      status: 409,
      code: 'NO_AVAILABILITY',
      reason: 'NO_BAY_AVAILABLE',
      alternatives: [
        { start: '2026-11-02T10:00:00+07:00', end: '2026-11-02T11:00:00+07:00' },
        { start: '2026-11-03T08:00:00+07:00', end: '2026-11-03T09:00:00+07:00' },
      ],
    }

    const user = userEvent.setup()
    renderCard(
      <BookingErrorCard
        error={error}
        timeZone="Asia/Ho_Chi_Minh"
        onPickAlternative={onPickAlternative}
        context={{
          time: '09:00',
          start: '09:00',
          end: '10:00',
          referenceDate: '2026-11-02',
        }}
      />,
    )

    expect(screen.getByText('Nearest available slots')).toBeInTheDocument()
    const chip1 = screen.getByRole('button', { name: '10:00 today' })
    const chip2 = screen.getByRole('button', { name: '08:00 Tue 3 Nov' })
    expect(chip1).toBeInTheDocument()
    expect(chip2).toBeInTheDocument()

    await user.click(chip1)
    expect(onPickAlternative).toHaveBeenCalledWith('2026-11-02T10:00:00+07:00')

    vi.useRealTimers()
  })

  it('renders empty alternative slots message when error has empty alternatives', () => {
    const error = {
      status: 409,
      code: 'NO_AVAILABILITY',
      reason: 'NO_BAY_AVAILABLE',
      alternatives: [],
    }

    renderCard(<BookingErrorCard error={error} timeZone="Asia/Ho_Chi_Minh" />)
    expect(screen.getByText('Nearest available slots')).toBeInTheDocument()
    expect(screen.getByText('No free time nearby. Pick another day.')).toBeInTheDocument()
  })
})
