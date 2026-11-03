export function safeReturnTo(value: string | null | undefined, fallback: string = '/staff'): string {
  if (!value) return fallback
  if (value.startsWith('/') && !value.startsWith('//') && !value.startsWith('/\\')) {
    return value
  }
  return fallback
}

