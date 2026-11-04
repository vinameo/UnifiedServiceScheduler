export interface AppointmentDiffInput {
  status?: string
  start?: string
  end?: string
  startAt?: string
  endAt?: string
  technician?: { id?: string; name?: string } | string | null
  bay?: { id?: string; code?: string } | string | null
  vehicle?:
    | { id?: string; licencePlate?: string; vin?: string; make?: string; model?: string }
    | string
    | null
  version?: number
  [key: string]: unknown
}

export interface AppointmentFieldDiff {
  field: 'status' | 'time' | 'technician' | 'bay' | 'vehicle'
  label: string
  from: string
  to: string
  text: string
}

export function formatStatus(status: string | undefined): string {
  if (!status) return ''
  switch (status.toUpperCase()) {
    case 'CONFIRMED':
      return 'Confirmed'
    case 'CANCELLED':
      return 'Cancelled'
    case 'HELD':
      return 'Held'
    case 'COMPLETED':
      return 'Completed'
    case 'IN_PROGRESS':
      return 'In progress'
    case 'IN_SERVICE':
      return 'In service'
    case 'CHECKED_IN':
      return 'Checked in'
    case 'NO_SHOW':
      return 'No show'
    case 'EXPIRED':
      return 'Expired'
    default:
      return status.charAt(0).toUpperCase() + status.slice(1).toLowerCase()
  }
}

function extractTime(input: AppointmentDiffInput): string {
  const start = input.start ?? input.startAt ?? ''
  const end = input.end ?? input.endAt ?? ''
  if (!start && !end) return ''
  if (start && !end) return formatTimeString(start)
  return `${formatTimeString(start)} - ${formatTimeString(end)}`
}

function formatTimeString(isoOrTime: string): string {
  if (isoOrTime.includes('T')) {
    const timePart = isoOrTime.split('T')[1] ?? ''
    return timePart.slice(0, 5)
  }
  return isoOrTime
}

function extractTechnician(input: AppointmentDiffInput): string {
  if (!input.technician) return ''
  if (typeof input.technician === 'string') return input.technician
  return input.technician.name ?? ''
}

function extractBay(input: AppointmentDiffInput): string {
  if (!input.bay) return ''
  if (typeof input.bay === 'string') return input.bay
  return input.bay.code ?? ''
}

function extractVehicle(input: AppointmentDiffInput): string {
  if (!input.vehicle) return ''
  if (typeof input.vehicle === 'string') return input.vehicle
  if (input.vehicle.licencePlate) return input.vehicle.licencePlate
  if (input.vehicle.vin) return input.vehicle.vin
  const makeModel = `${input.vehicle.make ?? ''} ${input.vehicle.model ?? ''}`.trim()
  return makeModel
}

/**
 * Pure function comparing before and latest appointment data (WEB-04.6).
 * Lists differences strictly in order: status, time, technician, bay, vehicle.
 */
export function diffAppointment(
  before: AppointmentDiffInput,
  latest: AppointmentDiffInput,
): AppointmentFieldDiff[] {
  const diffs: AppointmentFieldDiff[] = []

  // 1. Status
  const fromStatus = formatStatus(before.status)
  const toStatus = formatStatus(latest.status)
  if (fromStatus !== toStatus && (fromStatus || toStatus)) {
    diffs.push({
      field: 'status',
      label: 'Status',
      from: fromStatus,
      to: toStatus,
      text: `Status: ${fromStatus} → ${toStatus}`,
    })
  }

  // 2. Time
  const fromTime = extractTime(before)
  const toTime = extractTime(latest)
  if (fromTime !== toTime && (fromTime || toTime)) {
    diffs.push({
      field: 'time',
      label: 'Time',
      from: fromTime,
      to: toTime,
      text: `Time: ${fromTime} → ${toTime}`,
    })
  }

  // 3. Technician
  const fromTech = extractTechnician(before)
  const toTech = extractTechnician(latest)
  if (fromTech !== toTech && (fromTech || toTech)) {
    diffs.push({
      field: 'technician',
      label: 'Technician',
      from: fromTech,
      to: toTech,
      text: `Technician: ${fromTech} → ${toTech}`,
    })
  }

  // 4. Bay
  const fromBay = extractBay(before)
  const toBay = extractBay(latest)
  if (fromBay !== toBay && (fromBay || toBay)) {
    diffs.push({
      field: 'bay',
      label: 'Bay',
      from: fromBay,
      to: toBay,
      text: `Bay: ${fromBay} → ${toBay}`,
    })
  }

  // 5. Vehicle
  const fromVehicle = extractVehicle(before)
  const toVehicle = extractVehicle(latest)
  if (fromVehicle !== toVehicle && (fromVehicle || toVehicle)) {
    diffs.push({
      field: 'vehicle',
      label: 'Vehicle',
      from: fromVehicle,
      to: toVehicle,
      text: `Vehicle: ${fromVehicle} → ${toVehicle}`,
    })
  }

  return diffs
}

