/**
 * Masks phone number in the middle: first 4 digits + *** + last 3 digits.
 * Conforms to WEB-02.3 and D-02.13.
 * Example: '0903123218' -> '0903 *** 218'.
 */
export function maskPhone(phone: string | null | undefined): string {
  if (!phone) {
    return ''
  }

  const trimmed = phone.trim()
  if (trimmed.length < 7) {
    return trimmed
  }

  const prefix = trimmed.slice(0, 4)
  const suffix = trimmed.slice(-3)
  return `${prefix} *** ${suffix}`
}

