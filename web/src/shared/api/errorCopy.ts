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

