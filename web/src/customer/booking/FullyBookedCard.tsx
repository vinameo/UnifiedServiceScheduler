import { Button, Card, Skeleton, Stack, Text } from '@mantine/core'
import { useNextAvailable } from '../../shared/api/availability'
import { formatInZone } from '../../shared/format/time'

export interface FullyBookedCardProps {
  dealershipId: string
  serviceTypeId: string
  serviceName: string
  phone?: string | null
  timeZone: string
  date: string
  after?: string
  onPick: (slotIso: string) => void
}

function resolveAfterIso(date: string, timeZone: string): string {
  if (date.includes('T') && (date.includes('Z') || date.includes('+') || (date.includes('-') && date.indexOf('-') > 10))) {
    return date
  }
  const dateOnly = date.slice(0, 10)
  const probeDate = new Date(`${dateOnly}T12:00:00Z`)
  const formatter = new Intl.DateTimeFormat('en-GB', {
    timeZone,
    timeZoneName: 'longOffset',
  })
  const parts = formatter.formatToParts(probeDate)
  const tzPart = parts.find((p) => p.type === 'timeZoneName')?.value ?? 'GMT+07:00'
  const offset = tzPart.replace(/^GMT/, '') || '+00:00'
  return `${dateOnly}T00:00:00${offset}`
}

export function FullyBookedCard({
  dealershipId,
  serviceTypeId,
  serviceName,
  phone,
  timeZone,
  date,
  after,
  onPick,
}: FullyBookedCardProps) {
  const effectiveAfter = after ?? resolveAfterIso(date, timeZone)

  const { data, isLoading } = useNextAvailable({
    dealershipId,
    serviceTypeId,
    after: effectiveAfter,
    enabled: Boolean(dealershipId && serviceTypeId),
  })

  const nextSlot = data?.nextAvailable

  return (
    <Card withBorder padding="lg" radius="md">
      <Stack align="center" gap="sm" ta="center">
        <Text fw={600} size="md">
          This day is fully booked
        </Text>

        {isLoading ? (
          <Skeleton height={36} width={160} radius="sm" />
        ) : nextSlot ? (
          <>
            <Text size="sm" c="dimmed">
              Next available slot for {serviceName}:
            </Text>
            <Button
              variant="default"
              size="sm"
              onClick={() => onPick(nextSlot.start)}
            >
              {formatInZone(nextSlot.start, timeZone, 'dayShort')} · {formatInZone(nextSlot.start, timeZone, 'time')}
            </Button>
          </>
        ) : (
          <Text size="sm" c="dimmed">
            No free time in the next 7 days. Call {phone || 'the dealership'}.
          </Text>
        )}
      </Stack>
    </Card>
  )
}
