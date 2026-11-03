const ERROR_MESSAGES: Record<string, string> = {
  VALIDATION_ERROR: 'Check the highlighted fields.',
  IDEMPOTENCY_KEY_REQUIRED: 'Something went wrong with this request. Please try again.',
  UNAUTHENTICATED: 'Please sign in to continue.',
  INVALID_CREDENTIALS: 'Email or password is incorrect.',
  FORBIDDEN: "You don't have permission to view this page.",
  DEALERSHIP_NOT_FOUND: 'Dealership not found.',
  TECHNICIAN_NOT_FOUND: 'Technician not found.',
  CSRF_TOKEN_MISMATCH: 'The page has expired. Please reload and try again.',
  EMAIL_ALREADY_USED: 'An account with this email already exists. Sign in instead.',
  WEAK_PASSWORD: 'Password must be at least 8 characters, with a letter and a number.',
  TOO_MANY_ATTEMPTS: 'Too many attempts. Please try again later.',
  INTERNAL_ERROR: 'Something went wrong. Please try again.',
}

export function errorCopy(code: string | undefined): string {
  if (!code) {
    return ERROR_MESSAGES.INTERNAL_ERROR
  }
  return ERROR_MESSAGES[code] ?? ERROR_MESSAGES.INTERNAL_ERROR
}

export interface NoAvailabilityContext {
  time?: string
  start?: string
  end?: string
  bayTypeLabel?: string
}

export interface NoAvailabilityCopy {
  heading: string
  body: string
}

export function noAvailabilityReasonCopy(
  reason: string | undefined,
  context: NoAvailabilityContext = {},
): NoAvailabilityCopy {
  const time = context.time ?? context.start ?? ''
  const start = context.start ?? ''
  const end = context.end ?? ''
  const bayType = context.bayTypeLabel ?? ''

  switch (reason) {
    case 'NO_QUALIFIED_TECHNICIAN':
      return {
        heading: "This service can't be booked right now",
        body: 'No technician at this dealership can do this service right now. Call the dealership to arrange it.',
      }
    case 'NO_TECHNICIAN_AVAILABLE':
      return {
        heading: `The ${time} slot was just taken`,
        body: `No qualified technician is free from ${start} to ${end}. Your appointment was not created.`,
      }
    case 'NO_BAY_AVAILABLE':
      return {
        heading: `The ${time} slot was just taken`,
        body: `No ${bayType} bay is free from ${start} to ${end}. Your appointment was not created.`,
      }
    case 'SLOT_TAKEN':
    default:
      return {
        heading: `The ${time} slot was just taken`,
        body: 'This time was just taken by another booking. Your appointment was not created.',
      }
  }
}

