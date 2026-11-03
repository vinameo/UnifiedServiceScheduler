import type { Appointment } from '../api/appointments'

export const CUSTOMER_CHANGE_WINDOW_HOURS = 2

/**
 * Checks whether a customer is allowed to cancel or reschedule the appointment.
 * Allowed when:
 * - Status is HELD (holds have no 2-hour limit, D-07.3); OR
 * - Status is CONFIRMED and start - now >= 2 hours (INV-07.3, BR-07.2).
 *
 * @param appointment The appointment to check (must have start and status)
 * @param now Current timestamp in milliseconds (defaults to Date.now())
 */
export function canCustomerChange(
  appointment: Pick<Appointment, 'start' | 'status'>,
  now: number = Date.now(),
): boolean {
  if (appointment.status === 'HELD') {
    return true
  }

  if (appointment.status !== 'CONFIRMED') {
    return false
  }

  const startTime = new Date(appointment.start).getTime()
  if (Number.isNaN(startTime)) {
    return false
  }

  const twoHoursMs = CUSTOMER_CHANGE_WINDOW_HOURS * 60 * 60 * 1000
  return startTime - now >= twoHoursMs
}
