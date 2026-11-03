import { Button, Group, Stack, Text } from '@mantine/core'
import { formatInZone, todayInZone } from '../format/time'

export interface AlternativeSlot {
  start: string
  end: string
}

export interface AlternativeChipsProps {
  alternatives?: AlternativeSlot[]
  timeZone: string
  onPick: (startIso: string) => void
  referenceDate?: string
}

function formatAlternativeChipLabel(
  startIso: string,
  timeZone: string,
  referenceDate?: string,
): string {
  const time = formatInZone(startIso, timeZone, 'time')
  const slotDate = todayInZone(timeZone, new Date(startIso))
  const today = todayInZone(timeZone)
  const normalizedRefDate = referenceDate
    ? (referenceDate.includes('T') ? todayInZone(timeZone, new Date(referenceDate)) : referenceDate)
    : today

  if (slotDate === today) {
    return `${time} today`
  }
  if (slotDate === normalizedRefDate) {
    return time
  }
  return `${time} ${formatInZone(startIso, timeZone, 'dayShort')}`
}

export function AlternativeChips({
  alternatives = [],
  timeZone,
  onPick,
  referenceDate,
}: AlternativeChipsProps) {
  return (
    <Stack gap="xs">
      <Text size="xs" fw={600}>
        Nearest available slots
      </Text>
      {alternatives.length > 0 ? (
        <Group gap="xs">
          {alternatives.map((alt) => {
            const label = formatAlternativeChipLabel(alt.start, timeZone, referenceDate)
            return (
              <Button
                key={alt.start}
                size="xs"
                variant="default"
                onClick={() => onPick(alt.start)}
              >
                {label}
              </Button>
            )
          })}
        </Group>
      ) : (
        <Text size="xs" c="dimmed">
          No free time nearby. Pick another day.
        </Text>
      )}
    </Stack>
  )
}
