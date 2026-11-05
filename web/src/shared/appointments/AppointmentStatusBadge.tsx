import { Badge } from '@mantine/core'
import { statusMeta, type AppointmentStatusType } from './status'

export type { AppointmentStatusType }

export interface AppointmentStatusBadgeProps {
  status: AppointmentStatusType | string
  size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl'
}

export function AppointmentStatusBadge({ status, size = 'sm' }: AppointmentStatusBadgeProps) {
  const config = statusMeta(status)

  return (
    <Badge color={config.color} size={size} variant="light">
      {config.label}
    </Badge>
  )
}

