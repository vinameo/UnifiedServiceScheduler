import { describe, it, expect } from 'vitest'
import { errorCopy } from './errorCopy'

describe('errorCopy', () => {
  it('returns exact copy for known error codes', () => {
    expect(errorCopy('INVALID_CREDENTIALS')).toBe('Email or password is incorrect.')
    expect(errorCopy('UNAUTHENTICATED')).toBe('Please sign in to continue.')
    expect(errorCopy('FORBIDDEN')).toBe("You don't have permission to view this page.")
    expect(errorCopy('VALIDATION_ERROR')).toBe('Check the highlighted fields.')
    expect(errorCopy('EMAIL_ALREADY_USED')).toBe('An account with this email already exists. Sign in instead.')
  })

  it('returns the F12 day-of-service copy (README §5)', () => {
    expect(errorCopy('NOT_SERVICE_DAY')).toBe('You can check in only on the day of the appointment.')
    expect(errorCopy('NO_SHOW_BEFORE_START')).toBe('You can mark a no-show only after the start time.')
    expect(errorCopy('NEEDS_RESCHEDULE')).toBe(
      'This booking no longer has a bay or technician. Reschedule it before checking in.',
    )
    expect(errorCopy('INVALID_STATUS_TRANSITION', { status: 'completed' })).toBe(
      'This appointment is completed and can no longer be changed this way.',
    )
  })

  it('falls back to internal error for unknown codes', () => {
    expect(errorCopy('UNKNOWN_CODE')).toBe('Something went wrong. Please try again.')
    expect(errorCopy(undefined)).toBe('Something went wrong. Please try again.')
  })
})

