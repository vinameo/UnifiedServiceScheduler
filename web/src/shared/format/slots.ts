export interface Slot {
  time: string
  iso: string
}

export interface DayOpeningHours {
  opensAt: string
  closesAt: string
}

/**
 * Returns slots (start times HH:mm from opening to closes - duration) with dealership's offset.
 * Conforms to WEB-02.2.
 */
export function localSlotsForDay(
  date: string,
  openingHours: DayOpeningHours | null | undefined,
  durationMinutes: number,
  step = 15,
  timeZone = 'Asia/Ho_Chi_Minh',
): Slot[] {
  if (!openingHours || !openingHours.opensAt || !openingHours.closesAt) {
    return []
  }

  const parseMinutes = (timeStr: string): number => {
    const [hh, mm] = timeStr.split(':').map(Number)
    return hh * 60 + mm
  }

  const openMinutes = parseMinutes(openingHours.opensAt)
  const closeMinutes = parseMinutes(openingHours.closesAt)
  const latestStartMinutes = closeMinutes - durationMinutes

  if (latestStartMinutes < openMinutes) {
    return []
  }

  const probeDate = new Date(`${date}T12:00:00Z`)
  const formatter = new Intl.DateTimeFormat('en-GB', {
    timeZone,
    timeZoneName: 'longOffset',
  })
  const parts = formatter.formatToParts(probeDate)
  const tzPart = parts.find((p) => p.type === 'timeZoneName')?.value ?? 'GMT+07:00'
  const offset = tzPart.replace(/^GMT/, '') || '+00:00'

  const slots: Slot[] = []
  for (let m = openMinutes; m <= latestStartMinutes; m += step) {
    const hh = String(Math.floor(m / 60)).padStart(2, '0')
    const mm = String(m % 60).padStart(2, '0')
    const time = `${hh}:${mm}`
    const iso = `${date}T${time}:00${offset}`
    slots.push({ time, iso })
  }

  return slots
}

