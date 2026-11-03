import { Badge, type MantineColor } from '@mantine/core'

export type AppointmentStatusType =
  | 'HELD'
  | 'CONFIRMED'
  | 'CHECKED_IN'
  | 'IN_PROGRESS'
  | 'COMPLETED'
  | 'CANCELLED'
  | 'NO_SHOW'
  | 'EXPIRED'

interface StatusMeta {
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

export interface AppointmentStatusBadgeProps {
  status: AppointmentStatusType | string
  size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl'
}

export function AppointmentStatusBadge({ status, size = 'sm' }: AppointmentStatusBadgeProps) {
  const config = STATUS_CONFIG[status as AppointmentStatusType] ?? {
    color: 'gray',
    label: status,
  }

  return (
    <Badge color={config.color} size={size} variant="light">
      {config.label}
    </Badge>
  )
}

