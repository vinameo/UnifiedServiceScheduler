export interface OpeningHourRow {
  weekday: number
  opensAt?: string | null
  closesAt?: string | null
}

export interface GroupWeekdaysOptions {
  format?: 'short' | 'long'
  includeClosed?: boolean
}

export interface GroupedOpeningHours {
  label: string
  days: string
  labelLong: string
  daysLong: string
  labelShort: string
  daysShort: string
  hours: string
  opensAt?: string
  closesAt?: string
  isClosed: boolean
  weekdays: number[]
}

const SHORT_NAMES: Record<number, string> = {
  1: 'Mon',
  2: 'Tue',
  3: 'Wed',
  4: 'Thu',
  5: 'Fri',
  6: 'Sat',
  7: 'Sun',
}

const LONG_NAMES: Record<number, string> = {
  1: 'Monday',
  2: 'Tuesday',
  3: 'Wednesday',
  4: 'Thursday',
  5: 'Friday',
  6: 'Saturday',
  7: 'Sunday',
}

function normalizeTime(time?: string | null): string {
  if (!time) return ''
  const trimmed = time.trim()
  if (trimmed.length === 2) {
    return `${trimmed}:00`
  }
  return trimmed.slice(0, 5)
}

export function formatWeekdays(weekdays: number[], format: 'short' | 'long' = 'short'): string {
  if (weekdays.length === 0) return ''

  const sorted = Array.from(new Set(weekdays)).sort((a, b) => a - b)
  const segments: number[][] = []
  let currentSegment: number[] = [sorted[0]]

  for (let i = 1; i < sorted.length; i++) {
    const prev = sorted[i - 1]
    const curr = sorted[i]
    if (curr === prev + 1) {
      currentSegment.push(curr)
    } else {
      segments.push(currentSegment)
      currentSegment = [curr]
    }
  }
  segments.push(currentSegment)

  const names = format === 'long' ? LONG_NAMES : SHORT_NAMES
  const connector = format === 'long' ? ' to ' : '–'

  return segments
    .map((seg) => {
      if (seg.length === 1) {
        return names[seg[0]] ?? ''
      }
      return `${names[seg[0]]}${connector}${names[seg[seg.length - 1]]}`
    })
    .join(', ')
}

export function formatTimezoneOffset(timeZone: string, date: Date = new Date()): string {
  try {
    const parts = new Intl.DateTimeFormat('en-US', {
      timeZone,
      timeZoneName: 'shortOffset',
    }).formatToParts(date)
    const tzPart = parts.find((p) => p.type === 'timeZoneName')?.value ?? ''
    return tzPart.replace('GMT', 'UTC')
  } catch {
    return 'UTC'
  }
}

export function groupWeekdays(
  rows: OpeningHourRow[] = [],
  options: GroupWeekdaysOptions = {},
): GroupedOpeningHours[] {
  const { format = 'short', includeClosed = false } = options

  const openDaysMap = new Map<number, { opensAt: string; closesAt: string }>()
  for (const row of rows) {
    const open = normalizeTime(row.opensAt)
    const close = normalizeTime(row.closesAt)
    if (open && close) {
      openDaysMap.set(row.weekday, { opensAt: open, closesAt: close })
    }
  }

  const groupsByHoursKey = new Map<
    string,
    {
      weekdays: number[]
      opensAt?: string
      closesAt?: string
      isClosed: boolean
    }
  >()

  const allWeekdays = includeClosed ? [1, 2, 3, 4, 5, 6, 7] : rows.map((r) => r.weekday)
  const uniqueWeekdays = Array.from(new Set(allWeekdays)).sort((a, b) => a - b)

  for (const weekday of uniqueWeekdays) {
    const openData = openDaysMap.get(weekday)
    if (openData) {
      const key = `${openData.opensAt}–${openData.closesAt}`
      if (!groupsByHoursKey.has(key)) {
        groupsByHoursKey.set(key, {
          weekdays: [],
          opensAt: openData.opensAt,
          closesAt: openData.closesAt,
          isClosed: false,
        })
      }
      groupsByHoursKey.get(key)!.weekdays.push(weekday)
    } else {
      const key = 'closed'
      if (!groupsByHoursKey.has(key)) {
        groupsByHoursKey.set(key, {
          weekdays: [],
          isClosed: true,
        })
      }
      groupsByHoursKey.get(key)!.weekdays.push(weekday)
    }
  }

  const result: GroupedOpeningHours[] = []

  for (const [, group] of groupsByHoursKey.entries()) {
    const sortedDays = group.weekdays.sort((a, b) => a - b)
    const shortLabel = formatWeekdays(sortedDays, 'short')
    const longLabel = formatWeekdays(sortedDays, 'long')
    const activeLabel = format === 'long' ? longLabel : shortLabel
    const hours = group.isClosed ? 'Closed' : `${group.opensAt}–${group.closesAt}`

    result.push({
      label: activeLabel,
      days: activeLabel,
      labelLong: longLabel,
      daysLong: longLabel,
      labelShort: shortLabel,
      daysShort: shortLabel,
      hours,
      opensAt: group.opensAt,
      closesAt: group.closesAt,
      isClosed: group.isClosed,
      weekdays: sortedDays,
    })
  }

  // Sort groups by their earliest weekday
  result.sort((a, b) => a.weekdays[0] - b.weekdays[0])

  return result
}
