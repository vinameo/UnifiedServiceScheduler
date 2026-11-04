/**
 * Time formatting helpers formatted in a specific timezone (never browser timezone).
 * Conforms to WEB-01.5 and WEB-02.1.
 */

export type FormatPattern = 'time' | 'timeWithSeconds' | 'range' | 'dayLong' | 'dayShort'

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

  if (pattern === 'timeWithSeconds') {
    return new Intl.DateTimeFormat('en-GB', {
      timeZone,
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
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

export function todayInZone(timeZone: string, now: Date = new Date()): string {
  return new Intl.DateTimeFormat('en-CA', {
    timeZone,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).format(now)
}

export function formatAppointmentSlot(startIso: string, endIso: string, timeZone: string): string {
  const day = formatInZone(startIso, timeZone, 'dayShort')
  const range = formatInZone(startIso, timeZone, 'range', endIso)
  return `${day} · ${range}`
}

export function formatDateTime(iso: string, timeZone: string): string {
  const date = new Date(iso)
  const parts = new Intl.DateTimeFormat('en-GB', {
    timeZone,
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  }).formatToParts(date)

  const find = (type: string) => parts.find((p) => p.type === type)?.value ?? ''
  return `${find('day')}/${find('month')}/${find('year')} ${find('hour')}:${find('minute')}`
}

export function formatShortDateTime(iso: string, timeZone: string): string {
  const date = new Date(iso)
  const parts = new Intl.DateTimeFormat('en-GB', {
    timeZone,
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  }).formatToParts(date)

  const find = (type: string) => parts.find((p) => p.type === type)?.value ?? ''
  return `${find('day')}/${find('month')} ${find('hour')}:${find('minute')}`
}

export function getTimezoneOffsetLabel(timeZone: string, date: Date = new Date()): string {
  try {
    const parts = new Intl.DateTimeFormat('en-GB', {
      timeZone,
      timeZoneName: 'shortOffset',
    }).formatToParts(date)
    return parts.find((p) => p.type === 'timeZoneName')?.value ?? timeZone
  } catch {
    return timeZone
  }
}

