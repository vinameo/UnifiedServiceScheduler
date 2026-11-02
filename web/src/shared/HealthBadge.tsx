import { Badge } from '@mantine/core'
import { useHealth } from './api/health'

export function HealthBadge() {
  const { data, isPending, isError } = useHealth()

  if (isPending) return <Badge color="gray" variant="light">Checking system</Badge>
  if (isError || data?.status !== 'ok') return <Badge color="red" variant="light">System unavailable</Badge>

  return <Badge color="green" variant="light">System online</Badge>
}
