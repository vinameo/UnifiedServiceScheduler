/**
 * Time formatting helpers formatted in a specific timezone (never browser timezone).
 * Conforms to WEB-01.5 and WEB-02.1.
 */

export type FormatPattern = 'time' | 'range' | 'dayLong' | 'dayShort'

export function formatInZone(
  iso: string,
  timeZone: string,
  pattern: FormatPattern,
  endIso?: string,
): string {
  const date = new Date(iso)

  if (pattern === 'time') {
    return new Intl.DateTimeFormat('en-GB', {
      timeZone,
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    }).format(date)
  }

  if (pattern === 'dayShort') {
    return new Intl.DateTimeFormat('en-GB', {
      timeZone,
      weekday: 'short',
      day: 'numeric',
      month: 'short',
    }).format(date)
  }

  if (pattern === 'dayLong') {
    return new Intl.DateTimeFormat('en-GB', {
      timeZone,
      weekday: 'long',
      day: 'numeric',
      month: 'short',
      year: 'numeric',
    }).format(date)
  }

  if (pattern === 'range' && endIso) {
    const endDate = new Date(endIso)
    const t1 = new Intl.DateTimeFormat('en-GB', {
      timeZone,
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    }).format(date)
    const t2 = new Intl.DateTimeFormat('en-GB', {
      timeZone,
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    }).format(endDate)
    return `${t1}–${t2}`
  }

  return ''
}

export function formatAppointmentSlot(startIso: string, endIso: string, timeZone: string): string {
  const day = formatInZone(startIso, timeZone, 'dayShort')
  const range = formatInZone(startIso, timeZone, 'range', endIso)
  return `${day} · ${range}`
}
