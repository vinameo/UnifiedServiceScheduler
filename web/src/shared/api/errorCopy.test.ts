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

  it('falls back to internal error for unknown codes', () => {
    expect(errorCopy('UNKNOWN_CODE')).toBe('Something went wrong. Please try again.')
    expect(errorCopy(undefined)).toBe('Something went wrong. Please try again.')
  })
})
