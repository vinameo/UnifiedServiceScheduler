import type { MantineColor } from '@mantine/core'

export const CLOSED_STATUSES = ['COMPLETED', 'CANCELLED', 'NO_SHOW', 'EXPIRED'] as const

export function isClosed(status: string): boolean {
  return CLOSED_STATUSES.includes(status as (typeof CLOSED_STATUSES)[number])
}

export type AppointmentStatusType =
  | 'HELD'
  | 'CONFIRMED'
  | 'CHECKED_IN'
  | 'IN_PROGRESS'
  | 'COMPLETED'
  | 'CANCELLED'
  | 'NO_SHOW'
  | 'EXPIRED'

export interface StatusMeta {
  color: MantineColor
  label: string
}

const STATUS_CONFIG: Record<AppointmentStatusType, StatusMeta> = {
  HELD: { color: 'yellow', label: 'On hold' },
  CONFIRMED: { color: 'blue', label: 'Confirmed' },
  CHECKED_IN: { color: 'cyan', label: 'Checked in' },
  IN_PROGRESS: { color: 'indigo', label: 'In progress' },
  COMPLETED: { color: 'green', label: 'Completed' },
  CANCELLED: { color: 'gray', label: 'Cancelled' },
  NO_SHOW: { color: 'red', label: 'No-show' },
  EXPIRED: { color: 'gray', label: 'Expired' },
}

export function statusMeta(status: AppointmentStatusType | string): StatusMeta {
  return STATUS_CONFIG[status as AppointmentStatusType] ?? { color: 'gray', label: status }
}
