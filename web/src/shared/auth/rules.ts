export const SIGN_UP_COPY = {
  badEmail: 'Enter a valid email address.',
  passwordHint: 'At least 8 characters, with a letter and a number.',
  passwordTooLong: 'Password must not be greater than 72 bytes.',
  mismatch: 'Passwords do not match.',
  badPhone: 'Enter a valid phone number.',
  badFullName: 'Enter a valid full name (2–100 characters).',
}

export function normalizePhone(phone: string): string {
  const hasLeadingPlus = phone.trim().startsWith('+')
  const digits = phone.replace(/\D/g, '')
  return (hasLeadingPlus ? '+' : '') + digits
}

export function validateEmail(email: unknown): { valid: boolean; error?: string } {
  if (typeof email !== 'string') {
    return { valid: false, error: SIGN_UP_COPY.badEmail }
  }

  const trimmed = email.trim()

  // ASCII only (D-00.13)
  if (!/^[\x21-\x7E]+$/.test(trimmed)) {
    return { valid: false, error: SIGN_UP_COPY.badEmail }
  }

  if (trimmed.length > 254) {
    return { valid: false, error: SIGN_UP_COPY.badEmail }
  }

  // RFC basic check (contains @, domain with dot)
  const emailRegex = /^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/
  if (!emailRegex.test(trimmed)) {
    return { valid: false, error: SIGN_UP_COPY.badEmail }
  }

  return { valid: true }
}

export function validatePassword(password: unknown): {
  valid: boolean
  code?: 'VALIDATION_ERROR' | 'WEAK_PASSWORD'
  error?: string
} {
  if (typeof password !== 'string' || password.length === 0) {
    return { valid: false, code: 'VALIDATION_ERROR', error: 'Password is required.' }
  }

  const byteLength = new TextEncoder().encode(password).length
  if (byteLength > 72) {
    return { valid: false, code: 'VALIDATION_ERROR', error: SIGN_UP_COPY.passwordTooLong }
  }

  // Strength check: at least 8 characters, letter and number (D-00.4)
  if (password.length < 8 || !/[A-Za-z]/.test(password) || !/[0-9]/.test(password)) {
    return { valid: false, code: 'WEAK_PASSWORD', error: SIGN_UP_COPY.passwordHint }
  }

  return { valid: true }
}

export function validatePasswordConfirmation(
  password: string,
  confirmation: unknown
): { valid: boolean; error?: string } {
  if (typeof confirmation !== 'string' || confirmation !== password) {
    return { valid: false, error: SIGN_UP_COPY.mismatch }
  }
  return { valid: true }
}

export function validateFullName(name: unknown): { valid: boolean; error?: string } {
  if (typeof name !== 'string') {
    return { valid: false, error: SIGN_UP_COPY.badFullName }
  }

  const trimmed = name.trim()
  if (trimmed.length < 2 || trimmed.length > 100) {
    return { valid: false, error: SIGN_UP_COPY.badFullName }
  }

  return { valid: true }
}

export function validatePhone(phone: unknown): {
  valid: boolean
  error?: string
  normalized?: string
} {
  if (typeof phone !== 'string') {
    return { valid: false, error: SIGN_UP_COPY.badPhone }
  }

  // Allowed characters: digits, spaces, +, -, ., (, )
  // Leading + allowed, but not in the middle
  if (!/^\+?[0-9\s\-.()]+$/.test(phone)) {
    return { valid: false, error: SIGN_UP_COPY.badPhone }
  }

  const normalized = normalizePhone(phone)
  const digits = normalized.replace(/\D/g, '')

  if (digits.length < 9 || digits.length > 15) {
    return { valid: false, error: SIGN_UP_COPY.badPhone }
  }

  return { valid: true, normalized }
}
